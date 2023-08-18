<?php
/**
 * @brief		GraphQL: Core mutations
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\Types;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Core mutationss GraphQL API
 */
abstract class _Mutation
{
	/**
	 * Get the supported query types in this app
	 *
	 * @return	array
	 */
	public static function mutations()
	{
		return [
			'follow' => new \IPS\core\api\GraphQL\Mutations\Follow(),
			'unfollow' => new \IPS\core\api\GraphQL\Mutations\Unfollow(),
			'markNotificationRead' => new \IPS\core\api\GraphQL\Mutations\MarkNotificationRead(),
			'uploadAttachment' => new \IPS\core\api\GraphQL\Mutations\UploadAttachment(),
			'deleteAttachment' => new \IPS\core\api\GraphQL\Mutations\DeleteAttachment(),
			'ignoreMember' => new \IPS\core\api\GraphQL\Mutations\IgnoreUser(),
			'changeNotificationSetting' => new \IPS\core\api\GraphQL\Mutations\ChangeNotificationSetting(),
			'leaveConversation' => new \IPS\core\api\GraphQL\Mutations\Messenger\LeaveConversation(),
			'removeConversationUser' => new \IPS\core\api\GraphQL\Mutations\Messenger\RemoveConversationUser(),
			'addConversationUser' => new \IPS\core\api\GraphQL\Mutations\Messenger\AddConversationUser(),
		];
	}
}
