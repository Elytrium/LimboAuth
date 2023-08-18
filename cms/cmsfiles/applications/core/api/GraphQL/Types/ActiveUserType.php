<?php
/**
 * @brief		GraphQL: ActiveUsers Type
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
 * ActiveUsers for GraphQL API
 */
class _ActiveUserType extends ObjectType
{
    /**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_ActiveUser',
			'fields' => function () {
				return [
					'url' => [
						'type' => TypeRegistry::url(),
						'resolve' => function ($user, $args) {
							return \IPS\Http\Url::createFromString( $user['location_url'] );
						}
					],
					'lang' => [
						'type' => TypeRegistry::string(),
						'args' => [
							'action' => [
								'type' => TypeRegistry::boolean(),
								'defaultValue' => TRUE
							]
						],
						'resolve' => function ($user, $args) {
							return self::getLocationLang( $user, $args['action'] );
						}
					],
					'anonymous' => [
						'type' => TypeRegistry::boolean(),
						'resolve' => function ($user, $args) {
							return $user['login_type'] == \IPS\Session\Front::LOGIN_TYPE_ANONYMOUS;
						}
					],
					'ipAddress' => [
						'type' => TypeRegistry::string(),
						'resolve' => function ($user, $args) {
							if( \IPS\Member::loggedIn()->modPermission( 'can_use_ip_tools' ) )
							{
								return $user['ip_address'];
							}

							return NULL;
						}
					],
					'timestamp' => [
						'type' => TypeRegistry::int(),
						'resolve' => function ($user, $args) {
							return $user['running_time'];
						}
					],
					'user' => [
						'type' => \IPS\core\api\GraphQL\TypeRegistry::member(),
						'resolve' => function ($user, $args) {
							if( $user['member_id'] )
							{
								return \IPS\Member::load( $user['member_id'] );
							}

							return new \IPS\Member;
						}
					]
				];
			}
		];

        parent::__construct($config);
	}

	/**
	 * Return the language string showing what the user is doing
	 * @todo this calls language()->get(), so we need to figure out we can get the keys in advance to load them
	 * @param	array 		$user	  	The active user row from session store
	 * @param	boolean		$action    	Use the 'action' version of the language string?
	 * @return	String
	 */
	protected static function getLocationLang($user, $action)
	{
		try
		{
			if( !$user['location_lang'] )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'app_user_browsing_community', FALSE, array('sprintf' => array( $user['member_name'] ) ) );
			}

			if ( $user['location_permissions'] === NULL or $user['location_permissions'] === '*' or \IPS\Member::loggedIn()->inGroup( explode( ',', $user['location_permissions'] ), TRUE ) )
			{
				$sprintf = array();
				$data = json_decode( $user['location_data'], TRUE );

				if ( !empty( $data ) )
				{
					foreach ( $data as $key => $parse )
					{
						$value		= htmlspecialchars( $parse ? \IPS\Member::loggedIn()->language()->get( $key ) : $key, ENT_DISALLOWED, 'UTF-8', FALSE );
						$sprintf[]	= $value;
					}
				}

				if( $action && \IPS\Member::loggedIn()->language()->checkKeyExists($user['location_lang'] . '_action') )
				{
					// Add the member name to the sprintf array since the action strings use it
					array_unshift( $sprintf, $user['member_name'] );
					return \IPS\Member::loggedIn()->language()->addToStack( $user['location_lang'] . '_action', FALSE, array( 'sprintf' => $sprintf ) );
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack( $user['location_lang'], FALSE, array( 'sprintf' => $sprintf ) );
				}

				return sprintf( $lang, $sprintf );
			}
		}
		catch ( \UnderflowException $e ){
			
		}

		return NULL;
	}
}
