<?php
/**
 * @brief		Downloads Application Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		27 Sep 2013
 * @version		
 */
 
namespace IPS\downloads;

/**
 * Downloads Application Class
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
		if ( \IPS\Request::i()->module == 'downloads' and \IPS\Request::i()->controller == 'browse' and \IPS\Request::i()->do == 'rss' )
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

			$this->sendDownloadsRss( $member ?? new \IPS\Member );

			if( !\IPS\Member::loggedIn()->group['g_view_board'] )
			{
				\IPS\Output::i()->error( 'node_error', '2D220/1', 404, '' );
			}
		}

		static::outputCss();
	}

	/**
	 * Output CSS files
	 *
	 * @return void
	 */
	public static function outputCss()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'downloads.css', 'downloads', 'front' ) );

		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'downloads_responsive.css', 'downloads', 'front' ) );
		}
	}

	/**
	 * Send the latest file RSS feed for the indicated member
	 *
	 * @param	\IPS\Member		$member		Member
	 * @return	void
	 */
	protected function sendDownloadsRss( $member )
	{
		if( !\IPS\Settings::i()->idm_rss )
		{
			\IPS\Output::i()->error( 'rss_offline', '2D175/2', 403, 'rss_offline_admin' );
		}

		$document = \IPS\Xml\Rss::newDocument( \IPS\Http\Url::internal( 'app=downloads&module=downloads&controller=browse', 'front', 'downloads' ), $member->language()->get('idm_rss_title'), $member->language()->get('idm_rss_title') );
		
		foreach ( \IPS\downloads\File::getItemsWithPermission( array(), NULL, 10, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, $member ) as $file )
		{
			$document->addItem( $file->name, $file->url(), $file->desc, \IPS\DateTime::ts( $file->updated ), $file->id );
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
		return 'download';
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
			'browseTabs'	=> array( array( 'key' => 'Downloads' ) ),
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
		if ( isset( \IPS\Request::i()->showfile ) AND \is_numeric( \IPS\Request::i()->showfile ) )
		{
			try
			{
				$file = \IPS\downloads\File::loadAndCheckPerms( \IPS\Request::i()->showfile );
				
				\IPS\Output::i()->redirect( $file->url() );
			}
			catch( \OutOfRangeException $e ) {}
		}

		if ( isset( \IPS\Request::i()->module ) AND \IPS\Request::i()->module == 'post' AND isset( \IPS\Request::i()->controller ) AND \IPS\Request::i()->controller == 'submit' )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=downloads&module=downloads&controller=submit", "front", "downloads_submit" ) );
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
		return array( 'idm_watermarkpath' );
	}

	/**
	 * Returns a list of all existing webhooks and their payload in this app.
	 *
	 * @return array
	 */
	public function getWebhooks(): array
	{
		return array_merge( parent::getWebhooks(), [ 'downloads_new_version' => \IPS\downloads\File::class ] );
	}

}