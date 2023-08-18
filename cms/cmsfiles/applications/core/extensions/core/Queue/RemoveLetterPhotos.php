<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		01 Aug 2017
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
class _RemoveLetterPhotos
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$data['count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_members', array( 'pp_photo_type=?', 'letter' ) )->first();
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
		$did = 0;

		foreach( \IPS\Db::i()->select( '*', 'core_members', array( 'pp_photo_type=?', 'letter' ), 'member_id ASC', \IPS\REBUILD_SLOW ) as $member )
		{
			try
			{
				$member = \IPS\Member::constructFromData( $member );

				if ( $member->pp_main_photo )
				{
					try
					{
						\IPS\File::get( 'core_Profile', $member->pp_main_photo )->delete();
					}
					catch ( \Exception $e ) {}
				}
				if ( $member->pp_thumb_photo )
				{
					try
					{
						\IPS\File::get( 'core_Profile', $member->pp_thumb_photo )->delete();
					}
					catch ( \Exception $e ) {}
				}

				$member->pp_main_photo = NULL;
				$member->pp_thumb_photo = NULL;
				$member->pp_photo_type = 'none';
				$member->save();
			}
			catch( \Exception $e ){}

			$did++;
			$data['done']++;
		}

		/* We are done */
		if( !$did )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		return $data['done'];
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
		$text = \IPS\Member::loggedIn()->language()->addToStack( 'cleanup_letter_photos', FALSE, array() );

		return array( 'text' => $text, 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $data['done'], 2 ) ) : 100 );
	}
}