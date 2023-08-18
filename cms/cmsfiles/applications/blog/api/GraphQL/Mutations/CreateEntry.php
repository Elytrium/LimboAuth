<?php
/**
 * @brief		GraphQL: Create blog entry mutation
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		22 oct 2022
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\blog\api\GraphQL\Mutations;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * Create blog entry mutation for GraphQL API
 */
class _CreateEntry extends \IPS\Content\Api\GraphQL\ItemMutator
{
    /**
     * Class
     */
    protected $class = \IPS\blog\Entry::class;

    /*
     * @brief 	Query description
     */
    public static $description = "Create a new blog entry";

    /*
     * Mutation arguments
     */
    public function args(): array
    {
		return [
			'blog' =>  TypeRegistry::nonNull( TypeRegistry::id() )
		];
    }

    /**
     * Return the mutation return type
     */
    public function type()
    {
        return \IPS\blog\api\GraphQL\TypeRegistry::entry();
    }

    /**
     * Resolves this mutation
     *
     * @param 	mixed 	Value passed into this resolver
     * @param 	array 	Arguments
     * @param 	array 	Context values
     * @return	\IPS\blog\Blog
     */
    public function resolve($val, $args, $context, $info)
    {
        /* Get blog */
        try
        {
            $blog = \IPS\blog\Blog::loadAndCheckPerms( $args['blogID'] );
        }
        catch ( \OutOfRangeException $e )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'NO_BLOG', '1B300/1_graphql', 400 );
        }

        /* Check permission */
        if ( !$blog->can( 'add', \IPS\Member::loggedIn() ) )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'NO_PERMISSION', '1B300/A_graphql', 403 );
        }

        /* Check we have a title and a post */
        if ( !$args['title'] )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'NO_TITLE', '1B300/4_graphql', 400 );
        }
        if ( !$args['content'] )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'NO_POST', '1B300/5_grapqhl', 400 );
        }


        return $this->_create( $args, $blog, $args['postKey'] ?? NULL );
    }
}
