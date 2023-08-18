<?php
/**
 * @brief		Profile-sync Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 Jun 2013
 */

namespace IPS\core\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Profile-sync Task
 */
class _profilesync extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		/* Do we have any login methods that even support syncing enabled? */
		if( !$this->canSync() )
		{
			return NULL;
		}

		$where = array( array( "temp_ban=?", 0 ), array( "profilesync_lastsync > ?", 0 ) );

		/* Only check accounts that have visited in the last 6 months */
		$where[] = array( "GREATEST(last_activity, last_visit) > ?", \IPS\DateTime::create()->sub( new \DateInterval('P6M') )->getTimestamp() );

		/* And only check accounts that are actually synchronizing, UNLESS the admin is also forcing synchronization for any login methods */
		if( !$this->hasForcedSync() )
		{
			$where[] = array( "profilesync IS NOT NULL and profilesync != ?", '[]' );
		}
		

		$totalToSync = \IPS\Db::i()->select( 'count(*)', 'core_members', $where )->first();

		if( !$totalToSync )
		{
			return NULL;
		}
		else
		{
			/* The task runs once per hour and looks for all accounts that haven't been synced in 12+ hours, so we want to set a hard limit that
				will allow all accounts to be processed in a 12 hour period if resources permit */
			$accounts = ceil( $totalToSync / 12 );
		}

		/* Only sync accounts we haven't synced in the last 12 hours */
		$where[] = array( "profilesync_lastsync < ?", ( time() - ( 60 * 60 * 12 ) ) );

		$this->runUntilTimeout( function() use( $where )
		{
			try
			{
				$member = \IPS\Db::i()->select( '*', 'core_members', $where, 'profilesync_lastsync ASC', 1 )->first();
			}
			catch ( \UnderflowException $e )
			{
				return FALSE;
			}
			
			$member = \IPS\Member::constructFromData( $member );
			$member->profileSync();
			
			return TRUE;
		}, $accounts );
		
		return NULL;
	}

	/**
	 * Determine if we can even sync
	 *
	 * @return bool
	 */
	protected function canSync()
	{
		foreach ( \IPS\Login::methods() as $method )
		{
			if( $method->hasSyncOptions() )
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Determine if any login handlers force syncing
	 *
	 * @return bool
	 */
	protected function hasForcedSync()
	{
		foreach ( \IPS\Login::methods() as $method )
		{
			if( \count( $method->forceSync() ) )
			{
				return TRUE;
			}
		}

		return FALSE;
	}
}