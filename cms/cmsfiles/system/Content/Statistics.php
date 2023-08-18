<?php
/**
 * @brief		Statistics Trait
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 February 2020
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */

use http\Exception\BadMethodCallException;

if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Statistics Trait
 */
trait Statistics
{
	/**
	 * Most downloaded attachments
	 *
	 * @param int $count The number of results to return
	 * @return    array
	 * @throw BadMethodCallException
	 */
	public function topAttachments( $count = 5 ): array
	{
		$attachments = $this->_getAllAttachments( array(), $count, 'attach_hits DESC' );

		$attachments = \array_slice( $attachments, 0, $count );

		return $attachments;
	}

	/**
	 * Get all image attachments
	 *
	 * @param int $count The number of results to return
	 * @return    array
	 * @throw BadMethodCallException
	 */
	public function imageAttachments( $count = 10 ): array
	{
		$attachments = $this->_getAllAttachments( array( 'attach_is_image=1' ), $count, 'attach_date DESC' );
		$attachments = \array_slice( $attachments, 0, $count );

		return $attachments;
	}

	/**
	 * Members with most posts
	 *
	 * @param int $count The number of results to return
	 * @return    array
	 * @throws \Exception
	 * @throw BadMethodCallException
	 */
	public function topPosters( $count = 10 ): array
	{
		$commentClass = static::$commentClass;

		if ( !isset( $commentClass::$databaseColumnMap['author'] ) )
		{
			throw new \BadMethodCallException();
		}

		$authorColumn = $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['author'];
		$cacheKey = 'topPosters_' . $count;

		try
		{
			$members = $this->_getCached( $cacheKey );
		}
		catch( \OutOfRangeException $e )
		{
			$where = $this->_getVisibleWhere();
			$where[] = [ $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['author'] . '!=?', 0];

			$members = iterator_to_array( \IPS\Db::i()->select( "count(*) as sum, {$authorColumn}", $commentClass::$databaseTable, $where, 'sum DESC', array( 0, $count ), array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['author'] ) ) );

			$this->_storeCached( $cacheKey, $members );
		}

		$contributors = array();
		$counts = array();
		foreach ( $members as $member )
		{
			$contributors[] = $member[$authorColumn];
			$counts[ $member[$authorColumn] ] = $member['sum'];
		}

		if ( empty( $contributors ) )
		{
			return array();
		}

		$return = array();
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members', array( \IPS\Db::i()->in( 'member_id', $contributors ) ) ), 'IPS\Member' ) as $member )
		{
			$return[] = array( 'member' => $member, 'count' => $counts[ $member->member_id ] );
		}

		usort($return, function ( $member, $member2 )
		{
			return $member2['count'] <=> $member['count'];
		});

		return $return;
	}
	
	/**
	 * Most Recent Participans
	 *
	 * @param	string|NULL		$name	If we are looking for a specific user.
	 * @param	int				$limit	The amount of results to return.
	 * @return	\IPS\Patterns\ActiveRecordIterator
	 */
	public function mostRecent( ?string $name = NULL, int $limit = 10 )
	{
		$commentClass = static::$commentClass;
		
		if ( !isset( $commentClass::$databaseColumnMap['author'] ) )
		{
			throw new \BadMethodCallException;
		}
		
		if ( $name )
		{
			$cacheKey = 'mostRecent_' . $limit . '_' . $name;
		}
		else
		{
			$cacheKey = 'mostRecent_' . $limit;
		}
		
		try
		{
			$members = $this->_getCached( $cacheKey );
		}
		catch( \OutOfRangeException $e )
		{
			/* Get the ten most recent posters in this content that match input. */
			$where = $this->_getVisibleWhere();
			if ( $name )
			{
				$subQuery = \IPS\Db::i()->select( 'core_members.member_id', 'core_members', \IPS\Db::i()->like( 'core_members.name', $name ) );
				$where[] = [ $commentClass::$databaseTable . '.' . $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['author'] . ' IN(?)', $subQuery ];
			}

			$members = iterator_to_array( \IPS\Db::i()->select(
				$commentClass::$databasePrefix . $commentClass::$databaseColumnMap['author'],
				$commentClass::$databaseTable,
				$where,
				NULL,
			$limit ) );

			$this->_storeCached( $cacheKey, $members );
		}
		
		return new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members', array( \IPS\Db::i()->in( 'member_id', $members ) ) ), 'IPS\Member' );
	}

	/**
	 * Members with most posts
	 *
	 * @param int $count The number of results to return, max 100
	 * @return    array
	 * @throw BadMethodCallException
	 * @throws \Exception
	 */
	public function topReactedPosts( $count = 5 ): array
	{
		$commentClass = static::$commentClass;
		$commentIdField = $commentClass::$databasePrefix . $commentClass::$databaseColumnId;
		$idField = static::$databaseColumnId;

		if ( !\IPS\IPS::classUsesTrait( $commentClass, 'IPS\Content\Reactable' ) )
		{
			throw new \BadMethodCallException();
		}
		
		if ( $count > 100 )
		{
			throw new \BadMethodCallException();
		}

		$cacheKey = 'topReactedPosts_' . $count;

		try
		{
			$posts = $this->_getCached( $cacheKey );
		}
		catch( \OutOfRangeException $e )
		{
			$where = array( 'app=? and type=? and item_id=?', $commentClass::$application, $commentClass::reactionType(), $this->$idField );
			$posts = \IPS\Db::i()->select( "count(*) as sum, type_id", 'core_reputation_index', $where, NULL, NULL, array( 'type_id' ) );
			
			$posts = iterator_to_array( $posts );
			
			usort( $posts, function( $item1, $item2 )
			{
				return $item2['sum'] <=> $item1['sum'];
			} );
			
			/* Just store the top 100 posts, they are already sorted by highest to lowest */
			$posts = \array_slice( $posts, 0, 100 );
			$this->_storeCached( $cacheKey, $posts );
		}

		$postIds = array();
		$counts = array();
		foreach ( $posts as $post )
		{
			$postIds[] = $post['type_id'];
			$counts[$post['type_id']] = $post['sum'];
		}

		if ( empty( $postIds ) )
		{
			return array();
		}

		$return = array();
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', $commentClass::$databaseTable, array( \IPS\Db::i()->in( $commentIdField, $postIds ) ), "FIND_IN_SET( {$commentIdField}, '" . implode( ",", $postIds ) . "' )", array( 0, $count ) ), $commentClass ) as $comment )
		{
			if ( $comment->canView() and $comment->mapped('item') == $this->$idField and isset( $counts[ $comment->$commentIdField ] ) )
			{
				$return[] = array( 'comment' => $comment, 'count' => $counts[ $comment->$commentIdField ] );
			}
		}

		return $return;
	}

	/**
	 * Fetch the top 10 popular days for posts
	 *
	 * @param int $count	Number of days to return
	 * @return array
	 * @throws \Exception
	 */
	public function popularDays( $count=10 )
	{
		$return = array();
		$commentClass = static::$commentClass;
		$commentIdField = $commentClass::$databasePrefix . $commentClass::$databaseColumnId;
		$rows = array();
		$commentIds = array();

		$cacheKey = 'popularDays_' . $count;

		try
		{
			$posts = $this->_getCached( $cacheKey );
		}
		catch( \OutOfRangeException $e )
		{
			$dateColumn = $commentClass::$databasePrefix . ( isset( $commentClass::$databaseColumnMap['updated'] ) ? $commentClass::$databaseColumnMap['updated'] : $commentClass::$databaseColumnMap['date'] );
			$where = $this->_getVisibleWhere();

			$posts = iterator_to_array( \IPS\Db::i()->select( "COUNT(*) AS count, MIN({$commentIdField}) as commentId, (DATE_FORMAT( FROM_UNIXTIME( IFNULL( {$dateColumn}, 0 ) ), '%Y-%c-%e' ) ) as time", $commentClass::$databaseTable, $where, 'count desc', array( 0, $count ), array( 'time' ) ) );

			$this->_storeCached( $cacheKey, $posts );
		}

		foreach ( $posts  as $row )
		{
			if ( ! \in_array( $row['time'], $rows ) )
			{
				$rows[ $row['time'] ] = 0;
			}

			$rows[ $row['time'] ] += $row['count'];
			$commentIds[ $row['time'] ] = $row['commentId'];
		}

		foreach ( $rows as $time => $val )
		{
			$datetime = new \IPS\DateTime;
			$datetime->setTime( 12, 0, 0 );
			$exploded = explode( '-', $time );
			$datetime->setDate( $exploded[0], $exploded[1], $exploded[2] );

			$return[ $time ] = array( 'date' => $datetime, 'count' => $val, 'commentId' => $commentIds[ $time ] );
		}
		
		return $return;
	}

	/**
	 * Clear any cached stats
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function clearCachedStatistics()
	{
		$idField = static::$databaseColumnId;
		\IPS\Db::i()->delete( 'core_item_statistics_cache', array( 'cache_class=? and cache_item_id=?', \get_class( $this ), $this->$idField ) );
	}

	/**
	 * @brief	Loaded Extensions
	 */
	protected static $loadedExtensions = array();

	/**
	 * Get all attachments for this item
	 *
	 * @param	array		$extraWhere	Additional where clause
	 * @param	int			$limit		Number to return/limit to
	 * @param	string|NULL	$orderBy	Order by clause (optional)
	 * @return  array
	 * @throws \Exception
	 */
	protected function _getAllAttachments( $extraWhere=NULL, $limit=10, $orderBy=NULL )
	{
		$idField = static::$databaseColumnId;
		$cacheKey = 'allAttachments' . md5( json_encode( $extraWhere ) . $limit . (string) $orderBy );
		$return = array();

		try
		{
			$return = $this->_getCached( $cacheKey );
		}
		catch( \OutOfRangeException $e )
		{
			$where = array( array( 'location_key=? and id1=? and id2 IN(?)', static::$application . '_' . mb_ucfirst( static::$module ), $this->$idField, $this->_subQueryVisibleComments( $this->$idField ) ) );

			if ( $extraWhere !== NULL )
			{
				array_push( $where, $extraWhere );
			}

			/* Get attachments from all comments */
			$return = iterator_to_array( \IPS\Db::i()->select( '*', 'core_attachments_map', $where, $orderBy, $limit )->join( 'core_attachments', array( 'attach_id=attachment_id' ) ) );

			/* Get link to comments */
			foreach( $return as $k => $map )
			{
				/* Get the attachment extension if we don't already have it */
				if ( !isset( static::$loadedExtensions[ $map['location_key'] ] ) )
				{
					$exploded = explode( '_', $map['location_key'] );
					try
					{
						$extensions = \IPS\Application::load( $exploded[0] )->extensions( 'core', 'EditorLocations' );
						if ( isset( $extensions[ $exploded[1] ] ) )
						{
							static::$loadedExtensions[ $map['location_key'] ] = $extensions[ $exploded[1] ];
						}
					}
					catch ( \OutOfRangeException $e ) { }
					catch ( \UnexpectedValueException $e ) { }
				}
				
				if ( isset( static::$loadedExtensions[ $map['location_key'] ] ) )
				{
					try
					{
						$url = static::$loadedExtensions[ $map['location_key'] ]->attachmentLookup( $map['id1'], $map['id2'], $map['id3'] );

						$return[ $k ]['commentUrl'] = (string) $url->url();
					}
					catch ( \LogicException $e ) { }
					catch ( \BadMethodCallException $e ){ }
				}
			}

			$this->_storeCached( $cacheKey, $return );
		}

		return $return;
	}

	/**
	 * Return a sub query to fetch only visible posts
	 *
	 * @param	int		$id		Content item ID
	 * @return \IPS\Db\Select
	 */
	protected function _subQueryVisibleComments( $id )
	{
		$commentClass = static::$commentClass;
		return \IPS\Db::i()->select( $commentClass::$databasePrefix . $commentClass::$databaseColumnId, $commentClass::$databaseTable, array_merge( $this->_getVisibleWhere(), array( array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=' . $id ) ) ) );
	}

	/**
	 * Return the where query to ensure we only select visible comments
	 *
	 * @return array
	 */
	protected function _getVisibleWhere()
	{
		$commentClass = static::$commentClass;
		$idField = static::$databaseColumnId;
		$commentItemField = $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'];

		$where = array();
		if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
		{
			$approvedColumn = $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'];
			$where[] = array( "{$approvedColumn} = 1" );
		}
		if ( isset( $commentClass::$databaseColumnMap['hidden'] ) )
		{
			$hiddenColumn = $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'];
			$where[] = array( "{$hiddenColumn} = 0" );
		}

		if ( $commentClass::commentWhere() !== NULL )
		{
			$where[] = $commentClass::commentWhere();
		}

		$where[] = array( $commentItemField . '=?', $this->$idField );

		return $where;
	}

	/**
	 * @brief Cached data
	 */
	static $cachedActivity = NULL;

	/**
	 * Get the cached data
	 *
	 * @param	string	$key	Key to get
	 * @throws \Exception
	 * @return mixed|NULL
	 */
	protected function _getCached( $key )
	{
		$idField = static::$databaseColumnId;

		$class = \get_class( $this );
		$arrayKey = $class.'.'.$this->$idField;
		if ( !isset( static::$cachedActivity[$arrayKey] ) )
		{
			try
			{
				$cache = \IPS\Db::i()->select( '*', 'core_item_statistics_cache', array( 'cache_class=? and cache_item_id=?', $class, $this->$idField ) )->first();
				
				if ( $cache['cache_added'] > time() - 86400 )
				{
					static::$cachedActivity[ $arrayKey ] = json_decode( $cache['cache_contents'], TRUE );
				}
				else
				{
					\IPS\Db::i()->delete( 'core_item_statistics_cache', array( 'cache_class=? and cache_item_id=?', \get_class( $this ), $this->$idField ) );
				}
			}
			catch( \UnderflowException $e ) { }
		}

		if ( isset(static::$cachedActivity[ $arrayKey ] ) and isset( static::$cachedActivity[ $arrayKey ][ $key ] ) )
		{
			return static::$cachedActivity[ $arrayKey ][ $key ];
		}
		else
		{
			if( !isset( static::$cachedActivity[ $arrayKey ] ) )
			{
				static::$cachedActivity[ $arrayKey ] = array();
			}

			static::$cachedActivity[ $arrayKey ][ $key ] = array();
			throw new \OutOfRangeException;
		}
	}

	/**
	 * @brief	Should we store the data in the cache?
	 */
	protected $storeCache = NULL;

	/**
	 * Set cached data
	 *
	 * @param string $key Key to store
	 * @param mixed $value Value to store
	 * @throws \Exception
	 */
	protected function _storeCached( $key, $value )
	{
		try
		{
			$this->_getCached( $key );
		}
		catch( \Exception $e ) { }

		$idField = static::$databaseColumnId;
		$class = \get_class( $this );
		$arrayKey = $class.'.'.$this->$idField;

		static::$cachedActivity[ $arrayKey ][ $key ] = $value;

		$this->storeCache[ $arrayKey ][ $key ] = $this->$idField;
	}

	/**
	 * Store the cache during destruction
	 *
	 * @return void
	 */
	public function __destruct()
	{
		if( $this->storeCache )
		{
			foreach( $this->storeCache as $key => $data )
			{
				\IPS\Db::i()->insert( 'core_item_statistics_cache', array(
					'cache_class'    => \get_class( $this ),
					'cache_item_id'  => str_replace( \get_class($this) . '.', '', $key),
					'cache_contents' => json_encode( static::$cachedActivity[$key] ),
					'cache_added'	 => time()
				), TRUE );
			}
		}

		if( \is_callable( 'parent::__destruct' ) )
		{
			parent::__destruct();
		}
	}
}