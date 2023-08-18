<?php
/**
 * @brief		[Front] Page Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		25 Feb 2014
 */

namespace IPS\cms\modules\front\pages;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * page
 */
class _page extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		parent::execute();
	}

	/**
	 * Determine which method to load
	 *
	 * @return void
	 */
	public function manage()
	{
		$this->view();
	}
	
	/**
	 * Display a page. Sounds simple doesn't it? Well it's not.
	 *
	 * @return	void
	 */
	protected function view()
	{
		$page = $this->getPage();
		
		/* Database specific checks */
		if ( isset( \IPS\Request::i()->advancedSearchForm ) AND isset( \IPS\Request::i()->d ) )
		{
			/* showTableSearchForm just triggers __call which returns the database dispatcher HTML as we
			 * do not want the page content around the actual database */
			\IPS\Output::i()->output = $this->showTableSearchForm();
			return;
		}

		if ( \IPS\Request::i()->path == $page->full_path )
		{
			/* Are we using Friendly URL's at all? */
			if ( \IPS\Settings::i()->use_friendly_urls )
			{
				/* Did we have a trailing slash? */
				if ( \IPS\Settings::i()->htaccess_mod_rewrite and mb_substr( \IPS\Request::i()->url()->data[ \IPS\Http\Url::COMPONENT_PATH ], -1 ) != '/' )
				{
					$url = $page->url();
					
					foreach( \IPS\Request::i()->url()->queryString as $k => $v )
					{
						$url = $url->setQueryString( $k, $v );
					}
					
					if ( ! empty( \IPS\Request::i()->url()->fragment ) )
					{
						$url = $url->setFragment( \IPS\Request::i()->url()->fragment );
					}
	
					\IPS\Output::i()->redirect( $url, NULL, 301 );
				}
				else if ( ! \IPS\Settings::i()->htaccess_mod_rewrite and ! mb_strstr( \IPS\Request::i()->url()->data[ \IPS\Http\Url::COMPONENT_QUERY ], $page->url()->data[ \IPS\Http\Url::COMPONENT_QUERY ] ) )
				{
					$url = $page->url();
					
					foreach( \IPS\Request::i()->url()->queryString as $k => $v )
					{
						if ( mb_substr( $k, 0, 1 ) == '/' and mb_substr( $k, -1 ) != '/' )
						{
							$k .= '/';
						}
					}
					
					$url = $url->setQueryString( $k, $v );
					
					if ( ! empty( \IPS\Request::i()->url()->fragment ) )
					{
						$url = $url->setFragment( \IPS\Request::i()->url()->fragment );
					}
	
					\IPS\Output::i()->redirect( $url, NULL, 301 );
				}
			}

			/* Just viewing this page, no database categories or records */
			$permissions = $page->permissions();
			\IPS\Session::i()->setLocation( $page->url(), explode( ",", $permissions['perm_view'] ), 'loc_cms_viewing_page', array( 'cms_page_' . $page->_id => TRUE ) );
		}
		
		try
		{
			$page->output();
		}
		catch ( \ParseError $e )
		{
			\IPS\Log::log( $e, 'page_error' );
			\IPS\Output::i()->error( 'content_err_page_500', '2T187/4', 500, 'content_err_page_500_admin', array(), $e );
		}
	}
	
	/**
	 * Get the current page
	 * 
	 * @return \IPS\cms\Pages\Page
	 */
	public function getPage()
	{
		$page = null;
		if ( isset( \IPS\Request::i()->page_id ) )
		{
			try
			{
				$page = \IPS\cms\Pages\Page::load( \IPS\Request::i()->page_id );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'content_err_page_404', '2T187/1', 404, '' );
			}
		}
		else if ( isset( \IPS\Request::i()->path ) AND  \IPS\Request::i()->path != '/' )
		{
			/* Sort out pagination for pages */
			list( $path, $pageNumber ) = \IPS\cms\Pages\Page::getStrippedPagePath( \IPS\Request::i()->path );
			if( $pageNumber AND !\IPS\Request::i()->page )
			{
				\IPS\Request::i()->page = $pageNumber;
			}

			try
			{
				$page = \IPS\cms\Pages\Page::loadFromPath( $path );
			}
			catch ( \OutOfRangeException $e )
			{
				try
				{
					$page = \IPS\cms\Pages\Page::getUrlFromHistory( \IPS\Request::i()->path, ( isset( \IPS\Request::i()->url()->data['query'] ) ? \IPS\Request::i()->url()->data['query'] : NULL ) );

					if( (string) $page == (string) \IPS\Request::i()->url() )
					{
						\IPS\Output::i()->error( 'content_err_page_404', '2T187/3', 404, '' );
					}

					\IPS\Output::i()->redirect( $page, NULL, 301 );
				}
				catch( \OutOfRangeException $e )
				{
					\IPS\Output::i()->error( 'content_err_page_404', '2T187/2', 404, '' );
				}
			}
		}
		else
		{
            try
            {
                $page = \IPS\cms\Pages\Page::getDefaultPage();
            }
            catch ( \OutOfRangeException $e )
            {
                \IPS\Output::i()->error( 'content_err_page_404', '2T257/1', 404, '' );
            }
		}
		
		if ( $page === NULL )
		{
            \IPS\Output::i()->error( 'content_err_page_404', '2T257/2', 404, '' );
		}

		if ( ! $page->can('view') )
		{
			\IPS\Output::i()->error( 'content_err_page_403', '2T187/3', 403, '' );
		}
		
		/* Set the current page, so other blocks, DBs, etc don't have to figure out where they are */
		\IPS\cms\Pages\Page::$currentPage = $page;
		
		return $page;
	}
	
	/**
	 * Capture database specific things
	 *
	 * @param	string	$method	Desired method
	 * @param	array	$args	Arguments
	 * @return	void
	 */
	public function __call( $method, $args )
	{
		$page = $this->getPage();
		$page->setTheme();
		$databaseId = ( isset( \IPS\Request::i()->d ) ) ? \IPS\Request::i()->d : $page->getDatabase()->_id;

		if ( $databaseId !== NULL )
		{
			try
			{
				if ( \IPS\Request::i()->isAjax() )
				{
					return \IPS\cms\Databases\Dispatcher::i()->setDatabase( $databaseId )->run();
				}
				else
				{
					$page->output();
				}
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'content_err_page_404', '2T257/3', 404, '' );
			}
		}
	}

	/**
	 * Embed
	 *
	 * @return	void
	 */
	protected function embed()
	{
		return $this->__call( 'embed', \func_get_args() );
	}
}