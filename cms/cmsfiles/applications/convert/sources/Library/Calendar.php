<?php

/**
 * @brief		Converter Library Calendar Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		21 Jan 2015
 */

namespace IPS\convert\Library;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Invision Calendar Converter
 * @note	We must extend the Core Library here so we can access methods like convertAttachment, convertFollow, etc
 */
class _Calendar extends Core
{
	/**
	 * @brief	Application
	 */
	public $app = 'calendar';

	/**
	 * Returns an array of items that we can convert, including the amount of rows stored in the Community Suite as well as the recommend value of rows to convert per cycle
	 *
	 * @param	bool	$rowCounts		enable row counts
	 * @return	array
	 */
	public function menuRows( $rowCounts=FALSE )
	{
		$return		= array();
		$extraRows 	= $this->software->extraMenuRows();

		foreach( $this->getConvertableItems() as $k => $v )
		{
			switch( $k )
			{
				case 'convertCalendarCalendars':
					$return[ $k ] = array(
						'step_title'		=> 'convert_calendar_calendars',
						'step_method'		=> 'convertCalendarCalendars',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'calendar_calendars' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array(),
						'link_type'			=> 'calendar_calendars',
					);
					break;

				case 'convertCalendarVenues':
					$return[ $k ] = array(
						'step_title'		=> 'convert_calendar_venues',
						'step_method'		=> 'convertCalendarVenues',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'calendar_venues' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertCalendarCalendars' ),
						'link_type'			=> 'calendar_venues',
					);
					break;
				
				case 'convertCalendarEvents':
					$return[ $k ] = array(
						'step_title'		=> 'convert_calendar_events',
						'step_method'		=> 'convertCalendarEvents',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'calendar_events' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertCalendarCalendars' ),
						'link_type'			=> 'calendar_events',
						'requires_rebuild'	=> TRUE
					);
					break;
				
				case 'convertCalendarComments':
					$return[ $k ] = array(
						'step_title'		=> 'convert_calendar_comments',
						'step_method'		=> 'convertCalendarComments',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'calendar_event_comments' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertCalendarEvents' ),
						'link_type'			=> 'calendar_event_comments',
						'requires_rebuild'	=> TRUE
					);
					break;
				
				case 'convertCalendarReviews':
					$return[ $k ] = array(
						'step_title'		=> 'convert_calendar_reviews',
						'step_method'		=> 'convertCalendarReviews',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'calendar_event_reviews' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertCalendarEvents' ),
						'link_type'			=> 'calendar_event_reviews',
						'requires_rebuild'	=> TRUE
					);
					break;
				
				case 'convertCalendarRsvps':
					$return[ $k ] = array(
						'step_title'		=> 'convert_calendar_rsvps',
						'step_method'		=> 'convertCalendarRsvps',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'calendar_event_rsvp' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertCalendarEvents' ),
						'link_type'			=> 'calendar_event_rsvp',
					);
					break;
				
				case 'convertCalendarFeeds':
					$return[ $k ] = array(
						'step_title'		=> 'convert_calendar_feeds',
						'step_method'		=> 'convertCalendarFeeds',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'calendar_import_feeds' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertCalendarCalendars' ),
						'link_type'			=> 'calendar_import_feeds',
					);
					break;

				case 'convertCalendarReminders':
					$return[ $k ] = array(
						'step_title'		=> 'convert_calendar_reminders',
						'step_method'		=> 'convertCalendarReminders',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'calendar_event_reminders' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertCalendarEvents' ),
						'link_type'			=> 'calendar_reminders',
					);
					break;
				
				case 'convertAttachments':
					$return[ $k ] = array(
						'step_title'		=> 'convert_attachments',
						'step_method'		=> 'convertAttachments',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'core_attachments_map', array( "location_key=?", 'calendar_Calendar' ) ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 10,
						'dependencies'		=> array( 'convertCalendarEvents' ),
						'link_type'			=> 'core_attachments',
					);
					break;
			}

			/* Append any extra steps immediately to retain ordering */
			if( isset( $v['extra_steps'] ) )
			{
				foreach( $v['extra_steps'] as $extra )
				{
					$return[ $extra ] = $extraRows[ $extra ];
				}
			}
		}

		/* Run the queries if we want row counts */
		if( $rowCounts )
		{
			$return = $this->getDatabaseRowCounts( $return );
		}

		return $return;
	}
	
	/**
	 * Returns an array of tables that need to be truncated when Empty Local Data is used
	 *
	 * @param	string	$method	Method to truncate
	 * @return	array
	 */
	protected function truncate( $method )
	{
		$return		= array();
		$classname	= \get_class( $this->software );

		if( $classname::canConvert() === NULL )
		{
			return array();
		}
		
		foreach( $classname::canConvert() as $k => $v )
		{
			switch( $k )
			{
				case 'convertCalendarCalendars':
					$return['convertCalendarCalendars'] = array( 
																'calendar_calendars'	=> NULL,
																'core_clubs_node_map'	=> array( "node_class=?", "IPS\\calendar\\Calendar" ),
																'core_permission_index' => array( 'app=? AND perm_type=?', 'calendar', 'calendar' )
						);
					break;
				
				case 'convertCalendarEvents':
					$return['convertCalendarEvents'] = array( 'calendar_events' => NULL );
					break;
				
				case 'convertCalendarComments':
					$return['convertCalendarComments'] = array( 'calendar_event_comments' => NULL );
					break;
				
				case 'convertCalendarReviews':
					$return['convertCalendarReviews'] = array( 'calendar_event_reviews' => NULL );
					break;
				
				case 'convertCalendarRsvps':
					$return['convertCalendarRsvps'] = array( 'calendar_event_rsvp' => NULL );
					break;
				
				case 'convertCalendarFeeds':
					$return['convertCalendarFeeds'] = array( 'calender_import_feeds' => NULL );
					break;

				case 'convertCalendarVenues':
					$return['convertCalendarVenues'] = array( 'calender_venues' => NULL );
					break;

				case 'convertCalendarReminders':
					$return['convertCalendarReminders'] = array( 'calender_reminders' => NULL );
					break;
				case 'convertAttachments':
					$attachIds = array();
					foreach( \IPS\Db::i()->select( 'attachment_id', 'core_attachments_map', array( 'location_key=?', 'calendar_Calendar' ) ) AS $attachment )
					{
						$attachIds[] = $attachment;
					}
					$return['convertAttachments'] = array( 'core_attachments' => \IPS\Db::i()->in( 'attach_id', $attachIds ), 'core_attachments_map' => array( "location_key=?", 'calendar_Calendar' ) );
					break;
			}
		}

		return $return[ $method ];
	}
	
	/**
	 * This is how the insert methods will work - basically like 3.x, but we should be using the actual classes to insert the data unless there is a real world reason not too.
	 * Using the actual routines to insert data will help to avoid having to resynchronize and rebuild things later on, thus resulting in less conversion time being needed overall.
	 * Anything that parses content, for example, may need to simply insert directly then rebuild via a task over time, as HTML Purifier is slow when mass inserting content.
	 */
	
	/**
	 * A note on logging -
	 * If the data is missing and it is unlikely that any source software would be able to provide this, we do not need to log anything and can use default data (for example, group_layout in convertLeaderGroups).
	 * If the data is missing and it is likely that a majority of the source software can provide this, we should log a NOTICE and use default data (for example, a_casesensitive in convertAcronyms).
	 * If the data is missing and it is required to convert the item, we should log a WARNING and return FALSE.
	 * If the conversion absolutely cannot proceed at all (filestorage locations not writable, for example), then we should log an ERROR and throw an \IPS\convert\Exception to completely halt the process and redirect to an error screen showing the last logged error.
	 */
	 
	/**
	 * Convert a Calendar
	 *
	 * @param	array			$info	Data to insert
	 * @return	integer|boolean	The ID of the newly inserted calendar, or FALSE on failure.
	 */
	public function convertCalendar( $info=array() )
	{
		if ( !isset( $info['cal_id'] ) )
		{
			$this->software->app->log( 'calendar_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( !isset( $info['cal_title'] ) )
		{
			$name = "Calendar {$info['cal_id']}";
			$this->software->app->log( 'calendar_missing_title', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['cal_id'] );
		}
		else
		{
			$name = $info['cal_title'];
			unset( $info['cal_title'] );
		}
		
		if ( !isset( $info['cal_description'] ) )
		{
			$desc = '';
		}
		else
		{
			$desc = $info['cal_description'];
			unset( $info['cal_description'] );
		}
		
		$info['cal_title_seo'] = \IPS\Http\Url::seoTitle( $name );
		
		/* Zero Defaults */
		foreach( array( 'cal_moderate', 'cal_comment_moderate', 'cal_allow_reviews', 'cal_review_moderate' ) AS $zeroDefault )
		{
			if ( !isset( $info[ $zeroDefault ] ) )
			{
				$info[ $zeroDefault ] = 0;
			}
		}
		
		if ( !isset( $info['cal_allow_comments'] ) )
		{
			$info['cal_allow_comments'] = 1;
		}
		
		if ( !isset( $info['cal_position'] ) )
		{
			$position = \IPS\Db::i()->select( 'MAX(cal_position)', 'calendar_calendars' )->first();
			$info['cal_position'] = $position + 1;
		}
		
		if ( !isset( $info['cal_color'] ) )
		{
			$genericCalendar = new \IPS\calendar\Calendar;
			$info['cal_color'] = $genericCalendar->_generateColor();
		}
		
		if ( isset( $info['cal_club_id'] ) )
		{
			try
			{
				$info['cal_club_id'] = $this->software->app->getLink( $info['cal_club_id'], 'core_clubs', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['cal_club_id'] = NULL;
			}
		}
		else
		{
			$info['cal_club_id'] = NULL;
		}
		
		$id = $info['cal_id'];
		unset( $info['cal_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'calendar_calendars', $info );
		$this->software->app->addLink( $inserted_id, $id, 'calendar_calendars' );
		
		\IPS\Lang::saveCustom( 'calendar', "calendar_calendar_{$inserted_id}", $name );
		\IPS\Lang::saveCustom( 'calendar', "calendar_calendar_{$inserted_id}_desc", $desc );

		\IPS\Db::i()->insert( 'core_permission_index', array( 'app' => 'calendar', 'perm_type' => 'calendar', 'perm_type_id' => $inserted_id, 'perm_view' => '' ) );
		
		if ( $info['cal_club_id'] )
		{
			\IPS\Db::i()->insert( 'core_clubs_node_map', array(
				'node_id'		=> $inserted_id,
				'node_class'	=> "IPS\\calendar\\Calendar",
				'club_id'		=> $info['cal_club_id'],
				'name'			=> $name,
			) );
			
			\IPS\calendar\Calendar::load( $inserted_id )->setPermissionsToClub( \IPS\Member\Club::load( $info['cal_club_id'] ) );
		}
		
		return $inserted_id;
	}
	
	/**
	 * Convert an event
	 *
	 * @param	array			$info		Data to insert
	 * @param	string|NULL		$filepath	Path to the event cover photo, or NULL.
	 * @param	string|NULL		$filedata	Cover photo binary data, or NULL
	 * @return	integer|boolean	The ID of the newly inserted event, or FALSE on failure.
	 */
	public function convertCalendarEvent( $info=array(), $filepath=NULL, $filedata=NULL )
	{
		if ( !isset( $info['event_id'] ) )
		{
			$this->software->app->log( 'calendar_event_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( isset( $info['event_calendar_id'] ) )
		{
			try
			{
				$info['event_calendar_id'] = $this->software->app->getLink( $info['event_calendar_id'], 'calendar_calendars' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'calendar_event_missing_calendar', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['event_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'calendar_event_missing_calendar', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['event_id'] );
			return FALSE;
		}
		
		if ( isset( $info['event_member_id'] ) )
		{
			try
			{
				$info['event_member_id'] = $this->software->app->getLink( $info['event_member_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['event_member_id'] = 0;
			}
		}
		else
		{
			$info['event_member_id'] = 0;
		}

		if ( isset( $info['event_venue'] ) )
		{
			try
			{
				$info['event_venue'] = $this->software->app->getLink( $info['event_venue'], 'calendar_venues', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['event_venue'] = NULL;
			}
		}
		else
		{
			$info['event_venue'] = NULL;
		}
		
		if ( !isset( $info['event_title'] ) )
		{
			$event['event_title'] = "Untitled Event {$info['event_id']}";
			$this->software->app->log( 'calendar_event_missing_title', __METHOD__, \IPS\convert\App::LOG_NOTICE, $info['event_id'] );
		}
		
		/* Maye we can do this for other apps too? I have seen some complaints where content can be intentionally left blank in some softwares */
		if ( empty( $info['event_content'] ) )
		{
			$event['event_content'] = "<p>{$info['event_title']}</p>";
			$this->software->app->log( 'calendar_event_missing_content', __METHOD__, \IPS\convert\App::LOG_NOTICE, $info['event_id'] );
		}
		
		/* Zero Defaults! */
		foreach( array( 'event_comments', 'event_rsvp', 'event_rating', 'event_sequence', 'event_all_day', 'event_reviews', 'event_locked', 'event_featured', 'event_queued_comments', 'event_hidden_comments', 'event_unapproved_reviews', 'event_hidden_reviews' ) AS $zeroDefault )
		{
			if ( !isset( $info[ $zeroDefault ] ) )
			{
				$info[ $zeroDefault ] = 0;
			}
		}
		
		if ( isset( $info['event_saved'] ) )
		{
			if ( $info['event_saved'] instanceof \IPS\DateTime )
			{
				$info['event_saved'] = $info['event_saved']->getTimestamp();
			}
		}
		else
		{
			$info['event_saved'] = time();
		}
		
		if ( isset( $info['event_lastupdated'] ) )
		{
			if ( $info['event_lastupdated'] instanceof \IPS\DateTime )
			{
				$info['event_lastupdated'] = $info['event_lastupdated']->getTimestamp();
			}
		}
		else
		{
			$info['event_lastupdated'] = $info['event_saved'];
		}
		
		if ( isset( $info['event_recurring'] ) )
		{
			/* If we have an array, pass off to ICSParser so we can build it */
			if ( \is_array( $info['event_recurring'] ) )
			{
				$info['event_recurring'] = \IPS\calendar\Icalendar\ICSParser::buildRrule( $info['event_recurring'] );
			}
			else
			{
				/* If we didn't, make sure it's valid */
				try
				{
					\IPS\calendar\Icalendar\ICSParser::parserRrule( $info['event_recurring'] );
				}
				catch( \Exception $e )
				{
					$this->software->app->log( 'calendar_event_recurring_invalid', __METHOD__, \IPS\convert\App::LOG_NOTICE, $info['event_id'] );
					$info['event_recurring'] = NULL;
				}
			}
		}
		else
		{
			$info['event_recurring'] = NULL;
		}
		
		if ( isset( $info['event_start_date'] ) )
		{
			if ( $info['event_start_date'] instanceof \IPS\calendar\Date )
			{
				$info['event_start_date'] = $info['event_start_date']->mysqlDatetime();
			}
			else if ( $info['event_start_date'] instanceof \IPS\DateTime )
			{
				$info['event_start_date'] = \IPS\calendar\Date::create( (string) $info['event_start_date'] )->mysqlDatetime();
			}
		}
		else
		{
			$this->software->app->log( 'calendar_event_missing_start_date', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( isset( $info['event_end_date'] ) )
		{
			if ( $info['event_end_date'] instanceof \IPS\calendar\Date )
			{
				$info['event_end_date'] = $info['event_end_date']->mysqlDatetime();
			}
			else if ( $info['event_end_date'] instanceof \IPS\DateTime )
			{
				$info['event_end_date'] = \IPS\calendar\Date::create( (string) $info['event_end_date'] )->mysqlDatetime();
			}
		}
		else
		{
			$info['event_end_date'] = NULL;
		}
		
		$info['event_title_seo']	= \IPS\Http\Url::seoTitle( $info['event_title'] );
		$info['event_post_key']		= md5( microtime() );
		
		if ( !isset( $info['event_ip_address'] ) OR filter_var( $info['event_ip_address'], FILTER_VALIDATE_IP ) === FALSE )
		{
			$info['event_ip_address'] = '127.0.0.1';
		}
		
		if ( isset( $info['event_last_comment'] ) )
		{
			if ( $info['event_last_comment'] instanceof \IPS\DateTime )
			{
				$info['event_last_comment'] = $info['event_last_comment']->getTimestamp();
			}
		}
		else
		{
			$info['event_last_comment'] = $info['event_saved'];
		}
		
		if ( isset( $info['event_last_review'] ) )
		{
			if ( $info['event_last_review'] instanceof \IPS\DateTime )
			{
				$info['event_last_review'] = $info['event_last_review']->getTimestamp();
			}
		}
		else
		{
			$info['event_last_review'] = $info['event_saved'];
		}
		
		if ( !isset( $info['event_approved'] ) )
		{
			$info['event_approved'] = 1;
		}
		
		if ( isset( $info['event_approved_by'] ) )
		{
			try
			{
				$info['event_approved_by'] = $this->software->app->getLink( $info['event_approved_by'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['event_approved_by'] = NULL;
			}
		}
		else
		{
			$info['event_approved_by'] = NULL;
		}
		
		if ( isset( $info['event_approved_on'] ) )
		{
			if ( $info['event_approved_on'] instanceof \IPS\DateTime )
			{
				$info['event_approved_on'] = $info['event_approved_on']->getTimestamp();
			}
		}
		else
		{
			$info['event_approved_on'] = NULL;
		}
		
		if ( isset( $info['event_location'] ) )
		{
			if ( \is_array( $info['event_location'] ) AND isset( $info['event_location']['lat'] ) AND isset( $info['event_location']['long'] ) )
			{
				$info['event_location'] = (string) \IPS\GeoLocation::getFromLatLong( $info['event_location']['lat'], $info['event_location']['long'] );
			}
			else if ( $info['event_location'] instanceof \IPS\GeoLocation )
			{
				$info['event_location'] = (string) $info['event_location'];
			}
		}
		else
		{
			$info['event_location'] = NULL;
		}
		
		if ( !isset( $info['event_rsvp_limit'] ) )
		{
			$info['event_rsvp_limit'] = -1;
		}
		
		if ( isset( $info['event_album'] ) )
		{
			try
			{
				$info['event_album'] = $this->software->app->getSiblingLink( $info['event_album'], 'gallery_albums', 'gallery' );
			}
			catch( \OutOfRangeException $e )
			{
				$info['event_album'] = NULL;
			}
		}
		else
		{
			$info['event_album'] = NULL;
		}
		
		if ( isset( $info['event_cover_photo'] ) AND ( !\is_null( $filepath ) OR !\is_null( $filedata ) ) )
		{
			try
			{
				if ( \is_null( $filedata ) AND !\is_null( $filepath ) )
				{
					$filedata = file_get_contents( $filepath );
				}
				
				$file = \IPS\File::create( 'calendar_Events', $info['event_cover_photo'], $filedata );
				$info['event_cover_photo'] = (string) $file;
			}
			catch( \Exception $e )
			{
				$info['event_cover_photo']	= NULL;
			}
			catch( \ErrorException $e )
			{
				$info['event_cover_photo']	= NULL;
			}
		}
		else
		{
			$info['event_cover_photo'] = NULL;
		}

		if ( isset( $info['event_edit_time'] ) )
		{
			if ( $info['event_edit_time'] instanceof \IPS\DateTime )
			{
				$info['event_edit_time'] = $info['event_edit_time']->getTimestamp();
			}
		}
		else
		{
			$info['event_edit_time'] = NULL;
		}

		if ( !isset( $info['event_edit_member_name'] ) )
		{
			$info['event_edit_member_name'] = NULL;
		}

		if ( !isset( $info['event_edit_reason'] ) )
		{
			$info['event_edit_reason'] = '';
		}

		if ( !isset( $info['event_append_edit'] ) )
		{
			$info['event_append_edit'] = 0;
		}
		
		$id = $info['event_id'];
		unset( $info['event_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'calendar_events', $info );
		$this->software->app->addLink( $inserted_id, $id, 'calendar_events' );
		
		return $inserted_id;
	}
	
	/**
	 * Convert a comment
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly inserted comment, or FALSE on failure.
	 */
	public function convertCalendarComment( $info=array() )
	{
		if ( !isset( $info['comment_id'] ) )
		{
			$this->software->app->log( 'calendar_event_comment_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( isset( $info['comment_eid'] ) )
		{
			try
			{
				$info['comment_eid'] = $this->software->app->getLink( $info['comment_eid'], 'calendar_events' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'calendar_event_comment_missing_event', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['comment_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'calendar_event_comment_missing_event', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['comment_id'] );
			return FALSE;
		}
		
		if ( empty( $info['comment_text'] ) )
		{
			$this->software->app->log( 'calendar_event_comment_empty', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['comment_id'] );
			return FALSE;
		}
		
		if ( isset( $info['comment_mid'] ) )
		{
			try
			{
				$info['comment_mid'] = $this->software->app->getLink( $info['comment_mid'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['comment_mid'] = 0;
			}
		}
		else
		{
			$info['comment_mid'] = 0;
		}
		
		if ( isset( $info['comment_date'] ) )
		{
			if ( $info['comment_date'] instanceof \IPS\DateTime )
			{
				$info['comment_date'] = $info['comment_date']->getTimestamp();
			}
		}
		else
		{
			$info['comment_date'] = time();
		}
		
		if ( !isset( $info['comment_approved'] ) )
		{
			$info['comment_approved'] = 1;
		}
		
		if ( !isset( $info['comment_append_edit'] ) )
		{
			$info['comment_append_edit'] = 0;
		}
		
		if ( isset( $info['comment_edit_time'] ) )
		{
			if ( $info['comment_edit_time'] instanceof \IPS\DateTime )
			{
				$info['comment_edit_time'] = $info['comment_edit_time']->getTimestamp();
			}
		}
		else
		{
			$info['comment_edit_time'] = 0;
		}
		
		if ( !isset( $info['comment_edit_name'] ) )
		{
			$info['comment_edit_name'] = NULL;
		}
		
		if ( !isset( $info['comment_ip_address'] ) OR filter_var( $info['comment_ip_address'], FILTER_VALIDATE_IP ) === FALSE )
		{
			$info['comment_ip_address'] = '127.0.0.1';
		}
		
		if ( !isset( $info['comment_author'] ) )
		{
			$author = \IPS\Member::load( $info['comment_mid'] );
			
			if ( $author->member_id )
			{
				$info['comment_author'] = $author->name;
			}
			else
			{
				$info['comment_author'] = "Guest";
			}
		}
		
		$id = $info['comment_id'];
		unset( $info['comment_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'calendar_event_comments', $info );
		$this->software->app->addLink( $inserted_id, $id, 'calendar_event_comments' );
		
		return $inserted_id;
	}
	
	/**
	 * Convert a review
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly inserted review, or FALSE on failure.
	 */
	public function convertCalendarReview( $info=array() )
	{
		if ( !isset( $info['review_id'] ) )
		{
			$this->software->app->log( 'calendar_event_review_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( isset( $info['review_eid'] ) )
		{
			try
			{
				$info['review_eid'] = $this->software->app->getLink( $info['review_eid'], 'calendar_events' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'calendar_event_review_missing_event', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'calendar_event_review_missing_event', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
			return FALSE;
		}
		
		/* Unlike comments, guests cannot review */
		if ( isset( $info['review_mid'] ) )
		{
			try
			{
				$info['review_mid'] = $this->software->app->getLink( $info['review_mid'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'calendar_event_review_missing_member', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'calendar_event_review_missing_member', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
			return FALSE;
		}
		
		if ( empty( $info['review_text'] ) )
		{
			$this->software->app->log( 'calendar_event_review_empty', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
			return FALSE;
		}
		
		/* This seems silly, but we really do need a rating  */
		if ( !isset( $info['review_rating'] ) OR $info['review_rating'] < 1 )
		{
			$this->software->app->log( 'calendar_event_review_invalid_rating', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
			return FALSE;
		}
		
		if ( !isset( $info['review_append_edit'] ) )
		{
			$info['review_append_edit'] = 0;
		}
		
		if ( isset( $info['review_edit_time'] ) )
		{
			if ( $info['review_edit_time'] instanceof \IPS\DateTime )
			{
				$info['review_edit_time'] = $info['review_edit_time']->getTimestamp();
			}
		}
		else
		{
			$info['review_edit_time'] = time();
		}
		
		if ( !isset( $info['review_edit_name'] ) )
		{
			$info['review_edit_name'] = NULL;
		}
		
		if ( isset( $info['review_date'] ) )
		{
			if ( $info['review_date'] instanceof \IPS\DateTime )
			{
				$info['review_date'] = $info['review_date']->getTimestamp();
			}
		}
		else
		{
			$info['review_date'] = time();
		}
		
		if ( !isset( $info['review_ip'] ) OR filter_var( $info['review_ip'], FILTER_VALIDATE_IP ) === FALSE )
		{
			$info['review_ip'] = '127.0.0.1';
		}
		
		if ( !isset( $info['review_author_name'] ) )
		{
			$author = \IPS\Member::load( $info['review_mid'] );
			
			if ( $author->member_id )
			{
				$info['review_author_name'] = $author->name;
			}
			else
			{
				$info['review_author_name'] = "Guest";
			}
		}
		
		if ( isset( $info['review_votes_data'] ) )
		{
			if ( !\is_array( $info['review_votes_data'] ) )
			{
				$info['review_votes_data'] = json_decode( $info['review_votes_data'], TRUE );
			}
			
			$newVoters = array();
			if ( !\is_null( $info['review_votes_data'] ) AND \count( $info['review_votes_data'] ) )
			{
				foreach( $info['review_votes_data'] as $member => $vote )
				{
					try
					{
						$memberId = $this->software->app->getLink( $member, 'core_members', TRUE );
					}
					catch( \OutOfRangeException $e )
					{
						continue;
					}
					
					$newVoters[ $memberId ] = $vote;
				}
			}
			
			if ( \count( $newVoters ) )
			{
				$info['review_votes_data'] = json_encode( $newVoters );
			}
			else
			{
				$info['review_votes_data'] = NULL;
			}
		}
		else
		{
			$info['review_votes_data'] = NULL;
		}
		
		if ( !isset( $info['review_votes'] ) )
		{
			if ( \is_null( $info['review_votes_data'] ) )
			{
				$info['review_votes'] = 0;
			}
			else
			{
				$info['review_votes'] = \count( json_decode( $info['review_votes_data'], TRUE ) );
			}
		}
		
		if ( !isset( $info['review_votes_helpful'] ) )
		{
			if ( \is_null( $info['review_votes_data'] ) )
			{
				$info['review_votes_helpful'] = 0;
			}
			else
			{
				$helpful = 0;
				foreach( json_decode( $info['review_votes_data'], TRUE ) AS $member => $vote )
				{
					if ( $vote == 1 )
					{
						$helpful += 1;
					}
				}
				
				$info['review_votes_helpful'] = $helpful;
			}
		}
		
		if ( !isset( $info['review_approved'] ) )
		{
			$info['review_approved'] = 1;
		}
		
		$id = $info['review_id'];
		unset( $info['review_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'calendar_event_reviews', $info );
		$this->software->app->addLink( $inserted_id, $id, 'calendar_event_reviews' );
		
		return $inserted_id;
	}
	
	/**
	 * Convert an RSVP
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly inserted RSVP, or FALSE on failure.
	 */
	public function convertCalendarRsvp( $info=array() )
	{
		$hasId = TRUE;
		if ( !isset( $info['rsvp_id'] ) )
		{
			$hasId = FALSE;
		}
		
		if ( isset( $info['rsvp_event_id'] ) )
		{
			try
			{
				$info['rsvp_event_id'] = $this->software->app->getLink( $info['rsvp_event_id'], 'calendar_events' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'calendar_rsvp_missing_event', __METHOD__, \IPS\convert\App::LOG_WARNING, ( $hasId ) ? $info['rsvp_id'] : NULL );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'calendar_rsvp_missing_event', __METHOD__, \IPS\convert\App::LOG_WARNING, ( $hasId ) ? $info['rsvp_id'] : NULL );
			return FALSE;
		}
		
		if ( isset( $info['rsvp_member_id'] ) )
		{
			try
			{
				$info['rsvp_member_id'] = $this->software->app->getLink( $info['rsvp_member_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'calendar_rsvp_missing_member', __METHOD__, \IPS\convert\App::LOG_WARNING, ( $hasId ) ? $info['rsvp_id'] : NULL );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'calendar_rsvp_missing_member', __METHOD__, \IPS\convert\App::LOG_WARNING, ( $hasId ) ? $info['rsvp_id'] : NULL );
			return FALSE;
		}
		
		if ( isset( $info['rsvp_date'] ) )
		{
			if ( $info['rsvp_date'] instanceof \IPS\DateTime )
			{
				$info['rsvp_date'] = $info['rsvp_date']->getTimestamp();
			}
		}
		else
		{
			$info['rsvp_date'] = time();
		}
		
		if ( !isset( $info['rsvp_response'] ) OR !\in_array( $info['rsvp_response'], array( 0, 1, 2 ) ) )
		{
			$this->software->app->log( 'calendar_rsvp_invalid_response', __METHOD__, \IPS\convert\App::LOG_WARNING, ( $hasId ) ? $info['rsvp_id'] : NULL );
			return FALSE;
		}
		
		if ( $hasId )
		{
			$id = $info['rsvp_id'];
			unset( $info['rsvp_id'] );
		}
		
		$inserted_id = \IPS\Db::i()->insert( 'calendar_event_rsvp', $info );
		
		if ( $hasId )
		{
			$this->software->app->addLink( $inserted_id, $id, 'calendar_event_rsvp' );
		}
		
		return $inserted_id;
	}
	
	/**
	 * Convert a Calendar Import Feed
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly inserted feed, or FALSE on failure.
	 */
	public function convertCalendarFeed( $info=array() )
	{
		if ( !isset( $info['feed_id'] ) )
		{
			$this->software->app->log( 'calendar_feed_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( !isset( $info['feed_title'] ) )
		{
			$info['feed_title'] = "Untitled Feed {$info['feed_id']}";
			$this->software->app->log( 'calendar_feed_missing_title', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['feed_id'] );
		}
		
		if ( !isset( $info['feed_url'] ) OR filter_var( $info['feed_url'], FILTER_VALIDATE_URL ) === FALSE )
		{
			$this->software->app->log( 'calendar_feed_invalid_url', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['feed_id'] );
			return FALSE;
		}
		
		if ( isset( $info['feed_added'] ) )
		{
			if ( $info['feed_added'] instanceof \IPS\DateTime )
			{
				$info['feed_added'] = $info['feed_added']->getTimestamp();
			}
		}
		else
		{
			$info['feed_added'] = time();
		}
		
		if ( isset( $info['feed_lastupdated'] ) )
		{
			if ( $info['feed_lastupdated'] instanceof \IPS\DateTime )
			{
				$info['feed_lastupdated'] = $info['feed_lastupdated']->getTimestamp();
			}
		}
		else
		{
			$info['feed_lastupdated'] = time();
		}
		
		if ( isset( $info['feed_calendar_id'] ) )
		{
			try
			{
				$info['feed_calendar_id'] = $this->software->app->getLink( $info['feed_calendar_id'], 'calendar_calendars' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'calendar_feed_missing_calendar', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['feed_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'calendar_feed_missing_calendar', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['feed_id'] );
			return FALSE;
		}
		
		if ( isset( $info['feed_member_id'] ) )
		{
			try
			{
				$info['feed_member_id'] = $this->software->app->getLink( $info['feed_member_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'calendar_feed_missing_member', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['feed_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'calendar_feed_missing_member', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['feed_id'] );
			return FALSE;
		}
		
		if ( isset( $info['feed_last_run'] ) )
		{
			if ( $info['feed_last_run'] instanceof \IPS\DateTime )
			{
				$info['feed_last_run'] = $info['feed_last_run']->getTimestamp();
			}
		}
		else
		{
			$info['feed_last_run'] = time();
		}
		
		if ( !isset( $info['feed_allow_rsvp'] ) )
		{
			$info['feed_allow_rsvp'] = 0;
		}
		
		$id = $info['feed_id'];
		unset( $info['feed_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'calendar_import_feeds', $info );
		$this->software->app->addLink( $inserted_id, $id, 'calendar_import_feeds' );
		
		return $inserted_id;
	}

	/**
	 * Convert a Calendar Venue
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly inserted venue, or FALSE on failure.
	 */
	public function convertCalendarVenue( $info=array() )
	{
		if ( !isset( $info['venue_id'] ) )
		{
			$this->software->app->log( 'calendar_venue_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( !isset( $info['venue_title'] ) )
		{
			$info['venue_title'] = "Untitled Venue {$info['venue_id']}";
			$this->software->app->log( 'calendar_venue_missing_title', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['venue_id'] );
		}

		if ( isset( $info['venue_enabled'] ) )
		{
			$info['venue_enabled'] = 1;
		}

		if ( isset( $info['venue_address'] ) )
		{
			if ( \is_array( $info['venue_address'] ) AND isset( $info['venue_address']['lat'] ) AND isset( $info['venue_address']['long'] ) )
			{
				$info['venue_address'] = (string) \IPS\GeoLocation::getFromLatLong( $info['venue_address']['lat'], $info['venue_address']['long'] );
			}
			else if ( $info['venue_address'] instanceof \IPS\GeoLocation )
			{
				$info['venue_address'] = (string) $info['venue_address'];
			}
			else
			{
				$this->software->app->log( 'calendar_venue_missing_address', __METHOD__, \IPS\convert\App::LOG_WARNING );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'calendar_venue_missing_address', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		if ( !isset( $info['venue_position'] ) )
		{
			$position = \IPS\Db::i()->select( 'MAX(venue_position)', 'calendar_venues' )->first();
			$info['venue_position'] = $position + 1;
		}

		$id = $info['venue_id'];
		unset( $info['venue_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'calendar_venues', $info );
		$this->software->app->addLink( $inserted_id, $id, 'calendar_venues' );
		
		return $inserted_id;
	}

	/**
	 * Convert a Calendar Event Reminder
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly inserted reminder, or FALSE on failure.
	 */
	public function convertCalendarReminder( $info=array() )
	{
		if ( !isset( $info['reminder_id'] ) )
		{
			$this->software->app->log( 'calendar_reminder_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		if ( isset( $info['reminder_days_before'] ) )
		{
			$info['reminder_days_before'] = 1;
		}

		if ( isset( $info['reminder_event_id'] ) )
		{
			try
			{
				$info['reminder_event_id'] = $this->software->app->getLink( $info['reminder_event_id'], 'calendar_events' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'calendar_reminder_missing_event', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['reminder_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'calendar_reminder_missing_event', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['reminder_id'] );
			return FALSE;
		}
		
		if ( isset( $info['reminder_member_id'] ) )
		{
			try
			{
				$info['reminder_member_id'] = $this->software->app->getLink( $info['reminder_member_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'calendar_reminder_missing_member', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['reminder_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'calendar_reminder_missing_member', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['reminder_id'] );
			return FALSE;
		}
		
		if ( isset( $info['reminder_date'] ) )
		{
			if ( $info['reminder_date'] instanceof \IPS\DateTime )
			{
				$info['reminder_date'] = $info['reminder_date']->getTimestamp();
			}
		}
		else
		{
			$info['reminder_date'] = time();
		}

		$id = $info['reminder_id'];
		unset( $info['reminder_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'calendar_event_reminders', $info );
		$this->software->app->addLink( $inserted_id, $id, 'calendar_event_reminders' );
		
		return $inserted_id;
	}
	
	/**
	 * Convert an attachment
	 *
	 * @param	array			$info		Data to insert
	 * @param	array			$map		Attachment Map Data
	 * @param	string|NULL		$filepath	Path to the file, or NULL.
	 * @param	string|NULL		$filedata	Binary data for the file, or NULL.
	 * @param	string|NULL		$thumbnailpath	Path to thumbnail, or NULL.
	 * @return	integer|boolean	The ID of the newly inserted attachment, or FALSE on failure.
	 */
	public function convertAttachment( $info=array(), $map=array(), $filepath=NULL, $filedata=NULL, $thumbnailpath=NULL )
	{
		$map['location_key']	= 'calendar_Calendar';
		$map['id1_type']		= 'calendar_events';
		$map['id1_from_parent']	= FALSE;
		$map['id2_from_parent']	= FALSE;

		/* Some set up */
		if ( !isset( $map['id3'] ) )
		{
			$map['id3'] = NULL;
		}
		
		if ( \is_null( $map['id3'] ) OR $map['id3'] != 'review' )
		{
			$map['id2_type'] = 'calendar_event_comments';
		}
		else
		{
			$map['id2_type'] = 'calendar_event_reviews';
		}
		
		return parent::convertAttachment( $info, $map, $filepath, $filedata, $thumbnailpath );
	}
}