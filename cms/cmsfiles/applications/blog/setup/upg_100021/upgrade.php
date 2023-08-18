<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		15 Mar 2015
 */

namespace IPS\blog\setup\upg_100021;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Convert blog view levels
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Some init */
		$did		= 0;
		$limit		= 0;
		
		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= \IPS\Request::i()->extra;
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'blog_blogs', "blog_view_level IN('private','friends','privateclub')", 'blog_id ASC', array( $limit, 50 ) ) as $blog )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			$blog	= \IPS\blog\Blog::constructFromData( $blog );

			/* Figure out type of blog and act accordingly */
			switch( $blog->view_level )
			{
				case 'private':
					$group	= \IPS\Db::i()->insert( 'core_sys_social_groups', array( 'owner_id' => $blog->member_id ) );
					\IPS\Db::i()->insert( 'core_sys_social_group_members', array( 'group_id' => $group, 'member_id' => $blog->member_id ) );

					$blog->social_group = $group;
				break;

				case 'friends':
				case 'privateclub':
					$group	= \IPS\Db::i()->insert( 'core_sys_social_groups', array( 'owner_id' => $blog->member_id ) );

					if( $blog->authorized_users )
					{
						$users	= explode( ',', trim( $blog->authorized_users, ',' ) );

						foreach( $users as $user )
						{
							\IPS\Db::i()->replace( 'core_sys_social_group_members', array( 'group_id' => $group, 'member_id' => $user ) );
						}

						\IPS\Db::i()->replace( 'core_sys_social_group_members', array( 'group_id' => $group, 'member_id' => $blog->member_id ) );
					}
					else
					{
						\IPS\Db::i()->insert( 'core_sys_social_group_members', array( 'group_id' => $group, 'member_id' => $blog->member_id ) );
					}

					$blog->social_group = $group;
				break;
			}

			$blog->save();
		}
		
		if( $did )
		{
			return $limit + $did;
		}
		else
		{
			unset( $_SESSION['_step1Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step1Count'] ) )
		{
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'blog_blogs', "blog_view_level IN('private','friends','privateclub')" )->first();
		}

		return "Upgrading blogs (Updated so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
	}
}