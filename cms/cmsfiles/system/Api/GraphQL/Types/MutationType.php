<?php
/**
 * @brief		API Exception
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		3 Dec 2015
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
 * API Exception
 */
class _MutationType extends ObjectType
{
	public function __construct()
	{
		$config = [
			'name' => 'Mutation',
			'fields' => function () {
				$_fields = array();

				foreach( \IPS\Application::enabledApplications() as $key => $app )
				{
					try 
					{
						$appMutationClass = "\\IPS\\" . $app->directory . "\\api\\GraphQL\\Mutation";

						if( !class_exists( $appMutationClass ) )
						{
							continue;
						}
						
						$mutationFields = array();
						$mutationType = $appMutationClass::mutations();

						foreach( $mutationType as $type => $mutationClass )
						{
							$mutationFields[ $type ] = array(
								'type' => $mutationClass->type(),
								'description' => $mutationClass::$description,
								'args' => $mutationClass->args()
							);
						}

						$_fields[ 'mutate' . ucfirst($app->directory) ] = new \GraphQL\Type\Definition\ObjectType( array(
							'name' => 'mutate_' . ucfirst($app->directory),
							'fields' => $mutationFields
						) );
					} 
					catch ( \Exception $err )
					{ }
				}

				return $_fields;
			},
			'resolveField' => function ($val, $args, $context, $info) {
				$name = str_replace( 'mutate', '', $info->fieldName );

				try 
				{
					$appMutationClass = '\\IPS\\' . $name . '\\api\\GraphQL\\Mutation';					
					$mutationResolvers = array();
					$mutationType = $appMutationClass::mutations();

					foreach( $mutationType as $type => $mutationClass )
					{
						$mutationResolvers[ $type ] = function($val, $args, $context, $info) use ($mutationClass)
						{
							return $mutationClass->resolve($val, $args, $context, $info);
						};
					}

					return $mutationResolvers;
				}
				catch ( \Exception $e )
				{
					throw new \IPS\Api\Exception( 'NO_RESOLVE', '3S291/2_graphl', 405 );
				}
			}
		];

		parent::__construct( $config );
	}
}
