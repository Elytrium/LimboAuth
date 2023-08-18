<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Mar 2014
 */

namespace IPS\gallery\setup\upg_60000;

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
	 * Easy basic updates
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Db::i()->update( 'gallery_images', "image_updated=image_date" );
		\IPS\Db::i()->update( 'gallery_categories', array( 'category_tag_prefixes' => 1 ) );
		\IPS\Db::i()->update( 'gallery_categories', array( 'category_allow_albums' => 1 ), array( 'category_type=?', '1' ) );

		/* We need to change 'categories' to 'category', but we also need to set perm 2 as the 'read' permission now, so just set it to same as view by default. There
			is also no longer a 'bypass' permission. */
		\IPS\Db::i()->delete( 'core_permission_index', array( 'app=? and perm_type=?', 'gallery', 'cat' ) );
		\IPS\Db::i()->update( 'core_permission_index', "perm_type='category', perm_5=perm_4, perm_4=perm_3, perm_3=perm_2, perm_2=perm_view", array( 'app=?', 'gallery' ) );

		foreach( \IPS\Db::i()->select( '*', 'gallery_categories' ) as $category )
		{
			\IPS\Lang::saveCustom( 'gallery', "gallery_category_{$category['category_id']}", \IPS\Text\Parser::utf8mb4SafeDecode( $category['category_name'] ) );
			\IPS\Lang::saveCustom( 'gallery', "gallery_category_{$category['category_id']}_desc", \IPS\Text\Parser::utf8mb4SafeDecode( $category['category_description'] ) );

			$_rules	= unserialize( $category['category_rules'] );

			\IPS\Lang::saveCustom( 'gallery', "gallery_category_{$category['category_id']}_rulestitle", \IPS\Text\Parser::utf8mb4SafeDecode( $_rules['title'] ) );
			\IPS\Lang::saveCustom( 'gallery', "gallery_category_{$category['category_id']}_rules", \IPS\Text\Parser::parseStatic( $_rules['text'], TRUE, NULL, NULL, TRUE, TRUE, TRUE ) );

			$_sort = unserialize( $category['category_sort_options'] );

			$_new = 'updated';

			switch( $_sort['key'] )
			{
				case 'image_rating':
				case 'album_rating_aggregate':
					$_new = 'rating';
				break;

				case 'image_comments':
				case 'album_count_comments':
					$_new = 'num_comments';
				break;
			}

			$update	= array( 'category_sort_options' => $_new );

			/* Verify cover photo is valid */
			if( $category['category_cover_img_id'] )
			{
				try
				{
					$image = \IPS\gallery\Image::load( $category['category_cover_img_id'] );
				}
				catch( \OutOfRangeException $e )
				{
					$update['category_cover_img_id']	= 0;
				}
			}

			\IPS\Db::i()->update( 'gallery_categories', $update, 'category_id=' . $category['category_id'] );
		}

		\IPS\Db::i()->dropColumn( 'gallery_categories', array( 'category_type', 'category_name', 'category_description', 'category_rules' ) );
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Upgrading gallery categories";
	}

	/**
	 * Convert ratings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\Db::i()->replace( 'core_ratings', 
			\IPS\Db::i()->select( "NULL, 'IPS\\gallery\\Image', rate_type_id, rate_member_id, rate_rate, '', rate_date", 'gallery_ratings', array( 'rate_type=?', 'image' ) )
		);

		\IPS\Db::i()->replace( 'core_ratings', 
			\IPS\Db::i()->select( "NULL, 'IPS\\gallery\\Album', rate_type_id, rate_member_id, rate_rate, '', rate_date", 'gallery_ratings', array( 'rate_type=?', 'album' ) )
		);

		\IPS\Db::i()->dropTable( 'gallery_ratings', TRUE );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Upgrading gallery ratings";
	}

	/**
	 * Fix paths to urls and GPS
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		/* Some init */
		$did		= 0;
		$perCycle	= 200;
		$limit		= 0;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= (int) \IPS\Request::i()->extra;
		}

		/* Settings */
		foreach( \IPS\Db::i()->select( '*', 'core_sys_conf_settings', array( "conf_key IN( 'gallery_images_path', 'gallery_images_url', 'gallery_watermark_path', 'upload_dir' )" ) ) as $row )
		{
			if ( $row['conf_value'] )
			{
				if ( $row['conf_key'] === 'gallery_images_path' and ! is_dir( $row['conf_value'] ) )
				{
					continue;
				}
				
				$settings[ $row['conf_key'] ] = $row['conf_value'];
			}
		}

		if( !isset( $settings['gallery_images_path'] ) )
		{
			$settings['gallery_images_path']	= \IPS\ROOT_PATH . '/uploads';
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

			$config	= \IPS\File::getClass( 'gallery_Images' );
			$url	= $config->configuration['url'];

			$prefix	= $row['image_directory'] ? $row['image_directory'] . '/' : '';
			$hasSmall	= FALSE;

			if( file_exists( $settings['gallery_images_path'] . '/' . $prefix . 'sml_' . $row['image_masked_file_name'] ) )
			{
				$hasSmall = TRUE;
			}

			$thumb	= $prefix . $row['image_masked_file_name'];

			if( $row['image_media'] )
			{
				if( $row['image_medium_file_name'] )
				{
					$thumb	= $prefix . $row['image_medium_file_name'];
				}
				else if( $row['image_media_thumb'] )
				{
					$thumb	= $prefix . $row['image_media_thumb'];
				}
				else
				{
					$thumb = NULL;
				}
			}

			$imageUpdate = array(
				'image_masked_file_name'	=> $thumb,
				'image_medium_file_name'	=> $row['image_media'] ? $thumb : ( $row['image_medium_file_name'] ? $prefix . $row['image_medium_file_name'] : $thumb ),
				/* The video was stored as masked_file_name in 3.x but should be original_file_name in 4.0 */
				'image_original_file_name'	=> $row['image_media'] ? ( $prefix . $row['image_masked_file_name'] ) : ( $row['image_original_file_name'] ? $prefix . $row['image_original_file_name'] : $thumb ),
				'image_thumb_file_name'		=> $row['image_media'] ? $thumb : ( $row['image_thumbnail'] ? $prefix . 'tn_' . $row['image_masked_file_name'] : NULL ),
				'image_small_file_name'		=> $row['image_media'] ? $thumb : ( $hasSmall ? $prefix . 'sml_' . $row['image_masked_file_name'] : $thumb ),
			);

			/* Without this, videos after upgrade may not play properly */
			if( !$row['image_file_type'] )
			{
				$imageUpdate['image_file_type']	= \IPS\File::getMimeType( $row['image_masked_file_name'] );
			}

			if( $row['image_gps_lat'] and $row['image_gps_lon'] )
			{
				\IPS\Settings::i()->googlemaps = 1;
				
				try
				{
					$imageUpdate['image_gps_raw']	= \IPS\GeoLocation::getByLatLong( $row['image_gps_lat'], $row['image_gps_lon'] );
				}
				catch( \Exception $e ){}
			}

			if( $row['image_metadata'] )
			{
				$imageUpdate['image_metadata']	= json_encode( array_map( 'trim', unserialize( $row['image_metadata'] ) ) );
			}

			/* Simply rebuild the image size values */
			$large	= @getimagesize( $config->configuration['dir'] . $imageUpdate['image_masked_file_name'] );
			$medium	= @getimagesize( $config->configuration['dir'] . $imageUpdate['image_medium_file_name'] );
			$small	= @getimagesize( $config->configuration['dir'] . $imageUpdate['image_small_file_name'] );
			$thumb	= @getimagesize( $config->configuration['dir'] . $imageUpdate['image_thumb_file_name'] );

			$imageUpdate['image_data']	= json_encode( array(
				'large'		=> array( (int) $large[0], (int) $large[1] ),
				'medium'	=> array( (int) $medium[0], (int) $medium[1] ),
				'small'		=> array( (int) $small[0], (int) $small[1] ),
				'thumb'		=> array( (int) $thumb[0], (int) $thumb[1] ),
			) );

			if( $row['image_notes'] )
			{
				$imageNotes	= unserialize( $row['image_notes'] );
				$notes		= array();
				$_id		= 1;

				if( \is_array( $imageNotes ) AND \count( $imageNotes ) )
				{
					foreach( $imageNotes as $note )
					{
						$notes[]	= array(
							'ID'		=> $_id,
							'LEFT'		=> $note['left'],
							'TOP'		=> $note['top'],
							'WIDTH'		=> $note['width'],
							'HEIGHT'	=> $note['height'],
							'NOTE'		=> $note['note'],
							'DATE'		=> '',
							'AUTHOR'	=> '',
							'LINK'		=> ''
						);

						$_id++;
					}
				}

				$imageUpdate['image_notes']	= json_encode( $notes );
			}

			\IPS\Db::i()->update( 'gallery_images', $imageUpdate, array( 'image_id=?', $row['image_id'] ) );
		}

		/* We set this flag so that in 100015 we can skip looping over images again to fix them if the user is upgrading from an older version */
		$_SESSION['40_gallery_images_done']	= true;

		/* And then continue */
		if( $did AND $did == $perCycle )
		{
			return $limit + $did;
		}
		else
		{
			/* Fix gallery watermark image */
			if( isset( $settings['gallery_watermark_path'] ) AND $settings['gallery_watermark_path'] )
			{
				unset( \IPS\Data\Store::i()->storageConfigurations );
				$pathBits	= explode( '/', $settings['gallery_watermark_path'] );
				$fileName	= array_pop( $pathBits );

				try
				{
					$url = \IPS\File::create( 'gallery_Images', $fileName, file_get_contents( $settings['gallery_watermark_path'] ), NULL, TRUE, NULL, FALSE );

					\IPS\Settings::i()->changeValues( array( 'gallery_watermark_path' => $url ) );
				}
				catch( \Exception $e )
				{
					\IPS\Settings::i()->changeValues( array( 'gallery_watermark_path' => '' ) );
				}
			}

			\IPS\Db::i()->dropColumn( 'gallery_images', array( 'image_thumbnail', 'image_directory', 'image_media_thumb' ) );

			\IPS\Db::i()->delete( 'core_sys_conf_settings', "conf_key IN ( 'gallery_watermark_opacity', 'gallery_images_url', 'gallery_images_path' )" );

			unset( $_SESSION['_step3Count'] );
			\IPS\Settings::i()->clearCache();

			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step3Count'] ) )
		{
			$_SESSION['_step3Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'gallery_images' )->first();
		}

		return "Upgrading gallery images (Upgraded so far: " . ( ( $limit > $_SESSION['_step3Count'] ) ? $_SESSION['_step3Count'] : $limit ) . ' out of ' . $_SESSION['_step3Count'] . ')';
	}

	/**
	 * Convert moderators
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		foreach( \IPS\Db::i()->select( '*', 'gallery_moderators' ) as $row )
		{
			$newPerms	= array(
				'gallery_categories'					=> explode( ',', $row['mod_categories'] ),
				'can_pin_gallery_image'					=> false,
				'can_unpin_gallery_image'				=> false,
				'can_feature_gallery_image'				=> false,
				'can_unfeature_gallery_image'			=> false,
				'can_edit_gallery_image'				=> (bool) $row['mod_can_edit'],
				'can_hide_gallery_image'				=> (bool) $row['mod_can_hide'],
				'can_unhide_gallery_image'				=> (bool) $row['mod_can_approve'],
				'can_view_hidden_gallery_image'			=> (bool) $row['mod_can_approve'],
				'can_move_gallery_image'				=> (bool) $row['mod_can_move'],
				'can_lock_gallery_image'				=> false,
				'can_unlock_gallery_image'				=> false,
				'can_reply_to_locked_gallery_image'		=> false,
				'can_delete_gallery_image'				=> (bool) $row['mod_can_delete'],
				'can_edit_gallery_image_comment'		=> (bool) $row['mod_can_edit_comments'],
				'can_hide_gallery_image_comment'		=> (bool) $row['mod_can_hide'],
				'can_unhide_gallery_image_comment'		=> (bool) $row['mod_can_approve_comments'],
				'can_view_hidden_gallery_image_comment'	=> (bool) $row['mod_can_approve_comments'],
				'can_delete_gallery_image_comment'		=> (bool) $row['mod_can_delete_comments'],
			);

			$moderator = array(
				'type'		=> ( $row['mod_type'] == 'group' ) ? 'g' : 'm',
				'id'		=> $row['mod_type_id'],
				'updated'	=> time(),
				'perms'		=> json_encode( $newPerms )
			);

			/* Make sure the record still exists */
			if( $moderator['type'] == 'g' )
			{
				try
				{
					\IPS\Member\Group::load( $moderator['id'] );
				}
				catch( \OutOfRangeException $e )
				{
					continue;
				}
			}
			else
			{
				try
				{
					\IPS\Member::load( $moderator['id'] );
				}
				catch( \OutOfRangeException $e )
				{
					continue;
				}
			}

			try
			{
				$existing	= \IPS\Db::i()->select( '*', 'core_moderators', array( "`type`=? and `id`=?", $moderator['type'], $moderator['id'] ) )->first();
				$perms		= json_decode( $existing['perms'], TRUE );

				$moderator['perms']	= ( $perms == '*' OR $perms == null ) ? $moderator['perms'] : json_encode( array_merge( (array) $perms, (array) json_decode( $moderator['perms'], TRUE ) ) );
				\IPS\Db::i()->update( 'core_moderators', array( 'perms' => $moderator['perms'] ), array( "`type`=? and `id`=?", $moderator['type'], $moderator['id'] ) );
			}
			catch( \UnderflowException $e )
			{
				\IPS\Db::i()->insert( 'core_moderators', $moderator );
			}
		}

		\IPS\Db::i()->dropTable( 'gallery_moderators', TRUE );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step4CustomTitle()
	{
		return "Upgrading gallery moderators";
	}

	/**
	 * Adjust bandwidth log - this can (and has been reported to) time out, e.g. if you have 5M rows
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step5()
	{
        if( ! \IPS\Db::i()->checkForColumn( 'gallery_bandwidth', 'image_id' ) )
        {
            $toRun = \IPS\core\Setup\Upgrade::runManualQueries(array(array(
                'table' => 'gallery_bandwidth',
                'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "gallery_bandwidth ADD COLUMN image_id BIGINT UNSIGNED NOT NULL,
			ADD INDEX image_id (image_id);"
            )));
        }
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'gallery', 'extra' => array( '_upgradeStep' => 6 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step5CustomTitle()
	{
		return "Adjusting bandwidth log";
	}

	/**
	 * Fix bandwidth log
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step6()
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

		/* Loop over members */
		foreach( \IPS\Db::i()->select( '*', 'gallery_bandwidth', NULL, 'bid ASC', array( $limit, $perCycle ) ) as $row )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			try
			{
				$image	= \IPS\Db::i()->select( 'image_id', 'gallery_images', array( 'image_file_name=?', $row['file_name'] ) )->first();

				\IPS\Db::i()->update( 'gallery_bandwidth', array( 'image_id' => $image ), 'bid=' . $row['bid'] );
			}
			catch( \UnderflowException $e ){}
		}

		/* And then continue */
		if( $did AND $did == $perCycle )
		{
			return $limit + $did;
		}
		else
		{
			\IPS\Db::i()->dropColumn( 'gallery_bandwidth', 'file_name' );

			unset( $_SESSION['_step6Count'] );

			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step6CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step6Count'] ) )
		{
			$_SESSION['_step6Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'gallery_bandwidth' )->first();
		}

		return "Upgrading gallery bandwidth logs (Upgraded so far: " . ( ( $limit > $_SESSION['_step6Count'] ) ? $_SESSION['_step6Count'] : $limit ) . ' out of ' . $_SESSION['_step6Count'] . ')';
	}

	/**
	 * Fix groups
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step7()
	{
		foreach( \IPS\Db::i()->select( '*', 'core_groups' ) as $group )
		{
			$update	= array();

			foreach( array( 'g_max_diskspace', 'g_max_upload', 'g_max_transfer', 'g_max_views', 'g_album_limit', 'g_img_album_limit', 'g_movie_size' ) as $column )
			{
				if( $group[ $column ] == -1 )
				{
					$update[ $column ]	= 0;
				}
			}

			if( \count( $update ) )
			{
				\IPS\Db::i()->update( 'core_groups', $update, 'g_id=' . $group['g_id'] );
			}
		}

		unset( \IPS\Data\Store::i()->groups );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step7CustomTitle()
	{
		return "Upgrading gallery group settings";
	}

	/**
	 * Check album cover photos
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step8()
	{
		/* Some init */
		$did		= 0;
		$perCycle	= 1000;
		$limit		= 0;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$data	= json_decode( base64_decode( \IPS\Request::i()->extra ), true );

			$limit	= $data['limit'];
		}
		else
		{
			$data = array(
				'total'	=> \IPS\Db::i()->select( 'COUNT(*)', 'gallery_albums', 'album_cover_img_id > 0' )->first()
			);
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'gallery_albums', "album_cover_img_id > 0", 'album_id ASC', array( $limit, $perCycle ) ) as $album )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				$data['limit']	= ( $limit + $did );

				return base64_encode( json_encode( $data ) );
			}

			$did++;

			try
			{
				$image = \IPS\gallery\Image::load( $album['album_cover_img_id'] );
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Db::i()->update( 'gallery_albums', array( 'album_cover_img_id' => 0 ), 'album_id=' . $album['album_id'] );
			}
		}

		/* And then continue */
		if( $did AND $did == $perCycle )
		{
			$data['limit']	= ( $limit + $did );

			return base64_encode( json_encode( $data ) );
		}
		else
		{
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step8CustomTitle()
	{
		if( isset( \IPS\Request::i()->extra ) )
		{
			$data	= json_decode( base64_decode( \IPS\Request::i()->extra ), true );

			return "Upgrading gallery albums (Upgraded so far: " . ( ( $data['limit'] > $data['total'] ) ? $data['total'] : $data['limit'] ) . ' out of ' . $data['total'] . ')';
		}
		else
		{
			return "Preparing to upgrade gallery albums";
		}
	}

	/**
	 * Finish - This is run after all apps have been upgraded
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 * @note	We opted not to let users run this immediately during the upgrade because of potential issues (it taking a long time and users stopping it or getting frustrated) but we can revisit later
	 */
	public function finish()
    {
	   \IPS\Task::queue( 'core', 'RebuildPosts', array( 'class' => 'IPS\gallery\Image' ), 2 );
	   \IPS\Task::queue( 'core', 'RebuildPosts', array( 'class' => 'IPS\gallery\Image\Comment' ), 2 );
	   \IPS\Task::queue( 'core', 'RebuildNonContentPosts', array( 'extension' => 'gallery_Categories' ), 2 );

        return TRUE;
    }
}