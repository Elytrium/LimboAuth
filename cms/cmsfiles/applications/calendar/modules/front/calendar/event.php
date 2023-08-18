<?php
/**
 * @brief		View Event Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		14 Jan 2014
 */

namespace IPS\calendar\modules\front\calendar;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * View Event Controller
 */
class _event extends \IPS\Content\Controller
{
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\calendar\Event';

	/**
	 * Init
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\calendar\Calendar::addCss();

		try
		{
			$this->event = \IPS\calendar\Event::load( \IPS\Request::i()->id );
			
			if ( !$this->event->canView( \IPS\Member::loggedIn() ) )
			{
				\IPS\Output::i()->error( 'node_error', '2L179/1', 403, '' );
			}
			
			if ( $this->event->cover_photo )
			{
				\IPS\Output::i()->metaTags['og:image'] = \IPS\File::get( 'calendar_Events', $this->event->cover_photo )->url;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2L179/2', 404, '' );
		}
		
		$this->event->container()->clubCheckRules();

		/* We want to present the same breadcrumb structure as the rest of the calendar */
		\IPS\Output::i()->breadcrumb['module'] = array( \IPS\Http\Url::internal( "app=calendar&module=calendar&controller=view", 'front', 'calendar' ), \IPS\Member::loggedIn()->language()->addToStack('module__calendar_calendar') );

		parent::execute();
	}
	
	/**
	 * View Event
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Init */
		parent::manage();

		/* Fetch RSVP data and pass to template */
		try
		{
			$attendees	= $this->event->attendees();
		}
		catch( \BadMethodCallException $e )
		{
			$attendees	= array( 0 => array(), 1 => array(), 2 => array() );
		}

		/* Sort out comments and reviews */
		$tabs = $this->event->commentReviewTabs();
		$_tabs = array_keys( $tabs );
		$tab = isset( \IPS\Request::i()->tab ) ? \IPS\Request::i()->tab : array_shift( $_tabs );
		$activeTabContents = $this->event->commentReviews( $tab );
		
		if ( \count( $tabs ) > 1 )
		{
			$commentsAndReviews = \count( $tabs ) ? \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $tab, $activeTabContents, $this->event->url(), 'tab', FALSE, TRUE ) : NULL;
		}
		else
		{
			$commentsAndReviews = $activeTabContents;
		}

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}

		/* Online User Location */
		\IPS\Session::i()->setLocation( $this->event->url(), $this->event->onlineListPermissions(), 'loc_calendar_viewing_event', array( $this->event->title => FALSE ) );

		/* Reminder */
		$reminder = NULL;
		try
		{
			$reminder = \IPS\Db::i()->select( '*', 'calendar_event_reminders', array( 'reminder_event_id=? and reminder_member_id=?', $this->event->id, (int) \IPS\Member::loggedIn()->member_id ) )->first();
		}
		catch ( \UnderflowException $e ) {}

		/* Address */
		$address = NULL;
		$location = NULL;
		$addressName = NULL;
		if ( \IPS\Settings::i()->calendar_venues_enabled and $this->event->venue() )
		{
			$location = \IPS\GeoLocation::buildFromjson( $this->event->venue()->address );
			$address = $location->toString();
			$addressName = $this->event->venue()->_title;
		}
		else if ( $this->event->location )
		{
			$location = \IPS\GeoLocation::buildFromjson( $this->event->location );
			$address = $location->toString();
		}

		/* Add JSON-LD */
		$format = $this->event->all_day ? "Y-m-d" : \IPS\DateTime::ISO8601;
		\IPS\Output::i()->jsonLd['event']	= array(
			'@context'		=> "http://schema.org",
			'@type'			=> "Event",
			'url'			=> (string) $this->event->url(),
			'name'			=> $this->event->mapped('title'),
			'description'	=> $this->event->truncated( TRUE, NULL ),
			'eventStatus'	=> "EventScheduled",
			'startDate'		=> $this->event->nextOccurrence( \IPS\calendar\Date::getDate(), 'startDate' ) ? 
				$this->event->nextOccurrence( \IPS\calendar\Date::getDate(), 'startDate' )->format( $format ) :
				$this->event->lastOccurrence( 'startDate' )->format( $format )
		);

		if( $this->event->_end_date )
		{
			\IPS\Output::i()->jsonLd['event']['endDate'] = $this->event->nextOccurrence( $this->event->nextOccurrence( \IPS\calendar\Date::getDate(), 'startDate' ) ?: \IPS\calendar\Date::getDate(), 'endDate' ) ? 
				$this->event->nextOccurrence( $this->event->nextOccurrence( \IPS\calendar\Date::getDate(), 'startDate' ) ?: \IPS\calendar\Date::getDate(), 'endDate' )->format( $format ) :
				$this->event->lastOccurrence( 'endDate' )->format( $format );
		}

		if( $this->event->container()->allow_reviews AND $this->event->reviews AND $this->event->averageReviewRating() )
		{
			\IPS\Output::i()->jsonLd['event']['aggregateRating'] = array(
				'@type'			=> 'AggregateRating',
				'reviewCount'	=> $this->event->reviews,
				'ratingValue'	=> $this->event->averageReviewRating(),
				'bestRating'	=> \IPS\Settings::i()->reviews_rating_out_of,
			);
		}

		if( $this->event->coverPhoto()->file )
		{
			\IPS\Output::i()->jsonLd['event']['image'] = (string) $this->event->coverPhoto()->file->url;
		}

		if( $this->event->rsvp )
		{
			if( \count( $attendees[1] ) )
			{
				\IPS\Output::i()->jsonLd['event']['attendee'] = array();

				foreach( $attendees[1] as $attendee )
				{
					\IPS\Output::i()->jsonLd['event']['attendee'][] = array(
						'@type'		=> 'Person',
						'name'		=> $attendee->name
					);
				}
			}
		}

		if( $location )
		{
			\IPS\Output::i()->jsonLd['event']['location'] = array(
				'@type'		=> 'Place',
				'address'	=> array(
					'@type'				=> 'PostalAddress',
					'streetAddress'		=> implode( ', ', $location->addressLines ),
					'addressLocality'	=> $location->city,
					'addressRegion'		=> $location->region,
					'postalCode'		=> $location->postalCode,
					'addressCountry'	=> $location->country,
				)
			);
			if( $addressName )
			{
				\IPS\Output::i()->jsonLd['event']['location']['name'] = $addressName;
			}
		}
		else
		{
			\IPS\Output::i()->jsonLd['event']['location'] = array(
				'@type'		=> 'Place',
				'name'		=> \IPS\Settings::i()->board_name,
				'address'	=> \IPS\Output::i()->jsonLd['event']['url'],
				'url'		=> \IPS\Output::i()->jsonLd['event']['url']
			);
		}

		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'view' )->view( $this->event, $commentsAndReviews, $attendees, $address, $reminder );
	}

	/**
	 * Show a small version of the calendar as a "hovercard"
	 *
	 * @return	void
	 */
	protected function hovercard()
	{
		/* Figure out our date object */
		$date = NULL;

		if( \IPS\Request::i()->sd )
		{
			$dateBits	= explode( '-', \IPS\Request::i()->sd );

			if( \count( $dateBits ) === 3 )
			{
				$date	= \IPS\calendar\Date::getDate( $dateBits[0], $dateBits[1], $dateBits[2] );
			}
		}

		if( $date === NULL )
		{
			$date	= \IPS\calendar\Date::getDate();
		}

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'view' )->eventBlock( $this->event, $date );
	}

	/**
	 * Download event as ICS
	 *
	 * @return	void
	 */
	protected function download()
	{

		$feed	= new \IPS\calendar\Icalendar\ICSParser;
		$feed->addEvent( $this->event );

		$ics	= $feed->buildICalendarFeed( $this->event->container() );

		\IPS\Output::i()->sendHeader( "Content-type: text/calendar; charset=UTF-8" );
		\IPS\Output::i()->sendHeader( 'Content-Disposition: inline; filename=calendarEvents.ics' );

		\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $ics );
		print $ics;
		exit;
	}

	/**
	 * Download RSVP attendee list
	 *
	 * @return	void
	 */
	protected function downloadRsvp()
	{
		$output	= \IPS\Theme::i()->getTemplate( 'view' )->attendees( $this->event );
		\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $output );
		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( $output ) );
	}

	/**
	 * RSVP for event
	 *
	 * @return	void
	 */
	protected function rsvp()
	{
		if( !$this->event->can('rsvp') )
		{
			\IPS\Output::i()->error( 'rsvp_error', '2L179/3', 403, '' );
		}

		if( $this->event->hasPassed() AND \IPS\Settings::i()->calendar_block_past_changes )
		{
			\IPS\Output::i()->error( 'no_rsvp_past_event', '2L179/6', 403, '' );
		}

		\IPS\Session::i()->csrfCheck();

		/* We delete either way at this point, because even if we select a different action we have to remove any existing RSVP preference */
		\IPS\Db::i()->delete( 'calendar_event_rsvp', array( 'rsvp_event_id=? AND rsvp_member_id=?', $this->event->id, (int) \IPS\Member::loggedIn()->member_id ) );

		if( \IPS\Request::i()->action == 'leave' )
		{
			$message	= 'rsvp_not_going';
		}
		else
		{
			/* Figure out the action */
			switch( \IPS\Request::i()->action )
			{
				case 'yes':
					$_go	= \IPS\calendar\Event::RSVP_YES;
				break;

				case 'maybe':
					$_go	= \IPS\calendar\Event::RSVP_MAYBE;
				break;

				case 'no':
				default:
					\IPS\Request::i()->action	= 'no';
					$_go	= \IPS\calendar\Event::RSVP_NO;
				break;
			}

			/* If there is a limit applied there are more rules */
			if( $this->event->rsvp_limit > 0 )
			{
				/* We do not accept "maybe" in this case */
				if( $_go === \IPS\calendar\Event::RSVP_MAYBE )
				{
					\IPS\Output::i()->error( 'rsvp_limit_nomaybe', '3L179/4', 403, '' );
				}

				/* And we have to actually check the limit */
				if( $_go == \IPS\calendar\Event::RSVP_YES and \count( $this->event->attendees( \IPS\calendar\Event::RSVP_YES ) ) >= $this->event->rsvp_limit )
				{
					\IPS\Output::i()->error( 'rsvp_limit_reached', '3L179/5', 403, '' );
				}
			}

			\IPS\Db::i()->insert( 'calendar_event_rsvp', array(
				'rsvp_event_id'		=> $this->event->id,
				'rsvp_member_id'	=> (int) \IPS\Member::loggedIn()->member_id,
				'rsvp_date'			=> time(),
				'rsvp_response'		=> (int) $_go
			) );

			$webhookData = [
				'event' => $this->event->apiOutput(),
				'action' => \IPS\Request::i()->action,
				'attendee' => \IPS\Member::loggedIn()->apiOutput(),
			];

			$message	= 'rsvp_selection_' . \IPS\Request::i()->action;

			\IPS\Api\Webhook::fire( 'calendarEvent_rsvp', $webhookData );

			\IPS\Member::loggedIn()->achievementAction( 'calendar', 'Rsvp', $this->event );
		}

		\IPS\Output::i()->redirect( $this->event->url(), $message );
	}

	/**
	 * Edit Item
	 *
	 * @return	void
	 */
	protected function edit()
	{
		if ( \IPS\Application::appIsEnabled('cloud') and $this->event->livetopic_id )
		{
			/* Allow live topic edit form to handle this */
			try
			{
				/* Make sure it's a valid topic */
				$liveTopic = \IPS\cloud\LiveTopic::load( $this->event->livetopic_id );
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=livetopics&action=create&fromEvent=1&id=' . $liveTopic->id, 'front', 'modcp_livetopics' ) );
			}
			catch( \Exception )	{ }
		}

		/* Are we blocking changes to past events? */
		if( $this->event->hasPassed() AND \IPS\Settings::i()->calendar_block_past_changes )
		{
			if ( !\IPS\calendar\Event::modPermission( 'edit', \IPS\Member::loggedIn(), $this->event->containerWrapper() ) )
			{
				\IPS\Output::i()->error( 'no_edit_past_event', '2L179/7', 403, '' );
			}
		}

		/* Output resources and go */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_submit.js', 'calendar', 'front' ) );

		return parent::edit();
	}

	/**
	 * Return the form for editing. Abstracted so controllers can define a custom template if desired.
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	string
	 */
	protected function getEditForm( $form )
	{
		return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'submit', 'calendar' ), 'submitForm' ) );
	}

	/**
	 * Set a reminder
	 *
	 * @return	void
	 */
	protected function setReminder()
	{
		\IPS\Session::i()->csrfCheck();

		/* Members only */
		if( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'node_error', '3L369/1', 403, '' );
		}

		/* Existing reminder? */
		$existing = NULL;
		try
		{
			$existing = \IPS\Db::i()->select( '*', 'calendar_event_reminders', array( 'reminder_event_id=? and reminder_member_id=?', $this->event->id, (int) \IPS\Member::loggedIn()->member_id ) )->first();
		}
		catch ( \UnderflowException $e ) {}

		/* Build the form */

		/* How far in the future is this event so we can set realistic max reminders */
		$diff = $this->event->_start_date->diff( \IPS\DateTime::create() );
		$max = $diff->days;

		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_vertical';
		$form->add( new \IPS\Helpers\Form\Number( 'event_remind_me', isset( $existing ) ? $existing['reminder_days_before'] : ( ( $max < 3 ) ? $max : 3 ), TRUE, array( 'min' => 1, 'max' => (int) $max ), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('event_remind_days_before'), 'event_remind_me' ) );

		if ( $existing )
		{
			$form->addButton( 'event_dont_remind', 'link', \IPS\Http\Url::internal( "app=calendar&module=calendar&controller=event&do=removeReminder&action=remove&id={$this->event->id}")->csrf(), 'ipsButton ipsButton_negative ipsPos_right', array('data-action' => 'removereminder') );
		}

		if( $values = $form->values() )
		{
			/* Delete existing */
			\IPS\Db::i()->delete( 'calendar_event_reminders', array( 'reminder_event_id=? AND reminder_member_id=?', $this->event->id, (int) \IPS\Member::loggedIn()->member_id ) );

			\IPS\Db::i()->insert( 'calendar_event_reminders', array(
				'reminder_event_id'		=> $this->event->id,
				'reminder_member_id'	=> (int) \IPS\Member::loggedIn()->member_id,
				'reminder_date'			=> $this->event->_start_date->sub( new \DateInterval( 'P' . (int) $values['event_remind_me'] . 'D' ) )->getTimestamp(),
				'reminder_days_before'	=> (int) $values['event_remind_me'],
			) );

			\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 1 ), array( '`key`=?', 'eventreminders' ) );

			$message = 'event_reminder_added';

			\IPS\Output::i()->redirect( $this->event->url(), $message );
		}

		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'event_set_reminder' );
		$output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'view', 'calendar' ), 'reminderForm' ) );

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( $output, 200, 'text/html' );
		}
		else
		{
			\IPS\Output::i()->output = $output;
		}
	}

	/**
	 * Remve Reminder
	 *
	 * @return	void
	 */
	protected function removeReminder()
	{
		\IPS\Session::i()->csrfCheck();

		if ( \IPS\Request::i()->action == 'remove' )
		{
			/* Delete existing */
			\IPS\Db::i()->delete( 'calendar_event_reminders', array( 'reminder_event_id=? AND reminder_member_id=?', $this->event->id, (int) \IPS\Member::loggedIn()->member_id ) );

			$message = 'event_reminder_removed';
		}

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'ok' );
		}
		else
		{
			\IPS\Output::i()->redirect( $this->event->url(), $message );
		}
	}

	/**
	 * Reminder button
	 *
	 * @return	void
	 */
	protected function reminderButton()
	{
		/* Existing reminder? */
		$existing = NULL;
		try
		{
			$existing = \IPS\Db::i()->select( '*', 'calendar_event_reminders', array( 'reminder_event_id=? and reminder_member_id=?', $this->event->id, (int)\IPS\Member::loggedIn()->member_id ) )->first();
		}
		catch ( \UnderflowException $e )
		{
		}

		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'view', 'calendar', 'front' )->reminderButton( $this->event, $existing ) );
	}
}