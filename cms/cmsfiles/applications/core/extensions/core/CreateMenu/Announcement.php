<?php
/**
 * @brief		Create Menu Extension : Announcement
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Jun 2015
 */

namespace IPS\core\extensions\core\CreateMenu;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Create Menu Extension: Announcement
 */
class _Announcement
{
	/**
	 * Get Items
	 *
	 * @return	array
	 */
	public function getItems()
	{
		if ( \IPS\Member::loggedIn()->modPermission('can_manage_announcements') )
		{
			return array(
				'announcement' => array(
					'link' 				=> \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=announcements&action=create', 'front', 'modcp_announcements' ),
					'title' 			=> 'add_announcement',
					'extraData'			=> array( 'data-ipsDialog' => true )
				)
			);
		}
		
		return array();
	}
}