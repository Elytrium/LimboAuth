<?php
/**
 * @brief		GraphQL: React to a post mutation
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 May 2017
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
 * React to post mutation for GraphQL API
 */
class _PostReaction extends \IPS\Content\Api\GraphQL\CommentMutator
{
	/**
	 * Class
	 */
	protected $class = 'IPS\forums\Topic\Post';

	/*
	 * @brief 	Query description
	 */
	public static $description = "React to a post";

	/*
	 * Mutation arguments
	 */
	public function args(): array
	{
		return [
			'postID' => TypeRegistry::nonNull( TypeRegistry::id() ),
			'reactionID' => TypeRegistry::int(),
			'removeReaction' => TypeRegistry::boolean()
		];
	}

	/**
	 * Return the mutation return type
	 */
	public function type() 
	{
		return \IPS\forums\api\GraphQL\TypeRegistry::post();
	}

	/**
	 * Resolves this mutation
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @param 	array 	Arguments
	 * @param 	array 	Context values
	 * @return	\IPS\forums\Topic\Post
	 */
	public function resolve($val, $args, $context, $info)
	{
		/* Get topic */
		try
		{
			$post = \IPS\forums\Topic\Post::loadAndCheckPerms( $args['postID'] );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_POST', '1F295/1_graphl', 403 );
		}

		/* Do it */
		if( isset( $args['removeReaction'] ) && $args['removeReaction'] )
		{
			$this->_unreactComment( $post );
		}
		else
		{
			$this->_reactComment( $args['reactionID'], $post );
		}

		return $post;
	}
}
