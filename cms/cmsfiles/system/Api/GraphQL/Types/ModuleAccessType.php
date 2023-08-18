<?php
/**
 * @brief		GraphQL: ModuleAccessType
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		15 Oct 2019
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
 * ModuleAccessType for GraphQL API
 */
class _ModuleAccessType extends ObjectType
{
    /**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
            'name' => "ModuleAccess",
            'description' => "Returns permissions for modules in the platform",
			'fields' => function () {
                return [
					'view' => [
                        'type' => TypeRegistry::boolean(),
                        'args' => [
                            'module' => TypeRegistry::string()
                        ],
						'description' => "Returns the member's access to this module",
						'resolve' => function( $app, $args, $context, $info ) {
                            $canAccess = FALSE;
                            try 
                            {
                                $canAccess = \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( $app, $args['module'], 'front' ) );
                            } 
                            catch (\Exception $e)
                            {
                                // Just fall through so we return false                                
                            }

                            return $canAccess;
						}
                    ]
                ];
			}
		];

		parent::__construct($config);
	}
}