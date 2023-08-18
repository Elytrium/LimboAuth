<?php

/**
 * @brief		Converter Invision Community Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		13 July 2021
 */

namespace IPS\convert\Software\Gallery;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Invision Gallery Converter
 */
class _Invisioncommunity extends \IPS\convert\Software
{
	/**
	 * @brief    Whether the versions of IPS4 match
	 */
	public static $versionMatch = FALSE;

	/**
	 * @brief    Whether the database has been required
	 */
	public static $dbNeeded = FALSE;

	/**
	 * Constructor
	 *
	 * @param \IPS\convert\App $app The application to reference for database and other information.
	 * @param bool $needDB Establish a DB connection
	 * @return    void
	 * @throws    \InvalidArgumentException
	 */
	public function __construct( \IPS\convert\App $app, $needDB = TRUE )
	{
		/* Set filename obscuring flag */
		\IPS\convert\Library::$obscureFilenames = FALSE;

		$return = parent::__construct( $app, $needDB );

		if ( $needDB )
		{
			static::$dbNeeded = TRUE;

			try
			{
				$version = $this->db->select( 'app_version', 'core_applications', array( 'app_directory=?', 'core' ) )->first();

				/* We're matching against the human version since the long version can change with patches */
				if ( $version == \IPS\Application::load( 'core' )->version )
				{
					static::$versionMatch = TRUE;
				}
			}
			catch( \IPS\Db\Exception $e ) {}

			/* Get parent sauce */
			$this->parent = $this->app->_parent->getSource();
		}

		return $return;
	}

	/**
	 * Software Name
	 *
	 * @return    string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return 'Invision Community (' . \IPS\Application::load( 'core' )->version . ')';
	}

	/**
	 * Software Key
	 *
	 * @return    string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "invisioncommunity";
	}

	/**
	 * Content we can convert from this software.
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		if( !static::$versionMatch AND static::$dbNeeded )
		{
			throw new \IPS\convert\Exception( 'convert_invision_mismatch' );
		}

		return array(
			'convertGalleryAlbums'			=> array(
				'table'						=> 'gallery_albums',
				'where'						=> NULL,
			),
			'convertGalleryAlbumComments'	=> array(
				'table'						=> 'gallery_album_comments',
				'where'						=> NULL,
			),
			'convertGalleryAlbumReviews'	=> array(
				'table'						=> 'gallery_album_reviews',
				'where'						=> NULL,
			),
			'convertGalleryCategories'	    => array(
				'table'						=> 'gallery_categories',
				'where'						=> NULL,
			),
			'convertGalleryComments'		=> array(
				'table'						=> 'gallery_comments',
				'where'						=> NULL,
			),
			'convertGalleryImages'		    => array(
				'table'						=> 'gallery_images',
				'where'						=> NULL,
			),
			'convertGalleryReviews'	        => array(
				'table'						=> 'gallery_reviews',
				'where'						=> NULL,
			),
			'convertAttachments'	        => array(
				'table'						=> 'core_attachments',
				'where'						=> NULL,
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
	 * @return	array
	 */
	public static function parents()
	{
		return array( 'core' => array( 'invisioncommunity' ) );
	}

	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertAttachments',
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
			case 'convertAttachments':
			case 'convertGalleryImages':
				\IPS\Member::loggedIn()->language()->words["upload_path"] = \IPS\Member::loggedIn()->language()->addToStack( 'convert_invision_upload_input' );
				\IPS\Member::loggedIn()->language()->words["upload_path_desc"] = \IPS\Member::loggedIn()->language()->addToStack( 'convert_invision_upload_input_desc' );
				$return[ $method ] = array(
					'upload_path'				=> array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Text',
						'field_default'		=> isset( $this->parent->app->_session['more_info']['convertEmoticons']['upload_path'] ) ? $this->parent->app->_session['more_info']['convertEmoticons']['upload_path'] : NULL,
						'field_required'	=> TRUE,
						'field_extra'		=> array(),
						'field_hint'		=> \IPS\Member::loggedIn()->language()->addToStack('convert_invision_upload_path'),
						'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
					)
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
		\IPS\Task::queue( 'convert', 'InvisionCommunityRebuildContent', array( 'app' => $this->app->app_id, 'link' => 'gallery_comments', 'class' => 'IPS\gallery\Image\Comment' ), 2, array( 'app', 'link', 'class' ) );
		\IPS\Task::queue( 'convert', 'InvisionCommunityRebuildContent', array( 'app' => $this->app->app_id, 'link' => 'gallery_reviews', 'class' => 'IPS\gallery\Image\Review' ), 2, array( 'app', 'link', 'class' ) );
		\IPS\Task::queue( 'core', 'RebuildItemCounts', array( 'class' => 'IPS\gallery\Image' ), 3, array( 'class' ) );
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\gallery\Album', 'count' => 0 ), 4, array( 'class' ) );
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\gallery\Category', 'count' => 0 ), 5, array( 'class' ) );

		/* Caches */
		\IPS\Task::queue( 'convert', 'RebuildTagCache', array( 'app' => $this->app->app_id, 'link' => 'gallery_images', 'class' => 'IPS\gallery\Image' ), 3, array( 'app', 'link', 'class' ) );

		return array( "f_gallery_images_rebuild", "f_gallery_cat_recount", "f_gallery_album_recount", "f_gallery_image_recount", "f_image_tags_recount" );
	}

	/**
	 * Convert attachments
	 *
	 * @return	void
	 */
	public function convertAttachments()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'attach_id' );

		foreach( $this->fetch( 'core_attachments', 'attach_id' ) AS $row )
		{
			try
			{
				$attachmentMap = $this->db->select( '*', 'core_attachments_map', array( 'attachment_id=? AND location_key=?', $row['attach_id'], 'gallery_Gallery' ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$libraryClass->setLastKeyValue( $row['attach_id'] );
				continue;
			}

			/* Remove non-standard columns */
			$this->parent->unsetNonStandardColumns( $row, 'core_attachments' );
			$this->parent->unsetNonStandardColumns( $attachmentMap, 'core_attachments_map' );

			/* Remap rows */
			$name = explode( '/', $row['attach_location'] );
			$row['attach_container'] = isset( $name[1] ) ? $name[0] : NULL;
			$thumbName = explode( '/', $row['attach_thumb_location'] );
			$row['attach_thumb_container'] = isset( $thumbName[1] ) ? $thumbName[0] : NULL;

			$filePath = $this->app->_session['more_info']['convertAttachments']['upload_path'] . '/' . $row['attach_location'];
			$thumbnailPath = $this->app->_session['more_info']['convertAttachments']['upload_path'] . '/' . $row['attach_thumb_location'];

			unset( $row['attach_file'] );

			$libraryClass->convertAttachment( $row, $attachmentMap, $filePath, NULL, $thumbnailPath );
			$libraryClass->setLastKeyValue( $row['attach_id'] );
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
		$libraryClass::setKey( 'album_id' );

		foreach( $this->fetch( 'gallery_albums', 'album_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->parent->unsetNonStandardColumns( $row, 'gallery_albums', 'gallery' );

			$id = $libraryClass->convertGalleryAlbum( $row );

			if( !empty( $id ) )
			{
				/* Convert Follows */
				foreach( $this->db->select( '*', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=?', 'gallery', 'album', $row['album_id'] ) ) as $follow )
				{
					/* Remove non-standard columns */
					$this->parent->unsetNonStandardColumns( $follow, 'core_follow', 'core' );

					/* Change follow data */
					$follow['follow_rel_id_type'] = 'gallery_albums';

					$libraryClass->convertFollow( $follow );
				}

				/* Reputation */
				foreach( $this->db->select( '*', 'core_reputation_index', array( "app=? AND type=? AND type_id=?", 'gallery', 'album_id', $row['album_id'] ) ) AS $rep )
				{
					/* Remove non-standard columns */
					$this->parent->unsetNonStandardColumns( $rep, 'core_reputation_index', 'core' );

					$libraryClass->convertReputation( $rep );
				}
			}

			$libraryClass->setLastKeyValue( $row['album_id'] );
		}
	}

	/**
	 * Convert album comments
	 *
	 * @return	void
	 */
	public function convertGalleryAlbumComments()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'comment_id' );

		foreach( $this->fetch( 'gallery_album_comments', 'comment_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->parent->unsetNonStandardColumns( $row, 'gallery_album_comments', 'gallery' );

			$id = $libraryClass->convertGalleryAlbumComment( $row );

			if( !empty( $id ) )
			{
				/* Reputation */
				foreach( $this->db->select( '*', 'core_reputation_index', array( "app=? AND type=? AND type_id=?", 'gallery', 'album_comment', $row['comment_id'] ) ) AS $rep )
				{
					/* Remove non-standard columns */
					$this->parent->unsetNonStandardColumns( $rep, 'core_reputation_index', 'core' );

					$libraryClass->convertReputation( $rep );
				}
			}

			$libraryClass->setLastKeyValue( $row['comment_id'] );
		}
	}

	/**
	 * Convert album reviews
	 *
	 * @return	void
	 */
	public function convertGalleryAlbumReviews()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'review_id' );

		foreach( $this->fetch( 'gallery_album_reviews', 'review_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->parent->unsetNonStandardColumns( $row, 'gallery_album_reviews', 'gallery' );

			$id = $libraryClass->convertGalleryAlbumReview( $row );

			if( !empty( $id ) )
			{
				/* Reputation */
				foreach( $this->db->select( '*', 'core_reputation_index', array( "app=? AND type=? AND type_id=?", 'gallery', 'album_review', $row['review_id'] ) ) AS $rep )
				{
					/* Remove non-standard columns */
					$this->parent->unsetNonStandardColumns( $rep, 'core_reputation_index', 'core' );

					$libraryClass->convertReputation( $rep );
				}
			}

			$libraryClass->setLastKeyValue( $row['review_id'] );
		}
	}

	/**
	 * Convert gallery category
	 *
	 * @return	void
	 */
	public function convertGalleryCategories()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'category_id' );

		foreach( $this->fetch( 'gallery_categories', 'category_id'  ) AS $row )
		{
			/* Remove non-standard columns */
			$this->parent->unsetNonStandardColumns( $row, 'gallery_categories', 'gallery' );

			/* Add name after clearing other columns */
			$row['category_name'] = $this->parent->getWord( 'gallery_category_' . $row['category_id'] );
			$row['category_desc'] = $this->parent->getWord( 'gallery_category_' . $row['category_id'] . '_desc' );

			$id = $libraryClass->convertGalleryCategory( $row );

			if( !empty( $id ) )
			{
				/* Convert Follows */
				foreach ( $this->db->select( '*', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=?', 'gallery', 'category', $row['category_id'] ) ) as $follow )
				{
					/* Remove non-standard columns */
					$this->parent->unsetNonStandardColumns( $follow, 'core_follow', 'core' );

					/* Change follow data */
					$follow['follow_rel_id_type'] = 'gallery_categories';

					$libraryClass->convertFollow( $follow );
				}
			}

			$libraryClass->setLastKeyValue( $row['category_id'] );
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
		$libraryClass::setKey( 'comment_id' );

		foreach( $this->fetch( 'gallery_comments', 'comment_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->parent->unsetNonStandardColumns( $row, 'gallery_comments', 'gallery' );

			$id = $libraryClass->convertGalleryComment( $row );

			if( !empty( $id ) )
			{
				/* Reputation */
				foreach( $this->db->select( '*', 'core_reputation_index', array( "app=? AND type=? AND type_id=?", 'gallery', 'comment_id', $row['comment_id'] ) ) AS $rep )
				{
					/* Remove non-standard columns */
					$this->parent->unsetNonStandardColumns( $rep, 'core_reputation_index', 'core' );

					$libraryClass->convertReputation( $rep );
				}
			}

			$libraryClass->setLastKeyValue( $row['comment_id'] );
		}
	}

	/**
	 * Convert gallery images
	 *
	 * @return	void
	 */
	public function convertGalleryImages()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'image_id' );

		foreach( $this->fetch( 'gallery_images', 'image_id' ) AS $row )
		{
			/* Remove non-standard columns */
			$this->parent->unsetNonStandardColumns( $row, 'gallery_images', 'gallery' );

			/* Remap rows */
			$name = explode( '/', $row['image_original_file_name'] );
			$row['image_container'] = isset( $name[1] ) ? $name[0] : NULL;

			$filePath = $this->app->_session['more_info']['convertGalleryImages']['upload_path'] . '/' . $row['image_original_file_name'];
			unset( $row['image_original_file_name'] );

			$id = $libraryClass->convertGalleryImage( $row, $filePath );

			if( !empty( $id ) )
			{
				/* Convert Follows */
				foreach ( $this->db->select( '*', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=?', 'gallery', 'image', $row['image_id'] ) ) as $follow )
				{
					/* Remove non-standard columns */
					$this->parent->unsetNonStandardColumns( $follow, 'core_follow', 'core' );

					/* Change follow data */
					$follow['follow_rel_id_type'] = 'gallery_images';

					$libraryClass->convertFollow( $follow );
				}

				/* Convert Tags */
				foreach ( $this->db->select( '*', 'core_tags', array( 'tag_meta_app=? AND tag_meta_area=? AND tag_meta_id=?', 'gallery', 'gallery', $row['image_id'] ) ) as $tag )
				{
					/* Remove non-standard columns */
					$this->parent->unsetNonStandardColumns( $tag, 'core_tags', 'core' );

					$libraryClass->convertTag( $tag );
				}
			}

			$libraryClass->setLastKeyValue( $row['image_id'] );
		}
	}

	/**
	 * Check if we can redirect the legacy URLs from this software to the new locations
	 *
	 * @return	NULL|\IPS\Http\Url
	 */
	public function checkRedirects()
	{
		$url = \IPS\Request::i()->url();

		if( !\stristr( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'ic-merge-' . $this->app->_parent->app_id ) )
		{
			return NULL;
		}

		/* account for non-mod_rewrite links */
		$searchOn = \stristr( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'index.php' ) ? $url->data[ \IPS\Http\Url::COMPONENT_QUERY ] : $url->data[ \IPS\Http\Url::COMPONENT_PATH ];

		if( preg_match( '#/(category|album|image)/([0-9]+)-(.+?)#i', $searchOn, $matches ) )
		{
			$oldId	= (int) $matches[2];

			switch( $matches[1] )
			{
				case 'category':
					$class	= '\IPS\gallery\Category';
					$types	= array( 'gallery_categories' );
					break;

				case 'album':
					$class	= '\IPS\gallery\Album';
					$types	= array( 'gallery_albums' );
					break;

				case 'image':
					$class	= '\IPS\gallery\Image';
					$types	= array( 'gallery_images' );
					break;
			}
		}

		if( isset( $class ) )
		{
			try
			{
				try
				{
					$data = (string) $this->app->getLink( $oldId, $types );
				}
				catch( \OutOfRangeException $e )
				{
					$data = (string) $this->app->getLink( $oldId, $types, FALSE, TRUE );
				}
				$item = $class::load( $data );

				if( $item instanceof \IPS\Content )
				{
					if( $item->canView() )
					{
						return $item->url();
					}
				}
				elseif( $item instanceof \IPS\Node\Model )
				{
					if( $item->can( 'view' ) )
					{
						return $item->url();
					}
				}
			}
			catch( \Exception $e )
			{
				return NULL;
			}
		}

		return NULL;
	}
}