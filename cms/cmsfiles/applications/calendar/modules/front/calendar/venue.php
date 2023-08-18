<?php
/**
 * @brief		Venue
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		27 Feb 2017
 */

namespace IPS\calendar\modules\front\calendar;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * venue
 */
class _venue extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Venue we are viewing
	 */
	protected $venue	= NULL;

	/**
	 * Init
	 *
	 * @return	void
	 */
	public function execute()
	{
		try
		{
			$this->venue = \IPS\calendar\Venue::loadAndCheckPerms( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2L354/1', 404, '' );
		}

		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function manage()
	{
		/* Load the css for Calendar badges */
		\IPS\calendar\Calendar::addCss();

		$today = \IPS\calendar\Date::getDate();

		/* Get the month data */
		$day		= NULL;

		if( ( !\IPS\Request::i()->y OR \IPS\Request::i()->y == $today->year ) AND ( !\IPS\Request::i()->m OR \IPS\Request::i()->m == $today->mon ) )
		{
			$day	= $today->mday;
		}

		try
		{
			$date		= \IPS\calendar\Date::getDate( \IPS\Request::i()->y ?: NULL, \IPS\Request::i()->m ?: NULL, $day );
		}
		catch( \InvalidArgumentException $e )
		{
			\IPS\Output::i()->error( 'error_bad_date', '2L354/2', 403, '' );
		}

		$upcoming = \IPS\calendar\Event::retrieveEvents(
			\IPS\calendar\Date::getDate( $date->firstDayOfMonth('year'), $date->firstDayOfMonth('mon'), $date->firstDayOfMonth('mday') ),
			\IPS\calendar\Date::getDate( $date->lastDayOfMonth('year'), $date->lastDayOfMonth('mon'), $date->lastDayOfMonth('mday'), 23, 59, 59 ),
			NULL,
			NULL,
			FALSE,
			NULL,
			$this->venue
		);

		$upcomingOutput = \IPS\Theme::i()->getTemplate( 'venue', 'calendar', 'front' )->upcomingStream( $date, $upcoming, $this->venue );

		/* Address */
		$address = NULL;
		if ( $this->venue->address )
		{
			$address = \IPS\GeoLocation::buildFromjson( $this->venue->address )->toString();
		}

		/* Display */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( $upcomingOutput, 200, 'text/html' );
		}
		else
		{
			/* Add JSON-LD */
			\IPS\Output::i()->jsonLd['eventVenue']	= array(
				'@context'		=> "http://schema.org",
				'@type'			=> "EventVenue",
				'url'			=> (string) $this->venue->url(),
				'name'			=> $this->venue->_title
			);

			\IPS\Output::i()->title = $this->venue->_title;
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_venue.js', 'calendar', 'front' ) );

			/* We want to present the same breadcrumb structure as the rest of the calendar */
			\IPS\Output::i()->breadcrumb['module'] = array( \IPS\Http\Url::internal( "app=calendar&module=calendar&controller=view", 'front', 'calendar' ), \IPS\Member::loggedIn()->language()->addToStack('module__calendar_calendar') );

			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'venue', 'calendar', 'front' )->view( $this->venue, $upcomingOutput, NULL, $address );
		}
	}
}