<?php
/**
 * @brief		GraphQL: Mark a topic read mutation
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		28 Nov 2018
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\forums\api\GraphQL\Mutations;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Mark topic read mutation for GraphQL API
 */
class _MarkTopicRead extends \IPS\Content\Api\GraphQL\ItemMutator
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Mark a topic as read";

	/*
	 * Mutation arguments
	 */
	public function args(): array
	{
		return [
			'id' => TypeRegistry::nonNull( TypeRegistry::id() )
		];
	}

	/**
	 * Return the mutation return type
	 */
	public function type() 
	{
		return \IPS\forums\api\GraphQL\TypeRegistry::topic();
	}

	/**
	 * Resolves this mutation
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @param 	array 	Arguments
	 * @param 	array 	Context values
	 * @return	\IPS\forums\Forum
	 */
	public function resolve($val, $args)
	{
		try
		{
			$topic = \IPS\forums\Topic::loadAndCheckPerms( $args['id'] );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_TOPIC', 'GQL/0005/1', 400 );
		}

		if( !$topic->can('read') )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'INVALID_ID', 'GQL/0005/2', 403 );
		}

		$this->_markRead( $topic );
		return $topic;
	}
}
