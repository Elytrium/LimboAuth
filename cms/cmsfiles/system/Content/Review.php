<?php
/**
 * @brief		Content Review Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		4 Nov 2013
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Content Review Model
 */
abstract class _Review extends \IPS\Content\Comment
{
	/**
	 * @brief	[Content\Comment]	Form Template
	 */
	public static $formTemplate = array( array( 'forms', 'core', 'front' ), 'reviewTemplate' );

	/**
	 * @brief	[Content\Comment]	Reviews Template
	 */
	public static $commentTemplate = array( array( 'global', 'core', 'front' ), 'reviewContainer' );

	/**
	 * Create first comment (created with content item)
	 *
	 * @param	\IPS\Content\Item		$item			The content item just created
	 * @param	string					$comment		The comment
	 * @param	bool					$first			Is the first comment?
	 * @param	int						$rating			The rating (1-5)
	 * @param	string					$guestName		If author is a guest, the name to use
	 * @param	\IPS\Member|NULL		$member			The author of this comment. If NULL, uses currently logged in member.
	 * @param	\IPS\DateTime|NULL		$time			The time
	 * @param	string|NULL				$ipAddress		The IP address or NULL to detect automatically
	 * @param	int|NULL				$hiddenStatus		NULL to set automatically or override: 0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @param	int|NULL				$anonymous			NULL for no value, 0 or 1 for a value (0=no, 1=yes)
	 * @return	static
	 * @throws	\InvalidArgumentException
	 */
	public static function create( $item, $comment, $first=FALSE, $rating=NULL, $guestName=NULL, $member=NULL, \IPS\DateTime $time=NULL, $ipAddress=NULL, $hiddenStatus=NULL, $anonymous=NULL )
	{
		if ( !\is_int( $rating ) or $rating < 1 or $rating > \intval( \IPS\Settings::i()->reviews_rating_out_of ) )
		{
			throw new \InvalidArgumentException;
		}

		$obj = parent::create( $item, $comment, $first, $guestName, NULL, $member, $time, $ipAddress, $hiddenStatus );
		
		foreach ( array( 'rating', 'votes_data' ) as $k )
		{
			if ( isset( static::$databaseColumnMap[ $k ] ) )
			{
				$val = NULL;
				switch ( $k )
				{
					case 'rating':
						$val = $rating;
						break;
					
					case 'votes_data':
						$val = json_encode( array() );
						break;
				}
				
				foreach ( \is_array( static::$databaseColumnMap[ $k ] ) ? static::$databaseColumnMap[ $k ] : array( static::$databaseColumnMap[ $k ] ) as $column )
				{
					$obj->$column = $val;
				}
			}
		}

		if( $obj->author()->member_id AND $obj::incrementPostCount() )
		{
			$obj->author()->member_last_post = time();
			$obj->author()->save();
		}

		$obj->save();

		/* Have to do these AFTER rating is set */
		$itemClass = static::$itemClass;
		$ratingField = $itemClass::$databaseColumnMap['rating'];
		
		$obj->item()->$ratingField = $obj->item()->averageReviewRating() ?: 0;
		$obj->item()->save();

		/* Send notifications and dish out points */
		if ( !$obj->hidden() and ( !$first or !$item::$firstCommentRequired ) )
		{
			$obj->sendNotifications();
			$obj->author()->achievementAction( 'core', 'Review', $obj );
		}
		else if( $obj->hidden() === 1 )
		{
			$obj->sendUnapprovedNotification();
		}
		
		return $obj;
	}
	
	/**
	 * Do stuff after creating (abstracted as comments and reviews need to do different things)
	 *
	 * @return	void
	 */
	public function postCreate()
	{		
		$item = $this->item();
		if ( !$this->hidden() )
		{
			if ( isset( $item::$databaseColumnMap['last_review'] ) )
			{
				$lastReviewField = $item::$databaseColumnMap['last_review'];
				if ( \is_array( $lastReviewField ) )
				{
					foreach ( $lastReviewField as $column )
					{
						$item->$column = time();
					}
				}
				else
				{
					$item->$lastReviewField = time();
				}
			}
			if ( isset( $item::$databaseColumnMap['last_review_by'] ) )
			{
				$lastReviewByField = $item::$databaseColumnMap['last_review_by'];
				$item->$lastReviewByField = $this->author()->member_id;
			}
			if ( isset( $item::$databaseColumnMap['last_review_name'] ) )
			{
				$lastReviewNameField = $item::$databaseColumnMap['last_review_name'];
				$item->$lastReviewNameField = $this->author()->name;
			}
			if ( isset( $item::$databaseColumnMap['num_reviews'] ) )
			{
				$numReviewsField = $item::$databaseColumnMap['num_reviews'];
				$item->$numReviewsField++;
			}
			
			if ( !$item->hidden() and $item->containerWrapper() and $item->container()->_reviews !== NULL )
			{
				$item->container()->_reviews = ( $item->container()->_reviews + 1 );
				$item->container()->setLastReview( $this );
				$item->container()->save();
			}
		}
		else
		{
			if ( isset( $item::$databaseColumnMap['unapproved_reviews'] ) )
			{
				$numReviewsField = $item::$databaseColumnMap['unapproved_reviews'];
				$item->$numReviewsField++;
			}
			if ( $item->containerWrapper() AND $item->container()->_unapprovedReviews !== NULL )
			{
				$item->container()->_unapprovedReviews = $item->container()->_unapprovedReviews + 1;
				$item->container()->save();
			}
		}
		
		$item->save();
		
		/* Was it moderated? Let's see why. */
		if ( $this->hidden() === 1 )
		{
			$idColumn = static::$databaseColumnId;
			
			/* Check we don't already have a reason from profanity / url / email filters */
			try
			{
				\IPS\core\Approval::loadFromContent( \get_called_class(), $this->$idColumn );
			}
			catch( \OutOfRangeException $e )
			{
				/* If the user is mod-queued - that's why. These will cascade, so check in that order. */
				$foundReason = FALSE;
				$log = new \IPS\core\Approval;
				$log->content_class	= \get_called_class();
				$log->content_id	= $this->$idColumn;
				if ( $this->author()->mod_posts )
				{
					
					$log->held_reason	= 'user';
					$foundReason = TRUE;
				}
				
				/* If the user isn't mod queued, but is in a group that is, that's why. */
				if ( $foundReason === FALSE AND $this->author()->group['g_mod_preview'] )
				{
					$log->held_reason	= 'group';
					$foundReason = TRUE;
				}
				
				/* If the user isn't on mod queue, but the container requires approval, that's why. */
				if ( $foundReason === FALSE )
				{
					try
					{
						if ( $item->container() AND $item->container()->contentHeldForApprovalByNode( 'review', $this->author() ) === TRUE )
						{
							$log->held_reason = 'node';
							$foundReason = TRUE;
						}
					}
					catch( \BadMethodCallException $e ) { }
				}
				
				/* Finally if the item itself requires moderation, that's why */
				if ( $foundReason === FALSE AND \is_array( $item::supportedMetaDataTypes() ) AND \in_array('core_ItemModeration', $item::supportedMetaDataTypes() ) AND (bool) \IPS\Application::load('core')->extensions( 'core', 'MetaData' )['ItemModeration']->enabled( $item, $this->author() ) )
				{
					$log->held_reason = 'item';
					$foundReason = TRUE;
				}
				
				if ( $foundReason )
				{
					$log->save();
				}
			}
		}
	}

	/**
	 * @brief	Value to set for the 'tab' parameter when redirecting to the comment (via _find())
	 */
	public static $tabParameter	= array( 'tab' => 'reviews' );

	/**
	 * Get URL
	 *
	 * @param	string|NULL		$action		Action
	 * @return	\IPS\Http\Url
	 */
	public function url( $action='find' )
	{
		$idColumn = static::$databaseColumnId;

		return $this->item()->url()->setQueryString( array(
			'do'		=> $action . 'Review',
			'review'	=> $this->$idColumn
		) );
	}
	
	/**
	 * Get line which says how many users found review helpful
	 *
	 * @return	string
	 */
	public function helpfulLine()
	{
		return \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->reviewHelpful( $this->mapped('votes_helpful'), $this->mapped('votes_total') );
	}
	
	/**
	 * Edit and existing rating
	 *
	 * @param	int		$rating		The new rating
	 * @return void
	 */
	public function editRating( $rating )
	{
		/* Review */
		$ratingField = static::$databaseColumnMap['rating'];
		$this->$ratingField = $rating;
		$this->save();
		
		/* Item */
		$item = $this->item();
		$ratingField = $item::$databaseColumnMap['rating'];
		$item->$ratingField = $item->averageReviewRating() ?: 0; 

		$item->resyncLastReview();
		$item->save();
	}
	
	/**
	 * Syncing to run when hiding
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onHide( $member )
	{
		$item = $this->item();
		if ( $item->container()->_reviews !== NULL )
		{
			$item->container()->_reviews = $item->container()->_reviews - 1;
			$item->container()->setLastReview();
			$item->container()->save();
		}
		if ( isset( $item::$databaseColumnMap['hidden_reviews'] ) )
		{
			$column = $item::$databaseColumnMap['hidden_reviews'];
			$item->$column = $item->mapped('hidden_reviews') + 1;
		}
		
		if ( isset( $item::$databaseColumnMap['num_reviews'] ) )
		{
			$column = $item::$databaseColumnMap['num_reviews'];
			$item->$column = $item->mapped('num_reviews') - 1;
		}

		$ratingField = $item::$databaseColumnMap['rating'];
		$item->$ratingField = $item->averageReviewRating() ?: 0; 

		$item->resyncLastReview();
		$item->save();
	}
	
	/**
	 * Syncing to run when unhiding
	 *
	 * @param	bool					$approving	If true, is being approved for the first time
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onUnhide( $approving, $member )
	{
		$item = $this->item();

		if ( $approving )
		{
			if ( isset( $item::$databaseColumnMap['unapproved_reviews'] ) )
			{
				$column = $item::$databaseColumnMap['unapproved_reviews'];
				$item->$column = $item->mapped('unapproved_reviews') - 1;
			}
			if ( $item->container()->_unapprovedReviews !== NULL )
			{
				$item->container()->_unapprovedReviews = $item->container()->_unapprovedReviews - 1;
				$item->container()->setLastReview();
				$item->container()->save();
			}
		}
		else if ( isset( $item::$databaseColumnMap['hidden_reviews'] ) )
		{
			$column = $item::$databaseColumnMap['hidden_reviews'];
			if ( $item->mapped('hidden_reviews') > 0 )
			{
				$item->$column = $item->mapped('hidden_reviews') - 1;
			}
		}

		if ( isset( $item::$databaseColumnMap['num_reviews'] ) )
		{
			$column = $item::$databaseColumnMap['num_reviews'];
			$item->$column = $item->mapped('num_reviews') + 1;
		}
		if ( $item->container()->_reviews !== NULL )
		{
			$item->container()->_reviews = $item->container()->_reviews + 1;
			$item->container()->setLastReview();
			$item->container()->save();
		}
		
		$ratingField = $item::$databaseColumnMap['rating'];
		$item->$ratingField = $item->averageReviewRating() ?: 0; 
		
		$item->resyncLastReview();
		$item->save();
	}
	
	/**
	 * Can split this comment off?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canSplit( $member=NULL )
	{
		return FALSE;
	}

	/**
	 * Can view?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for or NULL for the currently logged in member
	 * @return	bool
	 */
	public function canView( $member=NULL )
	{
		if( $member === NULL )
		{
			$member	= \IPS\Member::loggedIn();
		}

		if ( $this instanceof \IPS\Content\Hideable and $this->hidden() and !$this->item()->canViewHiddenReviews( $member ) and ( $this->hidden() !== 1 or $this->author() !== $member ) )
		{
			return FALSE;
		}

		return $this->item()->canView( $member );
	}
	
	/**
	 * Warning Reference Key
	 *
	 * @return	string
	 */
	public function warningRef()
	{
		/* If the member cannot warn, return NULL so we're not adding ugly parameters to the profile URL unnecessarily */
		if ( !\IPS\Member::loggedIn()->modPermission('mod_can_warn') )
		{
			return NULL;
		}
		
		$itemClass = static::$itemClass;
		$idColumn = static::$databaseColumnId;
		return base64_encode( json_encode( array( 'app' => $itemClass::$application, 'module' => $itemClass::$module . '-review' , 'id_1' => $this->mapped('item'), 'id_2' => $this->$idColumn ) ) );
	}
	
	/**
	 * Get attachment IDs
	 *
	 * @return	array
	 */
	public function attachmentIds()
	{
		$item = $this->item();
		$idColumn = $item::$databaseColumnId;
		$commentIdColumn = static::$databaseColumnId;
		return array( $this->item()->$idColumn, $this->$commentIdColumn, 'review' ); 
	}
	
	/**
	 * Create Notification
	 *
	 * @param	string|NULL		$extra		Additional data
	 * @return	\IPS\Notification
	 */
	protected function createNotification( $extra=NULL )
	{
		return new \IPS\Notification( \IPS\Application::load( 'core' ), 'new_review', $this->item(), array( $this ) );
	}
	
	/**
	 * Delete Review
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();
		$this->_recalculateRating();
	}

	/**
	 * Log for deletion later
	 *
	 * @param	\IPS\Member|NULL 	$member	The member, NULL for currently logged in, or FALSE for no member
	 * @return	void
	 */
	public function logDelete( $member = NULL )
	{
		parent::logDelete( $member );
		$this->_recalculateRating();
	}

	/**
	 * Restore Content
	 *
	 * @param	bool	$hidden	Restore as hidden?
	 * @return	void
	 */
	public function restore( $hidden = FALSE )
	{
		parent::restore( $hidden );
		$this->_recalculateRating();
	}

	/**
	 * Recalculate rating after review action
	 *
	 * @return void
	 */
	protected function _recalculateRating()
	{
		$itemClass = static::$itemClass;
		$ratingField = $itemClass::$databaseColumnMap['rating'];
		$this->item()->$ratingField = $this->item()->averageReviewRating() ?: 0;
		$this->item()->save();
	}
	
	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse	int			id				ID number
	 * @apiresponse	int			item			The ID number of the item this belongs to
	 * @apiresponse	\IPS\Member	author			Author
	 * @apiresponse	datetime	date			Date
	 * @apiresponse	int			rating			The number of stars this review gave
	 * @apiresponse	int			votesTotal		The number of users that have voted if this review was helpful or unhelpful
	 * @apiresponse	int			votesHelpful	The number of users that voted helpful
	 * @apiresponse	string		content			The content
	 * @apiresponse	bool		hidden			Is hidden?
	 * @apiresponse	string		url				URL to content
	 * @apiresponse	string|NULL	authorResponse	The content item's author's response to the review, if any
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		$idColumn = static::$databaseColumnId;
		$itemColumn = static::$databaseColumnMap['item'];
		return array(
			'id'				=> $this->$idColumn,
			'item_id'			=> $this->$itemColumn,
			'author'			=> $this->author()->apiOutput( $authorizedMember ),
			'date'				=> \IPS\DateTime::ts( $this->mapped('date') )->rfc3339(),
			'rating'			=> $this->mapped('rating'),
			'votesTotal'		=> $this->mapped('votes_total'),
			'votesHelpful'		=> $this->mapped('votes_helpful'),
			'content'			=> \IPS\Text\Parser::removeLazyLoad( $this->content() ),
			'hidden'			=> (bool) $this->hidden(),
			'url'				=> (string) $this->url(),
			'authorResponse'	=> $this->mapped('author_response')
		);
	}
	
	/* !Embeddable */
	
	/**
	 * Get content for embed
	 *
	 * @param	array	$params	Additional parameters to add to URL
	 * @return	string
	 */
	public function embedContent( $params )
	{
		return \IPS\Theme::i()->getTemplate( 'global', 'core' )->embedReview( $this->item(), $this, $this->url()->setQueryString( $params ), $this->item()->embedImage() );
	}

	/* !Author responses */

	/**
	 * Has the author responded to this review?
	 *
	 * @return bool
	 */
	public function hasAuthorResponse()
	{
		return !\is_null( $this->mapped('author_response') );
	}

	/**
	 * Can the specified user respond to the review?
	 *
	 * @note	Only the author of the content item can respond by default, but this is abstracted so third parties can override
	 * @param	\IPS\Member|NULL	$member	Member to check or NULL for currently logged in member
	 * @return	bool
	 */
	public function canRespond( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		/* If we have not responded... */
		if( !$this->hasAuthorResponse() )
		{
			/* ...and we are the author of the content item... */
			if( $member->member_id and $member->member_id == $this->item()->author()->member_id )
			{
				/* ...then we can respond to this review. */
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Can the specified user edit the response to this review?
	 *
	 * @param	\IPS\Member|NULL	$member	Member to check or NULL for currently logged in member
	 * @return	bool
	 */
	public function canEditResponse( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		/* If there is an author response... */
		if( $this->hasAuthorResponse() )
		{
			$item = $this->item();

			/* Moderators who can edit content can edit responses */
			if ( static::modPermission( 'edit', $member, $item->containerWrapper() ) )
			{
				return TRUE;
			}

			/* Or maybe the member can edit their own content? */
			if ( $member->member_id and $member->member_id == $item->author()->member_id and ( $member->group['g_edit_posts'] == '1' or \in_array( \get_class( $item ), explode( ',', $member->group['g_edit_posts'] ) ) ) and ( !( $item instanceof \IPS\Content\Lockable ) or !$item->locked() ) )
			{
				return TRUE;
			}
		}

		/* Nope, we cannot edit */
		return FALSE;
	}

	/**
	 * Can the specified user delete the response to this review?
	 *
	 * @param	\IPS\Member|NULL	$member	Member to check or NULL for currently logged in member
	 * @return	bool
	 */
	public function canDeleteResponse( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		/* If there is an author response... */
		if( $this->hasAuthorResponse() )
		{
			$container = NULL;

			try
			{
				$container = $this->item()->container();
			}
			catch ( \BadMethodCallException $e ) { }

			/* Moderators who can delete content can delete responses */
			if( static::modPermission( 'delete', $member, $container ) )
			{
				return TRUE;
			}

			/* Or maybe the author can delete their own content? */
			if( $member->member_id and $member->member_id == $this->item()->author()->member_id and ( $member->group['g_delete_own_posts'] == '1' or \in_array( \get_class( $this->item() ), explode( ',', $member->group['g_delete_own_posts'] ) ) ) )
			{
				return TRUE;
			}
		}

		/* Nope, we cannot delete */
		return FALSE;
	}

    /**
     * Respond to a review
     *
     * @param string $response
     * @return void
     */
    public function setResponse( string $response )
    {
		$this->checkProfanityFilters( FALSE, (bool) $this->hasAuthorResponse(), $response );

		$column = static::$databaseColumnMap['author_response'];
		$this->$column = $response;
		$this->save();

		/* Reindex in case it was hidden by the profanity filters */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}
    }
}