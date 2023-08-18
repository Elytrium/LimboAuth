<?php
/**
 * @brief		4.1.18 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		01 Dec 2016
 */

namespace IPS\core\setup\upg_101079;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.18 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Convert group promotion types. Previous versions used a bitwise column but as of this version 3 types are used
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach ( \IPS\Member\Group::groups() as $group )
		{
			$group->g_promotion_type = $group->g_bitoptions['gbw_promote_unit_type'];
			$group->save();
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
		return "Converting group promotion types";
	}

	
	/**
	 * Update Settings 
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		if ( \IPS\Settings::i()->prune_log_system > 30 )
		{
			\IPS\Settings::i()->changeValues( array( 'prune_log_system' => 30 ) );
		}
		
		\IPS\Db::i()->insert( 'core_sys_conf_settings', array( 
				'conf_key' => 'diagnostics_reporting',
				'conf_value' => \intval( $_SESSION['upgrade_options']['core']['101079']['diagnostics_reporting'] ),
				'conf_default' => 1,
				'conf_app' => 'core'
			) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Updating settings";
	}
	
	/**
	 * Initiate rebuild tasks for reputation
	 *
	 * @return boolean
	 */
	public function finish()
	{
		$classes = array();
		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', FALSE ) as $object )
		{
			$classes = array_merge( $object->classes, $classes );
		}
		
		foreach( $classes as $item )
		{
			try
			{
				$commentClass = NULL;
				$reviewClass  = NULL;
				
				if ( isset( $item::$commentClass ) )
				{
					$commentClass = $item::$commentClass;
				}
				
				if ( isset( $item::$reviewClass ) )
				{
					$reviewClass = $item::$reviewClass;
				}
				
				if ( \IPS\IPS::classUsesTrait( $item, 'IPS\Content\Reactable' ) )
				{
					\IPS\Task::queue( 'core', 'RebuildReputationIndex', array( 'class' => $item ), 3, array( 'class' ) );
				}
				
				if ( $commentClass and \IPS\IPS::classUsesTrait( $commentClass, 'IPS\Content\Reactable' ) )
				{
					\IPS\Task::queue( 'core', 'RebuildReputationIndex', array( 'class' => $commentClass ), 3, array( 'class' ) );
				}
				
				if ( $reviewClass and \IPS\IPS::classUsesTrait( $reviewClass, 'IPS\Content\Reactable' ) )
				{
					\IPS\Task::queue( 'core', 'RebuildReputationIndex', array( 'class' => $reviewClass ), 3, array( 'class' ) );
				}
			}
			catch( \Exception $e ) { }
		}
		
		/* Rebuild search index */
		\IPS\Content\Search\Index::i()->rebuild();
		
		return TRUE;
	}
}