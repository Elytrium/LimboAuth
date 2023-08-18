<?php
/**
 * @brief		GraphQL: Entry query
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		22 Oct 2022
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\blog\api\GraphQL\Queries;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * Entry query for GraphQL API
 */
class _Entry
{
    /*
     * @brief 	Query description
     */
    public static $description = "Returns a Blog Entry";

    /*
     * Query arguments
     */
    public function args(): array
    {
        return array(
            'id' => TypeRegistry::nonNull( TypeRegistry::id() )
        );
    }

    /**
     * Return the query return type
     */
    public function type()
    {
        return \IPS\blog\api\GraphQL\TypeRegistry::entry();
    }

    /**
     * Resolves this query
     *
     * @param 	mixed 	Value passed into this resolver
     * @param 	array 	Arguments
     * @param 	array 	Context values
     * @return	\IPS\blog\Entry
     */
    public function resolve($val, $args, $context, $info)
    {
        try
        {
            $entry = \IPS\blog\Entry::loadAndCheckPerms( $args['id'] );
        }
        catch ( \OutOfRangeException $e )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'NO_TOPIC', '2B300/A_graphql', 400 );
        }

        if( !$entry->can('read') )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'NO_PERMISSION', '2B300/9_graphql', 403 );
        }

        return $entry;
    }
}
