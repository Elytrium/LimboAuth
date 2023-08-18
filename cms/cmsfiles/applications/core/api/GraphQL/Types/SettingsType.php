<?php
/**
 * @brief		GraphQL: Settings field Type
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
 * SettingsType for GraphQL API
 */
class _SettingsType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{

		$config = [
			'name' => 'core_Settings',
			'description' => 'System settings',
			'fields' => function () {
				return [
					// Invidival settings are specified here to ensure that only whitelisted settings 
					// are exposed via the API.
					
					/* -------------------------------- */
					/* Basic system settings */
					'base_url' => [
						'type' => TypeRegistry::string()
					],
					'tags_enabled' => [
						'type' => TypeRegistry::boolean()
					],
					'tags_min' => [
						'type' => TypeRegistry::int(),
					],
					'tags_max' => [
						'type' => TypeRegistry::int(),
					],
					'tags_min_req' => [
						'type' => TypeRegistry::boolean()
					],
					'tags_len_min' => [
						'type' => TypeRegistry::int(),
					],
					'tags_len_max' => [
						'type' => TypeRegistry::int(),
					],
					'tags_open_system' => [
						'type' => TypeRegistry::boolean()
					],
					'site_online' => [
						'type' => TypeRegistry::boolean(),
					],
					'site_offline_message' => [
						'type' => TypeRegistry::string(),
					],
					'board_name' => [
						'type' => TypeRegistry::string(),
					],
					'reputation_enabled' => [
						'type' => TypeRegistry::boolean()
					],
					'reputation_highlight' => [
						'type' => TypeRegistry::int()
					],
					'reputation_show_profile' => [
						'type' => TypeRegistry::boolean()
					],
					'allow_reg' => [
						'type' => TypeRegistry::eNum([
							'name' => 'allowReg',
							'values' => ['NORMAL', 'FULL', 'REDIRECT', 'DISABLED']
						]),
						'resolve' => function ($val, $args, $context, $info) {
							return \strtoupper( \IPS\Login::registrationType() );
						}
					],
					'allow_reg_target' => [
						'type' => TypeRegistry::string()
					],
					'allow_result_view' => [
						'type' => TypeRegistry::boolean()
					],
					'geolocation_enabled' => [
						'type' => TypeRegistry::boolean(),
						'resolve' => function () {
							return \IPS\GeoLocation::enabled();
						}
					],
					'version' => [
						'type' => TypeRegistry::int(),
						'resolve' => function () {
							return \IPS\Application::load('core')->long_version;
						}
					],

					/* -------------------------------- */
					/* Legal settings */
					'privacy_type' => [
						'type' => TypeRegistry::eNum([
							'name' => 'privacyType',
							'values' => ['INTERNAL', 'EXTERNAL', 'NONE']
						]),
						'resolve' => function ($val) {
							return mb_strtoupper( \IPS\Settings::i()->privacy_type );
						}
					],
					'privacy_text' => [
						'type' => TypeRegistry::richText(),
						'resolve' => function () {
							return \IPS\Member::loggedIn()->language()->get('privacy_text_value');
						}
					],
					'privacy_link' => [
						'type' => TypeRegistry::string()
					],
					'reg_rules' => [
						'type' => TypeRegistry::richText(),
						'resolve' => function () {
							return \IPS\Member::loggedIn()->language()->get('reg_rules_value');
						}
					],
					'guidelines_type' => [
						'type' => TypeRegistry::eNum([
							'name' => 'guidelinesType',
							'values' => ['INTERNAL', 'EXTERNAL', 'NONE']
						]),
						'resolve' => function ($val) {
							return mb_strtoupper( \IPS\Settings::i()->gl_type );
						}
					],
					'guidelines_text' => [
						'type' => TypeRegistry::richText(),
						'resolve' => function ($val) {
							return \IPS\Member::loggedIn()->language()->get('guidelines_value');
						}
					],
					'guidelines_link' => [
						'type' => TypeRegistry::string(),
						'resolve' => function ($val) {
							return \IPS\Settings::i()->gl_link;
						}
					],

					/* -------------------------------- */
					/* Forums settings */
					'forums_questions_downvote' => [
						'type' => TypeRegistry::boolean()
					],
					'forums_uses_solved' => [
						'type' => TypeRegistry::boolean(),
						'resolve' => function ($val) {
							return \IPS\Application::appIsEnabled('forums') && \IPS\forums\Topic::anyContainerAllowsSolvable();
						}
					],

					/* -------------------------------- */
					/* Upload settings */
					'allowedFileTypes' => [
						'type' => TypeRegistry::listOf( TypeRegistry::string() ),
						'description' => "File extensions that are allowed to be uploaded or NULL for any extensions. Note: may be an empty array which means attachments not allowed.",
						'resolve' => function() {
							return \IPS\Helpers\Form\Editor::allowedFileExtensions();
						}
					],
					'chunkingSupported' => [
						'type' => TypeRegistry::boolean(),
						'description' => "If chunking is supported",
						'resolve' => function() {
							$storageClass = \IPS\File::getClass( 'core_Attachment' );
							return $storageClass::$supportsChunking;
						}
					],
					'maxChunkSize' => [
						'type' => TypeRegistry::int(),
						'description' => "The maximum size (in bytes) the server can handle without crashing. If chunking is supported, you can send chunks of up to this size - if it isn't, this effectively becomes the maximum size per file.",
						'resolve' => function() {
							return \IPS\Helpers\Form\Upload::maxChunkSize();
						}
					],

					/* -------------------------------- */
					/* Report settings */
					'automoderationEnabled' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Whether the automoderation feature is enabled",
						'resolve' => function () {
							return \IPS\Settings::i()->automoderation_enabled;
						}
					],
					'reportReasons' => [
						'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::reportReason() ),
						'deescription' => "The available reasons for reporting content",
						'resolve' => function () {
							$options = array();

							$options[] = array(
								'id' => \IPS\core\Reports\Report::TYPE_MESSAGE, 
								'reason' => \IPS\Member::loggedIn()->language()->addToStack('report_message_item') 
							);

							foreach( \IPS\core\Reports\Types::roots() as $type )
							{
								$options[] = array(
									'id' => $type->id,
									'reason' => $type->_title
								);
							}

							return $options;
						}
					]
				];
			},
			'resolveField' => function ($val, $args, $context, $info) {
				$setting = $info->fieldName;
				try 
				{
					return \IPS\Settings::i()->$setting;
				} 
				catch(\Exception $error)
				{
					return null;
				}
			}
		];

		parent::__construct($config);
	}
}
