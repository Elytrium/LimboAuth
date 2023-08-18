<?php
/**
 * @brief		Abstract Search Index
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 Aug 2014
*/

namespace IPS\Content\Search;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Abstract Search Index
 */
abstract class _Index extends \IPS\Patterns\Singleton
{
	/**
	 * @brief	Singleton Instances
	 */
	protected static $instance = NULL;
	
	/**
	 * Get instance
	 *
	 * @param	bool	$skipCache	Do not use the cached instance if one exists
	 * @return	static
	 */
	public static function i( $skipCache=FALSE )
	{
		if( static::$instance === NULL OR $skipCache === TRUE )
		{
			if ( \IPS\Settings::i()->search_method == 'elastic' )
			{
				static::$instance = new \IPS\Content\Search\Elastic\Index( \IPS\Http\Url::external( rtrim( \IPS\Settings::i()->search_elastic_server, '/' ) . '/' . \IPS\Settings::i()->search_elastic_index ) );
			}
			else
			{
				static::$instance = new \IPS\Content\Search\Mysql\Index;
			}
		}
		
		return static::$instance;
	}
	
	/**
	 * Get mass indexer
	 *
	 * @return	static
	 */
	public static function massIndexer()
	{
		if ( \IPS\Settings::i()->search_method == 'elastic' )
		{
			return new \IPS\Content\Search\Elastic\MassIndexer( \IPS\Http\Url::external( rtrim( \IPS\Settings::i()->search_elastic_server, '/' ) . '/' . \IPS\Settings::i()->search_elastic_index ) );
		}
		else
		{
			return static::i();
		}
	}
	
	/**
	 * Initalize when first setting up
	 *
	 * @return	void
	 */
	public function init()
	{
		// Does nothing by default
	}
	
	/**
	 * Clear and rebuild search index
	 *
	 * @return	void
	 */
	public function rebuild()
	{
		/* Delete everything currently in it */
		$this->prune();		
		
		/* If the queue is already running, clear it out */
		\IPS\Db::i()->delete( 'core_queue', array( "`key`=?", 'RebuildSearchIndex' ) );
		
		/* And set the queue in motion to rebuild */
		foreach ( \IPS\Content::routedClasses( FALSE ) as $class )
		{
			try
			{
				if( is_subclass_of( $class, 'IPS\Content\Searchable' ) )
				{
					\IPS\Task::queue( 'core', 'RebuildSearchIndex', array( 'class' => $class ), 5, TRUE );
				}
			}
			catch( \OutOfRangeException $ex ) {}
		}
	}
	
	/**
	 * Index a single items comments and reviews if applicable.
	 *
	 * @param	\IPS\Content\Searchable	$object		The item to index
	 * @return	void
	 */
	public function indexSingleItem( \IPS\Content\Searchable $object )
	{
		/* It is possible for some items to not have a valid URL */
		try
		{
			if( !$url = (string) $object->url() )
			{
				throw new \LogicException;
			}
		}
		catch( \LogicException $e )
		{
			$url = '';
		}

		try
		{
			$idColumn = $object::$databaseColumnId;
			if ( isset( $object::$commentClass ) AND is_subclass_of( $object::$commentClass, 'IPS\Content\Searchable' ) )
			{
				\IPS\Task::queue( 'core', 'IndexSingleItem', array( 'class' => $object::$commentClass, 'id' => $object->$idColumn, 'title' => $object->mapped('title'), 'url' => $url ), 5, TRUE );
			}
			
			if ( isset( $object::$reviewClass ) AND is_subclass_of( $object::$reviewClass, 'IPS\Content\Searchable' ) )
			{
				\IPS\Task::queue( 'core', 'IndexSingleItem', array( 'class' => $object::$reviewClass, 'id' => $object->$idColumn, 'title' => $object->mapped('title'), 'url' => $url ), 5, TRUE );
			}
		}
		catch( \OutOfRangeException $e ) {}
	}
	
	/**
	 * Get index data
	 *
	 * @param	\IPS\Content\Searchable	$object	Item to add
	 * @return	array|NULL
	 */
	public function indexData( \IPS\Content\Searchable $object )
	{
		/* Init */
		$class = \get_class( $object );
		$idColumn = $class::$databaseColumnId;
		$tags = ( $object instanceof \IPS\Content\Tags and \IPS\Settings::i()->tags_enabled ) ? implode( ',', array_filter( $object->tags() ) ) : NULL;
		$prefix = ( $object instanceof \IPS\Content\Tags and \IPS\Settings::i()->tags_enabled ) ? $object->prefix() : NULL;

		/* If this is an item where the first comment is required, don't index because the comment will serve as both */
		if ( $object instanceof \IPS\Content\Item and $class::$firstCommentRequired )
		{
			return NULL;
		}

		/* Don't index if this is an item to be published in the future */
		if ( $object->isFutureDate() )
		{
			return NULL;
		}

		/* Or if this *is* the first comment, add the title and replace the tags */
		$title = $object->searchIndexTitle();
		$isForItem = FALSE;
		if ( $object instanceof \IPS\Content\Comment )
		{
			$itemClass = $class::$itemClass;
			if ( $itemClass::$firstCommentRequired and $object->isFirst() )
			{
				try
				{
					$item = $object->item();
				}
				catch( \OutOfRangeException $ex )
				{
					/* Comment has no working item, return */
					return NULL;
				}

				$title = $item->searchIndexTitle();
				$tags = ( $item instanceof \IPS\Content\Tags and \IPS\Settings::i()->tags_enabled ) ? implode( ',', array_filter( $item->tags() ) ) : NULL;
				$prefix = ( $item instanceof \IPS\Content\Tags and \IPS\Settings::i()->tags_enabled ) ? $item->prefix() : NULL;
				$isForItem = TRUE;
			}
		}
		
		/* Get the last updated date */
		if ( $isForItem )
		{
			$dateUpdated = $object->item()->mapped('last_comment');
			$dateCommented = $object->item()->mapped('last_comment');
		}
		else
		{
			$dateUpdated = $object->mapped('date');
			$dateCommented = $object->mapped('date');
			if ( $object instanceof \IPS\Content\Item )
			{
				foreach ( array( 'last_comment', 'last_review', 'updated' ) as $k )
				{
					if ( $val = $object->mapped( $k ) )
					{
						if ( $val > $dateUpdated )
						{
							$dateUpdated = $val;
						}
						if ( $k != 'updated' and $val > $dateCommented )
						{
							$dateCommented = $val;
						}
					}
				}
			}
		}
		
		/* Is the the latest content? */
		$isLastComment = 0;
		if ( $object instanceof \IPS\Content\Comment )
		{
			try
			{
				$item = $object->item();
			}
			catch( \OutOfRangeException $ex )
			{
				/* Comment has no parent item, return */
				return NULL;
			}
			
			$latestThing = 0;
			foreach ( array( 'updated', 'last_comment', 'last_review' ) as $k )
			{
				if ( isset( $item::$databaseColumnMap[ $k ] ) and ( $item->mapped( $k ) < time() AND $item->mapped( $k ) > $latestThing ) )
				{
					$latestThing = $item->mapped( $k );
				}
			}
			
			if ( $object->mapped('date') >= $latestThing )
			{
				$isLastComment = 1;
			}
			
			/* If this comment is hidden, don't actually mark as the last comment as that will cause this item to be hidden in search if we are getting only the last comment. */
			if ( $isLastComment AND $object->hidden() )
			{
				$isLastComment = 0;
			}
			
			/* If we are re-indexing the first post of an item, which is triggered by a comment being added to a $itemClass::$firstCommentRequired,
			   then ensure that the isLastComment flag is not added as we index the comment first, which means the index_is_last_comment is incorrectly reset to 0 for the comment itself */
			if ( $isForItem and $isLastComment )
			{
				if ( isset( $item::$databaseColumnMap['num_comments'] ) and $item->mapped('num_comments') > 1 )
				{
					$isLastComment = 0;
				}
			}
		}
		else if ( $object instanceof \IPS\Content\Item and ! $class::$firstCommentRequired )
		{
			/* If this is item itself and not a comment, then we will store it as the last comment so the activity stream fetches the data correctly */
			$isLastComment = 1;
			
			if ( isset( $class::$databaseColumnMap['num_comments'] ) and $object->mapped('num_comments') )
			{
				$isLastComment = 0;
			}
			else if ( isset( $class::$databaseColumnMap['num_reviews'] ) and $object->mapped('num_reviews') )
			{
				$isLastComment = 0;
			}
			
			/* Is the item itself searchable but the comment not? */
			$commentClass = $object::$commentClass;
			if ( ! ( is_subclass_of( $commentClass, 'IPS\Content\Searchable' ) ) )
			{
				/* Then make this the last comment so it remains searchable */
				$isLastComment = 1;
			}
		}
		
		/* Strip spoilers */
		$content = $object->searchIndexContent();
		if ( preg_match( '#<div\s+?class=["\']ipsSpoiler["\']#', $content ) )
		{
			$content = \IPS\Text\Parser::removeElements( $content, array( 'div[class=ipsSpoiler]' ) );
		}
		
		/* Take the HTML out of the content */
		$content = trim( str_replace( \chr(0xC2) . \chr(0xA0), ' ', strip_tags( preg_replace( "/(<br(?: \/)?>|<\/p>)/i", ' ', preg_replace( "#<blockquote(?:[^>]+?)>.+?(?<!<blockquote)</blockquote>#s", " ", preg_replace( "#<script(.*?)>(.*)</script>#uis", "", ' ' . $content . ' ' ) ) ) ) ) );
	
		/* Work out the hidden status */
		$hiddenStatus = $object->hidden();
		if ( $hiddenStatus === 0 and method_exists( $object, 'item' ) and $object->item()->hidden() )
		{
			$hiddenStatus = $isForItem ? $object->item()->hidden() : 2;
		}
		if ( $hiddenStatus !== 0 and method_exists( $object, 'item' ) and $object->item()->isFutureDate() )
		{
			$hiddenStatus = 0;
		}
		if ( $hiddenStatus === -3 )
		{
			return NULL;
		}
		
		/* Get the item index ID */
		$itemIndexId = NULL;
		$itemClass = NULL;
		if ( $object instanceof \IPS\Content\Comment )
		{
			$itemClass = $object::$itemClass;
			if ( $itemClass::$firstCommentRequired )
			{
				try
				{
					/* If the first comment is required and there is no first comment, this is a broken piece of content - do not try to index */
					if( !$object->item()->firstComment() )
					{
						return NULL;
					}

					$itemIndexId = $this->getIndexId( $object->item()->firstComment() );
				}
				catch ( \Exception $e ) { }
			}
			else
			{
				try
				{
					$itemIndexId = $this->getIndexId( $object->item() );
				}
				catch ( \UnderflowException $e )
				{
					try
					{
						/* Try and index parent */
						\IPS\Content\Search\Index::i()->index( $object->item() );
						$itemIndexId = $this->getIndexId( $object->item() );
					}
					catch( \Exception $ex )
					{
						return NULL;
					}
				}
			}
		}
		else if ( $object instanceof \IPS\Content\Item )
		{
			if ( ! $object::$firstCommentRequired )
			{
				/* See if this has already been indexed */
				try
				{
					/* Good, we need the index_item_index_id so this is not wiped on re-index */
					$itemIndexId = $this->getIndexId( $object );
				}
				catch ( \Exception $e ) { }
			}
		}

		/* Club */
		$container = NULL;
		$containerId = NULL;
		$clubId = NULL;
		if ( $object instanceof \IPS\Content\Item )
		{
			$containerId = (int) $object->searchIndexContainer();
			$container = $object->containerWrapper();
		}
		else
		{
			$containerId = (int) $object->item()->mapped('container');
			$container = $object->item()->containerWrapper();
		}
		if ( $container and \IPS\IPS::classUsesTrait( $container, 'IPS\Content\ClubContainer' ) )
		{
			$clubId = $container->{$container::clubIdColumn()};
		}

		/* Work out the container class */
		if( $object instanceof \IPS\Content\Item )
		{
			$containerClass = ( $object->searchIndexContainerClass() ) ? \get_class( $object->searchIndexContainerClass() ) : NULL;
		}
		else
		{
			$containerClass = ( $object->item()->searchIndexContainerClass() ) ? \get_class( $object->item()->searchIndexContainerClass() ) : NULL;
		}

		/* Do we have an extension to modify this? */
		foreach( \IPS\Application::enabledApplications() as $app )
		{
			foreach ( $app->extensions( 'core', 'SearchIndex' ) as $extension )
			{
				$content = $extension->content( $object, $content );
			}
		}

		/* Return */
		return array(
			'index_class'				=> $class,
			'index_object_id'			=> $object->$idColumn,
			'index_item_id'				=> ( $object instanceof \IPS\Content\Item ) ? $object->$idColumn : $object->mapped('item'),
			'index_container_class'		=> $containerClass,
			'index_container_id'		=> ( $object instanceof \IPS\Content\Item ) ? (int) $object->searchIndexContainer() : (int) $object->item()->mapped('container'),
			'index_title'				=> $title,
			'index_content'				=> $content,
			'index_permissions'			=> $object->searchIndexPermissions(),
			'index_date_created'		=> \intval( $object->mapped('date') ),
			'index_date_updated'		=> \intval( $dateUpdated ),
			'index_date_commented'		=> \intval( $dateCommented ),
			'index_author'				=> (int) $object->mapped('author'),
			'index_tags'				=> $tags,
			'index_prefix'				=> $prefix,
			'index_hidden'				=> $hiddenStatus,
			'index_item_index_id'		=> $itemIndexId,
			'index_item_author'			=> \intval( ( $object instanceof \IPS\Content\Item ) ? $object->mapped('author') : $object->item()->mapped('author') ),
			'index_is_last_comment'		=> $isLastComment,
			'index_club_id'				=> $clubId,
			'index_class_type_id_hash'	=> md5( $class . ':' . $object->$idColumn ),
			'index_is_anon'				=> (int) $object->isAnonymous(),
			'index_item_solved'			=> ( $itemClass and \IPS\IPS::classUsesTrait( $itemClass, 'IPS\Content\Solvable' ) ) ? ( $object->item()->mapped('solved_comment_id') == $object->$idColumn ) ? 1 : 0 : NULL
		);
	}
	
	/**
	 * Index an item
	 *
	 * @param	\IPS\Content\Searchable	$object	Item to add
	 * @return	void
	 */
	abstract public function index( \IPS\Content\Searchable $object );
	
	/**
	 * Clear out any tasks associated with the search index method
	 *
	 * @return void
	 */
	public function clearTasks()
	{
		// Do nothing by default
	}
	
	/**
	 * Retrieve the search ID for an item
	 *
	 * @param	\IPS\Content\Searchable	$object	Item to add
	 * @return	void
	 */
	abstract public function getIndexId( \IPS\Content\Searchable $object );
	
	/**
	 * Remove item
	 *
	 * @param	\IPS\Content\Searchable	$object	Item to remove
	 * @return	void
	 */
	abstract public function removeFromSearchIndex( \IPS\Content\Searchable $object );
	
	/**
	 * Removes all content for a classs
	 *
	 * @param	string		$class 	The class
	 * @param	int|NULL	$containerId		The container ID to delete, or NULL
	 * @param	int|NULL	$authorId			The author ID to delete, or NULL
	 * @return	void
	 */
	abstract public function removeClassFromSearchIndex( $class, $containerId=NULL, $authorId=NULL );
	
	/**
	 * Removes all content for a specific application from the index (for example, when uninstalling).
	 *
	 * @param	\IPS\Application	$application The application
	 * @return	void
	 */
	public function removeApplicationContent( \IPS\Application $application )
	{
		foreach ( $application->extensions( 'core', 'ContentRouter' ) as $router )
		{
			foreach( $router->classes AS $class )
			{
				if ( is_subclass_of( $class, 'IPS\Content\Searchable' ) )
				{
					$this->removeClassFromSearchIndex( $class );
					
					if ( isset( $class::$commentClass ) )
					{
						$commentClass = $class::$commentClass;
						if ( is_subclass_of( $commentClass, 'IPS\Content\Searchable' ) )
						{
							$this->removeClassFromSearchIndex( $commentClass );
						}
					}
					
					if ( isset( $class::$reviewClass ) )
					{
						$reviewClass = $class::$reviewClass;
						if ( is_subclass_of( $reviewClass, 'IPS\Content\Searchable' ) )
						{
							$this->removeClassFromSearchIndex( $reviewClass );
						}
					}
				}
			}
		}
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
	abstract public function massUpdate( $class, $containerId = NULL, $itemId = NULL, $newPermissions = NULL, $newHiddenStatus = NULL, $newContainer = NULL, $authorId = NULL, $newItemId = NULL, $newItemAuthorId = NULL, $addAuthorToPermissions = FALSE );
	
	/**
	 * Update data for the first and last comment after a merge
	 * Sets index_is_last_comment on the last comment, and, if this is an item where the first comment is indexed rather than the item, sets index_title and index_tags on the first comment
	 *
	 * @param	\IPS\Content\Item	$item	The item
	 * @return	void
	 */
	abstract public function rebuildAfterMerge( \IPS\Content\Item $item );
	
	/**
	 * Prune search index
	 *
	 * @param	\IPS\DateTime|NULL	$cutoff	The date to delete index records from, or NULL to delete all
	 * @return	void
	 */
	abstract public function prune( \IPS\DateTime $cutoff = NULL );
	
	/**
	 * Reset the last comment flag in any given class/index_item_id
	 *
	 * @param	string				$class 						The class
	 * @param	int|NULL			$indexItemId				The index item ID
	 * @param	int|NULL			$ignoreId					ID to ignore because it is being removed
	 * @return 	void
	 */
	abstract public function resetLastComment( $class, $indexItemId, $ignoreId = NULL );
	
	/**
	 * Given a list of item index IDs, return the ones that a given member has participated in
	 *
	 * @param	array		$itemIndexIds	Item index IDs
	 * @param	\IPS\Member	$member			The member
	 * @return 	array
	 */
	abstract public function iPostedIn( array $itemIndexIds, \IPS\Member $member );
	
	/**
	 * Given a list of "index_class_type_id_hash"s, return the ones that a given member has permission to view
	 *
	 * @param	array		$hashes		Hashes
	 * @param	\IPS\Member	$member		The member
	 * @param	int|NULL	$limit		Number of results to return
	 * @return 	array
	 */
	abstract public function hashesWithPermission( array $hashes, \IPS\Member $member, $limit = NULL );
	
	/**
	 * Get timestamp of oldest thing in index
	 *
	 * @return 	int|null
	 */
	abstract public function firstIndexDate();
	
	/**
	 * Convert terms into stemmed terms for the highlighting JS
	 *
	 * @param	array	$terms	Terms
	 * @return	array
	 */
	public function stemmedTerms( $terms )
	{
		return $terms;
	}
	
	/**
	 * Supports filtering by views?
	 *
	 * @return	bool
	 */
	public function supportViewFiltering()
	{
		return TRUE;
	}
}