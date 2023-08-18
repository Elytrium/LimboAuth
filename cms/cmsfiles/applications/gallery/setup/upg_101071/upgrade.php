<?php
/**
 * @brief		4.1.17 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		24 Oct 2016
 */

namespace IPS\gallery\setup\upg_101071;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.17 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Remove old columns if they exist
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$columns = array();

		if( \IPS\Db::i()->checkForColumn( 'gallery_images_uploads', 'upload_key' ) )
		{
			$columns[] = 'upload_key';
		}

		if( \IPS\Db::i()->checkForColumn( 'gallery_images_uploads', 'upload_album_id' ) )
		{
			$columns[] = 'upload_album_id';
		}

		if( \IPS\Db::i()->checkForColumn( 'gallery_images_uploads', 'upload_category_id' ) )
		{
			$columns[] = 'upload_category_id';
		}

		if( \IPS\Db::i()->checkForColumn( 'gallery_images_uploads', 'upload_file_directory' ) )
		{
			$columns[] = 'upload_file_directory';
		}

		if( \IPS\Db::i()->checkForColumn( 'gallery_images_uploads', 'upload_file_name_original' ) )
		{
			$columns[] = 'upload_file_name_original';
		}

		if( \IPS\Db::i()->checkForColumn( 'gallery_images_uploads', 'upload_file_size' ) )
		{
			$columns[] = 'upload_file_size';
		}

		if( \IPS\Db::i()->checkForColumn( 'gallery_images_uploads', 'upload_file_type' ) )
		{
			$columns[] = 'upload_file_type';
		}

		if( \IPS\Db::i()->checkForColumn( 'gallery_images_uploads', 'upload_medium_name' ) )
		{
			$columns[] = 'upload_medium_name';
		}

		if( \IPS\Db::i()->checkForColumn( 'gallery_images_uploads', 'upload_title' ) )
		{
			$columns[] = 'upload_title';
		}

		if( \IPS\Db::i()->checkForColumn( 'gallery_images_uploads', 'upload_description' ) )
		{
			$columns[] = 'upload_description';
		}

		if( \IPS\Db::i()->checkForColumn( 'gallery_images_uploads', 'upload_copyright' ) )
		{
			$columns[] = 'upload_copyright';
		}

		if( \IPS\Db::i()->checkForColumn( 'gallery_images_uploads', 'upload_exif' ) )
		{
			$columns[] = 'upload_exif';
		}

		if( \IPS\Db::i()->checkForColumn( 'gallery_images_uploads', 'upload_feature_flag' ) )
		{
			$columns[] = 'upload_feature_flag';
		}

		if( \IPS\Db::i()->checkForColumn( 'gallery_images_uploads', 'upload_geodata' ) )
		{
			$columns[] = 'upload_geodata';
		}

		if( \IPS\Db::i()->checkForColumn( 'gallery_images_uploads', 'upload_media_data' ) )
		{
			$columns[] = 'upload_media_data';
		}

		if( \IPS\Db::i()->checkForColumn( 'gallery_images_uploads', 'upload_thumbnail' ) )
		{
			$columns[] = 'upload_thumbnail';
		}

		if( \count( $columns ) )
		{
			\IPS\Db::i()->dropColumn( 'gallery_images_uploads', $columns );
		}

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Removing unused database columns";
	}
}