<?php
/**
 * @brief		Active Record Pattern
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS\Patterns;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Active Record Pattern
 */
abstract class _ActiveRecord
{
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = '';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';

	/**
	 * @brief	[ActiveRecord] Database table
	 * @note	This MUST be over-ridden
	 */
	public static $databaseTable	= '';
		
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 * @note	If using this, declare a static $multitonMap = array(); in the child class to prevent duplicate loading queries
	 */
	protected static $databaseIdFields = array();
	
	/**
	 * @brief	Bitwise keys
	 */
	protected static $bitOptions = array();

	/**
	 * @brief	[ActiveRecord] Multiton Store
	 * @note	This needs to be declared in any child classes as well, only declaring here for editor code-complete/error-check functionality
	 */
	protected static $multitons	= array();

	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array();

	/**
	 * @brief	[ActiveRecord] Attempt to load from cache
	 * @note	If this is set to TRUE you should define a getStore() method to return the objects from cache
	 */
	protected static $loadFromCache = FALSE;
		
	/**
	 * @brief	[ActiveRecord] Database Connection
	 * @return	\IPS\Db
	 */
	public static function db()
	{
		return \IPS\Db::i();
	}
	
	/**
	 * Load Record
	 *
	 * @see		\IPS\Db::build
	 * @param	int|string	$id					ID
	 * @param	string		$idField			The database column that the $id parameter pertains to (NULL will use static::$databaseColumnId)
	 * @param	mixed		$extraWhereClause	Additional where clause(s) (see \IPS\Db::build for details) - if used will cause multiton store to be skipped and a query always ran
	 * @return	static
	 * @throws	\InvalidArgumentException
	 * @throws	\OutOfRangeException
	 */
	public static function load( $id, $idField=NULL, $extraWhereClause=NULL )
	{
		/* If we didn't specify an ID field, assume the default */
		if( $idField === NULL )
		{
			$idField = static::$databasePrefix . static::$databaseColumnId;
		}
		
		/* If we did, check it's valid */
		elseif( !\in_array( $idField, static::$databaseIdFields ) )
		{
			throw new \InvalidArgumentException;
		}

		/* Some classes can load directly from a cache, so check that first */
		if( static::$loadFromCache !== FALSE AND $idField === static::$databasePrefix . static::$databaseColumnId AND $extraWhereClause === NULL )
		{
			$cachedObjects = static::getStore();

			if ( isset( $cachedObjects[ $id ] ) )
			{
				return static::constructFromData( $cachedObjects[ $id ] );
			}
			else
			{
				throw new \OutOfRangeException;
			}
		}
				
		/* Does that exist in the multiton store? */
		if ( !$extraWhereClause )
		{
			if( $idField === static::$databasePrefix . static::$databaseColumnId )
			{
				if ( !empty( static::$multitons[ $id ] ) )
				{
					return static::$multitons[ $id ];
				}
			}
			elseif ( isset( static::$multitonMap ) and isset( static::$multitonMap[ $idField ][ $id ] ) )
			{
				return static::$multitons[ static::$multitonMap[ $idField ][ $id ] ];
			}
		}
		
		/* Load it */
		try
		{
			$row = static::constructLoadQuery( $id, $idField, $extraWhereClause )->first();
		}
		catch ( \UnderflowException $e )
		{
			throw new \OutOfRangeException;
		}
		
		/* If it doesn't exist in the multiton store, set it */
		if( !isset( static::$multitons[ $row[ static::$databasePrefix . static::$databaseColumnId ] ] ) )
		{
			static::$multitons[ $row[ static::$databasePrefix . static::$databaseColumnId ] ] = static::constructFromData( $row );
		}
		if ( isset( static::$multitonMap ) )
		{
			foreach ( static::$databaseIdFields as $field )
			{
				if ( $row[ $field ] )
				{
					static::$multitonMap[ $field ][ $row[ $field ] ] = $row[ static::$databasePrefix . static::$databaseColumnId ];
				}
			}
		}
		
		/* And return it */
		return static::$multitons[ $row[ static::$databasePrefix . static::$databaseColumnId ] ];
	}

	/**
	 * Load record based on a URL
	 *
	 * @param	\IPS\Http\Url	$url	URL to load from
	 * @return	static
	 * @throws	\InvalidArgumentException
	 * @throws	\OutOfRangeException
	 */
	public static function loadFromUrl( \IPS\Http\Url $url )
	{		
		if ( isset( $url->queryString['id'] ) )
		{
			return static::load( $url->queryString['id'] );
		}
		if ( isset( $url->hiddenQueryString['id'] ) )
		{
			return static::load( $url->hiddenQueryString['id'] );
		}
		
		throw new \InvalidArgumentException;
	}

	/**
	 * Construct Load Query
	 *
	 * @param	int|string	$id					ID
	 * @param	string		$idField			The database column that the $id parameter pertains to
	 * @param	mixed		$extraWhereClause	Additional where clause(s)
	 * @return	\IPS\Db\Select
	 */
	protected static function constructLoadQuery( $id, $idField, $extraWhereClause )
	{
		$where = array( array( '`' . $idField . '`=?', $id ) );
		if( $extraWhereClause !== NULL )
		{
			if ( !\is_array( $extraWhereClause ) or !\is_array( $extraWhereClause[0] ) )
			{
				$extraWhereClause = array( $extraWhereClause );
			}
			$where = array_merge( $where, $extraWhereClause );
		}
		
		return static::db()->select( '*', static::$databaseTable, $where );
	}
			
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		/* Does that exist in the multiton store? */
		$obj = NULL;
		if ( isset( static::$databaseColumnId ) )
		{
			$idField = static::$databasePrefix . static::$databaseColumnId;
			$id = $data[ $idField ];
			
			if( isset( static::$multitons[ $id ] ) )
			{
				if ( !$updateMultitonStoreIfExists )
				{
					return static::$multitons[ $id ];
				}
				$obj = static::$multitons[ $id ];
			}
		}
		
		/* Initiate an object */
		if ( !$obj )
		{
			$classname = \get_called_class();
			$obj = new $classname;
			$obj->_new  = FALSE;
			$obj->_data = array();
		}
		
		/* Import data */
		$databasePrefixLength = \strlen( static::$databasePrefix );
		foreach ( $data as $k => $v )
		{
			if( static::$databasePrefix AND mb_strpos( $k, static::$databasePrefix ) === 0 )
			{
				$k = \substr( $k, $databasePrefixLength );
			}

			$obj->_data[ $k ] = $v;
		}
		
		$obj->changed = array();
		
		/* Init */
		if ( method_exists( $obj, 'init' ) )
		{
			$obj->init();
		}
		
		/* If it doesn't exist in the multiton store, set it */
		if( isset( static::$databaseColumnId ) and !isset( static::$multitons[ $id ] ) )
		{
			static::$multitons[ $id ] = $obj;
		}
				
		/* Return */
		return $obj;
	}
	
	/**
	 * Get which IDs are already loaded
	 *
	 * @return	array
	 */
	public static function multitonIds()
	{
		if ( \is_array( static::$multitons ) )
		{
			return array_keys( static::$multitons );
		}
		return array();
	}
	
	/**
	 * @brief	Data Store
	 */
	protected $_data = array();
	
	/**
	 * @brief	Is new record?
	 */
	protected $_new = TRUE;
		
	/**
	 * @brief	Changed Columns
	 */
	public $changed = array();
	
	/**
	 * Constructor - Create a blank object with default values
	 *
	 * @return	void
	 */
	public function __construct()
	{						
		$this->setDefaultValues();
	}
	
	/**
	 * Set Default Values (overriding $defaultValues)
	 *
	 * @return	void
	 */
	protected function setDefaultValues()
	{
		
	} 
		
	/**
	 * Get value from data store
	 *
	 * @param	mixed	$key	Key
	 * @return	mixed	Value from the datastore
	 */
	public function __get( $key )
	{
		if( method_exists( $this, 'get_'.$key ) )
		{
			$method = 'get_' . $key;
			return $this->$method();
		}
		elseif( isset( $this->_data[ $key ] ) or isset( static::$bitOptions[ $key ] ) )
		{
			if ( isset( static::$bitOptions[ $key ] ) )
			{
				if ( !isset( $this->_data[ $key ] ) or !( $this->_data[ $key ] instanceof Bitwise ) )
				{
					$values = array();
					foreach ( static::$bitOptions[ $key ] as $k => $map )
					{
						$values[ $k ] = isset( $this->_data[ $k ] ) ? $this->_data[ $k ] : 0;
					}
					$this->_data[ $key ] = new Bitwise( $values, static::$bitOptions[ $key ], method_exists( $this, "setBitwise_{$key}" ) ? array( $this, "setBitwise_{$key}" ) : NULL );
				}
			}
			return $this->_data[ $key ];
		}
				
		return NULL;
	}
	
	/**
	 * Set value in data store
	 *
	 * @see		\IPS\Patterns\ActiveRecord::save
	 * @param	mixed	$key	Key
	 * @param	mixed	$value	Value
	 * @return	void
	 */
	public function __set( $key, $value )
	{
		if( method_exists( $this, 'set_'.$key ) )
		{
			$oldValues = $this->_data;
			
			$method = 'set_' . $key;
			$this->$method( $value );
						
			foreach( $this->_data as $k => $v )
			{				
				if( !array_key_exists( $k, $oldValues ) or ( $v instanceof \IPS\Patterns\Bitwise and !( $oldValues[ $k ] instanceof \IPS\Patterns\Bitwise ) ) or $oldValues[ $k ] !== $v )
				{
					$this->changed[ $k ]	= $v;
				}
			}
			
			unset( $oldValues );
		}
		else
		{
			if ( !array_key_exists( $key, $this->_data ) or $this->_data[ $key ] !== $value )
			{
				$this->changed[ $key ] = $value;
			}
			
			$this->_data[ $key ] = $value;
		}
	}
	
	/**
	 * Is value in data store?
	 *
	 * @param	mixed	$key	Key
	 * @return	bool
	 */
	public function __isset( $key )
	{
		if ( method_exists( $this, 'get_' . $key ) )
		{
			$method = 'get_' . $key;
			return $this->$method() !== NULL;
		}
		
		if ( isset( $this->_data[$key] ) )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * @brief	By default cloning will create a new ActiveRecord record, but if you truly want an object copy you can set this to TRUE first and a direct copy will be returned
	 */
	public $skipCloneDuplication	= FALSE;

	/**
	 * [ActiveRecord] Duplicate
	 *
	 * @return	void
	 */
	public function __clone()
	{
		if( $this->skipCloneDuplication === TRUE )
		{
			return;
		}

		$primaryKey = static::$databaseColumnId;
		$this->$primaryKey = NULL;
		
		$this->_new = TRUE;
		$this->save();
	}
	
	/**
	 * Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		if ( $this->_new )
		{
			$data = $this->_data;
		}
		else
		{
			$data = $this->changed;
		}

		foreach ( array_keys( static::$bitOptions ) as $k )
		{			
			if ( $this->$k instanceof Bitwise )
			{
				foreach( $this->$k->values as $field => $value )
				{ 
					if ( isset( $data[ $field ] ) or $this->$k->originalValues[ $field ] != \intval( $value ) )
					{
						$data[ $field ] = \intval( $value );
					}
				}
			}
		}

		if ( $this->_new )
		{
			$insert = array();
			if( static::$databasePrefix === NULL )
			{
				$insert = $data;
			}
			else
			{
				$insert = array();
				foreach ( $data as $k => $v )
				{
					$insert[ static::$databasePrefix . $k ] = $v;
				}
			}
			
			$insertId = static::db()->insert( static::$databaseTable, $insert );
			
			$primaryKey = static::$databaseColumnId;
			if ( $this->$primaryKey === NULL and $insertId )
			{
				$this->$primaryKey = $insertId;
			}
			
			$this->_new = FALSE;

			/* Reset our log of what's changed */
			$this->changed = array();

			static::$multitons[ $this->$primaryKey ] = $this;
		}
		elseif( !empty( $data ) )
		{
			/* Set the column names with a prefix */
			if( static::$databasePrefix === NULL )
			{
				$update = $data;
			}
			else
			{
				$update = array();

				foreach ( $data as $k => $v )
				{
					$update[ static::$databasePrefix . $k ] = $v;
				}
			}
						
			/* Save */
			static::db()->update( static::$databaseTable, $update, $this->_whereClauseForSave() );
			
			/* Reset our log of what's changed */
			$this->changed = array();
		}

		$this->clearCaches();
	}
	
	/**
	 * Get the WHERE clause for save()
	 *
	 * @return	void
	 */
	protected function _whereClauseForSave()
	{
		$idColumn = static::$databaseColumnId;
		return array( static::$databasePrefix . $idColumn . '=?', $this->$idColumn );
	}
	
	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		$idColumn = static::$databaseColumnId;
		static::db()->delete( static::$databaseTable, array( static::$databasePrefix . $idColumn . '=?', $this->$idColumn ) );

		$this->clearCaches( TRUE );
	}

	/**
	 * @brief	Cache followers count query to prevent running it multiple times
	 */
	protected static $followerCountCache = array();
	
	/**
	 * Get follow data
	 *
	 * @param	string					$area			Area
	 * @param	int|array				$id				ID or array of IDs
	 * @param	int						$privacy		static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS
	 * @param	array					$frequencyTypes	array( 'none', 'immediate', 'daily', 'weekly' )
	 * @param	\IPS\DateTime|int|NULL	$date			Only users who started following before this date will be returned. NULL for no restriction
	 * @param	int|array				$limit			LIMIT clause
	 * @param	string					$order			Column to order by
	 * @param	bool					$countOnly		Return only the count
	 * @return	\Iterator|int
	 * @throws	\BadMethodCallException
	 */
	protected static function _followers( $area, $id, $privacy, $frequencyTypes, $date=NULL, $limit=NULL, $order=NULL, $countOnly=FALSE )
	{
		/* Normalize the input */
		sort( $frequencyTypes );

		/* Can we use the cache table? */
		$canCache = FALSE;
		$cached = array();
		if ( \count( $frequencyTypes ) == 4 and $countOnly and ( $privacy == static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS ) )
		{
			$canCache = TRUE;

			if ( \is_array( $id ) )
			{
				foreach( \IPS\Db::i()->select( 'id, count', 'core_follow_count_cache', array( 'class=? and ' . \IPS\Db::i()->in( 'id', $id ), 'IPS\\' . static::$application . '\\' . ucfirst( $area ) ) ) as $row )
				{
					$cached[ $row['id'] ] = array( 'count' => $row['count'], 'follow_rel_id' => $row['id'] );
				}

				/* Got everything? */
				if ( \count( $id ) == \count( $cached ) )
				{
					$obj = new \ArrayObject( $cached );
					return $obj->getIterator();
				}
			}
			else
			{
				$_key = md5( static::$application . $area . $id );

				if( isset( static::$followerCountCache[ $_key ] ) )
				{
					return static::$followerCountCache[ $_key ];
				}

				try
				{
					static::$followerCountCache[ $_key ] = (int) \IPS\Db::i()->select( 'count', 'core_follow_count_cache', array('class=? and id=?', 'IPS\\' . static::$application . '\\' . ucfirst( $area ), $id ) )->first();

					return static::$followerCountCache[ $_key ];
				}
				catch ( \UnderflowException $e )
				{
				}
			}
		}

		/* We need to use a group by if $id is an array, but otherwise not */
		$groupBy = NULL;

		/* Initial where clause */
		if( \is_array( $id ) )
		{
			$where[]	= array( 'follow_app=? AND follow_area=? AND follow_rel_id IN(' . implode( ',', $id ) . ')', static::$application, $area );
			$groupBy	= 'follow_rel_id';
		}
		else
		{
			$where[] = array( 'follow_app=? AND follow_area=? AND follow_rel_id=?', static::$application, $area, $id );
		}
	
		/* Public / Anonymous */
		if ( !( $privacy & static::FOLLOW_PUBLIC ) )
		{
			$where[] = array( 'follow_is_anon=1' );
		}
		elseif ( !( $privacy & static::FOLLOW_ANONYMOUS ) )
		{
			$where[] = array( 'follow_is_anon=0' );
		}
	
		/* Specific type */
		if ( \count( array_diff( array( 'immediate', 'daily', 'weekly', 'none' ), $frequencyTypes ) ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'follow_notify_freq', $frequencyTypes ) );
		}
		
		/* Since */
		if( $date !== NULL )
		{
			$where[] = array( 'follow_added<?', ( $date instanceof \IPS\DateTime ) ? $date->getTimestamp() : \intval( $date ) );
		}

		/* We don't need order or limit if we're doing a count only, which makes the query more efficient */
		if( $countOnly === TRUE )
		{
			$limit = NULL;
			$order = NULL;
		}

		/* Cache the results as this may be called multiple times in one page load */
		static $cache	= array();
		$_hash			= md5( json_encode( \func_get_args() ) );

		if( isset( $cache[ $_hash ] ) )
		{
			return $cache[ $_hash ];
		}

		/* Get */
		if ( $order === 'name' )
		{
			$cache[ $_hash ]	= \IPS\Db::i()->select( 'core_follow.*, core_members.name', 'core_follow', $where, 'name ASC', $limit )->join( 'core_members', array( 'core_members.member_id=core_follow.follow_member_id' ) );
		}
		else
		{
			$cache[ $_hash ]	= \IPS\Db::i()->select( $countOnly ? ( \is_array( $id ) ? 'COUNT(*) as count, follow_rel_id' : 'COUNT(*)' ) : 'core_follow.*', 'core_follow', $where, $order, $limit, $groupBy );
		}

		/* If we only want the count, fetch it and store it now */
		if( $countOnly )
		{
			if( \is_array( $id ) )
			{
				$args = \func_get_args();

				foreach( $cache[ $_hash ] as $result )
				{
					$args[1] = $result['follow_rel_id'];
					$cache[ md5( json_encode( $args ) ) ] = $result;

					if ( $canCache and ! isset( $cached[ $result['follow_rel_id'] ] ) )
					{
						\IPS\Db::i()->replace( 'core_follow_count_cache', array(
							'id'	 => $result['follow_rel_id'],
							'class'  => 'IPS\\' . static::$application . '\\' . ucfirst( $area ),
							'count'  => $result['count'],
							'added'  => time()
						) );
					}

					$cached[ $result['follow_rel_id'] ] = $result;
				}

				/* And then any that do not exist were not found in the query, so they're 0 */
				foreach( $id as $_id )
				{
					$args[1] = $_id;
					$cache[ md5( json_encode( $args ) ) ] = array( 'follow_rel_id' => $id, 'count' => 0 );

					if ( $canCache and ! isset( $cached[ $_id ] ) )
					{
						\IPS\Db::i()->replace( 'core_follow_count_cache', array(
							'id'	 => $_id,
							'class'  => 'IPS\\' . static::$application . '\\' . ucfirst( $area ),
							'count'  => 0,
							'added'  => time()
						) );
					}
				}
			}
			else
			{
				$cache[ $_hash ] = $cache[ $_hash ]->first();

				if ( $canCache )
				{
					\IPS\Db::i()->replace( 'core_follow_count_cache', array(
						'id'	 => $id,
						'class'  => 'IPS\\' . static::$application . '\\' . ucfirst( $area ),
						'count'  => $cache[ $_hash ],
						'added'  => time()
					) );
				}
			}
		}

		if ( isset( $cached ) AND \is_array( $id ) )
		{
			$obj = new \ArrayObject( $cached );
			return $obj->getIterator();
		}

		return $cache[ $_hash ];
	}

	/**
	 * Get follower count
	 *
	 * @param	string					$area			Area
	 * @param	int|array				$id				ID or array of IDs
	 * @param	int						$privacy		static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS
	 * @param	array					$frequencyTypes	array( 'immediate', 'daily', 'weekly' )
	 * @param	\IPS\DateTime|int|NULL	$date			Only users who started following before this date will be returned. NULL for no restriction
	 * @return	int
	 * @throws	|\BadMethodCallException
	 */
	protected static function _followersCount( $area, $id, $privacy=3, $frequencyTypes=array( 'immediate', 'daily', 'weekly', 'none' ), $date=NULL )
	{
		return static::_followers( $area, $id, $privacy, $frequencyTypes, $date, NULL, NULL, TRUE );
	}
	
	/**
	 * Cover Photo
	 *
	 * @return	\IPS\Helpers\CoverPhoto
	 */
	public function coverPhoto()
	{
        $photo = new \IPS\Helpers\CoverPhoto;
        if( $file = $this->coverPhotoFile() )
        {
            $photoOffset = static::$databaseColumnMap[ 'cover_photo_offset' ];
            $photo->file = $file;
            $photo->offset = $this->$photoOffset;
        }
        $photo->editable = $this->canEdit();
        $photo->object = $this;
        return $photo;
	}

    /**
     * Returns the CoverPhoto File Instance or NULL if there's none
     *
     * @return null|\IPS\File
     */
    public function coverPhotoFile() : ?\IPS\File
    {
        $photoCol = static::$databaseColumnMap[ 'cover_photo' ];

        if ( isset( static::$databaseColumnMap['cover_photo'] ) and $this->$photoCol )
        {
            return \IPS\File::get( static::$coverPhotoStorageExtension, $this->$photoCol );
        }
        return NULL;
    }
	
	/**
	 * Produce a random hex color for a background
	 *
	 * @return string
	 */
	public function coverPhotoBackgroundColor()
	{
		return '#' . dechex( mt_rand( 0x000000, 0xFFFFFF ) );
	}

	/**
	 * Return cover photo background color based on a string
	 *
	 * @param	string	$string	Some string to base background color on
	 * @return	string
	 */
	protected function staticCoverPhotoBackgroundColor( $string )
	{
		$integer	= 0;

		for( $i=0, $j=\strlen($string); $i<$j; $i++ )
		{
			$integer = \ord( \substr( $string, $i, 1 ) ) + ( ( $integer << 5 ) - $integer );
			$integer = $integer & $integer;
		}

		return "hsl(" . ( $integer % 360 ) . ", 100%, 80% )";
	}

	/**
	 * Clear any defined caches
	 *
	 * @param	bool	$removeMultiton		Should the multiton record also be removed?
	 * @return void
	 */
	public function clearCaches( $removeMultiton=FALSE )
	{
		if( \count( $this->caches ) )
		{
			foreach( $this->caches as $cacheKey )
			{
				unset( \IPS\Data\Store::i()->$cacheKey );
			}
		}

		if( $removeMultiton === TRUE )
		{
			$idColumn = static::$databaseColumnId;

			if ( isset( static::$multitons[ $this->$idColumn ] ) )
			{
				unset( static::$multitons[ $this->$idColumn ] );

				if ( isset( static::$multitonMap ) )
				{
					foreach ( static::$databaseIdFields as $field )
					{
						if( isset( static::$multitonMap[ $field ] ) )
						{
							foreach( static::$multitonMap[ $field ] as $otherId => $mappedId )
							{
								if( $mappedId == $this->$idColumn )
								{
									unset( static::$multitonMap[ $field ][ $otherId ] );
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Attempt to load cached data
	 *
	 * @note	This should be overridden in your class if you define $cacheToLoadFrom
	 * @return	mixed
	 */
	public static function getStore()
	{
		return iterator_to_array( \IPS\Db::i()->select( '*', static::$databaseTable, NULL, static::$databasePrefix . static::$databaseColumnId )->setKeyField( static::$databasePrefix . static::$databaseColumnId ) );
	}
}