<?php
/**
 * @brief		GraphQL: Core controller
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		7 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Core controller for GraphQL API
 * @todo maybe this shouldn't be a class since it only has a static method?
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
			'activeUsers' => new \IPS\core\api\GraphQL\Queries\ActiveUsers(),
			'club' => new \IPS\core\api\GraphQL\Queries\Club(),
			'clubs' => new \IPS\core\api\GraphQL\Queries\Clubs(),
			'content' => new \IPS\core\api\GraphQL\Queries\Content(),
			'group' => new \IPS\core\api\GraphQL\Queries\Group(),
			'language' => new \IPS\core\api\GraphQL\Queries\Language(),
			'loginHandlers' => new \IPS\core\api\GraphQL\Queries\LoginHandlers(),
			'me' => new \IPS\core\api\GraphQL\Queries\Me(),
			'member' => new \IPS\core\api\GraphQL\Queries\Member(),
			'members' => new \IPS\core\api\GraphQL\Queries\Members(),
			'messengerConversation' => new \IPS\core\api\GraphQL\Queries\MessengerConversation(),
			'messengerConversations' => new \IPS\core\api\GraphQL\Queries\MessengerConversations(),
			'messengerFolders' => new \IPS\core\api\GraphQL\Queries\MessengerFolders(),
			'notificationTypes' => new \IPS\core\api\GraphQL\Queries\NotificationTypes(),
			'ourPicks' => new \IPS\core\api\GraphQL\Queries\OurPicks(),
			'popularContributors' => new \IPS\core\api\GraphQL\Queries\PopularContributors(),
			'search' => new \IPS\core\api\GraphQL\Queries\Search(),
			'settings' => new \IPS\core\api\GraphQL\Queries\Settings(),
			'stats' => new \IPS\core\api\GraphQL\Queries\Stats(),
			'stream' => new \IPS\core\api\GraphQL\Queries\Stream(),
			'streams' => new \IPS\core\api\GraphQL\Queries\Streams(),
		];
	}
}
