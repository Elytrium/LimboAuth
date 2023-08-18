<?php
/**
 * @brief		1.5.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		19 Dec 2014
 */

namespace IPS\nexus\setup\upg_15003;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 1.5.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Set primary images
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \intval( \IPS\Request::i()->extra ) : 0;
		$pergo = 50;
		$select = \IPS\Db::i()->select( '*', 'nexus_packages', NULL, 'p_id', array( $offset, $pergo ) );
		if ( \count( $select ) )
		{
			foreach ( $select as $row )
			{
				try
				{
					\IPS\Db::i()->update( 'nexus_packages', array( 'p_image' => \IPS\Db::i()->select( 'image_location', 'nexus_package_images', "image_product={$row['p_id']} AND image_primary=1" )->first() ), "p_id={$row['p_id']}" );
				}
				catch ( \UnderflowException $e ) { }
			}
			
			return $offset + $pergo;
		}
		else
		{
			return TRUE;
		}
	}	
}