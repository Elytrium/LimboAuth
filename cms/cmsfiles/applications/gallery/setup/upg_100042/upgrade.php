<?php
/**
 * @brief		4.0.12 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		27 Jul 2015
 */

namespace IPS\gallery\setup\upg_100042;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.12 Upgrade Code
 */
class _Upgrade
{
	/**
	 * We need to fix image data if any is bad - this is a relatively fast process typically
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

		/* Loop over images */
		foreach( \IPS\Db::i()->select( '*', 'gallery_images', NULL, 'image_id ASC', array( $limit, $perCycle ) ) as $row )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			$imageData	= json_decode( $row['image_data'], TRUE );
			$rebuild	= false;
			
			if ( \is_array( $imageData ) )
			{
				foreach( $imageData as $size => $dimensions )
				{
					if( $dimensions[0] == 0 )
					{
						$rebuild = true;
						break;
					}
				}
			}
			else
			{
				$rebuild = true;
			}

			if( $rebuild === true )
			{
				/* We use try/catch because getimagesize() will throw an \ErrorException if the file no longer exists */
				try
				{
					$large = ( isset( $row['image_masked_file_name'] ) ) ? \IPS\File::get( 'gallery_Images', $row['image_masked_file_name'] )->getImageDimensions() : array( 0, 0 );
				}
				catch( \Exception $e )
				{
					$large = array( 0, 0 );
				}

				try
				{
					$medium = ( isset( $row['image_medium_file_name'] ) ) ? \IPS\File::get( 'gallery_Images', $row['image_medium_file_name'] )->getImageDimensions() : array( 0, 0 );
				}
				catch( \Exception $e )
				{
					$medium = array( 0, 0 );
				}

				try
				{
					$small = ( isset( $row['image_small_file_name'] ) ) ? \IPS\File::get( 'gallery_Images', $row['image_small_file_name'] )->getImageDimensions() : array( 0, 0 );
				}
				catch( \Exception $e )
				{
					$small = array( 0, 0 );
				}

				try
				{
					$thumb = ( isset( $row['image_thumb_file_name'] ) ) ? \IPS\File::get( 'gallery_Images', $row['image_thumb_file_name'] )->getImageDimensions() : array( 0, 0 );
				}
				catch( \Exception $e )
				{
					$thumb = array( 0, 0 );
				}

				$newData = array(
					'large'		=> $large,
					'medium'	=> $medium,
					'small'		=> $small,
					'thumb'		=> $thumb,
				);

				\IPS\Db::i()->update( 'gallery_images', array( 'image_data' => json_encode( $newData ) ), array( 'image_id=?', $row['image_id'] ) );
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
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'gallery_images' )->first();
		}

		return "Fixing stored image dimensions (Upgraded so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
	}
}