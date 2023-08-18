<?php
/**
 * @brief		Browse Files Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		08 Oct 2013
 */

namespace IPS\downloads\modules\front\downloads;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Browse Files
 */
class _browse extends \IPS\Dispatcher\Controller
{
	
	/**
	 * Mark Read
	 *
	 * @return	void
	 */
	protected function markRead()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$category	= \IPS\downloads\Category::load( \IPS\Request::i()->id );

			\IPS\downloads\File::markContainerRead( $category, NULL, FALSE );

			\IPS\Output::i()->redirect( $category->url() );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2D175/3', 403, 'no_module_permission_guest' );
		}
	}

	/**
	 * Route
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( isset( \IPS\Request::i()->currency ) and \in_array( \IPS\Request::i()->currency, \IPS\nexus\Money::currencies() ) and isset( \IPS\Request::i()->csrfKey ) and \IPS\Request::i()->csrfKey === \IPS\Session\Front::i()->csrfKey )
		{
			\IPS\Request::i()->setCookie( 'currency', \IPS\Request::i()->currency );
		}
		
		if ( isset( \IPS\Request::i()->id ) )
		{
			if ( \IPS\Request::i()->id == 'clubs' and \IPS\Settings::i()->club_nodes_in_apps )
			{
				\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=downloads&module=downloads&controller=browse&do=categories', 'front', 'downloads_categories' ), array(), 'loc_downloads_browsing_categories' );
				\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=downloads&module=downloads&controller=browse&do=categories', 'front', 'downloads_categories' ), \IPS\Member::loggedIn()->language()->addToStack('download_categories') );
				\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=downloads&module=downloads&controller=browse&id=clubs', 'front', 'downloads_clubs' ), \IPS\Member::loggedIn()->language()->addToStack('club_node_downloads') );
				\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('club_node_downloads');
				\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'browse' )->categories( TRUE );
			}
			else
			{
				try
				{
					$this->_category( \IPS\downloads\Category::loadAndCheckPerms( \IPS\Request::i()->id, 'read' ) );
				}
				catch ( \OutOfRangeException $e )
				{
					\IPS\Output::i()->error( 'node_error', '2D175/1', 404, '' );
				}
			}
		}
		else
		{
			$this->_index();
		}
	}
	
	/**
	 * Show Index
	 *
	 * @return	void
	 */
	protected function _index()
	{
		/* Add RSS feed */
		if ( \IPS\Settings::i()->idm_rss )
		{
			\IPS\Output::i()->rssFeeds['idm_rss_title'] = \IPS\Http\Url::internal( 'app=downloads&module=downloads&controller=browse&do=rss', 'front', 'downloads_rss' );

			if ( \IPS\Member::loggedIn()->member_id )
			{
				$key = \IPS\Member::loggedIn()->getUniqueMemberHash();

				\IPS\Output::i()->rssFeeds['idm_rss_title'] = \IPS\Output::i()->rssFeeds['idm_rss_title']->setQueryString( array( 'member' => \IPS\Member::loggedIn()->member_id , 'key' => $key ) );
			}
		}
		
		/* Get stuff */
		$featured = \IPS\Settings::i()->idm_show_featured ? iterator_to_array( \IPS\downloads\File::featured( \IPS\Settings::i()->idm_featured_count, '_rand' ) ) : array();

		if ( \IPS\Settings::i()->idm_newest_categories )
		{
			$newestWhere = array( array( 'downloads_categories.copen=1 and ' . \IPS\Db::i()->in('file_cat', explode( ',', \IPS\Settings::i()->idm_newest_categories ) ) ) );
		}
		else
		{
			$newestWhere = array( array( 'downloads_categories.copen=1' ) );
		}
		if ( !\IPS\Settings::i()->club_nodes_in_apps )
		{
			$newestWhere[] = array( 'downloads_categories.cclub_id IS NULL' );
		}

        $new = ( \IPS\Settings::i()->idm_show_newest) ? \IPS\downloads\File::getItemsWithPermission( $newestWhere, NULL, 14, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, NULL, TRUE ) : array();

		if ( \IPS\Settings::i()->idm_highest_rated_categories )
		{
			$highestWhere = array( array( 'downloads_categories.copen=1 and ' . \IPS\Db::i()->in('file_cat', explode( ',', \IPS\Settings::i()->idm_highest_rated_categories ) ) ) );
		}
		else
		{
			$highestWhere = array( array( 'downloads_categories.copen=1' ) );
		}
		$highestWhere[] = array( 'file_rating > ?', 0 );
		if ( !\IPS\Settings::i()->club_nodes_in_apps )
		{
			$highestWhere[] = array( 'downloads_categories.cclub_id IS NULL' );
		}
		$highestRated = ( \IPS\Settings::i()->idm_show_highest_rated ) ? \IPS\downloads\File::getItemsWithPermission( $highestWhere, 'file_rating DESC, file_reviews DESC', 14, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, NULL, TRUE ) : array();

		if (\IPS\Settings::i()->idm_show_most_downloaded_categories )
		{
			$mostDownloadedWhere = array( array( 'downloads_categories.copen=1 and ' . \IPS\Db::i()->in('file_cat', explode( ',', \IPS\Settings::i()->idm_show_most_downloaded_categories ) ) ) );
		}
		else
		{
			$mostDownloadedWhere = array( array( 'downloads_categories.copen=1' ) );
		}
		$mostDownloadedWhere[] = array( 'downloads_categories.copen=1 and file_downloads > ?', 0 );
		if ( !\IPS\Settings::i()->club_nodes_in_apps )
		{
			$mostDownloadedWhere[] = array( 'downloads_categories.cclub_id IS NULL' );
		}
		$mostDownloaded = ( \IPS\Settings::i()->idm_show_most_downloaded ) ? \IPS\downloads\File::getItemsWithPermission( $mostDownloadedWhere, 'file_downloads DESC', 14, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, NULL, TRUE ) : array();
		
		/* Online User Location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=downloads', 'front', 'downloads' ), array(), 'loc_downloads_browsing' );
		
		/* Display */
		\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate( 'browse' )->indexSidebar( \IPS\downloads\Category::canOnAny('add') );
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('downloads');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'browse' )->index( $featured, $new, $highestRated, $mostDownloaded );
	}
	
	/**
	 * Show Category
	 *
	 * @param	\IPS\downloads\Category	$category	The category to show
	 * @return	void
	 */
	protected function _category( $category )
	{
		$category->clubCheckRules();
		
		\IPS\Output::i()->sidebar['contextual'] = '';
		
		$_count = \IPS\downloads\File::getItemsWithPermission( array( array( \IPS\downloads\File::$databasePrefix . \IPS\downloads\File::$databaseColumnMap['container'] . '=?', $category->_id ) ), NULL, 1, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, NULL, FALSE, FALSE, FALSE, TRUE );

		if( !$_count )
		{
			/* If we're viewing a club, set the breadcrumbs appropriately */
			if ( $club = $category->club() )
			{
				$club->setBreadcrumbs( $category );
			}
			else
			{
				foreach ( $category->parents() as $parent )
				{
					\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
				}
				\IPS\Output::i()->breadcrumb[] = array( NULL, $category->_title );
			}

			/* Show a 'no files' template if there's nothing to display */
			$table = \IPS\Theme::i()->getTemplate( 'browse' )->noFiles( $category );
		}
		else
		{
			/* Build table */
			$table = new \IPS\Helpers\Table\Content( 'IPS\downloads\File', $category->url(), NULL, $category );
			$table->classes = array( 'ipsDataList_large' );
			$table->sortOptions = array_merge( $table->sortOptions, array( 'file_downloads' => 'file_downloads' ) );

			if ( !$category->bitoptions['reviews_download'] )
			{
				unset( $table->sortOptions['num_reviews'] );
			}

			if ( !$category->bitoptions['comments'] )
			{
				unset( $table->sortOptions['last_comment'] );
				unset( $table->sortOptions['num_comments'] );
			}

			if ( $table->sortBy === 'downloads_files.file_title' )
			{
				$table->sortDirection = 'asc';
			}
			
			if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->idm_nexus_on )
			{
				$table->filters = array(
					'file_free'	=> "( ( file_cost='' OR file_cost IS NULL ) AND ( file_nexus='' OR file_nexus IS NULL ) )",
					'file_paid'	=> "( file_cost<>'' OR file_nexus>0 )",
				);
			}
			$table->title = \IPS\Member::loggedIn()->language()->pluralize(  \IPS\Member::loggedIn()->language()->get('download_file_count'), array( $_count ) );
		}

		/* Online User Location */
		$permissions = $category->permissions();
		\IPS\Session::i()->setLocation( $category->url(), explode( ",", $permissions['perm_view'] ), 'loc_downloads_viewing_category', array( "downloads_category_{$category->id}" => TRUE ) );
				
		/* Set default search option */
		\IPS\Output::i()->defaultSearchOption = array( 'downloads_file', "downloads_file_pl" );
		
		/* Update Views */
		if ( !\IPS\Request::i()->isAjax() )
		{
			$category->updateViews();
		}

		/* Output */
		\IPS\Output::i()->title		= $category->_title;
		\IPS\Output::i()->contextualSearchOptions[ \IPS\Member::loggedIn()->language()->addToStack( 'search_contextual_item_downloads_categories' ) ] = array( 'type' => 'downloads_file', 'nodes' => $category->_id );
		\IPS\Output::i()->sidebar['contextual'] .= \IPS\Theme::i()->getTemplate( 'browse' )->indexSidebar( \IPS\downloads\Category::canOnAny('add'), $category );
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'browse' )->category( $category, (string) $table );
	}

	/**
	 * Show a category listing
	 *
	 * @return	void
	 */
	protected function categories()
	{
		/* Online User Location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=downloads&module=downloads&controller=browse&do=categories', 'front', 'downloads_categories' ), array(), 'loc_downloads_browsing_categories' );
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('downloads_categories_pagetitle');
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=downloads&module=downloads&controller=browse&do=categories', 'front', 'downloads_categories' ), \IPS\Member::loggedIn()->language()->addToStack('download_categories') );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'browse' )->categories();
	}

	/**
	 * Set the viewing method
	 *
	 * @return	void
	 */
	protected function setMethod()
	{
		\IPS\Session::i()->csrfCheck();

		$method = ( isset( \IPS\Request::i()->method ) ) ? \IPS\Request::i()->method : \IPS\Settings::i()->idm_default_view;

		\IPS\Request::i()->setCookie( 'idm_category_view', $method, ( new \IPS\DateTime )->add( new \DateInterval( 'P1Y' ) ) );

		if ( \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Db::i()->replace( 'downloads_view_method', array( 'member_id' => \IPS\Member::loggedIn()->member_id, 'method' => $method ) );
		}
		\IPS\Output::i()->redirect( \IPS\downloads\Category::load( \IPS\Request::i()->id )->url() );
	}
}