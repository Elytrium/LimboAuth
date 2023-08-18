<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 Mar 2021
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
class _RebuildAchievements
{
	/**
	 * @brief Number of items to build per cycle
	 */
	public $perCycle	= \IPS\REBUILD_SLOW;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$data['processed'] = 0;
		$data['count'] = 0;
		$data['lastId'] = 0;

		try
		{
			list( $app, $extension ) = explode( '_', $data['extension'] );
			$extensionObject = \IPS\Application::load( $app )->extensions( 'core', 'AchievementAction' )[ $extension ];
			$first = false;
			foreach( $data['data'] as $entry )
			{
				if ( ! $first )
				{
					$first = true;
					$data['currentTable'] = $entry['table'];
				}

				$where = $entry['where'];
				if ( $data['time'] and $entry['date'] )
				{
					$where[] = [ $entry['date'] . ' > ?', $data['time'] ];
				}

				$data['count'] += \IPS\Db::i()->select( 'COUNT(*)', $entry['table'], $where )->first();
			}
		}
		catch( \Exception $ex )
		{
			return null;
		}

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
		/* Silence notifications */
		\IPS\Notification::silence();
		
		$process = NULL;
		foreach( $data['data'] as $entry )
		{
			if ( $data['currentTable'] == $entry['table'] )
			{
				$process = $entry;
				break;
			}
		}

		if ( $process === NULL )
		{
			\IPS\Log::log( "Nothing to process for " . json_encode( $data['data'] ), "achievements" );
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		list( $app, $extension ) = explode( '_', $data['extension'] );
		try
		{
			$extensionObject = \IPS\Application::load( $app )->extensions( 'core', 'AchievementAction' )[ $extension ];
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Log::debug("App {$app} deleted. Nothing to do.", "achievements");
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$where = $process['where'];
		if ( \stristr( $process['pkey'], '(' ) )
		{
			$where[] = [ $process['pkey'] . ' > ?', $data['lastId'] ];
		}
		else
		{
			$where[] = [ '`' . $process['pkey'] . '` > ?', $data['lastId'] ];
		}

		if ( $data['time'] and $process['date'] )
		{
			$where[] = [ '`' . $process['date'] . '` > ?', $data['time'] ];
		}

		$done = 0;
		$select = ( isset( $process['select'] ) ) ? array_merge( [ '*' ], $process['select'] ) : [ '*' ];
		foreach( \IPS\Db::i()->select( implode( ',', $select ), $process['table'], $where, $process['pkey'], $this->perCycle ) as $dbRow )
		{
			$data['lastId'] = $dbRow[ $process['pkey'] ];

			$done++;
			try
			{
				$extensionObject::rebuildRow( $dbRow, $process );
			}
			catch( \Exception $e ) {}
		}

		if( ! $done )
		{
			if ( \count( $data['data'] ) > 1 )
			{
				$oldTable = $data['currentTable'];
				$doNext = FALSE;
				$found = FALSE;
				foreach( $data['data'] as $entry )
				{
					if ( $oldTable == $entry['table'] )
					{
						$doNext = TRUE;
					}
					else if ( $doNext )
					{
						$found = TRUE;
						$data['currentTable'] = $entry['table'];
						break;
					}
				}

				if ( $found )
				{
					$data['lastId'] = 0;
					\IPS\Log::debug( "Finished {$data['extension']} {$oldTable}, next {$data['currentTable']}", "achievements" );
				}
				else
				{
					\IPS\Log::debug("Finished " . $data['extension'] . " as nothing to process", "achievements");
					throw new \IPS\Task\Queue\OutOfRangeException;
				}
			}
			else
			{
				\IPS\Log::debug("Finished " . $data['extension'] . " as nothing to process", "achievements");
				throw new \IPS\Task\Queue\OutOfRangeException;
			}
		}

		$data['processed'] += $done;
		
		/* We use lastId in the data and not the offset anyway, but it's nice to be nice to the framework */
		return \is_numeric( $data['lastId'] ) ? $data['lastId'] : 1;
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
		list( $app, $extension ) = explode( '_', $data['extension'] );
		if ( !\IPS\Application::appIsEnabled( $app ) )
		{
			throw new \OutOfRangeException;
		}

		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('rebuilding_achievements', FALSE, array( 'htmlsprintf' => array( $data['extension'] ) ) ), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $data['processed'], 2 ) ) : 100 );
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
		/* The core_queue row is deleted before this is run */
		if ( ! \IPS\Db::i()->select( 'COUNT(*)', 'core_queue', [ [ '`key`=?', 'RebuildAchievements' ] ], NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER  )->first() )
		{
			/* Rebuilding is complete! */
			\IPS\Settings::i()->changeValues( array( 'achievements_rebuilding' => 0, 'achievements_last_rebuilt' => time() ) );
		}
	}
}