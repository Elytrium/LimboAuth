<?php
/**
 * @brief		Initial installation onboarding
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Feb 2020
 */

namespace IPS\core\modules\admin\overview;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Initial installation onboarding
 */
class _onboard extends \IPS\Dispatcher\Controller
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
		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'dashboard/onboard.css', 'core', 'admin' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_dashboard.js', 'core', 'admin' ) );

		return parent::execute();
	}
	
	/**
	 * Welcome page
	 *
	 * @return	void
	 */
	public function initial()
	{
		\IPS\Session::i()->csrfCheck();
		\IPS\Settings::i()->changeValues( array( 'onboard_complete' => 1 ) ); // Set the flag that we've hit this page so that it doesn't keep trying to redirect us back
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('onboard_pagetitle');
		\IPS\Output::i()->showTitle	= FALSE;
		\IPS\Output::i()->bypassCsrfKeyCheck = TRUE;
		\IPS\Output::i()->output	.= \IPS\Theme::i()->getTemplate( 'dashboard' )->onboardWelcome();
	}
	
	/**
	 * Dismiss
	 *
	 * @return	void
	 */
	public function dismiss()
	{
		\IPS\Session::i()->csrfCheck();
		\IPS\Email::buildFromTemplate( 'core', 'onboard_reminder', array(), \IPS\Email::TYPE_TRANSACTIONAL )->send( \IPS\Member::loggedIn() );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=overview&controller=dashboard' ) );
	}
	
	/**
	 * Remind me later
	 *
	 * @return	void
	 */
	public function remind()
	{
		\IPS\Session::i()->csrfCheck();
		\IPS\Settings::i()->changeValues( array( 'onboard_complete' => \IPS\DateTime::ts( time() )->add( new \DateInterval( 'PT20M' ) )->getTimestamp() ) );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=overview&controller=dashboard' ), 'onboard_will_remind' );
	}

	/**
	 * Save the form values
	 *
	 * @param $values
	 * @param \IPS\Helpers\Form $form
	 * @return void
	 */
	public function handleForm( $values, \IPS\Helpers\Form $form )
	{
		/* If we dismissed, do that but also send an email */
		if( isset( \IPS\Request::i()->dismiss ) and \IPS\Request::i()->dismiss == 1 )
		{
			\IPS\Email::buildFromTemplate( 'core', 'onboard_reminder', array(), \IPS\Email::TYPE_TRANSACTIONAL )->send( \IPS\Member::loggedIn() );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=overview&controller=dashboard' ) );
		}

		/* If we said to remind us later, set the flag and redirect */
		if( isset( \IPS\Request::i()->remind ) and \IPS\Request::i()->remind == 1 )
		{
			\IPS\Settings::i()->changeValues( array( 'onboard_complete'=>\IPS\DateTime::ts( time() )->add( new \DateInterval( 'PT20M' ) )->getTimestamp() ) );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=overview&controller=dashboard' ), 'onboard_will_remind' );
		}

		array_walk( $values[ 'site_social_profiles' ], function( &$value ){
			$value[ 'key' ]= (string) $value[ 'key' ];
		} );
		$values[ 'site_social_profiles' ]=json_encode( array_filter( $values[ 'site_social_profiles' ], function( $value ){
			return (bool)$value[ 'key' ];
		} ) );

		$values[ 'site_address' ] = json_encode( $values[ 'site_address' ] );
		$values[ 'icons_favicon' ] = (string) $values[ 'icons_favicon' ];

		$values=\IPS\core\modules\admin\settings\webapp::processApplicationIcon( $values );
		$values=\IPS\core\modules\admin\settings\terms::processForm( $values );

		/* If a logo was uploaded, do stuff with it... */
		if( isset( $values[ 'initial_logo' ] ) )
		{
			$values[ 'email_logo' ]=(string)$values[ 'initial_logo' ];

			/* Do this once for efficiency */
			$image=\IPS\Image::create( $values[ 'initial_logo' ]->contents() );
			$width=$image->width;
			$height=$image->height;
			unset( $image );

			foreach( \IPS\Theme::themes() as $theme )
			{
				$logoUrl=(string)\IPS\File::create( 'core_Theme', $values[ 'initial_logo' ]->originalFilename, $values[ 'initial_logo' ]->contents(), NULL, TRUE );

				$theme->saveSet( array( 'logo'=>array( 'front'=>array( 'url'=>$logoUrl, 'width'=>$width, 'height'=>$height ) ) ) );
			}

			unset( $values[ 'initial_logo' ] );
		}
		elseif( array_key_exists( 'initial_logo', $values ) )
		{
			unset( $values[ 'initial_logo' ] );
		}

		$form->saveAsSettings( $values );
	}

	/**
	 * Returns the onboarding form
	 *
	 * @return \IPS\Helpers\Form
	 */
	public function getForm(): \IPS\Helpers\Form
	{
		/* Create the form and add our message to it */
		$form = new \IPS\Helpers\Form;
		$form->class = "ipsForm_vertical";

		$form->addTab( 'onboard_tab_identity' );

		/* Super basic configuration options */
		$form->add( new \IPS\Helpers\Form\Text( 'board_name', \IPS\Settings::i()->board_name, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Address( 'site_address', \IPS\GeoLocation::buildFromJson( \IPS\Settings::i()->site_address ), FALSE ) );
		$form->add( new \IPS\Helpers\Form\Stack( 'site_social_profiles', \IPS\Settings::i()->site_social_profiles ? json_decode( \IPS\Settings::i()->site_social_profiles, true ) : array(), FALSE, array( 'stackFieldType' => '\IPS\core\Form\SocialProfiles', 'maxItems' => 50, 'key' => array( 'placeholder' => 'http://example.com', 'size' => 20 ) ) ) );

		$form->add( new \IPS\Helpers\Form\Email( 'email_out', \IPS\Settings::i()->email_out, TRUE, array(), NULL, NULL, NULL, 'email_out' ) );
		$form->add( new \IPS\Helpers\Form\Email( 'email_in', \IPS\Settings::i()->email_in, TRUE ) );

		$form->addTab( 'onboard_tab_appearance' );

		/* If we do not have an email logo and none of our themes have a logo, show the logo field */
		$hasCustomLogo = FALSE;

		foreach( \IPS\Theme::themes() as $theme )
		{
			if( $theme->logo_front )
			{
				$hasCustomLogo = TRUE;
				break;
			}
		}

		if( !\IPS\Settings::i()->email_logo and !$hasCustomLogo )
		{
			$form->add( new \IPS\Helpers\Form\Upload( 'initial_logo', NULL, FALSE, array( 'image' => true, 'storageExtension' => 'core_Theme' ) ) );
		}

		/* Generic favicon - easy enough */
		$form->add( new \IPS\Helpers\Form\Upload( 'icons_favicon', \IPS\Settings::i()->icons_favicon ? \IPS\File::get( 'core_Icons', \IPS\Settings::i()->icons_favicon ) : NULL, FALSE, array( 'obscure' => false, 'allowedFileTypes' => array( 'ico', 'png', 'gif', 'jpeg', 'jpg', 'jpe' ), 'storageExtension' => 'core_Icons' ) ) );

		/* Homescreen icons - we accept one upload and create the images we need */
		$homeScreen = json_decode( \IPS\Settings::i()->icons_homescreen, TRUE ) ?? array();
		$form->add( new \IPS\Helpers\Form\Upload( 'icons_homescreen', ( isset( $homeScreen[ 'original' ] ) ) ? \IPS\File::get( 'core_Icons', $homeScreen[ 'original' ] ) : NULL, FALSE, array( 'image' => true, 'storageExtension' => 'core_Icons' ) ) );

		$form->add( new \IPS\Helpers\Form\Color( 'email_color', \IPS\Settings::i()->email_color, TRUE ) );

		/* These options are only for non-cic */
		if( !\IPS\CIC )
		{
			/* Get the task setting */
			$form->addTab( 'onboard_tab_tasks' );
			\IPS\core\modules\admin\settings\advanced::taskSetting( $form );

			/* Get the htaccess mod_Rewrite setting */
			$form->addTab( 'onboard_tab_furls' );
			\IPS\core\modules\admin\promotion\seo::htaccessSetting( $form );
		}

		/* Add the terms/privacy policy/etc. */
		$form->addTab( 'onboard_tab_privacyterms' );
		\IPS\core\modules\admin\settings\terms::buildForm( $form );

		/* Add dismiss and remind me later buttons */
		if( \IPS\Request::i()->initial )
		{
			$form->addButton( 'onboard_remind_later', 'submit', NULL, 'ipsButton ipsButton_secondary', array( 'name' => 'remind', 'value' => 1, 'csrfKey' => \IPS\Session::i()->csrfKey ) );
			$form->addButton( 'dismiss', 'submit', NULL, 'ipsButton ipsButton_secondary', array( 'name' => 'dismiss', 'value' => 1, 'csrfKey' => \IPS\Session::i()->csrfKey ) );
		}
		return $form;
	}

	/**
	 * Show a form allowing the admin to configure some common settings after an initial installation
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$form = $this->getForm();

		if ( $values = $form->values() )
		{
			$this->handleForm( $values, $form );

			/* Clear create menu caches */
			\IPS\Member::clearCreateMenu();

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			/* Clear data stores */
			unset(
				\IPS\Data\Store::i()->manifest,
				\IPS\Data\Store::i()->iebrowserconfig,
				\IPS\Data\Store::i()->frontNavigation
			);

			\IPS\Session::i()->log( 'acplogs__onboard_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=overview&controller=onboard&do=next' ), 'saved' );
		}

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('onboard_pagetitle_basic');
		\IPS\Output::i()->output	.= $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'dashboard' ), 'onboardForm' ) );
		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'settings/general.css', 'core', 'admin' ) );
	}

	/**
	 * Show a confirmation screen with "next steps"
	 *
	 * @return	void
	 */
	protected function next()
	{
		\IPS\Output::i()->showTitle	= FALSE;
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('onboard_pagetitle');

		/* Add Emoji - We can't put this directly in the template since those without utf8mb4 will have issues */
		\IPS\Member::loggedIn()->language()->words['onboard_complete'] .= ' 🎉';

		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'dashboard' )->onboard();
	}
}