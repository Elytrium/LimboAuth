<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Sep 2017
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
class _DeleteImageProxyFiles
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$data['count']			= \IPS\Db::i()->select( 'count(*)', 'core_image_proxy' )->first();
		$data['deleted']		= 0;
		$data['cachePeriod']	= \IPS\Settings::i()->image_proxy_cache_period;

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
		/* We don't want to delete the files if we are caching indefinitely */
		if( !$data['cachePeriod'] )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		/* Kill the process if there table doesn't exist anymore */
		if( !\IPS\Db::i()->checkForTable( 'core_image_proxy' ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$select = \IPS\Db::i()->select( 'location', 'core_image_proxy', array(), 'cache_time ASC', \IPS\REBUILD_SLOW );

		$completed	= 0;

		foreach ( $select as $location )
		{
			try
			{
				\IPS\File::get( 'core_Imageproxycache', $location )->delete();
			}
			catch ( \Exception $e ) { }

			\IPS\Db::i()->delete( 'core_image_proxy', array( 'location=?', $location ) );

			$data['deleted']++;
			$completed++;
		}

		if( $completed === 0 )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		return $completed + $offset;
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
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('deleting_imageproxy_files'), 'complete' => $data['count'] ? ( round( ( 100 / $data['count'] ) * $data['deleted'], 2 ) ) : 100 );
	}

	/**
	 * Perform post-completion processing
	 *
	 * @param	array	$data		Data returned from preQueueData
	 * @param	bool	$processed	Was anything processed or not? If preQueueData returns NULL, this will be FALSE.
	 * @return	void
	 */
	public function postComplete( $data, $processed = TRUE )
	{
		\IPS\Db::i()->dropTable( 'core_image_proxy' );
	}
}