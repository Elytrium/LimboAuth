<?php
/**
 * @brief		ACP Member Profile: Main Tab
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Nov 2017
 */

namespace IPS\core\extensions\core\MemberACPProfileTabs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: Main Tab
 */
class _Main extends \IPS\core\MemberACPProfile\MainTab
{
	/**
	 * Get left-column blocks
	 *
	 * @return	array
	 */
	public function leftColumnBlocks()
	{
		$return = array();
		$return[] = 'IPS\core\extensions\core\MemberACPProfileBlocks\BasicInformation';
		$return[] = 'IPS\core\extensions\core\MemberACPProfileBlocks\Groups';
		$return[] = 'IPS\core\extensions\core\MemberACPProfileBlocks\Referrals';
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'membertools_ip' ) )
		{
			$return[] = 'IPS\core\extensions\core\MemberACPProfileBlocks\DevicesAndIPAddresses';
		}
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_mfa' ) )
		{
			$return[] = 'IPS\core\extensions\core\MemberACPProfileBlocks\MFA';
		}
		
		$return[] = 'IPS\core\extensions\core\MemberACPProfileBlocks\OAuth';
		
		return $return;
	}
	
	/**
	 * Get main-column blocks
	 *
	 * @return	array
	 */
	public function mainColumnBlocks()
	{
		return array(
			'IPS\core\extensions\core\MemberACPProfileBlocks\Header',
			'IPS\core\extensions\core\MemberACPProfileBlocks\ContentStatistics',
			'IPS\core\extensions\core\MemberACPProfileBlocks\Points',
			'IPS\core\extensions\core\MemberACPProfileBlocks\Quotas',
			'IPS\core\extensions\core\MemberACPProfileBlocks\Warnings',
			'IPS\core\extensions\core\MemberACPProfileBlocks\ProfileData',
			'IPS\core\extensions\core\MemberACPProfileBlocks\Notifications',
		);
	}
}