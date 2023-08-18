<?php
/**
 * @brief		GraphQL: Language pack Type
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
 * LanguageType for GraphQL API
 */
class _LanguageType extends ObjectType
{
    /**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_Language',
			'description' => 'Language package',
			'fields' => function () {
				return [
					'title' => [
						'type' => TypeRegistry::string(),
						'description' => "Language name",
						'resolve' => function ($lang) {
							return $lang->title;
						}
					],
					'isDefault' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Is this language pack the site default?",
						'resolve' => function ($lang) {
							return (boolean) $lang->default;
						}
					],
					'locale' => [
						'type' => TypeRegistry::string(),
						'description' => "Returns the locale key for this language",
						'resolve' => function ($lang) {
							if ( preg_match( '/^\w{2}[-_]\w{2}($|\.)/i', $lang->short ) )
							{
								return \strtolower( \substr( $lang->short, 0, 2 ) ) . '-' . \strtoupper( \substr( $lang->short, 3, 2 ) );
							}

							return '';
						}
					],
					'phrases' => [
						'type' => TypeRegistry::string(),
						'description' => "Accepts a JSON object, which should be an array of phrase keys to return. Returned values are key/value pairs representing phrase key and translation.",
						'args' => [
							'keys' => [
								'type' => TypeRegistry::nonNull( TypeRegistry::listOf( TypeRegistry::string() ) )
							]
						],
						'resolve' => function ($lang, $args) {
							$phrases = $args['keys'];
							$toReturn = array();

							foreach( $phrases as $phrase )
							{
								$toReturn[ $phrase ] = $lang->addToStack( 'app_' . $phrase, FALSE, array('json' => TRUE) );
							}

							return json_encode($toReturn);
						}	
					],
					'phrase' => [
						'type' => TypeRegistry::string(),
						'description' => "Get a phrase from this language pack",
						'args' => [
							'key' => [
								'type' => TypeRegistry::nonNull( TypeRegistry::string() )
							]
						],
						'resolve' => function ($lang, $args, $context, $info) {
							try 
							{
								return $lang->addToStack( $args['key'] );
							} 
							catch (\Exception $err)
							{
								return $args['key'];
							}
						}
					]
				];
			}
		];

		parent::__construct($config);  
	}
}
