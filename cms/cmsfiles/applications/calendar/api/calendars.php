<?php
/**
 * @brief		Calendars API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		3 Apr 2017
 */

namespace IPS\calendar\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Calendars API
 */
class _calendars extends \IPS\Node\Api\NodeController
{
	/**
	 * Class
	 */
	protected $class = 'IPS\calendar\Calendar';

	/**
	 * GET /calendar/calendars
	 * Get list of calendars
	 *
	 * @apiparam	int		clubs			0|1 Include club calendars, default: 1
	 * @apiparam	int		page			Page number
	 * @apiparam	int		perPage			Number of results per page - defaults to 25
	 * @note		For requests using an OAuth Access Token for a particular member, only calendars the authorized user can view will be included
	 * @return		\IPS\Api\PaginatedResponse<IPS\calendar\Calendar>
	 */
	public function GETindex()
	{
		/* Return */
		return $this->_list();
	}

	/**
	 * GET /calendar/calendars/{id}
	 * Get specific calendar
	 *
	 * @param		int		$id			ID Number
	 * @throws		1L364/1	INVALID_ID	The calendar does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\calendar\Calendar
	 */
	public function GETitem( $id )
	{
		try
		{
			return $this->_view( $id );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '1L364/1', 404 );
		}
	}

	/**
	 * POST /calendar/calendars
	 * Create a calendar
	 *
	 * @apiclientonly
	 * @reqapiparam	string		title				The calendar title
	 * @apiparam	string		color				The calendar color (Hexadecimal)
	 * @apiparam	int			approve_events		0|1 Events must be approved?
	 * @apiparam	int			allow_comments		0|1 Allow comments
	 * @apiparam	int			approve_comments	0|1 Comments must be approved
	 * @apiparam	int			allow_reviews		0|1 Allow reviews
	 * @apiparam	int			approve_reviews		0|1 Reviews must be approved
	 * @apiparam	object		permissions			An object with the keys as permission options (view, read, add, reply, review, askrsvp, rsvp) and values as permissions to use (which may be * to grant access to all groups, or an array of group IDs to permit access to)
	 * @return		\IPS\calendar\Calendar
	 * @throws		1L364/2	NO_TITLE	A title for the calendar must be supplied
	 */
	public function POSTindex()
	{
		if ( !\IPS\Request::i()->title )
		{
			throw new \IPS\Api\Exception( 'NO_TITLE', '1L364/2', 400 );
		}

		return new \IPS\Api\Response( 201, $this->_create()->apiOutput( $this->member ) );
	}

	/**
	 * POST /calendar/calendars/{id}
	 * Edit a calendar
	 *
	 * @apiclientonly
	 * @reqapiparam	string		title				The calendar title
	 * @apiparam	string		color				The calendar color (Hexadecimal)
	 * @apiparam	int			approve_events		0|1 Events must be approved?
	 * @apiparam	int			allow_comments		0|1 Allow comments
	 * @apiparam	int			approve_comments	0|1 Comments must be approved
	 * @apiparam	int			allow_reviews		0|1 Allow reviews
	 * @apiparam	int			approve_reviews		0|1 Reviews must be approved
	 * @apiparam	object		permissions			An object with the keys as permission options (view, read, add, reply, review, askrsvp, rsvp) and values as permissions to use (which may be * to grant access to all groups, or an array of group IDs to permit access to)
	 * @param		int		$id			ID Number
	 * @return		\IPS\calendar\Calendar
	 */
	public function POSTitem( $id )
	{
		$class = $this->class;
		$calendar = $class::load( $id );

		return new \IPS\Api\Response( 200, $this->_createOrUpdate( $calendar )->apiOutput( $this->member ) );
	}

	/**
	 * DELETE /calendar/calendars/{id}
	 * Delete a calendar
	 *
	 * @apiclientonly
	 * @param		int		$id			ID Number
	 * @return		void
	 */
	public function DELETEitem( $id )
	{
		return $this->_delete( $id );
	}

	/**
	 * Create or update node
	 *
	 * @param	\IPS\node\Model	$calendar				The node
	 * @return	\IPS\node\Model
	 */
	protected function _createOrUpdate( \IPS\Node\Model $calendar )
	{
		if ( \IPS\Request::i()->title )
		{
			\IPS\Lang::saveCustom( 'calendar', 'calendar_calendar_' . $calendar->id, \IPS\Request::i()->title );
			$calendar->title_seo	= \IPS\Http\Url\Friendly::seoTitle( \IPS\Request::i()->title );
		}

		if ( isset( \IPS\Request::i()->color ) )
		{
			$calendar->color = \IPS\Request::i()->color;
		}

		$calendar->moderate 		= (int) isset( \IPS\Request::i()->approve_events ) ? \IPS\Request::i()->approve_events : 0;
		$calendar->allow_comments	= (int) isset( \IPS\Request::i()->allow_comments ) ? \IPS\Request::i()->allow_comments : 0;
		$calendar->comment_moderate = (int) isset( \IPS\Request::i()->approve_comments ) ? \IPS\Request::i()->approve_comments : 0;
		$calendar->allow_reviews 	= (int) isset( \IPS\Request::i()->allow_reviews ) ? \IPS\Request::i()->allow_reviews : 0;
		$calendar->review_moderate	= (int) isset( \IPS\Request::i()->approve_reviews ) ? \IPS\Request::i()->approve_reviews : 0;

		return parent::_createOrUpdate( $calendar );
	}
}