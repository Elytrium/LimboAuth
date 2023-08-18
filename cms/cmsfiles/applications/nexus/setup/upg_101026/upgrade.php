<?php
/**
 * @brief		4.1.9 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		5 Feb 2016
 */

namespace IPS\nexus\setup\upg_101026;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.9 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix Customer Addresses
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$perCycle	= 500;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );
		$cutOff		= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'nexus_customer_addresses', array(), NULL, array( $limit, $perCycle ) ) as $row )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			$address = json_decode( $row['address'], TRUE );

			if( $address['addressLines'][0] == NULL )
			{
				array_shift( $address['addressLines'] );
				\IPS\Db::i()->update( 'nexus_customer_addresses', array( 'address' => json_encode( $address ) ), array( 'id=?', $row['id'] ) );
			}

		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			return TRUE;
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Fixing customer addresses";
	}
}