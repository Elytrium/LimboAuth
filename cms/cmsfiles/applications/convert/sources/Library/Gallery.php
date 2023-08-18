<?php

/**
 * @brief		Converter Library Gallery Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		21 Jan 2015
 */

namespace IPS\convert\Library;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Invision Gallery Converter
 * @note	We must extend the Core Library here so we can access methods like convertAttachment, convertFollow, etc
 */
class _Gallery extends Core
{
	/**
	 * @brief	Application
	 */
	public $app = 'gallery';

	/**
	 * Returns an array of items that we can convert, including the amount of rows stored in the Community Suite as well as the recommend value of rows to convert per cycle
	 *
	 * @param	bool	$rowCounts		enable row counts
	 * @return	array
	 */
	public function menuRows( $rowCounts=FALSE )
	{
		$return		= array();
		$extraRows 	= $this->software->extraMenuRows();

		foreach( $this->getConvertableItems() as $k => $v )
		{
			switch( $k )
			{
				case 'convertGalleryCategories':
					$return[ $k ] = array(
						'step_method'		=> 'convertGalleryCategories',
						'step_title'		=> 'convert_gallery_categories',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'gallery_categories' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array(),
						'link_type'			=> 'gallery_categories',
					);
					break;
				
				case 'convertGalleryAlbums':
					/* Some software do not have a category concept */
					$dependencies = array();
					
					if ( array_key_exists( 'convertGalleryCategories', $this->getConvertableItems() ) )
					{
						$dependencies[] = 'convertGalleryCategories';
					}
					
					$return[ $k ] = array(
						'step_method'		=> 'convertGalleryAlbums',
						'step_title'		=> 'convert_gallery_albums',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'gallery_albums' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> $dependencies,
						'link_type'			=> 'gallery_albums',
					);
					break;

				case 'convertGalleryAlbumComments':
					$return[ $k ] = array(
						'step_method'		=> 'convertGalleryAlbumComments',
						'step_title'		=> 'convert_gallery_album_comments',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'gallery_album_comments' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertGalleryAlbums' ),
						'link_type'			=> 'gallery_album_comments',
						'requires_rebuild'	=> TRUE
					);
					break;

				case 'convertGalleryAlbumReviews':
					$return[ $k ] = array(
						'step_method'		=> 'convertGalleryAlbumReviews',
						'step_title'		=> 'convert_gallery_album_reviews',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'gallery_album_reviews' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertGalleryAlbums' ),
						'link_type'			=> 'gallery_album_reviews',
						'requires_rebuild'	=> TRUE
					);
					break;
				
				case 'convertGalleryImages':
					$dependencies = array();
					
					/* If the source is really weird and has neither, we can make do with creating manually */
					if ( array_key_exists( 'convertGalleryCategories', $this->getConvertableItems() ) )
					{
						$dependencies[] = 'convertGalleryCategories';
					}
					
					if ( array_key_exists( 'convertGalleryAlbums', $this->getConvertableItems() ) )
					{
						$dependencies[] = 'convertGalleryAlbums';
					}
					
					$return[ $k ] = array(
						'step_method'		=> 'convertGalleryImages',
						'step_title'		=> 'convert_gallery_images',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'gallery_images' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 10,
						'dependencies'		=> $dependencies,
						'link_type'			=> 'gallery_images',
						'requires_rebuild'	=> TRUE
					);
					break;
				
				case 'convertGalleryComments':
					$return[ $k ] = array(
						'step_method'		=> 'convertGalleryComments',
						'step_title'		=> 'convert_gallery_comments',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'gallery_comments' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertGalleryImages' ),
						'link_type'			=> 'gallery_comments',
						'requires_rebuild'	=> TRUE
					);
					break;
				
				case 'convertGalleryReviews':
					$return[ $k ] = array(
						'step_method'		=> 'convertGalleryReviews',
						'step_title'		=> 'convert_gallery_reviews',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'gallery_reviews' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertGalleryImages' ),
						'link_type'			=> 'gallery_reviews',
						'requires_rebuild'	=> TRUE
					);
					break;

				case 'convertClubGalleryCategories':
					$return[ $k ] = array(
						'step_title'		=> 'convert_club_gallery_categories',
						'step_method'		=> 'convertClubGalleryCategories',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'gallery_categories', array( "category_club_id IS NOT NULL" ) ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertGalleryImages' ),
						'link_type'			=> 'core_club_gallery_categories'
					);
					break;

				case 'convertClubGalleryImages':
					$return[ $k ] = array(
						'step_title'		=> 'convert_club_gallery_images',
						'step_method'		=> 'convertClubGalleryImages',
						'ips_rows'			=> \IPS\Db::i()->select( 'SUM(category_count_imgs)', 'gallery_categories', array( "category_club_id IS NOT NULL" ) ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 10,
						'dependencies'		=> array( 'convertClubGalleryCategories' ),
						'link_type'			=> 'core_club_gallery_images'
					);
					break;

				case 'convertClubGalleryComments':
					$return[ $k ] = array(
						'step_title'		=> 'convert_club_gallery_comments',
						'step_method'		=> 'convertClubGalleryComments',
						'ips_rows'			=> \IPS\Db::i()->select( 'SUM(category_count_comments)', 'gallery_categories', array( "category_club_id IS NOT NULL" ) ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertClubGalleryImages' ),
						'link_type'			=> 'core_club_gallery_comments'
					);
					break;

				case 'convertClubGalleryReviews':
					$return[ $k ] = array(
						'step_title'		=> 'convert_club_gallery_reviews',
						'step_method'		=> 'convertClubGalleryReviews',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'gallery_reviews', array( \IPS\Db::i()->in( 'review_image_id', \IPS\Db::i()->select( 'image_id', 'gallery_images', array( \IPS\Db::i()->in( 'image_category_id', \IPS\Db::i()->select( 'category_id', 'gallery_categories', array( "category_club_id IS NOT NULL" ) ) ) ) ) ) ) ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertClubGalleryImages' ),
						'link_type'			=> 'core_club_gallery_reviews'
					);
					break;
				
				case 'convertAttachments':
					$dependencies = array( 'convertGalleryImages' );
					
					if ( array_key_exists( 'convertGalleryComments', $this->getConvertableItems() ) )
					{
						$dependencies[] = 'convertGalleryComments';
					}
					
					if ( array_key_exists( 'convertGalleryReviews', $this->getConvertableItems() ) )
					{
						$dependencies[] = 'convertGalleryReviews';
					}
					
					$return[ $k ] = array(
						'step_method'		=> 'convertAttachments',
						'step_title'		=> 'convert_attachments',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'core_attachments_map', array( "location_key=?", 'gallery_Images' ) ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 10,
						'dependencies'		=> $dependencies,
						'link_type'			=> 'core_attachments',
					);
					break;
			}

			/* Append any extra steps immediately to retain ordering */
			if( isset( $v['extra_steps'] ) )
			{
				foreach( $v['extra_steps'] as $extra )
				{
					$return[ $extra ] = $extraRows[ $extra ];
				}
			}
		}

		/* Run the queries if we want row counts */
		if( $rowCounts )
		{
			$return = $this->getDatabaseRowCounts( $return );
		}

		return $return;
	}
	
	/**
	 * Returns an array of tables that need to be truncated when Empty Local Data is used
	 *
	 * @param	string	$method	Method to truncate
	 * @return	array
	 */
	protected function truncate( $method )
	{
		$return		= array();
		$classname	= \get_class( $this->software );

		if( $classname::canConvert() === NULL )
		{
			return array();
		}
		
		foreach( $classname::canConvert() as $k => $v )
		{
			switch( $k )
			{
				case 'convertGalleryCategories':
					$return['convertGalleryCategories'] = array(
																'gallery_categories'	=> NULL,
																'core_clubs_node_map'	=> array( "node_class=?", "IPS\\gallery\\Category" ),
																'core_permission_index' => array( 'app=? AND perm_type=?', 'gallery', 'category' )
						);
					break;
				
				case 'convertGalleryAlbums':
					$return['convertGalleryAlbums'] = array( 'gallery_albums' => NULL );
					break;

				case 'convertGalleryAlbumComments':
					$return['convertGalleryAlbumComments'] = array( 'gallery_album_comments' => NULL );
					break;

				case 'convertGalleryAlbumReviews':
					$return['convertGalleryAlbumReviews'] = array( 'gallery_album_reviews' => NULL );
					break;
				
				case 'convertGalleryImages':
					$return['convertGalleryImages'] = array( 'gallery_images' => NULL );
					break;
				
				case 'convertGalleryComments':
					$return['convertGalleryComments'] = array( 'gallery_comments' => NULL );
					break;
				
				case 'convertGalleryReviews':
					$return['convertGalleryReviews'] = array( 'gallery_reviews' => NULL );
					break;
				
				case 'convertAttachments':
					$return['convertAttachments'] = array( 'core_attachments' => \IPS\Db::i()->select( 'attachment_id', 'core_attachments_map', array( "location_key=?", 'gallery_Gallery' ) ), 'core_attachments_map' => array( 'location_key=?', 'gallery_Gallery' ) );
					break;

				case 'convertClubGalleryCategories':
					$return['convertClubGalleryCategories'] = array( 'gallery_categories' => array( "category_club_id IS NOT NULL" ) );
					break;

				case 'convertClubGalleryImages':
					$return['convertClubGalleryImages'] = array( 'gallery_images' => array( 'image_id IN ( ' . (string) \IPS\Db::i()->select( 'ipb_id', 'convert_link', array( "type='core_clubs_gallery_images' AND app={$this->software->app->app_id}" ) ) . ')' ) );
					break;

				case 'convertClubGalleryComments':
					$return['convertClubGalleryComments'] = array( 'gallery_comments' => array( 'comment_id IN ( ' . (string) \IPS\Db::i()->select( 'ipb_id', 'convert_link', array( "type='core_clubs_gallery_comments' AND app={$this->software->app->app_id}" ) ) . ')' ) );
					break;

				case 'convertClubGalleryReviews':
					$return['convertClubGalleryReviews'] = array( 'gallery_reviews' => array( 'review_id IN ( ' . (string) \IPS\Db::i()->select( 'ipb_id', 'convert_link', array( "type='core_clubs_gallery_reviews' AND app={$this->software->app->app_id}" ) ) . ')' ) );
					break;
			}
		}

		return $return[ $method ];
	}
	
	/**
	 * This is how the insert methods will work - basically like 3.x, but we should be using the actual classes to insert the data unless there is a real world reason not too.
	 * Using the actual routines to insert data will help to avoid having to resynchronize and rebuild things later on, thus resulting in less conversion time being needed overall.
	 * Anything that parses content, for example, may need to simply insert directly then rebuild via a task over time, as HTML Purifier is slow when mass inserting content.
	 */
	
	/**
	 * A note on logging -
	 * If the data is missing and it is unlikely that any source software would be able to provide this, we do not need to log anything and can use default data (for example, group_layout in convertLeaderGroups).
	 * If the data is missing and it is likely that a majority of the source software can provide this, we should log a NOTICE and use default data (for example, a_casesensitive in convertAcronyms).
	 * If the data is missing and it is required to convert the item, we should log a WARNING and return FALSE.
	 * If the conversion absolutely cannot proceed at all (filestorage locations not writable, for example), then we should log an ERROR and throw an \IPS\convert\Exception to completely halt the process and redirect to an error screen showing the last logged error.
	 */
	
	/**
	 * Convert a category
	 *
	 * @param	array			$info	Data to insert
	 * @return	integer|boolean	The ID of the newly converted category, or FALSE on error.
	 */
	public function convertGalleryCategory( $info=array() )
	{
		if ( !isset( $info['category_id'] ) )
		{
			$this->software->app->log( 'gallery_category_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( !isset( $info['category_name'] ) )
		{
			$name = "Untitled Category {$info['category_id']}";
			$this->software->app->log( 'gallery_category_missing_name', __METHOD__, \IPS\convert\App::LOG_NOTICE, $info['category_id'] );
		}
		else
		{
			$name = $info['category_name'];
			unset( $info['category_name'] );
		}
		
		if ( !isset( $info['category_desc'] ) )
		{
			$desc = '';
		}
		else
		{
			$desc = $info['category_desc'];
			unset( $info['category_desc'] );
		}
		
		if ( isset( $info['category_parent_id'] ) )
		{
			try
			{
				$info['category_parent_id'] = $this->software->app->getLink( $info['category_parent_id'], 'gallery_categories' );
			}
			catch( \OutOfRangeException $e )
			{
				$info['category_conv_parent'] = $info['category_parent_id'];
			}
		}
		else
		{
			$info['category_parent_id'] = 0;
		}
		
		$info['category_name_seo'] = \IPS\Http\Url::seoTitle( $name );
		
		/* Counts */
		foreach( array( 'category_count_imgs', 'category_count_comments', 'category_count_imgs_hidden', 'category_count_comments_hidden', 'category_public_albums', 'category_nonpublic_albums', 'category_rating_aggregate', 'category_rating_count', 'category_rating_total' ) AS $count )
		{
			if ( !isset( $info[ $count ] ) )
			{
				$info[ $count ] = 0;
			}
		}
		
		/* We don't know these yet, but we may be able to figure them out later. */
		$info['category_cover_img_id']	= 0;
		$info['category_last_img_id']	= 0;
		$info['category_last_img_date']	= 0;

		/* Album Sort Order */
		$sortOrders = array( 'album_last_img_date', 'album_last_comment', 'album_rating_aggregate', 'album_comments','album_reviews', 'album_name', 'album_count_comments', 'album_count_imgs' );
		if ( !isset( $info['category_sort_options'] ) OR !\in_array( $info['category_sort_options'], $sortOrders ) )
		{
			$info['category_sort_options'] = 'album_last_img_date';
		}

		/* Image Sort Order */
		if ( !isset( $info['category_sort_options_img'] ) OR !\in_array( $info['category_sort_options_img'], array( 'updated', 'last_comment', 'title', 'rating', 'date', 'num_comments', 'num_reviews', 'views' ) ) )
		{
			$info['category_sort_options_img'] = 'updated';
		}
		
		/* One Defaults */
		foreach( array( 'category_allow_comments', 'category_allow_rating', 'category_allow_albums' ) AS $oneDefault )
		{
			if ( !isset( $info[ $oneDefault ] ) )
			{
				$info[ $oneDefault ] = 1;
			}
		}
		
		/* Zero Defaults */
		foreach( array( 'category_approve_img', 'category_approve_com', 'category_watermark', 'category_can_tag', 'category_tag_prefixes', 'category_show_rules', 'category_allow_reviews', 'category_review_moderate' ) AS $zeroDefault )
		{
			if ( !isset( $info[ $zeroDefault ] ) )
			{
				$info[ $zeroDefault ] = 0;
			}
		}
		
		/* Not Used */
		$info['category_preset_tags']	= NULL;
		$info['category_rules_link']	= NULL;
		
		if ( !isset( $info['category_position'] ) )
		{
			$position = \IPS\Db::i()->select( 'MAX(category_position)', 'gallery_categories' )->first();
			
			$info['category_position'] = $position + 1;
		}
		
		if ( isset( $info['category_club_id'] ) )
		{
			try
			{
				$info['category_club_id'] = $this->software->app->getLink( $info['category_club_id'], 'core_clubs', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['category_club_id'] = NULL;
			}
		}
		else
		{
			$info['category_club_id'] = NULL;
		}
		
		$id = $info['category_id'];
		unset( $info['category_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'gallery_categories', $info );
		$this->software->app->addLink( $inserted_id, $id, 'gallery_categories' );
		
		\IPS\Lang::saveCustom( 'gallery', "gallery_category_{$inserted_id}", $name );
		\IPS\Lang::saveCustom( 'gallery', "gallery_category_{$inserted_id}_desc", $desc );
		
		\IPS\Db::i()->update( 'gallery_categories', array( "category_parent_id" => $inserted_id ), array( "category_conv_parent=?", $id ) );
		\IPS\Db::i()->insert( 'core_permission_index', array( 'app' => 'gallery', 'perm_type' => 'category', 'perm_type_id' => $inserted_id, 'perm_view' => '' ) );
		
		if ( $info['category_club_id'] )
		{
			\IPS\Db::i()->insert( 'core_clubs_node_map', array(
				'node_id'		=> $inserted_id,
				'node_class'	=> "IPS\\gallery\\Category",
				'club_id'		=> $info['category_club_id'],
				'name'			=> $name
			) );
			
			\IPS\gallery\Category::load( $inserted_id )->setPermissionsToClub( \IPS\Member\Club::load( $info['category_club_id'] ) );
		}
		
		return $inserted_id;
	}
	
	/**
	 * Convert an album
	 *
	 * @param	array			$info			Data to insert
	 * @param	array|NULL		$socialgroup	Array of social group data, or NULL.
	 * @param	integer|NULL	$category		If the source software only has albums, and not categories, an existing category ID can be passed here to store converted albums. NULL to auto-create.
	 * @return	integer|boolean	The ID of the newly converted album, or FALSE on error.
	 */
	public function convertGalleryAlbum( $info=array(), $socialgroup=NULL, $category=NULL )
	{
		if ( !isset( $info['album_id'] ) )
		{
			$this->software->app->log( 'gallery_album_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( \is_null( $category ) )
		{
			if ( isset( $info['album_category_id'] ) )
			{
				try
				{
					$info['album_category_id'] = $this->software->app->getLink( $info['album_category_id'], 'gallery_categories' );
				}
				catch( \OutOfRangeException $e )
				{
					$info['album_category_id'] = $this->_orphanedAlbumsCategory();
				}
			}
			else
			{
				$info['album_category_id'] = $this->_orphanedAlbumsCategory();
			}
		}
		else
		{
			$info['album_category_id'] = $category;

			/* Check the category exists */
			try
			{
				\IPS\gallery\Category::load( $info['album_category_id'] );
			}
			catch( \OutOfRangeException $e )
			{
				$info['album_category_id'] = $this->_orphanedAlbumsCategory();
			}
		}
		
		if ( isset( $info['album_owner_id'] ) )
		{
			try
			{
				$info['album_owner_id'] = $this->software->app->getLink( $info['album_owner_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['album_owner_id'] = 0;
			}
		}
		else
		{
			$info['album_owner_id'] = 0;
		}
		
		if ( !isset( $info['album_name'] ) )
		{
			$info['album_name'] = "Untitled Album {$info['album_id']}";
			$this->software->app->log( 'gallery_album_missing_name', __METHOD__, \IPS\convert\App::LOG_NOTICE, $info['album_id'] );
		}
		
		$info['album_name_seo'] = \IPS\Http\Url::seoTitle( $info['album_name'] );
		
		if ( !isset( $info['album_description'] ) )
		{
			$info['album_description'] = '';
		}
		
		if ( !isset( $info['album_type'] ) OR !\in_array( $info['album_type'], array( 1, 2, 3 ) ) )
		{
			$info['album_type'] = 1;
		}
		
		/* Counts */
		foreach( array( 'album_count_imgs', 'album_count_comments', 'album_count_imgs_hidden', 'album_rating_aggregate', 'album_rating_count', 'album_rating_total', 'album_count_reviews', 'album_count_reviews_hidden' ) AS $count )
		{
			if ( !isset( $info[ $count ] ) )
			{
				$info[ $count ] = 0;
			}
		}
		
		/* These are things we will not know */
		$info['album_cover_img_id']		= 0;
		$info['album_last_img_id']		= 0;
		$info['album_last_img_date']	= 0;
		$info['album_last_x_images']	= NULL;
		
		if ( !isset( $info['album_sort_options'] ) OR !\in_array( $info['album_sort_options'], array( 'updated', 'last_comment', 'title', 'rating', 'date', 'num_comments', 'num_reviews', 'views' ) ) )
		{
			$info['album_sort_options'] = 'updated';
		}
		
		if ( !isset( $info['album_allow_comments'] ) )
		{
			$info['album_allow_comments'] = 1;
		}
		
		if ( !isset( $info['album_allow_rating'] ) )
		{
			$info['album_allow_rating'] = 1;
		}

		if ( !isset( $info['album_submit_type'] ) )
		{
			$info['album_submit_type'] = 0;
		}
		
		if ( !isset( $info['album_position'] ) )
		{
			$position = \IPS\Db::i()->select( 'MAX(album_position)', 'gallery_albums', array( "album_owner_id=?", $info['album_owner_id'] ) )->first();
			
			$info['album_position'] = $position + 1;
		}
		
		$info['album_allowed_access'] = NULL;
		if ( !\is_null( $socialgroup ) AND \is_array( $socialgroup ) )
		{
			$socialGroupId = \IPS\Db::i()->insert( 'core_sys_social_groups', array( 'owner_id' => $info['album_owner_id'] ) );
			$members	= array();
			$members[]	= array( 'group_id' => $socialGroupId, 'member_id' => $info['album_owner_id'] );
			foreach( $socialgroup['members'] AS $member )
			{
				try
				{
					$members[] = array( 'group_id' => $socialGroupId, 'member_id' => $this->software->app->getLink( $member, 'core_members', TRUE ) );
				}
				catch( \OutOfRangeException $e )
				{
					continue;
				}
			}
			\IPS\Db::i()->insert( 'core_sys_social_group_members', $members );
			
			$info['album_allowed_access'] = $socialGroupId;
		}
		
		if ( !isset( $info['album_allow_reviews'] ) )
		{
			$info['album_allow_reviews'] = 1;
		}
		
		$id = $info['album_id'];
		unset( $info['album_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'gallery_albums', $info );
		$this->software->app->addLink( $inserted_id, $id, 'gallery_albums' );
		
		return $inserted_id;
	}
	
	/**
	 * Orphaned Albums Category
	 *
	 * @return	integer	Orphaned Albums Category ID
	 */
	protected function _orphanedAlbumsCategory()
	{
		try
		{
			$id = $this->software->app->getLink( '__orphaned__', 'gallery_categories' );

			/* Check the category exists */
			try
			{
				\IPS\gallery\Category::load( $id );
			}
			catch( \OutOfRangeException $e )
			{
				/* Delete the relation if it no longer exists */
				$this->software->app->deleteLink( '__orphaned__', 'gallery_categories' );
				throw new \OutOfRangeException;
			}

			return $id;
		}
		catch( \OutOfRangeException $e )
		{
			return $this->convertGalleryCategory( array(
				'category_id'		=> '__orphaned__',
				'category_name'		=> 'Converted Albums',
			) );
		}
	}
	
	/**
	 * Convert an image
	 *
	 * @param	array			$info		Data to insert
	 * @param	string|NULL		$filepath	The path to the image, or NULL.
	 * @param	string|NULL		$fileinfo	The binary data for the image, or NULL
	 * @return	integer|boolean	The ID of the newly inserted image, or FALSE on failure.
	 */
	public function convertGalleryImage( $info=array(), $filepath=NULL, $filedata=NULL )
	{
		if ( !isset( $info['image_id'] ) )
		{
			$this->software->app->log( 'gallery_image_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		/* If we don't have any image to process, just stop here */
		if ( \is_null( $filedata ) AND ( \is_null( $filepath ) OR !file_exists( $filepath ) ) )
		{
			$this->software->app->log( 'gallery_image_missing_image', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['image_id'] );
			return FALSE;
		}
		
		/* Do this first - we may not have a category so we can figure it out here */
		$categoryFound = FALSE;
		if ( isset( $info['image_album_id'] ) )
		{
			/* We only want to do this if the Album ID is explicitly not 0, as that means it has no album, which is okay */
			if ( !empty( $info['image_album_id'] ) )
			{
				try
				{
					$info['image_album_id'] = $this->software->app->getLink( $info['image_album_id'], 'gallery_albums' );
					
					$album = \IPS\gallery\Album::load( $info['image_album_id'] );
					$info['image_category_id'] = $album->category_id;
					$categoryFound = TRUE;
				}
				catch( \OutOfRangeException $e )
				{
					/* We can just dump directly into the category */
					$info['image_album_id'] = 0;
				}
			}
		}
		else
		{
			$info['image_album_id'] = 0;
		}
		
		if ( isset( $info['image_category_id'] ) AND $categoryFound == FALSE )
		{
			try
			{
				$info['image_category_id'] = $this->software->app->getLink( $info['image_category_id'], 'gallery_categories' );
			}
			catch( \OutOfRangeException $e )
			{
				$info['image_category_id'] = $this->_orphanedAlbumsCategory();
			}
		}
		else
		{
			if ( $categoryFound == FALSE )
			{
				$info['image_category_id'] = $this->_orphanedAlbumsCategory();
			}
		}
		
		if ( isset( $info['image_member_id'] ) )
		{
			try
			{
				$info['image_member_id'] = $this->software->app->getLink( $info['image_member_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				/* Set it to NULL - we can try a few other methods. */
				$info['image_member_id'] = NULL;
			}
		}
		else
		{
			$info['image_member_id'] = NULL;
		}
		
		if ( \is_null( $info['image_member_id'] ) )
		{
			try
			{
				$album = \IPS\gallery\Album::load( $info['image_album_id'] );
				$info['image_member_id'] = (int) $album->owner()->member_id;
			}
			catch( \OutOfRangeException $e )
			{
				$info['image_member_id'] = 0;
			}
		}
		
		if ( !isset( $info['image_description'] ) )
		{
			$info['image_description'] = NULL;
		}
		
		/* We will build these later */
		$info['image_masked_file_name']	= NULL;
		$info['image_small_file_name']	= NULL;
		
		/* Figure this out before we bother creating the image */
		if ( !isset( $info['image_file_type'] ) )
		{
			$mime_type = \IPS\File::getMimeType( $info['image_file_name'] );
			
			if ( $mime_type === 'application/x-unknown' OR ( \strstr( $mime_type, 'image' ) === FALSE AND \strstr( $mime_type, 'video' ) === FALSE ) )
			{
				$this->software->app->log( 'gallery_image_invalid_mime_type', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['image_id'] );
				return FALSE;
			}
			
			$info['image_file_type'] = $mime_type;
		}
		
		if ( !isset( $info['image_file_name'] ) )
		{
			/* If we were passed a path, try and figure it out */
			if ( !\is_null( $filepath ) )
			{
				$file_name = explode( '/', $filepath );
				$file_name = array_pop( $file_name );
				$info['image_file_name'] = mb_substr( $file_name, 0, 255 );
			}
			else
			{
				/* We can't do much here... we have binary data, but no way to figure out the file name */
				$this->software->app->log( 'gallery_image_missing_file_name', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['image_id'] );
				return FALSE;
			}
		}
		else
		{
			$info['image_file_name'] = mb_substr( $info['image_file_name'], 0, 255 );
		}

		/* Image Caption */
		if ( !isset( $info['image_caption'] ) )
		{
			if ( !\is_null( $filepath ) )
			{
				$caption = explode( '/', $info['image_file_name'] );
				$caption = array_pop( $caption );
			}
			else
			{
				$caption = "Untitled Image {$info['image_id']}";
			}

			$info['image_caption'] = $caption;
		}

		if ( mb_strlen( $info['image_caption'] ) > 255 )
		{
			$info['image_caption'] = mb_substr( $info['image_caption'], 0, 200 );
		}

		/* Dates */
		foreach( array( 'image_date', 'image_last_comment', 'image_updated' ) AS $date )
		{
			if ( isset( $info[ $date ] ) )
			{
				if ( $info[ $date ] instanceof \IPS\DateTime )
				{
					$info[ $date ] = $info[ $date ]->getTimestamp();
				}
			}
			else
			{
				$info[ $date ] = time();
			}
		}
		
		/* Figure out the container */
		$container = 'monthly_' . date( 'Y', $info['image_date'] ) . '_' . date( 'm', $info['image_date'] );
		if( isset( $info['image_container'] ) )
		{
			$container = $info['image_container'];
			unset( $info['image_container'] );
		}
		
		/* Okay, let's create our image. We can use data returned from this later if it's missing. */
		try
		{
			if ( \is_null( $filedata ) AND !\is_null( $filepath ) )
			{
				if( !file_exists( $filepath ) )
				{
					$this->software->app->log( 'gallery_image_missing_file', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['image_id'] );
					return FALSE;
				}

				$filedata = file_get_contents( $filepath );
			}
			$image = \IPS\File::create( 'gallery_Images', $info['image_file_name'], $filedata, $container );
			$info['image_original_file_name'] = (string) $image;
			
			if ( !isset( $info['image_file_size'] ) )
			{
				$info['image_file_size'] = $image->filesize();
			}
		}
		catch( \Exception $e )
		{
			$this->software->app->log( $e->getMessage(), __METHOD__, \IPS\convert\App::LOG_WARNING, $info['image_id'] );
			return FALSE;
		}
		catch( \ErrorException $e )
		{
			$this->software->app->log( $e->getMessage(), __METHOD__, \IPS\convert\App::LOG_WARNING, $info['image_id'] );
			return FALSE;
		}
		
		if ( !isset( $info['image_approved'] ) )
		{
			$info['image_approved'] = 1;
		}
		
		/* Counts */
		foreach( array( 'image_views', 'image_comments', 'image_comments_queued', 'image_ratings_total', 'image_ratings_count', 'image_rating', 'image_hidden_comments', 'image_unapproved_comments', 'image_reviews', 'image_hidden_reviews', 'image_unapproved_reviews' ) AS $count )
		{
			if ( !isset( $info[ $count ] ) )
			{
				$info[ $count ] = 0;
			}
		}

		if ( !isset( $info['image_pinned'] ) )
		{
			$info['image_pinned'] = 0;
		}
		
		if ( !isset( $info['image_media'] ) )
		{
			if ( \strstr( $info['image_file_type'], 'video' ) !== FALSE )
			{
				$info['image_media'] = 1;
			}
			else
			{
				$info['image_media'] = 0;
			}
		}
		
		if ( !isset( $info['image_credit_info'] ) )
		{
			$info['image_credit_info'] = NULL;
		}
		
		if ( !isset( $info['image_copyright'] ) )
		{
			$info['image_copyright'] = NULL;
		}
		
		if ( !isset( $info['image_metadata'] ) )
		{
			try
			{
				$exif = \IPS\Image::create( $image->contents() )->getExifData();
			}
			catch( \LogicException $e )
			{
				$exif = NULL;
			}
			
			$info['image_metadata'] = json_encode( $exif );
		}
		
		$info['image_caption_seo'] = \IPS\Http\Url::seoTitle( $info['image_caption'] );
		
		if ( !isset( $info['image_privacy'] ) )
		{
			$info['image_privacy'] = 0;
		}
		
		$info['image_parent_permission'] = NULL;
		
		if ( !isset( $info['image_feature_flag'] ) )
		{
			$info['image_feature_flag'] = 0;
		}
		
		/* GeoLocation Stuffs */
		if ( isset( $info['image_geolocation'] ) AND $info['image_geolocation'] instanceof \IPS\GeoLocation )
		{
			if ( !isset( $info['image_gps_show'] ) )
			{
				$info['image_gps_show'] = 0;
			}
			$info['image_geolocation']->getLatLong();
			$info['image_gps_lat']		= $info['image_geolocation']->lat;
			$info['image_gps_lon']		= $info['image_geolocation']->long;
			$info['image_loc_short']	= (string) $info['image_geolocation'];
			$info['image_gps_raw']		= json_encode( $info['image_geolocation'] );
			unset( $info['image_geolocation'] );
		}
		else
		{
			$info['image_gps_show']		= 0;
			$info['image_gps_lat']		= NULL;
			$info['image_gps_lon']		= NULL;
			$info['image_loc_short']	= NULL;
			$info['image_gps_raw']		= NULL;
		}
		
		if ( isset( $info['image_approved_by'] ) )
		{
			try
			{
				$info['image_approved_by'] = $this->software->app->getLink( $info['image_approved_by'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['image_approved_by'] = NULL;
			}
		}
		else
		{
			$info['image_approved_by'] = NULL;
		}
		
		if ( isset( $info['image_approved_on'] ) )
		{
			if ( $info['image_approved_on'] instanceof \IPS\DateTime )
			{
				$info['image_approved_on'] = $info['image_approved_on']->getTimestamp();
			}
		}
		else
		{
			$info['image_approved_on'] = NULL;
		}
		
		if ( !isset( $info['image_locked'] ) )
		{
			$info['image_locked'] = 0;
		}
		
		if ( !isset( $info['image_ipaddress'] ) OR filter_var( $info['image_ipaddress'], FILTER_VALIDATE_IP ) === FALSE )
		{
			$info['image_ipaddress'] = '127.0.0.1';
		}
		
		/* We'll build this later */
		$info['image_data'] = NULL;
		
		/* Whew */
		$id = $info['image_id'];
		unset( $info['image_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'gallery_images', $info );
		$this->software->app->addLink( $inserted_id, $id, 'gallery_images' );
		
		return $inserted_id;
	}

	/**
	 * Convert an album comment
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly inserted comment, or FALSE on failure.
	 */
	public function convertGalleryAlbumComment( $info=array() )
	{
		if ( !isset( $info['comment_id'] ) )
		{
			$this->software->app->log( 'gallery_album_comment_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		if ( empty( $info['comment_text'] ) )
		{
			$this->software->app->log( 'gallery_album_comment_missing_content', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['comment_id'] );
			return FALSE;
		}

		if ( isset( $info['comment_album_id'] ) )
		{
			try
			{
				$info['comment_album_id'] = $this->software->app->getLink( $info['comment_album_id'], 'gallery_albums' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'gallery_album_comment_missing_image', __METHOD__, \IPS\convert\ApP::LOG_WARNING, $info['comment_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'gallery_album_omment_missing_image', __METHOD__, \IPS\convert\ApP::LOG_WARNING, $info['comment_id'] );
			return FALSE;
		}

		if ( isset( $info['comment_edit_time'] ) )
		{
			if ( $info['comment_edit_time'] instanceof \IPS\DateTime )
			{
				$info['comment_edit_time'] = $info['comment_edit_time']->getTimestamp();
			}
		}
		else
		{
			$info['comment_edit_time'] = 0;
		}

		if ( isset( $info['comment_author_id'] ) )
		{
			try
			{
				$info['comment_author_id'] = $this->software->app->getLink( $info['comment_author_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['comment_author_id'] = 0;
			}
		}
		else
		{
			$info['comment_author_id'] = 0;
		}

		if ( !isset( $info['comment_author_name'] ) )
		{
			$author = \IPS\Member::load( $info['comment_author_id'] );

			if ( $author->member_id )
			{
				$info['comment_author_name'] = $author->name;
			}
			else
			{
				$info['comment_author_name'] = "Guest";
			}
		}

		if ( !isset( $info['comment_ip_address'] ) OR filter_var( $info['comment_ip_address'], FILTER_VALIDATE_IP ) === FALSE )
		{
			$info['comment_ip_address'] = '127.0.0.1';
		}

		if ( isset( $info['comment_post_date'] ) )
		{
			if ( $info['comment_post_date'] instanceof \IPS\DateTime )
			{
				$info['comment_post_date'] = $info['comment_post_date']->getTimestamp();
			}
		}
		else
		{
			$info['comment_post_date'] = time();
		}

		if ( !isset( $info['comment_approved'] ) )
		{
			$info['comment_approved'] = 1;
		}

		if ( !isset( $info['comment_append_edit'] ) )
		{
			$info['comment_append_edit'] = 0;
		}

		if ( !isset( $info['comment_edit_name'] ) )
		{
			$info['comment_edit_name'] = NULL;
		}

		$id = $info['comment_id'];
		unset( $info['comment_id'] );

		$inserted_id = \IPS\Db::i()->insert( 'gallery_album_comments', $info );
		$this->software->app->addLink( $inserted_id, $id, 'gallery_album_comments' );

		return $inserted_id;
	}

	/**
	 * Convert an album review
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly inserted reiew, or FALSE on failure.
	 */
	public function convertGalleryAlbumReview( $info=array() )
	{
		if ( !isset( $info['review_id'] ) )
		{
			$this->software->app->log( 'gallery_album_review_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		if ( empty( $info['review_content'] ) )
		{
			$this->software->app->log( 'gallery_album_review_missing_content', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
			return FALSE;
		}

		if ( !isset( $info['review_rating'] ) OR $info['review_rating'] < 1 )
		{
			$this->software->app->log( 'gallery_album_review_missing_rating', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
			return FALSE;
		}

		if ( isset( $info['review_author'] ) )
		{
			try
			{
				$info['review_author'] = $this->software->app->getLink( $info['review_author'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'gallery_album_review_missing_author', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'gallery_album_review_missing_author', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
			return FALSE;
		}

		if ( isset( $info['review_album_id'] ) )
		{
			try
			{
				$info['review_album_id'] = $this->software->app->getLink( $info['review_album_id'], 'gallery_albums' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'gallery_album_review_missing_album', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
				return FALSE;
			}
		}

		if ( !isset( $info['review_author_name'] ) )
		{
			$info['review_author_name'] = \IPS\Member::load( $info['review_author'] )->name;
		}

		if ( isset( $info['review_date'] ) )
		{
			if ( $info['review_date'] instanceof \IPS\DateTime )
			{
				$info['review_date'] = $info['review_date']->getTimestamp();
			}
		}
		else
		{
			$info['review_date'] = time();
		}

		if ( !isset( $info['review_ip_address'] ) OR filter_var( $info['review_ip_address'], FILTER_VALIDATE_IP ) === FALSE )
		{
			$info['review_ip_address'] = '127.0.0.1';
		}

		if ( isset( $info['review_edit_time'] ) )
		{
			if ( $info['review_edit_time'] instanceof \IPS\DateTime )
			{
				$info['review_edit_time'] = $info['review_edit_time']->getTimestamp();
			}
		}
		else
		{
			$info['review_edit_time'] = 0;
		}

		if ( !isset( $info['review_edit_member_name'] ) )
		{
			$info['review_edit_member_name'] = '';
		}

		if ( !isset( $info['review_edit_show'] ) )
		{
			$info['review_edit_show'] = 0;
		}

		if ( isset( $info['review_votes_data'] ) )
		{
			if ( !\is_array( $info['review_votes_data'] ) )
			{
				$info['review_votes_data'] = json_decode( $info['review_votes_data'], TRUE );
			}

			$newVoters = array();
			if ( !\is_null( $info['review_votes_data'] ) AND \count( $info['review_votes_data'] ) )
			{
				foreach( $info['review_votes_data'] AS $member => $vote )
				{
					try
					{
						$memberId = $this->software->app->getLink( $member, 'core_members', TRUE );
					}
					catch( \OutOfRangeException $e )
					{
						continue;
					}

					$newVoters[ $memberId ] = $vote;
				}
			}

			if ( \count( $newVoters ) )
			{
				$info['review_votes_data'] = json_encode( $newVoters );
			}
			else
			{
				$info['review_votes_data'] = NULL;
			}
		}
		else
		{
			$info['review_votes_data'] = NULL;
		}

		if ( !isset( $info['review_votes'] ) )
		{
			if ( \is_null( $info['review_votes_data'] ) )
			{
				$info['review_votes'] = 0;
			}
			else
			{
				$info['review_votes'] = \count( json_decode( $info['review_votes_data'], TRUE ) );
			}
		}

		if ( !isset( $info['review_votes_helpful'] ) )
		{
			if ( \is_null( $info['review_votes_data'] ) )
			{
				$info['review_votes_helpful'] = 0;
			}
			else
			{
				$helpful = 0;
				foreach( json_decode( $info['review_votes_data'], TRUE ) AS $member => $vote )
				{
					if ( $vote == 1 )
					{
						$helpful += 1;
					}
				}

				$info['review_votes_helpful'] = $helpful;
			}
		}

		if ( !isset( $info['review_approved'] ) )
		{
			$info['review_approved'] = 1;
		}

		$id = $info['review_id'];
		unset( $info['review_id'] );

		$inserted_id = \IPS\Db::i()->insert( 'gallery_album_reviews', $info );
		$this->software->app->addLink( $inserted_id, $id, 'gallery_album_reviews' );

		return $inserted_id;
	}
		
	/**
	 * Convert a comment
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly inserted comment, or FALSE on failure.
	 */
	public function convertGalleryComment( $info=array() )
	{
		if ( !isset( $info['comment_id'] ) )
		{
			$this->software->app->log( 'gallery_comment_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( empty( $info['comment_text'] ) )
		{
			$this->software->app->log( 'gallery_comment_missing_content', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['comment_id'] );
			return FALSE;
		}
		
		if ( isset( $info['comment_img_id'] ) )
		{
			try
			{
				$info['comment_img_id'] = $this->software->app->getLink( $info['comment_img_id'], 'gallery_images' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'gallery_comment_missing_image', __METHOD__, \IPS\convert\ApP::LOG_WARNING, $info['comment_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'gallery_comment_missing_image', __METHOD__, \IPS\convert\ApP::LOG_WARNING, $info['comment_id'] );
			return FALSE;
		}
		
		if ( isset( $info['comment_edit_time'] ) )
		{
			if ( $info['comment_edit_time'] instanceof \IPS\DateTime )
			{
				$info['comment_edit_time'] = $info['comment_edit_time']->getTimestamp();
			}
		}
		else
		{
			$info['comment_edit_time'] = 0;
		}
		
		if ( isset( $info['comment_author_id'] ) )
		{
			try
			{
				$info['comment_author_id'] = $this->software->app->getLink( $info['comment_author_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['comment_author_id'] = 0;
			}
		}
		else
		{
			$info['comment_author_id'] = 0;
		}
		
		if ( !isset( $info['comment_author_name'] ) )
		{
			$author = \IPS\Member::load( $info['comment_author_id'] );
			
			if ( $author->member_id )
			{
				$info['comment_author_name'] = $author->name;
			}
			else
			{
				$info['comment_author_name'] = "Guest";
			}
		}
		
		if ( !isset( $info['comment_ip_address'] ) OR filter_var( $info['comment_ip_address'], FILTER_VALIDATE_IP ) === FALSE )
		{
			$info['comment_ip_address'] = '127.0.0.1';
		}
		
		if ( isset( $info['comment_post_date'] ) )
		{
			if ( $info['comment_post_date'] instanceof \IPS\DateTime )
			{
				$info['comment_post_date'] = $info['comment_post_date']->getTimestamp();
			}
		}
		else
		{
			$info['comment_post_date'] = time();
		}
		
		if ( !isset( $info['comment_approved'] ) )
		{
			$info['comment_approved'] = 1;
		}
		
		if ( !isset( $info['comment_append_edit'] ) )
		{
			$info['comment_append_edit'] = 0;
		}
		
		if ( !isset( $info['comment_edit_name'] ) )
		{
			$info['comment_edit_name'] = NULL;
		}
		
		$id = $info['comment_id'];
		unset( $info['comment_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'gallery_comments', $info );
		$this->software->app->addLink( $inserted_id, $id, 'gallery_comments' );
		
		return $inserted_id;
	}
	
	/**
	 * Convert a review
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly inserted reiew, or FALSE on failure.
	 */
	public function convertGalleryReview( $info=array() )
	{
		if ( !isset( $info['review_id'] ) )
		{
			$this->software->app->log( 'gallery_review_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( empty( $info['review_content'] ) )
		{
			$this->software->app->log( 'gallery_review_missing_content', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
			return FALSE;
		}
		
		if ( !isset( $info['review_rating'] ) OR $info['review_rating'] < 1 )
		{
			$this->software->app->log( 'gallery_review_missing_rating', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
			return FALSE;
		}
		
		if ( isset( $info['review_author'] ) )
		{
			try
			{
				$info['review_author'] = $this->software->app->getLink( $info['review_author'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'gallery_review_missing_author', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'gallery_review_missing_author', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
			return FALSE;
		}
		
		if ( isset( $info['review_image_id'] ) )
		{
			try
			{
				$info['review_image_id'] = $this->software->app->getLink( $info['review_image_id'], 'gallery_images' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'gallery_review_missing_image', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['review_id'] );
				return FALSE;
			}
		}
		
		if ( !isset( $info['review_author_name'] ) )
		{
			$info['review_author_name'] = \IPS\Member::load( $info['review_author'] )->name;
		}
		
		if ( isset( $info['review_date'] ) )
		{
			if ( $info['review_date'] instanceof \IPS\DateTime )
			{
				$info['review_date'] = $info['review_date']->getTimestamp();
			}
		}
		else
		{
			$info['review_date'] = time();
		}
		
		if ( !isset( $info['review_ip_address'] ) OR filter_var( $info['review_ip_address'], FILTER_VALIDATE_IP ) === FALSE )
		{
			$info['review_ip_address'] = '127.0.0.1';
		}
		
		if ( isset( $info['review_edit_time'] ) )
		{
			if ( $info['review_edit_time'] instanceof \IPS\DateTime )
			{
				$info['review_edit_time'] = $info['review_edit_time']->getTimestamp();
			}
		}
		else
		{
			$info['review_edit_time'] = 0;
		}
		
		if ( !isset( $info['review_edit_member_name'] ) )
		{
			$info['review_edit_member_name'] = '';
		}
		
		if ( !isset( $info['review_edit_show'] ) )
		{
			$info['review_edit_show'] = 0;
		}
		
		if ( isset( $info['review_votes_data'] ) )
		{
			if ( !\is_array( $info['review_votes_data'] ) )
			{
				$info['review_votes_data'] = json_decode( $info['review_votes_data'], TRUE );
			}
			
			$newVoters = array();
			if ( !\is_null( $info['review_votes_data'] ) AND \count( $info['review_votes_data'] ) )
			{
				foreach( $info['review_votes_data'] AS $member => $vote )
				{
					try
					{
						$memberId = $this->software->app->getLink( $member, 'core_members', TRUE );
					}
					catch( \OutOfRangeException $e )
					{
						continue;
					}
					
					$newVoters[$memberId] = $vote;
				}
			}
			
			if ( \count( $newVoters ) )
			{
				$info['review_votes_data'] = json_encode( $newVoters );
			}
			else
			{
				$info['review_votes_data'] = NULL;
			}
		}
		else
		{
			$info['review_votes_data'] = NULL;
		}
		
		if ( !isset( $info['review_votes'] ) )
		{
			if ( \is_null( $info['review_votes_data'] ) )
			{
				$info['review_votes'] = 0;
			}
			else
			{
				$info['review_votes'] = \count( json_decode( $info['review_votes_data'], TRUE ) );
			}
		}
		
		if ( !isset( $info['review_votes_helpful'] ) )
		{
			if ( \is_null( $info['review_votes_data'] ) )
			{
				$info['review_votes_helpful'] = 0;
			}
			else
			{
				$helpful = 0;
				foreach( json_decode( $info['review_votes_data'], TRUE ) AS $member => $vote )
				{
					if ( $vote == 1 )
					{
						$helpful += 1;
					}
				}
				
				$info['review_votes_helpful'] = $helpful;
			}
		}
		
		if ( !isset( $info['review_approved'] ) )
		{
			$info['review_approved'] = 1;
		}
		
		$id = $info['review_id'];
		unset( $info['review_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'gallery_reviews', $info );
		$this->software->app->addLink( $inserted_id, $id, 'gallery_reviews' );
		
		return $inserted_id;
	}
	
	/**
	 * Convert an attachment
	 *
	 * @param	array			$info		Data to insert
	 * @param	array			$map		Attachment Map Data
	 * @param	string|NULL		$filepath	The path to the attachment, or NULL.
	 * @param	string|NULL		$filedata	The binary data of the attachment, or NULL.
	 * @param	string|NULL		$thumbnailpath	Path to thumbnail, or NULL.
	 * @return	integer|boolean	The ID of the newly inserted attachment, or FALSE on failure.
	 */
	public function convertAttachment( $info=array(), $map=array(), $filepath=NULL, $filedata=NULL, $thumbnailpath = NULL )
	{
		$map['location_key']	= 'gallery_Gallery';
		$map['id1_type']		= 'gallery_images';
		$map['id1_from_parent']	= FALSE;
		$map['id2_from_parent']	= FALSE;
		/* Some set up */
		if ( !isset( $info['id3'] ) )
		{
			$info['id3'] = NULL;
		}
		
		if ( \is_null( $info['id3'] ) OR $info['id3'] != 'review' )
		{
			$map['id2_type'] = 'gallery_comments';
		}
		else
		{
			$map['id2_type'] = 'gallery_reviews';
		}
		
		return parent::convertAttachment( $info, $map, $filepath, $filedata, $thumbnailpath );
	}

	/**
	 * Convert a Club Gallery Category
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly inserted category, or FALSE on failure.
	 */
	public function convertClubGalleryCategory( $info=array() )
	{
		$insertedId = $this->convertGalleryCategory( $info );
		if ( $insertedId )
		{
			$this->software->app->addLink( $insertedId, $info['category_id'], 'core_clubs_gallery_categories' );
		}
		return $insertedId;
	}

	/**
	 * Convert a Club Gallery Image
	 *
	 * @param	array			$info		Data to insert
	 * @param	string|NULL		$filepath	The path to the image, or NULL.
	 * @param	string|NULL		$filedata	The binary data for the image, or NULL
	 * @return	integer|boolean	The ID of the newly inserted image, or FALSE on failure.
	 */
	public function convertClubGalleryImage( $info=array(), $filepath=NULL, $filedata=NULL )
	{
		$insertedId = $this->convertGalleryImage( $info, $filepath, $filedata );
		if ( $insertedId )
		{
			$this->software->app->addLink( $insertedId, $info['image_id'], 'core_club_gallery_images' );
		}
		return $insertedId;
	}

	/**
	 * Convert a Club Gallery Comment
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly inserted comment, or FALSE on failure.
	 */
	public function convertClubGalleryComment( $info=array() )
	{
		$insertedId = $this->convertGalleryComment( $info );
		if ( $insertedId )
		{
			$this->software->app->addLink( $insertedId, $info['comment_id'], 'core_club_gallery_comments' );
		}
		return $insertedId;
	}

	/**
	 * Convert a Club Gallery Review
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly inserted review, or FALSE on failure.
	 */
	public function convertClubGalleryReview( $info=array() )
	{
		$insertedId = $this->convertGalleryReview( $info );
		if ( $insertedId )
		{
			$this->software->app->addLink( $insertedId, $info['review_id'], 'core_club_gallery_reviews' );
		}
		return $insertedId;
	}
}