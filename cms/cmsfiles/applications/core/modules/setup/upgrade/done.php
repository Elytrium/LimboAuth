<?php
/**
 * @brief		Upgrader: Finished Screen
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 May 2014
 */
 
namespace IPS\core\modules\setup\upgrade;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Upgrader: Finished Screen
 */
class _done extends \IPS\Dispatcher\Controller
{
	/**
	 * Finished
	 *
	 * @return	void
	 */
	public function manage()
	{
		\IPS\Output::clearJsFiles();
		
		/* Get rid of temporary upgrade data */
		\IPS\Db::i()->dropTable( 'upgrade_temp', TRUE );
		
		/* Reset theme maps to make sure bad data hasn't been cached by visits mid-setup */
		foreach( \IPS\Theme::themes() as $id => $set )
		{
			/* Update mappings */
			$set->css_map = array();
			$set->save();
		}

		/* Delete some variables we stored in our session */
		unset( $_SESSION['apps'] );

		if( isset( $_SESSION['upgrade_options'] ) )
		{
			unset( $_SESSION['upgrade_options'] );
		}
		
		if( isset( $_SESSION['sqlFinished'] ) )
		{
			unset( $_SESSION['sqlFinished'] );
		}

		if( isset( $_SESSION['uniqueKey'] ) )
		{
			unset( $_SESSION['uniqueKey'] );
		}

		unset( $_SESSION['key'] );

		/* Clear recent datastore logs to prevent an error message displaying immediately after upgrade */
		\IPS\Db::i()->delete( 'core_log', array( '`category`=? AND `time`>?', 'datastore', \IPS\DateTime::create()->sub( new \DateInterval( 'PT1H' ) )->getTimestamp() ) );
		
		/* Unset settings datastore to prevent any upgrade settings that were overridden becoming persistent */
		\IPS\Settings::i()->clearCache();
		
		/* IPS Cloud Sync */
		\IPS\IPS::resyncIPSCloud('Upgraded community');
		
		/* Remove any new version ACP Notifications */
		\IPS\core\AdminNotification::remove( 'core', 'NewVersion' );

		/* Check for php8 method substitution issues */
		if ( \IPS\Application\Scanner::scanCustomizationIssues( false, true ) !== null )
		{
			$foundIssues = \IPS\Application\Scanner::scanCustomizationIssues()[0];
			$disabledApps = array();
			$disabledPlugins = array();
			foreach ( $foundIssues as $appOrPlugin => $classes )
			{
				$components = explode( "-", $appOrPlugin );
				$isApp = \mb_strtolower( trim( $components[0] ) ) === 'app';
				$appDir = trim( $components[1] );

				/* Disable the plugin/app and flag for manual intervention */
				if ( $isApp and ( empty( $disabledApps[$appDir] ) ) )
				{
					/* IPS Code is flawless, skip it */
					if ( \in_array( $appDir, \IPS\IPS::$ipsApps ) )
					{
						continue;
					}

					try
					{
						$app = \IPS\Application::load( $appDir );
						$app->_enabled = false;

						if ( ! \IPS\IN_DEV )
						{
							$app->requires_manual_intervention = 1;
						}
						
						$app->save();
						$disabledApps[$appDir] = $app->_title;
					}
					catch ( \InvalidArgumentException|\OutOfRangeException $e )
					{
						/* This still won't work, so don't leave it empty */
						$disabledApps[$appDir] = $appDir;
						continue;
					}
				}
				elseif ( empty( $disabledPlugins[$appDir] ) )
				{
					try
					{
						$pluginId = \IPS\Db::i()->select( 'plugin_id', 'core_plugins', [ 'plugin_location=?', $appDir ], null, 1 )->first();
						$plugin = \IPS\Plugin::load( $pluginId );
						$plugin->_enabled = false;

						if ( ! \IPS\IN_DEV )
						{
							$plugin->requires_manual_intervention = 1;
						}

						$plugin->save();
						$disabledPlugins[$appDir] = $plugin->name;
					}
					catch ( \UnderflowException|\InvalidArgumentException|\OutOfRangeException $e )
					{
						/* This still won't work, so don't leave it empty */
						$disabledPlugins[$appDir] = $appDir;
						continue;
					}
				}
			}

			if ( \count( $disabledPlugins ) OR \count( $disabledApps ) )
			{
				\IPS\core\AdminNotification::send( 'core', 'ManualInterventionMessage' );

				$_SESSION['upgrade_postUpgrade']['core'][] = \IPS\Theme::i()->getTemplate( 'global' )->methodIssues(
					\IPS\Theme::i()->getTemplate( 'global' )->disabled3rdParty( array_keys( $disabledApps ), array_keys( $disabledPlugins ), false )
				);
			}
		}
		
		/* And show the complete page - the template handles this step special already so we don't have to output anything */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('done');
		
		/* The upgrader will cause a few syncs on Cloud2, but we don't have to wait for those. Clear the flag here. */
		unset( \IPS\Data\Store::i()->syncCompleted );
	}
}