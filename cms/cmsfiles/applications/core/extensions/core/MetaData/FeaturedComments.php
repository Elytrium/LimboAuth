<?php
/**
 * @brief		Meta Data: Featured Comments
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		04 Dec 2016
 */

namespace IPS\core\extensions\core\MetaData;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Meta Data: Featured Comments
 */
class _FeaturedComments
{
	/**
	 * Can Feature a Comment
	 *
	 * @param	\IPS\Content\Item	$item		The content item
	 * @param	\IPS\Member|NULL	$member		The member, or NULL for currently logged in
	 * @return	bool
	 */
	public function canFeatureComment( \IPS\Content\Item $item, \IPS\Member $member = NULL )
	{
		if ( !( $item instanceof \IPS\Content\MetaData ) )
		{
			return FALSE;
		}
		
		if ( !\in_array( 'core_FeaturedComments', $item::supportedMetaDataTypes() ) )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		try
		{
			return $item::modPermission( 'feature_comments', $member, $item->container() );
		}
		catch( \BadMethodCallException $e )
		{
			return $member->modPermission( 'can_feature_comments' );
		}
	}
	
	/**
	 * Can Unfeature a Comment
	 *
	 * @param	\IPS\Content\Item	$item		The content item
	 * @param	\IPS\Member|NULL	$member		The member, or NULL for currently logged in
	 * @return	bool
	 */
	public function canUnfeatureComment( \IPS\Content\Item $item, \IPS\Member $member = NULL )
	{
		if ( !( $item instanceof \IPS\Content\MetaData ) )
		{
			return FALSE;
		}
		
		if ( !\in_array( 'core_FeaturedComments', $item::supportedMetaDataTypes() ) )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		try
		{
			return $item::modPermission( 'unfeature_comments', $member, $item->container() );
		}
		catch( \BadMethodCallException $e )
		{
			return $member->modPermission( 'can_unfeature_comments' );
		}
	}
	
	/**
	 * Feature A Comment
	 *
	 * @param	\IPS\Content\Item		$item		The content item
	 * @param	\IPS\Content\Comment	$comment	The Comment
	 * @param	string|NULL				$note		An optional note to include
	 * @param	\IPS\Member|NULL		$member		The member featuring the comment
	 * @return	void
	 */
	public function featureComment( \IPS\Content\Item $item, \IPS\Content\Comment $comment, $note = NULL, \IPS\Member $member = NULL )
	{
		$member				= $member ?: \IPS\Member::loggedIn();
		$idColumn			= $item::$databaseColumnId;
		$commentIdColumn	= $comment::$databaseColumnId;
		
		$save = array(
			'comment' 		=> $comment->$commentIdColumn,
			'featured_by'	=> $member->member_id
		);
		
		if ( $note )
		{
			$save['note'] = $note;
		}
		
		$item->addMeta( 'core_FeaturedComments', $save );
	}
	
	/**
	 * Unfeature a comment
	 *
	 * @param	\IPS\Content\Item		$item		The content item
	 * @param	\IPS\Content\Comment	$comment	The Comment
	 * @param	\IPS|Member|NULL		$member		The member unfeaturing the comment
	 * @return	void
	 */
	public function unfeatureComment( \IPS\Content\Item $item, \IPS\Content\Comment $comment, \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		$commentIdField = $comment::$databaseColumnId;
		
		$idToRemove = FALSE;
		foreach( $item->getMeta()['core_FeaturedComments'] AS $key => $data )
		{
			if ( $data['comment'] == $comment->$commentIdField )
			{
				$idToRemove = $key;
				break;
			}
		}
		
		$item->deleteMeta( $idToRemove );
	}
	
	/**
	 * Get Featured Comments in the most efficient way possible
	 *
	 * @param	\IPS\Content\Item	$item	The content item
	 * @return	array
	 */
	public function featuredComments( \IPS\Content\Item $item )
	{
		if ( $meta = $item->getMeta() AND isset( $meta['core_FeaturedComments'] ) AND \is_array( $meta['core_FeaturedComments'] ) )
		{
			/* Start by constructing our array and gathering ID's - we'll need them later */
			$comments	= array();
			$commentIds	= array();
			$reviewIds	= array();
			$memberIds	= array();
			foreach( $meta['core_FeaturedComments'] AS $key => $comment )
			{
				$comments[ $comment['comment'] ] = array(
					'note'		=> isset( $comment['note'] ) ? $comment['note'] : '',
				);
				$commentIds[] = $comment['comment'];

				$memberIds[ $comment['featured_by'] ][]	= $comment['comment'];
			}
			
			$commentClass = $item::$commentClass;
			$commentIdField = $commentClass::$databaseColumnId;

			if ( isset( $commentClass::$databaseColumnMap['hidden'] ) )
			{
				$col = $commentClass::$databaseColumnMap['hidden'];
			}
			else if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
			{
				$col = $commentClass::$databaseColumnMap['approved'];
			}

			$softDeleted = [];
			foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', $commentClass::$databaseTable, array( \IPS\Db::i()->in( $commentClass::$databasePrefix . $commentIdField, $commentIds ) ), $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['date'] . " DESC" ), $commentClass ) AS $row )
			{
				if( $row->$col < 0 )
				{
					unset( $comments[$row->$commentIdField] );
					$softDeleted[] = $row->$commentIdField;
				}
				else
				{
					$comments[ $row->$commentIdField ]['comment'] = $row;
				}
			}

			/* And finally, who featured them */
			if ( \count( $memberIds ) )
			{
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members', array( \IPS\Db::i()->in( 'member_id', array_keys( $memberIds ) ) ) ), 'IPS\Member' ) AS $m )
				{
					foreach( $memberIds[ $m->member_id ] AS $attach )
					{
						if( \in_array( $attach, $softDeleted ) )
						{
							continue;
						}

						$comments[ $attach ]['featured_by'] = $m;
					}
				}
			}

			/* And off we go! */
			return $comments;
		}
		
		return array();
	}
	
	/**
	 * Is a featured comment?
	 *
	 * @param	\IPS\Content\Comment	$item	The comment
	 * @return	bool
	 */
	public function isFeatured( \IPS\Content\Comment $item )
	{
		try
		{
			$idColumn = $item::$databaseColumnId;
			if ( $meta = $item->item()->getMeta() AND isset( $meta['core_FeaturedComments'] ) )
			{
				foreach( $meta['core_FeaturedComments'] AS $key => $comment )
				{
					if ( $comment['comment'] === $item->$idColumn )
					{
						return TRUE;
					}
				}
			}
			
			return FALSE;
		}
		catch( \BadMethodCallException $e )
		{
			return FALSE;
		}
	}
}
