<?php
/**
 * @brief		eventreminders Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	calendar
 * @since		14 Feb 2017
 */

namespace IPS\calendar\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * eventreminders Task
 */
class _eventreminders extends \IPS\Task
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
		$reminderCount = \IPS\Db::i()->select( 'count(*)', 'calendar_event_reminders', array() )->first();

		if( $reminderCount == 0 )
		{
			\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 0 ), array( '`key`=?', 'eventreminders' ) );

			/* Nothing to send */
			return NULL;
		}

		$this->runUntilTimeout( function()
		{
			/* Grab some reminders */
			$reminders = \IPS\Db::i()->select( '*', 'calendar_event_reminders', array( 'reminder_date <= ?', \IPS\DateTime::create()->getTimestamp() ), 'reminder_date ASC', array( 0, 20 ), NULL, NULL, \IPS\Db::SELECT_DISTINCT );

			if ( !$reminders->count() )
			{
				return FALSE;
			}

			foreach( $reminders as $reminder )
			{
				$event = \IPS\calendar\Event::load( $reminder['reminder_event_id'] );
				$member = \IPS\Member::load( $reminder['reminder_member_id'] );
				
				if ( $event->canView( $member ) )
				{
				    $notification = new \IPS\Notification( \IPS\Application::load( 'calendar' ), 'event_reminder', $event, array( $event, $reminder['reminder_days_before'] ), array( 'daysToGo' => $reminder['reminder_days_before'] ) );
				    $notification->recipients->attach( $member );
				    $notification->send();
				}
				
				/* Delete the reminder now it has sent */
				\IPS\Db::i()->delete( 'calendar_event_reminders', array( 'reminder_event_id=? AND reminder_member_id=?', $reminder['reminder_event_id'], $reminder['reminder_member_id'] ) );
			}
		});

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