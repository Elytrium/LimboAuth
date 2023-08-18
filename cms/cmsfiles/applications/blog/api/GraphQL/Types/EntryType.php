<?php
/**
 * @brief		GraphQL: Topic Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\blog\api\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\_TypeRegistry;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * Entry for GraphQL API
 */
class _EntryType extends \IPS\Content\Api\GraphQL\ItemType
{
    /*
     * @brief 	The item classname we use for this type
     */
    protected static $itemClass	= \IPS\blog\Entry::class;

    /*
     * @brief 	GraphQL type name
     */
    protected static $typeName = 'blog_Entry';

    /*
     * @brief 	GraphQL type description
     */
    protected static $typeDescription = 'A blog entry';

    /*
     * @brief 	Follow data passed in to FollowType resolver
     */
    protected static $followData = array('app' => 'blogs', 'area' => 'entry');

    /**
     * Get the comment type that goes with this item type
     *
     * @return	ObjectType
     */
    protected static function getCommentType()
    {
        return \IPS\blog\api\GraphQL\TypeRegistry::comment();
    }
}
