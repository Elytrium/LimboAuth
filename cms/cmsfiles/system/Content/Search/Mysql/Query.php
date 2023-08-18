<?php
/**
 * @brief		MySQL Search Query
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 Aug 2014
*/

namespace IPS\Content\Search\Mysql;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * MySQL Search Query
 */
class _Query extends \IPS\Content\Search\Query
{	
	/**
	 * @brief		The SELECT clause
	 */
	protected $select = array( 'main' => 'main.*' );
	
	/**
     * @brief       The WHERE clause
     */
    protected $where = array();
    
    /**
     * @brief       The WHERE clause for hidden/unhidden
     */
    protected $hiddenClause = NULL;
    
     /**
     * @brief       The WHERE clause for last updated date
     */
    protected $lastUpdatedClause = NULL;
    
    /**
     * @brief       The offset
     */
    protected $offset = 0;
    
    /**
     * @brief       The ORDER BY clause
     */
    protected $order = NULL;
    
    /**
     * @brief       Joins
     */
    protected $joins = array();
    
    /**
     * @brief       Item classes included
     */
    protected $itemClasses = NULL;
    
    /**
     * @brief       Force specific table index
     */
    protected $forceIndex = NULL;
    
    /**
     * @brief       Filter by items I posted in?
     * @see			filterByItemsIPostedIn()
     */
    protected $filterByItemsIPostedIn = FALSE;
    
    /**
     * @brief       Filter by unread items?
     * @see			filterByUnread()
     */
    protected $filterByUnread = FALSE;
	
    /**
     * @brief       InnoDb Stop words
     */
    protected static $innoDBStopWords = array( 'a','about','an','are','as','at','be','by','com','de','en','for','from','how','i','in','is','it','la','of','on','or','that','the','this','to','was','what','when','where','who','will','with','und','the','www' );
    
	/**
	 * Filter by multiple content types
	 *
	 * @param	array	$contentFilters	Array of \IPS\Content\Search\ContentFilter objects
	 * @param	bool	$type			TRUE means only include results matching the filters, FALSE means exclude all results matching the filters
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByContent( array $contentFilters, $type = TRUE )
	{
		/* Init */
		$filters = array();
		$params = array();
		if ( $type )
		{
			$this->itemClasses = array();
		}

		/* Loop the filters */
		foreach ( $contentFilters as $filter )
		{
			$clause = array();
			if ( $type and $filter->itemClass )
			{
				$this->itemClasses[] = $filter->itemClass;
			}
			
			/* Set the class */
			if ( \count( $filter->classes ) > 1 )
			{
				$clause[] = \IPS\Db::i()->in( 'index_class', $filter->classes );
			}
			else
			{
				$clause[] = 'index_class=?';
				$params[] = array_pop( $filter->classes );
			}
			
			/* Set the containers */
			if ( $filter->containerIdFilter !== NULL )
			{
				$clause[] = \IPS\Db::i()->in( 'index_container_id', $filter->containerIds, $filter->containerIdFilter === FALSE );
			}
			
			if ( $filter->itemClass )
			{
				$itemClass = $filter->itemClass;
				if ( isset( $itemClass::$containerNodeClass ) )
				{
					$containerClass  = $itemClass::$containerNodeClass;
					$unsearchableIds = $containerClass::unsearchableNodeIds();
					
					if ( $unsearchableIds != NULL )
					{
						$clause[] = \IPS\Db::i()->in( 'index_container_id', $unsearchableIds, TRUE );
					}	
				}
			}

			/* Are we excluding certain container classes? */
			if( $filter->containerClasses !== NULL )
			{
				if( $filter->containerClassExclusions !== NULL )
				{
					$clause[] = '(' . \IPS\Db::i()->in( 'index_container_class', $filter->containerClasses ) . ' OR ' . \IPS\Db::i()->in( 'index_class', $filter->containerClassExclusions ) . ')';
				}
				else
				{
					$clause[] = \IPS\Db::i()->in( 'index_container_class', $filter->containerClasses );
				}
			}
			
			/* Set the item IDs */
			if ( $filter->itemIdFilter !== NULL )
			{
				$clause[] = \IPS\Db::i()->in( 'index_item_id', $filter->itemIds, $filter->itemIdFilter === FALSE );
			}
			if ( $filter->objectIdFilter !== NULL )
			{
				$clause[] = \IPS\Db::i()->in( 'index_object_id', $filter->objectIds, $filter->objectIdFilter === FALSE );
			}
			
			/* Minimum comments/reviews/views? */
			if ( $filter->minimumComments or $filter->minimumReviews or $filter->minimumViews )
			{
				$class = $filter->itemClass;
				
				$this->joins[] = array( 'from' => $class::$databaseTable, 'where' => $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnId . '=main.index_item_id' );
				
				if ( $filter->minimumComments )
				{
					$this->select[ $class::$databaseTable . '_comments' ] = $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['num_comments'];
					$clause[] = $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['num_comments'] . '>=' . \intval( $filter->minimumComments );
				}
				
				if ( $filter->minimumReviews )
				{
					$this->select[ $class::$databaseTable . '_reviews' ] = $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['num_reviews'];
					$clause[] = $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['num_reviews'] . '>=' . \intval( $filter->minimumReviews );
				}
				
				if ( $filter->minimumViews )
				{
					$this->select[ $class::$databaseTable . '_views' ] = $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['views'];
					$clause[] = $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['views'] . '>=' . \intval( $filter->minimumViews );
				}
			}
			
			/* Only first comment? */
			if ( $filter->onlyFirstComment )
			{
				$clause[] = "index_title IS NOT NULL";
			}
			
			/* Only last comment? */
			if ( $filter->onlyLastComment )
			{
				$clause[] = "index_is_last_comment=1";
			}
			
			/* Put it together */
			if ( \count( $clause ) > 1 )
			{
				$filters[] = '( ' . implode( ' AND ', $clause ) . ' )';
			}
			else
			{
				$filters[] = array_pop( $clause );
			}
		}
		
		/* Put it all together */
		$this->where[] = array_merge( array( $type ? ( '( ' . implode( ' OR ', $filters ) . ' )' ) : ( '!( ' . implode( ' OR ', $filters ) . ' )' ) ), $params );
		
		/* Return */
		return $this;
	}
		
	/**
	 * Filter by author
	 *
	 * @param	\IPS\Member|int|array	$author		The author, or an array of author IDs
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByAuthor( $author )
	{
		if ( \is_array( $author ) )
		{
			$this->where[] = array( \IPS\Db::i()->in( 'index_author', $author ) );
		}
		else
		{
			$this->where[] = array( 'index_author=?', $author instanceof \IPS\Member ? $author->member_id : $author );
		}
		 
		return $this;
	}
	
	
	/**
	 * Filter by club
	 *
	 * @param	\IPS\Member\Club|int|array|null	$club	The club, or array of club IDs, or NULL to exclude content from clubs
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByClub( $club )
	{
		if ( $club === NULL )
		{
			$this->where[] = 'index_club_id IS NULL';
		}
		if ( \is_array( $club ) )
		{
			$this->where[] = array( \IPS\Db::i()->in( 'index_club_id', $club ) );
		}
		else
		{
			$this->where[] = array( 'index_club_id=?', $club instanceof \IPS\Member\Club ? $club->id : $club );
		}

		/* Get the list of valid classes */
		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', FALSE ) as $object )
		{
			foreach ( $object->classes as $class )
			{
				if ( \in_array( 'IPS\Content\Item', class_parents( $class ) ) )
				{
					$classesChecked[]	= $class;
				}
			}
		}

		/* Give content item classes a chance to inspect and manipulate filters */
		$filters = array();
		foreach( $classesChecked as $itemClass )
		{
			$itemClass::searchEngineFiltering( $filters, $this );
		}

		
		return $this;
	}
	
	/**
	 * Filter for profile
	 *
	 * @param	\IPS\Member	$member	The member whose profile is being viewed
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterForProfile( \IPS\Member $member )
	{
		/* Check to see if this member has even had anyone write on their status */
		$count = \IPS\Db::i()->select( 'COUNT(*)', 'core_search_index', array( 'index_class=? AND index_container_id=?', 'IPS\core\Statuses\Status', $member->member_id ) )->first();
		
		if ( $count )
		{
			$this->where[] = array( '( index_author=? OR ( index_class=? AND index_container_id=? ) )', $member->member_id, 'IPS\core\Statuses\Status', $member->member_id );

			$filterResult = $this;
		}
		else
		{
			$filterResult = $this->filterByAuthor( $member );
		}

		/* Get the list of valid classes */
		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', FALSE ) as $object )
		{
			foreach ( $object->classes as $class )
			{
				if ( \in_array( 'IPS\Content\Item', class_parents( $class ) ) )
				{
					$classesChecked[]	= $class;
				}
			}
		}

		/* Give content item classes a chance to inspect and manipulate filters */
		$filters = array();
		foreach( $classesChecked as $itemClass )
		{
			$itemClass::searchEngineFiltering( $filters, $filterResult );
		}

		return $filterResult;
	}
	
	/**
	 * Filter by item author
	 *
	 * @param	\IPS\Member	$author		The author
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByItemAuthor( \IPS\Member $author )
	{
		$this->where[] = array( 'index_item_author=?', $author->member_id );
		 
		return $this;
	}

	/**
	 * Filter by container class
	 *
	 * @param	array	$classes	Container classes to exclude from results.
	 * @param	array	$exclude	Content classes to exclude from the filter. For cases where multiple content classes may have the same container class
	 * 								such as Gallery images, comments and reviews.
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByContainerClasses( $classes=array(), $exclude=array() )
	{
		if( empty( $exclude ) )
		{
			$this->where[] = '( index_container_class IS NULL OR ' . \IPS\Db::i()->in( 'index_container_class', $classes, TRUE ) . ')';
		}
		else
		{
			foreach( $classes as $i => $class )
			{
				$classes[$i] = "'" . \IPS\DB::i()->real_escape_string( $class ) . "'";
			}
			foreach( $exclude as $i => $class )
			{
				$exclude[$i] = "'" . \IPS\DB::i()->real_escape_string( $class ) . "'";
			}

			$this->where[] = '( index_container_class IS NULL OR index_container_class NOT IN(' . implode( ',', $classes ) . ') OR index_class IN(' . implode( ',', $exclude ) . ') )';
		}
		 
		return $this;
	}
	
	/**
	 * Filter by content the user follows
	 *
	 * @param	bool	$includeContainers	Include content in containers the user follows?
	 * @param	bool	$includeItems		Include items and comments/reviews on items the user follows?
	 * @param	bool	$includeMembers		Include content posted by members the user follows?
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByFollowed( $includeContainers, $includeItems, $includeMembers )
	{
		$where = array();
		$params = array();
		$followApps = $followAreas = $case = $containerCase = array();
		$followedItems		= array();
		$followedContainers	= array();

		/* Are we including items or containers? */
		if ( $includeContainers or $includeItems )
		{
			/* Work out what classes we need to examine */
			if ( $this->itemClasses !== NULL )
			{
				$classes = $this->itemClasses;
			}
			else
			{
				$classes = array();
				foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', FALSE ) as $object )
				{
					$classes = array_merge( $object->classes, $classes );
				}
			}
			
			/* Loop them */

			foreach ( $classes as $class )
			{
				if( is_subclass_of( $class, 'IPS\Content\Followable' ) )
				{
					$followApps[ $class::$application ] = $class::$application;
					$followArea = mb_strtolower( mb_substr( $class, mb_strrpos( $class, '\\' ) + 1 ) );
					
					if ( $includeContainers and $includeItems )
					{
						$followAreas[] = mb_strtolower( mb_substr( $class::$containerNodeClass, mb_strrpos( $class::$containerNodeClass, '\\' ) + 1 ) );
						$followAreas[] = $followArea;
					}
					elseif ( $includeItems )
					{
						$followAreas[] = $followArea;
					}
					elseif ( $includeContainers )
					{
						$followAreas[] = mb_strtolower( mb_substr( $class::$containerNodeClass, mb_strrpos( $class::$containerNodeClass, '\\' ) + 1 ) );
					}
					
					/* Work out what classes this applies to - need to specify comment and review classes */
					if ( ! $class::$firstCommentRequired )
					{
						$case[ $followArea ][] = $class;
					}
					
					if( $includeContainers )
					{
						$containerCase[ $followArea ] = mb_strtolower( mb_substr( $class::$containerNodeClass, mb_strrpos( $class::$containerNodeClass, '\\' ) + 1 ) ) ;
					}
					
					if ( isset( $class::$commentClass ) )
					{
						$case[ $followArea ][] = $class::$commentClass;
					}
					if ( isset( $class::$reviewClass ) )
					{
						$case[ $followArea ][] = $class::$reviewClass;
					}
				}
			}

			/* Get the stuff we follow */
			foreach( \IPS\Db::i()->select( '*', 'core_follow', array( 'follow_member_id=? AND ' . \IPS\Db::i()->in( 'follow_app', $followApps ) . ' AND ' . \IPS\Db::i()->in( 'follow_area', $followAreas ), $this->member->member_id ) ) as $follow )
			{
				if( array_key_exists( $follow['follow_area'], $case ) )
				{
					$followedItems[ $follow['follow_area'] ][]	= $follow['follow_rel_id'];
				}
				else if( \in_array( $follow['follow_area'], $containerCase ) )
				{
					$followedContainers[ $follow['follow_area'] ][]	= $follow['follow_rel_id'];
				}
			}
		}

		foreach( $followedItems as $area => $item )
		{
			$where[] = '( ' . \IPS\Db::i()->in( 'index_class', $case[ $area ] ) . " AND index_item_id IN(" . implode( ',', $item ) . ") )";
		}

		foreach( $followedContainers as $area => $container )
		{
			$indexClasses	= array();

			foreach( $containerCase as $followArea => $containerArea )
			{
				if( $containerArea == $area )
				{
					$indexClasses	= $case[ $followArea ];
				}
			}

			$where[] = '( ' . \IPS\Db::i()->in( 'index_class', $indexClasses ) . " AND index_container_id IN(" . implode( ',', $container ) . ") )";
		}

		/* Are we including content posted by followed members? */
		if ( $includeMembers )
		{
			/* Another area where a small result set can drastically slow down the entire query */
			try
			{
				$followed = iterator_to_array( \IPS\Db::i()->select( 'follow_rel_id', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_member_id=?', 'core', 'member', $this->member->member_id ), 'follow_rel_id asc', array( 0, 501 ) ) );

				if ( \count( $followed ) == 501 )
				{
					/* Assume we have loads of matches, so do a full query */
					$where[] = 'index_author IN(?)';
					$params[] = \IPS\Db::i()->select( 'follow_rel_id', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_member_id=?', 'core', 'member', $this->member->member_id ) );
				}
				else if ( \count( $followed ) )
				{
					/* IN is not a SIN. It's been a long day */
					$where[] = \IPS\Db::i()->in( 'index_author', $followed );	
				}
				else
				{
					/* There are no results */
					$this->where[] = "1=2 /*Filter by followed returned nothing*/";
				}
			}
			catch( \UnderflowException $ex )
			{
				/* There are no results */
				$this->where[] = "1=2 /*Filter by followed returned nothing*/";
			}
		}
		
		/* Put it all together */
		if ( \count( $where ) )	
		{
			$this->where[] = array_merge( array( '( ' . implode( ' OR ', $where ) . ' )' ), $params );
		}
		else
		{
			/* If we want to filter by followed content, and we don't actually follow any content then we shouldn't return anything */
			$this->where[] = "1=2 /*Filter by followed returned nothing*/";
		}

		/* And return */
		return $this;
	}
	
	/**
	 * Filter by content the user has posted in. This must be at the end of the chain.
	 *
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByItemsIPostedIn()
	{
		/* We have to set a property because we need the other data like other filters and ordering to figure this out */
		$this->filterByItemsIPostedIn = TRUE;
		
		/* Return for daisy chaining */
		return $this;	
	}
	
	/**
	 * Filter by content the user has not read
	 *
	 * @note	If applicable, it is more efficient to call filterByContent() before calling this method
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByUnread()
	{		
		/* Work out what classes we need to examine */
		if ( $this->itemClasses !== NULL )
		{
			$classes = $this->itemClasses;
		}
		else
		{
			$classes = array();
			foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', FALSE ) as $object )
			{
				$classes = array_merge( $object->classes, $classes );
			}
		}
		
		/* Loop them */
		$where = array();
		$params = array();
		foreach ( $classes as $class )
		{
			if( is_subclass_of( $class, 'IPS\Content\ReadMarkers' ) )
			{
				/* Get the actual clause */
				$unreadWhere = $this->_getUnreadWhere( $class );
				
				/* Work out what classes this applies to - need to specify comment and review classes */
				$_classes = array( $class );
				if ( isset( $class::$commentClass ) )
				{
					$_classes[] = $class::$commentClass;
				}
				if ( isset( $class::$reviewClass ) )
				{
					$_classes[] = $class::$reviewClass;
				}
				
				/* Add it to the array */
				$clause = array( \IPS\Db::i()->in( 'index_class', $_classes ) );
				foreach ( $unreadWhere as $_clause )
				{
					$clause[] = array_shift( $_clause );
					$params = array_merge( $params, $_clause );
				}
				$where[] = '( ' . implode( ' AND ', $clause ) . ' )';
			}
		}
		
		if ( \count( $where ) )
		{
			/* Put it all together */		
			$this->where[] = array_merge( array( '( ' . implode( ' OR ', $where ) . ' )' ), $params );
		}
		
		$this->filterByUnread = TRUE;
	}

	/**
	 * Filter only solved content
	 *
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterBySolved()
	{
		$this->where[] = array( 'index_item_solved=?', 1 );

		return $this;
	}

	/**
	 * Filter only unsolved content
	 *
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByUnsolved()
	{
		$this->where[] = array( 'index_item_solved=?', 0 );

		return $this;
	}

	/**
	 * Get the 'unread' where SQL
	 *
	 * @param	string	$class 		Content class (\IPS\forums\Forum)
	 * @return	array
	 */
	protected function _getUnreadWhere( $class )
	{
		$classBits	    = explode( "\\", $class );
		$application    = $classBits[1];
		$resetTimes	    = $this->member->markersResetTimes( NULL );
		$resetTimes		= isset( $resetTimes[ $application ] ) ? $resetTimes[ $application ] : array();
		$oldestTime	    = time();
		$markers	    = array();
		$excludeIds     = array();
		$where          = array();
		$unreadWheres	= array();
		$containerIds	= array();
		$containerClass = isset( $class::$containerNodeClass ) ? $class::$containerNodeClass : NULL;

		if ( \is_array( $resetTimes ) )
		{
			foreach( $resetTimes as $containerId => $timestamp )
			{
				/* Pages has different classes per database, but recorded as 'cms' and the container ID in the marking tables */
				if ( $containerClass and method_exists( $containerClass, 'isValidContainerId' ) )
				{
					if ( ! $containerClass::isValidContainerId( $containerId ) )
					{
						continue;
					}
				}
	
				$timestamp	= $timestamp ?: $this->member->marked_site_read;
		
				$containerIds[]	= $containerId;
				$unreadWheres[]	= '( index_container_id=' . $containerId . ' AND index_date_updated > ' . (int) $timestamp . ')';
				
				$items = $this->member->markersItems( $application, \IPS\Content\Item::makeMarkerKey( $containerId ) );
				
				if ( \count( $items ) )
				{
					foreach( $items as $mid => $mtime )
					{
						if ( $mtime > $timestamp )
						{
							/* If an item has been moved from one container to another, the user may have a marker
								in it's old location, with the previously 'read' time. In this circumstance, we need
								to only use more recent read time, otherwise the topic may be incorrectly included
								in the results */
							if ( \in_array( $mid, $markers ) )
							{
								$_key = array_search( $mid, $markers );
								$_mtime = \intval( mb_substr( $_key, 0, mb_strpos( $_key, '.' ) ) );
								if ( $_mtime < $mtime )
								{
									unset( $markers[ $_key ] );
								}
								/* If the existing timestamp is higher, retain that since we reset the $markers array below */
								else
								{
									$mtime = $_mtime;
								}
							}
							
							$markers[ $mtime . '.' . $mid ] = $mid;
						}
					}
				}
			}
		} 
		else 
		{
			$unreadWheres[] = "( index_date_updated > " . \intval( $resetTimes ) . ")";
		}
		
		if( \count( $containerIds ) )
		{
			$unreadWheres[]	= "( index_date_updated > " . \intval( $this->member->marked_site_read ) . " AND ( index_container_id NOT IN(" . implode( ',', $containerIds ) . ") ) )";
		}
		else
		{
			$unreadWheres[]	= "( index_date_updated > " . \intval( $this->member->marked_site_read ) . ")";
		}
	
		if( \count( $unreadWheres ) )
		{
			$where[] = array( "(" . implode( " OR ", $unreadWheres ) . ")" );
		}
	
		if ( \count( $markers ) )
		{
			/* Avoid packet issues */
			krsort( $markers );
			$useIds = array_flip( \array_slice( $markers, 0, 1000, TRUE ) );
			$select = '';
			$from   = '';
			$notIn  = array();
			
			/* What is the best date column? */
			$dateColumns = array();
			foreach ( array( 'updated', 'last_comment', 'last_review' ) as $k )
			{
				if ( isset( $class::$databaseColumnMap[ $k ] ) )
				{
					if ( \is_array( $class::$databaseColumnMap[ $k ] ) )
					{
						foreach ( $class::$databaseColumnMap[ $k ] as $v )
						{
							$dateColumns[] = " IFNULL( " . $class::$databaseTable . '.'. $class::$databasePrefix . $v . ", 0 )";
						}
					}
					else
					{
						$dateColumns[] = " IFNULL( " . $class::$databaseTable . '.'. $class::$databasePrefix . $class::$databaseColumnMap[ $k ] . ", 0 )";
					}
				}
			}
			$dateColumnExpression = \count( $dateColumns ) > 1 ? ( 'GREATEST(' . implode( ',', $dateColumns ) . ')' ) : array_pop( $dateColumns );
			
			foreach( \IPS\Db::i()->select( $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnId. ' as _id, ' . $dateColumnExpression . ' as _date', $class::$databaseTable, \IPS\Db::i()->in( $class::$databasePrefix . $class::$databaseColumnId, array_keys( $useIds ) ) ) as $row )
			{
				if ( isset( $useIds[ $row['_id'] ] ) )
				{
					if ( $useIds[ $row['_id'] ] >= $row['_date'] )
					{
						/* Still read */
						$notIn[] = \intval( $row['_id'] );
					}
				}
			}
			
			if ( \count( $notIn ) )
			{
				$where[] = array( "( index_item_id NOT IN (" . implode( ',', $notIn ) . ") )" );
			}
		}
		
		return $where;
	}
		
	/**
	 * Filter by start date
	 *
	 * @param	\IPS\DateTime|NULL	$start		The start date (only results AFTER this date will be returned)
	 * @param	\IPS\DateTime|NULL	$end		The end date (only results BEFORE this date will be returned)
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByCreateDate( \IPS\DateTime $start = NULL, \IPS\DateTime $end = NULL )
	{
		if ( $start )
		{
			$this->where[] = array( 'index_date_created>?', $start->getTimestamp() );
		}
		if ( $end )
		{
			$this->where[] = array( 'index_date_created<?', $end->getTimestamp() );
		}
		return $this;
	}
	
	/**
	 * Filter by last updated date
	 *
	 * @param	\IPS\DateTime|NULL	$start		The start date (only results AFTER this date will be returned)
	 * @param	\IPS\DateTime|NULL	$end		The end date (only results BEFORE this date will be returned)
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByLastUpdatedDate( \IPS\DateTime $start = NULL, \IPS\DateTime $end = NULL )
	{
		if ( $start )
		{
			$this->lastUpdatedClause[] = array( 'index_date_updated>?', $start->getTimestamp() );
		}
		if ( $end )
		{
			$this->lastUpdatedClause[] = array( 'index_date_updated<?', $end->getTimestamp() );
		}
		return $this;
	}
	
	/**
	 * Set hidden status
	 *
	 * @param	int|array	$statuses	The statuses (array of HIDDEN_ constants)
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function setHiddenFilter( $statuses )
	{
		if ( \is_null( $statuses ) )
		{
			$this->hiddenClause = NULL;
		}
		if ( \is_array( $statuses ) )
		{
			$this->hiddenClause = array( \IPS\Db::i()->in( 'index_hidden', $statuses ) );
		}
		else
		{
			$this->hiddenClause = array( 'index_hidden=?', $statuses );
		}
		
		return $this;
	}
	
	/**
	 * Set page
	 *
	 * @param	int		$page	The page number
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function setPage( $page )
	{
		$this->offset = ( $page - 1 ) * $this->resultsToGet;
		
		return $this;
	}
	
	/**
	 * Set order
	 *
	 * @param	int		$order	Order (see ORDER_ constants)
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function setOrder( $order )
	{		
		switch ( $order )
		{
			case static::ORDER_NEWEST_UPDATED:
				$this->order = 'index_date_updated DESC';
				break;
				
			case static::ORDER_OLDEST_UPDATED:
				$this->order = 'index_date_updated ASC';
				break;
			
			case static::ORDER_NEWEST_CREATED:
				$this->order = 'index_date_created DESC';
				break;
				
			case static::ORDER_OLDEST_CREATED:
				$this->order = 'index_date_created ASC';
				break;
				
			case static::ORDER_NEWEST_COMMENTED:
				$this->order = 'index_date_commented DESC';
				break;

			case static::ORDER_RELEVANCY:
				$this->order = 'calcscore DESC';
				break;
		}
		
		return $this;
	}
	
	/**
	 * Build where
	 *
	 * @param	string|null	$term		The term to search for
	 * @param	array|null	$tags		The tags to search for
	 * @param	int			$method		See \IPS\Content\Search\Query::TERM_* contants
	 * @param	string		$operator	If $term contains more than one word, determines if searching for both ("and") or any ("or") of those terms
	 * @return	array
	 */
	protected function _searchWhereClause( $term = NULL, $tags = NULL, $method = 1, $operator = 'and' )
	{
		$tagWhere = NULL;
		$termWhere = NULL;
		
		/* Do we have tags? */
		if ( $tags !== NULL )
		{
			$itemsOnlyTagSearch = '';
			
			if ( $method & static::TAGS_MATCH_ITEMS_ONLY )
			{
				$itemsOnlyTagSearch = 'index_item_index_id=index_id AND ';
			}
			
			/* Large index tables and small tag tables can cause a significant slow down in this query execution, so we attempt to pre-fetch some results */
			try
			{
				$tagIds = iterator_to_array( \IPS\Db::i()->select( 'index_id', 'core_search_index_tags', array( \IPS\Db::i()->in( 'index_tag', $tags ) ), 'index_id asc', array( 0, 501 ) ) );
				
				/* Now, if we have 501 results, then we have to assume there are more, so the join is required */
				if ( \count( $tagIds ) == 501 )
				{
					$tagWhere = array( $itemsOnlyTagSearch . 'index_item_index_id IN (' . \IPS\Db::i()->select( 'index_id', 'core_search_index_tags', array( \IPS\Db::i()->in( 'index_tag', $tags ) ) ) . ')' );
				}
				else
				{
					$tagWhere = array( $itemsOnlyTagSearch . \IPS\Db::i()->in( 'index_item_index_id', $tagIds ) );
				}
			}
			catch( \UnderflowException $ex )
			{
				/* No matches at all */
				if ( $method & static::TERM_AND_TAGS )
				{
					/* We want to match term and tags, so return an impossible result set */
					$tagWhere = array( "1=2" );
				}
				else
				{
					$tagWhere = array( "1=1" );
				}
			}
		}

		/* Do we have a term? */
		if ( $term !== NULL )
		{	
			$termWhere = static::matchClause( $method & static::TERM_TITLES_ONLY ? 'index_title' : 'index_content,index_title', $term, $operator === static::OPERATOR_AND ? '+' : '' );
		}
		
		/* Put those two together */
		if ( $termWhere !== NULL and $tagWhere !== NULL )
		{
			if ( $method & static::TERM_OR_TAGS )
			{
				$where[] = array_merge( array( '( ( ' . array_shift( $termWhere ) . ' ) OR ( ' . array_shift( $tagWhere ) . ' ) )' ), array_merge( $termWhere, $tagWhere ) );
			}
			else
			{
				$where[] = $termWhere;
				$where[] = $tagWhere;
			}
		}
		/* Or just use the term if that's all we have */
		elseif ( $termWhere !== NULL )
		{
			$where[] = $termWhere;
		}
		/* Or just use tags if that's all we have */
		elseif ( $tagWhere !== NULL )
		{
			$where[] = $tagWhere;
		}
		
		/* Only get stuff we have permission for */
		$where[] = array( "( index_permissions = '*' OR " . \IPS\Db::i()->findInSet( 'index_permissions', $this->permissionArray() ) . ' )' );
		if ( $this->hiddenClause )
		{
			$where[] = $this->hiddenClause;
		}
		
		/* Filer by items I posted in? */
		if ( $this->filterByItemsIPostedIn and $this->member->member_id )
		{
			/* Another area where a small result set can drastically slow down the entire query */
			try
			{
				/* Work out what classes we need to examine */
				if ( $this->itemClasses !== NULL )
				{
					$classes = $this->itemClasses;
				}
				else
				{
					$classes = array();
					foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', FALSE ) as $object )
					{
						$classes = array_merge( $object->classes, $classes );
					}
				}

				$results = iterator_to_array( \IPS\Db::i()->select( 'index_item_id, index_class', array( 'core_search_index_item_map', 'sub' ), array( array( 'index_author_id=' . \intval( $this->member->member_id ) . ' AND ' . \IPS\Db::i()->in( 'index_class', $classes ) ) ), 'index_item_id desc', array( 0, 501 ) ) );
			
				if ( \count( $results ) )
				{
					/* Assume we have loads of matches, so do a full query */
					$subClause = array( array( 'sub.index_item_id=main.index_item_id AND index_author_id=' . \intval( $this->member->member_id ) . ' AND ' . $this->makeEmbeddedIfClauseForSearchMapTable('sub.index_class') ) );
					$query = \IPS\Db::i()->select( 'index_item_id', array( 'core_search_index_item_map', 'sub' ), $subClause );
					$where[] = array( 'EXISTS(?)', $query );
				}
				else
				{
					/* There are no results */
					$where[] = "1=2 /*Filter by items I posted in returned nothing*/";
				}
			}
			catch( \UnderflowException $ex )
			{
				/* There are no results */
				$where[] = "1=2 /*Filter by items I posted in returned nothing*/";
			}
		}

		/* Filter by last updated? */
		if ( $this->lastUpdatedClause !== NULL )
		{
			foreach( $this->lastUpdatedClause as $clause )
			{
				$where[] = $clause;
			}
		}

		/* Return */
		return $where;
	}
	
	/**
	 * Makes an embedded search clause to convert comment classes to item classes
	 *
	 * @param	string		$column		Column to use in the clause
	 * @return	string
	 */
	protected function makeEmbeddedIfClauseForSearchMapTable( $column )
	{
		/* Work out what classes we need to examine */
		if ( $this->itemClasses !== NULL )
		{
			$classes = $this->itemClasses;
		}
		else
		{
			$classes = array();
			foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', FALSE ) as $object )
			{
				$classes = array_merge( $object->classes, $classes );
			}
		}
		
		$stack = array();
		foreach( $classes as $class )
		{
			if ( isset( $class::$commentClass ) )
			{
				$stack[ $class::$commentClass ] = $class;
			}
		}
		
		if ( \count( $stack ) )
		{
			$if = '';
			foreach( $stack as $commentClass => $class )
			{
				$if .= " IF( main.index_class='" . \IPS\Db::i()->escape_string( $commentClass ) . "', '" . \IPS\Db::i()->escape_string( $class ) . "', ";
			}
			
			$if .= 'main.index_class' . str_repeat( ' )', \count( $stack ) );
		}
		
		return $column . '=' . ( $if == '' ? $column : $if );
	}
	
	/**
	 * Match clause
	 *
	 * @param	string	$columns			Columns to match against (e.g. "index_tags" or "index_title,index_content")
	 * @param	string	$term				The term
	 * @param	string	$defaultOperator	The default operator to add to each word if there isn't one - "+" is "and mode"; "" is "or mode"
	 * @param	bool	$prepared			If FALSE, does not use prepared statements (used for the sorting algorithm because you can't use ?s in the select clause)
	 * @return	array|string
	 */
	public static function matchClause( $columns, $term, $defaultOperator='+', $prepared=TRUE )
	{
		/* Loop the words */
		$words = array();
		
		/* If we have a phrase, we'd normally use the boolean engine to search it, but this is incredibly slow on larger tables (5+ million rows) because MySQL searches all the words in the word index
		   but if some words are below the minimum length, it performs a full table scan which is very expensive.
		   The solution here is to break down the phrase (i love the chocolate cake) into usable keywords ('love +chocolate +cake') and back up the full text results with a LIKE '%i love the chocolate cake%'
		   search which is actually very efficient as MySQL performs a search on the FT index first to narrow down the results */
		if ( static::termIsPhrase( $term ) )
		{
			$term = str_replace( array( "'", '"', '?' ), '', $term );

			foreach ( static::termAsWordsArray( $term ) as $word )
			{
				/* Operators are not allowed */
				$word = preg_replace( '/[+\-><\(\)~*\"@%]+/', '', $word );
				
				/* If any words are stop words the lookup will fail */
				if ( $word and !\in_array( $word, static::$innoDBStopWords ) )
				{
					$words[] = $word;
				}
			}

			$booleanTerm = implode( ' +', $words );

			/* Return */
			if ( $prepared )
			{
				if ( mb_strstr( $columns, ',' ) )
				{
					$like = array();
					$extra = '';
					foreach( explode( ',', $columns ) as $col )
					{
						$like[] = $col . ' LIKE \'%' . \IPS\Db::i()->escape_string( $term ) . '%\'';
					}
					
					$extra = implode( ' OR ', $like );
				}
				else
				{
					$extra = $columns . ' LIKE \'%' . \IPS\Db::i()->escape_string( $term ) . '%\'';
				}

				return array( "MATCH({$columns}) AGAINST (? IN BOOLEAN MODE) AND (" . $extra . ")", $booleanTerm );
			}
			else
			{
				return "MATCH({$columns}) AGAINST ('" . \IPS\Db::i()->escape_string( $booleanTerm ) . "' IN BOOLEAN MODE)";
			}
		}
		else
		{
			$likeWhere = array();
			foreach ( static::termAsWordsArray( $term ) as $word )
			{
				/* Add the default operator */
				if ( $defaultOperator and !\in_array( mb_substr( $word, 0, 1 ), array( '+', '-', '~', '<', '>' ) ) )
				{
					/* Clear out leading * symbols so we don't end up with +*a** */
					$word = $defaultOperator . ltrim( $word, '*' );
				}

				/* Double operators are not allowed */
				$word = preg_replace( '/^([\+\-~\*<>]){2,}/', '$1', $word );
				$word = preg_replace( '/([\+\-~\*<>]){2,}$/', '$1', $word );

				/* Trailing + or -s are not allowed */
				$word = rtrim( $word, '+-' );

				/* ? are not allowed either */
				$word = str_replace( '?', '', $word );
				
				/* These rules only apply if we're not in a quoted phrase... */
				if ( !static::termIsPhrase( $word ) )
				{
					/* We can't have any other operators as MySQL will interpret them as a separate word. If they exist, wrap the word in quotes */
					if ( preg_match( '/^.+[\+\-~<>\.]/', $word ) )
					{
						$trimmedWord = \IPS\Db::i()->escape_string( str_replace( '"', '', ltrim( $word, $defaultOperator ) ) );
						$likes = array();
						foreach( explode( ',', $columns ) AS $column )
						{
							$likes[] = $column . ' LIKE \'%' . $trimmedWord . '%\'';
						}
						$likeWhere[] = '( ' . implode( ' or ', $likes ) . ' )';
						continue;
					}
					/* Otherwise carry on... */
					else
					{				
						/* +* and +- are not allowed anywhere in the word */
						$word = str_replace( array( '+*', '+-' ), '+', $word );
			
						/* Nor is @ or parenthesis (while paranthesis can be used to group words and apply operators to the group,
							(e.g. "+apple +(>turnover <strudel)") - it's unlikely a user intends this behaviour) */
						$word = str_replace( array( '(', ')' ), '', str_replace( '@', ' ', $word ) );
						
						preg_match( '#^(\+|\-|\*)#', $word, $matches );
						if ( mb_stripos( $word, "'" ) and isset( $matches[1] ) )
						{
							/* Due to MySQL bug (https://bugs.mysql.com/bug.php?id=69932) apostrophes confuse things */
							preg_match( '#^(\+|\-|\*)#', $word, $matches );
							
							$word = $matches[1] . '(' . str_replace( $matches[1], '', $word ) . ')';
						}			
					}
				}

				/* Add it */
				$words[] = $word;
			}
			$term = implode( ' ', $words );

			/* Return */
			$return = array();

			if ( $prepared )
			{
				/* Force newest as of 4.2.6 to test search results */
				if ( \count( $likeWhere ) and \count( $words ) )
				{
					$return = array( implode( ' AND ', $likeWhere ) . " AND MATCH({$columns}) AGAINST (? IN BOOLEAN MODE)", $term );
				}
				else if ( \count( $likeWhere ) )
				{
					$return = array( implode( ' AND ', $likeWhere ) );
				}
				else
				{
					$return = array( "MATCH({$columns}) AGAINST (? IN BOOLEAN MODE)", $term );
				}
			}
			else
			{
				/* Force newest as of 4.2.6 to test search results */
				if ( \count( $likeWhere ) )
				{
					$return[] = implode( ' AND ', $likeWhere );
				}
				
				$return[] = "MATCH({$columns}) AGAINST ('" . \IPS\Db::i()->escape_string( $term ) . "' IN BOOLEAN MODE)";

				$return = implode( ' AND ', $return );
			}
		}

		return $return;
	}
	
	/**
	 * Search
	 *
	 * @param	string|null	$term		The term to search for
	 * @param	array|null	$tags		The tags to search for
	 * @param	int			$method 	See \IPS\Content\Search\Query::TERM_* contants - controls where to search
	 * @param	string|null	$operator	If $term contains more than one word, determines if searching for both ("and") or any ("or") of those terms. NULL will go to admin-defined setting
	 * @return	\IPS\Content\Search\Results
	 */
	public function search( $term = NULL, $tags = NULL, $method = 1, $operator = NULL )
	{
		/* What's our operator? */
		$operator = $operator ?: \IPS\Settings::i()->search_default_operator;
		
		/* Set the select clause */
		$select = implode( ', ', $this->select );
		
		/* Get the where clause */
		$where = array_merge( $this->where, $this->_searchWhereClause( $term, $tags, $method, $operator ) );
		
		/* Set order clause */
		$order = $this->order;
		
		/* This forces MySQL to strongly hint a better index to the optimiser for MySQL 5.7+, even though sorting will not be affected */
		if ( !\IPS\CIC )
		{
			try
			{
				if ( $this->filterByUnread !== FALSE and ( !mb_stristr( \IPS\Db::i()->server_info, '-MariaDB' ) and \IPS\Db::i()->server_version >= 50700 ) )
				{
					$order .= ", index_date_updated";
				}

				/* If we're filtering by unread, and not using MariaDB, then force an index because MySQL tends to use the incorrect one in this scenario. */
				if ( !mb_stristr( \IPS\Db::i()->server_info, '-MariaDB' ) and $this->filterByUnread === TRUE )
				{
					$this->forceIndex = 'index_date_updated';
				}
			}
			catch( \Throwable $e ) { /* Do nothing if server_info access fails */ }
		}
			
		/* But we're sorting by relevancy, we need to actually select that value with our fancy algorithm */
		if ( mb_substr( $this->order, 0, 9 ) === 'calcscore' )
		{
			/* But we can only do that if there's a term (rather than tag-only) */
			if ( $term !== NULL )
			{
				if ( \IPS\Settings::i()->search_title_boost )
				{
					$titleField = '(' . static::matchClause( 'index_title', $term, $operator === static::OPERATOR_AND ? '+' : '', FALSE ) . '*' . \intval( \IPS\Settings::i()->search_title_boost ) . ')'; // The title score times multiplier
				}
				else
				{
					$titleField = '(' . static::matchClause( 'index_title', $term, $operator === static::OPERATOR_AND ? '+' : '', FALSE ) . ')';
				}
								
				$select .= ', ' 
					. '('
						. $titleField
						. '+'
						. '(' . static::matchClause( 'index_content,index_title', $term, $operator === static::OPERATOR_AND ? '+' : '', FALSE ) . ')'		// Plus the content score times 1
					. ')'
					. '/'																																// Divided by
					. 'POWER('
						. '( ( UNIX_TIMESTAMP( NOW() ) - ( CASE WHEN index_date_updated <= UNIX_TIMESTAMP( NOW() ) THEN index_date_updated ELSE 0 END )) / 3600 ) + 2' // The number of days between now and the updated date, plus 2
					. ',1.5)'																															// To the power of 1.5
				. ' AS calcscore';
			}
			/* So if we don't have a term, fallback to last updated */
			else
			{
				$order = 'index_date_updated DESC';
			}
		}
		
		/* Construct the query */
		$query = \IPS\Db::i()->select( $select, array( 'core_search_index', 'main' ), $where, $order, array( $this->offset, $this->resultsToGet ), NULL, NULL );
		foreach ( $this->joins as $data )
		{
			$query->join( $data['from'], $data['where'], isset( $data['type'] ) ? $data['type'] : 'LEFT' );
		}
		
		/* Force index? */
		if ( $this->forceIndex )
		{
			$query->forceIndex( $this->forceIndex );
		}
		
		/* Return */
		$count = $this->count( $term, $tags, $method, $operator, FALSE );
		return new \IPS\Content\Search\Results( iterator_to_array( $query ), $count );
	}
	
	/**
	 * Get count
	 *
	 * @param	string|null	$term				The term to search for
	 * @param	array|null	$tags				The tags to search for
	 * @param	int			$method				See \IPS\Content\Search\Query::TERM_* contants
	 * @param	string|null	$operator			If $term contains more than one word, determines if searching for both ("and") or any ("or") of those terms. NULL will go to admin-defined setting
	 * @param	boolean		$returnCountAsInt	If TRUE, it will return the count as an integer, when FALSE it will return the \IPS\Db\Select object
	 * @return	\IPS\Db\Query|int
	 */
	public function count( $term = NULL, $tags = NULL, $method = 1, $operator = NULL, $returnCountAsInt=TRUE )
	{
		/* Get the where clause */
		$where = array_merge( $this->where, $this->_searchWhereClause( $term, $tags, $method, $operator ) );
		
		/* Construct the query */
		$query = \IPS\Db::i()->select( 'COUNT(*)', array( 'core_search_index', 'main' ), $where );
		foreach ( $this->joins as $data )
		{
			$query->join( $data['from'], $data['where'], isset( $data['type'] ) ? $data['type'] : 'LEFT' );
		}
		
		/* Return */
		return ( ! $returnCountAsInt ) ? $query : $query->first();
	}
	
	/**
	 * Get the default date cut off
	 *
	 * @return string
	 */
	public function getDefaultDateCutOff()
	{
		if ( \IPS\USE_MYSQL_SEARCH_BASIC_MODE_THRESHOLD and static::getTableSize() >= \IPS\USE_MYSQL_SEARCH_BASIC_MODE_THRESHOLD )
		{
			return 'year';
		}
		
		return 'any';
	}
	
	/**
	 * Get the default sort method
	 *
	 * @return string
	 */
	public function getDefaultSortMethod()
	{
		if ( \IPS\USE_MYSQL_SEARCH_BASIC_MODE_THRESHOLD and static::getTableSize() >= \IPS\USE_MYSQL_SEARCH_BASIC_MODE_THRESHOLD )
		{
			return 'newest';
		}
		
		return parent::getDefaultSortMethod();
	}
	
	protected static $tableCount = NULL;
	
	/**
	 * Get the row count from the core_search_index table
	 *
	 * @return string
	 */
	protected static function getTableSize()
	{
		if ( static::$tableCount === NULL )
		{
			static::$tableCount = \IPS\Db::i()->select( 'COUNT(*)', 'core_search_index' )->first();
		}
		
		return static::$tableCount;
	}
	
	/**
	 * Convert the term into an array of words
	 *
	 * @param	string			$term			The term to search for
	 * @param	boolean			$ignorePhrase	When true, phrases are stripped of quotes and treated as normal words
	 * @param	int|NULL		$minLength		The minimum length a sequence of characters has to be before it is considered a word. If null, ft_min_word_len/innodb_ft_min_token_size is used.
	 * @param	int|NULL		$maxLength		The maximum length a sequence of characters can be for it to be considered a word. If null, ft_max_word_len/innodb_ft_max_token_size is used.
	 * @return	array
	 */
	public static function termAsWordsArray( $term, $ignorePhrase=FALSE, $minLength=NULL, $maxLength=NULL )
	{		
		/* If we haven't set a preferred min/max length, use the MySQL configuration */
		if ( $minLength === NULL or $maxLength === NULL )
		{
			/* If we don't already know what they are, get the values from the MySQL configuration */
			if ( ( $minLength === NULL and !isset( \IPS\Data\Store::i()->mysqlMinWord ) ) or ( $maxLength === NULL and !isset( \IPS\Data\Store::i()->mysqlMaxWord ) ) )
			{
				/* The variable we need depends on whether the table is MyISAM or InnoDB */
				$tableDefinition = \IPS\Db::i()->getTableDefinition('core_search_index');
				if ( $tableDefinition['engine'] == 'InnoDB' )
				{
					$minVariable = 'innodb_ft_min_token_size';
					$maxVariable = 'innodb_ft_max_token_size';
				}
				else
				{
					$minVariable = 'ft_min_word_len';
					$maxVariable = 'ft_max_word_len';
				}
				
				/* Now fetch those */			
				try
				{
					foreach ( new \IPS\Db\Select( 'SHOW VARIABLES WHERE Variable_Name=? OR Variable_Name=?', array( $minVariable, $maxVariable ), \IPS\Db::i() ) as $row )
					{
						if ( $row['Variable_name'] === $minVariable )
						{
							\IPS\Data\Store::i()->mysqlMinWord = \intval( $row['Value'] );
						}
						elseif ( $row['Variable_name'] === $maxVariable )
						{
							\IPS\Data\Store::i()->mysqlMaxWord = \intval( $row['Value'] );
						}
					}
				}
				catch( \IPS\Db\Exception $e ) { }
				
				/* If we weren't able to get them, set sensible defaults */
				if ( !isset( \IPS\Data\Store::i()->mysqlMinWord ) )
				{
					\IPS\Data\Store::i()->mysqlMinWord = 3;
				}
				if ( !isset( \IPS\Data\Store::i()->mysqlMaxWord ) )
				{
					\IPS\Data\Store::i()->mysqlMaxWord = 84;
				}
			}
			
			/* Set */
			if ( $minLength === NULL )
			{
				$minLength = \IPS\Data\Store::i()->mysqlMinWord;
			}
			if ( $maxLength === NULL )
			{
				$maxLength = \IPS\Data\Store::i()->mysqlMaxWord;
			}
		}
		
		/* And then pass up */
		return parent::termAsWordsArray( $term, $ignorePhrase, $minLength, $maxLength );
	}
}
