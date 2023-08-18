<?php
/**
 * @brief		GraphQL: Club Type
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
 * ClubType for GraphQL API
 */
class _ClubType extends ObjectType
{
    /**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_Club',
			'description' => 'Clubs',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::id(),
						'description' => "Club ID",
						'resolve' => function ($club) {
							return $club->id;
						}
					],
					'name' => [
						'type' => TypeRegistry::string(),
						'description' => "Club name",
						'resolve' => function ($club) {
							return $club->name;
						}
					],
					'type' => [
						'type' => TypeRegistry::string(),
						'description' => "Club type",
						'resolve' => function ($club) {
							return $club->type;
						}
					],
					'seoTitle' => [
						'type' => TypeRegistry::string(),
						'description' => "Club SEO title",
						'resolve' => function ($club) {
							return \IPS\Http\Url\Friendly::seoTitle( $club->name );
						}
					],
					'createdDate' => [
						'type' => TypeRegistry::int(),
						'description' => "Timestamp of club creation date",
						'resolve' => function ($club) {
							return $club->created->getTimestamp();
						}
					],
					'memberCount' => [
						'type' => TypeRegistry::int(),
						'description' => "Number of members in this club",
						'resolve' => function ($club) {
							return $club->members;
						}
					],
					'members' => [
						'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::member() ),
						'description' => "List of members in this club",
						'args' => [
							'type' => [
								'type' => TypeRegistry::eNum([
                                    'name' => 'club_member_type',
                                    'values' => ['all', 'leader', 'moderator', 'member']
                                ]),
								'defaultValue' => 'all'
							],
							'limit' => [
								'type' => TypeRegistry::int(),
								'defaultValue' => 25
							]
						],
						'resolve' => function ($club, $args) {
							return self::members( $club, $args );
						}
					],
					'owner' => [
						'type' => \IPS\core\api\GraphQL\TypeRegistry::member(),
						'description' => "Owner of this club",
						'resolve' => function ($club) {
							return $club->owner;
						}
					],
					'icon' => [
						'type' => TypeRegistry::string(),
						'description' => "Club icon image",
						'resolve' => function ($club) {
							return ( $club->profile_photo ) ? \IPS\File::get( 'core_Clubs', $club->profile_photo )->url : null;
						}
					],
					'coverPhoto' => [
						'type' => TypeRegistry::string(),
						'description' => "Club cover photo image",
						'resolve' => function ($club) {
							return ( $club->coverPhoto(FALSE)->file ) ? $club->coverPhoto(FALSE)->file : null;
						}
					],
					'isFeatured' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Club featured flag",
						'resolve' => function ($club) {
							return $club->featured;
						}
					],
					'about' => [
						'type' => TypeRegistry::string(),
						'description' => "Club description",
						'resolve' => function ($club) {
							return $club->about;
						}
					],
					'lastActivity' => [
						'type' => TypeRegistry::int(),
						'description' => "Timestamp of the last activity in this club",
						'resolve' => function ($club) {
							return $club->last_activity;
						}
					],
					'nodes' => [
						'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::clubNode() ),
						'description' => "List of nodes in this club",
						'resolve' => function ($club, $args) {
							return self::nodes( $club, $args );
						}
					]
				];
			}
		];

        parent::__construct($config);
	}

	/**
	 * Resolve nodes field
	 *
	 * @param 	\IPS\Member\Club
	 * @param 	array 	Arguments passed to this resolver
	 * @return	array
	 */
	protected static function nodes($club, $args)
	{
		return $club->nodes();
	}

	/**
	 * Resolve members field
	 *
	 * @param 	\IPS\Member\Club
	 * @param 	array 	Arguments passed to this resolver
	 * @return	array
	 */
	protected static function members($club, $args)
	{
		$memberType = ( $args['type'] == 'all' ) ? array( 'member', 'moderator', 'leader' ) : array( $args['type'] );
		$result = array();
		$limit = min( $args['limit'], 50 );
		$members = $club->members( $memberType, $limit, 'core_clubs_memberships.joined ASC', 2 );

		foreach( $members as $memberRow ){
			$result[] = \IPS\Member::constructFromData( $memberRow );
		}

		return $result;
	}

	/**
	 * Return the sort options available for this type
	 *
	 * @return array
	 */
	public static function getOrderByOptions(): array
	{
		return [
			'created', 'last_activity', 'name'
		];
	}
}
