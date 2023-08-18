<?php
/**
 * @brief		ACP Live Search Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		08 Apr 2014
 */

namespace IPS\calendar\extensions\core\LiveSearch;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Live Search Extension
 */
class _Calendars
{
	/**
	 * Check we have access
	 *
	 * @return	bool
	 */
	public function hasAccess()
	{
		/* Check Permissions */
		return \IPS\Member::loggedIn()->hasAcpRestriction( 'calendar', 'calendars', 'calendars_manage' );
	}

	/**
	 * Get the search results
	 *
	 * @param	string	$searchTerm	Search Term
	 * @return	array 	Array of results
	 */
	public function getResults( $searchTerm )
	{
		/* Init */
		$results = array();
		$searchTerm = mb_strtolower( $searchTerm );
		
		/* Start with categories */
		if( $this->hasAccess() )
		{
			/* Perform the search */
			$calendars = \IPS\Db::i()->select(
							"*",
							'calendar_calendars',
							array( "cal_club_id IS NULL AND word_custom LIKE CONCAT( '%', ?, '%' ) AND lang_id=?", $searchTerm, \IPS\Member::loggedIn()->language()->id ),
							NULL,
							NULL
					)->join(
							'core_sys_lang_words',
							"word_key=CONCAT( 'calendar_calendar_', cal_id )"
						);
			
			/* Format results */
			foreach ( $calendars as $calendar )
			{
				$calendar = \IPS\calendar\Calendar::constructFromData( $calendar );
				
				$results[] = \IPS\Theme::i()->getTemplate( 'livesearch', 'calendar', 'admin' )->calendar( $calendar );
			}
		}

		return $results;
	}
	
	/**
	 * Is default for current page?
	 *
	 * @return	bool
	 */
	public function isDefault()
	{
		return \IPS\Dispatcher::i()->application->directory == 'calendar';
	}
}