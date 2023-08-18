<?php
/**
 * @brief		Announcements Extension : Forums
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		28 Apr 2014
 */

namespace IPS\forums\extensions\core\Announcements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Announcements Extension: Forums
 */
class _Forums
{
	/**
	 * @brief	ID Field
	 */
	public static $idField = "id";
	
	/**
	 * @brief	Controller classes
	 */
	public static $controllers = array(
		"IPS\\forums\\modules\\front\\forums\\forums",
		"IPS\\forums\\modules\\front\\forums\\topic",
		"IPS\\forums\\modules\\front\\forums\\index"
	);

	/**
	 * Get Setting Field
	 *
	 * @param	\IPS\core\Announcements\Announcement	$announcement
	 * @return	Form element
	 */
	public function getSettingField( $announcement )
	{
		return new \IPS\Helpers\Form\Node( 'announce_forums', ( $announcement AND $announcement->ids ) ? explode( ",", $announcement->ids ) : 0, FALSE, array( 'class' => 'IPS\forums\Forum', 'zeroVal' => 'any', 'multiple' => TRUE, 'permissionCheck' => function ( $forum )
		{
			return $forum->sub_can_post and !$forum->redirect_url;
		} ), NULL, NULL, NULL, 'announce_forums' );
	}
}