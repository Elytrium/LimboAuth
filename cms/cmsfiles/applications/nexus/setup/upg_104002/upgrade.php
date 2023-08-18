<?php
/**
 * @brief		4.4.0 Beta 3 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		01 Feb 2019
 */

namespace IPS\nexus\setup\upg_104002;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.4.0 Beta 3 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Remove orphaned product options
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Db::i()->delete( 'nexus_product_options', array( 'opt_package NOT IN (?)', \IPS\Db::i()->select( 'p_id', 'nexus_packages' ) ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Removing orphaned product options";
	}

	/**
	 * Remove orphaned product base prices
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\Db::i()->delete( 'nexus_package_base_prices', array( 'id NOT IN (?)', \IPS\Db::i()->select( 'p_id', 'nexus_packages' ) ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Removing orphaned product base prices";
	}

	/**
	 * Remove orphaned package images
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		$perCycle	= 25;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );

		/* Try to prevent timeouts to the extent possible */
		$cutOff = \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'nexus_package_images', array( 'image_product NOT IN (?)', \IPS\Db::i()->select( 'p_id', 'nexus_packages' ) ), 'image_id ASC', array( $limit, $perCycle ) ) as $image )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			/* Delete the actual image */
			try
			{
				\IPS\File::get( 'nexus_Products', $image['image_location'] )->delete();
			}
			catch( \Exception $e ) {}
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			\IPS\Db::i()->delete( 'nexus_package_images', array( 'image_product NOT IN (?)', \IPS\Db::i()->select( 'p_id', 'nexus_packages' ) ) );

			unset( $_SESSION['_step3Count'] );

			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step3Count'] ) )
		{
			$_SESSION['_step3Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_package_images', array( 'image_product NOT IN (?)', \IPS\Db::i()->select( 'p_id', 'nexus_packages' ) ) )->first();
		}

		return "Removing orphaned package images (Removed so far: " . ( ( $limit > $_SESSION['_step3Count'] ) ? $_SESSION['_step3Count'] : $limit ) . ' out of ' . $_SESSION['_step3Count'] . ')';
	}
}