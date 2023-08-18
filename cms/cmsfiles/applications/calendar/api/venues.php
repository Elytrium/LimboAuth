<?php
/**
 * @brief		Calendar Venues API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		5 June 2018
 */

namespace IPS\calendar\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Calendar Venues API
 */
class _venues extends \IPS\Node\Api\NodeController
{
	/**
	 * Class
	 */
	protected $class = 'IPS\calendar\Venue';

	/**
	 * GET /calendar/venues
	 * Get list of venues
	 *
	 * @apiparam	int		page			Page number
	 * @apiparam	int		perPage			Number of results per page - defaults to 25
	 * @return		\IPS\Api\PaginatedResponse<IPS\calendar\Venue>
	 */
	public function GETindex()
	{
		/* Return */
		return $this->_list();
	}

	/**
	 * GET /calendar/venues/{id}
	 * Get specific venue
	 *
	 * @param		int		$id			ID Number
	 * @throws		1L384/1	INVALID_ID	The venue does not exist
	 * @return		\IPS\calendar\Venue
	 */
	public function GETitem( $id )
	{
		try
		{
			return $this->_view( $id );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '1L384/1', 404 );
		}
	}

	/**
	 * POST /calendar/venues
	 * Create a venue
	 *
	 * @apiclientonly
	 * @reqapiparam	string		title				The venue title
	 * @reqapiparam	\IPS\GeoLocation		address				The venue address (latitude and longitude do not need to be supplied and will be ignored)
	 * @apiparam	string		description			The venue description
	 * @return		\IPS\calendar\Venue
	 * @throws		1L384/2	NO_ADDRESS	An address is required for the venue but was not supplied
	 * @throws		1L384/3	INVALID_ADDRESS	An invalid address was supplied
	 * @throws		1L384/4	NO_TITLE	No title was supplied for the venue
	 */
	public function POSTindex()
	{
		if( !\IPS\Request::i()->address OR !\is_array( \IPS\Request::i()->address ) )
		{
			throw new \IPS\Api\Exception( 'NO_ADDRESS', '1L384/2', 400 );
		}
		else
		{
			/* Just check before we try to save */
			$this->_getGeoLocationObject();
		}

		if ( !\IPS\Request::i()->title )
		{
			throw new \IPS\Api\Exception( 'NO_TITLE', '1L384/4', 400 );
		}

		return new \IPS\Api\Response( 201, $this->_create()->apiOutput( $this->member ) );
	}

	/**
	 * POST /calendar/venues/{id}
	 * Edit a venue
	 *
	 * @apiclientonly
	 * @reqapiparam	string		title				The venue title
	 * @reqapiparam	\IPS\GeoLocation		address				The venue address (latitude and longitude do not need to be supplied and will be ignored)
	 * @apiparam	string		description			The venue description
	 * @param		int		$id			ID Number
	 * @return		\IPS\calendar\Venue
	 * @throws		1L384/2	NO_ADDRESS	An address is required for the venue but was not supplied
	 * @throws		1L384/3	INVALID_ADDRESS	An invalid address was supplied
	 * @throws		1L384/4	NO_TITLE	No title was supplied for the venue
	 */
	public function POSTitem( $id )
	{
		$class = $this->class;
		$venue = $class::load( $id );

		return new \IPS\Api\Response( 200, $this->_createOrUpdate( $venue )->apiOutput( $this->member ) );
	}

	/**
	 * DELETE /calendar/venues/{id}
	 * Delete a venue
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
	 * @param	\IPS\node\Model	$venue				The node
	 * @return	\IPS\node\Model
	 */
	protected function _createOrUpdate( \IPS\Node\Model $venue )
	{
		if( \IPS\Request::i()->address AND \is_array( \IPS\Request::i()->address ) )
		{
			$geoLocation = $this->_getGeoLocationObject();

			$venue->address = json_encode( $geoLocation );
		}

		if ( isset( \IPS\Request::i()->title ) )
		{
			\IPS\Lang::saveCustom( 'calendar', 'calendar_venue_' . $venue->id, \IPS\Request::i()->title );
			$venue->title_seo = \IPS\Http\Url\Friendly::seoTitle( \IPS\Request::i()->title );
		}

		if( isset( \IPS\Request::i()->description ) )
		{
			\IPS\Lang::saveCustom( 'calendar', 'calendar_venue_' . $venue->id .'_desc', \IPS\Request::i()->description );
		}

		return parent::_createOrUpdate( $venue );
	}

	/**
	 * Get geolocation object
	 *
	 * @return array
	 */
	protected function _getGeoLocationObject()
	{
		$geoLocation = new \IPS\GeoLocation;
		$geoLocation->addressLines = array();

		foreach( \IPS\Request::i()->address as $k => $v )
		{
			if( \in_array( $k, array( 'city', 'postalCode', 'region', 'addressLines', 'country' ) ) )
			{
				$geoLocation->$k = $v;
			}
		}

		try
		{
			$geoLocation->getLatLong();
		}
		catch( \BadMethodCallException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ADDRESS', '1L384/3', 400 );
		}

		return $geoLocation;
	}
}