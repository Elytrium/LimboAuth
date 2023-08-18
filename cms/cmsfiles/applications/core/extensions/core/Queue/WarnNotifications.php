<?php
/**
 * @brief		Background Task: Send Warning Notifications
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		26 May 2016
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Send Warning Notifications
 */
class _WarnNotifications
{
	/**
	 * Run Background Task
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	int						New offset
	 * @throws	\IPS\Task\Queue\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( &$data, $offset )
	{
		$classname = $data['class'];
        $exploded = explode( '\\', $classname );
        if ( !class_exists( $classname ) or !\IPS\Application::appIsEnabled( $exploded[1] ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		try
		{
			$item = $classname::load( $data['item'] );
		}
		catch( \OutOfRangeException $e )
		{
			/* Item no longer exists, so we're done here. */
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		$sentTo = isset( $data['sentTo'] ) ? $data['sentTo'] : array();
		$newOffset = $item->sendNotificationsBatch( $offset, $sentTo, isset( $data['extra'] ) ? $data['extra'] : NULL );
		$data['sentTo'] = $sentTo;

		if( $newOffset === NULL )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		return $newOffset;
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
		$warning			= \IPS\core\Warnings\Warning::load( $data['item'] );
		$numberofFollowers	= \intval( $warning->notificationsCount( $warning->getModerators() ) );
		$complete			= $numberofFollowers ? ( round( 100 / $numberofFollowers * $offset, 2 ) ) : 100;

		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('backgroundQueue_follow', FALSE, array( 'htmlsprintf' => array( \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $warning->url(), TRUE, $warning->mapped('title'), FALSE ) ) ) ), 'complete' => $complete );
	}	
}