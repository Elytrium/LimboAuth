<?php
/**
 * @brief		Reaction Trait
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Nov 2016
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Reaction Trait
 */
trait Reactable
{
	/**
	 * Reaction type
	 *
	 * @return	string
	 */
	public static function reactionType()
	{
		throw new \BadMethodCallException;
	}
	
	/**
	 * Reaction class
	 *
	 * @return	string
	 */
	public static function reactionClass()
	{
		return \get_called_class();
	}
	
	/**
	 * React
	 *
	 * @param	\IPS\core\Reaction		$reaction	The reaction
	 * @param	\IPS\Member				$member		The member reacting, or NULL 
	 * @return	void
	 * @throws	\DomainException
	 */
	public function react( \IPS\Content\Reaction $reaction, \IPS\Member $member = NULL )
	{
		/* Did we pass a member? */
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* Figure out the owner of this - if it is content, it will be the author. If it is a node, then it will be the person who created it */
		if ( $this instanceof \IPS\Content )
		{
			$owner = $this->author();
		}
		else if ( $this instanceof \IPS\Node\Model )
		{
			$owner = $this->owner();
		}

		/* Can we react? */
		if ( !$this->canView( $member ) or !$this->canReact( $member ) or !$reaction->enabled )
		{
			throw new \DomainException( 'cannot_react' );
		}
		
		/* Have we hit our limit? Also, why 999 for unlimited? */
		if ( $member->group['g_rep_max_positive'] !== -1 )
		{
			$count = \IPS\Db::i()->select( 'COUNT(*)', 'core_reputation_index', array( 'member_id=? AND rep_date>?', $member->member_id, \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) )->getTimestamp() ) )->first();
			if ( $count >= $member->group['g_rep_max_positive'] )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'react_daily_exceeded', FALSE, array( 'sprintf' => array( $member->group['g_rep_max_positive'] ) ) ) );
			}
		}
		
		/* Figure out our app - we do it this way as content items and nodes will always have a lowercase namespace for the app, so if the match below fails, then 'core' can be assumed */
		$app = explode( '\\', \get_class( $this ) );
		if ( \strtolower( $app[1] ) === $app[1] )
		{
			$app = $app[1];
		}
		else
		{
			$app = 'core';
		}
		
		/* If this is a comment, we need the parent items ID */
		$itemId = 0;
		if ( $this instanceof \IPS\Content\Comment )
		{
			$item			= $this->item();
			$itemIdColumn	= $item::$databaseColumnId;
			$itemId			= $item->$itemIdColumn;
		}
		
		/* Have we already reacted? */
		$reacted = $this->reacted( $member );
		
		/* Remove the initial reaction, if we have reacted */
		if ( $reacted )
		{
			$this->removeReaction( $member, FALSE );
		}
		
		/* Give points */
		$owner->achievementAction( 'core', 'Reaction', [
			'giver'		=> $member,
			'content'	=> $this,
			'reaction'	=> $reaction
		] );
		
		/* Actually insert it */
		$idColumn = static::$databaseColumnId;
		\IPS\Db::i()->insert( 'core_reputation_index', array(
			'member_id'				=> $member->member_id,
			'app'					=> $app,
			'type'					=> static::reactionType(),
			'type_id'				=> $this->$idColumn,
			'rep_date'				=> \IPS\DateTime::create()->getTimestamp(),
			'rep_rating'			=> $reaction->value,
			'member_received'		=> $owner->member_id,
			'rep_class'				=> static::reactionClass(),
			'class_type_id_hash'	=> md5( static::reactionClass() . ':' . $this->$idColumn ),
			'item_id'				=> $itemId,
			'reaction'				=> $reaction->id
		) );

		/* Send the notification but only if we aren't reacting to our own content, we can view the content, the user isn't ignored and we aren't changing from one reaction to another */
		if ( $this->author()->member_id AND $this->author() != \IPS\Member::loggedIn() AND $this->canView( $owner ) AND !$reacted AND !$member->isIgnoring( $this->author(), 'posts' ) )
		{
			$notification = new \IPS\Notification( \IPS\Application::load('core'), 'new_likes', $this, array( $this, $member ), array(), TRUE, \IPS\Content\Reaction::isLikeMode() ? NULL : 'notification_new_react' );
			$notification->recipients->attach( $owner );
			$notification->send();
		}
		
		if ( $owner->member_id )
		{
			$owner->pp_reputation_points += $reaction->value;
			$owner->save();
		}

		/* Reset some cached values */
		$this->_reactionCount	= NULL;
		$this->_reactions		= NULL;

		$this->hasReacted[ $member->member_id ] = $reaction;

		if( \IPS\Application::appIsEnabled( 'cloud' ) and \IPS\cloud\Realtime::i()->isEnabled('realtime') )
		{
			$this->reactions();
			\IPS\cloud\Realtime::i()->publishEvent( 'reaction-count', array('count' => $this->_reactionCount) );
		}

		if( \IPS\Application::appIsEnabled( 'cloud' ) and \IPS\cloud\Realtime::i()->isEnabled('trending') )
		{
			/* We need to make sure we're using the item */
			$item = ( ! $this instanceof \IPS\Content\Item ) ? $this->item() : $this;
			$itemIdColumn	= $item::$databaseColumnId;
			$itemId			= $item->$itemIdColumn;

			try
			{
				\IPS\Redis::i()->zIncrBy( 'trending', time() * 0.4, \get_class( $item ) .'__' . $itemId );
			}
			catch( \BadMethodCallException | \RedisException $e ) {}
		}
	}
	
	/**
	 * Remove Reaction
	 *
	 * @param	\IPS\Member|NULL		$member					The member, or NULL for currently logged in member
	 * @param	bool					$removeNotifications	Whether to remove notifications or not
	 * @return	void
	 */
	public function removeReaction( \IPS\Member $member = NULL, $removeNotifications = TRUE )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		try
		{
			try
			{
				$idColumn	= static::$databaseColumnId;
				
				$where = $this->getReactionWhereClause( NULL, FALSE );
				$where[] = array( 'member_id=?', $member->member_id );
				$rep		= \IPS\Db::i()->select( '*', 'core_reputation_index', $where )->first();
			}
			catch( \UnderflowException $e )
			{
				throw new \OutOfRangeException;
			}
			
			$memberReceived		= \IPS\Member::load( $rep['member_received'] );
			$reaction			= \IPS\Content\Reaction::load( $rep['reaction'] );
		}
		catch( \OutOfRangeException $e )
		{
			throw new \DomainException;
		}
		
		if( \IPS\Db::i()->delete( 'core_reputation_index', array( "app=? AND type=? AND type_id=? AND member_id=?", static::$application, static::reactionType(), $this->$idColumn, $member->member_id ) ) )
		{
			if ( $memberReceived->member_id )
			{
				$memberReceived->pp_reputation_points = $memberReceived->pp_reputation_points - $reaction->value;
				$memberReceived->save();
			}

			/* Remove Notifications */
			if( $removeNotifications === TRUE )
			{
				$memberIds	= array();

				foreach( \IPS\Db::i()->select( '`member`', 'core_notifications', array( 'notification_key=? AND item_class=? AND item_id=?', 'new_likes', (string) \get_class( $this ), (int) $this->$idColumn ) ) as $memberToRecount )
				{
					$memberIds[ $memberToRecount ]	= $memberToRecount;
				}

				\IPS\Db::i()->delete( 'core_notifications', array( 'notification_key=? AND item_class=? AND item_id=?', 'new_likes', (string) \get_class( $this ), (int) $this->$idColumn ) );

				foreach( $memberIds as $memberToRecount )
				{
					\IPS\Member::load( $memberToRecount )->recountNotifications();
				}
			}
		}

		/* Reset some cached values */
		$this->_reactionCount	= NULL;
		$this->_reactions		= NULL;

		if( isset( $this->hasReacted[ $member->member_id ] ) )
		{
			unset( $this->hasReacted[ $member->member_id ] );
		}
	}
	
	/**
	 * Can React
	 *
	 * @param	\IPS\Member|NULL		$member	The member, or NULL for currently logged in
	 * @return	bool
	 */
	public function canReact( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( $this instanceof \IPS\Content )
		{
			$owner = $this->author();
		}
		else if ( $this instanceof \IPS\Node\Model )
		{
			$owner = $this->owner();
		}
		
		/* Only members can react */
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		/* Cannot react to guest content */
		if ( !$owner->member_id )
		{
			return FALSE;
		}
		
		/* Protected Groups */
		if ( $owner->inGroup( explode( ',', \IPS\Settings::i()->reputation_protected_groups ) ) )
		{
			return FALSE;
		}
		
		/* Reactions per day */
		if ( $member->group['g_rep_max_positive'] == 0 )
		{
			return FALSE;
		}
		
		/* React to own content */
		if ( !\IPS\Settings::i()->reputation_can_self_vote AND $this->author()->member_id == $member->member_id )
		{
			return FALSE;
		}
		
		/* Unacknowledged warnings */
		if ( $member->members_bitoptions['unacknowledged_warnings'] )
		{
			return FALSE;
		}
		
		/* Still here? All good. */
		return TRUE;
	}
	
	/**
	 * @brief	Reactions Cache
	 */
	protected $_reactions = NULL;
	
	/**
	 * Reactions
	 *
	 * @return	array
	 */
	public function reactions()
	{
		if ( $this->_reactionCount === NULL )
		{
			$this->_reactionCount = 0;
		}
		
		if ( $this->_reactions === NULL )
		{
			$idColumn	= static::$databaseColumnId;
			$this->_reactions = array();
			
			if ( \is_array( $this->reputation ) )
			{
				if ( $enabledReactions = \IPS\Content\Reaction::enabledReactions() )
				{
					foreach( $this->reputation AS $memberId => $reactionId )
					{
						if( isset( $enabledReactions[ $reactionId ] ) )
						{
							$this->_reactionCount += $enabledReactions[ $reactionId ]->value;
							$this->_reactions[ $memberId ][] = $reactionId;
						}
					}
				}
			}
			else
			{
				/* Set the data in $this->reputation to save queries later */
				$this->reputation = array();
				foreach( \IPS\Db::i()->select( '*', 'core_reputation_index', $this->getReactionWhereClause() )->join( 'core_reactions', 'reaction=reaction_id' ) AS $reaction )
				{
					$this->reputation[ $reaction['member_id'] ] = $reaction['reaction'];
					$this->_reactions[ $reaction['member_id'] ][] = $reaction['reaction'];
					$this->_reactionCount += $reaction['rep_rating'];
				}
			}
		}
		
		return $this->_reactions;
	}
	
	/**
	 * @brief Reaction Count
	 */
	protected $_reactionCount = NULL;
	
	/**
	 * Reaction Count
	 *
	 * @return int
	 */
	public function reactionCount()
	{
		if( $this->_reactionCount === NULL )
		{
			$this->reactions();
		}

		return $this->_reactionCount;
	}
	
	/**
	 * Reaction Where Clause
	 *
	 * @param	\IPS\Content\Reaction|array|int|NULL	$reactions			This can be any one of the following: An \IPS\Content\Reaction object, an array of \IPS\Content\Reaction objects, an integer, or an array of integers, or NULL
	 * @param	bool									$enabledTypesOnly 	If TRUE, only reactions of the enabled reaction types will be included (must join core_reactions)
	 * @return	array
	 */
	public function getReactionWhereClause( $reactions = NULL, $enabledTypesOnly=TRUE )
	{
		$idColumn = static::$databaseColumnId;
		$where = array( array( 'rep_class=? AND type=? AND type_id=?', static::reactionClass(), static::reactionType(), $this->$idColumn ) );
		
		if ( $enabledTypesOnly )
		{
			$where[] = array( 'reaction_enabled=1' );
		}
		
		if ( $reactions !== NULL )
		{
			if ( !\is_array( $reactions ) )
			{
				$reactions = array( $reactions );
			}
			
			$in = array();
			foreach( $reactions AS $reaction )
			{
				if ( $reaction instanceof \IPS\Content\Reaction )
				{
					$in[] = $reaction->id;
				}
				else
				{
					$in[] = $reaction;
				}
			}
			
			if ( \count( $in ) )
			{
				$where[] = array( \IPS\Db::i()->in( 'reaction', $in ) );
			}
		}
		
		return $where;
	}
	
	/**
	 * Reaction Table
	 *
	 * @param	\IPS\Content\Reaction|int|NULL	$reaction			This can be any one of the following: An \IPS\Content\Reaction object, an integer, or NULL
	 * @return	\IPS\Helpers\Table\Db
	 */
	public function reactionTable( $reaction=NULL )
	{
		if ( !\IPS\Member::loggedIn()->group['gbw_view_reps'] or !$this->canView() )
		{
			throw new \DomainException;
		}
		
		$idColumn = static::$databaseColumnId;
		
		$table = new \IPS\Helpers\Table\Db( 'core_reputation_index', $this->url('showReactions'), $this->getReactionWhereClause( $reaction ) );
		$table->sortBy			= 'rep_date';
		$table->sortDirection	= 'desc';
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' ), 'reactionLogTable' );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' ), 'reactionLog' );
		$table->joins = array( array( 'from' => 'core_reactions', 'where' => 'reaction=reaction_id' ) );

		$table->parsers = array(
			'rep_date' => function( $date, $row )
			{
				if ( isset( \IPS\Request::i()->item ) and \IPS\Request::i()->item )
				{
					/* This is an item level thing, and not a comment level thing */
					try
					{
						$class = $row['rep_class'];
						$item = $class::load( $row['type_id'] );

						return \IPS\Member::loggedIn()->language()->addToStack( '_defart_from_date', FALSE, array( 'htmlsprintf' => array( $item->url(), $item->mapped('title'), \IPS\DateTime::ts( $date )->html() ) ) );
					}
					catch( \Exception $e )
					{
						return \IPS\DateTime::ts( $date )->html();
					}
				}
				return \IPS\DateTime::ts( $date )->html();
			}
		);

		$table->rowButtons = function( $row )
		{
			return array(
				'delete'	=> array(
					'icon'	=> 'times-circle',
					'title'	=> 'delete',
					'link'	=> $this->url( 'unreact' )->csrf()->setQueryString( array( 'member' => $row['member_id'] ) ),
					'data' 	=> array( 'confirm' => TRUE )
				)
			);
		};
		
		return $table;
	}

	/**
	 * @brief	Cached Reacted
	 */
	protected $hasReacted = array();

	/**
	 * Has reacted?
	 *
	 * @param	\IPS\Member|NULL	$member	The member, or NULL for currently logged in
	 * @return	\IPS\Content\Reaction|FALSE
	 */
	public function reacted( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		if( !isset( $this->hasReacted[ $member->member_id ] ) )
		{
			$this->hasReacted[ $member->member_id ] = FALSE;

			try
			{
				if ( \is_array( $this->reputation ) )
				{
					if ( isset( $this->reputation[ $member->member_id ] ) )
					{
						$this->hasReacted[ $member->member_id ] = \IPS\Content\Reaction::load( $this->reputation[ $member->member_id ] );
					}
				}
				else
				{
					$where = $this->getReactionWhereClause( NULL, FALSE );
					$where[] = array( 'member_id=?', $member->member_id );
					$this->hasReacted[ $member->member_id ] = \IPS\Content\Reaction::load( \IPS\Db::i()->select( 'reaction', 'core_reputation_index', $where )->first() );
				}
			}
			catch( \UnderflowException $e ){}
		}

		return $this->hasReacted[ $member->member_id ];
	}
	
	/**
	 * @brief	Cached React Blurb
	 */
	public $reactBlurb = NULL;
	
	/**
	 * React Blurb
	 *
	 * @return	array
	 */
	public function reactBlurb(): array
	{
		if ( $this->reactBlurb === NULL )
		{
			$this->reactBlurb = array();

			/*
			 	If we have lots of rows, then use a more efficient way of getting the data (but does increase query count and uses a group by).
				I know this is an artibrary number, but it should mean that most communities never need to run this code, and just those with topics with more than 4,000 pages
			*/
			if ( ( $this instanceof \IPS\Content\Item ) and isset( $this::$databaseColumnMap['num_comments'] ) and $this->mapped( 'num_comments' ) >= 100000 )
			{
				$enabledReactions = [];
				foreach( \IPS\Content\Reaction::enabledReactions() as $reaction )
				{
					$enabledReactions[] = $reaction->id;
				}

				foreach( \IPS\Db::i()->select( 'COUNT(*) as count, reaction', 'core_reputation_index', $this->getReactionWhereClause( NULL , FALSE ), 'count DESC', NULL, 'reaction' ) as $row )
				{
					if( in_array( needle: $row['reaction'], haystack: $enabledReactions ) )
					{
						$this->reactBlurb[ $row['reaction'] ] = $row['count'];
					}
				}
			}
			else
			{
				if ( \count( $this->reactions() ) )
				{
					$idColumn = static::$databaseColumnId;
					if ( \is_array( $this->_reactions ) )
					{
						foreach ( $this->_reactions as $memberId => $reactions )
						{
							foreach ( $reactions as $reaction )
							{
								if ( !isset( $this->reactBlurb[$reaction] ) )
								{
									$this->reactBlurb[$reaction] = 0;
								}

								$this->reactBlurb[$reaction]++;
							}
						}
					}
					else
					{
						foreach ( \IPS\Db::i()->select( 'reaction', 'core_reputation_index', $this->getReactionWhereClause() )->join( 'core_reactions', 'reaction=reaction_id' ) as $rep )
						{
							if ( !isset( $this->reactBlurb[$rep] ) )
							{
								$this->reactBlurb[$rep] = 0;
							}

							$this->reactBlurb[$rep]++;
						}
					}

					/* Error suppressor for https://bugs.php.net/bug.php?id=50688 */
					$enabledReactions = \IPS\Content\Reaction::enabledReactions();

					@uksort( $this->reactBlurb, function ( $a, $b ) use ( $enabledReactions ) {
						$positionA = $enabledReactions[$a]->position;
						$positionB = $enabledReactions[$b]->position;

						if ( $positionA == $positionB )
						{
							return 0;
						}

						return ( $positionA < $positionB ) ? -1 : 1;
					} );
				}
				else
				{
					$this->reactBlurb = array();
				}
			}
		}

		return $this->reactBlurb;
	}
	
	/**
	 * @brief	Cached like blurb
	 */
	public $likeBlurb	= NULL;
	
	/**
	 * Who Reacted
	 *
	 * @param	bool|NULL	$isLike	Use like text instead? NULL to automatically determine
	 * @return	string
	 */
	public function whoReacted( $isLike = NULL )
	{
		if ( $isLike === NULL )
		{
			$isLike =  \IPS\Content\Reaction::isLikeMode();
		}
		
		if( $this->likeBlurb === NULL )
		{
			$langPrefix = 'react_';
			if ( $isLike )
			{
				$langPrefix = 'like_';
			}

			/* Did anyone like it? */
			$numberOfLikes = \count( $this->reactions() ); # int
			if ( $numberOfLikes )
			{				
				/* Is it just us? */
				$userLiked = ( $this->reacted() );
				if ( $userLiked and $numberOfLikes < 2 )
				{
					$this->likeBlurb = \IPS\Member::loggedIn()->language()->addToStack("{$langPrefix}blurb_just_you");
				}
				/* Nope, we need to display a number... */
				else
				{
					$peopleToDisplayInMainView = array();
					$peopleToDisplayInSecondaryView = array(); // This is only used for "like" mode (i.e. there's only 1 reputation type)
					$andXOthers = $numberOfLikes;

					/* If the user liked, we always show "You" first */
					if ( $userLiked )
					{
						$peopleToDisplayInMainView[] = \IPS\Member::loggedIn()->language()->addToStack("{$langPrefix}blurb_you_and_others");
						$andXOthers--;
					}
										
					/* Some random names */
					$i = 0;
					$app = explode( '\\', static::reactionClass() );
					if ( \strtolower( $app[1] ) === $app[1] )
					{
						$app = $app[1];
					}
					else
					{
						$app = 'core';
					}
					$where = $this->getReactionWhereClause();
					$where[] = array( 'member_id!=?', \IPS\Member::loggedIn()->member_id ?: 0 );
					foreach ( \IPS\Db::i()->select( '*', 'core_reputation_index', $where, 'RAND()', \IPS\Content\Reaction::isLikeMode() ? 18 : ( $userLiked ? 2 : 3 ) )->join( 'core_reactions', 'reaction=reaction_id' ) as $rep )
					{
						if ( $i < ( $userLiked ? 2 : 3 ) )
						{
							$peopleToDisplayInMainView[] = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->userLink( \IPS\Member::load( $rep['member_id'] ) );
							$andXOthers--;
						}
						else
						{
							$peopleToDisplayInSecondaryView[] = htmlspecialchars( \IPS\Member::load( $rep['member_id'] )->name, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE );
						}
						$i++;
					}
					
					/* If there's people to display in the secondary view, add that */
					if ( $andXOthers )
					{
						if ( \count( $peopleToDisplayInSecondaryView ) < $andXOthers )
						{
							$peopleToDisplayInSecondaryView[] = \IPS\Member::loggedIn()->language()->addToStack( "{$langPrefix}blurb_others_secondary", FALSE, array( 'pluralize' => array( $andXOthers - \count( $peopleToDisplayInSecondaryView ) ) ) );
						}
						$peopleToDisplayInMainView[] = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->reputationOthers( $this->url( 'showReactions' ), \IPS\Member::loggedIn()->language()->addToStack( "{$langPrefix}blurb_others", FALSE, array( 'pluralize' => array( $andXOthers ) ) ), json_encode( $peopleToDisplayInSecondaryView ) );
					}
					
					/* Put it all together */
					$this->likeBlurb = \IPS\Member::loggedIn()->language()->addToStack( "{$langPrefix}blurb", FALSE, array( 'pluralize' => array( $numberOfLikes ), 'htmlsprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $peopleToDisplayInMainView ) ) ) );
				}				
			}
			/* Nobody liked it - show nothing */
			else
			{
				$this->likeBlurb = '';
			}
		}
				
		return $this->likeBlurb;
	}

	/**
	 * Return boolean indicating if *any* reaction elements are available to the user
	 * Provides a convenient way of reducing logic in frontend templates rather than having to check every condition.
	 *
	 * @return	boolean
	 */
	public function hasReactionBar()
	{
		/* If we're in count mode and have > 0 */
		if ( \IPS\Settings::i()->reaction_count_display == 'count' && $this->reactionCount() )
		{
			return TRUE;
		}

		/* Not in count mode and have some blurb to show */
		if( \IPS\Settings::i()->reaction_count_display !== 'count' && $this->reactBlurb() && \count( $this->reactBlurb() ) )
		{
			return TRUE;
		}

		if( $this->canReact() )
		{
			return TRUE;
		}

		return FALSE;
	}
}