<?php
/**
* @brief		Pages Controller
* @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
* @copyright	(c) Invision Power Services, Inc.
* @license		https://www.invisioncommunity.com/legal/standards/
* @package		Invision Community
* @subpackage	Content
* @since		15 Jan 2013
* @version		SVN_VERSION_NUMBER
*/

namespace IPS\cms\modules\admin\pages;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
exit;
}

/**
* Page management
*/
class _pages extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = '\IPS\cms\Pages\Folder';
	
	/**
	 * Store the database page map to prevent many queries
	 */
	protected static $pageToDatabaseMap = NULL;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'page_manage' );
		parent::execute();
	}

	/**
	 * Get Root Buttons
	 *
	 * @return	array
	 */
	public function _getRootButtons()
	{
		$nodeClass = $this->nodeClass;
		$buttons   = array();

		return $buttons;
	}

	/**
	 * Show the pages tree
	 *
	 * @return	string
	 */
	protected function manage()
	{
		$url = \IPS\Http\Url::internal( "app=cms&module=pages&controller=pages" );
		static::$pageToDatabaseMap = iterator_to_array( \IPS\Db::i()->select( 'database_id, database_page_id', 'cms_databases', array( 'database_page_id > 0' ) )->setKeyField('database_page_id')->setValueField('database_id') );
		
		/* Display the table */
		\IPS\Output::i()->title  = \IPS\Member::loggedIn()->language()->addToStack('menu__cms_pages_pages');
		\IPS\Output::i()->output = new \IPS\Helpers\Tree\Tree( $url, 'menu__cms_pages_pages',
			/* Get Roots */
			function () use ( $url )
			{
				$data = \IPS\cms\modules\admin\pages\pages::getRowsForTree( 0 );
				$rows = array();

				foreach ( $data as $id => $row )
				{
					$rows[ $id ] = ( $row instanceof \IPS\cms\Pages\Page ) ? \IPS\cms\modules\admin\pages\pages::getPageRow( $row, $url ) : \IPS\cms\modules\admin\pages\pages::getFolderRow( $row, $url );
				}

				return $rows;
			},
			/* Get Row */
			function ( $id, $root ) use ( $url )
			{
				if ( $root )
				{
					return \IPS\cms\modules\admin\pages\pages::getFolderRow( \IPS\cms\Pages\Folder::load( $id ), $url );
				}
				else
				{
					return \IPS\cms\modules\admin\pages\pages::getPageRow( \IPS\cms\Pages\Page::load( $id ), $url );
				}
			},
			/* Get Row Parent ID*/
			function ()
			{
				return NULL;
			},
			/* Get Children */
			function ( $id ) use ( $url )
			{
				$rows = array();
				$data = \IPS\cms\modules\admin\pages\pages::getRowsForTree( $id );

				if ( ! isset( \IPS\Request::i()->subnode ) )
				{
					foreach ( $data as $id => $row )
					{
						$rows[ $id ] = ( $row instanceof \IPS\cms\Pages\Page ) ? \IPS\cms\modules\admin\pages\pages::getPageRow( $row, $url ) : \IPS\cms\modules\admin\pages\pages::getFolderRow( $row, $url );
					}
				}
				return $rows;
			},
           array( $this, '_getRootButtons' ),
           TRUE,
           FALSE,
           FALSE
		);
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'cms', 'pages', 'page_add' )  )
		{
			\IPS\Output::i()->sidebar['actions']['add_folder'] = array(
				'primary'	=> true,
				'icon'	=> 'folder-open',
				'title'	=> 'content_add_folder',
				'link'	=> \IPS\Http\Url::internal( 'app=cms&module=pages&controller=pages&do=form' ),
				'data'  => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('content_add_folder') )
			);

			\IPS\Output::i()->sidebar['actions']['add_page'] = array(
				'primary'	=> true,
				'icon'	=> 'plus-circle',
				'title'	=> 'content_add_page',
				'link'	=>  \IPS\Http\Url::internal( 'app=cms&module=pages&controller=pages&subnode=1&do=add' ),
				'data'  => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('content_add_page') )
			);
		}
	}
	
	/**
	 * Download .htaccess file
	 *
	 * @return	void
	 */
	protected function htaccess()
	{
		$dir = str_replace( \IPS\CP_DIRECTORY . '/index.php', '', $_SERVER['PHP_SELF'] );
		$dirs = explode( '/', trim( $dir, '/' ) );
		
		if ( \count( $dirs ) )
		{
			array_pop( $dirs );
			$dir = implode( '/', $dirs );
			
			if ( ! $dir )
			{
				$dir = '/';
			}
		}
		
		$path = $dir . 'index.php';
		
		if( \strpos( $dir, ' ' ) !== FALSE )
		{
			$dir = '"' . $dir . '"';
			$path = '"' . $path . '"';
		}


		$htaccess = <<<FILE
<IfModule mod_rewrite.c>
Options -MultiViews
RewriteEngine On
RewriteBase {$dir}
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule \\.(js|css|jpeg|jpg|gif|png|ico)(\\?|$) - [L,NC,R=404]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . {$path} [L]
</IfModule>
FILE;

		\IPS\Output::i()->sendOutput( $htaccess, 200, 'application/x-htaccess', array( 'Content-Disposition' => 'attachment; filename=.htaccess' ) );
	}

	/**
	 * Page content form
	 *
	 * @return void
	 */
	protected function add()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'page_add' );

		$form = new \IPS\Helpers\Form( 'form', 'next' );
		$form->hiddenValues['parent'] = ( isset( \IPS\Request::i()->parent ) ) ? \IPS\Request::i()->parent : 0;

		$form->add( new \IPS\Helpers\Form\Radio(
			            'page_type',
			            NULL,
			            FALSE,
			            array( 'options'      => array( 'builder' => 'page_type_builder', 'html' => 'page_type_manual' ),
			                   'descriptions' => array( 'builder' => 'page_type_builder_desc', 'html' => 'page_type_manual_custom_desc' ) ),
			            NULL,
			            NULL,
			            NULL,
			            'page_type'
		            ) );


		if ( $values = $form->values() )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=pages&controller=pages&do=form&subnode=1&page_type=' . $values['page_type'] . '&parent=' . \IPS\Request::i()->parent ) );
		}

		/* Display */
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global', 'core', 'admin' )->block( \IPS\Member::loggedIn()->language()->addToStack('content_add_page'), $form, FALSE );
		\IPS\Output::i()->title  = \IPS\Member::loggedIn()->language()->addToStack('content_add_page');
	}

	/**
	 * Delete
	 *
	 * @return	void
	 */
	protected function delete()
	{
		if ( isset( \IPS\Request::i()->id ) )
		{
			\IPS\Session::i()->csrfCheck();
			\IPS\cms\Pages\Page::deleteCompiled( \IPS\Request::i()->id );
		}

		return parent::delete();
	}

	/**
	 * Set as default page for this folder
	 *
	 * @return void
	 */
	protected function setAsDefault()
	{
		\IPS\Session::i()->csrfCheck();
		\IPS\cms\Pages\Page::load( \IPS\Request::i()->id )->setAsDefault();
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=cms&module=pages&controller=pages" ), 'saved' );
	}

	/**
	 * Set as default error page
	 *
	 * @return void
	 */
	protected function toggleDefaultError()
	{
		\IPS\Session::i()->csrfCheck();
		\IPS\Settings::i()->changeValues( array( 'cms_error_page' => \IPS\Request::i()->id ? \IPS\Request::i()->id : NULL ) );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=cms&module=pages&controller=pages" ), 'saved' );
	}

	/**
	 * Tree Search
	 *
	 * @return	void
	 */
	protected function search()
	{
		$rows = array();
		$url  = \IPS\Http\Url::internal( "app=cms&module=pages&controller=pages" );

		/* Get results */
		$folders = \IPS\cms\Pages\Folder::search( 'folder_name'  , \IPS\Request::i()->input, 'folder_name' );
		$pages   = \IPS\cms\Pages\Page::search( 'page_seo_name', \IPS\Request::i()->input, 'page_seo_name' );

		$results =  \IPS\cms\Pages\Folder::munge( $folders, $pages );

		/* Convert to HTML */
		foreach ( $results as $id => $result )
		{
			$rows[ $id ] = ( $result instanceof \IPS\cms\Pages\Page ) ? $this->getPageRow( $result, $url ) : $this->getFolderRow( $result, $url );
		}

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'trees', 'core' )->rows( $rows, '' );
	}

	/**
	 * Return HTML for a page row
	 *
	 * @param   array   $page	Row data
	 * @param	object	$url	\IPS\Http\Url object
	 * @return	string	HTML
	 */
	public static function getPageRow( $page, $url )
	{
		$badge = NULL;
		
		if ( isset( static::$pageToDatabaseMap[ $page->id ] ) )
		{
			$badge = array( 0 => 'style7', 1 => \IPS\Member::loggedIn()->language()->addToStack( 'page_database_display', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack('content_db_' . static::$pageToDatabaseMap[ $page->id ] ) ) ) ) );
		}
		return \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row( $url, $page->id, $page->seo_name, false, $page->getButtons( \IPS\Http\url::internal('app=cms&module=pages&controller=pages'), true ), "", 'file-text-o', NULL, FALSE, NULL, NULL, $badge, FALSE, FALSE, FALSE );
	}

	/**
	 * Return HTML for a folder row
	 *
	 * @param   array   $folder	Row data
	 * @param	object	$url	\IPS\Http\Url object
	 * @return	string	HTML
	 */
	public static function getFolderRow( $folder, $url )
	{
		return \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row( $url, $folder->id, $folder->name, true, $folder->getButtons( \IPS\Http\url::internal('app=cms&module=pages&controller=pages') ),  "", 'folder-o', NULL );
	}

	/**
	 * Fetch rows of folders/pages
	 *
	 * @param	int	$folderId		Parent ID to fetch from
	 */
	public static function getRowsForTree( $folderId=0 )
	{
		try
		{
			if ( $folderId === 0 )
			{
				$folders = \IPS\cms\Pages\Folder::roots();
			}
			else
			{
				$folders = \IPS\cms\Pages\Folder::load( $folderId )->children( NULL, NULL, FALSE );
			}
		}
		catch( \OutOfRangeException $ex )
		{
			$folders = array();
		}

		$pages   = \IPS\cms\Pages\Page::getChildren( $folderId );

		return \IPS\cms\Pages\Folder::munge( $folders, $pages );
	}

	/**
	 * Redirect after save
	 *
	 * @param	\IPS\Node\Model	$old			A clone of the node as it was before or NULL if this is a creation
	 * @param	\IPS\Node\Model	$new			The node now
	 * @param	string			$lastUsedTab	The tab last used in the form
	 * @return	void
	 */
	protected function _afterSave( ?\IPS\Node\Model $old, \IPS\Node\Model $new, $lastUsedTab = FALSE )
	{
		/* Is there a default page in the folder? */
		try
		{
			$existingDefault = \IPS\Db::i()->select( 'page_id', 'cms_pages', array( 'page_folder_id=? and page_default=? and page_id <> ?', $new->folder_id, 1, $new->id ) )->first();

			/* If this page was the default in a folder, and it was moved to a new folder that already has a default, we need to unset the
			default page flag or there will be two defaults in the destination folder */
			if( $old !== NULL AND $old->folder_id != $new->folder_id AND $old->default )
			{
				\IPS\Db::i()->update( 'cms_pages', array( 'page_default' => 0 ), array( 'page_id=?', $new->id ) );

				\IPS\cms\Pages\Page::buildPageUrlStore();
			}
		}
		catch( \UnderflowException $e )
		{
			/* No default found in destination folder, make this the default */
			\IPS\Db::i()->update( 'cms_pages', array( 'page_default' => 1 ), array( 'page_id=?', $new->id ) );

			\IPS\cms\Pages\Page::buildPageUrlStore();
		}
		
		/* If page filename changes or the folder ID changes, we need to clear front navigation cache*/
		if( $old !== NULL AND ( $old->folder_id != $new->folder_id OR $old->seo_name != $new->seo_name ) )
		{
			unset( \IPS\Data\Store::i()->pages_page_urls );
		}

		parent::_afterSave( $old, $new, $lastUsedTab );
	}
}