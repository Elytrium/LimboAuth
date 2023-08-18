<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		14 Oct 2019
 */

namespace IPS\downloads\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _Notify
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
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
			$file = \IPS\downloads\File::load( $data['file'] );
		}
		catch( \OutOfRangeException $e )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$notifyIds = array();

		$recipients = iterator_to_array( \IPS\Db::i()->select( 'downloads_files_notify.*', 'downloads_files_notify', array( 'notify_file_id=?', $data['file'] ), 'notify_id ASC', array( $offset, \IPS\Downloads\File::NOTIFICATIONS_PER_BATCH ) ) );

		if( !\count( $recipients ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$notification = new \IPS\Notification( \IPS\Application::load( 'downloads' ), 'new_file_version', $file, array( $file ) );

		foreach( $recipients AS $recipient )
		{
			$recipientMember = \IPS\Member::load( $recipient['notify_member_id'] );
			if ( $file->container()->can( 'view', $recipientMember ) )
			{
				$notifyIds[] = $recipient['notify_id'];
				$notification->recipients->attach( $recipientMember );
			}
		}

		\IPS\Db::i()->update( 'downloads_files_notify', array( 'notify_sent' => time() ), \IPS\Db::i()->in( 'notify_id', $notifyIds ) );
		$notification->send();

		return $offset + \IPS\downloads\File::NOTIFICATIONS_PER_BATCH;
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
		try
		{
			$file = \IPS\downloads\File::load( $data['file'] );
		}
		catch( \OutOfRangeException $e )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$complete			= $data['notifyCount'] ? round( 100 / $data['notifyCount'] * $offset, 2 ) : 100;

		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('backgroundQueue_new_version', FALSE, array( 'htmlsprintf' => array( \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $file->url(), TRUE, $file->name, FALSE ) ) ) ), 'complete' => $complete );
	}
}