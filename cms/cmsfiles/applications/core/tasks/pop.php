<?php
/**
 * @brief		POP3 Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		16 Apr 2014
 */

namespace IPS\core\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * POP3 Task
 */
class _pop extends \IPS\Task
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
		/* If we haven't set up POP3, disable ourselves */
		if ( !\IPS\Settings::i()->pop3_server )
		{
			$this->enabled = FALSE;
			$this->save();
			return;
		}
		
		/* Do it */
		try
		{
			/* Connect */
			$pop3 = new \IPS\Email\Incoming\PopImap(  \IPS\Settings::i()->pop3_server, \IPS\Settings::i()->pop3_tls, \IPS\Settings::i()->pop3_port, \IPS\Settings::i()->pop3_user, \IPS\Settings::i()->pop3_password );
			
			/* Fetch emails */
			if ( $emailsInInbox = $pop3->emailsInInbox() )
			{	
				foreach ( range( 1, min( $emailsInInbox, 50 ) ) as $i )
				{						
					try
					{
						$incomingEmail = new \IPS\Email\Incoming\Email( $pop3->getEmail( $i ) );
						$incomingEmail->route();
						$pop3->deleteEmail( $i );
					}
					catch ( \IPS\Email\Incoming\PopImapException $e )
					{
						\IPS\Log::log( $e, 'pop3' );
					}
				}
			}
			
			/* Disconnect */	
			unset( $pop3 );
		}
		catch ( \Exception $exception )
		{
			\IPS\Log::log( $exception, 'pop3' );
			throw new \IPS\Task\Exception( $this, $exception->getMessage() );
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