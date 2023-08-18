<?php
/**
 * @brief		general
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Apr 2013
 */

namespace IPS\core\modules\admin\settings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * general
 */
class _general extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'general_manage' );
		parent::execute();
	}

	/**
	 * Manage Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Text( 'board_name', \IPS\Settings::i()->board_name, TRUE ) );
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'site_online', \IPS\Settings::i()->site_online, FALSE, array(
			'togglesOff'	=> array( 'site_offline_message_id' ),
		) ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'site_offline_message', \IPS\Settings::i()->site_offline_message, FALSE, array( 'app' => 'core', 'key' => 'Admin', 'autoSaveKey' => 'onlineoffline', 'attachIds' => array( NULL, NULL, 'site_offline_message' ) ), NULL, NULL, NULL, 'site_offline_message_id' ) );
		$form->add( new \IPS\Helpers\Form\Address( 'site_address', \IPS\GeoLocation::buildFromJson( \IPS\Settings::i()->site_address ), FALSE ) );
		$form->add( new \IPS\Helpers\Form\Stack( 'site_social_profiles', \IPS\Settings::i()->site_social_profiles ? json_decode( \IPS\Settings::i()->site_social_profiles, true ) : array(), FALSE, array( 'stackFieldType' => '\IPS\core\Form\SocialProfiles', 'maxItems' => 50, 'key' => array( 'placeholder' => 'http://example.com', 'size' => 20 ) ) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'site_twitter_id', \IPS\Settings::i()->site_twitter_id, FALSE, array( 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('site_twitter_id_placeholder'), 'size' => 20 ) ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'copyright_line', NULL, FALSE, array( 'app' => 'core', 'key' => 'copyright_line_value', 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('copyright_line_placeholder') ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'relative_dates_enable', \IPS\Settings::i()->relative_dates_enable, FALSE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'site_site_elsewhere', \IPS\Settings::i()->site_site_elsewhere, FALSE, [ 'togglesOn' => [ 'site_main_url', 'site_main_title' ] ] ) );
		$form->add( new \IPS\Helpers\Form\Url( 'site_main_url', \IPS\Settings::i()->site_main_url, FALSE, [], NULL, NULL, NULL, 'site_main_url' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'site_main_title', \IPS\Settings::i()->site_main_title, FALSE, [], NULL, NULL, NULL, 'site_main_title' ) );
		/* $form->add( new \IPS\Helpers\Form\CheckboxSet( 'diagnostics_reporting', array( 'usage' => ( (bool) \IPS\Settings::i()->usage_reporting ), 'diagnostics' => ( (bool) \IPS\Settings::i()->diagnostics_reporting ) ), FALSE, array( 'options' => array(
			'usage'			=> 'diagnostics_reporting_usage',
			'diagnostics'	=> 'diagnostics_reporting_diagnostics',
		) ) ) ); */
		
		if ( $values = $form->values() )
		{
			\IPS\Lang::saveCustom( 'core', "copyright_line_value", $values['copyright_line'] );
			unset( $values['copyright_line'] );

			array_walk( $values['site_social_profiles'], function( &$value ){
				$value['key'] = (string) $value['key'];
			});
			$values['site_social_profiles']	= json_encode( array_filter( $values['site_social_profiles'], function( $value ) {
				return (bool) $value['key'];
			} ) );

			$values['site_address']			= json_encode( $values['site_address'] );
			
			// $values['usage_reporting'] = \in_array( 'usage', $values['diagnostics_reporting'] );
			// $values['diagnostics_reporting'] = \in_array( 'diagnostics', $values['diagnostics_reporting'] );
			// \IPS\Db::i()->update( 'core_tasks', array( 'enabled' => \intval( $values['usage_reporting'] ) ), array( '`key`=?', 'usagereporting' ) );
						
			$form->saveAsSettings( $values );
			
			if ( $values['site_online'] )
			{
				\IPS\core\AdminNotification::remove( 'core', 'ConfigurationError', 'siteOffline' );
			}
			else
			{
				\IPS\core\AdminNotification::send( 'core', 'ConfigurationError', 'siteOffline', FALSE, NULL, \IPS\Member::loggedIn() );
			}

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			/* Clear manifest and ie browser data stores */
			unset( \IPS\Data\Store::i()->manifest, \IPS\Data\Store::i()->iebrowserconfig );

			\IPS\Session::i()->log( 'acplogs__general_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=general' ), 'saved' );
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_settings_general');
		\IPS\Output::i()->output	.= \IPS\Theme::i()->getTemplate( 'global' )->block( 'menu__core_settings_general', $form );
		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'settings/general.css', 'core', 'admin' ) );
	}
}