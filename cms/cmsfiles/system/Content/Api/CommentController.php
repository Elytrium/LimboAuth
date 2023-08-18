<?php
/**
 * @brief		Base API endpoint for Content Comments
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		8 Dec 2015
 */

namespace IPS\Content\Api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Base API endpoint for Content Comments
 */
class _CommentController extends \IPS\Api\Controller
{
	/**
	 * List
	 *
	 * @param	array	$where			Extra WHERE clause
	 * @param	string	$containerParam	The parameter which includes the container values
	 * @param	bool	$byPassPerms	If permissions should be ignored
	 * @return	\IPS\Api\PaginatedResponse
	 */
	protected function _list( $where = array(), $containerParam = 'categories', $byPassPerms=FALSE )
	{
		$class = $this->class;
		$itemClass = $class::$itemClass;
		
		/* Containers */
		if ( isset( \IPS\Request::i()->$containerParam ) )
		{
			$where[] = array( \IPS\Db::i()->in( $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['container'], array_map( 'intval', array_filter( explode( ',', \IPS\Request::i()->$containerParam ) ) ) ) );
		}
		
		/* Authors */
		if ( isset( \IPS\Request::i()->authors ) )
		{
			$where[] = array( \IPS\Db::i()->in( $class::$databasePrefix . $class::$databaseColumnMap['author'], array_map( 'intval', array_filter( explode( ',', \IPS\Request::i()->authors ) ) ) ) );
		}
		
		/* Pinned? */
		if ( isset( \IPS\Request::i()->pinned ) AND \in_array( 'IPS\Content\Pinnable', class_implements( $itemClass ) ) )
		{
			if ( \IPS\Request::i()->pinned )
			{
				$where[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['pinned'] . "=1" );
			}
			else
			{
				$where[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['pinned'] . "=0" );
			}
		}
		
		/* Featured? */
		if ( isset( \IPS\Request::i()->featured ) AND \in_array( 'IPS\Content\Featurable', class_implements( $itemClass ) ) )
		{
			if ( \IPS\Request::i()->featured )
			{
				$where[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['featured'] . "=1" );
			}
			else
			{
				$where[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['featured'] . "=0" );
			}
		}
		
		/* Locked? */
		if ( isset( \IPS\Request::i()->locked ) AND \in_array( 'IPS\Content\Lockable', class_implements( $itemClass ) ) )
		{
			if ( isset( static::$databaseColumnMap['locked'] ) )
			{
				$where[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['locked'] . '=?', \intval( \IPS\Request::i()->locked ) );
			}
			else
			{
				$where[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['state'] . '=?', \IPS\Request::i()->locked ? 'closed' : 'open' );
			}
		}
		
		/* Hidden */
		if ( isset( \IPS\Request::i()->hidden ) AND \in_array( 'IPS\Content\Hideable', class_implements( $class ) ) )
		{
			if ( \IPS\Request::i()->hidden )
			{
				if ( isset( $class::$databaseColumnMap['hidden'] ) )
				{
					$where[] = array( $class::$databasePrefix . $class::$databaseColumnMap['hidden'] . '<>0' );
				}
				else
				{
					$where[] = array( $class::$databasePrefix . $class::$databaseColumnMap['approved'] . '<>1' );
				}
			}
			else
			{
				if ( isset( $class::$databaseColumnMap['hidden'] ) )
				{
					$where[] = array( $class::$databasePrefix . $class::$databaseColumnMap['hidden'] . '=0' );
				}
				else
				{
					$where[] = array( $class::$databasePrefix . $class::$databaseColumnMap['approved'] . '=1' );
				}
			}
		}
		
		/* Has poll? */
		if ( isset( \IPS\Request::i()->hasPoll ) AND \in_array( 'IPS\Content\Polls', class_implements( $itemClass ) ) )
		{
			if ( \IPS\Request::i()->hasPoll )
			{
				$where[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['poll'] . ">0" );
			}
			else
			{
				$where[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['poll'] . "=0" );
			}
		}
		
		/* Sort */
		if ( isset( \IPS\Request::i()->sortBy ) and \in_array( \IPS\Request::i()->sortBy, array( 'date' ) ) )
		{
			$sortBy = $class::$databasePrefix . $class::$databaseColumnMap[ \IPS\Request::i()->sortBy ];
		}
		if ( isset( \IPS\Request::i()->sortBy ) and \in_array( \IPS\Request::i()->sortBy, array( 'title' ) ) )
		{
			$sortBy = $itemClass::$databasePrefix . $itemClass::$databaseColumnMap[ \IPS\Request::i()->sortBy ];
		}
		else
		{
			$sortBy = $class::$databasePrefix . $class::$databaseColumnId;
		}
		$sortDir = ( isset( \IPS\Request::i()->sortDir ) and \in_array( mb_strtolower( \IPS\Request::i()->sortDir ), array( 'asc', 'desc' ) ) ) ? \IPS\Request::i()->sortDir : 'asc';
		
		/* Get results */
		if ( $this->member and !$byPassPerms )
		{
			$query = $class::getItemsWithPermission( $where, "{$sortBy} {$sortDir}", NULL, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, $this->member )->getInnerIterator();
			$count = $class::getItemsWithPermission( $where, "{$sortBy} {$sortDir}", NULL, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, $this->member, FALSE, FALSE, FALSE, TRUE );
		}
		else
		{
			$itemWhere = array();
			
			/* And no PBR or queued for deletion things either */
			if ( isset( $class::$databaseColumnMap['hidden'] ) )
			{
				$col = $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['hidden'];
				$where[] = array( "{$col}!=-2 AND {$col} !=-3" );
			}
			else if ( isset( $class::$databaseColumnMap['approved'] ) )
			{
				$col = $class::$databaseTable . '.' . $class::$databasePrefix . $class::$databaseColumnMap['approved'];
				$where[] = array( "{$col}!=-2 AND {$col}!=-3" );
			}

			/* We also need to check the item for soft delete and post before register */
			if( \in_array( 'IPS\Content\Hideable', class_implements( $itemClass ) ) )
			{
				/* No matter if we can or cannot view hidden items, we do not want these to show: -2 is queued for deletion and -3 is posted before register */
				if ( isset( $itemClass::$databaseColumnMap['hidden'] ) )
				{
					$col = $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['hidden'];
					$itemWhere[] = array( "{$col}!=-2 AND {$col} !=-3" );
				}
				else if ( isset( $itemClass::$databaseColumnMap['approved'] ) )
				{
					$col = $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['approved'];
					$itemWhere[] = array( "{$col}!=-2 AND {$col} !=-3" );
				}
			}

			$query = \IPS\Db::i()->select( '*', $class::$databaseTable, $where, "{$sortBy} {$sortDir}" )->join( $itemClass::$databaseTable, array_merge( array( array( $class::$databaseTable . "." . $class::$databasePrefix . $class::$databaseColumnMap['item'] . "=" . $itemClass::$databaseTable . "." . $itemClass::$databasePrefix . $itemClass::$databaseColumnId ) ), $itemWhere ), 'STRAIGHT_JOIN' );
			$count = \IPS\Db::i()->select( 'COUNT(*)', $class::$databaseTable, $where )->join( $itemClass::$databaseTable, array_merge( array( array( $class::$databaseTable . "." . $class::$databasePrefix . $class::$databaseColumnMap['item'] . "=" . $itemClass::$databaseTable . "." . $itemClass::$databasePrefix . $itemClass::$databaseColumnId ) ), $itemWhere ), 'STRAIGHT_JOIN' )->first();
		}
		
		/* Return */
		return new \IPS\Api\PaginatedResponse(
			200,
			$query,
			isset( \IPS\Request::i()->page ) ? \IPS\Request::i()->page : 1,
			$class,
			$count,
			$this->member,
			isset( \IPS\Request::i()->perPage ) ? \IPS\Request::i()->perPage : NULL
		);
	}
	
	/**
	 * Create
	 *
	 * @param	\IPS\Content\Item	$item			Content Item
	 * @param	\IPS\Member			$author			Author
	 * @param	string				$contentParam	The parameter that contains the content body
	 * @return	\IPS\Api\Response
	 */
	protected function _create( \IPS\Content\Item $item, \IPS\Member $author, $contentParam='content' )
	{
		return new \IPS\Api\Response( 201, $this->_createComment( $item, $author, $contentParam )->apiOutput( $this->member ) );
	}
	
	/**
	 * Create
	 *
	 * @param	\IPS\Content\Item	$item			Content Item
	 * @param	\IPS\Member			$author			Author
	 * @param	string				$contentParam	The parameter that contains the content body
	 * @return	\IPS\Api\Response
	 */
	protected function _createComment( \IPS\Content\Item $item, \IPS\Member $author, $contentParam='content' )
	{
		/* Work out the date */
		$date = ( !$this->member and \IPS\Request::i()->date ) ? new \IPS\DateTime( \IPS\Request::i()->date ) : \IPS\DateTime::create();
		
		/* Is it hidden? */
		$hidden = NULL;
		if ( isset( \IPS\Request::i()->hidden ) and !$this->member )
		{
			$hidden = \IPS\Request::i()->hidden;
		}
		
		/* Parse */
		$content = \IPS\Request::i()->$contentParam;
		if ( $this->member )
		{
			$content = \IPS\Text\Parser::parseStatic( $content, TRUE, NULL, $this->member, $item::$application . '_' . mb_ucfirst( $item::$module ) );
		}
		
		/* Create post */
		$class = $this->class;
		if ( \in_array( 'IPS\Content\Review', class_parents( $class ) ) )
		{
			$comment = $class::create( $item, $content, FALSE, \intval( \IPS\Request::i()->rating ), $author->member_id ? NULL : $author->real_name, $author, $date, ( !$this->member and \IPS\Request::i()->ip_address ) ? \IPS\Request::i()->ip_address : \IPS\Request::i()->ipAddress(), $hidden, ( isset( \IPS\Request::i()->anonymous ) ? (bool) \IPS\Request::i()->anonymous : NULL ) );
		}
		else
		{
			$comment = $class::create( $item, $content, FALSE, $author->member_id ? NULL : $author->real_name, NULL, $author, $date, ( !$this->member and \IPS\Request::i()->ip_address ) ? \IPS\Request::i()->ip_address : \IPS\Request::i()->ipAddress(), $hidden, ( isset( \IPS\Request::i()->anonymous ) ? (bool) \IPS\Request::i()->anonymous : NULL ) );
		}

		/* Index */
		if ( $item instanceof \IPS\Content\Searchable )
		{
			if ( $item::$firstCommentRequired and !$comment->isFirst() )
			{
				if ( \in_array( 'IPS\Content\Searchable', class_implements( $class ) ) )
				{					
					\IPS\Content\Search\Index::i()->index( $item->firstComment() );
				}
			}
			else
			{
				\IPS\Content\Search\Index::i()->index( $item );
			}
		}
		if ( $comment instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $comment );
		}
		
		/* Hide */
		if ( isset( \IPS\Request::i()->hidden ) and $this->member and \IPS\Request::i()->hidden and $comment->canHide( $this->member ) )
		{
			$comment->hide( $this->member );
		}
		
		/* Return */
		return $comment;
	}
	
	/**
	 * Edit
	 *
	 * @param	\IPS\Content\Comment		$comment		The comment
	 * @param	string						$contentParam	The parameter that contains the content body
	 * @throws	InvalidArgumentException	Invalid author
	 * @return	\IPS\Api\Response
	 */
	protected function _edit( $comment, $contentParam='content' )
	{
		/* Hidden */
		if ( !$this->member and isset( \IPS\Request::i()->hidden ) )
		{			
			if ( \IPS\Request::i()->hidden )
			{
				$comment->hide( FALSE );
			}
			else
			{
				$comment->unhide( FALSE );
			}
		}
		
		/* Change author */
		if ( !$this->member and isset( \IPS\Request::i()->author ) )
		{
			$authorIdColumn = $comment::$databaseColumnMap['author'];
			$authorNameColumn = $comment::$databaseColumnMap['author_name'];
			
			/* Just renaming the guest */
			if ( !$comment->$authorIdColumn and ( !isset( \IPS\Request::i()->author ) or !\IPS\Request::i()->author ) and isset( \IPS\Request::i()->author_name ) )
			{
				$comment->$authorNameColumn = \IPS\Request::i()->author_name;
			}
			
			/* Actually changing the author */
			else
			{
				try
				{
					$member = \IPS\Member::load( \IPS\Request::i()->author );
					if ( !$member->member_id )
					{
						throw new \InvalidArgumentException;
					}
					
					$comment->changeAuthor( $member );
				}
				catch ( \OutOfRangeException $e )
				{
					throw new \InvalidArgumentException;
				}
			}
		}
		
		/* Post value */
		if ( isset( \IPS\Request::i()->$contentParam ) )
		{
			$contentColumn = $comment::$databaseColumnMap['content'];
			
			$content = \IPS\Request::i()->$contentParam;
			if ( $this->member )
			{
				$item = $comment->item();
				$content = \IPS\Text\Parser::parseStatic( $content, TRUE, NULL, $this->member, $item::$application . '_' . mb_ucfirst( $item::$module ) );
			}
			$comment->$contentColumn =$content;
		}
		
		/* Rating */
		$ratingChanged = FALSE;
		if ( isset( \IPS\Request::i()->rating ) )
		{
			$ratingChanged = TRUE;
			$ratingColumn = $comment::$databaseColumnMap['rating'];
			$comment->$ratingColumn = \intval( \IPS\Request::i()->rating );
		}
		
		/* Save and return */
		$comment->save();
		
		/* Recalculate ratings */
		if ( $ratingChanged )
		{
			$itemClass = $comment::$itemClass;
			$ratingField = $itemClass::$databaseColumnMap['rating'];
			
			$comment->item()->$ratingField = $comment->item()->averageReviewRating() ?: 0;
			$comment->item()->save();
		}
		
		/* Return */
		return new \IPS\Api\Response( 200, $comment->apiOutput( $this->member ) );
	}

	/**
	 * Delete a reaction to a comment
	 *
	 * @param $id
	 * @return \IPS\Api\Response
	 * @throws \IPS\Api\Exception
	 */
	public function _reactRemove( $id ): \IPS\Api\Response
	{
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->author );
		}
		catch( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'NO_AUTHOR', '1S425/6', 404 );
		}

		try
		{
			$class = $this->class;
			if ( $this->member )
			{
				$object = $class::loadAndCheckPerms( $id, $this->member );
			}
			else
			{
				$object = $class::load( $id );
			}

			$object->removeReaction( $member );

			return new \IPS\Api\Response( 200, $object->apiOutput( $this->member ) );
		}
		catch ( \DomainException $e )
		{
			throw new \IPS\Api\Exception( $e->getMessage(), '1S425/7', 403 );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '1S425/8', 404 );
		}
	}

	/**
	 * React to a comment
	 *
	 * @param $id
	 * @return \IPS\Api\Response
	 * @throws \IPS\Api\Exception
	 */
	public function _reactAdd( $id ): \IPS\Api\Response
	{
		try
		{
			$reaction = \IPS\Content\Reaction::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'NO_REACTION', '1S425/2', 404 );
		}

		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->author );
		}
		catch( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'NO_AUTHOR', '1S425/3', 404 );
		}

		try
		{
			$class = $this->class;
			if ( $this->member )
			{
				$object = $class::loadAndCheckPerms( $id, $this->member );
			}
			else
			{
				$object = $class::load( $id );
			}

			$object->react( $reaction, $member );

			return new \IPS\Api\Response( 200, $object->apiOutput( $this->member ) );
		}
		catch ( \DomainException $e )
		{
			throw new \IPS\Api\Exception( 'REACT_ERROR_' . $e->getMessage(), '1S425/4', 403 );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '1S425/5', 404 );
		}
	}


	/**
	 * Report a comment
	 *
	 * @param $id
	 * @return \IPS\Api\Response
	 * @throws \IPS\Api\Exception
	 */
	public function _report( $id ): \IPS\Api\Response
	{
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->author );
		}
		catch( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'NO_AUTHOR', '1S425/B', 404 );
		}

		$class = $this->class;
		$idColumn = $class::$databaseColumnId;
		if ( $this->member )
		{
			$object = $class::loadAndCheckPerms( $id, $this->member );
		}
		else
		{
			$object = $class::load( $id );
		}

		/* Has this member already reported this in the past 24 hours */
		try
		{
			$index = \IPS\core\Reports\Report::loadByClassAndId( \get_class( $object ), $object->$idColumn );
			$report = \IPS\Db::i()->select( '*', 'core_rc_reports', array( 'rid=? and report_by=? and date_reported > ?', $index->id, $member, time() - ( \IPS\Settings::i()->automoderation_report_again_mins * 60 ) ) )->first();

			/* They have aleady reported, so do nothing */
			throw new \IPS\Api\Exception( 'REPORTED_ALREADY', '1S425/C', 404 );
		}
		catch( \Exception $e )
		{
			/* No issues here */
		}

		try
		{
			$object->report( ( isset( \IPS\Request::i()->message ) ? \IPS\Request::i()->message : '' ), ( isset(\IPS\Request::i()->report_type) ? \IPS\Request::i()->report_type : 0 ), $member );
		}
		catch( \UnexpectedValueException $e )
		{
			throw new \IPS\Api\Exception( 'REPORT_ERROR_' . $e->getMessage(), '1S425/B', 403 );
		}

		return new \IPS\Api\Response( 200, $object->apiOutput( $this->member ) );
	}
}