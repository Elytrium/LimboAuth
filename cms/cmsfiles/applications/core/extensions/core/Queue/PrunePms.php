<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		27 Sep 2022
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
class _PrunePms
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$rows = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_message_topics', array( 'mt_last_post_time < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . \IPS\Settings::i()->prune_pms . 'D' ) )->getTimestamp() ) ), '\IPS\core\Messenger\Conversation');
		$data['count'] = $rows->count();

		if( $data['count'] == 0 )
		{
			return null;
		}

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
	public function run( &$data, $offset )
	{
		$rows = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_message_topics', array( 'mt_last_post_time < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . \IPS\Settings::i()->prune_pms . 'D' ) )->getTimestamp() ), 'mt_last_post_time ASC', 100 ), '\IPS\core\Messenger\Conversation');

		if ( !$rows->count() )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		foreach( $rows AS $conversation )
		{
			$conversation->delete();

			$offset++;
		}

		return $offset;
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
		$text = \IPS\Member::loggedIn()->language()->addToStack('pruning_pms', FALSE, array() );

		return array( 'text' => $text, 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $offset, 2 ) ) : 100 );
	}
}