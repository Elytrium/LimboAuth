<?php
/**
 * @brief		Base mutator class for Content Items
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 Jun 2018
 */

namespace IPS\Content\Api\GraphQL;

use \IPS\Api\GraphQL\TypeRegistry;
/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Base mutator class for Content Items
 */
abstract class _ItemMutator extends ContentMutator
{
    public function args(): array
    {
        return [
            'category' =>  TypeRegistry::nonNull( TypeRegistry::id() ),
            'title' => TypeRegistry::nonNull( TypeRegistry::string() ),
            'content' => TypeRegistry::nonNull( TypeRegistry::string() ),
            'tags' => TypeRegistry::listOf( TypeRegistry::string() ),
            'state' => TypeRegistry::itemState(),
            'postKey' => TypeRegistry::string()
        ];
    }

	/**
	 * Mark as read
	 *
	 * @param	\IPS\Content\Item	$item			Item to mark as read
	 * @return	void
	 */
	protected function _markRead( $item )
	{
		$item->markRead();
		return $item;
	}

	/**
	 * Create
	 *
	 * @param	\IPS\Node\Model	$container			Container
	 * @param	\IPS\Member		$author				Author
	 * @param	string			$postKey			Post key
	 * @return	\IPS\Content\Item
	 */
	protected function _create( $itemData, \IPS\Node\Model $container = NULL, string $postKey = NULL )
	{
		$class = $this->class;
		
		/* Work out the date */
		$date = \IPS\DateTime::create();
				
		/* Create item */
		$item = $class::createItem( \IPS\Member::loggedIn(), \IPS\Request::i()->ipAddress(), $date, $container );
		$this->_createOrUpdate( $item, $itemData, 'add' );
		$item->save();
		
		/* Create post */
		if ( $class::$firstCommentRequired )
		{	
			$attachmentIdsToClaim = array();	
			if ( $postKey )
			{
				try
				{
					$this->_addAttachmentsToContent( $postKey, $itemData['content'] );
				}
				catch ( \DomainException $e )
				{
					throw new \IPS\Api\GraphQL\SafeException( 'ATTACHMENTS_TOO_LARGE', '2S401/1', 403 );
				}
			}
			
			$postContents = \IPS\Text\Parser::parseStatic( $itemData['content'], TRUE, md5( $postKey . ':' ), \IPS\Member::loggedIn(), $class::$application . '_' . mb_ucfirst( $class::$module ) );
			
			$commentClass = $item::$commentClass;
			$post = $commentClass::create( $item, $postContents, TRUE, \IPS\Member::loggedIn()->member_id ? NULL : \IPS\Member::loggedIn()->real_name, NULL, \IPS\Member::loggedIn(), $date );
			$itemIdColumn = $item::$databaseColumnId;
			$postIdColumn = $commentClass::$databaseColumnId;
			\IPS\File::claimAttachments( "{$postKey}:", $item->$itemIdColumn, $post->$postIdColumn );
			
			if ( isset( $class::$databaseColumnMap['first_comment_id'] ) )
			{
				$firstCommentColumn = $class::$databaseColumnMap['first_comment_id'];
				$commentIdColumn = $commentClass::$databaseColumnId;
				$item->$firstCommentColumn = $post->$commentIdColumn;
				$item->save();
			}
		}
		
		/* Index */
		if ( $item instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $item );
		}

		/* Mark it as read */
		if( $item instanceof \IPS\Content\ReadMarkers )
		{
			$item->markRead();
		}
		
		/* Send notifications and dish out points */
		if ( !$item->hidden() )
		{
			$item->sendNotifications();
			\IPS\Member::loggedIn()->achievementAction( 'core', 'NewContentItem', $item );
		}
		elseif( $item->hidden() !== -1 )
		{
			$item->sendUnapprovedNotification();
		}
		
		/* Output */
		return $item;
	}

	/**
	 * Create or update item
	 *
	 * @param	\IPS\Content\Item	$item		The item
	 * @param	array				$itemData	Item data
	 * @param	string				$type		add or edit
	 * @param	string				$postKey	Post key
	 * @return	\IPS\Content\Item
	 */
	protected function _createOrUpdate( \IPS\Content\Item $item, array $itemData=array(), $type='add', $postKey = NULL )
	{
		$class = $this->class;

		/* Title */
		if ( isset( $itemData['title'] ) and isset( $item::$databaseColumnMap['title'] ) )
		{
			$titleColumn = $item::$databaseColumnMap['title'];
			$item->$titleColumn = $itemData['title'];
		}
		
		/* Tags */
		if ( ( isset( $itemData['prefix'] ) or isset( $itemData['tags'] ) ) and \in_array( 'IPS\Content\Tags', class_implements( \get_class( $item ) ) ) )
		{
			if ( \IPS\Member::loggedIn()->member_id && $item::canTag( NULL, $item->containerWrapper() ) )
			{				
				$source = array();

				if( !\IPS\Settings::i()->tags_open_system )
				{
					/* And get the defined tags */
					if( $class::definedTags( $item->containerWrapper() ) )
					{
						$source = array_map( 'trim', array_unique( array_merge( $source, $class::definedTags( $item->containerWrapper() ) ) ) );
					}
				}

				/* Filter our provided tags and exclude any that are invalid */
				$validTags = array_filter( array_map( 'trim', array_unique( $itemData['tags'] ) ), function($tag) use ($source) {
					
					if( !\IPS\Settings::i()->tags_open_system && !\in_array($tag, $source) )
					{
						return FALSE;
					}

					if( \IPS\Settings::i()->tags_len_min && \strlen($tag) < \IPS\Settings::i()->tags_len_min )
					{
						return FALSE;
					}

					if( \IPS\Settings::i()->tags_len_max && \strlen($tag) < \IPS\Settings::i()->tags_len_max )
					{
						return FALSE;
					}

					if( \strpos( $tag, '#' ) !== FALSE )
					{
						return FALSE;
					}

					return TRUE;
				});

				if( \IPS\Settings::i()->tags_min && \count( $validTags ) < \IPS\Settings::i()->tags_min )
				{
					throw new \IPS\Api\GraphQL\SafeException( 'TOO_FEW_TAGS', 'GQL/0011/1', 400 );
				}

				if( \IPS\Settings::i()->tags_max && \count( $validTags ) > \IPS\Settings::i()->tags_max )
				{
					throw new \IPS\Api\GraphQL\SafeException( 'TOO_MANY_TAGS', 'GQL/0011/2', 400 );
				}
	
				/* we need to save the item before we set the tags because setTags requires that the item exists */
				$idColumn = $item::$databaseColumnId;
				if ( !$item->$idColumn )
				{
					$item->save();
				}
	
				$item->setTags( $validTags );
			}
		}
		
		/* Open/closed */
		if ( isset( $itemData['state']['locked'] ) and \in_array( 'IPS\Content\Lockable', class_implements( \get_class( $item ) ) ) )
		{
			if ( \IPS\Member::loggedIn()->member_id && ( $itemData['state']['locked'] and $item->canLock() ) or ( !$itemData['state']['locked'] and $item->canUnlock() ) )
			{
				if ( isset( $item::$databaseColumnMap['locked'] ) )
				{
					$lockedColumn = $item::$databaseColumnMap['locked'];
					$item->$lockedColumn = \intval( $itemData['state']['locked'] );
				}
				else
				{
					$stateColumn = $item::$databaseColumnMap['status'];
					$item->$stateColumn = $itemData['state']['locked'] ? 'closed' : 'open';
				}
			}
		}
		
		/* Hidden */
		if ( isset( $itemData['state']['hidden'] ) and \in_array( 'IPS\Content\Hideable', class_implements( \get_class( $item ) ) ) )
		{
			if ( \IPS\Member::loggedIn()->member_id && ( $itemData['state']['hidden'] and $item->canHide() ) or ( !$itemData['state']['hidden'] and $item->canUnhide() ) )
			{
				$idColumn = $item::$databaseColumnId;
				if ( $itemData['state']['hidden'] )
				{
					if ( $item->$idColumn )
					{
						$item->hide( FALSE );
					}
					else
					{
						if ( isset( $item::$databaseColumnMap['hidden'] ) )
						{
							$hiddenColumn = $item::$databaseColumnMap['hidden'];
							$item->$hiddenColumn = $itemData['state']['hidden'];
						}
						else
						{
							$approvedColumn = $item::$databaseColumnMap['approved'];
							$item->$approvedColumn = ( $itemData['state']['hidden'] == -1 ) ? -1 : 0;
						}
					}
				}
				else
				{
					if ( $item->$idColumn )
					{
						$item->unhide( FALSE );
					}
					else
					{
						if ( isset( $item::$databaseColumnMap['hidden'] ) )
						{
							$hiddenColumn = $item::$databaseColumnMap['hidden'];
							$item->$hiddenColumn = 0;
						}
						else
						{
							$approvedColumn = $item::$databaseColumnMap['approved'];
							$item->$approvedColumn = 1;
						}
					}
				}
			}
		}
		
		/* Pinned */
		if ( isset( $itemData['state']['pinned'] ) and \in_array( 'IPS\Content\Pinnable', class_implements( \get_class( $item ) ) ) )
		{
			if ( \IPS\Member::loggedIn()->member_id && ( $itemData['state']['pinned'] and $item->canPin() ) or ( !$itemData['state']['pinned'] and $item->canUnpin() ) )
			{
				$pinnedColumn = $item::$databaseColumnMap['pinned'];
				$item->$pinnedColumn = \intval( $itemData['state']['pinned'] );
			}
		}
		
		/* Featured */
		if ( isset( $itemData['state']['featured'] ) and \in_array( 'IPS\Content\Featurable', class_implements( \get_class( $item ) ) ) )
		{
			if ( \IPS\Member::loggedIn()->member_id && ( $itemData['state']['featured'] and $item->canFeature() ) or ( !$itemData['state']['featured'] and $item->canUnfeature() ) )
			{
				$featuredColumn = $item::$databaseColumnMap['featured'];
				$item->$featuredColumn = \intval( $itemData['state']['featured'] );
			}
		}

		/* Update first comment if required, and it's not a new item */
		$field = isset( $item::$databaseColumnMap['first_comment_id'] ) ? $item::$databaseColumnMap['first_comment_id'] : NULL;
		$commentClass = $item::$commentClass;
		$contentField = $commentClass::$databaseColumnMap['content'];
		if ( $item::$firstCommentRequired AND isset( $item->$field ) AND isset( $itemData[ $contentField ] ) AND $type == 'edit' )
		{
			$attachmentIdsToClaim = array();
			if ( $postKey )
			{
				try
				{
					$this->_addAttachmentsToContent( $postKey, $itemData[ $contentField ] );
				}
				catch ( \DomainException $e )
				{
					throw new \IPS\Api\GraphQL\SafeException( 'ATTACHMENTS_TOO_LARGE', '2S401/2', 403 );
				}
			}
			
			$content = \IPS\Text\Parser::parseStatic( $itemData[ $contentField ], TRUE, array( $item->_id, $item->$field ), \IPS\Member::loggedIn(), $item::$application . '_' . mb_ucfirst( $item::$module ) );

			try
			{
				$comment = $commentClass::load( $item->$field );
			}
			catch ( \OutOfRangeException $e )
			{
				throw new \IPS\Api\Exception( 'NO_FIRST_POST', '1S377/1', 400 );
			}

			$comment->$contentField = $content;
			$comment->save();

			/* Update Search Index of the first item */
			if ( $item instanceof \IPS\Content\Searchable )
			{
				\IPS\Content\Search\Index::i()->index( $comment );
			}
		}
		
		/* Return */
		return $item;
	}


}