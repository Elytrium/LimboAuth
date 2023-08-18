<?php
/**
 * @brief		Unlock Members Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		29 Sep 2016
 */

namespace IPS\core\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Unlock Members Task
 */
class _unlockmembers extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * If ran successfully, should return anything worth logging. Only log something
	 * worth mentioning (don't log "task ran successfully"). Return NULL (actual NULL, not '' or 0) to not log (which will be most cases).
	 * If an error occurs which means the task could not finish running, throw an \IPS\Task\Exception - do not log an error as a normal log.
	 * Tasks should execute within the time of a normal HTTP request.
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		if(  !\IPS\Settings::i()->ipb_bruteforce_unlock or !\IPS\Settings::i()->ipb_bruteforce_period or !\IPS\Settings::i()->ipb_bruteforce_attempts )
		{
			return NULL;
		}

		foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members', array( 'failed_login_count >= ?', \IPS\Settings::i()->ipb_bruteforce_attempts ) ), 'IPS\Member') AS $member )
		{
			$failedLogins = $member->failed_logins;

			if ( \is_array( $failedLogins ) )
			{
				foreach ( $failedLogins as $ipAddress => $times )
				{
					foreach ( $times as $k => $v )
					{
						if ( $v < \IPS\DateTime::create()->sub( new \DateInterval( 'PT' . \IPS\Settings::i()->ipb_bruteforce_period . 'M' ) )->getTimestamp() )
						{
							unset( $failedLogins[ $ipAddress ][ $k ] );
						}
					}
				}
				$member->failed_logins = $failedLogins;
			}
			else
			{
				$member->failed_logins = array();
			}
			$member->save();
		}

		return NULL;
	}
	
	/**
	 * Cleanup
	 *
	 * If your task takes longer than 15 minutes to run, this method
	 * will be called before execute(). Use it to clean up anything which
	 * may not have been done
	 *
	 * @return	void
	 */
	public function cleanup()
	{
		
	}
}