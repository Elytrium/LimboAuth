<?php

/**
 * @brief		Converter PhotoPlog Gallery Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		02 October 2016
 */

namespace IPS\convert\Software\Gallery;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Photoplog Gallery Converter
 */
class _Photoplog extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "PhotoPlog (vBulletin 3.x/4.x)";
	}

	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "photoplog";
	}

	/**
	 * Content we can convert from this software.
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertGalleryCategories' => array(
				'table'		=> 'categories',
				'where'		=> NULL
			),
			'convertGalleryAlbums'=> array(
				'table'		=> 'useralbums',
				'where'		=> NULL,
			),
			'convertGalleryImages'	=> array(
				'table'		=> 'fileuploads',
				'where'		=> NULL
			),
			'convertGalleryComments'	=> array(
				'table'		=> 'ratecomment',
				'where'		=> array( "rating=?", 0 )
			)
		);
	}

	/**
	 * Requires Parent
	 *
	 * @return	boolean
	 */
	public static function requiresParent()
	{
		return TRUE;
	}

	/**
	 * Possible Parent Conversions
	 *
	 * @return	NULL|array
	 */
	public static function parents()
	{
		return array( 'core' => array( 'vbulletin' ) );
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

		return array( "f_gallery_images_rebuild", "f_gallery_cat_recount", "f_gallery_album_recount", "f_gallery_image_recount" );
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
					$options[ $category->_id ] = $category->_title;
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
					'field_hint'		=> \IPS\Member::loggedIn()->language()->addToStack('convert_photoplog_hint'),
					'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);
				break;
		}

		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : array();
	}

	/**
	 * Convert Photoplog Albums
	 *
	 * @return	void
	 */
	public function convertGalleryAlbums()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'albumid' );

		foreach( $this->fetch( 'useralbums', 'albumid' ) AS $row )
		{
			$info = array(
				'album_id'					=> $row['albumid'],
				'album_owner_id'			=> $row['userid'],
				'album_description'			=> $row['description'],
				'album_name'				=> $row['title'],
				'album_type'				=> $row['visible'] ?: 0
			);

			$category = $this->app->_session['more_info']['convertGalleryAlbums']['members_gallery_category'];
			if ( $category == 0 )
			{
				$category = NULL;
			}

			$libraryClass->convertGalleryAlbum( $info, NULL, $category );

			$libraryClass->setLastKeyValue( $row['albumid'] );
		}
	}

	/**
	 * Convert Photoplog Categories
	 *
	 * @return	void
	 */
	public function convertGalleryCategories()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'catid' );

		foreach( $this->fetch( 'categories', 'catid' ) AS $row )
		{
			$libraryClass->convertGalleryCategory( array(
				'category_id'			=> $row['catid'],
				'category_name'			=> $row['title'],
				'category_desc'			=> $row['description'],
				'category_parent_id'	=> $row['parentid'] > 0 ? $row['parentid'] : 0,
				'category_position'		=> $row['displayorder']
			) );
		}

		$libraryClass->setLastKeyValue( $row['catid'] );
	}

	/**
	 * Convert Photoplog Images
	 *
	 * @return	void
	 */
	public function convertGalleryImages()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'fileid' );

		foreach( $this->fetch( 'fileuploads', 'fileid' ) AS $row )
		{
			$album = \unserialize( $row['albumids'] );
			$albumId = NULL;

			if( \is_array( $album ) )
			{
				$albumId = $album[0];
			}

			$info = array(
				'image_id'				=> $row['fileid'],
				'image_album_id'		=> $albumId,
				'image_category_id'		=> $row['catid'],
				'image_member_id'		=> $row['userid'],
				'image_caption'			=> $row['title'],
				'image_views'			=> $row['views'],
				'image_comments'		=> $row['num_comments0'] + $row['num_comments1'],
				'image_ratings_total'	=> $row['sum_ratings1'],
				'image_ratings_count'	=> $row['num_ratings1'],
				'image_rating'			=> $row['num_ratings1'] > 0 ? \intval( $row['sum_ratings1'] / $row['num_ratings1'] ) : 0,
				'image_date'			=> $row['dateline'],
				'image_metadata'		=> json_encode( \unserialize( $row['exifinfo'] ) ),
				'image_file_name'		=> $row['filename'],
				'image_description'		=> $row['description'],
				'image_approved'		=> $row['moderate'] == 1 ? 0 : 1,
			);

			$libraryClass->convertGalleryImage( $info, rtrim( $this->app->_session['more_info']['convertGalleryImages']['file_location'], '/' ) . '/' . $row['userid'] . '/' . $row['filename'] );

			/* Ratings! */
			foreach( $this->db->select( '*', 'ratecomment', array( "fileid=? AND rating>0", $row['fileid'] ) ) AS $rating )
			{
				$libraryClass->convertRating( array(
					'id'		=> $rating['commentid'],
					'class'		=> 'IPS\gallery\Image',
					'item_link'	=> 'gallery_images',
					'item_id'	=> $row['fileid'],
					'rating'	=> $rating['rating'],
					'member'	=> $rating['userid'],
				) );
			}

			$libraryClass->setLastKeyValue( $row['fileid'] );
		}
	}

	/**
	 * Convert Photoplog Comments
	 *
	 * @return	void
	 */
	public function convertGalleryComments()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'commentid' );

		foreach( $this->fetch( 'ratecomment', 'commentid', array( "rating=?", 0 ) ) AS $row )
		{
			$libraryClass->convertGalleryComment( array(
				'comment_id'			=> $row['commentid'],
				'comment_text'			=> $row['comment'],
				'comment_img_id'		=> $row['fileid'],
				'comment_author_id'		=> $row['userid'],
				'comment_author_name'	=> $row['username'],
				'comment_post_date'		=> $row['dateline'],
				'comment_approved'		=> ( $row['moderate'] == 1 ) ? 0 : 1,
			) );

			$libraryClass->setLastKeyValue( $row['commentid'] );
		}
	}
}