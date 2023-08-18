<?php
/**
 * @brief		Gallery Application Class 
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Mar 2014
 * @version		
 */
 
namespace IPS\gallery;

/**
 * Gallery Application Class
 */
class _Application extends \IPS\Application
{
	/**
	 * Init
	 *
	 * @return	void
	 */
	public function init()
	{
		/* Handle RSS requests */
		if ( \IPS\Request::i()->module == 'gallery' and \IPS\Request::i()->controller == 'browse' and \IPS\Request::i()->do == 'rss' )
		{
			$member = NULL;
			if( \IPS\Request::i()->member AND \IPS\Request::i()->key )
			{
				$member = \IPS\Member::load( \IPS\Request::i()->member );
				if( !\IPS\Login::compareHashes( $member->getUniqueMemberHash(), (string) \IPS\Request::i()->key ) )
				{
					$member = NULL;
				}
			}

			$this->sendGalleryRss( $member ?? new \IPS\Member );

			if( !\IPS\Member::loggedIn()->group['g_view_board'] )
			{
				\IPS\Output::i()->error( 'node_error', '2G218/1', 404, '' );
			}
		}

		if( \IPS\Member::loggedIn()->members_bitoptions['remove_gallery_access'] )
		{
			\IPS\Output::i()->error( 'node_error', '2G218/1', 404, '' );
		}
	}

	/**
	 * Send the gallery RSS feed for the indicated member
	 *
	 * @param	\IPS\Member		$member		Member
	 * @return	void
	 * @note	We use a template so that we can embed image directly into feed while still allowing it to be customized
	 */
	protected function sendGalleryRss( $member )
	{
		if( !\IPS\Settings::i()->gallery_rss_enabled )
		{
			\IPS\Output::i()->error( 'gallery_rss_offline', '2G189/3', 403, 'gallery_rss_offline_admin' );
		}
		
		$where	= array();

		if( isset( \IPS\Request::i()->category ) )
		{
			try
			{
				$category	= \IPS\gallery\Category::load( \IPS\Request::i()->category );

				if( !$category->can( 'read', $member ) )
				{
					throw new \OutOfRangeException;
				}

				$where[]	= array( 'image_category_id=?', $category->id );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2G189/8', 404, '' );
			}
		}
		else if ( isset( \IPS\Request::i()->album ) )
		{
			try
			{
				$album		= \IPS\gallery\Album::load( \IPS\Request::i()->album );

				if( !$album->can( 'read', $member ) )
				{
					throw new \OutOfRangeException;
				}

				$where[]	= array( 'image_album_id=?', $album->id );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2G189/9', 404, '' );
			}
		}

		$document = \IPS\Xml\Rss::newDocument( isset( $category ) ? $category->url() : ( isset( $album ) ? $album->url() : \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=browse', 'front', 'gallery' ) ), $member->language()->get('gallery_rss_title'), $member->language()->get('gallery_rss_title') );

		foreach ( \IPS\gallery\Image::getItemsWithPermission( $where, NULL, 10, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, $member ) as $image )
		{
			$document->addItem( $image->caption, $image->url(), \IPS\Theme::i()->getTemplate( 'view' )->rssContent( $image ), \IPS\DateTime::ts( $image->updated ), $image->id );
		}
		
		/* @note application/rss+xml is not a registered IANA mime-type so we need to stick with text/xml for RSS */
		\IPS\Output::i()->sendOutput( $document->asXML(), 200, 'text/xml', array(), TRUE );
	}

	/**
	 * [Node] Get Icon for tree
	 *
	 * @note	Return the class for the icon (e.g. 'globe')
	 * @return	string|null
	 */
	protected function get__icon()
	{
		return 'camera';
	}
	
	/**
	 * Default front navigation
	 *
	 * @code
	 	
	 	// Each item...
	 	array(
			'key'		=> 'Example',		// The extension key
			'app'		=> 'core',			// [Optional] The extension application. If ommitted, uses this application	
			'config'	=> array(...),		// [Optional] The configuration for the menu item
			'title'		=> 'SomeLangKey',	// [Optional] If provided, the value of this language key will be copied to menu_item_X
			'children'	=> array(...),		// [Optional] Array of child menu items for this item. Each has the same format.
		)
	 	
	 	return array(
		 	'rootTabs' 		=> array(), // These go in the top row
		 	'browseTabs'	=> array(),	// These go under the Browse tab on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Browse tab may not exist)
		 	'browseTabsEnd'	=> array(),	// These go under the Browse tab after all other items on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Browse tab may not exist)
		 	'activityTabs'	=> array(),	// These go under the Activity tab on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Activity tab may not exist)
		)
	 * @endcode
	 * @return array
	 */
	public function defaultFrontNavigation()
	{
		return array(
			'rootTabs'		=> array(),
			'browseTabs'	=> array( array( 'key' => 'Gallery' ) ),
			'browseTabsEnd'	=> array(),
			'activityTabs'	=> array()
		);
	}

	/**
	 * Perform some legacy URL parameter conversions
	 *
	 * @return	void
	 */
	public function convertLegacyParameters()
	{
		/* convert ?module=images&section=img_ctrl&img=100&file=medium */
		/* convert ?module=images&section=img_ctrl&id=100&file=medium */
		if( isset( \IPS\Request::i()->section ) AND \IPS\Request::i()->section == 'img_ctrl' )
		{
			$id = ( isset( \IPS\Request::i()->img ) ) ? \IPS\Request::i()->img : \IPS\Request::i()->id;

			if( $id )
			{
				if( \IPS\Request::i()->file == 'med' )
				{
					\IPS\Request::i()->file = 'medium';
				}

				$imageSize = ( ( \IPS\Request::i()->file == 'small' ) ? 'small' : 'masked' ) . '_file_name';

				try
				{
					\IPS\Output::i()->redirect( (string) \IPS\File::get( 'gallery_Images', \IPS\gallery\Image::load( $id )->$imageSize )->url );
				}
				catch ( \Exception $e ){}
			}
		}

		/* convert ?app=gallery&module=images&section=viewimage&img=14586 */
		if( isset( \IPS\Request::i()->section ) AND \IPS\Request::i()->section == 'viewimage' )
		{
			$id = ( isset( \IPS\Request::i()->img ) ) ? \IPS\Request::i()->img : \IPS\Request::i()->id;

			if( $id )
			{
				if( \IPS\Request::i()->file == 'med' )
				{
					\IPS\Request::i()->file = 'medium';
				}

				$imageSize = ( ( \IPS\Request::i()->file == 'small' ) ? 'small' : 'masked' ) . '_file_name';

				try
				{
					\IPS\Output::i()->redirect( \IPS\gallery\Image::load( $id )->url() );
				}
				catch ( \Exception $e ){}
			}
		}
	}

	/**
	 * Output CSS files
	 *
	 * @return void
	 */
	public static function outputCss()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'gallery.css', 'gallery' ), \IPS\Theme::i()->css( 'global.css', 'gallery' ) );
		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'gallery_responsive.css', 'gallery', 'front' ) );
		}
	}
	
	/**
	 * Get any settings that are uploads
	 *
	 * @return	array
	 */
	public function uploadSettings()
	{
		/* Apps can overload this */
		return array( 'gallery_watermark_path' );
	}

	/**
	 * Returns a list of all existing webhooks and their payload in this app.
	 *
	 * @return array
	 */
	public function getWebhooks() : array
	{
		return array_merge( [
			'galleryAlbum_create' => \IPS\gallery\Album::class
		],parent::getWebhooks());
	}

}