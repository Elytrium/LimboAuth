<?php
/**
 * @brief		Statistics Chart Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @subpackage	Downloads
 * @since		26 Jan 2023
 */

namespace IPS\downloads\extensions\core\Statistics;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Statistics Chart Extension
 */
class _Downloads extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'downloads_stats_downloads';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		$chart = new \IPS\Helpers\Chart\Database( $url, 'downloads_downloads', 'dtime', '', array(
			'backgroundColor' 	=> '#ffffff',
			'colors'			=> array( '#10967e', '#ea7963', '#de6470', '#6b9dde', '#b09be4', '#eec766', '#9fc973', '#e291bf', '#55c1a6', '#5fb9da' ),
			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
			'lineWidth'			=> 1,
			'areaOpacity'		=> 0.4
		), 'AreaChart', 'monthly', array( 'start' => 0, 'end' => 0 ), array( 'dmid', 'dfid', 'dtime', 'dsize', 'dua', 'dip' ) );
		$chart->setExtension( $this );
		
		$chart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('downloads'), 'number', 'COUNT(*)', FALSE );
		$chart->availableTypes = array( 'AreaChart', 'ColumnChart', 'BarChart' );
		
		$chart->tableParsers = array(
			'dmid'	=> function( $val )
			{
				$member = \IPS\Member::load( $val );

				if( $member->member_id )
				{
					return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $member->url(), TRUE, $member->name );
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack('deleted_member');
				}
			},
			'dfid'	=> function( $val )
			{
				try
				{
					$file = \IPS\downloads\File::load( $val );
					return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $file->url(), TRUE, $file->name );
				}
				catch ( \OutOfRangeException $e )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('deleted_file');
				}
			},
			'dtime'	=> function( $val )
			{
				return (string) \IPS\DateTime::ts( $val );
			},
			'dsize'	=> function( $val )
			{
				return \IPS\Output\Plugin\Filesize::humanReadableFilesize( $val );
			},
			'dua'	=> function( $val )
			{
				return (string) \IPS\Http\Useragent::parse( $val );
			},
			'dip'	=> function( $val )
			{
				return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( \IPS\Http\Url::internal( "app=core&module=members&controller=ip&ip={$val}&tab=downloads_DownloadLog" ), FALSE, $val );
			}
		);
		
		return $chart;
	}
}