<?php
/**
 * @brief		4.1.12 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Apr 2016
 */

namespace IPS\core\setup\upg_101030;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.12 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Removing a legacy column
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Check for the legacy 3.x column */
		if( \IPS\Db::i()->checkForColumn( 'core_pfields_groups', 'pf_group_key' ) )
		{
			/* And Remove */
			\IPS\Db::i()->dropColumn( 'core_pfields_groups', 'pf_group_key' );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Cleaning up database";
	}

	/**
	 * Update main menu item permissions
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\Db::i()->update( 'core_menu', "permissions = '*'", "permissions = '' OR permissions IS NULL" );
		unset( \IPS\Data\Store::i()->frontNavigation );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Fixing menu items";
	}

	/**
	 * Adds an index to the attach location columns to improve attachment parser performance
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		if ( !\IPS\Db::i()->checkForIndex( 'core_attachments', 'attach_location' ) )
		{
			\IPS\Db::i()->addIndex( 'core_attachments', array(
				'type'			=> 'key',
				'name'			=> 'attach_location',
				'columns'		=> array( 'attach_location' )
			) );
		}

		if ( !\IPS\Db::i()->checkForIndex( 'core_attachments', 'attach_thumb_location' ) )
		{
			\IPS\Db::i()->addIndex( 'core_attachments', array(
				'type'			=> 'key',
				'name'			=> 'attach_thumb_location',
				'columns'		=> array( 'attach_thumb_location' )
			) );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Indexing attachment locations";
	}

	/**
	 * Reset image download counts to zero
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		/* 3.x used to track image downloads through a redirect script. 4.x does not which leads to inconsistent and unreliable data */
		\IPS\Db::i()->update( 'core_attachments', 'attach_hits = 0', "attach_is_image = 1" );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step4CustomTitle()
	{
		return "Resetting image download counts";
	}

	/**
	 * Adjust group messenger settings so that -1 is the new unlimited value to allow for 0
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step5()
	{
		/* Groups */
		\IPS\Db::i()->update( 'core_groups', 'g_pm_perday = -1', "g_pm_perday = 0" );
		\IPS\Db::i()->update( 'core_groups', 'g_pm_flood_mins = -1', "g_pm_flood_mins = 0" );
		\IPS\Db::i()->update( 'core_groups', 'g_max_mass_pm = -1', "g_max_mass_pm = 0" );
		\IPS\Db::i()->update( 'core_groups', 'g_max_messages = -1', "g_max_messages = 0" );

		unset( \IPS\Data\Store::i()->groups );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step5CustomTitle()
	{
		return "Adjusting group messenger settings";
	}

	/**
	 * Finish step
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		/* Fix reports in report center */		
		$perCycle	= 1000;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'core_rc_index', null, 'id ASC', array( $limit, $perCycle ) ) as $report )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$class	= $report['class'];

			if( class_exists( $class ) )
			{
				try
				{
					$item	= $class::load( $report['content_id'] );
				}
					/* Item may no longer exist */
				catch ( \OutOfRangeException $e)
				{
					continue;
				}
					/* An old Pages bug may have \IPS\cms\Records stored instead of \IPS\cms\Records# as the class. When that is
						the case, an invalid db query results in an SQL error we can ignore */
				catch ( \IPS\Db\Exception $e )
				{
					continue;
				}
			}
			else
			{
				continue;
			}

			/* Skip if the content no longer has an author */
			if( !$item->author()->member_id )
			{
				continue;
			}

			/* We need to update author and perm_id, which may be set incorrectly */
			$update = array( 'author' => $item->author()->member_id );

			try
			{
				/* As above, an old Pages bug may have \IPS\cms\Records\Comment stored instead of \IPS\cms\Records\Comment# as the class. What that is
					the case, the parent item class will not exist, resulting in a fatal error. Look for this and bubble up the exception accordingly. */
				if ( \in_array( 'IPS\Content\Comment', class_parents( $item ) ) )
				{
					if ( isset( $item::$itemClass ) )
					{
						$item = $item->item();
					}
					else
					{
						throw new \Exception;
					}
				}

				$permissions		= $item->container()->permissions();
				$update['perm_id']	= $permissions['perm_id'];
			}
			catch( \Exception $e ){}

			\IPS\Db::i()->update( 'core_rc_index', $update, array( 'id=?', $report['id'] ) );

			$did++;
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			return TRUE;
		}
	}
}