<?php
/**
 * @brief		API output for custom fields groups
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		4 Mar 2016
 */

namespace IPS\core\ProfileFields\Api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * API output for custom fields groups
 */
class _FieldGroup
{
	/**
	 * @brief	Name
	 */
	protected $name;
	
	/**
	 * @brief	Values
	 */
	protected $values;
	
	/**
	 * Constructor
	 *
	 * @param	string	$name	Group name
	 * @param	array	$values	Values
	 */
	public function __construct( $name, $values )
	{
		$this->name = $name;
		$this->values = $values;
	}
	
	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse	string									name	Group name
	 * @apiresponse	[\IPS\core\ProfileFields\Api\Field]		fields	Fields
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		return array( 'name' => $this->name, 'fields' => array_map( function( $val ) use ( $authorizedMember ) {
			return $val->apiOutput( $authorizedMember );
		}, $this->values ) );
	}
}