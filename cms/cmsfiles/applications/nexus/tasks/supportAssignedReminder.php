<?php
/**
 * @brief		supportAssignedReminder Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		25 Apr 2014
 */

namespace IPS\nexus\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * supportAssignedReminder Task
 */
class _supportAssignedReminder extends \IPS\Task
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
		/* Don't send reminders if the application is disabled */
		if( !\IPS\Application::appIsEnabled('nexus') )
		{
			return NULL;
		}

		$openStatuses = iterator_to_array( \IPS\Db::i()->select( 'status_id', 'nexus_support_statuses', 'status_open=1' ) );
		$staffIds = array_keys( \IPS\nexus\Support\Request::staff() );
		
		$sendTo = array();
		foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_support_requests', array(
			array( \IPS\Db::i()->in( 'r_status', $openStatuses ) ),
			array( 'r_staff<>0' )
		) ), 'IPS\nexus\Support\Request' ) as $request )
		{
			$sendTo[ $request->staff->member_id ][ $request->id ] = $request;
		}
		
		foreach ( $sendTo as $staffId => $requests )
		{
			if ( \in_array( $staffId, $staffIds ) )
			{
				$email = \IPS\Email::buildFromTemplate( 'nexus', 'staffAssignedReminder', array( $requests ), \IPS\Email::TYPE_TRANSACTIONAL )->send( \IPS\Member::load( $staffId ) );
			}
			else
			{
				\IPS\Db::i()->update( 'nexus_support_requests', array( 'r_staff' => 0 ), array( 'r_staff=?', $staffId ) );
			}
		}
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