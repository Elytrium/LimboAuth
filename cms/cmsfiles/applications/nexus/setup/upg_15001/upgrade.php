<?php
/**
 * @brief		1.5.0 Alpha 2 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		19 Dec 2014
 */

namespace IPS\nexus\setup\upg_15001;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 1.5.0 Alpha 2 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Convert renewal options
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		if ( !isset( \IPS\Request::i()->extra ) )
		{
			\IPS\Db::i()->addColumn( 'nexus_packages', array( 'name' => 'p_renew_options', 'type' => 'TEXT' ) );
		}
		
		$offset = isset( \IPS\Request::i()->extra ) ? \intval( \IPS\Request::i()->extra ) : 0;
		$pergo = 50;
		$select = \IPS\Db::i()->select( '*', 'nexus_packages', NULL, 'p_id', array( $offset, $pergo ) );
		if ( \count( $select ) )
		{
			foreach ( $select as $row )
			{
				$renewalOptions = array();
				if ( $row['p_renewals'] )
				{
					$renewalOptions[] = array(
						'unit'	=> $row['p_renewal_unit'],
						'term'	=> $row['p_renewals'],
						'price'	=> $row['p_renewal_price'] ? $row['p_renewal_price'] : $row['p_base_price'],
					);
				}
				
				\IPS\Db::i()->update( 'nexus_packages', array( 'p_renew_options' => \serialize( $renewalOptions ) ), "p_id={$row['p_id']}" );
			}
			
			return $offset + $pergo;
		}
		else
		{
			\IPS\Db::i()->dropColumn( 'nexus_packages', 'p_renewals' );
			\IPS\Db::i()->dropColumn( 'nexus_packages', 'p_renewal_price' );
			\IPS\Db::i()->dropColumn( 'nexus_packages', 'p_renewal_unit' );
			return TRUE;
		}
	}
}