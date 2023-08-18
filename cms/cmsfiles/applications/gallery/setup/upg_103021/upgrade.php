<?php
/**
 * @brief		4.3.4 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		24 May 2018
 */

namespace IPS\gallery\setup\upg_103021;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.3.4 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Get rid of the duplicate setting
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_groups' ), 'IPS\Member\Group' ) as $group )
		{
			if( $group->g_delete_own_albums AND $group->g_delete_own_posts != 1 )
			{
				$classes = explode( ',', $group->g_delete_own_posts );

				if( !\in_array( 'IPS\gallery\Album\Item', $classes ) )
				{
					$classes[] = 'IPS\gallery\Album\Item';
					\IPS\Db::i()->update( 'core_groups', array( 'g_delete_own_posts' => implode( ',', $classes ) ), array( 'g_id=?', $group->g_id ) );
				}
			}
			elseif( !$group->g_delete_own_albums AND $group->g_delete_own_posts == 1 )
			{
				$contentClasses = array( 'IPS\core\Messenger\Conversation' );
				foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', $group, NULL, NULL, FALSE ) as $extension )
				{
					/* It's possible when upgrading from 3.x that the modules table isn't populated, some extensions (clubs)
					 * Will throw an exception in this situation
					 */
					try
					{
						$class = new $extension;
					}
					catch( \OutOfRangeException $e )
					{
						continue;
					}

					foreach ( $class->classes as $class )
					{
						if ( isset( $class::$databaseColumnMap['author'] ) AND $class != 'IPS\gallery\Album\Item' )
						{
							$contentClasses[] = $class;
						}
					}
				}

				\IPS\Db::i()->update( 'core_groups', array( 'g_delete_own_posts' => implode( ',', $contentClasses ) ), array( 'g_id=?', $group->g_id ) );
			}
			elseif( !$group->g_delete_own_albums AND $group->g_delete_own_posts != 1 AND \in_array( 'IPS\gallery\Album\Item', explode( ',', $group->g_delete_own_posts ) ) )
			{
				$classes = array_filter( explode( ',', $group->g_delete_own_posts ), function( $className ) {
					return $className != 'IPS\gallery\Album\Item';
				} );

				\IPS\Db::i()->update( 'core_groups', array( 'g_delete_own_posts' => implode( ',', $classes ) ), array( 'g_id=?', $group->g_id ) );
			}
		}

		\IPS\Db::i()->dropColumn( 'core_groups', 'g_delete_own_albums' );
		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Removing duplicate setting";
	}
}