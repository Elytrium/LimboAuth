<?php
/**
 * @brief		GraphQL: Blog Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		22 Oct 2022
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\calendar\api\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * CalendarType for GraphQL API
 */
class _CalendarType extends \IPS\Node\Api\GraphQL\NodeType
{
    /*
     * @brief 	The item classname we use for this type
     */
    protected static $nodeClass	= \IPS\calendar\Calendar::class;

    /*
     * @brief 	GraphQL type name
     */
    protected static $typeName = 'calendar_Calendar';

    /*
     * @brief 	GraphQL type description
     */
    protected static $typeDescription = 'A calendar';

    /*
     * @brief 	Follow data passed in to FollowType resolver
     */
    protected static $followData = array('app' => 'calendar', 'area' => 'calendar');


    /**
     * Get the item type that goes with this node type
     *
     * @return	ObjectType
     */
    public static function getItemType()
    {
        return \IPS\calendar\api\GraphQL\TypeRegistry::event();
    }
}

