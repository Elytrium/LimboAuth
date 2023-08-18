<?php
/**
 * @brief		MySQL Search Index
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
 * MySQL Search Index
 */
class _Index extends \IPS\Content\Search\Index
{
	/**
	 * Get index data
	 *
	 * @param	\IPS\Content\Searchable	$object	Item to add
	 * @return	array|NULL
	 */
	public function indexData( \IPS\Content\Searchable $object )
	{
		$indexData = parent::indexData( $object );

		if( $indexData === NULL )
		{
			return $indexData;
		}

		/* The index_title in core_search_index is varchar(255) so we have to limit to this length */
		if( $indexData['index_title'] !== NULL )
		{
			$indexData['index_title'] = mb_substr( $indexData['index_title'], 0, 255 );
		}

		return $indexData;
	}

	/**
	 * Index an item
	 *
	 * @param	\IPS\Content\Searchable	$object	Item to add
	 * @return	void
	 */
	public function index( \IPS\Content\Searchable $object )
	{
		/* Get the index data */
		$indexData = $this->indexData( $object );
		$class = \get_class( $object );

		/* If we got the data... */
		if( $indexData )
		{
			/* If nobody has permission to access it, just remove it */
			if ( !$indexData['index_permissions'] )
			{
				$this->removeFromSearchIndex( $object );
			}
			/* Otherwise, go ahead... */
			else
			{
				$existingData		= NULL;
				$existingIndexId	= NULL;
				$resetLastComment	= FALSE;
				$newIndexId			= NULL;
				
				try
				{
					$existingData = \IPS\Db::i()->select( 'index_id, index_class, index_object_id, index_item_id, index_hidden, index_is_last_comment, index_author', 'core_search_index', array( 'index_class=? AND index_object_id=?', $indexData['index_class'], $indexData['index_object_id'] ) )->first();
					$existingIndexId = $existingData['index_id'];
				}
				catch( \Exception $e ) { }
				
				/* Adjust tags */
				$tags = NULL;
				if ( array_key_exists( 'index_tags', $indexData ) )
				{
					$tags = array_filter( array_merge ( array( $indexData['index_prefix'] ), explode( ',', $indexData['index_tags'] ) ) );
					$prefix = $indexData['index_prefix'];
					unset( $indexData['index_tags'] );
					unset( $indexData['index_prefix'] );
				}
				
				if ( $object instanceof \IPS\Content\Comment and $existingIndexId and $existingData['index_is_last_comment'] and $indexData['index_is_last_comment'] and $indexData['index_item_id'] and $indexData['index_hidden'] !== 0 )
				{
					/* We do not allow hidden or needing approval comments to become flagged as the last comment as this means users without hidden view permission never see the item in an item only stream */
					$indexData['index_is_last_comment'] = 0;
					
					$resetLastComment = TRUE;
				}
				else if ( $indexData['index_is_last_comment'] and $indexData['index_item_id'] )
				{
					$classes = array( $class );
					
					/* If this is the latest comment, unflag what was set before on both item and comment */
					if ( $object instanceof \IPS\Content\Comment )
					{
						$itemClass = $object::$itemClass;
						if ( ! $itemClass::$firstCommentRequired )
						{
							$classes[] = $itemClass;
						}
						
						if ( isset( $itemClass::$reviewClass ) )
						{
							$classes[] = $itemClass::$reviewClass;
						}
					}
					else if ( $object instanceof \IPS\Content\Item )
					{
						if ( isset( $class::$commentClass ) )
						{
							$classes[] = $class::$commentClass;
						}
						if ( isset( $class::$reviewClass ) )
						{
							$classes[] = $class::$reviewClass;
						}
					}
					
					\IPS\Db::i()->update( 'core_search_index', array( 'index_is_last_comment' => 0 ), array( \IPS\Db::i()->in( 'index_class', $classes ) . ' AND index_item_id=? AND index_is_last_comment=1', $indexData['index_item_id'] ) );
				}
				
				if ( $existingData !== NULL and ( $indexData['index_class'] == $existingData['index_class'] and $indexData['index_object_id'] == $existingData['index_object_id'] ) )
				{
					\IPS\Db::i()->update( 'core_search_index', $indexData, array( 'index_class=? and index_object_id=?', $indexData['index_class'], $indexData['index_object_id'] ) );
					$newIndexId = $existingIndexId;
				}
				else
				{
					if ( $existingData !== NULL )
					{
						\IPS\Db::i()->delete( 'core_search_index', array( 'index_class=? and index_object_id=?', $indexData['index_class'], $indexData['index_object_id'] ) );
					}
					
					try
					{
						$newIndexId = \IPS\Db::i()->insert( 'core_search_index', $indexData );
					}
					catch( \IPS\Db\Exception $e )
					{
						if ( $e->getCode() == 1062 )
						{
							/* Duplicate key which could be caused by a race condition on rebuild. Use replace in this case, as it is more expensive than an insert, so we only use it when we have to */
							$newIndexId = \IPS\Db::i()->replace( 'core_search_index', $indexData );
						}
					}
				}
				
				/* If that was successful... */
				if ( $newIndexId )
				{
					/* Remove existing tags */
					if ( $existingIndexId )
					{
						\IPS\Db::i()->delete( 'core_search_index_tags', array( 'index_id=?', $existingIndexId ) );
					}
					
					/* Add add them back if we have any */
					if ( \count( $tags ) )
					{
						foreach( $tags as $tag )
						{
							\IPS\Db::i()->replace( 'core_search_index_tags', array( 'index_id' => $newIndexId, 'index_tag' => $tag, 'index_is_prefix' => ( $tag == $prefix ) ) );
						}
					}
					
					/* Populate the map table, we always populate it under the item class regardless */
					if ( $existingData == NULL or ( $existingData !== NULL and ( $existingData['index_author'] != $indexData['index_author'] OR $existingData['index_item_id'] != $indexData['index_item_id'] ) ) )
					{
						\IPS\Db::i()->replace( 'core_search_index_item_map', array( 'index_author_id' => $indexData['index_author'], 'index_item_id' => $indexData['index_item_id'], 'index_class' => ( $object instanceof \IPS\Content\Comment ? $object::$itemClass : $class ) ) );
					}

					$databaseColumnId = $object::$databaseColumnId;
					
					/* Set index_item_index_id on other index items */
					if ( $existingIndexId != $newIndexId )
					{
						if ( $object instanceof \IPS\Content\Item )
						{
							$subClasses = array( $class );
							if ( isset( $class::$commentClass ) )
							{
								$subClasses[] = $class::$commentClass;
							}
							if ( isset( $class::$reviewClass ) )
							{
								$subClasses[] = $class::$reviewClass;
							}
							
							\IPS\Db::i()->update( 'core_search_index', array( 'index_item_index_id' => $newIndexId ), array( array( \IPS\Db::i()->in( 'index_class', $subClasses ) ), array( 'index_item_id=?', $object->$databaseColumnId ) ) );
						}
						elseif ( $object instanceof \IPS\Content\Comment )
						{
							$itemClass = $object::$itemClass;
							if ( $itemClass::$firstCommentRequired and $object->isFirst() )
							{						
								$itemColumnId = $class::$databaseColumnMap['item'];
								\IPS\Db::i()->update( 'core_search_index', array( 'index_item_index_id' => $newIndexId ), array( \IPS\Db::i()->in( 'index_class', array( $class, $class::$itemClass ) ) . ' AND index_item_id=?', $object->$itemColumnId ) );
							}
						}
					}
				}
				
				if ( $resetLastComment )
				{
					$this->resetLastComment( array( $indexData['index_class'] ), $indexData['index_item_id'] );
				}
			}
		}
	}
	
	/**
	 * Retrieve the search ID for an item
	 *
	 * @param	\IPS\Content\Searchable	$object	Item to add
	 * @return	void
	 */
	public function getIndexId( \IPS\Content\Searchable $object )
	{
		$databaseColumnId = $object::$databaseColumnId;
		return \IPS\Db::i()->select( 'index_id', 'core_search_index', array( 'index_class=? AND index_object_id=?', \get_class( $object ),$object->$databaseColumnId ) )->first();
	}
	
	/**
	 * Remove item
	 *
	 * @param	\IPS\Content\Searchable	$object	Item to remove
	 * @return	void
	 */
	public function removeFromSearchIndex( \IPS\Content\Searchable $object )
	{
		$class = \get_class( $object );
		$idColumn = $class::$databaseColumnId;

		/* Tags */
		$this->_deleteTagsFromIndex( $class, $object->$idColumn );
		
		\IPS\Db::i()->delete( 'core_search_index', array( 'index_class=? AND index_object_id=?', $class, $object->$idColumn ) );
	
		/* If this was a comment, we really need to reset the index_is_last_comment flag if it was set */
		if ( $object instanceof \IPS\Content\Comment )
		{
			$itemClass = $object::$itemClass;
			$classes = array( $class );
			
			if ( ! $itemClass::$firstCommentRequired )
			{
				$classes[] = $itemClass;
			}
			
			if ( isset( $itemClass::$reviewClass ) )
			{
				$classes[] = $itemClass::$reviewClass;
			}
				
			try
			{
				$this->resetLastComment( $classes, $object->mapped('item'), $object->$idColumn );
			}
			catch( \Exception $ex ) { }
			
			/* We need to see if this is the only comment the author has in this item and if so, remove their map */
			if ( ! \IPS\Db::i()->select( 'COUNT(*)', 'core_search_index', array( \IPS\Db::i()->in('index_class', $classes ) . ' and index_item_id=? and index_author=?', $object->mapped('item'), (int) $object->mapped('author') ) )->first() )
			{
				try
				{
					\IPS\Db::i()->delete( 'core_search_index_item_map', array( 'index_class=? AND index_item_id=? and index_author_id=?', $itemClass, $object->mapped('item'), (int) $object->mapped('author') ) );
				}
				catch( \Exception $ex ) { }
			}
		}
		else if ( $object instanceof \IPS\Content\Item )
		{
			/* Just remove all rows matching the item and class. */
			\IPS\Db::i()->delete( 'core_search_index_item_map', array( 'index_class=? AND index_item_id=?', $class, $object->$idColumn ) );
		}
		
		if ( isset( $class::$commentClass ) )
		{
			$commentClass = $class::$commentClass;
			$this->_deleteTagsFromIndex( $commentClass, $object->$idColumn );
			\IPS\Db::i()->delete( 'core_search_index', array( 'index_class=? AND index_item_id=?', $commentClass, $object->$idColumn ) );
		}
		
		if ( isset( $class::$reviewClass ) )
		{
			$reviewClass = $class::$reviewClass;
			$this->_deleteTagsFromIndex( $reviewClass, $object->$idColumn );
			\IPS\Db::i()->delete( 'core_search_index', array( 'index_class=? AND index_item_id=?', $reviewClass, $object->$idColumn ) );
		}
	}

	/**
	 * Direct removal from the search index - only used when we don't need to perform ancillary cleanup (i.e. orphaned data)
	 *
	 * @param	string	$class	Class
	 * @param	int		$id		ID
	 * @return	void
	 */
	public function directIndexRemoval( $class, $id )
	{
		/* Tags */
		$this->_deleteTagsFromIndex( $class, $id );
		
		\IPS\Db::i()->delete( 'core_search_index', array( 'index_class=? AND index_object_id=?', $class, $id ) );
	
		/* If this was a comment, we really need to reset the index_is_last_comment flag if it was set */
		if ( is_subclass_of( $class, 'IPS\Content\Item' ) )
		{
			\IPS\Db::i()->delete( 'core_search_index_item_map', array( 'index_class=? AND index_item_id=?', $class, $id ) );
		}
		
		if ( isset( $class::$commentClass ) )
		{
			$commentClass = $class::$commentClass;
			$this->_deleteTagsFromIndex( $commentClass, $id );
			\IPS\Db::i()->delete( 'core_search_index', array( 'index_class=? AND index_item_id=?', $commentClass, $id ) );
		}
		
		if ( isset( $class::$reviewClass ) )
		{
			$reviewClass = $class::$reviewClass;
			$this->_deleteTagsFromIndex( $reviewClass, $id );
			\IPS\Db::i()->delete( 'core_search_index', array( 'index_class=? AND index_item_id=?', $reviewClass, $id ) );
		}
	}
	
	/**
	 * Return the index IDs associated with this class and $id
	 *
	 * @param	\IPS\Content object		$class 	The class
	 * @param	int						$id		The index_item_id
	 * @return array
	 */
	protected function _deleteTagsFromIndex( $class, $id )
	{
		try
		{
			$ids = iterator_to_array( \IPS\Db::i()->select( 'index_id', 'core_search_index', array( 'index_class=? AND index_item_id=?', $class, $id ) ) );
		}
		catch( \Exception $ex )
		{
			$ids = FALSE;
		}
		
		if ( \is_array( $ids ) and \count( $ids ) < 1000 )
		{
			\IPS\Db::i()->delete( 'core_search_index_tags', array( \IPS\Db::i()->in( 'index_id', $ids ) ) );
		}
		else
		{
			\IPS\Db::i()->delete( 'core_search_index_tags', array( 'index_id IN( ? )', \IPS\Db::i()->select( 'index_id', 'core_search_index', array( 'index_class=? AND index_item_id=?', $class, $id ) ) ) );
		}
	}

	/**
	 * Removes all content for a specific application from the index (for example, when uninstalling).
	 *
	 * @param	\IPS\Application	$application The application
	 * @return	void
	 */
	public function removeApplicationContent( \IPS\Application $application )
	{
		$classes = array();

		foreach ( $application->extensions( 'core', 'ContentRouter' ) as $router )
		{
			foreach( $router->classes AS $class )
			{
				if ( is_subclass_of( $class, 'IPS\Content\Searchable' ) )
				{
					$classes[] = $class;
					
					if ( isset( $class::$commentClass ) )
					{
						$commentClass = $class::$commentClass;
						if ( is_subclass_of( $commentClass, 'IPS\Content\Searchable' ) )
						{
							$classes[] = $commentClass;
						}
					}
					
					if ( isset( $class::$reviewClass ) )
					{
						$reviewClass = $class::$reviewClass;
						if ( is_subclass_of( $reviewClass, 'IPS\Content\Searchable' ) )
						{
							$classes[] = $reviewClass;
						}
					}
				}
			}
		}

		$where = array( \IPS\Db::i()->in( 'index_class', $classes ) );

		\IPS\Db::i()->delete( 'core_search_index_item_map', $where );
		\IPS\Db::i()->delete( 'core_search_index_tags', array( 'index_id IN( ? )', \IPS\Db::i()->select( 'index_id', 'core_search_index', $where ) ) );
		\IPS\Db::i()->delete( 'core_search_index', $where );
	}
	
	/**
	 * Removes all content for a classs
	 *
	 * @param	string		$class 	The class
	 * @param	int|NULL	$containerId		The container ID to delete, or NULL
	 * @param	int|NULL	$authorId			The author ID to delete, or NULL
	 * @return	void
	 */
	public function removeClassFromSearchIndex( $class, $containerId=NULL, $authorId=NULL )
	{
		$where = array( array( 'index_class=?', $class ) );
		if ( $containerId !== NULL )
		{
			$where[] = array( 'index_container_id=?', $containerId );
		}
		if ( $authorId !== NULL )
		{
			$where[] = array( 'index_author=?', $authorId );
		}
		
		\IPS\Db::i()->delete( 'core_search_index_item_map', array( 'index_class=? and index_item_id IN( ? )', $class, \IPS\Db::i()->select( 'index_item_id', 'core_search_index', $where ) ) );
		\IPS\Db::i()->delete( 'core_search_index_tags', array( 'index_id IN( ? )', \IPS\Db::i()->select( 'index_id', 'core_search_index', $where ) ) );
		\IPS\Db::i()->delete( 'core_search_index', $where );
	}
	
	/**
	 * Mass Update (when permissions change, for example)
	 *
	 * @param	string				$class 						The class
	 * @param	int|NULL			$containerId				The container ID to update, or NULL
	 * @param	int|NULL			$itemId						The item ID to update, or NULL
	 * @param	string|NULL			$newPermissions				New permissions (if applicable)
	 * @param	int|NULL			$newHiddenStatus			New hidden status (if applicable) special value 2 can be used to indicate hidden only by parent
	 * @param	int|NULL			$newContainer				New container ID (if applicable)
	 * @param	int|NULL			$authorId					The author ID to update, or NULL
	 * @param	int|NULL			$newItemId					The new item ID (if applicable)
	 * @param	int|NULL			$newItemAuthorId			The new item author ID (if applicable)
	 * @param	bool				$addAuthorToPermissions		If true, the index_author_id will be added to $newPermissions - used when changing the permissions for a node which allows access only to author's items
	 * @return	void
	 */
	public function massUpdate( $class, $containerId = NULL, $itemId = NULL, $newPermissions = NULL, $newHiddenStatus = NULL, $newContainer = NULL, $authorId = NULL, $newItemId = NULL, $newItemAuthorId = NULL, $addAuthorToPermissions = FALSE )
	{
		$where = array( array( 'index_class=?', $class ) );
		if ( $containerId !== NULL )
		{
			$where[] = array( 'index_container_id=?', $containerId );
		}
		if ( $itemId !== NULL )
		{
			$where[] = array( 'index_item_id=?', $itemId );
		}
		if ( $authorId !== NULL )
		{
			$where[] = array( 'index_item_author=?', $authorId );
		}

		$update = array();
		if ( $newPermissions !== NULL )
		{
			$update['index_permissions'] = $newPermissions;
		}
		if ( $newContainer )
		{
			$update['index_container_id'] = $newContainer;
			
			if ( $itemClass = ( \in_array( 'IPS\Content\Item', class_parents( $class ) ) ? $class : $class::$itemClass ) and $containerClass = $itemClass::$containerNodeClass and \IPS\IPS::classUsesTrait( $containerClass, 'IPS\Content\ClubContainer' ) and $clubIdColumn = $containerClass::clubIdColumn() )
			{
				try
				{
					$update['index_club_id'] = $containerClass::load( $newContainer )->$clubIdColumn;
				}
				catch ( \OutOfRangeException $e )
				{
					$update['index_club_id'] = NULL;
				}
			}
		}
		if ( $newItemId )
		{
			$update['index_item_id'] = $newItemId;
		}
		if ( $newItemAuthorId )
		{
			$update['index_item_author'] = $newItemAuthorId;
		}
		
		if ( \count( $update ) )
		{
			\IPS\Db::i()->update( 'core_search_index', $update, $where );
		}
		if ( $addAuthorToPermissions )
		{
			$addAuthorToPermissionsWhere = $where;
			$addAuthorToPermissionsWhere[] = array( 'index_author<>0' );
			\IPS\Db::i()->update( 'core_search_index', "index_permissions = CONCAT( index_permissions, ',m', index_author )", $addAuthorToPermissionsWhere );
		}
		
		if ( $newHiddenStatus !== NULL )
		{
			if ( $newHiddenStatus === 2 )
			{
				$where[] = array( 'index_hidden=0' );
			}
			else
			{
				$where[] = array( 'index_hidden=2' );
			}
			
			\IPS\Db::i()->update( 'core_search_index', array( 'index_hidden' => $newHiddenStatus ), $where );
		}
	}
	
	/**
	 * Update data for the first and last comment after a merge
	 * Sets index_is_last_comment on the last comment, and, if this is an item where the first comment is indexed rather than the item, sets index_title and index_tags on the first comment
	 *
	 * @param	\IPS\Content\Item	$item	The item
	 * @return	void
	 */
	public function rebuildAfterMerge( \IPS\Content\Item $item )
	{
		if ( $item::$commentClass )
		{
			$firstComment = $item->comments( 1, 0, 'date', 'asc', NULL, FALSE, NULL, NULL, TRUE );
			$lastComment = $item->comments( 1, 0, 'date', 'desc', NULL, FALSE, NULL, NULL, TRUE );
			
			$idColumn = $item::$databaseColumnId;
			$update = array( 'index_is_last_comment' => 0 );
			if ( $item::$firstCommentRequired )
			{
				$update['index_title'] = NULL;
			}
			\IPS\Db::i()->update( 'core_search_index', $update, array( 'index_class=? AND index_item_id=?', $item::$commentClass, $item->$idColumn ) );
	
			if ( $firstComment )
			{
				$this->index( $firstComment );
			}
			if ( $lastComment )
			{
				$this->index( $lastComment );
			}
		}
	}
	
	/**
	 * Prune search index
	 *
	 * @param	\IPS\DateTime|NULL	$cutoff	The date to delete index records from, or NULL to delete all
	 * @return	void
	 */
	public function prune( \IPS\DateTime $cutoff = NULL )
	{
		if ( $cutoff )
		{
			\IPS\Db::i()->delete( array( 'core_search_index_item_map', 'core_search_index' ), array( "core_search_index_item_map.index_item_id=core_search_index.index_item_id AND (core_search_index.index_id=core_search_index.index_item_index_id AND index_date_updated < ?)", $cutoff->getTimestamp() ), NULL, NULL, NULL, 'core_search_index_item_map' );
			\IPS\Db::i()->delete( array( 'core_search_index_tags', 'core_search_index' ), array( "core_search_index_tags.index_id=core_search_index.index_id AND (core_search_index.index_id=core_search_index.index_item_index_id AND index_date_updated < ?)", $cutoff->getTimestamp() ), NULL, NULL, NULL, 'core_search_index_tags' );
			\IPS\Db::i()->delete( 'core_search_index', array( 'index_date_updated < ?', $cutoff->getTimestamp() ) );
		}
		else
		{
			\IPS\Db::i()->delete( 'core_search_index_item_map' );
			\IPS\Db::i()->delete( 'core_search_index_tags' );
			\IPS\Db::i()->delete( 'core_search_index' );
		}
	}
	
	/**
	 * Reset the last comment flag in any given class/index_item_id
	 *
	 * @param	array				$classes					The classes (when first post is required, this is typically just \IPS\forums\Topic\Post but for others, it will be both item and comment classes)
	 * @param	int|NULL			$indexItemId				The index item ID
	 * @param	int|NULL			$ignoreId					ID to ignore because it is being removed
	 * @return 	void
	 */
	public function resetLastComment( $classes, $indexItemId, $ignoreId = NULL )
	{
		\IPS\Db::i()->update( 'core_search_index', array( 'index_is_last_comment' => 0 ), array( \IPS\Db::i()->in( 'index_class', $classes ) . ' AND index_item_id=? AND index_is_last_comment=1', $indexItemId ) );
		try
		{
			$latest = \IPS\Db::i()->select( 'index_object_id, index_date_updated, index_class', 'core_search_index', array( \IPS\Db::i()->in( 'index_class', $classes ) . ' AND index_item_id=? and index_hidden=0', $indexItemId ), 'index_date_created DESC', array( 0, 1 ) )->first();
		}
		catch( \Exception )
		{
			/* Didn't find a latest? Was it because the entire thing is hidden? */
			try
			{
				$index = \IPS\Db::i()->select( 'index_id, index_item_index_id, index_hidden', 'core_search_index', array( \IPS\Db::i()->in( 'index_class', $classes ) . ' AND index_item_id=?', $indexItemId ), 'index_date_created DESC', array( 0, 1 ) )->first();

				/* Is the root item hidden? */
				if ( $index['index_id'] == $index['index_item_index_id'] and in_array( $index['index_hidden'], [ -1, 2 ] ) )
				{
					$latest = \IPS\Db::i()->select( 'index_object_id, index_date_updated, index_class, index_date_commented', 'core_search_index', array( \IPS\Db::i()->in( 'index_class', $classes ) . ' AND index_item_id=? and index_hidden=?', $indexItemId, $index['index_hidden'] ), 'index_date_created DESC', array( 0, 1 ) )->first();
				}
				else
				{
					/* No, so we can't find a latest item that is hidden, so skip this */
					return FALSE;
				}
			}
			catch( \Exception )
			{
				return FALSE;
			}
		}

		\IPS\Db::i()->update( 'core_search_index', array( 'index_is_last_comment' => 1 ), array( 'index_class=? AND index_object_id=?', $latest['index_class'], $latest['index_object_id'] ) );

		/* Now reset the item index with the latest comment time */
		\IPS\Db::i()->update( 'core_search_index', array( 'index_date_updated' => $latest['index_date_updated'], 'index_date_commented' => $latest['index_date_commented'] ), array( \IPS\Db::i()->in( 'index_class', $classes ) . ' AND index_item_id=? AND index_object_id=?', $indexItemId, $indexItemId ) );

	}
	
	/**
	 * Given a list of item index IDs, return the ones that a given member has participated in
	 *
	 * @param	array		$itemIndexIds	Item index IDs
	 * @param	\IPS\Member	$member			The member
	 * @return 	array
	 */
	public function iPostedIn( array $itemIndexIds, \IPS\Member $member )
	{
		return iterator_to_array( \IPS\Db::i()->select( 'index_item_index_id', 'core_search_index', array( array( \IPS\Db::i()->in( 'index_item_index_id', $itemIndexIds ) ), array( 'index_author=?', $member->member_id ) ) )->setKeyField('index_item_index_id') );
	}
	
	/**
	 * Given a list of "index_class_type_id_hash"s, return the ones that a given member has permission to view
	 *
	 * @param	array		$itemIndexIds	Item index IDs
	 * @param	\IPS\Member	$member			The member
	 * @param	int|NULL		$limit			Number of results to return
	 * @return 	array
	 */
	public function hashesWithPermission( array $hashes, \IPS\Member $member, $limit = NULL )
	{
		return iterator_to_array( \IPS\Db::i()->select( 'index_class_type_id_hash', array( 'core_search_index', 'si' ), array(
			array( "( si.index_permissions = '*' OR " . \IPS\Db::i()->findInSet( 'si.index_permissions', \IPS\Member::loggedIn()->permissionArray() ) . ' AND si.index_hidden=0 )' ),
			array( \IPS\Db::i()->in( 'si.index_class_type_id_hash', $hashes ) )
		), NULL, $limit )->setKeyField('index_class_type_id_hash') );
	}
	
	/**
	 * Get timestamp of oldest thing in index
	 *
	 * @return 	int|null
	 */
	public function firstIndexDate()
	{
		return \IPS\Db::i()->select( 'MIN(index_date_updated)', 'core_search_index' )->first();
	}
}