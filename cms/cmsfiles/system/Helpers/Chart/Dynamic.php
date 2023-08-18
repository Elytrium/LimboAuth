<?php
/**
 * @brief		Dynamic Chart Builder Helper
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		29 Mar 2017
 */

namespace IPS\Helpers\Chart;

use IPS\Member;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Dynamic Chart Helper
 */
abstract class _Dynamic extends \IPS\Helpers\Chart
{
	/**
	 * @brief	URL
	 */
	public $url;

	/**
	 * @brief	$timescale (daily, weekly, monthly)
	 */
	public $timescale = 'monthly';

	/**
	 * @brief	Unique identifier for URLs
	 */
	public $identifier	= '';
	
	/**
	 * @brief	Start Date
	 */
	public $start;
	
	/**
	 * @brief	End Date
	 */
	public $end;
	
	/**
	 * @brief	Series
	 */
	protected $series = array();
	
	/**
	 * @brief	Title
	 */
	public $title;

	/**
	 * @brief	Description
	 */
	public $description;

	/**
	 * @brief	Google Chart Options
	 */
	public $options = array();
	
	/**
	 * @brief	Type
	 */
	public $type;

	/**
	 * @brief	Search term
	 */
	public $searchTerm = null;
	
	/**
	 * @brief	Available Types
	 */
	public $availableTypes = array( 'AreaChart', 'LineChart', 'ColumnChart', 'BarChart', 'PieChart', 'Table' );
	
	/**
	 * @brief	Available Filters
	 */
	public $availableFilters = array();
	
	/**
	 * @brief	Current Filters
	 */
	public $currentFilters = array();

	/**
	 * @brief	Plot zeros
	 */
	public $plotZeros = TRUE;
	
	/**
	 * @brief	Value for number formatter
	 */
	public $format = NULL;

	/**
	 * @brief	Allow user to adjust interval (group by daily, monthly, etc.)
	 */
	public $showIntervals = TRUE;
	
	/**
	 * @brief	Allow user to adjust date range
	 */
	public $showDateRange = TRUE;
	
	/**
	 * @brief	Show save button
	 */
	public $showSave = TRUE;
	
	/**
	 * @brief	If a warning about timezones needs to be shown
	 */
	public $timezoneError = FALSE;

	/**
	 * @brief	If we need to show a timezone warning, we usually include a link to learn more - set this to TRUE to hide the link
	 */
	public $hideTimezoneLink = FALSE;

	/**
	 * @brief	If set to an \IPS\DateTime instance, minimum time will be checked against this value
	 */
	public $minimumDate = NULL;

	/**
	 * @brief	Enable hourly filtering. USE WITH CAUTION.
	 */
	public $enableHourly	= FALSE;

	/**
	 * @brief	Error(s) to show on chart UI
	 */
	public $errors = array();
	
	/**
	 * @brief	Saved custom filters
	 */
	public $savedCustomFilters = array();
		
	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url	$url			The URL the chart will be displayed on
	 * @param	string			$title			Title
	 * @param	array			$options		Options
	 * @param	string			$defaultType	The default chart type
	 * @param	string			$defaultTimescale	The default timescale to use
	 * @param	array			$defaultTimes	The default start/end times to use
	 * @param	string			$identifier		If there will be more than one chart per page, provide a unique identifier
	 * @param	\IPS\DateTime|NULL	$minimumDate	The earliest available date for this chart
	 * @see		<a href='https://google-developers.appspot.com/chart/interactive/docs/gallery'>Charts Gallery - Google Charts - Google Developers</a>
	 * @return	void
	 */
	public function __construct( \IPS\Http\Url $url, $title='', $options=array(), $defaultType='AreaChart', $defaultTimescale='monthly', $defaultTimes=array( 'start' => 0, 'end' => 0 ), $identifier='', $minimumDate=NULL )
	{
		/* If we are deleting a chart, just do that now and redirect */
		if( isset( \IPS\Request::i()->deleteChart ) )
		{
			\IPS\Session::i()->csrfCheck();
			\IPS\Db::i()->delete( 'core_saved_charts', array( 'chart_id=? and chart_member=?', \IPS\Request::i()->deleteChart, \IPS\Member::loggedIn()->member_id ) );
			\IPS\Output::i()->redirect( \IPS\Request::i()->url()->stripQueryString( array( 'deleteChart', 'chartId', 'csrfKey' ) ), 'chart_deleted' );
		}

		if ( !isset( $options['chartArea'] ) )
		{
			$options['chartArea'] = array(
				'left'	=> '50',
				'width'	=> '75%'
			);
		}

		if( isset( \IPS\Request::i()->chartId ) AND \IPS\Request::i()->chartId != '_default' )
		{
			$url = $url->setQueryString( 'chartId', \IPS\Request::i()->chartId );
		}
		
		$this->baseURL		= $url;
		$this->title		= $title;
		$this->options		= $options;
		$this->timescale	= $defaultTimescale;
		$this->start		= $defaultTimes['start'] ?: \IPS\DateTime::create()->sub( new \DateInterval('P6M') );
		$this->end			= $defaultTimes['end'] ?: \IPS\DateTime::create();
		$this->minimumDate	= $minimumDate;

		if ( isset( \IPS\Request::i()->type[ $this->identifier ] ) and \in_array( \IPS\Request::i()->type[ $this->identifier ], $this->availableTypes ) )
		{
			$this->type = \IPS\Request::i()->type[ $this->identifier ];
			$url = $url->setQueryString( 'type', array( $this->identifier => $this->type ) );
		}
		else
		{
			$this->type = $defaultType;
		}

		/* Are we searching? The chart controller should inspect this property if it supports searching to limit the series it adds. */
		if( isset( $this->options['limitSearch'] ) AND isset( \IPS\Request::i()->search[ $this->identifier ] ) )
		{
			$this->searchTerm = \IPS\Request::i()->search[ $this->identifier ];
		}

		/* Change timescale */
		if ( isset( \IPS\Request::i()->timescale[ $this->identifier ] ) and \in_array( \IPS\Request::i()->timescale[ $this->identifier ], array( 'hourly', 'daily', 'weekly', 'monthly' ) ) )
		{
			if( \IPS\Request::i()->timescale[ $this->identifier ] != 'hourly' OR ( \IPS\Request::i()->timescale[ $this->identifier ] == 'hourly' AND $this->enableHourly === TRUE ) )
			{
				$this->timescale = \IPS\Request::i()->timescale[ $this->identifier ];
				$url = $url->setQueryString( 'timescale', array( $this->identifier => \IPS\Request::i()->timescale[ $this->identifier ] ) );
			}
		}

		if ( $this->type === 'PieChart' or $this->type === 'GeoChart' )
		{
			$this->addHeader( 'key', 'string' );
			$this->addHeader( 'value', 'number' );
		}
		else
		{
			$this->addHeader( \IPS\Member::loggedIn()->language()->addToStack('date'), ( $this->timescale == 'none' OR $this->timescale == 'hourly' ) ? 'datetime' : 'date' );
		}

		if ( isset( \IPS\Request::i()->start[ $this->identifier ] ) )
		{
			try
			{
				$originalStart = $this->start;

				if( !\IPS\Request::i()->start[ $this->identifier ] )
				{
					$this->start = 0;
				}
				elseif ( \is_numeric( \IPS\Request::i()->start[ $this->identifier ] ) )
				{
					$this->start = \IPS\DateTime::ts( \IPS\Request::i()->start[ $this->identifier ] );
				}
				else
				{
					$this->start = new \IPS\DateTime( \IPS\Helpers\Form\Date::_convertDateFormat( \IPS\Request::i()->start[ $this->identifier ] ), new \DateTimeZone( \IPS\Member::loggedIn()->timezone ) );
				}

				if( $this->minimumDate > $this->start )
				{
					$this->errors[] = array( 'string' => 'minimum_chart_date', 'sprintf' => $this->minimumDate->localeDate() );
					$this->start = $originalStart;
				}
				else
				{
					unset( $originalStart );
				}

				if( $this->start )
				{
					$url = $url->setQueryString( 'start', array( $this->identifier => $this->start->getTimestamp() ) );
				}
			}
			catch ( \Exception $e ) {}
		}

		if ( isset( \IPS\Request::i()->end[ $this->identifier ] ) )
		{
			try
			{
				if( !\IPS\Request::i()->end[ $this->identifier ] )
				{
					$this->end = \IPS\DateTime::create();
				}
				elseif ( \is_numeric( \IPS\Request::i()->end[ $this->identifier ] ) )
				{
					$this->end = \IPS\DateTime::ts( \IPS\Request::i()->end[ $this->identifier ] );
				}
				else
				{
					$this->end = new \IPS\DateTime( \IPS\Helpers\Form\Date::_convertDateFormat( \IPS\Request::i()->end[ $this->identifier ] ), new \DateTimeZone( \IPS\Member::loggedIn()->timezone ) );
				}

				/* The end date should include items to the end of the day */
				$this->end->setTime( 23, 59, 59 );

				$url = $url->setQueryString( 'end', array( $this->identifier => $this->end->getTimestamp() ) );
			}
			catch ( \Exception $e ) {}
		}	
		
		if ( isset( \IPS\Request::i()->filters[ $this->identifier ] ) )
		{
			$url = $url->setQueryString( 'filters', '' );
		}
		
		$this->url = $url;
		
		if ( \IPS\Member::loggedIn()->timezone and \in_array( \IPS\Member::loggedIn()->timezone, \DateTimeZone::listIdentifiers() ) )
		{
			try
			{
				$r = \IPS\Db::i()->query( "SELECT TIMEDIFF( NOW(), CONVERT_TZ( NOW(), @@session.time_zone, '" . \IPS\Db::i()->escape_string( \IPS\Member::loggedIn()->timezone ) . "' ) );" )->fetch_row();
				if ( $r[0] === NULL )
				{
					$this->timezoneError = TRUE;
				}
			}
			catch ( \IPS\Db\Exception $e )
			{
				$this->timezoneError = TRUE;
			}
		}

		/* If we have requested a saved chart, load its filters */
		if( isset( \IPS\Request::i()->chartId ) AND \IPS\Request::i()->chartId != '_default' AND !isset( \IPS\Request::i()->filters ) )
		{
			foreach( $this->loadAvailableChartTabs() as $chart )
			{
				if( $chart['chart_id'] == \IPS\Request::i()->chartId )
				{
					$filters = array();
					foreach( json_decode( $chart['chart_configuration'], true ) as $key => $value )
					{
						if ( \mb_substr( $key, 0, 11 ) == 'customform_' )
						{
							$this->savedCustomFilters[ \mb_substr( $key, 11 ) ] = $value;
						}
						else
						{
							$filters[ $key ] = $value;
						}
					}
					\IPS\Request::i()->filters = array( $this->identifier => $filters );
				}
			}
		}
		
		foreach( \IPS\Request::i() as $key => $value )
		{
			if ( $value and \mb_substr( $key, 0, 11 ) === 'customform_' )
			{
				$name = \mb_substr( preg_replace( '#' . $this->identifier . '#', '', $key ), 11 );
				
				$this->savedCustomFilters[ $name ] = $value;
			}
		}
	}
	
	/**
	 * Compile Data for Output
	 *
	 * @param	array	$data	The data
	 * @return	void
	 */
	abstract public function compileForOutput();
	
	/**
	 * Get the chart output
	 *
	 * @return string
	 */
	abstract public function getOutput();

	/**
	 * @brief	Form to save filters
	 */
	public $form	= NULL;

	/**
	 * HTML
	 *
	 * @return	string
	 */
	public function __toString()
	{
		try
		{
			/* Generate a form so we can save our filters as a new saved chart */
			$this->form	= new \IPS\Helpers\Form;
			$this->form->class = 'ipsForm_vertical';

			if ( $this->customFiltersForm )
			{
				if ( $customValues = $this->getCustomFiltersForm()->values( TRUE ) )
				{
					foreach( $customValues as $key => $value )
					{
						$this->form->hiddenValues['customform_' . $key ] = $value;
					}
				}
			}

			/* If we have a sub selector from a node form, add on the identifier and pass off to the form to show the ajax */
			if ( \IPS\Request::i()->_nodeSelectName )
			{
				\IPS\Request::i()->_nodeSelectName = $this->identifier . \IPS\Request::i()->_nodeSelectName;
				return (string) $this->getCustomFiltersForm();
			}
			
			/* We have an existing chart ID */
			if( isset( \IPS\Request::i()->chartId ) AND \IPS\Request::i()->chartId != '_default' )
			{
				$title = '';

				foreach( $this->loadAvailableChartTabs() as $chart )
				{
					if( $chart['chart_id'] == \IPS\Request::i()->chartId )
					{
						$title = $chart['chart_title'];
						break;
					}
				}
				
				$custom = array();
				$chartFilters = ( isset( \IPS\Request::i()->chartFilters ) and \IPS\Request::i()->chartFilters ) ? \IPS\Request::i()->chartFilters : array();
				$this->timescale = $chart['chart_timescale'] ?? 'monthly';
				
				foreach( \IPS\Request::i() as $key => $value )
				{
					if ( \mb_substr( $key, 0, 11 ) === 'customform_' )
					{
						$custom[ preg_replace( '#' . $this->identifier . '#', '', $key ) ] = $value;
					}
				}
				
				$this->form->add( new \IPS\Helpers\Form\Text( 'custom_chart_title', $title, TRUE ) );

				if( $values = $this->form->values() )
				{
					\IPS\Db::i()->update( 'core_saved_charts', array( 'chart_title' => $values['custom_chart_title'] ), array( 'chart_id=? AND chart_member=?', \IPS\Request::i()->chartId, \IPS\Member::loggedIn()->member_id ) );

					/* And then return the output we need */
					\IPS\Output::i()->json( array(
						'title'		=> $values['custom_chart_title']
					)	);
				}

				/* And we want to save our filter updates */
				if( isset( \IPS\Request::i()->saveFilters ) )
				{
					\IPS\Db::i()->update( 'core_saved_charts', array( 'chart_configuration'	=> json_encode( array_merge( $chartFilters, $custom ) ), 'chart_timescale' => \IPS\Request::i()->timescale ?? $this->timescale ?? 'monthly' ), array( 'chart_id=? AND chart_member=?', \IPS\Request::i()->chartId, \IPS\Member::loggedIn()->member_id ) );
				}
			}
			/* We are not viewing a saved chart */
			else
			{
				$this->form->add( new \IPS\Helpers\Form\Text( 'custom_chart_title', NULL, TRUE ) );
				
				if( $values = $this->form->values() )
				{
					$custom = array();
					foreach( \IPS\Request::i() as $key => $value )
					{
						if ( \mb_substr( $key, 0, 11 ) === 'customform_' )
						{
							$custom[ preg_replace( '#' . $this->identifier . '#', '', $key ) ] = $value;
						}
					}
					
					$chartFilters = ( isset( \IPS\Request::i()->chartFilters ) and \IPS\Request::i()->chartFilters ) ? \IPS\Request::i()->chartFilters : array();
											
					/* Store the new chart */
					$id = \IPS\Db::i()->insert( 'core_saved_charts', array(
						'chart_member'			=> \IPS\Member::loggedIn()->member_id,
						'chart_controller'		=> \IPS\Request::i()->app . '_' . \IPS\Request::i()->module . '_' . \IPS\Request::i()->controller . ( \IPS\Request::i()->tab ? '_' . \IPS\Request::i()->tab : '' ),
						'chart_configuration'	=> json_encode( array_merge( $chartFilters, $custom ) ),
						'chart_timescale'		=> \IPS\Request::i()->timescale ?? $this->timescale ?? 'monthly',
						'chart_title'			=> $values['custom_chart_title'],
					) );

					/* Set some input parameters */
					$this->url					= $this->url->setQueryString( 'chartId', $id );
					\IPS\Request::i()->chartId	= $id;
					\IPS\Request::i()->filters	= array( $this->identifier => \IPS\Request::i()->chartFilters );

					$this->currentFilters		= \IPS\Request::i()->filters;
					
					if ( isset( \IPS\Request::i()->filters[ $this->identifier ] ) )
					{
						$this->url = $this->url->setQueryString( 'filters', array( $this->identifier => $this->currentFilters ) );
					}

					/* Reset form, since template looks for it and it should not be set for a saved chart */
					$this->form	= new \IPS\Helpers\Form;
					$this->form->class = 'ipsForm_vertical';
					$this->form->add( new \IPS\Helpers\Form\Text( 'custom_chart_title', $values['custom_chart_title'], TRUE ) );

					/* And then return the output we need */
					\IPS\Output::i()->json( array(
						'tabHref'	=> $this->url->stripQueryString( 'filters' ),
						'chartId'	=> $id,
						'tabId'		=> md5( $this->url->acpQueryString() ),
					)	);
				}
			}

			/* Get data */
			$output = '';
			if ( !empty( $this->series ) or $this->customFiltersForm )
			{
				$output = $this->getOutput();
			}
			else
			{
				$output = \IPS\Member::loggedIn()->language()->addToStack('chart_no_results');
			}

			/* Display */
			if ( \IPS\Request::i()->noheader )
			{
				return $output;
			}
			else
			{
				$chartOutput = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->dynamicChart( $this, $output );
				
				/* If we're not showing filter tabs, just return here. */
				if ( !$this->showFilterTabs )
				{
					return $chartOutput;
				}

				if( !\IPS\Request::i()->isAjax() OR ( \IPS\Request::i()->tab AND !\IPS\Request::i()->chartId ) )
				{
					return \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $this->getChartTabs(), ( isset( \IPS\Request::i()->chartId ) ) ? \IPS\Request::i()->chartId : NULL, $chartOutput, $this->url, 'chartId', 'ipsTabs_small ipsTabs_contained' );
				}
				else
				{
					return $chartOutput;
				}
			}
		}
		catch ( \Exception $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
		catch ( \Throwable $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
	}
	
	/**
	 * @brief	Show filter tabs
	 */
	public $showFilterTabs = TRUE;

	/**
	 * @brief	Cached tab data
	 */
	protected $availableChartTabs	= NULL;
	
	/**
	 * @Brief	Extension
	 */
	public $extension = NULL;

	/**
	 * Retrieve tabs based on saved charts
	 *
	 * @return array
	 */
	protected function getChartTabs()
	{
		$tabs	= array( '_default' => 'dynamic_chart_overview' );

		foreach( $this->loadAvailableChartTabs() as $chart )
		{
			$tabs[ $chart['chart_id'] ] = $chart['chart_title'];
		}

		return $tabs;
	}

	/**
	 * Load and return available chart tabs
	 *
	 * @return array
	 */
	protected function loadAvailableChartTabs()
	{
		if( $this->availableChartTabs === NULL )
		{
			if ( $this->extension !== NULL )
			{
				$this->availableChartTabs = iterator_to_array( \IPS\Db::i()->select( '*', 'core_saved_charts', array( 'chart_member=? AND chart_controller=?', \IPS\Member::loggedIn()->member_id, $this->extension->controller ) ) );
			}
			else
			{
				$this->availableChartTabs = iterator_to_array( \IPS\Db::i()->select( '*', 'core_saved_charts', array( 'chart_member=? AND chart_controller=?', \IPS\Member::loggedIn()->member_id, \IPS\Request::i()->app . '_' . \IPS\Request::i()->module . '_' . \IPS\Request::i()->controller . ( \IPS\Request::i()->tab ? '_' . \IPS\Request::i()->tab : '' ) ) ) );
			}
		}

		return $this->availableChartTabs;
	}
	
	/**
	 * Set extension
	 *
	 * @param	\IPS\core\Statistics\Chart	$ext	Extension
	 * @return	void
	 */
	public function setExtension( \IPS\core\Statistics\Chart $ext )
	{
		$this->extension = $ext;
		
		$this->availableChartTabs = NULL;
	}
		
	/**
	 * Flip URL Filter
	 *
	 * @param	string	$filter	The Filter
	 * @return	\IPS\Http\Url
	 */
	public function flipUrlFilter( $filter )
	{
		$filters = $this->currentFilters;
		
		if ( \in_array( $filter, $filters ) )
		{
			unset( $filters[ array_search( $filter, $filters ) ] );
		}
		else
		{
			$filters[] = $filter;
		}
		
		return $this->url->setQueryString( 'filters', array( $this->identifier => $filters ) );
	}

	/**
	 * Init the data array
	 *
	 * @return array
	 */
	protected function initData()
	{
		/* Init data */
		$data = array();
		if ( $this->start AND $this->timescale !== 'none' )
		{
			$date = clone $this->start;
			while ( $date->getTimestamp() < ( $this->end ? $this->end->getTimestamp() : time() ) )
			{
				switch ( $this->timescale )
				{
					case 'hourly':
						$data[ $date->format( 'Y-n-j-h-i-s' ) ] = array();

						$date->add( new \DateInterval( 'PT1H' ) );
						break;

					case 'daily':
						$data[ $date->format( 'Y-n-j' ) ] = array();

						$date->add( new \DateInterval( 'P1D' ) );
						break;
						
					case 'weekly':
						/* o is the ISO year number, which we need when years roll over.
							@see http://php.net/manual/en/function.date.php#106974 */
						$data[ $date->format( 'o-W' ) ] = array();

						$date->add( new \DateInterval( 'P7D' ) );
						break;
						
					case 'monthly':
						$data[ $date->format( 'Y-n' ) ] = array();

						$date->add( new \DateInterval( 'P1M' ) );
						break;
				}
			}
		}

		return $data;
	}
	
	/**
	 * Custom filter form
	 *
	 * @return \IPS\Helpers\Form
	 */
	public function getCustomFiltersForm()
	{
		$customForm = new \IPS\Helpers\Form('filter_form', 'chart_customfilters_save');
		$customForm->class = 'ipsForm_vertical';
		
		if ( isset( \IPS\Request::i()->chartId ) and \IPS\Request::i()->chartId != '_default' )
		{
			$customForm->hiddenValues['chartId'] = \IPS\Request::i()->chartId;
		}

		foreach( $this->customFiltersForm['form'] as $field )
		{
			if ( ! preg_match( '#^' . $this->identifier . '#', $field->name ) )
			{
				$langKey = $field->name;
				$field->name = $this->identifier . $field->name;
				\IPS\Member::loggedIn()->language()->words[ $field->name ] = \IPS\Member::loggedIn()->language()->get( $langKey );
			}
			$customForm->add( $field );
		}
		
		return $customForm;
	}
	
	/**
	 * Download as CSV
	 *
	 * @param	array|NULL	$headers		Headers, or NULL to use headers defined by this object.
	 * @param	array|NULL	$rows		Rows, or NULL to use rows defined by this object.
	 * @param	string|NULL	$fileName	File Name, or NULL to use the title defined by this object.
	 * @return	void
	 */
	public function download( ?array $rawHeaders = NULL, ?array $rawRows = NULL, ?string $fileName = NULL )
	{
		/* Compile the data */
		$file = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
		$fh = fopen( $file, 'w' );
		
		/* Set headers */
		$headers = array();
		
		if ( $rawHeaders === NULL )
		{
			$rawHeaders = $this->headers;
		}

		/* Ok this is a very horrible hack to get the language hashes converted into the real names and in a format
		   that we can then reparse later. */
		$uglyHack = '';
		foreach( $rawHeaders AS $data )
		{
			$uglyHack .= str_replace( "\n", " ", $data['label'] ) . "\n";
		}

		/* This really is awful */
		Member::loggedIn()->language()->parseOutputForDisplay( $uglyHack );

		foreach( explode( "\n", trim( $uglyHack ) ) as $label )
		{
			/* Now we have the true string, eg: News, Now! instead of a language hash, eg: 77e90423a7642378a1590420cd66a465 so fputcsv can escape it correctly */
			$headers[] = $label;
		}
		fputcsv( $fh, $headers );
		
		/* Set Rows */
		
		if ( $rawRows === NULL )
		{
			$rawRows = $this->rows;
		}
		
		foreach( $rawRows AS $row )
		{
			$i = 0;
			$save = array();
			foreach( $rawHeaders AS $data )
			{
				switch( $data['type'] )
				{
					/* Booleans convert to string 'true' or 'false' */
					case 'bool':
						$save[] = ( $row[$i] ) ? 'true' : 'false';
						break;
					
					/* Make dates human readable */
					case 'date':
					case 'datetime':
					case 'timeofday':
						$save[] = ( new \IPS\DateTime( $row[$i] ) )->fullYearLocaleDate();
						break;
					
					/* Anything else can just save as-is */
					default:
						$save[] = $row[$i];
						break;
				}
				$i++;
			}
			fputcsv( $fh, $save );
		}
		
		fclose( $fh );
		$csv = \file_get_contents( $file );
		if ( $fileName )
		{
			$name = $fileName;
		}
		else if ( $this->title )
		{
			$name = $this->title;
		}
		else
		{
			$name = \IPS\Output::i()->title;
		}
		\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $name );
		\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $csv );
		\IPS\Output::i()->sendOutput( $csv, 200, 'text/csv', array( 'Content-Disposition' => \IPS\Output::getContentDisposition( 'attachment', "{$name}.csv" ) ), FALSE, FALSE, FALSE );
	}
}