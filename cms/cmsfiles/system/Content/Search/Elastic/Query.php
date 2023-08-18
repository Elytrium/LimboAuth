<?php
/**
 * @brief		Elasticsearch Search Query
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		9 Nov 2017
*/

namespace IPS\Content\Search\Elastic;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Elasticsearch Search Query
 */
class _Query extends \IPS\Content\Search\Query
{
	/**
	 * @brief	The server URL
	 */
	protected $url;
	
	/**
	 * @brief	Filters
	 */
	protected $filters = array();
	
	/**
	 * @brief	"Must Not" filter
	 */
	protected $mustNot = array();
	
	 /**
     * @brief	The sort clause
     */
    protected $sort = NULL;
    
    /**
     * @brief       The offset
     */
    protected $offset = 0;
	
	/**
	 * @brief	index_hidden statuses
	 */
	protected $hiddenStatuses = NULL;
	
	/**
     * @brief       Item classes included
     */
    protected $itemClasses = NULL;

	/**
	 * Constructor
	 *
	 * @param	\IPS\Member	$member	The member performing the search
	 * @param	\IPS\Http\Url	$url	The server URL
	 * @return	void
	 */
	public function __construct( \IPS\Member $member, \IPS\Http\Url $url )
	{
		parent::__construct( $member );
		$this->url = $url;
	}

	/**
	 * @var bool Stores whether or not any content type needs to be limited by the latest comment
	 */
	protected $lastCommentMustBeTrue = false;

	/**
	 * Filter by multiple content types
	 *
	 * @param	array	$contentFilters	Array of \IPS\Content\Search\ContentFilter objects
	 * @param	bool	$type			TRUE means only include results matching the filters, FALSE means exclude all results matching the filters
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByContent( array $contentFilters, $type = TRUE )
	{
		$contentFilterConditions = array();
		if ( $type )
		{
			$this->itemClasses = array();
		}
		
		/* Loop through the filters... */
		foreach ( $contentFilters as $filter )
		{
			$conditions = array();
			if ( $type )
			{
				$this->itemClasses[] = $filter->itemClass;
			}
			
			/* Start by specifying the classes */
			$conditions[] = array(
				'terms' => array(
					'index_class' => $filter->classes
				)
			);
			
			/* Container filer */
			if ( $filter->containerIdFilter !== NULL )
			{
				if ( $filter->containerIdFilter )
				{
					$conditions[] = array(
						'terms' => array(
							'index_container_id' => $filter->containerIds
						)
					);
				}
				else
				{
					$conditions[] = array(
						'bool'	=> array(
							'must_not' => array(
								'terms' => array(
									'index_container_id' => $filter->containerIds
								)
							)
						)
					);
				}
			}
			
			/* Item filter */
			if ( $filter->itemIdFilter !== NULL )
			{
				if ( $filter->itemIdFilter )
				{
					$conditions[] = array(
						'terms' => array(
							'index_item_id' => $filter->itemIds
						)
					);
				}
				else
				{
					$conditions[] = array(
						'bool'	=> array(
							'must_not' => array(
								'terms' => array(
									'index_item_id' => $filter->itemIds
								)
							)
						)
					);
				}
			}
			if ( $filter->objectIdFilter !== NULL )
			{
				if ( $filter->objectIdFilter )
				{
					$conditions[] = array(
						'terms' => array(
							'index_object_id' => $filter->objectIds
						)
					);
				}
				else
				{
					$conditions[] = array(
						'bool'	=> array(
							'must_not' => array(
								'terms' => array(
									'index_object_id' => $filter->objectIds
								)
							)
						)
					);
				}
			}
			
			/* Minimum comments / reviews */
			foreach ( array( 'minimumComments' => 'index_comments', 'minimumReviews' => 'index_reviews' ) as $filterKey => $indexKey )
			{
				if ( $filter->$filterKey )
				{
					$conditions[] = array(
						'range' => array(
							$indexKey => array( 'gte' => $filter->$filterKey )
						)
					);
				}
			}
			
			/* Only first comment? */
			if ( $filter->onlyFirstComment )
			{
				$conditions[] = array(
					'exists' => array( 'field' => 'index_title' )
				);
			}
			
			/* Only last comment? */
			if ( $filter->onlyLastComment )
			{
				/* Set this for when the search JSON is built */
				$this->lastCommentMustBeTrue = true;

				$conditions[] = array(
					'term' => array( 'index_is_last_comment' => true )
				);
			}

			/* Put it together */
			if( \count( $conditions ) )
			{
				$contentFilterConditions[] = Index::convertConditionsToQuery( $conditions );
			}
		}

		/* Put them together */
		if ( \count( $contentFilterConditions ) > 1 )
		{		
			$this->filters[] = array(
				'bool'	=> array(
					( $type ? 'should' : 'must_not' ) => $contentFilterConditions
				)
			);
		}
		elseif ( $type )
		{
			if( \count( $contentFilterConditions ) )
			{
				$this->filters[] = $contentFilterConditions[0];
			}
		}
		else
		{
			if( \count( $contentFilterConditions ) )
			{
				$this->mustNot[] = $contentFilterConditions[0];
			}
		}
		
		return $this;
	}
		
	/**
	 * Filter by author
	 *
	 * @param	\IPS\Member|int|array	$author						The author, or an array of author IDs
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByAuthor( $author )
	{
		if ( \is_array( $author ) )
		{
			$this->filters[] = array( 'terms' => array( 'index_author' => $author ) );
		}
		else
		{
			$this->filters[] = array( 'term' => array( 'index_author' => $author instanceof \IPS\Member ? $author->member_id : $author ) );
		}
		
		return $this;
	}
	
	/**
	 * Filter by club
	 *
	 * @param	\IPS\Member\Club|int|array	$club	The club, or array of club IDs
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByClub( $club )
	{
		if ( $club === NULL )
		{
			$this->mustNot[] = array(
				'exists' => array( 'field' => 'index_club_id' )
			);
		}
		elseif ( \is_array( $club ) )
		{
			$this->filters[] = array(
				'terms' => array( 'index_club_id' => $club )
			);
		}
		else
		{
			$this->filters[] = array(
				'term' => array( 'index_club_id' => $club instanceof \IPS\Member\Club ? $club->id : $club )
			);
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
		/* Filter by content they've posted or posts on their wall */
		$this->filters[] = array(
			'bool' => array(
				'should' => array(
					array(
						'term' => array( 'index_author' => $member->member_id )
					),
					array(
						'bool' => array(
							'filter' => array(
								array(
									'term' => array(
										'index_class' => 'IPS\core\Statuses\Status'
									)
								),
								array(
									'term' => array(
										'index_container_id' => $member->member_id
									)
								)
							)
						)
					)
				)
			)
		);
		
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
		
		/* Return for daisy chaining */
		return $this;
	}

	/**
	 * Stores the "more like this" Content object for search()
	 * @param null
	 */
	protected $moreLikeThis = NULL;

	/**
	 * Filter by more like this
	 * @param \IPS\Content\Searchable $object
	 */
	public function filterByMoreLikeThis( \IPS\Content\Searchable $object )
	{
		$index = new \IPS\Content\Search\Elastic\Index( $this->url );
		$this->moreLikeThis = $index->getIndexId( ( $object instanceof \IPS\Content\Item and $object::$firstCommentRequired ) ? $object->firstComment() : $object );

		/* Some container types cannot be cached */
		$classes = [];
		$conditions = [];
		$noSimilarContentClasses = [];

		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', FALSE ) as $object )
		{
			$classes = array_merge( $object->classes, $classes );

			if ( empty( $object->similarContent ) )
			{
				foreach( $object->classes as $class )
				{
					$noSimilarContentClasses[] = $class;

					if ( is_subclass_of( $class, 'IPS\Content\Item' ) and isset( $class::$commentClass ) )
					{
						$noSimilarContentClasses[] = $class::$commentClass;
					}
				}
			}
		}

		/* Some nodes have complex permissions, so avoid any permission issues by not selecting these */
		foreach( $classes as $itemClass )
		{
			if ( isset( $itemClass::$containerNodeClass ) )
			{
				$containerClass = $itemClass::$containerNodeClass;
				$blockIds = [];
				if ( $customNodes = $containerClass::customPermissionNodes() )
				{
					foreach ( $customNodes as $key => $ids )
					{
						if ( $key !== 'count' )
						{
							$blockIds = array_merge( $blockIds, $ids );
						}
					}
				}

				if ( \count( $blockIds ) )
				{
					$conditions[] = array(
						'bool' => array(
							'filter' => array(
								array(
									'terms' => array( 'index_container_class' => [ $containerClass ] )
								),
								array(
									'terms' => array( 'index_container_id' => $blockIds )
								),
							)
						)
					);
				}

				if ( \count( $conditions ) )
				{
					foreach( $conditions as $condition )
					{
						$this->mustNot[] = $condition;
					}
				}
			}
		}

		/* Prevent some things from being in similar content widget */
		if ( \count( $noSimilarContentClasses ) )
		{
			$this->mustNot[] = array(
				'bool' => array(
					'filter' => array(
						array(
							'terms' => array( 'index_class' => $noSimilarContentClasses )
						)
					)
				)
			);
		}

		/* Only show non-hidden and approved items */
		$this->setHiddenFilter( static::HIDDEN_VISIBLE );
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
			$this->filters[] = array(
				'bool'	=> array(
					'must_not' => array(
						'terms' => array( 'index_container_class' => $classes )
					)
				)
			);
		}
		elseif ( $classes )
		{
			$this->filters[] = array(
				'bool'	=> array(
					'should' => array(
						array(
							'bool' => array(
								'must_not' => array(
									'terms' => array( 'index_container_class' => $classes ),
								)
							)
						),
						array(
							'terms' => array( 'index_container_class' => $exclude ),
						)
					)
				)
			);
		}

		 
		return $this;
	}
	
	/**
	 * Filter by item author
	 *
	 * @param	\IPS\Member	$author		The author
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByItemAuthor( \IPS\Member $author )
	{
		$this->filters[] = array(
			'term' => array( 'index_item_author' => $author->member_id )
		);
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
		$conditions = array();
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
			$conditions[] = array(
				'bool' => array(
					'filter' => array(
						array(
							'terms'	=> array( 'index_class' =>  $case[ $area ] )
						),
						array(
							'terms'	=> array( 'index_item_id' =>  $item )
						),
					)
				)
			);
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
			
			$conditions[] = array(
				'bool' => array(
					'filter' => array(
						array(
							'terms'	=> array( 'index_class' =>  $indexClasses )
						),
						array(
							'terms'	=> array( 'index_container_id' =>  $container )
						),
					)
				)
			);
		}
		
		/* Are we including content posted by followed members? */
		if ( $includeMembers and $followed = iterator_to_array( \IPS\Db::i()->select( 'follow_rel_id', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_member_id=?', 'core', 'member', $this->member->member_id ), 'follow_rel_id asc' ) ) )
		{
			$conditions[] = array(
				'terms'	=> array( 'index_author' =>  $followed )
			);			
		}
		
		/* Put it all together */
		if ( \count( $conditions ) )	
		{
			$this->filters[] = array( 'bool' => array( 'should' => $conditions ) );
		}
		else
		{
			$this->filters[] = array( 'match_none' => new \StdClass );
		}

		/* And return */
		return $this;
	}
	
	/**
	 * Filter by content the user has posted in
	 *
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByItemsIPostedIn()
	{
		$this->filters[] = array(
			'term'			=> array(
				'index_participants'	=> $this->member->member_id
			)
		);
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
		$conditions = array();
		$resetTimes = $this->member->markersResetTimes( NULL );
		foreach ( $classes as $class )
		{
			if( is_subclass_of( $class, 'IPS\Content\ReadMarkers' ) )
			{
				$containerClass = ( $class::$containerNodeClass ) ? $class::$containerNodeClass : NULL;
				$classConditions = array();
				
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
				$classConditions[] = array(
					'terms' => array( 'index_class' => $_classes )
				);
				
				/* Get the reset times */
				$classBits = explode( "\\", $class );
				$application = $classBits[1];
				$containerConditions = array();
				$markers = array();
				if ( isset( $resetTimes[ $application ] ) )
				{
					foreach( $resetTimes[ $application ] as $containerId => $timestamp )
					{
						/* Pages has different classes per database, but recorded as 'cms' and the container ID in the marking tables */
						if ( $containerClass and method_exists( $containerClass, 'isValidContainerId' ) )
						{
							if ( ! $containerClass::isValidContainerId( $containerId ) )
							{
								continue;
							}
						}
						
						/* Add a condition to exlude anything in this container since the last time we marked the whole thing read */
						$timestamp = $timestamp ?: $this->member->marked_site_read;
						$containerConditions[ $containerId ] = array(
							'bool' => array(
								'filter' => array(
									array(
										'term' => array( 'index_container_id' => $containerId )
									),
									array(
										'range' => array( 'index_date_updated' => array( 'gt' => $timestamp ) )
									)
								)
							)
						);
						
						/* And get the times each individual item was read for later */
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
				if ( $containerConditions )
				{
					$containerConditions[] = array(
						'bool' => array(
							'must_not' => array(
								'terms' => array( 'index_container_id' => array_keys( $containerConditions ) )
							),
							'filter' => array(
								'range' => array( 'index_date_updated' => array( 'gt' => $this->member->marked_site_read ) )
							)
						)
					);
					
					$classConditions[] = array(
						'bool' => array(
							'should' => array_values( $containerConditions )
						)
					);
				}
				else
				{
					$classConditions[] = array(
						'range' => array( 'index_date_updated' => array( 'gt' => $this->member->marked_site_read ) )
					);
				}
				
				$notIn  = array();
				if ( \count( $markers ) )
				{
					$useIds = array_flip( $markers );
					
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
				}
				
				/* Add it to the array */
				$_condition = array(
					'bool' => array(
						'filter' => $classConditions
					)
				);
				if ( \count( $notIn ) )
				{
					$_condition['bool']['must_not'] = array(
						'terms' => array(
							'index_item_id' => $notIn
						)
					);
				}
				$conditions[] = $_condition;
			}
		}		
		
		/* Put it all together */
		if ( \count( $conditions ) )
		{
			$this->filters[] = array(
				'bool' => array(
					'should' => $conditions
				)
			);
		}
						
		return $this;
	}

	/**
	 * Filter by solved
	 *
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterBySolved()
	{
		$this->filters[] = array(
			'term' => array( 'index_item_solved' => 1 )
		);

		return $this;
	}

	/**
	 * Filter by Unsolved
	 *
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByUnsolved()
	{
		$this->filters[] = array(
			'term' => array( 'index_item_solved' => 0 )
		);

		return $this;
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
		$range = array();
		
		if ( $start )
		{
			$range['gt'] = $start->getTimestamp();
		}
		if ( $end )
		{
			$range['lt'] = $end->getTimestamp();
		}
		
		if ( $range )
		{
			$this->filters[] = array(
				'range' => array( 'index_date_created' => $range )
			);
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
		$range = array();
		
		if ( $start )
		{
			$range['gt'] = $start->getTimestamp();
		}
		if ( $end )
		{
			$range['lt'] = $end->getTimestamp();
		}
		
		if ( $range )
		{
			$this->filters[] = array(
				'range' => array( 'index_date_updated' => $range )
			);
		}
		
		return $this;
	}
	
	/**
	 * Set hidden status
	 *
	 * @param	int|array|NULL	$statuses	The statuses (see HIDDEN_ constants) or NULL for any
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function setHiddenFilter( $statuses )
	{
		$this->hiddenStatuses = $statuses;
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
				$this->sort = array( array( 'index_date_updated' => 'desc' ) );
				break;
				
			case static::ORDER_OLDEST_UPDATED:
				$this->sort = array( array( 'index_date_updated' => 'asc' ) );
				break;
			
			case static::ORDER_NEWEST_CREATED:
				$this->sort = array( array( 'index_date_created' => 'desc' ) );
				break;
				
			case static::ORDER_OLDEST_CREATED:
				$this->sort = array( array( 'index_date_created' => 'asc' ) );
				break;
				
			case static::ORDER_NEWEST_COMMENTED:
				$this->sort = array( array( 'index_date_commented' => 'desc' ) );
				break;

			case static::ORDER_RELEVANCY:
				$this->sort = NULL;
				break;
		}
		
		return $this;
	}



	/**
	 * Debug by itemId, used for debugging purposes only
	 * @note No permission checks run, do not use in production
	 * @param $itemId
	 * @return void
	 */
	public function debugByItemId( $itemId )
	{
		$array = array(
			'query'	=> [
				'term'	=> [
					'index_item_id' => [
						'value'	=> \intval( $itemId )
					]
				]
			],
			'sort'	=> $this->sort ?: array(),
			'from'	=> 0,
			'size'	=> 50,
		);

		$json = json_encode( $array, JSON_PARTIAL_OUTPUT_ON_ERROR );

		return \IPS\Content\Search\Elastic\Index::request( $this->url->setPath( $this->url->data[ \IPS\Http\Url::COMPONENT_PATH ] . '/_search' ) )->setHeaders( array( 'Content-Type' => 'application/json' ) )->get( $json )->decodeJson();
	}

	/**
	 * Search
	 *
	 * @param	string|null	$term		The term to search for
	 * @param	array|null	$tags		The tags to search for
	 * @param	int			$method 	See \IPS\Content\Search\Query::TERM_* contants
	 * @param	string|null	$operator	If $term contains more than one word, determines if searching for both ("and") or any ("or") of those terms. NULL will go to admin-defined setting
	 * @return	\IPS\Content\Search\Results
	 */
	public function search( $term = NULL, $tags = NULL, $method = 1, $operator = NULL )
	{
		/* If we're looking for more results than we can fetch, we don't need to ask Elastic Search */
		if( ( $this->offset + $this->resultsToGet ) > \IPS\Settings::i()->search_index_maxresults )
		{
			return new \IPS\Content\Search\Results( array(), 0 );
		}

		$operator = $operator ?: \IPS\Settings::i()->search_default_operator;
		$must = array();
		$filters = $this->filters;
		
		/* Set our conditions for this search */
		if ( $term !== NULL or $tags !== NULL )
		{
			$searchConditions = array();
			$titleField = \IPS\Settings::i()->search_title_boost ? ( 'index_title^' . \intval( \IPS\Settings::i()->search_title_boost ) ) : 'index_title';
			
			/* Build the condition for the search term */
			if ( $term !== NULL )
			{
				/* If term is in "quotes" handle it as a phrase */
				if ( static::termIsPhrase( $term ) )
				{
					$term = trim( $term, '"' );
					if ( $method & static::TERM_TITLES_ONLY )
					{
						$searchConditions[] = array( 'match_phrase' => array( 'index_title' => array( 'query' => $term ) ) );
					}
					else
					{
						$searchConditions[] = array( 'multi_match' => array( 'query' => $term, 'fields' => array( 'index_content', $titleField ), 'type' => 'phrase' ) );
					}
				}
				/* If term contains * wildcard, but doesn't start with it (from elasticsearch docs: "In order to prevent extremely slow wildcard queries, a wildcard term should not start with one of the wildcards * or ?"), handle it as a wildcard search - NOTE this will not use the analyzer */
				elseif ( preg_match( '/^[^\s\*].*\*.*$/', $term ) )
				{
					if ( $method & static::TERM_TITLES_ONLY )
					{
						$searchConditions[] = array( 'wildcard' => array( 'index_title' => array( 'value' => $term ) ) );
					}
					else
					{
						$searchConditions[] = array(
							'bool' => array(
								'should' => array(
									array(
										'wildcard' => array( 'index_title' => ( \IPS\Settings::i()->search_title_boost ? array( 'value' => $term, 'boost' => \intval( \IPS\Settings::i()->search_title_boost ) ) : array( 'value' => $term ) ) )
									),
									array(
										'wildcard'	=> array( 'index_content' => array( 'value' => $term ) )
									)
								)
							)
						);
					}
				}
				/* Otherwise just do it as a match search */
				else
				{
					if ( $method & static::TERM_TITLES_ONLY )
					{
						$searchConditions[] = array( 'match' => array( 'index_title' => array( 'query' => $term, 'operator' => $operator ) ) );
					}
					else
					{
						$searchConditions[] = array( 'multi_match' => array( 'query' => $term, 'fields' => array( 'index_content', $titleField ), 'operator' => $operator ) );
					}
				}
			}
			/* Build the condition for the tags */
			if ( $tags !== NULL )
			{
				$searchConditions[] = array(
					'bool' => array(
						'should' => array(
							array(
								'terms' => array( 'index_tags' => $tags )
							),
							array(
								'terms'	=> array( 'index_prefix' => $tags )
							)
						)
					)
				);

				/* If we're not searching with a term, then just show the title record, not comments */
				if ( $term === NULL and ! $this->lastCommentMustBeTrue )
				{
					$must[] = ['exists' => ['field' => 'index_title']];
				}
			}
			
			/* Put that with the rest of the conditions */
			if ( $term !== NULL and $tags !== NULL )
			{
				if ( $method & static::TERM_OR_TAGS )
				{
					$must[] = array( 'bool' => array( 'should' => $searchConditions ) );
				}
				else
				{
					$must = $searchConditions;
				}
			}
			else
			{
				$must[] = $searchConditions[0];
			}
		}
		
		/* Only get stuff we have permission for */
		$filters[] = array( 'terms' => array( 'index_permissions' => array_merge( $this->permissionArray(), array( '*' ) ) ) );
		if ( $this->hiddenStatuses !== NULL )
		{
			if ( \is_array( $this->hiddenStatuses ) )
			{
				$filters[] = array( 'terms' => array( 'index_hidden' => $this->hiddenStatuses ) );
			}
			else
			{
				$filters[] = array( 'term' => array( 'index_hidden' => $this->hiddenStatuses ) );
			}
		}

		$searchUrl = $this->url->setPath( $this->url->data[ \IPS\Http\Url::COMPONENT_PATH ] . '/_search' );

		if ( $this->moreLikeThis )
		{
			$must[] = [
				'more_like_this' => [
					'fields' => [ 'index_title' ],
					'like' => [ '_index' => \IPS\Settings::i()->search_elastic_index, '_id' => $this->moreLikeThis ],
					'min_term_freq' => 1,
					'max_query_terms' => 12,
					'min_word_length' => 3,
				]
			];

			/* Let's enforce a relatively short timeout to prevent slow ES server from dragging down topics */
			$searchUrl = $searchUrl->setQueryString( 'timeout', '3s' );
		}

		/* Peform the search */
		try
		{
			/* Initial query */
			$query = array(
				'bool'	=> array(
					'must'		=> $must,
					'must_not'	=> $this->mustNot,
					'filter'	=> $filters
				)
			);

			/* Add the time decay */
			if ( \IPS\Settings::i()->search_decay_factor and !$this->sort and ! $this->sort )
			{
				$query = array(
					'function_score' => array(
						'query'			=> $query,
						'linear'			=> array(
							'index_date_updated' => array(
								'scale'				=> \intval( \IPS\Settings::i()->search_decay_days ) . 'd',
								'decay'				=> number_format( \IPS\Settings::i()->search_decay_factor, 1, '.', '' ),
								'origin'			=> time(),
							)
						)
					)
				);
			}
			
			/* Add the self boost */
			if ( \IPS\Settings::i()->search_elastic_self_boost and $this->member->member_id and !$this->sort and ! $this->moreLikeThis )
			{
				$query = array(
					'function_score' => array(
						'query'			=> $query,
						'script_score'		=> array(
							'script'			=> array(
								'source'			=> "doc['index_author'].value == params.param_memberId ? ( _score * Float.parseFloat( params.param_booster ) ) : _score",
								'lang'				=> 'painless',
								'params'			=> array(
									'param_memberId'	=> \intval( $this->member->member_id ),
									'param_booster'		=> number_format( \IPS\Settings::i()->search_elastic_self_boost, 1, '.', '' )
								)
							)
						)
					)
				);
			}
			
			/* Build the JSON and validate it. Use JSON_PARTIAL_OUTPUT_ON_ERROR in case someone has used an unencodable value as
				the term, and check json_encode() didn't return FALSE (which it may do on error) as a sanity check against
				sending a blank query, which would return everything in the index */
			$array = array(
				'query'	=> $query,
				'sort'	=> $this->sort ?: array(),
				'from'	=> $this->offset,
				'size'	=> $this->resultsToGet,
			);

			$json = json_encode( $array, JSON_PARTIAL_OUTPUT_ON_ERROR );
			if ( $json === FALSE )
			{				
				return new \IPS\Content\Search\Results( array(), 0 );
			}

			/* Make the call! */
			$return = \IPS\Content\Search\Elastic\Index::request( $searchUrl )->setHeaders( array( 'Content-Type' => 'application/json' ) )->get( $json )->decodeJson();
			if ( isset( $return['error'] ) )
			{
				\IPS\Log::log( print_r( array_merge( $array, ['error' => $return['error'] ] ), TRUE ), 'elasticsearch' );
				return new \IPS\Content\Search\Results( array(), 0 );
			}

			/* Set results */
			$total = $return['hits']['total']['value'] ?? $return['hits']['total'];
			return new \IPS\Content\Search\Results( array_map( function( $hit ) {
				$indexData = $hit['_source'];
				$indexData['index_permissions'] = implode( ',', $indexData['index_permissions'] );
				$indexData['index_tags'] = $indexData['index_tags'] ? implode( ',', $indexData['index_tags'] ) : NULL;
				return $indexData;
			}, $return['hits']['hits'] ), $total <= \IPS\Settings::i()->search_index_maxresults ? $total : (int) \IPS\Settings::i()->search_index_maxresults );
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'elasticsearch' );
			return new \IPS\Content\Search\Results( array(), 0 );
		}
	}
}