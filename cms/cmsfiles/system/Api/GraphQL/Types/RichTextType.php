<?php
/**
 * @brief		GraphQL: RichText Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		7 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Api\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * RichTextType for GraphQL API
 */
class _RichTextType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_RichText',
			'description' => 'Represents an internal URL',
			'fields' => [
				'plain' => [
					'type' => TypeRegistry::string(),
					'description' => "Returns plain-text version of the text",
					'args' => [
						'singleLine' => [
							'type' => TypeRegistry::boolean(),
							'description' => "Should linebreaks be removed?",
							'defaultValue' => TRUE
						],
						'truncateLength' => [
							'type' => TypeRegistry::int(),
							'description' => 'Characters to truncate on. Only available if stripped option is true.',
							'defaultValue' => 0
						]
					],
					'resolve' => function ($string, $args) {
						
						/* Remove stuff we don't want to include (quotes, spoilers, scripts) */
						$string = \IPS\Text\Parser::removeElements( $string, array( 'blockquote', 'script', 'div[class=ipsSpoiler]' ) );
						
						/* Put a break in places where we actually want one */
						$string = str_replace( array( '</p>', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>', '</li>' ), '<br>', $string );
						
						/* Strip all other tags */
						$string = strip_tags( $string, '<br>' );
						$string = html_entity_decode( $string );
						
						/* Fix linebreaks */
						$string = preg_replace( "/\r|\n|\t/", '', $string );
						$string = str_replace( '<br>', $args['singleLine'] ? ' ' : "\n", $string );
						
						/* Trim whitespace */
						$string = trim( $string );
						
						/* Truncate */
						if( $args['truncateLength'] )
						{
							$string = mb_substr( $string, 0, $args['truncateLength'] );
						}
						
						/* Return */
						return $string;
					}
				],
				'original' => [
					'type' => TypeRegistry::string(),
					'description' => "Returns original rich text (i.e. containing full rich markup)",
					'args' => [
						'removeLazyLoad' => [
							'type' => TypeRegistry::boolean(),
							'description' => "Remove lazy-load placeholders?",
							'defaultValue' => TRUE
						]
					],
					'resolve' => function ($string, $args) {
						if( $args['removeLazyLoad'] )
						{
							$string = \IPS\Text\Parser::removeLazyLoad( $string );
						}

						\IPS\Output::i()->parseFileObjectUrls( $string );
						return $string;
					}
				]
			]
		];

		parent::__construct($config);
	}
}