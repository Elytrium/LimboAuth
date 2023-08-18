<?php
/**
 * @brief		GraphQL: Forums queries
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\forums\api\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\Types;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Forums queries for GraphQL API
 */
abstract class _Query
{
	/**
	 * Get the supported query types in this app
	 *
	 * @return	array
	 */
	public static function queries()
	{
		return [
			'forums' => new \IPS\forums\api\GraphQL\Queries\Forums(),
			'forum' => new \IPS\forums\api\GraphQL\Queries\Forum(),
			'topics' => new \IPS\forums\api\GraphQL\Queries\Topics(),
			'topic' => new \IPS\forums\api\GraphQL\Queries\Topic(),
			'post' => new \IPS\forums\api\GraphQL\Queries\Post()
		];
	}
}
