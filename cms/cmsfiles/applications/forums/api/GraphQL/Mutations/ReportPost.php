<?php
/**
 * @brief		GraphQL: Report a post mutation
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		11 Jun 2019
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
 * Report post mutation for GraphQL API
 */
class _ReportPost extends \IPS\Content\Api\GraphQL\CommentMutator
{
	/**
	 * Class
	 */
	protected $class = 'IPS\forums\Topic\Post';

	/*
	 * @brief 	Query description
	 */
	public static $description = "Report a post";

	/*
	 * Mutation arguments
	 */
	public function args(): array
	{
		return [
			'id'		        => TypeRegistry::nonNull( TypeRegistry::id() ),
			'reason'		    => [
				'type' => TypeRegistry::int(),
				'defaultValue' => 0
			],
			'additionalInfo'    => TypeRegistry::string()
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
		/* Get post */
		try
		{
			$post = \IPS\forums\Topic\Post::loadAndCheckPerms( $args['id'] );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_POST', '1F295/1_graphl', 403 );
		}
		
		/* Do it */
		return $this->_reportComment( $args, $post );
	}
}
