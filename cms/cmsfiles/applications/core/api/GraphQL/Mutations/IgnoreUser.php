<?php
/**
 * @brief		GraphQL: Ignore user mutation
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		29 May 2020
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
 * Ignore user mutation for GraphQL API
 */
class _IgnoreUser
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Ignore a member";

	/*
	 * Mutation arguments
	 */
	public function args(): array
	{
		return [
			'member' => TypeRegistry::nonNull( TypeRegistry::int() ),
			'type' => TypeRegistry::nonNull( TypeRegistry::string() ),
			'isIgnoring' => TypeRegistry::nonNull( TypeRegistry::boolean() )
		];
	}

	/**
	 * Return the mutation return type
	 */
	public function type() 
	{
		return \IPS\core\api\GraphQL\TypeRegistry::ignoreOption();
	}

	/**
	 * Resolves this mutation
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @param 	array 	Arguments
	 * @param 	array 	Context values
	 * @return	array
	 */
	public function resolve($val, $args)
	{
		if ( !\in_array( $args['type'], \IPS\core\Ignore::types() ) )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'INVALID_TYPE', 'GQL/0006/1', 404 );
        }
        
        $type = $args['type'];
        $member = \IPS\Member::load( $args['member'] );

        if( !$member->member_id )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'INVALID_MEMBER', 'GQL/0006/2', 403 );
        }

        if ( $member->member_id == \IPS\Member::loggedIn()->member_id )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'NO_IGNORE_SELF', 'GQL/0006/3', 403 );
        }
        
        if ( !$member->canBeIgnored() )
        {
            throw new \IPS\Api\GraphQL\SafeException( 'NO_IGNORE_MEMBER', 'GQL/0006/4', 403 );
        }

        try
        {
            $ignore = \IPS\core\Ignore::load( $member->member_id, 'ignore_ignore_id', array( 'ignore_owner_id=?', \IPS\Member::loggedIn()->member_id ) );
            $ignore->$type = $args['isIgnoring'];
            $ignore->save();
        }
        catch( \OutOfRangeException $e )
        {
            $ignore = new \IPS\core\Ignore;
            $ignore->$type = $args['isIgnoring'];
            $ignore->owner_id	= \IPS\Member::loggedIn()->member_id;
            $ignore->ignore_id	= $member->member_id;
            $ignore->save();
        }

        $return = array(
            'type' => $type,
            'is_being_ignored' => $args['isIgnoring']
        );

        \IPS\Member::loggedIn()->members_bitoptions['has_no_ignored_users'] = FALSE;
		\IPS\Member::loggedIn()->save();

		return $return;
	}
}
