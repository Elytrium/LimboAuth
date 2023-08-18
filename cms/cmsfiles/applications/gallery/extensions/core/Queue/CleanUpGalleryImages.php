<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		06 Mar 2018
 */

namespace IPS\gallery\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _CleanUpGalleryImages
{
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
			$data['count']		= \IPS\Db::i()->select( 'COUNT(*)', 'gallery_images', array( 'image_media=?', 0 ) )->first();
		}
		catch( \Exception $ex )
		{
			throw new \OutOfRangeException;
		}

		if( $data['count'] == 0 )
		{
			return null;
		}

		$data['did']	= 0;

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
		if ( !\IPS\Application::appIsEnabled( 'gallery' ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$last     = NULL;
		foreach( \IPS\Db::i()->select( '*', 'gallery_images', array( 'image_media=? AND image_id > ?', 0, (int) $offset ), 'image_id ASC', \IPS\REBUILD_SLOW ) as $image )
		{
			$data['did']++;

			/* Update the sizes array to remove the images we no longer support */
			$sizes = json_decode( $image['image_data'], true );

			if( isset( $sizes['thumb'] ) )
			{
				unset( $sizes['thumb'] );
			}

			if( isset( $sizes['medium'] ) )
			{
				unset( $sizes['medium'] );
			}

			$update	= array( 'image_data' => json_encode( $sizes ) );

			/* If we have a medium but no large image, or a thumb but no small image, adapt as appropriate. Also if one image is mapped for
				multiple columns be sure we don't remove it if we still need it. */
			$keepMedium = FALSE;
			if( $image['image_medium_file_name'] == $image['image_masked_file_name'] )
			{
				$keepMedium = TRUE;
			}
			$keepThumb = FALSE;
			if( $image['image_thumb_file_name'] == $image['image_masked_file_name'] )
			{
				$keepThumb = TRUE;
			}

			if( !$image['image_masked_file_name'] AND $image['image_medium_file_name'] )
			{
				$image['image_masked_file_name']	= $image['image_medium_file_name'];
				$update['image_masked_file_name']	= $image['image_masked_file_name'];
				$keepMedium = TRUE;
			}

			if( !$image['image_small_file_name'] AND $image['image_thumb_file_name'] )
			{
				$image['image_small_file_name']		= $image['image_thumb_file_name'];
				$update['image_small_file_name']	= $image['image_small_file_name'];
				$keepThumb = TRUE;
			}

			\IPS\Db::i()->update( 'gallery_images', $update, array( 'image_id=?', $image['image_id'] ) );

			/* Delete the images we don't need now */
			if( !$keepMedium AND $image['image_medium_file_name'] )
			{
				try
				{
					\IPS\File::get( 'gallery_Images', $image['image_medium_file_name'] )->delete();
				}
				catch( \Exception $e ) {}
			}

			if( !$keepThumb AND $image['image_thumb_file_name'] )
			{
				try
				{
					\IPS\File::get( 'gallery_Images', $image['image_thumb_file_name'] )->delete();
				}
				catch( \Exception $e ) {}
			}
			$last = $image['image_id'];
		}
		
		if ( $last === NULL )
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
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('cleaning_up_gallery_images'), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $data['did'], 2 ) ) : 100 );
	}

	/**
	 * Perform post-completion processing
	 *
	 * @param	array	$data		Data returned from preQueueData
	 * @param	bool	$processed	Was anything processed or not? If preQueueData returns NULL, this will be FALSE.
	 * @return	void
	 */
	public function postComplete( $data, $processed = TRUE )
	{
		\IPS\Db::i()->query( "ALTER TABLE `" . \IPS\Db::i()->prefix . "gallery_images` DROP COLUMN image_thumb_file_name, DROP COLUMN image_medium_file_name" );
	}
}