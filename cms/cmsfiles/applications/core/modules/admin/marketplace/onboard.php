<?php
/**
 * @brief		Marketplace Onboarding Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		1 May 2020
 */

namespace IPS\core\modules\admin\marketplace;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Marketplace Onboarding Controller
 */
class _onboard extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;

	/**
	 * Run
	 *
	 * @return	void
	 */
	final public function manage()
	{
	    /* Has onboarding been completed? */
	    if( \IPS\Settings::i()->mp_onboard_complete )
        {
            \IPS\Output::i()->error( 'marketplace_onboard_already_complete', '2C420/1', 403, '' );
        }

		$steps = [];
		if ( array_filter( array_keys( \IPS\Application::applications() ), function( $k ) { return !\in_array( $k, \IPS\IPS::$ipsApps ); } ) )
		{
			$steps['marketplace_onboard_applications'] = array( $this, '_applications' );
		}
		if ( \IPS\Plugin::plugins() )
		{
			$steps['marketplace_onboard_plugins'] = array( $this, '_plugins' );
		}
		if ( array_filter( \IPS\Theme::themes(), function( $t ) { return $t->isCustomized(); } ) )
		{
			$steps['marketplace_onboard_themes'] = array( $this, '_themes' );
		}
		if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_sys_lang_words', array( 'word_custom IS NOT NULL AND word_export=1' ) )->first() )
		{
			$steps['marketplace_onboard_languages'] = array( $this, '_languages' );
		}
		$steps['marketplace_onboard_complete'] = array( $this, '_complete' );
		if ( !$steps )
		{
			$this->_complete();
		}
		
		$wizard = new \IPS\Helpers\Wizard( $steps, \IPS\Http\Url::internal('app=core&module=marketplace&controller=onboard') );
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('marketplace_onboard_title');
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_marketplace.js', 'core', 'admin' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'marketplace/marketplace.css', 'core', 'admin' ) );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->blurb( \IPS\Member::loggedIn()->language()->addToStack( 'marketplace_onboard_blurb' ) ) . $wizard;
	}
	
	/**
	 * Applications
	 *
	 * @param	mixed	$data	Wizard data
	 * @return	mixed
	 */
	final public function _applications( $data )
	{
		if ( \IPS\Request::i()->match_apps )
		{
			foreach ( \IPS\Application::applications() as $application )
			{
				if ( isset( \IPS\Request::i()->match_apps[ $application->directory ] ) and \IPS\Request::i()->match_apps[ $application->directory ] )
				{
					$application->marketplace_id = \IPS\Request::i()->match_apps[ $application->directory ];
					$application->save();
				}
			}
			
			return array();
		}
		
		$rows = [];
		foreach ( \IPS\Application::applications() as $application )
		{
			if ( !\in_array( $application->directory, \IPS\IPS::$ipsApps ) )
			{
				$rows[ $application->directory ] = \IPS\Theme::i()->getTemplate('marketplace')->onboardRow( $application->directory, $application->marketplace_id, 'apps', $application->_title, $application->version, $application->author, $application->website, 'apps' );
			}
		}
		if ( $rows )
		{
			return \IPS\Theme::i()->getTemplate('marketplace')->onboardTemplate( $rows );
		}
		else
		{
			return array();
		}
	}
	
	/**
	 * Plugins
	 *
	 * @param	mixed	$data	Wizard data
	 * @return	mixed
	 */
	final public function _plugins( $data )
	{
		if ( \IPS\Request::i()->match_plugins )
		{
			foreach ( \IPS\Plugin::plugins() as $plugin )
			{
				if ( isset( \IPS\Request::i()->match_plugins[ $plugin->id ] ) and \IPS\Request::i()->match_plugins[ $plugin->id ] )
				{
					$plugin->marketplace_id = \IPS\Request::i()->match_plugins[ $plugin->id ];
					$plugin->save();
				}
			}
			
			return array();
		}
		
		$rows = [];
		foreach ( \IPS\Plugin::plugins() as $plugin )
		{
			$rows[ $plugin->id ] = \IPS\Theme::i()->getTemplate('marketplace')->onboardRow( $plugin->id, $plugin->marketplace_id, 'apps', $plugin->name, $plugin->version_human, $plugin->author, $plugin->website, 'plugins' );
		}
		if ( $rows )
		{
			return \IPS\Theme::i()->getTemplate('marketplace')->onboardTemplate( $rows );
		}
		else
		{
			return array();
		}
	}
	
	/**
	 * Themes
	 *
	 * @param	mixed	$data	Wizard data
	 * @return	mixed
	 */
	final public function _themes( $data )
	{
		if ( \IPS\Request::i()->match_themes )
		{
			foreach ( \IPS\Theme::themes() as $theme )
			{
				if ( isset( \IPS\Request::i()->match_themes[ $theme->id ] ) and \IPS\Request::i()->match_themes[ $theme->id ] )
				{
					$theme->marketplace_id = \IPS\Request::i()->match_themes[ $theme->id ];
					$theme->save();
				}
			}
			
			return array();
		}
		
		$rows = [];
		foreach ( \IPS\Theme::themes() as $theme )
		{
			if ( $theme->isCustomized() )
			{
				$rows[ $theme->id ] = \IPS\Theme::i()->getTemplate('marketplace')->onboardRow( $theme->id, $theme->marketplace_id, 'themes', $theme->_title, $theme->version, $theme->author_name, $theme->author_url, 'themes' );
			}
		}
		if ( $rows )
		{
			return \IPS\Theme::i()->getTemplate('marketplace')->onboardTemplate( $rows );
		}
		else
		{
			return array();
		}
	}
	
	/**
	 * Languages
	 *
	 * @param	mixed	$data	Wizard data
	 * @return	mixed
	 */
	final public function _languages( $data )
	{
		if ( \IPS\Request::i()->match_languages )
		{
			foreach ( \IPS\Lang::languages() as $language )
			{
				if ( isset( \IPS\Request::i()->match_languages[ $language->id ] ) and \IPS\Request::i()->match_languages[ $language->id ] )
				{
					$language->marketplace_id = \IPS\Request::i()->match_languages[ $language->id ];
					$language->save();
				}
			}
			
			return array();
		}
		
		$rows = [];
		foreach ( \IPS\Lang::languages() as $language )
		{
			if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_sys_lang_words', array( 'lang_id=? AND word_custom IS NOT NULL AND word_export=1', $language->_id ) )->first() )
			{
				$rows[ $language->id ] = \IPS\Theme::i()->getTemplate('marketplace')->onboardRow( $language->id, $language->marketplace_id, 'languages', $language->_title, $language->version, $language->author_name, $language->author_url, 'languages' );
			}
		}
		if ( $rows )
		{
			return \IPS\Theme::i()->getTemplate('marketplace')->onboardTemplate( $rows );
		}
		else
		{
			return array();
		}
	}
	
	/**
	 * Complete
	 *
	 * @param	mixed	$data	Wizard data
	 * @return	mixed
	 */
	final public function _complete()
	{
	    /* Set onboard complete flag */
        \IPS\Settings::i()->changeValues( array( 'mp_onboard_complete' => 1 ) );

		\IPS\core\AdminNotification::remove( 'core', 'ConfigurationError', 'marketplaceSetup' );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=marketplace&controller=marketplace') );
	}
}