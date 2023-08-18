<?php
/**
 * @brief		Create Menu Extension : Status
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 Sep 2014
 */

namespace IPS\core\extensions\core\CreateMenu;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Create Menu Extension: Status
 */
class _Status
{
	/**
	 * Get Items
	 *
	 * @return	array
	 */
	public function getItems()
	{
		if ( \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) and \IPS\core\Statuses\Status::canCreateFromCreateMenu() and \IPS\Settings::i()->profile_comments )
		{
			return array(
				'member_status' => array(
					'link' 				=> \IPS\Http\Url::internal( 'app=core&module=status&controller=ajaxcreate', 'front' ),
					'title' 			=> 'status_update',
					'flashMessage'		=> 'saved',
					'extraData'			=> array( 'data-ipsdialog-remotesubmit' => true, 'data-ipsDialog' => true, 'data-role' => 'updateStatus' )
				)
			);
		}
		return array();
	}
}