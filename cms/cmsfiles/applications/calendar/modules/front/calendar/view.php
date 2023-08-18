<?php
/**
 * @brief		Calendar Views
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		23 Dec 2013
 */

namespace IPS\calendar\modules\front\calendar;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Calendar Views
 */
class _view extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Calendar we are viewing
	 */
	protected $_calendar	= NULL;

	/**
	 * @brief	Date object for the current day
	 */
	protected $_today		= NULL;
	
	/**
	 * @brief	Root nodes
	 */
	protected $roots		= NULL;

	/**
	 * Route
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* We aren't showing a sidebar in Calendar */
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\calendar\Calendar::addCss();

		/* Show the RSS link */
		if ( \IPS\Settings::i()->calendar_rss_feed )
		{
			$urls = $this->_downloadLinks();
			\IPS\Output::i()->rssFeeds['calendar_rss_title'] = $urls['rss'];
		}

		/* Is there only one calendar? */
		$this->roots	= \IPS\Settings::i()->club_nodes_in_apps ? \IPS\calendar\Calendar::rootsWithClubs() : \IPS\calendar\Calendar::roots();
		if ( \count( $this->roots ) == 1 AND !isset( \IPS\Request::i()->id ) )
		{
			$root				= array_shift( $this->roots );
			$this->_calendar	= \IPS\calendar\Calendar::loadAndCheckPerms( $root->_id );
		}

		/* Are we viewing a specific calendar only? */
		if( \IPS\Request::i()->id )
		{
			try
			{
				$this->_calendar	= \IPS\calendar\Calendar::loadAndCheckPerms( \IPS\Request::i()->id );
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2L182/2', 404, '' );
			}

			/* If we're viewing a club, set the breadcrumbs appropriately */
			if ( $club = $this->_calendar->club() )
			{
				$this->_calendar->clubCheckRules();
				
				$club->setBreadcrumbs( $this->_calendar );
			}
			else
			{
				\IPS\Output::i()->breadcrumb[] = array( NULL, $this->_calendar->_title );
			}
			
			/* Update Views */
			if ( !\IPS\Request::i()->isAjax() )
			{
				$this->_calendar->updateViews();
			}
		}

		if( $this->_calendar !== NULL AND $this->_calendar->_id )
		{
			\IPS\Output::i()->contextualSearchOptions[ \IPS\Member::loggedIn()->language()->addToStack( 'search_contextual_item_calendars' ) ] = array( 'type' => 'calendar_event', 'nodes' => $this->_calendar->_id );
		}

		$this->_today	= \IPS\calendar\Date::getDate();

		/* Get the date jumper - do this first in case we need to redirect */
		$jump		= $this->_jump();

		if( !\IPS\Request::i()->isAjax() )
        {
            \IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_browse.js', 'calendar', 'front' ) );
        }

		/* If there is a view requested in the URL, use it */
		if( isset( \IPS\Request::i()->view ) )
		{
			if( method_exists( $this, '_view' . ucwords( \IPS\Request::i()->view ) ) )
			{
				$method	= "_view" . ucwords( \IPS\Request::i()->view );
				$this->$method( $jump );
			}
			else
			{
				$method	= "_view" . ucwords( \IPS\Settings::i()->calendar_default_view );
				$this->$method( $jump );
			}
		}
		/* Otherwise use ACP default preference */
		else
		{
			\IPS\Request::i()->view = \IPS\Settings::i()->calendar_default_view;
			$method	= "_view" . ucwords( \IPS\Settings::i()->calendar_default_view );
			$this->$method( $jump );
		}

		/* Online User Location */
		if ( $this->_calendar )
		{
			\IPS\Session::i()->setLocation( $this->_calendar->url(), $this->_calendar->permissions()['perm_view'], 'loc_calendar_viewing_calendar', array( "calendar_calendar_{$this->_calendar->id}" => TRUE ) );
		}
		else
		{
			\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=calendar', 'front', 'calendar' ), array(), 'loc_calendar_viewing_calendar_all' );
		}
	}
	
	/**
	 * Show month view
	 *
	 * @param	\IPS\Helpers\Form	$jump	Calendar jump
	 * @return	void
	 */
	protected function _viewMonth( $jump )
	{
		/* Get the month data */
		$day		= NULL;

		if( ( !\IPS\Request::i()->y OR \IPS\Request::i()->y == $this->_today->year ) AND ( !\IPS\Request::i()->m OR \IPS\Request::i()->m == $this->_today->mon ) )
		{
			$day	= $this->_today->mday;
		}

		try
		{
			$date		= \IPS\calendar\Date::getDate( \IPS\Request::i()->y ?: NULL, \IPS\Request::i()->m ?: NULL, $day );

			/* Get the events within this range */
			$events		= \IPS\calendar\Event::retrieveEvents(
				\IPS\calendar\Date::getDate( $date->firstDayOfMonth('year'), $date->firstDayOfMonth('mon'), $date->firstDayOfMonth('mday') ),
				\IPS\calendar\Date::getDate( $date->lastDayOfMonth('year'), $date->lastDayOfMonth('mon'), $date->lastDayOfMonth('mday'), 23, 59, 59 ),
				$this->_calendar
			);
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->error( 'error_bad_date', '2L182/7', 403, '' );
		}

		/* If there are no events, tell search engines not to index the page but do NOT tell them not to follow links */
		if( \count($events) === 0 )
		{
			\IPS\Output::i()->metaTags['robots'] = 'noindex';
		}

		/* Display */
		$output = \IPS\Theme::i()->getTemplate( 'browse' )->calendarMonth( $this->roots, $date, $events, $this->_today, $this->_calendar, $jump );

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( $output, 200, 'text/html' );
		}
		else
		{
			\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('cal_month_title', FALSE, array( 'sprintf' => array( $date->monthName, $date->year ) ) );

			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'browse' )->calendarWrapper(
				$output,
				$this->roots,
				$this->_calendar,
				$jump,
				$date,
				$this->_downloadLinks()
			);	
		}		
	}
	
	/**
	 * Show week view
	 *
	 * @param	\IPS\Helpers\Form	$jump	Calendar jump
	 * @return	void
	 */
	protected function _viewWeek( $jump )
	{
		/* Get the week data */
		$week		= \IPS\Request::i()->w ? explode( '-', \IPS\Request::i()->w ) : NULL;
		try
		{
			$date		= \IPS\calendar\Date::getDate( isset( $week[0] ) ? $week[0] : NULL, isset( $week[1] ) ? $week[1] : NULL, isset( $week[2] ) ? $week[2] : NULL );
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->error( 'error_bad_date', '2L182/8', 403, '' );
		}

		$nextWeek	= $date->adjust( '+1 week' );
		$lastWeek	= $date->adjust( '-1 week' );

		/* Get the days of the week - we do this in PHP to help keep template a little cleaner */
		try
		{
			$days	= array();

			for( $i = 0; $i < 7; $i++ )
			{
				$days[]	= \IPS\calendar\Date::getDate( $date->firstDayOfWeek('year'), $date->firstDayOfWeek('mon'), $date->firstDayOfWeek('mday') )->adjust( $i . ' days' );
			}

			/* Get the events within this range */
			$events		= \IPS\calendar\Event::retrieveEvents(
				\IPS\calendar\Date::getDate( $date->firstDayOfWeek('year'), $date->firstDayOfWeek('mon'), $date->firstDayOfWeek('mday') ),
				\IPS\calendar\Date::getDate( $date->lastDayOfWeek('year'), $date->lastDayOfWeek('mon'), $date->lastDayOfWeek('mday'), 23, 59, 59 ),
				$this->_calendar
			);
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->error( 'error_bad_date', '2L182/C', 403, '' );
		}

		/* If there are no events, tell search engines not to index the page but do NOT tell them not to follow links */
		if( \count( $events ) === 0 )
		{
			\IPS\Output::i()->metaTags['robots'] = 'noindex';
		}

		/* Display */
		$output = \IPS\Theme::i()->getTemplate( 'browse' )->calendarWeek( $this->roots, $date, $events, $nextWeek, $lastWeek, $days, $this->_today, $this->_calendar, $jump );

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( $output, 200, 'text/html' );
		}
		else
		{
			\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('cal_week_title', FALSE, array( 'sprintf' => array( 
				$date->firstDayOfWeek('monthNameShort'), 
				$date->firstDayOfWeek('mday'),
				$date->firstDayOfWeek('year'),
				$date->lastDayOfWeek('monthNameShort'),
				$date->lastDayOfWeek('mday'),
				$date->lastDayOfWeek('year')
			) ) );

			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'browse' )->calendarWrapper(
				$output,
				$this->roots,
				$this->_calendar,
				$jump,
				$date,
				$this->_downloadLinks()
			);	
		}		
	}
	
	/**
	 * Show day view
	 *
	 * @param	\IPS\Helpers\Form	$jump	Calendar jump
	 * @return	void
	 */
	protected function _viewDay( $jump )
	{
		/* Get the day data */
		try
		{
			$date		= \IPS\calendar\Date::getDate( \IPS\Request::i()->y ?: NULL, \IPS\Request::i()->m ?: NULL, \IPS\Request::i()->d ?: NULL );
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->error( 'error_bad_date', '2L182/9', 403, '' );
		}

		$tomorrow	= clone $date->adjust( '+1 day' );
		$yesterday	= clone $date->adjust( '-1 day' );

		/* Get the events within this range */
		$events		= \IPS\calendar\Event::retrieveEvents( clone $date, clone $date, $this->_calendar );

		/* If there are no events, tell search engines not to index the page but do NOT tell them not to follow links */
		if( \count($events) === 0 )
		{
			\IPS\Output::i()->metaTags['robots'] = 'noindex';
		}

		$dayEvents	= array_fill( 0, 23, array() );
		$dayEvents['allDay']	= array();
		$dayEvents['count']		= 0;

		foreach( $events as $day => $_events )
		{
			foreach( $_events as $type => $event )
			{
				foreach( $event as $_event )
				{
					$dayEvents['count']++;

					if( $_event->all_day AND ( $_event->nextOccurrence( $date, 'startDate' ) AND
						$_event->nextOccurrence( $date, 'startDate' )->strFormat('%d') == $date->mday OR
						$_event->nextOccurrence( $date, 'endDate' ) AND $_event->nextOccurrence( $date, 'endDate' )->strFormat('%d') == $date->mday
					) )
					{
						$dayEvents['allDay'][ $_event->id ]	= $_event;
					}
					else
					{
						if( $_event->nextOccurrence( $date, 'startDate' ) AND $_event->nextOccurrence( $date, 'startDate' )->strFormat('%d') == $date->mday )
						{
							$dayEvents[ $_event->_start_date->hours ][ $_event->id ]	= $_event;
						}
						elseif( $_event->nextOccurrence( $date, 'endDate' ) AND $_event->nextOccurrence( $date, 'endDate' )->strFormat('%d') == $date->mday )
						{
							$dayEvents[ 0 ][ $_event->id ]	= $_event;
						}
					}
				}
			}
		}

		/* If there are no events, tell search engines not to index the page but do NOT tell them not to follow links */
		if( $dayEvents['count'] === 0 )
		{
			\IPS\Output::i()->metaTags['robots'] = 'noindex';
		}

		/* Display */
		$output = \IPS\Theme::i()->getTemplate( 'browse' )->calendarDay( $this->roots, $date, $dayEvents, $tomorrow, $yesterday, $this->_today, $this->_calendar, $jump );

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( $output, 200, 'text/html' );
		}
		else
		{
			\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('cal_month_day', FALSE, array( 'sprintf' => array( $date->monthName, $date->mday, $date->year ) ) );
			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'browse' )->calendarWrapper(
				$output,
				$this->roots,
				$this->_calendar,
				$jump,
				$date,
				$this->_downloadLinks()
			);
		}
	}

	/**
	 * @brief	Stream per page
	 */
	public $streamPerPage	= 50;

	/**
	 * Generate keyed links for RSS/iCal download
	 *
	 * @return	array
	 */
	protected function _downloadLinks()
	{		
		$downloadLinks = array( 'iCalCalendar' => '', 'iCalAll' => \IPS\Http\Url::internal( 'app=calendar&module=calendar&controller=view&do=download', 'front', 'calendar_icaldownload' ), 'rss' => \IPS\Http\Url::internal( 'app=calendar&module=calendar&controller=view&do=rss', 'front', 'calendar_rss' ) );

		if( $this->_calendar )
		{
			$downloadLinks['iCalCalendar'] = \IPS\Http\Url::internal( 'app=calendar&module=calendar&controller=view&id=' . $this->_calendar->id . '&do=download', 'front', 'calendar_calicaldownload', $this->_calendar->title_seo );
		}

		if ( \IPS\Member::loggedIn()->member_id )
		{
			$key = \IPS\Member::loggedIn()->getUniqueMemberHash();

			if( $this->_calendar )
			{
				$downloadLinks['iCalCalendar'] = $downloadLinks['iCalCalendar']->setQueryString( array( 'member' => \IPS\Member::loggedIn()->member_id , 'key' => $key ) );
			}
			$downloadLinks['iCalAll'] = $downloadLinks['iCalAll']->setQueryString( array( 'member' => \IPS\Member::loggedIn()->member_id , 'key' => $key ) );
			$downloadLinks['rss'] = $downloadLinks['rss']->setQueryString( array( 'member' => \IPS\Member::loggedIn()->member_id , 'key' => $key ) );
		}

		return $downloadLinks;
	}

	/**
	 * Return jump form and redirect if appropriate
	 *
	 * @return	void
	 */
	protected function _jump()
	{
		/* Build the form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Date( 'jump_to', \IPS\DateTime::create(), TRUE, array(), NULL, NULL, NULL, 'jump_to' ) );

		if( $values = $form->values() )
		{
			if( \IPS\Request::i()->goto )
			{
				$dateToGoTo = \IPS\DateTime::create();
			}
			else
			{
				$dateToGoTo = $values['jump_to'];
			}
			
			if ( $this->_calendar )
			{
				$url = \IPS\Http\Url::internal( "app=calendar&module=calendar&controller=view&view=day&id={$this->_calendar->_id}&y={$dateToGoTo->format('Y')}&m={$dateToGoTo->format('m')}&d={$dateToGoTo->format('j')}", 'front', 'calendar_calday', $this->_calendar->title_seo );
			}
			else
			{
				$url = \IPS\Http\Url::internal( "app=calendar&module=calendar&controller=view&view=day&y={$dateToGoTo->format('Y')}&m={$dateToGoTo->format('m')}&d={$dateToGoTo->format('j')}", 'front', 'calendar_day' );
			}
			
			\IPS\Output::i()->redirect( $url );
		}

		return $form;
	}

	/**
	 * Overview
	 *
	 * @return	void
	 */
	protected function _viewOverview()
	{
		$this->_today	= \IPS\calendar\Date::getDate();

		/* Featured events */
		$featured = iterator_to_array( \IPS\calendar\Event::featured( 13, '_rand' ) );

		/* If there are no featured get upcoming */
		if( \count( $featured ) === 0 )
		{
			$featured = \IPS\calendar\Event::retrieveEvents(
				$this->_today,
				\IPS\calendar\Date::getDate()->adjust( "next year" ),
				NULL,
				3,
				FALSE,
				NULL,
				NULL,
				FALSE,
				NULL,
				TRUE
			);
		}

		/* Events near me */
		$location = NULL;
		if( isset( \IPS\Request::i()->lat ) and isset( \IPS\Request::i()->lon ) )
		{
			$location = array( 'lat' => \IPS\Request::i()->lat, 'lon' => \IPS\Request::i()->lon );
		}
		else
		{
			/* Do an IP lookup */
			try
			{
				$geo = \IPS\GeoLocation::getByIp( \IPS\Request::i()->ipAddress() );
				$location = array( 'lat' => $geo->lat, 'lon' => $geo->long );
			}
			catch ( \Exception $e )
			{
				$location = array( 'lat' => \IPS\Settings::i()->map_center_lat, 'lon' => \IPS\Settings::i()->map_center_lon );
			}
		}

		/* Get the calendars we can view */
		$calendars	= \IPS\Settings::i()->club_nodes_in_apps ? \IPS\calendar\Calendar::rootsWithClubs() : \IPS\calendar\Calendar::roots();

		$nearme	= \IPS\calendar\Event::retrieveEvents(
			$this->_today,
			\IPS\calendar\Date::getDate()->adjust( "next year" ),
			NULL,
			6,
			FALSE,
			NULL,
			NULL,
			FALSE,
			$location,
			FALSE
		);

		/* Set map markers */
		$mapMarkers = array();
		foreach ( $nearme as $event )
		{
			$mapMarkers[ $event->id ] = array( 'lat' => (float) $event->latitude, 'long' => (float) $event->longitude , 'title' => $event->title );
		}

		/* Are we just returning nearby events? */
		if( \IPS\Request::i()->isAjax() && \IPS\Request::i()->get == 'nearMe' )
		{
			$output = \IPS\Theme::i()->getTemplate( 'events', 'calendar' )->nearMeContent( $nearme, $mapMarkers );
			\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $output );

			$toReturn = array(
				'content' => $output,
				'lat' => (float) $location['lat'],
				'long' => (float) $location['lon']
			);

			\IPS\Output::i()->sendOutput( json_encode($toReturn), 200, 'application/json' );
			return;
		}

		/* By month */
		$day		= NULL;

		if( ( !\IPS\Request::i()->y OR \IPS\Request::i()->y == $this->_today->year ) AND ( !\IPS\Request::i()->m OR \IPS\Request::i()->m == $this->_today->mon ) )
		{
			$day	= $this->_today->mday;
		}

		try
		{
			$date		= \IPS\calendar\Date::getDate( \IPS\Request::i()->y ?: NULL, \IPS\Request::i()->m ?: NULL, $day );

			/* Get the events within this range */
			$events		= \IPS\calendar\Event::retrieveEvents(
				\IPS\calendar\Date::getDate( $date->firstDayOfMonth('year'), $date->firstDayOfMonth('mon'), $date->firstDayOfMonth('mday') ),
				\IPS\calendar\Date::getDate( $date->lastDayOfMonth('year'), $date->lastDayOfMonth('mon'), $date->lastDayOfMonth('mday'), 23, 59, 59 ),
				NULL,
				NULL,
				FALSE,
				NULL,
				NULL,
				FALSE
			);
		}
		catch( \InvalidArgumentException $e )
		{
			\IPS\Output::i()->error( 'error_bad_date', '', 403, '' ); //@todo
		}

		/* Sort */
		$startDate = \IPS\calendar\Date::getDate();
		@usort( $events, function( $a, $b ) use ( $startDate )
		{
			if( $a->nextOccurrence( $startDate, 'startDate' ) === NULL )
			{
				return -1;
			}

			if( $b->nextOccurrence( $startDate, 'startDate' ) === NULL )
			{
				return 1;
			}

			if ( $a->nextOccurrence( $startDate, 'startDate' )->mysqlDatetime() == $b->nextOccurrence( $startDate, 'startDate' )->mysqlDatetime() )
			{
				return 0;
			}

			return ( $a->nextOccurrence( $startDate, 'startDate' )->mysqlDatetime() < $b->nextOccurrence( $startDate, 'startDate' )->mysqlDatetime() ) ? -1 : 1;
		} );

		/* Pagination */
		$offset = isset( \IPS\Request::i()->offset ) ? min( array( \IPS\Request::i()->offset, \count( $events ) ) ) : 0;

		/* If there are no events, tell search engines not to index the page but do NOT tell them not to follow links */
		if( \count( $events ) === 0 )
		{
			\IPS\Output::i()->metaTags['robots'] = 'noindex';
		}
		else
		{
			$events = \array_slice( $events, $offset, $this->streamPerPage );
		}

		/* Return events if we're after those specifically */
		if( \IPS\Request::i()->isAjax() && \IPS\Request::i()->get == 'byMonth' )
		{
			$eventHtml = "";

			if( \count( $events ) )
			{
				foreach( $events as $idx => $event )
				{
					$eventHtml .= \IPS\Theme::i()->getTemplate( 'events', 'calendar' )->event( $event );
				}
			}
			else
			{
				$eventHtml = \IPS\Theme::i()->getTemplate( 'events', 'calendar' )->noEvents();
			}

			\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $eventHtml );

			$toReturn = array(
				'count' => \count( $events ),
				'html' => $eventHtml
			);

			\IPS\Output::i()->sendOutput( json_encode( $toReturn ), 200, 'application/json' );
			return;
		}

		/* Non-existent page */
		if( $offset > 0 && !\count( $events ) )
		{
			\IPS\Output::i()->error( 'no_events_month', '2L182/B', 404, '' );
		}

		/* Clone date so we can update time without affecting other areas on this page */
		$startTime = clone $date;
		$endTime = clone $date;

		$startTime->setTime(0,0,0);
		$endTime->setTime(23,59,59);

		/* Online */
		$online = \IPS\calendar\Event::retrieveEvents(
			$startTime,
			NULL,
			NULL,
			NULL,
			FALSE,
			NULL,
			NULL,
			FALSE,
			NULL,
			TRUE
		);

		/* Build an array of month objects for the nav */
		$months = new \DatePeriod( (new \DateTime)->setDate( date('Y'), date('m'), 1 ), new \DateInterval( 'P1M' ), 11 );

		$stream = \IPS\Theme::i()->getTemplate( 'overview', 'calendar' )->byMonth( $calendars, \IPS\calendar\Date::getDate( $date->firstDayOfMonth('year'), $date->firstDayOfMonth('mon'), $date->firstDayOfMonth('mday') ), $featured, $events, NULL, $months );

		$form = $this->getForm();

		$date = \IPS\calendar\Date::getDate();

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( $stream, 200, 'text/html' );
		}
		else
		{
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_overview.js', 'calendar', 'front' ) );
			\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( '__app_calendar' );
			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'overview' )->wrapper( $featured, $nearme, $stream, $form, $mapMarkers, $online, $date, $this->_downloadLinks() );
		}

	}

	/**
	 * Build search form
	 *
	 * @return	\IPS\Helpers\Form
	 */
	public function getForm()
	{
		/* Search form */
		$form = new \IPS\Helpers\Form('form', 'search', \IPS\Http\Url::internal( 'app=calendar&module=calendar&controller=view&do=search' ) );
		if ( \IPS\GeoLocation::enabled() )
		{
			$form->add( new \IPS\Helpers\Form\Text( 'location', FALSE, \IPS\Request::i()->location, array( 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack( 'events_search_search_location') ) ) );
		}
		$form->add( new \IPS\Helpers\Form\DateRange( 'date', array( 'start' => NULL, 'end' => NULL ), FALSE, array() ) );
		$form->add( new \IPS\Helpers\Form\Select( 'show',  'all', FALSE, array( 'options' => array('all' => \IPS\Member::loggedIn()->language()->addToStack( 'all_events' ), 'online' => \IPS\Member::loggedIn()->language()->addToStack( 'online_events' ), 'physical' => \IPS\Member::loggedIn()->language()->addToStack( 'physical_events' ) ) ) ) );

		return $form;
	}

	/**
	 * Search
	 *
	 * @return	void
	 */
	protected function search()
	{
		\IPS\Output::i()->bypassCsrfKeyCheck = TRUE;
		$results = array();

		/* Build the search form */
		$form = $this->getForm();

		if( $values = $form->values() )
		{
			$select = "calendar_events.*, core_clubs.name";
			$sort = 'event_start_date asc';
			$location = NULL;
			$having = array();
			$where = array();
			$where[] = array( 'event_approved=?', 1 );
			$searchNearLocation = FALSE; // Should results be limited to near the provided location only? If not, we'll just use location to allow sorting.

			if( isset( \IPS\Request::i()->searchNearLocation ) && \IPS\Request::i()->searchNearLocation )
			{
				$searchNearLocation = TRUE;
			}

			if( \IPS\GeoLocation::enabled() )
			{
				if( \IPS\Request::i()->location )
				{
					/* Is it a location? */
					$locations = static::geocodeLocation( \IPS\Request::i()->location, FALSE );
					if( \is_array( $locations ) and \count( $locations ) )
					{
						if( $locations[0]['value'] )
						{
							$having[] = "( event_title LIKE CONCAT( '%', '" . $locations[0]['value'] . "', '%' ) OR name LIKE CONCAT( '%', '" . $locations[0]['value'] . "', '%' ) )";
						}

						$location = array( 'lat' => $locations[0]['lat'], 'lon' => $locations[0]['long'] );
						$searchNearLocation = TRUE;
					}
					else
					{
						$having[] = "( event_title LIKE CONCAT( '%', '" . \IPS\Request::i()->location . "', '%' ) OR name LIKE CONCAT( '%', '" . \IPS\Request::i()->location . "', '%' ) )";
					}
				}
				else if ( isset( \IPS\Request::i()->lat ) and isset( \IPS\Request::i()->lon ) and \IPS\Request::i()->lat !== 0 and \IPS\Request::i()->lon !== 0 )
				{
					$location = array( 'lat' => \IPS\Request::i()->lat, 'lon' => \IPS\Request::i()->lon );
					$searchNearLocation = TRUE;
				}
				elseif( $searchNearLocation )
				{
					/* Do an IP lookup */
					try
					{
						$geo = \IPS\GeoLocation::getByIp( \IPS\Request::i()->ipAddress() );
						$location = array( 'lat' => $geo->lat, 'lon' => $geo->long );
					}
					catch ( \Exception $e )
					{
						$location = array( 'lat' => \IPS\Settings::i()->map_center_lat, 'lon' => \IPS\Settings::i()->map_center_lon );
					}
				}

				if ( \is_array( $location ) and isset( $location['lat'] ) and isset( $location['lon'] ) and is_numeric( $location['lat'] ) and is_numeric( $location['lon'] )  )
				{
					$select = $select . ', ( 3959 * acos( cos( radians(' . $location['lat'] . ') ) * cos( radians( event_latitude ) ) * cos( radians( event_longitude ) - radians(' . $location['lon'] . ') ) + sin( radians(' . $location['lat'] . ') ) * sin(radians(event_latitude)) ) ) AS distance';

					if( $searchNearLocation )
					{
						$having[] = 'distance < 500';
					}

					if( isset( \IPS\Request::i()->sortBy ) && \IPS\Request::i()->sortBy == 'nearest' )
					{
						$sort = 'distance asc';
					}
				}
			}

			/* Filter? */
			if( isset( \IPS\Request::i()->show ) && \IPS\Request::i()->show !== 'all' )
			{
				if( \IPS\Request::i()->show == 'online' )
				{
					$where[] = array( '( event_online = 1 )' );
				}
				else {
					$where[] = array( '( ( event_end_date IS NULL OR TIMESTAMPDIFF( DAY, event_start_date, event_end_date ) < ? ) AND event_online = 0)', 30 );
				}
			}

			$today = new \IPS\calendar\Date;
			$member = \IPS\Member::loggedIn();

			$startDate = \IPS\calendar\Date::dateTimeToCalendarDate( $values['date']['start'] ?: $today );
			$endDate = $values['date']['end'] ? \IPS\calendar\Date::dateTimeToCalendarDate( $values['date']['end'] ) : NULL;

			/* Get timezone adjusted versions of start/end time */
			$startDateTimezone	= \IPS\calendar\Date::parseTime( $startDate->mysqlDatetime(), TRUE );
			$endDateTimezone	= ( $endDate !== NULL ) ? \IPS\calendar\Date::parseTime( $endDate->mysqlDatetime() ) : NULL;

			if ( $member->timezone )
			{
				$startDateTimezone->setTimezone( new \DateTimeZone( 'UTC' ) );

				if( $endDateTimezone !== NULL )
				{
					$endDateTimezone->setTimezone( new \DateTimeZone( 'UTC' ) );
				}
			}

			if( $endDate !== NULL AND $startDate == $endDate )
			{
				$where[]	= array(
					'( 
						( event_end_date IS NULL AND DATE( event_start_date ) = ? AND event_all_day=1 )
						OR
						( event_end_date IS NOT NULL AND DATE( event_start_date ) <= ? AND DATE( event_end_date ) >= ? AND event_all_day=1 )
						OR
						( event_end_date IS NULL AND event_start_date >= ? AND event_start_date <= ? AND event_all_day=0 )
						OR
						( event_end_date IS NOT NULL AND event_start_date <= ? AND event_end_date >= ? AND event_all_day=0 )
					)',
					$startDate->mysqlDatetime( FALSE ),
					$endDate->mysqlDatetime( FALSE ),
					$startDate->mysqlDatetime( FALSE ),
					$startDateTimezone->mysqlDatetime(),
					$startDateTimezone->adjust('+1 day')->mysqlDatetime(),
					$endDateTimezone->adjust('+1 day')->mysqlDatetime(),
					$startDateTimezone->mysqlDatetime()
				);
			}
			elseif( $endDate !== NULL )
			{
				$where[]	= array(
					'( 
						( event_end_date IS NULL AND DATE( event_start_date ) >= ? AND DATE( event_start_date ) <= ? AND event_all_day=1 )
						OR
						( event_end_date IS NOT NULL AND DATE( event_start_date ) <= ? AND DATE( event_end_date ) >= ? AND event_all_day=1 )
						OR
						( event_end_date IS NULL AND event_start_date >= ? AND event_start_date <= ? AND event_all_day=0 )
						OR
						( event_end_date IS NOT NULL AND event_start_date <= ? AND event_end_date >= ? AND event_all_day=0 )
					)',
					$startDate->mysqlDatetime( FALSE ),
					$endDate->mysqlDatetime( FALSE ),
					$endDate->mysqlDatetime( FALSE ),
					$startDate->mysqlDatetime( FALSE ),
					$startDateTimezone->mysqlDatetime(),
					$endDateTimezone->mysqlDatetime(),
					$endDateTimezone->mysqlDatetime(),
					$startDateTimezone->mysqlDatetime()
				);
			}
			else
			{
				$where[]	= array(
					"( 
						( DATE( event_start_date ) >= ? AND event_all_day=1 )
						OR
						( event_start_date >= ? AND event_all_day=0 )
						OR 
						( event_end_date IS NOT NULL AND DATE( event_start_date ) <= ? AND DATE( event_end_date ) >= ? AND event_all_day=1 ) 
						OR
						( event_end_date IS NOT NULL AND event_start_date <= ? AND event_end_date >= ? AND event_all_day=0 ) 
					)",
					$startDate->mysqlDatetime( FALSE ),
					$startDateTimezone->mysqlDatetime(),
					$startDate->mysqlDatetime( FALSE ),
					$startDate->mysqlDatetime( FALSE ),
					$startDateTimezone->adjust('+1 day')->mysqlDatetime(),
					$startDateTimezone->mysqlDatetime()
				);
			}

			$offset = isset( \IPS\Request::i()->offset ) ? \intval( \IPS\Request::i()->offset ) : 0;
			$limit = isset( \IPS\Request::i()->limit ) ? min( array( \intval( \IPS\Request::i()->limit ), 50 ) ) : 20;
			$query = \IPS\Db::i()->select( $select, 'calendar_events', $where, $sort, array( $offset, $limit ), NULL, implode( " OR ", $having ) );
			$query->join( 'calendar_calendars', "event_calendar_id = cal_id" );
			$query->join( 'core_clubs', "cal_club_id = id" );

			$results = new \IPS\Patterns\ActiveRecordIterator( $query, '\IPS\calendar\Event' );
		}

		$mapMarkers = array();
		foreach ( $results as $event )
		{
			if( $event->latitude and $event->longitude )
			{
				$mapMarkers[ $event->id ] = array( 'lat' => (float) $event->latitude, 'long' => (float) $event->longitude	, 'title' => $event->title );
			}
		}

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( '__app_calendar' );
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'overview' )->search( $form, $results, json_encode( $mapMarkers ) );
	}

	/**
	 * Geocode Location
	 *
	 * @param 	string 	$input 		Location search term
	 * @param 	bool 	$asJson 	Return type
	 * @return	array|string
	 */
	public static function geocodeLocation( $input = NULL, $asJson = TRUE )
	{
		$items = array();

		try
		{
			$geolocation = \IPS\GeoLocation::geocodeLocation( $input );
			if( $geolocation instanceof \IPS\GeoLocation )
			{
				$items[] = array(
					'value' => $geolocation->placeName,
					'html' => $geolocation->placeName,
					'lat' => $geolocation->lat,
					'long' => $geolocation->long
				);
			}
		}
		catch( \BadFunctionCallException ){}

		if( $asJson )
		{
			\IPS\Output::i()->json( $items );
		}
		else
		{
			return $items;
		}
	}
}