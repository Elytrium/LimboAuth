<?php
/**
 * @brief		[Database] Category List Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		16 April 2014
 */

namespace IPS\cms\modules\front\database;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * List
 */
class _index extends \IPS\cms\Databases\Controller
{

	/**
	 * Determine which method to load
	 *
	 * @return void
	 */
	public function manage()
	{
		/* If the Databases module is set as default we end up here, but not routed through the database dispatcher which means the
			database ID isn't set. In that case, just re-route back through the pages controller which handles everything. */
		if( \IPS\cms\Databases\Dispatcher::i()->databaseId === NULL )
		{
			$pages = new \IPS\cms\modules\front\pages\page;
			return $pages->manage();
		}

		$database = \IPS\cms\Databases::load( \IPS\cms\Databases\Dispatcher::i()->databaseId );

		/* Not using categories? */
		if ( ! $database->use_categories AND $database->cat_index_type === 0 )
		{
			$controller = new \IPS\cms\modules\front\database\category( $this->url );
			return $controller->view();
		}
		
		$this->view();
	}

	/**
	 * Display database category list.
	 *
	 * @return	void
	 */
	protected function view()
	{
		$database    = \IPS\cms\Databases::load( \IPS\cms\Databases\Dispatcher::i()->databaseId );
		$recordClass = 'IPS\cms\Records' . \IPS\cms\Databases\Dispatcher::i()->databaseId;
		$url         = \IPS\Http\Url::internal( "app=cms&module=pages&controller=page&path=" . \IPS\cms\Pages\Page::$currentPage->full_path, 'front', 'content_page_path', \IPS\cms\Pages\Page::$currentPage->full_path );

		/* RSS */
		if ( $database->rss )
		{
			/* Show the link */
			\IPS\Output::i()->rssFeeds[ $database->_title ] = $url->setQueryString( 'rss', 1 );

			/* Or actually show RSS feed */
			if ( isset( \IPS\Request::i()->rss ) )
			{
				$document     = \IPS\Xml\Rss::newDocument( $url, \IPS\Member::loggedIn()->language()->get('content_db_' . $database->id ), \IPS\Member::loggedIn()->language()->get('content_db_' . $database->id . '_desc' ) );
				$contentField = 'field_' . $database->field_content;
				
				foreach ( $recordClass::getItemsWithPermission( array(), $database->field_sort . ' ' . $database->field_direction, $database->rss, 'read' ) as $record )
				{
					$content = $record->$contentField;
						
					if ( $record->record_image )
					{
						$content = \IPS\cms\Theme::i()->getTemplate( 'listing', 'cms', 'database' )->rssItemWithImage( $content, $record->record_image );
					}

					$document->addItem( $record->_title, $record->url(), $content, \IPS\DateTime::ts( $record->_publishDate ), $record->_id );
				}
		
				/* @note application/rss+xml is not a registered IANA mime-type so we need to stick with text/xml for RSS */
				\IPS\Output::i()->sendOutput( $document->asXML(), 200, 'text/xml', array(), TRUE );
			}
		}

		$page = isset( \IPS\Request::i()->page ) ? \intval( \IPS\Request::i()->page ) : 1;

		if( $page < 1 )
		{
			$page = 1;
		}

		if ( $database->cat_index_type === 1 and ! isset( \IPS\Request::i()->show ) )
		{
			/* Featured */
			$limit = 0;
			$count = 0;

			if ( isset( \IPS\Request::i()->page ) )
			{
				$limit = $database->featured_settings['perpage'] * ( $page - 1 );
			}

			$where = ( $database->featured_settings['featured'] ) ? array( array( 'record_featured=?', 1 ) ) : NULL;
			
			if ( isset( $database->featured_settings['categories'] ) and \is_array( $database->featured_settings['categories'] ) and \count( $database->featured_settings['categories'] ) )
			{
				$categoryField = "`cms_custom_database_{$database->_id}`.`category_id`";
				$where[] = array( \IPS\Db::i()->in( $categoryField, array_values( $database->featured_settings['categories'] ) ) );
			}
			
			$articles = $recordClass::getItemsWithPermission( $where, 'record_pinned DESC, ' . $database->featured_settings['sort'] . ' ' . $database->featured_settings['direction'], array( $limit, $database->featured_settings['perpage'] ), 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, NULL, TRUE, FALSE, FALSE, FALSE );

			if ( $database->featured_settings['pagination'] )
			{
				$count = $recordClass::getItemsWithPermission( $where, 'record_pinned DESC, ' . $database->featured_settings['sort'] . ' ' . $database->featured_settings['direction'], $database->featured_settings['perpage'], 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, NULL, FALSE, FALSE, FALSE, TRUE );
			}

			/* Pagination */
			$pagination = array(
				'page'  => $page,
				'pages' => ( $count > 0 ) ? ceil( $count / $database->featured_settings['perpage'] ) : 1
			);
			
			/* Make sure we are viewing a real page */
			if ( $page > $pagination['pages'] )
			{
				\IPS\Output::i()->redirect( \IPS\Request::i()->url()->setPage( 'page', 1 ), NULL, 303 );
			}
			
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'database_index/featured.css', 'cms', 'front' ) );
			\IPS\Output::i()->title = ( $page > 1 ) ? \IPS\Member::loggedIn()->language()->addToStack( 'title_with_page_number', FALSE, array( 'sprintf' => array( $database->pageTitle(), $page ) ) ) : $database->pageTitle();

			\IPS\cms\Databases\Dispatcher::i()->output .= \IPS\Output::i()->output = \IPS\cms\Theme::i()->getTemplate( $database->template_featured, 'cms', 'database' )->index( $database, $articles, $url, $pagination );
		}
		else
		{
			/* Category view */
			$class = '\IPS\cms\Categories' . $database->id;
			
			/* Load into memory */
			$class::loadIntoMemory();
			$categories = $class::roots();

			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'records/index.css', 'cms', 'front' ) );
			\IPS\Output::i()->title = $database->pageTitle();
			\IPS\cms\Databases\Dispatcher::i()->output .= \IPS\Output::i()->output = \IPS\cms\Theme::i()->getTemplate( $database->template_categories, 'cms', 'database' )->index( $database, $categories, $url );
		}
	}

	/**
	 * Show the pre add record form. This is used when no category is set.
	 *
	 * @return	void
	 */
	protected function form()
	{
		/* If the page is the default page and Pages is the default app, the node selector cannot find the page as it bypasses the Database dispatcher */
		if ( \IPS\cms\Pages\Page::$currentPage === NULL and \IPS\cms\Databases\Dispatcher::i()->databaseId === NULL and isset( \IPS\Request::i()->page_id ) )
		{
			try
			{
				\IPS\cms\Pages\Page::$currentPage = \IPS\cms\Pages\Page::load( \IPS\Request::i()->page_id );
				$database = \IPS\cms\Pages\Page::$currentPage->getDatabase();
				
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'content_err_page_404', '2T389/1', 404, '' );
			}
		}
		else if ( \IPS\cms\Pages\Page::$currentPage === NULL and \IPS\cms\Databases\Dispatcher::i()->databaseId === NULL and isset( \IPS\Request::i()->d ) )
		{
			\IPS\cms\Pages\Page::$currentPage = \IPS\cms\Pages\Page::loadByDatabaseId( \IPS\Request::i()->d );
		}
		
		$form = new \IPS\Helpers\Form( 'select_category', 'continue' );
		$form->class = 'ipsForm_vertical ipsForm_noLabels';
		$form->add( new \IPS\Helpers\Form\Node( 'category', NULL, TRUE, array(
			'url'					=> \IPS\cms\Pages\Page::$currentPage->url()->setQueryString( array( 'do' => 'form', 'page_id' => \IPS\cms\Pages\Page::$currentPage->id ) ),
			'class'					=> 'IPS\cms\Categories' . \IPS\cms\Pages\Page::$currentPage->getDatabase()->_id,
			'permissionCheck'		=> function( $node )
			{
				if ( $node->can( 'view' ) )
				{
					if ( $node->can( 'add' ) )
					{
						return TRUE;
					}

					return FALSE;
				}

				return NULL;
			},
		) ) );

		if ( $values = $form->values() )
		{
			\IPS\Output::i()->redirect( $values['category']->url()->setQueryString( 'do', 'form' ) );
		}

		\IPS\Output::i()->title						= \IPS\Member::loggedIn()->language()->addToStack( 'cms_select_category' );
		\IPS\Output::i()->breadcrumb[]				= array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'cms_select_category' ) );
		\IPS\cms\Databases\Dispatcher::i()->output	= \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'records' )->categorySelector( $form );
	}
}
