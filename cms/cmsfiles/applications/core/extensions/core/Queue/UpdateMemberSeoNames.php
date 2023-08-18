<?php
/**
 * @brief		Background Task: Fix missing members_seo_names
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Jun 2014
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Rebuild posts
 */
class _UpdateMemberSeoNames
{
	/**
	 * @brief Number of items to rebuild per cycle
	 */
	public $rebuild	= \IPS\REBUILD_QUICK;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		\IPS\Log::debug( "Getting preQueueData", 'UpdateMemberSeoNames' );

		try
		{
			$data['count']		= \IPS\Db::i()->select( 'MAX(member_id)', 'core_members', array( 'members_seo_name = \'\'' ) )->first();
			$data['realCount']	= \IPS\Db::i()->select( 'COUNT(*)', 'core_members', array( 'members_seo_name = \'\'' ) )->first();
			
			/* We're going to use the < operator, so we need to ensure the most recent item is rebuilt */
		    $data['runMemberId'] = $data['count'] + 1;
		}
		catch( \Exception $ex )
		{
			throw new \OutOfRangeException;
		}

		\IPS\Log::debug( "PreQueue count for is " . $data['count'], 'UpdateMemberSeoNames' );

		if( $data['count'] == 0 )
		{
			return null;
		}

		$data['indexed']	= 0;
		
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
		\IPS\Log::debug( "Running, with an offset of " . $offset, 'UpdateMemberSeoNames' );
		$last = NULL;

		foreach( \IPS\Db::i()->select( '*', 'core_members', array( 'members_seo_name = \'\' and member_id < ?',  $data['runMemberId'] ), 'member_id DESC', array( 0, $this->rebuild ) ) as $member )
		{
			if ( empty( $member['name'] ) )
			{
				continue;
			}
			
			\IPS\Db::i()->update( 'core_members', array( 'members_seo_name' => \IPS\Http\Url\Friendly::seoTitle( $member['name'] ) ), array( 'member_id=?', $member['member_id'] ) );
			
			$last = $member['member_id'];
			
			$data['indexed']++;
		}
		
		/* Store the runPid for the next iteration of this Queue task. This allows the progress bar to show correctly. */
		$data['runMemberId'] = $last;
			
		if( $last === NULL )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		/* Return the number rebuilt so far, so that the rebuild progress bar text makes sense */
		return $data['indexed'];
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
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('rebuilding_member_seo_names'), 'complete' => $data['realCount'] ? ( round( 100 / $data['realCount'] * $data['indexed'], 2 ) ) : 100 );
	}	
}