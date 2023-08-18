<?php
/**
 * @brief		Notification Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Apr 2013
 */

namespace IPS\core\modules\admin\membersettings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Notification Settings
 */
class _notifications extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'notifications_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$types = array();
		foreach ( \IPS\Application::allExtensions( 'core', 'Notifications' ) as $k => $extension )
		{
			$types[ $k ] = array(
				'title'	=> \IPS\Member::loggedIn()->language()->get('notifications__' . $k)
			);
		}
		
		$typeTable = new \IPS\Helpers\Table\Custom( $types, \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=notifications', 'admin' ) );
		$typeTable->langPrefix = 'notificationsettings_';
		$typeTable->rowButtons = function( $row, $k ) {
			return array(
				'edit'	=> array(
					'title'	=> 'edit',
					'link'	=> \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=notifications&do=edit', 'admin' )->setQueryString( 'id', $k ),
					'icon'	=> 'pencil'
				)
			);
		};

		if( !\IPS\CIC )
		{
			\IPS\Output::i()->sidebar['actions'] = array(
				'settings'	=> array(
					'title'		=> 'prunesettings',
					'icon'		=> 'cog',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=notifications&do=pruneSettings' ),
					'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('prunesettings') )
				),
			);
		}

		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'membersettings', 'profiles_manage' ) )
		{
			\IPS\Output::i()->sidebar['actions']['reset'] = array(
				'icon'		=> 'undo',
				'title'		=> 'notification_prefs_reset',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=notifications&do=updateMemberFollowPrefs' )->csrf(),
				'data'		=> array( 'confirm' => '', 'confirmSubMessage' => \IPS\Member::loggedIn()->language()->addToStack('notification_prefs_reset_confirm') )
			);
		}
				
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('notifications');

		if ( ! \IPS\Notification::canUseWebPush() )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('global')->message( 'acp_notifications_cannot_use_web_push', 'general' );
		}

		\IPS\Output::i()->output .= $typeTable;
	}

	/**
	 * Prune Settings
	 *
	 * @return	void
	 */
	protected function pruneSettings()
	{
		if( \IPS\CIC )
		{
			\IPS\Output::i()->error( 'node_error', '2C413/1', 403, '' );
		}

		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Interval( 'prune_follows', \IPS\Settings::i()->prune_follows, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), function( $val ) {
			if( $val > 0 AND $val < 30 )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_interval_min_d', FALSE, array( 'pluralize' => array( 29 ) ) ) );
			}
		}, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'prune_follows' ) );
		$form->add( new \IPS\Helpers\Form\Interval( 'prune_notifications', \IPS\Settings::i()->prune_notifications, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), function( $val ) {
			if( $val > 0 AND $val < 7 )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_interval_min_d', FALSE, array( 'pluralize' => array( 6 ) ) ) );
			}
		}, \IPS\Member::loggedIn()->language()->addToStack('after') ) );
		
		if ( $values = $form->values() )
		{
			/* If we're enabling pruning on a potentially large table, handle that */
			if( !\IPS\Settings::i()->prune_follows AND $values['prune_follows'] )
			{
				\IPS\Task::queue( 'core', 'PruneLargeTable', array(
					'table'			=> 'core_follow',
					'where'			=> array( 'follow_app!=? AND follow_area!=? AND follow_member_id IN(?)', 'core', 'member', \IPS\Db::i()->select( 'member_id', 'core_members', array( 'last_activity < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $values['prune_follows'] . 'D' ) )->getTimestamp() ) ) ),
					'setting'		=> 'prune_follows',
					'deleteJoin'	=> array(
						'column'		=> 'member_id',
						'table'			=> 'core_members',
						'where'			=> array( 'last_activity < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $values['prune_follows'] . 'D' ) )->getTimestamp() ),
						'outerColumn'	=> 'follow_member_id'
					)
				), 4 );
			}

			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplog__follow_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=notifications' ), 'saved' );
		}
	
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('notification_pruning');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'notification_pruning', $form, FALSE );
	}
	
	/**
	 * Edit
	 *
	 * @return	void
	 */
	protected function edit()
	{
		$extensions = \IPS\Application::allExtensions( 'core', 'Notifications' );
		if ( !isset( $extensions[ \IPS\Request::i()->id ] ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C404/2', 404, '' );
		}
		$extension = $extensions[ \IPS\Request::i()->id ];
		
		$defaultConfiguration = \IPS\Notification::defaultConfiguration();
		
		$form = new \IPS\Helpers\Form;
		
		foreach ( \IPS\Notification::availableOptions( NULL, $extension ) as $key => $option )
		{
			if ( $option['type'] === 'standard' )
			{
				$form->addHeader( $option['title'] );
				if ( isset( $option['adminDescription'] ) )
				{
					$form->addMessage( $option['adminDescription'] );
				}
				elseif ( $option['description'] )
				{
					$form->addMessage( $option['description'] );
				}
				
				if ( !\in_array( 'inline', $option['disabled'] ) or (  \IPS\Notification::webPushEnabled() and !\in_array( 'push', $option['disabled'] ) ) or !\in_array( 'email', $option['disabled'] ) )
				{
					$canEditField = new \IPS\Helpers\Form\YesNo( 'notificationsettings_editable_' . $key, $defaultConfiguration[ $key ]['editable'], FALSE, array(
						'togglesOn'		=> array( "member_notifications_{$key}_inline_editable",  "member_notifications_{$key}_push",  "member_notifications_{$key}_email_editable" ),
						'togglesOff'	=> array( "member_notifications_{$key}_inline", "member_notifications_{$key}_email" )
					) );
					$canEditField->label = \IPS\Member::loggedIn()->language()->addToStack('notificationsettings_editable');
					$form->add( $canEditField );
				}
				
				if ( isset( $option['extra'] ) )
				{
					foreach ( $option['extra'] as $k => $extra )
					{
						if ( isset( $extra['adminCanSetDefault'] ) and $extra['adminCanSetDefault'] )
						{
							$field = new \IPS\Helpers\Form\Radio( 'member_notifications_' . $key . '_' . $k, $extra['default'] ? 'default' : 'optional', FALSE, array( 'options' => array(
								'default'	=> isset( $extra['admin_lang'] ) ? $extra['admin_lang']['default'] : 'admin_notification_pref_default',
								'optional'	=> isset( $extra['admin_lang'] ) ? $extra['admin_lang']['optional'] : 'admin_notification_pref_optional',
							) ), NULL, NULL, NULL, 'member_notifications_' . $k );
							$field->label = \IPS\Member::loggedIn()->language()->addToStack( isset( $extra['admin_lang'] ) ? $extra['admin_lang']['title'] : $extra['title'] );
							$form->add( $field );
						}
					}
				}

				if ( \IPS\Notification::canUseWebPush() )
				{
					$types = array( 'inline', 'push', 'email' );
				}
				else
				{
					$types = array( 'inline', 'email' );
				}
				foreach ( $types as $k )
				{
					if ( !\in_array( $k, $option['disabled'] ) )
					{
						$editableFieldValue = \in_array( $k, $defaultConfiguration[ $key ]['default'] ) ? 'default' : 'optional';
						if ( \in_array( $k, $defaultConfiguration[ $key ]['disabled'] ) )
						{
							$editableFieldValue = 'disabled';
						}
						if ( $k !== 'push' )
						{
							$editableField = new \IPS\Helpers\Form\Radio( 'member_notifications_' . $key . '_' . $k . '_editable', $editableFieldValue, FALSE, array(
								'options'	=> array(
									'default'	=> 'admin_notification_pref_default',
									'optional'	=> 'admin_notification_pref_optional',
									'disabled'	=> 'admin_notification_pref_disabled'
								),
								'toggles'	=> $k === 'inline' ? array(
									'default'	=> array( 'member_notifications_' . $key . '_push' ),
									'optional'	=> array( 'member_notifications_' . $key . '_push' ),
								) : array()
							), NULL, NULL, NULL, 'member_notifications_' . $key . '_' . $k . '_editable' );
							$editableField->label = \IPS\Member::loggedIn()->language()->addToStack( 'member_notifications_' . $k );
							$form->add( $editableField );
						}
						
						$nonEditableField = new \IPS\Helpers\Form\Radio( 'member_notifications_' . $key . '_' . $k, \in_array( $k, $defaultConfiguration[ $key ]['disabled'] ) ? 'disabled' : 'default', FALSE, array( 'options' => array(
							'default'	=> $k === 'push' ? 'admin_notification_pref_available' : 'admin_notification_pref_force',
							'disabled'	=> 'admin_notification_pref_disabled'
						) ), NULL, NULL, NULL, 'member_notifications_' . $key . '_' . $k );
						$nonEditableField->label = \IPS\Member::loggedIn()->language()->addToStack( 'member_notifications_' . $k );
						$form->add( $nonEditableField );
					}
				}
			}
			elseif ( $option['type'] === 'custom' )
			{
				if ( ( isset( $option['adminCanSetDefault'] ) and $option['adminCanSetDefault'] ) or ( isset( $option['adminOnly'] ) and $option['adminOnly'] ) )
				{
					if ( isset( $option['admin_lang']['header'] ) and isset( $option['admin_lang'] ) )
					{
						$form->addHeader( $option['admin_lang']['header'] );
					}
					$form->add( $option['field'] );
				}
			}
		}
				
		if ( $values = $form->values() )
		{
			foreach ( \IPS\Notification::availableOptions( NULL, $extension ) as $key => $option )
			{
				if ( $option['type'] === 'standard' )
				{		
					if ( isset( $option['extra'] ) )
					{
						foreach ( $option['extra'] as $k => $extra )
						{
							if ( isset( $extra['adminCanSetDefault'] ) and $extra['adminCanSetDefault'] )
							{
								$extension->saveExtra( NULL, $k, ( $values[ 'member_notifications_' . $key . '_' . $k ] === 'default' ) );
							}
						}
					}
					
					if ( !\in_array( 'inline', $option['disabled'] ) or (  \IPS\Notification::webPushEnabled()and !\in_array( 'push', $option['disabled'] ) ) or !\in_array( 'email', $option['disabled'] ) )
					{		
						$row = array(
							'notification_key'	=> $key,
							'default'			=> array(),
							'disabled'			=> array(),
							'editable'			=> $values[ 'notificationsettings_editable_' . $key ]
						);
						
						foreach ( array( 'inline', 'push', 'email' ) as $k )
						{							
							if ( !\in_array( $k, $option['disabled'] ) )
							{
								if ( $k === 'push' )
								{
									if ( !\IPS\Notification::webPushEnabled() )
									{
										continue;
									}
									$fieldToCheck = $values[ 'member_notifications_' . $key . '_push' ];
								}
								else
								{
									$fieldToCheck = $values[ 'notificationsettings_editable_' . $key ] ? $values[ 'member_notifications_' . $key . '_' . $k . '_editable' ] : $values[ 'member_notifications_' . $key . '_' . $k ];
								}
								
								if ( $fieldToCheck === 'default' )
								{
									$row['default'][] = $k;
								}
								elseif ( $fieldToCheck === 'disabled' )
								{
									$row['disabled'][] = $k;
								}
							}
						}
						
						$row['default'] = implode( ',', $row['default'] );
						$row['disabled'] = implode( ',', $row['disabled'] );
												
						\IPS\Db::i()->replace( 'core_notification_defaults', $row, array( 'notification_key=?', $key ) );
						
						$extensionConfiguration = $extension->configurationOptions();
						if ( isset( $extensionConfiguration[ $key ] ) and $extensionConfiguration[ $key ]['type'] === 'standard' )
						{
							foreach ( $extensionConfiguration[ $key ]['notificationTypes'] as $notificationType )
							{
								$row['notification_key'] = $notificationType;
								\IPS\Db::i()->replace( 'core_notification_defaults', $row, array( 'notification_key=?', $notificationType ) );
							}
						}
					}
				}
				elseif ( $option['type'] === 'custom' )
				{
					if ( ( isset( $option['adminCanSetDefault'] ) and $option['adminCanSetDefault'] ) or ( isset( $option['adminOnly'] ) and $option['adminOnly'] ) )
					{
						$extension->saveExtra( NULL, $key, $option['field']->value );
					}
				}
			}
						
			\IPS\Session::i()->log( 'acplog__notifications_edited' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=notifications' ), 'saved' );
		}
		
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=notifications' ), \IPS\Member::loggedIn()->language()->addToStack( 'menu__core_membersettings_notifications' ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'notifications__' . \IPS\Request::i()->id );
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Member Auto Follow Preferences
	 *
	 * @return	void
	 */
	protected function updateMemberFollowPrefs()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'profiles_manage' );
		\IPS\Session::i()->csrfCheck();
		
		/* Do standard preferences */
		\IPS\Db::i()->delete( 'core_notification_preferences' );
		
		/* Do "extra" preferences */
		foreach ( \IPS\Application::allExtensions( 'core', 'Notifications' ) as $k => $extension )
		{
			if ( method_exists( $extension, 'resetExtra' ) )
			{
				$extension->resetExtra();
			}
		}

		/* Log and redirect */
		\IPS\Session::i()->log( 'acplog__notification_settings_existing' );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=notifications' ), 'reset' );
	}
}