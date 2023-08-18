<?php
/**
 * @brief		GraphQL: Topics query
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 May 2017
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
 * Topics query for GraphQL API
 */
class _Entries
{
    /*
     * @brief 	Query description
     */
    public static $description = "Returns a list of blog entries";

    /*
     * Query arguments
     */
    public function args(): array
    {
        return array(
            'blogs' => TypeRegistry::listOf( TypeRegistry::int() ),
            'offset' => [
                'type' => TypeRegistry::int(),
                'defaultValue' => 0
            ],
            'limit' => [
                'type' => TypeRegistry::int(),
                'defaultValue' => 25
            ],
            'orderBy' => [
                'type' => TypeRegistry::eNum([
                    'name' => 'blog_order_by',
                    'description' => 'Fields on which event can be sorted',
                    'values' => \IPS\blog\api\GraphQL\Types\EntryType::getOrderByOptions()
                ]),
                'defaultValue' => NULL // will use default sort option
            ],
            'orderDir' => [
                'type' => TypeRegistry::eNum([
                    'name' => 'entries_order_dir',
                    'description' => 'Directions in which items can be sorted',
                    'values' => [ 'ASC', 'DESC' ]
                ]),
                'defaultValue' => 'DESC'
            ],
            'honorPinned' => [
                'type' => TypeRegistry::boolean(),
                'defaultValue' => true
            ]
        );
    }

    /**
     * Return the query return type
     */
    public function type()
    {
        return TypeRegistry::listOf( \IPS\blog\api\GraphQL\TypeRegistry::entry() );
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
        \IPS\blog\Blog::loadIntoMemory('view', \IPS\Member::loggedIn() );

        $blogIds = [];

        /* Are we filtering by blogs? */
        if( isset( $args['blogs'] ) && \count( $args['blogs'] ) )
        {
            foreach( $args['blogs'] as $id )
            {
                $blog = \IPS\blog\Blog::loadAndCheckPerms( $id );
                $blogIds[] = $blog->id;
            }

            if( \count( $blogIds ) )
            {
                $where['container'][] = array( \IPS\Db::i()->in( 'blog_blogs.blog_id', array_filter( $blogIds ) ) );
            }
        }

        /* Get sorting */
        try
        {
            if( $args['orderBy'] === NULL )
            {
                $orderBy = 'last_post';
            }
            else
            {
                $orderBy = \IPS\blog\Entry::$databaseColumnMap[ $args['orderBy'] ];
            }

            if( $args['orderBy'] === 'last_comment' )
            {
                $orderBy = \is_array( $orderBy ) ? array_pop( $orderBy ) : $orderBy;
            }
        }
        catch (\Exception $e)
        {
            $orderBy = 'last_post';
        }

        $sortBy = \IPS\blog\Entry::$databaseTable . '.' . \IPS\blog\Entry::$databasePrefix . "{$orderBy} {$args['orderDir']}";
        $offset = max( $args['offset'], 0 );
        $limit = min( $args['limit'], 50 );

        /* Figure out pinned status */
        if ( $args['honorPinned'] )
        {
            $column = \IPS\blog\Entry::$databaseTable . '.' . \IPS\blog\Entry::$databasePrefix . \IPS\blog\Entry::$databaseColumnMap['pinned'];
            $sortBy = "{$column} DESC, {$sortBy}";
        }

        return \IPS\blog\Entry::getItemsWithPermission( $where, $sortBy, array( $offset, $limit ), 'read' );
    }
}
