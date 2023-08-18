<?php
/**
 * @brief		Chart Helper
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Jul 2013
 */

namespace IPS\Helpers;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Chart Helper
 *
 * @code
	$chart = new \IPS\Helpers\Chart;
	
	$chart->addHeader( "Year", 'string' );
	$chart->addHeader( "Sales", 'number' );
	$chart->addHeader( "Expenses", 'number' );
			
	$chart->addRow( array( '2004', 1000, 400 ) );
	$chart->addRow( array( '2005', 1170, 460 ) );
	$chart->addRow( array( '2006', 660, 1120 ) );
	$chart->addRow( array( '2007', 1030, 540 ) );
	
	\IPS\Output::i()->output = $chart->render( 'PieChart', array(
		'title'	=> "Cash Flow",
		'is3D'	=> TRUE
	) );
 * @endcode
 */
class _Chart
{
	/**
	 * @brief	Headers
	 * @see		addHeader();
	 */
	public $headers = array();
	
	/**
	 * @brief	Rows
	 * @see		addHeader();
	 */
	public $rows = array();
	
	/**
	 * @brief	Google Charts will assume numbers can be negative, which can produce graphs showing negative data points if no data is provided. The default baheviour is to set the minimum value to 0. Change this property if the chart should be able to show negative number values.
	 */
	public $numbersCanBeNegative = FALSE;

	/**
	 * Add Header
	 *
	 * @param	string	$label	Label
	 * @param	string	$type	Type of value
	 *	@li	string
	 *	@li	number
	 *	@li	boolean
	 *	@li	date
	 *	@li	datetime
	 *	@li	timeofday
	 * @return	void
	 */
	public function addHeader( $label, $type )
	{
		$this->headers[] = array( 'label' => $label, 'type' => $type );
	}
	
	/**
	 * Add Row
	 *
	 * @param	array	$values	Values, in the order that headers were added
	 * @return	void
	 * @throws	\LogicException
	 */
	public function addRow( $values )
	{
		if ( \count( $values ) !== \count( $this->headers ) )
		{
			throw new \LengthException('COLUMN_COUNT_MISMATCH');
		}
		
		$i = 0;
		$values = array_values( $values );
		foreach ( $this->headers as $data )
		{
			$value = \is_array( $values[ $i ] ) ? $values[ $i ]['value'] : $values[ $i ];
			
			switch ( $data['type'] )
			{
				case 'string':
					if ( !\is_string( $value ) )
					{
						throw new \InvalidArgumentException( "VALUE_{$i}_NOT_STRING" );
					}
					break;
				
				case 'number':
					if ( !\is_numeric( $value ) and !\is_null( $value ) )
					{
						throw new \InvalidArgumentException( "VALUE_{$i}_NOT_NUMBER" );
					}
					break;
					
				case 'bool':
					if ( !\is_bool( $value ) )
					{
						throw new \InvalidArgumentException( "VALUE_{$i}_NOT_BOOL" );
					}
					break;
					
				case 'date':
				case 'datetime':
				case 'timeofday':
					if ( !( $value instanceof \IPS\DateTime ) )
					{
						throw new \InvalidArgumentException( "VALUE_{$i}_NOT_DATETIME" );
					}
					
					if ( \is_array( $values[ $i ] ) )
					{
						$values[ $i ]['value'] = $value->rfc3339();
					}
					else
					{
						$values[ $i ] = $value->rfc3339();
					}
					break;
			}
			$i++;
		}
				
		$this->rows[] = $values;
	}
	
	/**
	 * Render
	 *
	 * @param	string	$type		Type
	 * @param	array	$options	Options
	 * @param	string	$format		Value for number formatter
	 * @see		<a href='https://google-developers.appspot.com/chart/interactive/docs/gallery'>Charts Gallery - Google Charts - Google Developers</a>
	 * @return	string
	 */
	public function render( $type, $options=array(), $format=NULL )
	{
		if ( !\IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->jsFiles[] = 'https://www.gstatic.com/charts/loader.js';
			\IPS\Output::i()->headJs .= "google.charts.load( '47', { 'packages':['corechart'] } );";
		}
		
		if ( !$this->numbersCanBeNegative and \in_array( $type, array( 'LineChart', 'ColumnChart' ) ) and !isset( $options['vAxis']['viewWindow']['min'] ) )
		{
			$options['vAxis']['viewWindow']['min'] = 0;
		}
				
		return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->chart( $this, $type, json_encode( $options ), $format );
	}
}