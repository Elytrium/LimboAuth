<?php
/**
 * @brief		Dashboard extension: Current online admins
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Jul 2014
 */

namespace IPS\core\extensions\core\Dashboard;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Dashboard extension: Current online admins
 */
class _OnlineAdmins
{
	/**
	* Can the current user view this dashboard item?
	*
	* @return	bool
	*/
	public function canView()
	{
		return TRUE;
	}

	/**
	 * Return the block to show on the dashboard
	 *
	 * @return	string
	 */
	public function getBlock()
	{
		$admins	= array();

		foreach( \IPS\Db::i()->select( '*', 'core_sys_cp_sessions', NULL, 'session_running_time DESC' ) as $admin )
		{
			$user	= \IPS\Member::load( $admin['session_member_id'] );

			if( $user->member_id )
			{
				$admins[ $user->member_id ]	= array( 'session' => $admin, 'user' => $user );
			}
		}


		return \IPS\Theme::i()->getTemplate( 'dashboard' )->onlineAdmins( $admins );
	}
}