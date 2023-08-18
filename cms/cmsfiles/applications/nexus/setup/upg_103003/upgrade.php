<?php
/**
 * @brief		4.3.0 Beta 3 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		28 Mar 2018
 */

namespace IPS\nexus\setup\upg_103003;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.3.0 Beta 3 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Clean up orphaned Commerce packages from 3.x
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Do we even need to do this? */
		$count = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_packages', array( "p_group!=? AND " . \IPS\Db::i()->in( 'p_group', iterator_to_array( \IPS\Db::i()->select( 'pg_id', 'nexus_package_groups' ) ), TRUE ), 0 ) )->first();
		
		if ( $count )
		{
			/* Create a new group */
			try
			{
				$position = (int) \IPS\Db::i()->select( 'MAX(pg_position)', 'nexus_package_groups' )->first();
				$position++;
			}
			catch( \Exception $e )
			{
				/* Technically shouldn't happen but... */
				$position = 1;
			}
			
			$group = new \IPS\nexus\Package\Group;
			$group->position = $position;
			$group->save();
			\IPS\Lang::saveCustom( 'nexus', "nexus_pgroup_{$group->id}", "Products" );
			
			foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_packages', array( "p_group!=? AND " . \IPS\Db::i()->in( 'p_group', iterator_to_array( \IPS\Db::i()->select( 'pg_id', 'nexus_package_groups' ) ), TRUE ), 0 ) ), 'IPS\nexus\Package' ) AS $pkg )
			{
				$pkg->group = $group->id;
				$pkg->save();
			}
		}
		
		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step1CustomTitle()
	{
		return 'Cleaning up orphaned commerce packages';
	}
}