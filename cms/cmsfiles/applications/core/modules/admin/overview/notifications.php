<?php
/**
 * @brief		ACP Notification Center
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 June 2018
 */

namespace IPS\core\modules\admin\overview;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ACP Notification Center
 */
class _notifications extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Show notifications
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$notifications = \IPS\core\AdminNotification::notifications();
		
		if ( \IPS\Request::i()->isAjax() and !isset( \IPS\Request::i()->_table ) )
		{
			\IPS\Output::i()->json( array( 'data' => \IPS\Theme::i()->getTemplate('notifications')->popupList( $notifications ), 'count' => \count( $notifications ) ) );
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('acp_notifications');
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/notifications.css', 'core', 'admin' ) );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('notifications')->index( $notifications );
			
			\IPS\Output::i()->sidebar['actions'] = array(
				'settings'	=> array(
					'title'		=> 'notification_options',
					'icon'		=> 'cog',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=overview&controller=notifications&do=settings' ),
				),
			);
		}
	}
	
	/**
	 * Dismiss a notification
	 *
	 * @return	void
	 */
	protected function dismiss()
	{
		\IPS\Session::i()->csrfCheck();
		
		\IPS\core\AdminNotification::dismissNotification( \IPS\Request::i()->id );
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'status' => 'OK' ) );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=overview&controller=notifications' ) );
		}
	}
	
	/**
	 * Notification Settings
	 *
	 * @return	void
	 */
	protected function settings()
	{
		$preferences = iterator_to_array( \IPS\Db::i()->select( '*', 'core_acp_notifications_preferences', array( '`member`=?', \IPS\Member::loggedIn()->member_id ) )->setKeyField('type') );
		
		$matrix = new \IPS\Helpers\Form\Matrix;
		$matrix->langPrefix = 'acp_notifications_';
		$matrix->manageable = FALSE;
		$matrix->columns = array(
			'name'	=> function( $key, $value, $data )
			{
				return $value;
			},
			'view'	=> function( $key, $value, $data )
			{
				return new \IPS\Helpers\Form\YesNo( $key, $data['maybeOptional'] ? $value : TRUE, FALSE, array( 'disabled' => !$data['maybeOptional'] ) );
			},
			'email'	=> function( $key, $value, $data )
			{
				if ( $data['customEmail'] )
				{
					return $data['customEmail'];
				}
				else
				{
					$options = $data['mayRecur'] ? array(
						'never'		=> 'acp_notifications_email_never',
						'once'		=> 'acp_notifications_email_once',
						'always'	=> 'acp_notifications_email_always',
					) : array(
						'never'		=> 'acp_notifications_email_never',
						'once'		=> 'acp_notifications_email_yes',
					);
					
					return new \IPS\Helpers\Form\Select( $key, $value, FALSE, array( 'options' => $options, 'class' => 'ipsField_medium' ) );
				}
			},
		);
		
		$rows = array();
		foreach ( \IPS\Application::allExtensions( 'core', 'AdminNotifications', TRUE, NULL, NULL, FALSE ) as $ext )
		{
			if ( $ext::permissionCheck( \IPS\Member::loggedIn() ) )
			{
				$exploded = explode( '\\', $ext );
				$key = "{$exploded[1]}_{$exploded[5]}";
				
				$rows[ $ext::$group ]['priority'] = $ext::$groupPriority;
				$rows[ $ext::$group ]['rows'][ $ext ] = array(
					'name'			=> $ext::settingsTitle(),
					'view'			=> isset( $preferences[ $key ]['view'] ) ? $preferences[ $key ]['view'] : $ext::defaultValue(),
					'email'			=> isset( $preferences[ $key ]['email'] ) ? $preferences[ $key ]['email'] : 'never',
					'maybeOptional' => $ext::mayBeOptional(),
					'mayRecur' 		=> $ext::mayRecur(),
					'customEmail' 	=> $ext::customEmailConfigurationSetting( "{$ext}[email]", isset( $preferences[ $key ]['email'] ) ? $preferences[ $key ]['email'] : NULL ),
					'priority' 		=> $ext::$itemPriority,
				);
			}
		}
				
		uasort( $rows, function( $a, $b ) {
			return $a['priority'] - $b['priority'];
		});
		
		foreach ( $rows as $group => $data )
		{
			$matrix->rows[] = \IPS\Member::loggedIn()->language()->addToStack("acp_notification_group_{$group}");
			
			uasort( $data['rows'], function( $a, $b ) {
				return $a['priority'] - $b['priority'];
			});
			
			foreach ( $data['rows'] as $ext => $row )
			{
				$matrix->rows[ $ext ] = $row;
			}
		}
						
		if ( $values = $matrix->values() )
		{
			foreach ( $values as $ext => $_values )
			{
				$exploded = explode( '\\', $ext );
				$key = "{$exploded[1]}_{$exploded[5]}";
				
				$v = \IPS\Request::i()->$ext;
								
				\IPS\Db::i()->insert( 'core_acp_notifications_preferences', array(
					'member'	=> \IPS\Member::loggedIn()->member_id,
					'type'		=> $key,
					'view'		=> $ext::mayBeOptional() ? $_values['view'] : TRUE,
					'email'		=> isset( $v['email'] ) ? $v['email'] : 'never',
				), TRUE );
			}
			
			if( isset( \IPS\Data\Store::i()->acpNotificationIds ) )
			{
				$notificationCache = \IPS\Data\Store::i()->acpNotificationIds;
				unset( $notificationCache[ \IPS\Member::loggedIn()->member_id ] );
				\IPS\Data\Store::i()->acpNotificationIds = $notificationCache;
			}
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=overview&controller=notifications' ) );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('notification_options');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('forms')->blurb( 'acp_notifications_settings_blurb' ) . $matrix;
	}
	
	/**
	 * Recheck configuration errors
	 *
	 * @return	void
	 */
	protected function configurationErrorChecks()
	{
		\IPS\Session::i()->csrfCheck();
		\IPS\core\extensions\core\AdminNotifications\ConfigurationError::runChecksAndSendNotifications();
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=overview&controller=notifications' ) );
	}
	
	/**
	 * Delete orig_ database tables
	 *
	 * @return	void
	 */
	protected function removeOrigTables()
	{
		\IPS\Session::i()->csrfCheck();
		
		$tables = \IPS\Db::i()->getTables( 'orig_' . \IPS\Db::i()->prefix );
		\IPS\Task::queue( 'core', 'CleanupOrigTables', array( 'originalCount' => \count( $tables ) ), 5 );
		
		\IPS\core\AdminNotification::remove( 'core', 'ConfigurationError', 'origTables' );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=overview&controller=notifications' ) );
	}
}