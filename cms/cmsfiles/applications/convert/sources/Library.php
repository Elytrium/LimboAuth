<?php

/**
 * @brief		Converter Library Master Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		21 Jan 2015
 */

namespace IPS\convert;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Converter Library Class
 */
abstract class _Library
{
	/**
	 * @brief	Flag to indicate that we are using a specific key, and should do WHERE static::$currentKeyName > static::$currentKeyValue rather than LIMIT 0,1000
	 */
	public static $usingKeys			= FALSE;
	
	/**
	 * @brief	The name of the current key in the database for this step
	 */
	public static $currentKeyName	= NULL;
	
	/**
	 * @brief	The current value of the key
	 */
	public static $currentKeyValue	= 0;
	
	/**
	 * @brief	Amount of data being processed per cycle.
	 */
	public static $perCycle				= 2000;
	
	/**
	 * @brief	If not using keys, the current start value for LIMIT clause.
	 */
	public static $startValue		= 0;
	
	/**
	 * @brief	The current conversion step
	 */
	public static $action			= NULL;
	
	/**
	 * @brief	\IPS\convert\Software instance for the software we are converting from
	 */
	public $software					= NULL;

	/**
	 * @brief	Obscure filenames?
	 *
	 * This is only referenced in certain content types that may be referenced in already parsed data such as attachments and emoticons
	 * - Designed for use with the Invision Community converter.
	 */
	public static $obscureFilenames		= TRUE;
	
	/**
	 * @brief	Array of field types
	 */
	protected static $fieldTypes = array( 'Address', 'Checkbox', 'CheckboxSet', 'Codemirror', 'Color', 'Date', 'Editor', 'Email', 'Item', 'Member', 'Number', 'Password', 'Poll', 'Radio', 'Rating', 'Select', 'Tel', 'Text', 'TextArea', 'Upload', 'Url', 'YesNo' );
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\convert\Software	$software	Software Instance we are converting from
	 * @return	void
	 */
	public function __construct( \IPS\convert\Software $software )
	{
		$this->software = $software;
	}
	
	/**
	 * Libraries
	 *
	 * @return	array
	 */
	public static function libraries()
	{
		return array(
			'core'			=> 'IPS\convert\Library\Core',
			'blog'			=> 'IPS\convert\Library\Blog',
			'calendar'		=> 'IPS\convert\Library\Calendar',
			'cms'			=> 'IPS\convert\Library\Cms',
			'downloads'		=> 'IPS\convert\Library\Downloads',
			'forums'		=> 'IPS\convert\Library\Forums',
			'gallery'		=> 'IPS\convert\Library\Gallery',
			'nexus'			=> 'IPS\convert\Library\Nexus',
		);
	}
	
	/**
	 * When called at the start of a conversion step, indicates that we are using a specific key for WHERE clauses which nets performance improvements
	 *
	 * @param	string	$key	The key to use
	 * @return	void
	 */
	public static function setKey( $key )
	{
		static::$usingKeys		= TRUE;
		static::$currentKeyName	= $key;
	}
	
	/**
	 * When using a key reference, sets the current value of that key for the WHERE clause
	 *
	 * @param	mixed	$value	The current value
	 * @return	void
	 */
	public function setLastKeyValue( $value )
	{
		$_SESSION['currentKeyValue'] = $value;
		static::$currentKeyValue = $value;
	}
	
	/**
	 * Processes a conversion cycle.
	 *
	 * @param	integer				$data		Data from the previous step.
	 * @param	string				$method		Conversion method we are processing
	 * @param	int|NULL			$perCycle	The number of results to process per cycle
	 * @return	array|NULL	Data for the MultipleRedirect
	 */
	public function process( $data, $method, $perCycle=NULL )
	{
		if ( !\is_null( $perCycle ) )
		{
			static::$perCycle = $perCycle;
		}
		
		/* temp */
		$classname			= \get_class( $this->software );
		$canConvert			= $classname::canConvert();

		/* If we hit something that can't be converted, there's a problem */
		if( $canConvert === NULL )
		{
			return NULL;
		}

		static::$action		= $canConvert[ $method ]['table'];
		static::$startValue	= $data;
		$masterAppId = $this->software->app->getMasterConversionId();
		
		if ( !isset( $_SESSION['convertCountRows'] ) )
		{
			$_SESSION['convertCountRows'] = array();
		}

		if ( !isset( $_SESSION['convertCountRows'][ $masterAppId ] ) )
		{
			$_SESSION['convertCountRows'][ $masterAppId ] = array();
		}

		if ( !isset( $_SESSION['convertCountRows'][ $masterAppId ][ $this->software->app->app_id ] ) )
		{
			$_SESSION['convertCountRows'][ $masterAppId ][ $this->software->app->app_id ] = array();
		}
		
		if ( !isset( $_SESSION['convertCountRows'][ $masterAppId ][ $this->software->app->app_id ][ $method ] ) )
		{
			$_SESSION['convertCountRows'][ $masterAppId ][ $this->software->app->app_id ][ $method ] = $this->software->countRows( static::$action, $canConvert[ $method ]['where'], true );
		}
		
		$total = $_SESSION['convertCountRows'][ $masterAppId ][ $this->software->app->app_id ][ $method ];
		
		if ( $data >= $total )
		{
			$completed	= $this->software->app->_session['completed'];
			$more_info	= $this->software->app->_session['more_info'];
			if ( !\in_array( $method, $completed ) )
			{
				$completed[] = $method;
			}

			/* Manually set running flag to save write queries */
			$running = $this->software->app->_session['running'];
			$running[ $method ] = FALSE;

			$this->software->app->_session = array( 'working' => array(), 'more_info' => $more_info, 'completed' => $completed, 'running' => $running );
			unset( $_SESSION['currentKeyValue'] );
			unset( $_SESSION['convertContinue'] );

			$percentage	= ( 100 / static::getTotalCachedRows( $this->software->app->getMasterConversionId() ) ) * $this->_getConvertedCount();
			return array( 0, sprintf( \IPS\Member::loggedIn()->language()->get( 'converted_x_of_x' ), $total, $total, \IPS\Member::loggedIn()->language()->addToStack( '_' . $this->getMethodFromMenuRows( $method )['step_title'] ) ), $percentage );
		}
		else
		{
			/* Are we continuing? Let's figure out where we are... unfortunately, this only works when we are using keys (which is 99% of the time) */
			if ( isset( $_SESSION['convertContinue'] ) AND $_SESSION['convertContinue'] === TRUE )
			{
				$table = 'convert_link';

				switch( $method )
				{
					case 'convertForumsPosts':
						$table = 'convert_link_posts';
						break;
					
					case 'convertForumsTopics':
						$table = 'convert_link_topics';
						break;
					
					case 'convertPrivateMessages':
					case 'convertPrivateMessageReplies':
						$table = 'convert_link_pms';
						break;
				}
				
				/* Select our max foreign ID */
				$type = $this->getMethodFromMenuRows( $method )['link_type'];

				if( \is_array( $type ) )
				{
					$type = $type[0];
				}

				try
				{
					$lastId = \IPS\Db::i()->select( 'foreign_id', $table, array( "app=? AND type=?", $this->software->app->app_id, $type ), 'link_id DESC', 1 )->first();
				}
				catch( \UnderflowException $e )
				{
					// Nothing has been ran yet
					$lastId = 0;
				}

				/* Set the data count for MR */
				$data = \IPS\Db::i()->select( 'COUNT(*)', $table, array( "app=? AND type=?", $this->software->app->app_id, $type ) )->first();

				/* Set as last key value */
				$this->setLastKeyValue( $lastId );

				/* Clear the running flag but only after we've given previous runs a chance to clear */
				if( $this->software->app->getRunningFlag( $method ) AND $this->software->app->getRunningFlag( $method ) < ( time() - 45 ) )
				{
					unset( $_SESSION['convertSkipped'] );
					$this->software->app->setRunningFlag( $method, FALSE );
				}

				/* Unset this so we don't do this again. */
				$_SESSION['convertContinue'] = false;
				unset( $_SESSION['convertContinue'] );
			}

			/* Conversion process is still running, process may have broken out of the multiredirector */
			if( $this->software->app->getRunningFlag( $method ) )
			{
				/* Count how many times we've seen this */
				$_SESSION['convertSkipped'] = ( isset( $_SESSION['convertSkipped'] ) ? $_SESSION['convertSkipped'] : 0 ) + 1;

				/* If we've shown this 5 times,  and the flag was set more than 60 seconds ago, there's a good chance something is wrong */
				if( $_SESSION['convertSkipped'] > 5 AND $this->software->app->getRunningFlag( $method ) < ( time() - 60 ))
				{
					unset( $_SESSION['convertSkipped'] );
					\IPS\Output::i()->error( 'converter_could_not_continue', '2V387/1', 403, '' );
				}

				/* Generate dots for UI to show that processing is still occurring */
				$dots = str_repeat( '.', $_SESSION['convertSkipped'] );

				/* Sleep to allow the other process to complete without hammering the server */
				sleep(5);
				return array( $data, \IPS\Member::loggedIn()->language()->get( 'waiting_previous_process' ) . $dots, ( 100 / static::getTotalCachedRows( $this->software->app->getMasterConversionId() ) ) * $this->_getConvertedCount( $total ) );
			}

			/* Set conversion as running */
			$this->software->app->setRunningFlag( $method, TRUE );

			/* Fetch data from the software */
			try
			{
				$this->software->$method();
			}
			catch( \IPS\convert\Software\Exception $e )
			{
				/* A Software Exception indicates we are done */
				$completed	= $this->software->app->_session['completed'];
				$more_info	= $this->software->app->_session['more_info'];
				if ( !\in_array( $method, $completed ) )
				{
					$completed[] = $method;
				}

				/* Manually set running flag to save write queries */
				$running = $this->software->app->_session['running'];
				$running[ $method ] = FALSE;

				$this->software->app->_session = array( 'working' => array(), 'more_info' => $more_info, 'completed' => $completed, 'running' => $running );
				unset( $_SESSION['currentKeyValue'] );
				unset( $_SESSION['convertContinue'] );

				$percentage	= ( 100 / static::getTotalCachedRows( $this->software->app->getMasterConversionId() ) ) * $this->_getConvertedCount();
				return array( 0, sprintf( \IPS\Member::loggedIn()->language()->get( 'converted_x_of_x' ), $total, $total, \IPS\Member::loggedIn()->language()->addToStack( '_' . $this->getMethodFromMenuRows( $method )['step_title'] ) ), $percentage );
			}
			catch( \Exception $e )
			{
				\IPS\Log::log( $e, 'converters' );

				/* Clear the running flag */
				$this->software->app->setRunningFlag( $method, FALSE );

				$this->software->app->log( $e->getMessage(), __METHOD__, \IPS\convert\App::LOG_WARNING );
				throw new \IPS\convert\Exception;
			}
			catch( \ErrorException $e )
			{
				\IPS\Log::log( $e, 'converters' );

				/* Clear the running flag */
				$this->software->app->setRunningFlag( $method, FALSE );

				$this->software->app->log( $e->getMessage(), __METHOD__, \IPS\convert\App::LOG_WARNING );
				throw new \IPS\convert\Exception;
			}

			/* Manually set running flag to save write queries */
			$running = $this->software->app->_session['running'];
			$running[ $method ] = FALSE;

			$this->software->app->_session = array_merge( $this->software->app->_session, array( 'working' => array( $method => $data + static::$perCycle ), 'running' => $running ) );

			$percentage	= ( 100 / static::getTotalCachedRows( $this->software->app->getMasterConversionId() ) ) * $this->_getConvertedCount( ( $data + static::$perCycle > $total ) ? $total : $data + static::$perCycle );
			return array( $data + static::$perCycle, sprintf( \IPS\Member::loggedIn()->language()->get( 'converted_x_of_x' ), ( $data + static::$perCycle ) < $total ? $data + static::$perCycle : $total, $total, \IPS\Member::loggedIn()->language()->addToStack( '_' . $this->getMethodFromMenuRows( $method )['step_title'] ) ), $percentage );
		}
	}

	/**
	 * Return the count of everything converted so far
	 *
	 * @param	int	$add	Amount to add to completed count
	 * @return	int
	 */
	protected function _getConvertedCount( $add = 0 )
	{
		if( $this->software->app->parent )
		{
			$masterAppId = $this->software->app->_parent->app_id;
			$applicationsToCheck[]	= $this->software->app->_parent;
			$applicationsToCheck	= array_merge( $applicationsToCheck, iterator_to_array( $this->software->app->_parent->children() ) );
		}
		else
		{
			$masterAppId = $this->software->app->app_id;
			$applicationsToCheck[]	= $this->software->app;
			$applicationsToCheck	= array_merge( $applicationsToCheck, iterator_to_array( $this->software->app->children() ) );
		}

		$totalSoFar	= 0;

		foreach( $applicationsToCheck as $app )
		{
			/* Check app has cached data */
			if( !isset( $_SESSION['convertCountRows'][ $masterAppId ][ $app->app_id ] ) )
			{
				continue;
			}

			$totalSoFar += array_sum(
				array_filter( $_SESSION['convertCountRows'][ $masterAppId ][ $app->app_id ], function( $key ) use( $app ) {
					return ( \in_array( $key, $app->_session['completed'] ) );
				}, ARRAY_FILTER_USE_KEY )
			);
		}

		if( $add )
		{
			$totalSoFar += $add;
		}

		return $totalSoFar;
	}
	
	/**
	 * Empty Conversion Data
	 *
	 * @param	integer				$data	Data from the previous step.
	 * @param	\IPS\convert\App	$method	The conversion method to empty results for
	 * @return	array|NULL	Data for the MultipleRedirect
	 */
	public function emptyData( $data, $method )
	{
		$perCycle = 500;
		
		/* temp */
		$classname			= \get_class( $this->software );
		$canConvert			= $this->menuRows();
		
		if ( !isset( $canConvert[ $method ]['link_type'] ) )
		{
			return NULL;
		}
		
		$type = $canConvert[ $method ]['link_type'];
		
		if ( !isset( $_SESSION['emptyConvertedDataCount'] ) )
		{
			$count = 0;
			/* Just one type? */
			if ( !\is_array( $type ) )
			{
				foreach( array( 'convert_link', 'convert_link_pms', 'convert_link_posts', 'convert_link_topics' ) as $table )
				{
					$count += \IPS\Db::i()->select( 'COUNT(*)', $table, array( "type=? AND app=?", $type, $this->software->app->app_id ) )->first();
				}
			}
			else
			{
				foreach( $type as $t )
				{
					foreach( array( 'convert_link', 'convert_link_pms', 'convert_link_posts', 'convert_link_topics' ) as $table )
					{
						$count += \IPS\Db::i()->select( 'COUNT(*)', $table, array( "type=? AND app=?", $t, $this->software->app->app_id ) )->first();
					}
				}
			}
			
			$_SESSION['emptyConvertedDataCount'] = $count;
		}
		
		if ( $data >= $_SESSION['emptyConvertedDataCount'] )
		{
			unset( $_SESSION['emptyConvertedDataCount'] );
			return NULL;
		}
		else
		{
			/* Fetch data from the software */
			try
			{
				/* If we're dealing with more than one type, then we can just delete from any one at random until we're done */
				if ( \is_array( $type ) )
				{
					$type = array_rand( $type );
				}
				
				switch( $type )
				{
					case 'forums_topics':
						$table = 'convert_link_topics';
						break;
					
					case 'forums_posts':
						$table = 'convert_link_posts';
						break;
					
					case 'core_message_topics':
					case 'core_message_posts':
					case 'core_message_topic_user_map':
						$table = 'convert_link_pms';
						break;
					
					default:
						$table = 'convert_link';
						break;
				}
				
				$total	= (int) \IPS\Db::i()->select( 'COUNT(*)', $table, array( "type=? AND app=?", $type, $this->software->app->app_id ) )->first();
				$rows	= iterator_to_array( \IPS\Db::i()->select( 'link_id, ipb_id', $table, array( "type=? AND app=?", $type, $this->software->app->app_id ), "link_id ASC", array( 0, $perCycle ) )->setKeyField( 'link_id' )->setValueField( 'ipb_id' ) );
				$def	= \IPS\Db::i()->getTableDefinition( $type );
				
				if ( isset( $def['indexes']['PRIMARY']['columns'] ) )
				{
					$id = array_pop( $def['indexes']['PRIMARY']['columns'] );
				}
				
				\IPS\Db::i()->delete( $type, array( \IPS\Db::i()->in( $id, array_values( $rows ) ) ) );
				\IPS\Db::i()->delete( $table, array( \IPS\Db::i()->in( 'link_id', array_keys( $rows ) ) ) );
			}
			catch( \Exception $e )
			{
				\IPS\Log::log( $e, 'converters' );

				$this->software->app->log( $e->getMessage(), __METHOD__, \IPS\convert\App::LOG_WARNING );
				throw new \IPS\convert\Exception;
			}
			catch( \ErrorException $e )
			{
				\IPS\Log::log( $e, 'converters' );

				$this->software->app->log( $e->getMessage(), __METHOD__, \IPS\convert\App::LOG_ERROR );
				throw new \IPS\convert\Exception;
			}
			
			return array( $data + $perCycle, sprintf( \IPS\Member::loggedIn()->language()->get( 'removed_x_of_x' ), ( $data + $perCycle > $total ) ? $_SESSION['emptyConvertedDataCount'] : $data + $perCycle, \IPS\Member::loggedIn()->language()->addToStack( $method ), $_SESSION['emptyConvertedDataCount'] ), 100 / $_SESSION['emptyConvertedDataCount'] * ( $data + $perCycle ) );
		}
	}
	
	/**
	 * Truncates data from local database
	 *
	 * @param	string	$method	Convert method to run truncate call for
	 * @return	void
	 */
	public function emptyLocalData( $method )
	{
		$truncate = $this->truncate( $method );

		/* Get the link type */
		$menuRows = $this->menuRows();

		/* Delete these */
		$toDelete = array( 'convert_link' => array(), 'convert_link_pms' => array(), 'convert_link_posts' => array(), 'convert_link_topics' => array() );

		foreach( $truncate as $table => $where )
		{
			/* Kind of a hacky way to make sure we truncate the right forums archive table */
			if ( $table === 'forums_archive_posts' )
			{
				\IPS\forums\Topic\ArchivedPost::db()->delete( $table, $where );
			}
			else
			{
				\IPS\Db::i()->delete( $table, $where );
			}

			/* Do we have a specific link type? */
			$linkType = NULL;
			if( isset( $menuRows[ $method ] ) AND isset( $menuRows[ $method ]['link_type'] ) )
			{
				$linkType = $menuRows[ $method ]['link_type'];
			}

			switch( $linkType )
			{
				default:
					$key = $linkType ?: $table;
					if( \is_array( $key ) )
					{
						foreach( $key as $link )
						{
							$toDelete['convert_link'][ $link ] = $link;
						}
					}
					else
					{
						$toDelete['convert_link'][ $key ] = $key;
					}
					break;
				case 'core_message_topics':
				case 'core_message_posts':
					$toDelete['convert_link_pms'][ $linkType ] = $linkType;
					break;
				case 'forums_topics':
					$toDelete['convert_link_topics'][ $linkType ] = $linkType;
					break;
				case 'forums_posts':
					$toDelete['convert_link_posts'][ $linkType ] = $linkType;
					break;
			}
		}
		unset( $_SESSION['currentKeyValue'] );

		foreach( $toDelete AS $table => $links )
		{
			if( !\count( $links ) )
			{
				continue;
			}

			/* If posts or topics we may be able to truncate */
			if( \in_array( $table, array( 'convert_link_topics', 'convert_link_posts' ) ) )
			{
				try
				{
					/* Check for other app data in this table */
					\IPS\Db::i()->select( 'link_id', $table, array('app<>?', $this->software->app->app_id ), NULL, 1 )->first();
				}
				catch ( \UnderflowException $e )
				{
					/* There isn't any other app data in this table, truncate */
					\IPS\Db::i()->delete( $table );
					continue;
				}
			}

			\IPS\Db::i()->delete( $table, array( \IPS\Db::i()->in( 'type', $links ) . " AND app=?", $this->software->app->app_id ) );
		}

		/* Clear the running flag */
		$this->software->app->setRunningFlag( $method, FALSE );
	}

	/**
	 * @brief	Cached convertable items
	 */
	protected $convertable	= NULL;

	/**
	 * Return the items we can convert. Removes things that are empty.
	 *
	 * @return	array
	 */
	public function getConvertableItems()
	{
		if( $this->convertable !== NULL )
		{
			return $this->convertable;
		}

		$classname			= \get_class( $this->software );
		$this->convertable	= $classname::canConvert();

		if( $this->convertable === NULL )
		{
			$this->convertable = array();
		}

		foreach( $this->convertable as $k => $v )
		{
			if( $this->software->countRows( $v['table'], $v['where'] ) == 0 )
			{
				unset( $this->convertable[ $k ] );
			}
		}

		return $this->convertable;
	}
	
	/**
	 * Magic __call() method
	 *
	 * @param	string	$name			The method to call without convert prefix.
	 * @param	mixed	$arguments		Arguments to pass to the method
	 * @return 	mixed
	 */
	public function __call( $name, $arguments )
	{
		if ( method_exists( $this, 'convert' . $name ) )
		{
			$function = 'convert' . $name;
			return $this->$function( $arguments );
		}
		elseif ( method_exists( $this, $name ) )
		{
			return $this->$name( $arguments );
		}
		else
		{
			\IPS\Log::log( "Call to undefined method in " . \get_class( $this ) . "::{$name}", 'converters' );
			return NULL;
		}
	}
	
	/**
	 * Returns a block of text, or a language string, that explains what the admin must do to complete this conversion
	 *
	 * @return	string
	 */
	public function getPostConversionInformation()
	{
		return '';
	}

	/**
	 * Return a count of cached convertable rows
	 *
	 * @param	int		$appId		Parent conversion APP ID
	 * @return	int|null
	 */
	public static function getTotalCachedRows( int $appId ) :?int
	{
		if( empty( $_SESSION['convertCountRows'][ $appId ] ) )
		{
			return 0;
		}

		return array_sum( array_map( function( $app ) {
			return array_sum( $app );
		}, $_SESSION['convertCountRows'][ $appId ] ) );
	}

	/**
	 * Returns an array of items that we can convert, including the amount of rows stored in the Community Suite as well as the recommend value of rows to convert per cycle
	 *
	 * @param	bool	$rowCounts		enable row counts
	 * @return	array
	 */
	abstract public function menuRows( $rowCounts=FALSE );

	/**
	 * Utility method to run queries for menuRows() data
	 *
	 * @param	array 	$return		menuRow() data
	 * @param	bool	$ips		Count IPS rows
	 * @param	bool	$source		Count source rows
	 * @return	array
	 * @throws	\UnderflowException
	 * @throws	\IPS\convert\Exception
	 */
	public function getDatabaseRowCounts( array $return, $ips=TRUE, $source=TRUE )
	{
		foreach( $return as $key => $value )
		{
			if( $ips AND isset( $value['ips_rows'] ) AND $value['ips_rows'] instanceof \IPS\Db\Select )
			{
				$return[ $key ]['ips_rows'] = (int) $value['ips_rows']->first();
			}

			if( $source AND isset( $return[ $key ]['source_rows']['table'] ) )
			{
				$return[ $key ]['source_rows'] = $this->software->countRows( $value['source_rows']['table'], $value['source_rows']['where'] );
			}
		}

		return $return;
	}

	/**
	 * @brief	Cache menu row data
	 */
	protected $_menuRowCache = array( 'rows' => array(), 'noRows' => array() );

	/**
	 * Get method from menu rows - abstracted to allow 'fake' entries not in menuRows()
	 *
	 * @param	string	$method			Method requested
	 * @param	bool	$rowCount		Count local rows
	 * @return	array
	 */
	public function getMethodFromMenuRows( $method, $rowCount=FALSE )
	{
		$key = $rowCount ? 'rows' : 'noRows';
		if( isset( $this->_menuRowCache[ $key ][ $method ] ) )
		{
			return $this->_menuRowCache[ $key ][ $method ];
		}

		/* Set to to the cache */
		$this->_menuRowCache[ $key ] = $this->menuRows( $rowCount );

		/* Cache an empty array for the response */
		if( !isset( $this->_menuRowCache[ $key ][ $method ] ) )
		{
			$this->_menuRowCache[ $key ][ $method ] = array();
		}

		return $this->_menuRowCache[ $key ][ $method ];
	}
	
	/**
	 * Returns an array of tables that need to be truncated when Empty Local Data is used
	 *
	 * @param	string	$method	The method to truncate
	 * @return	array
	 */
	abstract protected function truncate( $method );
}