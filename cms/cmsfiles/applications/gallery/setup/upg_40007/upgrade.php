<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		24 Oct 2014
 */

namespace IPS\gallery\setup\upg_40007;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Conversion from 3.0
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		if ( !\IPS\Db::i()->checkForColumn( 'gallery_images', 'image_gps_latlon' ) )
		{
			\IPS\Db::i()->addColumn( 'gallery_images', array( 'name' => 'image_gps_latlon', 'type' => 'VARCHAR', 'length' => 255, 'allow_null' => false, 'default' => '' ) );
		}

		if ( !\IPS\Db::i()->checkForTable( 'gallery_albums_temp' ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'gallery_albums_temp',
				'columns'	=> array(
					array(
						'name'			=> 'album_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> '0'
					),
					array(
						'name'			=> 'album_g_perms_view',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					)
				)
			) );
		}
		
		return TRUE;
	}
}