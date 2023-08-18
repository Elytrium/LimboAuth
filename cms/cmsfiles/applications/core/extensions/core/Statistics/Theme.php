<?php
/**
 * @brief		Statistics Chart Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/

 * @since		26 Jan 2023
 */

namespace IPS\core\extensions\core\Statistics;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Statistics Chart Extension
 */
class _Theme extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'core_stats_preferences_theme';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		$chart	= new \IPS\Helpers\Chart;
		$counts = iterator_to_array( \IPS\Db::i()->select( 'skin, COUNT(member_id) as count', 'core_members', array( "skin > ?", 0), NULL, NULL, "skin" )->setKeyField( 'skin' ) );

		$chart->addHeader( "theme", "string" );
		$chart->addHeader( \IPS\Member::loggedIn()->language()->get('chart_members'), "number" );
		foreach( $counts as $id => $theme )
		{
			$chart->addRow( array( \IPS\Member::loggedIn()->language()->addToStack('core_theme_set_title_' . $id ), $theme['count'] ) );
		}
		$chart->title = \IPS\Member::loggedIn()->language()->addToStack('stats_theme_title');
				
		return $chart;
	}
}