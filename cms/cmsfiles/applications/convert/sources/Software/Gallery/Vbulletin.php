<?php

/**
 * @brief		Converter vBulletin 4.x Gallery Class
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
 * vBulletin Gallery Converter
 */
class _Vbulletin extends \IPS\convert\Software
{
	/**
	 * @brief	vBulletin 4 Stores all attachments under one table - this will store the content type for the forums app.
	 */
	protected static $imageContentType		= NULL;
	
	/**
	 * @brief	The schematic for vB3 and vB4 is similar enough that we can make specific concessions in a sinle converter for either version.
	 */
	protected static $isLegacy					= NULL;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\convert\App	$app	The application to reference for database and other information.
	 * @param	bool				$needDB	Establish a DB connection
	 * @return	void
	 * @throws	\InvalidArgumentException
	 */
	public function __construct( \IPS\convert\App $app, $needDB=TRUE )
	{
		$return = parent::__construct( $app, $needDB );
		
		/* Is this vB3 or vB4? */
		if ( $needDB )
		{
			try
			{
				if ( static::$isLegacy === NULL )
				{
					$version = $this->db->select( 'value', 'setting', array( "varname=?", 'templateversion' ) )->first();
					
					if ( mb_substr( $version, 0, 1 ) == '3' )
					{
						static::$isLegacy = TRUE;
					}
					else
					{
						static::$isLegacy = FALSE;
					}
				}
				
				
				/* If this is vB4, what is the content type ID for posts? */
				if ( static::$imageContentType === NULL AND ( static::$isLegacy === FALSE OR \is_null( static::$isLegacy ) ) )
				{
					static::$imageContentType = $this->db->select( 'contenttypeid', 'contenttype', array( "class=?", 'Album' ) )->first();
				}
			}
			catch( \Exception $e ) {}
		}
		
		return $return;
	}
	
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "vBulletin Gallery (3.8.x/4.x)";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "vbulletin";
	}
	
	/**
	 * Content we can convert from this software. 
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		$imageWhere = NULL;
		$imageTable = 'picture';
		
		if ( static::$isLegacy == FALSE )
		{
			$imageWhere = array( "contenttypeid=?", static::$imageContentType );
			$imageTable = 'attachment';
		}
		
		return array(
			'convertGalleryAlbums'=> array(
				'table'		=> 'album',
				'where'		=> NULL,
			),
			'convertGalleryImages'	=> array(
				'table'		=> $imageTable,
				'where'		=> $imageWhere
			),
			'convertGalleryComments'	=> array(
				'table'		=> 'picturecomment',
				'where'		=> NULL
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
		return array( 'core' => array( 'vbulletin' ) );
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

		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'gallery_albums', 'extension' => 'gallery_Albums' ), 2, array( 'app', 'link', 'extension' ) );
		
		/* Caches */
		\IPS\Task::queue( 'convert', 'RebuildTagCache', array( 'app' => $this->app->app_id, 'link' => 'gallery_images', 'class' => 'IPS\gallery\Image' ), 3, array( 'app', 'link', 'class' ) );

		return array( "f_gallery_images_rebuild", "f_gallery_cat_recount", "f_gallery_album_recount", "f_gallery_image_recount", "f_image_tags_recount" );
	}
	
	/**
	 * Fix Post Data
	 *
	 * @param	string	$post	Post
	 * @return	string	Fixed Posts
	 */
	public static function fixPostData( $post )
	{
		return \IPS\convert\Software\Core\Vbulletin::fixPostData( $post );
	}

	/**
	 * Get More Information
	 *
	 * @param	string	$method	Conversion method
	 * @return	array
	 */
	public function getMoreInfo( $method )
	{
		$return = array();
		switch( $method )
		{
			case 'convertGalleryImages':
				$return['convertGalleryImages'] = array(
					'file_location' => array(
						'field_class'			=> 'IPS\\Helpers\\Form\\Radio',
						'field_default'			=> 'database',
						'field_required'		=> TRUE,
						'field_extra'			=> array(
							'options'				=> array(
								'database'				=> \IPS\Member::loggedIn()->language()->addToStack( 'conv_store_database' ),
								'file_system'			=> \IPS\Member::loggedIn()->language()->addToStack( 'conv_store_file_system' ),
							),
							'userSuppliedInput'	=> 'file_system',
						),
						'field_hint'			=> NULL,
					)
				);
				break;
		}
		
		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : array();
	}
	
	
	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array( 'convertGalleryImages' );
	}

	/**
	 * Convert gallery albums
	 *
	 * @return	void
	 */
	public function convertGalleryAlbums()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'albumid' );
		
		foreach( $this->fetch( 'album', 'albumid' ) AS $album )
		{
			$socialgroup = NULL;
			if ( $album['state'] == 'private' )
			{
				/* Fetch users friends */
				$friends = array();
				foreach( $this->db->select( 'relationid', 'userlist', array( "userid=?", $album['userid'] ) ) AS $friend )
				{
					$friends[$friend] = $friend;
				}
				
				$socialgroup = array( 'members' => $friends );
			}
			
			$info = array(
				'album_id'					=> $album['albumid'],
				'album_owner_id'			=> $album['userid'],
				'album_name'				=> $album['title'],
				'album_description'			=> $album['description'],
				'album_type'				=> ( $album['state'] == 'private' ) ? 3 : 1,
				'album_count_imgs'			=> $album['visible'],
				'album_count_imgs_hidden'	=> $album['moderation'],
				'album_last_img_date'		=> $album['lastpicturedate']
			);
			
			$libraryClass->convertGalleryAlbum( $info, $socialgroup );
			
			$libraryClass->setLastKeyValue( $album['albumid'] );
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
		
		/* Don't even bother trying to swap things out - just do different things based on version */
		if ( static::$isLegacy === TRUE )
		{
			$libraryClass::setKey( 'pictureid' );
			
			foreach( $this->fetch( 'picture', 'pictureid' ) AS $image )
			{
				$filedata = NULL;
				$filepath = NULL;
				
				if ( $this->app->_session['more_info']['convertGalleryImages']['file_location'] == 'database' )
				{
					/* Simples! */
					$filedata = $image['filedata'];
				}
				else
				{
					$filepath = floor( $image['pictureid'] / 1000 );
					$filepath = rtrim( $this->app->_session['more_info']['convertGalleryImages']['file_location'], '/' ) . '/' . $filepath . '/' . $image['pictureid'] . '.picture';
				}
				
				try
				{
					$albumAndDate = $this->db->select( 'albumid, dateline', 'albumpicture', array( "pictureid=?", $image['pictureid'] ) )->first();
				}
				catch( \UnderflowException $e )
				{
					/* Orphaned */
					$libraryClass->setLastKeyValue( $image['pictureid'] );
					continue;
				}
				
				$info = array(
					'image_id'			=> $image['pictureid'],
					'image_album_id'	=> $albumAndDate['albumid'],
					'image_member_id'	=> $image['userid'],
					'image_caption'		=> $image['caption'],
					'image_file_name'	=> $image['caption'] . '.' . $image['extension'],
					'image_date'		=> $albumAndDate['dateline'],
				);
				
				$libraryClass->convertGalleryImage( $info, $filepath, $filedata );
				
				$libraryClass->setLastKeyValue( $image['pictureid'] );
			}
		}
		else
		{
			$libraryClass::setKey( 'attachmentid' );
			
			foreach( $this->fetch( 'attachment', 'attachmentid', array( "contenttypeid=?", static::$imageContentType ) ) AS $image )
			{
				try
				{
					$data = $this->db->select( '*', 'filedata', array( "filedataid=?", $image['filedataid'] ) )->first();
				}
				catch( \UnderflowException $e )
				{
					$libraryClass->setLastKeyValue( $image['attachmentid'] );
					continue;
				}
				
				$filedata = NULL;
				$filepath = NULL;
				
				if ( $this->app->_session['more_info']['convertGalleryImages']['file_location'] == 'database' )
				{
					$filedata = $data['filedata'];
				}
				else
				{
					$filepath = implode( '/', preg_split( '//', $data['userid'], -1, PREG_SPLIT_NO_EMPTY ) );
					$filepath = rtrim( $this->app->_session['more_info']['convertGalleryImages']['file_location'], '/' ) . '/' . $filepath . '/' . $data['filedataid'] . '.attach';
				}
				
				$info = array(
					'image_id'			=> $image['attachmentid'],
					'image_album_id'	=> $image['contentid'],
					'image_member_id'	=> $image['userid'],
					'image_caption'		=> $image['caption'],
					'image_file_name'	=> $image['filename'],
					'image_date'		=> $image['dateline']
				);
				
				$libraryClass->convertGalleryImage( $info, $filepath, $filedata );
				
				$libraryClass->setLastKeyValue( $image['attachmentid'] );
			}
		}
	}

	/**
	 * Convert gallery comments
	 *
	 * @return	void
	 */
	public function convertGalleryComments()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'commentid' );
		
		foreach( $this->fetch( 'picturecomment', 'commentid' ) AS $comment )
		{
			switch( $comment['state'] )
			{
				case 'visible':
					$approved = 1;
					break;
				
				case 'moderation':
					$approved = 0;
					break;
				
				case 'deleted':
					$approved = -1;
					break;
			}
			
			/* For 3.x this is just the value stored for pictureid */
			$image_id = $comment['pictureid'];

			/* For 4.x though, we have to get the attachmentid based on the filedataid */
			if ( static::$isLegacy === FALSE )
			{
				/* If VB tracked the attachmentid, just use it so we don't have to query */
				if( $comment['sourceattachmentid'] )
				{
					$image_id = $comment['sourceattachmentid'];
				}
				else
				{
					try
					{
						$image_id = $this->db->select( 'attachmentid', 'attachment', array( array( 'filedataid=?', $comment['filedataid'] ) ) )->first();
					}
					catch( \Exception $e )
					{
						$image_id = 0;
					}
				}
			}
			
			$libraryClass->convertGalleryComment( array(
				'comment_id'			=> $comment['commentid'],
				'comment_text'			=> $comment['pagetext'],
				'comment_img_id'		=> $image_id,
				'comment_author_id'		=> $comment['postuserid'],
				'comment_author_name'	=> $comment['postusername'],
				'comment_post_date'		=> $comment['dateline'],
				'comment_approved'		=> $approved,
			) );
			
			$libraryClass->setLastKeyValue( $comment['commentid'] );
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

		try
		{
			if( isset( \IPS\Request::i()->albumid ) AND mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'picture.php' ) === FALSE )
			{
				$data = $this->app->getLink( \IPS\Request::i()->albumid, 'gallery_albums' );
				$item = \IPS\gallery\Album::load( $data );

				if( $item->can( 'view' ) )
				{
					return $item->url();
				}
			}
			elseif( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'album.php' ) !== FALSE AND isset( \IPS\Request::i()->pictureid ) )
			{
				$data = $this->app->getLink( \IPS\Request::i()->pictureid, 'gallery_images' );
				$item = \IPS\gallery\Image::load( $data );

				if( $item->canView() )
				{
					return $item->url();
				}
			}
			elseif( isset( \IPS\Request::i()->userid ) )
			{
				$data = $this->app->getLink( \IPS\Request::i()->userid, array( 'members', 'core_members' ) );
				return \IPS\Member::load( $data )->url();
			}
			elseif( ( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'picture.php' ) !== FALSE AND isset( \IPS\Request::i()->pictureid ) ) OR
					( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'attachment.php' ) !== FALSE AND isset( \IPS\Request::i()->attachmentid ) ) )
			{
				try
				{
					$data = $this->app->getLink( \IPS\Request::i()->pictureid ?: \IPS\Request::i()->attachmentid, 'gallery_images' );
				}
				catch( \OutOfRangeException $e )
				{
					$data = \IPS\Request::i()->pictureid ?: \IPS\Request::i()->attachmentid;
				}

				return \IPS\File::get( 'gallery_Images', \IPS\gallery\Image::load( $data )->masked_file_name )->url;
			}
		}
		catch( \Exception $e )
		{
			return NULL;
		}

		return NULL;
	}
}