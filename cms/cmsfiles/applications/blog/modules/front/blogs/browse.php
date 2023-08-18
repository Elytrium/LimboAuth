<?php
/**
 * @brief		All Blogs
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		03 Mar 2014
 */

namespace IPS\blog\modules\front\blogs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * browse
 */
class _browse extends \IPS\Dispatcher\Controller
{
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Category */
		try
		{
			$category = \IPS\blog\Category::loadAndCheckPerms( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			$category = NULL;
		}

		/* Featured stuff */
		$featured = iterator_to_array( \IPS\blog\Entry::featured( 5, '_rand' ) );
		$blogs = \IPS\blog\Blog::loadByOwner( \IPS\Member::loggedIn(), array( array( 'blog_disabled=?', 0 ) ) );
		
		if ( ! \IPS\Settings::i()->blog_allow_grid )
		{
			$viewMode = 'list';
		}
		else
		{
			$viewMode = ( isset( \IPS\Request::i()->view ) ) ? \IPS\Request::i()->view : ( isset( \IPS\Request::i()->cookie['blog_view'] ) ? \IPS\Request::i()->cookie['blog_view'] : \IPS\Settings::i()->blog_view_mode );
		}
		
		if ( isset( \IPS\Request::i()->view ) )
		{
			\IPS\Session::i()->csrfCheck();
			\IPS\Request::i()->setCookie( 'blog_view', \IPS\Request::i()->view, ( new \IPS\DateTime )->add( new \DateInterval( 'P1Y' ) ) );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=blog', 'front', 'blogs' ) );
		}
		
		/* Grid view */
		if ( $viewMode == 'grid' )
		{
			$perpage = 23;
			$page    = 1;
			
			if ( \IPS\Request::i()->page )
			{
				$page = \intval( \IPS\Request::i()->page );
				if ( !$page OR $page < 1 )
				{
					$page = 1;
				}
			}
			
			/* @note We cannot check individual member permissions here, so entries in draft status are excluded. */

			$where		= array();
			$where[]		= array( "blog_entries.entry_status!=?", 'draft' );
			if ( !\IPS\Settings::i()->club_nodes_in_apps )
			{
				$where[] = array( "blog_blogs.blog_club_id IS NULL" );
			}
			if( $category )
			{
				$where[] = array( 'blog_blogs.blog_category_id=?', $category->_id );
			}
			$count   = \IPS\blog\Entry::getItemsWithPermission( $where, 'entry_date desc', NULL, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, NULL, TRUE, FALSE, FALSE, TRUE );
			$entries = \IPS\blog\Entry::getItemsWithPermission( $where, 'entry_date desc', array( ( $perpage * ( $page - 1 ) ), $perpage ), 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, NULL, TRUE );

            $queryString = 'app=blog&module=blogs&controller=browse';
            if( $category )
            {
                $queryString .= '&id=' . $category->_id;
            }
            $paginationUrl = \IPS\Http\Url::internal( $queryString, 'front', ( $category ) ? 'blog_category' : 'blogs' );
			$pagination = array(
				'page'    => $page,
				'pages'   => ceil( $count / $perpage ),
				'perpage' => $perpage,
				'url'     => $paginationUrl
			);
			
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'grid.css', 'blog', 'front' ) );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'browse' )->indexGrid( $entries, $featured, $blogs, $pagination, $viewMode, $category );
		}
		else
		{	
			/* Blogs table */
			$table = new \IPS\blog\Blog\Table( \IPS\Http\Url::internal( 'app=blog&module=blogs&controller=browse', 'front', 'blogs' ) );
			if( $category )
			{
				$table->where[] = array( 'blog_category_id=?', $category->_id );
			}
			$table->title = 'our_community_blogs';
			$table->classes = array( 'cBlogList', 'ipsAreaBackground', 'ipsDataList_large' );
	
			/* Filters */
			$table->filters = array(
				'my_blogs'				=> array( '(' . \IPS\Db::i()->findInSet( 'blog_groupblog_ids', \IPS\Member::loggedIn()->groups ) . ' OR ' . 'blog_member_id=? )', \IPS\Member::loggedIn()->member_id ),
				'blogs_with_content'	=> array( 'blog_count_entries>0' )
			);
			
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'browse' )->index( $table, $featured, $blogs, $viewMode, $category );
		}
		
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=blog', 'front', 'blogs' ), array(), 'loc_blog_viewing_index' );
				
		/* Display */
		if( \count( \IPS\blog\Category::roots() ) > 1 )
		{
			\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate( 'browse' )->categories( $category );
		}

		if( $category )
		{
			foreach( $category->parents() as $parent )
			{
				\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
			}
			\IPS\Output::i()->breadcrumb[] = array( $category->url(), $category->_title );

			/* Set default search option */
			\IPS\Output::i()->defaultSearchOption = array( 'blog_entry', 'blog_entry_pl' );
		}
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_browse.js', 'blog', 'front' ) );
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('blogs');
		
	}
}