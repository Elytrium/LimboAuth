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
class _QueryType extends ObjectType
{
	public function __construct()
	{
		$config = [
			'name' => 'Query',
			'fields' => function () {
				$_fields = array();

				foreach( \IPS\Application::enabledApplications() as $key => $app )
				{
					try 
					{
						$globalAppQueries = \IPS\Api\GraphQL\AppQueries::queries($app->directory);
						$appQueryClass = "\\IPS\\" . $app->directory . "\\api\\GraphQL\\Query";

						if( !class_exists( $appQueryClass ) )
						{
							continue;
						}
						
						$queryFields = array();
						$queryTypes = $appQueryClass::queries();

						// Add the queries that *every* app receives
						foreach( $globalAppQueries as $globalType => $globalQueryClass )
						{					
							$queryFields[ $globalType ] = array(
								'type' => $globalQueryClass->type(),
								'description' => $globalQueryClass::$description,
								'args' => $globalQueryClass->args()
							);
						}

						// Add each app's individual queries
						foreach( $queryTypes as $type => $queryClass )
						{
							$queryFields[ $type ] = array(
								'type' => $queryClass->type(),
								'description' => $queryClass::$description,
								'args' => $queryClass->args()
							);
						}

						$_fields[ $app->directory ] = new \GraphQL\Type\Definition\ObjectType( array(
							'name' => $app->directory,
							'fields' => $queryFields
						) );
					} 
					catch ( \Exception $e ) 
					{ 
						print_r( $e );
						exit;
					}
				}

				return $_fields;
			},
			'resolveField' => function ($val, $args, $context, $info) {
				try 
				{
					$globalAppQueries = \IPS\Api\GraphQL\AppQueries::queries($info->fieldName);
					$appQueryClass = '\\IPS\\' . $info->fieldName . '\\api\\GraphQL\\Query';					
					$queryResolvers = array();
					$queryTypes = $appQueryClass::queries();

					// Resolve the global app queries that every app receives
					foreach( $globalAppQueries as $globalType => $globalQueryClass )
					{
						$queryResolvers[ $globalType ] = function ($val, $args, $context, $info) use ($globalQueryClass)
						{
							return $globalQueryClass->resolve( $val, $args, $context, $info);
						};
					}

					// Resolve the individual per-app queries
					foreach( $queryTypes as $type => $queryClass )
					{
						$queryResolvers[ $type ] = function($val, $args, $context, $info) use ($queryClass)
						{
							return $queryClass->resolve($val, $args, $context, $info);
						};
					}

					return $queryResolvers;
				}
				catch ( \Exception $e )
				{
					throw new \IPS\Api\Exception( 'NO_RESOLVE', '3S291/2', 405 );
				}
			}
		];

		parent::__construct( $config );
	}
}
