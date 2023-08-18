<?php
/**
 * @brief		Statistics Chart Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @subpackage	Commerce
 * @since		26 Jan 2023
 */

namespace IPS\nexus\extensions\core\Statistics;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Statistics Chart Extension
 */
class _Sales extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'nexus_reports_purchases';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		$chart = new \IPS\Helpers\Chart\Database(
			$url,
			'nexus_purchases',
			'ps_start',
			'',
			array(
				'backgroundColor' 	=> '#ffffff',
				'colors'			=> array( '#10967e', '#ea7963', '#de6470', '#6b9dde', '#b09be4', '#eec766', '#9fc973', '#e291bf', '#55c1a6', '#5fb9da' ),
				'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
				'lineWidth'			=> 1,
				'areaOpacity'		=> 0.4
			)
		);
		$chart->setExtension( $this );
		$chart->where[] = array( 'ps_app=? AND ps_type=?', 'nexus', 'package' );
		$chart->groupBy = 'ps_item_id';
		$chart->tableInclude	= array( 'ps_id', 'ps_member', 'ps_name', 'ps_start', 'ps_expire' );
		$chart->tableParsers	= array( 
			'ps_member' => function( $val ) {
				return \IPS\Theme::i()->getTemplate('global', 'nexus')->userLink( \IPS\Member::load( $val ) );
			},
			'ps_start'	=> function( $val ) {
				return \IPS\DateTime::ts( $val );
			},
			'ps_expire'	=> function( $val ) {
				return $val ? \IPS\DateTime::ts( $val ) : '';
			}
		);
		
		$packages = array();
		foreach ( \IPS\Db::i()->select( 'p_id', 'nexus_packages' ) as $packageId )
		{
			$packages[ $packageId ] = \IPS\Member::loggedIn()->language()->get( 'nexus_package_' . $packageId );
		}
		
		asort( $packages );
		foreach ( $packages as $id => $name )
		{
			$chart->addSeries( $name, 'number', 'COUNT(*)', TRUE, $id );
		}
		
		return $chart;
	}
}