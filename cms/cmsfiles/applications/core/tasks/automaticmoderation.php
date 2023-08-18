<?php
/**
 * @brief		automaticmoderation Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		08 Dec 2017
 */

namespace IPS\core\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * automaticmoderation Task
 */
class _automaticmoderation extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * If ran successfully, should return anything worth logging. Only log something
	 * worth mentioning (don't log "task ran successfully"). Return NULL (actual NULL, not '' or 0) to not log (which will be most cases).
	 * If an error occurs which means the task could not finish running, throw an \IPS\Task\Exception - do not log an error as a normal log.
	 * Tasks should execute within the time of a normal HTTP request.
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		if ( ! \IPS\Settings::i()->automoderation_enabled )
		{
			return NULL;
		}

		foreach( \IPS\Db::i()->select( '*', 'core_automatic_moderation_pending', NULL, 'pending_added ASC', array( 0, 50 ) ) as $pending )
		{
			/* Check report exists still */
			try
			{
				$report = \IPS\core\Reports\Report::load( $pending['pending_report_id'] );
			}
			catch( \OutofRangeException $e )
			{
				/* Report is missing */
				\IPS\Db::i()->delete( 'core_automatic_moderation_pending', array( 'pending_report_id=?', $pending['pending_report_id'] ) );
				continue;
			}
			
			/* Check object exists still */
			$className = $pending['pending_object_class'];
			
			try
			{
				$object = $className::load( $pending['pending_object_id'] );
			}
			catch( \OutofRangeException $e )
			{
				/* Object no longer exists */
				\IPS\Db::i()->delete( 'core_automatic_moderation_pending', array( 'pending_object_class=? and pending_object_id=?', $pending['pending_object_class'], $pending['pending_object_id'] ) );
				continue;
			}
			
			/* Check rule exists still */
			try
			{
				$rule = \IPS\core\Reports\Rules::load( $pending['pending_rule_id'] );
			}
			catch( \OutofRangeException $e )
			{
				/* Rule no longer exists */
				\IPS\Db::i()->delete( 'core_automatic_moderation_pending', array( 'pending_rule_id=?', $pending['pending_rule_id'] ) );
				continue;
			}

			/* Right, that's all sorted so process the bad boy */
			$hidden = FALSE;
			try
			{
				if ( $object instanceof \IPS\Content\Comment )
				{
					$item = $object->item();
					if ( $item and $item::$firstCommentRequired and $object->isFirst() )
					{
						/* Hide the item, not the object */
						$item->hide( FALSE, \IPS\core\Reports\Rules::getDefaultHideReason() );
						$hidden = TRUE;
					}
				}
			}
			catch( \Exception $e ) { }
			
			if ( $hidden === FALSE )
			{
				$object->hide( FALSE, \IPS\core\Reports\Rules::getDefaultHideReason() );
			}
			
			/* Remove from the pending queue */
			\IPS\Db::i()->delete( 'core_automatic_moderation_pending', array( 'pending_object_class=? and pending_object_id=?', $pending['pending_object_class'], $pending['pending_object_id'] ) );
			
			/* Send notification to mods */
			$moderators = array( 'm' => array(), 'g' => array() );
			foreach ( \IPS\Db::i()->select( '*', 'core_moderators' ) as $mod )
			{
				$canView = FALSE;
				if ( $mod['perms'] == '*' )
				{
					$canView = TRUE;
				}
				if ( $canView === FALSE )
				{
					$perms = json_decode( $mod['perms'], TRUE );
					
					if ( isset( $perms['can_view_reports'] ) AND $perms['can_view_reports'] === TRUE )
					{
						$canView = TRUE;
					}
				}
				if ( $canView === TRUE )
				{
					$moderators[ $mod['type'] ][] = $mod['id'];
				}
			}
			
			try
			{
				$latestReport = \IPS\Db::i()->select( '*', 'core_rc_reports', array( 'rid=?', $report->id ), 'date_reported DESC' )->first();
				$notification = new \IPS\Notification( \IPS\Application::load('core'), 'automatic_moderation', $report, array( $report, $latestReport, $object ) );
				foreach ( \IPS\Db::i()->select( '*', 'core_members', ( \count( $moderators['m'] ) ? \IPS\Db::i()->in( 'member_id', $moderators['m'] ) . ' OR ' : '' ) . \IPS\Db::i()->in( 'member_group_id', $moderators['g'] ) . ' OR ' . \IPS\Db::i()->findInSet( 'mgroup_others', $moderators['g'] ) ) as $member )
				{
					$notification->recipients->attach( \IPS\Member::constructFromData( $member ) );
				}
				
				$notification->send();
			}
			catch( \Exception $e ) { }
		}
		
		return NULL;
	}
	
	/**
	 * Cleanup
	 *
	 * If your task takes longer than 15 minutes to run, this method
	 * will be called before execute(). Use it to clean up anything which
	 * may not have been done
	 *
	 * @return	void
	 */
	public function cleanup()
	{
		
	}
}