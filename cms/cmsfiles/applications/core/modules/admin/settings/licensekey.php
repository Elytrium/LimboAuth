<?php
/**
 * @brief		licensekey
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
 * licensekey
 */
class _licensekey extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * @brief Data about the license key from the store
	 */
	protected $licenseData = array();

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'licensekey_manage' );
		parent::execute();
	}

	/**
	 * License key overview screen
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Get license info.  If license info is empty, refresh it. */
		$licenseData = \IPS\IPS::licenseKey();
		
		/* If no license key has been supplied yet just show the form */
		if( !\IPS\Settings::i()->ipb_reg_number )
		{
			return $this->settings();
		}
		
		/* Init */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('license_settings');
		\IPS\Output::i()->sidebar['actions'] = array(
			'refresh'	=> array(
				'icon'	=> 'refresh',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=licensekey&do=refresh' )->csrf(),
				'title'	=> 'license_refresh',
			),
			'remove'	=> array(
				'icon'	=> 'pencil',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=licensekey&do=settings' ),
				'title'	=> 'license_change',
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('license_change') )
			),
		);
		
		/* If we have a license key, but the server doesn't recognise it, show an error */
		if ( !$licenseData )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( 'license_not_recognised', 'error' );
		}
		/* Otherwise show the normal info */
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'licensekey', 'core' )->overview( $licenseData );
		}
	}

	/**
	 * Refresh the license key data stored locally
	 *
	 * @return	void
	 */
	protected function refresh()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Fetch the license key data and update our local storage */
		\IPS\IPS::licenseKey( TRUE );

		/* Return the overview screen afterwards */
		if ( isset( \IPS\Request::i()->return ) and \IPS\Request::i()->return === 'cloud' and \IPS\Application::appIsEnabled('cloud') )
		{
			\IPS\cloud\Application::toggleDisabledApps();
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cloud&module=smartcommunity&controller=smartcommunity' ), 'cloud_license_refreshed' );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=licensekey' ), 'license_key_refreshed' );
		}
	}

	/**
	 * Manage Settings
	 *
	 * @return	void
	 */
	protected function settings()
	{
		$form = new \IPS\Helpers\Form;
		$form->addHeader('ipb_license_edit_main');
		$form->add( new \IPS\Helpers\Form\Text( 'ipb_reg_number', \IPS\Settings::i()->ipb_reg_number, TRUE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'ipb_license_active', \IPS\Settings::i()->ipb_license_active ) );
		$form->add( new \IPS\Helpers\Form\Text( 'ipb_license_expires', \IPS\Settings::i()->ipb_license_expires ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'ipb_license_cloud', \IPS\Settings::i()->ipb_license_cloud ) );

		$form->addHeader('ipb_license_urls');
		$form->add( new \IPS\Helpers\Form\Text( 'ipb_license_url', \IPS\Settings::i()->ipb_license_url ) );
		$form->add( new \IPS\Helpers\Form\Text( 'ipb_license_test_url', \IPS\Settings::i()->ipb_license_test_url ) );

		$form->addHeader('ipb_license_components');
		$form->add( new \IPS\Helpers\Form\YesNo( 'ipb_license_product_forums', \IPS\Settings::i()->ipb_license_product_forums ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'ipb_license_product_calendar', \IPS\Settings::i()->ipb_license_product_calendar ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'ipb_license_product_blog', \IPS\Settings::i()->ipb_license_product_blog ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'ipb_license_product_gallery', \IPS\Settings::i()->ipb_license_product_gallery ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'ipb_license_product_downloads', \IPS\Settings::i()->ipb_license_product_downloads ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'ipb_license_product_cms', \IPS\Settings::i()->ipb_license_product_cms ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'ipb_license_product_nexus', \IPS\Settings::i()->ipb_license_product_nexus ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'ipb_license_product_copyright', \IPS\Settings::i()->ipb_license_product_copyright ) );

		$form->addHeader('ipb_license_services');
		$form->add( new \IPS\Helpers\Form\Text( 'ipb_license_chat_limit', \IPS\Settings::i()->ipb_license_chat_limit ) );
		$form->add( new \IPS\Helpers\Form\Text( 'ipb_license_support', \IPS\Settings::i()->ipb_license_support ) );

		if ( $values = $form->values() )
		{
			$values['ipb_reg_number'] = trim( $values['ipb_reg_number'] );

			if ( mb_substr( $values['ipb_reg_number'], -12 ) === '-TESTINSTALL' )
			{
				$values['ipb_reg_number'] = mb_substr( $values['ipb_reg_number'], 0, -12 );
			}
			
			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplogs__license_settings' );

			/* Refresh the locally stored license info */
			unset( \IPS\Data\Store::i()->license_data );
			
			\IPS\core\AdminNotification::remove( 'core', 'License', 'missing' );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=licensekey' ), 'saved' );
		}

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('license_settings');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'global' )->block( 'menu__core_settings_licensekey', $form );
	}
}