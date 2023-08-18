<?php
/**
 * @brief		Base class for Content Items
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		28 Aug 2018
 */

namespace IPS\Content\Api\GraphQL;
use GraphQL\Type\Definition\UnionType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Base mutator class for Content Items
 */
class _ContentType extends UnionType
{
	public function __construct()
	{
		$config = [
			'name' => 'core_Content',
			'description' => 'A union type that returns a CommentType, ItemType or @todo ReviewType depending on the object passed to it',
			'types' => [
				\IPS\Content\Api\GraphQL\TypeRegistry::comment(),
				\IPS\Content\Api\GraphQL\TypeRegistry::item()
			],
			'resolveType' => function ($content) {
				if ( $content instanceof \IPS\Content\Comment )
				{
					return \IPS\Content\Api\GraphQL\TypeRegistry::comment();
				}
				elseif( $content instanceof \IPS\Content\Item )
				{
					return \IPS\Content\Api\GraphQL\TypeRegistry::item();
				}
			}
		];

		parent::__construct($config);
	}
}