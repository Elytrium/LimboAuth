<?php
/**
 * @brief		Member Groups API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		4 Apr 2017
 */

namespace IPS\core\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Member Groups API
 */
class _groups extends \IPS\Api\Controller
{
	/**
	 * GET /core/groups
	 * Get list of groups
	 *
	 * @apiparam	int		page		Page number
	 * @apiparam	int		perPage		Number of results per page - defaults to 25
	 * @return		\IPS\Api\PaginatedResponse<IPS\Member\Group>
	 */
	public function GETindex()
	{
		/* Where clause */
		$where = array();

		/* Return */
		return new \IPS\Api\PaginatedResponse(
			200,
			\IPS\Db::i()->select( '*', 'core_groups', $where, "g_id asc" ),
			isset( \IPS\Request::i()->page ) ? \IPS\Request::i()->page : 1,
			'IPS\Member\Group',
			\IPS\Db::i()->select( 'COUNT(*)', 'core_groups', $where )->first(),
			$this->member,
			isset( \IPS\Request::i()->perPage ) ? \IPS\Request::i()->perPage : NULL
		);
	}

	/**
	 * GET /core/groups/{id}
	 * Get information about a specific group
	 *
	 * @param		int		$id			ID Number
	 * @throws		1C358/1	INVALID_ID	The group ID does not exist
	 * @return		\IPS\Member\Group
	 */
	public function GETitem( $id )
	{
		try
		{
			$group = \IPS\Member\Group::load( $id );
			if ( !$group->g_id )
			{
				throw new \OutOfRangeException;
			}

			return new \IPS\Api\Response( 200, $group->apiOutput( $this->member ) );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '1C358/1', 404 );
		}
	}

	/**
	 * DELETE /core/groups/{id}
	 * Deletes a group
	 *
	 * @apiclientonly
	 * @param		int		$id			ID Number
	 * @throws		2C358/3	CANNOT_DELETE	The group can't be deleted
	 * @throws		1C358/2	INVALID_ID	The group ID does not exist
	 * @return		void
	 */
	public function DELETEitem( $id )
	{
		try
		{
			$group = \IPS\Member\Group::load( $id );
			if ( !$group->g_id )
			{
				throw new \OutOfRangeException;
			}

			if( !$group->canDelete() )
			{
				throw new \IPS\Api\Exception( 'CANNOT_DELETE', '2C358/3', 403 );
			}

			$group->delete();

			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '1C358/2', 404 );
		}
	}
}