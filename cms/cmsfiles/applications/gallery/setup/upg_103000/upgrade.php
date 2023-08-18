<?php
/**
 * @brief		4.3.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		03 Nov 2017
 */

namespace IPS\gallery\setup\upg_103000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.3.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Delete copies of images we no longer need, and rebuild the sizes array
	 *
	 * @return bool
	 */
	public function finish()
	{
		\IPS\Task::queue( 'gallery', 'CleanUpGalleryImages', array(), 5 );
		return TRUE;
	}
}