<?php
/**
 * @brief		GraphQL: Attachment Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		22 May 2019
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
 * AttachmentType for GraphQL API
 */
class _AttachmentType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_Attachment',
			'description' => 'An attachment',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::id(),
						'description' => "The attachment ID",
						'resolve' => function( $attachment ) {
							return $attachment['attach_id'];
						}
					],
					'ext' => [
						'type' => TypeRegistry::string(),
						'description' => "The attachment's filename extension",
						'resolve' => function( $attachment ) {
							return $attachment['attach_ext'];
						}
					],
					'name' => [
						'type' => TypeRegistry::string(),
						'description' => "The attachment's original filename (not necessarily what is actually stored on disk)",
						'resolve' => function( $attachment ) {
							return $attachment['attach_file'];
						}
					],
					'size' => [
						'type' => TypeRegistry::int(),
						'description' => "Size of attachment in bytes",
						'resolve' => function( $attachment ) {
							return $attachment['attach_filesize'];
						}
					],
					'image' => [
						'type' => TypeRegistry::image(),
						'description' => "Image details (will be NULL for non-image files)",
						'resolve' => function( $attachment ) {
							if ( $attachment['attach_is_image'] )
							{
								return array(
									'url'		=> (string) \IPS\File::get( 'core_Attachment', $attachment['attach_location'] )->url,
									'width'		=> $attachment['attach_img_width'],
									'height'	=> $attachment['attach_img_height'],
								);
							}
							return NULL;
						}
					],
					'thumbnail' => [
						'type' => TypeRegistry::image(),
						'description' => "A thumbnail for the attachment (will be NULL for non-image files)",
						'resolve' => function( $attachment ) {
							if ( $attachment['attach_is_image'] )
							{
								if ( $attachment['attach_thumb_location'] )
								{
									return array(
										'url'		=> (string) \IPS\File::get( 'core_Attachment', $attachment['attach_thumb_location'] )->url,
										'width'		=> $attachment['attach_thumb_width'],
										'height'	=> $attachment['attach_thumb_height'],
									);
								}
								else
								{
									return array(
										'url'		=> (string) \IPS\File::get( 'core_Attachment', $attachment['attach_location'] )->url,
										'width'		=> $attachment['attach_img_width'],
										'height'	=> $attachment['attach_img_height'],
									);
								}
							}
							return NULL;
						}
					],
					'date' => [
						'type' => TypeRegistry::int(),
						'description' => "Timestamp of when the attachment was uploaded",
						'resolve' => function( $attachment ) {
							return $attachment['attach_date'];
						}
					],
					'uploader' => [
						'type' => \IPS\core\api\GraphQL\TypeRegistry::member(),
						'description' => "Member who uploaded the attachment",
						'resolve' => function ( $attachment ) {
							return \IPS\Member::load( $attachment['attach_member_id'] );
						}
					],
				];
			}
		];

		parent::__construct($config);
	}
}
