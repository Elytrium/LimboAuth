<?php
/**
 * @brief		4.1.5 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		24 Nov 2015
 */

namespace IPS\nexus\setup\upg_101017;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.5 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix nexus_package_base_prices
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		
		if ( !$offset )
		{
			\IPS\Db::i()->dropTable( 'nexus_package_base_prices', TRUE );
	
			$schema = array(
				'name' => 'nexus_package_base_prices',
				'columns' => array(
					'id' =>  array(
						'name'				=> 'id',
						'type'				=> 'BIGINT',
						'length'			=> '20',
						'unsigned'			=> true,
						'zerofill'			=> false,
						'binary'			=> false,
						'allow_null'		=> false,
						'default'			=> NULL,
						'auto_increment'	=> true,
					),
				),
				'indexes' =>  array(
					'PRIMARY' => array (
						'type'		=> 'primary',
						'name'		=> 'PRIMARY',
						'columns'	=> array( 'id' )
					)
				)
			);
			
			foreach ( \IPS\nexus\Money::currencies() as $currency )
			{
				$schema['columns'][ $currency ] = array(
					'name'	=> $currency,
					'type'	=> 'FLOAT'
				);
			}
			
			\IPS\Db::i()->createTable( $schema );
		}
		
		$select = \IPS\Db::i()->select( '*', 'nexus_packages', NULL, 'p_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{
				$basePrices = json_decode( $row['p_base_price'], TRUE );
		
				$insert = array( 'id' => $row['p_id'] );
				foreach ( \IPS\nexus\Money::currencies() as $currency )
				{
					if ( isset( $basePrices[ $currency ] ) )
					{
						$insert[ $currency ] = $basePrices[ $currency ]['amount'];
					}
					else
					{
						$insert[ $currency ] = NULL;
					}
				}
				
				\IPS\Db::i()->insert( 'nexus_package_base_prices', $insert );
			}
			
			return $offset + 100;
		}
		else
		{
			unset( $_SESSION['_step1Count'] );
			return TRUE;
		}


		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}