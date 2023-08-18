<?php
/**
 * @brief		Calendar-specific date functions
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		30 Dec 2013
 */

namespace IPS\calendar;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Calendar-specific date functions
 */
class _Date extends \IPS\DateTime
{
	/**
	 * @brief	Data retrieved from get_date() with timezone accounted for
	 */
	protected $dateInformation	= array();

	/**
	 * @brief	Information about the first day of the month we are working with
	 */
	protected $firstDayOfMonth	= array();

	/**
	 * @brief	Information about the last day of the month we are working with
	 */
	protected $lastDayOfMonth	= array();

	/**
	 * @brief	Information about the first day of the week we are working with
	 */
	protected $firstDayOfWeek	= array();

	/**
	 * @brief	Information about the last day of the week we are working with
	 */
	protected $lastDayOfWeek	= array();

	/**
	 * @brief	Information about the previous month
	 */
	protected $lastMonth	= array();

	/**
	 * @brief	Information about the next month
	 */
	protected $nextMonth	= array();

	/**
	 * @brief	Timezone offset for the current user
	 */
	public $offset	= NULL;

	/**
	 * @brief	Custom date formatting options for calendar
	 */
	public static $dateFormats	= array(
		'locale' => "%x",
		'd_sm_y' => "%d %b %Y",
		'd_lm_y' => "%d {monthName} %Y",
		'sm_d_y' => "%b %d, %Y",
		'lm_d_y' => "{monthName} %d, %Y",
	);

	/**
	 * @brief	Cache date objects we've created through getDate()
	 */
	protected static $dateObjects	= array();

	/**
	 * Creates a new object to represent the requested date
	 *
	 * @param	int|NULL	$year	Year, or NULL for current year
	 * @param	int|NULL	$month	Month, or NULL for current month
	 * @param	int|NULL	$day	Day, or NULL for current day
	 * @param	int			$hour	Hour (defaults to 0)
	 * @param	int			$minute	Minutes (defaults to 0)
	 * @param	int			$second	Seconds (defaults to 0)
	 * @param	int			$offset	The offset from GMT (NULL to calculate automatically based on member's current time)
	 * @return	\IPS\calendar\Date
	 * @throws	\InvalidArgumentException
	 */
	public static function getDate( $year=NULL, $month=NULL, $day=NULL, $hour=0, $minute=0, $second=0, $offset=NULL )
	{
		/* Get our time zone offset */
		$timezone = NULL;

		if ( $offset === NULL and \IPS\Member::loggedIn()->timezone )
		{
			$timezone = \IPS\DateTime::create();
			$validTimezone = TRUE;
			if( \in_array( \IPS\Member::loggedIn()->timezone, static::getTimezoneIdentifiers() ) )
			{
				try
				{
					$timezone->setTimezone( new \DateTimeZone( \IPS\Member::loggedIn()->timezone ) );
				}
				catch ( \Exception $e )
				{
					$validTimezone = FALSE;
				}
			}
			else
			{
				$validTimezone = FALSE;
			}

			if( ! $validTimezone )
			{
				\IPS\Member::loggedIn()->timezone = null;

				if ( \IPS\Member::loggedIn()->member_id )
				{
					\IPS\Member::loggedIn()->save();
				}
			}

			$offset		= $timezone->getOffset();
		}

		/* Set appropriate defaults for values not supplied, which is normal */
		if( $year === NULL )
		{
			$year = date( 'Y', time() + $offset );
		}

		if( $day === NULL )
		{
			$day = ( $month !== NULL ) ? 1 : date( 'd', time() + $offset );
		}

		if( $month === NULL )
		{
			$month = date( 'm', time() + $offset );
		}

		/* If the date is not valid, that means a bad value was supplied */
		try
		{
			if( !checkdate( $month, $day, $year ) )
			{
				throw new \InvalidArgumentException;
			}
		}
		catch ( \TypeError $e )
		{
			throw new \InvalidArgumentException;
		}

		/* Create the timestamp */
		$timeStamp	= gmmktime( $hour, $minute, $second, $month, $day, $year );

		/* Recalculate offset in case we go over DST boundary */
		if( $timezone )
		{
			$timeZoneCheck = $timezone->setTimestamp( $timeStamp );
			
			$offset = $timeZoneCheck->getOffset();
		}

		/* Store the information and return the object */
		$obj	= static::ts( $timeStamp - $offset );
		$obj->getDateInformation( $timeStamp );
		$obj->offset			= $offset;

		return $obj;
	}

	/**
	 * Create a new datetime object
	 *
	 * @note	We override to update dateInformation and stored offset
	 * @param	string				$time			Time
	 * @param	\DateTimeZone|null	$timezone		Timezone
	 * @return	\IPS\calendar\Date
	 */
	public function __construct( $time="now", $timezone=NULL )
	{
		if ( $timezone )
		{
			$result	= parent::__construct( $time, $timezone );
		}
		else
		{
			$result	= parent::__construct( $time );
		}

		$this->getDateInformation( $this->getTimestamp() );
		$this->offset			= $this->getOffset();

		return $result;
	}

	/**
	 * Convert the Root DateTime instance to a \IPS\calendar\Date instance
	 *
	 * @param \DateTime $dateTime
	 * @return Date
	 */
	public static function dateTimeToCalendarDate( \DateTime $dateTime ): \IPS\calendar\Date
	{
		if( \in_array( \get_class( $dateTime ), [ 'DateTime', 'IPS\\DateTime'] ) )
		{
			return new static( $dateTime->format('c'), $dateTime->getTimezone() );
		}

		return $dateTime;
	}

	/**
	 * Sets the time zone for the DateTime object
	 *
	 * @note	We override to update dateInformation and stored offset
	 * @param	\DateTimeZone	$timezone		New timezone
	 * @return	\IPS\calendar\Date|FALSE
	 */
	public function setTimezone( $timezone )
	{
		$result	= parent::setTimezone( $timezone );

		$this->getDateInformation( $this->getTimestamp() );
		$this->offset			= $this->getOffset();

		return $result;
	}

	/**
	 * Get the date information
	 *
	 * @note	This is basically a timezone aware wrapper for getdate
	 * @param	int		$time	Timestamp
	 * @return	array
	 */
	public function getDateInformation( $time )
	{
		$this->dateInformation	= array(
			'seconds'	=> (int) $this->strFormat( '%S' ),
			'minutes'	=> (int) $this->strFormat( '%M' ),
			'hours'		=> (int) $this->strFormat( '%H' ),
			'mday'		=> (int) $this->strFormat( '%d' ),
			'wday'		=> (int) $this->strFormat( '%w' ),
			'mon'		=> (int) $this->strFormat( '%m' ),
			'year'		=> (int) $this->strFormat( '%Y' ), 
			'yday'		=> (int) $this->strFormat( '%j' ) - 1,
			'weekday'	=> $this->strFormat( '%A' ),
			'month'		=> $this->strFormat( '%B' ),
			0			=> $time
		);
		return $this->dateInformation;
	}

	/**
	 * Returns a date object created based on an arbitrary string. Used for both relative time strings and SQL datetime values.
	 *
	 * @note	Datetime values are stored in the database normalized to UTC
	 * @param	string	$datetime		String-based date/time
	 * @param	bool	$forceUTC		Force timezone to UTC (necessary when passing a datetime retrieved from the database)
	 * @return	\IPS\calendar\Date
	 */
	public static function parseTime( $datetime, $forceUTC=FALSE )
	{
		/* Create an \IPS\DateTime object from the datetime value passed in */
		if ( !$forceUTC AND \IPS\Dispatcher::hasInstance() )
		{
			if( !\IPS\Member::loggedIn()->timezone )
			{
				$timezone	= NULL;
			}
			else
			{
				$validTimezone = TRUE;
				if( \in_array( \IPS\Member::loggedIn()->timezone, static::getTimezoneIdentifiers() ) )
				{
					try
					{
						$timezone	= new \DateTimeZone( \IPS\Member::loggedIn()->timezone );
					}
					catch ( \Exception $e )
					{
						$validTimezone = FALSE;
					}
				}
				else
				{
					$validTimezone = FALSE;
				}

				if( ! $validTimezone )
				{
					\IPS\Member::loggedIn()->timezone = null;

					if ( \IPS\Member::loggedIn()->member_id )
					{
						\IPS\Member::loggedIn()->save();
					}
				}
			}
		}
		else
		{
			$timezone	= new \DateTimeZone( 'UTC' );
		}

		$datetime		= new \IPS\DateTime( $datetime, $timezone );

		/* Now correct it back if necessary */
		if( $forceUTC === TRUE AND \IPS\Dispatcher::hasInstance() AND \IPS\Member::loggedIn()->timezone )
		{
			$datetime->setTimezone( new \DateTimeZone( \IPS\Member::loggedIn()->timezone ) );
		}

		return static::getDate( $datetime->format('Y'), $datetime->format('m'), $datetime->format('d'), $datetime->format('H'), $datetime->format('i'), $datetime->format('s'), $datetime->getOffset() );
	}

	/**
	 * Adjusts the date and returns a new date object representing the adjustment
	 *
	 * @param	string	$adjustment		String to turn into a \DateInterval object which will be applied to the current date/time (supports most strtotime adjustments)
	 * @return	\IPS\calendar\Date
	 * @see		<a href='http://www.php.net/manual/en/dateinterval.createfromdatestring.php'>DateInterval::createFromDateString() docs</a>
	 */
	public function adjust( $adjustment )
	{
		$datetime		= \IPS\DateTime::ts( $this->dateInformation[0] )->setTimezone( new \DateTimeZone( "UTC" ) );
		$dateInterval=\DateInterval::createFromDateString( $adjustment );

		if( $dateInterval )
		{
			$datetime->add( $dateInterval );
		}


		return static::getDate( gmdate( 'Y', $datetime->getTimestamp() ), gmdate( 'm', $datetime->getTimestamp() ), gmdate( 'd', $datetime->getTimestamp() ), gmdate( 'H', $datetime->getTimestamp() ), gmdate( 'i', $datetime->getTimestamp() ), gmdate( 's', $datetime->getTimestamp() ) );
	}

	/**
	 * @brief	Cache results of getDayNames() for performance
	 */
	protected static $cachedDayNames = NULL;

	/**
	 * Get the localized day names in correct order
	 *
	 * @return	array
	 * @see		<a href='http://stackoverflow.com/questions/7765469/retrieving-day-names-in-php'>Get localized day names in PHP</a>
	 */
	public static function getDayNames()
	{
		if( static::$cachedDayNames !== NULL )
		{
			return static::$cachedDayNames;
		}

		$dayNames	= array();
		$startDay	= \IPS\Settings::i()->ipb_calendar_mon ? 'Monday' : 'Sunday';

		for( $i = 0; $i < 7; $i++ )
		{
			$_time		= strtotime( 'next ' . $startDay . ' +' . $i . ' days' );
			$_abbr		= \IPS\Member::loggedIn()->language()->convertString( strftime( '%a', $_time ) );

			$dayNames[]	= array( 'full' => \IPS\Member::loggedIn()->language()->convertString( strftime( '%A', $_time ) ), 'english' => date( 'l', $_time ), 'abbreviated' => $_abbr, 'letter' => mb_substr( $_abbr, 0, 1 ), 'ical' => mb_strtoupper( mb_substr( date( 'D', $_time ), 0, 2 ) ) );
		}

		static::$cachedDayNames = $dayNames;

		return $dayNames;
	}

	/**
	 * Get an array of time zones with GMT offset information supplied in a user-friendly format
	 *
	 * @return	array
	 */
	public static function getTimezones()
	{
		$zones = \DateTimeZone::listIdentifiers( \DateTimeZone::ALL );

		$timezones[\IPS\Member::loggedIn()->timezone] = \IPS\Member::loggedIn()->timezone;
		foreach($zones as $timezone)
		{
			if( $timezone == \IPS\Member::loggedIn()->timezone )
			{
				continue;
			}
			$timezones[$timezone] = $timezone;
		}

		return $timezones;
	}

	/**
	 * Return a date object based on supplied values, factoring in the timezone offset which could be in the "friendly" timezone format
	 *
	 * @param	string	$date		Date as a textual string, from the date form helper
	 * @param	string	$time		Time as a textual string
	 * @param	string	$timezone	Timezone chosen
	 * @return	\IPS\calendar\Date
	 */
	public static function createFromForm( $date, $time, $timezone )
	{
		/* Correct date */
		$date = \IPS\Helpers\Form\Date::_convertDateFormat( $date );

		/* Fix time inconsistencies */
		if( $time )
		{
			$time	= mb_strtolower( $time );

			/* If they typed in 'am', convert '12' to 00, and then strip 'am' */
			if( \strpos( $time, 'am' ) !== FALSE )
			{
				if( \strpos( $time, '12' ) === 0 )
				{
					$time	= substr_replace( $time, '00', 0, 2 );
				}

				$time	= str_replace( 'am', '', $time );
			}
			/* If they typed in 'pm', add 12 to anything other than 12 and strip 'pm' */
			else if( \strpos( $time, 'pm' ) !== FALSE )
			{
				$_timeBits		= explode( ':', $time );
				$_timeBits[0]	= $_timeBits[0] < 12 ? ( $_timeBits[0] + 12 ) : $_timeBits[0];
				$time			= implode( ':', $_timeBits );

				$time	= str_replace( 'pm', '', $time );
			}

			/* Make sure we have 3 pieces and that all are 2 digits */
			$_timeBits		= explode( ':', $time );
			
			if ( \count( $_timeBits ) < 3 )
			{
				while( \count( $_timeBits ) < 3 )
				{
					$_timeBits[]	= '00';
				}
			}

			foreach( $_timeBits as $k => $v )
			{
				$_timeBits[ $k ]	= str_pad( trim( $v ), 2, '0', STR_PAD_LEFT );
			}

			/* Avengers assemble! */
			$time	= implode( ':', $_timeBits );
		}

		$dateObject	= new static( $date . ( $time ? ' ' . $time : '' ), new \DateTimeZone( $timezone ) );
		
		return $dateObject->setTimezone( new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * Modified version of ISO-8601 used by iCalendar - omits the timezone identifier and all dashes and colons
	 *
	 * @param	bool	$includeTime		Whether to include time or not
	 * @param	bool	$includeIdentifier	Whether to include 'Z' at the end or not
	 * @return	string
	 * @see		<a href='http://www.kanzaki.com/docs/ical/dateTime.html'>DateTime explanation</a>
	 */
	public function modifiedIso8601( $includeTime=TRUE, $includeIdentifier=FALSE )
	{
		if( $includeTime )
		{
			return date( 'Ymd', $this->getTimestamp() ) . 'T' . date( 'His', $this->getTimestamp() ) . ( $includeIdentifier ? 'Z' : '' );
		}
		else
		{
			return date( 'Ymd', $this->getTimestamp() );
		}
	}

	/**
	 * Return the date for use in calendar (used instead of localeDate() to allow admin to configure)
	 *
	 * @param	\IPS\Lang|\IPS\Member|NULL	$memberOrLanguage		The language or member to use, or NULL for currently logged in member
	 * @return	string
	 */
	public function calendarDate( $memberOrLanguage=NULL )
	{
		return static::determineLanguage( $memberOrLanguage )->convertString( strftime( static::calendarDateFormat(), $this->getTimestamp() + $this->offset ) );
	}
	
	/**
	 * Return the format used by calendarDate()
	 *
	 * @return	string
	 */
	public static function calendarDateFormat()
	{
		if( \IPS\Settings::i()->calendar_date_format == -1 AND \IPS\Settings::i()->calendar_date_format_custom )
		{
			return \IPS\Settings::i()->calendar_date_format_custom;
		}
		elseif( isset( static::$dateFormats[ \IPS\Settings::i()->calendar_date_format ] ) )
		{
			return str_replace( '{monthName}', static::setMonthModifier(), static::$dateFormats[ \IPS\Settings::i()->calendar_date_format ] );
		}
		else
		{
			return '%x';
		}
	}

	/**
	 * Return the MySQL-style datetime value
	 *
	 * @param	bool	$includeTime	Whether to include time or not
	 * @return	string
	 */
	public function mysqlDatetime( $includeTime=TRUE )
	{
		if( $includeTime )
		{
			return $this->dateInformation['year'] . '-' . str_pad( $this->dateInformation['mon'], 2, 0, STR_PAD_LEFT ) . '-' . str_pad( $this->dateInformation['mday'], 2, 0, STR_PAD_LEFT ) . ' ' . str_pad( $this->dateInformation['hours'], 2, 0, STR_PAD_LEFT ) . ':' . str_pad( $this->dateInformation['minutes'], 2, 0, STR_PAD_LEFT ) . ':' . str_pad( $this->dateInformation['seconds'], 2, 0, STR_PAD_LEFT );
		}
		else
		{
			return $this->dateInformation['year'] . '-' . str_pad( $this->dateInformation['mon'], 2, 0, STR_PAD_LEFT ) . '-' . str_pad( $this->dateInformation['mday'], 2, 0, STR_PAD_LEFT );
		}
	}

	/**
	 * Magic method to make retrieving certain data easier
	 *
	 * @param	mixed	$key	Value we tried to retrieve
	 * @return	mixed
	 */
	public function __get( $key )
	{
		return $this->_findValue( $key, $this->dateInformation );
	}

	/**
	 * Retrieve information about the first day of the month
	 *
	 * @param	mixed	$key	Key (from getdate()) we want to retrieve
	 * @return	mixed
	 */
	public function firstDayOfMonth( $key )
	{
		if( !isset( $this->firstDayOfMonth[0] ) )
		{
			$this->firstDayOfMonth	= getdate( gmmktime( 0, 0, 0, $this->mon, 1, $this->year ) );
		}

		return $this->_findValue( $key, $this->firstDayOfMonth );
	}

	/**
	 * Retrieve information about the last day of the month
	 *
	 * @param	mixed	$key	Key (from getdate()) we want to retrieve
	 * @return	mixed
	 */
	public function lastDayOfMonth( $key )
	{
		if( !isset( $this->lastDayOfMonth[0] ) )
		{
			$this->lastDayOfMonth	= getdate( gmmktime( 0, 0, 0, $this->mon + 1, 0, $this->year ) );
		}

		return $this->_findValue( $key, $this->lastDayOfMonth );
	}

	/**
	 * Retrieve information about the previous month
	 *
	 * @param	mixed	$key	Key (from getdate()) we want to retrieve
	 * @return	mixed
	 */
	public function lastMonth( $key )
	{
		if( !isset( $this->lastMonth[0] ) )
		{
			$this->lastMonth	= getdate( gmmktime( 0, 0, 0, $this->mon - 1, 1, $this->year ) );
		}

		return $this->_findValue( $key, $this->lastMonth );
	}

	/**
	 * Retrieve information about the next month
	 *
	 * @param	mixed	$key	Key (from getdate()) we want to retrieve
	 * @return	mixed
	 */
	public function nextMonth( $key )
	{
		if( !isset( $this->nextMonth[0] ) )
		{
			$this->nextMonth	= getdate( gmmktime( 0, 0, 0, $this->mon + 1, 1, $this->year ) );
		}

		return $this->_findValue( $key, $this->nextMonth );
	}

	/**
	 * Retrieve information about the first day of the week we are working with
	 *
	 * @param	mixed	$key	Key (from getdate()) we want to retrieve
	 * @return	mixed
	 */
	public function firstDayOfWeek( $key )
	{
		if( !isset( $this->firstDayOfWeek[0] ) )
		{
			$this->firstDayOfWeek	= getdate( gmmktime( 0, 0, 0, $this->mon, $this->mday - $this->wday, $this->year ) );
		}

		return $this->_findValue( $key, $this->firstDayOfWeek );
	}

	/**
	 * Retrieve information about the last day of the week we are working with
	 *
	 * @param	mixed	$key	Key (from getdate()) we want to retrieve
	 * @return	mixed
	 */
	public function lastDayOfWeek( $key )
	{
		if( !isset( $this->lastDayOfWeek[0] ) )
		{
			$this->lastDayOfWeek	= getdate( gmmktime( 0, 0, 0, $this->mon, $this->mday + ( 6 - $this->wday ), $this->year ) );
		}

		return $this->_findValue( $key, $this->lastDayOfWeek );
	}

	/**
	 * Returns whether the date falls in a leap year or not
	 *
	 * @return bool
	 */
	public function leapYear()
	{
		return (bool) gmdate( 'L', $this->dateInformation[0] );
	}

	/**
	 * Returns whether the current locale uses AM/PM or 24 hour format
	 *
	 * @return	bool
	 * @see	<a href='http://stackoverflow.com/questions/6871258/how-to-determine-if-current-locale-has-24-hour-or-12-hour-time-format-in-php'>Check for 24 hour locale use</a>
	 */
	public static function usesAmPm()
	{
		return ( \substr( gmstrftime( '%X', 57600 ), 0, 2) != 16 );
	}

	/**
	 * Get the 12 hour version of an hour value
	 *
	 * @param	int		$hour	The hour value between 0 and 23
	 * @return	int
	 */
	public static function getTwelveHour( $hour )
	{
		if( $hour == 0 )
		{
			return 12;
		}
		else if( $hour > 12 )
		{
			return $hour - 12;
		}
		else
		{
			return $hour;
		}
	}

	/**
	 * Get the AM/PM value for the current locale
	 *
	 * @param	int		$hour	The hour value between 0 and 23
	 * @return	string
	 */
	public static function getAmPm( $hour )
	{
		return gmstrftime( '%p', $hour * 60 * 60 );
	}

	/**
	 * Find a value in the supplied array and return it. Also supports a few 'special' keys.
	 *
	 * @param	mixed	$key	Key (from getdate()) we want to retrieve
	 * @param	array	$data	Array to look for the key in
	 * @return	mixed
	 */
	protected function _findValue( $key, $data )
	{
		if( isset( $data[ $key ] ) )
		{
			if( $key == 'wday' AND \IPS\Settings::i()->ipb_calendar_mon )
			{
				return ( $data[ $key ] == 0 ) ? 6: ( $data[ $key ] - 1 );
			}

			if( $key == 'mon' OR $key == 'mday' )
			{
				return str_pad( $data[ $key ], 2, '0', STR_PAD_LEFT );
			}

			return $data[ $key ];
		}

		if( $key == 'monthName' AND isset( $data[0] ) )
		{
			return mb_convert_case( \IPS\Member::loggedIn()->language()->convertString( strftime( static::setMonthModifier(), $data[0] ) ), MB_CASE_TITLE );
		}

		if( $key == 'monthNameShort' AND isset( $data[0] ) )
		{
			return mb_convert_case( \IPS\Member::loggedIn()->language()->convertString( strftime( '%b', $data[0] ) ), MB_CASE_TITLE );
		}

		if( $key == 'dayName' AND isset( $data[0] ) )
		{
			return mb_convert_case( \IPS\Member::loggedIn()->language()->convertString( strftime( '%A', $data[0] ) ), MB_CASE_TITLE );
		}

		if( $key == 'dayNameShort' AND isset( $data[0] ) )
		{
			return mb_convert_case( \IPS\Member::loggedIn()->language()->convertString( strftime( '%a', $data[0] ) ), MB_CASE_TITLE );
		}

		return NULL;
	}

	/**
	 * @brief strftime-compatible modifier for month name
	 */
	protected static $monthNameModifier = NULL;

	/**
	 * Sets the month name modifier
	 *
	 * @note	Some languages on some OS's (e.g. FreeBSD) require using a different modifier for full month name
	 * @return	void
	 */
	static protected function setMonthModifier()
	{
		if( static::$monthNameModifier === NULL )
		{
			$test = @strftime( '%OB' );

			if( $test === FALSE OR $test == '%OB' )
			{
				static::$monthNameModifier = '%B';
			}
			else
			{
				static::$monthNameModifier = '%OB';
			}
		}

		return static::$monthNameModifier;
	}

	/**
	 * Format the time according to the user's locale (without the date)
	 *
	 * @param	bool				$seconds	If TRUE, will include seconds
	 * @param	bool				$minutes	If TRUE, will include minutes
	 * @param	\IPS\Lang|\IPS\Member|NULL	$memberOrLanguage		The language or member to use, or NULL for currently logged in member
	 * @return	string
	 */
	public function localeTime( $seconds=TRUE, $minutes=TRUE, $memberOrLanguage=NULL )
	{
		return static::determineLanguage( $memberOrLanguage )->convertString( strftime( static::localeTimeFormat( $seconds, $minutes, $memberOrLanguage ), $this->getTimestamp() + $this->offset ) );
	}
}