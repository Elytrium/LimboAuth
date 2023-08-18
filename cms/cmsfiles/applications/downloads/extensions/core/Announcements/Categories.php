<?php
/**
 * @brief		Announcements Extension : Download Categories
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		28 Apr 2014
 */

namespace IPS\downloads\extensions\core\Announcements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Announcements Extension: Download Categories
 */
class _Categories
{
	/**
	 * @brief	ID Field
	 */
	public static $idField = "id";
	
	/**
	 * @brief	Controller classes
	 */
	public static $controllers = array( "IPS\\downloads\\modules\\front\\downloads\\browse" );
	
	/**
	 * Get Setting Field
	 *
	 * @param	\IPS\core\Announcements\Announcement	$announcement
	 * @return	Form element
	 */
	public function getSettingField( $announcement )
	{
		return new \IPS\Helpers\Form\Node( 'announce_download_categories', ( $announcement AND $announcement->ids ) ? explode( ",", $announcement->ids ) : 0, FALSE, array( 'class' => 'IPS\downloads\Category', 'zeroVal' => 'any', 'multiple' => TRUE ), NULL, NULL, NULL, 'announce_download_categories' );
	}
}