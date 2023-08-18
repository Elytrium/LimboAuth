<?php
/**
 * @brief		upcomingEvents Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		18 Dec 2013
 */

namespace IPS\calendar\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * upcomingEvents Widget
 */
class _upcomingEvents extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'upcomingEvents';
	
	/**
	 * @brief	App
	 */
	public $app = 'calendar';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * Constructor
	 *
	 * @param	string				$uniqueKey				Unique key for this specific instance
	 * @param	array				$configuration			Widget custom configuration
	 * @param	null|string|array	$access					Array/JSON string of executable apps (core=sidebar only, content=IP.Content only, etc)
	 * @param	null|string			$orientation			Orientation (top, bottom, right, left)
	 * @return	void
	 */
	public function __construct( $uniqueKey, array $configuration, $access=null, $orientation=null )
	{
		parent::__construct( $uniqueKey, $configuration, $access, $orientation );

		/* We need to adjust for timezone too */
		$this->cacheKey = "widget_{$this->key}_" . $this->uniqueKey . '_' . md5( json_encode( $configuration ) . "_" . \IPS\Member::loggedIn()->language()->id . "_" . \IPS\Member::loggedIn()->skin . "_" . json_encode( \IPS\Member::loggedIn()->groups ) . "_" . $orientation . \IPS\Member::loggedIn()->timezone );
	}

	/**
	 * Initialize this widget
	 *
	 * @return	void
	 */
	public function init()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'calendar.css', 'calendar' ) );
		
		parent::init();
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
 		
 		/* Container */
		$form->add( new \IPS\Helpers\Form\Node( 'widget_calendar', isset( $this->configuration['widget_calendar'] ) ? $this->configuration['widget_calendar'] : 0, FALSE, array(
			'class'           => '\IPS\calendar\Calendar',
			'zeroVal'         => 'all',
			'permissionCheck' => 'view',
			'multiple'        => true
		) ) );
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'auto_hide', isset( $this->configuration['auto_hide'] ) ? $this->configuration['auto_hide'] : FALSE, FALSE ) );
		$form->add( new \IPS\Helpers\Form\Number( 'days_ahead', isset( $this->configuration['days_ahead'] ) ? $this->configuration['days_ahead'] : 7, TRUE, array( 'unlimited' => -1 ) ) );
		$form->add( new \IPS\Helpers\Form\Number( 'maximum_count', isset( $this->configuration['maximum_count'] ) ? $this->configuration['maximum_count'] : 5, TRUE, array( 'unlimited' => -1 ) ) );
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
 		if ( \is_array( $values['widget_calendar'] ) )
 		{
	 		$values['widget_calendar'] = array_keys( $values['widget_calendar'] );
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
		if( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'calendar', 'calendar' ) ) )
		{
			return '';
		}

		$_today	= new \IPS\calendar\Date( "now", \IPS\Member::loggedIn()->timezone ? new \DateTimeZone( \IPS\Member::loggedIn()->timezone ) : NULL );

		/* Do we have a days ahead cutoff? */
		$endDate	= NULL;

		if( isset( $this->configuration['days_ahead'] ) AND  $this->configuration['days_ahead'] > 0 )
		{
			$endDate	= $_today->adjust( "+" . $this->configuration['days_ahead'] . " days" );
		}
		
		$calendars = NULL;
		
		if ( ! empty( $this->configuration['widget_calendar'] ) )
		{
			$calendars = $this->configuration['widget_calendar'];
		}

		/* How many are we displaying? */
		$count = 5;
		if( isset( $this->configuration['maximum_count'] ) )
		{
			if(  $this->configuration['maximum_count'] > 0  )
			{
				$count = $this->configuration['maximum_count'];
			}
			else if ( $this->configuration['maximum_count'] == -1 )
			{
				$count = NULL;
			}
		}

		$events = \IPS\calendar\Event::retrieveEvents( $_today, $endDate, ( $calendars === NULL ? NULL : ( !\is_array( $calendars ) ? array( $calendars ) : $calendars ) ), $count, FALSE );
		
		/* Auto hiding? */
		if( !\count($events) AND isset( $this->configuration['auto_hide'] ) AND $this->configuration['auto_hide'] )
		{
			return '';
		}

		return $this->output( $events, $_today );
	}
}