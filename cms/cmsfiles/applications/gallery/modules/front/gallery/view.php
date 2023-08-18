<?php
/**
 * @brief		View image
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Mar 2014
 */

namespace IPS\gallery\modules\front\gallery;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * View image or movie
 */
class _view extends \IPS\Content\Controller
{
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\gallery\Image';

	/**
	 * Init
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( \IPS\Request::i()->do != 'embed' )
		{
			try
			{
				$this->image = \IPS\gallery\Image::load( \IPS\Request::i()->id );
				
				$this->image->container()->clubCheckRules();
				
				if ( !$this->image->canView( \IPS\Member::loggedIn() ) )
				{
					\IPS\Output::i()->error( $this->image->container()->errorMessage(), '2G188/1', 403, '' );
				}				
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2G188/2', 404, '' );
			}
		}

		/* When preloading we don't want to update stuff */
		if( isset( \IPS\Request::i()->preload ) AND \IPS\Request::i()->preload )
		{
			$this->updateViewsAndMarkersOnAjax = FALSE;
		}

		\IPS\gallery\Application::outputCss();

		parent::execute();
	}
	
	/**
	 * View Image
	 *
	 * @return	void
	 * @link	http://www.videojs.com/projects/mimes.html
	 * @note	Only HTML5 and some flash-based video formats will work. MP4, webm and ogg are relatively safe bets but anything else isn't likely to play correctly.
	 *	The above link will allow you to check what is supported in the browser you are using.
	 * @note	As of RC1 we fall back to a generic 'embed' for non-standard formats for better upgrade compatibility...need to look into transcoding in the future
	 */
	protected function manage()
	{
		/* Init */
		parent::manage();

		/* Check restrictions */
		if( \IPS\Settings::i()->gallery_detailed_bandwidth AND ( \IPS\Member::loggedIn()->group['g_max_transfer'] OR \IPS\Member::loggedIn()->group['g_max_views'] ) )
		{
			$lastDay		= \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) )->getTimestamp();

			if( \IPS\Member::loggedIn()->group['g_max_views'] )
			{
				if( \IPS\Db::i()->select( 'COUNT(*) as total', 'gallery_bandwidth', array( 'member_id=? AND bdate > ?', (int) \IPS\Member::loggedIn()->member_id, $lastDay ) )->first() >= \IPS\Member::loggedIn()->group['g_max_views'] )
				{
					\IPS\Output::i()->error( 'maximum_daily_views', '1G188/7', 403, 'maximum_daily_views_admin' );
				}
			}

			if( \IPS\Member::loggedIn()->group['g_max_transfer'] )
			{
				if( \IPS\Db::i()->select( 'SUM(bsize) as total', 'gallery_bandwidth', array( 'member_id=? AND bdate > ?', (int) \IPS\Member::loggedIn()->member_id, $lastDay ) )->first() >= ( \IPS\Member::loggedIn()->group['g_max_transfer'] * 1024 ) )
				{
					\IPS\Output::i()->error( 'maximum_daily_transfer', '1G188/8', 403, 'maximum_daily_transfer_admin' );
				}
			}
		}

		/* Set some meta tags */
		if( $this->image->media )
		{
			\IPS\Output::i()->metaTags['og:video']		= \IPS\File::get( 'gallery_Images', $this->image->original_file_name )->url;
			\IPS\Output::i()->metaTags['og:video:type']	= $this->image->file_type;
			\IPS\Output::i()->metaTags['og:type']		= 'video';

			if( \count( $this->image->tags() ) )
			{
				\IPS\Output::i()->metaTags['og:video:tag']	= $this->image->tags();
			}

			if( $this->image->masked_file_name )
			{
				\IPS\Output::i()->metaTags['og:image']		= \IPS\File::get( 'gallery_Images', $this->image->masked_file_name )->url;
			}
		}
		else
		{
			\IPS\Output::i()->metaTags['og:image']		= \IPS\File::get( 'gallery_Images', $this->image->masked_file_name )->url;
			\IPS\Output::i()->metaTags['og:image:type']	= $this->image->file_type;

			if( \count( $this->image->tags() ) )
			{
				\IPS\Output::i()->metaTags['og:object:tag']	= $this->image->tags();
			}
		}

		/* Prioritize the main image */
		\IPS\Output::i()->linkTags[]	= array(
			'rel'	=> 'preload',
			'href'	=> (string) \IPS\File::get( 'gallery_Images', $this->image->masked_file_name )->url,
			'as'	=> $this->image->media ? 'video' : 'image',
			'type'	=> $this->image->file_type
		);

		/* Sort out comments and reviews */
		$tabs = $this->image->commentReviewTabs();
		$_tabs = array_keys( $tabs );
		$tab = isset( \IPS\Request::i()->tab ) ? \IPS\Request::i()->tab : array_shift( $_tabs );
		$activeTabContents = $this->image->commentReviews( $tab, \IPS\Request::i()->lightbox );

		if ( \count( $tabs ) > 1 )
		{
			$commentsAndReviews = \count( $tabs ) ? \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $tab, $activeTabContents, \IPS\Request::i()->lightbox ? $this->image->url()->setQueryString( 'lightbox', 1 ) : $this->image->url(), 'tab', FALSE, TRUE, \IPS\Request::i()->lightbox ? 'ipsTabs_small ipsTabs_stretch' : '' ) : NULL;
		}
		else
		{
			$commentsAndReviews = $activeTabContents;
		}

		/* Set the session location */
		\IPS\Session::i()->setLocation( $this->image->url(), $this->image->onlineListPermissions(), 'loc_gallery_viewing_image', array( $this->image->caption => FALSE ) );

		/* Store bandwidth log */
		if( \IPS\Settings::i()->gallery_detailed_bandwidth )
		{
			/* Media items should get the file size of the original file instead of a thumbnail */
			if( $this->image->media )
			{
				$displayedImage = \IPS\File::get( 'gallery_Images', $this->image->original_file_name, $this->image->file_size );
			}
			/* Otherwise, fetch the thumbnails */
			else
			{
				$displayedImage	= \IPS\File::get( 'gallery_Images', $this->image->masked_file_name );
			}

			/* Get filesize, but don't error out if there is a problem fetching it at this point */
			try
			{
				$filesize = ( ( isset( $displayedImage ) AND $displayedImage->filesize() ) ? $displayedImage->filesize() : $this->image->file_size );
			}
			catch( \Exception $e )
			{
				$filesize = $this->image->file_size;
			}

			\IPS\Db::i()->insert( 'gallery_bandwidth', array(
				'member_id'		=> (int) \IPS\Member::loggedIn()->member_id,
				'bdate'			=> time(),
				'bsize'			=> (int) $filesize,
				'image_id'		=> $this->image->id
			)	);
		}

		/* Add JSON-ld */
		\IPS\Output::i()->jsonLd['gallery']	= array(
			'@context'		=> "http://schema.org",
			'@type'			=> "MediaObject",
			'@id'			=> (string) $this->image->url(),
			'url'			=> (string) $this->image->url(),
			'name'			=> $this->image->mapped('title'),
			'description'	=> $this->image->truncated( TRUE, NULL ),
			'dateCreated'	=> \IPS\DateTime::ts( $this->image->date )->format( \IPS\DateTime::ISO8601 ),
			'fileFormat'	=> $this->image->file_type,
			'keywords'		=> $this->image->tags(),
			'author'		=> array(
				'@type'		=> 'Person',
				'name'		=> \IPS\Member::load( $this->image->member_id )->name,
				'image'		=> \IPS\Member::load( $this->image->member_id )->get_photo( TRUE, TRUE )
			),
			'interactionStatistic'	=> array(
				array(
					'@type'					=> 'InteractionCounter',
					'interactionType'		=> "http://schema.org/ViewAction",
					'userInteractionCount'	=> $this->image->views
				)
			)
		);

		/* Do we have a real author? */
		if( $this->image->member_id )
		{
			\IPS\Output::i()->jsonLd['gallery']['author']['url']	= (string) \IPS\Member::load( $this->image->member_id )->url();
		}

		if ( $this->image->container()->allow_comments AND $this->image->directContainer()->allow_comments )
		{
			\IPS\Output::i()->jsonLd['gallery']['interactionStatistic'][] = array(
				'@type'					=> 'InteractionCounter',
				'interactionType'		=> "http://schema.org/CommentAction",
				'userInteractionCount'	=> $this->image->mapped('num_comments')
			);

			\IPS\Output::i()->jsonLd['gallery']['commentCount'] = $this->image->mapped('num_comments');
		}

		if ( $this->image->container()->allow_reviews AND $this->image->directContainer()->allow_reviews )
		{
			\IPS\Output::i()->jsonLd['gallery']['interactionStatistic'][] = array(
				'@type'					=> 'InteractionCounter',
				'interactionType'		=> "http://schema.org/ReviewAction",
				'userInteractionCount'	=> $this->image->mapped('num_reviews')
			);

			if ( $this->image->averageReviewRating() )
			{
				\IPS\Output::i()->jsonLd['gallery']['aggregateRating'] = array(
					'@type'			=> 'AggregateRating',
					'ratingValue'	=> $this->image->averageReviewRating(),
					'reviewCount'	=> $this->image->reviews,
					'bestRating'	=> \IPS\Settings::i()->reviews_rating_out_of
				);
			}
		}

		if( $this->image->media )
		{
			if( $this->image->masked_file_name )
			{
				\IPS\Output::i()->jsonLd['gallery']['thumbnail']	= (string) \IPS\File::get( 'gallery_Images', $this->image->masked_file_name )->url;
				\IPS\Output::i()->jsonLd['gallery']['thumbnailUrl']	= (string) \IPS\File::get( 'gallery_Images', $this->image->masked_file_name )->url;
			}

			\IPS\Output::i()->jsonLd['gallery']['contentSize'] = (string) \IPS\File::get( 'gallery_Images', $this->image->original_file_name )->filesize();
		}
		else
		{
			try
			{
				$largeFile	= \IPS\File::get( 'gallery_Images', $this->image->masked_file_name );
				$dimensions	= $this->image->_dimensions;

				\IPS\Output::i()->jsonLd['gallery']['artMedium']	= 'Digital';
				\IPS\Output::i()->jsonLd['gallery']['width'] 		= $dimensions['large'][0];
				\IPS\Output::i()->jsonLd['gallery']['height'] 		= $dimensions['large'][1];
				\IPS\Output::i()->jsonLd['gallery']['image']		= array(
					'@type'		=> 'ImageObject',
					'url'		=> (string) $largeFile->url,
					'caption'	=> $this->image->mapped('title'),
					'thumbnail'	=> (string) \IPS\File::get( 'gallery_Images', $this->image->small_file_name )->url,
					'width'		=> $dimensions['large'][0],
					'height'	=> $dimensions['large'][1],
				);

				if( \is_countable( $this->image->metadata ) AND \count( $this->image->metadata ) )
				{
					\IPS\Output::i()->jsonLd['gallery']['image']['exifData'] = array();

					foreach( $this->image->metadata as $k => $v )
					{
						\IPS\Output::i()->jsonLd['gallery']['image']['exifData'][] = array(
							'@type'		=> 'PropertyValue',
							'name'		=> $k, 
							'value'		=> $v
						);
					}
				}
				\IPS\Output::i()->jsonLd['gallery']['thumbnailUrl']	= (string) \IPS\File::get( 'gallery_Images', $this->image->small_file_name )->url;
			}
			/* File doesn't exist */
			catch ( \RuntimeException $e ){}
		}

		/* Display */
		if( \IPS\Request::i()->isAjax() && isset( \IPS\Request::i()->browse ) )
		{
			$return = array(
				'title' => htmlspecialchars( $this->image->mapped('title'), ENT_DISALLOWED | ENT_QUOTES, 'UTF-8', FALSE ),
				'image' => \IPS\Theme::i()->getTemplate( 'view' )->imageFrame( $this->image ),
				'info' => \IPS\Theme::i()->getTemplate( 'view' )->imageInfo( $this->image )
			);

			if( $this->image->directContainer()->allow_comments )
			{
				$return['comments'] = $commentsAndReviews;
			}

			/* Data Layer Properties */
			if ( \IPS\Settings::i()->core_datalayer_enabled )
			{
				$return['dataLayer'] = $this->image->getDataLayerProperties();
			}

			\IPS\Output::i()->json( $return );
		}
		/* Switching comments only */
		elseif( \IPS\Request::i()->isAjax() AND !isset( \IPS\Request::i()->rating_submitted ) AND \IPS\Request::i()->tab )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}
		else
		{
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_view.js', 'gallery' ) );
			\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_browse.js', 'gallery' ) );
			\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_global.js', 'gallery' ) );

			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'view' )->image( $this->image, $commentsAndReviews );
		}
	}

	/**
	 * Download the full size image
	 *
	 * @return	void
	 */
	protected function download()
	{
		if( $this->image->canDownloadOriginal() == \IPS\gallery\Image::DOWNLOAD_ORIGINAL_NONE )
		{
			\IPS\Output::i()->error( 'cannot_download_original_image', '2G188/E', 403, '' );
		}

		try
		{
			/* Get file and data */
			$image = NULL;

			try
			{
				switch( $this->image->canDownloadOriginal() )
				{
					case \IPS\gallery\Image::DOWNLOAD_ORIGINAL_WATERMARKED:
						/* We need to watermark the original image on the fly in this case */
						$file		= \IPS\File::get( 'gallery_Images', $this->image->original_file_name );

						if( $file->isImage() )
						{
							$image		= $this->image->createImageFile( $file, NULL );
						}
					break;

					case \IPS\gallery\Image::DOWNLOAD_ORIGINAL_RAW:
						$file		= \IPS\File::get( 'gallery_Images', $this->image->original_file_name );
					break;
				}

				if( $file->filesize() === false )
				{
					throw new \RuntimeException( 'DOES_NOT_EXIST' );
				}
			}
			catch( \RuntimeException $e )
			{
				\IPS\Log::log( "Original image for {$this->image->id} is missing, falling back to masked image", 'gallery_image_missing' );
				$file = \IPS\File::get( 'gallery_Images', $this->image->masked_file_name );
			}

			$headers	= array_merge( \IPS\Output::getCacheHeaders( time(), 360 ), array( "Content-Disposition" => \IPS\Output::getContentDisposition( 'attachment', $file->originalFilename ), "X-Content-Type-Options" => "nosniff" ) );

			/* Send headers and print file */
			\IPS\Output::i()->sendStatusCodeHeader( 200 );
			\IPS\Output::i()->sendHeader( "Content-type: " . \IPS\File::getMimeType( $file->originalFilename ) . ";charset=UTF-8" );

			foreach( $headers as $key => $header )
			{
				\IPS\Output::i()->sendHeader( $key . ': ' . $header );
			}
			
			\IPS\Output::i()->sendHeader( "Content-Security-Policy: default-src 'none'; sandbox" );
			\IPS\Output::i()->sendHeader( "X-Content-Security-Policy:  default-src 'none'; sandbox" );

			if( $image !== NULL )
			{
				\IPS\Output::i()->sendHeader( "Content-Length: " . \strlen( (string) $image ) );
				print (string) $image;
			}
			else
			{
				\IPS\Output::i()->sendHeader( "Content-Length: " . $file->filesize() );
				$file->printFile();
			}
			exit;
		}
		catch ( \UnderflowException $e )
		{
			\IPS\Output::i()->sendOutput( '', 404 );
		}
	}

	/**
	 * Mark the image as read
	 *
	 * @note	We preload the next/prev image in the lightbox and do not want those images marked as read when doing so. While this speeds up the loading to the end user, we still need to separately mark the image as read when it's pulled into view.
	 * @return	void
	 */
	public function markread()
	{
		/* Run CSRF check */
		\IPS\Session::i()->csrfCheck();

		/* Mark image as read */
		$this->image->markRead();

		/* We also want to update the views */
		$countUpdated = false;
		if ( \IPS\REDIS_ENABLED and \IPS\CACHE_METHOD == 'Redis' and ( \IPS\CACHE_CONFIG or \IPS\REDIS_CONFIG ) )
		{
			try
			{
				\IPS\Redis::i()->zIncrBy( 'topic_views', 1, static::$contentModel .'__' . $this->image->id );
				$countUpdated = true;
			}
			catch( \Exception $e ) {}
		}
		
		if ( ! $countUpdated )
		{
			\IPS\Db::i()->insert( 'core_view_updates', array(
					'classname'	=> static::$contentModel,
					'id'		=> $this->image->id
			) );
		}

		/* And return an AJAX response */
		\IPS\Output::i()->json('OK');
	}

	/**
	 * View all of the metadata for this image
	 *
	 * @return	void
	 */
	protected function metadata()
	{
		/* Set navigation and title */
		$this->_setBreadcrumbAndTitle( $this->image );

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'gallery_metadata', FALSE, array( 'sprintf' => $this->image->caption ) );
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'view' )->metadata( $this->image );
	}

	/**
	 * Set this image as a cover photo
	 *
	 * @return	void
	 */
	protected function cover()
	{
		switch( \IPS\Request::i()->set )
		{
			case 'album':
				$check = $this->image->canSetAsAlbumCover();
			break;

			case 'category':
				$check = $this->image->canSetAsCategoryCover();
			break;

			case 'both':
				$check = ( $this->image->canSetAsAlbumCover() AND $this->image->canSetAsCategoryCover() );
			break;
		}

		if ( !$check )
		{
			\IPS\Output::i()->error( 'node_error', '2G188/5', 403, '' );
		}

		\IPS\Session::i()->csrfCheck();
		$lang = '';

		if( $this->image->canSetAsAlbumCover() && ( \IPS\Request::i()->set == 'album' or \IPS\Request::i()->set == 'both' ) )
		{
			$this->image->directContainer()->cover_img_id	= $this->image->id;
			$this->image->directContainer()->save();

			$lang = \IPS\Member::loggedIn()->language()->addToStack('set_as_album_done');
		}

		if( $this->image->canSetAsCategoryCover() && ( \IPS\Request::i()->set == 'category' or \IPS\Request::i()->set == 'both' ) )
		{
			$this->image->container()->cover_img_id	= $this->image->id;
			$this->image->container()->save();

			if( $lang )
			{
				$lang = \IPS\Member::loggedIn()->language()->addToStack('set_as_both_done');
			}
			else
			{
				$lang = \IPS\Member::loggedIn()->language()->addToStack('set_as_category_done');
			} 
		}

		/* Redirect back to image */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'message' => $lang ) );
		}
		else
		{
			\IPS\Output::i()->redirect( $this->image->url() );	
		}		
	}

	/**
	 * Rotate image
	 *
	 * @return	void
	 */
	protected function rotate()
	{
		/* Check permission */
		if( !$this->image->canEdit() )
		{
			\IPS\Output::i()->error( 'node_error', '2G188/3', 403, '' );
		}

		\IPS\Session::i()->csrfCheck();

		/* Determine angle to rotate */
		if( \IPS\Request::i()->direction == 'right' )
		{
			$angle = ( \IPS\Settings::i()->image_suite == 'imagemagick' and class_exists( 'Imagick', FALSE ) ) ? 90 : -90;
		}
		else
		{
			$angle = ( \IPS\Settings::i()->image_suite == 'imagemagick' and class_exists( 'Imagick', FALSE ) ) ? -90 : 90;
		}

		/* Rotate the image and rebuild thumbnails */
		$file	= \IPS\File::get( 'gallery_Images', $this->image->original_file_name );
		$image	= \IPS\Image::create( $file->contents() );
		$image->rotate( $angle );
		$file->replace( (string) $image );
		$this->image->buildThumbnails( $file );
		$this->image->original_file_name = (string) $file;
		$this->image->save();

		/* Respond or redirect */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 
				'src'		=> (string) \IPS\File::get( 'gallery_Images', $this->image->masked_file_name )->url, 
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack('gallery_image_rotated'),
				'width'		=> $this->image->_dimensions['large'][0],
				'height'	=> $this->image->_dimensions['large'][1],
			) );
		}
		else
		{
			\IPS\Output::i()->redirect( $this->image->url() );
		}
	}

	/**
	 * Change Author
	 *
	 * @return	void
	 */
	public function changeAuthor()
	{
		/* Permission check */
		if ( !$this->image->canChangeAuthor() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2G188/6', 403, '' );
		}
		
		/* Build form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Member( 'author', NULL, TRUE ) );
		$form->class .= 'ipsForm_vertical';

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$this->image->changeAuthor( $values['author'] );
			$this->image->save();
			
			\IPS\Output::i()->redirect( $this->image->url() );
		}
		
		/* Display form */
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}

	/**
	 * Set this image as a profile image
	 *
	 * @return	void
	 */
	public function setAsPhoto()
	{
		/* Permission check */
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2G188/9', 403, '' );
		}

		/* Only images... */
		if ( $this->image->media )
		{
			\IPS\Output::i()->error( 'no_photo_for_media', '2G188/A', 403, '' );
		}
		
		\IPS\Session::i()->csrfCheck();
		
		/* Update profile photo */
		$file	= \IPS\File::get( 'gallery_Images', $this->image->masked_file_name );
		$image	= \IPS\Image::create( $file->contents() );
		$photo	= \IPS\File::create( 'core_Profile', $file->filename, (string) $image );

		\IPS\Member::loggedIn()->pp_main_photo = (string) $photo;
		\IPS\Member::loggedIn()->pp_thumb_photo = (string) $photo->thumbnail( 'core_Profile', \IPS\PHOTO_THUMBNAIL_SIZE, \IPS\PHOTO_THUMBNAIL_SIZE );
		\IPS\Member::loggedIn()->pp_photo_type = "custom";
		\IPS\Member::loggedIn()->photo_last_update = time();
		\IPS\Member::loggedIn()->save();
		\IPS\Member::loggedIn()->logHistory( 'core', 'photo', array( 'action' => 'new', 'type' => 'gallery', 'id' => $this->image->id ) );

		/* Redirect back to image */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'message' => \IPS\Member::loggedIn()->language()->addToStack('set_as_profile_photo') ) );
		}
		else
		{
			\IPS\Output::i()->redirect( $this->image->url() );	
		}
	}

	/**
	 * Get the next image
	 *
	 * @param bool $return (bool)		Return image object or redirect?
	 * @return \IPS\gallery\Image|void
	 */
	protected function next( bool $return=FALSE )
	{
		$image = $this->image->nextItem() ? $this->image->nextItem() : $this->image->fetchFirstOrLast('first');

		if ( $return )
		{
			return $image;
		}

		$this->redirectToUrl( $image );
	}

	/**
	 * Get the previous image
	 *
	 * @param bool $return (bool)		Return image object or redirect?
	 * @return \IPS\gallery\Image|void
	 */
	protected function previous( bool $return=FALSE )
	{
		$image = $this->image->prevItem() ? $this->image->prevItem() : $this->image->fetchFirstOrLast('last');

		if ( $return )
		{
			return $image;
		}

		$this->redirectToUrl( $image );
	}

	/**
	 * Move
	 *
	 * @return	void
	 * @note	Overridden so we can show an album selector as well
	 */
	protected function move()
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			if ( !$item->canMove() )
			{
				throw new \DomainException;
			}
			
			$form = \IPS\gallery\Image\Table::buildMoveForm( $item->container(), 'IPS\\gallery\\Image', array( 'where' => array( "album_owner_id=? OR " . \IPS\Db::i()->in( 'album_submit_type', array( \IPS\gallery\Album::AUTH_SUBMIT_PUBLIC, \IPS\gallery\Album::AUTH_SUBMIT_GROUPS, \IPS\gallery\Album::AUTH_SUBMIT_MEMBERS, \IPS\gallery\Album::AUTH_SUBMIT_CLUB ) ), $item->author()->member_id ) ), $item->author() );

			if ( $values = $form->values() )
			{
				if ( isset( $values['move_to'] ) )
				{
					if ( $values['move_to'] == 'new_album' )
					{
						$albumValues = $values;
						unset( $albumValues['move_to'] );
						unset( $albumValues['move_to_category'] );
						unset( $albumValues['move_to_album'] );
						
						$target = new \IPS\gallery\Album;
						$target->saveForm( $target->formatFormValues( $albumValues ) );
						$target->save();
					}
					else
					{						
						$target = ( \IPS\Request::i()->move_to == 'category' ) ? $values['move_to_category'] : $values['move_to_album'];
					}
				}
				else
				{
					$target = isset( $values['move_to_category'] ) ? $values['move_to_category'] : $values['move_to_album'];
				}

				$item->move( $target, FALSE );
				\IPS\Output::i()->redirect( $item->url() );
			}
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2G188/B', 403, '' );
		}
	}

	/**
	 * Set the breadcrumb and title
	 *
	 * @param	\IPS\Content\Item	$item	Content item
	 * @param	bool				$link	Link the content item element in the breadcrumb
	 * @return	void
	 */
	protected function _setBreadcrumbAndTitle( $item, $link=TRUE )
	{
		$container	= NULL;
		try
		{
			$container = $this->image->container();
			
			if ( $club = $container->club() )
			{
				\IPS\core\FrontNavigation::$clubTabActive = TRUE;
				\IPS\Output::i()->breadcrumb = array();
				\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs') );
				\IPS\Output::i()->breadcrumb[] = array( $club->url(), $club->name );
				
				if ( \IPS\Settings::i()->clubs_header == 'sidebar' )
				{
					\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->header( $club, $container, 'sidebar' );
				}
			}
			else
			{
				foreach ( $container->parents() as $parent )
				{
					\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
				}
			}
			\IPS\Output::i()->breadcrumb[] = array( $container->url(), $container->_title );
		}
		catch ( \Exception $e ) { }

		/* Add album */
		if( $this->image->album_id )
		{
			\IPS\Output::i()->breadcrumb[] = array( $this->image->directContainer()->url(), $this->image->directContainer()->_title );
		}

		\IPS\Output::i()->breadcrumb[] = array( $link ? $this->image->url() : NULL, $this->image->mapped('title') );
		
		$title = ( isset( \IPS\Request::i()->page ) and \IPS\Request::i()->page > 1 ) ? \IPS\Member::loggedIn()->language()->addToStack( 'title_with_page_number', FALSE, array( 'sprintf' => array( $this->image->mapped('title'), \intval( \IPS\Request::i()->page ) ) ) ) : $this->image->mapped('title');
		\IPS\Output::i()->title = $container ? ( $title . ' - ' . $container->_title ) : $title;
	}

	/**
	 * Redirect to the URL or album/category on error
	 *
	 * @param $image
	 * @return void
	 */
	protected function redirectToUrl( $image ): void
	{
		if ( $image )
		{
			$url = $image->url();

			if ( \IPS\Request::i()->url()->queryString )
			{
				foreach ( \IPS\Request::i()->url()->queryString as $k => $v )
				{
					if ( !in_array( $k, array( 'id', 'do' ) ) )
					{
						$url = $url->setQueryString( $k, $v );
					}
				}
			}

			\IPS\Output::i()->redirect( $url );
		}
		else
		{
			/* Go to the album or category */
			\IPS\Output::i()->redirect( $this->image->directContainer()->url() );
		}
	}

	/**
	 * Toggle not safe for work
	 *
	 * @return	void
	 */
	public function toggleNSFW()
	{
		/* Permission check */
		if ( !\IPS\Settings::i()->gallery_nsfw or !$this->image->canEdit() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '1G188/F', 403, '' );
		}

		\IPS\Session::i()->csrfCheck();

		/* Update profile photo */
		$this->image->nsfw = !$this->image->nsfw;
		$this->image->save();

		/* Redirect back to image */
		\IPS\Output::i()->redirect( $this->image->url(), $this->image->nsfw ? 'set_gallery_image_nsfw_off' : 'set_gallery_image_nsfw_on' );
	}
}