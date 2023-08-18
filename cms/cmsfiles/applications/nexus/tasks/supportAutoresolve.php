<?php
/**
 * @brief		supportAutoresolve Task
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
 * supportAutoresolve Task
 */
class _supportAutoresolve extends \IPS\Task
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
		if ( \IPS\Settings::i()->nexus_autoresolve_days AND \IPS\Settings::i()->nexus_autoresolve_status != '' )
		{
			/* Build where */
			$where = array();
			$where[] = array( \IPS\Db::i()->in( 'r_status', array_filter( explode( ',', \IPS\Settings::i()->nexus_autoresolve_applicable ), function( $val ) {
				return $val != \IPS\Settings::i()->nexus_autoresolve_status;
			} ) ) );
			if ( \IPS\Settings::i()->nexus_autoresolve_departments !== '*' )
			{
				$where[] = array( \IPS\Db::i()->in( 'r_department', explode( ',', \IPS\Settings::i()->nexus_autoresolve_departments ) ) );
			}
			
			/* Resolve */
			$resolveWhere = array_merge( $where, array( array( 'r_last_reply<?', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . \IPS\Settings::i()->nexus_autoresolve_days . 'D' ) )->getTimestamp() ) ) );
			$resolvedStatus = \IPS\nexus\Support\Status::load( \IPS\Settings::i()->nexus_autoresolve_status );
			foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_support_requests', $resolveWhere ), 'IPS\nexus\Support\Request' ) as $request )
			{
				$oldStatus = $request->status;
				$request->status = $resolvedStatus;
				$request->save();
				$request->log( 'autoresolve', $oldStatus, $resolvedStatus, FALSE );
			}
			
			/* Warnings */
			if ( \IPS\Settings::i()->nexus_autoresolve_notify )
			{
				$warningWhere = array_merge( $where, array(
					array( 'r_last_reply<?',  \IPS\DateTime::create()->sub( new \DateInterval( 'P' . \IPS\Settings::i()->nexus_autoresolve_days . 'D' ) )->add( new \DateInterval( 'PT' . \IPS\Settings::i()->nexus_autoresolve_notify . 'H' ) )->getTimestamp() ),
					array( 'r_ar_notify=0' )
				) );
				foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_support_requests', $warningWhere ), 'IPS\nexus\Support\Request' ) as $request )
				{
					$fromEmail = $request->department->email ?: \IPS\Settings::i()->email_out;
					$email = \IPS\Email::buildFromTemplate( 'nexus', 'autoresolveWarning', array( $request, \IPS\DateTime::ts( $request->last_reply )->add( new \DateInterval( 'P' . \IPS\Settings::i()->nexus_autoresolve_days . 'D' ) ) ), \IPS\Email::TYPE_TRANSACTIONAL );
					$email->send( $request->email ?: $request->author(), array(), array(), $fromEmail, NULL, array( 'Message-Id' => "<IPS-000-SR{$request->id}.{$request->email_key}-{$fromEmail}>" ) );
					
					$request->ar_notify = TRUE;
					$request->save();
					$request->log( 'autoresolve_warning', NULL, NULL, FALSE );
				}
			}
		}
		else
		{
			$this->enabled = FALSE;
			$this->save();
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