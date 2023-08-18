<?php
/**
 * @brief		4.1.5 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		06 Nov 2015
 */

namespace IPS\core\setup\upg_101018;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.5 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix custom furl definition keys from a previous fix that wasn't so accurate
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		$existing = array();
		foreach ( \IPS\Application::applications() as $app )
		{
			if( file_exists( \IPS\ROOT_PATH . "/applications/{$app->directory}/data/furl.json" ) )
			{
				$data = json_decode( preg_replace( '/\/\*.+?\*\//s', '', \file_get_contents( \IPS\ROOT_PATH . "/applications/{$app->directory}/data/furl.json" ) ), TRUE );
				$existing = array_merge( $existing, array_keys( $data['pages'] ) );
			}
		}
							
		$json = array();
		try
		{
			$config = \IPS\Db::i()->select( 'conf_value', 'core_sys_conf_settings', array( array( 'conf_key=?', 'furl_configuration' ) ) )->first();
			$json   = array();
			
			if( $config )
			{
				foreach( json_decode( $config, TRUE ) as $k => $v )
				{
					if ( mb_substr( $k, 0, 3 ) === 'key' )
					{
						$originalKey = mb_substr( $k, 3 );
						
						if ( \in_array( $originalKey, $existing ) )
						{
							$json[ $originalKey ] = $v;
						}
						else
						{
							$json[ $k ] = $v;
						}
					}
					else
					{
						$json[ $k ] = $v;
					}
				}
		
				\IPS\Settings::i()->changeValues( array( 'furl_configuration' => json_encode( $json ) ) );
			}
		}
		catch( \UnderflowException $e ) { }
		
		return TRUE;
	}
}