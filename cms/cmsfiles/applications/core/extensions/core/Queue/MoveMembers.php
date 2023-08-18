<?php
/**
 * @brief		Background Task: Move members
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		8 Jun 2016
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Move members
 */
class _MoveMembers
{

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$data['count'] = $this->getQuery( 'COUNT(*)', $data )->first();

		if( $data['count'] == 0 )
		{
			return null;
		}

		/* Skip this task if we're trying to move them to the same group */
		if( isset( $data['oldGroup'] ) AND $data['oldGroup'] AND $data['oldGroup'] === $data['group'] )
		{
			return NULL;
		}

		return $data;
	}

	/**
	 * Run Background Task
	 *
	 * @param	mixed						$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int							$offset	Offset
	 * @return	int							New offset
	 * @throws	\IPS\Task\Queue\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( $data, $offset )
	{
		$data['where'][] = array( 'core_members.member_id > ?' , $offset );

		$select	= $this->getQuery( 'core_members.*', $data, $offset );

		if ( !$select->count() or $offset > $data['count'] )
		{
			if( isset( $data['oldGroup'] ) AND $data['oldGroup'] )
			{
				$cacheKey = 'groupMembersCount_' . $data['oldGroup'];
				unset( \IPS\Data\Store::i()->$cacheKey );
			}

			$cacheKey = 'groupMembersCount_' . $data['group'];
			unset( \IPS\Data\Store::i()->$cacheKey );

			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		try
		{
			$newGroup = \IPS\Member\Group::load( $data['group'] );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$by = isset( $data['by'] ) ? \IPS\Member::load( $data['by'] ) : FALSE;

		foreach( $select AS $row )
		{
			try
			{
				$member = \IPS\Member::constructFromData( $row );

				/* Is this member an admin? */
				if ( $member->isAdmin() AND ( !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_edit_admin' ) OR !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_move_admin1' ) ) )
				{
					throw new \OutOfBoundsException;
				}

				/* Member is already in this group? */
				if ( $member->inGroup( $data['group'] ) )
				{
					$extraGroups	= array_filter( explode( ',', $member->mgroup_others ) );

					$member->mgroup_others = implode( ',', array_diff( $extraGroups, array( $newGroup->g_id ) ) );
				}

				/* If the member has a secondary group that includes the 'old' group, we need to clear it out */
				if( isset( $data['oldGroup'] ) AND $data['oldGroup'] AND $member->inGroup( $data['oldGroup'] ) )
				{
					$extraGroups	= array_filter( explode( ',', $member->mgroup_others ) );

					$member->mgroup_others = implode( ',', array_diff( $extraGroups, array( $data['oldGroup'] ) ) );
				}
				
				if ( $member->member_group_id != $newGroup->g_id )
				{
					$member->logHistory( 'core', 'group', array( 'type' => 'primary', 'by' => 'mass', 'old' => $member->member_group_id, 'new' => $newGroup->g_id ), $by );
				}
				$member->member_group_id = $newGroup->g_id;
				$member->save();
			}
			catch( \Exception $e ) { }

			$offset++;
		}

		return $offset;
	}

	/**
	 * Return the query
	 *
	 * @param	string	$select		What to select
	 * @param	array	$data		Queue data
	 * @param	int		$offset		Offset to use (FALSE to not apply limit)
	 * @return	\IPS\Db\Select
	 */
	protected function getQuery( $select, $data, $applyLimit=FALSE )
	{
		return \IPS\Db::i()->select( $select, 'core_members', $data['where'], 'core_members.member_id ASC', ( $offset !== FALSE ) ? array( $offset, \IPS\REBUILD_SLOW ) : array() )
			->join( 'core_pfields_content', 'core_members.member_id=core_pfields_content.member_id' )
			->join( array( 'core_validating', 'v' ), 'v.member_id=core_members.member_id')
			->join( array( 'core_admin_permission_rows', 'm' ), "m.row_id=core_members.member_id AND m.row_id_type='member'" )
			->join( array( 'core_admin_permission_rows', 'g' ), array( 'g.row_id', \IPS\Db::i()->select( 'row_id', array( 'core_admin_permission_rows', 'sub' ), array( "((sub.row_id=core_members.member_group_id OR FIND_IN_SET( sub.row_id, core_members.mgroup_others ) ) AND sub.row_id_type='group') AND g.row_id_type='group'" ), NULL, array( 0, 1 ) ) ) );
	}

	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array( 'text' => 'Doing something...', 'complete' => 50 )	Text explaining task and percentage complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function getProgress( $data, $offset )
	{
		$text = \IPS\Member::loggedIn()->language()->addToStack('moving_members', FALSE, array() );

		return array( 'text' => $text, 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $offset, 2 ) ) : 100 );
	}
}