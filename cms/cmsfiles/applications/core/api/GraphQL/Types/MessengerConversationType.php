<?php
/**
 * @brief		GraphQL: Messenger conversation Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		25 Sep 2019
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
 * MessengerConversation for GraphQL API
 */
class _MessengerConversationType extends \IPS\Content\Api\GraphQL\ItemType
{
	/*
	 * @brief 	The item classname we use for this type
	 */
	protected static $itemClass	= '\IPS\core\Messenger\Conversation';

	/*
	 * @brief 	GraphQL type name
	 */
	protected static $typeName = 'core_MessengerConversation';

	/*
	 * @brief 	GraphQL type description
	 */
	protected static $typeDescription = 'A messenger conversation';

	/**
	 * Return the fields available in this type
	 *
	 * @return	array
	 */
	public function fields()
	{
        $defaultFields = parent::fields();
		$conversationFields = array(
            'folder' => [
                'type' => \IPS\core\api\GraphQL\TypeRegistry::messengerFolder(),
                'description' => "Returns the folder ID that this conversation exists in for the logged-in user",
                'resolve' => function ($message) {
                    $folderObj = \IPS\core\api\GraphQL\TypeRegistry::messengerFolder();
                    $folders = $folderObj->getMemberFolders();

                    if( isset( $folders[ $message->map['map_folder_id'] ] ) )
                    {
                        return $folders[ $message->map['map_folder_id'] ];
                    }

                    return NULL;
                }
            ],
            'participants' => [
                'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::messengerParticipant() ),
                'description' => "Returns a list of participants in this conversation",
                'resolve' => function ($message) {
                    return $message->maps();
                }
            ],
            'activeParticipants' => [
                'type' => TypeRegistry::int(),
                'description' => "Returns a count of active participants in this conversation",
                'resolve' => function ($message) {
                    return $message->activeParticipants;
                }
            ],
            'participantBlurb' => [
                'type' => TypeRegistry::string(),
                'description' => "A short blurb containing the names of the participants",
                'resolve' => function ($message) {
                    return $message->participantBlurb();
                }
            ],
            'lastMessage' => [
                'type' => \IPS\core\api\GraphQL\TypeRegistry::messengerReply(),
                'description' => "The most recent reply to the conversation",
                'resolve' => function ($message) {
                    return $message->comments( 1, 0, 'date', 'desc' );
                }
            ],
            'updated' => [
                'type' => TypeRegistry::int(),
                'description' => "Timestamp of when this conversation was last updated",
                'resolve' => function ($message) {
                    return $message->last_post_time;
                }
            ],
            'isUnread' => [
                'type' => TypeRegistry::boolean(),
                'description' => "Is this conversation unread?",
                'resolve' => function ($message) {
                    return (bool) $message->map['map_has_unread'];
                }
            ]
        );

        // Remove duplicated fields
        unset( $defaultFields['container'] );
        unset( $defaultFields['views'] );
        unset( $defaultFields['isLocked'] );
        unset( $defaultFields['isPinned'] );
        unset( $defaultFields['isFeatured'] );
        unset( $defaultFields['hiddenStatus'] );
        unset( $defaultFields['updated'] );
        unset( $defaultFields['follow'] );
        unset( $defaultFields['tags'] );
        unset( $defaultFields['hasPoll'] );
        unset( $defaultFields['poll'] );
        
        
		return array_merge( $defaultFields, $conversationFields );
	}

	/**
	 * Return item permission fields.
	 * Here we adjust the resolver for the commentInformation field to check whether this is
	 * a poll-only topic.
	 *
	 * @return	string|null
	 */
	public static function getItemPermissionFields()
	{
        $defaultFields = parent::getItemPermissionFields();

        // Messenger does not restrict based on locked status etc.
        $defaultFields['commentInformation']['resolve'] = function () {
            return NULL;
        };

        return $defaultFields;
	}

	/**
	 * Return the available sorting options
	 *
	 * @return	array
	 */
	public static function getOrderByOptions()
	{
        // Note: these options all get prefixed with 'mt_' when passed into the query.
		return array('last_post_time', 'start_time', 'replies');
	}

	/**
	 * Get the comment type that goes with this item type
	 *
	 * @return	ObjectType
	 */
	protected static function getCommentType()
	{
		return \IPS\core\api\GraphQL\TypeRegistry::messengerReply();
	}

	/**
	 * Resolve the comments field - overridden from ItemType
	 * If this is a question and order isn't date, then force order by votes
	 *
	 * @param 	\IPS\forums\Topic
	 * @param 	array 	Arguments passed to this resolver
	 * @return	array
	 */
	/*protected static function comments($topic, $args)
	{
		return parent::comments($topic, $args);
	}*/
}
