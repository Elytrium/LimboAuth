<?php

/**
 * @brief		Converter Coppermine Gallery Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		21 Jan 2015
 */

namespace IPS\convert\Software\Gallery;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Coppermine Gallery Converter
 */
class _Coppermine extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "Coppermine (phpBB 3.1.x/3.2.x)";
	}

	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "coppermine";
	}

	/**
	 * Content we can convert from this software.
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertGalleryCategories'	=> array(
				'table'							=> 'categories',
				'where'							=> NULL
			),
			'convertGalleryAlbums'		=> array(
				'table'							=> 'albums',
				'where'							=> NULL,
			),
			'convertGalleryImages'		=> array(
				'table'							=> 'pictures',
				'where'							=> NULL,
			),
			'convertGalleryComments'		=> array(
				'table'							=> 'comments',
				'where'							=> NULL,
			)
		);
	}

	/**
	 * Possible Parent Conversions
	 *
	 * @return	NULL|array
	 */
	public static function parents()
	{
		return array( 'core' => array( 'phpbb' ) );
	}

	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertGalleryAlbums',
			'convertGalleryImages'
		);
	}

	/**
	 * Get More Information
	 *
	 * @param	string	$method	Method name
	 * @return	array
	 */
	public function getMoreInfo( $method )
	{
		$return = array();
		switch( $method )
		{
			case 'convertGalleryAlbums':
				$options = array();
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'gallery_categories' ), 'IPS\gallery\Category' ) AS $category )
				{
					$options[$category->_id] = $category->_title;
				}

				$return['convertGalleryAlbums']['members_gallery_category'] = array(
					'field_class'		=> 'IPS\\Helpers\\Form\\Select',
					'field_default'		=> NULL,
					'field_required'	=> FALSE,
					'field_extra'		=> array(
						'options'			=> $options
					),
					'field_hint'		=> NULL,
				);
				break;
			case 'convertGalleryImages':
				$return['convertGalleryImages']['file_location'] = array(
					'field_class'		=> 'IPS\\Helpers\\Form\\Text',
					'field_default'		=> NULL,
					'field_required'	=> TRUE,
					'field_extra'		=> array(),
					'field_hint'		=> \IPS\Member::loggedIn()->language()->addToStack( 'convert_coppermine_filehint' ),
					'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);
				break;
		}

		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : array();
	}

	/**
	 * Finish - Adds everything it needs to the queues and clears data store
	 *
	 * @return	array		Messages to display
	 */
	public function finish()
	{
		/* Content Rebuilds */
		\IPS\Task::queue( 'convert', 'RebuildGalleryImages', array( 'app' => $this->app->app_id ), 2, array( 'app' ) );
		\IPS\Task::queue( 'convert', 'RebuildContent', array( 'app' => $this->app->app_id, 'link' => 'gallery_comments', 'class' => 'IPS\gallery\Image\Comment' ), 2, array( 'app', 'link', 'class' ) );
		\IPS\Task::queue( 'core', 'RebuildItemCounts', array( 'class' => 'IPS\gallery\Image' ), 3, array( 'class' ) );
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\gallery\Album', 'count' => 0 ), 4, array( 'class' ) );
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\gallery\Category', 'count' => 0 ), 5, array( 'class' ) );

		/* Caches */
		\IPS\Task::queue( 'convert', 'RebuildTagCache', array( 'app' => $this->app->app_id, 'link' => 'gallery_images', 'class' => 'IPS\gallery\Image' ), 3, array( 'app', 'link', 'class' ) );

		return array( "f_gallery_images_rebuild", "f_gallery_cat_recount", "f_gallery_album_recount", "f_gallery_image_recount", "f_image_tags_recount" );
	}

	/**
	 * Convert categories
	 *
	 * @return	void
	 */
	public function convertGalleryCategories()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'cid' );

		foreach( $this->fetch( 'categories', 'cid' ) AS $row )
		{
			$libraryClass->convertGalleryCategory( array(
				'category_id'			=> $row['cid'],
				'category_name'			=> $row['name'],
				'category_desc'			=> $row['description'],
				'category_parent_id'	=> $row['parent'],
				'category_position'		=> $row['pos']
			) );

			$libraryClass->setLastKeyValue( $row['cid'] );
		}
	}

	/**
	 * Convert albums
	 *
	 * @return	void
	 */
	public function convertGalleryAlbums()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'aid' );

		foreach( $this->fetch( 'albums', 'aid' ) AS $row )
		{
			$info = array(
				'album_id'					=> $row['aid'],
				'album_owner_id'			=> isset( $row['owner'] ) ? $row['owner'] : NULL,
				'album_category_id'			=> $row['category'],
				'album_description'			=> $row['description'],
				'album_position'			=> $row['pos'],
				'album_name'				=> $row['title'],
				'album_allow_comments'		=> ( $row['comments'] == 'YES' ),
				'album_allow_reviews'		=> ( $row['comments'] == 'YES' ),
				'album_allow_rating'		=> ( $row['votes'] == 'YES' )
			);

			$category = NULL;
			try
			{
				$this->app->getLink( $row['category'], 'gallery_categories' );
			}
			catch( \Exception $e )
			{
				$category = $this->app->_session['more_info']['convertGalleryAlbums']['members_gallery_category'];
				if ( $category == 0 )
				{
					$category = NULL;
				}
			}

			$libraryClass->convertGalleryAlbum( $info, NULL, $category );
			$libraryClass->setLastKeyValue( $row['aid'] );
		}
	}

	/**
	 * Convert images
	 *
	 * @return	void
	 */
	public function convertGalleryImages()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'pid' );

		foreach( $this->fetch( 'pictures', 'pid' ) AS $row )
		{
			$info = array(
				'image_id'				=> $row['pid'],
				'image_album_id'		=> $row['aid'],
				'image_member_id'		=> $row['owner_id'],
				/* Some images in the test data don't have titles, use filename if title is missing */
				'image_caption'			=> $row['title'] ?: $row['filename'],
				'image_description'		=> $row['caption'],
				'image_views'			=> $row['hits'],
				'image_date'			=> $row['ctime'],
				'image_updated'			=> \strtotime( $row['mtime'] ),
				'image_ipaddress'		=> $row['pic_raw_ip'],
				'image_file_name'		=> $row['filename']
			);

			/* Filepath */
			$path = rtrim( $this->app->_session['more_info']['convertGalleryImages']['file_location'], '/' ) . '/' . $row['filepath'] . '/' . $row['filename'];

			$libraryClass->convertGalleryImage( $info, $path );

			/* Tags */
			if( !empty( $row['keywords'] ) )
			{
				$keywords = explode( ' ', $row['keywords'] );
				foreach( $keywords as $key )
				{
					$word = explode( ',', $key );

					foreach( $word as $w )
					{
						$libraryClass->convertTag( array(
							'tag_meta_app'			=> 'gallery',
							'tag_meta_area'			=> 'gallery',
							'tag_meta_parent_id'	=> $row['aid'],
							'tag_meta_id'			=> $row['pid'],
							'tag_text'				=> $w,
							'tag_prefix'			=> 0,
							'tag_member_id'			=> $row['owner_id'],
							'tag_added'             => $row['ctime']
						) );
					}
				}
			}

			$libraryClass->setLastKeyValue( $row['pid'] );
		}
	}

	/**
	 * Convert comments
	 *
	 * @return	void
	 */
	public function convertGalleryComments()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'msg_id' );

		foreach( $this->fetch( 'comments', 'msg_id' ) AS $row )
		{
			$libraryClass->convertGalleryComment( array(
				'comment_id'			=> $row['msg_id'],
				'comment_text'			=> $row['msg_body'],
				'comment_img_id'		=> $row['pid'],
				'comment_author_id'		=> $row['author_id'],
				'comment_author_name'	=> $row['msg_author'],
				'comment_ip_address'	=> $row['msg_raw_ip'],
				'comment_post_date'		=> \strtotime( $row['msg_date'] ),
			) );

			$libraryClass->setLastKeyValue( $row['msg_id'] );
		}
	}
}