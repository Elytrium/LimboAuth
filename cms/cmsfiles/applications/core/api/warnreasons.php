<?php
/**
 * @brief		Warn Reasons API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		5 June 2018
 */

namespace IPS\core\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Warn Reasons API
 */
class _warnreasons extends \IPS\Node\Api\NodeController
{
	/**
	 * Class
	 */
	protected $class = 'IPS\core\Warnings\Reason';

	/**
	 * GET /core/warnreasons
	 * Get list of warn reasons
	 *
	 * @apiparam	int		page			Page number
	 * @apiparam	int		perPage			Number of results per page - defaults to 25
	 * @return		\IPS\Api\PaginatedResponse<IPS\core\Warnings\Reason>
	 * @throws		1C292/S	NO_PERMISSION	The current authorized user does not have permission to issue warnings and as such cannot view the list of warn reasons
	 */
	public function GETindex()
	{
		/* Check permissions */
		if( $this->member AND ( !$this->member->modPermission('mod_can_warn') OR !$this->member->modPermission('mod_see_warn') ) )
		{
			throw new \IPS\Api\Exception( 'NO_PERMISSION', '1C292/S', 403 );
		}

		/* Return */
		return $this->_list();
	}

	/**
	 * GET /core/warnreasons/{id}
	 * Get specific warn reason
	 *
	 * @apiclientonly
	 * @param		int		$id			ID Number
	 * @throws		1C385/1	INVALID_ID	The warn reason does not exist
	 * @return		\IPS\core\Warnings\Reason
	 */
	public function GETitem( $id )
	{
		try
		{
			return $this->_view( $id );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '1C385/1', 404 );
		}
	}

	/**
	 * POST /core/warnreasons
	 * Create a warn reason
	 *
	 * @apiclientonly
	 * @reqapiparam	string		name				Name for the warn reason
	 * @apiparam	string		defaultNotes		Default notes to associate with the warn reason
	 * @apiparam	int			points				Default points to be issued with the warn reason
	 * @apiparam	bool		pointsOverride		Whether or not moderators can override the default points
	 * @apiparam	bool		pointsAutoRemove	Whether or not to automatically remove points (defaults to false). If true, you must supply removePoints.
	 * @apiparam	string|null		removePoints		Timeframe to remove points after as a date interval (e.g. P2D for 2 days or PT6H for 6 hours)
	 * @apiparam	bool		removeOverride		Whether or not moderators can override the default points removal configuration
	 * @return		\IPS\core\Warnings\Reason
	 * @throws		1C385/2	INVALID_POINTS_EXPIRATION	Points were specified as automatically removing but the removal time period was not supplied or is invalid
	 * @throws		1C385/4	NO_NAME		A name for the warn reason must be supplied
	 */
	public function POSTindex()
	{
		if( !\IPS\Request::i()->name )
		{
			throw new \IPS\Api\Exception( 'NO_NAME', '1C385/4', 400 );
		}

		if( isset( \IPS\Request::i()->pointsAutoRemove ) AND \IPS\Request::i()->pointsAutoRemove AND ( !isset( \IPS\Request::i()->removePoints ) OR !\IPS\Request::i()->removePoints ) )
		{
			throw new \IPS\Api\Exception( 'INVALID_POINTS_EXPIRATION', '1C385/2', 400 );
		}

		return new \IPS\Api\Response( 201, $this->_create()->apiOutput( $this->member ) );
	}

	/**
	 * POST /core/warnreasons/{id}
	 * Edit a warn reason
	 *
	 * @apiclientonly
	 * @reqapiparam	string		name				Name for the warn reason
	 * @apiparam	string		defaultNotes		Default notes to associate with the warn reason
	 * @apiparam	int			points				Default points to be issued with the warn reason
	 * @apiparam	bool		pointsOverride		Whether or not moderators can override the default points
	 * @apiparam	bool		pointsAutoRemove	Whether or not to automatically remove points (defaults to false). If true, you must supply removePoints.
	 * @apiparam	string|null		removePoints		Timeframe to remove points after as a date interval (e.g. P2D for 2 days or PT6H for 6 hours)
	 * @apiparam	bool		removeOverride		Whether or not moderators can override the default points removal configuration
	 * @param		int		$id			ID Number
	 * @return		\IPS\core\Warnings\Reason
	 * @throws		1C385/3	INVALID_POINTS_EXPIRATION	Points were specified as automatically removing but the removal time period was not supplied or is invalid
	 */
	public function POSTitem( $id )
	{
		if( isset( \IPS\Request::i()->pointsAutoRemove ) AND \IPS\Request::i()->pointsAutoRemove AND ( !isset( \IPS\Request::i()->removePoints ) OR !\IPS\Request::i()->removePoints ) )
		{
			throw new \IPS\Api\Exception( 'INVALID_POINTS_EXPIRATION', '1C385/3', 400 );
		}

		$class	= $this->class;
		$reason	= $class::load( $id );

		return new \IPS\Api\Response( 200, $this->_createOrUpdate( $reason )->apiOutput( $this->member ) );
	}

	/**
	 * DELETE /core/warnreasons/{id}
	 * Delete a warn reason
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
	 * @param	\IPS\node\Model	$reason				The node
	 * @return	\IPS\node\Model
	 */
	protected function _createOrUpdate( \IPS\Node\Model $reason )
	{
		if( \IPS\Request::i()->name )
		{
			\IPS\Lang::saveCustom( 'core', 'core_warn_reason_' . $reason->id, \IPS\Request::i()->name );
		}

		$reason->points				= (int) \IPS\Request::i()->points;
		$reason->points_override	= (bool) \IPS\Request::i()->pointsOverride;
		$reason->remove_override	= (bool) \IPS\Request::i()->removeOverride;
		$reason->notes				= \IPS\Request::i()->notes;

		if( isset( \IPS\Request::i()->pointsAutoRemove ) OR isset( \IPS\Request::i()->removePoints ) )
		{
			if( !\IPS\Request::i()->pointsAutoRemove )
			{
				$reason->remove	= -1;
				$reason->remove_unit = NULL;
			}
			else
			{
				$reason->remove	= (int) str_ireplace( array( 'P', 'T', 'H' ), '', \IPS\Request::i()->removePoints );

				if( mb_strpos( \IPS\Request::i()->removePoints, 'PT' ) === 0 )
				{
					$reason->remove_unit = 'h';
				}
				else
				{
					$reason->remove_unit = 'd';
				}
			}
		}

		return parent::_createOrUpdate( $reason );
	}
}