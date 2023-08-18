<?php
/**
 * @brief		recovery
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		03 Nov 2016
 */

namespace IPS\core\modules\admin\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * recovery
 */
class _recovery extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Recover
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Are we even in recovery mode? */
		if ( \IPS\RECOVERY_MODE === FALSE )
		{
			\IPS\Output::i()->error( 'recovery_mode_disabled', '1C342/1', 403, '' );
		}
		
		if ( \IPS\NO_WRITES === TRUE )
		{
			\IPS\Output::i()->error( 'no_writes', '1C342/2', 403, '' );
		}
		
		\IPS\Session::i()->csrfCheck();
		
		/* We are, let's set up a multi-redirect to disable things. At the end of the process, we'll list everything we did. */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'recovery_mode' );
		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect( \IPS\Http\Url::internal( 'app=core&module=support&controller=recovery' )->csrf(), function( $step )
		{
			$step = \intval( $step );
			
			switch( $step )
			{
				case 0: # Applications
					$appsDisabled = array();
					
					/* Disable All non-IPS Applications */
					foreach( \IPS\Application::applications() AS $app )
					{
						if ( !\in_array( $app->directory, \IPS\IPS::$ipsApps ) )
						{
							$app->_enabled = FALSE;
							$appDisabled[] = $app->_id;
						}
					}
					
					$_SESSION['recoveryApps'] = $appsDisabled;
					
					return array( 1, \IPS\Member::loggedIn()->language()->addToStack( 'disabled_applications' ), 25 );
					break;
				
				case 1: # Plugins
					$pluginsDisabled = array();
					
					/* Disable All Plugins */
					foreach( \IPS\Plugin::plugins() AS $plugin )
					{
						$plugin->_enabled = FALSE;
						$plugin->save();
						$pluginsDisabled[] = $plugin->_id;
					}
					
					$_SESSION['recoveryPlugins'] = $pluginsDisabled;
					
					return array( 2, \IPS\Member::loggedIn()->language()->addToStack( 'disabled_plugins' ), 50 );
					break;
				
				case 2: # Reset Theme
					$themeReset = FALSE;
					
					if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_theme_templates', array( "template_set_id>?", 0 ) )->first() OR \IPS\Db::i()->select( 'COUNT(*)', 'core_theme_css', array( "css_set_id>?", 0 ) )->first() )
					{
						/* Create a new theme */
						$theme = new \IPS\Theme;
						$theme->permissions = \IPS\Member::loggedIn()->member_group_id;
						$theme->save();
						$theme->installThemeSettings();
						$theme->copyResourcesFromSet();
						
						\IPS\Lang::saveCustom( 'core', "core_theme_set_title_" . $theme->id, "IPS Support" );
						
						/* Set this account to use that theme */
						\IPS\Member::loggedIn()->skin		= $theme->id;
						\IPS\Member::loggedIn()->save();
						
						$themeReset = TRUE;
					}
					
					$_SESSION['recoveryTheme'] = $themeReset;
					
					return array( 3, \IPS\Member::loggedIn()->language()->addToStack( 'reset_theme_to_default' ), 75 );
					break;
								
				case 4: # Done
					return NULL;
					break;
			}
		}, function()
		{
			\IPS\IPS::resyncIPSCloud('Enabled recovery mode');
			\IPS\Session::i()->log( 'acplog__enabled_recovery' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=support&controller=recovery&do=done' ) );
		} );
	}
	
	/**
	 * "Done" Screen
	 *
	 * @return	void
	 */
	public function done()
	{
		/* Did we disable any apps? */
		$apps = array();
		foreach( $_SESSION['recoveryApps'] AS $app )
		{
			$apps[] = \IPS\Application::load( $app );
		}
		
		/* Did we disable any plugins? */
		$plugins = array();
		foreach( $_SESSION['recoveryPlugins'] AS $plugin )
		{
			$plugins[] = \IPS\Plugin::load( $plugin );
		}
		
		/* Did we reset the theme? */
		$theme = $_SESSION['recoveryTheme'];
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'recovery_mode' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'support' )->recovery( $apps, $plugins, $theme );
	}
}