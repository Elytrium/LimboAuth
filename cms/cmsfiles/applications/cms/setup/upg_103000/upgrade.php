<?php
/**
 * @brief		4.3.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		06 Dec 2017
 */

namespace IPS\cms\setup\upg_103000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.3.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
    /**
	 * Fix any custom database tables that may be missing record_meta_data
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$queries = array();

		foreach( \IPS\cms\Databases::databases() as $database )
		{
			if ( \IPS\Db::i()->checkForTable( 'cms_custom_database_' . $database->id ) AND !\IPS\Db::i()->checkForColumn( 'cms_custom_database_' . $database->id, 'record_meta_data' ) )
			{
				$column = \IPS\Db::i()->compileColumnDefinition( array(
			 		'name'			=> 'record_meta_data',
			 		'type'			=> 'BIT',
			 		'length'		=> 1,
			 		'allow_null'	=> FALSE,
			 		'default'		=> 'b\'0\'',
			 	) );
				$queries[]	= array(
					'table'	=> 'cms_custom_database_' . $database->id,
					'query'	=> "ALTER TABLE `" . \IPS\Db::i()->prefix . "cms_custom_database_" . $database->id . "` ADD COLUMN " . $column
				);
			}
		}

		if( \count( $queries ) )
		{
			$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $queries );

			if ( \count( $toRun ) )
			{
				\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'cms', 'extra' => array( '_upgradeStep' => 2 ) ) );

				/* Queries to run manually */
				return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
			}
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
		return "Fixing custom database table definitions";
	}
	
	/**
	 * Step 1
	 * Index pages
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\Task::queue( 'core', 'RebuildSearchIndex', array( 'class' => 'IPS\cms\Pages\PageItem' ), 5 );
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step2CustomTitle()
	{
		return 'Adding pages to search index';
	}

	/**
	 * Step 3
	 * Remove announcement widgets
	 *
	 * @return bool
	 */
	public function step3()
	{
		foreach( \IPS\Db::i()->select( '*', 'cms_page_widget_areas' ) as $area )
		{
			$update = FALSE;
			$widgets = json_decode( $area['area_widgets'], TRUE );
			foreach ( $widgets as $widgetKey => $widget )
			{
				if( isset( $widget['app'] ) AND $widget['app'] == 'core' AND $widget['key'] == 'announcements' )
				{
					unset( $widgets[ $widgetKey ] );
					$update = TRUE;
				}
			}

			if( $update )
			{
				\IPS\Db::i()->update( 'cms_page_widget_areas', array( 'area_widgets' => json_encode( array_values( $widgets ) ) ), array( 'area_page_id=? AND area_area=?', $area['area_page_id'], $area['area_area'] ) );
			}
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step3CustomTitle()
	{
		return "Removing announcement widgets";
	}

	
	/**
	 * Finish - This is run after all apps have been upgraded
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 * @note	We opted not to let users run this immediately during the upgrade because of potential issues (it taking a long time and users stopping it or getting frustrated) but we can revisit later
	 */
	public function finish()
	{
		$ids = array();
		foreach ( \IPS\Db::i()->select( 'database_id', 'cms_databases' ) as $id )
		{
			$ids[] = $id;
			\IPS\Task::queue( 'core', 'RebuildItemCounts', array( 'class' => 'IPS\cms\Records' . $id, 'count' => 0 ), 4, array( 'class' ) );
		}
	}


}