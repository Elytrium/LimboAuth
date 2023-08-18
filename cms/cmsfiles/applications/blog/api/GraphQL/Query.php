<?php
/**
 * @brief		GraphQL: Blog queries
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

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * Blog queries for GraphQL API
 */
abstract class _Query
{
    /**
     * Get the supported query types in this app
     *
     * @return	array
     */
    public static function queries(): array
    {
        return [
            'blogs' => new \IPS\blog\api\GraphQL\Queries\Blogs(),
            'blog' => new \IPS\blog\api\GraphQL\Queries\Blog(),
            'entries' => new \IPS\blog\api\GraphQL\Queries\Entries(),
           'entry' => new \IPS\blog\api\GraphQL\Queries\Entry(),
        ];
    }
}
