<?php
/**
 * @brief		GraphQL: Messenger participant Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		27 Sep 2019
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
 * MessengerParticipant for GraphQL API
 */
class _MessengerParticipantType extends ObjectType
{
    /**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_MessengerParticipant',
			'description' => 'Messenger participant',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::id(),
						'description' => "Participant ID (this is not the member ID, but an ID relative to the conversation)",
						'resolve' => function ($map) {
							return $map['map_id'];
						}
                    ],
                    'member' => [
                        'type' => \IPS\core\api\GraphQL\TypeRegistry::member(),
                        'description' => "The member",
                        'resolve' => function ($map) {
                            if( $map['map_user_id'] )
                            {
                                return \IPS\Member::load( $map['map_user_id'] );
                            }

                            return NULL;
                        }
                    ],
                    'isActive' => [
                        'type' => TypeRegistry::boolean(),
                        'description' => 'Is the member active in this conversation?',
                        'resolve' => function ($map) {
                            return $map['map_user_active'];
                        }
                    ],
                    'isBanned' => [
                        'type' => TypeRegistry::boolean(),
                        'description' => 'Has the member been removed from this conversation?',
                        'resolve' => function ($map) {
                            return $map['map_user_banned'];
                        }
                    ],
                    'lastRead' => [
                        'type' => TypeRegistry::int(),
                        'description' => 'Timestamp of when the member last read this conversation',
                        'resolve' => function ($map) {
                            if( $map['map_read_time'] !== 0 )
                            {
                                return $map['map_read_time'];
                            }

                            return NULL;
                        }
                    ],
                    'leftDate' => [
                        'type' => TypeRegistry::int(),
                        'description' => 'The timestamp of when the participant left the conversation if they are no longer active',
                        'resolve' => function ($map) {
                            if( !$map['map_user_active'] && $map['map_left_time'] )
                            {
                                return $map['map_left_time'];
                            }

                            return NULL;
                        }
                    ],
                    'lastReply' => [
                        'type' => TypeRegistry::int(),
                        'description' => 'The timestamp of when the participant last replied',
                        'resolve' => function ($map) {
                            if( $map['map_last_topic_reply'] )
                            {
                                return $map['map_last_topic_reply'];
                            }

                            return NULL;
                        }
                    ]
				];
			}
		];

        parent::__construct($config);
	}
}
