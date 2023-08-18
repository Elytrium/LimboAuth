<?php
/**
 * @brief		Notification Settings Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 Aug 2013
 */
 
namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Notification Settings Controller
 */
class _notifications extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 */
	protected function _checkLoggedIn()
	{
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2C154/2', 403, '' );
		}
		
		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_system.js', 'core' ) );
		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/notification_settings.css' ) );
		if ( \IPS\Theme::i()->settings['responsive'] )
        {
            \IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/notification_settings_responsive.css' ) );
        }
	}
	
	/**
	 * View Notifications
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$this->_checkLoggedIn();

		/* Init table */
		$urlObject	= \IPS\Http\Url::internal( 'app=core&module=system&controller=notifications', 'front', 'notifications' );
		$table = new \IPS\Notification\Table( $urlObject );
		$table->setMember( \IPS\Member::loggedIn() );		
		
		$notifications = $table->getRows();
	
		\IPS\Db::i()->update( 'core_notifications', array( 'read_time' => time() ), array( '`member`=?', \IPS\Member::loggedIn()->member_id ) );
		\IPS\Member::loggedIn()->recountNotifications();
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'data' => \IPS\Theme::i()->getTemplate( 'system' )->notificationsAjax( $notifications ) ) );
		}
		else
		{
			\IPS\Output::i()->metaTags['robots'] = 'noindex';
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('notifications');
			\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Output::i()->title );
			\IPS\Output::i()->output = (string) $table;
		}
	}
	
	/**
	 * Subscribe a user's device to push notifications
	 *
	 * @return	void
	 */
	protected function subscribeToPush()
	{
		$this->_checkLoggedIn();
		\IPS\Session::i()->csrfCheck();
		
		if ( isset( \IPS\Request::i()->subscription ) AND $subscription = json_decode( \IPS\Request::i()->subscription, TRUE ) )
		{
			$device = \IPS\Member\Device::loadOrCreate( \IPS\Member::loggedIn(), FALSE );
			/* If a subscription already exists, then just return */
			try
			{
				\IPS\Db::i()->select( '*', 'core_notifications_pwa_keys', array( "`member`=? AND p256dh=? AND auth=?", \IPS\Member::loggedIn()->member_id, $subscription['keys']['p256dh'], $subscription['keys']['auth'] ) )->first();
				
				/* Make sure encoding and device is up to date, though since it's needed for encryption transfer. */
				\IPS\Db::i()->update( 'core_notifications_pwa_keys', array(
					'encoding'		=> \IPS\Request::i()->encoding,
					'device'		=> $device->device_key
				), array( "`member`=? AND p256dh=? AND auth=?", \IPS\Member::loggedIn()->member_id, $subscription['keys']['p256dh'], $subscription['keys']['auth'] ) );
			}
			catch( \UnderflowException $e )
			{
				\IPS\Db::i()->insert( 'core_notifications_pwa_keys', array(
					'member'		=> \IPS\Member::loggedIn()->member_id,
					'endpoint'		=> $subscription['endpoint'],
					'p256dh'		=> $subscription['keys']['p256dh'],
					'auth'			=> $subscription['keys']['auth'],
					'encoding'		=> \IPS\Request::i()->encoding,
					'device'		=> $device->device_key
				) );
			}
			
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->error( 'invalid_push_subscription', '2C154/H', 403, '' );
		}
	}

	/**
	 * Verify a subscription with the provided key exists for the logged-in user
	 *
	 * @return	void
	 */
	protected function verifySubscription()
	{
		$this->_checkLoggedIn();
		\IPS\Session::i()->csrfCheck();
		
		if ( isset( \IPS\Request::i()->key ) )
		{
			/* See if a subscription already exists */
			try
			{
				\IPS\Db::i()->select( '*', 'core_notifications_pwa_keys', array( "`member`=? AND p256dh=?", \IPS\Member::loggedIn()->member_id, \IPS\Request::i()->key ) )->first();
				\IPS\Output::i()->json( 'OK' );
			}
			catch( \UnderflowException $e )
			{
				// Just let it return the error below
			}
		}

		\IPS\Output::i()->error( 'invalid_push_subscription', '2C154/I', 403, '' );			
	}
	
	/**
	 * Options: Dispatcher
	 *
	 * @return	void
	 */
	protected function options()
	{
		/* Check we're logged in */
		$this->_checkLoggedIn();
		
		/* Init breadcrumb */
		$extensions = \IPS\Application::allExtensions( 'core', 'Notifications' );
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=system&controller=notifications', 'front', 'notifications' ), \IPS\Member::loggedIn()->language()->addToStack('notifications') );
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=system&controller=notifications&do=options', 'front', 'notifications_options' ), \IPS\Member::loggedIn()->language()->addToStack('options') );

		/* Are we viewing a particular type? */
		if ( isset( \IPS\Request::i()->type ) and array_key_exists( \IPS\Request::i()->type, $extensions ) )
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( "notifications__" . \IPS\Request::i()->type );
			\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( "notifications__" . \IPS\Request::i()->type ) );
			return $this->_optionsType( \IPS\Request::i()->type, $extensions[ \IPS\Request::i()->type ] );
		}
				
		/* Nope, viewing the index */
		else
		{			
			return $this->_optionsIndex( $extensions );
		}
	}
	
	/**
	 * Options: Index
	 *
	 * @param	array	$extensions	The extensions
	 * @return	void
	 */
	protected function _optionsIndex( $extensions )
	{		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('notification_options');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('system')->notificationSettingsIndex( \IPS\Notification::membersOptionCategories( \IPS\Member::loggedIn(), $extensions ) );
		\IPS\Output::i()->globalControllers[] = 'core.front.system.notificationSettings';
	}
	
	/**
	 * Options: Index
	 *
	 * @param	string	$extensionKey	The extension key
	 * @param	object	$extension		The extension
	 * @return	void
	 */
	protected function _optionsType( $extensionKey, $extension )
	{
		$form = \IPS\Notification::membersTypeForm( \IPS\Member::loggedIn(), $extension );
		if ( $form === TRUE )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				$categories = \IPS\Notification::membersOptionCategories( \IPS\Member::loggedIn(), array( $extensionKey => $extension ) );
				\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate('system')->notificationSettingsIndexRowDetails( $extensionKey, $categories[ $extensionKey ] ), 200, 'text/html', \IPS\Output::i()->httpHeaders );	
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=notifications&do=options', 'front', 'notifications_options' ), 'saved' );
			}
		}
		elseif ( $form )
		{
			$form->class = 'ipsForm_vertical';
		}
		
		if ( \IPS\Request::i()->isAjax() )
		{
			$form->actionButtons = array();
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate('system')->notificationSettingsType( \IPS\Member::loggedIn()->language()->addToStack("notifications__{$extensionKey}"), $form, TRUE ), 200, 'text/html', \IPS\Output::i()->httpHeaders );	
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('system')->notificationSettingsType( \IPS\Member::loggedIn()->language()->addToStack("notifications__{$extensionKey}"), $form, FALSE );
		}
	}
	
	/**
	 * Stop receiving all notifications to a particular method
	 *
	 * @return void
	 */
	protected function disable()
	{
		$this->_checkLoggedIn();
		\IPS\Session::i()->csrfCheck();
		
		foreach ( \IPS\Application::allExtensions( 'core', 'Notifications' ) as $extension )
		{
			$options = \IPS\Notification::availableOptions( \IPS\Member::loggedIn(), $extension );
			foreach ( $options as $option )
			{
				if ( $option['type'] === 'standard' )
				{
					$value = array();
					foreach ( $option['options'] as $k => $optionDetails )
					{
						if ( ( $optionDetails['editable'] and $k !== \IPS\Request::i()->type and $optionDetails['value'] ) or ( !$optionDetails['editable'] and $optionDetails['value'] ) )
						{
							$value[] = $k;
						}
					}
					
					foreach ( $option['notificationTypes'] as $notificationKey )
					{
						\IPS\Db::i()->insert( 'core_notification_preferences', array(
							'member_id'			=> \IPS\Member::loggedIn()->member_id,
							'notification_key'	=> $notificationKey,
							'preference'		=> implode( ',', $value )
						), TRUE );
					}
				}
			}
			
			if ( method_exists( $extension, 'disableExtra' ) )
			{
				$extension::disableExtra( \IPS\Member::loggedIn(), \IPS\Request::i()->type );
			}
		}

		if ( \IPS\Request::i()->type === 'push' )
		{
			\IPS\Member::loggedIn()->clearPwaAuths();
		}

		/* Digests */
		\IPS\Db::i()->update( 'core_follow', array( 'follow_notify_freq' => 'none'), array( 'follow_member_id=? AND follow_notify_freq IN(?,?)', \IPS\Member::loggedIn()->member_id, "daily", "weekly" ) );

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'ok' );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=notifications&do=options', 'front', 'notifications_options' ) );
		}
	}
	
	/**
	 * Follow Something
	 *
	 * @return	void
	 */
	protected function follow()
	{
		$this->_checkLoggedIn();

		try
		{
			$application = \IPS\Application::load( \IPS\Request::i()->follow_app );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'error_no_app', '3C154/F', 404, '' );
		}

		/* Get class */
		$class = NULL;
		foreach ( $application->extensions( 'core', 'ContentRouter' ) as $ext )
		{
			foreach ( $ext->classes as $classname )
			{
				if ( $classname == 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . mb_ucfirst( \IPS\Request::i()->follow_area ) )
				{
					$class = $classname;
					break;
				}
				if ( isset( $classname::$containerNodeClass ) and $classname::$containerNodeClass == 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . mb_ucfirst( \IPS\Request::i()->follow_area ) )
				{
					$class = $classname::$containerNodeClass;
					break;
				}
				if( isset( $classname::$containerFollowClasses ) )
				{
					foreach( $classname::$containerFollowClasses as $followClass )
					{
						if( $followClass == 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . mb_ucfirst( \IPS\Request::i()->follow_area ) )
						{
							$class = $followClass;
							break;
						}
					}
				}
			}
		}
		
		if( \IPS\Request::i()->follow_app == 'core' and \IPS\Request::i()->follow_area == 'member' )
		{
			/* You can't follow yourself */
			if( \IPS\Request::i()->follow_id == \IPS\Member::loggedIn()->member_id )
			{
				\IPS\Output::i()->error( 'cant_follow_self', '3C154/7', 403, '' );
			}
			
			/* Following disabled */
			$member = \IPS\Member::load( \IPS\Request::i()->follow_id );

			if( !$member->member_id )
			{
				\IPS\Output::i()->error( 'cant_follow_member', '3C154/9', 403, '' );
			}

			if( $member->members_bitoptions['pp_setting_moderate_followers'] and !\IPS\Member::loggedIn()->following( 'core', 'member', $member->member_id ) )
			{
				\IPS\Output::i()->error( 'cant_follow_member', '3C154/8', 403, '' );
			}
				
			$class = 'IPS\\Member';
		}
		
		if( \IPS\Request::i()->follow_app == 'core' and \IPS\Request::i()->follow_area == 'club' )
		{
			$class = 'IPS\Member\Club';
		}
		
		if ( !$class )
		{
			\IPS\Output::i()->error( 'node_error', '2C154/3', 404, '' );
		}
		
		/* Get thing */
		$thing = NULL;
		try
		{
			if ( \in_array( 'IPS\Node\Model', class_parents( $class ) ) )
			{
				$thing = $class::loadAndCheckPerms( (int) \IPS\Request::i()->follow_id );
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'follow_thing', FALSE, array( 'sprintf' => array( $thing->_title ) ) );

				/* Set navigation */
				try
				{
					foreach ( $thing->parents() as $parent )
					{
						\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
					}
					\IPS\Output::i()->breadcrumb[] = array( NULL, $thing->_title );
				}
				catch ( \Exception $e ) { }
			}
			elseif ( $class == 'IPS\Member\Club' )
			{
				$thing = $class::loadAndCheckPerms( (int) \IPS\Request::i()->follow_id );
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'follow_thing', FALSE, array( 'sprintf' => array( $thing->_title ) ) );
				\IPS\Output::i()->breadcrumb = array(
					array( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs') ),
					array( $thing->url(), $thing->name )
				);
			}
			elseif ( $class != "IPS\Member" )
			{	
				if( !is_subclass_of( $class, "\IPS\Content\Followable" ) )
				{
					throw new \OutOfRangeException;
				}
					
				$thing = $class::loadAndCheckPerms( (int) \IPS\Request::i()->follow_id );
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'follow_thing', FALSE, array( 'sprintf' => array( $thing->mapped('title') ) ) );

				/* Set navigation */
				$container = NULL;
				try
				{
					$container = $thing->container();
					foreach ( $container->parents() as $parent )
					{
						\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
					}
					\IPS\Output::i()->breadcrumb[] = array( $container->url(), $container->_title );
				}
				catch ( \Exception $e ) { }
				
				/* Set meta tags */
				\IPS\Output::i()->breadcrumb[] = array( NULL, $thing->mapped('title') );
			}
			else 
			{
				$thing = $class::load( (int) \IPS\Request::i()->follow_id );				
				
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('follow_thing', FALSE, array( 'sprintf' => array( $thing->name ) ) );

				/* Set navigation */
				\IPS\Output::i()->breadcrumb[] = array( NULL, $thing->name );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C154/4', 404, '' );
		}
		
		/* Do we follow it? */
		try
		{
			$current = \IPS\Db::i()->select( '*', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=? AND follow_member_id=?', \IPS\Request::i()->follow_app, \IPS\Request::i()->follow_area, \IPS\Request::i()->follow_id, \IPS\Member::loggedIn()->member_id ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			$current = FALSE;
		}
				
		/* How do we receive notifications? */
		if ( $class == 'IPS\Member' )
		{
			$type = 'follower_content';
		}
		elseif ( $class == 'IPS\Member\Club' or \in_array( 'IPS\Content\Item', class_parents( $class ) ) )
		{
			$type = 'new_comment';
		}
		else
		{
			$type = 'new_content';
		}
		$notificationConfiguration = \IPS\Member::loggedIn()->notificationsConfiguration();
		$notificationConfiguration = isset( $notificationConfiguration[ $type ] ) ? $notificationConfiguration[ $type ] : array();
		$lang = 'follow_type_immediate';
		if ( \in_array( 'email', $notificationConfiguration ) and \in_array( 'inline', $notificationConfiguration ) )
		{
			$lang = 'follow_type_immediate_inline_email';
		}
		elseif ( \in_array( 'email', $notificationConfiguration ) )
		{
			$lang = 'follow_type_immediate_email';
		}
		
		if ( $class == "IPS\Member" )
		{
			\IPS\Member::loggedIn()->language()->words[ $lang ] = \IPS\Member::loggedIn()->language()->addToStack( $lang . '_member', FALSE, array( 'sprintf' => array( $thing->name ) ) );
		}
		
		if ( empty( $notificationConfiguration ) )
		{
			\IPS\Member::loggedIn()->language()->words[ $lang . '_desc' ] = \IPS\Member::loggedIn()->language()->addToStack( 'follow_type_immediate_none', FALSE ) . ' <a href="' .  \IPS\Http\Url::internal( 'app=core&module=system&controller=notifications&do=options&type=core_Content', 'front', 'notifications_options' ) . '">' . \IPS\Member::loggedIn()->language()->addToStack( 'notification_options', FALSE ) . '</a>';
		}
		else
		{
			\IPS\Member::loggedIn()->language()->words[ $lang . '_desc' ] = '<a href="' .  \IPS\Http\Url::internal( 'app=core&module=system&controller=notifications&do=options&type=core_Content', 'front', 'notifications_options' ) . '">' . \IPS\Member::loggedIn()->language()->addToStack( 'follow_type_immediate_change', FALSE ) . '</a>';
		}
			
		/* Build form */
		$form = new \IPS\Helpers\Form( 'follow', ( $current ) ? 'update_follow' : 'follow', NULL, array(
			'data-followApp' 	=> \IPS\Request::i()->follow_app,
			'data-followArea' 	=> \IPS\Request::i()->follow_area,
			'data-followID' 	=> \IPS\Request::i()->follow_id
		) );

		$form->class = 'ipsForm_vertical';
		
		$options = array();
		$options['immediate'] = $lang;
		
		if ( $class != "IPS\Member" )
		{
			if ( $class != "IPS\Member\Club" )
			{
				$options['daily']	= \IPS\Member::loggedIn()->language()->addToStack('follow_type_daily');
				$options['weekly']	= \IPS\Member::loggedIn()->language()->addToStack('follow_type_weekly');
			}
			$options['none']	= \IPS\Member::loggedIn()->language()->addToStack('follow_type_no_notification');
		}
		
		if ( \count( $options ) > 1 )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'follow_type', $current ? $current['follow_notify_freq'] : NULL, TRUE, array(
				'options'	=> $options,
				'disabled'	=> empty( $notificationConfiguration ) ? array( 'immediate' ) : array()
			) ) );
		}
		else
		{
			foreach ( $options as $k => $v )
			{
				$form->hiddenValues[ $k ] = $v;
				if ( empty( $notificationConfiguration ) )
				{
					$type = $type == 'follower_content' ? 'core_Content' : $type;
					$form->addMessage( \IPS\Member::loggedIn()->language()->addToStack( 'follow_type_no_config' ) . ' <a href="' .  \IPS\Http\Url::internal( 'app=core&module=system&controller=notifications&do=options&type=' . $type, 'front', 'notifications_options' ) . '">' . \IPS\Member::loggedIn()->language()->addToStack( 'notification_options', FALSE ) . '</a>', 'ipsPadding:none', FALSE );
				}
				else
				{
					$form->addMessage( \IPS\Member::loggedIn()->language()->addToStack( $v ) . '<br>' . \IPS\Member::loggedIn()->language()->addToStack( $lang  . '_desc' ), 'ipsPadding:none', FALSE );
				}
			}
		}
		$form->add( new \IPS\Helpers\Form\Checkbox( 'follow_public', $current ? !$current['follow_is_anon'] : TRUE, FALSE, array(
			'label' => ( $class != "IPS\Member" ) ? \IPS\Member::loggedIn()->language()->addToStack( 'follow_public' ) : \IPS\Member::loggedIn()->language()->addToStack('follow_public_member', FALSE, array( 'sprintf' => array( $thing->name ) ) )
		) ) );
		if ( $current )
		{
			$unfollowUrl = \IPS\Http\Url::internal( "app=core&module=system&controller=notifications&do=unfollow&id={$current['follow_id']}&follow_app={$current['follow_app']}&follow_area={$current['follow_area']}" )->csrf();
			if ( method_exists( $thing, 'url' ) AND $thing->url() )
			{
				$unfollowUrl = $unfollowUrl->addRef( (string) $thing->url() );
			}
			$form->addButton( 'unfollow', 'link', $unfollowUrl, 'ipsButton ipsButton_link ipsPos_left ipsButton_narrow', array('data-action' => 'unfollow') );
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Insert */
			$save = array(
				'follow_id'			=> md5( \IPS\Request::i()->follow_app . ';' . \IPS\Request::i()->follow_area . ';' . \IPS\Request::i()->follow_id . ';' .  \IPS\Member::loggedIn()->member_id ),
				'follow_app'			=> \IPS\Request::i()->follow_app,
				'follow_area'			=> \IPS\Request::i()->follow_area,
				'follow_rel_id'		=> \IPS\Request::i()->follow_id,
				'follow_member_id'	=> \IPS\Member::loggedIn()->member_id,
				'follow_is_anon'		=> !$values['follow_public'],
				'follow_added'		=> time(),
				'follow_notify_do'	=> ( isset( $values['follow_type'] ) AND $values['follow_type'] == 'none' ) ? 0 : 1,
				'follow_notify_meta'	=> '',
				'follow_notify_freq'	=> ( $class == "IPS\Member" ) ? 'immediate' : $values['follow_type'],
				'follow_notify_sent'	=> 0,
				'follow_visible'		=> 1,
			);
			if ( $current )
			{
				\IPS\Db::i()->update( 'core_follow', $save, array( 'follow_id=?', $current['follow_id'] ) );
			}
			else
			{
				\IPS\Db::i()->insert( 'core_follow', $save );
			}
			
			/* Remove cached */
			\IPS\Db::i()->delete( 'core_follow_count_cache', array( 'id=? AND class=?', \IPS\Request::i()->follow_id, 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . mb_ucfirst( \IPS\Request::i()->follow_area ) ) );
			
			/* Also follow all nodes if following club */
			if( $class == "IPS\Member\Club"  )
			{
				foreach ( $thing->nodes() as $node )
				{
					$itemClass = $node['node_class']::$contentItemClass;
					$followApp = $itemClass::$application;
					$followArea = mb_strtolower( mb_substr( $node['node_class'], mb_strrpos( $node['node_class'], '\\' ) + 1 ) );
					
					$save = array(
						'follow_id'				=> md5( $followApp . ';' . $followArea . ';' . $node['node_id'] . ';' .  \IPS\Member::loggedIn()->member_id ),
						'follow_app'			=> $followApp,
						'follow_area'			=> $followArea,
						'follow_rel_id'			=> $node['node_id'],
						'follow_member_id'		=> \IPS\Member::loggedIn()->member_id,
						'follow_is_anon'		=> !$values['follow_public'],
						'follow_added'			=> time(),
						'follow_notify_do'		=> ( isset( $values['follow_type'] ) AND $values['follow_type'] == 'none' ) ? 0 : 1,
						'follow_notify_meta'	=> '',
						'follow_notify_freq'	=> $values['follow_type'],
						'follow_notify_sent'	=> 0,
						'follow_visible'		=> 1,
					);
					\IPS\Db::i()->insert( 'core_follow', $save, TRUE );
				}
			}
			
			/* Send notification if following member */
			if( $class == "IPS\Member"  )
			{
				if( $values['follow_public'] )
				{
					/* Give points */
					$receiver = \IPS\Member::load( \IPS\Request::i()->follow_id );
					$receiver->achievementAction( 'core', 'FollowMember', [
						'giver' => \IPS\Member::loggedIn()
					] );

					$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'member_follow', \IPS\Member::loggedIn(), array( \IPS\Member::loggedIn() ) );
					$notification->recipients->attach( $thing );
					$notification->send();
				}
			}
			else if ( \in_array( 'IPS\Node\Model', class_parents( $class ) ) )
			{
				\IPS\Member::loggedIn()->achievementAction( 'core', 'FollowNode', $class::load( \IPS\Request::i()->follow_id ) );
			}
			else if ( \in_array( 'IPS\Content\Item', class_parents( $class ) ) )
			{
				$item = $class::load( \IPS\Request::i()->follow_id );
				\IPS\Member::loggedIn()->achievementAction( 'core', 'FollowContentItem', [
					'item' => $item,
					'author' => $item->author()
				] );
			}
			
			/* Boink */
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( 'ok' );
			}
			else
			{
				\IPS\Output::i()->redirect( $thing->url() );
			}
		}

		/* Display */
		$output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'system', 'core' ), 'followForm' ) );

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( $output, 200, 'text/html' );
		}
		else
		{
			\IPS\Output::i()->output = $output;
		}		
	}
	
	/**
	 * Unfollow
	 *
	 * @return	void
	 */
	protected function unfollow()
	{
		$this->_checkLoggedIn();

		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$follow = \IPS\Db::i()->select( '*', 'core_follow', array( 'follow_id=? AND follow_member_id=?', \IPS\Request::i()->id, \IPS\Member::loggedIn()->member_id ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'cant_find_unfollow', '2C154/D', 404, '' );
		}
		
		\IPS\Db::i()->delete( 'core_follow', array( 'follow_id=? AND follow_member_id=?', \IPS\Request::i()->id, \IPS\Member::loggedIn()->member_id ) );
		\IPS\Db::i()->delete( 'core_follow_count_cache', array( 'id=? AND class=?', $follow['follow_rel_id'], 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . mb_ucfirst( \IPS\Request::i()->follow_area ) ) );
		
		/* If we are unfollowing a club, unfollow all of its nodes */		
		if( \IPS\Request::i()->follow_app == 'core' and \IPS\Request::i()->follow_area == 'club' )
		{
			$class = 'IPS\\Member\Club';

			/* Get thing */
			$thing = NULL;

			try
			{
				$thing = $class::loadAndCheckPerms( (int) $follow['follow_rel_id'] );

				foreach ( $thing->nodes() as $node )
				{
					$itemClass = $node['node_class']::$contentItemClass;
					$followApp = $itemClass::$application;
					$followArea = mb_strtolower( mb_substr( $node['node_class'], mb_strrpos( $node['node_class'], '\\' ) + 1 ) );
					
					\IPS\Db::i()->delete( 'core_follow', array( 'follow_id=? AND follow_member_id=?', md5( $followApp . ';' . $followArea . ';' . $node['node_id'] . ';' .  \IPS\Member::loggedIn()->member_id ), \IPS\Member::loggedIn()->member_id ) );
				}
			}
			catch ( \OutOfRangeException $e ) {}
		}

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'ok' );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Request::i()->referrer() ?: \IPS\Http\Url::internal( '' ) );
		}
	}
	
	/**
	 * Show Followers
	 *
	 * @return	void
	 */
	protected function followers()
	{
		$perPage	= 50;
		$thisPage	= isset( \IPS\Request::i()->followerPage ) ? \IPS\Request::i()->followerPage : 1;
		$thisPage	= ( $thisPage > 0 ) ? $thisPage : 1;

		if( !\IPS\Request::i()->follow_app OR !\IPS\Request::i()->follow_area )
		{
			\IPS\Output::i()->error( 'node_error', '2C154/E', 404, '' );
		}
				
		/* Get class */
		if( \IPS\Request::i()->follow_app == 'core' and \IPS\Request::i()->follow_area == 'member' )
		{
			$class = 'IPS\Member';
		}
		else if( \IPS\Request::i()->follow_app == 'core' and \IPS\Request::i()->follow_area == 'club' )
		{
			$class = 'IPS\Member\Club';
		}
		else
		{
			$class = 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . mb_ucfirst( \IPS\Request::i()->follow_area );
		}
		
		if ( !class_exists( $class ) or !array_key_exists( \IPS\Request::i()->follow_app, \IPS\Application::applications() ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C154/5', 404, '' );
		}
		
		/* Get thing */
		$thing = NULL;
		$anonymous = 0;
		try
		{
			if ( \in_array( 'IPS\Node\Model', class_parents( $class ) ) )
			{
				$classname = $class::$contentItemClass;
				$containerClass = $class;
				$thing = $containerClass::loadAndCheckPerms( (int) \IPS\Request::i()->follow_id );
				$followers = $classname::containerFollowers( $thing, $classname::FOLLOW_PUBLIC, array( 'none', 'immediate', 'daily', 'weekly' ), NULL, array( ( $thisPage - 1 ) * $perPage, $perPage ), 'name' );
				$followersCount = $classname::containerFollowerCount( $thing );
				$anonymous = $classname::containerFollowerCount( $thing, $classname::FOLLOW_ANONYMOUS );
				$title = $thing->_title;
			}
			else if ( $class == "IPS\Member\Club" )
			{
				$thing = $class::loadAndCheckPerms( (int) \IPS\Request::i()->follow_id );
				$followers = $thing->followers( $class::FOLLOW_PUBLIC, array( 'none', 'immediate', 'daily', 'weekly' ), NULL, array( ( $thisPage - 1 ) * $perPage, $perPage ), 'name' );
				$followersCount = $thing->followersCount();
				$anonymous = $thing->followersCount( $class::FOLLOW_ANONYMOUS );
				$title = $thing->_title;
			}
			else if ( $class != "IPS\Member" )
			{
				$thing = $class::loadAndCheckPerms( (int) \IPS\Request::i()->follow_id );
				$followers = $thing->followers( $class::FOLLOW_PUBLIC, array( 'none', 'immediate', 'daily', 'weekly' ), NULL, array( ( $thisPage - 1 ) * $perPage, $perPage ), 'name' );
				$followersCount = $thing->followersCount();
				$anonymous = $thing->followersCount( $class::FOLLOW_ANONYMOUS );
				$title = $thing->mapped('title');
			}
			else
			{
				$thing = $class::load( (int) \IPS\Request::i()->follow_id );
				$followers = $thing->followers( $class::FOLLOW_PUBLIC, array( 'none', 'immediate', 'daily', 'weekly' ), NULL, array( ( $thisPage - 1 ) * $perPage, $perPage ), 'name' );
				$followersCount = $thing->followersCount();
				$anonymous = $thing->followersCount( $class::FOLLOW_ANONYMOUS );
				$title = $thing->name;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C154/6', 404, '' );
		}

		/* Display */
		if ( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->_infScroll ) )
		{
			\IPS\Output::i()->sendOutput(  \IPS\Theme::i()->getTemplate( 'system' )->followersRows( $followers ) );
		}
		else
		{
			$url = \IPS\Http\Url::internal( "app=core&module=system&controller=notifications&do=followers&follow_app=". \IPS\Request::i()->follow_app ."&follow_area=". \IPS\Request::i()->follow_area ."&follow_id=" . \IPS\Request::i()->follow_id . "&_infScroll=1" );
			$removeAllUrl = \IPS\Http\Url::internal( "app=core&module=system&controller=notifications&do=removeFollowers&follow_app=". \IPS\Request::i()->follow_app ."&follow_area=". \IPS\Request::i()->follow_area ."&follow_id=" . \IPS\Request::i()->follow_id )->csrf();
			if ( method_exists( $thing, 'url' ) AND $thing->url() )
			{
				$removeAllUrl = $removeAllUrl->addRef( (string) $thing->url() );
			}

			$pagination = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $url, ceil( $followersCount / $perPage ), $thisPage, $perPage, FALSE, 'followerPage' );
			
			/* Instruct bots not to index this page */
			\IPS\Output::i()->metaTags['robots']	= 'noindex';

			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('item_followers', FALSE, array( 'sprintf' => array( $title ) ) );
			\IPS\Output::i()->breadcrumb[] = array( $thing->url(), $title );
			\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('who_follows_this') );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->followers( $url, $pagination, $followers, $anonymous, $removeAllUrl );
		}
	}
	
	/**
	 * Unfollow from email
	 * If we're logged in, we can send them right to the normal follow form.
	 * Otherwise, they get a special guest page using the gkey as an authentication key.
	 *
	 * @return void
	 */
	protected function unfollowFromEmail()
	{		
		/* Logged in? */
		if ( \IPS\Member::loggedIn()->member_id )
		{
			/* Go to the normal page */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=notifications&do=follow&follow_app=". \IPS\Request::i()->follow_app ."&follow_area=". \IPS\Request::i()->follow_area ."&follow_id=" . \IPS\Request::i()->follow_id ) );
		}
		
		if ( ! empty( \IPS\Request::i()->gkey ) )
		{
			list( $followKey, $memberKey ) = explode( '-', \IPS\Request::i()->gkey );
			/* Do we follow it? */
			try
			{
				$current = \IPS\Db::i()->select( '*', 'core_follow', array( 'MD5( CONCAT_WS( \';\', follow_app, follow_area, follow_rel_id, follow_member_id, follow_added ) )=?', $followKey ) )->first();
				
				/* Already no subs? */
				if ( $current['follow_notify_freq'] === 'none' )
				{
					\IPS\Output::i()->error( 'follow_guest_not_notified', '2C154/C', 404, '' );
				}
				
				$member = \IPS\Member::load( $current['follow_member_id'] );
				
				if ( md5( $member->email . ';' . $member->ip_address . ';' . $member->joined->getTimestamp() ) != $memberKey )
				{
					throw new \Exception;
				}
				
				if( !array_key_exists( \IPS\Request::i()->follow_app, \IPS\Application::applications() ) )
				{
					throw new \Exception;
				}
				
				/* Get class */
				if( \IPS\Request::i()->follow_app == 'core' and \IPS\Request::i()->follow_area == 'member' )
				{
					$class = 'IPS\\Member';
				}
				elseif( \IPS\Request::i()->follow_app == 'core' and \IPS\Request::i()->follow_area == 'club' )
				{
					$class = 'IPS\\Member\Club';
				}
				else
				{
					$class = 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . mb_ucfirst( \IPS\Request::i()->follow_area );
					
					if( !is_subclass_of( $class, "\IPS\Content\Followable" ) )
					{
						throw new \Exception;
					}
				}
				if ( !class_exists( $class ) )
				{
					throw new \Exception;
				}
				
				/* Get thing */
				$thing = NULL;
				
				if ( \in_array( 'IPS\Node\Model', class_parents( $class ) ) )
				{
					$classname = $class::$contentItemClass;
					$containerClass = $class;
					$thing = $containerClass::load( (int) \IPS\Request::i()->follow_id );
					$title = $thing->_title;
				}
				else if ( $class == "IPS\Member\Club" )
				{
					$thing = $class::load( (int) \IPS\Request::i()->follow_id );
					$title = $thing->_title;
				}
				else if ( $class != "IPS\Member" )
				{
					$thing = $class::load( (int) \IPS\Request::i()->follow_id );
					$title = $thing->mapped('title');
				}
				else
				{
					$thing = $class::load( (int) \IPS\Request::i()->follow_id );
					$title = $thing->name;
				}
				
				/* Grab a count */
				$count = \IPS\Db::i()->select( 'COUNT(*)', 'core_follow', array( 'follow_member_id=? and follow_notify_freq != ?', $member->member_id, 'none' ) )->first();
				
				$form = new \IPS\Helpers\Form( 'unfollowFromEmail', 'update_follow' );
				$form->class = 'ipsForm_vertical';
				
				if ( $count == 1 )
				{
					$form->add( new \IPS\Helpers\Form\Checkbox( 'guest_unfollow_single', 'single', FALSE, array( 'disabled' => true ) ) );
					\IPS\Member::loggedIn()->language()->words['guest_unfollow_single'] = \IPS\Member::loggedIn()->language()->addToStack('follow_guest_unfollow_thing', FALSE, array( 'sprintf' => array( $title ) ) );
				}
				else
				{
					$form->add( new \IPS\Helpers\Form\Radio( 'guest_unfollow_choice', 'single', FALSE, array(
						'options'      => array(
							'single'   => \IPS\Member::loggedIn()->language()->addToStack('follow_guest_unfollow_thing', FALSE, array( 'sprintf' => array( $title ) ) ),
							'all'	   => \IPS\Member::loggedIn()->language()->addToStack('follow_guest_unfollow_all', FALSE, array( 'pluralize' => array( $count ) ) ),
						),
						'descriptions' => array(
							'single' => \IPS\Member::loggedIn()->language()->addToStack('follow_guest_unfollow_thing_desc'),
							'all'	 => \IPS\Member::loggedIn()->language()->addToStack('follow_guest_unfollow_all_desc', FALSE, array( 'sprintf' => array( base64_encode( \IPS\Http\Url::internal( "app=core&module=system&controller=followed" ) ) ) ) )
						)
					) ) );
				}
				
				if ( $values = $form->values() )
				{
					if ( $values['guest_unfollow_choice'] == 'single' or isset( $values['guest_unfollow_single'] ) )
					{
						\IPS\Db::i()->update( 'core_follow', array( 'follow_notify_freq' => 'none' ), array( 'follow_id=? AND follow_member_id=?', $current['follow_id'], $member->member_id ) );
						
						/* Unfollow club areas */
						if ( $class == "IPS\Member\Club"  )
						{
							foreach ( $thing->nodes() as $node )
							{
								$itemClass = $node['node_class']::$contentItemClass;
								$followApp = $itemClass::$application;
								$followArea = mb_strtolower( mb_substr( $node['node_class'], mb_strrpos( $node['node_class'], '\\' ) + 1 ) );
								
								\IPS\Db::i()->update( 'core_follow', array( 'follow_notify_freq' => 'none' ), array( 'follow_id=? AND follow_member_id=?', md5( $followApp . ';' . $followArea . ';' . $node['node_id'] . ';' .  $member->member_id ), $member->member_id ) );
							}
						}
					}
					else
					{
						\IPS\Db::i()->update( 'core_follow', array( 'follow_notify_freq' => 'none' ), array( 'follow_member_id=?', $member->member_id ) );
					}
				}
				
				\IPS\Output::i()->sidebar['enabled'] = FALSE;
				\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->unfollowFromEmail( $title, $member, $form, ! isset( \IPS\Request::i()->guest_unfollow_choice ) ? FALSE : \IPS\Request::i()->guest_unfollow_choice );
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('follow_guest_unfollow_thing', FALSE, array( 'sprintf' => array( $title ) ) );
			}
			catch ( \Exception $e )
			{
				\IPS\Output::i()->error( 'follow_guest_key_not_found', '2C154/B', 404, '' );
			}
		}
	}

	/**
	 * Follow button
	 *
	 * @return	void
	 */
	protected function button()
	{
		/* Get class */
		if( \IPS\Request::i()->follow_app == 'core' and \IPS\Request::i()->follow_area == 'member' )
		{
			$class = 'IPS\\Member';
		}
		elseif( \IPS\Request::i()->follow_app == 'core' and \IPS\Request::i()->follow_area == 'club' )
		{
			$class = 'IPS\\Member\Club';
		}
		else
		{
			$class = 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . mb_ucfirst( \IPS\Request::i()->follow_area );
		}
		
		if ( !class_exists( $class ) or !array_key_exists( \IPS\Request::i()->follow_app, \IPS\Application::applications() ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C154/5', 404, '' );
		}
		
		/* Get thing */
		$thing = NULL;
		try
		{
			if ( \in_array( 'IPS\Node\Model', class_parents( $class ) ) )
			{
				$classname = $class::$contentItemClass;
				$containerClass = $class;
				$thing = $containerClass::loadAndCheckPerms( (int) \IPS\Request::i()->follow_id );
				$count = $classname::containerFollowerCount( $thing );
			}
			else if ( $class != "IPS\Member" )
			{
				$thing = $class::loadAndCheckPerms( (int) \IPS\Request::i()->follow_id );
				$count = $thing->followersCount();
			}
			else
			{
				if( !is_subclass_of( $class, "\IPS\Content\Followable" ) AND $class != "IPS\Member" )
				{
					\IPS\Output::i()->error( 'node_error', '2C154/J', 404, '' );
				}
					
				$thing = $class::load( (int) \IPS\Request::i()->follow_id );
				$count = $thing->followersCount();
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C154/6', 404, '' );
		}

		if ( \IPS\Request::i()->follow_area == 'member' && ( !isset( \IPS\Request::i()->button_type ) || \IPS\Request::i()->button_type === 'search' ) )
		{
			if ( isset( \IPS\Request::i()->button_type ) && \IPS\Request::i()->button_type === 'search' )
			{
				\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'profile' )->memberSearchFollowButton( \IPS\Request::i()->follow_app, \IPS\Request::i()->follow_area, \IPS\Request::i()->follow_id, $count ) );
			}
			else
			{
				\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'profile' )->memberFollowButton( \IPS\Request::i()->follow_app, \IPS\Request::i()->follow_area, \IPS\Request::i()->follow_id, $count ) );	
			}			
		}
		else
		{
			if ( \IPS\Request::i()->button_type == 'manage' )
			{
				\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'system' )->manageFollowButton( \IPS\Request::i()->follow_app, \IPS\Request::i()->follow_area, \IPS\Request::i()->follow_id ) );
			}
			else
			{
				\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global' )->followButton( \IPS\Request::i()->follow_app, \IPS\Request::i()->follow_area, \IPS\Request::i()->follow_id, $count ) );	
			}			
		}
	}

	/**
	 * Remove all followers
	 *
	 * @return	void
	 */
	protected function removeFollowers()
	{
		\IPS\Session::i()->csrfCheck();

		if ( !\IPS\Member::loggedIn()->modPermission('can_remove_followers') )
		{
			\IPS\Output::i()->error( 'cant_remove_followers', '2C154/A', 403, 'cant_remove_followers_admin' );
		}

		\IPS\Db::i()->delete( 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=?', \IPS\Request::i()->follow_app, \IPS\Request::i()->follow_area, \IPS\Request::i()->follow_id ) );
        \IPS\Db::i()->delete( 'core_follow_count_cache', array( 'id=? AND class=?', \IPS\Request::i()->follow_id, 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . mb_ucfirst( \IPS\Request::i()->follow_area ) ) );
        
        if( \IPS\Request::i()->follow_area == 'club' )
		{
			try
			{
				$club = \IPS\Member\Club::load( \IPS\Request::i()->follow_id );
				foreach ( $club->nodes() as $node )
				{
					$itemClass = $node['node_class']::$contentItemClass;
					$followApp = $itemClass::$application;
					$followArea = mb_strtolower( mb_substr( $node['node_class'], mb_strrpos( $node['node_class'], '\\' ) + 1 ) );
					
					\IPS\Db::i()->delete( 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=?', $followApp, $followArea, $node['node_id'] ) );
				}
			}
			catch ( \OutOfRangeException $e ) { }
		}

		\IPS\Session::i()->modLog( 'modlog__item_follow_removed', array( \IPS\Request::i()->follow_app => FALSE, \IPS\Request::i()->follow_area=> FALSE, \IPS\Request::i()->follow_id => FALSE ) );
		
		\IPS\Output::i()->redirect( \IPS\Request::i()->referrer() ?: \IPS\Http\Url::internal( '' ), 'followers_removed' );
	}

	/**
	 * Retrieve notification data and return it to the service worker
	 *
	 * @return void
	 */
	protected function fetchNotification()
	{
		/* Got the ID? */
		if( !\IPS\Request::i()->id )
		{
			\IPS\Output::i()->json( array( 'error' => 'missing_id' ), 404 );
		}

		/* Got the notification? */
		try
		{
			$notification = \IPS\Db::i()->select( '*', 'core_notifications_pwa_queue', array( 'id=?', \IPS\Request::i()->id ) )->first();
		}
		catch( \UnderflowException $e )
		{
			\IPS\Output::i()->json( array( 'error' => 'not_found' ), 404 );
		}

		/* Is this our notification? */
		$data = json_decode( $notification['notification_data'], true );

		if( !isset( $data['member'] ) OR $data['member'] != \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->json( array( 'error' => 'no_permission' ), 403 );
		}

		/* Send the data */
		\IPS\Output::i()->json( $data );
	}
}