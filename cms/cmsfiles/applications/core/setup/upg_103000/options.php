<?php
/**
 * @brief		Upgrader: Custom Upgrade Options
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Aug 2017
 */

$options = array();

/* Usage reporting */
// $options[] = new \IPS\Helpers\Form\YesNo( '103000_usage_reporting', TRUE );

/* Is IPS Connect enabled?  */
$ipsConnectEnabled = FALSE;
try
{
	$ipsConnectEnabled = (bool) \IPS\Db::i()->select( 'COUNT(*)', 'core_login_handlers', array( 'login_key=? AND login_enabled=1', 'Ipsconnect' ) )->first();
}
catch ( \IPS\Db\Exception $e )
{
	try
	{
		$ipsConnectEnabled = (bool) \IPS\Db::i()->select( 'COUNT(*)', 'login_methods', array( 'login_folder_name=? AND login_enabled=1', 'ipsconnect' ) )->first();
	}
	catch ( \IPS\Db\Exception $e ) { }
}
if( $ipsConnectEnabled )
{
	$options[] = new \IPS\Helpers\Form\Custom( 'ipsconnect', null, FALSE, array( 'getHtml' => function( $element ) use ( $members ){
		$url = \IPS\Http\Url::ips('docs/login_invision');
		return "<br>IPS Connect has been replaced with a new OAuth-based system for connecting two or more communities.<br><br><strong>If you have already upgraded the master install in your IPS Connect network</strong> follow the <a href='{$url}' target='_blank' rel='noopener'>Connect Two Invision Communities</a> guide to get the Client Identifier and Client Secret and enter them below.<br><strong>If you have not yet upgraded the master install in your IPS Connect network or do not want to enter these details yet</strong> you can leave the settings below blank, but make sure you have bookmarked the <a href='{$url}' target='_blank' rel='noopener'>Connect Two Invision Communities</a> guide to refer to after you have finished the upgrade.<br><br><strong class='ipsType_warning'><i class='fa fa-exclamation-triangle'></i> If you do not provide these details now you will not be able to log in using IPS Connect once the upgrade is complete. If you find yourself unable to log in after the upgrade, use the Forgot Password tool to regain access to your account.</strong>";
	} ), function( $val ) {}, NULL, NULL, 'ipsconnect' );
	
	$row = \IPS\Db::i()->select( '*', 'core_login_handlers', array( 'login_key=? AND login_enabled=1', 'Ipsconnect' ) )->first();
	$settings = json_decode( $row['login_settings'], TRUE );
	
	$options[] = new \IPS\Helpers\Form\Text( '103000_ipsconnect_url', preg_replace( '/applications\/core\/interface\/ipsconnect\/ipsconnect\.php$/', '', $settings['url'] ) );
	$options[] = new \IPS\Helpers\Form\Text( '103000_ipsconnect_client_id' );
	$options[] = new \IPS\Helpers\Form\Text( '103000_ipsconnect_client_secret' );
	
	
}

/* Login over HTTPs */
if( \IPS\Settings::i()->logins_over_https OR ( \IPS\Settings::i()->nexus_https AND \IPS\Application::appIsEnabled( 'nexus' ) ) )
{
	$setting = array();
	
	if( \IPS\Settings::i()->logins_over_https )
	{
		$setting[] = '"Use https for logins and the AdminCP?"';
	}

	if( \IPS\Application::appIsEnabled( 'nexus' ) AND \IPS\Settings::i()->nexus_https )
	{
		$setting[] = '"Use a secure connection for checkout?"';
	}

	$options[] = new \IPS\Helpers\Form\Custom( 'https_settings_changes', null, FALSE, array( 'getHtml' => function( ) use ( $setting ){
		$url = \IPS\Http\Url::ips('docs/https');
		$settingString = implode( ' and ', $setting );
		$pluralS = count( $setting ) > 1 ? 's' : '';
		$pluralThis = count( $setting ) > 1 ? 'these' : 'this';
		$pluralIs = count( $setting ) > 1 ? 'are' : 'is';
		return "You currently have the {$settingString} setting{$pluralS} enabled. Due to recent changes to some browsers, {$pluralThis} feature{$pluralS} can cause browser warnings and so {$pluralIs} no longer supported in Invision Community. To keep the security benefits, we recommend moving your entire community to using https after this upgrade is complete. <a href='{$url}' rel='external' target='_blank' rel='noopener'>Learn how</a>";
	} ), function( $val ) {}, NULL, NULL, 'https_settings_changes' );
}

/* Is the link to the admin directory hidden?  */
if( \IPS\Settings::i()->security_remove_acp_link )
{
	$options[] = new \IPS\Helpers\Form\Custom( 'admincp_hide_deprecate', null, FALSE, array( 'getHtml' => function( $element ) use ( $members ){
		$url = \IPS\Http\Url::ips('docs/two_factor_auth');
		return "<br>The setting to hide the link to the AdminCP has been removed. We recommend enabling two factor authentication for enhanced AdminCP security. After completing the upgrade, follow the <a href='{$url}' target='_blank' rel='noopener'>Two Factor Authentication</a> guide to enable this functionality.<br><br><strong class='ipsType_warning'><i class='fa fa-exclamation-triangle'></i> Before continuing, ensure you have bookmarked this guide to refer to after you have finished the upgrade.</strong>";
	} ), function( $val ) {}, NULL, NULL, 'admincp_hide_deprecate' );
}

/* reCAPTCHA v1 Removed  */
if( \IPS\Settings::i()->bot_antispam_type === 'recaptcha' )
{

	$options[] = new \IPS\Helpers\Form\Custom( 'recaptcha_v1_removed', null, FALSE, array( 'getHtml' => function( $element ) use ( $members ){
        $url = \IPS\Http\Url::ips('docs/captcha_recaptcha_invisible');
		return "<br>Your community was set to use reCAPTCHA 1, a CAPTCHA method where the user is shown two distorted words and must type the displayed words. Google recently retired this service so we are automatically upgrading you to the latest <a href='{$url}' target='_blank' rel='noopener'>Invisible reCAPTCHA</a> method. This doesn't require user input as the system intelligently detects if the user is human in the background.";
	} ), function( $val ) {}, NULL, NULL, 'recaptcha_v1_removed' );
}
