<?php
/**
 * @brief		Solvable Trait
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2020
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Solvable Trait
 */
trait Solvable
{
	/**
	 * Container has solvable enabled
	 *
	 * @return	string
	 */
	public function containerAllowsSolvable()
	{
		throw new \BadMethodCallException;
	}
	
	/**
	 * Container has solvable enabled
	 *
	 * @return	string
	 */
	public function containerAllowsMemberSolvable()
	{
		throw new \BadMethodCallException;
	}
	
	/**
	 * Any container has solvable enabled?
	 *
	 * @return	boolean
	 */
	public static function anyContainerAllowsSolvable()
	{
		return FALSE;
	}
	
	/**
	 * Toggle the solve value of a comment
	 *
	 * @param 	int		$commentId	The comment ID
	 * @param 	boolean	$value		TRUE/FALSE value
	 * @param	\IPS\Member	$member	The member (null for currently logged in member)
	 */
	public function toggleSolveComment( $commentId, $value, \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		$commentClass = static::$commentClass;
		$commentIdField = $commentClass::$databaseColumnId;
		$idField = static::$databaseColumnId;
		$solvedField = static::$databaseColumnMap['solved_comment_id'];
		
		$comment = $commentClass::load( $commentId );
		$comment->setSolved( $value );
		$comment->save();
		
		if ( $value )
		{
			if ( $this->$solvedField )
			{
				try 
				{
					$oldComment = $commentClass::load( $this->$solvedField );
					$oldComment->setSolved( FALSE );
					$oldComment->save();
					
					\IPS\Db::i()->delete( 'core_solved_index', array( 'comment_class=? AND comment_id=?', $commentClass, $oldComment->$commentIdField ) );
				}
				catch( \Exception $e ) { }
			}
			
			$this->$solvedField = $comment->$commentIdField;
			$this->save();
		
			\IPS\Db::i()->insert( 'core_solved_index', array(
				'member_id' => (int) $comment->author()->member_id,
				'app'	=> $commentClass::$application,
				'comment_class' => $commentClass,
				'comment_id' => $comment->$commentIdField,
				'item_id'	 => $this->$idField,
				'solved_date' => time()
			) );

			/* Send the "solution to your topic" notification but only if we didn't post the solution, we're not marking the solution, we can view the content, and the user isn't ignored */
			if ( $this->author()->member_id AND $comment->author() != $this->author() AND $this->author() != $member AND $this->canView( $this->author() ) AND !$this->author()->isIgnoring( $comment->author(), 'posts' ) )
			{
				$notification = new \IPS\Notification( \IPS\Application::load('core'), 'mine_solved', $this, array( $this, $comment, $member ), array(), TRUE, NULL );
				$notification->recipients->attach( $this->author() );
				$notification->send();
			}

			/* Send the "you solved the topic" notification but only if we didn't mark the solution */
			if ( $comment->author()->member_id AND $comment->author() != $member )
			{
				$notification = new \IPS\Notification( \IPS\Application::load('core'), 'my_solution', $this, array( $this, $comment, $member ), array(), TRUE, NULL );
				$notification->recipients->attach( $comment->author() );
				$notification->send();
			}

			$payload = [
				'item' => $this,
				'comment' => $comment,
				'markedBy' => $member
			];
			\IPS\Api\Webhook::fire( 'content_marked_solved', $payload );
		}
		else
		{
			$this->$solvedField = 0;
			$this->save();
		
			\IPS\Db::i()->delete( 'core_solved_index', array( 'comment_class=? and comment_id=?', $commentClass, $comment->$commentIdField ) );

			$memberIds	= array();

			foreach( \IPS\Db::i()->select( '`member`', 'core_notifications', array( \IPS\Db::i()->in( 'notification_key', array( 'mine_solved', 'my_solution' ) ) . ' AND item_class=? AND item_id=?', (string) \get_class( $this ), (int) $this->$idField ) ) as $memberToRecount )
			{
				$memberIds[ $memberToRecount ]	= $memberToRecount;
			}

			\IPS\Db::i()->delete( 'core_notifications', array( \IPS\Db::i()->in( 'notification_key', array( 'mine_solved', 'my_solution' ) ) . ' AND item_class=? AND item_id=?', (string) \get_class( $this ), (int) $this->$idField ) );

			foreach( $memberIds as $memberToRecount )
			{
				\IPS\Member::load( $memberToRecount )->recountNotifications();
			}
		}

		/* Update search index */
		\IPS\Content\Search\Index::i()->index( $comment );
	}
	
	/**
	 * Query to get additional data for search result / stream view
	 *
	 * @param	array	$items	Item data (will be an array containing values from basicDataColumns())
	 * @return	array
	 */
	public static function searchResultExtraData( $items )
	{
		$itemIds = array();
		$idField = static::$databaseColumnId;
		
		foreach ( $items as $item )
		{
			if ( $item[ $idField ] )
			{
				$itemIds[ $item[ $idField ] ] = $item[ $idField ];
			}
		}

		if ( \count( $itemIds ) )
		{
			foreach ( static::getItemsWithPermission( array(array( $idField . ' IN(' . implode( ',', $itemIds ) . ')') ), NULL, NULL ) as $row )
			{
				$items[ $row->$idField ]['solved'] = $row->isSolved();
			}

			return $items;
		}

		return array();
	}
	
	/**
	 * Is this topic a "best answer" and solved?
	 *
	 * @return	bool
	 */
	public function isSolved()
	{
		return ( ( $this->containerAllowsMemberSolvable() OR $this->containerAllowsSolvable() ) and $this->mapped('solved_comment_id') );
	}

	/**
	 * Is this a non-admin/mod but can solve this item?
	 *
	 * @param \IPS\Member|null $member The member (null for currently logged in member)
	 * @return boolean
	 */
	public function isNotModeratorButCanSolve( \IPS\Member $member = NULL ): bool
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( $this->canSolve( $member ) and $member === $this->author() and $this->containerAllowsMemberSolvable() and ! $member->modPermissions() )
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Can user solve this item?
	 *
	 * @param \IPS\Member|null $member The member (null for currently logged in member)
	 * @return    bool
	 */
	public function canSolve( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		if( isset( static::$archiveClass ) AND method_exists( $this, 'isArchived' ) AND $this->isArchived() )
		{
			return FALSE;
		}

		/* If we have no replies, it's not solvable yet */
		if( $this->commentCount() <= 0 OR ( static::$firstCommentRequired AND $this->commentCount() == 1 ) )
		{
			return false;
		}
		
		if ( $this->containerAllowsSolvable() )
		{
			if ( $member === $this->author() and $this->containerAllowsMemberSolvable() )
			{
				return TRUE;
			}
		}
		else
		{
			return FALSE;
		}
		
		/* Or if we're a moderator */
		$container = $this->container();
		if ( isset( $container::$modPerm ) )
		{
			if
			(
				$member->modPermission( 'can_set_best_answer' )
				and
				(
					( $member->modPermission( $container::$modPerm ) === TRUE or $member->modPermission( $container::$modPerm ) === -1 )
					or
					(
						\is_array( $member->modPermission( $container::$modPerm ) )
						and
						\in_array( $this->container()->_id, $member->modPermission( $container::$modPerm ) )
					)
				)
			)
			{
				return TRUE;
			}
		}
		
		/* Otherwise no */
		return FALSE;
	}

	/**
	 * Get the solution
	 *
	 * @return \IPS\Content\Comment|NULL
	 */
	public function getSolution()
	{
		try
		{
			$commentClass = static::$commentClass;

			return $commentClass::load( $this->mapped('solved_comment_id') );
		}
		catch( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
}