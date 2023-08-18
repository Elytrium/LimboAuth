<?php
/**
 * @brief		GraphQL: Attachment Permissions Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		28 May 2019
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
 * AttachmentPermissionsType for GraphQL API
 */
class _AttachmentPermissionsType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_AttachmentPermissions',
			'description' => 'Details about what the user can attach',
			'fields' => function () {
				return [
					'consumedUploadSize' => [
						'type' => TypeRegistry::int(),
						'description' => "The maximum *combined* size (in bytes) remaining for attachments, or NULL for no restriction. Note: if 0 this means no more uploads are allowed. If the user deletes an attachment, you should query again for an updated value.",
						'resolve' => function( $data ) {	
							$currentPostUsage = 0;
							// @todo Currently this only considers postKey because the GraphQL doesn't support editing. When edit support is added we'll need to modify this query to do "OR ( location_key=? AND id=? AND id2=?... )"
							foreach ( \IPS\Db::i()->select( '*', 'core_attachments', array( 'attach_post_key=?', $data['postKey'] ) ) as $attachment )
							{
								$currentPostUsage += $attachment['attach_filesize'];
							}
							return $currentPostUsage;
						}
					],
				];
			}
		];

		parent::__construct($config);
	}
}