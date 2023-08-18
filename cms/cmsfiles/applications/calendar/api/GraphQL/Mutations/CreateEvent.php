<?php
/**
 * @brief		GraphQL: Create entry mutation
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		22 oct 2022
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\calendar\api\GraphQL\Mutations;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * Create Event mutation for GraphQL API
 */
class _CreateEvent extends \IPS\Content\Api\GraphQL\ItemMutator
{
    /**
     * Class
     */
    protected $class = \IPS\calendar\Event::class;

    /*
     * @brief 	Query description
     */
    public static $description = "Create a new calendar event";

    /*
     * Mutation arguments
     */
    public function args(): array
    {
        $args = parent::args();
        $args['calendar'] = TypeRegistry::nonNull( TypeRegistry::id() );
        return $args;
    }

    /**
     * Return the mutation return type
     */
    public function type()
    {
        return \IPS\calendar\api\GraphQL\TypeRegistry::event();
    }

    /**
     * Resolves this mutation
     *
     * @param 	mixed 	Value passed into this resolver
     * @param 	array 	Arguments
     * @param 	array 	Context values
     * @return	\IPS\calendar\Event
     */
    public function resolve($val, $args, $context, $info)
    {
        /* Get calendar */
        try
        {
            $calendar = \IPS\calendar\Calendar::loadAndCheckPerms( $args['calendarID'] );
        }
        catch ( \OutOfRangeException $e )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'NO_CALENDAR', '1L296/6_graphql', 400 );
        }

        /* Check permission */
        if ( !$calendar->can( 'add', \IPS\Member::loggedIn() ) )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'NO_PERMISSION', '1L296/7_graphql', 403 );
        }

        /* Check we have a title and a post */
        if ( !$args['title'] )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'NO_TITLE', '1L296/8_graphql', 400 );
        }
        if ( !$args['content'] )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'NO_POST', '1L296/9_grapqhl', 400 );
        }

        if ( !$args['start'] )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'INVALID_START', '1L296/A_grapqhl', 400 );
        }

        return $this->_create( $args, $calendar, $args['postKey'] ?? NULL );
    }
}
