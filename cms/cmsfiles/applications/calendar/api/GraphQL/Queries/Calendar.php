<?php
/**
 * @brief		GraphQL: Forum query
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\calendar\api\GraphQL\Queries;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * Calendar query for GraphQL API
 */
class _calendar
{
    /*
     * @brief 	Query description
     */
    public static $description = "Returns a calendar";

    /*
     * Query arguments
     */
    public function args(): array
    {
        return array(
            'id' => TypeRegistry::nonNull( TypeRegistry::id() ),
        );
    }

    /**
     * Return the query return type
     */
    public function type()
    {
        return \IPS\calendar\api\GraphQL\TypeRegistry::calendar();
    }

    /**
     * Resolves this query
     *
     * @param 	mixed 	Value passed into this resolver
     * @param 	array 	Arguments
     * @param 	array 	Context values
     * @return	\IPS\calendar\Calendar
     */
    public function resolve($val, $args, $context, $info)
    {
        $calendar = \IPS\calendar\Calendar::load( $args['id'] );

        if( !$calendar->can( 'view', $context['member'] ) )
        {
            throw new \OutOfRangeException;
        }
        return $calendar;
    }
}
