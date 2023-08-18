<?php
/**
 * @brief		ACP Member Profile: Devices & IP Addresses Block
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Nov 2017
 */

namespace IPS\core\extensions\core\MemberACPProfileBlocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: Devices & IP Addresses Block
 */
class _DevicesAndIPAddresses extends \IPS\core\MemberACPProfile\Block
{
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output()
	{
		$lastUsedIp = $this->member->lastUsedIp();
		$devices = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members_known_devices', array( 'member_id=?', $this->member->member_id ), 'last_seen DESC', 5 ), 'IPS\Member\Device' );
		
		return \IPS\Theme::i()->getTemplate('memberprofile')->devicesAndIPAddresses( $this->member, $lastUsedIp, $devices );
	}
}