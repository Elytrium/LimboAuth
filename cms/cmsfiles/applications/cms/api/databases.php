<?php
/**
 * @brief		Pages Databases API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		21 Feb 2020
 */

namespace IPS\cms\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Pages Databases API
 */
class _databases extends \IPS\Node\Api\NodeController
{
	/**
	 * Class
	 */
	protected $class = 'IPS\cms\Databases';

	/**
	 * GET /cms/databases
	 * Get list of databases
	 *
	 * @apiclientonly
	 * @return		\IPS\Api\PaginatedResponse<IPS\cms\Databases>
	 */
	public function GETindex()
	{
		return $this->_list();
	}

	/**
	 * GET /cms/databases/{id}
	 * Get specific database
	 *
	 * @apiclientonly
	 * @param		int		$id			ID Number
	 * @return		\IPS\cms\Databases
	 */
	public function GETitem( $id )
	{
		return $this->_view( $id );
	}
}