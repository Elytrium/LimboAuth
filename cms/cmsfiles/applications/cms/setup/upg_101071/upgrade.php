<?php
/**
 * @brief		4.1.17 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		26 Oct 2016
 */

namespace IPS\cms\setup\upg_101071;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.17 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix any future dates that are set incorrectly
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$queries = array();

		foreach( \IPS\cms\Databases::databases() as $database )
		{
			if ( \IPS\Db::i()->checkForTable( 'cms_custom_database_' . $database->id ) )
			{
				$queries[]	= array(
					'table'	=> 'cms_custom_database_' . $database->id,
					'query'	=> "UPDATE " . \IPS\Db::i()->prefix . "cms_custom_database_" . $database->id . " SET record_last_comment=record_publish_date WHERE record_last_comment > UNIX_TIMESTAMP() AND record_publish_date < UNIX_TIMESTAMP()"
				);
	
				$queries[]	= array(
					'table'	=> 'cms_custom_database_' . $database->id,
					'query'	=> "UPDATE " . \IPS\Db::i()->prefix . "cms_custom_database_" . $database->id . " SET record_last_review=record_publish_date WHERE record_last_review > UNIX_TIMESTAMP() AND record_publish_date < UNIX_TIMESTAMP()"
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
		return "Resetting last comment/review times in custom Pages databases";
	}

	/**
	 * Removing widgets from uninstalled applications - Uninstalled applications may have left behind entries in this table.
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$applications = array_keys( \IPS\Application::applications() );

		foreach( \IPS\Db::i()->select( '*', 'cms_page_widget_areas' ) as $area )
		{
			$update = FALSE;
			$widgets = json_decode( $area['area_widgets'], TRUE );
			foreach ( $widgets as $widgetKey => $widget )
			{
				if( isset( $widget['app'] ) AND !\in_array( $widget['app'], $applications ) )
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
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Removing widgets from uninstalled applications";
	}
}