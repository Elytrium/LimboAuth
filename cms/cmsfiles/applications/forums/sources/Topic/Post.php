<?php
/**
 * @brief		Post Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		8 Jan 2014
 */

namespace IPS\forums\Topic;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Post Model
 */
class _Post extends \IPS\Content\Comment implements \IPS\Content\EditHistory, \IPS\Content\Hideable, \IPS\Content\Shareable, \IPS\Content\Searchable, \IPS\Content\Embeddable, \IPS\Content\Anonymous
{
	use \IPS\Content\Reactable, \IPS\Content\Reportable, \IPS\Content\Recognizable;
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'pid';
	
	/**
	 * @brief	[Content\Comment]	Item Class
	 */
	public static $itemClass = 'IPS\forums\Topic';
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'forums_posts';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = '';
	
	/**
	 * @brief	Application
	 */
	public static $application = 'forums';

	/**
	 * @brief	Title
	 */
	public static $title = 'post';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'item'				=> 'topic_id',
		'author'			=> 'author_id',
		'author_name'		=> 'author_name',
		'content'			=> 'post',
		'date'				=> 'post_date',
		'ip_address'		=> 'ip_address',
		'edit_time'			=> 'edit_time',
		'edit_show'			=> 'append_edit',
		'edit_member_name'	=> 'edit_name',
		'edit_reason'		=> 'post_edit_reason',
		'hidden'			=> 'queued',
		'first'				=> 'new_topic',
		'is_anon'			=> 'post_field_t2'
	);
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'comment';
	
	/**
	 * @brief	[Content\Comment]	Comment Template
	 */
	public static $commentTemplate = array( array( 'topics', 'forums', 'front' ), 'postContainer' );
	
	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'post';
	
	/**
	 * @brief	Bitwise values for post_bwoptions field
	 */
	public static $bitOptions = array(
		'post_bwoptions' => array(
			'post_bwoptions' => array(
				'best_answer'	=> 1
			)
		)
	);
	
	/**
	 * Join profile fields when loading comments?
	 */
	public static $joinProfileFields = TRUE;

	/**
	 * @brief   Used for the datalayer
	 */
	public static $commentType = 'reply';

	/**
	 * @brief	Value to set for the 'tab' parameter when redirecting to the comment (via _find())
	 */
	public static $tabParameter	= NULL; /* We do not want ?tab=comments appended to links in from findComment links */

	/**
	 * Create comment
	 *
	 * @param	\IPS\Content\Item		$item				The content item just created
	 * @param	string					$comment			The comment
	 * @param	bool					$first				Is the first comment?
	 * @param	string					$guestName			If author is a guest, the name to use
	 * @param	bool|NULL				$incrementPostCount	Increment post count? If NULL, will use static::incrementPostCount()
	 * @param	\IPS\Member|NULL		$member				The author of this comment. If NULL, uses currently logged in member.
	 * @param	\IPS\DateTime|NULL		$time				The time
	 * @param	string|NULL				$ipAddress			The IP address or NULL to detect automatically
	 * @param	int|NULL				$hiddenStatus		NULL to set automatically or override: 0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @param	int|NULL				$anonymous			NULL for no value, 0 or 1 for a value (0=no, 1=yes)
	 * @return	static
	 */
	public static function create( $item, $comment, $first=FALSE, $guestName=NULL, $incrementPostCount=NULL, $member=NULL, \IPS\DateTime $time=NULL, $ipAddress=NULL, $hiddenStatus=NULL, $anonymous=NULL )
	{
		$comment = parent::create( $item, $comment, $first, $guestName, $incrementPostCount, $member, $time, $ipAddress, $hiddenStatus, $anonymous );
		
		if ( !$comment->hidden() )
		{
			$item->rebuildPopularTime();
		}
		
		return $comment;
	}

	/**
	 * Syncing to run when hiding
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onHide( $member )
	{
		parent::onHide( $member );
		$this->item()->rebuildPopularTime();
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
		parent::onUnhide( $approving, $member );
		$this->item()->rebuildPopularTime();
	}
	
	/**
	 * Should posting this increment the poster's post count?
	 *
	 * @param	\IPS\Node\Model|NULL	$container	Container
	 * @return	void
	 * @see		incrementPostCount
	 */
	public static function incrementPostCount( \IPS\Node\Model $container = NULL )
	{
		return !$container or $container->inc_postcount;
	}
	
	/**
	 * Post count for member
	 *
	 * @param	\IPS\Member	$member	The memner
	 * @return	int
	 */
	public static function memberPostCount( \IPS\Member $member, bool $includeNonPostCountIncreasing = FALSE, bool $includeHiddenAndPendingApproval = TRUE )
	{
		$where = [];
		$where[] = [ 'author_id=?', $member->member_id ];
		
		if ( !$includeNonPostCountIncreasing )
		{
			$where[] = [ 'forums_topics.forum_id IN(?)', \IPS\Db::i()->select( 'id', 'forums_forums', 'inc_postcount=1' ) ];
		}
		if ( !$includeHiddenAndPendingApproval )
		{
			$where[] = [ 'queued=0' ];
		}
		
		$query = \IPS\Db::i()->select( 'COUNT(*)', 'forums_posts', $where );
		if ( !$includeNonPostCountIncreasing )
		{
			$query = $query->join( 'forums_topics', 'tid=topic_id' );
		}
		
		return $query->first();
	}
	
	/**
	 * Get items with permission check
	 *
	 * @param	array		$where				Where clause
	 * @param	string		$order				MySQL ORDER BY clause (NULL to order by date)
	 * @param	int|array	$limit				Limit clause
	 * @param	string		$permissionKey		A key which has a value in the permission map (either of the container or of this class) matching a column ID in core_permission_index
	 * @param	mixed		$includeHiddenItems	Include hidden comments? NULL to detect if currently logged in member has permission, -1 to return public content only, TRUE to return unapproved content and FALSE to only return unapproved content the viewing member submitted
	 * @param	int			$queryFlags			Select bitwise flags
	 * @param	\IPS\Member	$member				The member (NULL to use currently logged in member)
	 * @param	bool		$joinContainer		If true, will join container data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinComments		If true, will join comment data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinReviews		If true, will join review data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$countOnly				If true will return the count
	 * @param	array|null	$joins					Additional arbitrary joins for the query
	 * @return	array|NULL|\IPS\Content\Comment		If $limit is 1, will return \IPS\Content\Comment or NULL for no results. For any other number, will return an array.
	 */
	public static function getItemsWithPermission( $where=array(), $order=NULL, $limit=10, $permissionKey='read', $includeHiddenItems=\IPS\Content\Hideable::FILTER_AUTOMATIC, $queryFlags=0, \IPS\Member $member=NULL, $joinContainer=FALSE, $joinComments=FALSE, $joinReviews=FALSE, $countOnly=FALSE, $joins=NULL )
	{
		$where = \IPS\forums\Topic::getItemsWithPermissionWhere( $where, $permissionKey, $member, $joinContainer );
		
		return parent::getItemsWithPermission( $where, $order, $limit, $permissionKey, $includeHiddenItems, $queryFlags, $member, $joinContainer, $joinComments, $joinReviews, $countOnly, $joins );
	}
	
	/**
	 * Get HTML for search result display
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$authorData		Basic data about the author. Only includes columns returned by \IPS\Member::columnsForPhoto()
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	array|NULL	$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	array		$reputationData	Array of people who have given reputation and the reputation they gave
	 * @param	int|NULL	$reviewRating	If this is a review, the rating
	 * @param	bool		$iPostedIn		If the user has posted in the item
	 * @param	string		$view			'expanded' or 'condensed'
	 * @param	bool		$asItem	Displaying results as items?
	 * @param	bool		$canIgnoreComments	Can ignore comments in the result stream? Activity stream can, but search results cannot.
	 * @param	array		$template	Optional custom template
	 * @param	array		$reactions	Reaction Data
	 * @return	string
	 */
	public static function searchResult( array $indexData, array $authorData, array $itemData, ?array $containerData, array $reputationData, $reviewRating, $iPostedIn, $view, $asItem, $canIgnoreComments=FALSE, $template=NULL, $reactions=array() )
	{	
		/* Password protected */
		if (
			$containerData['password'] // There is a password
			and !\IPS\Member::loggedIn()->inGroup( explode( ',', $containerData['password_override'] ) ) // We can't bypass it
			and (
				!isset( \IPS\Request::i()->cookie[ 'ipbforumpass_' . $indexData['index_container_id'] ] )
				or
				!\IPS\Login::compareHashes( md5( $containerData['password'] ), \IPS\Request::i()->cookie[ 'ipbforumpass_' . $indexData['index_container_id'] ] )
			) // We don't have the correct password
		)
		{
			return \IPS\Theme::i()->getTemplate( 'global', 'forums' )->searchNoPermission(
				\IPS\Member::loggedIn()->language()->addToStack('no_perm_post_password'),
				\IPS\Http\Url::internal( \IPS\forums\Forum::$urlBase . $indexData['index_container_id'], 'front', \IPS\forums\Forum::$urlTemplate, array( $containerData[ \IPS\forums\Forum::$databasePrefix . \IPS\forums\Forum::$seoTitleColumn ] ) )->setQueryString( 'topic', $indexData['index_item_id'] )
			);
		}

		/* Minimum posts */
		elseif ( $containerData['min_posts_view'] and $containerData['min_posts_view'] > \IPS\Member::loggedIn()->member_posts )
		{
			return \IPS\Theme::i()->getTemplate( 'global', 'forums' )->searchNoPermission( \IPS\Member::loggedIn()->language()->addToStack( 'no_perm_post_min_posts', FALSE, array( 'pluralize' => array( $containerData['min_posts_view'] ) ) ) );
		}
		
		/* Normal */
		else
		{
			return parent::searchResult( $indexData, $authorData, $itemData, $containerData, $reputationData, $reviewRating, $iPostedIn, $view, $asItem, $canIgnoreComments, $template, $reactions );
		}
	}
	
	/**
	 * Can promote this comment/item?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	boolean
	 */
	public function canPromoteToSocialMedia( $member=NULL )
	{
		return $this->item()->canPromoteToSocialMedia( $member );
	}
	
	/* !Questions & Answers */
	
	/**
	 * Can the user rate answers?
	 *
	 * @param	int					$rating		1 for positive, -1 for negative, 0 for either
	 * @param	\IPS\Member|NULL	$member		The member (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\InvalidArgumentException
	 */
	public function canVote( $rating=0, $member=NULL )
	{
		/* Is $rating valid */
		if ( !\in_array( $rating, array( -1, 0, 1 ) ) )
		{
			throw new \InvalidArgumentException;
		}
		
		/* Downvoting disabled? */
		if ( $rating === -1 and !\IPS\Settings::i()->forums_answers_downvote )
		{
			return FALSE;
		}
		
		/* Guests can't vote */
		$member = $member ?: \IPS\Member::loggedIn();
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		/* Can't vote your own answers */
		if ( $member == $this->author() )
		{
			return FALSE;
		}
		
		/* Check the forum settings */
		if ( $this->item()->container()->qa_rate_answers !== NULL and $this->item()->container()->qa_rate_answers != '*' and !$member->inGroup( explode( ',', $this->item()->container()->qa_rate_answers ) ) )
		{
			return FALSE;
		}

		return TRUE;
	}
	
	/**
	 * [ActiveRecord]	Save
	 *
	 * @return	void
	 */
	public function save()
	{
		parent::save();
		$this->item()->clearVotes();
	}

    /**
     * Delete Post
     *
     * @return	void
     */
    public function delete()
    {
		/* It is possible to delete a post that is orphaned, so let's try to protect against that */
		try
		{
			$item	= $this->item();
		}
		catch( \OutOfRangeException $e )
		{
			$item	= NULL;
		}

        parent::delete();

		/* Deleting a post may make the item no longer popular */
		if( $item !== NULL )
		{
			$item->rebuildPopularTime();
		}
    }
    
    /**
	 * Get the snippet (stripped of HTML)
	 *
	 * @param	int		$length		The length of the snippet to return
	 * @return string
	 */
	public function snippet( $length=300 )
	{
		$contentField = static::$databaseColumnMap['content'];
		$content = trim( str_replace( \chr(0xC2) . \chr(0xA0), ' ', strip_tags( preg_replace( "/(<br(?: \/)?>|<\/p>)/i", ' ', preg_replace( "#<blockquote(?:[^>]+?)>.+?(?<!<blockquote)</blockquote>#s", " ", preg_replace( "#<script(.*?)>(.*)</script>#uis", "", ' ' . $this->$contentField . ' ' ) ) ) ) ) );
		return mb_substr( $content, 0, $length ) . ( mb_strlen( $content ) > $length ? '&hellip;' : '' );
	}
	
	/**
	 * Indefinite Article
	 *
	 * @param	\IPS\Lang|NULL	$lang	The language to use, or NULL for the language of the currently logged in member
	 * @return	string
	 */
	public function indefiniteArticle( \IPS\Lang $lang = NULL )
	{
		if ( $this->isFirst() AND $this->item()->container()->forums_bitoptions['bw_enable_answers'] )
		{
			$lang = $lang ?: \IPS\Member::loggedIn()->language();
			return $lang->addToStack( '__indefart_question', FALSE );
		}
		
		return parent::indefiniteArticle( $lang );
	}

	/**
	 * Indefinite Article
	 *
	 * @param  array 	$containerData	 Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param  \IPS\Lang $lang		 The language to use, or NULL for the language of the currently logged in member
	 * @return string
	 */
	public static function _indefiniteArticle( array $containerData = NULL, \IPS\Lang $lang = NULL )
	{
		if ( !$containerData )
		{
			return parent::_indefiniteArticle( $containerData, $lang );
		}
		
		$bitOptions = ( $containerData['forums_bitoptions'] instanceof \IPS\Patterns\Bitwise ) ? $containerData['forums_bitoptions'] : new \IPS\Patterns\Bitwise( array( 'forums_bitoptions' => $containerData['forums_bitoptions'] ), \IPS\forums\Forum::$bitOptions['forums_bitoptions'] );
		
		if ( $bitOptions['bw_enable_answers'] )
		{
			$lang = $lang ?: \IPS\Member::loggedIn()->language();
			return $lang->addToStack( '__indefart_answer', FALSE );
		}
		else
		{
			return parent::_indefiniteArticle( $containerData, $lang );
		}
	}
	
	/**
	 * Definite Article
	 *
	 * @param	array|NULL		$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	\IPS\Lang|NULL	$lang			The language to use, or NULL for the language of the currently logged in member
	 * @param	array			$options		Options to pass to \IPS\Lang::addToStack
	 * @param	integer|boolean	$count			Count of things; if int > 1, uses pluralized version of definite article
	 * @return	string
	 */
	public static function _definiteArticle( array $containerData = NULL, \IPS\Lang $lang = NULL, $options = array(), $count = FALSE )
	{
		if ( !$containerData )
		{
			return parent::_definiteArticle( $containerData, $lang, $options );
		}
		
		$bitOptions = ( $containerData['forums_bitoptions'] instanceof \IPS\Patterns\Bitwise ) ? $containerData['forums_bitoptions'] : new \IPS\Patterns\Bitwise( array( 'forums_bitoptions' => $containerData['forums_bitoptions'] ), \IPS\forums\Forum::$bitOptions['forums_bitoptions'] );
		
		if ( $bitOptions['bw_enable_answers'] )
		{
			$lang = $lang ?: \IPS\Member::loggedIn()->language();
			if( \is_int( $count ) )
			{
				$options['pluralize'] = array( $count );
				return $lang->addToStack( '__defart_answer_plural', FALSE, $options );
			}

			return $lang->addToStack( '__defart_answer', FALSE, $options );
		}
		else
		{
			return parent::_definiteArticle( $containerData, $lang, $options, $count );
		}
	}

	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		/* Set forum theme if it has been overridden */
		$this->item()->container()->setTheme();

		return parent::html();
	}
	
	/**
	 * Force HTML posting abilities to TRUE for this comment
	 * This is usually determined by the member group and Editor extension.
	 * Here it can be overridden on a per comment basis
	 *
	 * @note Used currently in applications/core/extensions/core/Queue/RebuildPosts when rebuilding
	 *
	 * @return boolean
	 */
	public function htmlParsingEnforced()
	{
		return (boolean) $this->post_htmlstate > 0;
	}
	
	/**
	 * Reaction type
	 *
	 * @return	string
	 */
	public static function reactionType()
	{
		return 'pid';
	}

	/**
	 * Set this comment as solved
	 *
	 * @param 	boolean	$value		TRUE/FALSE value
	 * @return	void
	 */
	public function setSolved( $value )
	{
		$this->post_bwoptions['best_answer'] = (bool) $value;
	}

	/**
	 * Get content for embed
	 *
	 * @param	array	$params	Additional parameters to add to URL
	 * @return	string
	 */
	public function embedContent( $params )
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'embed.css', 'forums', 'front' ) );
		return \IPS\Theme::i()->getTemplate( 'global', 'forums' )->embedPost( $this, $this->item(), $this->url()->setQueryString( $params ) );
	}

	/**
	 * Create a query to fetch the "top members"
	 *
	 * @note	The intention is to formulate a query that will fetch the members with the most contributions
	 * @note	The group by confuses MySQL and it ends up using the 'queued' index, when the 'author_id' index is far better
	 * @param	int		$limit	The number of members to return
	 * @return	\IPS\Db\Select
	 */
	public static function topMembersQuery( $limit )
	{
		$query = parent::topMembersQuery( $limit );

		return $query->forceIndex( 'author_id' );
	}
	
	/**
	 * Webhook filters
	 *
	 * @return	array
	 */
	public function webhookFilters()
	{
		$return = parent::webhookFilters();
		try
		{
			$return['forums'] = $this->item()->container()->_id;
			$return['hasBestAnswer'] = (bool) $this->item()->topic_answered_pid;
		}
		catch( \OutOfRangeException $e ) {}

		return $return;
	}

	/**
	 * Is this a future entry?
	 *
	 * @return bool
	 */
	public function isFutureDate()
	{
		return $this->item()->isFutureDate();
	}
}