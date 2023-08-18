<?php
/**
 * @brief		Content Item Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		5 Jul 2013
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Content Item Model
 */
abstract class _Item extends \IPS\Content
{
	/**
	 * @brief	[Content\Item]	Comment Class
	 */
	public static $commentClass = NULL;
		
	/**
	 * @brief	[Content\Item]	First "comment" is part of the item?
	 */
	public static $firstCommentRequired = FALSE;
	
	/**
	 * @brief	[Content\Item]	First comment
	 */
	public $firstComment = NULL;
	
	/**
	 * @brief	[Content\Item]	Follower count
	 */
	public $followerCount = NULL;

	/**
	 * Should IndexNow be skipped for this item? Can be used to prevent that Private Messages,
	 * Reports and other content which is never going to be visible to guests is triggering the requests.
	 * @var bool
	 */
	public static bool $skipIndexNow = FALSE;
	
	/**
	 * @brief	[Content\Item]	If $firstCommentRequired is TRUE, when comments are split from an item or items are merged, the author
	 * 							of the item is set to the author of the new first comment. If this is set to FALSE, this won't be
	 *							done. Useful for circumstances like support requests where the first comment author is not necessarily
	 *							the item author
	 */
	public static $changeItemAuthorChangingFirstComment = TRUE;

	/**
	 * @brief	[Content\Item]	Include the ability to search this content item in global site searches
	 */
	public static $includeInSiteSearch = TRUE;

	/**
	 * @brief	[Content\Item]	Include these items in trending content
	 */
	public static $includeInTrending = TRUE;
	
	/**
	 * @brief	[Content\Item]	Sharelink HTML
	 */
	protected $sharelinks = array();

	/**
	 * @brief   [Content\Item]  Group Posted cache
	 */
	public $groupsPosted = [];

	/**
	 * Whether or not to include in site search
	 *
	 * @return	bool
	 */
	public static function includeInSiteSearch()
	{
		return static::$includeInSiteSearch;
	}

	/**
	 * @brief	[Content\Comment]	EditLine Template
	 */
	public static $editLineTemplate = array( array( 'global', 'core', 'front' ), 'contentEditLine' );

	/**
	 * Get the last modification date for the sitemap
	 *
	 * @return \IPS\DateTime|null		timestamp of the last modification time for the sitemap
	 */
	public function lastModificationDate()
	{
		$lastMod = NULL;
		if ( isset( static::$databaseColumnMap['last_comment'] ) )
		{
			$lastCommentField = static::$databaseColumnMap['last_comment'];
			if ( \is_array( $lastCommentField ) )
			{
				foreach ( $lastCommentField as $column )
				{
					if( $this->$column )
					{
						$lastMod = \IPS\DateTime::ts( $this->$column );
					}
				}
			}
			else
			{
				if( $this->$lastCommentField )
				{
					$lastMod = \IPS\DateTime::ts( $this->$lastCommentField );
				}
			}
		}

		return $lastMod;
	}

	/**
	 * Build form to create
	 *
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @return	\IPS\Helpers\Form
	 */
	public static function create( \IPS\Node\Model $container=NULL )
	{
		/* Perform permission checks */
		static::canCreate( \IPS\Member::loggedIn(), $container, TRUE );
		
		/* Build the form */
		$form = static::buildCreateForm( $container );
				
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Disable read/write separation */
			\IPS\Db::i()->readWriteSeparation = FALSE;

			try
			{
				$obj = static::createFromForm( $values, $container );
				
				if ( $obj->hidden() === -3 )
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=register', 'front', 'register' ) );
				}
				elseif ( !\IPS\Member::loggedIn()->member_id and $obj->hidden() )
				{
					\IPS\Output::i()->redirect( $obj->container()->url(), 'mod_queue_message' );
				}
				else if ( $obj->hidden() == 1 )
				{
					\IPS\Output::i()->redirect( $obj->url(), 'mod_queue_message' );
				}
				else
				{
					\IPS\Output::i()->redirect( $obj->url() );
				}
			}
			catch ( \DomainException $e )
			{
				$form->error = $e->getMessage();
			}			
		}
		
		/* Return */
		return $form;
	}
	
	/**
	 * Build form to create
	 *
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @param	\IPS\Content\Item|NULL	$item		Content item, e.g. if editing
	 * @return	\IPS\Helpers\Form
	 */
	protected static function buildCreateForm( \IPS\Node\Model $container=NULL, \IPS\Content\Item $item=NULL )
	{
		$form = new \IPS\Helpers\Form( 'form', \IPS\Member::loggedIn()->language()->checkKeyExists( static::$formLangPrefix . '_save' ) ? static::$formLangPrefix . '_save' : 'save' );
		$form->class = 'ipsForm_vertical';
		$formElements = static::formElements( $item, $container );
		if ( isset( $formElements['poll'] ) )
		{
			$form->addTab( static::$formLangPrefix . 'mainTab' );
		}
		foreach ( $formElements as $key => $object )
		{
			if ( $key === 'poll' )
			{
				$form->addTab( static::$formLangPrefix . 'pollTab' );
			}
			
			if ( \is_object( $object ) )
			{
				$form->add( $object );
			}
			else
			{
				$form->addMessage( $object, NULL, FALSE, $key );
			}
		}
		
		return $form;
	}

	/**
	 * Build form to edit
	 *
	 * @return	\IPS\Helpers\Form
	 */
	public function buildEditForm()
	{
		return static::buildCreateForm( $this->containerWrapper(), $this );
	}
	
	/**
	 * Create generic object
	 *
	 * @param	\IPS\Member				$author		The author
	 * @param	string|NULL				$ipAddress	The IP address
	 * @param	\IPS\DateTime			$time		The time
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @param	bool|NULL				$hidden		Hidden? (NULL to work our automatically)
	 * @param	\IPS\DateTime|NULL		$futureDate Publish date for future items
	 * @return	static
	 */
	public static function createItem(\IPS\Member $author, $ipAddress, \IPS\DateTime $time, \IPS\Node\Model $container = NULL, $hidden=NULL)
	{
		/* Create the object */
		$obj = new static;

		foreach ( array( 'date', 'updated', 'author', 'author_name', 'ip_address', 'last_comment', 'last_comment_by', 'last_comment_name', 'last_review', 'container', 'approved', 'hidden', 'locked', 'status', 'views', 'pinned', 'featured', 'is_future_entry', 'num_comments', 'num_reviews', 'unapproved_comments', 'hidden_comments', 'unapproved_reviews', 'hidden_reviews', 'future_date' ) as $k )
		{
			if ( isset( static::$databaseColumnMap[ $k ] ) )
			{
				$val = NULL;
				switch ( $k )
				{
					case 'container':
						$val = $container->_id;
						break;
					
					case 'last_comment':
					case 'last_review':
					case 'date':
						$val = $time->getTimestamp();
						break;
					case 'updated':
						$val = $time->getTimestamp();
						break;
					
					case 'author':
					case 'last_comment_by':
						$val = (int) $author->member_id;
						break;
					
					case 'author_name':
					case 'last_comment_name':
						$val = ( $author->member_id ) ? $author->name : $author->real_name;
						break;

					case 'ip_address':
						$val = $ipAddress;
						break;
						
					case 'approved':
						if ( $hidden === NULL )
						{
							if ( !$author->member_id and $container and !$container->can( 'add', $author, FALSE ) )
							{
								$val = -3;
							}
							else
							{
								$val = static::moderateNewItems( $author, $container ) ? 0 : 1;
							}
						}
						else
						{
							$val = \intval( !$hidden );
						}
						break;
					
					case 'hidden':
						if ( $hidden === NULL )
						{
							if ( !$author->member_id and $container and !$container->can( 'add', $author, FALSE ) )
							{
								$val = -3;
							}
							else
							{
								$val = static::moderateNewItems( $author, $container ) ? 1 : 0;
							}
						}
						else
						{
							$val = \intval( $hidden );
						}
						break;
						
					case 'locked':
						$val = FALSE;
						break;
						
					case 'status':
						$val = 'open';
						break;
					
					case 'views':
					case 'pinned':
					case 'featured':
					case 'num_comments':
					case 'num_reviews':
					case 'unapproved_comments':
					case 'hidden_comments':
					case 'unapproved_reviews':
					case 'hidden_reviews':
						$val = 0;
						break;

					case 'is_future_entry':
						$val = ( $time->getTimestamp() > time() ) ? 1 : 0;
						break;
					case 'future_date':
						$val = ( $time->getTimestamp() > time() ) ? $time->getTimestamp() : time();
						break;
				}
				
				foreach ( \is_array( static::$databaseColumnMap[ $k ] ) ? static::$databaseColumnMap[ $k ] : array( static::$databaseColumnMap[ $k ] ) as $column )
				{
					$obj->$column = $val;
				}
			}
		}

		/* Update the container */
		if ( $container )
		{
			$hiddenStatus = $obj->hidden();
			if ( $obj->isFutureDate() )
			{
				if ( $container->_futureItems !== NULL )
				{
					$container->_futureItems = ( $container->_futureItems + 1 );
				}
			}
			elseif ( !$hiddenStatus )
			{
				if ( $container->_items !== NULL )
				{
					$container->_items = ( $container->_items + 1 );
				}
			}
			elseif ( $hiddenStatus !== -3 and $container->_unapprovedItems !== NULL )
			{
				$container->_unapprovedItems = ( $container->_unapprovedItems + 1 );
			}
			$container->save();
		}
		
		/* Increment post count */
		if ( !$obj->hidden() and static::incrementPostCount( $container ) and ! $obj->isAnonymous() )
		{
			$obj->author()->member_posts++;
		}
		
		/* Update member's last post */
		if( $obj->author()->member_id AND $obj::incrementPostCount() AND ! $obj->isAnonymous() )
		{
			$obj->author()->member_last_post = time();
			$obj->author()->save();
		}

		/* Return */
		return $obj;
	}
	
	/**
	 * Create from form
	 *
	 * @param	array					$values				Values from form
	 * @param	\IPS\Node\Model|NULL	$container			Container (e.g. forum), if appropriate
	 * @param	bool					$sendNotification	TRUE to automatically send new content notifications (useful for items that may be uploaded in bulk)
	 * @return	static
	 */
	public static function createFromForm( $values, \IPS\Node\Model $container = NULL, $sendNotification = TRUE )
	{
		/* Some applications may include the container selection on the form itself. If $container is NULL, attempt to find it automatically. */
		if( $container === NULL )
		{
			if( isset( $values[ static::$formLangPrefix . 'container'] ) AND isset( static::$containerNodeClass ) AND static::$containerNodeClass AND $values[ static::$formLangPrefix . 'container'] instanceof static::$containerNodeClass )
			{
				$container	= $values[ static::$formLangPrefix . 'container'];
			}
		}

		$member	= \IPS\Member::loggedIn();

		if( isset( $values['guest_name'] ) AND isset( static::$databaseColumnMap['author_name'] ) )
		{
			$member->name = $values['guest_name'];
		}

		/* Create the item */
		$time = ( static::canFuturePublish( NULL, $container ) and isset( static::$databaseColumnMap['future_date'] ) and isset( $values[ static::$formLangPrefix . 'date' ] ) and $values[ static::$formLangPrefix . 'date' ] instanceof \IPS\DateTime ) ? $values[ static::$formLangPrefix . 'date' ] : new \IPS\DateTime;

		/* Create the item */
		$obj = static::createItem( $member, \IPS\Request::i()->ipAddress(), $time, $container, NULL );
		$obj->processBeforeCreate( $values );
		$obj->processForm( $values );
		$obj->save();

		/* It is possible that $member has changed */
		if( $obj->author()->member_id != $member->member_id )
		{
			$member = $obj->author();
		}

		/* Create the comment */
		$comment = NULL;
		if ( isset( static::$commentClass ) and static::$firstCommentRequired )
		{
			$commentClass = static::$commentClass;
			
			$comment = $commentClass::create( $obj, $values[ static::$formLangPrefix . 'content' ], TRUE, ( !$member->real_name ) ? NULL : $member->real_name, $obj->hidden() ? FALSE : NULL, $member, $time );
			
			$idColumn = static::$databaseColumnId;
			$commentIdColumn = $commentClass::$databaseColumnId;
			
			if ( isset( static::$databaseColumnMap['first_comment_id'] ) )
			{
				$firstCommentIdColumn = static::$databaseColumnMap['first_comment_id'];
				$obj->$firstCommentIdColumn = $comment->$commentIdColumn;
				$obj->save();
			}
		}

		/* Update posts per day limits - don't do this for content items that require a first comment as the comment class will handle that */
		if ( $member->member_id AND $member->group['g_ppd_limit'] AND static::$firstCommentRequired === FALSE )
		{
			$current = $member->members_day_posts;
			
			$current[0] += 1;
			if ( $current[1] == 0 )
			{
				$current[1] = \IPS\DateTime::create()->getTimestamp();
			}
			
			$member->members_day_posts = $current;
			$member->save();
		}
		
		/* Post anonymously */
		if( isset( $values[ 'post_anonymously' ] ) )
		{
			$obj->setAnonymous( $values[ 'post_anonymously' ], $member );
		}
		
		/* Do any processing */
		$obj->processAfterCreate( $comment, $values );
		
		/* Auto-follow */
		if( isset( $values[ static::$formLangPrefix . 'auto_follow'] ) AND $values[ static::$formLangPrefix . 'auto_follow'] )
		{
			$followArea = mb_strtolower( mb_substr( \get_called_class(), mb_strrpos( \get_called_class(), '\\' ) + 1 ) );
			
			/* Insert */
			$idColumn = static::$databaseColumnId;
			$save = array(
				'follow_id'				=> md5( static::$application . ';' . $followArea . ';' . $obj->$idColumn . ';' .  \IPS\Member::loggedIn()->member_id ),
				'follow_app'			=> static::$application,
				'follow_area'			=> $followArea,
				'follow_rel_id'			=> $obj->$idColumn,
				'follow_member_id'		=> \IPS\Member::loggedIn()->member_id,
				'follow_is_anon'		=> 0,
				'follow_added'			=> time() + 1, // Make sure streams show follows after content is created
				'follow_notify_do'		=> 1,
				'follow_notify_meta'	=> '',
				'follow_notify_freq'	=> \IPS\Member::loggedIn()->auto_follow['method'],
				'follow_notify_sent'	=> 0,
				'follow_visible'		=> 1
			);
			
			\IPS\Db::i()->insert( 'core_follow', $save );
		}
		
		/* Auto-share */
		if ( $obj->canShare() and !$obj->hidden() and !$obj->isFutureDate() )
		{
			foreach( \IPS\core\ShareLinks\Service::shareLinks() as $node )
			{
				if ( isset( $values[ "auto_share_{$node->key}" ] ) and $values[ "auto_share_{$node->key}" ] )
				{
					try
					{
						$key = \IPS\Content\ShareServices::getClassByKey( $node->key );
						$obj->autoShare( $key );
					}
					catch( \InvalidArgumentException $e )
					{
						/* Anything we can do here? Can't and shouldn't stop the submission */
					}
				}
			}
		}

		/* Send notifications */
		if ( $sendNotification and !$obj->isFutureDate() )
		{
			if ( !$obj->hidden() )
			{
				$obj->sendNotifications();
			}
			else if( $obj instanceof \IPS\Content\Hideable and !\in_array( $obj->hidden(), array( -1, -3 ) ) )
			{
				$obj->sendUnapprovedNotification();
			}
		}
		
		/* Dish out points */
		if ( !$obj->isFutureDate() and !$obj->hidden() and !$obj instanceof \IPS\core\Messenger\Conversation )
		{
			$member->achievementAction( 'core', 'NewContentItem', $obj );
		}

		/* Return */
		return $obj;
	}
	
	/**
	 * Share this content using a share service
	 *
	 * @param	string	$className	The share service classname
	 * @return	void
	 * @throws	\InvalidArgumentException
	 */
	protected function autoShare( $className )
	{
		$className::publish( $this->mapped('title'), $this->url() );
	}
	
	/**
	 * Process create/edit form
	 *
	 * @param	array				$values	Values from form
	 * @return	void
	 */
	public function processForm( $values )
	{
		/* General columns */
		foreach ( array( 'title', 'poll' ) as $k )
		{
			if ( isset( static::$databaseColumnMap[ $k ] ) and array_key_exists( static::$formLangPrefix . $k , $values ) )
			{
				$val = $values[ static::$formLangPrefix . $k ];
				if ( $k === 'poll' )
				{
					$val = $val ? $val->pid : NULL;
				}

				foreach ( \is_array( static::$databaseColumnMap[ $k ] ) ? static::$databaseColumnMap[ $k ] : array( static::$databaseColumnMap[ $k ] ) as $column )
				{
					$this->$column = $val;
				}
			}
		}
				
		/* Tags */
		if ( $this instanceof \IPS\Content\Tags and static::canTag( NULL, $this->containerWrapper() ) and isset( $values[ static::$formLangPrefix . 'tags' ] ) )
		{
			$idColumn = static::$databaseColumnId;
			if ( !$this->$idColumn )
			{
				$this->save();
			}
			
			$this->setTags( $values[ static::$formLangPrefix . 'tags' ] ?: array() );
		}
		
		/* Future Publishing */
		if( static::canFuturePublish( NULL, $this->containerWrapper() ) and isset( static::$databaseColumnMap['future_date'] ) and isset( $values[ static::$formLangPrefix . 'date'] ) )
		{
			$this->setFuturePublishingDates( $values );
		}
		
		/* Post before registering */
		if ( isset( $values['guest_email'] ) and ( !$this->containerWrapper() or !$this->containerWrapper()->can( 'add', \IPS\Member::loggedIn(), FALSE ) ) )
		{
			$idColumn = static::$databaseColumnId;
			if ( !$this->$idColumn )
			{
				$this->save();
			}
			
			\IPS\Request::i()->setCookie( 'post_before_register', $this->_logPostBeforeRegistering( $values['guest_email'], isset( \IPS\Request::i()->cookie['post_before_register'] ) ? \IPS\Request::i()->cookie['post_before_register'] : NULL ) );
		}
	}

	/**
	 * Set future publishing dates
	 *
	 * @param	array	$values	Values from form
	 * @return	void
	 */
	protected function setFuturePublishingDates( array $values )
	{
		$time = $values[ static::$formLangPrefix . 'date' ] instanceof \IPS\DateTime ? $values[ static::$formLangPrefix . 'date' ] : new \IPS\DateTime;

		if( isset( static::$databaseColumnMap['date'] ) )
		{
			$column = static::$databaseColumnMap['date'];
			$this->$column = ( $time->getTimestamp() > time() ) ? $time->getTimestamp() : time();
		}

		if( isset( static::$databaseColumnMap['future_date'] ) )
		{
			$column = static::$databaseColumnMap['future_date'];
			$this->$column =  $time->getTimestamp();

            if( isset( static::$databaseColumnMap['is_future_entry'] ) )
            {
                $column = static::$databaseColumnMap['is_future_entry'];
                $this->$column = $time->getTimestamp() > time();
            }
		}
	}
			
	/**
	 * Can a given member create this type of content?
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @param	bool		$showError	If TRUE, rather than returning a boolean value, will display an error
	 * @return	bool
	 */
	public static function canCreate( \IPS\Member $member, \IPS\Node\Model $container=NULL, $showError=FALSE )
	{
		$return = TRUE;
		$error = $member->member_id ? 'no_module_permission' : 'no_module_permission_guest';
				
		/* Are we restricted from posting completely? */
		if ( $member->restrict_post )
		{
			$return = FALSE;
			$error = 'restricted_cannot_comment';
			
			if ( $member->restrict_post > 0 )
			{
				$error = $member->language()->addToStack( $error ) . ' ' . $member->language()->addToStack( 'restriction_ends', FALSE, array( 'sprintf' => array( \IPS\DateTime::ts( $member->restrict_post )->relative() ) ) ); 
			}
		}
		
		/* Or have an unacknowledged warning? */
		if ( $member->members_bitoptions['unacknowledged_warnings'] and \IPS\Settings::i()->warn_on and \IPS\Settings::i()->warnings_acknowledge )
		{
			$return = FALSE;
			
			if ( $showError )
			{
				/* If we are running from the command line (ex: profilesync task syncing statuses while using cron) then this can cause an error due to \IPS\Dispatcher not being instantiated.
					If we are not showing an error, then we do not need to call the template. */
				$error = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->createItemUnavailable( 'unacknowledged_warning_cannot_post', $member->warnings( 1, FALSE ) );
			}
		}
		
		/* Do we have permission? */
		if ( $container !== NULL AND \in_array( 'IPS\Content\Permissions', class_implements( \get_called_class() ) ) )
		{
			if ( !$container->can('add') )
			{
				$return = FALSE;
			}
		}
		else if( $container === NULL AND \in_array( 'IPS\Content\Permissions', class_implements( \get_called_class() ) ) )
		{
			$containerClass	= static::$containerNodeClass;
			if ( !$containerClass::canOnAny('add') )
			{
				$return = FALSE;
			}
		}
		
		/* Can we access the module */
		if ( !static::_canAccessModule( $member ) )
		{
			$return = FALSE;
		}
		
		/* Return */
		if ( $showError and !$return )
		{
			\IPS\Output::i()->error( $error, '2C137/3', 403 );
		}
		return $return;
	}

	/**
	 * During canCreate() check, verify member can access the module too
	 *
	 * @param	\IPS\Member	$member		The member
	 * @note	The only reason this is abstracted at this time is because Pages creates dynamic 'modules' with its dynamic records class which do not exist
	 * @return	bool
	 */
	protected static function _canAccessModule( \IPS\Member $member )
	{
		/* Can we access the module */
		return $member->canAccessModule( \IPS\Application\Module::get( static::$application, static::$module, 'front' ) );
	}
	
	/**
	 * Get elements for add/edit form
	 *
	 * @param	\IPS\Content\Item|NULL	$item		The current item if editing or NULL if creating
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @return	array
	 */
	public static function formElements( $item=NULL, \IPS\Node\Model $container=NULL )
	{
		$return = array();
				
		/* Title */
		if ( isset( static::$databaseColumnMap['title'] ) )
		{
			$return['title'] = new \IPS\Helpers\Form\Text( static::$formLangPrefix . 'title',  isset( \IPS\Request::i()->title ) ? \IPS\Request::i()->title : $item?->mapped( 'title' ), TRUE, array( 'maxLength' => \IPS\Settings::i()->max_title_length ?: 255, 'bypassProfanity' => \IPS\Helpers\Form\Text::BYPASS_PROFANITY_SWAP ) );
			$return['title']->rowClasses[] = 'ipsFieldRow_primary';
			$return['title']->rowClasses[] = 'ipsFieldRow_fullWidth';
		}
		
		/* Container */
		if ( $container === NULL AND isset( static::$containerNodeClass ) AND static::$containerNodeClass )
		{
			$return['container'] = new \IPS\Helpers\Form\Node( static::$formLangPrefix . 'container', NULL, TRUE, array(
				'class'				=> static::$containerNodeClass,
				'permissionCheck'	=> 'add',
				'togglePerm'		=> 'add',
				'togglePermPBR'		=> FALSE,
				'toggleIds'			=> array( 'guest_name' ),
				'toggleIdsOff'		=> array( 'guest_email' ),
			), NULL, NULL, NULL, static::$formLangPrefix . 'container' );
		}

		if ( !\IPS\Member::loggedIn()->member_id )
		{
			$guestsCanPostInContainer = $container ? $container->can( 'add', \IPS\Member::loggedIn(), FALSE ) : NULL;
			
			if ( !$container or !$guestsCanPostInContainer )
			{
				$return['guest_email'] = new \IPS\Helpers\Form\Email( 'guest_email', NULL, TRUE, array( 'accountEmail' => TRUE, 'htmlAutocomplete' => "email" ), NULL, NULL, NULL, 'guest_email' );
			}
			if ( !$container or $guestsCanPostInContainer )
			{
				if ( isset( static::$databaseColumnMap['author_name'] ) )
				{
					$return['guest_name']	= new \IPS\Helpers\Form\Text( 'guest_name', NULL, FALSE, array( 'minLength' => \IPS\Settings::i()->min_user_name_length, 'maxLength' => \IPS\Settings::i()->max_user_name_length, 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('comment_guest_name'), 'htmlAutocomplete' => "username" ), NULL, NULL, NULL, 'guest_name' );
				}
			}
			if ( \IPS\Settings::i()->bot_antispam_type !== 'none' and \IPS\Settings::i()->guest_captcha )
			{
				$return['captcha']	= new \IPS\Helpers\Form\Captcha;
			}
		}

		/* Tags */
		if ( \in_array( 'IPS\Content\Tags', class_implements( \get_called_class() ) ) and static::canTag( NULL, $container ) )
		{
			if( $tagsField = static::tagsFormField( $item, $container ) )
			{
				$return['tags']	= $tagsField;
			}
		}

		/* Intitial Comment */
		if ( isset( static::$commentClass ) and static::$firstCommentRequired )
		{
			$idColumn = static::$databaseColumnId;
			$commentClass = static::$commentClass;
			if ( $item )
			{
				$commentObj = $item->firstComment();
			}
			$commentIdColumn = $commentClass::$databaseColumnId;
			$return['content'] = new \IPS\Helpers\Form\Editor( static::$formLangPrefix . 'content', $item ? $commentObj->mapped('content') : NULL, TRUE, array(
				'app'			=> static::$application,
				'key'			=> mb_ucfirst( static::$module ),
				'autoSaveKey'	=> ( $item === NULL ? ( 'newContentItem-' . static::$application . '/' . static::$module . '-' . ( $container ? $container->_id : 0 ) ) : ( 'contentEdit-' . static::$application . '/' . static::$module . '-' . $item->$idColumn ) ),
				'attachIds'		=> ( $item === NULL ? NULL : array( $item->$idColumn, $commentObj->$commentIdColumn ) )
			), '\IPS\Helpers\Form::floodCheck', NULL, NULL, static::$formLangPrefix . 'content_editor' );
			
			if ( $item AND \in_array( 'IPS\Content\EditHistory', class_implements( $commentClass ) ) and \IPS\Settings::i()->edit_log )
			{
				if ( \IPS\Settings::i()->edit_log == 2 or isset( $commentClass::$databaseColumnMap['edit_reason'] ) )
				{
					$return['comment_edit_reason'] = new \IPS\Helpers\Form\Text( 'comment_edit_reason', ( isset( $commentClass::$databaseColumnMap['edit_reason'] ) ) ? $commentObj->mapped('edit_reason') : NULL, FALSE, array( 'maxLength' => 255 ) );
				}
				if ( \IPS\Member::loggedIn()->group['g_append_edit'] )
				{
					$return['comment_log_edit'] = new \IPS\Helpers\Form\Checkbox( 'comment_log_edit', FALSE );
				}
			}
		}
		else
		{
			/* Edit Reason */
			if ( $item AND \in_array( 'IPS\Content\EditHistory', class_implements( $item ) ) and \IPS\Settings::i()->edit_log )
			{
				if ( \IPS\Settings::i()->edit_log == 2 or isset( $item::$databaseColumnMap['edit_reason'] ) )
				{
					$return['edit_reason'] = new \IPS\Helpers\Form\Text( 'edit_reason', ( isset( $item::$databaseColumnMap['edit_reason'] ) ) ? $item->mapped('edit_reason') : NULL, FALSE, array( 'maxLength' => 255 ) );
				}
				if ( \IPS\Member::loggedIn()->group['g_append_edit'] )
				{
					$return['log_edit'] = new \IPS\Helpers\Form\Checkbox( 'log_edit', FALSE );
				}
			}
		}
		
		/* Auto-follow */
		if ( $item === NULL and \in_array( 'IPS\Content\Followable', class_implements( \get_called_class() ) ) and \IPS\Member::loggedIn()->member_id )
		{
			$return['auto_follow']	= new \IPS\Helpers\Form\YesNo( static::$formLangPrefix . 'auto_follow', (bool) \IPS\Member::loggedIn()->auto_follow['content'], FALSE, array( 'label' => \IPS\Member::loggedIn()->language()->addToStack( static::$formLangPrefix . 'auto_follow_suffix' ) ), NULL, NULL, NULL, static::$formLangPrefix . 'auto_follow' );
		}
		
		/* Post Anonymously */
		if ( $container and $container->canPostAnonymously( $container::ANON_ITEMS ) and ( $item === NULL or ( $item->author() and $item->author()->group['gbw_can_post_anonymously'] ) or $item->isAnonymous() ) )
		{
			$return['post_anonymously']	= new \IPS\Helpers\Form\YesNo( 'post_anonymously', ( $item ) ? $item->isAnonymous() : FALSE , FALSE, array( 'label' => \IPS\Member::loggedIn()->language()->addToStack( 'post_anonymously_suffix' ) ), NULL, NULL, NULL, 'post_anonymously' );
		}
		
		/* Share Links */
		if ( $item === NULL and \in_array( 'IPS\Content\Shareable', class_implements( \get_called_class() ) ) )
		{
			foreach( \IPS\core\ShareLinks\Service::roots() as $node )
			{
				if ( $node->enabled AND $node->autoshare )
				{
					/* Do guests have permission to see this? */
					if ( $container and !$container->can( 'read', new \IPS\Member ) )
					{
						continue;
					}

					try
					{
						$class = \IPS\Content\ShareServices::getClassByKey( $node->key );

						if ( $class::canAutoshare() )
						{
							$return["auto_share_{$node->key}"] = new \IPS\Helpers\Form\Checkbox( "auto_share_{$node->key}", 0, FALSE );
						}
					}
					catch ( \InvalidArgumentException $e )
					{
					}
				}
			}
		}
		
		/* Polls */
		if ( \in_array( 'IPS\Content\Polls', class_implements( \get_called_class() ) ) and static::canCreatePoll( NULL, $container ) )
		{
			/* Can we create a poll on this item? */
			$existingPoll = NULL;
			$canCreatePoll = FALSE;
			
			if ( $item )
			{
				$existingPoll = $item->getPoll();
				
				/* If there's already a poll, we can edit it... */
				if ( $existingPoll )
				{
					$canCreatePoll = TRUE;
				}
				/* Otherwise, it depends on the cutoff for adding a poll */
				else
				{
					if ( ! empty( \IPS\Settings::i()->startpoll_cutoff ) )
					{
						$canCreatePoll = ( \IPS\Settings::i()->startpoll_cutoff == -1 or \IPS\DateTime::create()->sub( new \DateInterval( 'PT' . \IPS\Settings::i()->startpoll_cutoff . 'H' ) )->getTimestamp() < $item->mapped('date') );
					}
				}
			}
			else
			{
				/* If this is a new item, we can create a poll */
				$canCreatePoll = TRUE;
			}
			
			/* Create form element */
			if ( $canCreatePoll )
			{
				$return['poll'] = new \IPS\Helpers\Form\Poll( static::$formLangPrefix . 'poll', $existingPoll, FALSE, array( 'allowPollOnly' => TRUE, 'itemClass' => \get_called_class() ) );
			}
		}

		/* Show the future date field for new items or while editing an item, but only if the item wasn't published yet */
		if ( static::supportsPublishDate( $item ) and static::canFuturePublish( NULL, $container ) )
		{
			$return['date'] = static::getPublishDateField( $item );
		}
		
		return $return;
	}

	/**
	 * Returns the Date Field for the publish date
	 * 
	 * @param \IPS\Content|NULL $item
	 * @return \IPS\Helpers\Form\Date
	 */
	protected static function getPublishDateField( \IPS\Content $item = NULL ): \IPS\Helpers\Form\Date
	{
		/* If it's not published, we don't want to allow any past times */
		$column = static::$databaseColumnMap['future_date'];
		
		$minFutureTime = static::getMinimumPublishDate();
		$unlimited = 0;
		$unlimitedLang = 'immediately';
		if( $item )
		{
			$unlimited = NULL;
			$unlimitedLang = NULL;
		}

		return new \IPS\Helpers\Form\Date( static::$formLangPrefix . 'date', ( $item and $item->$column ) ? \IPS\DateTime::ts( $item->$column ) : 0, FALSE, array( 'time' => TRUE, 'unlimited' => $unlimited, 'unlimitedLang' => $unlimitedLang, 'min' => $minFutureTime ), NULL, NULL, NULL,  static::$formLangPrefix . 'date' );
	}

	/**
	 * Can the publish date be changed while editing the item?
	 *
	 * @var bool
	 */
	public static bool $allowPublishDateWhileEditing = FALSE;

	/**
	 * Whether this content supports future publish dates
	 * 
	 * @return bool
	 */
	protected static function supportsPublishDate( ?\IPS\Content\Item $item ): bool
	{
		return \in_array( 'IPS\Content\FuturePublishing', class_implements( \get_called_class() ) ) and isset( static::$databaseColumnMap['future_date'] ) AND ( ( $item AND ( $item->isFutureDate() OR static::$allowPublishDateWhileEditing ) OR !$item ) );
	}

	/**
	 * Returns the earliest publish date for the new content item , should be the current timestamp for most content types
	 *
	 * @return \IPS\DateTime|null
	 */
	protected static function getMinimumPublishDate(): ?\IPS\DateTime
	{
		return \IPS\DateTime::create();
	}

	/**
	 * Generate the tags form element
	 *
	 * @note	It is up to the calling code to verify the tag input field should be shown
	 * @param	\IPS\Content\Item|NULL	$item		Item, if editing
	 * @param	\IPS\Node\Mode|NULL		$container	Container
	 * @param	bool					$minimized	If the form field should be minimized by default
	 * @return	\IPS\Helpers\Form\Text|NULL
	 */
	public static function tagsFormField( $item, $container, $minimized = FALSE )
	{
		/* If this is an open tag system, we use a URI callback for source */
		if( \IPS\Settings::i()->tags_open_system )
		{
			$source = 'app=core&module=system&controller=ajax&do=findTags&class=' . \get_called_class();

			if( $container )
			{
				$source .= '&container=' . $container->_id;
			}
		}
		else
		{
			/* Include existing tags in case we're editing */
			if ( $item )
			{
				$source = $item->prefix() ? array_merge( array( 'prefix' => $item->prefix() ), $item->tags() ) : $item->tags();
			}
			else
			{
				$source = array();
			}

			/* And get the defined tags */
			if( static::definedTags( $container ) )
			{
				$source = array_unique( array_merge( $source, static::definedTags( $container ) ) );
			}
		}

		$options = array( 'autocomplete' => array( 'unique' => TRUE, 'source' => $source, 'resultItemTemplate' => 'core.autocomplete.tagsResultItem', 'freeChoice' => \IPS\Settings::i()->tags_open_system ? TRUE : FALSE, 'lang' => 'tags_optional' ) );

		if ( \IPS\Settings::i()->tags_force_lower )
		{
			$options['autocomplete']['forceLower'] = TRUE;
		}
		if ( \IPS\Settings::i()->tags_min )
		{
			$options['autocomplete']['minItems'] = \IPS\Settings::i()->tags_min;
		}
		if ( \IPS\Settings::i()->tags_max )
		{
			$options['autocomplete']['maxItems'] = \IPS\Settings::i()->tags_max;
		}
		if ( \IPS\Settings::i()->tags_len_min )
		{
			$options['autocomplete']['minLength'] = \IPS\Settings::i()->tags_len_min;
		}
		if ( \IPS\Settings::i()->tags_len_max )
		{
			$options['autocomplete']['maxLength'] = \IPS\Settings::i()->tags_len_max;
		}
		if ( \IPS\Settings::i()->tags_clean )
		{
			$options['autocomplete']['filterProfanity'] = TRUE;
		}
		if ( \IPS\Settings::i()->tags_alphabetical )
		{
			$options['autocomplete']['alphabetical'] = TRUE;
		}
		
		$options['autocomplete']['prefix'] = static::canPrefix( NULL, $container );
		$options['autocomplete']['disallowedCharacters'] = array( '#' ); // @todo Pending \IPS\Http\Url rework, hashes cannot be used in URLs

		if ( $minimized )
		{
			$options['autocomplete']['minimized'] = TRUE;
		}

		/* Language strings for tags description */
		if ( \IPS\Settings::i()->tags_open_system )
		{
			$extralang = array();

			if ( \IPS\Settings::i()->tags_min && \IPS\Settings::i()->tags_max )
			{
				$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_min_max', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->tags_max ), 'pluralize' => array( \IPS\Settings::i()->tags_min ) ) );
			}
			else if( \IPS\Settings::i()->tags_min )
			{
				$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_min', FALSE, array( 'pluralize' => array( \IPS\Settings::i()->tags_min ) ) );
			}
			else if( \IPS\Settings::i()->tags_min )
			{
				$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_max', FALSE, array( 'pluralize' => array( \IPS\Settings::i()->tags_max ) ) );
			}

			if( \IPS\Settings::i()->tags_len_min && \IPS\Settings::i()->tags_len_max )
			{
				$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_len_min_max', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->tags_len_min, \IPS\Settings::i()->tags_len_max ) ) );
			}
			else if( \IPS\Settings::i()->tags_len_min )
			{
				$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_len_min', FALSE, array( 'pluralize' => array( \IPS\Settings::i()->tags_len_min ) ) );
			}
			else if( \IPS\Settings::i()->tags_len_max )
			{
				$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_len_max', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->tags_len_max ) ) );
			}

			$options['autocomplete']['desc'] = \IPS\Member::loggedIn()->language()->addToStack('tags_desc') . ( ( \count( $extralang ) ) ? '<br>' . implode( ' ', $extralang ) : '' );
		}
					
		if ( $options['autocomplete']['freeChoice'] or \count( $options['autocomplete']['source'] ) )
		{
			$containerClass = static::$containerNodeClass;
			$containerFieldName = static::$formLangPrefix . 'container';
			$thisClass = \get_called_class();
			return new \IPS\Helpers\Form\Text( static::$formLangPrefix . 'tags', $item ? ( $item->prefix() ? array_merge( array( 'prefix' => $item->prefix() ), $item->tags() ) : $item->tags() ) : array(), ( \IPS\Settings::i()->tags_min and \IPS\Settings::i()->tags_min_req ) ? ( $container ? TRUE : NULL ) : FALSE, $options, function ( $val ) use ( $container, $containerClass, $containerFieldName, $thisClass ) {
				if ( empty( $val ) and \IPS\Settings::i()->tags_min and \IPS\Settings::i()->tags_min_req )
				{
					if ( !$container )
					{
						try
						{
							$container = $containerClass::load( \IPS\Request::i()->$containerFieldName );
						}
						catch ( \Exception $e )
						{
							return TRUE;
						}
						if ( $thisClass::canTag( NULL, $container ) )
						{
							throw new \DomainException('form_required');
						}
					}
				}
			} );
		}

		return NULL;
	}
	
	/**
	 * Process created object BEFORE the object has been created
	 *
	 * @param	array				$values	Values from form
	 * @return	void
	 */
	protected function processBeforeCreate( $values ) {

		/* Check for banned IP - The banned ip addresses are only checked inside the register and login controller, so people are able to bypass them when PBR is used */
		if( !\IPS\Member::loggedIn()->member_id AND \IPS\Request::i()->ipAddressIsBanned() )
		{
			\IPS\Output::i()->showBanned();
		}

	}
	
	/**
	 * Process created object AFTER the object has been created
	 *
	 * @param	\IPS\Content\Comment|NULL	$comment	The first comment
	 * @param	array						$values		Values from form
	 * @return	void
	 */
	protected function processAfterCreate( $comment, $values )
	{
		/* Add to search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}

		/* Are we tracking keywords? */
		$this->checkKeywords( $comment ? $comment->mapped('content') : $this->mapped('content'), $this->mapped('title') );
		
		/* Send webhook */
		if ( \in_array( $this->hidden(), array( -1, 0, 1 ) ) ) // i.e. not post before register or pending deletion
		{
			\IPS\Api\Webhook::fire( str_replace( '\\', '', \substr( \get_called_class(), 3 ) ) . '_create', $this, $this->webhookFilters() );
		}

		/* Data Layer Event */
		if ( \IPS\Settings::i()->core_datalayer_enabled )
		{
			/* Is it a status update? */
			if ( isset( static::$title ) and static::$title === \IPS\core\Statuses\Status::$title )
			{
				$member = \IPS\Member::load( $this->member_id );
				$properties = array(
					'profile_id'   => $member->member_id,
					'profile_name' => $member->real_name ?: null,
				);
				\IPS\core\DataLayer::i()->addEvent( 'social_update', $properties );
			}
			/* Otherwise, make sure it isn't a PM Conversation */
			elseif ( !isset( static::$title ) or static::$title !== \IPS\core\Messenger\Conversation::$title )
			{
				\IPS\core\DataLayer::i()->addEvent( 'content_create', $this->getDataLayerProperties() );
			}
		}

		/* Send this URL to IndexNow if the guest can view it */
		if ( $this->canView( new \IPS\Member ) AND !static::$skipIndexNow )
		{
			\IPS\core\IndexNow::addUrlToQueue( $this->url() );
		}
		
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
						if ( $this->container() AND $this->container()->contentHeldForApprovalByNode( 'item', $this->author() ) === TRUE )
						{
							$log->held_reason = 'node';
							$foundReason = TRUE;
						}
					}
					catch( \BadMethodCallException $e ) { }
				}
				
				if ( $foundReason )
				{
					$log->save();
				}	
			}
		}
	}
	
	/**
	 * Process after the object has been edited on the front-end
	 *
	 * @param	array	$values		Values from form
	 * @return	void
	 */
	public function processAfterEdit( $values )
	{
		/* Add to search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}

		/* Initial Comment */
		if ( isset( static::$commentClass ) and static::$firstCommentRequired )
		{
			$commentClass = static::$commentClass;
			$commentObj = $this->firstComment();
			$column = $commentClass::$databaseColumnMap['content'];
			$idField = $commentClass::$databaseColumnId;

			/* Update the comment date, in case the topic scheduled publish date has changed */
			if( isset( $values[ static::$formLangPrefix . 'date' ] ) and $values[ static::$formLangPrefix . 'date' ] instanceof \IPS\DateTime and isset( $commentClass::$databaseColumnMap['date'] ) )
			{
				$dateColumn = $commentClass::$databaseColumnMap['date'];
				$commentObj->$dateColumn = $values[ static::$formLangPrefix . 'date' ]->getTimestamp();
			}

			if ( $commentObj instanceof \IPS\Content\EditHistory and \IPS\Settings::i()->edit_log )
			{
				$editIsPublic = \IPS\Member::loggedIn()->group['g_append_edit'] ? $values['comment_log_edit'] : TRUE;

				if ( \IPS\Settings::i()->edit_log == 2 )
				{
					\IPS\Db::i()->insert( 'core_edit_history', array(
						'class' => \get_class( $commentObj ),
						'comment_id' => $commentObj->$idField,
						'member' => \IPS\Member::loggedIn()->member_id,
						'time' => time(),
						'old' => $commentObj->$column,
						'new' => $values[static::$formLangPrefix . 'content'],
						'public' => $editIsPublic,
						'reason' => isset( $values['comment_edit_reason'] ) ? $values['comment_edit_reason'] : NULL,
					) );
				}

				if ( isset( $commentClass::$databaseColumnMap['edit_reason'] ) and isset( $values['comment_edit_reason'] ) )
				{
					$field = $commentClass::$databaseColumnMap['edit_reason'];
					$commentObj->$field = $values['comment_edit_reason'];
				}
				if ( isset( $commentClass::$databaseColumnMap['edit_time'] ) )
				{
					$field = $commentClass::$databaseColumnMap['edit_time'];
					$commentObj->$field = time();
				}
				if ( isset( $commentClass::$databaseColumnMap['edit_member_id'] ) )
				{
					$field = $commentClass::$databaseColumnMap['edit_member_id'];
					$commentObj->$field = \IPS\Member::loggedIn()->member_id;
				}
				if ( isset( $commentClass::$databaseColumnMap['edit_member_name'] ) )
				{
					$field = $commentClass::$databaseColumnMap['edit_member_name'];
					$commentObj->$field = \IPS\Member::loggedIn()->name;
				}
				if ( isset( $commentClass::$databaseColumnMap['edit_show'] ) and $editIsPublic )
				{
					$field = $commentClass::$databaseColumnMap['edit_show'];
					$commentObj->$field = \IPS\Member::loggedIn()->group['g_append_edit'] ? $values['comment_log_edit'] : TRUE;
				}
				else
				{
					if ( isset( $commentClass::$databaseColumnMap['edit_show'] ) )
					{
						$field = $commentClass::$databaseColumnMap['edit_show'];
						$commentObj->$field = 0;
					}
				}

				/* Check if profanity filters should mod-queue this comment */
				$sendNotifications = $commentObj->checkProfanityFilters( TRUE, TRUE, $values[ static::$formLangPrefix . 'content'] );

				/* Send notifications */
				if ( $sendNotifications AND !\in_array( 'IPS\Content\Review', class_parents( \get_called_class() ) ) )
				{
					if ( $commentObj->hidden() === 1 )
					{
						$commentObj->sendUnapprovedNotification();
					}
				}
			}

			$oldValue = $commentObj->$column;
			$commentObj->$column = $values[ static::$formLangPrefix . 'content'];
			$commentObj->save();
			$commentObj->sendAfterEditNotifications( $oldValue );

			if ( $commentObj instanceof \IPS\Content\Searchable )
			{
				\IPS\Content\Search\Index::i()->index( $commentObj );
			}
		}
		else if ( !static::$firstCommentRequired  AND $this instanceof \IPS\Content\EditHistory and \IPS\Settings::i()->edit_log )
		{
			$this->logEdit( $values );
		}
		
		$container = $this->containerWrapper();
		
		if ( $this instanceof \IPS\Content\FuturePublishing AND isset( $values[ static::$formLangPrefix . 'date' ] ) )
		{
			$column    = static::$databaseColumnMap['is_future_entry'];

			if ( $container AND $this->$column )
			{
				if ( ( ! ( $values[ static::$formLangPrefix . 'date' ] instanceof \IPS\DateTime ) AND $values[ static::$formLangPrefix . 'date' ] == 0 ) OR ( $values[ static::$formLangPrefix . 'date' ] instanceof \IPS\DateTime AND $values[ static::$formLangPrefix . 'date' ]->getTimestamp() <= time() ) )
				{
					/* Was future, now not */
					$this->publish();
				}
			}
		}

		/* Post anonymously */
		if( isset( $values[ 'post_anonymously' ] ) and ( $container and $container->canPostAnonymously( $container::ANON_ITEMS ) ) )
		{
			$this->setAnonymous( $values[ 'post_anonymously' ], $this->author() );
		}

		/* Send this URL to IndexNow if the guest can view it */
		if( $this->canView( new \IPS\Member ) )
		{
			\IPS\core\IndexNow::addUrlToQueue( $this->url() );
		}

	}

	/* Holds the old content for edit logging */
	protected $oldContent = NULL;

	/**
	 * Set value in data store
	 *
	 * @see		\IPS\Patterns\ActiveRecord::save
	 * @param	mixed	$key	Key
	 * @param	mixed	$value	Value
	 * @return	void
	 */
	public function __set( $key, $value )
	{
		if( !$this->_new AND \IPS\Settings::i()->edit_log == 2 AND isset( $this::$databaseColumnMap['content'] ) )
		{
			$column = $this::$databaseColumnMap['content'];
			if ( $key === $column )
			{
				$this->oldContent = $this->$column;
			}
		}

		parent::__set($key, $value);
	}


	/**
	 * Edit logging
	 *
	 * @param array $values
	 */
	protected function logEdit( array $values )
	{
		$editIsPublic = \IPS\Member::loggedIn()->group['g_append_edit'] ? $values['log_edit'] : TRUE;
		$idField = $this::$databaseColumnId;
		$column = $this::$databaseColumnMap['content'];

		if ( \IPS\Settings::i()->edit_log == 2 )
		{
			$content =  $values[static::$formLangPrefix . $column];

			\IPS\Db::i()->insert( 'core_edit_history', array(
				'class' => \get_class( $this ),
				'comment_id' => $this->$idField,
				'member' => \IPS\Member::loggedIn()->member_id,
				'time' => time(),
				'old' => $this->oldContent,
				'new' => $content,
				'public' => $editIsPublic,
				'reason' => isset( $values['edit_reason'] ) ? $values['edit_reason'] : NULL,
			) );
		}

		if ( isset( $this::$databaseColumnMap['edit_reason'] ) and isset( $values['edit_reason'] ) )
		{
			$field = $this::$databaseColumnMap['edit_reason'];
			$this->$field = $values['edit_reason'];
		}
		if ( isset( $this::$databaseColumnMap['edit_time'] ) )
		{
			$field = $this::$databaseColumnMap['edit_time'];
			$this->$field = time();
		}
		if ( isset( $this::$databaseColumnMap['edit_member_id'] ) )
		{
			$field = $this::$databaseColumnMap['edit_member_id'];
			$this->$field = \IPS\Member::loggedIn()->member_id;
		}
		if ( isset( $this::$databaseColumnMap['edit_member_name'] ) )
		{
			$field = $this::$databaseColumnMap['edit_member_name'];
			$this->$field = \IPS\Member::loggedIn()->name;
		}
		if ( isset( $this::$databaseColumnMap['edit_show'] ) and $editIsPublic )
		{
			$field = $this::$databaseColumnMap['edit_show'];
			$this->$field = \IPS\Member::loggedIn()->group['g_append_edit'] ? $values['log_edit'] : TRUE;
		}
		else
		{
			if ( isset( $this::$databaseColumnMap['edit_show'] ) )
			{
				$field = $this::$databaseColumnMap['edit_show'];
				$this->$field = 0;
			}
		}
		$this->save();
	}

	/**
	 * Callback to execute when tags are edited
	 *
	 * @return	void
	 */
	protected function processAfterTagUpdate()
	{
		/* If we edited tags for a topic, we have to manually update search index because it wants
			the first comment and not the content item itself. */
		if ( $this instanceof \IPS\Content\Item and $this instanceof \IPS\Content\Searchable and static::$firstCommentRequired and $this->firstComment() )
		{
			\IPS\Content\Search\Index::i()->index( $this->firstComment() );
		}
	}
	
	/**
	 * @brief	Container
	 */
	protected $container;

	/**
	 * Wrapper to get container. May return NULL if there is no container (e.g. private messages)
	 *
	 * @param	bool	$allowOutOfRangeException	If TRUE, will return NULL if the container doesn't exist rather than throw OutOfRangeException
	 * @return	\IPS\Node\Model|NULL
	 * @note	This simply wraps container()
	 * @see		container()
	 */
	public function containerWrapper( $allowOutOfRangeException = FALSE )
	{
		/* Get container, if valid */
		$container = NULL;

		try
		{
			$container = $this->container();
		}
		catch( \OutOfRangeException $e )
		{
			if ( !$allowOutOfRangeException )
			{
				throw $e;
			}
		}
		catch( \BadMethodCallException $e ){}

		return $container;
	}

	/**
	 * Get container
	 *
	 * @return	\IPS\Node\Model
	 * @note	Certain functionality requires a valid container but some areas do not use this functionality (e.g. messenger)
	 * @throws	\OutOfRangeException|\BadMethodCallException
	 */
	public function container()
	{
		if ( $this->container === NULL )
		{
			if ( !isset( static::$containerNodeClass ) or !isset( static::$databaseColumnMap['container'] ) )
			{
				throw new \BadMethodCallException;
			}

			$containerClass		= static::$containerNodeClass;
			$this->container	= $containerClass::load( $this->mapped('container') );
		}
		
		return $this->container;
	}

	/**
	 * Return the container class to store in the search index
	 *
	 * @return \IPS\Node\Model|NULL
	 */
	public function searchIndexContainerClass()
	{
		if( !$this->containerWrapper( true ) )
		{
			return NULL;
		}
		else
		{
			return $this->containerWrapper( true );
		}
	}
	
	/**
	 * Get container ID for search index
	 *
	 * @return	int
	 */
	public function searchIndexContainer()
	{
		return $this->mapped('container');
	}
	
	/**
	 * Get URL
	 *
	 * @param	string|NULL		$action		Action
	 * @return	\IPS\Http\Url
	 * @throws	\BadMethodCallException
	 * @throws	\IPS\Http\Url\Exception
	 */
	public function url( $action=NULL )
	{
		if( $action === 'getPrefComment' AND \IPS\Member::loggedIn()->member_id  )
		{
			$pref = \IPS\Member::loggedIn()->linkPref() ?: \IPS\Settings::i()->link_default;

			switch( $pref )
			{
				case 'unread':
					$action = \IPS\Member::loggedIn()->member_id ? 'getNewComment' : NULL;
					break;

				case 'last':
					$action = 'getLastComment';
					break;

				default:
					$action = NULL;
					break;
			}
		}
		elseif( ( $action == 'getPrefComment' OR $action == 'getNewComment' OR $action == 'getLastComment' ) AND !\IPS\Member::loggedIn()->member_id  )
		{
			$action = NULL;
		}

		if ( isset( static::$urlBase ) and isset( static::$urlTemplate ) and isset( static::$seoTitleColumn ) )
		{
			$_key	= md5( $action );
	
			if( !isset( $this->_url[ $_key ] ) )
			{
				$idColumn = static::$databaseColumnId;
				$seoTitleColumn = static::$seoTitleColumn;
				
				try
				{
					$this->_url[ $_key ] = \IPS\Http\Url::internal( static::$urlBase . $this->$idColumn, 'front', static::$urlTemplate, $this->$seoTitleColumn );
				}
				catch ( \IPS\Http\Url\Exception $e )
				{					
					if ( isset( static::$databaseColumnMap['title'] ) )
					{
						$titleColumn = static::$databaseColumnMap['title'];
						$correctSeoTitle = \IPS\Http\Url\Friendly::seoTitle( $this->$titleColumn );
						if ( $this->$seoTitleColumn != $correctSeoTitle )
						{
							$this->$seoTitleColumn = $correctSeoTitle;
							$this->save();
							return $this->url( $action );
						}
					}
					
					throw $e;
				}
			
				if ( $action )
				{
					$this->_url[ $_key ] = $this->_url[ $_key ]->setQueryString( 'do', $action );
				}
			}
		
			return $this->_url[ $_key ];
		}
		throw new \BadMethodCallException;
	}
	
	/**
	 * Get URL from index data
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	string|NULL	$action			Action
	 * @return	\IPS\Http\Url
	 */
	public static function urlFromIndexData( $indexData, $itemData, $action = NULL )
	{
		if( $action == 'getPrefComment' )
		{
			$pref = \IPS\Member::loggedIn()->linkPref() ?: \IPS\Settings::i()->link_default;

			switch( $pref )
			{
				case 'unread':
					$action = \IPS\Member::loggedIn()->member_id ? 'getNewComment' : NULL;
					break;

				case 'last':
					$action = 'getLastComment';
					break;

				default:
					$action = NULL;
					break;
			}
		}
		elseif( !\IPS\Member::loggedIn()->member_id AND $action == 'getNewComment' )
		{
			$action = NULL;
		}

		$url = \IPS\Http\Url::internal( static::$urlBase . $indexData['index_item_id'], 'front', static::$urlTemplate, \IPS\Http\Url\Friendly::seoTitle( $indexData['index_title'] ?: $itemData[ static::$databasePrefix . static::$databaseColumnMap['title'] ] ) );

		if( $action )
		{
			$url = $url->setQueryString( 'do', $action );
		}

		return $url;
	}
	
	/**
	 * Get title from index data
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	array|NULL	$containerData	Basic data about the author. Only includes columns returned by container::basicDataColumns()
	 * @return	\IPS\Http\Url
	 */
	public static function titleFromIndexData( $indexData, $itemData, $containerData )
	{
		return \IPS\Member::loggedIn()->language()->addToStack( static::$titleLangPrefix . $indexData['index_container_id'] );
	}
	
	/**
	 * Get mapped value
	 *
	 * @param	string	$key	date,content,ip_address,first
	 * @return	mixed
	 */
	public function mapped( $key )
	{
		$return = parent::mapped( $key );
		
		/* unapproved_comments etc may be set to NULL if the value has not yet been calculated */
		if ( $return === NULL and isset( static::$databaseColumnMap[ $key ] ) and \in_array( $key, array( 'unapproved_comments', 'hidden_comments', 'unapproved_reviews', 'hidden_reviews' ) ) )
		{			
			/* Work out if we're using the comment class or the review class */
			if ( $key === 'unapproved_comments' or $key === 'hidden_comments' )
			{
				$commentClass = static::$commentClass;
			}
			else
			{
				$commentClass = static::$reviewClass;
			}
			
			/* Set the intial where for the ID column */
			$idColumn = static::$databaseColumnId;
			$where = array( array( "{$commentClass::$databasePrefix}{$commentClass::$databaseColumnMap['item']}=?", $this->$idColumn ) );
			
			/* Work out the appropriate value to look for depending on if the class uses "approved" or "hidden" */
			if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
			{
				$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '=?', ( $key === 'unapproved_comments' or $key === 'unapproved_reviews' ) ? 0 : -1 );
			}
			else
			{
				$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '=?', ( $key === 'unapproved_comments' or $key === 'unapproved_reviews' ) ? 1 : -1 );
			}
			
			/* Query */
			$return = \IPS\Db::i()->select( 'COUNT(*)', $commentClass::$databaseTable, $where )->first();
			
			/* Save that value */
			$mappedKey = static::$databaseColumnMap[ $key ];
			$this->$mappedKey = $return;
			$this->save();			
		}
		
		return $return;
	}
	
	/**
	 * Returns the content
	 *
	 * @return	string
	 * @throws	\BadMethodCallException
	 */
	public function content()
	{
		if ( isset( static::$databaseColumnMap['content'] ) )
		{
			return parent::content();
		}
		elseif ( static::$commentClass )
		{
			if ( $comment = $this->firstComment() )
			{
				return $comment->content();
			}
			else
			{
				throw new \BadMethodCallException;
			}
		}
		else
		{
			throw new \BadMethodCallException;
		}
	}
	
	/**
	 * Returns the content images
	 *
	 * @param	int|null	$limit				Number of attachments to fetch, or NULL for all
	 * @param	bool		$ignorePermissions	If set to TRUE, permission to view the images will not be checked
	 * @return	array|NULL
	 * @throws	\BadMethodCallException
	 */
	public function contentImages( $limit = NULL, $ignorePermissions = FALSE )
	{
		$idColumn = static::$databaseColumnId;
		$internal = array();
		$attachments = array();
		$loadedExtensions = array();
		
		/* Get attachments from the content, or all comments */
		if ( isset( static::$databaseColumnMap['content'] ) or isset( static::$commentClass ) )
		{
			$internal = iterator_to_array( \IPS\Db::i()->select( '*', 'core_attachments_map', array( 'location_key=? and id1=?', static::$application . '_' . mb_ucfirst( static::$module ), $this->$idColumn ) )->setKeyField('attachment_id') );
		}
						
		if ( $internal )
		{
			foreach( \IPS\Db::i()->select( '*', 'core_attachments', array( array( \IPS\Db::i()->in( 'attach_id', array_keys( $internal ) ) ), array( 'attach_is_image=1' ) ), 'attach_id ASC', $limit ) as $row )
			{
				if( $ignorePermissions )
				{
					$attachments[] = array( 'core_Attachment' => $row['attach_location'] );
				}
				else
				{
					$map = $internal[ $row['attach_id'] ];	
					
					if ( !isset( $loadedExtensions[ $map['location_key'] ] ) )
					{
						$exploded = explode( '_', $map['location_key'] );
						try
						{
							$extensions = \IPS\Application::load( $exploded[0] )->extensions( 'core', 'EditorLocations' );
							if ( isset( $extensions[ $exploded[1] ] ) )
							{
								$loadedExtensions[ $map['location_key'] ] = $extensions[ $exploded[1] ];
							}
						}
						catch ( \OutOfRangeException $e ) { }
					}
									
					if ( isset( $loadedExtensions[ $map['location_key'] ] ) )
					{		
						try
						{
							if ( $loadedExtensions[ $map['location_key'] ]->attachmentPermissionCheck( \IPS\Member::loggedIn(), $map['id1'], $map['id2'], $map['id3'], $row, TRUE ) )
							{
								$attachments[] = array( 'core_Attachment' => $row['attach_location'] );
							}
						}
						catch ( \Exception $e ) { }
					}
				}
			}
		}

		/* IS there a club with a cover photo? */
		if( $container = $this->containerWrapper() )
		{
			if ( \IPS\IPS::classUsesTrait( $container, 'IPS\Content\ClubContainer' ) and $club = $container->club() )
			{
				$attachments[] = array( 'core_Clubs' => $club->cover_photo );
			}
		}
		
		return \count( $attachments ) ? \array_slice( $attachments, 0, $limit ) : NULL;
	}
	
	/**
	 * Returns the meta description
	 *
	 * @param	string|NULL	$return	Specific description to use (useful for paginated displays to prevent having to run extra queries)
	 * @return	string
	 * @throws	\BadMethodCallException
	 */
	public function metaDescription( $return = NULL )
	{
		if( $return === NULL AND isset( $_SESSION['_findComment'] ) )
		{
			$commentId	= $_SESSION['_findComment'];
			unset( $_SESSION['_findComment'] );

			$commentClass	= static::$commentClass;
			
			if( $commentClass !== NULL )	
			{
				try
				{
					$comment = $commentClass::loadAndCheckPerms( $commentId );

					$return = $comment->content();
				}
				catch( \Exception $e ){}
			}
		}
		
		if ( $return === NULL )
		{
			if ( isset( static::$databaseColumnMap['content'] ) )
			{
				$return = parent::content();
			}
			elseif( static::$firstCommentRequired AND $comment = $this->firstComment() )
			{
				$return = $comment->content();
			}
			else
			{
				$return = $this->mapped('title');
			}
		}
		
		if ( $return )
		{
			$return =  trim( preg_replace( "/\s+/um", " ", str_replace( '&nbsp;', ' ', strip_tags( preg_replace('#(<(script|style)\b[^>]*>).*?(</\2>)#is', "$1$3", $return ) ) ) ) );
			if ( mb_strlen( $return ) > 300 )
			{
				$return = mb_substr( $return, 0, 297 ) . '...';
			}
		}
		
		return $return;
	}
	
	/**
	 * @brief	Hot stats
	 */
	public $hotStats = array();
	
	/**
	 * Stats for table view
	 *
	 * @param	bool	$includeFirstCommentInCommentCount	Determines whether the first comment should be inlcluded in the comment \count(e.g. For "posts", use TRUE. For "replies", use FALSE)
	 * @return	array
	 */
	public function stats( $includeFirstCommentInCommentCount=TRUE )
	{
		$return = array();

		if ( static::$commentClass )
		{
			$return['comments'] = (int) $this->mapped('num_comments');
			if ( !$includeFirstCommentInCommentCount )
			{
				$return['comments']--;
			}

			if ( $return['comments'] < 0 )
			{
				$return['comments'] = 0;
			}
		}
		
		if ( \IPS\IPS::classUsesTrait( $this, 'IPS\Content\ViewUpdates' ) )
		{
			$return['num_views'] = (int) $this->mapped('views');
		}
		
		return $return;
	}
	
	/**
	 * Move
	 *
	 * @param	\IPS\Node\Model	$container	Container to move to
	 * @param	bool			$keepLink	If TRUE, will keep a link in the source
	 * @return	void
	 */
	public function move( \IPS\Node\Model $container, $keepLink=FALSE )
	{
		/* Reduce the counts in the old node */
		$oldContainer = $this->container();

		if ( $this->isFutureDate() and $oldContainer->_futureItems !== NULL )
		{
			$oldContainer->_futureItems = \intval( $oldContainer->_futureItems - 1 );
		}
		else if ( !$this->hidden() )
		{
			if ( $oldContainer->_items !== NULL )
			{
				$oldContainer->_items = \intval( $oldContainer->_items - 1 );
			}
			if ( isset( static::$commentClass ) and $oldContainer->_comments !== NULL )
			{
				$oldContainer->_comments = \intval( $oldContainer->_comments - $this->mapped('num_comments') );
			}
			if ( isset( static::$reviewClass ) and $oldContainer->_reviews !== NULL )
			{
				$oldContainer->_reviews = \intval( $oldContainer->_reviews - $this->mapped('num_reviews') );
			}
		}
		elseif ( $this->hidden() === 1 and $oldContainer->_unapprovedItems !== NULL )
		{
			$oldContainer->_unapprovedItems = \intval( $oldContainer->_unapprovedItems - 1 );
		}

		if ( isset( static::$commentClass ) and $oldContainer->_unapprovedComments !== NULL and isset( static::$databaseColumnMap['unapproved_comments'] ) )
		{
			$oldContainer->_unapprovedComments = \intval( $oldContainer->_unapprovedComments - $this->mapped('unapproved_comments') );
		}
		if ( isset( static::$reviewClass ) and $oldContainer->_unapprovedReviews !== NULL and isset( static::$databaseColumnMap['unapproved_reviews'] ) )
		{
			$oldContainer->_unapprovedReviews = \intval( $oldContainer->_unapprovedReviews - $this->mapped('unapproved_reviews') );
		}

		/* Make a link */
		if ( $keepLink )
		{
			$link = clone $this;
			$movedToColumn = static::$databaseColumnMap['moved_to'];
			$idColumn = static::$databaseColumnId;
			$link->$movedToColumn = $this->$idColumn . '&' . $container->_id;
			
			/* Do not keep comment counts on the link item */
			if ( isset( static::$databaseColumnMap['num_comments'] ) )
			{
				$commentsColumn = static::$databaseColumnMap['num_comments'];
				$link->$commentsColumn = 0;
			}
			
			if ( isset( static::$databaseColumnMap['num_reviews'] ) )
			{
				$reviewsColumn = static::$databaseColumnMap['num_reviews'];
				$link->$reviewsColumn = 0;
			}
			
			if ( isset( static::$databaseColumnMap['unapproved_comments'] ) )
			{
				$unapprovedComments = static::$databaseColumnMap['unapproved_comments'];
				$link->$unapprovedComments = 0;
			}
			
			if ( isset( static::$databaseColumnMap['unapproved_reviews'] ) )
			{
				$unapprovedReviews = static::$databaseColumnMap['unapproved_reviews'];
				$link->$unapprovedReviews = 0;
			}
			
			if ( isset( static::$databaseColumnMap['state'] ) )
			{
				$stateColumn = static::$databaseColumnMap['state'];
				$link->$stateColumn = 'link';
			}
			if ( isset( static::$databaseColumnMap['moved_on'] ) )
			{
				$movedOnColumn = static::$databaseColumnMap['moved_on'];
				$link->$movedOnColumn = time();
			}
			
			$link->save();
		}
		
		/* Change container */
		$column = static::$databaseColumnMap['container'];
		$this->$column = $container->_id;
		$this->save();
		$this->container = $container;
	
		/* Rebuild tags */
		$containerClass = static::$containerNodeClass;
		if ( $this instanceof \IPS\Content\Tags )
		{
			/* If the user can post tags in the destination forum, then we will want to retain the tags */
			if( static::canTag( $this->author(), $container ) )
			{
				\IPS\Db::i()->update( 'core_tags', array(
					'tag_aap_lookup'		=> $this->tagAAPKey(),
					'tag_meta_parent_id'	=> $container->_id
				), array( 'tag_aai_lookup=?', $this->tagAAIKey() ) );

				if ( isset( $containerClass::$permissionMap['read'] ) )
				{
					\IPS\Db::i()->update( 'core_tags_perms', array(
						'tag_perm_aap_lookup'	=> $this->tagAAPKey(),
						'tag_perm_text'			=> \IPS\Db::i()->select( 'perm_' . $containerClass::$permissionMap['read'], 'core_permission_index', array( 'app=? AND perm_type=? AND perm_type_id=?', $containerClass::$permApp, $containerClass::$permType, $container->_id ) )->first()
					), array( 'tag_perm_aai_lookup=?', $this->tagAAIKey() ) );
				}
			}
			else
			{
				$tagsToKeep = array();

				/* We need to ensure we retain tags that were set by users (i.e. moderators) who can post tags in the destination forum */
				foreach( \IPS\Db::i()->select( '*', 'core_tags', array( 'tag_aai_lookup=?', $this->tagAAIKey() ) ) as $tag )
				{
					if( static::canTag( \IPS\Member::load( $tag['tag_member_id'] ), $container ) )
					{
						if( $tag['tag_prefix'] )
						{
							$tagsToKeep['prefix']	= $tag['tag_text'];
						}
						else
						{
							$tagsToKeep[]	= $tag['tag_text'];
						}
					}
				}

				$this->setTags( $tagsToKeep );
			}
		}
		
		/* Update the counts in the new node */
		if ( $this->isFutureDate() and $container->_futureItems !== NULL )
		{
			$container->_futureItems = ( $container->_futureItems + 1 );
		}
		elseif ( !$this->hidden() )
		{
			if ( $container->_items !== NULL )
			{
				$container->_items = ( $container->_items + 1 );
			}
			if ( isset( static::$commentClass ) and $container->_comments !== NULL )
			{
				$container->_comments = ( $container->_comments + $this->mapped('num_comments') );
			}
			if ( isset( static::$reviewClass ) and $this->container()->_reviews !== NULL )
			{
				$container->_reviews = ( $container->_reviews + $this->mapped('num_reviews') );
			}
		}
		elseif ( $this->hidden() === 1 and $container->_unapprovedItems !== NULL )
		{
			$container->_unapprovedItems = ( $container->_unapprovedItems + 1 );
		}
		if ( isset( static::$commentClass ) and $container->_unapprovedComments !== NULL and isset( static::$databaseColumnMap['unapproved_comments'] ) )
		{
			$container->_unapprovedComments = ( $container->_unapprovedComments + $this->mapped('unapproved_comments') );
		}
		if ( isset( static::$reviewClass ) and $this->container()->_unapprovedReviews !== NULL and isset( static::$databaseColumnMap['unapproved_reviews'] ) )
		{
			$container->_unapprovedReviews = ( $container->_unapprovedReviews + $this->mapped('unapproved_reviews') );
		}
				
		/* Rebuild node data */
		if( !$this->skipContainerRebuild )
		{
			$oldContainer->setLastComment();
			$oldContainer->setLastReview();
			$oldContainer->save();
			$container->setLastComment();
			$container->setLastReview();
			$container->save();
		}

		/* Add to search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
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
		}

		/* Update reports */
		\IPS\Db::i()->update( 'core_rc_index', array( 'node_id' => $container->_id ), array( 'class=? and content_id=?', \get_class( $this ), $oldContainer->_id ) );

		/* Update caches */
		$this->expireWidgetCaches();

		try
		{
			$this->adjustSessions();
		}
		catch( \LogicException $e ) {}

		/* If we have a link, mark it read */
		if ( $keepLink )
		{
			$link->markRead();
		}
	}
	
	/**
	 * Moved to
	 *
	 * @return	static|NULL
	 */
	public function movedTo()
	{
		if ( isset( static::$databaseColumnMap['moved_to'] ) )
		{
			$exploded = explode( '&', $this->mapped('moved_to') );
			try
			{
				return static::load( $exploded[0] );
			}
			catch ( \Exception $e ) { }
		}
	}
	
	/**
	 * Get Next Item
	 *
	 * @return	static|NULL
	 */
	public function nextItem()
	{
		try
		{
			$column = $this->getDateColumn();
			$idColumn = static::$databaseColumnId;

			$item	= NULL;

			foreach( static::getItemsWithPermission( array(
				array( static::$databaseTable . '.' . static::$databasePrefix . $column . '>?', $this->$column ),
				array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['container'] . '=?', $this->container()->_id ),
				array( static::$databaseTable . '.' . static::$databasePrefix . $idColumn . '!=?', $this->$idColumn )
			), static::$databasePrefix . $column . ' ASC', 1 ) AS $item )
			{
				break;
			}

			return $item;
		}
		catch( \Exception $e ) { }
	}
	
	/**
	 * Get Previous Item
	 *
	 * @return	static|NULL
	 */
	public function prevItem()
	{
		try
		{
			$column = $this->getDateColumn();
			$idColumn = static::$databaseColumnId;

			$item	= NULL;
			foreach( static::getItemsWithPermission( array(
				array( static::$databaseTable . '.' . static::$databasePrefix . $column . '<?', $this->$column ),
				array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['container'] . '=?', $this->container()->_id ),
				array( static::$databaseTable . '.' . static::$databasePrefix . $idColumn . '!=?', $this->$idColumn )
			), static::$databasePrefix . $column . ' DESC', 1 ) AS $item )
			{
				break;
			}
			
			return $item;
		}
		catch( \Exception $e ) { }
	}

	/**
	 * Get date column for next/prev item
	 * Does not use last comment / last review as these will often be 0 and is not how items are generally ordered
	 *
	 * @return	string
	 */
	protected function getDateColumn()
	{
		if( isset( static::$databaseColumnMap['updated'] ) )
		{
			$column	= \is_array( static::$databaseColumnMap['updated'] ) ? static::$databaseColumnMap['updated'][0] : static::$databaseColumnMap['updated'];
		}
		else if( isset( static::$databaseColumnMap['date'] ) )
		{
			$column	= \is_array( static::$databaseColumnMap['date'] ) ? static::$databaseColumnMap['date'][0] : static::$databaseColumnMap['date'];
		}

		return $column;
	}
	
	/**
	 * Merge other items in (they will be deleted, this will be kept)
	 *
	 * @param	array	$items		Items to merge in
	 * @param	bool	$keepLinks	Retain redirect links for the items that were merge in
	 * @return	void
	 */
	public function mergeIn( array $items, $keepLinks=FALSE )
	{
		$idColumn = static::$databaseColumnId;
		$views    = 0;		
		foreach ( $items as $item )
		{
			if ( isset( static::$commentClass ) )
			{
				$commentClass = static::$commentClass;
				
				if ( \in_array( 'IPS\Content\Hideable', class_implements( $commentClass ) ) and isset( $commentClass::$databaseColumnMap['hidden'] ) )
				{
					if ( $item->hidden() and !$this->hidden() )
					{
						\IPS\Db::i()->update( $commentClass::$databaseTable, array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] => 0 ), array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=? AND ' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '=2', $item->$idColumn ) );
					}
					elseif ( $this->hidden() and !$item->hidden() )
					{
						\IPS\Db::i()->update( $commentClass::$databaseTable, array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] => 2 ), array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=? AND ' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '=0', $item->$idColumn ) );
					}
				}
				
				$commentUpdate = array();
				$commentUpdate[ $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] ] = $this->$idColumn;
				if ( isset( $commentClass::$databaseColumnMap['first'] ) )
				{
					/* This item is being merged into another, so any comments defined as "first" need to be reset */
					$commentUpdate[ $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['first'] ] = FALSE;
				}
				\IPS\Db::i()->update( $commentClass::$databaseTable, $commentUpdate, array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $item->$idColumn ) );
				
				\IPS\Content\Search\Index::i()->massUpdate( $commentClass, NULL, $item->$idColumn, $this->searchIndexPermissions(), $this->hidden() ? 2 : NULL, $this->searchIndexContainer(), NULL, $this->$idColumn, $this->author()->member_id );
			}
			if ( isset( static::$reviewClass ) )
			{
				$reviewClass = static::$reviewClass;
				$reviewUpdate = array();
				$reviewUpdate[ $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] ] = $this->$idColumn;

				\IPS\Db::i()->update( $reviewClass::$databaseTable, $reviewUpdate, array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $item->$idColumn ) );
				\IPS\Content\Search\Index::i()->massUpdate( $reviewClass, NULL, $item->$idColumn, $this->searchIndexPermissions(), $this->hidden() ? 2 : NULL, $this->searchIndexContainer(), NULL, $this->$idColumn, $this->author()->member_id );
			}
						
			/* Merge view counts */
			if ( \IPS\IPS::classUsesTrait( $this, 'IPS\Content\ViewUpdates' ) )
			{
				$views += $item->mapped('views');
				\IPS\Db::i()->update( 'core_view_updates', array( 'id' => $this->$idColumn ), array( 'classname=? and id=?', (string) \get_class( $item ), $item->$idColumn ) );
			}
			
			/* Attachments */
			$locationKey = (string) $item::$application . '_' . mb_ucfirst( $item::$module );
			\IPS\Db::i()->update( 'core_attachments_map', array( 'id1' => $this->$idColumn ), array( 'location_key=? and id1=?', $locationKey, $item->$idColumn ) );

			/* Update notifications */
			\IPS\Db::i()->update( 'core_notifications', array( 'item_id' => $this->$idColumn ), array( 'item_class=? and item_id=?', (string) \get_class( $item ), $item->$idColumn ) );

			/* Follows */
			if ( $this instanceof \IPS\Content\Followable )
			{
				\IPS\Db::i()->update( 'core_follow', "`follow_id` = MD5( CONCAT( `follow_app`, ';', `follow_area`, ';', {$this->$idColumn}, ';', `follow_member_id` ) ), `follow_rel_id` = {$this->$idColumn}", array( "follow_id=MD5( CONCAT( `follow_app`, ';', `follow_area`, ';', {$item->$idColumn}, ';', `follow_member_id` ) )" ), array(), NULL, \IPS\Db::IGNORE );
				\IPS\Db::i()->delete( 'core_follow_count_cache', array( 'class=? AND id=?', \get_called_class(), (int) $this->$idColumn ) );
			}
			
			/* Update moderation history */
            \IPS\Db::i()->update( 'core_moderator_logs', array( 'item_id' => $this->$idColumn ), array( 'item_id=? AND class=?', $item->$idColumn, (string) \get_class( $this ) ) );
			\IPS\Session::i()->modLog( 'modlog__action_merge', array( $item->mapped('title') => FALSE, $this->url()->__toString() => FALSE, $this->mapped('title') => FALSE ), $this );
			
			/* Add to the redirect table */
			$item->setRedirectTo( $this );
			
			/* If we are adding redirects to the merged items, then we need to change these to link items. */
			if ( $keepLinks AND isset( $item::$databaseColumnMap['moved_to'] ) )
			{
				$movedToColumn			= static::$databaseColumnMap['moved_to'];
				$item->$movedToColumn	= $this->$idColumn . '&' . $this->container()->_id;
				
				if ( isset( static::$databaseColumnMap['status'] ) )
				{
					$statusColumn			= static::$databaseColumnMap['status'];
					$item->$statusColumn	= 'merged';
				}
				
				if ( isset( static::$databaseColumnMap['moved_on'] ) )
				{
					$movedOnColumn			= static::$databaseColumnMap['moved_on'];
					$item->$movedOnColumn	= time();
				}

				/* Move links cannot be hidden or pending approval */
				if ( \in_array( 'IPS\Content\Hideable', class_implements( $item ) ) and ( isset( $item::$databaseColumnMap['hidden'] ) OR isset( $item::$databaseColumnMap['approved'] ) ) )
				{
					/* Now do the actual stuff */
					if ( isset( $item::$databaseColumnMap['hidden'] ) )
					{
						$column = $item::$databaseColumnMap['hidden'];

						$item->$column = 0;
					}
					elseif ( isset( $item::$databaseColumnMap['approved'] ) )
					{
						$column = $item::$databaseColumnMap['approved'];

						$item->$column = 1;
					}
				}

				/* Also remove unapproved and hidden comment counts if this is a move/merge link */
				if ( isset( $item::$databaseColumnMap['unapproved_comments'] ) )
				{
					$column = $item::$databaseColumnMap['unapproved_comments'];

					$item->$column = 0;
				}
				if ( isset( $item::$databaseColumnMap['hidden_comments'] ) )
				{
					$column = $item::$databaseColumnMap['hidden_comments'];

					$item->$column = 0;
				}

				if ( isset( $item::$databaseColumnMap['unapproved_reviews'] ) )
				{
					$column = $item::$databaseColumnMap['unapproved_reviews'];

					$item->$column = 0;
				}
				if ( isset( $item::$databaseColumnMap['hidden_reviews'] ) )
				{
					$column = $item::$databaseColumnMap['hidden_reviews'];

					$item->$column = 0;
				}
				
				$item->save();
			}
			else
			{
				/* Otherwise just delete them */
				$item->delete();
			}

			/* We need to reset container counts after */
			try
			{
				$item->container()->resetCommentCounts();
				$item->container()->save();
			}
			catch( \BadMethodCallException $e ) {}
		}
		
		if ( $views > 0 )
		{
			$viewColumn = $item::$databaseColumnMap['views'];
			$this->$viewColumn = $this->mapped('views') + $views;
		}
		
		$this->rebuildFirstAndLastCommentData();

		if( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->rebuildAfterMerge( $this );
		}
	}

	/**
	 * @brief	Force comments() calls to write database server if read/write separation is used
	 */
	protected static $useWriteServer	= FALSE;
	
	/**
	 * Rebuild meta data after splitting/merging
	 *
	 * @return	void
	 */
	public function rebuildFirstAndLastCommentData()
	{
		$existingFlag = static::$useWriteServer;
		static::$useWriteServer = TRUE;

		if ( isset( static::$commentClass ) )
		{
			$firstComment = $this->comments( 1, 0, 'date', 'asc', NULL, static::$firstCommentRequired ?: FALSE, NULL, NULL, TRUE );
			$idColumn = static::$databaseColumnId;

			$commentClass = static::$commentClass;
			$commentIdColumn = $commentClass::$databaseColumnId;

			/* Reset the content 'author' if the first comment is required (i.e. in posts), otherwise the first comment author
			should not be set as the file submitter in downloads (eg) */
			if ( static::$firstCommentRequired )
			{
				if ( static::$changeItemAuthorChangingFirstComment )
				{
					if ( isset( static::$databaseColumnMap['author'] ) )
					{
						$authorField = static::$databaseColumnMap['author'];
						$this->$authorField = $firstComment->author()->member_id ?: 0;
					}
					if ( isset( static::$databaseColumnMap['author_name'] ) )
					{
						$authorNameField = static::$databaseColumnMap['author_name'];
						$this->$authorNameField = $firstComment->mapped('author_name');
					}
				}
				if ( isset( static::$databaseColumnMap['date'] ) )
				{
					if( \is_array( static::$databaseColumnMap['date'] ) )
					{
						$dateField = static::$databaseColumnMap['date'][0];
					}
					else
					{
						$dateField = static::$databaseColumnMap['date'];
					}

					$this->$dateField = $firstComment->mapped('date');
				}
			}
			if ( isset( static::$databaseColumnMap['first_comment_id'] ) )
			{
				$firstCommentField = static::$databaseColumnMap['first_comment_id'];
				$this->$firstCommentField = $firstComment->$commentIdColumn;
			}

			/* Set first comments */
			if ( isset( $commentClass::$databaseColumnMap['first'] ) )
			{
				/* This can fail if we are, for example, splitting a post into a new topic, where a previous comment does not exist */
				$hasPrevious = TRUE;
				try
				{
					$previousFirstComment = $commentClass::constructFromData( \IPS\Db::i()->select( '*', $commentClass::$databaseTable, array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=? AND ' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['first'] . '=?', $this->$idColumn, TRUE ), NULL, 1 )->first() );
				}
				catch( \UnderflowException $e )
				{
					$hasPrevious = FALSE;
				}

				if ( $hasPrevious )
				{
					if ( $previousFirstComment->$commentIdColumn !== $firstComment->$commentIdColumn )
					{
						$firstColumn = $commentClass::$databaseColumnMap['first'];

						$previousFirstComment->$firstColumn = FALSE;
						$previousFirstComment->save();

						$firstComment->$firstColumn = TRUE;
						$firstComment->save();
					}
				}
				else
				{
					$firstColumn = $commentClass::$databaseColumnMap['first'];

					$firstComment->$firstColumn = TRUE;
					$firstComment->save();
				}
			}

			/* If this is a new item from a split and the first comment is hidden, we need to adjust the item hidden/approved attribute. */
			if ( $this instanceof \IPS\Content\Hideable and static::$firstCommentRequired and isset( $firstComment::$databaseColumnMap['hidden'] ) )
			{
				$commentColumn = $firstComment::$databaseColumnMap['hidden'];
				if ( $firstComment->$commentColumn == -1 )
				{
					/* The first comment is hidden so ensure topic is actually hidden correctly and all posts have a queued status of 2 to denote parent is hidden */
					\IPS\Db::i()->update( $commentClass::$databaseTable, array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] => 2 ), array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );
					$this->hide( NULL );
				}
			}

			/* Update mappings */
			if ( isset( static::$databaseColumnMap['container'] ) and \IPS\IPS::classUsesTrait( $this->container(), 'IPS\Node\Statistics' ) )
			{
				$this->container()->rebuildPostedIn( array( $this->$idColumn ) );
			}
		}
		
		/* Update last comment stuff */
		$this->resyncLastComment();

		/* Update last review stuff */
		$this->resyncLastReview();

		/* Update number of comments */
		$this->resyncCommentCounts();

		/* Update number of reviews */
		$this->resyncReviewCounts();

		/* Save*/
		$this->save();

		/* run only if we have a container */
		if ( isset( static::$databaseColumnMap['container'] ) )
		{
			/* Update container */
			$this->container()->resetCommentCounts();
			$this->container()->setLastComment();
			$this->container()->setLastReview();
			$this->container()->save();
		}
		
		/* Clear cached statistics */
		if ( \IPS\IPS::classUsesTrait( $this, 'IPS\Content\Statistics' ) )
		{
			$this->clearCachedStatistics();
		}

		/* Add to search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}

		static::$useWriteServer = $existingFlag;
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
		$idColumn = static::$databaseColumnId;

		\IPS\Db::i()->delete( 'core_notifications', array( 'item_class=? AND item_id=?', (string) \get_class( $this ), (int) $this->$idColumn ) );

		if ( $this instanceof \IPS\Content\Searchable )
		{
			if ( $this instanceof \IPS\Content\Item AND ( isset( static::$commentClass ) OR isset( static::$reviewClass ) ) )
			{
				if ( isset( static::$commentClass ) )
				{
					$commentClass = static::$commentClass;
					if ( \in_array( 'IPS\Content\Hideable', class_implements( $commentClass ) ) AND isset( $commentClass::$databaseColumnMap['hidden'] ) )
					{
						\IPS\Db::i()->update( $commentClass::$databaseTable, array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] => 2 ), array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=? AND ' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '=?', $this->$idColumn, 0 ) );
					}
				}
				
				if ( isset( static::$reviewClass ) )
				{
					$reviewClass = static::$reviewClass;
					if ( \in_array( 'IPS\Content\Hideable', class_implements( $reviewClass ) ) AND isset( $reviewClass::$databaseColumnMap['hidden'] ) )
					{
						\IPS\Db::i()->update( $reviewClass::$databaseTable, array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['hidden'] => 2 ), array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=? AND ' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['hidden'] . '=?', $this->$idColumn, 0 ) );
					}
				}
				
				$firstComment = NULL;
				if ( static::$firstCommentRequired AND isset( static::$commentClass ) AND $firstComment = $this->firstComment() )
				{
					$className = static::$commentClass;
					$column = $className::$databaseColumnMap['hidden'];
					$firstComment->$column = 2;
				}

				\IPS\Content\Search\Index::i()->index( ( static::$firstCommentRequired AND $firstComment ) ? $firstComment : $this );
				\IPS\Content\Search\Index::i()->indexSingleItem( $this );
			}
			else
			{
				/* Either this is a comment / review, or the item doesn't support comments or reviews, so we can just reindex it now. */
				\IPS\Content\Search\Index::i()->index( $this );
			}
		}
		
		parent::hide( $member, $reason );
	}

	/**
	 * Item is moderator hidden by a moderator
	 *
	 * @return	boolean
	 * @throws	\RuntimeException
	 */
	public function approvedButHidden()
	{
		if ( $this instanceof \IPS\Content\Hideable )
		{
			if ( isset( static::$databaseColumnMap['hidden'] ) )
			{
				$column = static::$databaseColumnMap['hidden'];
				return ( $this->$column == 2 ) ? TRUE : FALSE;
			}
			elseif ( isset( static::$databaseColumnMap['approved'] ) )
			{
				$column = static::$databaseColumnMap['approved'];
				return $this->$column == -1 ? TRUE : FALSE;
			}
			else
			{
				throw new \RuntimeException;
			}
		}

		return FALSE;
	}

	/**
	 * Unhide
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function unhide( $member )
	{
		/* Update our comments first - this is so that when onUnhide is called in the parent, then these posts will be accounted for when comment counts are reset */
		$idColumn = static::$databaseColumnId;
		foreach ( array( 'commentClass', 'reviewClass' ) as $class )
		{
			if ( isset( static::$$class ) )
			{
				$className = static::$$class;
				if ( \in_array( 'IPS\Content\Hideable', class_implements( $className ) ) AND isset( $className::$databaseColumnMap['hidden'] ) )
				{
					\IPS\Db::i()->update( $className::$databaseTable, array( $className::$databasePrefix . $className::$databaseColumnMap['hidden'] => 0 ), array( $className::$databasePrefix . $className::$databaseColumnMap['item'] . '=? AND ' . $className::$databasePrefix . $className::$databaseColumnMap['hidden'] . '=?', $this->$idColumn, 2 ) );
				}
			}
		}
		
		/* Do the item */
		parent::unhide( $member );

		/* And then update the search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			if ( $this instanceof \IPS\Content\Item AND ( isset( static::$commentClass ) OR isset( static::$reviewClass ) ) )
			{
				if( isset( static::$databaseColumnMap['state'] ) )
				{
					$stateColumn = static::$databaseColumnMap['state'];
					if ( $this->$stateColumn == 'link' )
					{
						return;
					}
				}

				$firstComment = NULL;
				if ( static::$firstCommentRequired AND isset( static::$commentClass ) AND $firstComment = $this->firstComment() )
				{
					$className = static::$commentClass;

					$column = $className::$databaseColumnMap['hidden'];
					$firstComment->$column = 0;
				}

				\IPS\Content\Search\Index::i()->index( ( static::$firstCommentRequired AND $firstComment ) ? $firstComment : $this );
				\IPS\Content\Search\Index::i()->indexSingleItem( $this );
			}
			else
			{
				/* Either this is a comment / review, or the item doesn't support comments or reviews, so we can just reindex it now. */
				\IPS\Content\Search\Index::i()->index( $this );
			}
		}
		
		/* Update container if needed */
		try
		{
			if ( $this->container()->_comments !== NULL )
			{
				$this->container()->setLastComment();
				$this->container()->save();
			}

			if ( $this->container()->_reviews !== NULL )
			{
				$this->container()->setLastReview();
				$this->container()->save();
			}
		} catch ( \BadMethodCallException $e ) {}
	}
		
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		/* Remove from search index - we must do this before deleting comments so we know what to remove */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->removeFromSearchIndex( $this );
		}

		$idColumn = static::$databaseColumnId;

		/* Don't do anything for shadow items */
		if ( isset( static::$databaseColumnMap['moved_to'] ) )
		{
			$movedToColumn = static::$databaseColumnMap['moved_to'];
			if ( $this->$movedToColumn )
			{
				/* Go ahead and delete this item record and return now */
				parent::delete();

				return;
			}
		}

		\IPS\Db::i()->delete( 'core_item_member_map', array( 'map_class=? and map_item_id=?',  (string) \get_class( $this ), (int) $this->$idColumn ) );

		/* Remove any meta data */
		if ( ( $this instanceof \IPS\Content\MetaData ) AND isset( static::$databaseColumnMap['meta_data'] ) )
		{
			$this->deleteAllMeta();
		}

		/* Unclaim attachments */
		$this->unclaimAttachments();

		/* Delete it from the database */
		parent::delete();

		/* Update count */
		try
		{
			if ( $this->container()->_items !== NULL )
			{
				if ( $this->isFutureDate() and $this->container()->_futureItems !== NULL )
				{
					$this->container()->_futureItems = ( $this->container()->_futureItems - 1 );
				}
				elseif ( !$this->hidden() )
				{
					$this->container()->_items = ( $this->container()->_items - 1 );
				}
				elseif ( $this->hidden() === 1 )
				{
					$this->container()->_unapprovedItems = ( $this->container()->_unapprovedItems - 1 );
				}
			}
		} catch ( \BadMethodCallException $e ) {}

		/* Delete comments */
		if ( isset( static::$commentClass ) )
		{
			$commentClass = static::$commentClass;
			$where = array( array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );

			if ( method_exists( $commentClass, 'deleteWhereSql' ) )
			{
				$where = $commentClass::deleteWhereSql( $this->$idColumn );
			}

			/* Remove any deletion logs for comments */
			$commentIds = array();
			$commentIdColumn = $commentClass::$databasePrefix . $commentClass::$databaseColumnId;
			foreach( \IPS\Db::i()->select( $commentIdColumn, $commentClass::$databaseTable, array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) ) AS $commentId )
			{
				$commentIds[] = $commentId;
			}

			$this->deleteCommentOrReviewData( $commentIds, $commentClass );

			if ( \is_array( $where ) and \count( $where ) )
			{
				\IPS\Db::i()->delete( $commentClass::$databaseTable, $where );
			}

			if( !$this->skipContainerRebuild )
			{
				try
				{
					if ( $this->container()->_comments !== NULL )
					{
						/* We decrement the comment count onHide() */
						if ( ! $this->hidden() )
						{
							$this->container()->_comments = ( $this->container()->_comments - $this->mapped('num_comments') );
						}

						$this->container()->setLastComment();
					}
					if ( $this->container()->_unapprovedComments !== NULL )
					{
						$this->container()->_unapprovedComments = ( $this->container()->_unapprovedComments - $this->mapped('unapproved_comments') );
					}
					$this->container()->save();
				} catch ( \BadMethodCallException $e ) {}
			}
		}

		/* Delete reviews */
		if ( isset( static::$reviewClass ) )
		{
			$reviewClass = static::$reviewClass;
			$where = array( array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );

			if ( method_exists( $reviewClass, 'deleteWhereSql' ) )
			{
				$where = $reviewClass::deleteWhereSql( $this->$idColumn );
			}

			/* Remove any deletion logs for reviews */
			$reviewIds = array();
			$reviewIdColumn = $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'];
			foreach( \IPS\Db::i()->select( $reviewIdColumn, $reviewClass::$databaseTable, array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) ) AS $reviewId )
			{
				$reviewIds[] = $reviewId;
			}

			$this->deleteCommentOrReviewData( $reviewIds, $reviewClass );

			\IPS\Db::i()->delete( $reviewClass::$databaseTable, $where );

			if( !$this->skipContainerRebuild )
			{
				try
				{
					if ( $this->container()->_reviews !== NULL )
					{
						/* We decrement the review count onHide() */
						if ( ! $this->hidden() )
						{
							$this->container()->_reviews = ( $this->container()->_reviews - $this->mapped('num_reviews') );
						}

						$this->container()->setLastReview();
					}
					if ( $this->container()->_unapprovedReviews !== NULL )
					{
						$this->container()->_unapprovedReviews = ( $this->container()->_unapprovedReviews - $this->mapped('unapproved_reviews') );
					}
					$this->container()->save();
				} catch ( \BadMethodCallException $e ) {}
			}
		}

		/* Delete tags */
		if ( $this instanceof \IPS\Content\Tags )
		{
			$aaiLookup = $this->tagAAIKey();
			\IPS\Db::i()->delete( 'core_tags', array( 'tag_aai_lookup=?', $aaiLookup ) );
			\IPS\Db::i()->delete( 'core_tags_cache', array( 'tag_cache_key=?', $aaiLookup ) );
			\IPS\Db::i()->delete( 'core_tags_perms', array( 'tag_perm_aai_lookup=?', $aaiLookup ) );
		}

		/* Delete follows */
		if ( $this instanceof \IPS\Content\Followable )
		{
			$followArea = mb_strtolower( mb_substr( \get_called_class(), mb_strrpos( \get_called_class(), '\\' ) + 1 ) );
			\IPS\Db::i()->delete( 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=?', static::$application, $followArea, (int) $this->$idColumn ) );
			\IPS\Db::i()->delete( 'core_follow_count_cache', array( 'class=? AND id=?', \get_called_class(), (int) $this->$idColumn ) );
		}

		/* Remove Notifications */
		$memberIds	= array();

		foreach( \IPS\Db::i()->select( '`member`', 'core_notifications', array( 'item_class=? AND item_id=?', (string) \get_class( $this ), (int) $this->$idColumn ) ) as $member )
		{
			$memberIds[ $member ]	= $member;
		}

		\IPS\Db::i()->delete( 'core_notifications', array( 'item_class=? AND item_id=?', (string) \get_class( $this ), (int) $this->$idColumn ) );

		/* Delete from Our Picks */
		\IPS\Db::i()->delete( 'core_social_promote', array( 'promote_class=? AND promote_class_id=?', (string) \get_class( $this ), (int) $this->$idColumn ) );

		/* Delete from redirect links */
		\IPS\Db::i()->delete( 'core_item_redirect', array( 'redirect_class=? AND redirect_new_item_id=?', (string) \get_class( $this ), (int) $this->$idColumn ) );

		/* Delete Polls */
		if ( $this instanceof \IPS\Content\Polls and $this->getPoll() )
		{
			$this->getPoll()->delete();
		}

		/* Delete Ratings */
		if ( $this instanceof \IPS\Content\Ratings )
		{
			\IPS\Db::i()->delete( 'core_ratings', array( 'class=? AND item_id=?', \get_called_class(), $this->$idColumn ) );
		}

		foreach( $memberIds as $member )
		{
			\IPS\Member::load( $member )->recountNotifications();
		}

		/** Item::url() can throw a LogicException exception in specific cases like when a Pages Record has no valid page */
		if( !static::$skipIndexNow )
		{
			try
			{
				\IPS\core\IndexNow::addUrlToQueue( $this->url() );
			}
			catch( \LogicException $e ) {}
		}
	}

	/**
	 * Deletes any additional comment Related data
	 *
	 * @param array 	$ids			comment or review ids which are going to be deleted
	 * @param string	$class			comment or review class name
	 */
	protected function deleteCommentOrReviewData( array $ids, string $class )
	{
		\IPS\Db::i()->delete( 'core_deletion_log', array('dellog_content_class=? AND ' . \IPS\Db::i()->in( 'dellog_content_id', $ids ), $class) );
		\IPS\Db::i()->delete( 'core_social_promote', array('promote_class=? AND ' . \IPS\Db::i()->in( 'promote_class_id', $ids ), $class) );
		\IPS\Db::i()->delete( 'core_reputation_index', array('rep_class=? AND ' . \IPS\Db::i()->in( 'type_id', $ids ), $class) );
		\IPS\Db::i()->delete( 'core_solved_index', array('comment_class=? AND ' . \IPS\Db::i()->in( 'comment_id', $ids ), $class) );
	}
	
	/**
	 * Change IP Address
	 * @param	string		$ip		The new IP address
	 *
	 * @return void
	 */
	public function changeIpAddress( $ip )
	{
		parent::changeIpAddress( $ip );
				
		/* How about a required comment? */
		if ( isset( static::$commentClass ) and static::$firstCommentRequired )
		{
			$commentClass = static::$commentClass;

			if ( isset( static::$databaseColumnMap['first_comment_id'] ) AND $comment = $this->firstComment() )
			{
				$comment->changeIpAddress( $ip );
			}
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

		/* Update the row */
		parent::changeAuthor( $newAuthor, $log );
		
		/* Adjust post counts, but only if this is a visible post or the previous user was not a guest */
		if ( static::incrementPostCount( $this->containerWrapper() ) AND ( $oldAuthor->member_id OR $this->hidden() === 0 ) and ! $this->isAnonymous() )
		{
			if( $oldAuthor->member_id )
			{
				$oldAuthor->member_posts--;
				$oldAuthor->save();
			}
			
			if( $newAuthor->member_id )
			{
				$newAuthor->member_posts++;
				$newAuthor->save();
			}
		}

		$setComment	= FALSE;
		if ( isset( static::$commentClass ) and static::$firstCommentRequired )
		{
			$commentClass = static::$commentClass;

			if ( isset( static::$databaseColumnMap['first_comment_id'] ) AND $comment = $this->firstComment() )
			{
				$comment->changeAuthor( $newAuthor, $log );

				$setComment	= TRUE;
			}
		}
		
		/* Update container, but don't bother if we just updated the comment because it will have triggered the container to update */
		if ( !$setComment AND $container = $this->containerWrapper() )
		{
			$container->setLastComment();
			$container->setLastReview();
			$container->save();
		}
		
		/* Update search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}
	}
	
	/**
	 * Unclaim attachments
	 *
	 * @return	void
	 */
	protected function unclaimAttachments()
	{
		$idColumn = static::$databaseColumnId;
		\IPS\File::unclaimAttachments( static::$application . '_' . mb_ucfirst( static::$module ), $this->$idColumn );
	}
	
	/**
	 * @brief Cached containers we can access
	 */
	protected static $permissionSelect	= array();

	/**
	 * @brief Query flag to select IDs first. This is generally more efficient as it means you do not have to use loads of joins which slows down the query.
	 */
	const SELECT_IDS_FIRST = 256;
	
	/**
	 * Get items with permission check
	 *
	 * @param	array		$where				Where clause
	 * @param	string		$order				MySQL ORDER BY clause (NULL to order by date)
	 * @param	int|array	$limit				Limit clause
	 * @param	string|NULL	$permissionKey		A key which has a value in the permission map (either of the container or of this class) matching a column ID in core_permission_index or NULL to ignore permissions
	 * @param	mixed		$includeHiddenItems	Include hidden items? NULL to detect if currently logged in member has permission, -1 to return public content only, TRUE to return unapproved content and FALSE to only return unapproved content the viewing member submitted
	 * @param	int			$queryFlags			Select bitwise flags
	 * @param	\IPS\Member	$member				The member (NULL to use currently logged in member)
	 * @param	bool		$joinContainer		If true, will join container data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinComments		If true, will join comment data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinReviews		If true, will join review data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$countOnly			If true will return the count
	 * @param	array|null	$joins				Additional arbitrary joins for the query
	 * @param	mixed		$skipPermission		If you are getting records from a specific container, pass the container to reduce the number of permission checks necessary or pass TRUE to skip container-based permission. You must still specify this in the $where clause
	 * @param	bool		$joinTags			If true, will join the tags table
	 * @param	bool		$joinAuthor			If true, will join the members table for the author
	 * @param	bool		$joinLastCommenter	If true, will join the members table for the last commenter
	 * @param	bool		$showMovedLinks		If true, moved item links are included in the results
	 * @param	array|null	$location			Array of item lat and long
	 * @return	\IPS\Patterns\ActiveRecordIterator|int
	 */
	public static function getItemsWithPermission( $where=array(), $order=NULL, $limit=10, $permissionKey='read', $includeHiddenItems=\IPS\Content\Hideable::FILTER_AUTOMATIC, $queryFlags=0, \IPS\Member $member=NULL, $joinContainer=FALSE, $joinComments=FALSE, $joinReviews=FALSE, $countOnly=FALSE, $joins=NULL, $skipPermission=FALSE, $joinTags=TRUE, $joinAuthor=TRUE, $joinLastCommenter=TRUE, $showMovedLinks=FALSE, $location=NULL )
	{
		/* Are we trying to improve count performance? */
		$countShortcut = FALSE;

		$having = NULL;
		if ( isset( $location['lat'] ) and isset( $location['lon'] ) )
		{
			/* Make sure co-ordinates are in a valid format regardless of locale */
			$location['lat'] = number_format( $location['lat'], 6, '.', '' );
			$location['lon'] = number_format( $location['lon'], 6, '.', '' );

			$where[] = array( static::$databasePrefix . 'latitude' . ' IS NOT NULL AND ' . static::$databasePrefix . 'longitude' . ' IS NOT NULL' );
			$having = array( 'distance < 500' );
			$order = 'distance ASC';
		}

		/* Do we really need tags? */
		if ( $joinTags and ! \IPS\Settings::i()->tags_enabled )
		{
			$joinTags = FALSE;	
		}
		
		/* Work out the order */
		if ( $order === NULL )
		{
			$dateColumn = static::$databaseColumnMap['date'];
			if ( \is_array( $dateColumn ) )
			{
				$dateColumn = array_pop( $dateColumn );
			}
			$order = static::$databaseTable . '.' . static::$databasePrefix . $dateColumn . ' DESC';
		}
		
		$containerWhere = array();
		
		/* Queries are always more efficient when the WHERE clause is added to the ON */
		if ( \is_array( $where ) )
		{
			foreach( $where as $key => $value )
			{
				if ( $key ==='item' )
				{
					$where = array_merge( $where, $value );
					
					unset( $where[ $key ] );
				}
				
				if ( $key === 'container' )
				{
					$containerWhere = array_merge( $containerWhere, $value );
					unset( $where[ $key ] );
				}
			}
		}
		
		/* Exclude hidden items */
		if( $includeHiddenItems === \IPS\Content\Hideable::FILTER_AUTOMATIC )
		{
			$containersTheUserCanViewHiddenItemsIn = static::canViewHiddenItemsContainers( $member );
			if ( $containersTheUserCanViewHiddenItemsIn === TRUE )
			{
				$includeHiddenItems = \IPS\Content\Hideable::FILTER_SHOW_HIDDEN;
			}
			elseif ( \is_array( $containersTheUserCanViewHiddenItemsIn ) )
			{
				$includeHiddenItems = $containersTheUserCanViewHiddenItemsIn;
			}
			else
			{
				$includeHiddenItems = \IPS\Content\Hideable::FILTER_OWN_HIDDEN;
			}
		}

		if ( \in_array( 'IPS\Content\Hideable', class_implements( \get_called_class() ) ) and $includeHiddenItems === \IPS\Content\Hideable::FILTER_ONLY_HIDDEN )
		{
			/* If we can't view hidden stuff, just return now */
			if( !static::canViewHiddenItemsContainers( $member ) )
			{
				return $countOnly ? 0 : new \IPS\Patterns\ActiveRecordIterator( new \ArrayIterator( array() ), \get_called_class() );
			}

			if ( isset( static::$databaseColumnMap['approved'] ) )
			{
				$col = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['approved'];
				$where[] = array( "{$col}=0" );
			}
			elseif ( isset( static::$databaseColumnMap['hidden'] ) )
			{
				$col = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['hidden'];
				$where[] = array( "{$col}=1" );
			}
		}
		elseif ( \in_array( 'IPS\Content\Hideable', class_implements( \get_called_class() ) ) and $includeHiddenItems !== \IPS\Content\Hideable::FILTER_SHOW_HIDDEN )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			$authorCol = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['author'];
			$extra = \is_array( $includeHiddenItems ) ? ( ' OR ' . \IPS\Db::i()->in( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['container'], $includeHiddenItems ) ) : '';
			
			if ( isset( static::$databaseColumnMap['approved'] ) )
			{
				$col = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['approved'];
				if ( $member->member_id and $includeHiddenItems !== \IPS\Content\Hideable::FILTER_PUBLIC_ONLY and isset( static::$databaseColumnMap['author'] ) )
				{
					/* Only fetching a count, for a single container with a item cache count, no future publishing, and there is only one where clause (the container limitation) */
					if( $countOnly === TRUE AND $skipPermission instanceof \IPS\Node\Model AND $skipPermission->_items !== NULL AND !\in_array( 'IPS\Content\FuturePublishing', class_implements( \get_called_class() ) ) AND \count( $where ) === 1 )
					{
						$countShortcut = TRUE;
						$where[] = array( "({$col}=0 AND ( {$authorCol}={$member->member_id}{$extra} ) )" );
					}
					else
					{
						$where[] = array( "( {$col}=1 OR ( {$col}=0 AND ( {$authorCol}={$member->member_id}{$extra} ) ) )" );
					}
				}
				else
				{
					$where[] = array( "{$col}=1" );
				}
			}
			elseif ( isset( static::$databaseColumnMap['hidden'] ) )
			{
				$col = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['hidden'];
				if ( $member->member_id and $includeHiddenItems !== \IPS\Content\Hideable::FILTER_PUBLIC_ONLY )
				{
					/* Only fetching a count, for a single container with a item cache count, no future publishing, and there is only one where clause (the container limitation) */
					if( $countOnly === TRUE AND $skipPermission instanceof \IPS\Node\Model AND $skipPermission->_items !== NULL AND !\in_array( 'IPS\Content\FuturePublishing', class_implements( \get_called_class() ) ) AND \count( $where ) === 1 )
					{
						$countShortcut = TRUE;
						$where[] = array( "({$col}=1 AND ( {$authorCol}={$member->member_id}{$extra} ) )" );
					}
					else
					{
						$where[] = array( "( {$col}=0 OR ( {$col}=1 AND ( {$authorCol}={$member->member_id}{$extra} ) ) )" );
					}
				}
				else
				{
					$where[] = array( "{$col}=0" );
				}
			}
		}
        else
        {
			if ( \is_array( $includeHiddenItems ) )
			{
				$where[] = array( \IPS\Db::i()->in( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['container'], $includeHiddenItems ) );
			}
	        
            /* Legacy items pending deletion in 3.x at time of upgrade may still exist */
            $col	= null;

            if ( isset( static::$databaseColumnMap['approved'] ) )
            {
                $col = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['approved'];
            }
            else if( isset( static::$databaseColumnMap['hidden'] ) )
            {
                $col = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['hidden'];
            }

            if( $col )
            {
            	$where[] = array( "{$col} < 2" );
            }
        }
        
        /* No matter if we can or cannot view hidden items, we do not want these to show: -2 is queued for deletion and -3 is posted before register */
        if ( isset( static::$databaseColumnMap['hidden'] ) )
        {
	        $col = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['hidden'];
	        $where[] = array( "{$col}!=-2 AND {$col} !=-3" );
        }
        else if ( isset( static::$databaseColumnMap['approved'] ) )
        {
	        $col = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['approved'];
	        $where[] = array( "{$col}!=-2 AND {$col}!=-3" );
        }
        
		/* Future items? */
		if ( \in_array( 'IPS\Content\FuturePublishing', class_implements( \get_called_class() ) ) )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			$authorCol = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['author'];

			if ( ! static::canViewFutureItems( $member ) )
			{
				$col = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['is_future_entry'];
				if ( $member->member_id )
				{
					$where[] = array( "( {$col}=0 OR ( {$col}=1 AND {$authorCol}={$member->member_id} ) )" );
				}
				else
				{
					$where[] = array( "{$col}=0" );
				}
			}
		}
		
		/* Don't show links to moved items? */
		if ( ! $showMovedLinks and isset( static::$databaseColumnMap['moved_to'] ) and ( ( $skipPermission or $permissionKey === NULL ) or ( !$skipPermission and \in_array( $permissionKey, array( 'view', 'read' ) ) ) ) )
		{
			$where[] = array( "( NULLIF(" . static::$databaseTable . "." . static::$databaseColumnMap['moved_to'] . ", '') IS NULL )" );
		}

		/* Set permissions */
		if ( \in_array( 'IPS\Content\Permissions', class_implements( \get_called_class() ) ) AND $permissionKey !== NULL and !$skipPermission )
		{
			$containerClass = static::$containerNodeClass;

			$member = $member ?: \IPS\Member::loggedIn();
			$categories	= array();
			$lookupKey	= md5( $containerClass::$permApp . $containerClass::$permType . $permissionKey . json_encode( $member->groups ) );

			if( !isset( static::$permissionSelect[ $lookupKey ] ) )
			{
				static::$permissionSelect[ $lookupKey ] = array();
				$permQuery = \IPS\Db::i()->select( 'perm_type_id', 'core_permission_index', array( "core_permission_index.app='" . $containerClass::$permApp . "' AND core_permission_index.perm_type='" . $containerClass::$permType . "' AND (" . \IPS\Db::i()->findInSet( 'perm_' . $containerClass::$permissionMap[ $permissionKey ], $member->permissionArray() ) . ' OR ' . 'perm_' . $containerClass::$permissionMap[ $permissionKey ] . "='*' )" ) );

				/* If we cannot access clubs, skip them */
				if ( \IPS\IPS::classUsesTrait( $containerClass, 'IPS\Content\ClubContainer' ) AND !$member->canAccessModule( \IPS\Application\Module::get( 'core', 'clubs', 'front' ) ) )
				{
					$containerWhere[] = array( $containerClass::$databaseTable . '.' . $containerClass::$databasePrefix . $containerClass::clubIdColumn() . ' IS NULL' );
				}
				
				if ( \count( $containerWhere ) )
				{
					$permQuery->join( $containerClass::$databaseTable, array_merge( $containerWhere, array( 'core_permission_index.perm_type_id=' . $containerClass::$databaseTable . '.' . $containerClass::$databasePrefix . $containerClass::$databaseColumnId ) ), 'STRAIGHT_JOIN' );
				}

				foreach( $permQuery as $result )
				{
					static::$permissionSelect[ $lookupKey ][] = $result;
				}
			}

			$categories = static::$permissionSelect[ $lookupKey ];

			if( \count( $categories ) )
			{
				$where[]	= array( static::$databaseTable . "." . static::$databasePrefix . static::$databaseColumnMap['container'] . ' IN(' . implode( ',', $categories ) . ')' );
			}
			else
			{
				$where[]	= array( static::$databaseTable . "." . static::$databasePrefix . static::$databaseColumnMap['container'] . '=0' );
			}
		}
		
		$groupBy = ( $joinComments ? static::$databasePrefix . static::$databaseColumnId : NULL );
		
		/* Build the select clause */
		if( $countOnly )
		{
			$select = \IPS\Db::i()->select( 'COUNT(*) as cnt', static::$databaseTable, $where, NULL, NULL, $groupBy, NULL, $queryFlags );
			if ( $joinContainer AND isset( static::$containerNodeClass ) )
			{
				$containerClass = static::$containerNodeClass;
				$select->join( $containerClass::$databaseTable, array_merge( $containerWhere, array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['container'] . '=' . $containerClass::$databaseTable . '.' . $containerClass::$databasePrefix . $containerClass::$databaseColumnId ) ) );
			}
			if ( $joinComments )
			{
				$commentClass = static::$commentClass;
				$select->join( $commentClass::$databaseTable, array( $commentClass::$databaseTable . '.' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=' . static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnId ) );
			}
			if ( $joins !== NULL AND \count( $joins ) )
			{
				foreach( $joins as $join )
				{
					$select->join( $join['from'], ( isset( $join['where'] ) ? $join['where'] : null ), ( isset( $join['type'] ) ? $join['type'] : 'LEFT' ) );
				}
			}
			
			try
			{
				$count = $select->first();
			}
			catch ( \UnderflowException $e )
			{
				$count = 0;
			}

			/* Were we trying to take a shortcut for performance reasons? */
			if( $countShortcut === TRUE )
			{
				return $count + $skipPermission->_items;
			}

			return $count;
		}
		else
		{
			if ( ( $queryFlags & static::SELECT_IDS_FIRST or \IPS\CIC ) or $groupBy )
			{
				$pass = false;
				
				if ( \is_numeric( $limit ) and $limit <= 2000 )
				{
					$pass = true;
				}
				else if ( \is_array( $limit ) and $limit[1] <= 2000 )
				{
					$pass = true;
				}
				
				if ( $pass === true )
				{
					$subSelectClause = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnId;

					/* Are we doing a pseudo-rand ordering? */
					if( $order == '_rand' )
					{
						$subSelectClause	.= static::_getRandomizationSql( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnId, static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['title'] );
					}

					$select = \IPS\Db::i()->select( $subSelectClause, static::$databaseTable, array_merge( $where, $containerWhere ), $order, $limit, ( $joinComments ? static::$databasePrefix . static::$databaseColumnId : NULL ), NULL, $queryFlags );
					
					if ( ( $joinContainer OR $containerWhere ) AND isset( static::$containerNodeClass ) )
					{
						$containerClass = static::$containerNodeClass;
						$select->join( $containerClass::$databaseTable, array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['container'] . '=' . $containerClass::$databaseTable . '.' . $containerClass::$databasePrefix . $containerClass::$databaseColumnId ) );
					}
					
					if ( $joinComments )
					{
						$commentClass = static::$commentClass;
						$select->join( $commentClass::$databaseTable, array( $commentClass::$databaseTable . '.' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=' . static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnId ) );
					}
					
					if ( $joins !== NULL AND \count( $joins ) )
					{
						foreach( $joins as $join )
						{
							$select->join( $join['from'], ( isset( $join['where'] ) ? $join['where'] : null ), ( isset( $join['type'] ) ? $join['type'] : 'LEFT' ) );
						}
					}

					if( $order == '_rand' )
					{
						$ids = array();
						foreach ( iterator_to_array( $select ) as $item )
						{
							$ids[] = $item[static::$databasePrefix . static::$databaseColumnId];
						}
					}
					else
					{
						$ids = iterator_to_array( $select );
					}

					if ( \count( $ids ) )
					{
						/* Reset the where */
						$where = array( array( \IPS\Db::i()->in( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnId, $ids ) ) );

						/* Reset the offset */
						$limit = NULL;
						
						/* Drop the group by as it will fail due to ONLY_FULL_GROUP_BY and we already have the item ids we need */
						$groupBy = NULL;
						
						/* Set joinComments to false as we do not need it now we have the ids */
						$joinComments = FALSE;
					}
					else
					{
						/* If no ids were found, stop now - there are no results. If we don't return, the original regular query will run and return unexpected results */
						return new \IPS\Patterns\ActiveRecordIterator( new \ArrayIterator, \get_called_class() );
					}
				}
			}
			
			/* We always want to make this multidimensional */
			$queryFlags |= \IPS\Db::SELECT_MULTIDIMENSIONAL_JOINS;
			
			$selectClause = static::$databaseTable . '.*';

			if ( isset( $location['lat'] ) and isset( $location['lon'] ) and is_numeric( $location['lat'] ) and is_numeric( $location['lon'] )  )
			{
				$selectClause .= ', ( 3959 * acos( cos( radians(' . $location['lat'] . ') ) * cos( radians( ' . static::$databasePrefix . 'latitude' . ' ) ) * cos( radians( ' . static::$databasePrefix . 'longitude' . ' ) - radians( ' . $location['lon'] . ' ) ) + sin( radians( ' . $location['lat'] . ' ) ) * sin( radians( ' . static::$databasePrefix . 'latitude' . ') ) ) ) AS distance';
			}

            if( $joinAuthor and isset( static::$databaseColumnMap['author'] ) )
            {
                $selectClause .= ', author.*';
            }
            if( $joinLastCommenter and isset( static::$databaseColumnMap['last_comment_by'] ) )
            {
                $selectClause .= ', last_commenter.*';
            }

			/* Are we doing a pseudo-rand ordering? */
			if( $order == '_rand' )
			{
				$selectClause	.= static::_getRandomizationSql( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnId, static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['title'] );
			}

			if ( $joins !== NULL AND \count( $joins ) )
			{
				foreach( $joins as $join )
				{
					if( isset( $join['select']) AND $join['select'] )
					{
						$selectClause .= ', ' . $join['select'];
					}
				}
			}
			
			if ( $joinTags and \in_array( 'IPS\Content\Tags', class_implements( \get_called_class() ) ) )
			{
				$selectClause .= ', core_tags_cache.tag_cache_text';
			}

			$select = \IPS\Db::i()->select( $selectClause, static::$databaseTable, $where, $order, $limit, $groupBy, $having, $queryFlags );
		}

		/* Join stuff */
		if ( $joinContainer AND isset( static::$containerNodeClass ) )
		{
			$containerClass = static::$containerNodeClass;
			$select->join( $containerClass::$databaseTable, array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['container'] . '=' . $containerClass::$databaseTable . '.' . $containerClass::$databasePrefix . $containerClass::$databaseColumnId ) );
		}
		if ( $joinComments )
		{
			$commentClass = static::$commentClass;
			$select->join( $commentClass::$databaseTable, array( $commentClass::$databaseTable . '.' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=' . static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnId ) );
		}
		if ( $joinReviews )
		{
			$reviewClass = static::$reviewClass;
			$select->join( $reviewClass::$databaseTable, array( $reviewClass::$databaseTable . '.' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=' . static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnId ) );
		}

		/* Join the tags cache, if applicable */
		if ( $joinTags and \in_array( 'IPS\Content\Tags', class_implements( \get_called_class() ) ) )
		{
			$itemClass = \get_called_class();
			$idColumn = static::$databasePrefix . static::$databaseColumnId;
			$select->join( 'core_tags_cache', array( "tag_cache_key=MD5(CONCAT(?,{$itemClass::$databaseTable}.{$idColumn}))", static::$application . ';' . static::$module . ';' ) );
		}

        /* Join the members table */
        if ( $joinAuthor and isset( static::$databaseColumnMap['author'] ) )
        {
            $authorColumn = static::$databaseColumnMap['author'];
            $select->join( array( 'core_members', 'author' ), array( 'author.member_id = ' . static::$databaseTable . '.' . static::$databasePrefix . $authorColumn ) );
        }
	    if ( $joinLastCommenter and isset( static::$databaseColumnMap['last_comment_by'] ) )
	    {
	        $lastCommeneterColumn = static::$databaseColumnMap['last_comment_by'];
            $select->join( array( 'core_members', 'last_commenter' ), array( 'last_commenter.member_id = ' . static::$databaseTable . '.' . static::$databasePrefix . $lastCommeneterColumn ) );
	    }

        if ( $joins !== NULL AND \count( $joins ) )
		{
 			foreach( $joins as $join )
			{
				$select->join( $join['from'], ( isset( $join['where'] ) ? $join['where'] : null ), ( isset( $join['type'] ) ? $join['type'] : 'LEFT' ) );
			}
		}

		/* Return */
		return new \IPS\Patterns\ActiveRecordIterator( $select, \get_called_class() );
	}

	/**
	 * Get randomization SQL query clause
	 *
	 * @param	string		$id			ID column to use
	 * @param	string		$title		Text column to use
	 * @return	string
	 */
	protected static function _getRandomizationSql( $id, $title )
	{
		return ", SUBSTR( MD5( CONCAT( {$id}, {$title} ) ), " . rand( 2, 25 ) . " ) as _rand";
	}
	
	/**
	 * Additional WHERE clauses for Follow view
	 *
	 * @param	bool		$joinContainer		If true, will join container data (set to TRUE if your $where clause depends on this data)
	 * @param	array		$joins				Other joins
	 * @return	array
	 */
	public static function followWhere( &$joinContainer, &$joins )
	{
		return array();
	}
	
	/**
	 * Get featured items
	 *
	 * @param	int						$limit		Number to get
	 * @param	string					$order		MySQL ORDER BY clause
	 * @param	\IPS\Node\Model|NULL	$container	Container to restrict to (or NULL for any)
	 * @return	\IPS\Patterns\AciveRecordIterator
	 * @throws	\BadMethodCallException
	 */
	public static function featured( $limit=10, $order='RAND()', $container = NULL )
	{
		if ( !\in_array( 'IPS\Content\Featurable', class_implements( \get_called_class() ) ) )
		{
			throw new \BadMethodCallException;
		}
		
		$where = array( array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['featured'] . '=?', 1 ) );
		if ( $container )
		{
			$where[] = array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['container'] . '=?', $container->_id );
		}

		if ( \in_array( 'IPS\Content\FuturePublishing', class_implements( \get_called_class() ) ) )
		{
			$where[] = array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['is_future_entry'] . '=?', 0 );
		}
		
		return static::getItemsWithPermission( $where, $order, $limit );
	}

	/**
	 * @brief	Allow the title to be editable via AJAX
	 */
	public $editableTitle	= TRUE;
	
	/**
	 * Get template for content tables
	 *
	 * @return	callable
	 */
	public static function contentTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'tables', 'core', 'front' ), 'rows' );
	}

	/**
	 * Get HTML for search result display snippet
	 *
	 * @return	callable
	 */
	public static function manageFollowRows()
	{
		return array( \IPS\Theme::i()->getTemplate( 'tables', 'core', 'front' ), 'manageFollowRow' );
	}
	
	/**
	 * Return the filters that are available for selecting table rows
	 *
	 * @return	array
	 */
	public static function getTableFilters()
	{
		$return = array();
		
		if ( \in_array( 'IPS\Content\ReadMarkers', class_implements( \get_called_class() ) ) )
		{
			$return[] = 'read';
			$return[] = 'unread';
		}
		
		$return = array_merge( $return, parent::getTableFilters() );
		
		if ( \in_array( 'IPS\Content\Lockable', class_implements( \get_called_class() ) ) )
		{
			$return[] = 'locked';
		}
		
		if ( \in_array( 'IPS\Content\Pinnable', class_implements( \get_called_class() ) ) )
		{
			$return[] = 'pinned';
		}
		
		if ( \in_array( 'IPS\Content\Featurable', class_implements( \get_called_class() ) ) )
		{
			$return[] = 'featured';
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
		$return	= explode( ' ', parent::tableStates() );

		$return[]	= ( $this->unread() === -1 or $this->unread() === 1 ) ? "unread" : "read";

		if( $this->hidden() === -1 )
		{
			$return[]	= "hidden";
		}
		else if( $this->hidden() === 1 )
		{
			$return[]	= "unapproved";
		}

		if( $this->mapped('pinned') )
		{
			$return[]	= "pinned";
		}

		if( $this->mapped('featured') )
		{
			$return[]	= "featured";
		}

		try
		{
			if( $this->locked() )
			{
				$return[]	= "locked";
			}
		}
		catch( \BadMethodCallException $e ){}

		try
		{
			if( $this->isFutureDate() )
			{
				$return[]	= "future";
			}
		}
		catch( \BadMethodCallException $e ){}
		
		if ( $this->_followData )
		{
			$return[] = 'follow_freq_' . $this->_followData['follow_notify_freq'];
			$return[] = 'follow_privacy_' . \intval( $this->_followData['follow_is_anon'] );
		}

		return implode( ' ', $return );
	}
	
	/**
	 * Columns needed to query for search result / stream view
	 *
	 * @return	array
	 */
	public static function basicDataColumns()
	{
		$return = array( static::$databasePrefix . static::$databaseColumnId, static::$databasePrefix . static::$databaseColumnMap['title'], static::$databasePrefix . static::$databaseColumnMap['author'] );
		
		if ( isset( static::$databaseColumnMap['num_comments'] ) )
		{
			$return[] = static::$databasePrefix . static::$databaseColumnMap['num_comments'];
		}
		
		if ( isset( static::$databaseColumnMap['num_reviews'] ) )
		{
			$return[] = static::$databasePrefix . static::$databaseColumnMap['num_reviews'];
		}

		return $return;
	}
				
	/* !Comments & Reviews */
	
	/**
	 * Are comments supported by this class?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for or NULL to not check permission
	 * @param	\IPS\Node\Model|NULL	$container	The container to check in, or NULL for any container
	 * @return	bool
	 */
	public static function supportsComments( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{		
		return isset( static::$commentClass );
	}
	
	/**
	 * Are reviews supported by this class?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for or NULL to not check permission
	 * @param	\IPS\Node\Model|NULL	$container	The container to check in, or NULL for any container
	 * @return	bool
	 */
	public static function supportsReviews( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return isset( static::$reviewClass );
	}

	/**
	 * @brief	[Content\Item]	Number of reviews to show per page
	 */
	public static $reviewsPerPage = 25;

	/**
	 * @brief	Review Page count
	 * @see		reviewPageCount()
	 */
	protected $reviewPageCount;

	/**
	 * @brief	Comment Page count
	 * @see		commentPageCount()
	 */
	protected $commentPageCount;

	/**
	 * Get number of comments to show per page
	 *
	 * @return int
	 */
	public static function getCommentsPerPage()
	{
		return 25;
	}

	/**
	 * Get comment page count
	 *
	 * @param	bool		$recache		TRUE to recache the value
	 * @return	int
	 */
	public function commentPageCount( $recache=FALSE )
	{		
		if ( $this->commentPageCount === NULL or $recache )
		{
			$this->commentPageCount = ceil( $this->commentCount() / $this->getCommentsPerPage() );

			if( $this->commentPageCount < 1 )
			{
				$this->commentPageCount	= 1;
			}
		}
		return $this->commentPageCount;
	}
	
	/**
	 * Get comment count
	 *
	 * @return	int
	 */
	public function commentCount()
	{
		if( !isset( static::$commentClass ) )
		{
			return 0;
		}

		$count = $this->mapped('num_comments');

		if( $this->canViewHiddenComments() )
		{
			if ( isset( static::$databaseColumnMap['hidden_comments'] ) )
			{
				$count += $this->mapped('hidden_comments');
			}
			if ( isset( static::$databaseColumnMap['unapproved_comments'] ) )
			{
				$count += $this->mapped('unapproved_comments');
			}
		}
		elseif ( isset( static::$databaseColumnMap['unapproved_comments'] ) and \IPS\Member::loggedIn()->member_id and $this->mapped('unapproved_comments') )
		{
			$idColumn = static::$databaseColumnId;
			$class = static::$commentClass;
			$authorCol = $class::$databasePrefix . $class::$databaseColumnMap['author'];
			$where = array( array( $class::$databasePrefix . $class::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );
			if ( isset( $class::$databaseColumnMap['approved'] ) )
			{
				$col = $class::$databasePrefix . $class::$databaseColumnMap['approved'];
				$where[] = array( "{$col}=0 AND {$authorCol}=" . \IPS\Member::loggedIn()->member_id );
			}
			elseif( isset( $class::$databaseColumnMap['hidden'] ) )
			{
				$col = $class::$databasePrefix . $class::$databaseColumnMap['hidden'];
				$where[] = array( "{$col}=1 AND {$authorCol}=" . \IPS\Member::loggedIn()->member_id );
			}
			$count += \IPS\Db::i()->select( 'COUNT(*)', $class::$databaseTable, $where )->first();
		}

		return $count;
	}
	
	/**
	 * Get review page count
	 *
	 * @return	int
	 */
	public function reviewPageCount()
	{
		if ( $this->reviewPageCount === NULL )
		{
			$this->reviewPageCount = ceil( $this->reviewCount() / static::$reviewsPerPage );

			if( $this->reviewPageCount < 1 )
			{
				$this->reviewPageCount	= 1;
			}
		}
		return $this->reviewPageCount;
	}
	
	/**
	 * Get review count
	 *
	 * @return	int
	 */
	public function reviewCount()
	{
		if( !isset( static::$reviewClass ) )
		{
			return 0;
		}

		$count = $this->mapped('num_reviews');

		if( $this->canViewHiddenReviews() )
		{
			if ( isset( static::$databaseColumnMap['hidden_reviews'] ) )
			{
				$count += $this->mapped('hidden_reviews');
			}
			if ( isset( static::$databaseColumnMap['unapproved_reviews'] ) )
			{
				$count += $this->mapped('unapproved_reviews');
			}
		}
		elseif ( isset( static::$databaseColumnMap['unapproved_reviews'] ) and \IPS\Member::loggedIn()->member_id and $this->mapped('unapproved_reviews') )
		{
			$idColumn = static::$databaseColumnId;
			$class = static::$reviewClass;
			$authorCol = $class::$databasePrefix . $class::$databaseColumnMap['author'];
			$where = array( array( $class::$databasePrefix . $class::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );
			if ( isset( $class::$databaseColumnMap['approved'] ) )
			{
				$col = $class::$databasePrefix . $class::$databaseColumnMap['approved'];
				$where[] = array( "{$col}=0 AND {$authorCol}=" . \IPS\Member::loggedIn()->member_id );
			}
			elseif( isset( $class::$databaseColumnMap['hidden'] ) )
			{
				$col = $class::$databasePrefix . $class::$databaseColumnMap['hidden'];
				$where[] = array( "{$col}=1 AND {$authorCol}=" . \IPS\Member::loggedIn()->member_id );
			}
			$count += \IPS\Db::i()->select( 'COUNT(*)', $class::$databaseTable, $where )->first();
		}

		return $count;
	}
	
	/**
	 * Get comment pagination
	 *
	 * @param	array				$qs	Query string parameters to keep (for example sort options)
	 * @param	string				$template	Template to use
	 * @param	int|null			$pageCount	The number of pages, if known, or NULL to calculate automatically
	 * @param	\IPS\Http\Url|NULL	$baseUrl	The base URL, if not the normal item url
	 * @return	string
	 */
	public function commentPagination( $qs=array(), $template='pagination', $pageCount = NULL, $baseUrl = NULL )
	{
		return $this->_pagination( $qs, $pageCount ?: $this->commentPageCount(), $this->getCommentsPerPage(), $template, $baseUrl, 'comments' );
	}
	
	/**
	 * Get review pagination
	 *
	 * @param	array				$qs			Query string parameters to keep (for example sort options)
	 * @param	string				$template	Template to use
	 * @param	int|null			$pageCount	The number of pages, if known, or NULL to calculate automatically
	 * @param	\IPS\Http\Url|NULL	$baseUrl	The base URL, if not the normal item url
	 * @return	string
	 */
	public function reviewPagination( $qs=array(), $template='pagination', $pageCount = NULL, $baseUrl = NULL )
	{
		return $this->_pagination( $qs, $pageCount ?: $this->reviewPageCount(), static::$reviewsPerPage, $template, $baseUrl, 'reviews' );
	}
	
	/**
	 * Get comment/review pagination
	 *
	 * @param	array				$qs			Query string parameters to keep (for example sort options)
	 * @param	int					$count		Page count
	 * @param	int					$perPage	Number per page
	 * @param	string				$template	Name of the pagination template
	 * @param	\IPS\Http\Url|NULL	$baseUrl	The base URL, if not the normal item url
	 * @param	string				$fragment	Query Parameter which can be applied to the url as anchor/fragment
	 * @return	string
	 */
	protected function _pagination( $qs, $count, $perPage, $template, $baseUrl = NULL, $fragment = NULL )
	{
		$url = $baseUrl ?: $this->url();
		foreach ( $qs as $key )
		{
			if ( isset( \IPS\Request::i()->$key ) )
			{
				$url = $url->setQueryString( $key, \IPS\Request::i()->$key );
			}
		}

		if ( $fragment )
		{
			$url = $url->setFragment( $fragment );
		}

		$page = isset( \IPS\Request::i()->page ) ? \intval( \IPS\Request::i()->page ) : 1;

		if( $page < 1 )
		{
			$page = 1;
		}

		return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->$template( $url->setPage( 'page', $page ), $count, $page, $perPage );
	}

	/**
	 * Whether we're viewing the last page of reviews/comments on this item
	 *
	 * @param	string	$type		"reviews" or "comments"
	 * @return	boolean
	 */
	public function isLastPage( $type='comments' )
	{
		/* If this class does not have any comments or reviews, return true */
		if ( !isset( static::$commentClass ) AND !isset( static::$reviewClass ) )
		{
			return TRUE;
		}
		
		$pageCount = ( $type == 'reviews' ) ? $this->reviewPageCount() : $this->commentPageCount();

		if( $pageCount !== NULL && ( ( \IPS\Request::i()->page && \IPS\Request::i()->page == $pageCount ) || !isset( \IPS\Request::i()->page ) && \in_array( $pageCount, array( 0, 1 ) ) ) )
		{
			return TRUE;
		}

		return FALSE;
	}
	
	/**
	 * Get comments
	 *
	 * @param	int|NULL			$limit					The number to get (NULL to use static::getCommentsPerPage())
	 * @param	int|NULL			$offset					The number to start at (NULL to examine \IPS\Request::i()->page)
	 * @param	string				$order					The column to order by
	 * @param	string				$orderDirection			"asc" or "desc"
	 * @param	\IPS\Member|NULL	$member					If specified, will only get comments by that member
	 * @param	bool|NULL			$includeHiddenComments	Include hidden comments or not? NULL to base of currently logged in member's permissions
	 * @param	\IPS\DateTime|NULL	$cutoff					If an \IPS\DateTime object is provided, only comments posted AFTER that date will be included
	 * @param	mixed				$extraWhereClause		Additional where clause(s) (see \IPS\Db::build for details)
	 * @param	bool|NULL			$bypassCache			Used in cases where comments may have already been loaded i.e. splitting comments on an item.
	 * @param	bool				$includeDeleted			Include Deleted Comments
	 * @param	bool|NULL			$canViewWarn			TRUE to include Warning information, NULL to determine automatically based on moderator permissions.
	 * @return	array|NULL|\IPS\Content\Comment	If $limit is 1, will return \IPS\Content\Comment or NULL for no results. For any other number, will return an array.
	 */
	public function comments( $limit=NULL, $offset=NULL, $order='date', $orderDirection='asc', $member=NULL, $includeHiddenComments=NULL, $cutoff=NULL, $extraWhereClause=NULL, $bypassCache=FALSE, $includeDeleted=FALSE, $canViewWarn=NULL )
	{		
		static $comments	= array();
		$idField			= static::$databaseColumnId;
		$_hash				= md5( $this->$idField . json_encode( \func_get_args() ) );

		if( !$bypassCache and isset( $comments[ $_hash ] ) )
		{
			return $comments[ $_hash ];
		}

		$class = static::$commentClass;

		if ( !$class )
		{
			return NULL;
		}
						
		$comments[ $_hash ]	= $this->_comments( $class, $limit ?: $this->getCommentsPerPage(), $offset, ( isset( $class::$databaseColumnMap[ $order ] ) ? ( $class::$databasePrefix . $class::$databaseColumnMap[ $order ] ) : $order ) . ' ' . $orderDirection, $member, $includeHiddenComments, $cutoff, $canViewWarn, $extraWhereClause, $includeDeleted );
		return $comments[ $_hash ];
	}

	/**
	 * @brief	Cached review pulls
	 */
	protected $cachedReviews	= array();

	/**
	 * Get reviews
	 *
	 * @param	int|NULL			$limit					The number to get (NULL to use static::getCommentsPerPage())
	 * @param	int|NULL			$offset					The number to start at (NULL to examine \IPS\Request::i()->page)
	 * @param	string				$order					The column to order by (NULL to examine \IPS\Request::i()->sort)
	 * @param	string				$orderDirection			"asc" or "desc" (NULL to examine \IPS\Request::i()->sort)
	 * @param	\IPS\Member|NULL	$member					If specified, will only get comments by that member
	 * @param	bool|NULL			$includeHiddenReviews	Include hidden comments or not? NULL to base of currently logged in member's permissions
	 * @param	\IPS\DateTime|NULL	$cutoff					If an \IPS\DateTime object is provided, only comments posted AFTER that date will be included
	 * @param	mixed				$extraWhereClause		Additional where clause(s) (see \IPS\Db::build for details)
	 * @param	bool				$includeDeleted			Include deleted content
	 * @param	bool|NULL			$canViewWarn			TRUE to include Warning information, NULL to determine automatically based on moderator permissions.
	 * @return	array|NULL|\IPS\Content\Comment	If $limit is 1, will return \IPS\Content\Comment or NULL for no results. For any other number, will return an array.
	 */
	public function reviews( $limit=NULL, $offset=NULL, $order=NULL, $orderDirection='desc', $member=NULL, $includeHiddenReviews=NULL, $cutoff=NULL, $extraWhereClause=NULL, $includeDeleted=FALSE, $canViewWarn=NULL )
	{
		$cacheKey	= md5( json_encode( \func_get_args() ) );

		if( isset( $this->cachedReviews[ $cacheKey ] ) )
		{
			return $this->cachedReviews[ $cacheKey ];
		}

		$class = static::$reviewClass;

		if ( !$class )
		{
			return NULL;
		}
	
		if ( $order === NULL )
		{
			if ( isset( \IPS\Request::i()->sort ) and \IPS\Request::i()->sort === 'newest' )
			{
				$order = $class::$databasePrefix . $class::$databaseColumnMap['date'] . ' DESC';
			}
			else
			{
				$order = "({$class::$databasePrefix}{$class::$databaseColumnMap['votes_helpful']}/{$class::$databasePrefix}{$class::$databaseColumnMap['votes_total']}) DESC, {$class::$databasePrefix}{$class::$databaseColumnMap['votes_helpful']} DESC, {$class::$databasePrefix}{$class::$databaseColumnMap['date']} DESC";
			}
		}
		else
		{
			$order = ( isset( $class::$databaseColumnMap[ $order ] ) ? ( $class::$databasePrefix . $class::$databaseColumnMap[ $order ] ) : $order ) .  ' ' . $orderDirection;
		}
		
		$this->cachedReviews[ $cacheKey ]	= $this->_comments( $class, $limit ?: static::$reviewsPerPage, $offset, $order, $member, $includeHiddenReviews, $cutoff, $canViewWarn, $extraWhereClause, $includeDeleted );
		return $this->cachedReviews[ $cacheKey ];
	}
	
	/**
	 * Get comments/reviews
	 *
	 * @param	string				$class 					The class
	 * @param	int|NULL			$limit					The number to get (NULL to use $perPage)
	 * @param	int|NULL			$offset					The number to start at (NULL to examine \IPS\Request::i()->page)
	 * @param	string				$order					The ORDER BY clause
	 * @param	\IPS\Member|NULL	$member					If specified, will only get comments by that member
	 * @param	bool|NULL			$includeHidden			Include hidden comments or not? NULL to base of currently logged in member's permissions
	 * @param	\IPS\DateTime|NULL	$cutoff					If an \IPS\DateTime object is provided, only comments posted AFTER that date will be included
	 * @param	bool|NULL			$canViewWarn			TRUE to include Warning information, NULL to determine automatically based on moderator permissions.
	 * @param	mixed				$extraWhereClause		Additional where clause(s) (see \IPS\Db::build for details)
	 * @param	bool				$includeDeleted			Include Deleted Content
	 * @return	array|NULL|\IPS\Content\Comment	If $limit is 1, will return \IPS\Content\Comment or NULL for no results. For any other number, will return an array.
	 */
	protected function _comments( $class, $limit, $offset=NULL, $order='date DESC', $member=NULL, $includeHidden=NULL, $cutoff=NULL, $canViewWarn=NULL, $extraWhereClause=NULL, $includeDeleted=FALSE )
	{
		/* Initial WHERE clause */
		$idColumn = static::$databaseColumnId;
		$where = array( array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );
		if ( $member !== NULL )
		{
			$where[] = array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['author'] . '=?', $member->member_id );
		}
		if ( $cutoff !== NULL )
		{
			$where[] = array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['date'] . '>?', $cutoff->getTimestamp() );
		}
		
		/* Exclude hidden comments? */
		$skipDeletedCheck = FALSE;
		
		if ( \in_array( 'IPS\Content\Hideable', class_implements( $class ) ) )
		{
			/* If $includeHidden is not a bool, work it out from the member's permissions */
			$includeHiddenByMember = FALSE;
			if ( $includeHidden === NULL )
			{
				if ( isset( static::$commentClass ) and $class == static::$commentClass )
				{
					$includeHidden = $this->canViewHiddenComments();
				}
				else if ( isset( static::$reviewClass ) and $class == static::$reviewClass )
				{
					$includeHidden = $this->canViewHiddenReviews();
				}

				$includeHiddenByMember = TRUE;
			}
			
			/* Does the item have any hidden comments? */
			if ( $includeHiddenByMember and isset( static::$databaseColumnMap['unapproved_comments'] ) and ! $this->mapped('unapproved_comments') )
			{
				$includeHiddenByMember = FALSE;
			}
						
			/* If we can't view hidden comments, exclude them with the WHERE clause */
			if ( !$includeHidden )
			{
				$authorCol = $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['author'];
				if ( isset( $class::$databaseColumnMap['approved'] ) )
				{
					$col = $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['approved'];
					if ( $includeHiddenByMember and \IPS\Member::loggedIn()->member_id )
					{
						$where[] = array( "({$col}=1 OR ( {$col}=0 AND {$authorCol}=" . \IPS\Member::loggedIn()->member_id . '))' );
					}
					else
					{
						$where[] = array( "{$col}=1" );
					}
				}
				elseif( isset( $class::$databaseColumnMap['hidden'] ) )
				{
					$col = $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['hidden'];
					
					/* Possible values for this column are -2, -1, 0, 1, 2. We want to select 0 and 2. However, when we use "OR", this can force MySQL to stop using indexes correctly. This is true of forums, for example. Using AND allows the index to be used. */
					$hiddenWhereClause = "({$col} IN(0,2))";
					$skipDeletedCheck	= TRUE;
					
					if ( $includeHiddenByMember and \IPS\Member::loggedIn()->member_id )
					{
						$where[] = array( "( {$hiddenWhereClause} OR ( {$col}=1 AND {$authorCol}=" . \IPS\Member::loggedIn()->member_id . '))' );
					}
					else
					{
						
						$where[] = array( $hiddenWhereClause );
					}
				}
				
			}
		}

		if ( $includeDeleted === FALSE AND $skipDeletedCheck === FALSE )
		{
	        if ( isset( $class::$databaseColumnMap['hidden'] ) )
	        {
		        $col = $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['hidden'];
		        $where[] = array( "{$col}!=-2" );
	        }
	        else if ( isset( $class::$databaseColumnMap['approved'] ) )
	        {
		        $col = $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['approved'];
		        $where[] = array( "{$col}!=-2" );
	        }
	    }
		
		/* We do not want to show any PBR content at all */
		if( $skipDeletedCheck === FALSE )
		{
			if ( isset( $class::$databaseColumnMap['hidden'] ) )
			{
				$col = $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['hidden'];
				$where[] = array( "{$col}!=-3" );
			}
			else if ( isset( $class::$databaseColumnMap['approved'] ) )
			{
				$col = $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['approved'];
				$where[] = array( "{$col}!=-3" );
			}
		}

		/* Additional where clause */
		if( $extraWhereClause !== NULL )
		{
			if ( !\is_array( $extraWhereClause ) or !\is_array( $extraWhereClause[0] ) )
			{
				$extraWhereClause = array( $extraWhereClause );
			}
			$where = array_merge( $where, $extraWhereClause );
		}
		
		/* Get the joins */
		$selectClause = $class::$databaseTable . '.*';		
		$joins = $class::joins( $this );
		if ( \is_array( $joins ) )
		{
			foreach ( $joins as $join )
			{
				if ( isset( $join['select'] ) )
				{
					$selectClause .= ', ' . $join['select'];
				}
			}
		}

		/* Bad offset values can create an SQL error with a negative limit */
		$_pageValue = ( \IPS\Request::i()->page ? \intval( \IPS\Request::i()->page ) : 1 );

		if( $_pageValue < 1 )
		{
			$_pageValue = 1;
		}

		/* If we have a cutoff with no offset explicitly defined, we should not automatically generate one for pagination since our results will be limited */
		$offset	= ( $cutoff and $offset === NULL ) ? 0 : ( $offset !== NULL ? $offset : ( ( $_pageValue - 1 ) * $limit ) );
		$ids    = array();

		/* If we are ordering by (date) DESC/ASC and only fetching one result, we are trying to find the first/last comment. This can be sped up greatly by selecting MIN(id) or MAX(id) instead of running the normal query, which often results in a filesort */
		if( $limit == 1 AND !$offset AND mb_strtolower( $order ) == mb_strtolower( $class::$databasePrefix . $class::$databaseColumnMap['date'] . ' desc' ) )
		{
			try
			{
				$where  = array( array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnId . '=(?)', $class::db()->select( 'MAX(' . $class::$databasePrefix . $class::$databaseColumnId . ')', $class::$databaseTable, $where ) ) );
				$offset = 0;
				$order  = NULL;
			}
			catch( \UnderflowException $e ){}
		}
		elseif( $limit == 1 AND !$offset AND mb_strtolower( $order ) == mb_strtolower( $class::$databasePrefix . $class::$databaseColumnMap['date'] . ' asc' ) )
		{
			try
			{
				$where  = array( array( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnId . '=(?)', $class::db()->select( 'MIN(' . $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnId . ')', $class::$databaseTable, $where ) ) );
				$offset = 0;
				$order  = NULL;
			}
			catch( \UnderflowException $e ){}
		}
		/* Large topics, private messages, etc. benefit greatly from splitting the query into two queries */
		elseif ( $this->mapped('num_comments') >= 500 )
		{
			/* Its more efficient to just get the primary ids first without joins and then we can fetch all the data on the second query once the offset gets larger */
			if( method_exists( $this, 'forceIndexForPaginatedIds' ) and $this->forceIndexForPaginatedIds() and $offset < 20000 )
			{
				/* Only use the index if we're not at a massive offset */
				$ids = iterator_to_array( $class::db()->select( $class::$databasePrefix . $class::$databaseColumnId, $class::$databaseTable, $where, $order, array($offset, $limit) )->forceIndex( $this->forceIndexForPaginatedIds() )->setKeyField( $class::$databasePrefix . $class::$databaseColumnId ) );
			}
			else
			{
				$ids = iterator_to_array( $class::db()->select( $class::$databasePrefix . $class::$databaseColumnId, $class::$databaseTable, $where, $order, array($offset, $limit) )->setKeyField( $class::$databasePrefix . $class::$databaseColumnId ) );
			}

			$where  = array( array( $class::db()->in( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnId, array_keys( $ids ) ) ) );
			$offset = 0;
			$order  = NULL;
		}
		
		/* Construct the query */
		$results = array();
		$bits = \IPS\Db::SELECT_MULTIDIMENSIONAL_JOINS;

		if( static::$useWriteServer === TRUE )
		{
			$bits += \IPS\Db::SELECT_FROM_WRITE_SERVER;
		}

		$query = $class::db()->select( $selectClause, $class::$databaseTable, $where, $order, array( $offset, $limit ), NULL, NULL, $bits );
		if ( \is_array( $joins ) )
		{
			foreach ( $joins as $join )
			{
				$query->join( $join['from'], $join['where'] );
			}
		}

		/* Get the results */
		$commentIdColumn = $class::$databaseColumnId;
		foreach ( $query as $row )
		{
			$result = $class::constructFromData( $row );
			if ( $limit === 1 )
			{
				return $result;
			}
			else
			{
				if ( \IPS\IPS::classUsesTrait( $class, 'IPS\Content\Reactable' ) )
				{
					$result->reputation = array();
					$result->reactBlurb = array();
				}
				$results[ $result->$commentIdColumn ] = $result;
			}
		}
		
		/* If we used two queries, ensure $result is sorted by the order of $ids */
		if ( \count( $ids ) )
		{
			$newResults = array();
			
			foreach( $ids as $k => $v )
			{
				$newResults[ $k ] = $results[ $k ]; 
			}
			
			$results = $newResults;
			unset($newResults);
		}
		
		/* Get the reputation stuff now so we don 't have to do lots of queries later */
		if ( \IPS\Settings::i()->reputation_enabled AND \IPS\IPS::classUsesTrait( $class, 'IPS\Content\Reactable' ) AND \count( $results ) AND \IPS\Dispatcher::hasInstance() )
		{
			/* Some basic init */
			$names				= array();
			$reactions			= array();
			$enabledReactions	= \IPS\Content\Reaction::enabledReactions();

			/* Jump ahead if there are no enabled reactions */
			if( !\count( $enabledReactions ) )
			{
				goto noReactions;
			}

			/* Work out the query */
			$reputationWhere	= array();
			$reputationWhere[]	= array( 'core_reputation_index.rep_class=? AND core_reputation_index.type=?', $class::reactionClass(), $class::reactionType() );
			$reputationWhere[]	= array( \IPS\Db::i()->in( 'core_reputation_index.type_id', array_keys( $results ) ) );
			$reputationWhere[]	= array( \IPS\Db::i()->in( 'core_reputation_index.reaction', array_keys( $enabledReactions ) ) );
			
			$select = \IPS\Db::i()->select( 'core_reputation_index.type_id, core_reputation_index.member_id, core_reputation_index.reaction', 'core_reputation_index', $reputationWhere );
			
			/* Get the reputation data first */
			$reputationData	= array();
			$memberIds		= array();

			foreach ( $select as $reputation )
			{
				$reputationData[]	= $reputation;
				$memberIds[ $reputation['member_id'] ] = $reputation['member_id'];
			}

			/* Sanity check to make sure we have reputation data */
			if( !\count( $reputationData ) )
			{
				goto noReactions;
			}

			/* Get the member data */
			$memberData = iterator_to_array( \IPS\Db::i()->select( 'member_id, name, members_seo_name, member_group_id', 'core_members', array( \IPS\Db::i()->in( 'member_id', $memberIds ) ) )->setKeyField('member_id') );

			/* Randomize the reactions */
			shuffle( $reputationData );

			/* Now loop over the reputation data and assign as appropriate */
			foreach ( $reputationData as $reputation )
			{
				if ( !isset( $memberData[ $reputation['member_id'] ] ) )
				{
					continue;
				}

				$results[ $reputation['type_id'] ]->reputation[ $reputation['member_id'] ] = $reputation['reaction'];

				if ( $reputation['member_id'] === \IPS\Member::loggedIn()->member_id )
				{
					if( isset( $names[ $reputation['type_id'] ] ) )
					{
						array_unshift( $names[ $reputation['type_id'] ], '' );
					}
					else
					{
						$names[ $reputation['type_id'] ][0] = '';
					}
				}
				elseif ( !isset( $names[ $reputation['type_id'] ] ) or \count( $names[ $reputation['type_id'] ] ) < 3 )
				{
					$names[ $reputation['type_id'] ][ $reputation['member_id'] ] = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->userLinkFromData( $reputation['member_id'], $memberData[ $reputation['member_id'] ]['name'], $memberData[ $reputation['member_id'] ]['members_seo_name'], $memberData[ $reputation['member_id'] ]['member_group_id'] );
				}
				elseif ( \count( $names[ $reputation['type_id'] ] ) < 18 )
				{
					$names[ $reputation['type_id'] ][ $reputation['member_id'] ] = htmlspecialchars( $memberData[ $reputation['member_id'] ]['name'], ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE );
				}

				if ( !isset( $reactions[ $reputation['type_id'] ][ $reputation['reaction'] ] ) )
				{
					$reactions[ $reputation['type_id'] ][ $reputation['reaction'] ] = 0;
				}
				
				$reactions[ $reputation['type_id'] ][ $reputation['reaction'] ]++;
			}

			if ( \count( $reactions ) )
			{
				/* Sort the reactions */
				foreach( array_keys( $reactions ) as $typeId )
				{
					/* Error suppressor for: https://bugs.php.net/bug.php?id=50688 */
					@uksort( $reactions[ $typeId ], function( $a, $b ) use ( $enabledReactions ) {
						$positionA = $enabledReactions[ $a ]->position;
						$positionB = $enabledReactions[ $b ]->position;
						
						if ( $positionA == $positionB )
						{
							return 0;
						}
						
						return ( $positionA < $positionB ) ? -1 : 1;
					} );
				}				
			}	

			noReactions:

			$commentOrReview = ( isset( static::$commentClass ) and $class == static::$commentClass ) ? 'Comment' : 'Review';

			/* If we need to display the "like blurb", compile that now */
			$langPrefix = 'react_';
			if ( \IPS\Content\Reaction::isLikeMode() )
			{
				$langPrefix = 'like_';
			}
			foreach ( $names as $commentId => $people )
			{
				$i = 0;

				if ( isset( $people[0] ) )
				{						
					if ( \count( $names[ $commentId ] ) === 1 )
					{
						$results[ $commentId ]->likeBlurb = \IPS\Member::loggedIn()->language()->addToStack( "{$langPrefix}blurb_just_you" );
						continue;
					}
					
					$people[0] = \IPS\Member::loggedIn()->language()->addToStack("{$langPrefix}blurb_you_and_others");
				}
				
				$peopleToDisplayInMainView = array();
				$peopleToDisplayInSecondaryView = array();
				$numberOfLikes = \count( $results[ $commentId ]->reputation );
				$andXOthers = $numberOfLikes;
				foreach ( $people as $id => $name )
				{
					if ( $i < 3 )
					{
						$peopleToDisplayInMainView[] = $name;
						$andXOthers--;
					}
					else
					{
						$peopleToDisplayInSecondaryView[] = strip_tags( $name );
					}
					$i++;
				}
				
				if ( $andXOthers )
				{
					if ( \count( $peopleToDisplayInSecondaryView ) < $andXOthers )
					{
						$peopleToDisplayInSecondaryView[] = \IPS\Member::loggedIn()->language()->addToStack( "{$langPrefix}blurb_others_secondary", FALSE, array( 'pluralize' => array( $andXOthers - \count( $peopleToDisplayInSecondaryView ) ) ) );
					}
					$peopleToDisplayInMainView[] = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->reputationOthers( $results[ $commentId ]->url( 'showReactions' ), \IPS\Member::loggedIn()->language()->addToStack( "{$langPrefix}blurb_others", FALSE, array( 'pluralize' => array( $andXOthers ) ) ), json_encode( $peopleToDisplayInSecondaryView ) );
				}
				
				$results[ $commentId ]->likeBlurb = \IPS\Member::loggedIn()->language()->addToStack( "{$langPrefix}blurb", FALSE, array( 'pluralize' => array( $numberOfLikes ), 'htmlsprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $peopleToDisplayInMainView ) ) ) );
			}
			
			foreach( $reactions AS $commentId => $reaction )
			{
				$results[ $commentId ]->reactBlurb = $reaction;
			}
		}

		/* We don't need to fetch report data if there is no instance (i.e. generating a content/comment digest) */
		if( \IPS\Dispatcher::hasInstance() )
		{
			$member = $member ? $member : \IPS\Member::loggedIn();

			/* Do report stuff so we don't have to do lots of queries later */
			if ( \IPS\IPS::classUsesTrait( $this, 'IPS\Content\Reportable' ) and ( $member->group['g_can_report'] == '1' OR \in_array( $class, explode( ',', $member->group['g_can_report'] ) ) ) )
			{
				$reportIds = array();

				if( \count( $results ) )
				{
					foreach ( \IPS\Db::i()->select( 'id, content_id', 'core_rc_index', array( array( 'class=?', $class ), array( \IPS\Db::i()->in( 'content_id', array_keys( $results ) ) ) ) ) as $report )
					{
						$reportIds[ $report['id'] ] = $report['content_id'];
					}
				}

				if ( \count( $reportIds ) )
				{
					foreach ( \IPS\Db::i()->select( '*', 'core_rc_reports', array( array( 'report_by=?', $member->member_id ), array( \IPS\Db::i()->in( 'rid', array_keys( $reportIds ) ) ) ) ) as $detail )
					{
						$results[ $reportIds[ $detail['rid'] ] ]->reportData = $detail;
					}
				}

				/* Now populate the rest of the results */
				foreach ( $results as $id => $obj )
				{
					if ( !isset( $results[ $id ]->reportData ) )
					{
						$results[ $id ]->reportData = FALSE;
					}
				}
			}

			/* Get the warning stuff now so we don 't have to do lots of queries later */
			$canViewWarn = \is_null( $canViewWarn ) ? \IPS\Member::loggedIn()->modPermission( 'mod_see_warn' ) : $canViewWarn;
			if ( $canViewWarn and \count( $results ) )
			{
				$module = static::$module;

				if ( isset( static::$commentClass ) and $class == static::$commentClass )
				{
					$module .= '-comment';
				}
				if ( isset( static::$reviewClass ) and $class == static::$reviewClass )
				{
					$module .= '-review';
				}

				$where = array( array( 'wl_content_app=? AND wl_content_module=? AND wl_content_id1=?', static::$application, $module, $this->$idColumn ) );
				$where[] = array( \IPS\Db::i()->in( 'wl_content_id2', array_keys( $results ) ) );

				foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members_warn_logs', $where ), 'IPS\core\Warnings\Warning' ) as $warning )
				{
					$results[ $warning->content_id2 ]->warning = $warning;
				}
			}
		}

		/* Solved count */
		$commentClass = static::$commentClass;
		$showSolvedStats = FALSE;

		if ( method_exists( $this, 'isQuestion' ) and $this->isQuestion() )
		{
			$showSolvedStats = TRUE;
		}
		else if ( \IPS\IPS::classUsesTrait( $this, 'IPS\Content\Solvable' ) and ( $this->containerAllowsMemberSolvable() OR $this->containerAllowsSolvable() ) )
		{
			$showSolvedStats = TRUE;
		}

		if ( $commentClass AND $showSolvedStats )
		{
			$memberIds = array();
			$solvedCounts = array();
			$authorField = $commentClass::$databaseColumnMap['author'];

			foreach( $results as $id => $data )
			{
				$memberIds[ $data->$authorField ] = $data->$authorField;
			}

			if ( \count( $memberIds ) )
			{
				foreach( \IPS\Db::i()->select( 'COUNT(*) as count, member_id', 'core_solved_index', array( \IPS\Db::i()->in( 'member_id', $memberIds ) ), NULL, NULL, 'member_id' ) as $member )
				{
					$solvedCounts[ $member['member_id'] ] = $member['count'];
				}

				foreach( $results as $id => $data )
				{
					if ( isset( $solvedCounts[ $data->$authorField ] ) )
					{
						$results[ $id ]->author_solved_count = $solvedCounts[ $data->$authorField ];
					}
				}
			}
		}

		/* Recognized content */
		if ( $commentClass AND \IPS\IPS::classUsesTrait( $commentClass, 'IPS\Content\Recognizable' ) )
		{
			foreach( \IPS\Db::i()->select( '*', 'core_member_recognize', [ [ 'r_content_class=?', $commentClass ], [ \IPS\Db::i()->in('r_content_id', array_keys( $results ) ) ] ] ) as $row )
			{
				if ( isset( $results[ $row['r_content_id'] ] ) )
				{
					$results[ $row['r_content_id'] ]->recognized = \IPS\core\Achievements\Recognize::constructFromData( $row );
				}
			}
		}

		/* Return */
		return ( $limit === 1 ) ? NULL : $results;
	}
		
	/**
	 * @brief	Comment form output cached
	 */
	protected $_commentFormHtml	= NULL;
	
	/**
	 * If, when making a post, we should merge with an existing comment, this method returns the comment to merge with
	 *
	 * @return	\IPS\Content\Comment|NULL
	 */
	public function mergeConcurrentComment()
	{
		if ( \IPS\Member::loggedIn()->member_id and \IPS\Settings::i()->merge_concurrent_posts and $this->lastCommenter()->member_id == \IPS\Member::loggedIn()->member_id and !\IPS\Member::loggedIn()->moderateNewContent() )
		{
			$lastComment = $this->comments( 1, 0, 'date', 'desc', NULL, TRUE );

			if ( $lastComment !== NULL and $lastComment->mapped('date') > \IPS\DateTime::create()->sub( new \DateInterval( 'PT' . \IPS\Settings::i()->merge_concurrent_posts . 'M' ) )->getTimestamp() AND $lastComment->mapped('author') == \IPS\Member::loggedIn()->member_id AND !$lastComment->hidden() )
			{
				return $lastComment;
			}
		}
		return NULL;
	}
	
	/**
	 * When making a reply, the javascript handler will post the reply form if the ajax post fails. We want to ensure that we're not creating a duplicate post.
	 * We will consider a post to be duplicate if the author matches, the content matches and it is within a 2 minute window and the last comment is not hidden
	 *
	 * @param	string		$comment	Comment content as returned from the editor
	 * @return	\IPS\Content\Comment|false
	 */
	public function isDuplicateComment( $comment )
	{
		if ( isset( \IPS\Request::i()->failedReply ) )
		{
			if ( \IPS\Member::loggedIn()->member_id )
			{
				/* It is possible that even though this is a duplicate post, it is not the last reply in this item, so let us just get the latest reply by this member */
				$lastComment = $this->comments( 1, 0, 'date', 'desc', \IPS\Member::loggedIn() );
	
				if ( $lastComment !== NULL and $lastComment->mapped('date') > \IPS\DateTime::create()->sub( new \DateInterval( 'PT2M' ) )->getTimestamp() AND !$lastComment->hidden() )
				{
					if ( $lastComment->mapped('content') == $comment )
					{
						return $lastComment;
					}
				}
			}
		}
		
		return FALSE;
	}
	
	/**
	 * @brief	Check posts per day limits? Useful for things that use the content system, but aren't necessarily content themselves.
	 */
	public static $checkPostsPerDay = TRUE;
	
	/**
	 * Return the comment form object
	 *
	 * @return	\IPS\Helpers\Form
	 */
	protected function _commentForm()
	{
		$idColumn			= static::$databaseColumnId;
		$form				= new \IPS\Helpers\Form( 'commentform' . '_' . $this->$idColumn, static::$formLangPrefix . 'submit_comment' );
		$form->class		= 'ipsForm_vertical';
		$form->hiddenValues['_contentReply']	= TRUE;

		return $form;
	}

	/**
	 * Build comment form
	 *
	 * @param	int|NULL	$lastSeenId		Last ID seen (point to start from for new comment polling)
	 * @return	string
	 */
	public function commentForm( $lastSeenId = NULL )
	{
		/* Have we built it already? */
		if( $this->_commentFormHtml !== NULL )
		{
			return $this->_commentFormHtml;
		}

		/* Can we comment? */
		if ( $this->canComment() )
		{
			$commentClass = static::$commentClass;
			$idColumn = static::$databaseColumnId;
			$commentIdColumn = $commentClass::$databaseColumnId;
			$commentDateColumn = $commentClass::$databaseColumnMap['date'];
			
			$form	= $this->_commentForm();

			$elements = $this->commentFormElements();
			
			foreach( $elements as $element )
			{
				$form->add( $element );
			}
						
			if ( $values = $form->values() )
			{
				/* Disable read/write separation */
				\IPS\Db::i()->readWriteSeparation = FALSE;
				
				$newCommentContent = $values[ static::$formLangPrefix . 'comment' . '_' . $this->$idColumn ];
				
				/* Is this a duplicate comment? */
				if ( $duplicateComment = $this->isDuplicateComment( $newCommentContent ) )
				{
					/* Log it */
					\IPS\Log::debug( "Member ID:" . \IPS\Member::loggedIn()->member_id . "\nContent: " . mb_substr( $newCommentContent, 0, 1000 ), "duplicate_comment" );
					
					/* And redirect them */
					\IPS\Output::i()->redirect( $this->lastCommentPageUrl()->setFragment( 'comment-' . $duplicateComment->$commentIdColumn ) );
				}
				
				/* Check Post Per Day Limits */
				if ( \IPS\Member::loggedIn()->member_id AND static::$checkPostsPerDay === TRUE AND \IPS\Member::loggedIn()->checkPostsPerDay() === FALSE )
				{
					if ( \IPS\Request::i()->isAjax() )
					{
						\IPS\Output::i()->json( array( 'type' => 'error', 'message' => \IPS\Member::loggedIn()->language()->addToStack( 'posts_per_day_error' ) ) );
					}
					else
					{
						\IPS\Output::i()->error( 'posts_per_day_error', '2S177/2', 403, '' );
					}
				}

				/* Check for banned IP - The banned ip addresses are only checked inside the register and login controller, so people are able to bypass them when PBR is used */
				if( !\IPS\Member::loggedIn()->member_id AND \IPS\Request::i()->ipAddressIsBanned() )
				{
					\IPS\Output::i()->showBanned();
				}
				
				$currentPageCount = \IPS\Request::i()->currentPage;
				
				/* Merge? */
				if ( $lastComment = $this->mergeConcurrentComment() AND ( !isset( $values['hide'] ) OR !$values['hide'] ) )
				{
					/* Determine if the post is hidden to start with */
					$isHidden	= $lastComment->hidden();

					$valueField = $lastComment::$databaseColumnMap['content'];		
					$newContent = $lastComment->$valueField . $newCommentContent;				
					$lastComment->editContents( $newContent );

					$parameters = array_merge( array( 'reply-' . static::$application . '/' . static::$module  . '-' . $this->$idColumn ), $lastComment->attachmentIds() );
					\IPS\File::claimAttachments( ...$parameters );
					
					if ( \IPS\Request::i()->isAjax() )
					{
						$newPageCount = $this->commentPageCount();
						/* We will do a redirect if either the page number changes or if the post was not hidden but is now */
						if ( $currentPageCount != $newPageCount OR $isHidden != $lastComment->hidden() )
						{
							\IPS\Output::i()->json( array( 'type' => 'redirect', 'page' => $newPageCount, 'total' => $this->mapped('num_comments'), 'content' => $lastComment->html(), 'url' => (string) $lastComment->url('find') ) );
						}
						else
						{
							\IPS\Output::i()->json( array( 'type' => 'merge', 'id' => $lastComment->$commentIdColumn, 'page' => $newPageCount, 'total' => $this->mapped('num_comments'), 'content' => \IPS\Output::i()->replaceEmojiWithImages( $newContent ) ) );
						}
					}
					else
					{
						\IPS\Output::i()->redirect( $this->lastCommentPageUrl()->setFragment( 'comment-' . $lastComment->$commentIdColumn ) );
					}
				}
				
				/* Or post? */
				$comment = $this->processCommentForm( $values );
				unset( $this->commentPageCount );

				$newPageCount = $this->commentPageCount();
				
				if ( $comment->hidden() === -3 )
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=register', 'front', 'register' ) );
				}
				elseif( $comment->hidden() AND !\IPS\Member::loggedIn()->member_id )
				{
					\IPS\Output::i()->json( array( 'type' => 'add', 'id' => $comment->$commentIdColumn, 'page' => $newPageCount, 'total' => $this->mapped('num_comments'), 'content' => '', 'message' => \IPS\Member::loggedIn()->language()->addToStack( 'mod_queue_message' ) ) );
				}
				elseif ( \IPS\Request::i()->isAjax() )
				{
					$this->markRead( NULL, NULL, NULL, TRUE );

					if ( $currentPageCount != $newPageCount )
					{
						\IPS\Output::i()->json( array( 'type' => 'redirect', 'page' => $newPageCount, 'total' => $this->mapped('num_comments'), 'content' => \IPS\Output::i()->replaceEmojiWithImages( $comment->html() ), 'url' => (string) $comment->url('find') ) );
					}
					else
					{
						$output = '';
						/* This comes from a form field and has an underscore, see the form definition above */
						if ( isset( \IPS\Request::i()->_lastSeenID ) and \intval( \IPS\Request::i()->_lastSeenID ) )
						{
							try
							{
								$lastComment = $commentClass::load( \IPS\Request::i()->_lastSeenID );
								foreach ( $this->comments( NULL, 0, 'date', 'asc', NULL, NULL, \IPS\DateTime::ts( $lastComment->$commentDateColumn ) ) as $newComment )
								{
									if ( $newComment->$commentIdColumn != $comment->$commentIdColumn )
									{
										$output .= $newComment->html();
									}
								}
							}
							catch ( \OutOfRangeException $e) {}

						}
						$output .= $comment->html();
						
						$message = '';
						if ( $comment->hidden() == 1 )
						{
							$message = \IPS\Member::loggedIn()->language()->addToStack( 'mod_queue_message' );
						}

						/* Data Layer stuff for comments here, not in Comment class. We track user actions, and the user action is handled here */
						$dataLayer = \IPS\Settings::i()->core_datalayer_enabled ? $this->getDataLayerProperties( $comment ) : array();

						\IPS\Output::i()->json(
							array(
								'type' => 'add',
								'id' => $comment->$commentIdColumn,
								'page' => $newPageCount,
								'total' => $this->mapped('num_comments'),
								'content' => \IPS\Output::i()->replaceEmojiWithImages( $output ),
								'message' => $message,
								'postedByLoggedInMember' => true,
								/* This is used on the front end */
								"dataLayer" => $dataLayer,
							)
						);
					}
					return;
				}
				else
				{
					\IPS\Output::i()->redirect( $this->lastCommentPageUrl()->setFragment( 'comment-' . $comment->$commentIdColumn ) );
				}
			}
			elseif ( \IPS\Request::i()->isAjax() )
			{
				$hasError = FALSE;
				foreach ( $elements as $input )
				{
					if ( $input->error )
					{
						$hasError = $input->error;
					}
				}
				if ( $hasError )
				{
					\IPS\Output::i()->json( array( 'type' => 'error', 'message' => \IPS\Member::loggedIn()->language()->addToStack( $hasError ), 'form' => (string) $form->customTemplate( array( \IPS\Theme::i()->getTemplate( $commentClass::$formTemplate[0][0], $commentClass::$formTemplate[0][1], $commentClass::$formTemplate[0][2] ), $commentClass::$formTemplate[1] ) ) ) );
				}
			}
			
			/* Mod Queue? */
			$return = '';
			$guestPostBeforeRegister = ( !\IPS\Member::loggedIn()->member_id ) ? ( !isset( static::$containerNodeClass ) or ( $container = $this->container() and !$container->can( 'reply', \IPS\Member::loggedIn(), FALSE ) ) ) : NULL;
			$modQueued = static::moderateNewComments( \IPS\Member::loggedIn(), $guestPostBeforeRegister );
			if ( $guestPostBeforeRegister or $modQueued )
			{
				$return .= \IPS\Theme::i()->getTemplate( 'forms', 'core' )->postingInformation( $guestPostBeforeRegister, $modQueued );
			}			
			$this->_commentFormHtml	= $return . $form->customTemplate( array( \IPS\Theme::i()->getTemplate( $commentClass::$formTemplate[0][0], $commentClass::$formTemplate[0][1], $commentClass::$formTemplate[0][2] ), $commentClass::$formTemplate[1] ) );
			return $this->_commentFormHtml;
		}
		/* Show an explanation why comments are disabled for future items */
		else if ( \IPS\Member::loggedIn()->member_id AND $this->isFutureDate() )
		{
			return $this->_commentFormHtml	= \IPS\Member::loggedIn()->language()->addToStack( 'comments_disabled_future_item', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( static::$title . '_pl_lc' ) ) ) );
		}

		/* Hang on, are we a guest, but if logged in, could comment? */
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			$testUser = new \IPS\Member;
			$testUser->member_group_id = \IPS\Settings::i()->member_group;
			if ( $this->canComment( $testUser ) )
			{
				$this->_commentFormHtml	= $this->guestTeaser();
				return $this->_commentFormHtml;
			}
		}
		
		/* Nope, just display nothing */
		$this->_commentFormHtml	= '';

		return $this->_commentFormHtml;
	}

	protected $_dataLayerProperties = array();

	/**
	 * Most, if not all of these are the same for different events, so we can just have one method
	 *
	 * @param   IPS\Content\Comment $comment    A comment item, leave null for these keys to be omitted
	 *
	 * @return  array
	 */
	public function getDataLayerProperties( ?\IPS\Content\Comment $comment = null )
	{
		$commentIdColumn = $comment ? $comment::$databaseColumnId : null;
		$index = $commentIdColumn ? ( $comment->$commentIdColumn ?: 0 ) : 0;

		if ( !isset( $this->_dataLayerProperties[$index] ) )
		{
			$app      = static::$application ?? null;
			$idColumn = static::$databaseColumnId;
			$dataLayer = array(
				'author_id'     => \IPS\core\DataLayer::i()->getSsoId( $this->author()->member_id ),
				'author_name'   => $this->author()->real_name ?: null,
				'content_age'   => $this->mapped( 'date' ) ? \intval( floor( ( time() - $this->mapped( 'date' ) ) / 86400 ) ) : null,
				'content_container_id'   => null,
				'content_container_name' => null,
				'content_container_type' => null,
				'content_container_url'  => null,
				'content_id'    => $this->$idColumn,
				'content_title' => $this->mapped( 'title' ),
				'content_type'  => static::$contentType ?? null,
				'content_url'   => (string) $this->url(),
			);

			if ( $comment AND ( $commentIdColumn = $comment::$databaseColumnId ?? null ) )
			{
				$dataLayer = array_replace( $dataLayer, array(
					'comment_id'    => $comment->$commentIdColumn,
					'comment_type'  => $comment::$commentType ?? null,
					'comment_url'   => (string) $comment->url()
				) );
			}

			/* For QA forums, the comment_type and content_type is an exception */
			if ( \IPS\Settings::i()->core_datalayer_distinguish_qa AND
			     $this instanceof \IPS\forums\Topic AND
			     $this->container()->_forum_type === 'qa' )
			{
				$dataLayer['content_type'] = 'question';
				if ( isset( $comment ) )
				{
					$dataLayer['comment_type'] = 'answer';
				}
			}
			/* If we didn't find a Comment or Content type, try pulling that info from the static title fields */
			elseif ( ( !isset( $dataLayer['content_type'] ) AND isset( static::$title ) ) OR ( !isset( $dataLayer['comment_type'] ) AND isset( $comment, $comment::$title ) ) )
			{
				$contentType = ( $app ? preg_replace( "/[{$app}_]?(.*)/", '$1', static::$title ?? "" ) : ( static::$title ?? null ) ) ?: null;
				if ( $contentType AND !isset( $dataLayer['content_type'] ) )
				{
					$dataLayer['content_type'] = $contentType;
				}

				if ( $comment AND isset( $comment::$title ) AND !isset( $dataLayer['comment_type'] ) )
				{
					$commentType = ($app or $contentType) ? preg_replace( "/" . ( $app ? "[{$app}_]?" : '' ) . ( $contentType ? "[{$contentType}_]?" : '' ) . "(.*)/", "$1", $comment::$title ) : $comment::$title;
					$dataLayer['comment_type'] = $commentType;
				}
			}

			/* Use either comment or review if there still is no comment type (note this should never happen as of IPS v4.6 unless 3rd party things are going on) */
			if ( $comment AND !isset( $dataLayer['comment_type'] ) )
			{
				$dataLayer['comment_type'] = $comment instanceof \IPS\Content\Review ? 'review' : 'comment';
			}

			try
			{
				$container = $this->container();
				$dataLayer = array_replace( $dataLayer, $container->getDataLayerProperties() );
			}
			catch ( \OutOfRangeException | \BadMethodCallException $e ) {}

			if ( !isset( $dataLayer['content_area'] ) AND $app )
			{
				$lang = \IPS\Lang::load( \IPS\Lang::defaultLanguage() );
				if ( $lang->checkKeyExists( '__app_' . static::$application ) )
				{
					$dataLayer['content_area'] = $lang->addToStack( '__app_' . static::$application );
				}
			}

			$this->_dataLayerProperties[$index] = \IPS\core\DataLayer::i()->filterProperties( $dataLayer );
		}

		return $this->_dataLayerProperties[$index];
	}
	
	/**
	 * Add the comment form elements
	 *
	 * @return	array
	 */
	public function commentFormElements()
	{
		$commentClass = static::$commentClass;
		$idColumn = static::$databaseColumnId;
		$return   = array();
		$submitted = 'commentform' . '_' . $this->$idColumn . '_submitted';
		
		$self = $this;
		$editorField = new \IPS\Helpers\Form\Editor( static::$formLangPrefix . 'comment' . '_' . $this->$idColumn, NULL, TRUE, array(
			'app'			=> static::$application,
			'key'			=> mb_ucfirst( static::$module ),
			'autoSaveKey' 	=> 'reply-' . static::$application . '/' . static::$module . '-' . $this->$idColumn,
			'minimize'		=> isset( \IPS\Request::i()->$submitted ) ? NULL : static::$formLangPrefix . '_comment_placeholder',
			'contentClass'	=> \get_called_class(),
			'contentId'		=> $this->$idColumn
		), function() use( $self ) {
			if ( !$self->mergeConcurrentComment() )
			{
				\IPS\Helpers\Form::floodCheck();
			}
		} );
		$return['editor'] = $editorField;
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			if ( !$this->canComment( \IPS\Member::loggedIn(), FALSE ) )
			{
				$return['guest_email'] = new \IPS\Helpers\Form\Email( 'guest_email', NULL, TRUE, array( 'accountEmail' => TRUE, 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('comment_guest_email'), 'htmlAutocomplete' => "email" ) );
			}
			else
			{
				if ( isset( $commentClass::$databaseColumnMap['author_name'] ) )
				{
					$return['guest_name'] = new \IPS\Helpers\Form\Text( 'guest_name', NULL, FALSE, array( 'minLength' => \IPS\Settings::i()->min_user_name_length, 'maxLength' => \IPS\Settings::i()->max_user_name_length, 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('comment_guest_name') ) );
				}
			}
			if ( \IPS\Settings::i()->bot_antispam_type !== 'none' and \IPS\Settings::i()->guest_captcha )
			{
				$return['captcha'] = new \IPS\Helpers\Form\Captcha;
			}
		}
		
		$followArea = mb_strtolower( mb_substr( \get_called_class(), mb_strrpos( \get_called_class(), '\\' ) + 1 ) );
	
		/* Add in the "automatically follow" option */
		if ( \in_array( 'IPS\Content\Followable', class_implements( \get_called_class() ) ) and \IPS\Member::loggedIn()->member_id )
		{
			$return['follow'] = new \IPS\Helpers\Form\YesNo( static::$formLangPrefix . 'auto_follow', (bool) ( \IPS\Member::loggedIn()->auto_follow['comments'] or \IPS\Member::loggedIn()->following( static::$application, $followArea, $this->$idColumn ) ), FALSE, array( 'label' => static::$formLangPrefix . 'auto_follow_suffix' ), NULL, NULL, NULL, 'auto_follow_toggle' );
		}

		$container = $this->containerWrapper();
		$member = \IPS\Member::loggedIn();

		if ( \in_array( 'IPS\Content\Hideable', class_implements( $commentClass ) ) and ( static::modPermission( 'hide', $member, $container ) OR $member->group['g_hide_own_posts'] == '1'  ) )
		{
			$return['hide'] = new \IPS\Helpers\Form\YesNo( 'hide', FALSE , FALSE, array( 'label' => 'hide' ) );
		}

		/* Post Anonymously */
		if ( $container and $container->canPostAnonymously( $container::ANON_COMMENTS ) )
		{
			$return['post_anonymously']	= new \IPS\Helpers\Form\YesNo( 'post_anonymously', FALSE, FALSE, array( 'label' => \IPS\Member::loggedIn()->language()->addToStack( 'post_anonymously_suffix' ) ), NULL, NULL, NULL, 'post_anonymously' );
		}

		return $return;
	}
	
	/**
	 * Process the comment form
	 *
	 * @param	array	$values		Array of $form values
	 * @return  \IPS\Content\Comment
	 */
	public function processCommentForm( $values )
	{
		$commentClass = static::$commentClass;
		$idColumn = static::$databaseColumnId;
		$commentIdColumn = $commentClass::$databaseColumnId;
		$followArea = mb_strtolower( mb_substr( \get_called_class(), mb_strrpos( \get_called_class(), '\\' ) + 1 ) );	

		/* Moderator wants to hide the comment */
		if( isset( $values['hide'] ) AND $values['hide'] )
		{
			$hidden = -1;
		}
		else
		{
			$hidden = NULL;
		}

		$comment = $commentClass::create( $this, $values[ static::$formLangPrefix . 'comment' . '_' . $this->$idColumn ], FALSE, isset( $values['guest_name'] ) ? $values['guest_name'] : NULL, NULL, NULL, NULL, NULL, $hidden, ( isset( $values[ 'post_anonymously' ] ) ? (bool) $values[ 'post_anonymously' ] : NULL ) );
		
		/* Auto-follow - If posted anonymously we should set follow to anonymous as well */
		if( isset( $values[ static::$formLangPrefix . 'auto_follow' ] ) )
		{
			if ( $values[ static::$formLangPrefix . 'auto_follow' ] and !\IPS\Member::loggedIn()->following( static::$application, $followArea, $this->$idColumn ) )
			{
				/* Insert */
				$save = array(
					'follow_id'				=> md5( static::$application . ';' . $followArea . ';' . $this->$idColumn . ';' .  \IPS\Member::loggedIn()->member_id ),
					'follow_app'			=> static::$application,
					'follow_area'			=> $followArea,
					'follow_rel_id'			=> $this->$idColumn,
					'follow_member_id'		=> \IPS\Member::loggedIn()->member_id,
					'follow_is_anon'		=> 0,
					'follow_added'			=> time() + 1, // Make sure streams show follows after content is created
					'follow_notify_do'		=> 1,
					'follow_notify_meta'	=> '',
					'follow_notify_freq'	=> \IPS\Member::loggedIn()->auto_follow['method'],
					'follow_notify_sent'	=> 0,
					'follow_visible'		=> 1
				);
			
				\IPS\Db::i()->insert( 'core_follow', $save );
				\IPS\Db::i()->delete( 'core_follow_count_cache', array( 'class=? AND id=?', \get_called_class(), (int) $this->$idColumn ) );
			}
			else if ( $values[ static::$formLangPrefix . 'auto_follow' ] === false AND \IPS\Member::loggedIn()->following( static::$application, $followArea, $this->$idColumn ) )
			{
				\IPS\Db::i()->delete( 'core_follow', array( 'follow_id=?', (string) md5( static::$application . ';' . $followArea . ';' . $this->$idColumn . ';' . \IPS\Member::loggedIn()->member_id ) ) );
				\IPS\Db::i()->delete( 'core_follow_count_cache', array( 'id=? AND class=?', $this->$idColumn, \get_called_class() ) );
			}
		}

		/* Update the search index (note: we already index the comment in Comment::create()) */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			if ( static::$firstCommentRequired and !$comment->isFirst() )
			{
				$commentClass = static::$commentClass;
				
				\IPS\Content\Search\Index::i()->index( $this->firstComment() );
			}
			else
			{
				\IPS\Content\Search\Index::i()->index( $this );
			}
		}
				
		/* Post before registering */
		if ( isset( $values['guest_email'] ) )
		{
			\IPS\Request::i()->setCookie( 'post_before_register', $comment->_logPostBeforeRegistering( $values['guest_email'], isset( \IPS\Request::i()->cookie['post_before_register'] ) ? \IPS\Request::i()->cookie['post_before_register'] : NULL ) );
		}

		return $comment;
	}
	
	/**
	 * Build review form
	 *
	 * @return	string
	 */
	public function reviewForm()
	{
		/* Can we review? */
		if ( $this->canReview() )
		{
			$reviewClass = static::$reviewClass;
			$idColumn = static::$databaseColumnId;
			$reviewIdColumn = static::$databaseColumnId;
			
			$form = new \IPS\Helpers\Form( 'review', 'add_review' );
			$form->class  = 'ipsForm_vertical';
			
			if ( !\IPS\Member::loggedIn()->member_id )
			{
				if ( !$this->canReview( \IPS\Member::loggedIn(), FALSE ) )
				{
					$form->add( new \IPS\Helpers\Form\Email( 'guest_email', NULL, TRUE, array( 'accountEmail' => TRUE, 'htmlAutocomplete' => "email" ) ) );
				}
				else
				{
					if ( isset( $reviewClass::$databaseColumnMap['author_name'] ) )
					{
						$form->add( new \IPS\Helpers\Form\Text( 'guest_name', NULL, FALSE, array( 'minLength' => \IPS\Settings::i()->min_user_name_length, 'maxLength' => \IPS\Settings::i()->max_user_name_length, 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('comment_guest_name') ) ) );
					}
				}
				if ( \IPS\Settings::i()->bot_antispam_type !== 'none' and \IPS\Settings::i()->guest_captcha )
				{
					$form->add( new \IPS\Helpers\Form\Captcha );
				}
			}
			
			$form->add( new \IPS\Helpers\Form\Rating( static::$formLangPrefix . 'rating_value', NULL, TRUE, array( 'max' => \IPS\Settings::i()->reviews_rating_out_of ) ) );
			$editorField = new \IPS\Helpers\Form\Editor( static::$formLangPrefix . 'review_text', NULL, TRUE, array(
				'app'			=> static::$application,
				'key'			=> mb_ucfirst( static::$module ),
				'autoSaveKey' 	=> 'review-' . static::$application . '/' . static::$module . '-' . $this->$idColumn,
				'minimize'		=> static::$formLangPrefix . '_review_placeholder'
			), '\IPS\Helpers\Form::floodCheck' );
			$form->add( $editorField );
			
			if ( $values = $form->values() )
			{
				/* Disable read/write separation */
				\IPS\Db::i()->readWriteSeparation = FALSE;
			
				$currentPageCount = \IPS\Request::i()->currentPage;
				
				unset( $this->reviewpageCount );
								
				$review = $this->processReviewForm( $values );
				
				if ( $review->hidden() === -3 )
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=register', 'front', 'register' ) );
				}
				else
				{
					\IPS\Output::i()->redirect( $review->url(), 'thanks_for_your_review' );
				}
			}
			elseif ( \IPS\Request::i()->isAjax() and $editorField->error )
			{
				\IPS\Output::i()->json( array( 'type' => 'error', 'message' => \IPS\Member::loggedIn()->language()->addToStack( $editorField->error ) ) );
			}

			/* Mod Queue? */
			$return = '';
			$guestPostBeforeRegister = ( !\IPS\Member::loggedIn()->member_id ) ? ( !isset( static::$containerNodeClass ) or ( $container = $this->container() and !$container->can( 'reply', \IPS\Member::loggedIn(), FALSE ) ) ) : NULL;
			$modQueued = static::moderateNewReviews( \IPS\Member::loggedIn(), $guestPostBeforeRegister );
			if ( $guestPostBeforeRegister or $modQueued )
			{
				$return .= \IPS\Theme::i()->getTemplate( 'forms', 'core' )->postingInformation( $guestPostBeforeRegister, $modQueued );
			}			
			$return .= $form->customTemplate( array( \IPS\Theme::i()->getTemplate( $reviewClass::$formTemplate[0][0], $reviewClass::$formTemplate[0][1], $reviewClass::$formTemplate[0][2] ), $reviewClass::$formTemplate[1] ) );
			return $return;
		}
		
		/* Hang on, are we a guest, but if logged in, could comment? */
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			$testUser = new \IPS\Member;
			$testUser->member_group_id = \IPS\Settings::i()->member_group;
			
			if ( $this->canReview( $testUser ) )
			{
				return $this->guestTeaser( TRUE );
			}
		}
		
		/* Nope, just display nothing */
		return '';
	}
	
	/**
	 * Process the review form
	 *
	 * @param	array	$values		Array of $form values
	 * @return  \IPS\Content\Comment
	 */
	public function processReviewForm( $values )
	{
		$reviewClass = static::$reviewClass;
		$idColumn = static::$databaseColumnId;
		$reviewIdColumn = $reviewClass::$databaseColumnId;
		
		$review = $reviewClass::create( $this, $values[ static::$formLangPrefix . 'review_text' ], FALSE, $values[ static::$formLangPrefix . 'rating_value' ], isset( $values['guest_name'] ) ? $values['guest_name'] : NULL );

		$parameters = array_merge( array( 'review-' . static::$application . '/' . static::$module  . '-' . $this->$idColumn ), $review->attachmentIds() );
		\IPS\File::claimAttachments( ...$parameters );
		
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}
		
		if ( isset( $values['guest_email'] ) )
		{
			\IPS\Request::i()->setCookie( 'post_before_register', $review->_logPostBeforeRegistering( $values['guest_email'], isset( \IPS\Request::i()->cookie['post_before_register'] ) ? \IPS\Request::i()->cookie['post_before_register'] : NULL ) );
		}
		
		return $review;
	}
	
	/**
	 * Message explaining to guests that if they log in they can comment
	 *
	 * @param	bool	$isReview	Is this a review form instead of a comment form?
	 * @return	string
	 * @note	April fools joke!
	 */
	public function guestTeaser( $isReview=FALSE )
	{
		return \IPS\Theme::i()->getTemplate( 'global', 'core' )->guestCommentTeaser( $this, $isReview );
	}
	
	/**
	 * Get URL for last comment page
	 *
	 * @return	\IPS\Http\Url
	 */
	public function lastCommentPageUrl()
	{
		$url = $this->url();
		$lastPage = $this->commentPageCount();
		if ( $lastPage != 1 )
		{
			$url = $url->setPage( 'page', $lastPage );
		}
		return $url;
	}
	
	/**
	 * Get URL for last review page
	 *
	 * @return	\IPS\Http\Url
	 */
	public function lastReviewPageUrl()
	{
		$url = $this->url();
		$lastPage = $this->reviewPageCount();
		if ( $lastPage != 1 )
		{
			$url = $url->setPage( 'page', $lastPage );
		}
		return $url;
	}
	
	/**
	 * Can comment?
	 *
	 * @param	\IPS\Member\NULL	$member							The member (NULL for currently logged in member)
	 * @param	bool				$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 */
	public function canComment( $member=NULL, $considerPostBeforeRegistering = TRUE )
	{
		return $this->canCommentReview( 'reply', $member, $considerPostBeforeRegistering );
	}
	
	/**
	 * Can review?
	 *
	 * @param	\IPS\Member\NULL	$member							The member (NULL for currently logged in member)
	 * @param	bool				$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 */
	public function canReview( $member=NULL, $considerPostBeforeRegistering = TRUE )
	{
		return $this->canCommentReview( 'review', $member, $considerPostBeforeRegistering ) and !$this->hasReviewed( $member );
	}

	/**
 	 * @brief	Cache if we have already reviewed this item
 	 */
	protected $_hasReviewed	= NULL;

	/**
	 * Already reviewed?
	 *
	 * @param	\IPS\Member|NULL	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function hasReviewed( $member=NULL )
	{
		$reviewClass = static::$reviewClass;
		$idColumn = static::$databaseColumnId;
		
		$isGuest	= ( $member === NULL and !\IPS\Member::loggedIn()->member_id );
		$member		= $member ?: \IPS\Member::loggedIn();
		
		if( !isset( $this->_hasReviewed[ $member->member_id ] )  )
		{
			/* If guest, check core_post_before_registering */
			if ( $isGuest )
			{
				if ( isset( \IPS\Request::i()->cookie['post_before_register'] ) )
				{
					$this->_hasReviewed[ $member->member_id ] = 0;

					foreach( \IPS\Db::i()->select( '*', 'core_post_before_registering', array( 'class=? AND secret=?', $reviewClass, \IPS\Request::i()->cookie['post_before_register'] ) ) as $pbrWithoutJelly )
					{
						try
						{
							$theJelly	= $pbrWithoutJelly['class']::load( $pbrWithoutJelly['id'] );
							$jellyId	= $theJelly->item()::$databaseColumnId;

							if( $theJelly->item()->$idColumn == $this->$idColumn )
							{
								$this->_hasReviewed[ $member->member_id ]++;
							}
						}
						catch( \OutOfRangeException $e ){}
					}
				}
				else
				{
					$this->_hasReviewed[ $member->member_id ] = FALSE;
				}
			}
			
			/* Otherwise check the DB */
			else
			{
				$where = array();
				$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $this->$idColumn );
				$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['author'] . '=?', $member->member_id );

				if ( \in_array( 'IPS\Content\Hideable', class_implements( $reviewClass ) ) )
				{
					/* Exclude content pending deletion, as it will not be shown inline  */
					if ( isset( $reviewClass::$databaseColumnMap['approved'] ) )
					{
						$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['approved'] . '<>?', -2 );
					}
					elseif( isset( $reviewClass::$databaseColumnMap['hidden'] ) )
					{
						$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['hidden'] . '<>?', -2 );
					}
				}

				$this->_hasReviewed[ $member->member_id ]	= \IPS\Db::i()->select( 'COUNT(*)', $reviewClass::$databaseTable, $where )->first();
				return $this->_hasReviewed[ $member->member_id ];
			}
		}
		
		return $this->_hasReviewed[ $member->member_id ];
	}
	
	/**
	 * Can Comment/Review
	 *
	 * @param	string				$type							Type
	 * @param	\IPS\Member\NULL	$member							The member (NULL for currently logged in member)
	 * @param	bool				$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 */
	protected function canCommentReview( $type, \IPS\Member $member = NULL, $considerPostBeforeRegistering = TRUE )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		/* Are we restricted from posting completely? */
		if ( $member->restrict_post )
		{
			return FALSE;
		}

		/* Future Items can't be commented and reviewed */
		if ( $this->isFutureDate() )
		{
			return FALSE;
		}

		/* Or have an unacknowledged warning? */
		if ( $member->members_bitoptions['unacknowledged_warnings'] )
		{
			return FALSE;
		}

		/* Is this locked? */
		if ( ( $this instanceof \IPS\Content\Lockable and $this->locked() ) or ( $this instanceof \IPS\Content\Polls and $this->getPoll() and $this->getPoll()->poll_only ) )
		{
			if ( !$member->member_id )
			{
				return FALSE;
			}

			return ( static::modPermission( 'reply_to_locked', $member, $this->containerWrapper() ) and $this->can( $type, $member ) );
		}

		/* Check permissions as normal */
		return $this->can( $type, $member, $considerPostBeforeRegistering );
	}
	
	/**
	 * Should new items be moderated?
	 *
	 * @param	\IPS\Member		$member		The member posting
	 * @param	\IPS\Node\Model	$container	The container
	 * @param	bool			$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public static function moderateNewItems( \IPS\Member $member, \IPS\Node\Model $container = NULL, $considerPostBeforeRegistering = FALSE )
	{
		if( static::modPermission( 'approve', $member, $container ) )
		{
			return FALSE;
		}

		return ( \in_array( 'IPS\Content\Hideable', class_implements( \get_called_class() ) ) and $member->moderateNewContent( $considerPostBeforeRegistering ) );
	}
	
	/**
	 * Should new comments be moderated?
	 *
	 * @param	\IPS\Member	$member							The member posting
	 * @param	bool		$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public function moderateNewComments( \IPS\Member $member, $considerPostBeforeRegistering = FALSE )
	{
		$return = ( \in_array( 'IPS\Content\Hideable', class_implements( static::$commentClass ) ) and $member->moderateNewContent( $considerPostBeforeRegistering ) );
		
		if ( $return === FALSE AND static::supportedMetaDataTypes() !== NULL AND \in_array( 'core_ItemModeration', static::supportedMetaDataTypes() ) )
		{
			$return = $this->itemModerationEnabled( $member );
		}
		
		return $return;
	}
	
	/**
	 * Should new reviews be moderated?
	 *
	 * @param	\IPS\Member	$member							The member posting
	 * @param	bool		$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public function moderateNewReviews( \IPS\Member $member, $considerPostBeforeRegistering = FALSE )
	{
		$return = ( \in_array( 'IPS\Content\Hideable', class_implements( static::$reviewClass ) ) and $member->moderateNewContent( $considerPostBeforeRegistering ) );
		
		if ( $return === FALSE AND static::supportedMetaDataTypes() !== NULL AND \in_array( 'core_ItemModeration', static::supportedMetaDataTypes() ) )
		{
			$return = $this->itemModerationEnabled( $member );
		}
		
		return $return;
	}
		
	/**
	 * @brief	Review Ratings submitted by members
	 */
	protected $memberReviewRatings = array();
	
	/**
	 * Review Rating submitted by member
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @return	int|null
	 * @throws	\BadMethodCallException
	 */
	public function memberReviewRating( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( !array_key_exists( $member->member_id, $this->memberReviewRatings ) )
		{
			$reviewClass = static::$reviewClass;
			$idColumn = static::$databaseColumnId;
			
			try
			{
				$where = array();
				$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $this->$idColumn );
				$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['author'] . '=?', $member->member_id );

				if ( \in_array( 'IPS\Content\Hideable', class_implements( $reviewClass ) ) )
				{
					if ( isset( $reviewClass::$databaseColumnMap['approved'] ) )
					{
						$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['approved'] . '=?', 1 );
					}
					elseif ( isset( $reviewClass::$databaseColumnMap['hidden'] ) )
					{
						$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['hidden'] . '=?', 0 );
					}
				}

				$this->memberReviewRatings[ $member->member_id ] = \intval( \IPS\Db::i()->select( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['rating'], $reviewClass::$databaseTable, $where )->first() );
			}
			catch ( \UnderflowException $e )
			{
				$this->memberReviewRatings[ $member->member_id ] = NULL;
			}
		}
		
		return $this->memberReviewRatings[ $member->member_id ];
	}
	
	/**
	 * @brief	Cached calculated average review rating
	 */
	protected $_averageReviewRating = NULL;

	/**
	 * Get average review rating
	 *
	 * @return	int
	 */
	public function averageReviewRating()
	{
		if( $this->_averageReviewRating !== NULL )
		{
			return $this->_averageReviewRating;
		}

		$reviewClass = static::$reviewClass;
		$idColumn = static::$databaseColumnId;
		
		$where = array();
		$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $this->$idColumn );

		if ( \in_array( 'IPS\Content\Hideable', class_implements( $reviewClass ) ) )
		{
			/* Exclude content pending deletion, as it will not be shown inline  */
			if ( isset( $reviewClass::$databaseColumnMap['approved'] ) )
			{
				$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['approved'] . '=1' );
			}
			elseif( isset( $reviewClass::$databaseColumnMap['hidden'] ) )
			{
				$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['hidden'] . '=0' );
			}
		}
		
		$this->_averageReviewRating = round( \IPS\Db::i()->select( 'AVG(' . $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['rating'] . ')', $reviewClass::$databaseTable, $where )->first(), 1 );
		
		return $this->_averageReviewRating;
	}
	
	/**
	 * @brief	Cached last commenter
	 */
	protected $_lastCommenter	= NULL;

	/**
	 * Get last comment author
	 *
	 * @return	\IPS\Member
	 * @throws	\BadMethodCallException
	 */
	public function lastCommenter()
	{
		if ( !isset( static::$commentClass ) )
		{
			throw new \BadMethodCallException;
		}

		if( $this->_lastCommenter === NULL )
		{
			if ( isset( static::$databaseColumnMap['last_comment_by'] ) )
			{
				$this->_lastCommenter	= \IPS\Member::load( $this->mapped('last_comment_by') );
				
				if ( ! $this->_lastCommenter->member_id and isset( static::$databaseColumnMap['is_anon'] ) )
				{
					$_lastComment = $this->comments( 1, 0, 'date', 'desc' );
					if ( $_lastComment !== NULL AND $_lastComment->isAnonymous() )
					{
						$this->_lastCommenter->name = \IPS\Member::loggedIn()->language()->addToStack( "post_anonymously_placename" );	
					}
				}
				else if ( ! $this->_lastCommenter->member_id and isset( static::$databaseColumnMap['last_comment_name'] ) )
				{
					if ( $this->mapped('last_comment_name') )
					{
						/* A bug in 4.0.0 - 4.0.5 allowed the md5 hash of the word 'Guest' to be stored' */
						if ( ! preg_match( '#^[0-9a-f]{32}$#', $this->mapped('last_comment_name') ) )
						{
							$this->_lastCommenter->name = $this->mapped('last_comment_name');
						}
					}
				}
			}
			else
			{
				$_lastComment = $this->comments( 1, 0, 'date', 'desc' );

				if( $_lastComment !== NULL )
				{
					if ( $_lastComment->isAnonymous() )
					{
						$this->_lastCommenter	= new \IPS\Member;
						$this->_lastCommenter->name = \IPS\Member::loggedIn()->language()->addToStack( "post_anonymously_placename" );
					}
					else
					{
						$this->_lastCommenter	= $this->comments( 1, 0, 'date', 'desc' )->author();
					}	
				}
				else
				{
					$this->_lastCommenter	= new \IPS\Member;
				}
			}
		}

		return $this->_lastCommenter;
	}

	/**
	 * Resync the comments/unapproved comment counts
	 *
	 * @param	string	$commentClass	Override comment class to use
	 * @return void
	 */
	public function resyncCommentCounts( $commentClass=NULL )
	{
		if( !isset( static::$commentClass ) )
		{
			return;
		}

		$idColumn     = static::$databaseColumnId;
		$commentClass = $commentClass ?: static::$commentClass;

		/* Number of comments */
		if ( isset( static::$databaseColumnMap['num_comments'] ) )
		{
			$where = array( array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );
			
			if ( \in_array( 'IPS\Content\Hideable', class_implements( $commentClass ) ) )
			{
				if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
				{
					$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '=?', 1 );
				}
				elseif ( isset( $commentClass::$databaseColumnMap['hidden'] ) )
				{
					$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . ' IN( 0, 2 )' ); # 2 means the parent is hidden but the post itself is not
				}
			}

			if ( $commentClass::commentWhere() !== NULL )
			{
				$where[] = $commentClass::commentWhere();
			}

			$numCommentsField        = static::$databaseColumnMap['num_comments'];
			$this->$numCommentsField = \IPS\Db::i()->select( 'COUNT(*)', $commentClass::$databaseTable, $where, NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		}
		if ( isset( static::$databaseColumnMap['unapproved_comments'] ) )
		{
			$where = array( array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );

			if ( \in_array( 'IPS\Content\Hideable', class_implements( $commentClass ) ) )
			{
				if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
				{
					$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '=?', 0 );
				}
				elseif ( isset( $commentClass::$databaseColumnMap['hidden'] ) )
				{
					$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '=?', 1 );
				}
			}

			if ( $commentClass::commentWhere() !== NULL )
			{
				$where[] = $commentClass::commentWhere();
			}

			$numUnapprovedCommentsField        = static::$databaseColumnMap['unapproved_comments'];
			$this->$numUnapprovedCommentsField = \IPS\Db::i()->select( 'COUNT(*)', $commentClass::$databaseTable, $where, NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		}
		if ( isset( static::$databaseColumnMap['hidden_comments'] ) )
		{
			$where = array( array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );
			
			if ( \in_array( 'IPS\Content\Hideable', class_implements( $commentClass ) ) )
			{
				if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
				{
					$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '=?', -1 );
				}
				elseif ( isset( $commentClass::$databaseColumnMap['hidden'] ) )
				{
					$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '=?', -1 );
				}
			}
			
			if ( $commentClass::commentWhere() !== NULL )
			{
				$where[] = $commentClass::commentWhere();
			}
			
			$numHiddenCommentsField			= static::$databaseColumnMap['hidden_comments'];
			$this->$numHiddenCommentsField	= \IPS\Db::i()->select( 'COUNT(*)', $commentClass::$databaseTable, $where, NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		}
	}

	/**
	 * Resync the hidden/approved/unapproved review counts
	 *
	 * @return void
	 */
	public function resyncReviewCounts()
	{
		if( !isset( static::$reviewClass ) )
		{
			return;
		}

		$idColumn		= static::$databaseColumnId;
		$reviewClass	= static::$reviewClass;

		/* Number of reviews */
		if ( isset( static::$databaseColumnMap['num_reviews'] ) )
		{
			$where = array( array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );
			
			if ( \in_array( 'IPS\Content\Hideable', class_implements( $reviewClass ) ) )
			{
				if ( isset( $reviewClass::$databaseColumnMap['approved'] ) )
				{
					$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['approved'] . '=?', 1 );
				}
				elseif ( isset( $reviewClass::$databaseColumnMap['hidden'] ) )
				{
					$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['hidden'] . ' IN( 0, 2 )' ); # 2 means the parent is hidden but the post itself is not
				}
			}

			if ( $reviewClass::commentWhere() !== NULL )
			{
				$where[] = $reviewClass::commentWhere();
			}

			$numCommentsField        = static::$databaseColumnMap['num_reviews'];
			$this->$numCommentsField = \IPS\Db::i()->select( 'COUNT(*)', $reviewClass::$databaseTable, $where )->first();
		}

		/* Number of unapproved reviews */
		if ( isset( static::$databaseColumnMap['unapproved_reviews'] ) )
		{
			$where = array( array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );

			if ( \in_array( 'IPS\Content\Hideable', class_implements( $reviewClass ) ) )
			{
				if ( isset( $reviewClass::$databaseColumnMap['approved'] ) )
				{
					$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['approved'] . '=?', 0 );
				}
				elseif ( isset( $reviewClass::$databaseColumnMap['hidden'] ) )
				{
					$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['hidden'] . '=?', 1 );
				}
			}

			$numUnapprovedCommentsField        = static::$databaseColumnMap['unapproved_reviews'];
			$this->$numUnapprovedCommentsField = \IPS\Db::i()->select( 'COUNT(*)', $reviewClass::$databaseTable, $where )->first();
		}

		/* Number of hidden reviews */
		if ( isset( static::$databaseColumnMap['hidden_reviews'] ) )
		{
			$where = array( array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ) );
			
			if ( \in_array( 'IPS\Content\Hideable', class_implements( $reviewClass ) ) )
			{
				if ( isset( $reviewClass::$databaseColumnMap['approved'] ) )
				{
					$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['approved'] . '=?', -1 );
				}
				elseif ( isset( $reviewClass::$databaseColumnMap['hidden'] ) )
				{
					$where[] = array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['hidden'] . '=?', -1 );
				}
			}

			$numHiddenCommentsField			= static::$databaseColumnMap['hidden_reviews'];
			$this->$numHiddenCommentsField	= \IPS\Db::i()->select( 'COUNT(*)', $reviewClass::$databaseTable, $where )->first();
		}
	}
		
	/**
	 * Resync last comment
	 *
	 * @return	void
	 */
	public function resyncLastComment()
	{
		if( !isset( static::$commentClass ) )
		{
			return;
		}

		$columns = array( 'last_comment', 'last_comment_by', 'last_comment_name', 'last_comment_anon' );
		$resync = FALSE;
		foreach ( $columns as $k )
		{
			if ( isset( static::$databaseColumnMap[ $k ] ) )
			{
				$resync = TRUE;
			}
		}
		
		if ( $resync )
		{
			$existingFlag = static::$useWriteServer;
			static::$useWriteServer = TRUE;

			try
			{
				$comment = $this->comments( 1, 0, 'date', 'desc', NULL, FALSE, NULL, NULL, TRUE );
				if ( !$comment )
				{
					throw new \UnderflowException;
				}
				
				if ( isset( static::$databaseColumnMap['last_comment'] ) )
				{
					$lastCommentField = static::$databaseColumnMap['last_comment'];
					if ( \is_array( $lastCommentField ) )
					{
						foreach ( $lastCommentField as $column )
						{
							$this->$column = $comment->mapped('date');
						}
					}
					else
					{
						if ( !\is_null( $comment ) )
						{
							$this->$lastCommentField = $comment->mapped('date');
						}
						else
						{
							$this->$lastCommentField = $this->date;
						}
					}
				}
				if ( isset( static::$databaseColumnMap['last_comment_by'] ) )
				{
					$lastCommentByField = static::$databaseColumnMap['last_comment_by'];
					$this->$lastCommentByField = (int) $comment->author()->member_id;
				}
				if ( isset( static::$databaseColumnMap['last_comment_name'] ) )
				{
					$lastCommentNameField = static::$databaseColumnMap['last_comment_name'];
					$this->$lastCommentNameField = ( !$comment->author()->member_id and isset( $comment::$databaseColumnMap['author_name'] ) ) ? $comment->mapped('author_name') : $comment->author()->name;
				}
				if ( isset( static::$databaseColumnMap['last_comment_anon'] ) )
				{
					$lastCommentAnonField = static::$databaseColumnMap['last_comment_anon'];
					$this->$lastCommentAnonField = (int) $comment->isAnonymous();
				}
			}
			catch ( \UnderflowException $e )
			{
				foreach ( $columns as $c )
				{
					if ( $c === 'last_comment' and isset( static::$databaseColumnMap['last_comment'] ) and \is_array( static::$databaseColumnMap['last_comment'] ) )
					{
						$lastCommentField = static::$databaseColumnMap['last_comment'];
						if ( \is_array( $lastCommentField ) )
						{
							foreach ( $lastCommentField as $col )
							{
								$this->$col = 0;
							}
						}
					}
					else if ( $c === 'last_comment' and isset( static::$databaseColumnMap['last_comment'] ) )
					{
						$field        = static::$databaseColumnMap[$c];
						$this->$field = 0;
					}
					else if( $c === 'last_comment_by' AND isset( static::$databaseColumnMap['last_comment_by'] ) )
					{
						$field        = static::$databaseColumnMap[$c];
						$this->$field = 0;
					}
					else if( $c === 'last_comment_anon' AND isset( static::$databaseColumnMap['last_comment_anon'] ) )
					{
						$field        = static::$databaseColumnMap[$c];
						$this->$field = 0;
					}
					else
					{
						if ( isset( static::$databaseColumnMap[$c] ) )
						{
							$field        = static::$databaseColumnMap[$c];
							$this->$field = NULL;
						}
					}
				}
			}

			static::$useWriteServer = $existingFlag;
		}
	}
	
	/**
	 * Resync last review
	 *
	 * @return	void
	 */
	public function resyncLastReview()
	{
		if( !isset( static::$reviewClass ) )
		{
			return;
		}

		$columns = array( 'last_review', 'last_review_by', 'last_review_name' );
		$resync = FALSE;
		foreach ( $columns as $k )
		{
			if ( isset( static::$databaseColumnMap[ $k ] ) )
			{
				$resync = TRUE;
			}
		}
		
		if ( $resync )
		{
			$existingFlag = static::$useWriteServer;
			static::$useWriteServer = TRUE;

			try
			{
				$review = $this->reviews( 1, 0, 'date', 'desc', NULL, FALSE );
				
				if ( isset( static::$databaseColumnMap['last_review'] ) )
				{
					$lastReviewField = static::$databaseColumnMap['last_review'];
					if ( \is_array( $lastReviewField ) )
					{
						foreach ( $lastReviewField as $column )
						{
							$this->$column = $review->mapped('date');
						}
					}
					else
					{
						if ( !\is_null( $review ) )
						{
							$this->$lastReviewField = $review->mapped('date');
						}
						else
						{
							$this->$lastReviewField = $this->date;
						}
					}
				}
				if ( isset( static::$databaseColumnMap['last_review_by'] ) )
				{
					$lastReviewByField = static::$databaseColumnMap['last_review_by'];
					$this->$lastReviewByField = ( \is_null( $review ) ? NULL : $review->author()->member_id );
				}
				if ( isset( static::$databaseColumnMap['last_review_name'] ) )
				{
					$lastReviewNameField = static::$databaseColumnMap['last_review_name'];
					$this->$lastReviewNameField = ( \is_null( $review ) ? NULL : ( ( !$review->author()->member_id and isset( $review::$databaseColumnMap['author_name'] ) ) ? $review->mapped('author_name') : $review->author()->name ) );
				}
			}
			catch ( \UnderflowException $e )
			{
				if ( \is_array( $columns ) )
				{
					foreach ( $columns as $c )
					{
						if ( isset( static::$databaseColumnMap[ $c ] ) )
						{
							$field = static::$databaseColumnMap[ $c ];
							$this->$field = NULL;
						}
					}
				}
				else
				{
					if ( isset( static::$databaseColumnMap[ $column ] ) )
					{
						$field = static::$databaseColumnMap[ $column ];
						$this->$field = NULL;
					}
				}
			}

			static::$useWriteServer = $existingFlag;
		}
	}
	
	/**
	 * @brief	Item counts
	 */
	protected static $itemCounts = array();
	
	/**
	 * @brief	Comment counts
	 */
	protected static $commentCounts = array();
	
	/**
	 * @brief	Review counts
	 */
	protected static $reviewCounts = array();
	
	/**
	 * Total item \count(including children)
	 *
	 * @param	\IPS\Node\Model	$container			The container
	 * @param	bool			$includeItems		If TRUE, items will be included (this should usually be true)
	 * @param	bool			$includeComments	If TRUE, comments will be included
	 * @param	bool			$includeReviews		If TRUE, reviews will be included
	 * @param	int				$depth				Used to keep track of current depth to avoid going too deep
	 * @return	int|NULL|string	When depth exceeds 10, will return "NULL" and initial call will return something like "100+"
	 * @note	This method may return something like "100+" if it has lots of children to avoid exahusting memory. It is intended only for display use
	 * @note	This method includes counts of hidden and unapproved content items as well
	 */
	public static function contentCount( \IPS\Node\Model $container, $includeItems=TRUE, $includeComments=FALSE, $includeReviews=FALSE, $depth=0 )
	{
		/* Are we in too deep? */
		if ( $depth > 3 )
		{
			return '+';
		}

		/* Generate a key */
		$_key	= md5( \get_class( $container ) . $container->_id );
		
		/* Count items */
		$count = 0;
		if( $includeItems )
		{
			if ( $container->_items === NULL )
			{
				if ( !isset( static::$itemCounts[ $_key ] ) )
				{
					$_count = static::getItemsWithPermission( array( array( static::$databasePrefix . static::$databaseColumnMap['container'] . '=?', $container->_id ) ), NULL, 1, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, NULL, FALSE, FALSE, FALSE, TRUE );

					$_key = md5( \get_class( $container ) . $container->_id );
					static::$itemCounts[ $_key ][ $container->_id ] = $_count;
				}

				if ( isset( static::$itemCounts[ $_key ][ $container->_id ] ) )
				{
					$count += static::$itemCounts[ $_key ][ $container->_id ];
				}
			}
			else
			{
				$count += $container->_items;
			}
		}

		/* Count comments */
		if ( $includeComments )
		{
			if ( $container->_comments === NULL )
			{
				if ( !isset( static::$commentCounts ) )
				{
					$commentClass = static::$commentClass;
					static::$commentCounts[ $_key ] = iterator_to_array( \IPS\Db::i()->select(
						'COUNT(*) AS count, ' . static::$databasePrefix . static::$databaseColumnMap['container'],
						$commentClass::$databaseTable,
						NULL,
						NULL,
						NULL,
						static::$databasePrefix . static::$databaseColumnMap['container']
					)->join( static::$databaseTable, $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=' . static::$databasePrefix . static::$databaseColumnId )
					->setKeyField( static::$databasePrefix . static::$databaseColumnMap['container'] )
					->setValueField('count') );
				}
				
				if ( isset( static::$commentCounts[ $_key ][ $container->_id ] ) )
				{
					$count += static::$commentCounts[ $_key ][ $container->_id ];
				}
			}
			else
			{
				$count += $container->_comments;
			}
		}
		
		/* Count Reviews */
		if ( $includeReviews )
		{
			if ( $container->_reviews === NULL )
			{
				if ( !isset( static::$reviewCounts ) )
				{
					$reviewClass = static::$commentClass;
					static::$reviewCounts[ $_key ] = iterator_to_array( \IPS\Db::i()->select(
						'COUNT(*) AS count, ' . static::$databasePrefix . static::$databaseColumnMap['container'],
						$reviewClass::$databaseTable,
						NULL,
						NULL,
						NULL,
						static::$databasePrefix . static::$databaseColumnMap['container']
					)->join( static::$databaseTable, $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=' . static::$databasePrefix . static::$databaseColumnId )
					->setKeyField( static::$databasePrefix . static::$databaseColumnMap['container'] )
					->setValueField('count') );
				}

				if ( isset( static::$reviewCounts[ $_key ][ $container->_id ] ) )
				{
					$count += static::$reviewCounts[ $_key ][ $container->_id ];
				}
			}
			else
			{
				$count += $container->_reviews;
			}
		}
		
		/* Add Children */
		$childDepth	= $depth++;
		foreach ( $container->children() as $child )
		{
			$toAdd = static::contentCount( $child, $includeItems, $includeComments, $includeReviews, $childDepth );
			if ( \is_string( $toAdd ) )
			{
				return $count . '+';
			}
			else
			{
				$count += $toAdd;
			}
			
		}
		return $count;
	}
	
	/**
	 * @brief	Actions to show in comment multi-mod
	 * @see		\IPS\Content\Item::commentMultimodActions()
	 */
	protected $_commentMultiModActions;
	
	/**
	 * @brief	Actions to show in review multi-mod
	 * @see		\IPS\Content\Item::reviewMultimodActions()
	 */
	protected $_reviewMultiModActions;
	
	/**
	 * Actions to show in comment multi-mod
	 *
	 * @param	\IPS\Member	$member	Member (NULL for currently logged in member)
	 * @return	array
	 */
	public function commentMultimodActions( \IPS\Member $member = NULL )
	{
		if ( $this->_commentMultiModActions === NULL )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			$this->_commentMultiModActions = array();
			if ( isset( static::$commentClass ) )
			{
				$this->_commentMultiModActions = $this->_commentReviewMultimodActions( static::$commentClass, $member );
			}
		}
		
		return $this->_commentMultiModActions;
	}
	
	/**
	 * Actions to show in review multi-mod
	 *
	 * @param	\IPS\Member	$member	Member (NULL for currently logged in member)
	 * @return	array
	 */
	public function reviewMultimodActions( \IPS\Member $member = NULL )
	{
		if ( $this->_reviewMultiModActions === NULL )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			$this->_reviewMultiModActions = array();
			if ( isset( static::$reviewClass ) )
			{
				$this->_reviewMultiModActions = $this->_commentReviewMultimodActions( static::$reviewClass, $member );
			}
		}
		
		return $this->_reviewMultiModActions;
	}
	
	/**
	 * Actions to show in comment/review multi-mod
	 *
	 * @param	string		$class 	The class
	 * @param	\IPS\Member	$member	Member (NULL for currently logged in member)
	 * @return	array
	 */
	protected function _commentReviewMultimodActions( $class, \IPS\Member $member )
	{
		$itemClass = $class::$itemClass;

		$return = array();
		$check = array();
		$check[] = 'split_merge';
		if ( \in_array( 'IPS\Content\Hideable', class_implements( $class ) ) )
		{
			$check[] = 'approve';
			$check[] = 'hide';
			$check[] = 'unhide';
		}
		$check[] = 'delete';
		
		foreach ( $check as $k )
		{
			if ( $k == 'split_merge' )
			{
				if( $itemClass::modPermission( $k, $member, $this->containerWrapper() ) )
				{
					$return[] = $k;
				}
			}
			else
			{
				if( $class::modPermission( $k, $member, $this->containerWrapper() ) )
				{
					$return[] = $k;
				}
			}
		}
		
		return $return;
	}
	
	/**
	 * Generate meta data between comments. It is assumed they all belong to the same topic
	 *
	 * @param 	array	$comments	Array of $comment objects
	 * @param	bool	$anonymous	Anonymize the moderator actions
	 * @return	array
	 */
	public function generateCommentMetaData( array $comments, $anonymous = FALSE ) : array
	{
		if ( ! $this->showCommentMeta('time') and ! $this->showCommentMeta('moderation') )
		{
		    return $comments;
        }

        $showAnonymous = FALSE; 

        if( $anonymous )
        {
        	$containerClass = static::$containerNodeClass;

        	if( isset( $containerClass::$modPerm ) AND $containerClass::$modPerm )
        	{
        		$showAnonymous = !(
					\IPS\Member::loggedIn()->modPermission( $containerClass::$modPerm ) === TRUE 
					or
					\IPS\Member::loggedIn()->modPermission( $containerClass::$modPerm ) === -1
					or
					(
						\is_array( \IPS\Member::loggedIn()->modPermission( $containerClass::$modPerm ) )
						and
						\in_array( $this->container()->_id, \IPS\Member::loggedIn()->modPermission( $containerClass::$modPerm ) )
					)
				);
        	}
        	else
        	{
        		$showAnonymous = TRUE;
        	}
        }


        $anonymous ? (bool) \IPS\Member::loggedIn()->modPermissions() : FALSE;
		
		$lastDate = NULL;
		
		/* Get moderation items */
		$idColumn = static::$databaseColumnId;
		$moderationItems = array();

		if ( $this->showCommentMeta('moderation') )
		{
			foreach ( \IPS\Db::i()->select( '*', 'core_moderator_logs', array('class=? AND item_id=?', \get_class( $this ), $this->$idColumn), 'ctime ASC' ) as $row )
			{
				if ( \in_array( $row['lang_key'], array('modlog__action_unfeature', 'modlog__action_feature', 'modlog__action_unlock', 'modlog__action_lock', 'modlog__action_unpin', 'modlog__action_pin', 'modlog__item_edit', 'modlog__comment_edit_title') ) )
				{
					$moderationItems[] = $row;
				}
			}
		}

		foreach( $comments as $id => &$comment )
		{
			$next = next( $comments );
			$commentDate = $comment->mapped( 'date' );
			$nextCommentDate = ( $next ) ? $next->mapped( 'date' ) : 0;

			if ( $this->showCommentMeta('moderation') )
			{
				$otherActions = array();
				$currentActionGroup = array();
				$lastActionDate = NULL;
				$lastActionMember = NULL;

				foreach ( $moderationItems as $id => $data )
				{
					$modActionDate = $data['ctime'];

					if ( ( $modActionDate > $commentDate and $modActionDate < $nextCommentDate ) or ( $modActionDate > $commentDate and !$next )  )
					{
						$commentDate = $modActionDate;
						$blurb = NULL;
						$langKey = 'comment_meta_moderation_' . ( $showAnonymous ? 'anon_' : '' ) . $data['lang_key'];

						/* Edits always get their own meta action bubble, so handle those individually */
						if ( \in_array( $data['lang_key'], array( 'modlog__item_edit', 'modlog__comment_edit_title' ) ) )
						{
							$note = array_keys( json_decode( $data['note'], TRUE ) );
							
							if( ( \count( $note ) == 4 && $data['lang_key'] == 'modlog__item_edit' ) || ( \count( $note ) == 3 && $data['lang_key'] == 'modlog__comment_edit_title' ) )
							{
								$oldTitle = array_pop( $note );
								$newTitle = array_pop( $note );

								if ( $oldTitle )
								{
									$blurb = \IPS\Member::loggedIn()->language()->addToStack( $langKey, FALSE, array('htmlsprintf' => array(\IPS\Member::load( $data['member_id'] )->link()), 'sprintf' => array( $newTitle ) ) );

									$comment->_data['metaData']['comment']['moderation'][$data['lang_key']] = array(
										'row' => $data,
										'blurb' => $blurb,
										'action' => $data['lang_key']
									);
								}
							}
						}
						else
						{
							/* Other actions like pinned, featured etc. get combined into one bubble if they are close together, so keep track of those here 
							   If this action occurred more than 10 minutes after the last, start a new group. Otherwise, append to previous group.
							   We want to end up with a structure like this, assuming pinned and featured were within 10 mins but lock happened later:
							   
								array (
									0 => array( 0 => [pinned data], 1 => [featured data] ),
									1 => array( 0 => [locked data] )
								); 
							*/						
							if( \count( $currentActionGroup) && ( $modActionDate - $lastActionDate > 600 || $data['member_id'] !== $lastActionMember ) ){
								$otherActions[] = $currentActionGroup;
								$currentActionGroup = array();
							}

							$currentActionGroup[] = array(
								'row' => $data,
								'action' => $data['lang_key'],
								'lang_key' => $langKey
							);

							$lastActionDate = $modActionDate;	
							$lastActionMember = $data['member_id'];
						}

						unset( $moderationItems[$id] );
					}
				}

				/* Push any remaining actions into otherActions */
				if( \count( $currentActionGroup ) )
				{
					$otherActions[] = $currentActionGroup;
				}

				/* Process any other actions */
				if( \count( $otherActions ) )
				{
					foreach( $otherActions as $groupIdx => $actions )
					{
						if( \count( $actions ) === 1 )
						{
							/* If this is just one action, generate a full bubble */
							$comment->_data['metaData']['comment']['moderation'][$actions[0]['action']] = array(
								'row' => $actions[0]['row'],
								'blurb' => \IPS\Member::loggedIn()->language()->addToStack( $actions[0]['lang_key'], FALSE, array('htmlsprintf' => array($this->definiteArticle(), \IPS\Member::load( $actions[0]['row']['member_id'] )->link())) ),
								'action' => $actions[0]['lang_key']
							);
						}
						else
						{
							$actionPhrases = array_map( 
								function ($_action) {
									return \IPS\Member::loggedIn()->language()->addToStack( 'comment_meta_moderation_' . $_action['action'] . '_short' );
								},
								$actions
							);

							$comment->_data['metaData']['comment']['moderation'][$groupIdx] = array(
								'row' => $actions[0]['row'], // Use the first action in this group as the 'row', to provide the time etc. for all items in this bubble
								'blurb' => \IPS\Member::loggedIn()->language()->addToStack( 'comment_meta_moderation_'  . ( $showAnonymous ? 'anon_' : '' ) . 'modlog__action_group', FALSE, array('htmlsprintf' => array($this->definiteArticle(), \IPS\Member::load( $lastActionMember )->link(), \IPS\Member::loggedIn()->language()->formatList( $actionPhrases ) ) ) ),
								'action' => $actions[0]['lang_key']
							);
						}
					}
				}
			}

			$date = \IPS\DateTime::ts( $commentDate );
			$nextDate = ( $next ) ? \IPS\DateTime::ts( $nextCommentDate ) : NULL;

			if ( $this->showCommentMeta('time') )
			{
				if ( $nextDate instanceOf \IPS\DateTime and $nextDate->diff( $date )->days > 7 )
				{
					$blurb = NULL;

					/* Years? */
					if ( $nextDate->diff( $date )->y > 0 )
					{
						$blurb = \IPS\Member::loggedIn()->language()->pluralize( \IPS\Member::loggedIn()->language()->get( 'comment_meta_date_years_later' ), array($nextDate->diff( $date )->y) );
					}
					else if ( $nextDate->diff( $date )->m > 0 )
					{
						$blurb = \IPS\Member::loggedIn()->language()->pluralize( \IPS\Member::loggedIn()->language()->get( 'comment_meta_date_months_later' ), array($nextDate->diff( $date )->m) );
					}
					else if ( $nextDate->diff( $date )->days > 7 )
					{
						$blurb = \IPS\Member::loggedIn()->language()->pluralize( \IPS\Member::loggedIn()->language()->get( 'comment_meta_date_weeks_later' ), array(ceil( $nextDate->diff( $date )->days / 7 )) );
					}

					$comment->_data['metaData']['comment']['timeGap'] = array(
						'days' => $nextDate->diff( $date )->days,
						'blurb' => $blurb
					);
				}
			}
		}
		
		return $comments;
	}

	/**
	 * @brief Setting name for show_meta
	 */
	public static $showMetaSettingKey = NULL;

	/**
	 * Show the topic meta?
	 *
	 * @param	$key	string		Key to check (time, moderation)
	 * @return boolean
	 */
	public function showCommentMeta( $key )
	{
		if( $key == 'moderation' AND \IPS\Member::loggedIn()->group['gbw_hide_inline_modevents'] )
		{
			return FALSE;
		}

		$metaKey = static::$showMetaSettingKey;

		if ( isset( \IPS\Settings::i()->$metaKey ) and \IPS\Settings::i()->$metaKey )
		{
			$meta = json_decode( \IPS\Settings::i()->$metaKey, TRUE );
			if ( $meta and \in_array( $key, $meta ) )
			{
				return TRUE;
			}
		}

		return FALSE;
	}
	
	/**
	 * Get table showing moderation actions
	 *
	 * @return	\IPS\Helpers\Table\Db
	 * @throws	\DomainException
	 */
	public function moderationTable()
	{
		if( !\IPS\Member::loggedIn()->modPermission('can_view_moderation_log') )
		{
			throw new \DomainException;
		}
		
		$idColumn = static::$databaseColumnId;
		$where = array( 'class=? AND item_id=?', \get_class( $this ), $this->$idColumn );
	
		$table = new \IPS\Helpers\Table\Db( 'core_moderator_logs', $this->url( 'modLog' ), $where );
		$table->langPrefix = 'modlogs_';
		$table->include = array( 'member_id', 'action', 'ip_address', 'ctime' );
		$table->mainColumn = 'action';
		/* Because this is shown in a modal, limit the number of results per page */
		$table->limit = 10;
		
		$table->tableTemplate	= array( \IPS\Theme::i()->getTemplate( 'moderationLog', 'core' ), 'table' );
		$table->rowsTemplate	= array( \IPS\Theme::i()->getTemplate( 'moderationLog', 'core' ), 'rows' );
		
		$table->parsers = array(
				'action'	=> function( $val, $row )
				{
					if ( $row['lang_key'] )
					{
						$langKey = $row['lang_key'];
						$params = array();
						foreach ( json_decode( $row['note'], TRUE ) as $k => $v )
						{
							$params[] = $v ? \IPS\Member::loggedIn()->language()->addToStack( $k ) : $k;
						}

						return \IPS\Member::loggedIn()->language()->addToStack( $langKey, FALSE, array( 'sprintf' => $params ) );
					}
					else
					{
						return $row['note'];
					}
				}
		);
		$table->sortBy = $table->sortBy ?: 'ctime';
		$table->sortDirection = $table->sortDirection ?: 'desc';

		if( !\IPS\Request::i()->isAjax() )
		{
			return \IPS\Theme::i()->getTemplate( 'tables', 'core' )->container( (string) $table );	
		}
		else
		{
			return (string) $table;
		}
	}
			
	/* !Permissions */
	
	/**
	 * Get permission index ID
	 *
	 * @return	int|NULL
	 */
	public function permId()
	{
		if ( $this instanceof \IPS\Content\Permissions )
		{
			$permissions = $this->container()->permissions();
			return $permissions['perm_id'];
		}
		
		return NULL;
	}
	
	/**
	 * Check permissions
	 *
	 * @param	mixed								$permission						A key which has a value in the permission map (either of the container or of this class) matching a column ID in core_permission_index
	 * @param	\IPS\Member|\IPS\Member\Group|NULL	$member							The member or group to check (NULL for currently logged in member)
	 * @param	bool								$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 * @throws	\OutOfBoundsException	If $permission does not exist in map
	 */
	public function can( $permission, $member=NULL, $considerPostBeforeRegistering=TRUE )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* If the member is banned they can't do anything? */
		if ( ! ( $member instanceof \IPS\Member\Group ) and ! $member->group['g_view_board'] )
		{
			return FALSE;
		}

		/* Node-related permissions... */
		if ( $this instanceof \IPS\Content\Permissions )
		{
			/* If we can find the node... */
			try
			{
				/* Check with the node if we can do what we're trying to do */
				if( !$this->container()->can( $permission, $member, $considerPostBeforeRegistering ) )
				{
					return FALSE;
				}
				
				/* If we're trying to *read* a content item (or in fact anything, but we only check read since if we managed to access it we don't need to check this again for other permissions),
				   check if we can *view* (i.e. access) all of the parents. This is so if an admin, for example, removes a group's permission to view (i.e. access) a node, they will not be able
				   to access content within it. Though this is not in line with conventional ACL practices, it is how the suite has always worked and we don't want to mess up permissions for upgrades  */
				if ( $permission === 'read' )
				{
					foreach( $this->container()->parents() as $parent )
					{
						if( !$parent->can( 'view', $member ) )
						{
							return FALSE;
						}
					}
				}
			}
			/* If the node has been lost, assume we can do nothing */
			catch ( \OutOfRangeException $e )
			{
				return FALSE;
			}
		}
		
		/* Still here? It must be okay */
		return TRUE;
	}
	
	/**
	 * Can view?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for or NULL for the currently logged in member
	 * @return	bool
	 */
	public function canView( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
				
		/* Check it isn't hidden */
		if ( $this instanceof \IPS\Content\Hideable and $this->hidden() )
		{
			/* If we're a moderator who can see hidden items it's fine, unless it's a guest post before register */
			if ( $this->hidden() !== -3 and static::canViewHiddenItems( $member, $this->containerWrapper() ) )
			{
				// OK
			}
			/* If it's pending approval, and we're the author it's fine */
			elseif ( $this->hidden() === 1 and $this->author()->member_id and $this->author()->member_id == $member->member_id )
			{
				// OK
			}
			/* Otherwise hidden content can't be viewed */
			else
			{
				return FALSE;
			}
		}
		
		/* Check it isn't set to be published in the future */
		if ( $this instanceof \IPS\Content\FuturePublishing )
		{
			$future = static::$databaseColumnMap['is_future_entry'];

			if ( $this->$future == 1 AND ( !static::canViewFutureItems( $member, $this->containerWrapper() ) AND $this->author()->member_id != $member->member_id ) )
			{
				return FALSE;
			}
		}
		
		/* Check node */
		return $this->can( 'read', $member );
	}
	
	/**
	 * Search Index Permissions
	 *
	 * @return	string	Comma-delimited values or '*'
	 * 	@li			Number indicates a group
	 *	@li			Number prepended by "m" indicates a member
	 *	@li			Number prepended by "s" indicates a social group
	 */
	public function searchIndexPermissions()
	{
		try
		{
			return $this->container()->searchIndexPermissions();
		}
		catch ( \BadMethodCallException $e )
		{
			return '*';
		}
	}
	
	/**
	 * Deletion log Permissions
	 * Usually, this is the same as searchIndexPermissions. However, some applications may restrict searching but
	 * still want to allow delayed deletion log viewing and searching
	 *
	 * @return	string	Comma-delimited values or '*'
	 * 	@li			Number indicates a group
	 *	@li			Number prepended by "m" indicates a member
	 *	@li			Number prepended by "s" indicates a social group
	 */
	public function deleteLogPermissions()
	{
		try
		{
			return $this->container()->deleteLogPermissions();
		}
		/* The container may not exist */
		catch( \BadMethodCallException | \OutOfRangeException $e )
		{
			return '';
		}
	}
	
	/**
	 * Online List Permissions
	 *
	 * @return	string	Comma-delimited values or '*'
	 * 	@li			Number indicates a group
	 *	@li			Number prepended by "m" indicates a member
	 *	@li			Number prepended by "s" indicates a social group
	 */
	public function onlineListPermissions()
	{
		if ( $this->hidden() or $this->isFutureDate() )
		{
			return '0';
		}

		return $this->searchIndexPermissions();
	}
	
	/* !Moderation */
	
	/**
	 * Can edit?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canEdit( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		$couldEdit = $this->couldEdit( $member );
		
		if ( $couldEdit === TRUE )
		{
			if ( static::modPermission( 'edit', $member, $this->containerWrapper() ) )
			{
				return TRUE;
			}
			
			/* Still here, we can edit this post */
			if ( !$member->group['g_edit_cutoff'] )
			{
				return TRUE;
			}
			else
			{
				/* Check if we are looking for a time out */
				if( \IPS\DateTime::ts( $this->mapped('date') )->add( new \DateInterval( "PT{$member->group['g_edit_cutoff']}M" ) ) > \IPS\DateTime::create() )
				{
					return TRUE;
				}
			}
		}
	}
	
	/**
	 * Could edit an item?
	 * Useful to see if one can edit something even if the cut off has expired
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function couldEdit( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		/* Are we restricted from posting or have an unacknowledged warning? */
		if ( $member->restrict_post or ( $member->members_bitoptions['unacknowledged_warnings'] and \IPS\Settings::i()->warn_on and \IPS\Settings::i()->warnings_acknowledge ) )
		{
			return FALSE;
		}

		if ( $member->member_id )
		{
			/* Do we have moderator permission to edit stuff in the container? */
			if ( static::modPermission( 'edit', $member, $this->containerWrapper() ) )
			{
				return TRUE;
			}

			/* Can the member edit their own content? */
			if ( $member->member_id == $this->author()->member_id and ( $member->group['g_edit_posts'] == '1' or \in_array( \get_class( $this ), explode( ',', $member->group['g_edit_posts'] ) ) ) and ( !( $this instanceof \IPS\Content\Lockable ) or !$this->locked() ) )
			{
				return TRUE;
			}
		}
		
		return FALSE;
	}
	
	/**
	 * Can edit title?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canEditTitle( $member=NULL )
	{
		return $this->canEdit( $member );
	}
	
	/**
	 * Can pin?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canPin( $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Pinnable ) or $this->mapped('pinned') )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		return ( $member->member_id and static::modPermission( 'pin', $member, $this->containerWrapper() ) );
	}
	
	/**
	 * Can unpin?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canUnpin( $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Pinnable ) or !$this->mapped('pinned') )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		return ( $member->member_id and static::modPermission( 'unpin', $member, $this->containerWrapper() ) );
	}
	
	/**
	 * Can feature?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canFeature( $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Featurable ) or $this->mapped('featured') or $this->hidden() !== 0 )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		return ( $member->member_id and static::modPermission( 'feature', $member, $this->containerWrapper() ) );
	}
	
	/**
	 * Can unfeature?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canUnfeature( $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Featurable ) or !$this->mapped('featured') )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		return ( $member->member_id and static::modPermission( 'unfeature', $member, $this->containerWrapper() ) );
	}
	
	/**
	 * Is locked?
	 *
	 * @return	bool
	 * @throws	\BadMethodCallException
	 */
	public function locked()
	{
		if ( $this instanceof \IPS\Content\Lockable )
		{
			if ( isset( static::$databaseColumnMap['locked'] ) )
			{
				return $this->mapped('locked');
			}
			else
			{
				return ( $this->mapped('status') == 'closed' );
			}
		}
		else
		{
			throw new \BadMethodCallException;
		}
	}
	
	/**
	 * Can lock?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canLock( $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Lockable ) or $this->locked() )
		{
			return FALSE;
		}

		$member = $member ?: \IPS\Member::loggedIn();
		
		if( $member->member_id and static::modPermission( 'lock', $member, $this->containerWrapper() ) )
		{
			return TRUE;
		}

		if( ( $member->group['g_lock_unlock_own'] == '1' or \in_array( \get_class( $this ), explode( ',', $member->group['g_lock_unlock_own'] ) ) ) AND $member->member_id == $this->author()->member_id )
		{
			return TRUE;
		}

		return FALSE;
	}
	
	/**
	 * Can unlock?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canUnlock( $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Lockable ) or !$this->locked() )
		{
			return FALSE;
		}

		$member = $member ?: \IPS\Member::loggedIn();

		if( $member->member_id and static::modPermission( 'unlock', $member, $this->containerWrapper() ) )
		{
			return TRUE;
		}

		if( $member->group['g_lock_unlock_own'] AND $member->member_id == $this->author()->member_id )
		{
			return TRUE;
		}

		return FALSE;
	}
	
	/**
	 * Can hide?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canHide( $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Hideable ) or $this->hidden() === -1 )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		return ( $member->member_id and ( static::modPermission( 'hide', $member, $this->containerWrapper() ) or ( $member->member_id == $this->author()->member_id and ( $member->group['g_hide_own_posts'] == '1' or \in_array( \get_class( $this ), explode( ',', $member->group['g_hide_own_posts'] ) ) ) ) ) );
	}
	
	/**
	 * Can unhide?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canUnhide( $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Hideable ) or !$this->hidden() )
		{
			return FALSE;
		}

		/* Check delayed deletes */
		if ( $this->hidden() == -2 )
		{
			return FALSE;
		}

		$member = $member ?: \IPS\Member::loggedIn();
		return ( $member->member_id and ( static::modPermission( 'unhide', $member, $this->containerWrapper() ) ) );
	}
	
	/**
	 * Can view hidden items?
	 *
	 * @param	\IPS\Member|NULL	    $member	        The member to check for (NULL for currently logged in member)
	 * @param   \IPS\Node\Model|null    $container      Container
	 * @return	bool
	 * @note	If called without passing $container, this method falls back to global "can view hidden content" moderator permission which isn't always what you want - pass $container if in doubt or use canViewHiddenItemsContainers()
	 */
	public static function canViewHiddenItems( $member=NULL, \IPS\Node\Model $container = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $container ? static::modPermission( 'view_hidden', $member, $container ) : $member->modPermission( "can_view_hidden_content" );
	}
	
	/**
	 * Container IDs that the member can view hidden items in
	 *
	 * @param	\IPS\Member|NULL	    $member	        The member to check for (NULL for currently logged in member)
	 * @return	bool|array				TRUE means all, FALSE means none
	 */
	public static function canViewHiddenItemsContainers( $member=NULL )
	{
		if ( !\in_array( 'IPS\Content\Hideable', class_implements( \get_called_class() ) ) )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		if ( $member->modPermission( "can_view_hidden_content" ) )
		{
			return TRUE;
		}
		elseif ( $member->modPermission( "can_view_hidden_" . static::$title ) )
		{
			if ( !isset( static::$containerNodeClass ) )
			{
				return TRUE;
			}

			$containerClass = static::$containerNodeClass;
			if ( isset( $containerClass::$modPerm ) )
			{
				$containers = $member->modPermission( $containerClass::$modPerm );
				if ( $containers === -1 )
				{
					return TRUE;
				}
				return $containers;
			}
			else
			{
				return TRUE;
			}
		}
		
		return FALSE;
	}

	/**
	 * Can view hidden comments on this item?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canViewHiddenComments( $member=NULL )
	{		
		$commentClass = static::$commentClass;

		return $commentClass::modPermission( 'view_hidden', $member, $this->containerWrapper() );
	}
	
	/**
	 * Can view hidden reviews on this item?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canViewHiddenReviews( $member=NULL )
	{
		$reviewClass = static::$reviewClass;

		return $reviewClass::modPermission( 'view_hidden', $member, $this->containerWrapper() );
	}

	/**
	 * Can move?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canMove( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		try
		{
			return ( $member->member_id and $this->container() and ( static::modPermission( 'move', $member, $this->containerWrapper() ) ) );
		}
		catch( \BadMethodCallException $e )
		{
			return FALSE;
		}
	}
	
	/**
	 * Can Merge?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canMerge( $member=NULL )
	{
		if ( static::$firstCommentRequired )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			return ( $member->member_id and ( static::modPermission( 'split_merge', $member, $this->containerWrapper() ) ) );
		}
		return FALSE;
	}
	
	/**
	 * Can delete?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canDelete( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* Guests can never delete */
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		/* Can we delete our own content? */
		if ( $member->member_id == $this->author()->member_id and ( $member->group['g_delete_own_posts'] == '1' or \in_array( \get_class( $this ), explode( ',', $member->group['g_delete_own_posts'] ) ) ) )
		{
			return TRUE;
		}
		
		/* What about this? */
		try
		{
			return static::modPermission( 'delete', $member, $this->containerWrapper() );
		}
		catch ( \BadMethodCallException $e )
		{
			return $member->modPermission( "can_delete_content" );
		}
		
		return FALSE;
	}

	/**
	 * Syncing to run when hiding
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onHide( $member )
	{
		if ( method_exists( $this, 'container' ) AND !$this->skipContainerRebuild )
		{
			try
			{
				$container = $this->container();

				if ( isset( static::$commentClass ) )
				{
					$container->setLastComment();
				}
				if ( isset( static::$reviewClass ) )
				{
					if( !$this->hidden() )
					{
						$container->_reviews = $container->_reviews - $this->mapped('num_reviews');
					}

					$container->setLastReview();
				}

				$container->resetCommentCounts();
				$container->save();
			}
			catch ( \BadMethodCallException $e ) { }
		}
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
		/* If approving, we may need to increase the post count */
		if ( $approving AND isset( static::$commentClass ) )
		{
			$commentClass = static::$commentClass;
			if ( !$this->isAnonymous() and ( static::$firstCommentRequired and $commentClass::incrementPostCount( $this->containerWrapper() ) ) or static::incrementPostCount( $this->containerWrapper() ) )
			{
				$this->author()->member_posts++;
				$this->author()->save();
			}
		}
		
		/* Update container */
		if ( method_exists( $this, 'container' ) AND !$this->skipContainerRebuild )
		{
			try
			{
				$container = $this->container();

				if ( ! $this->isFutureDate() )
				{
					$container->resetCommentCounts();
				}

				if ( isset( static::$commentClass ) )
				{
					$container->setLastComment();
				}
				if ( isset( static::$reviewClass ) )
				{
					$container->_reviews = $container->_reviews + $this->mapped('num_reviews');
					$container->setLastReview();
				}
				
				$container->save();
			}
			catch ( \BadMethodCallException $e ) { }
		}
	}

	/**
	 * Warning Reference Key
	 *
	 * @return	string|NULL
	 */
	public function warningRef()
	{
		/* If the member cannot warn, return NULL so we're not adding ugly parameters to the profile URL unnecessarily */
		if ( !\IPS\Member::loggedIn()->modPermission('mod_can_warn') )
		{
			return NULL;
		}
		
		$idColumn = static::$databaseColumnId;
		return base64_encode( json_encode( array( 'app' => static::$application, 'module' => static::$module, 'id_1' => $this->$idColumn ) ) );
	}
	
	/* !Sharelinks */
	
	/**
	 * Can share
	 *
	 * @return boolean
	 */
	public function canShare()
	{
		if ( !( $this instanceof \IPS\Content\Shareable ) )
		{
			return FALSE;
		}
		
		if ( !$this->canView( \IPS\Member::load( 0 ) ) )
		{
			return FALSE;
		}
		
		return TRUE;
	}
	 
	/**
	 * Return sharelinks for this item
	 *
	 * @return array
	 */
	public function sharelinks()
	{
		if( !\count( $this->sharelinks ) )
		{
			if ( $this instanceof Shareable and $this->canShare() )
			{
				$idColumn = static::$databaseColumnId;
				$shareUrl = $this->url();
									
				$this->sharelinks = \IPS\core\ShareLinks\Service::getAllServices( $shareUrl, $this->mapped('title'), NULL, $this );
			}
			else
			{
				$this->sharelinks = array();
			}
		}
		
		return $this->sharelinks;
	}
	
	/**
	 * Web Share Data
	 *
	 * @return	array|NULL
	 */
	public function webShareData(): ?array
	{
		return array(
			'title'		=> \IPS\Text\Parser::truncate( $this->mapped('title'), TRUE ),
			'text'		=> \IPS\Text\Parser::truncate( $this->mapped('title'), TRUE ),
			'url'		=> (string) $this->url()
		);
	}
	
	/* !ReadMarkers */
	
	/**
	 * Read Marker cache
	 */
	protected $unread = NULL;
	
	/**
	 * Does a container contain unread items?
	 *
	 * @param	\IPS\Node\Model		$container	The container
	 * @param	\IPS\Member|NULL	$member		The member (NULL for currently logged in member)
	 * @param	bool				$children	Check children for unread items
	 * @return	bool|NULL
	 */
	public static function containerUnread( \IPS\Node\Model $container, \IPS\Member $member = NULL, $children=TRUE )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		/* We only do this if the thing is tracking markers */
		if ( !\in_array( 'IPS\Content\ReadMarkers', class_implements( \get_called_class() ) ) or !$member->member_id )
		{
			return NULL;
		}
		
		/* What was the last time something was posted in here? */
		$lastCommentTime = $container->getLastCommentTime();

		if ( $lastCommentTime === NULL )
		{
			/* Do we have any children to be concerned about? */
			if( $children )
			{
				foreach ( $container->children( 'view', $member ) AS $child )
				{
					if ( static::containerUnread( $child, $member ) )
					{
						return TRUE;
					}
				}
			}
			
			return FALSE;
		}
		
		/* Was that after the last time we marked this forum read? */
		$markers = $member->markersResetTimes( static::$application );

		if ( isset( $markers[ $container->_id ] ) )
		{
			if ( $markers[ $container->_id ] < $lastCommentTime->getTimestamp() )
			{
				return TRUE;
			}
		}
		else if ( $member->marked_site_read >= $lastCommentTime->getTimestamp() )
		{
			if( $children )
			{
				/* This forum has nothing new, but do children? */
				foreach ( $container->children( 'view', $member ) as $child )
				{
					if ( static::containerUnread( $child, $member ) )
					{
						return TRUE;
					}
				}
			}
			
			return FALSE;
		}
		else
		{
			if( $container->_items !== 0 or $container->_comments !== 0 )
			{
				return TRUE;
			}
		}
		
		/* Check children */
		if( $children )
		{
			foreach ( $container->children( 'view', $member ) as $child )
			{
				if ( static::containerUnread( $child, $member ) )
				{
					return TRUE;
				}
			}
		}
		
		/* Still here? It's read */
		return FALSE;
	}
	
	/**
	 * Is unread?
	 *
	 * @param	\IPS\Member|NULL	$member	The member (NULL for currently logged in member)
	 * @return	int|NULL	0 = read. -1 = never read. 1 = updated since last read. NULL = unsupported
	 * @note	When a node is marked read, we stop noting which individual content items have been read. Therefore, -1 vs 1 is not always accurate but rather goes on if the item was created
	 */
	public function unread( \IPS\Member $member = NULL )
	{
		if ( $this->unread === NULL )
		{
			$latestThing = 0;
			foreach ( array( 'updated', 'last_comment', 'last_review' ) as $k )
			{
				if ( isset( static::$databaseColumnMap[ $k ] ) and ( $this->mapped( $k ) <= time() AND $this->mapped( $k ) > $latestThing ) )
				{
					$latestThing = $this->mapped( $k );
				}
			}
			
			$idColumn = static::$databaseColumnId;
			$container = $this->containerWrapper();
			
			$this->unread = static::unreadFromData( $member, $latestThing, $this->mapped('date'), $this->$idColumn, $container ? $container->_id : NULL, TRUE );
		}
		
		return $this->unread;
	}
	
	/**
	 * Calculate unread status from data
	 *
	 * @param	\IPS\Member|NULL	$member			The member (NULL for currently logged in member)
	 * @param	int					$updateDate		Timestamp of when item was last updated or replied to
	 * @param	int					$createDate		Timestamp of when item was created
	 * @param	int					$itemId			The item ID
	 * @param	int|null			$containerId	The container ID
	 * @param	bool				$limitToApp		If FALSE, will load all item markers into memopry rather than just what's in this app. This should be used in views which combine data from multiple apps like streams.
	 * @return	int|NULL	0 = read. -1 = never read. 1 = updated since last read. NULL = unsupported
	 * @note	When a node is marked read, we stop noting which individual content items have been read. Therefore, -1 vs 1 is not always accurate but rather goes on if the item was created
	 */
	public static function unreadFromData( ?\IPS\Member $member, $updateDate, $createDate, $itemId, $containerId, $limitToApp = TRUE )
	{
		/* Get the member */
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* We only do this if the thing is tracking markers and the user is logged in */
		if ( !\in_array( 'IPS\Content\ReadMarkers', class_implements( \get_called_class() ) ) or !$member->member_id )
		{
			return NULL;
		}
		
		/* Get the markers */
		if ( $limitToApp )
		{
			$resetTimes = $member->markersResetTimes( static::$application );
		}
		else
		{
			$resetTimes = $member->markersResetTimes( NULL );
			$resetTimes = isset( $resetTimes[ static::$application ] ) ? $resetTimes[ static::$application ] : array();
		}
		$markers = $member->markersItems( static::$application, static::makeMarkerKey( $containerId ) );
		
		/* If we do not have a marker for this item... */
		if( !isset( $markers[ $itemId ] ) )
		{
			/* Figure the reset time - i.e. when the user marked either the container or the whole site as read */
			if( $containerId )
			{
				$resetTime = ( isset( $resetTimes[ $containerId ] ) AND $resetTimes[ $containerId ] > $member->marked_site_read ) ? $resetTimes[ $containerId ] : $member->marked_site_read;
			}
			else
			{
				$resetTime = ( !\is_array( $resetTimes ) AND $resetTimes > $member->marked_site_read ) ? $resetTimes : $member->marked_site_read;
			}
			
			/* If the reset time is after when this item was updated, it's read */
			if ( !\is_null( $resetTime ) and $resetTime >= $updateDate )
			{
				return 0;
			}
			/* Otherwise it's unread */
			else
			{
				/* If we have a reset time, but it's after when this item was created, it's been updated since we read it */
				if ( !\is_null( $resetTime ) and $resetTime > $createDate )
				{
					return 1;
				}
				/* Otherwise it's completely new to us */
				else
				{
					return -1;
				}
			}
		}
		/* If we do have a marker, but the thing has been updated since our marker, it's updated */
		elseif( isset( $markers[ $itemId ] ) AND $markers[ $itemId ] < $updateDate )
		{
			return 1;
		}
		/* Otherwise it's read */
		else
		{
			return 0;
		}
	}
	
	/**
	 * @brief	Time last read cache
	 */
	protected $timeLastRead = array();
	
	/**
	 * Time last read
	 *
	 * @param	\IPS\Member|NULL	$member	The member (NULL for currently logged in member)
	 * @return	\IPS\DateTime|NULL
	 * @throws	\BadMethodCallException
	 */
	public function timeLastRead( \IPS\Member $member = NULL )
	{
		/* We only do this if the thing is tracking markers */
		if ( !( $this instanceof ReadMarkers ) )
		{
			throw new \BadMethodCallException;
		}
		
		/* Work out the member */
		$member = $member ?: \IPS\Member::loggedIn();
		if ( !$member->member_id )
		{
			return NULL;
		}
		
		/* Get it */
		if ( !isset( $this->timeLastRead[ $member->member_id ] ) )
		{
			/* Check the time the entire site was marked read */
			$times = array();
			$times[] =  $member->marked_site_read;

			$containerId = NULL;
			
			/* Check the reset time */
			if ( $container = $this->containerWrapper() )
			{
				$resetTimes = $member->markersResetTimes( static::$application );
				if ( isset( $resetTimes[ $container->_id ] ) and \is_numeric( $resetTimes[ $container->_id ] ) )
				{
					$times[] = $resetTimes[ $container->_id ];
				}

				$containerId = $container->_id;
			}
	
			/* Check the actual item */
			$markers = $member->markersItems( static::$application, static::makeMarkerKey( $containerId ) );
			$idColumn = static::$databaseColumnId;
			if ( isset( $markers[ $this->$idColumn ] ) )
			{
				$times[] = ( \is_array( $markers[ $this->$idColumn ] ) ) ? max( $markers[ $this->$idColumn ] ) : $markers[ $this->$idColumn ];
			}
			
			/* Set the highest of those */
			$this->timeLastRead[ $member->member_id ] = ( \count( $times ) ? max( $times ) : NULL );
		}
		
		/* Return */
		return $this->timeLastRead[ $member->member_id ] ? \IPS\DateTime::ts( $this->timeLastRead[ $member->member_id ] ) : NULL;
	}
	
	/**
	 * Mark as read
	 *
	 * @param	\IPS\Member|NULL	$member					The member (NULL for currently logged in member)
	 * @param	int|NULL			$time					The timestamp to set (or NULL for current time)
	 * @param	mixed				$extraContainerWhere	Additional where clause(s) (see \IPS\Db::build for details)
	 * @param	bool				$force					Mark as unread even if we already appear to be unread?
	 * @return	void
	 */
	public function markRead( \IPS\Member $member = NULL, $time = NULL, $extraContainerWhere = NULL, $force = FALSE )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		$time	= $time ?: time();

		if ( $this instanceof ReadMarkers and ( $this->unread() OR $force ) and $member->member_id )
		{
			/* Mark this one read */
			$idColumn	= static::$databaseColumnId;
			$container = $this->containerWrapper();
			$key		= static::makeMarkerKey( $container ? $container->_id : NULL );
			$readArray	= $member->markersItems( static::$application, $key );

			if ( isset( $member->markers[ static::$application ][ $key ] ) )
			{
				$marker = $member->markers[ static::$application ][ $key ];

				/* We've already read this topic more recently */
				if( isset( $readArray[ $this->$idColumn ] ) AND $readArray[ $this->$idColumn ] >= $time )
				{
					return;
				}

				$readArray[ $this->$idColumn ] = $time;

				$readArray = \array_slice( $readArray, ( \count( $readArray ) > static::STORAGE_CUTOFF ) ? (int) '-' . static::STORAGE_CUTOFF : 0, ( \count( $readArray ) > static::STORAGE_CUTOFF ) ? NULL : static::STORAGE_CUTOFF, TRUE );

				$toStore	= array( 'update', array( 'item_read_array' => json_encode( $readArray ) ), array( 'item_key=? AND item_member_id=? AND item_app=?', $key, $member->member_id, static::$application ) );
            }
			else
			{
				$readArray = array( $this->$idColumn => $time );
				$marker = array(
					'item_key'			=> $key,
					'item_member_id'	=> $member->member_id,
					'item_app'			=> static::$application,
					'item_read_array'	=> json_encode( $readArray ),
					'item_global_reset'	=> $member->marked_site_read ?: 0,
					'item_app_key_1'	=> $this->mapped('container') ?: 0,
					'item_app_key_2'	=> static::getItemMarkerKey( 2 ),
					'item_app_key_3'	=> static::getItemMarkerKey( 3 ),
				);

				$toStore	= array( 'insert', $marker );
			}

			/* Reset cached markers in the member object */
			$member->markers[ static::$application ][ $key ] = $marker;
			
			/* Have we now read the whole node? */
			$whereClause = array();

			/* Ignore linked content if linked content is supported */
			if ( isset( static::$databaseColumnMap['state'] ) )
			{
				$whereClause[] = array( static::$databaseTable . '.' . static::$databaseColumnMap['state'] . '!=?', 'link' );
				$whereClause[] = array( static::$databaseTable . '.' . static::$databaseColumnMap['state'] . '!=?', 'merged' );
			}

			if ( \count( $readArray ) > 0 )
			{
				$whereClause[] = array( static::$databaseTable . '.' . static::$databasePrefix . $idColumn . ' NOT IN(' . implode( ',', array_keys( $readArray ) ) . ')' );
			}

			if( $this->containerWrapper() )
			{
				$whereClause[]	= array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['container'] . '=?', $this->container()->_id );
			}

			if ( \in_array( 'IPS\Content\Hideable', class_implements( \get_called_class() ) ) )
			{
				if ( !static::canViewHiddenItems( $member, $this->containerWrapper() ) )
				{
					if ( isset( static::$databaseColumnMap['approved'] ) )
					{
						$whereClause[] = array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['approved'] . '=?', 1 );
					}
					elseif ( isset( static::$databaseColumnMap['hidden'] ) )
					{
						$whereClause[] = array( static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['hidden'] . '=?', 0 );
					}
				}

				/* No matter if we can or cannot view hidden items, we do not want these to show: -2 is queued for deletion and -3 is posted before register */
				if ( isset( static::$databaseColumnMap['hidden'] ) )
				{
					$col = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['hidden'];
					$whereClause[] = array( "{$col}!=-2 AND {$col} !=-3" );
				}
				else if ( isset( static::$databaseColumnMap['approved'] ) )
				{
					$col = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap['approved'];
					$whereClause[] = array( "{$col}!=-2 AND {$col}!=-3" );
				}
			}

            if( $extraContainerWhere !== NULL )
            {
                if ( !\is_array( $extraContainerWhere ) or !\is_array( $extraContainerWhere[0] ) )
                {
                    $extraContainerWhere = array( $extraContainerWhere );
                }
                $whereClause = array_merge( $whereClause, $extraContainerWhere );
            }

			if ( isset( $marker['item_global_reset'] ) )
			{
				$subWhere	= array();
				$checked	= array();
				foreach ( array( 'last_comment', 'last_review', 'updated' ) as $k )
				{
					/* If we already hit last_comment and/or last_review, skip updated since we don't mark as unread when stuff is updated */
					if( \count( $checked ) AND $k == 'updated' )
					{
						break;
					}

					if ( isset( static::$databaseColumnMap[ $k ] ) )
					{
						if ( \is_array( static::$databaseColumnMap[ $k ] ) )
						{
							if( !\in_array( static::$databaseColumnMap[ $k ][0], $checked ) )
							{
								$subWhere[] = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap[ $k ][0] . '>' . $marker['item_global_reset'];
								$checked[] = static::$databaseColumnMap[ $k ][0];
							}
						}
						else
						{
							if( !\in_array( static::$databaseColumnMap[ $k ], $checked ) )
							{
								$subWhere[] = static::$databaseTable . '.' . static::$databasePrefix . static::$databaseColumnMap[ $k ] . '>' . $marker['item_global_reset'];
								$checked[] = static::$databaseColumnMap[ $k ];
							}
						}
					}
				}

				if( \count( $subWhere ) )
				{
					$whereClause[]	= array( '(' . implode( ' OR ', $subWhere ) . ')' );
				}
			}

			$unreadCount = \IPS\Db::i()->select(
				'COUNT(*) as count',
				static::$databaseTable,
				$whereClause
			)->first();

			if ( !$unreadCount AND $this->containerWrapper() )
			{
				static::markContainerRead( $this->containerWrapper(), $member, FALSE );
			}
			elseif( $toStore !== NULL )
			{
				if( $toStore[0] == 'update' )
				{
					\IPS\Db::i()->update( 'core_item_markers', $toStore[1], $toStore[2] );
				}
				else
				{
					\IPS\Db::i()->replace( 'core_item_markers', $toStore[1] );
				}
			}
		}
	}
	
	/**
	 * Mark container as read
	 *
	 * @param	\IPS\Node\Model		$container	The container
	 * @param	\IPS\Member|NULL	$member		The member (NULL for currently logged in member)
	 * @param	bool				$children	Whether to mark children as read (default) or not as well
	 * @return	void
	 */
	public static function markContainerRead( \IPS\Node\Model $container, \IPS\Member $member = NULL, $children = TRUE )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		if ( \in_array( 'IPS\Content\ReadMarkers', class_implements( \get_called_class() ) ) and $member->member_id )
		{		
			$key = static::makeMarkerKey( $container->_id );

			$data = array(
				'item_key'			=> $key,
				'item_member_id'	=> $member->member_id,
				'item_app'			=> static::$application,
				'item_read_array'	=> json_encode( array() ),
				'item_global_reset'	=> time(),
				'item_app_key_1'	=> $container->_id,
				'item_app_key_2'	=> static::getItemMarkerKey( 2 ),
				'item_app_key_3'	=> static::getItemMarkerKey( 3 ),
			);

			\IPS\Db::i()->replace( 'core_item_markers', $data );

			/* Update container caches */
			$member->setMarkerResetTimes( $data );
			$member->haveAllMarkers = FALSE;
			unset( $member->markersResetTimes[ static::$application ] );
			
			if( $children )
			{
				foreach( $container->children( 'view', $member, false ) as $child )
				{
					static::markContainerRead( $child, $member );
				}
			}
		}
	}
	
	/**
	 * Make key
	 *
	 * @param	int|NULL	$containerId	The container ID
	 * @return	string
	 * @note	We use serialize here which is usually not allowed, however, the value is encoded and never unserialized so there is no security issue.
	 */
	public static function makeMarkerKey( $containerId = NULL )
	{
		$keyData = array();
		if ( $containerId )
		{
			$keyData['item_app_key_1'] = $containerId;
		}
		
		return md5( \serialize( $keyData ) );
	}

	/**
	 * Find out if there are any unread items in the same container
	 *
	 * @return	bool
	 * @throws	\OutOfRangeException
	 */
	public function containerHasUnread()
	{
		/* What container are we in? */
		$container = $this->container();

		/* If the whole container is read or there's a guest, we know we have nothing */
		if ( static::containerUnread( $container, NULL, FALSE ) !== TRUE  OR !\IPS\Member::loggedIn()->member_id )
		{
			throw new \OutOfRangeException;
		}

		return TRUE;
	}

	/**
	 * Fetch next unread item in the same container
	 *
	 * @return	static
	 * @throws	\OutOfRangeException
	 */
	public function nextUnread()
	{
		/* What container are we in? */
		$container = $this->container();

		/* Check container has unread */
		$this->containerHasUnread();
		
		/* Otherwise we need to query... */
		$where = array();		
		$where[] = array( static::$databaseTable . '.' . static::$databaseColumnMap['container'] . '=?', $container->_id );

        /* Exclude links */
        if ( isset( static::$databaseColumnMap['state'] ) )
        {
            $where[] = array( static::$databaseTable . '.' .static::$databaseColumnMap['state'] . '!=?', 'link' );
            $where[] = array( static::$databaseTable . '.' .static::$databaseColumnMap['state'] . '!=?', 'merged' );
        }

		/* What are we going by? */
		$fields = array();
		foreach ( array( 'updated', 'last_comment', 'last_review' ) as $k )
		{
			if ( isset( static::$databaseColumnMap[ $k ] ) )
			{
				if ( \is_array( static::$databaseColumnMap[ $k ] ) )
				{
					foreach ( static::$databaseColumnMap[ $k ] as $_k )
					{
						$fields[] = 'IFNULL(`' . static::$databaseTable . '`.`' . static::$databasePrefix . $_k . '`,0)';
					}
				}
				else
				{
					$fields[] = 'IFNULL(`' . static::$databaseTable . '`.`' . static::$databasePrefix . static::$databaseColumnMap[ $k ] . '`,0)';
				}
			}
		}
		$fields = array_unique( $fields );
		$fields = ( \count( $fields ) > 1 ) ? ( 'GREATEST( ' . implode( ', ', $fields ) . ' )' ) : $fields;
		
		/* We need only items that have been updated since we reset the container (or the site, if that was more recent) */
		$resetTimes = \IPS\Member::loggedIn()->markersResetTimes( static::$application );
		$resetTime = NULL;
		if( isset( $resetTimes[ $container->_id ] ) )
		{
			$resetTime = $resetTimes[ $container->_id ];
		}
		if ( \is_null( $resetTime ) or $resetTime < \IPS\Member::loggedIn()->marked_site_read )
		{
			$resetTime = \IPS\Member::loggedIn()->marked_site_read;
		}
		if ( $resetTime )
		{
			$where[] = array( $fields . ' > ?', $resetTime );
		}
		
		/* And we don't want this one */
		$idColumn = static::$databaseColumnId;
		$where[] = array( static::$databasePrefix . static::$databaseColumnId . '<> ?', $this->$idColumn );
		
		/* Find one */
		$markers = \IPS\Member::loggedIn()->markersItems( static::$application, static::makeMarkerKey( $container->_id ) );
		foreach ( static::getItemsWithPermission( $where, static::$databasePrefix . $this->getDateColumn() . ' DESC', 5000, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, NULL, FALSE, FALSE, FALSE, FALSE, NULL, $container, FALSE, FALSE, FALSE ) as $item )
		{
			/* If we have never read it, return it */
			if( !isset( $markers[ $item->$idColumn ] ) )
			{
				return $item;
			}
			
			/* Otherwise, check when it was updated... */
			$latestThing = 0;
			foreach ( array( 'updated', 'last_comment', 'last_review' ) as $k )
			{
				if ( isset( static::$databaseColumnMap[ $k ] ) and ( $item->mapped( $k ) < time() AND $item->mapped( $k ) > $latestThing ) )
				{
					$latestThing = $item->mapped( $k );
				}
			}
			
			/* And return it if that was after the last time we read it */
			if ( $latestThing > $markers[ $item->$idColumn ] )
			{
				return $item;
			}
		}
				
		/* Or throw an exception saying we have nothing if we're still here */
		throw new \OutOfRangeException;
	}
		
	/* !\IPS\Helpers\Table */
	
	/**
	 * @brief	Table hover URL
	 */
	public $tableHoverUrl = FALSE;
	
	/* !Tags */
	
	/**
	 * Can tag?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	bool
	 */
	public static function canTag( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return \in_array( 'IPS\Content\Tags', class_implements( \get_called_class() ) ) and \IPS\Settings::i()->tags_enabled and !( $member->group['gbw_disable_tagging'] ) and !( $member->members_bitoptions['bw_disable_tagging'] );
	}
	
	/**
	 * Can use prefixes?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	bool
	 */
	public static function canPrefix( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return \in_array( 'IPS\Content\Tags', class_implements( \get_called_class() ) ) and \IPS\Settings::i()->tags_enabled and \IPS\Settings::i()->tags_can_prefix and !( $member->group['gbw_disable_tagging'] ) and !( $member->group['gbw_disable_prefixes'] ) and !( $member->members_bitoptions['bw_disable_tagging'] ) and !( $member->members_bitoptions['bw_disable_prefixes'] );
	}
	
	/**
	 * Defined Tags
	 *
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	array
	 */
	public static function definedTags( \IPS\Node\Model $container = NULL )
	{
		$return = \IPS\Settings::i()->tags_predefined ? explode( ',', \IPS\Settings::i()->tags_predefined ) : array();
		if ( \IPS\Settings::i()->tags_alphabetical )
		{
			natcasesort( $return );
		}
		
		return $return;
	}
	
	/**
	 * @brief	Tags cache
	 */
	protected $tags = NULL;
	
	/**
	 * Get prefix
	 *
	 * @param	bool|NULL		$encode	Encode returned value
	 * @return	string|NULL
	 */
	public function prefix( $encode=FALSE )
	{
		if ( $this instanceof \IPS\Content\Tags )
		{
			if ( $this->tags === NULL )
			{
				$this->tags();
			}
									
			return isset( $this->tags['prefix'] ) ? ( $encode ) ? rawurlencode( $this->tags['prefix'] ) : $this->tags['prefix'] : NULL;
		}
		else
		{
			return NULL;
		}
	}
	
	/**
	 * Get tags
	 *
	 * @return	array
	 */
	public function tags()
	{
		if ( $this instanceof \IPS\Content\Tags and \IPS\Settings::i()->tags_enabled )
		{
			if ( $this->tags === NULL )
			{
				$idColumn = static::$databaseColumnId;
				$this->tags = array( 'tags' => array(), 'prefix' => NULL );
				foreach ( \IPS\Db::i()->select( '*', 'core_tags', array( 'tag_meta_app=? AND tag_meta_area=? AND tag_meta_id=?', static::$application, static::$module, $this->$idColumn ) ) as $tag )
				{
					if ( $tag['tag_prefix'] )
					{
						$this->tags['prefix'] = $tag['tag_text'];
					}
					else
					{
						$this->tags['tags'][] = $tag['tag_text'];
					}
				}
			}
			
			if ( isset( $this->tags['tags'] ) )
			{
				if ( \IPS\Settings::i()->tags_alphabetical )
				{
					natcasesort( $this->tags['tags'] );
				}
				
				return $this->tags['tags'];
			}
			else
			{
				return [];
			}
		}
		else
		{
			return [];
		}
	}
	
	/**
	 * Set tags
	 *
	 * @param	array				$set	The tags (if one has the key "prefix", it will be set as the prefix)
	 * @param	\IPS\Member::NULL	$member	The member saving the tags, or NULL for currently logged in member
	 * @return	void
	 */
	public function setTags( $set, $member=NULL )
	{
		if( $member === NULL )
		{
			$member = \IPS\Member::loggedIn();
		}

		$aaiLookup = $this->tagAAIKey();
		$aapLookup = $this->tagAAPKey();
		$idColumn = static::$databaseColumnId;
		$this->tags = array( 'tags' => array(), 'prefix' => NULL );
		
		\IPS\Db::i()->delete( 'core_tags', array( 'tag_aai_lookup=?', $aaiLookup ) );
		
		if ( !\is_array( $set ) )
		{
			$set = array( $set );
		}
		
		foreach ( $set as $key => $tag )
		{
			\IPS\Db::i()->insert( 'core_tags', array(
				'tag_aai_lookup'		=> $aaiLookup,
				'tag_aap_lookup'		=> $aapLookup,
				'tag_meta_app'			=> static::$application,
				'tag_meta_area'			=> static::$module,
				'tag_meta_id'			=> $this->$idColumn,
				'tag_meta_parent_id'	=> $this->container()->_id,
				'tag_member_id'			=> $member->member_id ?: 0,
				'tag_added'				=> time(),
				'tag_prefix'			=> $key === 'prefix',
				'tag_text'				=> $tag
			), TRUE );
			
			if ( $key === 'prefix' )
			{
				$this->tags['prefix'] = $tag;
			}
			else
			{
				$this->tags['tags'][] = $tag;
			}
		}
					
		\IPS\Db::i()->insert( 'core_tags_cache', array(
			'tag_cache_key'		=> $aaiLookup,
			'tag_cache_text'	=> json_encode( array( 'tags' => $this->tags['tags'], 'prefix' => $this->tags['prefix'] ) ),
			'tag_cache_date'	=> time()
		), TRUE );
		
		$containerClass = static::$containerNodeClass;
		if ( isset( $containerClass::$permissionMap['read'] ) )
		{
			$permissions = $containerClass::load( $this->container()->_id )->permissions();
			
			if ( isset( $permissions[ 'perm_' . $containerClass::$permissionMap['read'] ] ) )
			{
				\IPS\Db::i()->insert( 'core_tags_perms', array(
					'tag_perm_aai_lookup'		=> $aaiLookup,
					'tag_perm_aap_lookup'		=> $aapLookup,
					'tag_perm_text'				=> $permissions[ 'perm_' . $containerClass::$permissionMap['read'] ],
					'tag_perm_visible'			=> ( $this->hidden() OR $this->isFutureDate() ) ? 0 : 1,
				), TRUE );
			}
		}

		/* Add to search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}

		/* Callback once tags are updated */
		$this->processAfterTagUpdate();
	}
	
	/**
	 * Get tag AAI key
	 *
	 * @return	string
	 */
	public function tagAAIKey()
	{
		$idColumn = static::$databaseColumnId;
		return md5( static::$application . ';' . static::$module . ';' . $this->$idColumn );
	}
	
	/**
	 * Get tag AAP key
	 *
	 * @return	string
	 */
	public function tagAAPKey()
	{
		$containerClass = static::$containerNodeClass;
		return md5( $containerClass::$permApp . ';' . $containerClass::$permType . ';' . $this->container()->_id );
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
		$obj = parent::constructFromData( $data, $updateMultitonStoreIfExists );
		
		if ( isset( $data[ static::$databaseTable ] ) and \is_array( $data[ static::$databaseTable ] ) )
		{
			if ( isset( $data['core_tags_cache'] ) )
			{
				$obj->tags = ! empty( $data['core_tags_cache']['tag_cache_text'] ) ? json_decode( $data['core_tags_cache']['tag_cache_text'], TRUE ) : array( 'tags' => array(), 'prefix' => NULL );
			}
			if ( isset( $data['last_commenter'] ) )
			{
				\IPS\Member::constructFromData( $data['last_commenter'], FALSE );
			}
		}
		
		return $obj;
	}
	
	/* !Follow */
	
	/**
	 * @brief	Cache for current follow data, used on "My Followed Content" screen
	 */
	public $_followData;
	
	/**
	 * Followers
	 *
	 * @param	int						$privacy		static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS
	 * @param	array					$frequencyTypes	array( 'none', 'immediate', 'daily', 'weekly' )
	 * @param	\IPS\DateTime|int|NULL	$date			Only users who started following before this date will be returned. NULL for no restriction
	 * @param	int|array				$limit			LIMIT clause
	 * @param	string					$order			Column to order by
	 * @param	bool					$countOnly		Return only the count
	 * @return	\IPS\Db\Select|int
	 * @throws	\BadMethodCallException
	 */
	public function followers( $privacy=3, $frequencyTypes=array( 'none', 'immediate', 'daily', 'weekly' ), $date=NULL, $limit=array( 0, 25 ), $order=NULL, $countOnly=FALSE )
	{
		/* Check this class is followable */
		if ( !( $this instanceof \IPS\Content\Followable ) )
		{
			throw new \BadMethodCallException;
		}
		
		$idColumn = static::$databaseColumnId;

		return static::_followers( mb_strtolower( mb_substr( \get_called_class(), mb_strrpos( \get_called_class(), '\\' ) + 1 ) ), $this->$idColumn, $privacy, $frequencyTypes, $date, $limit, $order, $countOnly );
	}

	/**
	 * Followers Count
	 *
	 * @param	int						$privacy		static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS
	 * @param	array					$frequencyTypes	array( 'none', 'immediate', 'daily', 'weekly' )
	 * @param	\IPS\DateTime|int|NULL	$date			Only users who started following before this date will be returned. NULL for no restriction
	 * @return	int
	 * @throws	\BadMethodCallException
	 */
	public function followersCount( $privacy=3, $frequencyTypes=array( 'none', 'immediate', 'daily', 'weekly' ), $date=NULL )
	{
		return $this->followers( $privacy, $frequencyTypes, $date, NULL, NULL, TRUE );
	}

	/**
	 * Followers Count
	 *
	 * @param	array					$items			Array of \IPS\Content|Item
	 * @param	int						$privacy		static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS
	 * @param	array					$frequencyTypes	array( 'none', 'immediate', 'daily', 'weekly' )
	 * @param	\IPS\DateTime|int|NULL	$date			Only users who started following before this date will be returned. NULL for no restriction
	 * @return	int
	 * @throws	\BadMethodCallException
	 */
	public static function followersCounts( $items, $privacy=3, $frequencyTypes=array( 'none', 'immediate', 'daily', 'weekly' ), $date=NULL )
	{
		/* Check this class is followable */
		if ( !\in_array( 'IPS\Content\Followable', class_implements( \get_called_class() ) ) )
		{
			throw new \BadMethodCallException;
		}

		$ids = array();
		$idField = NULL;
		foreach( $items as $item )
		{
			if ( $idField === NULL )
			{
				$idField = static::$databaseColumnId;
			}
			$ids[] = $item->$idField;
		}

		return static::_followers( mb_strtolower( mb_substr( \get_called_class(), mb_strrpos( \get_called_class(), '\\' ) + 1 ) ), $ids, $privacy, $frequencyTypes, $date, NULL, NULL, TRUE );
	}
	
	/**
	 * Container Followers
	 *
	 * @param	\IPS\Node\Model			$container		The container
	 * @param	int						$privacy		static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS
	 * @param	array					$frequencyTypes	array( 'none', 'immediate', 'daily', 'weekly' )
	 * @param	\IPS\DateTime|int|NULL	$date			Only users who started following before this date will be returned. NULL for no restriction
	 * @param	int|array				$limit			LIMIT clause
	 * @param	string					$order			Column to order by
	 * @param	bool					$countOnly		Return only the count
	 * @return	\IPS\Db\Select|int
	 */
	public static function containerFollowers( \IPS\Node\Model $container, $privacy=3, $frequencyTypes=array( 'none', 'immediate', 'daily', 'weekly' ), $date=NULL, $limit=array( 0, 25 ), $order=NULL, $countOnly=FALSE )
	{
		/* Check this class is followable */
		if ( !\in_array( 'IPS\Content\Followable', class_implements( \get_called_class() ) ) )
		{
			throw new \BadMethodCallException;
		}

		return static::_followers( mb_strtolower( mb_substr( \get_class( $container ), mb_strrpos( \get_class( $container ), '\\' ) + 1 ) ), $container->_id, $privacy, $frequencyTypes, $date, $limit, $order, $countOnly );
	}
	
	/**
	 * Container Follower Count
	 *
	 * @param	\IPS\Node\Model	$container		The container
	 * @param	int						$privacy		static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS
	 * @param	array					$frequencyTypes	array( 'immediate', 'daily', 'weekly' )
	 * @param	\IPS\DateTime|int|NULL	$date			Only users who started following before this date will be returned. NULL for no restriction
	 * @return	int
	 */
	public static function containerFollowerCount( \IPS\Node\Model $container, $privacy=3, $frequencyTypes=array( 'none', 'immediate', 'daily', 'weekly' ), $date=NULL )
	{
		/* Check this class is followable */
		if ( !\in_array( 'IPS\Content\Followable', class_implements( \get_called_class() ) ) )
		{
			throw new \BadMethodCallException;
		}

		/* Return the count */
		return static::_followersCount( mb_strtolower( mb_substr( \get_class( $container ), mb_strrpos( \get_class( $container ), '\\' ) + 1 ) ), $container->_id, $privacy, $frequencyTypes, $date );
	}

	/**
	 * Container Follower Count
	 *
	 * @param	array					$containers		Array of \IPS\Node|Model
	 * @param	int						$privacy		static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS
	 * @param	array					$frequencyTypes	array( 'immediate', 'daily', 'weekly' )
	 * @param	\IPS\DateTime|int|NULL	$date			Only users who started following before this date will be returned. NULL for no restriction
	 * @return	int
	 */
	public static function containerFollowerCounts( $containers, $privacy=3, $frequencyTypes=array( 'none', 'immediate', 'daily', 'weekly' ), $date=NULL )
	{
		/* Check this class is followable */
		if ( !\in_array( 'IPS\Content\Followable', class_implements( \get_called_class() ) ) )
		{
			throw new \BadMethodCallException;
		}

		$ids = array();
		$class = NULL;
		foreach( $containers as $node )
		{
			if ( $class === NULL )
			{
				$class = \get_class( $node );
			}

			$ids[] = $node->_id;
		}

		/* Return the count */
		return static::_followersCount( mb_strtolower( mb_substr( $class, mb_strrpos( $class, '\\' ) + 1 ) ), $ids, $privacy, $frequencyTypes, $date );
	}
	
	/**
	 * Users to receive immediate notifications
	 *
	 * @param	int|array		$limit		LIMIT clause
	 * @param	string|NULL		$extra		Additional data
	 * @param	boolean			$countOnly	Just return the count
	 * @return \IPS\Db\Select
	 */
	public function notificationRecipients( $limit=array( 0, 25 ), $extra=NULL, $countOnly=FALSE )
	{
		/* Do we only want the count? */
		if( $countOnly )
		{
			$count	= 0;
			$count	+= $this->author()->followersCount( 3, array( 'immediate' ), $this->mapped('date') );
			$count	+= static::containerFollowerCount( $this->container(), 3, array( 'immediate' ), $this->mapped('date') );

			return $count;
		}

		$memberFollowers = $this->author()->followers( 3, array( 'immediate' ), $this->mapped('date'), NULL );

		if( $memberFollowers !== NULL )
		{
			$unions	= array( 
				static::containerFollowers( $this->container(), 3, array( 'immediate' ), $this->mapped('date'), NULL ),
				$memberFollowers
			);

			return \IPS\Db::i()->union( $unions, 'follow_added', $limit );
		}
		else
		{
			return static::containerFollowers( $this->container(), static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS, array( 'immediate' ), $this->mapped('date'), $limit, 'follow_added' );
		}
	}
	
	/**
	 * Create Notification
	 *
	 * @param	string|NULL		$extra		Additional data
	 * @return	\IPS\Notification
	 */
	protected function createNotification( $extra=NULL )
	{
		// New content is sent with itself as the item as we deliberately do not group notifications about new content items. Unlike comments where you're going to read them all - you might scan the notifications list for topic titles you're interested in
		return new \IPS\Notification( \IPS\Application::load( 'core' ), 'new_content', $this, array( $this ) );
	}
	
	/* !Polls */
	
	/**
	 * Can create polls?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if polls can be used in, if applicable
	 * @return	bool
	 */
	public static function canCreatePoll( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $member->group['g_post_polls'];
	}
	
	/**
	 * Get poll
	 *
	 * @return	\IPS\Poll|NULL
	 * @throws	\BadMethodCallException
	 */
	public function getPoll()
	{
		if ( !\in_array( 'IPS\Content\Polls', class_implements( \get_called_class() ) ) )
		{
			throw new \BadMethodCallException;
		}
		
		try
		{
			if( $this->mapped('poll') )
			{
				/* If the poll is in a club, return the special extended class */
				if( $this->containerWrapper() AND $club = $this->containerWrapper()->club() )
				{
					$poll		= \IPS\Member\Club\Poll::load( $this->mapped('poll') );
					$poll->club	= $club;

					return $poll;
				}
				else
				{
					return \IPS\Poll::load( $this->mapped('poll') );
				}
			}
			
			return NULL;
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}

	/* !Future Publishing */
	/**
	 * Can view future publishing items?
	 *
	 * @param	\IPS\Member|NULL	    $member	        The member to check for (NULL for currently logged in member)
	 * @param   \IPS\Node\Model|null    $container      Container
	 * @return	bool
	 * @note	If called without passing $container, this method falls back to global "can view hidden content" moderator permission which isn't always what you want - pass $container if in doubt
	 */
	public static function canViewFutureItems( $member=NULL, \IPS\Node\Model $container = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $container ? static::modPermission( 'view_future', $member, $container ) : $member->modPermission( "can_view_future_content" );
	}

	/**
	 * Can set items to be published in the future?
	 *
	 * @param	\IPS\Member|NULL	    $member	        The member to check for (NULL for currently logged in member)
	 * @param   \IPS\Node\Model|null    $container      Container
	 * @return	bool
	 */
	public static function canFuturePublish( $member=NULL, \IPS\Node\Model $container = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $container ? static::modPermission( 'future_publish', $member, $container ) : $member->modPermission( "can_future_publish_content" );
	}

	/**
	 * Can publish future items?
	 *
	 * @param	\IPS\Member|NULL	    $member	        The member to check for (NULL for currently logged in member)
	 * @param   \IPS\Node\Model|null    $container      Container
	 * @return	bool
	 */
	public function canPublish( $member=NULL, \IPS\Node\Model $container = NULL )
	{
		return static::canFuturePublish( $member, $container );
	}

	/**
	 * "Unpublishes" an item.
	 * @note    This will not change the item's date. This should be done via the form methods if required
	 *
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @return	void
	 */
	public function unpublish( $member=NULL )
	{
		/* Now do the actual stuff */
		if ( isset( static::$databaseColumnMap['is_future_entry'] ) AND isset( static::$databaseColumnMap['future_date'] ) )
		{
			$future = static::$databaseColumnMap['is_future_entry'];

			$this->$future = 1;
		}
		
		$this->save();
		$this->onUnpublish( $member );

		/* And update the tags perm cache */
		if ( $this instanceof \IPS\Content\Tags )
		{
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_visible' => 0 ), array( 'tag_perm_aai_lookup=?', $this->tagAAIKey() ) );
		}

		/* Update search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->removeFromSearchIndex( $this );
		}

		$this->expireWidgetCaches();
		$this->adjustSessions();
	}

	/**
	 * Publishes a 'future' entry now
	 *
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @return	void
	 */
	public function publish( $member=NULL )
	{
		/* Now do the actual stuff */
		if ( isset( static::$databaseColumnMap['is_future_entry'] ) AND isset( static::$databaseColumnMap['future_date'] ) )
		{
			$date   = static::$databaseColumnMap['future_date'];
			$future = static::$databaseColumnMap['is_future_entry'];

			$this->$date = time();
			$this->$future = 0;
		}

		$this->save();

		/* And update the tags perm cache */
		if ( $this instanceof \IPS\Content\Tags )
		{
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_visible' => 1 ), array( 'tag_perm_aai_lookup=?', $this->tagAAIKey() ) );
		}

		/* Update the first comment */
		if ( static::$firstCommentRequired )
		{
			$comment = $this->firstComment();
			$date  = $comment::$databaseColumnMap['date'];

			$comment->$date = time();
			$comment->save();

			if ( isset( static::$databaseColumnMap['last_comment'] ) )
			{
				$lastCommentField = static::$databaseColumnMap['last_comment'];
				if ( \is_array( $lastCommentField ) )
				{
					foreach ( $lastCommentField as $column )
					{
						$this->$column = time();
					}
				}
				else
				{
					$this->$lastCommentField = time();
				}

				$this->save();
			}
		}

		$this->onPublish( $member );

		/* Mark this item as read for the author */
		$this->markRead( $this->author() );

		/* Update search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( ( static::$firstCommentRequired ) ? $this->firstComment() : $this );
		}

		/* Send notifications if necessary */
		$this->sendNotifications();
		
		/* Give out points */
		$this->author()->achievementAction( 'core', 'NewContentItem', $this );
	}

	/**
	 * Syncing to run when publishing something previously pending publishing
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onPublish( $member )
	{
		if ( method_exists( $this, 'container' ) )
		{
			try
			{
				$container = $this->container();

				if ( $container->_futureItems !== NULL )
				{
					$container->_futureItems = ( $container->_futureItems > 0 ) ? $container->_futureItems - 1 : 0;
				}

				if( !$this->skipContainerRebuild )
				{
					$container->_items = $container->_items + 1;

					if ( isset( static::$commentClass ) )
					{
						$container->_comments = $container->_comments + $this->mapped('num_comments');
						$container->setLastComment();
					}
					if ( isset( static::$reviewClass ) )
					{
						$container->_reviews = $container->_reviews + $this->mapped('num_reviews');
						$container->setLastReview();
					}

					$container->save();
				}
			}
			catch ( \BadMethodCallException $e ) { }
		}
	}

	/**
	 * Syncing to run when unpublishing an item (making it a future dated entry when it was already published)
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onUnpublish( $member )
	{
		if ( method_exists( $this, 'container' ) )
		{
			try
			{
				$container = $this->container();

				if ( $container->_futureItems !== NULL )
				{
					$container->_futureItems = $container->_futureItems + 1;
				}

				$container->_items = $container->_items - 1;

				if ( isset( static::$commentClass ) )
				{
					$container->_comments = $container->_comments - $this->mapped('num_comments');
					$container->setLastComment();
				}
				if ( isset( static::$reviewClass ) )
				{
					$container->_reviews = $container->_reviews - $this->mapped('num_reviews');
					$container->setLastReview();
				}

				$container->save();
			}
			catch ( \BadMethodCallException $e ) { }
		}
	}

	/* !Ratings */
	
	/**
	 * Can Rate?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\BadMethodCallException
	 */
	public function canRate( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		switch ( $member->group['g_topic_rate_setting'] )
		{
			case 2:
				return TRUE;
			case 1:
				return $this->memberRating( $member ) === NULL;				
			default:
				return FALSE;
		}
	}
	
	/**
	 * @brief	Ratings submitted by members
	 */
	protected $memberRatings = array();
	
	/**
	 * Rating submitted by member
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @return	int|null
	 * @throws	\BadMethodCallException
	 */
	public function memberRating( \IPS\Member $member = NULL )
	{
		if ( !( $this instanceof \IPS\Content\Ratings ) )
		{
			throw new \BadMethodCallException;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		
		$idColumn = static::$databaseColumnId;
		if ( !array_key_exists( $member->member_id, $this->memberRatings ) )
		{
			try
			{
				$this->memberRatings[ $member->member_id ] = \intval( \IPS\Db::i()->select( 'rating', 'core_ratings', array( 'class=? AND item_id=? AND `member`=?', \get_called_class(), $this->$idColumn, $member->member_id ) )->first() );
			}
			catch ( \UnderflowException $e )
			{
				$this->memberRatings[ $member->member_id ] = NULL;
			}
		}
		
		return $this->memberRatings[ $member->member_id ];
	}

	/**
	 * @brief	Calculated average rating
	 */
	protected $_averageRating = NULL;
	
	/**
	 * Get average rating
	 *
	 * @return	float
	 * @throws	\BadMethodCallException
	 */
	public function averageRating()
	{
		if ( !( $this instanceof \IPS\Content\Ratings ) )
		{
			throw new \BadMethodCallException;
		}
				
		if ( isset( static::$databaseColumnMap['rating_average'] ) )
		{
			return (float) $this->mapped('rating_average');
		}
		elseif ( isset( static::$databaseColumnMap['rating_total'] ) and isset( static::$databaseColumnMap['rating_hits'] ) )
		{
			return $this->mapped('rating_hits') ? round( $this->mapped('rating_total') / $this->mapped('rating_hits'), 1 ) : 0;
		}
		else
		{
			if( $this->_averageRating === NULL )
			{
				$idColumn = static::$databaseColumnId;
				$this->_averageRating = round( \IPS\Db::i()->select( 'AVG(rating)', 'core_ratings', array( 'class=? AND item_id=?', \get_called_class(), $this->$idColumn ) )->first(), 1 );
			}

			return $this->_averageRating;
		}
	}

	/**
	 * Get number of ratings
	 *
	 * @return	float
	 * @throws	\BadMethodCallException
	 */
	public function numberOfRatings()
	{
		if ( !( $this instanceof \IPS\Content\Ratings ) )
		{
			throw new \BadMethodCallException;
		}
				
		if ( isset( static::$databaseColumnMap['rating_total'] ) and isset( static::$databaseColumnMap['rating_hits'] ) )
		{
			return $this->mapped('rating_hits') ?: 0;
		}
		else
		{
			$idColumn = static::$databaseColumnId;
			return \IPS\Db::i()->select( 'COUNT(*)', 'core_ratings', array( 'class=? AND item_id=?', \get_called_class(), $this->$idColumn ) )->first();
		}
	}
		
	/**
	 * Display rating (will just display stars if member cannot rate)
	 *
	 * @return	string
	 * @throws	\BadMethodCallException
	 */
	public function rating()
	{
		if ( !( $this instanceof \IPS\Content\Ratings ) )
		{
			throw new \BadMethodCallException;
		}

		if ( $this->canRate() )
		{
			$idColumn = static::$databaseColumnId;
						
			$form = new \IPS\Helpers\Form('rating');
			$averageRating = $this->averageRating();
			$form->add( new \IPS\Helpers\Form\Rating( 'rating', NULL, FALSE, array( 'display' => $averageRating, 'userRated' => $this->memberRating() ) ) );

			if ( $values = $form->values() )
			{
				\IPS\Db::i()->insert( 'core_ratings', array(
					'class'			=> \get_called_class(),
					'item_id'		=> $this->$idColumn,
					'member'		=> (int) \IPS\Member::loggedIn()->member_id,
					'rating'		=> $values['rating'],
					'ip'			=> \IPS\Request::i()->ipAddress(),
					'rating_date'	=> time()
				), TRUE );
				 
				if ( isset( static::$databaseColumnMap['rating_average'] ) )
				{
					$column = static::$databaseColumnMap['rating_average'];
					$this->$column = round( \IPS\Db::i()->select( 'AVG(rating)', 'core_ratings', array( 'class=? AND item_id=?', \get_called_class(), $this->$idColumn ) )->first(), 1 );
				}
				if ( isset( static::$databaseColumnMap['rating_total'] ) )
				{
					$column = static::$databaseColumnMap['rating_total'];
					$this->$column = \IPS\Db::i()->select( 'SUM(rating)', 'core_ratings', array( 'class=? AND item_id=?', \get_called_class(), $this->$idColumn ) )->first();
				}
				if ( isset( static::$databaseColumnMap['rating_hits'] ) )
				{
					$column = static::$databaseColumnMap['rating_hits'];
					$this->$column = \IPS\Db::i()->select( 'COUNT(*)', 'core_ratings', array( 'class=? AND item_id=?', \get_called_class(), $this->$idColumn ) )->first();
				}

				$this->save();

				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->json( 'OK' );
				}
			}
			
			return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'ratingTemplate' ) );
		}
		else
		{
			return \IPS\Theme::i()->getTemplate( 'global', 'core' )->rating( 'veryLarge', $this->averageRating(), 5, $this->memberRating() );
		}
	}
	
	/* !Sitemap */
	
	/**
	 * WHERE clause for getting items for sitemap (permissions are already accounted for)
	 *
	 * @return	array
	 */
	public static function sitemapWhere()
	{
		return array();
	}
	
	/**
	 * Sitemap Priority
	 *
	 * @return	int|NULL	NULL to use default
	 */
	public function sitemapPriority()
	{
		return NULL;
	}

	/**
	 * Retrieve any custom item_app_key_x values for item marking
	 *
	 * @param	int	$key	2 or 3 for respective column
	 * @return	void
	 * @note	This is abstracted to make it easier for apps to override
	 */
	public static function getItemMarkerKey( $key )
	{
		return 0;
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
		return \IPS\Theme::i()->getTemplate( 'global', 'core' )->embedItem( $this, $this->url()->setQueryString( $params ), $this->embedImage() );
	}
	
	/**
	 * Get image for embed
	 *
	 * @return	\IPS\File|NULL
	 */
	public function embedImage()
	{
		return NULL;
	}

	/**
	 * Return the first comment on the item
	 *
	 * @return \IPS\Content\Comment|NULL
	 */
	public function firstComment()
	{
		$comment		= NULL;
		$commentClass	= static::$commentClass;

		if( isset( static::$archiveClass ) AND method_exists( $this, 'isArchived' ) AND $this->isArchived() )
		{
			$commentClass	= static::$archiveClass;
		}

		/* If we map the first comment ID, load using that (if it's set) */
		if ( isset( static::$databaseColumnMap['first_comment_id'] ) )
		{
			$col = static::$databaseColumnMap['first_comment_id'];

			if( $this->$col )
			{
				try
				{
					$comment = $commentClass::load( $this->$col );
				}
				catch( \OutOfRangeException $e ){}
			}
		}

		/* If we still don't have the comment, load the old fashioned way */
		if( !$comment )
		{
			try
			{
				$idColumn	= static::$databaseColumnId;
				$comment	= $commentClass::constructFromData( \IPS\Db::i()->select( '*', $commentClass::$databaseTable, array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $this->$idColumn ), $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['date'] . ' ASC', 1 )->first() );

				/* If we do map the first_comment_id and we're here, it was either empty or wrong..let's fix that for next time */
				if ( isset( static::$databaseColumnMap['first_comment_id'] ) )
				{
					$col 				= static::$databaseColumnMap['first_comment_id'];
					$commentIdColumn	= $commentClass::$databaseColumnId;
					$this->$col = $comment->$commentIdColumn;
					$this->save();
				}
			}
			catch( \UnderflowException $e ){}
		}

		return $comment;
	}
	
	/* !MetaData */
	
	/**
	 * @brief	Meta Data Types
	 */
	public static $metaTypes = array( 'featured_comment', 'message' );
	
	/**
	 * Meta data types supported by this content
	 *
	 * @return	array|NULL
	 */
	public static function supportedMetaDataTypes()
	{
		return NULL;
	}
	
	/**
	 * Check if this content has meta data
	 *
	 * @return	bool
	 * @throws	\BadMethodCallException
	 */
	public function hasMetaData()
	{
		if ( !( $this instanceof \IPS\Content\MetaData ) )
		{
			throw new \BadMethodCallException;
		}
		
		$column = static::$databaseColumnMap['meta_data'];
		return (bool) $this->$column;
	}
	
	/**
	 * @brief	Meta Data Cache
	 */
	protected $_metaData = NULL;
	
	/**
	 * Fetch Meta Data
	 *
	 * @return	array
	 * @throws	\BadMethodCallException
	 */
	public function getMeta()
	{
		if ( !( $this instanceof \IPS\Content\MetaData ) )
		{
			throw new \BadMethodCallException;
		}
		
		/* If we don't have any, don't bother */
		if ( $this->hasMetaData() === FALSE )
		{
			return array();
		}
		
		$idColumn = static::$databaseColumnId;
		
		if ( $this->_metaData === NULL )
		{
			$this->_metaData = array();
			foreach( \IPS\Db::i()->select( '*', 'core_content_meta', array( "meta_class=? AND meta_item_id=?", \get_class( $this ), $this->$idColumn ) ) AS $row )
			{
				$this->_metaData[ $row['meta_type'] ][ $row['meta_id'] ] = json_decode( $row['meta_data'], TRUE );
			}
		}
		
		return $this->_metaData;
	}
	
	/**
	 * Add Meta Data
	 *
	 * @param	string	$type	The type of data
	 * @param	array	$data	The data
	 * @return	int		The ID of the inserted metadata record
	 * @throws	\BadMethodCallException
	 */
	public function addMeta( $type, $data )
	{
		if ( !static::supportedMetaDataTypes() OR !\in_array( $type, static::supportedMetaDataTypes() ) )
		{
			throw new \BadMethodCallException;
		}
		
		$idColumn = static::$databaseColumnId;
		$data = json_encode( $data );
		$id = \IPS\Db::i()->insert( 'core_content_meta', array(
			'meta_class'		=> \get_class( $this ),
			'meta_item_id'		=> $this->$idColumn,
			'meta_type'			=> $type,
			'meta_data'			=> $data,
			'meta_item_author'  => (int) $this->author()->member_id,
			'meta_added' 		=> time()
		) );
		
		$column = static::$databaseColumnMap['meta_data'];
		$this->$column = 1;
		$this->save();
		
		$this->_metaData = NULL;
		
		return $id;
	}
	
	/**
	 * Edit Meta Data
	 *
	 * @param	int		$id		The ID
	 * @param	array	$data	The data
	 * @return	void
	 * @throws \BadMethodCallException
	 */
	public function editMeta( $id, $data )
	{
		try
		{
			/* Get current data */
			$idColumn = static::$databaseColumnId;
			
			$current = json_decode( \IPS\Db::i()->select( 'meta_data', 'core_content_meta', array( "meta_class=? AND meta_item_id=? AND meta_id=?", \get_class( $this ), $this->$idColumn, $id ) )->first(), true );
			
			foreach ( $data as $key => $value )
			{
				$current[ $key ] = $value;
			}

			\IPS\Db::i()->update( 'core_content_meta', array( 'meta_data' => json_encode( $current ) ), array( "meta_id=?", $id ) );
			
			/* Make sure our flag is set */
			$column = static::$databaseColumnMap['meta_data'];
			$this->$column = TRUE;
			$this->save();
			
			$this->_metaData = NULL;
		}
		catch( \UnderflowException $e )
		{
			throw new \OutOfRangeException;
		}
	}
	
	/**
	 * Delete Meta Data
	 *
	 * @param	int		$id		The ID
	 * @return	void
	 */
	public function deleteMeta( $id )
	{
		$idColumn = static::$databaseColumnId;
		\IPS\Db::i()->delete( 'core_content_meta', array( "meta_class=? AND meta_item_id=? AND meta_id=?", \get_class( $this ), $this->$idColumn, $id ) );
		
		/* Any left? */
		$count = \IPS\Db::i()->select( 'COUNT(*)', 'core_content_meta', array( "meta_class=? AND meta_item_id=?", \get_class( $this ), $this->$idColumn ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		
		if ( !$count )
		{
			$column = static::$databaseColumnMap['meta_data'];
			$this->$column = FALSE;
			$this->save();
		}
		
		$this->_metaData = NULL;
	}
	
	/**
	 * Delete All Meta Data
	 *
	 * @return	void
	 */
	public function deleteAllMeta()
	{
		$idColumn = static::$databaseColumnId;
		\IPS\Db::i()->delete( 'core_content_meta', array( "meta_class=? AND meta_item_id=?", \get_class( $this ), $this->$idColumn ) );
	}
	
	/* ! Redirect links */
	
	/**
	 * Store a redirect
	 *
	 * Saves a redirect so when this class:item_id is attempted to be loaded in the future, it 301 redirects to the new item
	 *
	 * @param	\IPS\Content\Item	$item	The item to redirect to
	 */
	public function setRedirectTo( \IPS\Content\Item $item )
	{
		$idColumn = static::$databaseColumnId;
		\IPS\Db::i()->insert( 'core_item_redirect', array(
			'redirect_class'       => \get_class( $item ),
			'redirect_item_id'     => $this->$idColumn,
			'redirect_new_item_id' => $item->$idColumn
		) );
	}
	
	/**
	 * Get the redirect to data
	 *
	 * Fetches the \IPS\Content\Item we want to redirect to
	 *
	 * @param	object	$class		Class to look up
	 * @param	int		$id			The ID to look up
	 * @param	bool	$checkPerms	Check permissions when loading
	 * @throws	\OutOfRangeException
	 */
	public static function getRedirectFrom( $id, $checkPerms=TRUE )
	{
		$idColumn = static::$databaseColumnId;
		try
		{
			$method = ( $checkPerms ) ? 'loadAndCheckPerms' : 'load'; 
			return static::$method( \IPS\Db::i()->select( 'redirect_new_item_id', 'core_item_redirect', array( 'redirect_class=? and redirect_item_id=?', \get_called_class(), $id ) )->first() );
		}
		catch( \UnderflowException $e )
		{
			throw new \OutOfRangeException;
		}
	}
	
	/**
	 * Can perform an action on a message
	 *
	 * @param	string				$action	The action
	 * @param	\IPS\Member|NULL	$member	The member, or NULL for currently logged in
	 * @return	bool
	 * @note This is a wrapper for the extension so content items can extend and apply their own logic
	 */
	public function canOnMessage( $action, \IPS\Member $member = NULL )
	{
		return \IPS\Application::load('core')->extensions( 'core', 'MetaData' )['ContentMessages']->canOnMessage( $action, $this, $member );
	}
	
	/**
	 * Add Item Message
	 *
	 * @param	string				$message		The message
	 * @param	string				$color			The message color
	 * @param	\IPS|Member|NULL	$member			User adding the message
	 * @param	bool				$isPublic		Who should see the message
	 * @return	int
	 * @note This is a wrapper for the extension so content items can extend and apply their own logic
	 */
	public function addMessage( $message, $color = NULL, \IPS\Member $member = NULL, bool $isPublic = TRUE  )
	{
		return \IPS\Application::load('core')->extensions( 'core', 'MetaData' )['ContentMessages']->addMessage( $message, $color, $this, $member, $isPublic );
	}
	
	/**
	 * Edit Item Message
	 *
	 * @param	int					$id			The ID
	 * @param	string				$message	The new message
	 * @param	string|NULL			$color		Color
	 * @param	\IPS\Member|NULL	$member		The member editing the message, or NULL for currently logged in
	 * @param	bool				$onlyStaff		Who should see the message
	 * @return	void
	 * @note This is a wrapper for the extension so content items can extend and apply their own logic
	 */
	public function editMessage( $id, $message, $color = NULL, \IPS\Member $member = NULL, bool $onlyStaff = FALSE )
	{
		\IPS\Application::load('core')->extensions( 'core', 'MetaData' )['ContentMessages']->editMessage( $id, $message, $color, $this, $member, $onlyStaff );
	}
	
	/**
	 * Delete Item Message
	 *
	 * @param	int					$id		The ID
	 * @param	\IPS\Member|NULL	$member	The member deleting the message
	 * @note This is a wrapper for the extension so content items can extend and apply their own logic
	 */
	public function deleteMessage( $id, \IPS\Member $member = NULL )
	{
		\IPS\Application::load('core')->extensions( 'core', 'MetaData' )['ContentMessages']->deleteMessage( $id, $this, $member );
	}
	
	/**
	 * Get Item Messages
	 *
	 * @return	array
	 * @note This is a wrapper for the extension so content items can extend and apply their own logic
	 */
	public function getMessages()
	{
		return \IPS\Application::load('core')->extensions( 'core', 'MetaData' )['ContentMessages']->getMessages( $this );
	}
	
	/**
	 * Can Feature a Comment
	 *
	 * @param	\IPS\Member|NULL	$member	The member, or NULL for currently logged in
	 * @return	bool
	 * @note This is a wrapper for the extension so content items can extend and apply their own logic
	 */
	public function canFeatureComment( \IPS\Member $member = NULL )
	{
		return \IPS\Application::load('core')->extensions( 'core', 'MetaData' )['FeaturedComments']->canFeatureComment( $this, $member );
	}
	
	/**
	 * Can Unfeature a Comment
	 *
	 * @param	\IPS\Member|NULL	$member	The member, or NULL for currently logged in
	 * @return	bool
	 * @note This is a wrapper for the extension so content items can extend and apply their own logic
	 */
	public function canUnfeatureComment( \IPS\Member $member = NULL )
	{
		return \IPS\Application::load('core')->extensions( 'core', 'MetaData' )['FeaturedComments']->canUnfeatureComment( $this, $member );
	}
	
	/**
	 * Feature A Comment
	 *
	 * @param	\IPS\Content\Comment	$comment	The Comment
	 * @param	string|NULL				$note		An optional note to include
	 * @param	\IPS\Member|NULL		$member		The member featuring the comment
	 * @return	void
	 * @note This is a wrapper for the extension so content items can extend and apply their own logic
	 */
	public function featureComment( \IPS\Content\Comment $comment, $note = NULL, \IPS\Member $member = NULL )
	{
		\IPS\Application::load('core')->extensions( 'core', 'MetaData' )['FeaturedComments']->featureComment( $this, $comment, $note, $member );

		/* Give points */
		$comment->author()->achievementAction( 'core', 'ContentPromotion', [
			'content'	=> $comment,
			'promotype'	=> 'recommend' //Yeah it says feature, but it's really recommend
		] );
	}
	
	/**
	 * Unfeature a comment
	 *
	 * @param	\IPS\Content\Comment	$comment	The Comment
	 * @param	\IPS|Member|NULL		$member		The member unfeaturing the comment
	 * @return	void
	 * @note This is a wrapper for the extension so content items can extend and apply their own logic
	 */
	public function unfeatureComment( \IPS\Content\Comment $comment, \IPS\Member $member = NULL )
	{
		\IPS\Application::load('core')->extensions( 'core', 'MetaData' )['FeaturedComments']->unfeatureComment( $this, $comment, $member );
	}
	
	/**
	 * Get Featured Comments in the most efficient way possible
	 *
	 * @return	array
	 */
	public function featuredComments()
	{
		$featured = \IPS\Application::load('core')->extensions( 'core', 'MetaData' )['FeaturedComments']->featuredComments( $this );

		if ( \IPS\Request::i()->isAjax() && \IPS\Request::i()->recommended == 'comments' )
		{
			\IPS\Output::i()->json( array( 
				'html' => \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->featuredComments( $featured, $this->url()->setQueryString( 'recommended', 'comments' ) ),
				'count' => \count( $featured )
			) );
		}
		else
		{
			return $featured;
		}		
	}
	
	/**
	 * Is item-level moderation enabled?
	 *
	 * @param	\IPS\Member|\IPS\Member\Group|NULL	$memberOrGroup		A member of member group to check for bypassing moderation.
	 * @return	bool
	 */
	public function itemModerationEnabled( $memberOrGroup = NULL ): bool
	{
		return (bool) \IPS\Application::load('core')->extensions( 'core', 'MetaData' )['ItemModeration']->enabled( $this, $memberOrGroup );
	}
	
	/**
	 * Can toggle item-level moderation?
	 *
	 * @param	\IPS\Member|NULL		$member
	 * @return	bool
	 */
	public function canToggleItemModeration( ?\IPS\Member $member = NULL ): bool
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		return (bool) \IPS\Application::load('core')->extensions( 'core', 'MetaData' )['ItemModeration']->canToggle( $this, $member );
	}
	
	/**
	 * Toggle item-level moderation
	 *
	 * @param	string				$action
	 * @param	\IPS\Member|NULL		$member
	 * @return	void
	 */
	public function toggleItemModeration( $action, ?\IPS\Member $member = NULL )
	{
		if ( !\in_array( $action, array( 'enable', 'disable' ) ) )
		{
			throw new \InvalidArgumentException;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( !$this->canToggleItemModeration( $member ) )
		{
			throw new \BadMethodCallException;
		}
		
		\IPS\Application::load('core')->extensions( 'core', 'MetaData' )['ItemModeration']->$action( $this, $member );
	}

	/**
	 * Get widget sort options
	 *
	 * @return array
	 */
	public static function getWidgetSortOptions()
	{
		$sortOptions = array();
		foreach ( array( 'updated', 'title', 'num_comments', 'date', 'views', 'rating_average' ) as $k )
		{
			if ( isset( static::$databaseColumnMap[ $k ] ) )
			{
				$sortOptions[ static::$databaseColumnMap[ $k ] ] = 'sort_' . $k;
			}
		}

		return $sortOptions;
	}

	/**
	 * Give a content item the opportunity to filter similar content
	 * 
	 * @note Intentionally blank but can be overridden by child classes
	 * @return array|NULL
	 */
	public function similarContentFilter()
	{
		return NULL;
	}

	/**
	 * Return the form to merge two content items
	 *
	 * @return \IPS\Helpers\Form
	 */
	public function mergeForm()
	{
		$class = $this;

		$form = new \IPS\Helpers\Form( 'form', 'merge' );
		$form->class = 'ipsForm_vertical';
		$form->add( new \IPS\Helpers\Form\Url( 'merge_with', NULL, TRUE, array(), function ( $val ) use ( $class )
		{
			/* Load it */
			try
			{
				$toMerge = $class::loadFromUrl( $val );

				if ( !$toMerge->canView() )
				{
					throw new \OutOfRangeException;
				}

				/* Make sure the URL matches the content type we're merging */
				foreach( array( 'app', 'module', 'controller') as $index )
				{
					if( $toMerge->url()->hiddenQueryString[ $index ] != $val->hiddenQueryString[ $index ] )
					{
						throw new \OutOfRangeException;
					}
				}
			}
			catch ( \OutOfRangeException $e )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_url_bad_item', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title, FALSE, array( 'strtolower' => TRUE ) ) ) ) ) );
			}
			
			/* Make sure it isn't the same */
			if ( $toMerge == $class )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'cannot_merge_with_self' ) );
			}
			/* Or that it's a redirect link that is pointing to ourself */
			elseif( isset( static::$databaseColumnMap['moved_to'] ) AND $movedTo = $toMerge->mapped('moved_to') )
			{
				$movedToData	= explode( '&', $movedTo );
				$idColumn		= static::$databaseColumnId;

				if( $movedToData[0] == $class->$idColumn )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'cannot_merge_with_link_to_self' ) );
				}
			}
			/* Or that we're not a redirect link pointing to it */
			elseif( isset( static::$databaseColumnMap['moved_to'] ) AND $movedTo = $class->mapped('moved_to') )
			{
				$movedToData	= explode( '&', $movedTo );
				$idColumn		= static::$databaseColumnId;

				if( $movedToData[0] == $toMerge->$idColumn )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'cannot_merge_with_link_to_self' ) );
				}
			}

			/* And that we have permission */
			if ( !$toMerge->canMerge() )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'no_merge_permission', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title, FALSE, array( 'strtolower' => TRUE ) ) ) ) ) );
			}
		
		} ) );
		\IPS\Member::loggedIn()->language()->words['merge_with_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'merge_with__desc', FALSE, array( 'sprintf' => array( $this->definiteArticle(), $this->mapped( 'title' ) ) ) );
		if ( isset( static::$databaseColumnMap['moved_to'] ) )
		{
			$form->add( new \IPS\Helpers\Form\Checkbox( 'move_keep_link' ) );
			
			if ( \IPS\Settings::i()->topic_redirect_prune )
			{
				\IPS\Member::loggedIn()->language()->words['move_keep_link_desc'] = \IPS\Member::loggedIn()->language()->addToStack( '_move_keep_link_desc', FALSE, array( 'pluralize' => array( \IPS\Settings::i()->topic_redirect_prune ) ) );
			}
		}

		return $form;
	}

	/**
	 * Produce a random hex color for a background
	 *
	 * @return string
	 */
	public function coverPhotoBackgroundColor()
	{
		return $this->staticCoverPhotoBackgroundColor( $this->mapped('title') );
	}

	/**
	 * WHERE clause for getting items for digest (permissions are already accounted for)
	 *
	 * @return	array
	 */
	public static function digestWhere(): array
	{
		return array( );
	}

	/**
	 * Webhook filters
	 *
	 * @return	array
	 */
	public function webhookFilters()
	{
		$filters = parent::webhookFilters();

		if ( \in_array( 'IPS\Content\Lockable', class_implements( $this ) ) )
		{
			$filters['locked'] = $this->locked();
		}
		if ( \in_array( 'IPS\Content\Pinnable', class_implements( $this ) ) )
		{
			$filters['pinned'] = (bool) $this->mapped('pinned');
		}
		if ( \in_array( 'IPS\Content\Featurable', class_implements( $this ) ) )
		{
			$filters['featured'] = (bool) $this->mapped('featured');
		}
		if ( \in_array( 'IPS\Content\Polls', class_implements( $this ) ) )
		{
			$filters['hasPoll'] = (bool) $this->mapped('poll');
		}

		return $filters;
	}
}
