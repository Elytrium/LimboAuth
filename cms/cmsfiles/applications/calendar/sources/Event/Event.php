<?php
/**
 * @brief		Calendar Event Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		7 Jan 2014
 */

namespace IPS\calendar;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Calendar Event Model
 */
class _Event extends \IPS\Content\Item implements
\IPS\Content\Permissions,
\IPS\Content\Tags,
\IPS\Content\Followable,
\IPS\Content\ReadMarkers,
\IPS\Content\Hideable, \IPS\Content\Featurable, \IPS\Content\Lockable,
\IPS\Content\Shareable,
\IPS\Content\Searchable,
\IPS\Content\Embeddable,
\IPS\Content\MetaData,
\IPS\Content\EditHistory,
\IPS\Content\Anonymous
{
	use \IPS\Content\Reactable, \IPS\Content\Reportable, \IPS\Content\ViewUpdates, \IPS\Content\Statistics;
	
	/**
	 * @brief	Application
	 */
	public static $application = 'calendar';
	
	/**
	 * @brief	Module
	 */
	public static $module = 'calendar';
	
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'calendar_events';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'event_';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
		
	/**
	 * @brief	Node Class
	 */
	public static $containerNodeClass = 'IPS\calendar\Calendar';
	
	/**
	 * @brief	Comment Class
	 */
	public static $commentClass = 'IPS\calendar\Event\Comment';
	
	/**
	 * @brief	Review Class
	 */
	public static $reviewClass = 'IPS\calendar\Event\Review';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'container'				=> 'calendar_id',
		'author'				=> 'member_id',
		'author_name'		=> 'author_name',
		'title'					=> 'title',
		'content'				=> 'content',
		'num_comments'			=> 'comments',
		'unapproved_comments'	=> 'queued_comments',
		'hidden_comments'		=> 'hidden_comments',
		'num_reviews'			=> 'reviews',
		'unapproved_reviews'	=> 'unapproved_reviews',
		'hidden_reviews'		=> 'hidden_reviews',
		'last_comment'			=> 'last_comment',
		'last_review'			=> 'last_review',
		'date'					=> 'saved',
		'updated'				=> 'lastupdated',
		'rating'				=> 'rating',
		'approved'				=> 'approved',
		'approved_by'			=> 'approved_by',
		'approved_date'			=> 'approved_on',
		'featured'				=> 'featured',
		'locked'				=> 'locked',
		'ip_address'			=> 'ip_address',
		'cover_photo'			=> 'cover_photo',
		'cover_photo_offset'	=> 'cover_offset',
		'meta_data'				=> 'meta_data',
		'edit_time'			=> 'edit_time',
		'edit_show'			=> 'append_edit',
		'edit_member_name'	=> 'edit_member_name',
		'edit_reason'			=> 'edit_reason',
		'is_anon'			=> 'is_anon',
	);
	
	/**
	 * @brief	Title
	 */
	public static $title = 'calendar_event';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'calendar';
	
	/**
	 * @brief	Form Lang Prefix
	 */
	public static $formLangPrefix = 'event_';
	
	/**
	 * @brief	Cover Photo Storage Extension
	 */
	public static $coverPhotoStorageExtension = 'calendar_Events';
	
	/**
	 * @brief	Use a default cover photo
	 */
	public static $coverPhotoDefault = true;

	/**
	 * @brief	Cached date objects
	 */
	protected $dateObjects	= array( 'start' => NULL, 'end' => NULL );

	/**
	 * @brief	Cached venue
	 */
	protected $venueObject = NULL;

	/**
	 * @brief	Location Data Cache
	 */
	protected $locationData = NULL;

	/**
	 * @brief	Happening Cache
	 */
	protected $happeningData = FALSE;
	
	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'calendar-event';

	/**
	 * @brief		RSVP statuses
	 */
	const RSVP_NO		= 0;

	/**
	 * @brief		RSVP statuses
	 */
	const RSVP_YES		= 1;

	/**
	 * @brief		RSVP statuses
	 */
	const RSVP_MAYBE	= 2;
	
	/**
	 * Columns needed to query for search result / stream view
	 *
	 * @return	array
	 */
	public static function basicDataColumns()
	{
		$return = parent::basicDataColumns();
		$return[] = 'event_recurring';
		$return[] = 'event_start_date';
		$return[] = 'event_end_date';
		$return[] = 'event_all_day';
		return $return;
	}
	
	/**
	 * Set the title
	 *
	 * @param	string	$title	Title
	 * @return	void
	 */
	public function set_title( $title )
	{
		$this->_data['title'] = $title;
		$this->_data['title_seo'] = \IPS\Http\Url\Friendly::seoTitle( $title );
	}

	/**
	 * Get SEO name
	 *
	 * @return	string
	 */
	public function get_title_seo()
	{
		if ( !$this->_data['title_seo'] )
		{
			$this->title_seo	= \IPS\Http\Url\Friendly::seoTitle( $this->title );
			$this->save();
		}

		return $this->_data['title_seo'] ?: \IPS\Http\Url\Friendly::seoTitle( $this->title );
	}

	/**
	 * Get the album HTML, if there is one associated
	 *
	 * @return	string
	 */
	public function get__album()
	{
		if ( \IPS\Application::appIsEnabled( 'gallery' ) AND $this->album )
		{
			try
			{
				$album = \IPS\gallery\Album::loadAndCheckPerms( $this->album );

				$gallery = \IPS\Application::load( 'gallery' );
				$gallery::outputCss();

				return \IPS\Theme::i()->getTemplate( 'browse', 'gallery', 'front' )->miniAlbum( $album );
			}
			catch( \OutOfRangeException $e ){}
			catch( \UnderflowException $e ){}
		}

		return '';
	}

	/**
	 * Get the recurring event text
	 *
	 * @return	string
	 */
	public function get__recurring_text()
	{
		$recurringData	= \IPS\calendar\Icalendar\ICSParser::parseRrule( $this->recurring, 'UTC' );

		/* If the event does not repeat, just return */
		if ( !$recurringData['event_repeat'] )
		{
			return '';
		}

		/* Hold parameters to sprintf() into the language string */
		$params			= array( $this->_start_date->localeDate() );
		$pluralize		= array( $this->_start_date->localeDate() <= ( new \IPS\DateTime )->localeDate() ? 1 : 2 );

		/* Figure out the basic language string */
		$langString	= "recur_human_" . $recurringData['event_repeats'];

		if ( $recurringData['event_repeat_freq'] > 1 )
		{
			$langString		= $langString . '_multi';

			/* Get repeater info */
			$params[] = \IPS\Member::loggedIn()->language()->addToStack( 'recur_human__x' . $recurringData['event_repeats'], FALSE, array( 'pluralize' => array( $recurringData['event_repeat_freq'] ) ) );
		}
		
		/* If recurring weekly, take days into account */
		if ( $recurringData['event_repeats'] == 'weekly' )
		{
			$days	= array();

			foreach( \IPS\calendar\Date::getDayNames() as $day )
			{
				if ( $recurringData['repeat_freq_on_' . $day['ical'] ] )
				{
					$days[]	= $day['full'];
				}
			}

			if ( \count( $days ) )
			{
				$langString	= $langString . '_days';
				$params[]	= \IPS\Member::loggedIn()->language()->formatList( $days );
			}
		}

		/* Finally, reflect the ending data */
		if ( $recurringData['repeat_end_occurrences'] )
		{
			$params[]	= \IPS\Member::loggedIn()->language()->addToStack( 'recur_human__occurrences', FALSE, array( 'pluralize' => array( $recurringData['repeat_end_occurrences'] ) ) );
		}
		elseif ( $recurringData['repeat_end_date'] )
		{
			$params[]	= \IPS\Member::loggedIn()->language()->addToStack( 'recur_human__until', FALSE, array( 'sprintf' => array( $recurringData['repeat_end_date']->localeDate() ) ) );
		}
		else
		{
			$params[]	= \IPS\Member::loggedIn()->language()->addToStack('recur_human__forever');
		}

		return  \IPS\Member::loggedIn()->language()->addToStack( $langString, FALSE, array( 'pluralize' => $pluralize, 'sprintf' => $params ) );
	}

	/**
	 * @brief	Cached occurrences - this may or may not satisfy a query to nextOccurrence()
	 */
	protected $parsedOccurrences = array();

	/**
	 * @brief	Range of occurrences found already
	 */
	protected $occurenceRangeChecked = array( 'start' => NULL, 'end' => NULL );

	/**
	 * Find occurrences of an event within a supplied date range
	 *
	 * @param	\IPS\calendar\Date		$startDate		Date to start from
	 * @param	\IPS\calendar\Date		$endDate		Date to end at
	 * @return	array
	 */
	public function findOccurrences( $startDate, $endDate )
	{
		if ( !$this->recurring )
		{
			return array();
		}

		/* If we've already found our occurrences, just return them */
		if ( $this->occurenceRangeChecked['start'] !== NULL AND $this->occurenceRangeChecked['start'] <= $startDate->getTimestamp() AND $this->occurenceRangeChecked['end'] !== NULL AND $this->occurenceRangeChecked['end'] >= $endDate->getTimestamp() )
		{
			return $this->parsedOccurrences;
		}

		$this->occurenceRangeChecked = array( 'start' => $startDate->getTimestamp(), 'end' => $endDate->getTimestamp() );

		/* Parse out our recurrence data */
		$recurringData = \IPS\calendar\Icalendar\ICSParser::parseRrule( $this->recurring, NULL, $this->_start_date );
		if ( !$recurringData['event_repeat'] )
		{
			return array();
		}

		/* If this event starts after our ending range, it doesn't qualify */
		if ( $this->_start_date->mysqlDatetime( FALSE ) > $endDate->mysqlDatetime( FALSE ) )
		{
			return array();
		}

		/* If the recurrences have an end date, and the end date is before our start range, it doesn't qualify */
		if ( $recurringData['repeat_end_date'] !== NULL AND $recurringData['repeat_end_date']->mysqlDatetime( FALSE ) < $startDate->mysqlDatetime( FALSE ) )
		{
			/* Actually, this isn't true...I had an event that "ended" on March 24 but because it was a recurring ranged event, the last occurrence was March 22-March 25 */
			//return $results;
		}

		/* Return the results we found after storing them */
		$timezone = ( !empty( $this->timezone ) and \in_array( \IPS\DateTime::getFixedTimezone( $this->timezone ), \DateTimeZone::listIdentifiers() ) ) ? new \DateTimeZone( $this->timezone ) : NULL;
		$this->parsedOccurrences = static::_findOccurances( $this->_start_date, $this->_end_date, $startDate, $endDate, $recurringData, $timezone, $this->all_day );

		return $this->parsedOccurrences;
	}

	/**
	 * Get new occurrence end date
	 *
	 * @param	\IPS\calendar\Date	$eventStart		Event start date
	 * @param	\IPS\calendar\Date	$eventEnd		Event end date
	 * @param	\IPS\calendar\Date	$startDate		New start date
	 * @param	\IPS\calendar\Date	$endDate		Date to end at
	 * @return	\IPS\calendar\Date
	 */
	protected static function _determineNewEndDate( $eventStart, $eventEnd, $startDate, $endDate )
	{
		/* If there's no end date, there's nothing to do */
		if ( !$eventEnd )
		{
			return NULL;
		}

		/* Figure out the difference between the original start and end date */
		$diff	= $eventStart->diff( $eventEnd );
		$method	= $diff->invert ? 'sub' : 'add';

		return ( clone $startDate )
			->$method( $diff )
			->setTimezone( $endDate->getTimezone() )
			->setTime( $endDate->format('H'), $endDate->format( 'i' ), $endDate->format('s') );
	}
	
	/**
	 * Get occurances
	 *
	 * @param	\IPS\calendar\Date	$eventStart		Event start date
	 * @param	\IPS\calendar\Date	$eventEnd		Event end date
	 * @param	\IPS\calendar\Date	$startDate		Date to start from
	 * @param	\IPS\calendar\Date	$endDate		Date to end at
	 * @param	array				$recurringData	Reccurance data
	 * @param	\DateTimeZone		$eventTimezone	Event timezone
	 * @param 	boolean 			$allDay			All day event?
	 * @return	array
	 */
	public static function _findOccurances( $eventStart, $eventEnd, $startDate, $endDate, $recurringData, ?\DateTimeZone $eventTimezone=NULL, bool $allDay=FALSE )
	{
		$keyword		= NULL;
		$results 		= array();

		/* Because we manipulate these values to create new dates later, clone them so we are not manipulating the original objects */
		$startDate = ( clone $startDate )->sub( new \DateInterval( 'PT24H' ) );
		$endDate = ( clone $endDate )->add( new \DateInterval( 'PT24H' ) );

		$endDateInformation = $endDate->getDateInformation( $endDate->getTimestamp() );

		$endDate = \IPS\calendar\Date::getDate( $endDateInformation['year'], $endDateInformation['mon'], $endDateInformation['mday'], $endDateInformation['hours'], $endDateInformation['minutes'], $endDateInformation['seconds'] );

		switch ( $recurringData['event_repeats'] )
		{
			case 'daily':
				$keyword	= "days";
			break;

			case 'weekly':
				/* Get the days we repeat on */
				$_repeatDays	= array();

				/* adjust repeat freq relative to event start date */
				$offset = 0;

				/* Due to a change made in PR #2058, all day events are stored as UTC regardless of chosen TZ. This causes issues
				   when adjusting the wday (added in d75481b) as timed events do store the TZ as per the user's choice.
				   For now, we will only adjust the wday if the event is not all day.
				   processForm() has also been changed to allow the correct TZ to be stored for all day events, so we have the
				   correct data for a potenital future change in how all day events are processed.
				*/

				if( ! $allDay and $eventTimezone )
				{
					$relativeDate = clone $eventStart;
					$relativeDate->setTimezone( $eventTimezone );

					if( $relativeDate->weekday !== $eventStart->weekday )
					{
						$offset = $relativeDate->wday - $eventStart->wday;
					}
				}

				foreach ( \IPS\calendar\Date::getDayNames() as $key => $day )
				{
					if ( $recurringData['repeat_freq_on_' . $day['ical'] ] == TRUE )
					{
						$lookup = ( $key - $offset ) < 0 ? ( $key + $offset ) : ( $key - $offset );
						$_repeatDays[] = \IPS\calendar\Date::getDayNames()[ $lookup ]['english'];
					}
				}

				/* If not repeating only on specific days then we have a normal recurring event */
				if ( !\count($_repeatDays) )
				{
					$keyword	= 'weeks';
				}
				else
				{
					$date			= $eventStart;
					$eDate			= $eventEnd;

					if ( ( $date->mysqlDatetime( FALSE ) >= $startDate->mysqlDatetime( FALSE ) AND $date->mysqlDatetime( FALSE ) <= $endDate->mysqlDatetime( FALSE ) ) OR
						( $eDate !== NULL AND $eDate->mysqlDatetime( FALSE ) >= $startDate->mysqlDatetime( FALSE ) AND $eDate->mysqlDatetime( FALSE ) <= $endDate->mysqlDatetime( FALSE ) ) OR
						( $eDate !== NULL AND $date->mysqlDatetime( FALSE ) <= $startDate->mysqlDatetime( FALSE ) AND $eDate->mysqlDatetime( FALSE ) >= $endDate->mysqlDatetime( FALSE ) )
					  )
					{
						$results[]	= array( 'startDate' => $date, 'endDate' => $eDate );
					}
					
					/* Figure out which of the days is next. For example, if the start date is a Wednesday, and the
						event repeats every Monday and Friday, we need to start with Friday (not Monday) because that's
						when the first occurance is */
					$nextTimes = array();
					foreach ( $_repeatDays as $repeatDay )
					{
						$nextTimes[ $repeatDay ] = $date->adjust( "next {$repeatDay}" )->getTimestamp();
					}
					asort( $nextTimes );
					$nextTimes = array_keys( $nextTimes );
					$nextDay = array_shift( $nextTimes );

					/* We have to reset the $_repeatDays array pointer so that it matches $nextDay, otherwise it is possible a day might be skipped */
					while ( current( $_repeatDays ) !== $nextDay )
					{
						$resetNextDay	= next( $_repeatDays );

						if ( $resetNextDay === FALSE )
						{
							reset( $_repeatDays );
							$resetNextDay	= current( $_repeatDays );
						}
					}

					/* We need to set a counter in case we need to repeat every X weeks */
					$iteration	= 1;

					/* Do we have an occurrences limit? */
					if ( $recurringData['repeat_end_occurrences'] )
					{
						$occurrences	= 0;

						while ( $occurrences < $recurringData['repeat_end_occurrences'] )
						{
							$date		= $date->adjust( "next {$nextDay}" );
							$eDate		= static::_determineNewEndDate( $eventStart, $eventEnd, $date, $eDate );

							/* Figure out the next day this occurs on */
							$nextDay	= next( $_repeatDays );

							if ( $nextDay === FALSE )
							{
								reset( $_repeatDays );
								$nextDay	= current( $_repeatDays );
							}

							/* Are we repeating every other week or something? */
							if ( $recurringData['event_repeat_freq'] AND $iteration % $recurringData['event_repeat_freq'] != 0 )
							{
								$iteration++;
								continue;
							}

							$iteration++;
							$occurrences++;

							if ( ( $date->mysqlDatetime( FALSE ) >= $startDate->mysqlDatetime( FALSE ) AND $date->mysqlDatetime( FALSE ) <= $endDate->mysqlDatetime( FALSE ) ) OR
								( $eDate !== NULL AND $eDate->mysqlDatetime( FALSE ) >= $startDate->mysqlDatetime( FALSE ) AND $eDate->mysqlDatetime( FALSE ) <= $endDate->mysqlDatetime( FALSE ) ) OR
								( $eDate !== NULL AND $date->mysqlDatetime( FALSE ) <= $startDate->mysqlDatetime( FALSE ) AND $eDate->mysqlDatetime( FALSE ) >= $endDate->mysqlDatetime( FALSE ) )
							  )
							{
								$results[]	= array( 'startDate' => $date, 'endDate' => $eDate );
							}
						}
					}
					/* Do we have an end date for the recurrences? */
					elseif ( $recurringData['repeat_end_date'] )
					{
						while ( $date->mysqlDatetime( FALSE ) < $recurringData['repeat_end_date']->mysqlDatetime( FALSE ) )
						{
							$date		= $date->adjust( "next {$nextDay}" );
							$eDate		= static::_determineNewEndDate( $eventStart, $eventEnd, $date, $eDate );

							/* Figure out the next day this occurs on */
							$nextDay	= next( $_repeatDays );

							if ( $nextDay === FALSE )
							{
								reset( $_repeatDays );
								$nextDay	= current( $_repeatDays );
							}

							/* Are we repeating every other week or something? */
							if ( $recurringData['event_repeat_freq'] AND $iteration % $recurringData['event_repeat_freq'] != 0 )
							{
								$iteration++;
								continue;
							}
							
							$iteration++;

							if ( ( $date->mysqlDatetime( FALSE ) >= $startDate->mysqlDatetime( FALSE ) AND $date->mysqlDatetime( FALSE ) <= $endDate->mysqlDatetime( FALSE ) ) OR
								( $eDate !== NULL AND $eDate->mysqlDatetime( FALSE ) >= $startDate->mysqlDatetime( FALSE ) AND $eDate->mysqlDatetime( FALSE ) <= $endDate->mysqlDatetime( FALSE ) ) OR
								( $eDate !== NULL AND $date->mysqlDatetime( FALSE ) <= $startDate->mysqlDatetime( FALSE ) AND $eDate->mysqlDatetime( FALSE ) >= $endDate->mysqlDatetime( FALSE ) )
							  )
							{
								$results[]	= array( 'startDate' => $date, 'endDate' => $eDate );
							}
						}
					}
					/* Recurs indefinitely... the most fun type... */
					else
					{
						while ( $date->mysqlDatetime( FALSE ) < $endDate->mysqlDatetime( FALSE ) )
						{
							$date		= $date->adjust( "next {$nextDay}" );
							$eDate		= static::_determineNewEndDate( $eventStart, $eventEnd, $date, $eDate );

							/* Figure out the next day this occurs on */
							$nextDay	= next( $_repeatDays );

							if ( $nextDay === FALSE )
							{
								reset( $_repeatDays );
								$nextDay	= current( $_repeatDays );
							}

							/* Are we repeating every other week or something? */
							if ( $recurringData['event_repeat_freq'] AND $iteration % $recurringData['event_repeat_freq'] != 0 )
							{
								$iteration++;
								continue;
							}

							$iteration++;

							if ( ( $date->mysqlDatetime( FALSE ) >= $startDate->mysqlDatetime( FALSE ) AND $date->mysqlDatetime( FALSE ) <= $endDate->mysqlDatetime( FALSE ) ) OR
								( $eDate !== NULL AND $eDate->mysqlDatetime( FALSE ) >= $startDate->mysqlDatetime( FALSE ) AND $eDate->mysqlDatetime( FALSE ) <= $endDate->mysqlDatetime( FALSE ) ) OR
								( $eDate !== NULL AND $date->mysqlDatetime( FALSE ) <= $startDate->mysqlDatetime( FALSE ) AND $eDate->mysqlDatetime( FALSE ) >= $endDate->mysqlDatetime( FALSE ) )
							  )
							{
								$results[]	= array( 'startDate' => $date, 'endDate' => $eDate );
							}
						}
					}
				}
			break;

			case 'monthly':
				$keyword	= "months";
			break;

			case 'yearly':
				$keyword	= "years";
			break;
		}

		/* Normal recurrence checks */
		if ( $keyword !== NULL )
		{
			$date			= $eventStart;
			$eDate			= $eventEnd;

			/* Now figure out the period to loop over */
			$intervalKeyword	= mb_strtoupper( mb_substr( $keyword, 0, 1 ) );
			$period				= NULL;

			/* Perform some shortcuts to save resources if at all possible.... */

			/* If this is a daily recurring event that occurs every day, fast forward the start date to the beginning of our period */
			if ( $keyword == 'days' AND $startDate > $date )
			{
				if ( $recurringData['event_repeat_freq'] == 1 AND !$recurringData['repeat_end_occurrences'] )
				{
					$date	= clone $startDate
						->setTimezone( $date->getTimezone() )
						->setTime( $date->format('H'), $date->format( 'i' ), $date->format('s') );

					$eDate	= static::_determineNewEndDate( $eventStart, $eventEnd, $date, $eDate );
				}
				elseif ( !$recurringData['repeat_end_occurrences'] AND !$recurringData['repeat_end_date'] )
				{
					/* So this is an uber complex formula Rikki came up with...but it works like so:
						- Figure out number of days between the start of the period we're viewing and the original event
						- Figure out the modulus (remainder) of the days compared to the frequency
						- Subtract that from the start of the period (so if remainder is 0, we stay on the start of the period)
						- Then we can work forwards from there at the frequency interval. If start was year 1778, we can effectively skip forward to within a few days of our period more efficiently this way, rather than truly starting in 1778 and skipping forward 4 days at a time.
					 */
					$diff	= $startDate->diff( $date );
					$method	= $diff->invert ? 'sub' : 'add';
					$days	= $diff->days % $recurringData['event_repeat_freq'];

					$date	= ( clone $startDate )->sub( new \DateInterval( 'P' . $days . 'D' ) )
						->setTimezone( $date->getTimezone() )
						->setTime( $date->format('H'), $date->format( 'i' ), $date->format('s') );

					$eDate		= static::_determineNewEndDate( $eventStart, $eventEnd, $date, $eDate );
				}
			}

			/* If this is a weekly recurring event that occurs every week, fast forward the start date to the first day of the week starting at the beginning of our period (e.g. if the event started on Monday, fast forward to the first Monday from the period start date onwards) */
			if ( $keyword == 'weeks' AND $startDate > $date )
			{
				if ( $recurringData['event_repeat_freq'] == 1 AND !$recurringData['repeat_end_occurrences'] )
				{
					/* First, get information about the date the event started */
					$firstOccurrence = $date->getDateInformation( $date->getTimestamp() );

					/* Get information about the first date we are checking */
					$startDateInfo = $startDate->getDateInformation( $startDate->getTimestamp() );

					if ( $firstOccurrence['wday'] != $startDateInfo['wday'] )
					{
						$difference	= $firstOccurrence['wday'] - $startDateInfo['wday'];
						$method		= ( $difference > 0 ) ? 'add' : 'sub';
						$date		= ( clone $startDate )
							->$method( new \DateInterval( 'P' . abs( $difference ) . 'D' ) )
							->setTimezone( $date->getTimezone() )
							->setTime( $date->format('H'), $date->format( 'i' ), $date->format('s') );

						$eDate		= static::_determineNewEndDate( $eventStart, $eventEnd, $date, $eDate );
					}
					else
					{
						$date	= ( clone $startDate )
							->setTimezone( $date->getTimezone() )
							->setTime( $date->format('H'), $date->format( 'i' ), $date->format('s') );
						$diff	= $eventStart->diff( $date );
						$method	= $diff->invert ? 'sub' : 'add';

						$eDate		= static::_determineNewEndDate( $eventStart, $eventEnd, $date, $eDate );
					}
				}
				elseif ( !$recurringData['repeat_end_occurrences'] AND !$recurringData['repeat_end_date'] )
				{
					/* So this is an uber complex formula Rikki came up with...but it works like so:
						- Figure out number of days between the start of the period we're viewing and the original event
						- Figure out the modulus (remainder) of the days compared to the frequency
						- Subtract that from the start of the period (so if remainder is 0, we stay on the start of the period)
						- Then we can work forwards from there at the frequency interval. If start was year 1778, we can effectively skip forward to within a few days of our period more efficiently this way, rather than truly starting in 1778 and skipping forward 4 days at a time.
					 */
					$diff	= $startDate->diff( $date );
					$dayOff	= $diff->days;
					
					/* If the time of day of the start of the viewed interval is earlier than the time of day of the original event, we need to add an extra day */
					$startDateSeconds = ( $startDate->format('H') * 3600 ) + ( $startDate->format('i') * 60 ) + $startDate->format('s');
					$dateSeconds = ( $date->format('H') * 3600 ) + ( $date->format('i') * 60 ) + $date->format('s');
					if ( $startDateSeconds < $dateSeconds )
					{
						$dayOff += 1;
					}

					$days	= $dayOff % ( $recurringData['event_repeat_freq'] * 7 );

					$date	= ( clone $startDate )->sub( new \DateInterval( 'P' . $days . 'D' ) )
						->setTimezone( $date->getTimezone() )
						->setTime( $date->format('H'), $date->format('i'), $date->format('s') );

					$eDate		= static::_determineNewEndDate( $eventStart, $eventEnd, $date, $eDate );
				}
			}

			/* If this is a monthly recurring event that occurs every month, fast forward the start date to the day of the month within our period (e.g. if the event started on June 2, fast forward to the first instance of the 2nd following our period start date) */
			if ( $keyword == 'months' AND $startDate > $date )
			{
				if ( $recurringData['event_repeat_freq'] == 1 AND !$recurringData['repeat_end_occurrences'] )
				{
					$date		= ( clone $startDate )
						->setTimezone( $date->getTimezone() )
						->setTime( $date->format('H'), $date->format( 'i' ), $date->format('s') )
						->setDate( $startDate->format('Y'), $startDate->format('m'), $date->format('d') )
						->sub( new \DateInterval( 'P1M' ) );

					$eDate		= static::_determineNewEndDate( $eventStart, $eventEnd, $date, $eDate );
				}
			}

			/* And similarly short circuit yearly recurring events */
			if ( $keyword == 'years' AND $startDate > $date )
			{
				if ( $recurringData['event_repeat_freq'] == 1 AND !$recurringData['repeat_end_occurrences'] )
				{
					$date		= clone $startDate
						->setTimezone( $date->getTimezone() )
						->setTime( $date->format('H'), $date->format( 'i' ), $date->format('s') )
						->setDate( $startDate->format('Y'), $date->format('m'), $date->format('d') )
						->sub( new \DateInterval( 'P1Y' ) );

					$eDate		= static::_determineNewEndDate( $eventStart, $eventEnd, $date, $eDate );
				}
			}

			/* Add the initial start/end date if it matches */
			$dateInformation = $date->getDateInformation( $date->getTimestamp() ); // This is called to reset any stored cached properties to ensure the values are correct

			if ( ( $date->mysqlDatetime( FALSE ) >= $startDate->mysqlDatetime( FALSE ) AND $date->mysqlDatetime( FALSE ) <= $endDate->mysqlDatetime( FALSE ) ) OR
				( $eDate !== NULL AND $eDate->mysqlDatetime( FALSE ) >= $startDate->mysqlDatetime( FALSE ) AND $eDate->mysqlDatetime( FALSE ) <= $endDate->mysqlDatetime( FALSE ) ) OR
				( $eDate !== NULL AND $date->mysqlDatetime( FALSE ) <= $startDate->mysqlDatetime( FALSE ) AND $eDate->mysqlDatetime( FALSE ) >= $endDate->mysqlDatetime( FALSE ) )
			  )
			{
				$results[]	= array( 'startDate' => \IPS\calendar\Date::getDate( $dateInformation['year'], $dateInformation['mon'], $dateInformation['mday'], $dateInformation['hours'], $dateInformation['minutes'], $dateInformation['seconds'] ), 'endDate' => $eDate );
			}

			/* Do we have an occurrences limit? */
			if ( $period === NULL )
			{
				if ( $recurringData['repeat_end_occurrences'] )
				{
					$period = new \DatePeriod( $date, new \DateInterval( 'P' . $recurringData['event_repeat_freq'] . $intervalKeyword ), $recurringData['repeat_end_occurrences'], \DatePeriod::EXCLUDE_START_DATE );
				}
				/* Do we have an end date for the recurrences? */
				elseif ( $recurringData['repeat_end_date'] )
				{
					$endDateToUse = ( $endDate->mysqlDatetime( FALSE ) < $recurringData['repeat_end_date']->mysqlDatetime( FALSE ) ) ? $endDate : $recurringData['repeat_end_date'];

					$period = new \DatePeriod( $date, new \DateInterval( 'P' . $recurringData['event_repeat_freq'] . $intervalKeyword ), $endDateToUse->add( new \DateInterval( 'P' . $recurringData['event_repeat_freq'] . $intervalKeyword ) ), \DatePeriod::EXCLUDE_START_DATE );
				}
				/* Recurs indefinitely... the most fun type... */
				else
				{
					$period = new \DatePeriod( $date, new \DateInterval( 'P' . $recurringData['event_repeat_freq'] . $intervalKeyword ), $endDate, \DatePeriod::EXCLUDE_START_DATE );
				}
			}

			/* Get our occurrences based on the period we are looping */
			foreach ( $period as $dateOccurrence )
			{
				$dateOccurrence = \IPS\calendar\Date::dateTimeToCalendarDate( $dateOccurrence );

				$thisOccurrence = $dateOccurrence->getDateInformation( $dateOccurrence->getTimestamp() );
				$dateOccurrence = \IPS\calendar\Date::getDate( $thisOccurrence['year'], $thisOccurrence['mon'], $thisOccurrence['mday'], $thisOccurrence['hours'], $thisOccurrence['minutes'], $thisOccurrence['seconds'] );

				$eDate		= static::_determineNewEndDate( $eventStart, $eventEnd, $dateOccurrence, $eDate );

				if ( ( $dateOccurrence->mysqlDatetime( FALSE ) >= $startDate->mysqlDatetime( FALSE ) AND $dateOccurrence->mysqlDatetime( FALSE ) <= $endDate->mysqlDatetime( FALSE ) ) OR
					( $eDate !== NULL AND $eDate->mysqlDatetime( FALSE ) >= $startDate->mysqlDatetime( FALSE ) AND $eDate->mysqlDatetime( FALSE ) <= $endDate->mysqlDatetime( FALSE ) ) OR
					( $eDate !== NULL AND $dateOccurrence->mysqlDatetime( FALSE ) <= $startDate->mysqlDatetime( FALSE ) AND $eDate->mysqlDatetime( FALSE ) >= $endDate->mysqlDatetime( FALSE ) )
				)
				{
					$results[]	= array( 'startDate' => $dateOccurrence, 'endDate' => $eDate );
				}
			}
		}

		return $results;
	}

	/**
	 * Find the next occurrence of an event starting from a specified start point
	 *
	 * @param	\IPS\calendar\Date		$date		Date to start from
	 * @param	string					$type		Type of date to check against (startDate or endDate)
	 * @return	\IPS\calendar\Date|NULL
	 */
	public function nextOccurrence( $date, $type='startDate' )
	{
		/* If the event is not recurring, there is only one occurrence */
		if ( !$this->recurring )
		{
			return ( $type === 'startDate' ) ? $this->_start_date : $this->_end_date;
		}

		/* If we have passed the last recurrence date, return the start or end date */
		if ( $this->recurring_end_date )
		{
			if ( strtotime( $this->recurring_end_date ) < time() )
			{
				return ( $type === 'startDate' ) ? $this->_start_date : $this->_end_date;
			}
		}

		/* This is typically called after findOccurrences() using the same start date, so try the cached array first */
		if ( \count( $this->parsedOccurrences ) )
		{
			foreach( $this->parsedOccurrences as $occurrence )
			{
				if ( $this->_checkDateForOccurrence( $occurrence, $type, $date ) )
				{
					return $occurrence[ $type ];
				}
			}
		}

		/* Get the occurrences over the next year then and try from there. We go back one month to start with to account for the event stream. */
		foreach( $this->findOccurrences( ( clone $date )->adjust( "-1 month" ), ( clone $date )->adjust( "+2 years" ) ) as $occurrence )
		{
			if ( $this->_checkDateForOccurrence( $occurrence, $type, $date ) )
			{
				return $occurrence[ $type ];
			}
		}

		/* No? Then just return NULL */
		return NULL;
	}

	/**
	 * Determine if an event is within our range
	 *
	 * @see	nextOccurrence()
	 * @param	array				$occurrence		Event occurrence instance
	 * @param	string					$type		Type of date to check against (startDate or endDate)
	 * @param	\IPS\calendar\Date	$date			Date we are checking
	 * @return	bool
	 */
	protected function _checkDateForOccurrence( $occurrence, $type, $date )
	{
		/* If we're looking for an end date and we don't have one, return now */
		if ( $type == 'endDate' AND empty( $occurrence['endDate'] ) )
		{
			return FALSE;
		}

		/* If we are checking end date, or if the end date is the same as the start date, then we can simply compare the dates */
		if ( $type == 'endDate' OR !$occurrence['endDate'] OR $occurrence['startDate']->mysqlDatetime( FALSE ) == $occurrence['endDate']->mysqlDatetime( FALSE ) )
		{
			if ( $occurrence[ $type ] AND $occurrence[ $type ]->mysqlDatetime( FALSE ) >= $date->mysqlDatetime( FALSE ) )
			{
				return TRUE;
			}
		}

		/* Otherwise if this is a ranged event, then what we really want to determine is if the date in question falls within the range or comes after */
		if ( $type == 'startDate' AND ( !$occurrence['endDate'] OR $occurrence['endDate']->mysqlDatetime( FALSE ) > $occurrence['startDate']->mysqlDatetime( FALSE ) ) )
		{
			if ( $occurrence['startDate']->mysqlDatetime( FALSE ) >= $date->mysqlDatetime( FALSE ) )
			{
				return TRUE;
			}
			elseif ( $occurrence['endDate'] AND $date->mysqlDatetime( FALSE ) >= $occurrence['startDate']->mysqlDatetime( FALSE ) AND $date->mysqlDatetime( FALSE ) <= $occurrence['endDate']->mysqlDatetime( FALSE ) )
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Return the last occurrence of a recurring event
	 *
	 * @param	string					$type		Type of date to check against (startDate or endDate)
	 * @return	\IPS\calendar\Date
	 */
	public function lastOccurrence( $type='startDate' )
	{
		/* If the event is not recurring, there is only one occurrence */
		if ( !$this->recurring )
		{
			return ( $type === 'startDate' ) ? $this->_start_date : $this->_end_date;
		}

		/* This is typically called after findOccurrences() using the same start date, so try the cached array first */
		if ( \count( $this->parsedOccurrences ) )
		{
			$last	= end( $this->parsedOccurrences );

			return ( isset( $last[ $type ] ) ) ? $last[ $type ] : NULL;
		}

		/* Get the occurrences over the next year then and try from there */
		$date	= \IPS\calendar\Date::getDate();
		foreach( $this->findOccurrences( $date, $date->adjust( "+2 years" ) ) as $occurrence )
		{
			if ( $occurrence[ $type ] AND $occurrence[ $type ]->mysqlDatetime( FALSE ) >= $date->mysqlDatetime( FALSE ) )
			{
				return $occurrence[ $type ];
			}
		}

		$date	= \IPS\calendar\Date::getDate();
		$occurrences	= $this->findOccurrences( $date->adjust( "-2 years" ), $date );
		$occurrence		= array_pop( $occurrences );

		if ( $occurrence and $occurrence[ $type ] )
		{
			return $occurrence[ $type ];
		}

		/* Fall back to the defined start date */
		return $this->_start_date;
	}

	/**
	 * @brief	RSVP attendees
	 */
	protected $attendees	= NULL;

	/**
	 * Get the RSVP attendees
	 *
	 * @param	int|NULL	$type	Type of RSVP attendees to count, or NULL for all
	 * @param	int|NULL	$limit	Maximum number of attendees to return, or NULL for all. Only available when $type is specified.
	 * @return	array
	 * @throws	\BadMethodCallException
	 * @throws	\InvalidArgumentException
	 */
	public function attendees( $type=NULL, $limit=NULL )
	{
		/* RSVP enabled? */
		if ( !$this->rsvp )
		{
			throw new \BadMethodCallException;
		}

		/* You can only limit results if retrieving a specific type */
		if ( $type === NULL AND $limit !== NULL )
		{
			throw new \InvalidArgumentException;
		}

		/* Do we already have attendee list cached? */
		if ( $this->attendees === NULL )
		{
			/* Fetch RSVP data and pass to template */
			$this->attendees	= array( 0 => array(), 1 => array(), 2 => array() );

			foreach( \IPS\Db::i()->select( '*', 'calendar_event_rsvp', array( "rsvp_event_id=?", $this->id ) )->join( 'core_members', 'calendar_event_rsvp.rsvp_member_id=core_members.member_id' ) as $attendee )
			{
				$this->attendees[ $attendee['rsvp_response'] ][ $attendee['rsvp_member_id'] ]	= \IPS\Member::constructFromData( $attendee );
			}
		}

		/* Return requested type and limit */
		if ( $type !== NULL )
		{
			$results	= $this->attendees[ $type ];

			if ( $limit !== NULL )
			{
				return \array_slice( $results, 0, $limit, FALSE );
			}
			else
			{
				return $results;
			}
		}

		return $this->attendees;
	}

	/**
	 * Get the RSVP attendee count
	 *
	 * @param	int|NULL	$type	Type of RSVP attendees to count, or NULL for all
	 * @return	int
	 * @throws	\BadMethodCallException
	 */
	public function attendeeCount( $type=NULL )
	{
		$attendees	= $this->attendees( $type );

		if ( $type !== NULL )
		{
			return \count( $attendees );
		}
		else
		{
			return ( \count( $attendees[0] ) + \count( $attendees[1] ) + \count( $attendees[2] ) );
		}
	}

	/**
	 * Get the start date for display
	 *
	 * @return	\IPS\calendar\Date
	 */
	public function get__start_date()
	{		
		if ( $this->dateObjects['start'] === NULL )
		{
			$this->dateObjects['start']	= \IPS\calendar\Date::parseTime( $this->start_date, $this->all_day ? FALSE : TRUE );
		}
		
		return $this->dateObjects['start'];
	}

	/**
	 * Get the end date for display
	 *
	 * @return	\IPS\calendar\Date|NULL
	 */
	public function get__end_date()
	{
		if ( $this->dateObjects['end'] === NULL AND $this->end_date )
		{
			$this->dateObjects['end']	= \IPS\calendar\Date::parseTime( $this->end_date, $this->all_day ? FALSE : TRUE );
		}

		return $this->dateObjects['end'];
	}

	/**
	 * Get the live topic, or NULL if one not available
	 *
	 * @return \IPS\cloud\LiveTopic|null
	 */
	public function get__livetopic_id(): ?\IPS\cloud\LiveTopic
	{
		if ( ! \IPS\Application::appIsEnabled('cloud') )
		{
			return NULL;
		}

		try
		{
			return \IPS\cloud\LiveTopic::load( $this->livetopic_id );
		}
		catch( \Exception )
		{
			return NULL;
		}
	}
	
	/**
	 * Get a string with the event date/times in the timezone of the user who created the event
	 * Used in outgoing emails
	 *
	 * @param	\IPS\Lang	$language	The language to use
	 * @return	string
	 */
	public function fixedDateTimeDescription( \IPS\Lang $language )
	{
		/* Init */
		$return = '';

		try
		{
			$authorTimezone = new \DateTimeZone( $this->author()->timezone );
		}
		catch ( \Exception $e )
		{
			$authorTimezone = new \DateTimeZone('UTC');
		}
		
		/* Start */
		$start = ( $this->all_day ) ? $this->_start_date : $this->_start_date->setTimezone( $authorTimezone );
		$startOffset = $authorTimezone->getOffset( $start ) / 3600;
		$return .= $start->calendarDate( $language );
		if ( !$this->all_day )
		{
			$return .= ' '  . $start->localeTime( FALSE, TRUE, $language );

			if ( $startOffset )
			{
				$return .= ' (GMT' . ( ( $startOffset > 0 ) ? '+' : '' ) . $startOffset . ')';
			}
			else
			{
				$return .= ' (GMT)';
			}
		}
		
		/* End */
		if ( $this->_end_date )
		{
			$return .= ' - ';
			$end = ( $this->all_day ) ? $this->_end_date : $this->_end_date->setTimezone( $authorTimezone );
			$endOffset = $authorTimezone->getOffset( $end ) / 3600; // Unlikely, but if the event spans the DST switch, the end offset could be different to the start
			$return .= $end->calendarDate( $language );
			if ( !$this->all_day )
			{
				$return .= ' '  . $end->localeTime( FALSE, TRUE, $language );

				if ( $endOffset )
				{
					$return .= ' (GMT' . ( ( $endOffset > 0 ) ? '+' : '' ) . $endOffset . ')';
				}
				else
				{
					$return .= ' (GMT)';
				}
			}
		}
		
		/* Return */
		return $return;
	}

	/**
	 * @brief	Cached URLs
	 */
	protected $_url	= array();
	
	/**
	 * @brief	URL Base
	 */
	public static $urlBase = 'app=calendar&module=calendar&controller=event&id=';
	
	/**
	 * @brief	URL Base
	 */
	public static $urlTemplate = 'calendar_event';
	
	/**
	 * @brief	SEO Title Column
	 */
	public static $seoTitleColumn = 'title_seo';
	
	/**
	 * Get URL for last comment page
	 *
	 * @return	\IPS\Http\Url
	 */
	public function lastCommentPageUrl()
	{
		return parent::lastCommentPageUrl()->setQueryString( 'tab', 'comments' );
	}
	
	/**
	 * Get URL for last review page
	 *
	 * @return	\IPS\Http\Url
	 */
	public function lastReviewPageUrl()
	{
		return parent::lastCommentPageUrl()->setQueryString( 'tab', 'reviews' );
	}

	/**
	 * Get template for content tables
	 *
	 * @return	callable
	 */
	public static function contentTableTemplate()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'calendar.css', 'calendar', 'front' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'calendar_responsive.css', 'calendar', 'front' ) );
		return array( \IPS\Theme::i()->getTemplate( 'global', 'calendar', 'front' ), 'rows' );
	}

	/**
	 * HTML to manage an item's follows 
	 *
	 * @return	callable
	 */
	public static function manageFollowRows()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'calendar.css', 'calendar' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'calendar_responsive.css', 'calendar' ) );
		return array( \IPS\Theme::i()->getTemplate( 'global', 'calendar', 'front' ), 'manageFollowRow' );
	}
	
	/**
	 * Are comments supported by this class?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for or NULL to not check permission
	 * @param	\IPS\Node\Model|NULL	$container	The container to check in, or NULL for any container
	 * @return	bool
	 */
	public static function supportsComments( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		if ( $container !== NULL )
		{
			return parent::supportsComments() and $container->allow_comments AND ( !$member or $container->can( 'read', $member ) );
		}
		else
		{
			return parent::supportsComments() and ( !$member or \IPS\calendar\Calendar::countWhere( 'read', $member, array( 'cal_allow_comments=1' ) ) );
		}
	}
	
	/**
	 * Are reviews supported by this class?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for or NULL to not check permission
	 * @param	\IPS\Node\Model|NULL	$container	The container to check in, or NULL for any container
	 * @return	bool
	 */
	public static function supportsReviews( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		if ( $container !== NULL )
		{
			return parent::supportsReviews() and $container->allow_reviews AND ( !$member or $container->can( 'read', $member ) );
		}
		else
		{
			return parent::supportsReviews() and ( !$member or \IPS\calendar\Calendar::countWhere( 'read', $member, array( 'cal_allow_reviews=1' ) ) );
		}
	}

	/**
	 * Get available comment/review tabs
	 *
	 * @return	array
	 */
	public function commentReviewTabs()
	{
		$tabs = array();
		if ( $this->container()->allow_reviews )
		{
			$tabs['reviews'] = \IPS\Member::loggedIn()->language()->addToStack( 'event_review_count', TRUE, array( 'pluralize' => array( $this->mapped('num_reviews') ) ) );
		}
		if ( $this->container()->allow_comments )
		{
			$tabs['comments'] = \IPS\Member::loggedIn()->language()->addToStack( 'event_comment_count', TRUE, array( 'pluralize' => array( $this->mapped('num_comments') ) ) );
		}

		return $tabs;
	}
	
	/**
	 * Get comment/review output
	 *
	 * @param	string	$tab	Active tab
	 * @return	string
	 */
	public function commentReviews( $tab )
	{
		if ( $tab === 'reviews' and $this->container()->allow_reviews )
		{
			return \IPS\Theme::i()->getTemplate('view')->reviews( $this );
		}
		elseif ( $tab === 'comments' and $this->container()->allow_comments )
		{
			return \IPS\Theme::i()->getTemplate('view')->comments( $this );
		}
		
		return '';
	}
	
	/**
	 * Should new items be moderated?
	 *
	 * @param	\IPS\Member		$member							The member posting
	 * @param	\IPS\Node\Model	$container						The container
	 * @param	bool			$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public static function moderateNewItems( \IPS\Member $member, \IPS\Node\Model $container = NULL, $considerPostBeforeRegistering = FALSE )
	{
		if ( $container and $container->moderate and !$member->group['g_avoid_q'] )
		{
			return !static::modPermission( 'approve', $member, $container );
		}
		
		return parent::moderateNewItems( $member, $container, $considerPostBeforeRegistering );
	}
	
	/**
	 * Should new comments be moderated?
	 *
	 * @param	\IPS\Member	$member							The member posting
	 * @param	bool		$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public function moderateNewComments( \IPS\Member $member, $considerPostBeforeRegistering = FALSE )
	{
		$commentClass = static::$commentClass;
		return ( $this->container()->comment_moderate and !$member->group['g_avoid_q'] ) or parent::moderateNewComments( $member, $considerPostBeforeRegistering );
	}
	
	/**
	 * Should new reviews be moderated?
	 *
	 * @param	\IPS\Member	$member							The member posting
	 * @param	bool		$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public function moderateNewReviews( \IPS\Member $member, $considerPostBeforeRegistering = FALSE )
	{
		$reviewClass = static::$reviewClass;
		return ( $this->container()->review_moderate and !$member->group['g_avoid_q'] ) or parent::moderateNewReviews( $member, $considerPostBeforeRegistering );
	}

	/**
	 * Can view users who have RSVP'd?
	 *
	 * @param	\IPS\Member|NULL	$member		The member to check or NULL for currently logged in member
	 * @return	bool
	 */
	public function canViewRsvps( \IPS\Member $member = NULL )
	{
		return $this->can( 'rsvp', $member );
	}
	
	/**
	 * Get elements for add/edit form
	 *
	 * @param	\IPS\Content\Item|NULL	$item		The current item if editing or NULL if creating
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum) ID, if appropriate
	 * @param	bool					$isCopy		TRUE if we are copying $item rather than editing it
	 * @return	array
	 */
	public static function formElements( $item=NULL, \IPS\Node\Model $container=NULL, $isCopy=FALSE )
	{
		$newDefault	= date( 'Y-m-d' );
		$allDay		= FALSE;

		if ( \IPS\Request::i()->y AND \IPS\Request::i()->m AND \IPS\Request::i()->d )
		{
			$newDefault	= (int) \IPS\Request::i()->y . '-' . (int) \IPS\Request::i()->m . '-' . (int) \IPS\Request::i()->d;
			$allDay		= TRUE;
		}

		/* Adjust start and end time/date object timezones if needed */
		if ( $item )
		{
			$startDateObj	= \IPS\calendar\Date::parseTime( $item->start_date, $item->all_day ? FALSE : TRUE );
			$endDateObj		= $item->end_date ? \IPS\calendar\Date::parseTime( $item->end_date, $item->all_day ? FALSE : TRUE ) : NULL;

			if ( $item->timezone AND !$item->all_day )
			{
				if ( preg_match( "/-?([0-9]+?)(\.[0-9]+)?/", $item->timezone ) )
				{
					$timeZone = ( $item->timezone < 0 ) ? $item->timezone : '+' . $item->timezone;
				}
				else
				{
					$timeZone = $item->timezone;
				}

				if ( mb_strpos( $timeZone, '.5' ) !== FALSE )
				{
					$timeZone = mb_substr( $timeZone, 0, 1 ) . '0' . mb_substr( $timeZone, 1, 1 ) . '30';
				}

				if ( mb_strpos( $timeZone, '.75' ) !== FALSE )
				{
					$timeZone = mb_substr( $timeZone, 0, 1 ) . '0' . mb_substr( $timeZone, 1, 1 ) . '45';
				}

				$startDateObj->setTimezone( new \DateTimeZone( $timeZone ) );

				if ( $endDateObj )
				{
					$endDateObj->setTimezone( new \DateTimeZone( $timeZone ) );
				}
			}
		}

		/* We are using a custom template to provide an optimal experience for the user instead of a basic linear top-down form elements for the date fields */
		$dateValues	= array(
			'single_day'				=> $item ? ( !$item->end_date or ( $startDateObj->format('Y-m-d') === $endDateObj->format('Y-m-d') ) ) : TRUE,
			'start_date'				=> $item ? $startDateObj : \IPS\calendar\Date::parseTime( $newDefault ),
			'end_date'					=> ( $item AND $item->end_date ) ? $endDateObj : NULL,
			'all_day'					=> $item ? $item->all_day : $allDay,
			'event_repeat'				=> $item ? $item->recurring : FALSE,
			'event_timezone'			=> ( $item AND $item->timezone ) ? $item->timezone : ( \IPS\Member::loggedIn()->timezone ?: 'GMT' ),
			'start_time'				=> $item ? $startDateObj->format( 'H:i' ) : \IPS\calendar\Date::parseTime( $newDefault )->format( 'H:i' ),
			'end_time'					=> ( $item AND $item->end_date ) ? $endDateObj->format( 'H:i' ) : NULL,
			'event_repeats'				=> NULL,		/* Daily, weekly, monthly, yearly */
			'event_repeat_freq'			=> NULL,		/* Repeat every 1 day, 2 days, 3 days, etc. */
			'repeat_end_occurrences'	=> NULL,		/* Ends after x occurrences */
			'repeat_end_date'			=> NULL,		/* Ends on x date (which is separate from the event end date - e.g. jan 9 2014 3pm to jan 10 2014 3pm, repeat annually until jan 9 2019) */
		);

		/* If we're copying an event, reset the date & time fields */
		if ( \IPS\Request::i()->do === 'copy' )
		{
			$dateValues['start_date'] = NULL;
			$dateValues['start_time'] = NULL;
			$dateValues['end_date'] = NULL;
			$dateValues['end_time'] = NULL;
		}

		foreach( \IPS\calendar\Date::getDayNames() as $day )
		{
			$dateValues['repeat_freq_on_' . $day['ical'] ]	= NULL;	/* If repeating weekly, this is the days of the week as checkboxes (e.g. repeat every wed, fri and sat) */
		}

		/* Figure out the recurrence data if we are editing */
		if ( $item AND $item->recurring )
		{
			try
			{
				$dateValues	= array_merge( $dateValues, \IPS\calendar\Icalendar\ICSParser::parseRrule( $item->recurring, 'UTC' ) );
			}
			catch( \InvalidArgumentException $e ){}
		}

		$return['dates']		= new \IPS\Helpers\Form\Custom( 'event_dates', $dateValues, FALSE, array( 'getHtml' => function( $element )
		{
			return \IPS\Theme::i()->getTemplate( 'submit' )->datesForm( $element->name, $element->value, \IPS\calendar\Date::getTimezones(), $element->error );
		} ), function( $val )
			{
				/* Anything but Chrome, basically, falls back to a text input and submitter might submit 22.00 instead of 22:00 */
				if ( isset( $val['start_time'] ) )
				{
					$val['start_time']	= str_replace( '.', ':', $val['start_time'] );
				}

				/* If "single day event" was unchecked, the "no end time" checkbox is hidden so we should ignore it */
				if ( !isset( $val['single_day'] ) OR !$val['single_day'] )
				{
					unset( $val['no_end_time'] );
				}

				if ( isset( $val['no_end_time'] ) AND $val['no_end_time'] AND ( isset( $val['end_time'] ) OR isset( $val['end_date'] ) ) )
				{
					unset( $val['end_time'], $val['end_date'] );
				}

				if ( isset( $val['end_time'] ) )
				{
					$val['end_time']	= str_replace( '.', ':', $val['end_time'] );
				}

				try
				{
					$start	= \IPS\calendar\Date::createFromForm( $val['start_date'], ( ( !isset( $val['all_day'] ) OR !$val['all_day'] ) ? $val['start_time'] : NULL ), ( isset( $val['all_day'] ) AND $val['all_day'] ) ? 'UTC' : $val['event_timezone'] );
				}
				catch( \Exception $e )
				{
					throw new \DomainException( "invalid_start_date" );
				}

				$end	= null;

				if ( ( isset( $val['end_date'] ) AND $val['end_date'] ) OR ( isset( $val['end_time'] ) AND $val['end_time'] ) )
				{
					if ( !isset( $val['single_day'] ) OR !$val['single_day'] )
					{
						if ( !isset( $val['all_day'] ) OR !$val['all_day'] OR $val['end_date'] != $val['start_date'] )
						{
							try
							{
								$end	= \IPS\calendar\Date::createFromForm( $val['end_date'], ( ( !isset( $val['all_day'] ) OR !$val['all_day'] ) ? $val['end_time'] : NULL ), ( isset( $val['all_day'] ) AND $val['all_day'] ) ? 'UTC' : $val['event_timezone'] );
							}
							catch( \Exception $e )
							{
								throw new \DomainException( "invalid_end_date" );
							}
						}
					}
					elseif ( isset( $val['end_time'] ) )
					{
						try
						{
							$end = \IPS\calendar\Date::createFromForm( $val['start_date'], ( ( !isset( $val['all_day'] ) OR !$val['all_day'] ) ? $val['end_time'] : NULL ), ( isset( $val['all_day'] ) AND $val['all_day'] ) ? 'UTC' : $val['event_timezone'] );
						}
						catch( \Exception $e )
						{
							throw new \DomainException( "invalid_end_date" );
						}
					}
				}
				
				/* Check the dates */
				if ( $start === NULL )
				{
					throw new \DomainException( "invalid_start_date" );
				}

				try
				{
					\IPS\calendar\Date::parseTime( $start->format( 'Y-m-d H:i' ), FALSE );
				}
				catch( \InvalidArgumentException $e )
				{
					throw new \DomainException( "invalid_start_date" );
				}

				if ( $end )
				{
					if ( $end < $start )
					{
						throw new \DomainException( 'end_date_before_start' );
					}

					try
					{
						\IPS\calendar\Date::parseTime( $end->format( 'Y-m-d H:i' ), FALSE );
					}
					catch( \InvalidArgumentException $e )
					{
						throw new \DomainException( "invalid_end_date" );
					}
				}
				
				/* Is this a recurring event? If so we have some extra checks. */
				if ( isset( $val['event_repeat'] ) and $val['event_repeat'] and $end )
				{
					/* Firstly, if we range for more than one day we cannot recur daily. Same for weekly, monthly and yearly. */
					$dateDiff = $start->diff( $end );
				
					switch ( $val['event_repeats'] )
					{						
						case 'daily':
							if ( $dateDiff->d > 1 )
							{
								throw new \DomainException( "invalid_recurrence" );
							}
							break;
			
						case 'weekly':
							if ( $dateDiff->d > 7 )
							{
								throw new \DomainException( "invalid_recurrence" );
							}
							break;
			
						case 'monthly':
							if ( $dateDiff->m > 1 )
							{
								throw new \DomainException( "invalid_recurrence" );
							}
							break;
			
						case 'yearly':
							if ( $dateDiff->y > 1 )
							{
								throw new \DomainException( "invalid_recurrence" );
							}
							break;
					}
				}
				
			}, NULL, NULL, 'event_dates' );

		/* Init */
		if ( !$container and !\IPS\calendar\Calendar::theOnlyNode() )
		{
			$return['calendar']	= new \IPS\Helpers\Form\Node( static::$formLangPrefix . 'container', $container ? $container->id : ( isset( \IPS\Request::i()->calendar ) ? \IPS\Request::i()->calendar : NULL ), TRUE, array(
				'url'					=> \IPS\Http\Url::internal( 'app=calendar&module=calendar&controller=submit', 'front', 'calendar_submit' ),
				'class'					=> 'IPS\calendar\Calendar',
				'permissionCheck'		=> 'add',
				'togglePerm'			=> 'askrsvp',
				'toggleIds'				=> array( 'event_rsvp' ),
			) );
		}

		/* Get default elements */
		$return = array_merge( $return, parent::formElements( $item, $container ) );

		/* Event description */
		$return['description']	= new \IPS\Helpers\Form\Editor( 'event_content', $item ? $item->content : NULL, TRUE, array( 'app' => 'calendar', 'key' => 'Calendar', 'autoSaveKey' => ( $item === NULL )  ? 'calendar-event' : "calendar-event-{$item->id}", 'attachIds' => ( $item === NULL ? NULL : array( $item->id, NULL, 'description' ) ) ) );

		/* Edit Log Fields need to be under the editor */
		$editReason = NULL;
		if ( isset( $return['edit_reason']) )
		{
			$editReason = $return['edit_reason'];
			unset( $return['edit_reason'] );
			$return['edit_reason'] = $editReason;
		}

		$logEdit = NULL;
		if ( isset( $return['log_edit']) )
		{
			$logEdit = $return['log_edit'];
			unset( $return['log_edit'] );
			$return['log_edit'] = $logEdit;
		}

		/* Cover photo and location */
		$return['header']		= new \IPS\Helpers\Form\Upload( 'event_cover_photo', ( $item AND $item->cover_photo ) ? \IPS\File::get( 'calendar_Events', $item->cover_photo ) : NULL, FALSE, array( 'image' => TRUE, 'allowStockPhotos' => TRUE, 'storageExtension' => 'calendar_Events', 'canBeModerated' => TRUE, 'retainDeleted' => $isCopy ) );

		$return['event_online'] = new \IPS\Helpers\Form\YesNo( 'event_online', ( $item ) ? $item->online : FALSE, FALSE, array( 'togglesOff' => array( 'venue', 'event_location' ), 'togglesOn' => array( 'event_url' ) ) );

		if ( \IPS\Settings::i()->calendar_venues_enabled )
		{
			$roots = \IPS\calendar\Venue::roots();

			$return['venue']	= new \IPS\Helpers\Form\Node( static::$formLangPrefix . 'venue', ( $item ) ? $item->venue ?: 0 : ( isset( \IPS\Request::i()->venue ) ? \IPS\Request::i()->venue : ( \count( $roots ) ? NULL : 0 ) ), FALSE, array(
				'url'					=> \IPS\Http\Url::internal( 'app=calendar&module=calendar&controller=submit', 'front', 'calendar_submit' ),
				'class'					=> 'IPS\calendar\Venue',
				'zeroVal' 				=> 'venues_not_listed',
				'zeroValTogglesOn' 		=> array( 'event_location', 'event_new_venue' ),
				'permissionCheck'		=> 'add',
			), NULL,NULL, NULL, 'venue' );
		}

		$return['location']	= new \IPS\Helpers\Form\Address( 'event_location', ( $item AND $item->location ) ? \IPS\GeoLocation::buildFromJson( $item->location ) : NULL, FALSE, array( 'minimize' => ( $item AND $item->location ) ? FALSE : TRUE, 'requireFullAddress' => FALSE, 'preselectCountry' => FALSE ), NULL, NULL, NULL, 'event_location' );

		$return['url']	= new \IPS\Helpers\Form\Url( 'event_url', ( $item AND $item->url ) ? $item->url : NULL, FALSE, array(), NULL, NULL, NULL, 'event_url' );

		/* Save location as a new venue? */
		if ( \IPS\Settings::i()->calendar_venues_enabled and \IPS\Member::loggedIn()->hasAcpRestriction( 'calendar', 'calendars', 'venues_manage' ) )
		{
			$return['save_new_venue'] = new \IPS\Helpers\Form\YesNo( 'event_new_venue', NULL, FALSE, array( 'togglesOn' => array( 'venue_title', 'venue_description' ) ), NULL, NULL, NULL, 'event_new_venue' );
			$return['venue_title'] = new \IPS\Helpers\Form\Translatable( 'venue_title', NULL, TRUE, array( 'app' => 'calendar', 'key' => NULL ), NULL, NULL, NULL, 'venue_title' );
			$return['venue_description'] = new \IPS\Helpers\Form\Translatable( 'venue_description', NULL, FALSE, array( 'app' => 'calendar', 'key' => NULL, 'editor' => array( 'app' => 'calendar', 'key' => 'Venue', 'autoSaveKey' => "calendar-new-venue", 'attachIds' => NULL ) ), NULL, NULL, NULL, 'venue_description' );
		}

		/* Gallery album association */
		if ( \IPS\Application::appIsEnabled( 'gallery' ) )
		{
			$return['album']	= new \IPS\Helpers\Form\Node( 'event_album', ( $item AND $item->album ) ? $item->album : NULL, FALSE, array( 
				'url'					=> \IPS\Http\Url::internal( 'app=calendar&module=calendar&controller=submit', 'front', 'calendar_submit' ),
				'class'					=> 'IPS\gallery\Album',
				'permissionCheck'		=> 'add',
			) );
		}

		/* Event - request RSVP */
		if ( $container and $container->can( 'askrsvp', $item ? $item->author() : \IPS\Member::loggedIn() ) )
		{
			$return['rsvp']			= new \IPS\Helpers\Form\YesNo( 'event_rsvp', $item ? $item->rsvp : NULL, FALSE, array( 'togglesOn' => array( 'event_rsvp_limit' ) ), NULL, NULL, NULL, 'event_rsvp' );
			$return['rsvplimit']	= new \IPS\Helpers\Form\Number( 'event_rsvp_limit', $item ? $item->rsvp_limit : -1, FALSE, array( 'unlimited' => -1 ), NULL, NULL, NULL, 'event_rsvp_limit' );
		}

		/* If the calendar does not allow comments or reviews, disable the auto follow option */
		if ( $container AND !$container->allow_comments AND !$container->allow_reviews )
		{
			unset( $return['auto_follow'] );
		}

		return $return;
	}

	/**
	 * Process created object BEFORE the object has been created
	 *
	 * @param	array	$values	Values from form
	 * @return	void
	 */
	protected function processBeforeCreate( $values )
	{
		/* Post key is very much needed because...bacon */
		$this->post_key		= isset( $values['post_key'] ) ? $values['post_key'] : md5( mt_rand() );

		parent::processBeforeCreate( $values );
	}

	/**
	 * Process create/edit form
	 *
	 * @param	array				$values	Values from form
	 * @return	void
	 */
	public function processForm( $values )
	{		
		/* Anything but Chrome, basically, falls back to a text input and submitter might submit 22.00 instead of 22:00 */
		if ( isset( $values['event_dates']['start_time'] ) )
		{
			$values['event_dates']['start_time']	= str_replace( '.', ':', $values['event_dates']['start_time'] );
		}

		/* If "single day event" was unchecked, the "no end time" checkbox is hidden so we should ignore it */
		if ( !isset( $values['event_dates']['single_day'] ) OR !$values['event_dates']['single_day'] )
		{
			unset( $values['event_dates']['no_end_time'] );
		}

		/* If we checked "no end time" then ignore any supplied end time */
		if ( isset( $values['event_dates']['no_end_time'] ) AND $values['event_dates']['no_end_time'] AND isset( $values['event_dates']['end_time'] ) )
		{
			unset( $values['event_dates']['end_time'] );
		}

		if ( isset( $values['event_dates']['end_time'] ) )
		{
			$values['event_dates']['end_time']	= str_replace( '.', ':', $values['event_dates']['end_time'] );
		}

		/* Calendar */
		if ( isset( $values[ static::$formLangPrefix . 'container' ] ) )
		{
			$this->calendar_id		= ( $values[ static::$formLangPrefix . 'container' ] instanceof \IPS\Node\Model ) ? $values[ static::$formLangPrefix . 'container' ]->_id : \intval( $values[ static::$formLangPrefix . 'container' ] );
		}
		
		/* Start and end dates */
		$this->start_date	= \IPS\calendar\Date::createFromForm( $values['event_dates']['start_date'], ( ( !isset( $values['event_dates']['all_day'] ) OR !$values['event_dates']['all_day'] ) ? $values['event_dates']['start_time'] : NULL ), ( isset( $values['event_dates']['all_day'] ) AND $values['event_dates']['all_day'] ) ? 'UTC' : $values['event_dates']['event_timezone'] )->format( 'Y-m-d H:i' );
		$this->end_date		= null;

		/* Clear clear fields for non-selected items */
		switch( $values['event_dates']['repeat_end'] )
		{
			case 'never':
					unset( $values['event_dates']['repeat_end_occurrences'], $values['event_dates']['repeat_end_date'] );
				break;
			case 'after':
					unset( $values['event_dates']['repeat_end_date'] );
				break;
			case 'date':
					unset( $values['event_dates']['repeat_end_occurrences'] );
				break;
		}

		if ( isset( $values['event_dates']['end_date'] ) AND $values['event_dates']['end_date'] )
		{
			if ( !isset( $values['event_dates']['single_day'] ) OR !$values['event_dates']['single_day'] )
			{
				if ( !isset( $values['event_dates']['all_day'] ) OR !$values['event_dates']['all_day'] OR $values['event_dates']['end_date'] != $values['event_dates']['start_date'] )
				{
					$this->end_date	= \IPS\calendar\Date::createFromForm( $values['event_dates']['end_date'], ( ( !isset( $values['event_dates']['all_day'] ) OR !$values['event_dates']['all_day'] ) ? $values['event_dates']['end_time'] : NULL ), ( isset( $values['event_dates']['all_day'] ) AND $values['event_dates']['all_day'] ) ? 'UTC' : $values['event_dates']['event_timezone'] )->format( 'Y-m-d H:i' );
				}
			}
			elseif ( ( !isset( $values['event_dates']['all_day'] ) OR !$values['event_dates']['all_day'] ) AND isset( $values['event_dates']['end_time'] ) AND $values['event_dates']['end_time'] != $values['event_dates']['start_time'] )
			{
				$this->end_date	= \IPS\calendar\Date::createFromForm( $values['event_dates']['start_date'], $values['event_dates']['end_time'], ( isset( $values['event_dates']['all_day'] ) AND $values['event_dates']['all_day'] ) ? 'UTC' : $values['event_dates']['event_timezone'] )->format( 'Y-m-d H:i' );
			}
		}
		elseif ( ( !isset( $values['event_dates']['all_day'] ) OR !$values['event_dates']['all_day'] ) AND isset( $values['event_dates']['end_time'] ) AND $values['event_dates']['end_time'] != $values['event_dates']['start_time'] )
		{
			$this->end_date	= \IPS\calendar\Date::createFromForm( $values['event_dates']['start_date'], $values['event_dates']['end_time'], ( isset( $values['event_dates']['all_day'] ) AND $values['event_dates']['all_day'] ) ? 'UTC' : $values['event_dates']['event_timezone'] )->format( 'Y-m-d H:i' );
		}

		/* Store time zone information */
		if ( isset( $values['event_dates']['event_timezone'] ) )
		{
			$this->timezone	= $values['event_dates']['event_timezone'];
		}

		/* Now set all day flag */
		$this->all_day		= (int) ( isset( $values['event_dates']['all_day'] ) AND $values['event_dates']['all_day'] );

		/* Need to set recurring values */
		$this->recurring	= \IPS\calendar\Icalendar\ICSParser::buildRrule( $values['event_dates'] );

		/* Store the last recurrence date, if appropriate */
		if ( $this->recurring )
		{
			$recurrenceRule = \IPS\calendar\Icalendar\ICSParser::parseRrule( $this->recurring, 'UTC', $this->_start_date );

			/* Wipe out invalid "recur on" values for weekly events */
			if ( $this->end_date AND isset( $recurrenceRule['event_repeat'] ) AND $recurrenceRule['event_repeat'] AND $recurrenceRule['event_repeat_freq'] == 'weekly' )
			{
				$dateDiff = $this->start_date->diff( $this->end_date );

				if ( $dateDiff->d > 1 )
				{
					foreach( \IPS\calendar\Date::getDayNames() as $day )
					{
						if ( isset( $recurrenceRule['repeat_freq_on_' . $day['ical'] ] ) )
						{
							unset( $recurrenceRule['repeat_freq_on_' . $day['ical'] ] );
						}
					}
				}
			}

			/* Please be easy. Please be easy. */
			if ( $recurrenceRule['repeat_end_date'] )
			{
				$this->recurring_end_date = $recurrenceRule['repeat_end_date']->format( 'Y-m-d H:i' );
			}
			/* Ok, then check if occurrences limit is present... */
			elseif ( $recurrenceRule['repeat_end_occurrences'] )
			{
				switch( $recurrenceRule['event_repeats'] )
				{
					case 'daily':
						$keyword	= "days";
					break;

					case 'weekly':
						$keyword	= 'weeks';
					break;

					case 'monthly':
						$keyword	= "months";
					break;

					case 'yearly':
						$keyword	= "years";
					break;
				}

				$period = new \DatePeriod( \IPS\calendar\Date::parseTime( $this->start_date, $this->all_day ? FALSE : TRUE ), new \DateInterval( 'P' . $recurrenceRule['event_repeat_freq'] . mb_strtoupper( mb_substr( $keyword, 0, 1 ) ) ), $recurrenceRule['repeat_end_occurrences'] );

				foreach( $period as $dateOccurrence )
				{
					$this->recurring_end_date = $dateOccurrence->format( 'Y-m-d H:i' );
				}
			}
		}

		/* Set content */
		$oldContent = ( !$this->_new ) ? $this->content : NULL;
		$this->content	= $values['event_content'];
		$sendFilterNotifications = $this->checkProfanityFilters( FALSE, !$this->_new, NULL, NULL, 'calendar_Calendar', $this->_new ? ['calendar-event'] : NULL, $values['event_cover_photo'] ? [ $values['event_cover_photo'] ] : [] );
		if ( $oldContent AND $sendFilterNotifications === FALSE )
		{
			$this->sendAfterEditNotifications( $oldContent );
		}

		/* Cover photo */
		$this->cover_photo	= ( $values['event_cover_photo'] !== NULL ) ? (string) $values['event_cover_photo'] : NULL;

		/* Set location */
		$this->location		= ( $values['event_location'] !== NULL ) ? json_encode( $values['event_location'] ) : NULL;

		if ( \IPS\Settings::i()->calendar_venues_enabled )
		{
			$this->venue = NULL;

			if ( $values['event_venue'] instanceof \IPS\calendar\Venue )
			{
				$this->venue = $values['event_venue']->_id;
			}
			elseif ( \IPS\Member::loggedIn()->hasAcpRestriction( 'calendar', 'calendars', 'venues_manage' ) and $values['event_new_venue'] and $values['event_location'] )
			{
				$venue = new \IPS\calendar\Venue;
				$venue->address = json_encode( $values['event_location'] );
				$venue->save();
				
				/* Node titles can contain HTML, but we should prevent this from user submissions as these go right into the language DB */
				$values['venue_title'] = array_map( 'strip_tags', $values['venue_title'] );
				
				\IPS\Lang::saveCustom( 'calendar', 'calendar_venue_' . $venue->id, $values['venue_title'] );
				\IPS\Lang::saveCustom( 'calendar', 'calendar_venue_' . $venue->id . '_desc', $values['venue_description'] );
				$venue->title_seo	= \IPS\Http\Url\Friendly::seoTitle( $values['venue_title'][ \IPS\Lang::defaultLanguage() ] );

				/* Set the order */
				$order = \IPS\Db::i()->select( array( "MAX( `venue_position` )" ), 'calendar_venues', array() )->first();
				$venue->position = $order + 1;

				$venue->save();

				/* Set the new event to this venue */
				$this->venue = $venue->id;
				$this->location = NULL;
			}

		}

		if ( isset( $this->venue ) or isset( $this->location ) )
		{
			$location = json_decode( ( isset( $this->venue ) ) ? $this->venue()->address : $this->location, true );
			$this->latitude = $location['lat'];
			$this->longitude = $location['long'];
		}

		$this->online = $values['event_online'];
		$this->url = $values['event_url'];

		/* Do we know the event type */
		if( $this->online and $values['event_url'] )
		{
			$domain = preg_replace("/^([a-zA-Z0-9].*\.)?([a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z.]{2,})$/", '$2', $values['event_url']->data['host']);

			$services = $this->onlineEventServices();

			if( \array_key_exists( $domain, $services ) )
			{
				$this->online_type = $services[$domain];
			}
			else
			{
				$this->online_type = NULL;
			}
		}

		/* Gallery album association */
		if ( \IPS\Application::appIsEnabled( 'gallery' ) AND $values['event_album'] instanceof \IPS\gallery\Album )
		{
			$this->album		= $values['event_album']->_id;
		}
		else
		{
			$this->album = NULL;
		}

		/* RSVP options */
		$this->rsvp			= isset( $values['event_rsvp'] ) ? $values['event_rsvp'] : FALSE;
		$this->rsvp_limit	= isset( $values['event_rsvp_limit'] ) ? $values['event_rsvp_limit'] : 0;

		unset( $values['save_new_venue'] );
		unset( $values['venue_title'] );
		unset( $values['venue_description'] );

		/* Update event reminders */
		if ( !$this->_new )
		{
			$ts = \IPS\calendar\Date::parseTime( $this->start_date )->getTimestamp();
			\IPS\Db::i()->update( 'calendar_event_reminders', 'reminder_date = ' . $ts . ' - ( reminder_days_before * 86400 )', array( 'reminder_event_id=?', $this->id ) );
		}

		/* Get the normal stuff */
		parent::processForm( $values );
	}

	/**
	 * Process created object AFTER the object has been created
	 *
	 * @param	\IPS\Content\Comment|NULL	$comment	The first comment
	 * @param	array						$values		Values from form
	 * @return	void
	 */
	protected function processAfterCreate( $comment, $values )
	{
		parent::processAfterCreate( $comment, $values );

		/* And claim attachments */
		\IPS\File::claimAttachments( 'calendar-event', $this->id );

		/* And expire widget caches so an event for today will show in the upcoming events widget properly */
		$this->expireWidgetCaches();
	}

	/**
	 * Process created object AFTER the object has been edited
	 *
	 * @param	array						$values		Values from form
	 * @return	void
	 */
	public function processAfterEdit( $values )
	{
		parent::processAfterEdit( $values );

		$this->expireWidgetCaches();
	}

	/**
	 * Create Notification
	 *
	 * @param	string|NULL		$extra		Additional data
	 * @return	\IPS\Notification
	 */
	protected function createNotification( $extra=NULL )
	{
		// New content is sent with itself as the item as we deliberately do not group notifications about new content items. Unlike comments where you're going to read them all - you might scan the notifications list for topic titles you're interested in
		return new \IPS\Notification( \IPS\Application::load( 'calendar' ), 'new_content', $this, array( $this ) );
	}

	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();

		\IPS\Db::i()->delete( 'calendar_event_rsvp', array( 'rsvp_event_id=?', $this->id ) );
		\IPS\Db::i()->delete( 'calendar_event_reminders', array( 'reminder_event_id=?', $this->id ) );

		/* We should not delete maps for imported events, because we do not want to reimport them */
		//\IPS\Db::i()->delete( 'calendar_import_map', array( 'import_event_id=?', $this->id ) );

        $this->coverPhotoFile()?->delete();
	}

	/**
	 * Return the map for the event, if location is specified
	 *
	 * @param	int		$width	Width
	 * @param	int		$height	Height
	 * @return	string
	 * @note	\BadMethodCallException can be thrown if the google maps integration is shut off - don't show any error if that happens.
	 */
	public function map( $width, $height )
	{
		/* Show venue map */
		if ( \IPS\Settings::i()->calendar_venues_enabled )
		{
			if ( $this->venue )
			{
				try
				{
					$this->location = \IPS\calendar\Venue::load( $this->venue )->address;
				}
				catch( \OutOfRangeException $e ){}
			}
		}

		/* No venue? Do we have a specific event location? */
		if ( $this->location )
		{
			try
			{
				return \IPS\GeoLocation::buildFromJson( $this->location )->map()->render( $width, $height );
			}
			catch( \BadMethodCallException $e ){}
		}

		return '';
	}

	/**
	 * Retrieve events to show based on a provided start and end date, optionally filtering by a supplied calendar
	 *
	 * @param	\IPS\calendar\Date					$startDate			Date to start from
	 * @param	\IPS\calendar\Date|NULL				$endDate			Cut off date for events. NULL accepted as a possible value only if $formatEvents is set to FALSE.
	 * @param	\IPS\calendar\Calendar|NULL|array	$calendar			Calendar to filter by
	 * @param	int|null							$limit				Maximum number of events to return (only supported when not formatting events)
	 * @param	bool								$formatEvents		Whether or not to format events into a structured array
	 * @param	\IPS\Member							$member				The member (NULL to use currently logged in member)
	 * @param	\IPS\calendar\Venue					$venue				The venue to filter by (if provided)
	 * @param	bool								$skipPermissions	Skip permission checks (used by REST API to fetch all events which are filtered on later)
	 * @param	array|null							$location			Array of item lat and long
	 * @return	\IPS\Patterns\ActiveRecordIterator
	 * @throws	\InvalidArgumentException
	 * @see		\IPS\Content\_Item::getItemsWithPermission()
	 */
	public static function retrieveEvents ( $startDate, $endDate=NULL, $calendar=NULL, $limit=NULL, $formatEvents=TRUE, $member=NULL, $venue=NULL, $skipPermissions=FALSE, $location = NULL, $online = NULL )
	{
		$where	= array();

		if ( $calendar !== NULL )
		{
			if ( \is_array( $calendar ) )
			{
				$where[] = array( \IPS\Db::i()->in( 'event_calendar_id', $calendar ) );
			}
			
			else
			{
				$where[] = array( 'event_calendar_id=?', (int) $calendar->_id );
			}
		}

		if ( $venue !== NULL )
		{
			$where[] = array( 'event_venue=?', (int) $venue->_id );
		}

		if ( $endDate === NULL AND $formatEvents === TRUE )
		{
			throw new \InvalidArgumentException;
		}

		if( $online !== NULL )
		{
			$where[] = array( '( event_online = ? )', (int) $online );
		}

		if( $location )
		{
			$where[] = array( '( event_latitude IS NOT NULL AND event_longitude IS NOT NULL )' );
		}

		/* Load member */
		if ( $member === NULL AND $skipPermissions === FALSE )
		{
			$member = \IPS\Member::loggedIn();
		}

		/* Get timezone adjusted versions of start/end time */
		$startDateTimezone	= \IPS\calendar\Date::parseTime( $startDate->mysqlDatetime(), TRUE );
		$endDateTimezone	= ( $endDate !== NULL ) ? \IPS\calendar\Date::parseTime( $endDate->mysqlDatetime() ) : NULL;

		if ( $member->timezone )
		{
			$startDateTimezone->setTimezone( new \DateTimeZone( 'UTC' ) );

			if ( $endDateTimezone !== NULL )
			{
				$endDateTimezone->setTimezone( new \DateTimeZone( 'UTC' ) );
			}
		}

		/* First we get the non recurring events based on the timestamps */
		$nonRecurring	= array();
		$nonRecurring[]	= array( 'event_recurring IS NULL' );

		if ( $endDate !== NULL AND $startDate == $endDate )
		{
			$nonRecurring[]	= array( 
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
		elseif ( $endDate !== NULL )
		{
			$nonRecurring[]	= array( 
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
			$nonRecurring[]	= array( 
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

		/* Get the non-recurring events */
		$events	= \IPS\calendar\Event::getItemsWithPermission( array_merge( $where, $nonRecurring ), 'event_start_date ASC', NULL, ( $skipPermissions === TRUE ) ? NULL : 'read', ( $skipPermissions === TRUE ) ? \IPS\Content\Hideable::FILTER_SHOW_HIDDEN : \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, $member, FALSE, FALSE, FALSE,FALSE, NULL, FALSE, TRUE, TRUE, TRUE, FALSE, $location );
		/* We need to make sure ranged events repeat each day that they occur on */
		$formattedEvents	= array();

		if ( $formatEvents )
		{
			foreach( $events as $event )
			{
				/* Is this a ranged event? */
				if ( $event->_end_date !== NULL AND $event->_start_date->mysqlDatetime( FALSE ) < $event->_end_date->mysqlDatetime( FALSE ) )
				{
					$date	= $event->_start_date;
					while( $date->mysqlDatetime( FALSE ) < $event->_end_date->mysqlDatetime( FALSE ) )
					{
						$formattedEvents[ $date->mysqlDatetime( FALSE ) ]['ranged'][ $event->id ]	= $event;
						$date	= $date->adjust( '+1 day' );
					}

					$formattedEvents[ $event->_end_date->mysqlDatetime( FALSE ) ]['ranged'][ $event->id ]	= $event;
				}
				else
				{
					$formattedEvents[ $event->_start_date->mysqlDatetime( FALSE ) ]['single'][ $event->id ]	= $event;
				}
			}
		}
		else
		{
			$formattedEvents	= iterator_to_array( $events );
		}

		/* Now get the recurring events.... */
		$recurringEvents	= \IPS\calendar\Event::getItemsWithPermission( array_merge( $where, array( array( "event_recurring IS NOT NULL AND (event_recurring_end_date IS NULL OR DATE(event_recurring_end_date) >= '{$startDate->mysqlDatetime( FALSE )}')" ) ) ), 'event_start_date ASC', NULL, ( $skipPermissions === TRUE ) ? NULL : 'read', ( $skipPermissions === TRUE ) ? \IPS\Content\Hideable::FILTER_SHOW_HIDDEN : \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, $member, FALSE, FALSE, FALSE,FALSE, NULL, FALSE, TRUE, TRUE, TRUE, FALSE, $location );

		/* Loop over any results */
		foreach( $recurringEvents as $event )
		{
			/* Find occurrences within our date range (if any) */
			$thisEndDate	= ( $endDate ? $endDate->setTime( 23, 59, 59 ) : $startDate->adjust( "+2 years" ) )->setTime( 23, 59, 59 );
			$thisEndDate	= ( clone $thisEndDate )->adjust( "+1 day" );
			$occurrences	= $event->findOccurrences( $startDate, $thisEndDate );

			/* Do we have any? */
			if ( \count( $occurrences ) )
			{
				/* Are we formatting events? If so, place into the array as appropriate. */
				if ( $formatEvents )
				{
					foreach( $occurrences as $occurrence )
					{
						/* Is this a ranged repeating event? */
						if ( $occurrence['endDate'] !== NULL )
						{
							$date	= $occurrence['startDate'];
							$eDate	= ( $thisEndDate->mysqlDatetime( FALSE ) < $occurrence['endDate']->mysqlDatetime( FALSE ) ) ? $thisEndDate : $occurrence['endDate'];

							if ( $date->mysqlDatetime( FALSE ) < $eDate->mysqlDatetime( FALSE ) )
							{
								while( $date->mysqlDatetime( FALSE ) < $eDate->mysqlDatetime( FALSE ) )
								{
									$formattedEvents[ $date->mysqlDatetime( FALSE ) ]['ranged'][ $event->id ]	= $event;
									$date	= $date->adjust( '+1 day' );
								}

								$formattedEvents[ $eDate->mysqlDatetime( FALSE ) ]['ranged'][ $event->id ]	= $event;
							}
							else
							{
								$formattedEvents[ $date->mysqlDatetime( FALSE ) ]['single'][ $event->id ]	= $event;
							}
						}
						else
						{
							$formattedEvents[ $occurrence['startDate']->mysqlDatetime( FALSE ) ]['single'][ $event->id ]	= $event;
						}
					}
				}
				/* Otherwise we only want one instance of the event in our final array */
				else
				{
					$formattedEvents[]	= $event;
				}
			}
		}

		/* Resort non-formatted events */
		if ( $formatEvents === FALSE )
		{
			/* @note: Error suppressor is needed due to PHP bug https://bugs.php.net/bug.php?id=50688 */
			@usort( $formattedEvents, function( $a, $b ) use ( $startDate )
			{
				if ( $a->nextOccurrence( $startDate, 'startDate' ) === NULL )
				{
					return -1;
				}

				if ( $b->nextOccurrence( $startDate, 'startDate' ) === NULL )
				{
					return 1;
				}

				if ( $a->nextOccurrence( $startDate, 'startDate' )->mysqlDatetime() == $b->nextOccurrence( $startDate, 'startDate' )->mysqlDatetime() )
				{
					return 0;
				}
				
				return ( $a->nextOccurrence( $startDate, 'startDate' )->mysqlDatetime() < $b->nextOccurrence( $startDate, 'startDate' )->mysqlDatetime() ) ? -1 : 1;
			} );

			/* Limiting? */
			if ( $limit !== NULL )
			{
				$formattedEvents	= \array_slice( $formattedEvents, 0, $limit, TRUE );
			}
		}
		/* Resort formatted events by time */
		else
		{
			foreach( $formattedEvents as $date => $type )
			{
				foreach( $type as $typeKey => $event )
				{
					/* @note: Error suppressor is needed due to PHP bug https://bugs.php.net/bug.php?id=50688 */
					@usort( $formattedEvents[ $date ][ $typeKey ], function( $a, $b ) use ( $startDate )
					{
						if ( $a->nextOccurrence( $startDate, 'startDate' ) === NULL )
						{
							return -1;
						}

						if ( $b->nextOccurrence( $startDate, 'startDate' ) === NULL )
						{
							return 1;
						}

						if ( $a->nextOccurrence( $startDate, 'startDate' )->mysqlDatetime() == $b->nextOccurrence( $startDate, 'startDate' )->mysqlDatetime() )
						{
							return 0;
						}
						
						return ( $a->nextOccurrence( $startDate, 'startDate' )->format( 'H:i:s' ) < $b->nextOccurrence( $startDate, 'startDate' )->format( 'H:i:s' ) ) ? -1 : 1;
					} );
				}
			}
		}
		return $formattedEvents;
	}

	/**
	 * Has the event already occurred?
	 *
	 * @note	Recurring events that never end will always return FALSE
	 * @return	bool
	 */
	public function hasPassed()
	{
		// Get the end date. If none is available use the start date plus an hour so that online event join links show for a short while after the start
		$lastOccurrence = $this->lastOccurrence( 'endDate' ) ?? $this->lastOccurrence( 'startDate' );

		$lastOccurrenceClone = clone $lastOccurrence;
		$lastOccurrenceClone = $lastOccurrenceClone->add( new \DateInterval("PT1H") );
		if ( $lastOccurrenceClone < \IPS\DateTime::ts( time() ) )
		{
			return true;
		}

		return false;
	}
	
	/**
	 * Cover Photo
	 *
	 * @return	\IPS\Helpers\CoverPhoto
	 */
	public function coverPhoto()
	{
		$photo = parent::coverPhoto();
		$photo->overlay = \IPS\Theme::i()->getTemplate('view', 'calendar', 'front')->coverPhotoOverlay($this);
		return $photo;
	}

	/**
	 * Get HTML for search result display
	 *
	 * @param	string|NULL	$ref	Referrer
	 * @param	\IPS\calendar\Calendar	$container	Container
	 * @param	string		$title	Title
	 * @return	callable
	 */
	public function approvalQueueHtml( $ref, $container, $title )
	{
		return \IPS\Theme::i()->getTemplate( 'modcp', 'calendar', 'front' )->approvalQueueItem( $this, $ref, $container, $title );
	}
	
	/**
	 * Blurb ("On [date] in [calendar])
	 *
	 * @return	string
	 */
	public function eventBlurb()
	{
		$startDate = NULL;
		$endDate = NULL;
		$startTime = NULL;
		$endTime = NULL;
		
		/* Start date */
		if ( $startDate = $this->nextOccurrence( \IPS\calendar\Date::getDate(), 'startDate' ) )
		{
			$endDate = $this->nextOccurrence( $startDate, 'endDate' );
		}
		else
		{
			$startDate = $this->lastOccurrence( 'startDate' );
			$endDate = $this->lastOccurrence( 'endDate' );
		}
		
		/* Start time */
		if ( !$this->all_day )
		{
			$startTime = $startDate->localeTime( FALSE );
			if ( $endDate )
			{
				$endTime = $endDate->localeTime( FALSE );
			}
		}
		
		/* Put all that together */
		$startDate = $startTime ? \IPS\Member::loggedIn()->language()->addToStack( 'blurb_date_with_time', FALSE, array( 'sprintf' => array( $startDate->calendarDate(), $startTime ) ) ) : $startDate->calendarDate();
		$endDate = $endDate ? ( $endTime ? \IPS\Member::loggedIn()->language()->addToStack( 'blurb_date_with_time', FALSE, array( 'sprintf' => array( $endDate->calendarDate(), $endTime ) ) ) : $endDate->calendarDate() ) : NULL;
		$calendar = "<a href='{$this->container()->url()}'>{$this->container()->_title}</a>";
		return $endDate ? \IPS\Member::loggedIn()->language()->addToStack( 'blurb_start_and_end', FALSE, array( 'htmlsprintf' => array( $startDate, $endDate, $calendar ) ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'blurb_start_only', FALSE, array( 'htmlsprintf' => array( $startDate, $calendar ) ) );
	}
	
	/* !Embeddable */
	
	/**
	 * Get content for embed
	 *
	 * @param	array	$params	Additional parameters to add to URL
	 * @return	string
	 */
	public function embedContent( $params )
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'embed.css', 'calendar', 'front' ) );
		return \IPS\Theme::i()->getTemplate( 'global', 'calendar' )->embedEvent( $this, $this->url()->setQueryString( $params ), $this->embedImage() );
	}
	
	/**
	 * Get snippet HTML for search result display
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$authorData		Basic data about the author. Only includes columns returned by \IPS\Member::columnsForPhoto()
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	array|NULL	$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	array		$reputationData	Array of people who have given reputation and the reputation they gave
	 * @param	int|NULL	$reviewRating	If this is a review, the rating
	 * @param	string		$view			'expanded' or 'condensed'
	 * @return	callable
	 */
	public static function searchResultSnippet( array $indexData, array $authorData, array $itemData, ?array $containerData, array $reputationData, $reviewRating, $view )
	{
		$startDate = \IPS\calendar\Date::parseTime( $itemData['event_start_date'], $itemData['event_all_day'] ? FALSE : TRUE );
		$endDate = $itemData['event_end_date'] ? \IPS\calendar\Date::parseTime( $itemData['event_end_date'], $itemData['event_all_day'] ? FALSE : TRUE ) : NULL;
		$nextOccurance = $startDate;
		if ( $itemData['event_recurring'] )
		{
			$occurances = \IPS\calendar\Event::_findOccurances( $startDate, $endDate, $startDate->adjust( "-1 month" ), $startDate->adjust( "+2 years" ), \IPS\calendar\Icalendar\ICSParser::parseRrule( $itemData['event_recurring'] ), NULL, $itemData['event_all_day'] );
			foreach( $occurances as $occurrence )
			{
				if ( $occurrence['startDate'] AND $occurrence['startDate']->mysqlDatetime( FALSE ) >= $startDate->mysqlDatetime( FALSE ) )
				{
					$nextOccurance = $occurrence['startDate'];
					break;
				}
			}
		}
		
		return \IPS\Theme::i()->getTemplate( 'global', 'calendar', 'front' )->searchResultEventSnippet( $indexData, $itemData, $nextOccurance, $startDate, $endDate, $itemData['event_all_day'], $view == 'condensed' );
	}
	
	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse	int						id				ID number
	 * @apiresponse	string					title			Title
	 * @apiresponse	\IPS\calendar\Calendar	calendar		Calendar
	 * @apiresponse	datetime				start			Event start time
	 * @apiresponse	datetime				end				Event end time
	 * @apiresponse	string					recurrence		If this event recurs, the ICS recurrence definition
	 * @apiresponse	bool					rsvp			If this event accepts RSVPs
	 * @apiresponse	int						rsvpLimit		The number of RSVPs the event is limited to
	 * @apiresponse	\IPS\GeoLocation		location		The location where the event is taking place
	 * @apiresponse	\IPS\calendar\Venue		venue			The venue data
	 * @apiresponse	\IPS\Member				author			The member that created the event
	 * @apiresponse	datetime				postedDate		When the event was created
	 * @apiresponse	string					description		Event description
	 * @apiresponse	int						comments		Number of comments
	 * @apiresponse	int						reviews			Number of reviews
	 * @apiresponse	int						views			Number of posts
	 * @apiresponse	string					prefix			The prefix tag, if there is one
	 * @apiresponse	[string]				tags			The tags
	 * @apiresponse	bool					locked			Event is locked
	 * @apiresponse	bool					hidden			Event is hidden
	 * @apiresponse	bool					featured		Event is featured
	 * @apiresponse	string					url				URL
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		return array(
			'id'			=> $this->id,
			'title'			=> $this->title,
			'calendar'		=> $this->container()->apiOutput( $authorizedMember ),
			'start'			=> $this->_start_date->rfc3339(),
			'end'			=> $this->_end_date ? $this->_end_date->rfc3339() : NULL,
			'recurrence'	=> $this->recurring,
			'rsvp'			=> (bool) $this->rsvp,
			'rsvpLimit'		=> $this->rsvp_limit == -1 ? NULL : $this->rsvp_limit,
			'location'		=> $this->_location ? $this->_location->apiOutput( $authorizedMember ) : NULL,
			'venue'			=> $this->venue() ? $this->venue()->apiOutput( $authorizedMember ) : NULL,
			'author'		=> $this->author()->apiOutput( $authorizedMember ),
			'postedDate'	=> \IPS\DateTime::ts( $this->saved )->rfc3339(),
			'description'	=> \IPS\Text\Parser::removeLazyLoad( $this->content() ),
			'comments'		=> $this->comments,
			'reviews'		=> $this->reviews,
			'views'			=> $this->views,
			'prefix'		=> $this->prefix(),
			'tags'			=> $this->tags(),
			'locked'		=> (bool) $this->locked(),
			'hidden'		=> (bool) $this->hidden(),
			'featured'		=> (bool) $this->mapped('featured'),
			'url'			=> (string) $this->url(),
		);
	}

	/**
	 * Can edit?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canEdit( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		/* If we normally can edit... */
		if ( $canNormallyEdit = parent::canEdit( $member ) )
		{
			/* Verify the event is either not past, or we can edit past events */
			if ( !$this->hasPassed() OR !\IPS\Settings::i()->calendar_block_past_changes )
			{
				return $canNormallyEdit;
			}
			elseif ( static::modPermission( 'edit', $member, $this->containerWrapper() ) )
			{
				return $canNormallyEdit;
			}
			else
			{
				return FALSE;
			}
		}

		return FALSE;
	}

	/**
	 * Can comment?
	 *
	 * @param	\IPS\Member\NULL	$member							The member (NULL for currently logged in member)
	 * @param	bool				$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 */
	public function canComment( $member=NULL, $considerPostBeforeRegistering = TRUE )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return parent::canComment( $member, $considerPostBeforeRegistering ) and $this->container()->allow_comments;
	}

	/**
	 * Can review?
	 *
	 * @param	\IPS\Member\NULL	$member							The member (NULL for currently logged in member)
	 * @param	bool				$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 */
	public function canReview( $member=NULL, $considerPostBeforeRegistering = TRUE )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return parent::canReview( $member, $considerPostBeforeRegistering ) and $this->container()->allow_reviews;
	}
	
	/**
	 * Reaction Type
	 *
	 * @return	string
	 */
	public static function reactionType()
	{
		return 'event_id';
	}

	/**
	 * Can remind?
	 *
	 * @return	bool
	 */
	public function canRemind()
	{
		/* Does the event happen more than 1 day into the future? */
		return (bool) ( ( $this->_start_date->getTimestamp() - 86400 ) > time() );
	}

	/* !Tags */

	/**
	 * Can tag?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	bool
	 */
	public static function canTag( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return parent::canTag( $member, $container ) and ( $container === NULL or !$container->calendar_bitoptions['bw_disable_tagging'] );
	}

	/**
	 * Can use prefixes?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	bool
	 */
	public static function canPrefix( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return parent::canPrefix( $member, $container ) and ( $container === NULL or !$container->calendar_bitoptions['bw_disable_prefixes'] );
	}

	/**
	 * Venue
	 *
	 * @return	NULL|\IPS\calendar\Venue
	 */
	public function venue()
	{
		if ( \IPS\Settings::i()->calendar_venues_enabled and $this->venue )
		{
			try
			{
				if ( !$this->venueObject )
				{
					$this->venueObject = \IPS\calendar\Venue::load( $this->venue );
				}

				return $this->venueObject;
			}
			catch( \OutOfRangeException $e ){}
		}

		return NULL;
	}
	
	/**
	 * Supported Meta Data Types
	 *
	 * @return	array
	 */
	public static function supportedMetaDataTypes()
	{
		return array( 'core_FeaturedComments', 'core_ContentMessages' );
	}

	/**
	 * Get the real location, this method takes also the venue into account
	 *
	 * @return NULL|\IPS\GeoLocation
	 */
	public function get__location()
	{
		if ( $this->locationData === NULL )
		{
			if ( $this->venue() )
			{
				$this->locationData = \IPS\GeoLocation::buildFromjson( $this->venue()->address );
			}
			elseif ( $this->location )
			{
				$this->locationData = \IPS\GeoLocation::buildFromjson( $this->location );
			}
			else
			{
				$this->locationData = FALSE;
			}
		}

		return $this->locationData;
	}

	/**
	 * Returns the content images
	 *
	 * @param	int|null	$limit				Number of attachments to fetch, or NULL for all
	 * @param	bool		$ignorePermissions	If set to TRUE, permission to view the images will not be checked
	 * @return	array|NULL
	 * @throws	\BadMethodCallException
	 */
	public function contentImages( $limit = NULL, $ignorePermissions = FALSE )
	{
		$attachments = parent::contentImages( $limit, $ignorePermissions ) ?: array();

		/* Does the event have a cover photo? */
		if ( $this->cover_photo )
		{
			$attachments[] = array( 'calendar_Events' => $this->cover_photo );
		}

		return \count( $attachments ) ? \array_slice( $attachments, 0, $limit ) : NULL;
	}

	/**
	 * Get Online Event Services
	 *
	 * @return	array
	 */
	protected static function onlineEventServices()
	{
		$services = array(
			'eventbrite.com' 		=> 'eventbrite',
			'on24.com' 				=> 'on24',
			'zoom.com' 				=> 'zoom',
			'zoom.us' 				=> 'zoom',
			'facebook.com' 			=> 'facebook',
			'google.com' 			=> 'google',
			'webex.com'				=> 'webex',
			'slack.com'				=> 'slack',
			'discord.com'			=> 'discord',
			'discord.gg'			=> 'discord',
			'microsoft.com'			=> 'teams',
			'tiktok.com'			=> 'tiktok',
			'twitch.tv'				=> 'twitch',
			'vimeo.com'				=> 'vimeo',
			'spotme.com'			=> 'spotme',
		);

		return $services;
	}

	/**
	 * Is the event today/now?
	 *
	 * @return	string|bool
	 */
	public function get__happening()
	{
		if( !$this->happeningData )
		{
			$now = \IPS\calendar\Date::ts( time() );

			/* Non recurring and events with end date*/
			if( !$this->recurring and $this->_start_date and $this->_end_date and ( ( $now > $this->_start_date ) and $now < $this->_end_date ) )
			{
				$this->happeningData = \IPS\Member::loggedIn()->language()->get( 'event_happening_now' );
			}
			/* recurring/no end date */
			elseif( $this->nextOccurrence( $now ) and $now->format('Y-m-d') == $this->nextOccurrence( $now )->format('Y-m-d') )
			{
				$this->happeningData = \IPS\Member::loggedIn()->language()->get( 'event_happening_today' );
			}
			else
			{
				$this->happeningData = FALSE;
			}
		}

		return $this->happeningData;
	}

	/**
	 * Can the member copy the calendar event?
	 * 
	 * @param \IPS\Member|NULL $member
	 * @return bool
	 */
	public function canCopyEvent( \IPS\Member $member = NULL ): bool
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $this->author()->member_id == $member->member_id and $this->container()->can( 'add', $member );
	}
}
