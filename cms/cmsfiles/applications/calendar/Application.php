<?php
/**
 * @brief		Calendar Application Class
 * @author		<a href=''>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		18 Dec 2013
 * @version
 * @todo support for google maps as well as mapbox
 * @todo Only request location info when search box is focused.
 */
 
namespace IPS\calendar;

/**
 * Core Application Class
 */
class _Application extends \IPS\Application
{
	/**
	 * Init
	 *
	 * @return	void
	 */
	public function init()
	{
		/* Requesting iCal/RSS Subscription, but guests are required to login */
		if( \IPS\Request::i()->module == 'calendar' and \IPS\Request::i()->controller == 'view' and \in_array( \IPS\Request::i()->do, array( 'rss', 'download' ) ) )
		{
			/* Validate RSS/Download key */
			if( \IPS\Request::i()->member )
			{
				$member = \IPS\Member::load( \IPS\Request::i()->member );
				if( !\IPS\Login::compareHashes( $member->getUniqueMemberHash(), (string) \IPS\Request::i()->key ) )
				{
					\IPS\Output::i()->error( 'node_error', '2L217/1', 404, '' );
				}
			}

			/* Output */
			if( \IPS\Request::i()->do == 'download' )
			{
				$this->download( \IPS\Request::i()->member ? $member : NULL );
			}

			$this->rss( \IPS\Request::i()->member ? $member : NULL );
		}

		/* Reset first day of week */
		if( \IPS\Settings::i()->ipb_calendar_mon )
		{
			\IPS\Output::i()->jsVars['date_first_day'] = 1;
		}
	}

	/**
	 * [Node] Get Icon for tree
	 *
	 * @note	Return the class for the icon (e.g. 'globe')
	 * @return	string|null
	 */
	protected function get__icon()
	{
		return 'calendar';
	}

	/**
	 * Latest events RSS
	 *
	 * @param	\IPS\Member|NULL	$member	Member to generate feed for
	 * @return	void
	 * @note	There is a hard limit of the most recent 500 events updated
	 */
	public function download( $member=NULL )
	{
		$feed	= new \IPS\calendar\Icalendar\ICSParser;
		$calendar = NULL;

		/* Are we viewing a specific calendar only? */
		if( \IPS\Request::i()->id )
		{
			try
			{
				$calendar = \IPS\calendar\Calendar::load( \IPS\Request::i()->id );

				if ( !$calendar->can( 'view', $member ) )
				{
					throw new \OutOfRangeException;
				}
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2L217/3', 404, '' );
			}
		}

		$where = array();

		if( $calendar !== NULL )
		{
			$where[] = array( 'event_calendar_id=?', $calendar->id );
		}

		foreach( \IPS\calendar\Event::getItemsWithPermission( $where, 'event_lastupdated DESC', 500, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, $member ) as $event )
		{
			$feed->addEvent( $event );
		}

		$ics = $feed->buildICalendarFeed( $calendar );

		\IPS\Output::i()->sendOutput( $ics, 200, 'text/calendar', [ 'Content-Disposition' => \IPS\Output::getContentDisposition( 'inline', "calendarEvents.ics" ) ], FALSE, FALSE, FALSE );
		exit;
	}

	/**
	 * Latest events RSS
	 *
	 * @param	\IPS\Member|NULL	$member	Member to generate feed for
	 * @return	void
	 */
	public function rss( $member=NULL )
	{
		if( !\IPS\Settings::i()->calendar_rss_feed )
		{
			\IPS\Output::i()->error( 'event_rss_feed_off', '2L182/1', 404, 'event_rss_feed_off_admin' );
		}

		/* Load member */
		if ( $member === NULL )
		{
			$member = \IPS\Member::loggedIn();
		}

		$rssTitle = $member->language()->get('calendar_rss_title');
		$document = \IPS\Xml\Rss::newDocument( \IPS\Http\Url::internal( 'app=calendar&module=calendar&controller=view', 'front', 'calendar' ), $rssTitle, $rssTitle );

		$_today	= \IPS\calendar\Date::getDate();

		$endDate = NULL;

		if( \IPS\Settings::i()->calendar_rss_feed_days > 0 )
		{
			$endDate = $_today->adjust( "+" . \IPS\Settings::i()->calendar_rss_feed_days . " days" );
		}

		foreach ( \IPS\calendar\Event::retrieveEvents( $_today, $endDate, NULL, NULL, FALSE, $member ) as $event )
		{
			$next = ( (int) \IPS\Settings::i()->calendar_rss_feed_order === 0 ) ? $event->nextOccurrence( $_today, 'startDate' ) : \IPS\DateTime::ts( $event->saved );
			$document->addItem( $event->title, $event->url(), $event->content, $next, $event->id );
		}

		/* @note application/rss+xml is not a registered IANA mime-type so we need to stick with text/xml for RSS */
		\IPS\Output::i()->sendOutput( $document->asXML(), 200, 'text/xml', array(), TRUE );
	}
	
	/**
	 * Default front navigation
	 *
	 * @code
	 	
	 	// Each item...
	 	array(
			'key'		=> 'Example',		// The extension key
			'app'		=> 'core',			// [Optional] The extension application. If ommitted, uses this application	
			'config'	=> array(...),		// [Optional] The configuration for the menu item
			'title'		=> 'SomeLangKey',	// [Optional] If provided, the value of this language key will be copied to menu_item_X
			'children'	=> array(...),		// [Optional] Array of child menu items for this item. Each has the same format.
		)
	 	
	 	return array(
		 	'rootTabs' 		=> array(), // These go in the top row
		 	'browseTabs'	=> array(),	// These go under the Browse tab on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Browse tab may not exist)
		 	'browseTabsEnd'	=> array(),	// These go under the Browse tab after all other items on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Browse tab may not exist)
		 	'activityTabs'	=> array(),	// These go under the Activity tab on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Activity tab may not exist)
		)
	 * @endcode
	 * @return array
	 */
	public function defaultFrontNavigation()
	{
		return array(
			'rootTabs'		=> array(),
			'browseTabs'	=> array( array( 'key' => 'Calendar' ) ),
			'browseTabsEnd'	=> array(),
			'activityTabs'	=> array()
		);
	}

	/**
	 * Returns a list of all existing webhooks and their payload in this app.
	 *
	 * @return array
	 */
	public function getWebhooks() : array
	{
		return array_merge(  [
				'calendarEvent_rsvp' => [
					'event' => \IPS\calendar\Event::class,
					'action' => "string with the state (attending/etc..)",
					'attendee' => \IPS\Member::class
				]
			],parent::getWebhooks());
	}

	/**
	 * Perform some legacy URL parameter conversions
	 *
	 * @return	void
	 */
	public function convertLegacyParameters()
	{
		$url = \IPS\Request::i()->url();
		$baseUrl = parse_url( \IPS\Settings::i()->base_url );

		/* We want to match /calendar/ if IC is not installed inside a directory, or if it is, the  /path/calendar/ exactly */
		$strToCheck = ( ! empty( trim( $baseUrl['path'], '/' ) ) ) ? '/' . trim( $baseUrl['path'], '/' ) . '/calendar/' : '/calendar/';


		// once we use php8, we can use str_starts_with, right now here's the hacky way with substr
		if( \mb_substr($url->data[ \IPS\Http\Url::COMPONENT_PATH ], 0, \mb_strlen($strToCheck)) === $strToCheck )
		{
			$newPath = str_replace( '/calendar/', '/events/', $url->data[ \IPS\Http\Url::COMPONENT_PATH ] );
			$url = $url->setPath( $newPath );
	
			\IPS\Output::i()->redirect( $url, NULL, 301 );
		}
	}
}