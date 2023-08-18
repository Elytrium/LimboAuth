<?php
/**
 * @brief		GraphQL: Unfollow something mutation
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		9 Sep 2018
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Mutations;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Follow something mutation for GraphQL API
 */
class _Unfollow
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Unfollow a node, item or member";

	/*
	 * Mutation arguments
	 */
	public function args(): array
	{
		return [
			'app' => TypeRegistry::nonNull( TypeRegistry::string() ),
			'area' => TypeRegistry::nonNull( TypeRegistry::string() ),
			'id' => TypeRegistry::nonNull( TypeRegistry::id() ),
			'followID' => TypeRegistry::nonNull( TypeRegistry::id() )
		];
	}

	/**
	 * Return the mutation return type
	 */
	public function type() 
	{
		return TypeRegistry::follow();
	}

	/**
	 * Resolves this mutation
	 * @todo this is basically copied and pasted from notifications.php which isn't ideal, so we 
	 * might want to consider refactoring to abstract this functionality.
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @param 	array 	Arguments
	 * @param 	array 	Context values
	 * @return	array
	 */
	public function resolve($val, $args)
	{
		if( !\IPS\Member::loggedIn()->member_id )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NOT_LOGGED_IN', 'GQL/0002/6', 403 );
		}

		try
		{
			$follow = \IPS\Db::i()->select( '*', 'core_follow', array( 'follow_id=? AND follow_member_id=?', $args['followID'], \IPS\Member::loggedIn()->member_id ) )->first();
		}
		catch ( \OutOfRangeException $e ) {}
		
		\IPS\Db::i()->delete( 'core_follow', array( 'follow_id=? AND follow_member_id=?', $args['followID'], \IPS\Member::loggedIn()->member_id ) );

		/* Get class */		
		if( $args['app'] == 'core' and $args['area'] == 'member' )
		{
			$class = 'IPS\\Member';
		}
		elseif( $args['app'] == 'core' and $args['area'] == 'club' )
		{
			$class = 'IPS\\Member\Club';
		}
		else
		{
			$class = NULL;
			foreach ( \IPS\Application::load( $args['app'] )->extensions( 'core', 'ContentRouter' ) as $ext )
			{
				foreach ( $ext->classes as $classname )
				{
					if ( $classname == 'IPS\\' . $args['app'] . '\\' . mb_ucfirst( $args['area'] ) )
					{
						$class = $classname;
						break;
					}
					if ( isset( $classname::$containerNodeClass ) and $classname::$containerNodeClass == 'IPS\\' . $args['app'] . '\\' . mb_ucfirst( $args['area'] ) )
					{
						$class = $classname::$containerNodeClass;
						break;
					}
					if( isset( $classname::$containerFollowClasses ) )
					{
						foreach( $classname::$containerFollowClasses as $followClass )
						{
							if( $followClass == 'IPS\\' . $args['app'] . '\\' . mb_ucfirst( $args['area'] ) )
							{
								$class = $followClass;
								break;
							}
						}
					}
				}
			}
		}
		
		if ( !$class or !array_key_exists( $args['app'], \IPS\Application::applications() ) )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NOT_FOUND', 'GQL/0001/7', 404 );
		}

		/* Get our return info ready */
		$return = array(
			'app' => $args['app'],
			'area' => $args['area'],
			'id' => $args['id']
		);

		/* Get thing */
		$thing = NULL;

		try
		{
			if ( $class != "IPS\Member" )
			{
				$thing = $class::loadAndCheckPerms( (int) $follow['follow_rel_id'] );
			}
			else
			{
				$thing = $class::load( (int) $follow['follow_rel_id'] );
			}

			/* Unfollow club areas */
			if( $class == "IPS\Member\Club"  )
			{
				foreach ( $thing->nodes() as $node )
				{
					$itemClass = $node['node_class']::$contentItemClass;
					$followApp = $itemClass::$application;
					$followArea = mb_strtolower( mb_substr( $node['node_class'], mb_strrpos( $node['node_class'], '\\' ) + 1 ) );
					
					\IPS\Db::i()->delete( 'core_follow', array( 'follow_id=? AND follow_member_id=?', md5( $followApp . ';' . $followArea . ';' . $node['node_id'] . ';' .  \IPS\Member::loggedIn()->member_id ), \IPS\Member::loggedIn()->member_id ) );
				}
			}

			if ( \in_array( 'IPS\Node\Model', class_parents( $class ) ) )
			{
				$return = array_merge($return, array(
					'node' => $thing,
					'nodeClass' => \get_class( $thing )
				));
			}
			else if( $class == 'IPS\Member' )
			{
				$return = array_merge($return, array(
					'member' => $thing
				));
			}
			else if( $class == 'IPS\Member\Club' )
			{
				// @future Support clubs
			}
			else
			{
				$return = array_merge($return, array(
					'item' => $thing,
					'itemClass' => \get_class( $thing )
				));
			}
		}
		catch ( \OutOfRangeException $e )
		{
		}

		return $return;
	}
}
