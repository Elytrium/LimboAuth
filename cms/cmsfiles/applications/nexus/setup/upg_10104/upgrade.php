<?php
/**
 * @brief		1.1.0 Alpha 5 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		19 Dec 2014
 */

namespace IPS\nexus\setup\upg_10104;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 1.1.0 Alpha 5 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Add renewal IDs to invoices
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \intval( \IPS\Request::i()->extra ) : 0;
		$pergo = 50;
		$select = \IPS\Db::i()->select( '*', 'nexus_invoices', NULL, 'i_id', array( $offset, $pergo ) );
		if ( \count( $select ) )
		{
			foreach ( $select as $row )
			{
				$items = \unserialize( $row['i_items'] );
				$renewalIds = array();
				if ( \is_array( $items ) )
				{
					foreach ( $items as $i )
					{			
						/* New Purchase */
						if ( $row['i_member'] and $row['i_status'] == 'paid' and $i['act'] == 'new' )
						{
							$purchases = array();
							foreach ( \IPS\Db::i()->select( '*', 'nexus_purchases', "ps_member='{$row['i_member']}' AND ps_app='{$i['app']}' AND ps_type='{$i['type']}' AND ps_item_id='{$i['itemID']}' AND ps_start={$row['i_paid']}" ) as $p )
							{
								$purchases[] = $p['ps_id'];
							}
							
							if ( !empty( $purchases ) )
							{
								\IPS\Db::i()->update( 'nexus_purchases', array( 'ps_original_invoice' => $row['i_id'] ), 'ps_id IN(' . implode( ',', $purchases ) . ')' );
							}
						}
						
						/* Renewal */
						elseif ( $i['act'] != 'new' and $i['act'] != 'charge' )
						{
							$renewalIds[] = $i['itemID'];
						}
					}
				}
				
				\IPS\Db::i()->update( 'nexus_invoices', array( 'i_renewal_ids' => implode( ',', $renewalIds ) ), "i_id={$row['i_id']}" );
			}
			
			return $offset + $pergo;
		}
		else
		{
			return TRUE;
		}
	}
}