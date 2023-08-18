<?php
/**
 * @brief		Upgrader: Custom Upgrade Options
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		16 Jul 2019
 */

$options = array();
require "../upgrade/lang.php";

/* Are we using elasticsearch? Make sure it is 6.0.0 or greater, and warn that it will be disabled if not. */
if( \IPS\Settings::i()->search_method == 'elastic' AND \IPS\Settings::i()->search_elastic_server )
{
	try
	{
		$response = \IPS\Http\Url::external( rtrim( \IPS\Settings::i()->search_elastic_server, '/' ) )->request()->get()->decodeJson();
	}
	catch ( \Exception $e )
	{
		/* If there's an exception, the server may be down temporarily or something - let's not disable in that case */
	}

	if ( isset( $response ) AND ( !isset( $response['version']['number'] ) OR version_compare( $response['version']['number'], \IPS\Content\Search\Elastic\Index::MINIMUM_VERSION, '<' ) ) )
	{
		$options[] = new \IPS\Helpers\Form\Custom( '105000_es_version', null, FALSE, array( 'getHtml' => function( $element ) use ( $response ){
			$minimumVersion	= \IPS\Content\Search\Elastic\Index::MINIMUM_VERSION;
			$yourVersion	= $response['version']['number'];

			return "The new minimum supported version of Elasticsearch is {$minimumVersion}, however your server is currently running {$yourVersion}. By continuing, your search engine will revert back to MySQL searching.";
		}, 'formatValue' => function( $element ){ return true; } ), function( $val ) { return TRUE; }, NULL, NULL, '105000_es_version' );
	}
}

/* Try/catch because this table won't be present yet if coming from 3.x */
try
{
	$emailCount = \IPS\Db::i()->select( 'COUNT(*)', 'core_email_templates', array( "template_app=? AND template_name=? AND template_edited=?", 'core', 'admin_reg', 1 ) )->first();

	if ( $emailCount )
	{
		$options[] = new \IPS\Helpers\Form\Custom( '105000_admin_reg_customized', NULL, FALSE, array( 'getHtml' => function( $element ) {
			return "Customizations to the 'Admin notification of registration pending approval' email template will be reverted.";
		}, 'formatValue' => function( $element ) { return TRUE; } ), function( $val ) { return TRUE; }, NULL, NULL, '105000_admin_reg_customized' );
	}
}
catch( \Exception $e ){}

/* Keep bbcode enabled, or disable it? */
$options[]	= new \IPS\Helpers\Form\Radio( '105000_enable_bbcode', 'disable', TRUE, array( 'options' => array( 'disable' => '105000_bbcode_disable', 'enable' => '105000_bbcode_enable' ) ) );

/* Combined notifications - again wrapped in a try/catch for 3.4.x upgrades where the table won't exist */
$notificationOptions = array();
try
{
	$existingDefaults = iterator_to_array( \IPS\Db::i()->select( '*', 'core_notification_defaults' )->setKeyField('notification_key') );
	$newDefaults = array();
	$extensions = \IPS\Application::allExtensions( 'core', 'Notifications' );
	foreach ( $extensions as $group => $extension )
	{
		try
		{
			if ( method_exists( $extension, 'configurationOptions' ) )
			{
				foreach ( $extension->configurationOptions( NULL ) as $optionKey => $option )
				{
					if ( $option['type'] === 'standard' )
					{
						foreach ( $option['notificationTypes'] as $type )
						{
							if ( isset( $existingDefaults[$type] ) )
							{
								if ( !isset( $newDefaults[$optionKey] ) )
								{
									$newDefaults[$optionKey] = array(
										'notification_key' => $optionKey,
										'default' => $existingDefaults[$type]['default'],
										'disabled' => $existingDefaults[$type]['disabled'],
										'editable' => $existingDefaults[$type]['editable'],
									);
								}
								else
								{
									if ( !isset( $notificationOptions["105000_notifications_editable_{$optionKey}"] ) and $newDefaults[$optionKey]['editable'] != $existingDefaults[$type]['editable'] )
									{
										$notificationOptions["105000_notifications_editable_{$optionKey}"] = new \IPS\Helpers\Form\Radio( "105000_notifications_editable_{$optionKey}", TRUE, FALSE, array('options' => array(
											1 => 'notification_editable_yes',
											0 => 'notification_editable_no'
										)) );
										$notificationOptions["105000_notifications_editable_{$optionKey}"]->label = sprintf( $lang["105000_notifications_editable"], $lang['notifications__' . $optionKey] );
										$notificationOptions["105000_notifications_editable_{$optionKey}"]->description = $lang['notifications__' . $optionKey . '_desc'];
									}
									if ( !isset( $notificationOptions["105000_notifications_default_{$optionKey}"] ) and $newDefaults[$optionKey]['default'] != $existingDefaults[$type]['default'] )
									{
										$notificationOptions["105000_notifications_default_{$optionKey}"] = new \IPS\Helpers\Form\CheckboxSet( "105000_notifications_default_{$optionKey}", $option['default'], FALSE, array('options' => array(
											'inline' => 'notification_method_inline',
											'email' => 'notification_method_email'
										)) );
										$notificationOptions["105000_notifications_default_{$optionKey}"]->label = sprintf( $lang["105000_notifications_default"], $lang['notifications__' . $optionKey] );
										$notificationOptions["105000_notifications_default_{$optionKey}"]->description = $lang['notifications__' . $optionKey . '_desc'];
									}
									if ( !isset( $notificationOptions["105000_notifications_disabled_{$optionKey}"] ) and $newDefaults[$optionKey]['disabled'] != $existingDefaults[$type]['disabled'] )
									{
										$notificationOptions["105000_notifications_disabled_{$optionKey}"] = new \IPS\Helpers\Form\CheckboxSet( "105000_notifications_disabled_{$optionKey}", $option['disabled'], FALSE, array('options' => array(
											'inline' => 'notification_method_inline',
											'email' => 'notification_method_email'
										)) );
										$notificationOptions["105000_notifications_disabled_{$optionKey}"]->label = sprintf( $lang["105000_notifications_disabled"], $lang['notifications__' . $optionKey] );
										$notificationOptions["105000_notifications_disabled_{$optionKey}"]->description = $lang['notifications__' . $optionKey . '_desc'];
									}
								}
							}
						}
					}
				}
			}
		}
		catch( \Exception $e ){}
	}
}
catch( \Exception $e ){}


if( !\IPS\CIC )
{
	/* Use new default pruning preferences? */
	$options[]	= new \IPS\Helpers\Form\Radio( '105000_prune', 'enable', TRUE, array( 'options' => array( 'disable' => '105000_prune_disable', 'enable' => '105000_prune_enable' ) ) );
}

if ( $notificationOptions )
{
	$options[] = new \IPS\Helpers\Form\Custom( '105000_notifications', null, FALSE, array( 'getHtml' => function( $element ) use ( $members ){
		return "Some notifications options have been combined to allow members to manage their notification preferences more easily. Some of your default values for the combined options do not match. Please choose how you would like them to be combined:";
	} ), function( $val ) {}, NULL, NULL, '105000_notifications' );
	$options += $notificationOptions;
}