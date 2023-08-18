<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	convert
 * @since		16 Oct 2015
 */

namespace IPS\convert\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _RebuildGalleryImages
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data	Data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		try
		{
			$data['count'] = \IPS\Db::i()->select( 'count(image_id)', 'gallery_images' )->first();
		}
		catch( \Exception $e )
		{
			throw new \OutOfRangeException;
		}
		
		if( $data['count'] == 0 )
		{
			return NULL;
		}

		$data['completed'] = 0;
		
		return $data;
	}
	
	/**
	 * Run Background Task
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	int|null					New offset or NULL if complete
	 * @throws	\IPS\Task\Queue\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( &$data, $offset )
	{
		if ( !class_exists( 'IPS\gallery\Image' ) OR !\IPS\Application::appIsEnabled( 'gallery' ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		/* Intentionally no try/catch as it means app doesn't exist */
		try
		{
			$app = \IPS\convert\App::load( $data['app'] );
		}
		catch( \OutOfRangeException $e )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$last = NULL;
		
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'gallery_images', array( "image_id>?", $offset ), 'image_id ASC', array( 0, \IPS\REBUILD_SLOW ) ), 'IPS\gallery\Image' ) AS $image )
		{
			$data['completed']++;

			/* Is this converted content? */
			try
			{
				/* Just checking, we don't actually need anything */
				$app->checkLink( $image->id, 'gallery_images' );
			}
			catch( \OutOfRangeException $e )
			{
				$last = $image->id;
				continue;
			}

			try
			{
				$image->buildThumbnails();
				$image->save();
			}
			catch( \DomainException $e )
			{
				$image->delete();
			}
			/* File fails the image type test */
			catch( \InvalidArgumentException $e )
			{
				$image->delete();
			}
			
			$last = $image->id;
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
	 * @return	array	Text explaning task and percentage complete
	 */
	public function getProgress( $data, $offset )
	{
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack( 'queue_rebuilding_gallery_images' ), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $data['completed'], 2 ) ) : 100 );
	}	
}