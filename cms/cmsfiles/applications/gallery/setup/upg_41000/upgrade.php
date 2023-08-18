<?php
/**
 * @brief		4.1.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		17 Oct 2017
 */

namespace IPS\gallery\setup\upg_41000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * ...
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		try
		{
			\IPS\Db::i()->delete( 'custom_bbcode', array( 'bbcode_tag=? AND bbcode_php_plugin=?', 'gallery', 'gallery.php' ) );
		}
		catch ( \IPS\Db\Exception $e )
		{
			/* This table may not exist, depending on the version they are upgrading from */
			if( $e->getCode() != 1146 )
			{
				throw $e;
			}
		}

		return TRUE;
	}
}