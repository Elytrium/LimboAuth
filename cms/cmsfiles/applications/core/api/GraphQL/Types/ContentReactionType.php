<?php
/**
 * @brief		GraphQL: Content Reactions Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		7 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ContentReactionType for GraphQL API
 */
class _ContentReactionType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_Reaction',
			'description' => 'Content reactions',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::id(),
						'description' => "Reaction key. If this is a reaction given to content, this will be a unique key. If this is representing a particular reaction as a whole, it will be that reaction's ID.",
						'resolve' => function ($reaction) {
							return isset( $reaction['reaction'] ) ? $reaction['reaction']->id : $reaction['id'];
						}
					],
					'reactionId' => [
						'type' => TypeRegistry::int(),
						'description' => "The reaction's actual ID",
						'resolve' => function ($reaction) {
							return isset( $reaction['reaction'] ) ? $reaction['reaction']->id : $reaction['reactionId'];
						}
					],
					'count' => [
						'type' => TypeRegistry::int(),
						'description' => "Count of this reaction type",
						'resolve' => function ($reaction) {
							return isset( $reaction['count'] ) ? $reaction['count'] : 0;
						}
					],
					'image' => [
						'type' => TypeRegistry::string(),
						'description' => "Reaction image",
						'resolve' => function ($reaction) {
							$_reaction = isset( $reaction['reaction'] ) ? $reaction['reaction'] : \IPS\Content\Reaction::load( $reaction['reactionId'] );
							return (string) $_reaction->_icon->url;
						}
					],
					'name' => [
						'type' => TypeRegistry::string(),
						'description' => "Reaction name",
						'resolve' => function ($reaction) {
							$_reaction = isset( $reaction['reaction'] ) ? $reaction['reaction'] : \IPS\Content\Reaction::load( $reaction['reactionId'] );
							return \IPS\Member::loggedIn()->language()->addToStack("reaction_title_{$_reaction->id}", FALSE, array('escape' => TRUE));
						}
					]
				];
			}
		];

		parent::__construct($config);
	}
}
