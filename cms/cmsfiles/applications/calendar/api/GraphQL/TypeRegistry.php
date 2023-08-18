<?php
/**
 * @brief		GraphQL: Types registry
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		22 Oct 2022
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\calendar\api\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\Types;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}


class _TypeRegistry
{
    /**
     * The calendar type instance
     * @var \IPS\calendar\api\GraphQL\Types\CalendarType
     */
    protected static $calendar;

    /**
     * The event type instance
     * @var \IPS\calendar\api\GraphQL\Types\EventType
     */
    protected static $event;

    /**
     * The event comment instance
     * @var \IPS\calendar\api\GraphQL\Types\CalendarType
     */
    protected static $comment;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Defined to suppress static warnings
    }

    /**
     * @return CalendarType
     */
    public static function calendar() : \IPS\calendar\api\GraphQL\Types\CalendarType
    {
        return self::$calendar ?: (self::$calendar = new \IPS\calendar\api\GraphQL\Types\CalendarType());
    }

    /**
     * @return EventType
     */
    public static function event() : \IPS\calendar\api\GraphQL\Types\EventType
    {
        return self::$event ?: (self::$event = new \IPS\calendar\api\GraphQL\Types\EventType());
    }

    public static function comment() : \IPS\calendar\api\GraphQL\Types\CommentType
    {
        return self::$comment ?: ( self::$comment = new \IPS\calendar\api\GraphQL\Types\CommentType() );
    }
}