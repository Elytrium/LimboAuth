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

namespace IPS\gallery\setup\upg_40000;

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
		\IPS\Db::i()->query( "INSERT INTO " . \IPS\Db::i()->prefix . "gallery_albums_main
						(album_id, album_parent_id, album_owner_id, album_name, album_name_seo, album_description, album_is_public,
						 album_is_global, album_is_profile, album_count_imgs, album_count_comments, album_count_imgs_hidden,
						 album_count_comments_hidden, album_cover_img_id, album_last_img_id, album_last_img_date, album_sort_options,
						 album_allow_comments, album_cache, album_node_level, album_node_left, album_node_right, album_g_approve_img, album_g_approve_com,
						 album_g_bitwise, album_g_rules, album_g_container_only, album_g_perms_thumbs, album_g_perms_view,
						 album_g_perms_images, album_g_perms_comments, album_g_perms_moderate, album_child_tree, album_parent_tree, album_preset_tags, album_g_latest_imgs )
						( SELECT a.id, a.parent, a.member_id, a.name, a.name_seo, a.description,( CASE WHEN a.friend_only THEN 2 WHEN a.public_album THEN 1 ELSE 0 END ),
						0, a.profile_album, a.images, a.comments, a.mod_images,
						a.mod_comments, 0, 0, 0, '',
						1, CONCAT( 'cat-', a.category_id ), 0, 0, 0, 0, 0,
						0, '', 0, '*', '*',
						'*', '*', '', '', '', '', '' FROM " . \IPS\Db::i()->prefix . "gallery_albums a)" );

		\IPS\Db::i()->query( "INSERT INTO " . \IPS\Db::i()->prefix . "gallery_albums_main
						(album_parent_id, album_owner_id, album_name, album_name_seo, album_description, album_is_public,
						 album_is_global, album_is_profile, album_count_imgs, album_count_comments, album_count_imgs_hidden,
						 album_count_comments_hidden, album_cover_img_id, album_last_img_id, album_last_img_date, album_sort_options,
						 album_allow_comments, album_cache, album_node_level, album_node_left, album_node_right, album_g_approve_img, album_g_approve_com,
						 album_g_bitwise, album_g_rules, album_g_container_only, album_g_perms_thumbs, album_g_perms_view,
						 album_g_perms_images, album_g_perms_comments, album_g_perms_moderate, album_child_tree, album_parent_tree, album_preset_tags, album_g_latest_imgs )
						( SELECT 0, 0, c.name, c.name_seo, c.description, 1,
						1, 0, c.images, c.comments, c.mod_images,
						0, 0, 0, 0, '',
						c.allow_comments, CONCAT( 'catid-', c.id ), 0, 0, 0, c.mod_images, c.mod_comments,
						0, CONCAT( 'parent-cat-', c.parent ), c.category_only, p.perm_view, p.perm_2,
						p.perm_3, p.perm_4, p.perm_5, '', '', '', '' FROM `" . \IPS\Db::i()->prefix . "gallery_categories` c, `" . \IPS\Db::i()->prefix . "core_permission_index` p WHERE p.perm_type='cat' AND p.perm_type_id=c.id AND p.app='gallery' ORDER BY c.id ASC )" );
		
		return TRUE;
	}

	/**
	 * Convert albums step 1
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		foreach( \IPS\Db::i()->select( '*', 'gallery_albums_main', "album_is_global=1 AND album_g_rules LIKE 'parent-cat-%'" ) as $album )
		{
			$oldParent = str_replace( 'parent-cat-', '', $album['album_g_rules'] );

			if ( \intval( $oldParent ) and $oldParent > 0 )
			{
				try
				{
					$parent = \IPS\Db::i()->select( 'album_id', 'gallery_albums_main', "album_cache='catid-" . $oldParent . "'" )->first();
				}
				catch( \UnderflowException $e )
				{
					$parent = 0;
				}
															
				/* convert */
				\IPS\Db::i()->update( 'gallery_albums_main', array( 'album_parent_id' => \intval( $parent ) ), 'album_id=' . $album['album_id'] );
			}
		}
		
		return TRUE;
	}

	/**
	 * Convert albums step 2
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		foreach( \IPS\Db::i()->select( '*', 'gallery_albums_main', "album_cache LIKE 'catid-%'" ) as $album )
		{
			$oldParent = str_replace( 'catid-', '', $album['album_cache'] );

			\IPS\Db::i()->update( 'gallery_images', array( 'img_album_id' => \intval( $album['album_id'] ) ), 'category_id=' . \intval( $oldParent ) );
		}
		
		return TRUE;
	}

	/**
	 * Convert albums step 3
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		foreach( \IPS\Db::i()->select( '*', 'gallery_albums_main', "album_cache LIKE 'cat-%'" ) as $album )
		{
			$oldParent = str_replace( 'cat-', '', $album['album_cache'] );

			if ( \intval( $oldParent ) and $oldParent > 0 )
			{
				try
				{
					$parent = \IPS\Db::i()->select( 'album_id', 'gallery_albums_main', "album_cache='catid-" . $oldParent . "'" )->first();
				}
				catch( \UnderflowException $e )
				{
					$parent = 0;
				}
															
				/* convert */
				\IPS\Db::i()->update( 'gallery_albums_main', array( 'album_parent_id' => \intval( $parent ) ), 'album_id=' . $album['album_id'] );
				\IPS\Db::i()->update( 'gallery_images', array( 'img_album_id' => \intval( $parent ) ), 'category_id=' . \intval( $oldParent ) );
			}
		}

		\IPS\Db::i()->query( "UPDATE " . \IPS\Db::i()->prefix . "gallery_albums_main SET
				album_g_perms_thumbs   = TRIM( BOTH ',' FROM  album_g_perms_thumbs ),
				album_g_perms_view     = TRIM( BOTH ',' FROM  album_g_perms_view ),
				album_g_perms_images   = TRIM( BOTH ',' FROM  album_g_perms_images ),
				album_g_perms_comments = TRIM( BOTH ',' FROM  album_g_perms_comments ),
				album_g_perms_moderate = TRIM( BOTH ',' FROM  album_g_perms_moderate );" );
		
		return TRUE;
	}

	/**
	 * Convert albums step 4
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step5()
	{
		foreach( \IPS\Db::i()->select( '*', 'gallery_categories', "status=0" ) as $category )
		{
			\IPS\Db::i()->update( 'gallery_albums_main', array( 'album_g_perms_images' => '' ), "album_cache='catid-{$category['id']}'" );
		}

		\IPS\Db::i()->update( 'gallery_albums_main', array( 'album_cache' => '', 'album_node_level' => 0, 'album_node_left' => 0, 'album_node_right' => 0 ) );
		
		return TRUE;
	}
}