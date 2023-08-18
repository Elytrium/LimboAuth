<?php
/**
 * @brief		4.0.0 Beta 6 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		06 Jan 2015
 */

namespace IPS\cms\setup\upg_100018;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 RC5 Upgrade Code
 *
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Convert menu titles to translatable lang strings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach( \IPS\Db::i()->select( '*', 'cms_databases' ) as $db )
		{
			$save = array();
			foreach( \IPS\Lang::languages() as $id => $lang )
			{
				try
				{
					$base = $lang->get('__indefart_content_db_lang_sl');
				}
				catch ( \UnderflowException $e )
				{
					$base = "a %s"; // If upgrading from 3.x it won't exist yet
				}
				
				try
				{
					$string = $lang->get("content_db_lang_sl_" . $db['database_id']);
				}
				catch ( \UnderflowException $e )
				{
					$string = "record"; // If upgrading from 3.x it won't exist yet
				}

				$save[ $lang->_id ] = \sprintf( $base, $string );
			}
			
			\IPS\Lang::saveCustom( 'cms', "content_db_lang_ia_" . $db['database_id'], $save );
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
		return "Storing indefinite article translations";
	}

	/**
	 * Step 2
	 * Move any orphaned blocks to a new category
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		if( !\IPS\Db::i()->select( 'COUNT(*)', 'cms_blocks', array( 'block_category=?', 0 ) ) )
		{
			/* If there aren't any, don't create a new block category */
			return TRUE;
		}

		$container = new \IPS\cms\Blocks\Container;
		$container->name = 'Uncategorized Blocks';
		$container->type = 'block';
		$container->order = 0;
		$container->parent_id = 0;
		$container->key = md5( mt_rand() );
		$container->save();

		\IPS\Db::i()->update( 'cms_blocks', array( 'block_category' => $container->_id ), array( 'block_category=?', 0 ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Restoring orphaned Pages blocks";
	}
	
	/**
	 * Make sure all theme settings are applied to every theme.
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
    {
	    foreach ( \IPS\Db::i()->select( 'database_id', 'cms_databases', 'database_page_id>0' ) as $id )
	    {
	    	\IPS\Task::queue( 'core', 'RebuildReputationIndex', array( 'class' => 'IPS\cms\Records' . $id ), 4 );
	    	\IPS\Task::queue( 'core', 'RebuildReputationIndex', array( 'class' => 'IPS\cms\Records\Comment' . $id ), 4 );
	    	\IPS\Task::queue( 'core', 'RebuildReputationIndex', array( 'class' => 'IPS\cms\Records\Review' . $id ), 4 );
			\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\cms\Categories' . $id, 'count' => 0 ), 5, array( 'class' ) );
		}

        return TRUE;
    }
}