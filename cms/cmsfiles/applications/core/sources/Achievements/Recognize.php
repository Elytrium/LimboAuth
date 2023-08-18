<?php
/**
 * @brief		Badge Model (as in, a representation of a badge a member *can* earn, not a badge a particular member *has* earned)
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		25 Mar 21
 */

namespace IPS\core\Achievements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Recognize
 */
class _Recognize extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_member_recognize';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'r_';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons = array();

	/**
	 * Get member object
	 *
	 * @return \IPS\Member
	 */
	public function get__given_by(): \IPS\Member
	{
		return \IPS\Member::load( $this->given_by );
	}

	/**
	 * Load a recognize row from the content item
	 *
	 * @param	\IPS\Content						$content		Content item that has been rewarded
	 * @return \IPS\core\Achievements\Recognize
	 */
	public static function loadFromContent( $content ): \IPS\core\Achievements\Recognize
	{
		$idField = $content::$databaseColumnId;

		/* Let any exceptions bubble up */
		return static::constructFromData(
			\IPS\Db::i()->select( '*', 'core_member_recognize', [ 'r_content_class=? and r_content_id=?', \get_class( $content ), $content->$idField ] )->first()
		);
	}

	/**
	 * Add a new recognize entry
	 *
	 * @param	\IPS\Content						$content		Content item that is being rewarded
	 * @param	\IPS\Member							$member			Member to award
	 * @param	int									$points			Number of points to add
	 * @param	\IPS\core\Achievements\Badge|NULL	$badge			Badge to assign (if any)
	 * @param	string								$message		Custom message (if any)
	 * @param	\IPS\Member							$awardedBy		Awarded by
	 * @param	bool								$showPublicly	Show this message to everyone
	 */
	public static function add( $content, \IPS\Member $member, $points, $badge, $message, \IPS\Member $awardedBy, $showPublicly=FALSE )
	{
		$idField = $content::$databaseColumnId;
		
		/* Add to database */
		$recognize = new static;
		$recognize->member_id = $member->member_id;
		$recognize->content_class = \get_class( $content );
		$recognize->content_id = $content->$idField;
		$recognize->added = time();
		$recognize->points = $points;
		$recognize->badge = $badge ? $badge->_id : 0;
		$recognize->message = $message;
		$recognize->given_by = $awardedBy->member_id;
		$recognize->public = $showPublicly;
		$recognize->save();

		if ( $badge )
		{
			$member->awardBadge( $badge, 0, 0, ['subject'], $recognize->id );
			$member->logHistory( 'core', 'badges', [ 'action' => 'manual', 'id' => $badge->_id, 'recognize' => $recognize->id ] );
		}
		if ( $points )
		{
			$member->logHistory( 'core', 'points', array('by' => 'manual', 'points' => $points, 'recognize' => $recognize->id ) );
			$member->awardPoints( $points, 0, [], ['subject'], $recognize->id );
		}

		$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'new_recognize', $member, [ $member, $recognize ], [ $recognize->id ] );
		$notification->recipients->attach( $member );
		$notification->send();
	}
	
	/**
	 * Return the content object
	 *
	 * @return \IPS\Content
	 */
	public function content()
	{
		$class = $this->content_class;
		return $class::loadAndCheckPerms( $this->content_id );
	}

	/**
	 * Wrapper to get content.
	 *
	 * @return	\IPS\Content\Item|NULL
	 * @note	This simply wraps content()
	 */
	public function contentWrapper()
	{
		/* Get container, if valid */
		$content = NULL;

		try
		{
			$content = $this->content();
		}
		catch( \BadMethodCallException | \OutOfRangeException $e ) { }

		return $content;
	}
	
	/**
	 * Return a badge, or null
	 * 
	 * @return NULL|\IPS\core\Achievements\Badge
	 */
	public function badge()
	{
		try
		{
			return \IPS\core\Achievements\Badge::load( $this->badge );
		}
		catch( \Exception $e ) { }
		
		return NULL;
	}
	
	/**
	 * Return a member or NULL if the member no longer exists
	 * 
	 * @return NULL|\IPS\Member
	 */
	public function awardedBy()
	{
		try
		{
			return \IPS\Member::load( $this->given_by );
		}
		catch( \Exception $e ) { }
		
		return NULL;
	}

	/**
	 * Remove the recognize and remove points / badges earned
	 *
	 * @return void
	 */
	public function delete()
	{
		try
		{
			$author = \IPS\Member::load( $this->member_id );

			if ( $this->points )
			{
				/* Wade does it this way so I guess there's a reason. And let the reason be love */
				\IPS\Db::i()->update( 'core_members', "achievements_points = achievements_points - " . \intval( $this->points ), [ 'member_id=?', $this->member_id ] );
				\IPS\Db::i()->delete( 'core_points_log', [ 'recognize=?', $this->id ] );
			}

			if ( $this->badge )
			{
				\IPS\Db::i()->delete( 'core_member_badges', [ 'member=? and recognize=?', $this->member_id, $this->id ] );
			}

			/* Remove notifications */
			\IPS\Db::i()->delete( 'core_notifications', array( 'item_class=? and notification_key=? and item_id=? and extra=?', 'IPS\Member', 'new_recognize', $this->member_id, '[' . $this->id . ']' ) );
		}
		catch( \Exception $e ) {}

		parent::delete();
	}
}