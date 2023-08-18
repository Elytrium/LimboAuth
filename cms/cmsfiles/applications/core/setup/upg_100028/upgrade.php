<?php
/**
 * @brief		4.0.5 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 Apr 2015
 */

namespace IPS\core\setup\upg_100028;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.5 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Make sure all theme settings are applied to every theme.
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		/* We need to kick off some upgrade changes if we're upgrading from the first release of 4.0.0 */
		if ( \IPS\Application::load('core')->long_version >= 40000 )
		{
			/* Update all FileSystem configs */
			foreach ( \IPS\Db::i()->select( '*', 'core_file_storage', array( 'method=?', 'FileSystem' ) ) as $row )
			{
				$config = json_decode( $row['configuration'], TRUE );
				
				$configProtocolRelative = preg_replace( '#^http(s)?://#', '//', $config['url'] );
				$baseProtocolRelative   = preg_replace( '#^http(s)?://#', '//', \IPS\Settings::i()->base_url );
				
				$url = NULL;
				$customUrl = NULL;
				
				if ( mb_stristr( $configProtocolRelative, $baseProtocolRelative ) )
				{
					$url = trim( str_replace( $baseProtocolRelative, '', $configProtocolRelative ), '/' );
				}
				else if ( mb_substr( $config['url'], 0, 2 ) === '//' OR mb_substr( $config['url'], 0, 4 ) === 'http' )
				{
					$customUrl = $config['url'];
				}
				
				$save = array(
					'configuration' => array(
						'dir'		 => str_replace( \IPS\ROOT_PATH, '{root}', $config['dir'] ),
						'url'	     => $url,
						'custom_url' => $customUrl
				) );
				
				$save['configuration'] = json_encode( $save['configuration'] );
				
				\IPS\Db::i()->update( 'core_file_storage', $save, array( 'id=?', $row['id'] ) );
			}
			
			unset( \IPS\Data\Store::i()->storageConfigurations );
		}
		
		/* Kick off the tasks */
		\IPS\core\Setup\Upgrade::repairFileUrls('core');
		\IPS\Task::queue( 'core', 'RebuildContentImages', array( 'class' => 'IPS\core\Statuses\Status' ), 3, array( 'class' ) );
		\IPS\Task::queue( 'core', 'RebuildContentImages', array( 'class' => 'IPS\core\Statuses\Reply' ), 3, array( 'class' ) );
		\IPS\Task::queue( 'core', 'RebuildNonContentImages', array( 'extension' => 'core_Admin' ), 3, array( 'extension' ) );
		\IPS\Task::queue( 'core', 'RebuildNonContentImages', array( 'extension' => 'core_Announcement' ), 3, array( 'extension' ) );
		\IPS\Task::queue( 'core', 'RebuildNonContentImages', array( 'extension' => 'core_CustomField' ), 3, array( 'extension' ) );
		\IPS\Task::queue( 'core', 'RebuildNonContentImages', array( 'extension' => 'core_Signatures' ), 3, array( 'extension' ) );
		\IPS\Task::queue( 'core', 'RebuildNonContentImages', array( 'extension' => 'core_Staffdirectory' ), 3, array( 'extension' ) );

		return TRUE;
	}
}