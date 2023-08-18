<?php
/**
 * @brief		Blog Application Class
 * @author		<a href=''>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	Blog
 * @since		3 Mar 2014
 * @version		
 */
 
namespace IPS\blog;

/**
 * Blog Application Class
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
		if ( \IPS\Request::i()->module == 'blogs' and \IPS\Request::i()->controller == 'view' and \IPS\Request::i()->do == 'rss' )
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

			$this->sendBlogRss( $member ?? new \IPS\Member );

			if( !\IPS\Member::loggedIn()->group['g_view_board'] )
			{
				\IPS\Output::i()->error( 'node_error', '2B221/1', 404, '' );
			}
		}
	}

	/**
	 * Send the blog's RSS feed for the indicated member
	 *
	 * @param	\IPS\Member		$member		Member
	 * @return	void
	 */
	protected function sendBlogRss( $member )
	{
		try
		{
			$blog = \IPS\blog\Blog::load( \IPS\Request::i()->id );

			if( !$blog->can( 'view', $member ) )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			/* We'll let the regular controller handle the error */
			return;
		}

		if( !\IPS\Settings::i()->blog_allow_rss or !$blog->settings['allowrss'] )
		{
			\IPS\Output::i()->error( 'blog_rss_offline', '2B201/5', 403, 'blog_rss_offline_admin' );
		}

		/* We have to use get() to ensure CDATA tags wrap the blog title properly */
		$title	= $blog->member_id ? $blog->name : $member->language()->get( "blogs_blog_{$blog->id}" );

		$document = \IPS\Xml\Rss::newDocument( $blog->url(), $title, $blog->description );
	
		foreach ( \IPS\blog\Entry::getItemsWithPermission( array( array( 'entry_blog_id=?', $blog->id ), array( 'entry_status!=?', 'draft' ) ), 'entry_date DESC', 25, 'read', \IPS\Content\Hideable::FILTER_PUBLIC_ONLY, 0, $member, FALSE, FALSE, FALSE, FALSE, NULL, $blog ) as $entry )
		{
			$document->addItem( $entry->name, $entry->url(), $entry->content, \IPS\DateTime::ts( $entry->date ), $entry->id );
		}
	
		/* @note application/rss+xml is not a registered IANA mime-type so we need to stick with text/xml for RSS */
		\IPS\Output::i()->sendOutput( $document->asXML(), 200, 'text/xml', array(), TRUE );
	}

	/**
	 * Perform any additional installation needs
	 *
	 * @return void
	 */
	public function installOther()
	{
		/* Allow non guests to create and comment on Blogs */
		foreach( \IPS\Member\Group::groups( TRUE, FALSE ) as $group )
		{
			$group->g_blog_allowlocal = TRUE;
			$group->g_blog_allowcomment = TRUE;
			$group->save();
		}

		/* Create new default category */
		$category = new \IPS\blog\Category;
		$category->seo_name = 'general';
		$category->save();

		\IPS\Lang::saveCustom( 'blog', "blog_category_{$category->id}", "General" );
	}

	/**
	 * [Node] Get Icon for tree
	 *
	 * @note	Return the class for the icon (e.g. 'globe')
	 * @return	string|null
	 */
	protected function get__icon()
	{
		return 'file-text';
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
			'browseTabs'	=> array( array( 'key' => 'Blogs' ) ),
			'browseTabsEnd'	=> array(),
			'activityTabs'	=> array()
		);
	}

	/**
	 * Returns a list of all existing webhooks and their payload in this app.
	 *
	 * @return array
	 */
	public function getWebhooks() : array
	{
		return array_merge(  [
			'blogBlog_create' => \IPS\blog\Blog::class
		], parent::getWebhooks());
	}
}