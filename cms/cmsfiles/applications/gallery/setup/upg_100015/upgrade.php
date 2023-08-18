<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		18 Feb 2015
 */

namespace IPS\gallery\setup\upg_100015;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix gallery album serialization
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Some init */
		$did		= 0;
		$perCycle	= 1000;
		$limit		= 0;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= (int) \IPS\Request::i()->extra;
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'gallery_albums', NULL, 'album_id ASC', array( $limit, $perCycle ) ) as $album )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			$update = array();

			if( @unserialize( $album['album_last_x_images'] ) !== FALSE )
			{
				$update['album_last_x_images']	= json_encode( unserialize( $album['album_last_x_images'] ) );
			}

			if( @unserialize( $album['album_sort_options'] ) !== FALSE )
			{
				$_sort = unserialize( $album['album_sort_options'] );

				$_new = 'updated';

				switch( $_sort['key'] )
				{
					case 'image_rating':
						$_new = 'rating';
					break;

					case 'image_comments':
						$_new = 'num_comments';
					break;
				}

				$update['album_sort_options']	= $_new;
			}

			if( \count( $update ) )
			{
				\IPS\Db::i()->update( 'gallery_albums', $update, 'album_id=' . $album['album_id'] );
			}
		}

		/* And then continue */
		if( $did AND $did == $perCycle )
		{
			return $limit + $did;
		}
		else
		{
			unset( $_SESSION['_step1Count'] );

			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step1Count'] ) )
		{
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'gallery_albums' )->first();
		}

		return "Updating gallery album images (Updated so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
	}

	/**
	 * Fix references to small images that don't exist
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		/* Don't do this if we are coming from an older version as we've already done it */
		if( isset( $_SESSION['40_gallery_images_done'] ) )
		{
			unset( $_SESSION['40_gallery_images_done'] );
			return TRUE;
		}

		/* Some init */
		$did		= 0;
		$perCycle	= 200;
		$limit		= 0;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= (int) \IPS\Request::i()->extra;
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		/* Loop over images */
		foreach( \IPS\Db::i()->select( '*', 'gallery_images', NULL, 'image_id ASC', array( $limit, $perCycle ) ) as $row )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			$file = \IPS\File::get( 'gallery_Images', $row['image_small_file_name'] );

			/* If the size is 0 (or NULL more likely) it does not exist */
			if( !$file->filesize() )
			{
				$update = array( 'image_small_file_name' => $row['image_medium_file_name'] );

				$data = json_decode( $row['image_data'], TRUE );
				$data['small'] = $data['medium'];
				
				$update['image_data']	= json_encode( $data );

				\IPS\Db::i()->update( 'gallery_images', $update, array( 'image_id=?', $row['image_id'] ) );
			}
		}

		/* And then continue */
		if( $did AND $did == $perCycle )
		{
			return $limit + $did;
		}
		else
		{
			unset( $_SESSION['_step2Count'] );

			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step2Count'] ) )
		{
			$_SESSION['_step2Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'gallery_images' )->first();
		}

		return "Checking gallery images (Upgraded so far: " . ( ( $limit > $_SESSION['_step2Count'] ) ? $_SESSION['_step2Count'] : $limit ) . ' out of ' . $_SESSION['_step2Count'] . ')';
	}
	
	/**
	 * Finish - This is run after all apps have been upgraded
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 * @note	We opted not to let users run this immediately during the upgrade because of potential issues (it taking a long time and users stopping it or getting frustrated) but we can revisit later
	 */
	public function finish()
	{
		\IPS\Task::queue( 'core', 'RebuildItems', array( 'class' => 'IPS\gallery\Image' ), 3, array( 'class' ) );
		\IPS\Task::queue( 'core', 'RebuildItemCounts', array( 'class' => 'IPS\gallery\Image', 'count' => 0 ), 4, array( 'class' ) );
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\gallery\Category', 'count' => 0 ), 5, array( 'class' ) );
	}
}