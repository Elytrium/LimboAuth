<?php
/**
 * @brief		GraphQL: Post Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\blog\api\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * PostType for GraphQL API
 */
class _CommentType extends \IPS\Content\Api\GraphQL\CommentType
{
    /*
     * @brief 	The item classname we use for this type
     */
    protected static $commentClass	= \IPS\blog\Entry\Comment::class;

    /*
     * @brief 	GraphQL type name
     */
    protected static $typeName = 'blog_Comment';

    /*
     * @brief 	GraphQL type description
     */
    protected static $typeDescription = 'A blog comment';

    /**
     * Get the item type that goes with this item type
     *
     * @return	ObjectType
     */
    public static function getItemType()
    {
        return \IPS\blog\api\GraphQL\TypeRegistry::comment();
    }

    /**
     * Return the fields available in this type
     *
     * @return	array
     */
    public function fields()
    {
        $defaultFields = parent::fields();
        $postFields = array(
            'entry' => [
                'type' => \IPS\blog\api\GraphQL\TypeRegistry::entry(),
                'resolve' => function ($comment) {
                    return $comment->item();
                }
            ],
        );

        // Remove duplicated fields
        unset( $defaultFields['item'] );

        return array_merge( $defaultFields, $postFields );
    }
}
