<?php
/**
 * @brief		1.2 Alpha 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		19 Dec 2014
 */

namespace IPS\nexus\setup\upg_11100;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 1.2 Alpha 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Packages
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \intval( \IPS\Request::i()->extra ) : 0;
		$pergo = 2;
		$select = \IPS\Db::i()->select( '*', 'nexus_packages', NULL, 'p_id', array( $offset, $pergo ) );
		if ( \count( $select ) )
		{
			foreach ( $select as $row )
			{
				\IPS\Db::i()->insert( 'nexus_packages_products', array(
					'p_id'					=> $row['p_id'],
					'p_physical'			=> $row['p_physical'],
					'p_shipping'			=> $row['p_shipping'],
					'p_weight'				=> $row['p_weight'],
					'p_lkey'				=> $row['p_lkey'],
					'p_lkey_identifier'		=> $row['p_lkey_identifier'],
					'p_lkey_uses'			=> \intval( $row['p_lkey_uses'] ),
				) );
			}
			
			return $offset + $pergo;
		}
		else
		{
			\IPS\Db::i()->dropColumn( 'nexus_packages', 'p_physical' );
			\IPS\Db::i()->dropColumn( 'nexus_packages', 'p_shipping' );
			\IPS\Db::i()->dropColumn( 'nexus_packages', 'p_weight' );
			\IPS\Db::i()->dropColumn( 'nexus_packages', 'p_lkey' );
			\IPS\Db::i()->dropColumn( 'nexus_packages', 'p_lkey_identifier' );
			\IPS\Db::i()->dropColumn( 'nexus_packages', 'p_lkey_uses' );
			
			return TRUE;
		}
	}
	
	/**
	 * Ads
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		if ( isset( \IPS\Request::i()->extra ) )
		{
			$extra = explode( ',', \IPS\Request::i()->extra );
		}
		else
		{
			$group = \IPS\Db::i()->insert( 'nexus_package_groups', array(
				'pg_name'		=> "Advertisements",
				'pg_seo_name'	=> 'advertisements',
				'pg_parent'		=> 0,
			) );
				
			$extra = array( 0 => $group, 1 => 0 );
		}
		
		$offset = $extra[1];
		$pergo = 2;
		$select = \IPS\Db::i()->select( '*', 'nexus_adpacks', NULL, 'ap_id', array( $offset, $pergo ) );
		if ( \count( $select ) )
		{
			foreach ( $select as $row )
			{
				$renewals = 0;
				$renewal_unit = '';
				if ( \in_array( $row['ap_expire_unit'], array( 'd', 'w', 'm', 'y' ) ) )
				{
					$renewals = $row['ap_expire'];
					$renewal_unit = $row['ap_expire_unit'];
					$row['ap_expire'] = 0;
					$row['ap_expire_unit'] = 'i';
				}
			
				$p_id = \IPS\Db::i()->insert( 'nexus_packages', array(
					'p_name'				=> $row['ap_name'],
					'p_seo_name'			=> \IPS\Http\Url\Friendly::seoTitle( $row['ap_name'] ),
					'p_desc'				=> $row['ap_desc'],
					'p_group'				=> $extra[0],
					'p_stock'				=> -1,
					'p_reg'					=> 0,
					'p_store'				=> 1,
					'p_member_groups'		=> '*',
					'p_allow_upgrading'		=> 0,
					'p_upgrade_charge'		=> 0,
					'p_allow_downgrading'	=> 0,
					'p_downgrade_refund'	=> 0,
					'p_base_price'			=> $row['ap_price'],
					'p_tax'					=> 0,
					'p_renewals'			=> $renewals,
					'p_renewal_price'		=> 0,
					'p_renewal_unit'		=> $renewal_unit,
					'p_renewal_days'		=> 0,
					'p_primary_group'		=> 0, 
					'p_secondary_group'		=> 0,
					'p_perm_set'			=> 0,
					'p_return_primary'		=> 1,
					'p_return_secondary'	=> 1,
					'p_return_perm'			=> 1,
					'p_module'				=> '',
					'p_position'			=> $row['ap_order'],
					'p_associable'			=> 0,
					'p_force_assoc'			=> 0,
					'p_assoc_error'			=> '',
					'p_featured'			=> 0,
					'p_discounts'			=> \serialize( array( 'loyalty' => array(), 'bundle' => array(), 'usergroup' => array() ) ),
					'p_page'				=> 0,
					'p_support'				=> 0,
					'p_support_department'	=> 0,
					'p_support_severity'	=> 0,
					'p_upsell'				=> 0,
					'p_notify'				=> '',
					'p_type'				=> 'ad',
					) );
				
				\IPS\Db::i()->insert( 'nexus_packages_ads', array(
					'p_id'					=> $p_id,
					'p_locations'			=> $row['ap_locations'],
					'p_exempt'				=> $row['ap_exempt'],
					'p_expire'				=> $row['ap_expire'],
					'p_expire_unit'			=> $row['ap_expire_unit'],
					'p_max_height'			=> $row['ap_max_height'],
					'p_max_width'			=> $row['ap_max_width'],
				) );
				
				\IPS\Db::i()->update( 'nexus_purchases', array( 'ps_type' => 'newad', 'ps_item_id' => $p_id ), "ps_app='nexus' AND ps_type='ad' AND ps_name='{$row['ap_name']}'" );
			}
			
			$extra[1] += $pergo;
			return implode( ',', $extra );
		}
		else
		{
			\IPS\Db::i()->update( 'nexus_purchases', array( 'ps_type' => 'ad', 'ps_item_uri' => '' ), "ps_type='newad'" );
			\IPS\Db::i()->dropTable( 'nexus_adpacks' );
			
			return TRUE;
		}
	}
}