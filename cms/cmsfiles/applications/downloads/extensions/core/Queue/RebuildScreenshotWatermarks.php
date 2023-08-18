<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		01 Dec 2017
 */

namespace IPS\downloads\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _RebuildScreenshotWatermarks
{
	/**
	 * @brief Number of items to rebuild per cycle
	 */
	public $rebuild	= \IPS\REBUILD_INTENSE;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		try
		{
			$watermark = \IPS\Settings::i()->idm_watermarkpath ? \IPS\File::get( 'core_Theme', \IPS\Settings::i()->idm_watermarkpath )->contents() : NULL;
			$where = array( array( 'record_type=?', 'ssupload' ) );
			if ( !$watermark )
			{
				$where[] = array( 'record_no_watermark<>?', '' );
			}

			$data['count']		= \IPS\Db::i()->select( 'MAX(record_id)', 'downloads_files_records', $where )->first();
			$data['realCount']	= \IPS\Db::i()->select( 'COUNT(*)', 'downloads_files_records', $where )->first();
		}
		catch( \Exception $ex )
		{
			return NULL;
		}

		if( $data['count'] == 0 or $data['realCount'] == 0 )
		{
			return NULL;
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
		$last = NULL;
		$watermark = \IPS\Settings::i()->idm_watermarkpath ? \IPS\Image::create( \IPS\File::get( 'core_Theme', \IPS\Settings::i()->idm_watermarkpath )->contents() ) : NULL;

		$where = array( array( 'record_id>? AND record_type=?', $offset, 'ssupload' ) );
		if ( !$watermark )
		{
			$where[] = array( 'record_no_watermark<>?', '' );
		}

		$select = \IPS\Db::i()->select( '*', 'downloads_files_records', $where, 'record_id', array( 0, $this->rebuild ) );

		foreach ( $select as $row )
		{
			try
			{
				if ( $row['record_no_watermark'] )
				{
					$original = \IPS\File::get( 'downloads_Screenshots', $row['record_no_watermark'] );

					try
					{
						\IPS\File::get( 'downloads_Screenshots', $row['record_location'] )->delete();
						\IPS\File::get( 'downloads_Screenshots', $row['record_thumb'] )->delete();
					}
					catch ( \Exception $e ) { }

					if ( !$watermark )
					{
						\IPS\Db::i()->update( 'downloads_files_records', array(
							'record_location'		=> (string) $original,
							'record_thumb'			=> (string) $original->thumbnail( 'downloads_Screenshots' ),
							'record_no_watermark'	=> NULL
						), array( 'record_id=?', $row['record_id'] ) );

						$data['indexed']++;
						$last = $row['record_id'];

						continue;
					}
				}
				else
				{
					$original = \IPS\File::get( 'downloads_Screenshots', $row['record_location'] );
				}

				$image = \IPS\Image::create( $original->contents() );
				$image->watermark( $watermark );

				$newFile = \IPS\File::create( 'downloads_Screenshots', $original->originalFilename, $image );

				\IPS\Db::i()->update( 'downloads_files_records', array(
					'record_location'		=> (string) $newFile,
					'record_thumb'			=> (string) $newFile->thumbnail( 'downloads_Screenshots' ),
					'record_no_watermark'	=> (string) $original
				), array( 'record_id=?', $row['record_id'] ) );
			}
			catch ( \Exception $e ) { }

			$data['indexed']++;
			$last = $row['record_id'];
		}

		if( $last === NULL )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		return $last;
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
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('downloads_rebuilding_screenshots'), 'complete' => ( $data['realCount'] * $data['indexed'] ) > 0 ? round( ( $data['realCount'] * $data['indexed'] ) * 100, 2 ) : 0 );
	}
}