<?php
/**
 * @brief		GraphQL: Messenger conversation query
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		25 Sep 2019
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Queries;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Messenger folders query for GraphQL API
 */
class _MessengerFolders
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Returns the member's messenger folders";

	/*
	 * Query arguments
	 */
	public function args(): array
	{
		return array(
			
		);
	}

	/**
	 * Return the query return type
	 */
	public function type() 
	{
		return TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::messengerFolder() );
	}

	/**
	 * Resolves this query
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @param 	array 	Arguments
	 * @param 	array 	Context values
	 * @return	\IPS\core\Messenger\Conversation
	 */
	public function resolve($val, $args, $context, $info)
	{
        if( !\IPS\Member::loggedIn()->member_id || \IPS\Member::loggedIn()->members_disable_pm )
        {
            return NULL;
        }

		$folderObj = \IPS\core\api\GraphQL\TypeRegistry::messengerFolder();
        return $folderObj->getMemberFolders();
	}
}
