<?php
/**
 * @brief		Upgrader: Custom Upgrade Options
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		26 Oct 2015
 */

$options = array();

/* Check utilitiesMenu */
try
{
	$affectedThemes = array();
	
	foreach( \IPS\Db::i()->select( '*', 'core_theme_templates', array( array( 'template_set_id > 0 and template_name=? and template_group=? and template_app=? and template_location=?', 'globalTemplate', 'global', 'core', 'front' ) ) ) as $template )
	{
		if ( mb_stristr( $template['template_content'], '{template="utilitiesMenu"' ) )
		{
			$affectedThemes[] = $template['template_set_id'];
		}
	}
	
	if ( \count( $affectedThemes ) )
	{
		$options[] = new \IPS\Helpers\Form\Radio( '101000_globalTemplate_revert', 'no', TRUE, array( 'options' => array( 'yes' => '101000_yes', 'no' => '101000_no' ) ) );
	}
}
catch( \Exception $ex ) { }

/* We are going to go ahead and disable applications and plugins to prevent 3rd party code from breaking the upgrader (and/or the suite post-upgrade) - this doesn't
	apply if we're upgrading from a version before 4.0 (i.e. 3.x) since they won't have any applications or plugins */
if ( $application->long_version >= 40000 and !\IPS\NO_WRITES )
{
	$disabledAppNames 		= array();
	$disabledPluginNames	= array();

	/* Loop Apps */
	$language	= \IPS\Lang::load( \IPS\Lang::defaultLanguage() );

	foreach ( \IPS\Application::applications() as $_app )
	{
		if ( $_app->enabled and !\in_array( $_app->directory, \IPS\IPS::$ipsApps ) )
		{
			try
			{
				$disabledAppNames[] = $language->get( '__app_' . $_app->directory );
			}
			catch( \UnderflowException $e )
			{
				$disabledAppNames[] = $_app->directory;
			}

			$_app->enabled = FALSE;
			$_app->save( TRUE );
		}
	}
	
	/* Look Plugins */
	if( \IPS\Db::i()->checkForTable( 'core_plugins' ) )
	{
		foreach ( \IPS\Plugin::plugins() as $plugin )
		{
			if ( $plugin->enabled )
			{
				$plugin->enabled = FALSE;

				try
				{
					$plugin->save();
				}
				catch( \IPS\Db\Exception $e )
				{
					/* Column may be missing from a table, i.e. upgrading from an old version where set_cache_key doesn't exist */
					if( $e->getCode() != 1054 )
					{
						throw $e;
					}
				}

				$disabledPluginNames[] = $plugin->_title;
			}
		}
	}

	if ( \count( $disabledAppNames ) OR \count( $disabledPluginNames ) )
	{
		$_SESSION['upgrade_postUpgrade']['core'][101000] = \IPS\Theme::i()->getTemplate( 'global' )->disabled3rdParty( $disabledAppNames, $disabledPluginNames );

		/* Show us what is disabled */
		$options[] = new \IPS\Helpers\Form\Custom( '101000_disable_3rdparty', null, FALSE, array( 'getHtml' => function( $element ) use ( $disabledAppNames, $disabledPluginNames ){
			return \IPS\Theme::i()->getTemplate( 'global' )->disabled3rdParty( $disabledAppNames, $disabledPluginNames );
		} ), function( $val ) {}, NULL, NULL, '101000_disable_3rdparty' );
	}
}