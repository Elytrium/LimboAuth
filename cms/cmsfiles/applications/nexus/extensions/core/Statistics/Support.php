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
class _Support extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'nexus_support_volume';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		$chart	= new \IPS\Helpers\Chart\Database( $url, 'nexus_support_requests', 'r_started', '',
			array(
				'vAxis'		=> array( 'title' => \IPS\Member::loggedIn()->language()->addToStack('support_requests_created') ),
				'backgroundColor' 	=> '#ffffff',
				'colors'			=> array( '#10967e', '#ea7963', '#de6470', '#6b9dde', '#b09be4', '#eec766', '#9fc973', '#e291bf', '#55c1a6', '#5fb9da' ),
				'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
				'lineWidth'			=> 1,
				'areaOpacity'		=> 0.4
			),
			'AreaChart'
		);
		$chart->setExtension( $this );
		$chart->groupBy	= 'r_department';
		foreach( \IPS\nexus\Support\Department::roots() as $department )
		{
			$chart->addSeries( $department->_title, 'number', 'COUNT(*)', TRUE, $department->id );
		}

		$chart->tableInclude = array( 'r_id', 'r_title', 'r_member', 'r_department', 'r_status', 'r_started', 'r_last_reply', 'r_replies' );
		$chart->tableParsers = array(
			'r_member'	=> function( $val ) {
				return \IPS\Theme::i()->getTemplate('global', 'nexus')->userLink( \IPS\Member::load( $val ) );
			},
			'r_department'	=> function( $val ) {
				return \IPS\Member::loggedIn()->language()->addToStack( 'nexus_department_' . $val );
			},
			'r_status'	=> function( $val, $row )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'nexus_status_' . $val . '_admin' );
			},
			'r_started'	=> function( $val ) {
				return \IPS\DateTime::ts( $val );
			},
			'r_last_reply'	=> function( $val ) {
				return \IPS\DateTime::ts( $val );
			}
		);
		
		return $chart;
	}
}