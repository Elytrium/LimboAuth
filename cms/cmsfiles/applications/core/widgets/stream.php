<?php
/**
 * @brief		stream Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		26 Nov 2019
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * stream Widget
 */
class _stream extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'stream';
	
	/**
	 * @brief	App
	 */
	public $app = 'core';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';
	
	/**
	 * Initialise this widget
	 *
	 * @return void
	 */ 
	public function init()
	{
		parent::init(); //outputCss
		
		/* Necessary CSS/JS */
		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_streams.js', 'core' ) );
		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_statuses.js', 'core' ) );
		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/streams.css' ) );
		
		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/streams_responsive.css', 'core', 'front' ) );
		}
		
		$apps = array();
		if( isset( $this->configuration['content'] ) and \strstr( $this->configuration['content'], ',' ) )
		{
			foreach( explode( ',', $this->configuration['content'] ) as $content )
			{
				$class = explode( '\\', $content );
				$apps[] = $class[1];
			}
		}
		
		/* We will need specific CSS */
		foreach( \IPS\Application::enabledApplications() as $appDir => $app )
		{
			if ( isset( $this->configuration['content'] ) and $this->configuration['content'] === 0 or \in_array( $appDir, $apps ) )
			{
				if ( method_exists( $app, 'outputCss' ) )
				{
					$app::outputCss();
				}
			}
		}
	}
	
	/**
	 * Specify widget configuration
	 *
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	null|\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
	{
		$form = parent::configuration( $form );
		$options = array();
		foreach( \IPS\Content::routedClasses( FALSE, FALSE, TRUE ) AS $class )
		{
			if ( \in_array( 'IPS\Content\Searchable', \class_implements( $class ) ) )
			{
				$options[ $class ] = \IPS\Member::loggedIn()->language()->addToStack( $class::$title . '_pl' );
			}
		}
		$form->add( new \IPS\Helpers\Form\Text( 'title', $this->configuration['title'] ?? NULL, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Select( 'content', ( isset( $this->configuration['content'] ) AND $this->configuration['content'] !== 0 ) ? explode( ',', $this->configuration['content'] ) : 0, FALSE, array(
			'options'	=> $options,
			'multiple'	=> TRUE,
			'unlimited'	=> 0,
		) ) );

		$dateField = new \IPS\Helpers\Form\Select( 'date', ( isset( $this->configuration['date'] ) ) ? $this->configuration['date'] : 'year', FALSE, array(
			'options' => array(
				'today'		=> 'search_day',
				'week'		=> 'search_week',
				'month'		=> 'search_month',
				'year'		=> 'search_year'
			)
		) );
		$dateField->label = \IPS\Member::loggedIn()->language()->addToStack( 'stream_date_relative_days' );
		$form->add( $dateField );

		$form->add( new \IPS\Helpers\Form\Number( 'max_results', $this->configuration['max_results'] ?? 5, TRUE, array( 'max' => 20 ) ) );
		return $form;
	} 
	
	 /**
	 * Ran before saving widget configuration
	 *
	 * @param	array	$values	Values from form
	 * @return	array
	 */
	public function preConfig( $values )
	{
		if ( \is_array( $values['content'] ) )
		{
			$save = [];
			foreach( $values['content'] AS $class )
			{
				$save[] = $class;
			}
			$values['content'] = implode( ',', $save );
		}
		
		return $values;
	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{	
		if ( !isset( $this->configuration['content'] ) )
		{
			return '';
		}
		
		/* Set our content */
		if ( $this->configuration['content'] === 0 )
		{
			$stream = \IPS\core\Stream::allActivityStream();
		}
		else
		{
			$stream = new \IPS\core\Stream;
			$stream->classes = $this->configuration['content'];
		}
		
		/* Set our date range */
		$stream->date_type			= 'relative';
		$stream->date_relative_days = 365;
		if ( isset( $this->configuration['date'] ) )
		{
			switch( $this->configuration['date'] )
			{
				case 'today':
					$stream->date_relative_days = 1;
					break;
				
				case 'week':
					$stream->date_relative_days = 7;
					break;
				
				case 'month':
					$stream->date_relative_days = 30;
					break;
				
				case 'year':
				default:
					$stream->date_relative_days = 365;
					break;
			}
		}
		
		/* Set some defaults */
		$stream->id					= 0;
		$stream->include_comments	= TRUE;
		$stream->baseUrl			= \IPS\Http\Url::internal( "app=core&module=discover&controller=streams", 'front', 'discover_all' );
		
		/* Query */
		$query		= $stream->query()->setLimit( $this->configuration['max_results'] ?? 5 );
		$results	= $query->search();
		
		if ( !\count( $results ) )
		{
			return '';
		}

		return $this->output( $stream, $results, $this->configuration['title'] ?? \IPS\Member::loggedIn()->language()->addToStack( 'block_stream' ), $this->orientation );
	}
}