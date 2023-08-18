<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		11 Jan 2022
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
class _IndexNow
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		if( !\IPS\core\IndexNow::i()->isEnabled() )
		{
			return NULL;
		}

		if( isset( $data ['urls'] ) AND \count($data ['urls'] ) )
		{
			return $data;
		}
		/* We don't have any URLS to submit, so just return NULL to complete the task */
		return NULL;
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
		\IPS\core\IndexNow::i()->send( $data['urls'] );
		throw new \IPS\Task\Queue\OutOfRangeException;
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
		if( isset( $data['type']))
		{
			return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack( 'indexnow_s_submitting', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $data['type'], FALSE ), ) ) ), 'complete' => 0 );
		}
		else
		{
			return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack( 'indexnow_data_submitting' ), 'complete' => 0 );
		}
	}
}