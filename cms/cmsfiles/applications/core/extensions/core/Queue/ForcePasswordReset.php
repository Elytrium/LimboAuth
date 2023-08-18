<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		24 Sep 2019
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _ForcePasswordReset
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		/* Compile where */
		$where = array();
		$where[] = array( "core_members.temp_ban=0" );
		$where[] = array( "core_members.members_pass_hash!=''" );
		$where[] = array( "core_members.members_pass_hash IS NOT NULL" );
		$where[] = array( '( ! ' . \IPS\Db::i()->bitwiseWhere( \IPS\Member::$bitOptions['members_bitoptions'], 'password_reset_forced' ) . ' )' );

		foreach ( \IPS\Application::allExtensions( 'core', 'MemberFilter', FALSE, 'core' ) as $key => $extension )
		{
			if( method_exists( $extension, 'getQueryWhereClause' ) AND $extension->availableIn( 'passwordreset' ) )
			{
				/* Grab our fields and add to the form */
				if( !empty( $data[ $key ] ) )
				{
					if( $_where = $extension->getQueryWhereClause( $data[ $key ] ) )
					{
						if ( \is_string( $_where ) )
						{
							$_where = array( $_where );
						}
						
						$where	= array_merge( $where, $_where );
					}
				}
			}
		}
		
		$data['count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_members', $where )->first();
		$data['done'] = 0;
		
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
	public function run( &$data, $offset )
	{
		$lastId = ( isset( $data['lastId'] ) ) ? $data['lastId'] : 0;
		
		/* Compile where */
		$where = array();
		$where[] = array( "core_members.temp_ban=0" );
		$where[] = array( "core_members.members_pass_hash!=''" );
		$where[] = array( "core_members.members_pass_hash IS NOT NULL" );
		$where[] = array( "member_id>{$lastId}" );
		$where[] = array( '( ! ' . \IPS\Db::i()->bitwiseWhere( \IPS\Member::$bitOptions['members_bitoptions'], 'password_reset_forced' ) . ' )' );

		foreach ( \IPS\Application::allExtensions( 'core', 'MemberFilter', FALSE, 'core' ) as $key => $extension )
		{
			if( method_exists( $extension, 'getQueryWhereClause' ) AND $extension->availableIn( 'passwordreset' ) )
			{
				/* Grab our fields and add to the form */
				if( !empty( $data[ $key ] ) )
				{
					if( $_where = $extension->getQueryWhereClause( $data[ $key ] ) )
					{
						if ( \is_string( $_where ) )
						{
							$_where = array( $_where );
						}
						
						$where	= array_merge( $where, $_where );
					}
				}
			}
		}
		
		$done = 0;
		
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members', $where, 'member_id ASC', \IPS\REBUILD_SLOW ), 'IPS\Member' ) AS $member )
		{
			$lastId = $member->member_id;
			$member->forcePasswordReset();
			$done++;
		}
		
		if ( ! $done )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		$data['lastId'] = $lastId;
		$data['done'] += $done;
		return $lastId;
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
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack( 'forcing_password_resets' ), 'complete' => $data['done'] ? ( round( 100 / $data['done'] * $data['count'], 2 ) ) : 0 );
	}
}