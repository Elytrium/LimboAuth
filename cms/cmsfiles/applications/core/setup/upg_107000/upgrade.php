<?php
/**
 * @brief		4.7.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		27 Apr 2022
 */

namespace IPS\core\setup\upg_107000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.7.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Install the platform app for platform clients if it's not there which it won't be, probably.
	 *
	 * @return	bool|array 	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		if ( \IPS\CIC and ! \in_array( 'cloud', array_keys( \IPS\Application::applications() ) ) )
		{
			/* Load in our CiC functions */
			if ( isset( $_SERVER['IPS_CLOUD2'] ) AND isset( $_SERVER['IPS_CLOUD2_ID'] ) AND file_exists( "/var/www/sitefiles/{$_SERVER['IPS_CLOUD2_ID']}/applications/cloud/sources/functions.php" ) )
			{
				/* If we are uploading the cloud app manually, we need this */
				@include_once( "/var/www/sitefiles/{$_SERVER['IPS_CLOUD2_ID']}/applications/cloud/sources/functions.php" );
			}
			else if( file_exists( __DIR__ . '/applications/cloud/sources/functions.php' ) )
			{
				@require_once( __DIR__ . '/applications/cloud/sources/functions.php' );
			}

			\IPS\Cicloud\install();
		}

		return TRUE;
	}
  
  /**
	 * Update the setting for excluding groups from search logs
	 *
	 * @return	bool|array 	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\Db::i()->insert( 'core_sys_conf_settings', array( 'conf_app' => 'core', 'conf_key' => 'searchlog_exclude_groups', 'conf_value' => json_encode( array( \IPS\Settings::i()->guest_group ) ) ), TRUE );
		return TRUE;
	}
	
	/**
	 * Adjust search index
	 *
	 */
	public function step3()
	{
		/* Disable third party addons to prevent errors during upgrade */
		foreach( \IPS\Application::enabledApplications() as $app )
		{
			if( !\in_array( $app->directory, \IPS\IPS::$ipsApps ) )
			{
				$app->enabled = false;
				$app->save();
			}
		}

		/* Truncate index, as we have to rebuild index to get the solved flag populated */
		\IPS\Content\Search\Index::i()->prune();
		
		$json = <<<EOF
		[
			{
				"method": "addColumn",
				"params": [
					"core_search_index",
					{
						"name": "index_item_solved",
						"type": "TINYINT",
						"length": null,
						"decimals": null,
						"values": null,
						"allow_null": true,
						"default": null,
						"comment": "Object solved status",
						"unsigned": true,
						"auto_increment": false
					}
				]
			}
		]
EOF;
		$queries = json_decode( $json, TRUE );
		
		foreach( $queries as $query )
		{
			try
			{
				$run = \call_user_func_array( array( \IPS\Db::i(), $query['method'] ), $query['params'] );
			}
			catch( \IPS\Db\Exception $e )
			{
				if( !in_array( $e->getCode(), array( 1007, 1008, 1050, 1060, 1061, 1062, 1091, 1051 ) ) )
				{
					throw $e;
				}
			}
		}
		
		\IPS\Content\Search\Index::i()->rebuild();

		return TRUE;
	}


	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}