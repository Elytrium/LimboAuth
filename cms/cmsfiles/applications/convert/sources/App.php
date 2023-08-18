<?php

/**
 * @brief		Converter Applications Class
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
 * Converter Application Class
 */
class _App extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array( 'convert_apps' );

	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId		= 'app_id';
	
	/**
	 * @brief	[ActiveRecord] Database table
	 */
	public static $databaseTable		= 'convert_apps';
	
	/**
	 * @brief	Array Storage of loaded ID links.
	 */
	protected $linkCache				= array();
	
	/**
	 * @brief	Flag to indicate the log is simply a notice, and that it is informational only. Useful for when data is missing, but can be covered via default values.
	 */
	const LOG_NOTICE					= 1;
	
	/**
	 * @brief	Flag to indicate the log is a warning, and should be checked to see if conversion happened correctly. Useful for indicating something cannot be converted due to being orphaned or otherwise having missing data (ex. no parent topic).
	 */
	const LOG_WARNING					= 2;
	
	/**
	 * @brief	Flag to indicate something went wrong, and the data did not convert correctly. Useful for indicating when something legitimately does not convert, but should.
	 */
	const LOG_ERROR						= 3;
	
	/**
	 * Get converted apps
	 *
	 * @return	\IPS\Patterns\ActiveRecordIterator
	 */
	public static function apps()
	{
		return new \IPS\Patterns\ActiveRecordIterator( new \ArrayIterator( static::getStore() ), 'IPS\convert\App' );
	}

	/**
	 * Get all converter apps
	 *
	 * @return	array
	 */
	public static function getStore()
	{
		if ( !isset( \IPS\Data\Store::i()->convert_apps ) )
		{
			try
			{
				$rows = iterator_to_array( \IPS\Db::i()->select( '*', 'convert_apps', array(), 'app_id ASC' ) );
			}
			catch ( \IPS\Db\Exception $e )
			{
				if ( $e->getCode() === 1146 )
				{
					$rows = iterator_to_array( \IPS\Db::i()->select( '*', 'conv_apps', array(), 'app_id ASC' ) );
				}
				else
				{
					throw $e;
				}
			}
			
			\IPS\Data\Store::i()->convert_apps = $rows;
		}

		return \IPS\Data\Store::i()->convert_apps;
	}
	
	/**
	 * [ActiveRecord]	Save Record
	 *
	 * @return	void
	 */
	public function save()
	{
		if ( !$this->app_id )
		{
			$this->start_date = time();
			parent::save();
			
			\IPS\convert\Application::checkConvParent( $this->getSource()->getLibrary()->app );
			
			\IPS\Db::i()->insert( 'convert_app_sessions', array(
				'session_app_id'	=> $this->app_id,
				'session_app_data'	=> json_encode( array( 'completed' => array(), 'working' => array(), 'more_info' => array() ) ),
			) );
		}
		
		$classname			= \get_class( $this->getSource( TRUE, FALSE ) );
		$this->login		= ( $classname::loginEnabled() === TRUE ) ? 1 : 0;
		$this->db_driver	= 'mysql';  /* I was going to drop this, but it has the potential for expansion in the future, as all we do is select from the source */
		$this->app_merge	= 1;
		parent::save();
	}
	
	/**
	 * [ActiveRecord]	Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		foreach( array( 'convert_link', 'convert_link_pms', 'convert_link_topics', 'convert_link_posts' ) AS $table )
		{
			\IPS\Db::i()->delete( $table, array( 'app=?', $this->app_id ) );
		}
		
		\IPS\Db::i()->delete( 'convert_app_sessions', array( 'session_app_id=?', $this->app_id ) );
		\IPS\Db::i()->delete( 'convert_logs', array( 'log_app=?', $this->app_id ) );
		
		parent::delete();
	}

	/**
	 * @brief	Session data store
	 */
	protected $_sessionData = null;
	
	/**
	 * Save Session Data for this application
	 *
	 * @param	array	$value	Session Data
	 * @return	void
	 */
	public function set__session( $value )
	{
		\IPS\Db::i()->update( 'convert_app_sessions', array( 'session_app_data' => json_encode( $value ) ), array( 'session_app_id=?', $this->app_id ) );

		$this->_sessionData = $value;
	}
	
	/**
	 * Get Session Data for this application
	 *
	 * @return	array
	 */
	public function get__session()
	{
		/* Use ready-cached session data */
		if( $this->_sessionData !== null )
		{
			return $this->_sessionData;
		}

		try
		{
			$this->_sessionData = json_decode( \IPS\Db::i()->select( 'session_app_data', 'convert_app_sessions', array( 'session_app_id=?', $this->app_id ) )->first(), TRUE );
			return $this->_sessionData;
		}
		catch( \Exception $e )
		{
			/* If it doesn't exist, create it in the database and return an empty array. */
			\IPS\Db::i()->insert( 'convert_app_sessions', array(
				'session_app_id'	=> $this->app_id,
				'session_app_data'	=> json_encode( array( 'completed' => array(), 'working' => array(), 'more_info' => array() ) )
			) );
			return array( 'completed' => array(), 'working' => array(), 'more_info' => array() );
		}
	}
	
	/**
	 * [Legacy] Get Software. Automatically adjusts if legacy software which has since had its application key changed.
	 *
	 * @return	string
	 */
	public function get_sw()
	{
		switch( $this->_data['sw'] )
		{
			case 'board':
				return 'forums';
			break;
			
			case 'ccs':
				return 'cms';
			break;
			
			default:
				return $this->_data['sw'];
			break;
		}
	}
	
	/**
	 * [Legacy] Automatically fix any legacy application keys that have changed.
	 *
	 * @param	string	$value	App value
	 * @return	void
	 */
	public function set_sw( $value )
	{
		switch( $value )
		{
			case 'board':
				$this->_data['sw'] = 'forums';
			break;
			
			case 'ccs':
				$this->_data['sw'] = 'cms';
			break;
			
			default:
				$this->_data['sw'] = $value;
			break;
		}
	}
	
	/**
	 * @brief	Parent Store
	 */
	protected $parentStore = NULL;
	
	/**
	 * Get parent application
	 *
	 * @return	\IPS\convert\App
	 * @throws	\BadMethodCallException
	 */
	public function get__parent()
	{
		if ( \is_null( $this->parentStore ) )
		{
			if ( ! $this->parent )
			{
				throw new \BadMethodCallException;
			}
			
			try
			{
				$this->parentStore = static::constructFromData( \IPS\Db::i()->select( '*', 'convert_apps', array( "app_id=?", $this->parent ) )->first() );
			}
			catch( \UnderflowException $e )
			{
				throw new \BadMethodCallException;
			}
			catch( \OutOfRangeException $e )
			{
				throw new \BadMethodCallException;
			}
		}
		
		return $this->parentStore;
	}

	/**
	 * Return this application's children applications
	 *
	 * @return	array|\IPS\Patterns\ActiveRecordIterator
	 */
	public function children()
	{
		/* If we have a parent, we are already a child and will not have children */
		if( $this->parent )
		{
			return array();
		}

		return new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'convert_apps', array( 'parent=?', $this->app_id ) ), 'IPS\convert\App' );
	}
	
	/**
	 * Retrieves an Invision Community ID from a Foreign ID.
	 *
	 * @param	mixed			$foreign_id		The Foreign ID
	 * @param	string|array	$type			The type of item, or an array of types to check.
	 * @param	boolean			$parent			If set to TRUE, then retrieves from the parent application if one is available.
	 * @param	boolean			$mainTable		If set to TRUE, and the type is of either 'core_message_topics', 'core_message_posts', 'core_message_topic_user_map', 'forums_topics', or 'forums_posts', then the ID is retrieved from convert_link, rather than the other link tables.
	 *
	 * @return	integer	The Invision Community ID.
	 * @throws	\OutOfRangeException
	 */
	public function getLink( $foreign_id, $type, $parent=FALSE, $mainTable=FALSE )
	{
		if ( !\is_array( $type ) )
		{
			$type = array( $type );
		}
		
		$key = \count( $type ) > 1 ? md5( implode('', $type ) ) : $type[0];
		
		if ( isset( $this->linkCache[ $key ][ $foreign_id ] ) )
		{
			if( $this->linkCache[ $key ][ $foreign_id ] === FALSE )
			{
				throw new \OutOfRangeException( 'link_invalid' );
			}

			return $this->linkCache[ $key ][ $foreign_id ];
		}

		foreach ( $type as $t )
		{
			$table = $this->_getLinkTableName( $t, $mainTable );
		}

		try
		{
			$link = \IPS\Db::i()->select( 'ipb_id', $table, array( \IPS\Db::i()->in( 'type', $type ) . ' AND foreign_id=? AND app=?', (string) $foreign_id, ( $parent === TRUE and $this->parent ) ? $this->parent : $this->app_id ), 'link_id DESC' )->first();
			$this->linkCache[ $key ][ $foreign_id ] = $link;
			return $this->linkCache[ $key ][ $foreign_id ];
		}
		catch ( \UnderflowException $e )
		{
			/* If lookup failed, and we have a parent, try it anyway */
			try
			{
				$link = \IPS\Db::i()->select( 'ipb_id', $table, array( \IPS\Db::i()->in( 'type', $type ) . ' AND foreign_id=? AND app=?', (string) $foreign_id, $this->parent ), 'link_id DESC' )->first();
				$this->linkCache[ $key ][ $foreign_id ] = $link;
				return $this->linkCache[ $key ][ $foreign_id ];
			}
			catch( \UnderflowException $e ) {}
			
			/* Still here? Throw the exception */
			throw new \OutOfRangeException( 'link_invalid' );
		}
	}

	/**
	 * Get conversion ID of the master core conversion
	 *
	 * @return int
	 */
	public function getMasterConversionId(): int
	{
		try
		{
			return $this->_parent->app_id;
		}
		catch( \BadMethodCallException $e )
		{
			return $this->app_id;
		}
	}
	
	/**
	 * @brief	Sibling Link Cache
	 */
	protected $siblingLinkCache = array();
	
	/**
	 * Retrieves an Invision Community iD from a Foriegn ID in a Sibling Application
	 *
	 * @param	mixed		$foreign_id		The Foreign ID.
	 * @param	string		$type			The type of item.
	 * @param	string		$sibling		The sibling software library.
	 * @param	boolean		$mainTable		If set to TRUE, and the type is of either 'core_message_topics', 'core_message_posts', 'core_message_topic_user_map', 'forums_topics', or 'forums_posts', then the ID is retrieved from convert_link, rather than the other link tables.
	 *
	 * @return	inteer	The Invision Community ID.
	 * @throws	\OutOfRangeException
	 */
	public function getSiblingLink( $foreign_id, $type, $sibling, $mainTable=FALSE )
	{
		if ( isset( $this->siblingLinkCache[$type][$sibling][$foreign_id] ) )
		{
			return $this->siblingLinkCache[$type][$sibling][$foreign_id];
		}
		
		try
		{
			$sibling = static::constructFromData( \IPS\Db::i()->select( '*', 'convert_apps', array( "sw=? AND parent=?", $sibling, $this->parent ) )->first() );
		}
		catch( \UnderflowException $e )
		{
			throw new \OutOfRangeException( 'sibling_invalid' );
		}
		
		return $sibling->getLink( $foreign_id, $type, FALSE, $mainTable );
	}
	
	/**
	 * Saves a foreign ID to Invision Community ID reference to the convert_link tables.
	 *
	 * @param	integer		$ips_id			The Invision Community ID
	 * @param	mixed		$foreign_id		The Foreign ID
	 * @param	string		$type			The type of item
	 * @param	boolean		$duplicate		If TRUE, then this item is a duplicate and was merged into existing $ips_id
	 * @param	boolean		$mainTable		If TRUE, then link will be stored in the main convert_link table even if $type is 'core_message_topics', 'core_message_posts', 'core_message_topic_user_map', 'forums_topics', or 'forums_posts'
	 * @return	void
	 */
	public function addLink( $ips_id, $foreign_id, $type, $duplicate=FALSE, $mainTable=FALSE )
	{
		$table = 'convert_link';
		
		if ( \in_array( $type, [ 'core_message_topics', 'core_message_posts', 'core_message_topic_user_map', 'forums_topics', 'forums_posts', 'forums_topics_old', 'forums_posts_old' ] ) AND $mainTable === FALSE )
		{
			if ( \in_array( $type, array( 'core_message_topics', 'core_message_posts', 'core_message_topic_user_map' ) ) )
			{
				$table = 'convert_link_pms';
			}
			else
			{
				$tableType = str_replace( [ 'forums_', '_old' ], '', $type );
				$table = "convert_link_{$tableType}";
			}
		}
		
		\IPS\Db::i()->insert( $table, array(
			'ipb_id'		=> $ips_id,
			'foreign_id'	=> $foreign_id,
			'type'			=> $type,
			'duplicate'		=> ( $duplicate === TRUE ) ? 1 : 0,
			'app'			=> $this->app_id
		) );
		
		$this->linkCache[$type][$foreign_id] = $ips_id;
	}

	/**
	 * Pre-cache link data based on source data
	 *
	 * @param array $data
	 * @param array $map
	 */
	public function preCacheLinks( array $data, array $map )
	{
		$cachesToLookup = [];
		$tables = [];

		/* Check for data */
		if( !\count( $data ) )
		{
			return;
		}

		foreach( $map as $type => $columns )
		{
			$cachesToLookup[ $type ] = array();
			if ( \is_array( $columns ) )
			{
				foreach ( $columns as $column )
				{
					$cachesToLookup[ $type ] = \array_merge( $cachesToLookup[ $type ], \array_column( $data, $column ) );
				}
				$cachesToLookup[ $type ] = \array_map( 'strval', \array_unique( $cachesToLookup[ $type ] ) );
			}
			else
			{
				$cachesToLookup[ $type ] = \array_map( 'strval', \array_unique( \array_column( $data, $columns ) ) );
			}

			/* Check whether there are values we don't have cached */
			if( isset( $this->linkCache[ $type ] ) )
			{
				$cachesToLookup[ $type ] = \array_diff( $cachesToLookup[ $type ], \array_keys( $this->linkCache[ $type ] ) );
				if ( !\count( $cachesToLookup[ $type ] ) )
				{
					/* nope, so skip the query */
					continue;
				}
			}

			$tables[ $type ] =  $this->_getLinkTableName( $type );
		}

		foreach( $tables as $link => $table )
		{
			if( !isset( $this->linkCache[ $link ] ) )
			{
				$this->linkCache[ $link ] = array();
			}

			$where = [ '`type`=? AND ' . \IPS\Db::i()->in('foreign_id', $cachesToLookup[ $link ] ) . ' AND (app=? OR app=?)', $link, $this->parent, $this->app_id ];
			foreach( \IPS\Db::i()->select( 'ipb_id, foreign_id', $table, $where ) as $result )
			{
				$this->linkCache[ $link ][ (string) $result['foreign_id'] ] = $result['ipb_id'];
			}

			foreach( $cachesToLookup[ $link ] as $foreignId )
			{
				if( !isset( $this->linkCache[ $link ][ (string) $foreignId ] ) )
				{
					$this->linkCache[ $link ][ (string) $foreignId ] = FALSE;
				}
			}
		}
	}
	
	/**
	 * Checks to see if a link exists for an Invision Community ID
	 *
	 * @param	integer	$ips_id		The Invision Community ID.
	 * @param	string	$type		The type of item
	 * @param	bool	$mainTable	If TRUE, then link will be check against the main convert_link table even if $type is 'core_message_topics', 'core_message_posts', 'core_message_topic_user_map', 'forums_topics', or 'forums_posts'
	 * @return	void
	 * @throws	\OutOfRangeException
	 */
	public function checkLink( $ips_id, $type, $mainTable=FALSE )
	{
		$table = $this->_getLinkTableName( $type, $mainTable );
		
		try
		{
			$link = \IPS\Db::i()->select( '*', $table, array( "app=? AND ipb_id=? AND type=?", $this->app_id, $ips_id, $type ) )->first();
		}
		catch( \UnderflowException $e )
		{
			throw new \OutOfRangeException;
		}
	}
	
	/**
	 * Checks to see if this application also has a sibling of a specific type
	 *
	 * @param	string	$software	The application key to look for
	 * @return	void
	 * @throws	\OutOfRangeException
	 */
	public function checkForSibling( $software )
	{
		try
		{
			\IPS\Db::i()->select( '*', 'convert_apps', array( "sw=? AND parent=?", $software, $this->parent ) )->first();
		}
		catch( \UnderflowException $e )
		{
			throw new \OutOfRangeException;
		}
	}

	/**
	 * Delete a stored foreignId <-> IPS4 ID relation
	 *
	 * @param	mixed		$foreignId			Foreign data ID
	 * @param	string		$type				The type of item
	 * @return	void
	 */
	public function deleteLink( $foreignId, string $type )
	{
		$table = $this->_getLinkTableName( $type );

		\IPS\Db::i()->delete( $table, array( 'foreign_id=? AND type=? AND app=?', $foreignId, $type, $this->app_id ) );

		/* Remove this from the link cache */
		if( isset( $this->linkCache[ $type ][ $foreignId ] ) )
		{
			unset( $this->linkCache[ $type ][ $foreignId ] );
		}
	}
	
	/**
	 * @brief	Sibling Cache
	 */
	protected $siblingCache = array();
	
	/**
	 * Construct an \IPS\convert\App object for a sibling application.
	 *
	 * @param	string	$software	The application key to look for
	 * @return	\IPS\convert\App
	 * @throws	\OutOfRangeException
	 */
	public function getSibling( $software )
	{
		if ( !isset( $this->siblingCache[$software] ) )
		{
			try
			{
				$this->siblingCache[$software] = static::constructFromData( \IPS\Db::i()->select( '*', 'convert_apps', array( "sw=? AND parent=?", $software, $this->parent ) )->first() );
			}
			catch( \Exception $e )
			{
				throw new \OutOfRangeException;
			}
		}
		
		return $this->siblingCache[$software];
	}
	
	/**
	 * Fetch the source application class file.
	 *
	 * @param	bool	$construct	Construct the object
	 * @param	bool	$needDB		Establish a database connection ($construct must be TRUE)
	 * @return	\IPS\convert\Software|string
	 * @throws	\InvalidArgumentException
	 */
	public function getSource( $construct=TRUE, $needDB=TRUE )
	{
		/* Update software keys for communities converted to 3.x */
		switch( $this->_data['sw'] )
		{
			case 'board':
			case 'ccs':
				$this->sw = $this->sw;
				$this->save();
			break;
		}

		/* Change any app keys to newer versions */
		switch( $this->_data['app_key'] )
		{
			case 'photopost8':
			case 'photopost7':
				$this->app_key = 'photopost';
				$this->save();
			break;

			case 'bbpress23':
			case 'bbpress_standalone':
				$this->app_key = 'bbpress';
				$this->save();
			break;

			case 'mybb_legacy':
				$this->app_key = 'mybb';
				$this->save();
			break;

			case 'phpbb_legacy':
				$this->app_key = 'phpbb';
				$this->save();
			break;

			case 'smf_legacy':
				$this->app_key = 'smf';
				$this->save();
			break;

			case 'vbulletin_legacy':
			case 'vbulletin_legacy36':
			case 'vbulletin_subs':
			case 'vbblog':
			case 'vbdynamics':
				$this->app_key = 'vbulletin';
				$this->save();
			break;
			
			case 'vb5connect':
				$this->app_key = 'vbulletin5';
				$this->save();
			break;

			/* Make sure UBBThreads is now all lowercase */
			case 'UBBthreads':
				$this->app_key = 'ubbthreads';
				$this->save();
			break;
		}
		
		$classname = \IPS\convert\Software::software()[ $this->_data['sw'] ][ $this->_data['app_key'] ];

		if ( ! class_exists( $classname ) )
		{
			throw new \InvalidArgumentException( 'invalid_source' );
		}
		
		if ( $construct )
		{
			return new $classname( $this, $needDB );
		}
		else
		{
			return $classname;
		}
	}
	
	/**
	 * Log Something
	 *
	 * @param	string		$message	The message to log.
	 * @param	string		$method		The current conversion method (convert_posts, convert_topics, etc.)
	 * @param	integer		$severity	The severity level of the log. Default to LOG_NOTICE
	 * @param	int|NULL	$id			The item ID
	 * @return	void
	 * @throws \InvalidArgumentException
	 */
	public function log( $message, $method, $severity=1, $id=NULL )
	{
		if ( ! \in_array( $severity, array( static::LOG_NOTICE, static::LOG_WARNING, static::LOG_ERROR ) ) )
		{
			throw new \InvalidArgumentException( 'invalid_severity' );
		}
		
		\IPS\Db::i()->insert( 'convert_logs', array(
			'log_message'	=> $message,
			'log_app'		=> $this->app_id,
			'log_severity'	=> $severity,
			'log_method'	=> $method,
			'log_item_id'	=> $id,
			'log_time'		=> time()
		) );
	}
	
	/**
	 * Callback function to return all dependencies not yet converted.
	 *
	 * @param	string	$value	Value from depency array
	 * @return	boolean
	 */
	public function dependencies( $value )
	{
		if ( !\in_array( $value, $this->_session['completed'] ) )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Saves information about the current step to the session.
	 *
	 * @param	string	$method		The converison method
	 * @param	array	$values		Values from the form.
	 * @return	void
	 */
	public function saveMoreInfo( $method, $values=array() )
	{
		$sessionData = $this->_session;
		
		unset( $values['reconfigure'], $values['empty_local_data'] );
		
		$this->_session = array( 'working' => $sessionData['working'], 'completed' => $sessionData['completed'], 'more_info' => array_merge( $sessionData['more_info'], array( $method => $values ) ) );
	}
	
	/**
	 * Magic __isset() method
	 *
	 * @param	mixed $key key
	 * @return	bool
	 */
	public function __isset( $key )
	{
		if ( method_exists( $this, 'get_' . $key ) )
		{
			return TRUE;
		}
		
		if ( isset( $this->data[$key] ) )
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Set running flag
	 *
	 * @param	string		$method		Method name
	 * @param 	bool		$status		Flag status
	 * @return	void
	 */
	public function setRunningFlag( $method, $status )
	{
		$running = isset( $this->_session['running'] ) ? $this->_session['running'] : array();

		/* If setting running flag, set to a timestamp */
		if( $status === TRUE )
		{
			$status = time();
		}

		$running[ $method ] = $status;

		$this->_session = array_merge( $this->_session, array( 'running' => $running ) );
	}

	/**
	 * Get running flag
	 *
	 * @param	string		$method		Method name
	 * @return	bool|int				False or a timestamp
	 */
	public function getRunningFlag( $method )
	{
		if( isset( $this->_session['running'][ $method ] ) )
		{
			return $this->_session['running'][ $method ];
		}

		return FALSE;
	}

	/**
	 * Get table name for link caches
	 *
	 * @param	string		$type		Link type
	 * @param 	bool 		$mainTable	If set to TRUE, and the type is of either 'core_message_topics', 'core_message_posts', 'core_message_topic_user_map', 'forums_topics', or 'forums_posts', then the ID is retrieved from convert_link, rather than the other link tables.
	 * @return 	string					Table name
	 */
	protected function _getLinkTableName( string $type, bool $mainTable=FALSE ): string
	{
		$table = 'convert_link';

		if ( $mainTable === FALSE )
		{
			if ( \in_array( $type, array( 'core_message_topics', 'core_message_posts', 'core_message_topic_user_map', 'forums_topics', 'forums_posts', 'forums_topics_old', 'forums_posts_old' ) ) )
			{
				if ( \in_array( $type, array( 'core_message_topics', 'core_message_posts', 'core_message_topic_user_map' ) ) )
				{
					$table = 'convert_link_pms';
				}
				else
				{
					$tableType = str_replace(  [ 'forums_', '_old' ], '', $type );
					$table = "convert_link_{$tableType}";
				}
			}
		}

		return $table;
	}
}