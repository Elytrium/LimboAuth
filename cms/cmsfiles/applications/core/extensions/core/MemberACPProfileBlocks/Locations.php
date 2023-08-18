<?php
/**
 * @brief		ACP Member Profile: Locations Map Block
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
 * @brief	ACP Member Profile: Locations Map Block
 */
class _Locations extends \IPS\core\MemberACPProfile\LazyLoadingBlock
{
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function lazyOutput()
	{
		$mapMarkers = array();
		if ( \IPS\Settings::i()->ipsgeoip and \IPS\GeoLocation::enabled() )
		{
			foreach ( \IPS\Db::i()->select( 'DISTINCT ip_address', 'core_members_known_ip_addresses', array( 'member_id=?', $this->member->member_id ) ) as $ipAddress )
			{
				try
				{
					$location = \IPS\GeoLocation::getByIp( $ipAddress );
					$mapMarkers[ $ipAddress ] = array(
						'lat'	=> \floatval( $location->lat ),
						'long'	=> \floatval( $location->long ),
						'title'	=> $ipAddress
					);
				}
				catch ( \Exception $e ) { }
			}
		}
		
		return \IPS\Theme::i()->getTemplate('memberprofile')->locations( $this->member, $mapMarkers );
	}
}