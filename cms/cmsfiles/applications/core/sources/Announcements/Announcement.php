<?php
/**
 * @brief		Announcement model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		22 Aug 2013
 */

namespace IPS\core\Announcements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Announcements Model
 */
class _Announcement extends \IPS\Content\Item
{
	/**
	 * @brief	Title-only announcement
	 */
	const TYPE_NONE = 0;

	/**
	 * @brief	Standard announcement (title and body)
	 */
	const TYPE_CONTENT = 1;

	/**
	 * @brief	URL announcement (title and link)
	 */
	const TYPE_URL = 2;

	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_announcements';

	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array( 'announcements' );

	/**
	 * @brief	Application
	 */
	public static $application = 'core';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'announce_';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
		
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
			'title'			=> 'title',
			'date'			=> 'start',
			'author'		=> 'member_id',
			'views'			=> 'views',
			'content'		=> 'content'
	);
	
	/**
	 * @brief	Title
	 */
	public static $title = 'announcement';
	
	/**
	 * @brief	Title
	 */
	public static $icon = 'bullhorn';

	/**
	 * Get page location array
	 *
	 * @return	array
	 */
	protected function get_page_location()
	{
		return explode( ',', $this->_data['page_location'] );
	}

	/**
	 * Get SEO name
	 *
	 * @return	string
	 */
	public function get_seo_title()
	{
		if( !$this->_data['seo_title'] )
		{
			$this->seo_title	= \IPS\Http\Url\Friendly::seoTitle( $this->title );
			$this->save();
		}

		return $this->_data['seo_title'] ?: \IPS\Http\Url\Friendly::seoTitle( $this->title );
	}

	/**
	 * Load announcements by page location
	 *
	 * @param	string		$location		Page location: top, content or sidebar
	 * @return	array
	 */
	public static function loadAllByLocation( $location )
	{
		/* Are we banned? If so, do not return any announcements */
		if ( \IPS\Member::loggedIn()->isBanned() )
		{
			return [];
		}

		$announcements = static::getStore();

		$return = array();
		foreach( $announcements as $announce )
		{
			$announcement = static::constructFromData( $announce );

			/* Check page location, active status and permissions */
			if( ! \in_array( $location, $announcement->page_location ) OR !$announcement->active OR ( $announcement->start !== 0 AND $announcement->start >= time() ) OR ( $announcement->end !== 0 AND $announcement->end <= time() ) OR !$announcement->canView() )
			{
				continue;
			}

			/* Is this a global announcement? */
			if( $announcement->app == '*' )
			{
				$return[] = $announcement;
				continue;
			}

			/* If the dispatcher did not finish loading (perhaps we hit an error that occurred early in execution) we can't check per-app locations */
			if( !\IPS\Dispatcher::i()->application )
			{
				continue;
			}

			$extensions = \IPS\Dispatcher::i()->application->extensions( 'core', 'Announcements' );

			/* If we have no extension, we can only check the global setting */
			if ( !$extensions AND $announcement->app == \IPS\Dispatcher::i()->application->directory )
			{
				$return[] = $announcement;
			}
			else
			{
				/* App and container specific announcements */
				foreach ( $extensions as $key => $extension )
				{
					$id = $extension::$idField;

					if ( $announcement->ids AND isset( \IPS\Request::i()->$id ) )
					{
						/* Are we viewing a content item */
						if ( \IPS\Dispatcher::i()->dispatcherController instanceof \IPS\Content\Controller )
						{
							foreach( \IPS\Dispatcher::i()->application->extensions( 'core', 'ContentRouter' ) AS $contentRouter )
							{
								foreach( $contentRouter->classes AS $class )
								{
									try
									{
										if( $announcement->location == $key AND \in_array( $class::load( \IPS\Request::i()->$id )->mapped('container'), explode( ',', $announcement->ids ) ) )
										{
											$return[] = $announcement;
										}
									}
									catch( \OutOfRangeException $e ){}
								}
							}
						}
						/* Or are we inside an allowed controller */
						else if (isset( \IPS\Dispatcher::i()->dispatcherController ) AND \in_array( \get_class( \IPS\Dispatcher::i()->dispatcherController ), $extension::$controllers ) )
						{
							try
							{
								if( $announcement->location == $key AND \in_array(  \IPS\Request::i()->$id , explode( ',', $announcement->ids ) ) )
								{
									$return[] = $announcement;
								}
							}
							catch( \OutOfRangeException $e ){}
						}
					}
					/* App specific, doesn't matter which container we're in */
					elseif( $announcement->location == $key AND !$announcement->ids )
					{
						$return[] = $announcement;
					}
				}
			}
		}

		return $return;
	}

	/**
	 * Display Form
	 *
	 * @param	static|NULL	$announcement	Existing announcement (for edits)
	 * @return	\IPS\Helpers\Form
	 */
	public static function form( $announcement )
	{
		/* Build the form */
		$form = new \IPS\Helpers\Form( NULL, 'save' );
		$form->class = 'ipsForm_vertical';
		
		$form->add( new \IPS\Helpers\Form\Text( 'announce_title', ( $announcement ) ? $announcement->title : NULL, TRUE, array( 'maxLength' => 255 ) ) );
		$form->add( new \IPS\Helpers\Form\Date( 'announce_start', ( $announcement ) ? \IPS\DateTime::ts( $announcement->start ) : new \IPS\DateTime ) );
		$form->add( new \IPS\Helpers\Form\Date( 'announce_end', ( $announcement AND $announcement->end ) ? \IPS\DateTime::ts( $announcement->end ) : 0, FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'indefinitely' ) ) );

		$form->add( new \IPS\Helpers\Form\Radio( 'announce_type', ( $announcement ) ? $announcement->type : 'content', TRUE, array(
			'options' => array(
				static::TYPE_NONE		=> \IPS\Member::loggedIn()->language()->addToStack( 'announce_type_none' ),
				static::TYPE_CONTENT	=> \IPS\Member::loggedIn()->language()->addToStack( 'announce_type_content' ),
				static::TYPE_URL		=> \IPS\Member::loggedIn()->language()->addToStack( 'announce_type_url' ),
			),
			'toggles' => array(
				static::TYPE_CONTENT 	=> array( 'announce_content' ),
				static::TYPE_URL 		=> array( 'announce_url' )
			 ),
		) ) );

		$form->add( new \IPS\Helpers\Form\Editor( 'announce_content', ( $announcement ) ? $announcement->content : NULL, NULL, array( 'app' => 'core', 'key' => 'Announcement', 'autoSaveKey' => ( $announcement ? 'editAnnouncement__' . $announcement->id : 'createAnnouncement' ), 'attachIds' => $announcement ? array( $announcement->id, NULL, 'announcement' ) : NULL ), NULL, NULL, NULL, 'announce_content' ) );
		$form->add( new \IPS\Helpers\Form\Url( 'announce_url', ( $announcement ) ? $announcement->url : NULL, NULL, array( 'maxLength' => 2048 ), NULL, NULL, NULL, 'announce_url' ) );
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'announce_page_location', ( $announcement ) ? $announcement->page_location : array(), TRUE, array( 'options' => array( 'top' => 'page_top', 'content' => 'content_top', 'sidebar' => 'sidebar' ) ) ) );

		/* Apps */
		$apps = array();
		
		foreach( \IPS\Application::applications() as $key => $data )
		{
			if ( $key != 'core' )
			{
				/* Don't list apps without front modules or that are not being revealed */
				if( !\count( $data->modules( 'front' ) ) OR $data->hide_tab )
				{
					continue;
				}
				$apps[ $key ] = $data->_title;
			}
		}
		$apps['core'] = 'announce_other_areas';
		
		$toggles = array();
		$formFields = array();
		
		foreach ( \IPS\Application::allExtensions( 'core', 'Announcements', TRUE, 'core' ) as $key => $extension )
		{
			$app = mb_substr( $key, 0, mb_strpos( $key, '_' ) );

			if( method_exists( $extension, 'getSettingField' ) )
			{
				/* Grab our fields and add to the form */
				$field	= $extension->getSettingField( $announcement );
				
				$toggles[ $app ][] = $field->name;
				$formFields[] = $field;
			}
		}
		
		$form->add( new \IPS\Helpers\Form\Select( 'announce_app', ( $announcement ) ? $announcement->app : '*', TRUE, array( 'options' => $apps,'toggles' => $toggles, 'unlimited' => "*", 'unlimitedLang' => "everywhere" ) ) );

		foreach( $formFields as $field )
		{
			$form->add( $field );
		}

		$groups = array();
		foreach ( \IPS\Member\Group::groups() as $group )
		{
			$groups[ $group->g_id ] = $group->name;
		}

		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'announce_permissions', $announcement ? ( $announcement->permissions == '*' ? '*' : explode( ',', $announcement->permissions ) ) : '*', NULL, array( 'multiple' => TRUE, 'options' => $groups, 'unlimited' => '*', 'unlimitedLang' => 'everyone', 'impliedUnlimited' => TRUE ) ) );
		$form->add( new \IPS\Helpers\Form\Custom( 'announce_color', $announcement ? $announcement->color : 'information', NULL, array( 'getHtml' => function( $element )
        {
            return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'front' )->colorSelection( $element->name, $element->value );
        } ), NULL, NULL, NULL, 'announce_color' ) );

		return $form;
	}

	/**
	 * Get data store
	 *
	 * @return	array
	 */
	public static function getStore()
	{
		if ( !isset( \IPS\Data\Store::i()->announcements ) )
		{
			\IPS\Data\Store::i()->announcements = iterator_to_array( \IPS\Db::i()->select( '*', static::$databaseTable, NULL, "announce_id ASC" )->setKeyField( 'announce_id' ) );
		}

		return \IPS\Data\Store::i()->announcements;
	}
	
	/**
	 * Create from form
	 *
	 * @param	array	$values	Values from form
	 * @param	\IPS\core\Announcements\Announcement|NULL $current	Current announcement
	 * @return	\IPS\core\Announcements\Announcement
	 */
	public static function _createFromForm( $values, $current )
	{
		if( $current )
		{
			$obj = static::load( $current->id );
		}
		else
		{
			$obj = new static;
			$obj->member_id = \IPS\Member::loggedIn()->member_id;
		}

		$obj->title			= $values['announce_title'];
		$obj->seo_title 	= \IPS\Http\Url\Friendly::seoTitle( $values['announce_title'] );
		$obj->type			= $values['announce_type'];
		$obj->content		= $values['announce_content'];
		$obj->url			= $values['announce_url'];
		$obj->start			= $values['announce_start'] ? $values['announce_start']->getTimestamp() : time();
		$obj->end			= $values['announce_end'] ? $values['announce_end']->getTimestamp() : 0;
		$obj->app			= $values['announce_app'] ? $values['announce_app'] : "*";
		$obj->permissions	= \is_array( $values['announce_permissions'] ) ? implode( ',', $values['announce_permissions'] ) : '*';
		$obj->page_location = $values['announce_page_location'];
		$obj->color			= $values['announce_color'];

		/* We need to set the data, before we then iterate over the toggled extension form fields */
		$obj->location = "*";
		$obj->ids = NULL;

        if( \in_array( $obj->app, array_keys(\IPS\Application::applications() ) ) )
        {
            foreach ( \IPS\Application::load( $obj->app )->extensions( 'core', 'Announcements' ) as $key => $extension )
            {
                if( method_exists( $extension, 'getSettingField' ) )
                {
                    $field	= $extension->getSettingField( array() );
                    $obj->ids = \is_array( $values[ $field->name ] ) ? implode( ",", array_keys( $values[ $field->name ] ) ) : $values[ $field->name ];
                    $obj->location = mb_substr( $key, mb_strpos( $key, '_' ) );
                }
            }
        }
		
		$obj->save();
		
		if( !$current )
		{
			\IPS\File::claimAttachments( 'createAnnouncement', $obj->id, NULL, 'announcement' );
		}
		
		return $obj;
	}

	/**
	 * @brief	Cached URLs
	 */
	protected $_url	= array();

	/**
	 * Get URL
	 *
	 * @param	string|NULL		$action		Action
	 * @return	\IPS\Http\Url
	 */
	public function url( $action=NULL )
	{
		$_key	= md5( $action );

		if( !isset( $this->_url[ $_key ] ) )
		{
			if( $action )
			{
				$this->_url[ $_key ] = \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=announcements&id={$this->id}", 'front', 'modcp_announcements' );
				$this->_url[ $_key ] = $this->_url[ $_key ]->setQueryString( 'action', $action );
			}
			else
			{
				$this->_url[ $_key ] = \IPS\Http\Url::internal( "app=core&module=system&controller=announcement&id={$this->id}", 'front', 'announcement', $this->seo_title );
			}
		}
	
		return $this->_url[ $_key ];
	}

	/**
	 * Get owner
	 *
	 * @return	\IPS\Member
	 */
	public function owner()
	{
		return \IPS\Member::load( $this->member_id );
	}
	
	/**
	 * Unclaim attachments
	 *
	 * @return	void
	 */
	protected function unclaimAttachments()
	{
		\IPS\File::unclaimAttachments( 'core_Announcement', $this->id, NULL, 'announcement' );
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
		if( \in_array( $type, array( 'move', 'merge', 'lock', 'unlock', 'feature', 'unfeature', 'pin', 'unpin' ) ) )
		{
			return FALSE;
		}

		if( $type == 'hide' OR $type == 'unhide' OR $type == 'active' OR $type == 'inactive' )
		{
			$member = $member ?: \IPS\Member::loggedIn();
			return $member->modPermission( "can_manage_announcements" );
		}

		return parent::modPermission( $type, $member, $container );
	}

	/**
	 * Return the filters that are available for selecting table rows
	 *
	 * @return	array
	 */
	public static function getTableFilters()
	{
		return array(
			'active', 'inactive'
		);
	}

	/**
	 * Get content table states
	 *
	 * @return string
	 */
	public function tableStates()
	{
		$states = explode( ' ', parent::tableStates() );

		if( !$this->active )
		{
			$states[]	= "inactive";
		}
		else
		{
			$states[]	= "active";
		}

		return implode( ' ', $states );
	}

	/**
	 * Do Moderator Action
	 *
	 * @param	string				$action	The action
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @param	string|NULL			$reason	Reason (for hides)
	 * @param	bool				$immediately	Delete immediately
	 * @return	void
	 * @throws	\OutOfRangeException|\InvalidArgumentException|\RuntimeException
	 */
	public function modAction( $action, \IPS\Member $member = NULL, $reason = NULL, $immediately = FALSE )
	{
		if( static::modPermission( $action, $member ) )
		{
			if( $action == 'active' )
			{
				\IPS\Session::i()->modLog( 'modlog__action_announceactive', array( static::$title => TRUE, $this->url()->__toString() => FALSE, $this->mapped('title') => FALSE ), $this );

				$this->active	= 1;
				$this->save();

				return;
			}

			if( $action == 'inactive' )
			{
				\IPS\Session::i()->modLog( 'modlog__action_announceinactive', array( static::$title => TRUE, $this->url()->__toString() => FALSE, $this->mapped('title') => FALSE ), $this );

				$this->active	= 0;
				$this->save();

				return;
			}
		}

		return parent::modAction( $action, $member, $reason, $immediately );
	}

	/**
	 * Return any custom multimod actions this content item class supports
	 *
	 * @return	array
	 */
	public function customMultimodActions()
	{
		if( !$this->active )
		{
			return array( "active" );
		}
		else
		{
			return array( "inactive" );
		}
	}

	/**
	 * Can view?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for or NULL for the currently logged in member
	 * @return	bool
	 */
	public function canView( $member=NULL )
	{
		/* If all groups have access, we can */
		if( $this->permissions == '*' )
		{
			return TRUE;
		}

		/* Check member */
		$member	= ( $member === NULL ) ? \IPS\Member::loggedIn() : $member;
		$memberGroups	= array_merge( array( $member->member_group_id ), array_filter( explode( ',', $member->mgroup_others ) ) );
		$accessGroups	= explode( ',', $this->permissions );

		/* Are we in an allowed group? */
		if( \count( array_intersect( $accessGroups, $memberGroups ) ) )
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Return any available custom multimod actions this content item class supports
	 *
	 * @note	Return in format of array( array( 'action' => ..., 'icon' => ..., 'language' => ... ) )
	 * @return	array
	 */
	public static function availableCustomMultimodActions()
	{
		return array(
			array(
				'groupaction'	=> 'active',
				'icon'			=> 'eye',
				'grouplabel'	=> 'announce_active_status',
				'action'		=> array(
					array(
						'action'	=> 'active',
						'icon'		=> 'eye',
						'label'		=> 'announce_mark_active'
					),
					array(
						'action'	=> 'inactive',
						'icon'		=> 'eye',
						'label'		=> 'announce_mark_inactive'
					)
				)
			)
		);
	}

	/**
	 * Set page location array
	 *
	 * @param	array		$value	Page locations: top, content and sidebar
	 * @return	void
	 */
	protected function set_page_location( $value )
	{
		$this->_data['page_location'] = implode( ',', $value );
	}
}