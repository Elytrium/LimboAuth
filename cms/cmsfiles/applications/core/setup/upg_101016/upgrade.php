<?php
/**
 * @brief		4.1.4 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		06 Nov 2015
 */

namespace IPS\core\setup\upg_101016;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.4 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix ACP Restrictions
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$perCycle	= 50;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );
		$cutOff		= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'core_admin_permission_rows', NULL, 'row_id_type,row_id', array( $limit, $perCycle ) ) as $row )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}
			$did++;

			if ( $row['row_perm_cache'] != '*' and $row['row_perm_cache'] != '[]' )
			{
				$perms = json_decode( $row['row_perm_cache'], TRUE );
				$applications = array();

				/* Might have a broken permissions row that isn't stored in a format we expect */
				if( !isset( $perms['applications'] ) )
				{
					continue;
				}

				foreach ( $perms['applications'] as $k => $app )
				{
					/* We've stored ACP permissions in different ways - one way was 'app' => array( 'module1', 'module2' ) */
					if( \is_array( $app ) )
					{
						$applications[ $k ] = $app;
					}
					else
					{
						$applications[ $app ] = $perms['modules'];
					}
				}
				$perms['applications'] = $applications;
				unset( $perms['modules'] );
				
				\IPS\Db::i()->update( 'core_admin_permission_rows', array( 'row_perm_cache' => json_encode( $perms ) ), array( 'row_id=? AND row_id_type=?', $row['row_id'], $row['row_id_type'] ) );
			}		
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			unset( \IPS\Data\Store::i()->administrators );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Fixing admin permissions";
	}

	/**
	 * Clean up invalid themes set for a user
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$toRunQueries	= array(
			array(
				'table'	=> 'core_members',
				'query'	=> "UPDATE " . \IPS\Db::i()->prefix . "core_members SET skin=0 WHERE skin NOT IN(SELECT set_id FROM " . \IPS\Db::i()->prefix . "core_themes)",
			)
		);

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $toRunQueries );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 'extra' => array( '_upgradeStep' => 3 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Removing orphaned member theme preferences";
	}
	
	/**
	 * Fix orphaned profile fields
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		/* If there are none, do not bother */
		$count = \IPS\Db::i()->select( 'COUNT(*)', 'core_pfields_data', array( "pf_group_id=?", 0 ) )->first();
		
		if ( !$count )
		{
			return TRUE;
		}
		
		$group = new \IPS\core\ProfileFields\Group;
		$group->save();
		
		\IPS\Lang::saveCustom( 'core', "core_pfieldgroups_{$group->id}", 'Uncategorized' );
		
		\IPS\Db::i()->update( 'core_pfields_data', array( "pf_group_id" => $group->id ), array( "pf_group_id=?", 0 ) );
		
		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Fixing orphaned profile fields";
	}

	/**
	 * Fix searchable profile fields
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		$searchableFields = array( 'Email', 'Tel', 'Text', 'TextArea', 'Url' );

		\IPS\Db::i()->update( 'core_pfields_data', array( 'pf_search_type' => '' ), \IPS\Db::i()->in( 'pf_type', $searchableFields, TRUE ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step4CustomTitle()
	{
		return "Fixing searchable custom fields";
	}

	
	/**
	 * Fix custom furl definition keys
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		$json = array();
		try
		{
			$config = \IPS\Db::i()->select( 'conf_value', 'core_sys_conf_settings', array( array( 'conf_key=?', 'furl_configuration' ) ) )->first();

			if( $config )
			{
				foreach( json_decode( $config, TRUE ) as $k => $v )
				{
					if ( mb_substr( $k, 0, 3 ) !== 'key' and \is_numeric( $k ) )
					{
						$json[ 'key' . $k ] = $v;
					}
					else
					{
						$json[ $k ] = $v;
					}
				}
				
				\IPS\Settings::i()->changeValues( array( 'furl_configuration' => json_encode( $json ) ) );
			}
		}
		catch( \UnderflowException $e ) { }
		
		return TRUE;
	}
}