<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		24 Oct 2014
 */

namespace IPS\gallery\setup\upg_50000;

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
	 * Conversion from 3.0
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Clean up albums from previous versions */
		if ( \IPS\Db::i()->checkForTable( 'gallery_albums' ) AND \IPS\Db::i()->checkForTable( 'gallery_albums_main' ) )
		{
			\IPS\Db::i()->dropTable( 'gallery_albums' );
		}

		/* Do we need to fix ratings table? */
		if ( \IPS\Db::i()->checkForColumn( 'gallery_ratings', 'id' ) )
		{
			\IPS\Db::i()->changeColumn( 'gallery_ratings', 'id', array(
				'name'				=> 'rate_id',
				'type'				=> 'bigint',
				'length'			=> 20,
				'allow_null'		=> false,
				'default'			=> 0,
				'auto_increment'	=> true
			) );

			\IPS\Db::i()->changeColumn( 'gallery_ratings', 'member_id', array(
				'name'				=> 'rate_member_id',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_ratings', 'rating_where', array(
				'name'				=> 'rate_type',
				'type'				=> 'varchar',
				'length'			=> 32,
				'allow_null'		=> false,
				'default'			=> 'image'
			) );

			\IPS\Db::i()->changeColumn( 'gallery_ratings', 'rating_foreign_id', array(
				'name'				=> 'rate_type_id',
				'type'				=> 'bigint',
				'length'			=> 20,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_ratings', 'rdate', array(
				'name'				=> 'rate_date',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_ratings', 'rate', array(
				'name'				=> 'rate_rate',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeIndex( 'gallery_ratings', 'rating_find_me', array(
				'name'				=> 'rating_find_me',
				'type'				=> 'key',
				'columns'			=> array( 'rate_member_id', 'rate_type', 'rate_type_id' )
			) );
		}

		/* Do we need to fix comments? */
		if ( \IPS\Db::i()->checkForColumn( 'gallery_comments', 'pid' ) )
		{
			\IPS\Db::i()->update( 'gallery_comments', array( 'edit_time' => 0 ), array( 'edit_time=? OR edit_time IS NULL', '' ) );

			\IPS\Db::i()->changeColumn( 'gallery_comments', 'pid', array(
				'name'				=> 'comment_id',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0,
				'auto_increment'	=> true
			) );

			\IPS\Db::i()->changeColumn( 'gallery_comments', 'edit_time', array(
				'name'				=> 'comment_edit_time',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_comments', 'author_id', array(
				'name'				=> 'comment_author_id',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_comments', 'author_name', array(
				'name'				=> 'comment_author_name',
				'type'				=> 'varchar',
				'length'			=> 255,
				'allow_null'		=> true,
				'default'			=> null
			) );

			\IPS\Db::i()->changeColumn( 'gallery_comments', 'ip_address', array(
				'name'				=> 'comment_ip_address',
				'type'				=> 'varchar',
				'length'			=> 46,
				'allow_null'		=> true,
				'default'			=> null
			) );

			\IPS\Db::i()->changeColumn( 'gallery_comments', 'post_date', array(
				'name'				=> 'comment_post_date',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_comments', 'comment', array(
				'name'				=> 'comment_text',
				'type'				=> 'text',
				'allow_null'		=> true,
				'default'			=> null
			) );

			\IPS\Db::i()->changeColumn( 'gallery_comments', 'approved', array(
				'name'				=> 'comment_approved',
				'type'				=> 'tinyint',
				'length'			=> 1,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_comments', 'img_id', array(
				'name'				=> 'comment_img_id',
				'type'				=> 'bigint',
				'length'			=> 20,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->addIndex( 'gallery_comments', array(
				'name'				=> 'comment_ip_address',
				'type'				=> 'key',
				'columns'			=> array( 'comment_ip_address' )
			) );

			\IPS\Db::i()->changeIndex( 'gallery_comments', 'img_id', array(
				'name'				=> 'img_id',
				'type'				=> 'key',
				'columns'			=> array( 'comment_img_id', 'comment_post_date' )
			) );
		}

		/* Do we need to update the images table? This isn't hard, but there are a lot of changes. */
		if ( \IPS\Db::i()->checkForColumn( 'gallery_images', 'id' ) )
		{
			\IPS\Db::i()->changeColumn( 'gallery_images', 'id', array(
				'name'				=> 'image_id',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0,
				'auto_increment'	=> true
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'member_id', array(
				'name'				=> 'image_member_id',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->addColumn( 'gallery_images', array(
				'name'				=> 'image_category_id',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'img_album_id', array(
				'name'				=> 'image_album_id',
				'type'				=> 'bigint',
				'length'			=> 20,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'caption', array(
				'name'				=> 'image_caption',
				'type'				=> 'varchar',
				'length'			=> 255,
				'allow_null'		=> false,
				'default'			=> ''
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'description', array(
				'name'				=> 'image_description',
				'type'				=> 'text',
				'length'			=> null,
				'allow_null'		=> TRUE,
				'default'			=> null
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'directory', array(
				'name'				=> 'image_directory',
				'type'				=> 'varchar',
				'length'			=> 255,
				'allow_null'		=> true,
				'default'			=> null
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'masked_file_name', array(
				'name'				=> 'image_masked_file_name',
				'type'				=> 'varchar',
				'length'			=> 255,
				'allow_null'		=> true,
				'default'			=> null
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'file_name', array(
				'name'				=> 'image_file_name',
				'type'				=> 'varchar',
				'length'			=> 255,
				'allow_null'		=> true,
				'default'			=> null
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'medium_file_name', array(
				'name'				=> 'image_medium_file_name',
				'type'				=> 'varchar',
				'length'			=> 255,
				'allow_null'		=> true,
				'default'			=> null
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'original_file_name', array(
				'name'				=> 'image_original_file_name',
				'type'				=> 'varchar',
				'length'			=> 255,
				'allow_null'		=> true,
				'default'			=> null
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'file_size', array(
				'name'				=> 'image_file_size',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'file_type', array(
				'name'				=> 'image_file_type',
				'type'				=> 'varchar',
				'length'			=> 50,
				'allow_null'		=> true,
				'default'			=> null
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'approved', array(
				'name'				=> 'image_approved',
				'type'				=> 'tinyint',
				'length'			=> 1,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'thumbnail', array(
				'name'				=> 'image_thumbnail',
				'type'				=> 'tinyint',
				'length'			=> 1,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'views', array(
				'name'				=> 'image_views',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'comments', array(
				'name'				=> 'image_comments',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'comments_queued', array(
				'name'				=> 'image_comments_queued',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'idate', array(
				'name'				=> 'image_date',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'ratings_total', array(
				'name'				=> 'image_ratings_total',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'ratings_count', array(
				'name'				=> 'image_ratings_count',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'rating', array(
				'name'				=> 'image_rating',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'lastcomment', array(
				'name'				=> 'image_last_comment',
				'type'				=> 'int',
				'length'			=> 10,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'pinned', array(
				'name'				=> 'image_pinned',
				'type'				=> 'tinyint',
				'length'			=> 1,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'media', array(
				'name'				=> 'image_media',
				'type'				=> 'tinyint',
				'length'			=> 1,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'credit_info', array(
				'name'				=> 'image_credit_info',
				'type'				=> 'text',
				'length'			=> null,
				'allow_null'		=> TRUE,
				'default'			=> null
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'copyright', array(
				'name'				=> 'image_copyright',
				'type'				=> 'varchar',
				'length'			=> 255,
				'allow_null'		=> true,
				'default'			=> null
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'metadata', array(
				'name'				=> 'image_metadata',
				'type'				=> 'text',
				'length'			=> null,
				'allow_null'		=> TRUE,
				'default'			=> null
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'media_thumb', array(
				'name'				=> 'image_media_thumb',
				'type'				=> 'varchar',
				'length'			=> 255,
				'allow_null'		=> true,
				'default'			=> null
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'caption_seo', array(
				'name'				=> 'image_caption_seo',
				'type'				=> 'varchar',
				'length'			=> 255,
				'allow_null'		=> false,
				'default'			=> ''
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'image_feature_flag', array(
				'name'				=> 'image_feature_flag',
				'type'				=> 'tinyint',
				'length'			=> 1,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->changeColumn( 'gallery_images', 'image_gps_show', array(
				'name'				=> 'image_gps_show',
				'type'				=> 'tinyint',
				'length'			=> 1,
				'allow_null'		=> false,
				'default'			=> 0
			) );

			\IPS\Db::i()->addIndex( 'gallery_images', array(
				'name'				=> 'album_id',
				'type'				=> 'key',
				'columns'			=> array( 'image_album_id', 'image_approved', 'image_date' )
			) );

			\IPS\Db::i()->addIndex( 'gallery_images', array(
				'name'				=> 'image_feature_flag',
				'type'				=> 'key',
				'columns'			=> array( 'image_feature_flag', 'image_date' )
			) );

			\IPS\Db::i()->addIndex( 'gallery_images', array(
				'name'				=> 'gb_select',
				'type'				=> 'key',
				'columns'			=> array( 'image_approved', 'image_parent_permission', 'image_date' ),
				'length'			=> array( null, 100, null )
			) );

			\IPS\Db::i()->addIndex( 'gallery_images', array(
				'name'				=> 'lastcomment',
				'type'				=> 'key',
				'columns'			=> array( 'image_last_comment', 'image_date' )
			) );
		}
		
		return TRUE;
	}

	/**
	 * Convert members album amd categories
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		if ( !\IPS\Db::i()->checkForColumn( 'gallery_albums_main', 'album_category_id' ) )
		{
			\IPS\Db::i()->addColumn( 'gallery_albums_main', array( "name" => "album_category_id", "type" => "int", "length" => 40, "allow_null" => false, "default" => '0', "comment" => "", "auto_increment" => false, "binary" => false ) );
		}
		
		try
		{
			$setting	= \IPS\Db::i()->select( '*', 'core_sys_conf_settings', array( 'conf_key=?', 'gallery_members_album' ) )->first();
		}
		catch( \UnderflowException $e )
		{
			$setting	= array(
				'conf_key'			=> 'gallery_members_album',
				'conf_value'		=> 0,
				'conf_default'		=> 0
			);

			\IPS\Db::i()->insert( 'core_sys_conf_settings', $setting );
			\IPS\Settings::i()->gallery_members_album	= 0;
		}

		\IPS\Settings::i()->clearCache();

		//-----------------------------------------
		// Get global albums and loop
		//-----------------------------------------

		$albumCatMap	= array();
		$imagesOnly		= array();
		$position		= 1;
		$options		= null;

		foreach( \IPS\Db::i()->select( '*', 'gallery_albums_main', "album_is_global=1", 'album_position ASC' ) as $album )
		{
			//-----------------------------------------
			// Fix older sort options
			//-----------------------------------------

			$options	= @unserialize( $album['album_sort_options'] );

			if( $options['key'] )
			{
				$options['key']	= ( $options['key'] == 'name' ) ? 'image_caption' : ( ( $options['key'] == 'idate' ) ? 'image_date' : ( ( $options['key'] == 'rating' ) ? 'image_rating' : ( ( $options['key'] == 'comments' ) ? 'image_comments' : ( ( $options['key'] == 'views' ) ? 'image_views' : $options['key'] ) ) ) );
				$album['album_sort_options']	= serialize($options);
			}

			$options	= null;

			//-----------------------------------------
			// Insert new category
			//-----------------------------------------

			$category	= array(
								'category_name'				=> $album['album_name'],
								'category_name_seo'			=> $album['album_name_seo'],
								'category_description'		=> $album['album_description'],
								'category_cover_img_id'		=> $album['album_cover_img_id'],
								'category_type'				=> ( $album['album_g_container_only'] == 1 ) ? 1 : 2,
								'category_sort_options'		=> $album['album_sort_options'],
								'category_allow_comments'	=> $album['album_allow_comments'],
								'category_allow_rating'		=> $album['album_allow_rating'],
								'category_approve_img'		=> $album['album_g_approve_img'],
								'category_approve_com'		=> $album['album_g_approve_com'],
								'category_rules'			=> $album['album_g_rules'],
								'category_after_forum_id'	=> $album['album_after_forum_id'],
								'category_watermark'		=> ( !$album['album_watermark'] ) ? 0 : ( ( $album['album_watermark'] == 2 ) ? 2 : 1 ),
								'category_can_tag'			=> $album['album_can_tag'],
								'category_preset_tags'		=> $album['album_preset_tags'],
								'category_position'			=> $position,
								);

			$category['category_id'] = \IPS\Db::i()->insert( 'gallery_categories', $category );

			if( $album['album_g_perms_thumbs'] == 'member' )
			{
				\IPS\Settings::i()->gallery_members_album	= $category['category_id'];
			}

			//-----------------------------------------
			// Insert permissions
			//-----------------------------------------

			$permissions	= array(
									'app'			=> 'gallery',
									'perm_type'		=> 'categories',
									'perm_type_id'	=> $category['category_id'],
									'perm_view'		=> $album['album_g_perms_view'] ?: '',
									'perm_2'		=> $album['album_g_perms_images'],
									'perm_3'		=> $album['album_g_perms_comments'],
									'perm_4'		=> $album['album_g_perms_comments'],
									'perm_5'		=> $album['album_g_perms_moderate'],
									);

			\IPS\Db::i()->insert( 'core_permission_index', $permissions );

			//-----------------------------------------
			// Update images in this category
			//-----------------------------------------

			\IPS\Db::i()->update( 'gallery_images', array( 'image_category_id' => $category['category_id'], 'image_album_id' => 0, 'image_parent_permission' => $album['album_g_perms_view'], 'image_privacy' => 0 ), 'image_album_id=' . $album['album_id'] );

			//-----------------------------------------
			// Store mapping
			//-----------------------------------------

			$position++;

			$albumCatMap[ $album['album_id'] ]	= array( 'album' => $album, 'category' => $category );

			if( $category['category_type'] == 2 )
			{
				$imagesOnly[]					= $category['category_id'];
			}
		}

		//-----------------------------------------
		// Fix album data
		//-----------------------------------------

		$foundMembersGallery	= 0;

		foreach( $albumCatMap as $albumId => $data )
		{
			//-----------------------------------------
			// Set subcategory parent association if necessary
			//-----------------------------------------

			if( $data['album']['album_parent_id'] )
			{
				\IPS\Db::i()->update( 'gallery_categories', array( 'category_parent_id' => $albumCatMap[ $data['album']['album_parent_id'] ]['category']['category_id'] ), 'category_id=' . $data['category']['category_id'] );
			}

			//-----------------------------------------
			// Move our child albums
			//-----------------------------------------

			\IPS\Db::i()->update( 'gallery_albums_main', array( 'album_category_id' => $data['category']['category_id'], 'album_parent_id' => 0 ), 'album_parent_id=' . $albumId );

			//-----------------------------------------
			// Fix members album cat association
			//-----------------------------------------

			if( $albumId == \IPS\Settings::i()->gallery_members_album )
			{
				\IPS\Settings::i()->gallery_members_album	= $data['category']['category_id'];
				$foundMembersGallery	= $data['category']['category_id'];

				\IPS\Db::i()->update( 'gallery_categories', array( 'category_type' => 1 ), 'category_id=' . $data['category']['category_id'] );
			}
		}

		//-----------------------------------------
		// If we didn't find a members gallery, make one
		//-----------------------------------------

		if( !$foundMembersGallery )
		{
			$category	= array(
								'category_name'				=> 'Temp global album for root member albums',
								'category_name_seo'			=> 'temp-global-album-for-root-member-albums',
								'category_description'		=> "This is a temporary global album that holds the member albums that didn't have the proper parent album set. This album has NO permissions and is not visible from the public side, please move the albums in the proper location.",
								'category_cover_img_id'		=> 0,
								'category_type'				=> 1,
								'category_sort_options'		=> '',
								'category_allow_comments'	=> 1,
								'category_allow_rating'		=> 1,
								'category_approve_img'		=> 0,
								'category_approve_com'		=> 0,
								'category_rules'			=> '',
								'category_after_forum_id'	=> 0,
								'category_watermark'		=> 0,
								'category_can_tag'			=> 1,
								'category_preset_tags'		=> '',
								'category_position'			=> $position,
								);

			$category['category_id'] = \IPS\Db::i()->insert( 'gallery_categories', $category );

			$foundMembersGallery		= $category['category_id'];
			\IPS\Settings::i()->gallery_members_album	= $category['category_id'];
		}

		//-----------------------------------------
		// Move any albums in a category with type 2 to members album cat
		//-----------------------------------------

		if( \count( $imagesOnly ) )
		{
			\IPS\Db::i()->update( 'gallery_albums_main', array( 'album_category_id' => $foundMembersGallery ), 'album_category_id IN(' . implode( ',', $imagesOnly ) . ')' );
		}

		//-----------------------------------------
		// Delete global albums
		//-----------------------------------------

		\IPS\Db::i()->delete( 'gallery_albums_main', 'album_is_global=1' );

		\IPS\Settings::i()->changeValues( array( 'gallery_members_album' => \IPS\Settings::i()->gallery_members_album ) );

		return TRUE;
	}

	/**
	 * Clean up missing and extraneous fields and indexes
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		//-----------------------------------------
		// No idea why, but sometimes this index disappears?
		//-----------------------------------------

		if( !\IPS\Db::i()->checkForIndex( 'gallery_comments', 'img_id' ) )
		{
			if( \IPS\Db::i()->checkForIndex( 'gallery_comments', 'comment_img_id' ) )
			{
				\IPS\Db::i()->dropIndex( 'gallery_comments', 'comment_img_id' );
			}

			\IPS\Db::i()->addIndex( 'gallery_comments', array(
				'type'			=> 'index',
				'name'			=> 'img_id',
				'columns'		=> array( 'comment_img_id', 'comment_post_date' )
			) );
		}

		//-----------------------------------------
		// Add new columns
		//-----------------------------------------

		\IPS\Db::i()->addColumn( 'gallery_albums_main', array( "name" => "album_type", "type" => "INT", "length" => 10, "allow_null" => false, "default" => 0, "comment" => "", "auto_increment" => false, "binary" => false ) );
		\IPS\Db::i()->addColumn( 'gallery_albums_main', array( "name" => "album_last_x_images", "type" => "TEXT", "length" => null, "allow_null" => true, "default" => null, "comment" => "", "auto_increment" => false, "binary" => false ) );

		//-----------------------------------------
		// Change existing columns
		//-----------------------------------------

		\IPS\Db::i()->changeColumn( 'gallery_albums_main', "album_allow_comments", array( "name" => "album_allow_comments", "type" => "TINYINT", "length" => 1, "allow_null" => false, "default" => 0, "comment" => "", "auto_increment" => false, "binary" => false ) );
		\IPS\Db::i()->changeColumn( 'gallery_albums_main', "album_allow_rating", array( "name" => "album_allow_rating", "type" => "TINYINT", "length" => 1, "allow_null" => false, "default" => 0, "comment" => "", "auto_increment" => false, "binary" => false ) );
		\IPS\Db::i()->changeColumn( 'gallery_albums_main', "album_watermark", array( "name" => "album_watermark", "type" => "TINYINT", "length" => 1, "allow_null" => false, "default" => 0, "comment" => "", "auto_increment" => false, "binary" => false ) );

		//-----------------------------------------
		// Delete old columns
		//-----------------------------------------

		\IPS\Db::i()->dropColumn( 'gallery_albums_main', array(
			'album_is_global', 'album_is_profile', 'album_cache', 'album_node_level', 'album_node_left', 'album_preset_tags',
			'album_node_right', 'album_g_approve_img', 'album_g_approve_com', 'album_g_bitwise', 'album_g_rules',
			'album_g_container_only', 'album_g_perms_thumbs', 'album_g_perms_view', 'album_g_perms_images', 'album_g_perms_comments',
			'album_g_perms_moderate', 'album_g_latest_imgs', 'album_detail_default', 'album_child_tree', 'album_parent_tree', 'album_can_tag'
		) );
		
		return TRUE;
	}

	/**
	 * Convert albums step 1
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		$perCycle	= 100;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );
		
		//-----------------------------------------
		// Fetch albums that have a parent defined
		//-----------------------------------------

		
		foreach( \IPS\Db::i()->select( '*', 'gallery_albums_main', NULL, 'album_id ASC', array( $limit, $perCycle ) ) as $row )
		{
			$did++;
			$update	= array();

			//-----------------------------------------
			// Reset watermark
			//-----------------------------------------

			if( $row['album_watermark'] )
			{
				$update['album_watermark']	= 1;
			}

			//-----------------------------------------
			// Reset public/private/friend-only
			//-----------------------------------------

			if( $row['album_is_public'] == 1 )
			{
				$update['album_type']	= 1;
			}
			else if( $row['album_is_public'] == 2 )
			{
				$update['album_type']	= 3;
			}
			else
			{
				$update['album_type']	= 2;
			}

			//-----------------------------------------
			// Get the parent (up to 4 levels deep..)
			//-----------------------------------------

			if( $row['album_parent_id'] )
			{
				try
				{
					$parent	= \IPS\Db::i()->select( 'album_id, album_parent_id, album_category_id', 'gallery_albums_main', 'album_id=' . \intval($row['album_parent_id']) )->first();
				}
				catch( \UnderflowException $e )
				{
					$parent	= array( 'album_id' => 0 );	
				}

				if( $parent['album_id'] )
				{
					if( $parent['album_category_id'] )
					{
						$update['album_category_id']	= $parent['album_category_id'];
					}
					else if( $parent['album_parent_id'] )
					{
						try
						{
							$_parent	= \IPS\Db::i()->select( 'album_id, album_parent_id, album_category_id', 'gallery_albums_main', 'album_id=' . \intval($parent['album_parent_id']) )->first();
						}
						catch( \UnderflowException $e )
						{
							$_parent	= array( 'album_id' => 0 );	
						}

						if( $_parent['album_id'] )
						{
							if( $_parent['album_category_id'] )
							{
								$update['album_category_id']	= $_parent['album_category_id'];
							}
							else if( $_parent['album_parent_id'] )
							{
								try
								{
									$__parent	= \IPS\Db::i()->select( 'album_id, album_parent_id, album_category_id', 'gallery_albums_main', 'album_id=' . \intval($_parent['album_parent_id']) )->first();
								}
								catch( \UnderflowException $e )
								{
									$__parent	= array( 'album_id' => 0 );	
								}

								if( $__parent['album_id'] )
								{
									if( $__parent['album_category_id'] )
									{
										$update['album_category_id']	= $__parent['album_category_id'];
									}
									else if( $__parent['album_parent_id'] )
									{
										try
										{
											$___parent	= \IPS\Db::i()->select( 'album_id, album_parent_id, album_category_id', 'gallery_albums_main', 'album_id=' . \intval($__parent['album_parent_id']) )->first();
										}
										catch( \UnderflowException $e )
										{
											$___parent	= array( 'album_id' => 0 );	
										}

										if( $___parent['album_category_id'] )
										{
											$update['album_category_id']	= $___parent['album_category_id'];
										}
									}
								}
							}
						}
					}
				}
			}

			//-----------------------------------------
			// If we didn't find cat, move to members albums cat
			//-----------------------------------------

			if( !$update['album_category_id'] )
			{
				$update['album_category_id']	= $row['album_category_id'] ? $row['album_category_id'] : (int) \IPS\Settings::i()->gallery_members_album;
			}

			//-----------------------------------------
			// Save updates
			//-----------------------------------------

			if( \count($update) )
			{
				\IPS\Db::i()->update( 'gallery_albums_main', $update, 'album_id=' . $row['album_id'] );
			}
		}
		
		//-----------------------------------------
		// Got any more? .. redirect
		//-----------------------------------------

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			return TRUE;
		}
	}

	/**
	 * Convert albums step 2
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step5()
	{
		//-----------------------------------------
		// Move any lingering albums to member album cat
		//-----------------------------------------

		\IPS\Db::i()->update( 'gallery_albums_main', array( 'album_category_id' => (int) \IPS\Settings::i()->gallery_members_album ), 'album_category_id=0' );

		//-----------------------------------------
		// Delete old columns
		//-----------------------------------------

		\IPS\Db::i()->dropColumn( 'gallery_albums_main', 'album_parent_id' );
		\IPS\Db::i()->dropColumn( 'gallery_albums_main', 'album_is_public' );

		//-----------------------------------------
		// Update indexes
		//-----------------------------------------

		if( \IPS\Db::i()->checkForIndex( 'gallery_albums_main', 'album_nodes' ) )
		{
			\IPS\Db::i()->dropIndex( 'gallery_albums_main', 'album_nodes' );
		}

		if( \IPS\Db::i()->checkForIndex( 'gallery_albums_main', 'album_parent_id' ) )
		{
			\IPS\Db::i()->dropIndex( 'gallery_albums_main', 'album_parent_id' );
		}

		if( \IPS\Db::i()->checkForIndex( 'gallery_albums_main', 'album_owner_id' ) )
		{
			\IPS\Db::i()->dropIndex( 'gallery_albums_main', 'album_owner_id' );
		}

		if( \IPS\Db::i()->checkForIndex( 'gallery_albums_main', 'album_count_imgs' ) )
		{
			\IPS\Db::i()->dropIndex( 'gallery_albums_main', 'album_count_imgs' );
		}

		if( \IPS\Db::i()->checkForIndex( 'gallery_albums_main', 'album_has_a_perm' ) )
		{
			\IPS\Db::i()->dropIndex( 'gallery_albums_main', 'album_has_a_perm' );
		}

		if( \IPS\Db::i()->checkForIndex( 'gallery_albums_main', 'album_child_lup' ) )
		{
			\IPS\Db::i()->dropIndex( 'gallery_albums_main', 'album_child_lup' );
		}

		\IPS\Db::i()->addIndex( 'gallery_albums_main', array(
			'type'			=> 'index',
			'name'			=> 'album_owner_id',
			'columns'		=> array( 'album_owner_id', 'album_last_img_date' )
		) );

		\IPS\Db::i()->addIndex( 'gallery_albums_main', array(
			'type'			=> 'index',
			'name'			=> 'album_parent_id',
			'columns'		=> array( 'album_category_id', 'album_name_seo' )
		) );

		//-----------------------------------------
		// Rename the table
		//-----------------------------------------

		\IPS\Db::i()->renameTable( 'gallery_albums_main', 'gallery_albums' );
		
		return TRUE;
	}
}