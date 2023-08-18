<?php
/**
 * @brief		Background Task - Bulk Mails
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		16 Nov 2016
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _Bulkmail
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$data['count'] = \IPS\core\BulkMail\Bulkmailer::load( $data['mail_id'] )->getQuery( \IPS\core\BulkMail\Bulkmailer::GET_COUNT_ONLY )->first();
		return $data;
	}

	/**
	 * Run Background Task
	 *
	 * @param	mixed						$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int							$offset	Offset
	 * @return	int							New offset
	 * @throws	\IPS\Task\Queue\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( $data, $offset )
	{
		try
		{
			$mail = \IPS\core\BulkMail\Bulkmailer::load( $data['mail_id'] );
			$classToUse = \IPS\Email::classToUse( \IPS\Email::TYPE_BULK );
			
			/* Reduce the maximum number of emails to be sent for bulk mail to 500 to prevent member specific tag timeouts */
			$mailPerGo = min( $classToUse::MAX_EMAILS_PER_GO, 500 );
			$existingvalue = \IPS\Db::i()->readWriteSeparation;
			\IPS\Db::i()->readWriteSeparation = FALSE;
			$results = $mail->getQuery( array( $offset, $mailPerGo ) );
			
			if ( !\count( $results ) )
			{
				$mail->active = 0;
				$mail->offset = 0;
				$mail->save();
				\IPS\Db::i()->readWriteSeparation = $existingvalue;
				throw new \IPS\Task\Queue\OutOfRangeException;
			}

			/* Convert $results into an array with replacement tags */
			$recipients = array();
			foreach ( $results as $memberData )
			{
				$member = \IPS\Member::constructFromData( $memberData );
				
				$vars = array();
				foreach ( $mail->returnTagValues( 2, $member ) as $k => $v )
				{
					$vars[ mb_substr( $k, 1, -1 ) ] = $v;
				}
				
				$recipients[ $member->language()->_id ][ $memberData['email'] ] = $vars;
			}
					
			/* Convert member-specific {{tag}} into *|tag|* and global {{tag}} into the value */
			$content = $mail->content;
			foreach ( $mail->returnTagValues( 1 ) as $k => $v )
			{
				$content = str_replace( $k, $v, $content );
			}
			foreach( array_keys( \IPS\core\BulkMail\Bulkmailer::getTags() ) as $k )
			{
				if ( mb_strpos( $content, $k ) !== FALSE )
				{
					$content = str_replace( $k, '*|' . str_replace( array( '{', '}' ), '', $k ) . '|*', $content );
				}
			}

			/* Format content */
			$content = \IPS\Email::staticParseTextForEmail( $content, \IPS\Lang::load( \IPS\Lang::defaultLanguage() ) );

			foreach( array_keys( \IPS\core\BulkMail\Bulkmailer::getTags() ) as $k )
			{
				$content = str_replace( '%7B' . mb_substr( $k, 1, -1 ) . '%7D', '*|' . mb_substr( $k, 1, -1 ) . '|*', $content );
				$content = str_replace( '*%7C' . mb_substr( $k, 1, -1 ) . '%7C*', '*|' . mb_substr( $k, 1, -1 ) . '|*', $content );
			}
									
			/* Send it */
			$email = \IPS\Email::buildFromContent( $mail->subject, $content, NULL, \IPS\Email::TYPE_BULK, \IPS\Email::WRAPPER_USE, 'bulk_mail' )
				->setUnsubscribe( 'core', 'unsubscribeBulk' );
			$sent = 0;
			foreach ( $recipients as $languageId => $_recipients )
			{
				$sent += $email->mergeAndSend( $_recipients, NULL, NULL, array( 'List-Unsubscribe' => '<*|unsubscribe_url|*>' ), \IPS\Lang::load( $languageId ) );
			}
			
			$mail->updated	= time();
			$mail->offset	= ( $mail->offset + $mailPerGo );
			$mail->sentto	= ( $mail->sentto + $sent );
			$mail->save();
			\IPS\Db::i()->readWriteSeparation = $existingvalue;
			return $offset + $mailPerGo;
		}
		catch( \OutOfRangeException $e )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
	}
	
	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array( 'text' => 'Doing something...', 'complete' => 50 )	Text explaining task and percentage complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function getProgress( $data, $offset )
	{
		$mail = \IPS\core\BulkMail\Bulkmailer::load( $data['mail_id'] );
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack( 'bulk_mail_queue_running', FALSE, array( 'sprintf' => array( $mail->subject ) ) ), 'complete' => round( 100 / $data['count'] * $offset, 2 ) );
	}
}