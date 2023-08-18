<?php
/**
 * @brief		Task Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		5 Aug 2013
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Task Model
 */
class _Task extends \IPS\Patterns\ActiveRecord
{
	/**
	 * Run a background queue task
	 *
	 * @return	mixed
	 */	
	public static function runQueue()
	{
		$queueData	= \IPS\Db::i()->select( '*', 'core_queue', array( 'app_enabled=?', 1 ), 'priority ASC, RAND()', 1, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->join( 'core_applications', "app=app_directory" )->first();
		$newOffset	= 0;

		$queueData['_originalOffset']	= $queueData['offset'];

		try
		{
			$extensions = \IPS\Application::load( $queueData['app'] )->extensions( 'core', 'Queue', FALSE );
			if ( !isset( $extensions[ $queueData['key'] ] ) )
			{
				throw new \IPS\Task\Queue\OutOfRangeException;
			}
			
			$class = new $extensions[ $queueData['key'] ];
			$json  = json_decode( $queueData['data'], TRUE );
			$newOffset = $class->run( $json, $queueData['offset'] );

			$queueData['offset']			= $newOffset;

			/* This is here for legacy purposes - background queue tasks should throw \IPS\Task\Queue\OutOfRangeException to indicate they are completed, but
				for now we'll still support this and log it for investigation */
			if ( \is_null( $newOffset ) )
			{
				\IPS\Log::log( $queueData['key'] . " returned a NULL offset - tasks should throw \\IPS\\Task\\Queue\\OutOfRangeException when they are finished", 'runQueue_log' );
				\IPS\Db::i()->delete( 'core_queue', array( 'id=?', $queueData['id'] ) );

				/* Do we have a post-completion callback? */
				if( method_exists( $class, 'postComplete' ) )
				{
					$class->postComplete( $queueData, TRUE );
				}
			}
			/* Task completed successfully and a new offset was returned - store the value and then return it */
			else
			{
				\IPS\Log::debug( $queueData['key'] . " returned a new offset of " . $newOffset, 'runQueue_log' );

				\IPS\Db::i()->update( 'core_queue', array( 'offset' => $newOffset ), array( 'id=?', $queueData['id'] ) );
				
				$newData = json_encode( $json );
				
				/* Did it change?? */
				if ( $newData !== $queueData['data'] )
				{
					$queueData['data'] = $newData;
					\IPS\Db::i()->update( 'core_queue', array( 'data' => $newData ), array( 'id=?', $queueData['id'] ) );
				}
			}
		}
		/* This means the task is done */
		catch( \IPS\Task\Queue\OutOfRangeException $e )
		{
			\IPS\Log::debug( $queueData['key'] . " completed successfully", 'runQueue_log' );

			\IPS\Db::i()->delete( 'core_queue', array( 'id=?', $queueData['id'] ) );

			/* Do we have a post-completion callback? */
			if( isset( $class ) AND method_exists( $class, 'postComplete' ) )
			{
				$class->postComplete( $queueData, TRUE );
			}
		}
		/* Catch any OORE in the task and transform the exception so that we can display an error */
		catch( \OutOfRangeException $e )
		{
			\IPS\Log::log( $e, 'queue_oore');

			throw new \RuntimeException( $e->getMessage() );
		}

		return $queueData;
	}

	/**
	 * Queue a background task
	 *
	 * @param	string	$app						The application that will be responsible for processing
	 * @param	string	$key						The key of the extension that will be responsible for processing
	 * @param	mixed	$data						Data necessary for processing
	 * @param	int		$priority					Run order. Values 1 to 5 are allowed, 1 being highest priority.
	 * @param	array	$checkForDuplicationKeys	Pass keys to check to prevent duplicate queue tasks being added
	 * @return	void
	 * @throws	\InvalidArgumentException	If $app or $key is invalid
	 */	
	public static function queue( $app, $key, $data = NULL, $priority=5, $checkForDuplicationKeys=NULL )
	{
		try
		{
			$extensions = \IPS\Application::load( $app )->extensions( 'core', 'Queue', FALSE );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \InvalidArgumentException;
		}
		if ( !isset( $extensions[ $key ] ) )
		{
			throw new \InvalidArgumentException;
		}
		
		if ( method_exists( $extensions[ $key ], 'preQueueData' ) )
		{
			$class = new $extensions[ $key ];
			try
			{
				$data = $class->preQueueData( $data );
			}
			catch( \OutOfRangeException $e )
			{
				$data = NULL;
			}

			if ( $data === NULL )
			{
				if ( method_exists( $class, 'postComplete' ) )
				{
					$class->postComplete( $data, FALSE );
				}
				
				return;
			}
		}
		
		if ( \is_array( $checkForDuplicationKeys ) and \is_array( $data ) )
		{
			$insert = FALSE;
			foreach( \IPS\Db::i()->select( '*', 'core_queue', array( '`app`=? AND `key`=?', $app, $key ) ) as $row )
			{
				if ( $row['data'] )
				{
					$oldData = json_decode( $row['data'], TRUE );
					$got = 0;
					
					foreach( $checkForDuplicationKeys as $k )
					{
						if ( isset( $oldData[ $k ] ) and isset( $data[ $k ] ) and $oldData[ $k ] == $data[ $k ] )
						{
							$got++;
						}
					}
					
					if ( $got === \count( $checkForDuplicationKeys ) )
					{
						/* Ok, so we have a duplicate queue item, lets remove it so the new one which is set with the correct count is used and offset is returned to 0 to start over */
						\IPS\Db::i()->delete( 'core_queue', array( 'id=?', $row['id'] ) );
					}
				}
			}
		}
	
		\IPS\Db::i()->insert( 'core_queue', array(
			'data'		=> json_encode( $data ),
			'date'		=> time(),
			'app'		=> $app,
			'key'		=> $key,
			'priority'	=> $priority
		) );
		
		\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 1 ), array( '`key`=?', 'queue' ) );
	}
	
	/* !Task */
	
	/**
	 * Get next queued task
	 *
	 * @return	\IPS\Task|NULL
	 */
	public static function queued()
	{		
		$fifteenMinutesAgo = ( time() - 900 );
		foreach ( \IPS\Db::i()->select( '*', 'core_tasks', array( 'next_run<? AND enabled=1', ( time() + 60 ) ), 'next_run ASC', NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER ) as $task )
		{
			try
			{
				$task = static::constructFromData( $task );
		
				if ( !$task->running or $task->next_run < $fifteenMinutesAgo )
				{
					if ( $task->running )
					{
						$task->unlock();
					}
					else
					{
						return $task;
					}
				}
			}
			catch( \RuntimeException $e ) { }
		}
		return NULL;
	}
	
	/**
	 * Run and log
	 *
	 * @return	void
	 */
	public function runAndLog()
	{
		$result = NULL;
		$error = FALSE;
		
		try
		{
			$result = $this->run();
		}
		catch ( \IPS\Task\Exception $e )
		{
			$result = $e->getMessage();
			$error  = 1;
		}
		
		if ( $error !== FALSE or $result !== NULL )
		{
			\IPS\Db::i()->insert( 'core_tasks_log', array(
				'task'	=> $this->id,
				'error'	=> $error,
				'log'	=> json_encode( $result ),
				'time'	=> time()
			) );
		}
	}
	
	/**
	 * Run
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function run()
	{
		/* Enforce Lock. If affected rows = 0, the task has been locked by another process */
		if( (int) \IPS\Db::i()->update( 'core_tasks', array( 'next_run' => time(), 'running' => 1 ), array( '`running`=0 AND `id`=?', $this->id ) ) === 0 )
		{
			return NULL;
		}

		/* Keep the task object aligned with the database changes made above */
		$this->running = TRUE;
		$this->next_run = time();
		
		$output = $this->execute();
		
		$this->running = 0;
		$this->lock_count = 0;
		$this->next_run = \IPS\DateTime::create()->add( new \DateInterval( $this->frequency ) )->getTimestamp();
		$this->last_run = \IPS\DateTime::create()->getTimestamp();
		$this->save();
		
		\IPS\core\AdminNotification::remove( 'core', 'ConfigurationError', "taskLock-{$this->id}" );
		
		return $output;
	}
	
	/**
	 * Execute
	 *
	 * If ran successfully, should return anything worth logging. Only log something
	 * worth mentioning (don't log "task ran successfully"). Return NULL (actual NULL, not '' or 0) to not log (which will be most cases).
	 * If an error occurs which means the task could not finish running, throw an \IPS\core\Task\Exception - do not log an error as a normal log.
	 * Tasks should execute within the time of a normal HTTP request.
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		
	}
	
	/**
	 * Run until timeout
	 *
	 * @param	callback	$callback	The code to run. Should return TRUE if we're happy to keep going, and FALSE if there's no more work to do
	 * @param	int|null	$limit		A hard limit on the number of times to call $callback, or NULL for no limit
	 * @return	void
	 */
	public function runUntilTimeout( $callback, $limit=NULL )
	{
		/* Work out the maximum execution time */
		$timeLeft = 90;
		if ( $phpMaxExecutionTime = ini_get('max_execution_time') and $phpMaxExecutionTime <= $timeLeft )
		{
			$timeLeft = $phpMaxExecutionTime - 2;
		}

		/* Factor in wait_timeout if possible */
		try
		{
			$mysqlTimeout = \IPS\Db::i()->query( "SHOW SESSION VARIABLES LIKE 'wait_timeout'" )->fetch_assoc();
			$mysqlTimeout = $mysqlTimeout['Value'];

			if( $mysqlTimeout <= $timeLeft )
			{
				$timeLeft = $mysqlTimeout - 2;
			}
		}
		catch( \IPS\Db\Exception $e ){}

		$timeTheLastRunTook = 0;
				
		/* Work out the memory limit */
		$memoryLeft = 0;
		$memoryUnlimited = FALSE;
		if ( \function_exists( 'memory_get_usage' ) )
		{
			$memory_limit = ini_get('memory_limit');
			if ( $memory_limit == -1 )
			{
				$memoryUnlimited = TRUE;
			}
			else
			{
				if ( preg_match('/^(\d+)(.)$/', $memory_limit, $matches ) )
				{
					if ( $matches[2] == 'G' )
					{
						$memory_limit = $matches[1] * 1024 * 1024 * 1024;
					}
				    elseif ( $matches[2] == 'M' )
				    {
				        $memory_limit = $matches[1] * 1024 * 1024;
				    }
				    elseif ( $matches[2] == 'K' )
				    {
				        $memory_limit = $matches[1] * 1024;
				    }
				}
				$memoryLeft = $memory_limit - memory_get_usage( TRUE );
			}
		}
		$memoryTheLastRunTook = 0;
		
		/* Run until we run out of time or hit our limit */
		$calls = 0;
		do
		{
			/* Start a timer */
			$timer = microtime( TRUE );
			$memoryTimer = \function_exists( 'memory_get_usage' ) ? memory_get_usage( TRUE ) : 0;
			
			/* Execute */
			if ( $callback() === FALSE )
			{
				break;
			}

			$calls++;

			/* If we have a limit and we've hit it, then stop now */
			if( $limit !== NULL AND $calls >= $limit )
			{
				break;
			}
			
			/* Decrease the time left */
			$timeTheLastRunTook = round( ( microtime( TRUE ) - $timer ), 2 );
			$timeLeft -= $timeTheLastRunTook;
			$memoryTheLastRunTook = \function_exists( 'memory_get_usage' ) ? ( memory_get_usage( TRUE ) - $memoryTimer ) : 0;
			if ( !$memoryUnlimited )
			{
				$memoryLeft = $memory_limit - memory_get_usage( TRUE );
			}
			
		}
		while ( $timeLeft > $timeTheLastRunTook and ( $memoryUnlimited or $memoryLeft > $memoryTheLastRunTook ) );
	}
	
	/**
	 * Unlock
	 *
	 * @return	void
	 */
	public function unlock()
	{
		if ( $this->running )
		{
			$this->running = FALSE;
			if ( $this->lock_count < 3 ) // Allowing this to grow infinitely will eventually overflow. The warning triggers at 3, so we only need to know if it's more than 3 or not.
			{
				$this->lock_count++;
				
				if ( $this->lock_count >= 3 )
				{
					\IPS\core\AdminNotification::send( 'core', 'ConfigurationError', "taskLock-{$this->id}" );
				}
			}
			$this->next_run = \IPS\DateTime::create()->add( new \DateInterval( $this->frequency ) )->getTimestamp();
			$this->save();
			$this->cleanup();
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
	
	/* Dev management */
	
	/**
	 * Dev Table
	 *
	 * @param	string			$json				Path to JSON file
	 * @param	\IPS\Http\Url	$url				URL to page
	 * @param	string			$taskDirectory		Directory where PHP files are stored
	 * @param	string			$subpackage			The value to use for the subpackage in the task file's header
	 * @param	string			$namespace			The namespace for the task file
	 * @param	int|string		$appKeyOrPluginId	If taks belongs to an application, it's key, or if a plun, it's ID
	 * @return	string
	 */
	public static function devTable( $json, $url, $taskDirectory, $subpackage, $namespace, $appKeyOrPluginId )
	{
		if ( !file_exists( $json ) )
		{
			\file_put_contents( $json, json_encode( array() ) );
		}
		
		switch ( \IPS\Request::i()->taskTable )
		{
			case 'form':
			
				$form = new \IPS\Helpers\Form;
				
				$current = NULL;
				if ( isset( \IPS\Request::i()->key ) )
				{
					$tasks = json_decode( file_get_contents( $json ), TRUE );
					if ( array_key_exists( \IPS\Request::i()->key, $tasks ) )
					{
						$current = array(
							'dev_task_key'			=> \IPS\Request::i()->key,
							'dev_task_frequency'	=> new \DateInterval( $tasks[ \IPS\Request::i()->key ] )
						);

						try
						{
							$current['id']	= \IPS\Db::i()->select( 'id', 'core_tasks', array( '`key`=?', \IPS\Request::i()->key ) )->first();
						}
						catch( \UnderflowException $e ){}
						
						$form->hiddenValues['old'] = $current['dev_task_key'];
					}
					unset( $tasks );					
				}
		
				$form->add( new \IPS\Helpers\Form\Text( 'dev_task_key', $current ? $current['dev_task_key'] : NULL, TRUE, array( 'maxLength' => 255, 'regex' => '/^[a-z0-9_]*$/i' ), function( $val ) use ( $current )
				{
					$where = array( array( '`key`=?', $val ) );

					if ( isset( $current['id'] ) )
					{
						$where[] = array( 'id<>?', $current['id'] );
					}
					
					if ( \IPS\Db::i()->select( 'count(*)', 'core_tasks', $where )->first() )
					{
						throw new \DomainException( 'dev_task_key_err' );
					}
				} ) );
				$form->add( new \IPS\Helpers\Form\Custom( 'dev_task_frequency', $current ? $current['dev_task_frequency'] : NULL, TRUE, array(
					'getHtml' => function( $element )
					{
						return \IPS\Theme::i()->getTemplate( 'forms', 'core' )->dateinterval( $element->name, $element->value ?: new \DateInterval( 'P0D' ) );
					},
					'formatValue' => function ( $element )
					{
						if ( !( $element->value instanceof \DateInterval ) )
						{
							if( !empty($element->value) )
							{
								try
								{
									$interval	= new \DateInterval( "P{$element->value['y']}Y{$element->value['m']}M{$element->value['d']}DT{$element->value['h']}H{$element->value['i']}M{$element->value['s']}S" );
								}
								catch( \Exception $e )
								{
									$interval	= \DateInterval::createFromDateString('1 day');
								}
							}
							else
							{
								$interval	= \DateInterval::createFromDateString('1 day');
							}
		
							return $interval;
						}
						return $element->value;
					}
				), function ( $val )
				{
					foreach ( $val as $k => $v )
					{
						if ( $v )
						{
							return;
						}
					}
					throw new \InvalidArgumentException( 'form_required' );
				} ) );
				
				if ( $values = $form->values() )
				{
					/* Write PHP file */
					$taskFile =  $taskDirectory . "/{$values['dev_task_key']}.php";
					if ( isset( $values['old'] ) and $values['old'] !== $values['dev_task_key'] and file_exists( $taskDirectory . "/{$values['old']}.php" ) )
					{
						@rename( $taskDirectory . "/{$values['old']}.php", $taskFile );
						\IPS\Db::i()->delete( 'core_tasks', array( '`key`=?', $values['old'] ) );
					}
					if ( !file_exists( $taskFile ) )
					{
						if ( !is_dir( $taskDirectory ) )
						{
							mkdir( $taskDirectory );
							chmod( $taskDirectory, \IPS\IPS_FOLDER_PERMISSION );
						}
						
						\file_put_contents( $taskFile, str_replace(
							array(
								'{key}',
								"{subpackage}\n",
								'{date}',
								'{namespace}',
							),
							array(
								$values['dev_task_key'],
								( $subpackage != 'core' ) ? ( " * @subpackage\t" . $subpackage . "\n" ) : '',
								date( 'd M Y' ),
								$namespace,
							),
							file_get_contents( \IPS\ROOT_PATH . "/applications/core/data/defaults/Task.txt" )
						) );
					}
					
					/* Add to DB */
					$frequency = "P{$values['dev_task_frequency']->y}Y{$values['dev_task_frequency']->m}M{$values['dev_task_frequency']->d}DT{$values['dev_task_frequency']->h}H{$values['dev_task_frequency']->i}M{$values['dev_task_frequency']->s}S";
					\IPS\Db::i()->replace( 'core_tasks', array(
						'app'		=> \is_string( $appKeyOrPluginId ) ? $appKeyOrPluginId : NULL,
						'plugin'	=> \is_numeric( $appKeyOrPluginId ) ? $appKeyOrPluginId : NULL,
						'key'		=> $values['dev_task_key'],
						'frequency'	=> $frequency,
						'next_run'	=> \IPS\DateTime::create()->add( new \DateInterval( $frequency ) )->getTimestamp(),
						'running'	=> 0,
					) );
					
					/* Add to JSON file */
					$tasks = json_decode( file_get_contents( $json ), TRUE );
					$tasks[ $values['dev_task_key'] ] = $frequency;
					if ( isset( $values['old'] ) and $values['old'] !== $values['dev_task_key'] and isset( $tasks[ $values['old'] ] ) )
					{
						unset( $tasks[ $values['old'] ] );
					}
					\file_put_contents( $json, json_encode( $tasks ) );
					
					/* Redirect */
					\IPS\Output::i()->redirect( $url, 'saved' );
					
				}
				
				return $form;
		
			case 'delete':
				\IPS\Session::i()->csrfCheck();

				$tasks = json_decode( file_get_contents( $json ), TRUE );
				if ( array_key_exists( \IPS\Request::i()->key, $tasks ) )
				{
					unset( $tasks[ \IPS\Request::i()->key ] );
					\file_put_contents( $json, json_encode( $tasks ) );
					
					if ( file_exists( $taskDirectory . "/" . \IPS\Request::i()->key . ".php" ) )
					{
						unlink( $taskDirectory . "/" . \IPS\Request::i()->key . ".php" );
					}
					
					\IPS\Db::i()->delete( 'core_tasks', array( ( \is_string( $appKeyOrPluginId ) ? 'app' : 'plugin' ) . '=? AND `key`=?', $appKeyOrPluginId, \IPS\Request::i()->key ) );
				}
				\IPS\Output::i()->redirect( $url, 'saved' );
			
			default:

				$data = array();
				foreach ( json_decode( file_get_contents( $json ), TRUE ) as $k => $f )
				{
					$data[ $k ] = array(
						'dev_task_key' => $k,
						'dev_task_frequency' => $f
					);
				}
								
				$table = new \IPS\Helpers\Table\Custom( $data, $url );
				$table->rootButtons = array(
					'add' => array(
						'icon'	=> 'plus',
						'title'	=> 'add',
						'link'	=> $url->setQueryString( 'taskTable', 'form' ),
						'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('add') )
					)
				);
				$table->rowButtons = function( $row ) use ( $url )
				{
					return array(
						'edit' => array(
							'icon'	=> 'pencil',
							'title'	=> 'edit',
							'link'	=> $url->setQueryString( 'taskTable', 'form' )->setQueryString( 'key', $row['dev_task_key'] ),
							'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit') )
						),
						'delete' => array(
							'icon'	=> 'times-circle',
							'title'	=> 'delete',
							'link'	=> $url->setQueryString( 'taskTable', 'delete' )->setQueryString( 'key', $row['dev_task_key'] )->csrf(),
							'data'	=> array( 'delete' => '' )
						)
					);
				};
				
				$table->parsers = array(
					'dev_task_frequency' => function( $v )
					{
						$interval = new \DateInterval( $v );
						$return = array();
						foreach ( array( 'y' => 'years', 'm' => 'months', 'd' => 'days', 'h' => 'hours', 'i' => 'minutes', 's' => 'seconds' ) as $k => $v )
						{
							if ( $interval->$k )
							{
								$return[] = \IPS\Member::loggedIn()->language()->addToStack( 'every_x_' . $v, FALSE, array( 'pluralize' => array( $interval->format( '%' . $k ) ) ) );
							}
						}
						
						return \IPS\Member::loggedIn()->language()->formatList( $return );
					}
				);
						
				return $table;
			
		}
	}
	
	/* !ActiveRecord */
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_tasks';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'key' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{		
		/* Initiate an object */
		if ( $data['app'] )
		{
			$classname =  'IPS\\' . $data['app'] . '\tasks\\' . $data['key'];
		}
		else
		{
			$plugin = \IPS\Plugin::load( $data['plugin'] );
			require_once \IPS\SITE_FILES_PATH . '/plugins/' . $plugin->location . '/tasks/' . $data['key'] . '.php';
			$classname = 'IPS\pluginTasks\\' . $data['key'];
			\IPS\IPS::monkeyPatch( 'IPS\pluginTasks', $data['key'] );
		}
		
		if ( ! class_exists( $classname ) )
		{
			throw new \RuntimeException;
		}
	
		$obj = new $classname;
		$obj->_new = FALSE;
		
		/* Import data */
		foreach ( $data as $k => $v )
		{
			if( static::$databasePrefix )
			{
				$k = \substr( $k, \strlen( static::$databasePrefix ) );
			}
			
			$obj->_data[ $k ] = $v;
		}
		$obj->changed = array();
				
		/* Return */
		return $obj;
	}

}