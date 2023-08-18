<?php
/**
 * @brief		Purchases API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		10 Dec 2015
 */

namespace IPS\nexus\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Purchases API
 */
class _purchases extends \IPS\Api\Controller
{
	/**
	 * GET /nexus/purchases
	 * Get list of purchases
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, only the members own purchases will be included
	 * @apiparam	string	customers			Comma-delimited list of customer IDs - if provided, only invoices belonging to those customers are returned. Ignored for requests using an OAuth Access Token for a particular member
	 * @apiparam	int		active				If 1, only active purchases are returned. If 0, only inactive
	 * @apiparam	int		canceled			If 1, only canceled purchases are returned. If 0, only not canceled
	 * @apiparam	string	itemApp				If specified, only purchases with this application key are returned
	 * @apiparam	string	itemType			If specified, only purchases with this item type are returned
	 * @apiparam	int		itemId				If specified, only purchases with this item ID are returned
	 * @apiparam	int		parent				If specified, only purchases with which are children of the purchase with the ID specified are returned
	 * @apiparam	int		show				If 1, only purchases which show in the Admin CP are returned, if 0, only purchases which do not
	 * @apiparam	string	sortBy				What to sort by. Can be 'start' (for purchase date), 'expire' (for the epiry date) or do not specify for ID
	 * @apiparam	string	sortDir				Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page				Page number
	 * @apiparam	int		perPage				Number of results per page - defaults to 25
	 * @return		\IPS\Api\PaginatedResponse<IPS\nexus\Purchase>
	 */
	public function GETindex()
	{
		/* Where clause */
		$where = array();

		/* Get only the purchases from active applications */
		$where[] = array( "ps_app IN('" . implode( "','", array_keys( \IPS\Application::enabledApplications() ) ) . "')" );

		/* Customers */
		if ( $this->member )
		{
			$where[] = array( 'ps_member=?', $this->member->member_id );
		}
		elseif ( isset( \IPS\Request::i()->customers ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'ps_member', array_map( 'intval', array_filter( explode( ',', \IPS\Request::i()->customers ) ) ) ) );
		}

		/* Status */
		if ( isset( \IPS\Request::i()->active ) )
		{
			$where[] = array( 'ps_active=?', \intval( \IPS\Request::i()->active ) );
		}
		if ( isset( \IPS\Request::i()->canceled ) )
		{
			$where[] = array( 'ps_cancelled=?', \intval( \IPS\Request::i()->canceled ) );
		}
		
		/* Item */
		if ( isset( \IPS\Request::i()->itemApp ) )
		{
			$where[] = array( 'ps_app=?', \IPS\Request::i()->itemApp );
		}
		if ( isset( \IPS\Request::i()->itemType ) )
		{
			$where[] = array( 'ps_type=?', \IPS\Request::i()->itemType );
		}
		if ( isset( \IPS\Request::i()->itemId ) )
		{
			$where[] = array( 'ps_item_id=?', \IPS\Request::i()->itemId );
		}
		
		/* Parent */
		if ( isset( \IPS\Request::i()->parent ) )
		{
			$where[] = array( 'ps_parent=?', \intval( \IPS\Request::i()->parent ) );
		}
		
		/* Show */
		if ( isset( \IPS\Request::i()->show ) )
		{
			$where[] = array( 'ps_show=?', \intval( \IPS\Request::i()->show ) );
		}
						
		/* Sort */
		if ( isset( \IPS\Request::i()->sortBy ) and \in_array( \IPS\Request::i()->sortBy, array( 'start', 'expire' ) ) )
		{
			$sortBy = 'ps_' . \IPS\Request::i()->sortBy;
		}
		else
		{
			$sortBy = 'ps_id';
		}
		$sortDir = ( isset( \IPS\Request::i()->sortDir ) and \in_array( mb_strtolower( \IPS\Request::i()->sortDir ), array( 'asc', 'desc' ) ) ) ? \IPS\Request::i()->sortDir : 'asc';
		
		/* Return */
		return new \IPS\Api\PaginatedResponse(
			200,
			\IPS\Db::i()->select( '*', 'nexus_purchases', $where, "{$sortBy} {$sortDir}" ),
			isset( \IPS\Request::i()->page ) ? \IPS\Request::i()->page : 1,
			'IPS\nexus\Purchase',
			\IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', $where )->first(),
			$this->member,
			isset( \IPS\Request::i()->perPage ) ? \IPS\Request::i()->perPage : NULL
		);
	}
	
	/**
	 * GET /nexus/purchases/{id}
	 * Get information about a specific purchase
	 *
	 * @param		int		$id			ID Number
	 * @throws		2X310/1	INVALID_ID	The purchase ID does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\nexus\Purchase
	 */
	public function GETitem( $id )
	{
		try
		{			
			$object = \IPS\nexus\Purchase::load( $id );
			if ( $this->member and !$object->canView( $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			
			return new \IPS\Api\Response( 200, $object->apiOutput( $this->member ) );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2X309/1', 404 );
		}
	}
	
	/**
	 * POST /nexus/purchases/{id}
	 * Update custom fields for a purchase
	 *
	 * @apiclientonly
	 * @apiparam	object	customFields	Values for custom fields
	 * @param		int		$id			ID Number
	 * @throws		2X309/1	INVALID_ID	The purchase ID does not exist
	 * @return		\IPS\nexus\Purchase
	 */
	public function POSTitem( $id )
	{
		try
		{			
			$purchase =  \IPS\nexus\Purchase::load( $id );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2X309/1', 404 );
		}
		
		if ( isset( \IPS\Request::i()->customFields ) )
		{
			$customFields = $purchase->custom_fields;
			foreach ( \IPS\Request::i()->customFields as $k => $v )
			{
				$customFields[ $k ] = $v;
			}
			$purchase->custom_fields = $customFields;
		}
		
		$purchase->save();
		
		return new \IPS\Api\Response( 200, $purchase->apiOutput( $this->member ) );
	}
}