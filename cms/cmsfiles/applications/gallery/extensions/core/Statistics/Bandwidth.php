<?php
/**
 * @brief		Statistics Chart Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @subpackage	Gallery
 * @since		26 Jan 2023
 */

namespace IPS\gallery\extensions\core\Statistics;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Statistics Chart Extension
 */
class _Bandwidth extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'gallery_stats_bandwidth';
	
	/**
	 * Render Chart
	 *
	 * @param	\IPS\Http\Url	$url	URL the chart is being shown on.
	 * @return \IPS\Helpers\Chart
	 */
	public function getChart( \IPS\Http\Url $url ): \IPS\Helpers\Chart
	{
		$chart = new \IPS\Helpers\Chart\Database( \IPS\Http\Url::internal( "app=gallery&module=stats&controller=bandwidth" ), 'gallery_bandwidth', 'bdate', '', array( 
			'backgroundColor' 	=> '#ffffff',
			'colors'			=> array( '#10967e' ),
			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
			'lineWidth'			=> 1,
			'areaOpacity'		=> 0.4,
			'chartArea'			=> array( 'left' => 120, 'width' => '75%' ),
			'vAxis' 			=> array( 
				'title' => \IPS\Member::loggedIn()->language()->addToStack( 'filesize_raw_k' ) 
			)
		), 'AreaChart', 'daily', array( 'start' => 0, 'end' => 0 ), array( 'member_id', 'image_id', 'bdate', 'bsize' ) );
		$chart->setExtension( $this );
		
		$chart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('bandwidth'), 'number', 'ROUND((SUM(bsize)/1024),2)', FALSE );
		
		$chart->tableParsers = array(
			'member_id'	=> function( $val )
			{
				$member = \IPS\Member::load( $val );

				if( $member->member_id )
				{
					$url = \IPS\Http\Url::internal( "app=gallery&module=stats&controller=member&do=images&id={$member->member_id}" );
					return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $url, FALSE, $member->name );
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack('deleted_member');
				}
			},
			'image_id'	=> function( $val )
			{
				try
				{
					$image = \IPS\gallery\Image::load( $val );
					return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $image->url(), TRUE, $image->caption );
				}
				catch ( \OutOfRangeException $e )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('deleted_image');
				}
			},
			'bdate'	=> function( $val )
			{
				return (string) \IPS\DateTime::ts( $val );
			},
			'bsize'	=> function( $val )
			{
				return \IPS\Output\Plugin\Filesize::humanReadableFilesize( $val );
			}
		);
		
		$chart->availableTypes = array( 'AreaChart', 'ColumnChart', 'BarChart' );
		
		return $chart;
	}
}