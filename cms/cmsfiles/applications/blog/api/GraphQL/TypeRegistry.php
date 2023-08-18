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

namespace IPS\blog\api\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\Types;
use IPS\blog\api\GraphQL\Types\_BlogType;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}


class _TypeRegistry
{
    /**
     * The blog type instance
     * @var \IPS\blog\api\GraphQL\Types\BlogType
     */
    protected static $blog;

    /**
     * The blog entry type instance
     * @var \IPS\blog\api\GraphQL\Types\EntryType
     */
    protected static $entry;

    /**
     * The entry comment instance
     * @var \IPS\blog\api\GraphQL\Types\CommentType
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
     * @return BlogType
     */
    public static function blog(): \IPS\blog\api\GraphQL\Types\BlogType
    {
        return self::$blog ?: (self::$blog = new \IPS\blog\api\GraphQL\Types\BlogType());
    }

    /**
     * @return EntryType
     */
    public static function entry(): \IPS\blog\api\GraphQL\Types\EntryType
    {
        return self::$entry ?: (self::$entry = new \IPS\blog\api\GraphQL\Types\EntryType());
    }

    /**
     * @return CommentType
     */
    public static function comment(): \IPS\blog\api\GraphQL\Types\CommentType
    {
        return self::$comment ?: (self::$comment = new \IPS\blog\api\GraphQL\Types\CommentType());
    }
}