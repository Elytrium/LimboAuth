<?php
/**
 * @brief		4.4.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		12 Oct 2018
 */

namespace IPS\gallery\setup\upg_104000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.4.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Set the new "can download original images" column default value appropriately
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		if( \IPS\Settings::i()->gallery_use_watermarks )
		{
			\IPS\Db::i()->update( 'core_groups', array( 'g_download_original' => \IPS\gallery\Image::DOWNLOAD_ORIGINAL_WATERMARKED ) );
		}
		else
		{
			\IPS\Db::i()->update( 'core_groups', array( 'g_download_original' => \IPS\gallery\Image::DOWNLOAD_ORIGINAL_RAW ) );
		}

		unset( \IPS\Data\Store::i()->groups );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Setting new gallery group permissions";
	}

	/**
	 * Clean up orphaned club albums
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		/* Build query WHERE clause */
		$existingCategories = iterator_to_array( \IPS\Db::i()->select( 'category_id', 'gallery_categories' ) );
		$where = array( \IPS\Db::i()->in( 'album_category_id', $existingCategories , TRUE) );

		/* Do we even have any? Most won't */
		if( !\IPS\Db::i()->select( 'COUNT(*)', 'gallery_albums', $where )->first() )
		{
			return TRUE;
		}

		/* Create a temporary category to place these albums in */
		$category = new \IPS\gallery\Category;

		$category->saveForm( $category->formatFormValues( array(
			'category_name'		 	=> "Orphaned Albums",
			'category_description'	=> "Temporary category holding orphaned albums which will be removed automatically",
			'category_parent_id'	=> 0,
			'category_sort_options'	=> 'updated',
			'category_allow_albums'	=> 1
		) ) );
		$newCategoryId = $category->_id;

		/* Loop over albums, move to the new category, and then add a bg task to delete the album */
		foreach ( \IPS\Db::i()->select( 'album_id', 'gallery_albums', $where ) as $albumId )
		{
			/* "Move" the albums and images to the new category */
			\IPS\Db::i()->update( 'gallery_albums', array( 'album_category_id' => $newCategoryId ), array( 'album_id=?', $albumId ) );
			\IPS\Db::i()->update( 'gallery_images', array( 'image_category_id' => $newCategoryId ), array( 'image_album_id=?', $albumId ) );

			/* Add the bg task */
			\IPS\Task::queue( 'core', 'DeleteOrMoveContent', array( 'class' => 'IPS\gallery\Album', 'id' => $albumId, 'deleteWhenDone' => TRUE ), 4 );
		}

		/* And then add a bg task to delete our new category after the albums are deleted */
		\IPS\Task::queue( 'core', 'DeleteOrMoveContent', array( 'class' => 'IPS\gallery\Category', 'id' => $newCategoryId, 'deleteWhenDone' => TRUE ), 5 );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Removing orphaned albums";
	}
}