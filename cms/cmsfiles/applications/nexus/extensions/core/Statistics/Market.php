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
class _Market extends \IPS\core\Statistics\Chart
{
	/**
	 * @brief	Controller
	 */
	public $controller = 'nexus_reports_markets_count';
	
	/**
	 * @brief	Identifier
	 */
	protected $_identifier = 'count';
	
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
			'nexus_invoices',
			'i_paid',
			'',
			array(
				'backgroundColor' 	=> '#ffffff',
				'colors'			=> array( '#c9e2de', '#10967e' ),
				'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
				'lineWidth'			=> 1,
				'areaOpacity'		=> 0.4
			),
			'GeoChart',
			'monthly',
			array( 'start' => 0, 'end' => 0 ),
			array(),
			$this->_identifier
		);
		$chart->setExtension( $this );
		$chart->availableTypes[] = 'GeoChart';
		$chart->where[] = array( 'i_status=? AND i_billcountry IS NOT NULL', \IPS\nexus\Invoice::STATUS_PAID );
		$chart->groupBy = 'i_billcountry';
				
		foreach ( \IPS\GeoLocation::$countries as $countryCode )
		{
			$chart->addSeries( [ 'value' => \IPS\Member::loggedIn()->language()->get( 'country-' . $countryCode ), 'key' => $countryCode ], 'number', $this->_identifier === 'count' ? 'COUNT(*)' : 'SUM(i_total)', TRUE, $countryCode );
		}
		
		if ( $chart->type === 'GeoChart' )
		{
			$chart->options['height'] = 750;
			$chart->options['keepAspectRatio'] = true;
		}
		
		return $chart;
	}
	
	/**
	 * Set Currency
	 *
	 * @param	\IPS\Helpers\Chart 	$chart		Chart
	 * @param	string				$currency	Currency
	 */
	public function setCurrency( \IPS\Helpers\Chart &$chart, string $currency )
	{
		if ( !\in_array( $currency, \IPS\nexus\Money::currencies() ) )
		{
			throw new \InvalidArgumentException;
		}
		
		$this->_identifier = $currency;
		$chart->where[] = array( 'i_currency=?', $currency );
		$chart->format = $currency;
	}
}