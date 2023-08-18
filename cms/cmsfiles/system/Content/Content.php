<?php
/**
 * @brief		Abstract Content Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Oct 2013
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Abstract Content Model
 */
abstract class _Content extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	[Content\Comment]	Database Column Map
	 */
	protected static $databaseColumnMap = array();
	
	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = NULL;
	
	/**
	 * @brief	[Content\Comment]	Language prefix for forms
	 */
	public static $formLangPrefix = '';

	/**
	 * @brief	Include In Sitemap
	 */
	public static $includeInSitemap = TRUE;
	
	/**
	 * @brief	Reputation Store
	 */
	protected $reputation;
	
	/**
	 * @brief	Can this content be moderated normally from the front-end (will be FALSE for things like Pages and Commerce Products)
	 */
	public static $canBeModeratedFromFrontend = TRUE;
	
	/**
	 * Should posting this increment the poster's post count?
	 *
	 * @param	\IPS\Node\Model|NULL	$container	Container
	 * @return	void
	 */
	public static function incrementPostCount( \IPS\Node\Model $container = NULL )
	{
		return TRUE;
	}

	/**
	 * Post count for member
	 *
	 * @param	\IPS\Member	$member								The member
	 * @param	bool		$includeNonPostCountIncreasing		If FALSE, will skip any posts which would not cause the user's post count to increase
	 * @param	bool		$includeHiddenAndPendingApproval	If FALSE, will skip any hidden posts, or posts pending approval
	 * @return	int
	 */
	public static function memberPostCount( \IPS\Member $member, bool $includeNonPostCountIncreasing = FALSE, bool $includeHiddenAndPendingApproval = TRUE )
	{
		if ( !isset( static::$databaseColumnMap['author'] ) )
		{
			return 0;
		}
		
		if ( !$includeNonPostCountIncreasing and !static::incrementPostCount() )
		{
			return 0;
		}
		
		$where = [];
		$where[] = [ static::$databasePrefix . static::$databaseColumnMap['author'] . '=?', $member->member_id ];
		
		if ( !$includeHiddenAndPendingApproval )
		{
			if ( isset( static::$databaseColumnMap['hidden'] ) )
			{
				$where[] = [ static::$databasePrefix . static::$databaseColumnMap['hidden'] . '=0' ];
			}
			if ( isset( static::$databaseColumnMap['approved'] ) )
			{
				$where[] = [ static::$databasePrefix . static::$databaseColumnMap['approved'] . '=1' ];
			}
		}
		
		return \IPS\Db::i()->select( 'COUNT(*)', static::$databaseTable, $where )->first();
	}

	/**
	 * Post count for member
	 *
	 * @deprecated	Use options provided to memberPostCount()
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	int
	 */
	public static function rawMemberPostCount( \IPS\Member $member )
	{
		return static::memberPostCount( $member, TRUE );
	}
	
	/**
	 * Members with most contributions
	 *
	 * @param	int	$count	The number of results to return
	 * @return	array
	 */
	public static function mostContributions( $count = 5 )
	{
		if( !isset( static::$databaseColumnMap['author'] ) )
		{
			return array( 'counts' => NULL, 'members' => NULL );
		}

		$where = array();
		if( isset( static::$databaseColumnMap['approved'] ) )
		{
			$approvedColumn = static::$databasePrefix . static::$databaseColumnMap['approved'];
			$where[] = array( "{$approvedColumn} = 1" );
		}
		if( isset( static::$databaseColumnMap['hidden'] ) )
		{
			$hiddenColumn = static::$databasePrefix . static::$databaseColumnMap['hidden'];
			$where[] = array( "{$hiddenColumn} = 0" );
		}

		$authorColumn = static::$databasePrefix . static::$databaseColumnMap['author'];
		$members = \IPS\Db::i()->select( "count(*) as sum, {$authorColumn}", static::$databaseTable, $where, 'sum DESC', array( 0, $count ), array( static::$databasePrefix . static::$databaseColumnMap['author'] ) );

		$contributors = array();
		$counts = array();
		foreach ( $members as $member )
		{
			$contributors[] = $member[ $authorColumn ];
			$counts[ $member[ $authorColumn ] ] = $member[ 'sum' ];
		}

		if ( empty( $contributors ) )
		{
			return array( 'counts' => NULL, 'members' => NULL );
		}

		return array( 'counts' => $counts, 'members' => new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members', array( \IPS\Db::i()->in( 'member_id', $contributors ) ), "FIND_IN_SET( member_id, '" . implode( ",", $contributors) . "' )" ), 'IPS\Member' ) );
	}
		
	/**
	 * Load and check permissions
	 *
	 * @param	mixed				$id		ID
	 * @param	\IPS\Member|NULL	$member	Member, or NULL for logged in member
	 * @return	static
	 * @throws	\OutOfRangeException
	 */
	public static function loadAndCheckPerms( $id, \IPS\Member $member = NULL )
	{
		$obj = static::load( $id );
		
		$member = $member ?: \IPS\Member::loggedIn();
		if ( !$obj->canView( $member ) )
		{
			throw new \OutOfRangeException;
		}

		return $obj;
	}
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
    {
	    if ( isset( $data[ static::$databaseTable ] ) and \is_array( $data[ static::$databaseTable ] ) )
	    {
	        /* Add author data to multiton store to prevent ->author() running another query later */
	        if ( isset( $data['author'] ) and \is_array( $data['author'] ) )
	        {
	           	$author = \IPS\Member::constructFromData( $data['author'], FALSE );

	            if ( isset( $data['author_pfields'] ) )
	            {
		            unset( $data['author_pfields']['member_id'] );
					$author->contentProfileFields( $data['author_pfields'] );
	            }
	        }

	        /* Load content */
	        $obj = parent::constructFromData( $data[ static::$databaseTable ], $updateMultitonStoreIfExists );

			/* Add reputation if it was passed*/
			if ( isset( $data['core_reputation_index'] ) and \is_array( $data['core_reputation_index'] ) )
			{
				$obj->_data = array_merge( $obj->_data, $data['core_reputation_index'] );
			}

			/* Return */
			return $obj;
		}
		else
		{
			return parent::constructFromData( $data, $updateMultitonStoreIfExists );
		}
    }

    /**
     * @brief	Cached social groups
     */
    protected static $_cachedSocialGroups = array();
    
    /**
	 * Get WHERE clause for Social Group considerations for getItemsWithPermission
	 *
	 * @param	string		$socialGroupColumn	The column which contains the social group ID
	 * @param	\IPS\Member	$member				The member (NULL to use currently logged in member)
	 * @return	string
	 */
	public static function socialGroupGetItemsWithPermissionWhere( $socialGroupColumn, $member )
	{			
		$socialGroups = array();
		
		$member = $member ?: \IPS\Member::loggedIn();
		if ( $member->member_id )
		{
			if( !array_key_exists( $member->member_id, static::$_cachedSocialGroups ) )
			{
				static::$_cachedSocialGroups[ $member->member_id ] = iterator_to_array( \IPS\Db::i()->select( 'group_id', 'core_sys_social_group_members', array( 'member_id=?', $member->member_id ) ) );
			}

			$socialGroups = static::$_cachedSocialGroups[ $member->member_id ];
		}

		if ( \count( $socialGroups ) )
		{
			return $socialGroupColumn . '=0 OR ( ' . \IPS\Db::i()->in( $socialGroupColumn, $socialGroups ) . ' )';
		}
		else
		{
			return $socialGroupColumn . '=0';
		}
	}

	/**
	 * Check the request for legacy parameters we may need to redirect to
	 *
	 * @return	NULL|\IPS\Http\Url
	 */
	public function checkForLegacyParameters()
	{
		$paramsToSet	= array();
		$paramsToUnset	= array();

		/* st=20 needs to go to page=2 (or whatever the comments per page setting is set to) */
		if( isset( \IPS\Request::i()->st ) )
		{
			$commentsPerPage = static::getCommentsPerPage();

			$paramsToSet['page']	= floor( \intval( \IPS\Request::i()->st ) / $commentsPerPage ) + 1;
			$paramsToUnset[]		= 'st';
		}

		/* Did we have any? */
		if( \count( $paramsToSet ) )
		{
			$url = $this->url();

			if( \count( $paramsToUnset ) )
			{
				$url = $url->stripQueryString( $paramsToUnset );
			}

			$url = $url->setQueryString( $paramsToSet );

			return $url;
		}

		return NULL;
	}

	/**
	 * Get mapped value
	 *
	 * @param	string	$key	date,content,ip_address,first
	 * @return	mixed
	 */
	public function mapped( $key )
	{
		if ( isset( static::$databaseColumnMap[ $key ] ) )
		{
			$field = static::$databaseColumnMap[ $key ];
			
			if ( \is_array( $field ) )
			{
				$field = array_pop( $field );
			}
			
			return $this->$field;
		}
		return NULL;
	}
	
	/**
	 * Get author
	 *
	 * @return	\IPS\Member
	 */
	public function author()
	{
		if( $this->isAnonymous() )
		{
			$guest = new \IPS\Member;
			$guest->name = \IPS\Member::loggedIn()->language()->get( "post_anonymously_placename" );
			return $guest;
		}
		elseif ( $this->mapped('author') or !isset( static::$databaseColumnMap['author_name'] ) or !$this->mapped('author_name') )
		{
			return \IPS\Member::load( $this->mapped('author') );
		} 
		else
		{
			$guest = new \IPS\Member;
			$guest->name = $this->mapped('author_name');
			return $guest;
		}
	}
	
	/**
	 * Returns the content
	 *
	 * @return	string
	 */
	public function content()
	{
		return $this->mapped('content');
	}

	/**
	 * Text for use with data-ipsTruncate
	 * Returns the post with paragraphs turned into line breaks
	 *
	 * @param	bool		$oneLine	If TRUE, will use spaces instead of line breaks. Useful if using a single line display.
	 * @param	int|null	$length		If supplied, and $oneLine is set to TRUE, the returned content will be truncated to this length
	 * @return	string
	 * @note	For now we are removing all HTML. If we decide to change this to remove specific tags in future, we can use \IPS\Text\Parser::removeElements( $this->content() )
	 */
	public function truncated( $oneLine=FALSE, $length=500 )
	{
		return \IPS\Text\Parser::truncate( $this->content(), $oneLine, $length );
	}
	
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		$idColumn = static::$databaseColumnId;
		
		if ( \IPS\IPS::classUsesTrait( $this, 'IPS\Content\Reactable' ) )
		{
			\IPS\Db::i()->delete( 'core_reputation_index', array( 'app=? AND type=? AND type_id=?', static::$application, $this->reactionType(), $this->$idColumn ) );
		}

		if ( \IPS\IPS::classUsesTrait( $this, 'IPS\Content\Recognizable' ) )
		{
			\IPS\Db::i()->delete( 'core_member_recognize', array( 'r_content_class=? AND r_content_id=?', \get_class( $this ), $this->$idColumn ) );
		}

		if( $this instanceof \IPS\Content\Anonymous )
		{
			\IPS\Db::i()->delete( 'core_anonymous_posts', array( 'anonymous_object_class=? AND anonymous_object_id=?', \get_class( $this ), $this->$idColumn ) );
		}

		/* Remove any entries in the promotions table */
		\IPS\Db::i()->delete( 'core_social_promote', array( 'promote_class=? AND promote_class_id=?', \get_class( $this ), $this->$idColumn ) );
		
		\IPS\Db::i()->delete( 'core_deletion_log', array( "dellog_content_class=? AND dellog_content_id=?", \get_class( $this ), $this->$idColumn ) );
		\IPS\Db::i()->delete( 'core_solved_index', array( 'comment_class=? and comment_id=?', \get_class( $this ), $this->$idColumn ) );

		if ( static::$hideLogKey )
		{
			$idColumn = static::$databaseColumnId;
			\IPS\Db::i()->delete('core_soft_delete_log', array('sdl_obj_id=? AND sdl_obj_key=?', $this->$idColumn, static::$hideLogKey));
		}

		\IPS\Db::i()->delete( 'core_content_featured', [ 'feature_content_id=? and feature_content_class=?', $this->$idColumn, \get_class( $this ) ] );

		\IPS\Api\Webhook::fire( str_replace( '\\', '', \substr( \get_called_class(), 3 ) ) . '_delete', $this, $this->webhookFilters() );
		
		try
		{
			\IPS\core\Approval::loadFromContent( \get_called_class(), $this->$idColumn )->delete();
		}
		catch( \OutOfRangeException $e ) { }

		parent::delete();

		$this->expireWidgetCaches();
		$this->adjustSessions();
	}

	/**
	 * Is this a future entry?
	 *
	 * @return bool
	 */
	public function isFutureDate()
	{
		if ( $this instanceof \IPS\Content\FuturePublishing )
		{
			if ( isset( static::$databaseColumnMap['is_future_entry'] ) and isset( static::$databaseColumnMap['future_date'] ) )
			{
				$column = static::$databaseColumnMap['future_date'];
				if ( $this->$column > time() )
				{
					return TRUE;
				}
			}
		}

		return FALSE;
	}

	/**
	 * Return the tooltip blurb for future entries
	 *
	 * @return string
	 */
	public function futureDateBlurb()
	{
		$column = static::$databaseColumnMap['future_date'];
		$time   = \IPS\DateTime::ts( $this->$column );
		return  \IPS\Member::loggedIn()->language()->addToStack("content_future_date_blurb", FALSE, array( 'sprintf' => array( $time->localeDate(), $time->localeTime() ) ) );
	}
	
	/**
	 * Check comment against profanity filters but do not act on it
	 *
	 * @param	bool				$first				Is this the first comment?
	 * @param	bool				$edit				Are we editing or merging (true) or is this a new comment (false)?
	 * @param	string|NULL			$content			The content to check - useful for if the content needs to be checked first, before it gets saved to the database.
	 * @param	string|NULL|bool		$title				The title of the content to check, or NULL to check the current title, or FALSE to not check at all.
	 * @param	string				$autoSaveLocation	The autosave location key of any editors to check, or NULL to use the default.
	 * @param	string|NULL			$autoSaveKeys		The autosave keys (for new content) or attach lookup ids (for an edit) of any editors to check, or NULL to use the default.
	 * @param	array				$imageUploads		Images that have been uploaded that may require moderation
	 * @param	array|NULL			$filtersMatched	What matched, passed by reference
	 * @return	bool									Whether to send unapproved notifications (i.e. true if the content was hidden)
	 */
	public function shouldTriggerProfanityFilters( $first=FALSE, $edit=TRUE, $content=NULL, $title=NULL, $autoSaveLocation=NULL, $autoSaveKeys=NULL, $imageUploads=[], &$hiddenByFilter = FALSE, &$itemHiddenByFilter = FALSE, array &$filtersMatched = array() )
	{		
		/* Set our content */
		$content = $content ?: $this->content();
				
		/* We need our item */
		$item = $this;
		if ( $this instanceof \IPS\Content\Comment )
		{
			$item = $this->item();
			
			if ( $item::$firstCommentRequired AND $first AND $title !== FALSE )
			{
				$title = $title ?: $item->mapped('title');
			}
		}
		else
		{
			if ( $title !== FALSE )
			{
				$title = $title ?: $this->mapped('title');
			}
		}
		
		/* If this content is "post before register", skip this check (we'll do it after registration is complete) */
		if ( $this->hidden() === -3 or ( $item::$firstCommentRequired and $first and $item->hidden() === -3 ) )
		{
			return FALSE;
		}

		/* Check the author cannot bypass */
		$member = $this->author();
		if ( $member->group['g_bypass_badwords'] )
		{
			return FALSE;
		}
				
		/* First, check the image scanner */
		$idColumn = static::$databaseColumnId;
		$itemIdColumn = $item::$databaseColumnId;
		if ( \IPS\Settings::i()->ips_imagescanner_enable )
		{
			if ( $edit )
			{
				$autoSaveLocation = $autoSaveLocation ?: ( $item::$application . '_' . mb_ucfirst( $item::$module ) );
				
				if ( !$autoSaveKeys )
				{			
					if ( $this instanceof \IPS\Content\Comment and ( !( $this instanceof \IPS\Content\Review ) or !$first or !$item::$firstCommentRequired ) )
					{
						$autoSaveKeys = [ $this->attachmentIds() ];
					}
					else
					{
						$autoSaveKeys = [ [ $item->$itemIdColumn ] ];
					}
				}
				
				$clauses = [];
				$binds = [];
				foreach ( $autoSaveKeys as $_autoSaveKeys )
				{				
					$idLookups = [];
					foreach ( range( 1, \count( $_autoSaveKeys ) ) as $i )
					{
						$idLookups[]= "id{$i}=?";
					}
					$clauses[] = '( ' . implode( ' AND ', $idLookups ) . ' )';
					$binds = array_merge( $binds, $_autoSaveKeys );
				}

				if ( $this instanceof \IPS\Content\Item OR ( $first AND $item::$firstCommentRequired ) )
				{
					$itemHiddenByFilter = \IPS\core\Profanity::hiddenByFilters( $content, $member, $filtersMatched );
					
					if ( $title AND !$itemHiddenByFilter )
					{
						$itemHiddenByFilter = \IPS\core\Profanity::hiddenByFilters( $title, $member, $filtersMatched );
					}
				}
				else
				{
					$hiddenByFilter = \IPS\core\Profanity::hiddenByFilters( $content, $member, $filtersMatched );
					
					if ( $title AND !$hiddenByFilter )
					{
						$hiddenByFilter = \IPS\core\Profanity::hiddenByFilters( $title, $member, $filtersMatched );
					}
				}
				
				$attachmentsMapWhere = array_merge( array( 'location_key=? AND ( ' . implode( ' OR ', $clauses ) . ' )', $autoSaveLocation ), $binds );
			}
			else
			{
				if ( $autoSaveKeys === NULL )
				{
					if ( $this instanceof \IPS\Content\Item or ( $first and $item::$firstCommentRequired ) )
					{
						$container = $item->containerWrapper();
						$autoSaveKeys[] = 'newContentItem-' . $item::$application . '/' . $item::$module  . '-' . ( $container ? $container->_id : 0 );
					}
					else
					{
						if ( $this instanceof \IPS\Content\Review )
						{
							$autoSaveKeys[] = 'review-' . $item::$application . '/' . $item::$module  . '-' . $item->$itemIdColumn;
						}
						else
						{
							$autoSaveKeys[] = 'reply-' . $item::$application . '/' . $item::$module  . '-' . $item->$itemIdColumn;
						}
					}
				}

				if ( $this instanceof \IPS\Content\Item OR ( $first AND $item::$firstCommentRequired ) )
				{
					$itemHiddenByFilter = \IPS\core\Profanity::hiddenByFilters( $content, $member, $filtersMatched );
					
					if ( $title AND !$itemHiddenByFilter )
					{
						$itemHiddenByFilter = \IPS\core\Profanity::hiddenByFilters( $title, $member, $filtersMatched );
					}
				}
				else
				{
					$hiddenByFilter = \IPS\core\Profanity::hiddenByFilters( $content, $member, $filtersMatched );
					
					if ( $title AND !$hiddenByFilter )
					{
						$hiddenByFilter = \IPS\core\Profanity::hiddenByFilters( $title, $member, $filtersMatched );
					}
				}
				
				$attachmentsMapWhere = \IPS\Db::i()->in( 'temp', array_map( 'md5', $autoSaveKeys ) );
			}
			
			foreach ( \IPS\Db::i()->select( 'attach_moderation_status', 'core_attachments', [ 'attach_id IN (?)', \IPS\Db::i()->select( 'attachment_id', 'core_attachments_map', $attachmentsMapWhere ) ] ) as $attachModerationStatus )
			{
				if ( $attachModerationStatus === 'pending' )
				{
					if ( $this instanceof \IPS\Content\Item or ( $first and $item::$firstCommentRequired ) )
					{
						$itemHiddenByFilter = TRUE;
					}
					else
					{
						$hiddenByFilter = TRUE;
					}
					break;
				}
			}
			
			if ( !$hiddenByFilter and !$itemHiddenByFilter )
			{
				foreach ( $imageUploads as $file )
				{
					if ( $file->requiresModeration )
					{
						if ( $this instanceof \IPS\Content\Item or ( $first and $item::$firstCommentRequired ) )
						{
							$itemHiddenByFilter = TRUE;
						}
						else
						{
							$hiddenByFilter = TRUE;
						}
						break;
					}
				}
				
				if ( $hiddenByFilter OR $itemHiddenByFilter )
				{
					$log = new \IPS\core\Approval;
					$log->content_class	= \get_called_class();
					$log->content_id	= $this->$idColumn;
					$log->held_reason	= 'image';
					$log->save(); 
				}
			}
		}
				
		/* Then pass this through our profanity and link filters */
		try
		{
			if ( $this instanceof \IPS\Content\Item or ( $first and $item::$firstCommentRequired ) )
			{
				if ( !$itemHiddenByFilter )
				{
					$itemHiddenByFilter = \IPS\core\Profanity::hiddenByFilters( $content, $member, $filtersMatched );
				}
				if ( !$itemHiddenByFilter and $title )
				{
					$itemHiddenByFilter = \IPS\core\Profanity::hiddenByFilters( $title, $member, $filtersMatched );
				}
			}
			else
			{
				if ( !$hiddenByFilter )
				{
					$hiddenByFilter = \IPS\core\Profanity::hiddenByFilters( $content, $member, $filtersMatched );
				}
				if ( !$hiddenByFilter and $title )
				{
					$hiddenByFilter = \IPS\core\Profanity::hiddenByFilters( $title, $member, $filtersMatched );
				}
			}
		}
		catch( \BadMethodCallException $e ) { }
		
		/* Return */
		return ( $hiddenByFilter or $itemHiddenByFilter );
	}
	
	/**
	 * Check comment against profanity filters AND act on it
	 *
	 * @param	bool				$first				Is this the first comment?
	 * @param	bool				$edit				Are we editing or merging (true) or is this a new comment (false)?
	 * @param	string|NULL			$content			The content to check - useful for if the content needs to be checked first, before it gets saved to the database.
	 * @param	string|NULL|bool		$title				The title of the content to check, or NULL to check the current title, or FALSE to not check at all.
	 * @param	string				$autoSaveLocation	The autosave location key of any editors to check, or NULL to use the default.
	 * @param	string|NULL			$autoSaveKeys		The autosave keys (for new content) or attach lookup ids (for an edit) of any editors to check, or NULL to use the default.
	 * @param	array				$imageUploads		Images that have been uploaded that may require moderation
	 * @return	bool									Whether to send unapproved notifications (i.e. true if the content was hidden)
	 */
	public function checkProfanityFilters( $first=FALSE, $edit=TRUE, $content=NULL, $title=NULL, $autoSaveLocation=NULL, $autoSaveKeys=NULL, $imageUploads=[] )
	{
		/* Check this content type is hideable */
		if ( !( $this instanceof \IPS\Content\Hideable ) )
		{
			return FALSE;
		}

		/* Check it */
		$hiddenByFilter = FALSE;
		$itemHiddenByFilter = FALSE;
		$sendNotifications = FALSE;
		$filtersMatched = array();
		$item = ( $this instanceof \IPS\Content\Item ) ? $this : $this->item();
		$this->shouldTriggerProfanityFilters( $first, $edit, $content, $title, $autoSaveLocation, $autoSaveKeys, $imageUploads, $hiddenByFilter, $itemHiddenByFilter, $filtersMatched );

		/* If we need to hide the item, then do that */
		if ( $itemHiddenByFilter )
		{
			$sendNotifications = $edit;
			
			/* 'approved' is easy, clear and concise */
			if ( isset( $item::$databaseColumnMap['approved'] ) )
			{
				$column = $item::$databaseColumnMap['approved'];
				$item->$column = 0;
				$item->save();
			}
			/* 'hidden' is backwards */
			elseif ( isset( $item::$databaseColumnMap['hidden'] ) )
			{
				$column = $item::$databaseColumnMap['hidden'];
				$item->$column = 1;
				$item->save();
			}
		}
		
		/* If we need to hide this, then do that */
		if ( $hiddenByFilter === TRUE or $itemHiddenByFilter === TRUE )
		{
			$sendNotifications = TRUE;
			
			/* 'approved' is easy, clear and concise */
			if ( isset( static::$databaseColumnMap['approved'] ) )
			{
				$column = static::$databaseColumnMap['approved'];
				$this->$column = 0;
				$this->save();
			}
			/* 'hidden' is backwards */
			else if ( isset( static::$databaseColumnMap['hidden'] ) )
			{
				$column = static::$databaseColumnMap['hidden'];
				$this->$column = ( $itemHiddenByFilter and $this instanceof \IPS\Content\Comment ) ? 2 : 1; # We use the special 2 flag to note it is only hidden because the parent is
				$this->save();
			}
			
			$idColumn = static::$databaseColumnId;
			$itemColumnId = $item::$databaseColumnId;
			
			try
			{
				if ( $this instanceof \IPS\Content\Comment AND $item::$firstCommentRequired AND $this->isFirst() )
				{
					\IPS\core\Approval::loadFromContent( \get_class( $item ), $item->$itemColumnId );
				}
				else
				{
					\IPS\core\Approval::loadFromContent( \get_called_class(), $this->$idColumn );
				}
			}
			catch( \OutOfRangeException $e )
			{
				if ( isset( $filtersMatched['type'] ) AND isset( $filtersMatched['match'] ) )
				{
					$log = new \IPS\core\Approval;
					if ( $this instanceof \IPS\Content\Comment AND $item::$firstCommentRequired AND $this->isFirst() )
					{
						$log->content_class	= \get_class( $item );
						$log->content_id	= $item->$itemColumnId;
					}
					else
					{
						$log->content_class	= \get_called_class();
						$log->content_id	= $this->$idColumn;
					}
					$log->held_reason	= $filtersMatched['type'];
					switch( $filtersMatched['type'] )
					{
						case 'profanity':
							$log->held_data = array( 'word' => $filtersMatched['match'] );
							break;
						
						case 'url':
							$log->held_data = array( 'url' => $filtersMatched['match'] );
							break;
						
						case 'email':
							$log->held_data = array( 'email' => $filtersMatched['match'] );
							break;
					}
					$log->save();
				}
			}
		}
		
		/* If we did either, then recount number of comments */
		if ( $itemHiddenByFilter or $hiddenByFilter )
		{
			$item->resyncCommentCounts();
			$item->resyncLastComment();
			$item->save();

			if ( $first AND $container = $item->containerWrapper() )
			{
				$container->resetCommentCounts();
				$container->save();
			}
		}

		/* Return */
		return $sendNotifications;
	}
	
	/**
	 * Content is hidden?
	 *
	 * @return	int
	 *	@li -3 is a post made by a guest using the "post before register" feature
	 *	@li -2 is pending deletion
	 * 	@li	-1 is hidden having been hidden by a moderator
	 * 	@li	0 is unhidden
	 *	@li	1 is hidden needing approval
	 * @note	The actual column may also contain 2 which means the item is hidden because the parent is hidden, but it is not hidden in itself. This method will return -1 in that case.
	 *
	 * @note    A piece of content (item and comment) can have an alias for hidden OR approved.
	 *          With hidden: 0=not hidden, 1=hidden (needs moderator approval), -1=hidden by moderator, 2=parent item is hidden, -2=pending deletion, -3=guest post before register
	 *          With approved: 1=not hidden, 0=hidden (needs moderator approval), -1=hidden by moderator, -2=pending deletion, -3=guest post before register
	 *
	 *          User posting has moderator approval set: When adding an unapproved ITEM (approved=0, hidden=1) you should *not* increment container()->_comments but you should update container()->_unapprovedItems
	 *          User posting has moderator approval set: When adding an unapproved COMMENT (approved=0, hidden=1) you should *not* increment item()->num_comments in item or container()->_comments but you should update item()->unapproved_comments and container()->_unapprovedComments
	 *
	 *          User post is hidden by moderator (approved=-1, hidden=0) you should decrement item()->num_comments and decrement container()->_comments but *not* increment item()->unapproved_comments or container()->_unapprovedComments
	 *          User item is hidden by a moderator (approved=-1, hidden=0) you should decrement container()->comments and subtract comment count from container()->_comments, but *not* increment container()->_unapprovedComments
	 *
	 *          Moderator hides item (approved=-1, hidden=-1) you should substract num_comments from container()->_comments. Comments inside item are flagged as approved=-1, hidden=2 but item()->num_comments should not be substracted from
	 *
	 *          Comments with a hidden value of 2 should increase item()->num_comments but not container()->_comments
	 * @throws	\RuntimeException
	 */
	public function hidden()
	{
		if ( $this instanceof \IPS\Content\Hideable )
		{
			if ( isset( static::$databaseColumnMap['hidden'] ) )
			{
				$column = static::$databaseColumnMap['hidden'];
				return ( $this->$column == 2 ) ? -1 : \intval( $this->$column );
			}
			elseif ( isset( static::$databaseColumnMap['approved'] ) )
			{
				$column = static::$databaseColumnMap['approved'];
				if ( $this->$column == -2 or $this->$column == -3 )
				{
					return \intval( $this->$column );
				}
				return $this->$column == -1 ? \intval( $this->$column ) : \intval( !$this->$column );
			}
			else
			{
				throw new \RuntimeException;
			}
		}
		
		return 0;
	}
	
	/**
	 * Can see moderation tools
	 *
	 * @note	This is used generally to control if the user has permission to see multi-mod tools. Individual content items may have specific permissions
	 * @param	\IPS\Member|NULL	$member	The member to check for or NULL for the currently logged in member
	 * @param	\IPS\Node\Model|NULL		$container	The container
	 * @return	bool
	 */
	public static function canSeeMultiModTools( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return static::modPermission( 'pin', $member, $container ) or static::modPermission( 'unpin', $member, $container ) or static::modPermission( 'feature', $member, $container ) or static::modPermission( 'unfeature', $member, $container ) or static::modPermission( 'edit', $member, $container ) or static::modPermission( 'hide', $member, $container ) or static::modPermission( 'unhide', $member, $container ) or static::modPermission( 'delete', $member, $container );
	}

	/**
	 * Return a list of groups that cannot see this item
	 *
	 * @return 	NULL|array
	 */
	public function cannotViewGroups()
	{
		$groups = array();
		foreach( \IPS\Member\Group::groups() as $group )
		{
			if ( $this instanceof \IPS\Content\Comment )
			{
				if ( ! $this->item()->can( 'view', $group ) )
				{
					$groups[] = $group->name;
				}
			}
			else
			{
				if ( ! $this->can( 'view', $group, FALSE ) )
				{
					$groups[] = $group->name;
				}
			}
		}

		return \count( $groups ) ? $groups : NULL;
	}
	
	/**
	 * Check Moderator Permission
	 *
	 * @param	string						$type		'edit', 'hide', 'unhide', 'delete', etc.
	 * @param	\IPS\Member|NULL			$member		The member to check for or NULL for the currently logged in member
	 * @param	\IPS\Node\Model|NULL		$container	The container
	 * @return	bool
	 */
	public static function modPermission( $type, \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		/* Compatibility checks */
		if ( ( $type == 'hide' or $type == 'unhide' ) and !\in_array( 'IPS\Content\Hideable', class_implements( \get_called_class() ) ) )
		{
			return FALSE;
		}
		if ( ( $type == 'pin' or $type == 'unpin' ) and !\in_array( 'IPS\Content\Pinnable', class_implements( \get_called_class() ) ) )
		{
			return FALSE;
		}
		if ( ( $type == 'feature' or $type == 'unfeature' ) and !\in_array( 'IPS\Content\Featurable', class_implements( \get_called_class() ) ) )
		{
			return FALSE;
		}
		if ( ( $type == 'future_publish' ) and !\in_array( 'IPS\Content\FuturePublishing', class_implements( \get_called_class() ) ) )
		{
			return FALSE;
		}

		/* If this is called from a gateway script, i.e. email piping, just return false as we are a "guest" */
		if( $member === NULL AND !\IPS\Dispatcher::hasInstance() )
		{
			return FALSE;
		}
		
		/* Load Member */
		$member = $member ?: \IPS\Member::loggedIn();

		/* Global permission */
		if ( $member->modPermission( "can_{$type}_content" ) )
		{
			return TRUE;
		}
		/* Per-container permission */
		elseif ( $container )
		{
			return $container->modPermission( $type, $member, static::getContainerModPermissionClass() ?: \get_called_class() );
		}
		
		/* Still here? return false */
		return FALSE;
	}

	/**
	 * Get the content to use for mod permission checks
	 *
	 * @return	string|NULL
	 * @note	By default we will return NULL and the container check will execute against Node::$contentItemClass, however
	 *	in some situations we may need to override this (i.e. for Gallery Albums)
	 */
	protected static function getContainerModPermissionClass()
	{
		return NULL;
	}

	/**
	 * @brief	Flag to skip rebuilding container data (because it will be rebuilt in one batch later)
	 */
	public $skipContainerRebuild = FALSE;
		
	/**
	 * Do Moderator Action
	 *
	 * @param	string				$action	The action
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @param	string|NULL			$reason	Reason (for hides)
	 * @param	bool				$immediately Delete Immediately
	 * @return	void
	 * @throws	\OutOfRangeException|\InvalidArgumentException|\RuntimeException
	 */
	public function modAction( $action, \IPS\Member $member = NULL, $reason = NULL, $immediately = FALSE )
	{
		if( $action === 'approve' )
		{
			$action	= 'unhide';
		}

		/* Check it's a valid action */
		if ( !\in_array( $action, array( 'pin', 'unpin', 'feature', 'unfeature', 'hide', 'unhide', 'move', 'lock', 'unlock', 'delete', 'publish', 'restore', 'restoreAsHidden' ) ) )
		{
			throw new \InvalidArgumentException;
		}
		
		/* And that we can do it */
		$toCheck = $action;
		if ( $action == 'restoreAsHidden' )
		{
			$toCheck = 'restore';
		}
		
		$methodName = 'can' . mb_ucfirst( $toCheck );
		if ( !$this->$methodName( $member ) )
		{
			throw new \OutOfRangeException;
		}
		
		/* Log */
		\IPS\Session::i()->modLog( 'modlog__action_' . $action, array( static::$title => TRUE, $this->url()->__toString() => FALSE, $this->mapped('title') ?: ( method_exists( $this, 'item' ) ? $this->item()->mapped('title') : NULL ) => FALSE ), ( $this instanceof \IPS\Content\Item ) ? $this : $this->item() );

		$idColumn = static::$databaseColumnId;

		/* These ones just need a property setting */
		if ( \in_array( $action, array( 'pin', 'unpin', 'feature', 'unfeature', 'lock', 'unlock' ) ) )
		{
			$val = TRUE;
			switch ( $action )
			{
				case 'unpin':
					$val = FALSE;
				case 'pin':
					$column = static::$databaseColumnMap['pinned'];
					break;
				
				case 'unfeature':
					$val = FALSE;
					\IPS\Db::i()->delete( 'core_content_featured', [ 'feature_content_id=? and feature_content_class=?', $this->$idColumn, \get_class( $this ) ] );
				case 'feature':
					$column = static::$databaseColumnMap['featured'];
					break;
				
				case 'unlock':
					$val = FALSE;
				case 'lock':
					if ( isset( static::$databaseColumnMap['locked'] ) )
					{
						$column = static::$databaseColumnMap['locked'];
					}
					else
					{
						$val = $val ? 'closed' : 'open';
						$column = static::$databaseColumnMap['status'];
					}
					break;
			}
			$this->$column = $val;
			$this->save();

			if ( $action == 'feature' and $this->author()->member_id )
			{
				\IPS\Db::i()->insert( 'core_content_featured', [
					'feature_content_id' => $this->$idColumn,
					'feature_content_class' => \get_class( $this ),
					'feature_content_author' => $this->author()->member_id,
					'feature_date' => time()
				] );

				/* Points */
				$this->author()->achievementAction( 'core', 'ContentPromotion', [
					'content' => $this,
					'promotype' => 'feature'
				] );
			}

			return;
		}
		
		/* Hide is a tiny bit more complicated */
		elseif ( $action === 'hide' )
		{
			$this->hide( $member, $reason );
			return;
		}
		elseif ( $action === 'unhide' )
		{
			$this->unhide( $member );
			return;
		}
		
		/* Delete is just a method */
		elseif ( $action === 'delete' )
		{
			/* If we are retaining content for a period of time, we need to just hide it instead for deleting later - this only works, though, with items that implement \IPS\Content\Hideable */
			if ( \IPS\Settings::i()->dellog_retention_period AND ( $this instanceof \IPS\Content\Hideable ) AND $immediately === FALSE )
			{
				$this->logDelete( $member );
				return;
			}
			
			$idColumn = static::$databaseColumnId;
			$this->delete();
			return;
		}
		
		/* Restore is just a method */
		elseif ( $action === 'restore' )
		{
			$this->restore();
			return;
		}
		
		/* Restore As Hidden is just a method */
		elseif ( $action === 'restoreAsHidden' )
		{
			$this->restore( TRUE );
			return;
		}

		/* Publish is just a method */
		elseif ( $action === 'publish' )
		{
			$this->publish();
			return;
		}

		/* Move is just a method */
		elseif ( $action === 'move' )
		{
			$args	= \func_get_args();
			$this->move( $args[2][0], $args[2][1] );
			return;
		}
	}
	
	/**
	 * Log for deletion later
	 *
	 * @param	\IPS\Member|NULL 	$member	The member, NULL for currently logged in, or FALSE for no member
	 * @return	void
	 */
	public function logDelete( $member = NULL )
	{
		if( $member === NULL )
		{
			$member = \IPS\Member::loggedIn();
		}
		
		/* Log it! */
		$log = new \IPS\core\DeletionLog;
		$log->setContentAndMember( $this, $member );
		$log->save();
		
		if ( isset( static::$databaseColumnMap['hidden'] ) )
		{
			$column = static::$databaseColumnMap['hidden'];
		}
		else if ( isset( static::$databaseColumnMap['approved'] ) )
		{
			$column = static::$databaseColumnMap['approved'];
		}
		
		$this->$column = -2;
		$this->save();
		
		if ( $this instanceof \IPS\Content\Comment )
		{
			$item = $this->item();
			
			/* Update last comment stuff */
			$item->resyncLastComment();

			/* Update last review stuff */
			$item->resyncLastReview();

			/* Update number of comments */
			$item->resyncCommentCounts();

			/* Update number of reviews */
			$item->resyncReviewCounts();

			/* Save*/
			$item->save();
		}
		
		if ( $this instanceof \IPS\Content\Tags )
		{
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_visible' => 0 ), array( 'tag_perm_aai_lookup=?', $this->tagAAIKey() ) );
		}
		
		try
		{
			$idColumn = static::$databaseColumnId;
			
			if ( $this->container() AND !$this->skipContainerRebuild )
			{
				$this->container()->resetCommentCounts();
				$this->container()->setLastComment();
				$this->container()->setLastReview();
				$this->container()->save();
			}

			/* Update mappings */
			if ( $this->container() and \IPS\IPS::classUsesTrait( $this->container(), 'IPS\Node\Statistics' ) )
			{
				if ( $this instanceof \IPS\Content\Comment )
				{
					$this->container()->rebuildPostedIn( array( $this->mapped('item') ) );
				}
				else
				{
					$this->container()->rebuildPostedIn( array($this->$idColumn) );
				}
			}
			
			\IPS\core\Approval::loadFromContent( \get_called_class(), $this->$idColumn )->delete();
		}
		catch( \BadMethodCallException $e ) {}
		catch( \OutOfRangeException $e ) {}
	}
	
	/**
	 * Restore Content
	 *
	 * @param	bool	$hidden	Restore as hidden?
	 * @return	void
	 */
	public function restore( $hidden = FALSE )
	{
		try
		{
			$idColumn = static::$databaseColumnId;
			$log = \IPS\core\DeletionLog::constructFromData( \IPS\Db::i()->select( '*', 'core_deletion_log', array( "dellog_content_class=? AND dellog_content_id=?", \get_class( $this ), $this->$idColumn ) )->first() );
		}
		catch( \UnderflowException $e )
		{
			/* There's no deletion log record, but this shouldn't stop us from restoring */
		}
		
		/* Restoring as hidden? */
		if ( $hidden )
		{
			if ( isset( static::$databaseColumnMap['hidden'] ) )
			{
				$column = static::$databaseColumnMap['hidden'];
			}
			else if ( isset( static::$databaseColumnMap['approved'] ) )
			{
				$column = static::$databaseColumnMap['approved'];
			}
			
			$this->$column = -1;
		}
		else
		{
			if ( isset( static::$databaseColumnMap['hidden'] ) )
			{
				$column = static::$databaseColumnMap['hidden'];
				$this->$column = 0;
			}
			else if ( isset( static::$databaseColumnMap['approved'] ) )
			{
				$column = static::$databaseColumnMap['approved'];
				$this->$column = 1;
			}
		}
		
		if ( $this instanceof \IPS\Content\Tags AND !$hidden )
		{
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_visible' => 1 ), array( 'tag_perm_aai_lookup=?', $this->tagAAIKey() ) );
		}

		/* Save the changes */
		$this->save();

		/* Reindex the now hidden content - if this is a content item with comments or reviews, then make sure to do those too. */
		if ( $this instanceof \IPS\Content\Item AND ( isset( static::$commentClass ) OR isset( static::$reviewClass ) ) )
		{
			\IPS\Content\Search\Index::i()->index( ( static::$firstCommentRequired ) ? $this->firstComment() : $this );
			\IPS\Content\Search\Index::i()->indexSingleItem( $this );
		}
		else
		{
			/* Either this is a comment / review, or the item doesn't support comments or reviews, so we can just reindex it now. */
			\IPS\Content\Search\Index::i()->index( $this );
		}
		
		/* Delete the log */
		if ( isset( $log ) )
		{
			$log->delete();
		}

		/* Recount the container counters */
		if( $this->container() AND !$this->skipContainerRebuild )
		{
			$this->container()->resetCommentCounts();
			$this->container()->setLastComment();
			$this->container()->setLastReview();
			$this->container()->save();
		}
	}
	
	/**
	 * Can restore?*
	 *
	 * @param	\IPS\Member|NULL	$member	The member, or currently logged in member
	 * @return	bool
	 */
	public function canRestore( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $member->modPermission('can_manage_deleted_content');
	}
	
	/**
	 * Give class a chance to inspect and manipulate search engine filters for streams
	 *
	 * @param	array 						$filters	Filters to be used for activity stream
	 * @param	\IPS\Content\Search\Query	$query		Search query object
	 * @return	void
	 */
	public static function searchEngineFiltering( &$filters, &$query )
	{
		/* Intentionally left blank but child classes can override */
	}
	
	/**
	 * Hide
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @param	string					$reason	Reason
	 * @return	void
	 */
	public function hide( $member, $reason = NULL )
	{
		if ( isset( static::$databaseColumnMap['hidden'] ) )
		{
			$column = static::$databaseColumnMap['hidden'];
		}
		elseif ( isset( static::$databaseColumnMap['approved'] ) )
		{
			$column = static::$databaseColumnMap['approved'];
		}
		else
		{
			throw new \RuntimeException;
		}

		/* Already hidden? */
		if( $this->$column == -1 )
		{
			return;
		}

		$this->$column = -1;
		$this->save();
		$this->onHide( $member );
		
		$idColumn = static::$databaseColumnId;
		if ( static::$hideLogKey )
		{
			\IPS\Db::i()->delete( 'core_soft_delete_log', array( 'sdl_obj_id=? AND sdl_obj_key=?', $this->$idColumn, static::$hideLogKey ) );
			\IPS\Db::i()->insert( 'core_soft_delete_log', array(
				'sdl_obj_id'		=> $this->$idColumn,
				'sdl_obj_key'		=> static::$hideLogKey,
				'sdl_obj_member_id'	=> $member === FALSE ? 0 : \intval( $member ? $member->member_id : \IPS\Member::loggedIn()->member_id ),
				'sdl_obj_date'		=> time(),
				'sdl_obj_reason'	=> $reason,
				
			) );
		}
		
		if ( $this instanceof \IPS\Content\Tags )
		{
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_visible' => 0 ), array( 'tag_perm_aai_lookup=?', $this->tagAAIKey() ) );
		}

        /* Update search index */
        if ( $this instanceof \IPS\Content\Searchable )
        {
            \IPS\Content\Search\Index::i()->index( $this );
        }

		$this->expireWidgetCaches();
		$this->adjustSessions();
		
		try
		{
			\IPS\core\Approval::loadFromContent( \get_called_class(), $this->$idColumn )->delete();
		}
		catch( \OutOfRangeException $e ) { }
	}
	
	/**
	 * Unhide
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function unhide( $member )
	{
		/* If we're approving, we have to do extra stuff */
		$approving	= FALSE;
		$pbr		= FALSE;
		if ( $this->hidden() === 1 )
		{
			$approving = TRUE;
			if ( isset( static::$databaseColumnMap['approved_by'] ) and $member !== FALSE )
			{
				$column = static::$databaseColumnMap['approved_by'];
				$this->$column = $member ? $member->member_id : \IPS\Member::loggedIn()->member_id;
			}
			if ( isset( static::$databaseColumnMap['approved_date'] ) )
			{
				$column = static::$databaseColumnMap['approved_date'];
				$this->$column = time();
			}
		}
		elseif( $this->hidden() === -3 )
		{
			$pbr = TRUE;
		}

		/* Now do the actual stuff */
		if ( isset( static::$databaseColumnMap['hidden'] ) )
		{
			$column = static::$databaseColumnMap['hidden'];

			/* Already approved? */
			if( $this->$column == 0 )
			{
				return;
			}

			$this->$column = 0;
		}
		elseif ( isset( static::$databaseColumnMap['approved'] ) )
		{
			$column = static::$databaseColumnMap['approved'];

			/* Already approved? */
			if( $this->$column == 1 )
			{
				return;
			}

			$this->$column = 1;
		}
		else
		{
			throw new \RuntimeException;
		}
		$this->save();
		$this->onUnhide( ( $approving OR ( $pbr AND $this->hidden() === 0 ) ), $member );
		
		$idColumn = static::$databaseColumnId;
		if ( static::$hideLogKey )
		{
			\IPS\Db::i()->delete('core_soft_delete_log', array('sdl_obj_id=? AND sdl_obj_key=?', $this->$idColumn, static::$hideLogKey));
		}

		/* And update the tags perm cache */
		if ( $this instanceof \IPS\Content\Tags )
		{
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_visible' => 1 ), array( 'tag_perm_aai_lookup=?', $this->tagAAIKey() ) );
		}
		
		/* Update search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}
		
		/* Update report center stuff */
		if ( \IPS\IPS::classUsesTrait( $this, 'IPS\Content\Reportable' ) )
		{
			$this->moderated( 'unhide' );
		}
		
		/* Send notifications if necessary */
		if ( ( $approving OR ( $pbr AND $this->hidden() === 0 ) ) )
		{
			$this->sendApprovedNotification();
		}

		/* Award points */
		$itemClass = \in_array( 'IPS\Content\Comment', class_parents( \get_called_class() ) ) ? static::$itemClass : \get_called_class();
		if ( $this instanceof \IPS\Content\Item )
		{
			$this->author()->achievementAction( 'core', 'NewContentItem', $this );
		}
		elseif ( $this instanceof \IPS\Content\Review )
		{
			$this->author()->achievementAction( 'core', 'Review', $this );
		}
		elseif ( $this instanceof \IPS\Content\Comment and ( ( $itemClass::$firstCommentRequired AND ! $this->isFirst() ) OR ( ! $itemClass::$firstCommentRequired ) ) )
		{
			$this->author()->achievementAction( 'core', 'Comment', $this );
		}
		
		/* Send webhook if necessary */
		if ( $approving )
		{
			\IPS\Api\Webhook::fire( str_replace( '\\', '', \substr( \get_called_class(), 3 ) ) . '_create', $this, $this->webhookFilters() );

			if ( $this instanceof \IPS\Content\Item and $itemClass::$firstCommentRequired === TRUE and $firstComment = $this->firstComment() )
			{
				\IPS\Api\Webhook::fire( str_replace( '\\', '', \substr( \get_class( $firstComment ), 3 ) ) . '_create', $firstComment, $firstComment->webhookFilters() );
			}
		}

		$this->expireWidgetCaches();
		$this->adjustSessions();
		
		try
		{
			\IPS\core\Approval::loadFromContent( \get_called_class(), $this->$idColumn )->delete();
		}
		catch( \OutOfRangeException $e ) { }
	}

	/**
	 * @brief	Hidden blurb cache
	 */
	protected $hiddenBlurb	= NULL;

	/**
	 * Blurb for when/why/by whom this content was hidden
	 *
	 * @return	string
	 */
	public function hiddenBlurb()
	{
		if ( !( $this instanceof \IPS\Content\Hideable ) or !static::$hideLogKey )
		{
			throw new \BadMethodCallException;
		}
		
		if( $this->hiddenBlurb === NULL )
		{
			try
			{
				$idColumn = static::$databaseColumnId;
				$log = \IPS\Db::i()->select( '*', 'core_soft_delete_log', array( 'sdl_obj_id=? AND sdl_obj_key=?', $this->$idColumn, static::$hideLogKey ) )->first();
				
				if ( $log['sdl_obj_member_id'] )
				{
					$this->hiddenBlurb = \IPS\Member::loggedIn()->language()->addToStack('hidden_blurb', FALSE, array( 'sprintf' => array( \IPS\Member::load( $log['sdl_obj_member_id'] )->name, \IPS\DateTime::ts( $log['sdl_obj_date'] )->relative(),  $log['sdl_obj_reason'] ?: \IPS\Member::loggedIn()->language()->addToStack('hidden_no_reason') ) ) );
				}
				else
				{
					$this->hiddenBlurb = \IPS\Member::loggedIn()->language()->addToStack('hidden_blurb_no_member', FALSE, array( 'sprintf' => array( \IPS\DateTime::ts( $log['sdl_obj_date'] )->relative(), $log['sdl_obj_reason'] ?: \IPS\Member::loggedIn()->language()->addToStack('hidden_no_reason') ) ) );
				}
			
			}
			catch ( \UnderflowException $e )
			{
				/* If this is requiring approval and has a logged reason */
				$hidden = $this->hidden();
				$item = NULL;
				if ( ( $this instanceof \IPS\Content\Comment ) )
				{
					$item = $this->item();
					/* If this is the first comment, and it's required, then we need to check the items hidden status instead */
					if ( $item::$firstCommentRequired AND $this->isFirst() AND $hidden !== 1 )
					{
						$hidden = $this->item()->hidden();
					}
				}

				if ( $hidden === 1 )
				{
					/* If moderator, show the reason */
					$reason = NULL;
					if( $this->modPermission( 'unhide' ) )
					{
						$reason = ( $item and $item::$firstCommentRequired AND $this->isFirst() ) ? $item->approvalQueueReason() : $this->approvalQueueReason();
					}
					
					if ( $reason )
					{
						$this->hiddenBlurb = \IPS\Member::loggedIn()->language()->addToStack( 'hidden_with_reason', FALSE, array( 'sprintf' => array( $reason ) ) );
					}
					else
					{
						/* Otherwise show a message that the content requires approval before it can be edited. */
						$this->hiddenBlurb = \IPS\Member::loggedIn()->language()->addToStack( 'hidden_awaiting_approval' );
					}
				}
				else
				{
					$this->hiddenBlurb = \IPS\Member::loggedIn()->language()->addToStack('hidden');
				}
			}
		}

		return $this->hiddenBlurb;
	}
	
	/**
	 * Blurb for when/why/by whom this content was deleted
	 *
	 * @return	string
	 * @throws \BadMethodCallException
	 */
	public function deletedBlurb()
	{
		if ( !( $this instanceof \IPS\Content\Hideable ) )
		{
			throw new \BadMethodCallException;
		}
		
		try
		{
			$idColumn = static::$databaseColumnId;
			$log = \IPS\core\DeletionLog::constructFromData( \IPS\Db::i()->select( '*', 'core_deletion_log', array( "dellog_content_class=? AND dellog_content_id=?", \get_class( $this ), $this->$idColumn ) )->first() );
			if( $log->_deleted_by )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'deletion_blurb', FALSE, array( 'sprintf' => array( $log->_deleted_by->name, $log->deleted_date->fullYearLocaleDate(), $log->deletion_date->fullYearLocaleDate() ) ) );
			}
			else
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'deletion_blurb_no_member', FALSE, array( 'sprintf' => array( $log->deletion_date->fullYearLocaleDate() ) ) );
			}
		}
		catch( \UnderflowException $e )
		{
			return \IPS\Member::loggedIn()->language()->addToStack('deleted');
		}
	}
	
	/**
	 * @brief	Reason for requiring approval
	 */
	protected $_approvalQueueReason = NULL;
	
	/**
	 * Approval Queue Reason
	 *
	 * @return	bool|string
	 */
	public function approvalQueueReason()
	{
		if ( $this->_approvalQueueReason === NULL )
		{
			try
			{
				$idColumn = static::$databaseColumnId;
				$this->_approvalQueueReason = \IPS\core\Approval::loadFromContent( \get_class( $this ), $this->$idColumn )->reason();
			}
			catch( \OutOfRangeException $e )
			{
				$this->_approvalQueueReason = NULL;
			}
		}
		return $this->_approvalQueueReason;
	}
	
	/**
	 * Can promote this comment/item?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	boolean
	 */
	public function canPromoteToSocialMedia( $member=NULL )
	{
		return \IPS\core\Promote::canPromote( $member );
	}

	/**
	 * @brief	Have we already reported?
	 */
	protected $alreadyReported = NULL;
	
	/**
	 * Can report?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	TRUE|string			TRUE or a language string for why not
	 * @note	This requires a few queries, so don't run a check in every template
	 */
	public function canReport( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* Is this type of comment reportabe? */
		if ( !( \IPS\IPS::classUsesTrait( $this, 'IPS\Content\Reportable' ) ) )
		{
			return 'generic_error';
		}
		
		/* Can the member report content? */
		$classToCheck = ( $this instanceof \IPS\Content\Comment or $this instanceof \IPS\Content\Review ) ? \get_class( $this->item() ) : \get_class( $this );

		if ( $member->group['g_can_report'] != '1' AND !\in_array( $classToCheck, explode( ',', $member->group['g_can_report'] ) ) )
		{
			return 'no_module_permission';
		}
		
		/* Can they view this? */
		if ( !$this->canView() )
		{
			return 'no_module_permission';
		}

		/* Have they already subitted a report? */
		if( $this->alreadyReported === TRUE )
		{
			return 'report_err_already_reported';
		}
		elseif( $this->alreadyReported === NULL )
		{
			/* Have we already prefetched it? */
			if ( ! isset( $this->reportData ) )
			{
				try
				{
					$idColumn = static::$databaseColumnId;
					$report = \IPS\Db::i()->select( 'id', 'core_rc_index', array( 'class=? AND content_id=?', \get_called_class(), $this->$idColumn ) )->first();
					$this->reportData = \IPS\Db::i()->select( '*', 'core_rc_reports', array( 'rid=? AND report_by=?', $report, $member->member_id ) )->first();
				}
				catch( \UnderflowException $e ){}
			}
			
			/* Check again */
			if ( isset( $this->reportData ) AND \is_array( $this->reportData ) )
			{
				if ( \IPS\Settings::i()->automoderation_report_again_mins )
				{ 
					if ( ( ( time() - $this->reportData['date_reported'] ) / 60 ) > \IPS\Settings::i()->automoderation_report_again_mins )
					{
						return TRUE;
					}
				}
				
				$this->alreadyReported = TRUE;
				return 'report_err_already_reported';
			}
			
			$this->alreadyReported = FALSE;
		}
		
		return TRUE;
	}

	/**
	 * Can report or revoke report?
	 * This method will return TRUE if the link to report content should be shown (which can occur even if you have already reported if you have permission to revoke your report)
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 * @note	This requires a few queries, so don't run a check in every template
	 */
	public function canReportOrRevoke( $member=NULL )
	{
		/* If we are allowed to report, then we can return TRUE. */
		if( $this->canReport( $member ) === TRUE )
		{
			return TRUE;
		}
		/* If we have already reported but automatic moderation is enabled, show the link so the user can revoke their report. */
		elseif( $this->alreadyReported === TRUE AND \IPS\Settings::i()->automoderation_enabled )
		{
			return TRUE;
		}

		return FALSE;
	}
	
	/**
	 * Report
	 *
	 * @param	string					$reportContent	Report content message from member
	 * @param	int						$reportType		Report type (see constants in \IPS\core\Reports\Report)
	 * @param	\IPS\Member|NULL		$member			Member making the report (or NULL for loggedIn())
	 * @return	\IPS\core\Reports\Report
	 * @throws	\UnexpectedValueException	If there is a permission error - you should only call this method after checking canReport
	 */
	public function report( $reportContent, $reportType=1, $member=NULL )
	{
		$member = ( $member ) ? $member : \IPS\Member::loggedIn();

		/* Permission check */
		$permCheck = $this->canReport( $member );
		if ( $permCheck !== TRUE )
		{
			throw new \UnexpectedValueException( $permCheck );
		}

		/* Find or create an index */
		$idColumn = static::$databaseColumnId;
		$item = ( $this instanceof \IPS\Content\Comment ) ? $this->item() : $this;
		$itemIdColumn = $item::$databaseColumnId;

		try
		{
			$index = \IPS\core\Reports\Report::load( $this->$idColumn, 'content_id', array( 'class=?', \get_called_class() ) );
			$index->num_reports = $index->num_reports + 1;
		}
		catch ( \OutOfRangeException $e )
		{
			$index = new \IPS\core\Reports\Report;
			$index->class = \get_called_class();
			$index->content_id = $this->$idColumn;
			$index->perm_id = $this->permId();
			$index->first_report_by = (int) $member->member_id;
			$index->first_report_date = time();
			$index->last_updated = time();
			$index->author = (int) $this->author()->member_id;
			$index->num_reports = 1;
			$index->num_comments = 0;
			$index->auto_moderation_exempt = 0;
			$index->item_id = $item->$itemIdColumn;
			$index->node_id = $item->containerWrapper() ? $item->containerWrapper()->_id : 0;
		}

		/* Only set this to a new report if it is not already under review */
		if( $index->status != 2 )
		{
			$index->status = 1;
		}

		$index->save();

		/* Create a report */
		$reportInsert = array(
			'rid'			=> $index->id,
			'report'		=> $reportContent,
			'report_by'		=> (int) $member->member_id,
			'date_reported'	=> time(),
			'ip_address'	=> \IPS\Request::i()->ipAddress(),
			'report_type'	=> $member->member_id ? $reportType : 0
		);
		
		$insertID = \IPS\Db::i()->insert( 'core_rc_reports', $reportInsert );
		$reportInsert['id'] = $insertID;
		
		/* Run automatic moderation */
		$index->runAutomaticModeration();

		/* Send notification to mods */
		$moderators = array( 'm' => array(), 'g' => array() );
		foreach ( \IPS\Db::i()->select( '*', 'core_moderators' ) as $mod )
		{
			$canView = FALSE;
			if ( $mod['perms'] == '*' )
			{
				$canView = TRUE;
			}
			if ( $canView === FALSE )
			{
				$perms = json_decode( $mod['perms'], TRUE );

				if ( isset( $perms['can_view_reports'] ) AND $perms['can_view_reports'] === TRUE )
				{
					$canView = TRUE;
				}

				/* Got nodes? */
				if ( $canView === TRUE and $container = $item->containerWrapper() and isset( $container::$modPerm ) )
				{
					if ( isset( $perms[ $container::$modPerm ] ) and $perms[ $container::$modPerm ] != '*' and $perms[ $container::$modPerm ] != -1 )
					{
						if ( empty( $perms[ $container::$modPerm ] ) or ! in_array( $item->mapped('container'), $perms[ $container::$modPerm ] ) )
						{
							$canView = FALSE;
						}
					}
				}
			}
			if ( $canView === TRUE )
			{
				$moderators[ $mod['type'] ][] = $mod['id'];
			}
		}

		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'report_center', $index, array( $index, $reportInsert, $this ) );
		foreach ( \IPS\Db::i()->select( '*', 'core_members', ( \count( $moderators['m'] ) ? \IPS\Db::i()->in( 'member_id', $moderators['m'] ) . ' OR ' : '' ) . \IPS\Db::i()->in( 'member_group_id', $moderators['g'] ) . ' OR ' . \IPS\Db::i()->findInSet( 'mgroup_others', $moderators['g'] ) ) as $mem )
		{
			$memberObj = \IPS\Member::constructFromData( $mem );
			
			/* Members may have individual member level mod permissions, but are also in a group that has moderator permissions. In this case, the member level restrictions always win so we need to recheck those now that we have a member object. See \IPS\Member::modPermissions(). */
			$perms = $memberObj->modPermissions();
			$canView = $this->canView( $memberObj );
			if ( $canView === TRUE and $container = $item->containerWrapper() and isset( $container::$modPerm ) )
			{
				if ( isset( $perms[ $container::$modPerm ] ) and $perms[ $container::$modPerm ] != '*' and $perms[ $container::$modPerm ] != -1 )
				{
					if ( empty( $perms[ $container::$modPerm ] ) or ! in_array( $item->mapped('container'), $perms[ $container::$modPerm ] ) )
					{
						$canView = FALSE;
					}
				}
			}
			
			if( $canView === TRUE )
			{
				$notification->recipients->attach( $memberObj );
			}
		}
		$notification->send();
		
		/* Set flag so future calls to report methods return correct value */
		$this->alreadyReported = TRUE;
		$this->reportData = $reportInsert;

		/* Return */
		return $index;
	}

	/**
	 * Change IP Address
	 * @param	string		$ip		The new IP address
	 *
	 * @return void
	 */
	public function changeIpAddress( $ip )
	{
		if ( isset( static::$databaseColumnMap['ip_address'] ) )
		{
			$col = static::$databaseColumnMap['ip_address'];
			$this->$col = (string) $ip;
			$this->save();
		}
	}
	
	/**
	 * Change Author
	 *
	 * @param	\IPS\Member	$newAuthor	The new author
	 * @param	bool		$log		If TRUE, action will be logged to moderator log
	 * @return	void
	 */
	public function changeAuthor( \IPS\Member $newAuthor, $log=TRUE )
	{
		$oldAuthor = $this->author();

		/* If we delete a member, then change author, the old author returns 0 as does the new author as the
		   member row is deleted before the task is run */
		if( $newAuthor->member_id and ( $oldAuthor->member_id == $newAuthor->member_id ) )
		{
			return;
		}

		foreach ( array( 'author', 'author_name', 'edit_member_name', 'is_anon' ) as $k )
		{
			if ( isset( static::$databaseColumnMap[ $k ] ) )
			{
				$col = static::$databaseColumnMap[ $k ];
				switch ( $k )
				{
					case 'author':
						$this->$col = $newAuthor->member_id ? $newAuthor->member_id : 0;
						break;
					case 'author_name':
						$this->$col = $newAuthor->member_id ? $newAuthor->name : $newAuthor->real_name;
						break;
					case 'edit_member_name':
						/* Real name will contain the custom guest name if available or '' if not.
						   But we only want to update the user if oldName is the same as the current edit_member_name
						   So if "Bob" edited his own post, you want that to change if Bob becomes Bob2.
						   But if a moderator edited it, we don't want to change that name. */
						if ( $oldAuthor->name == $this->$col )
						{
							$this->$col = $newAuthor->member_id ? $newAuthor->name : $newAuthor->real_name;
						}
						break;
					case 'is_anon':
						/* We are specifying an author so turn off is_anon column */
						if ( $newAuthor->member_id )
						{
							$this->$col = 0;
						}
						break;
				}
			}
		}

		$this->save();

		if ( \IPS\Dispatcher::hasInstance() and \IPS\Dispatcher::i()->controllerLocation == 'front' and $log )
		{
			\IPS\Session::i()->modLog( 'modlog__action_changeauthor', array( static::$title => TRUE, $this->url()->__toString() => FALSE, $this->mapped('title') ?: ( method_exists( $this, 'item' ) ? $this->item()->mapped('title') : NULL ) => FALSE ), ( $this instanceof \IPS\Content\Item ) ? $this : $this->item() );
		}
	}
	
	/**
	 * Get language articles for the given container
	 * 
	 * @param 	string 		$itemClass 		The classname of this content
	 * @param	array|NULL	$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 */
	public static function articlesFromIndexData( $itemClass, ?array $containerData ): array
	{
		/* Object URL */
		$indefiniteArticle = static::_indefiniteArticle( $containerData );
		$definiteArticle = static::_definiteArticle( $containerData );
		$definiteArticleUc = static::_definiteArticle( $containerData, NULL, array( 'ucfirst' => TRUE ) );
		
		if ( \in_array( 'IPS\Content\Comment', class_parents( \get_called_class() ) ) )
		{			
			$indefiniteArticle = $itemClass::_indefiniteArticle( $containerData );
			$definiteArticle = $itemClass::_definiteArticle( $containerData );
			$definiteArticleUc = $itemClass::_definiteArticle( $containerData, NULL, array( 'ucfirst' => TRUE ) );
		}
		
		return array( 'indefinite' => $indefiniteArticle, 'definite' => $definiteArticle, 'definite_uc' => $definiteArticleUc );
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
		/* Item details */
		$itemClass = $indexData['index_class'];
		if ( \in_array( 'IPS\Content\Comment', class_parents( \get_called_class() ) ) )
		{
			$itemClass = static::$itemClass;
			$unread = $itemClass::unreadFromData( NULL, $indexData['index_date_updated'], $indexData['index_date_created'], $indexData['index_item_id'], $indexData['index_container_id'], FALSE );
		}
		else
		{
			$unread = static::unreadFromData( NULL, $indexData['index_date_updated'], $indexData['index_date_created'], $indexData['index_item_id'], $indexData['index_container_id'], FALSE );
		}
		$itemUrl = $itemClass::urlFromIndexData( $indexData, $itemData, 'getPrefComment' );
		
		/* Object URL */
		if ( \in_array( 'IPS\Content\Comment', class_parents( \get_called_class() ) ) )
		{
			if ( \in_array( 'IPS\Content\Review', class_parents( \get_called_class() ) ) )
			{
				$objectUrl = $itemUrl->setQueryString( array( 'do' => 'findReview', 'review' => $indexData['index_object_id'] ) );
				$showRepUrl = $itemUrl->setQueryString( array( 'do' => 'showReactionsReview', 'review' => $indexData['index_object_id'] ) );
			}
			else
			{
				$objectUrl = $itemUrl->setQueryString( array( 'do' => 'findComment', 'comment' => $indexData['index_object_id'] ) );
				$showRepUrl = $itemUrl->setQueryString( array( 'do' => 'showReactionsComment', 'comment' => $indexData['index_object_id'] ) );
			}
		}
		else
		{
			$objectUrl = $itemUrl;
			$showRepUrl = $itemUrl->setQueryString( 'do', 'showReactions' );
		}
		
		/* Articles language */
		$articles = static::articlesFromIndexData( $itemClass, $containerData );
		
		/* Container details */
		$containerUrl = NULL;
		$containerTitle = NULL;
		if ( isset( $itemClass::$containerNodeClass ) )
		{
			$containerClass	= $itemClass::$containerNodeClass;
			$containerTitle	= $containerClass::titleFromIndexData( $indexData, $itemData, $containerData );
			$containerUrl	= $containerClass::urlFromIndexData( $indexData, $itemData, $containerData );
		}
				
		/* Reputation - if we are showing the total value, then we need to load them up and total up all of the values */
		if ( \IPS\Settings::i()->reaction_count_display == 'count' )
		{
			$repCount = 0;
			foreach( $reputationData AS $memberId => $reactionId )
			{
				try
				{
					$repCount += \IPS\Content\Reaction::load( $reactionId )->value;
				}
				catch( \OutOfRangeException $e ) {}
			}
		}
		else
		{
			$repCount = \count( $reputationData );
		}
		
		/* Snippet */
		$snippet = static::searchResultSnippet( $indexData, $authorData, $itemData, $containerData, $reputationData, $reviewRating, $view );
		
		if ( $template === NULL )
		{
			$template = array( \IPS\Theme::i()->getTemplate( 'system', 'core', 'front' ), 'searchResult' );
		}
		
		/* Return */
		return $template( $indexData, $articles, $authorData, $itemData, $unread, $asItem ? $itemUrl : $objectUrl, $itemUrl, $containerUrl, $containerTitle, $repCount, $showRepUrl, $snippet, $iPostedIn, $view, $canIgnoreComments, $reactions );
	}
	
	public static function searchResultBlock(): array
	{
		return array( \IPS\Theme::i()->getTemplate( 'widgets', 'core', 'front' ), 'streamItem' );
	}
	
	/**
	 * Get snippet HTML for search result display
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$authorData		Basic data about the author. Only includes columns returned by \IPS\Member::columnsForPhoto()
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	array|NULL	$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	array		$reputationData	Array of people who have given reputation and the reputation they gave
	 * @param	int|NULL	$reviewRating	If this is a review, the rating
	 * @param	string		$view			'expanded' or 'condensed'
	 * @return	callable
	 */
	public static function searchResultSnippet( array $indexData, array $authorData, array $itemData, ?array $containerData, array $reputationData, $reviewRating, $view )
	{		
		return $view == 'expanded' ? \IPS\Theme::i()->getTemplate( 'system', 'core', 'front' )->searchResultSnippet( $indexData, $itemData ) : '';
	}

	/**
	 * Return the language string key to use in search results
	 *
	 * @note Normally we show "(user) posted a (thing) in (area)" but sometimes this may not be accurate, so this is abstracted to allow
	 *	content classes the ability to override
	 * @param	array 		$authorData		Author data
	 * @param	array 		$articles		Articles language strings
	 * @param	array 		$indexData		Search index data
	 * @param	array 		$itemData		Data about the item
	 * @param   bool        $includeLinks   Include links to member profile
	 * @return	string
	 */
	public static function searchResultSummaryLanguage( $authorData, $articles, $indexData, $itemData, $includeLinks = TRUE )
	{
		if( $includeLinks )
		{
			$authorTemplate = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->userLinkFromData( $authorData['member_id'], $authorData['name'], $authorData['members_seo_name'], $authorData['member_group_id'] ?? \IPS\Settings::i()->guest_group, NULL, $indexData['index_is_anon'] );
		}
		else
		{
			$authorTemplate = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->userNameFromData( $authorData['name'], $authorData['member_group_id'] ?? \IPS\Settings::i()->guest_group, NULL, $indexData['index_is_anon'] );
		}

		if( \in_array( 'IPS\Content\Comment', class_parents( $indexData['index_class'] ) ) )
		{
			if( isset( $itemData['author'] ) )
			{
				if( $includeLinks )
				{
					$itemAuthorTemplate = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->userLinkFromData( $itemData['author']['member_id'], $itemData['author']['name'], $itemData['author']['members_seo_name'], $itemData['author']['member_group_id'] ?? \IPS\Settings::i()->guest_group );
				}
				else
				{
					$itemAuthorTemplate = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->userNameFromData( $itemData['author']['name'], $itemData['author']['member_group_id'] ?? \IPS\Settings::i()->guest_group );
				}
			}

			if( \in_array( 'IPS\Content\Review', class_parents( $indexData['index_class'] ) ) )
			{
				if( isset( $itemData['author'] ) )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( "user_other_activity_review", FALSE, array( 'sprintf' => array( $articles['definite'] ), 'htmlsprintf' => array( $authorTemplate, $itemAuthorTemplate ) ) );
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack( "user_own_activity_review", FALSE, array( 'sprintf' => array( $articles['indefinite'] ), 'htmlsprintf' => array( $authorTemplate ) ) );
				}
			}
			else
			{
				if( static::$firstCommentRequired )
				{
					if( $indexData['index_title'] )
					{
						return \IPS\Member::loggedIn()->language()->addToStack( "user_own_activity_item", FALSE, array( 'sprintf' => array( $articles['indefinite'] ), 'htmlsprintf' => array( $authorTemplate ) ) );
					}
					else
					{
						if( isset( $itemData['author'] ) )
						{
							return \IPS\Member::loggedIn()->language()->addToStack( "user_other_activity_reply", FALSE, array( 'sprintf' => array( $articles['definite'] ), 'htmlsprintf' => array( $authorTemplate, $itemAuthorTemplate ) ) );
						}
						else
						{
							return \IPS\Member::loggedIn()->language()->addToStack( "user_own_activity_reply", FALSE, array( 'sprintf' => array( $articles['indefinite'] ), 'htmlsprintf' => array( $authorTemplate ) ) );
						}
					}
				}
				else
				{
					if( isset( $itemData['author'] ) )
					{
						return \IPS\Member::loggedIn()->language()->addToStack( "user_other_activity_comment", FALSE, array( 'sprintf' => array( $articles['definite'] ), 'htmlsprintf' => array( $authorTemplate, $itemAuthorTemplate ) ) );
					}
					else
					{
						return \IPS\Member::loggedIn()->language()->addToStack( "user_own_activity_comment", FALSE, array( 'sprintf' => array( $articles['indefinite'] ), 'htmlsprintf' => array( $authorTemplate ) ) );
					}
				}
			}
		}
		else
		{
			if ( isset( static::$databaseColumnMap['author'] ) )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( "user_own_activity_item", FALSE, array( 'sprintf' => array( $articles['indefinite'] ), 'htmlsprintf' => array( $authorTemplate ) ) );
			}
			else
			{
				return \IPS\Member::loggedIn()->language()->addToStack( "generic_activity_item", FALSE, array( 'sprintf' => array( $articles['definite_uc'] ) ) );
			}
		}
	}

	/**
	 * @brief	Return a classname applied to the search result block
	 */
	public static $searchResultClassName = '';

	/**
	 * Return the filters that are available for selecting table rows
	 *
	 * @return	array
	 */
	public static function getTableFilters()
	{
		$return = array();
		
		if ( \in_array( 'IPS\Content\Hideable', class_implements( \get_called_class() ) ) )
		{
			$return[] = 'hidden';
			$return[] = 'unhidden';
			$return[] = 'unapproved';
		}
				
		return $return;
	}
	
	/**
	 * Get content table states
	 *
	 * @return string
	 */
	public function tableStates()
	{
		$return	= array();

		if ( $this instanceof \IPS\Content\Hideable )
		{
			switch ( $this->hidden() )
			{
				case -1:
					$return[] = 'hidden';
					break;
				case 0:
					$return[] = 'unhidden';
					break;
				case 1:
					$return[] = 'unapproved';
					break;
			}
		}
		
		return implode( ' ', $return );
		
	}
	
	/**
	 * Prune IP addresses from content
	 *
	 * @param	int		$days 		Remove from content posted older than DAYS ago
	 * @return	void
	 */
	public static function pruneIpAddresses( $days=0 )
	{
		if ( $days and isset( static::$databaseColumnMap['ip_address'] ) and isset( static::$databaseColumnMap['date'] ) )
		{
			$time = time() - ( 86400 * $days );
			\IPS\Db::i()->update( static::$databaseTable, array( static::$databasePrefix . static::$databaseColumnMap['ip_address'] => '' ), array( static::$databasePrefix . static::$databaseColumnMap['ip_address'] . "!='' AND " . static::$databasePrefix . static::$databaseColumnMap['date'] . ' <= ' . $time ) );
		}
	}
	
	/**
	 * Log a row in core_post_before_registering
	 *
	 * @param	string	$guestEmail	Guest email address
	 * @param	string	$key		User's existing post_before_register cookie value
	 * @return	string	The new key, if one wasn't provided
	 */
	public function _logPostBeforeRegistering( $guestEmail, $key = NULL )
	{
		$key = $key ?: \IPS\Login::generateRandomString();
		
		$idColumn = static::$databaseColumnId;
		\IPS\Db::i()->insert( 'core_post_before_registering', array(
			'email'		=> $guestEmail,
			'class'		=> \get_class( $this ),
			'id'		=> $this->$idColumn,
			'timestamp'	=> time(),
			'secret'	=> $key,
			'language'	=> \IPS\Member::loggedIn()->language()->id
		) );
		
		/* enable followup task */
		\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 1 ), "`key`='postBeforeRegisterFollowup'" );
		
		return $key;
	}
	
	/**
	 * Get content for an email
	 *
	 * @param	\IPS\Email	$email			The email
	 * @param	string		$type			'html' or 'plaintext'
	 * @param	bool		$includeLinks	Whether or not to include links
	 * @param	bool		$includeAuthor	Whether or not to include the author
	 * @return	string
	 */
	public function emailContent( \IPS\Email $email, $type, $includeLinks=TRUE, $includeAuthor=TRUE )
	{
		return \IPS\Email::template( 'core', '_genericContent', $type, array( $this, $includeLinks, $includeAuthor, $email ) );
	}

	/**
	 * Returns "Foo posted {{indefart}} in {{container}}, {{date}}
	 *
	 * @param	\IPS\Lang|NULL	$plaintextLanguage	If specified, will return plaintext (not linking the user or the container in the language specified). If NULL, returns with links based on logged in user's theme and language
	 * @note	This function was extracted from IPS\Promote
	 * @return	string
	 */
	public function objectMetaDescription( $plaintextLanguage=NULL )
	{
		$object = $this;
		$author = $this->author();

		if ( $object instanceof \IPS\Content\Item )
		{
			$container = $object->containerWrapper();

			if ( $container )
			{
				if ( !$plaintextLanguage )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'promote_metadescription_container', FALSE, array(
						'htmlsprintf'	=> array( $author->link(), \IPS\DateTime::ts( $this->mapped('date')  )->html( FALSE ) ),
						'sprintf'		=> array( $object->indefiniteArticle(), $container->url(), $container->_title ),
					) );
				}
				else
				{
					return $plaintextLanguage->addToStack( 'promote_metadescription_container_nolink', FALSE, array(
						'sprintf'		=> array( $object->indefiniteArticle( $plaintextLanguage ), $container->getTitleForLanguage( $plaintextLanguage ), $author->name, \IPS\DateTime::ts( $this->mapped('date') ) ),
					) );
				}
			}
			else
			{
				if ( !$plaintextLanguage )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'promote_metadescription_nocontainer', FALSE, array(
						'htmlsprintf'	=> array( $author->link(), \IPS\DateTime::ts( $this->mapped('date')  )->html( FALSE ) ),
						'sprintf'		=> array( $object->indefiniteArticle() )
					) );
				}
				else
				{
					return $plaintextLanguage->addToStack( 'promote_metadescription_nocontainer', FALSE, array(
						'sprintf'		=> array( $object->indefiniteArticle( $plaintextLanguage ), $author->name, \IPS\DateTime::ts( $this->mapped('date') ) )
					) );
				}
			}
		}
		else if ( $object instanceof \IPS\Content\Comment )
		{
			if ( !$plaintextLanguage )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'promote_metadescription_nocontainer', FALSE, array(
					'htmlsprintf'	=> array( $author->link(), \IPS\DateTime::ts( $this->mapped('date') )->html( FALSE ) ),
					'sprintf'		=> array( $object->indefiniteArticle() )
				) );
			}
			else
			{
				return $plaintextLanguage->addToStack( 'promote_metadescription_nocontainer', FALSE, array(
					'sprintf'		=> array( $object->indefiniteArticle( $plaintextLanguage ), $author->name, \IPS\DateTime::ts( $this->mapped('date') ) )
				) );
			}
		}

		throw new \OutofRangeException('object_not_valid');
	}
	
	/**
	 * Get a count of the database table
	 *
	 * @param   bool    $approximate     Accept an approximate result if the table is large (approximate results are faster on large tables)
	 * @return  int
	 */
	public static function databaseTableCount( bool $approximate=FALSE ): int
	{
		$key = 'tbl_cnt_' . ( $approximate ? 'approx_' : 'accurate_' ) . static::$databaseTable;
		$fetchAgain = FALSE;
		
		if ( ! isset( \IPS\Data\Store::i()->$key ) )
		{
			$fetchAgain = TRUE;
		}
		else
		{
			/* Just check daily */
			$data = \IPS\Data\Store::i()->$key;
			
			if ( $data['time'] < time() - 86400 )
			{
				$fetchAgain = TRUE;
			}
		}

		if ( $fetchAgain )
		{
			$count = 0;
			/* Accept approximate result? */
			if ( $approximate )
			{
				$approxRows = \IPS\Db::i()->query( "SHOW TABLE STATUS LIKE '" . \IPS\Db::i()->prefix . static::$databaseTable . "';" )->fetch_assoc();
				$count = (int) $approxRows['Rows'];
			}

			/* If the table is a reasonable size, we'll get the real value instead */
			if ( $count < 1000000 )
			{
				$count = \IPS\Db::i()->select( 'COUNT(*)', static::$databaseTable )->first();
			}
			\IPS\Data\Store::i()->$key = array( 'time' => time(), 'count' => $count );
		}

		$data = \IPS\Data\Store::i()->$key;
		return $data['count'];
	}
	
	/* !Follow */

	/**
	 * @brief	Follow publicly
	 */
	const FOLLOW_PUBLIC = 1;

	/**
	 * @brief	Follow anonymously
	 */
	const FOLLOW_ANONYMOUS = 2;

	/**
	 * @brief	Number of notifications to process per batch
	 */
	const NOTIFICATIONS_PER_BATCH = \IPS\NOTIFICATIONS_PER_BATCH;
	
	/**
	 * Send notifications
	 *
	 * @return	void
	 */
	public function sendNotifications()
	{		
		/* Send quote and mention notifications */
		$sentTo = $this->sendQuoteAndMentionNotifications();
		
		/* How many followers? */
		try
		{
			$count = $this->notificationRecipients( NULL, NULL, TRUE );
		}
		catch ( \BadMethodCallException $e )
		{
			return;
		}
		
		/* Queue if there's lots, or just send them */
		if ( $count > \IPS\NOTIFICATION_BACKGROUND_THRESHOLD )
		{
			$idColumn = $this::$databaseColumnId;
			\IPS\Task::queue( 'core', 'Follow', array( 'class' => \get_class( $this ), 'item' => $this->$idColumn, 'sentTo' => $sentTo, 'followerCount' => $count ), 2 );
		}
		else
		{
			$this->sendNotificationsBatch( 0, $sentTo );
		}
	}
	
	/**
	 * Send notifications batch
	 *
	 * @param	int				$offset		Current offset
	 * @param	array			$sentTo		Members who have already received a notification and how - e.g. array( 1 => array( 'inline', 'email' )
	 * @param	string|NULL		$extra		Additional data
	 * @return	int|null		New offset or NULL if complete
	 */
	public function sendNotificationsBatch( $offset=0, &$sentTo=array(), $extra=NULL )
	{
		/* Check authors spam status */
		if( $this->author()->members_bitoptions['bw_is_spammer'] )
		{
			/* Author is flagged as spammer, don't send notifications */
			return NULL;
		}

		$followIds = array();
		$followers = $this->notificationRecipients( array( $offset, static::NOTIFICATIONS_PER_BATCH ), $extra );
		
		/* If $followers is NULL (which can be the case if the follows are just people following the author), just return as there is nothing to do */
		if ( $followers === NULL )
		{
			return NULL;
		}
		
		/* If we're still here, our iterator may not necessarily be one that implements Countable so we need to convert it to an array. */
		$followers = iterator_to_array( $followers );
		
		if( !\count( $followers ) )
		{
			return NULL;
		}

		/* Send notification */
		$notification = $this->createNotification( $extra );
		$notification->unsubscribeType = 'follow';
		foreach ( $followers as $follower )
		{
			$member = \IPS\Member::load( $follower['follow_member_id'] );
			if ( $member != $this->author() and $this->canView( $member ) )
			{
				$followIds[] = $follower['follow_id'];
				$notification->recipients->attach( $member, $follower );
			}
		}

		/* Log that we sent it */
		if( \count( $followIds ) )
		{
			\IPS\Db::i()->update( 'core_follow', array( 'follow_notify_sent' => time() ), \IPS\Db::i()->in( 'follow_id', $followIds ) );
		}

		$sentTo = $notification->send( $sentTo );
		
		/* Update the queue */
		return $offset + static::NOTIFICATIONS_PER_BATCH;
	}
	
	/**
	 * Send Approved Notification
	 *
	 * @return	void
	 */
	public function sendApprovedNotification()
	{
		$this->sendNotifications();
		$this->sendAuthorApprovalNotification();
	}

	/**
	 * Send Author Approval Notification
	 *
	 * @return  void
	 */
	public function sendAuthorApprovalNotification()
	{
		/* Tell the author their content has been approved */
		$member = \IPS\Member::load( $this->mapped('author') );
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'approved_content', $this, array( $this ), array(), FALSE );
		$notification->recipients->attach( $member );
		$notification->send();
	}
	
	/**
	 * Send Unapproved Notification
	 *
	 * @return	void
	 */
	public function sendUnapprovedNotification()
	{
		$moderators = array( 'g' => array(), 'm' => array() );
		foreach( \IPS\Db::i()->select( '*', 'core_moderators' ) AS $mod )
		{
			$canView = FALSE;
			$canApprove = FALSE;
			if ( $mod['perms'] == '*' )
			{
				$canView = TRUE;
				$canApprove = TRUE;
			}
			else
			{
				$perms = json_decode( $mod['perms'], TRUE );
								
				foreach ( array( 'canView' => 'can_view_hidden_', 'canApprove' => 'can_unhide_' ) as $varKey => $modPermKey )
				{
					if ( isset( $perms[ $modPermKey . 'content' ] ) AND $perms[ $modPermKey . 'content' ] )
					{
						$$varKey = TRUE;
					}
					else
					{						
						try
						{
							$container = ( $this instanceof \IPS\Content\Comment ) ? $this->item()->container() : $this->container();
							$containerClass = \get_class( $container );
							$title = static::$title;
							if
							(
								isset( $containerClass::$modPerm )
								and
								(
									$perms[ $containerClass::$modPerm ] === -1
									or
									(
										\is_array( $perms[ $containerClass::$modPerm ] )
										and
										\in_array( $container->_id, $perms[ $containerClass::$modPerm ] )
									)
								)
								and
								$perms["{$modPermKey}{$title}"]
							)
							{
								$$varKey = TRUE;
							}
						}
						catch ( \BadMethodCallException $e ) { }
					}
				}
			}
			if ( $canView === TRUE and $canApprove === TRUE )
			{
				$moderators[ $mod['type'] ][] = $mod['id'];
			}
		}
						
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'unapproved_content', $this, array( $this, $this->author() ) );
		foreach ( \IPS\Db::i()->select( '*', 'core_members', ( \count( $moderators['m'] ) ? \IPS\Db::i()->in( 'member_id', $moderators['m'] ) . ' OR ' : '' ) . \IPS\Db::i()->in( 'member_group_id', $moderators['g'] ) . ' OR ' . \IPS\Db::i()->findInSet( 'mgroup_others', $moderators['g'] ) ) as $member )
		{
			$member = \IPS\Member::constructFromData( $member );
			/* We don't need to notify the author of the content or when member cannot view this content*/
			if( $this->author()->member_id != $member->member_id AND $this->canView( $member ) )
            {
                $notification->recipients->attach( $member );
            }
		}
		$notification->send();
	}
	
	/**
	 * Send the notifications after the content has been edited (for any new quotes or mentiones)
	 *
	 * @param	string	$oldContent	The content before the edit
	 * @return	void
	 */
	public function sendAfterEditNotifications( $oldContent )
	{				
		$existingData = static::_getQuoteAndMentionIdsFromContent( $oldContent );
		$this->sendQuoteAndMentionNotifications( array_unique( array_merge( $existingData['quotes'], $existingData['mentions'], $existingData['embeds'] ) ) );
	}
		
	/**
	 * Send quote and mention notifications
	 *
	 * @param	array	$exclude		An array of member IDs *not* to send notifications to
	 * @return	array	The members that were notified and how they were notified
	 */
	protected function sendQuoteAndMentionNotifications( $exclude=array() )
	{
		return $this->_sendQuoteAndMentionNotifications( static::_getQuoteAndMentionIdsFromContent( $this->content() ), $exclude );
	}
	
	/**
	 * Send quote and mention notifications from data
	 *
	 * @param	array	$data		array( 'quotes' => array( ... member IDs ... ), 'mentions' => array( ... member IDs ... ), 'embeds' => array( ... member IDs ... ) )
	 * @param	array	$exclude	An array of member IDs *not* to send notifications to
	 * @return	array	The members that were notified and how they were notified
	 */
	protected function _sendQuoteAndMentionNotifications( $data, $exclude=array() )
	{
		/* Init */
		$sentTo = array();
		
		/* Quotes */
		$data['quotes'] = array_filter( $data['quotes'], function( $v ) use ( $exclude )
		{
			return !\in_array( $v, $exclude );
		} );
		if ( !empty( $data['quotes'] ) )
		{
			$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'quote', $this, array( $this ), array( $this->author()->member_id ) );
			foreach ( $data['quotes'] as $quote )
			{
				$member = \IPS\Member::load( $quote );
				if ( $member->member_id and $member != $this->author() and $this->canView( $member ) and !$member->isIgnoring( $this->author(), 'posts' ) )
				{
					$notification->recipients->attach( $member );
				}
			}
			$sentTo = $notification->send( $sentTo );
		}
		
		/* Mentions */
		$data['mentions'] = array_filter( $data['mentions'], function( $v ) use ( $exclude )
		{
			return !\in_array( $v, $exclude );
		} );
		if ( !empty( $data['mentions'] ) )
		{
			$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'mention', $this, array( $this ), array( $this->author()->member_id ) );
			foreach ( $data['mentions'] as $mention )
			{
				$member = \IPS\Member::load( $mention );
				if ( $member->member_id AND $member != $this->author() and $this->canView( $member ) and !$member->isIgnoring( $this->author(), 'mentions' ) )
				{
					$notification->recipients->attach( $member );
				}
			}
			$sentTo = $notification->send( $sentTo );
		}

		/* Embeds */
		$data['embeds'] = array_filter( $data['embeds'], function( $v ) use ( $exclude )
		{
			return !\in_array( $v, $exclude );
		} );
		if ( !empty( $data['embeds'] ) )
		{
			$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'embed', $this, array( $this ), array( $this->author()->member_id ) );
			foreach ( $data['embeds'] as $embed )
			{
				$member = \IPS\Member::load( $embed );
				if ( $member->member_id AND $member != $this->author() and $this->canView( $member ) and !$member->isIgnoring( $this->author(), 'posts' ) )
				{
					$notification->recipients->attach( $member );
				}
			}
			$sentTo = $notification->send( $sentTo );
		}
	
		/* Return */
		return $sentTo;
	}
	
	/**
	 * Get quote and mention notifications
	 *
	 * @param	string	$content	The content
	 * @return	array	array( 'quotes' => array( ... member IDs ... ), 'mentions' => array( ... member IDs ... ), 'embeds' => array( ... member IDs ... )  )
	 */
	protected static function _getQuoteAndMentionIdsFromContent( $content )
	{
		$return = array( 'quotes' => array(), 'mentions' => array(), 'embeds' => array() );
		
		$document = new \IPS\Xml\DOMDocument( '1.0', 'UTF-8' );
		if ( @$document->loadHTML( \IPS\Xml\DOMDocument::wrapHtml( '<div>' . $content . '</div>' ) ) !== FALSE )
		{
			/* Quotes */
			foreach( $document->getElementsByTagName('blockquote') as $quote )
			{
				if ( $quote->getAttribute('data-ipsquote-userid') and (int) $quote->getAttribute('data-ipsquote-userid') > 0 )
				{
					$return['quotes'][] = $quote->getAttribute('data-ipsquote-userid');
				}
			}
			
			/* Mentions */
			foreach( $document->getElementsByTagName('a') as $link )
			{
				if ( $link->getAttribute('data-mentionid') )
				{					
					if ( !preg_match( '/\/blockquote(\[\d*\])?\//', $link->getNodePath() ) )
					{
						$return['mentions'][] = $link->getAttribute('data-mentionid');
					}
				}
			}

			/* Embeds */
			foreach( $document->getElementsByTagName('iframe') as $embed )
			{
				if ( $embed->getAttribute('data-embedauthorid') )
				{
					if ( $embed->getAttribute('data-embedauthorid') and (int) $embed->getAttribute('data-embedauthorid') > 0 and !preg_match( '/\/blockquote(\[\d*\])?\//', $embed->getNodePath() ) )
					{
						$return['embeds'][] = $embed->getAttribute('data-embedauthorid');
					}
				}
			}
		}
		
		return $return;
	}
	
	/**
	 * Expire appropriate widget caches automatically
	 *
	 * @return void
	 */
	public function expireWidgetCaches()
	{
		\IPS\Widget::deleteCaches( NULL, static::$application );
	}

	/**
	 * Update "currently viewing" session data after moderator actions that invalidate that data for other users
	 *
	 * @return void
	 */
	public function adjustSessions()
	{
		if( $this instanceof \IPS\Content\Comment )
		{
			try
			{
				$item = $this->item();
			}
			catch( \OutOfRangeException $e )
			{
				return;
			}
		}
		else
		{
			$item = $this;
		}

		/** Item::url() can throw a LogicException exception in specific cases like when a Pages Record has no valid page */
		try
		{
			/* We have to send a limit even though we want all records because otherwise the Database store does not return all columns */
			foreach( \IPS\Session\Store::i()->getOnlineUsers( 0, 'desc', array( 0, 5000 ), NULL, TRUE ) as $session )
			{
				if( mb_strpos( $session['location_url'], (string) $item->url() ) === 0 )
				{
					$sessionData = $session;
					$sessionData['location_url']			= NULL;
					$sessionData['location_lang']			= NULL;
					$sessionData['location_data']			= json_encode( array() );
					$sessionData['current_id']				= 0;
					$sessionData['location_permissions']	= 0;

					\IPS\Session\Store::i()->updateSession( $sessionData );
				}
			}
		}
		catch( \LogicException $e ){}


	}

	/**
	 * Fetch classes from content router
	 *
	 * @param	bool|\IPS\Member	$member		Check member access
	 * @param	bool				$archived	Include any supported archive classes
	 * @param	bool				$onlyItems	Only include item classes
	 * @return	array
	 */
	public static function routedClasses( $member=FALSE, $archived=FALSE, $onlyItems=FALSE )
	{
		$classes	= array();

		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', $member, NULL, NULL, TRUE ) as $router )
		{
			foreach ( $router->classes as $class )
			{
				$classes[]	= $class;

				if( $onlyItems )
				{
					continue;
				}
				
				if ( !( $member instanceof \IPS\Member ) )
				{
					$member = $member ? \IPS\Member::loggedIn() : NULL;
				}
				
				if ( isset( $class::$commentClass ) and $class::supportsComments( $member ) )
				{
					$classes[]	= $class::$commentClass;
				}

				if ( isset( $class::$reviewClass ) and $class::supportsReviews( $member ) )
				{
					$classes[]	= $class::$reviewClass;
				}

				if( $archived === TRUE AND isset( $class::$archiveClass ) )
				{
					$classes[]	= $class::$archiveClass;
				}
			}
		}

		return $classes;
	}

	/**
	 * Override the HTML parsing enabled flag for rebuilds?
	 *
	 * @note	By default this will return FALSE, but classes can override
	 * @see		\IPS\forums\Topic\Post
	 * @return	bool
	 */
	public function htmlParsingEnforced()
	{
		return FALSE;
	}

	/**
	 * Return any custom multimod actions this content item supports
	 *
	 * @return	array
	 */
	public function customMultimodActions()
	{
		return array();
	}

	/**
	 * Return any available custom multimod actions this content item class supports
	 *
	 * @note	Return in format of EITHER
	 *	@li	array( array( 'action' => ..., 'icon' => ..., 'label' => ... ), ... )
	 *	@li	array( array( 'grouplabel' => ..., 'icon' => ..., 'groupaction' => ..., 'action' => array( array( 'action' => ..., 'label' => ... ), ... ) ) )
	 * @note	For an example, look at \IPS\core\Announcements\Announcement
	 * @return	array
	 */
	public static function availableCustomMultimodActions()
	{
		return array();
	}

	/**
	 * Get HTML for search result display
	 *
	 * @param	NULL|string		$ref		Referrer
	 * @param	\IPS\Node\Model	$container	Container
	 * @param	string			$title		Title
	 * @return	callable
	 */
	public function approvalQueueHtml( $ref, $container, $title )
	{
		return \IPS\Theme::i()->getTemplate( 'modcp', 'core', 'front' )->approvalQueueItem( $this, $ref, $container, $title );
	}

	/**
	 * Indefinite Article
	 *
	 * @param	\IPS\Lang|NULL	$lang	The language to use, or NULL for the language of the currently logged in member
	 * @return	string
	 */
	public function indefiniteArticle( \IPS\Lang $lang = NULL )
	{
		$container = ( $this instanceof \IPS\Content\Comment ) ? $this->item()->containerWrapper() : $this->containerWrapper();
		return static::_indefiniteArticle( $container ? $container->_data : array(), $lang );
	}
	
	/**
	 * Indefinite Article
	 *
	 * @param	array			$containerData	Container data
	 * @param	\IPS\Lang|NULL	$lang			The language to use, or NULL for the language of the currently logged in member
	 * @return	string
	 */
	public static function _indefiniteArticle( array $containerData = NULL, \IPS\Lang $lang = NULL )
	{
		$lang = $lang ?: \IPS\Member::loggedIn()->language();
		return $lang->addToStack( '__indefart_' . static::$title, FALSE );
	}
	
	/**
	 * Definite Article
	 *
	 * @param	\IPS\Lang|NULL		$lang	The language to use, or NULL for the language of the currently logged in member
	 * @param	integer|boolean		$count	Number of items. If not FALSE, pluralized version of phrase will be used
	 * @return	string
	 */
	public function definiteArticle( \IPS\Lang $lang = NULL, $count = FALSE )
	{
		$container = ( $this instanceof \IPS\Content\Comment ) ? $this->item()->containerWrapper() : $this->containerWrapper();
		return static::_definiteArticle( $container ? $container->_data : array(), $lang, array(), $count );
	}
	
	/**
	 * Definite Article
	 *
	 * @param	array			$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	\IPS\Lang|NULL	$lang			The language to use, or NULL for the language of the currently logged in member
	 * @param	array			$options		Options to pass to \IPS\Lang::addToStack
	 * @param	integer|boolean	$count			Number of items. If not false, pluralized version of phrase will be used.
	 * @return	string
	 */
	public static function _definiteArticle( array $containerData = NULL, \IPS\Lang $lang = NULL, $options = array(), $count = FALSE )
	{
		$lang = $lang ?: \IPS\Member::loggedIn()->language();
		
		if( ( \is_int( $count ) || $count === TRUE ) && $lang->checkKeyExists('__defart_' . static::$title . '_plural') )
		{
			/* If $count is TRUE, use the pluralized form but don't pluralize here - useful if we're passing to JS for example */
			if( \is_int( $count ) )
			{
				$options['pluralize'] = array( $count );
			}

			return $lang->addToStack( '__defart_' . static::$title . '_plural', FALSE, $options );
		}

		return $lang->addToStack( '__defart_' . static::$title, FALSE, $options );
	}

	/**
	 * Get preview image for share services
	 *
	 * @return	string
	 */
	public function shareImage()
	{
		/* While we now allow multiple share logos now, this deprecated method can only return one */
		$shareLogos = \IPS\Settings::i()->icons_sharer_logo ? json_decode( \IPS\Settings::i()->icons_sharer_logo, true ) : array();

		if( \count( $shareLogos ) )
		{
			try
			{
				return (string) \IPS\File::get( 'core_Icons', array_shift($shareLogos) )->url->setScheme( ( \IPS\Request::i()->isSecure() ) ? 'https' : 'http' );
			}
			catch( \Exception $e )
			{
				return '';
			}
		}

		return '';
	}

	/**
	 * Log keyword usage, if any
	 *
	 * @param	string				$content	Content/text of submission
	 * @param	string|NULL			$title		Title of submission
	 * @param	int|NULL			$date		Date of submission
	 * @return	void
	 */
	public function checkKeywords( $content, $title=NULL, ?int $date = NULL )
	{
		/* Do we have any keywords to track? */
		if( !\IPS\Settings::i()->stats_keywords )
		{
			return;
		}

		/* We need to know the ID */
		$idColumn	= static::$databaseColumnId;

		/* If this is a content item and first comment is required, skip checking the comment */
		if ( $this instanceof \IPS\Content\Comment )
		{
			$itemClass = static::$itemClass;

			if( $itemClass::$firstCommentRequired === TRUE )
			{
				/* During initial post, at this point the firstCommentIdColumn value won't be set, so we check for that or explicitly if this is the first post */
				if( !$this->item()->mapped('first_comment_id') OR $this->$idColumn == $this->item()->mapped('first_comment_id') )
				{
					return;
				}
			}
		}

		$words = preg_split("/[\s]+/", trim( strip_tags( preg_replace( "/<br( \/)?>/", "\n", $content ) ) ), NULL, PREG_SPLIT_NO_EMPTY );

		if( $title !== NULL )
		{
			$titleWords = explode( ' ', $title );
			$words		= array_merge( $words, $titleWords );
		}

		$words = array_unique( $words );

		$keywords = json_decode( \IPS\Settings::i()->stats_keywords, true );

		$extraData	= json_encode( array( 'class' => \get_class( $this ), 'id' => $this->$idColumn ) );

		foreach( $keywords as $keyword )
		{
			if( \in_array( $keyword, $words ) )
			{
				$date = $date ?: \IPS\DateTime::create()->getTimestamp();
				\IPS\Db::i()->insert( 'core_statistics', array( 'time' => $date, 'type' => 'keyword', 'value_4' => $keyword, 'extra_data' => $extraData ) );
			}
		}
	}
	
	/* !Search */
	
	/**
	 * Title for search index
	 *
	 * @return	string
	 */
	public function searchIndexTitle()
	{
		return $this->mapped('title');
	}
	
	/**
	 * Content for search index
	 *
	 * @return	string
	 */
	public function searchIndexContent()
	{
		return $this->mapped('content');
	}

	/**
	 * Return size and downloads count when this content type is inserted as an attachment via the "Insert other media" button on an editor.
	 *
	 * @note Most content types do not support this, and those that do will need to override this method to return the appropriate info
	 * @return array
	 */
	public function getAttachmentInfo()
	{
		return array();
	}

	/**
	 * Create a query to fetch the "top members"
	 *
	 * @note	The intention is to formulate a query that will fetch the members with the most contributions
	 * @param	int		$limit	The number of members to return
	 * @return	\IPS\Db\Select
	 */
	public static function topMembersQuery( $limit )
	{
		$contentWhere = array( array( static::$databasePrefix . static::$databaseColumnMap['author'] . '<>?', 0 ) );
		if ( isset( static::$databaseColumnMap['hidden'] ) )
		{
			$contentWhere[] = array( static::$databasePrefix . static::$databaseColumnMap['hidden'] . '=0' );
		}
		else if ( isset( static::$databaseColumnMap['approved'] ) )
		{
			$contentWhere[] = array( static::$databasePrefix . static::$databaseColumnMap['approved'] . '=1' );
		}
		
		$authorField = static::$databasePrefix . static::$databaseColumnMap['author'];

		return \IPS\Db::i()->select( 'COUNT(*) as count, ' . static::$databaseTable . '.' . $authorField, static::$databaseTable, $contentWhere, 'count DESC', $limit, $authorField );
	}

	/**
	 * Get edit line
	 *
	 * @return	string|NULL
	 */
	public function editLine()
	{
		if ( $this instanceof \IPS\Content\EditHistory and $this->mapped('edit_time') and ( $this->mapped('edit_show') or \IPS\Member::loggedIn()->modPermission('can_view_editlog') ) and \IPS\Settings::i()->edit_log )
		{
			$template = static::$editLineTemplate[1];
			return \IPS\Theme::i()->getTemplate( static::$editLineTemplate[0][0], static::$editLineTemplate[0][1], ( isset( static::$editLineTemplate[0][2] ) ) ? static::$editLineTemplate[0][2] : NULL )->$template( $this, ( isset( static::$databaseColumnMap['edit_reason'] ) and $this->mapped('edit_reason') ) );
		}
		return NULL;
	}

	/**
	 * Get edit history
	 *
	 * @param	bool		$staff		Set true for moderators who have permission to view the full log which will show edits not made by the author and private edits
	 * @param	int|NULL 	$limit
	 * @return	\IPS\Db\Select
	 */
	public function editHistory( $staff=FALSE, $limit=NULL )
	{
		$idColumn = static::$databaseColumnId;
		$where = array( array( 'class=? AND comment_id=?', \get_called_class(), $this->$idColumn ) );
		if ( !$staff )
		{
			$where[] = array( '`member`=? AND public=1', $this->author()->member_id );
		}

		return \IPS\Db::i()->select( '*', 'core_edit_history', $where, 'time DESC', $limit );
	}
	
	/**
	 * Webhook filters
	 *
	 * @return	array
	 */
	public function webhookFilters()
	{
		$filters = array();
		$filters['author'] = $this->author()->member_id;

		/* All our Zapier Triggers for content have a hidden setting, so we're using this one global for items and comments */
		$filters['hidden'] = (bool) $this->hidden();

		return $filters;
	}

	/**
	 * Set anonymous state
	 *
	 * @param	bool				$state		The state, TRUE for anonymous FALSE for not
	 * @param	\IPS\Member|NULL 	$member		The member posting anonymously or NULL for logged in member
	 * @return	void
	 */
	public function setAnonymous( bool $state = TRUE, \IPS\Member $member = NULL)
	{
		if( !$this instanceof \IPS\Content\Anonymous  )
		{
			throw new \BadMethodCallException();
		}

		if( $state == $this->isAnonymous() )
		{
			return;
		}

		$class = \get_class( $this );
		$idColumn = static::$databaseColumnId;
		$anonColumn = static::$databaseColumnMap['is_anon'];

		if( !$state )
		{
			try
			{
				$originalAuthor = \IPS\DB::i()->select( 'anonymous_member_id', 'core_anonymous_posts', array( 'anonymous_object_class=? and anonymous_object_id=?', $class, $this->$idColumn ) )->first();

				$member = \IPS\Member::load( $originalAuthor );
			}
			catch (\UnderflowException $e )
			{
				$member = NULL;
			}
		}

		$member = $member ?: \IPS\Member::loggedIn();

		if( $state and !$this->container()->canPostAnonymously( 0, $member ) )
		{
			throw new \BadMethodCallException();
		}

		if( $state )
		{
			/* Insert the anonymous map */
			$save = array(
				'anonymous_member_id'				=> ( $this->author()->member_id ) ? $this->author()->member_id : $member->member_id,
				'anonymous_object_class'			=> $class,
				'anonymous_object_id'				=> $this->$idColumn
			);

			\IPS\Db::i()->replace( 'core_anonymous_posts', $save );

			$member = new \IPS\Member;
			$this->$anonColumn = 1;
		}
		else
		{
			\IPS\Db::i()->delete( 'core_anonymous_posts', array( 'anonymous_object_class=? and anonymous_object_id=?', $class, $this->$idColumn ) );

			$this->$anonColumn = 0;
		}

		/* @todo only run the rest of the code here if the $anonColumn state is different from original. Waste of processing otherwise */
		$this->save();
		$this->changeAuthor( $member, FALSE );

		if ( \in_array( 'IPS\Content\Comment', class_parents( $this ) ) )
		{
			$this->item()->rebuildFirstAndLastCommentData();
		}

		$this->expireWidgetCaches();
	}

	/**
	 * Is this an anonymous entry?
	 *
	 * @return bool
	 */
	public function isAnonymous()
	{
		return $this->mapped('is_anon');
	}

	/**
	 * Returns the content images
	 *
	 * @return	array|NULL
	 * @throws	\BadMethodCallException
	 */
	public function imageLabelsForSearch()
	{
		return [];
	}
}