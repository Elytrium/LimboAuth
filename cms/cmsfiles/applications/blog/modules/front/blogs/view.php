<?php
/**
 * @brief		View Blog Entry Controller
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
 * View Blog Controller
 */
class _view extends \IPS\Helpers\CoverPhoto\Controller
{
	
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\blog\Blog';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		/* Load blog and check permissions */
		try
		{
			$this->blog	= \IPS\blog\Blog::loadAndCheckPerms( \IPS\Request::i()->id, 'read' );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2B201/1', 404, '' );
		}

		if ( $this->blog->cover_photo )
		{
			\IPS\Output::i()->metaTags['og:image'] = \IPS\File::get( $this->_coverPhotoStorageExtension(), $this->blog->cover_photo )->url;
		}
		
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$this->blog->clubCheckRules();
		
		/* Build table */
		$tableUrl = $this->blog->url();
		$where = array();
		if ( !\in_array( $this->blog->id, array_keys( \IPS\blog\Blog::loadByOwner( \IPS\Member::loggedIn() ) ) ) AND !\IPS\blog\Entry::canViewHiddenItems( \IPS\Member::loggedIn(), $this->blog ) )
		{
			if ( !( $club = $this->blog->club() AND \in_array( $club->memberStatus( \IPS\Member::loggedIn() ), array( \IPS\Member\Club::STATUS_LEADER, \IPS\Member\Club::STATUS_MODERATOR ) ) ) )
			{
				$where[] = array( "entry_status!='draft'" );
			}
		}

		/* Are we limiting by category? */
		try
		{
			$category = \IPS\blog\Entry\Category::load( \IPS\Request::i()->cat );
			$tableUrl = $tableUrl->setQueryString( [ 'cat' => $category->id ] );
		}
		catch( \OutOfRangeException $e )
		{
			$category = NULL;
		}

		if( $category )
		{
			$where[] = array( "entry_category_id=?", $category->id );
		}
		
		$table = new \IPS\Helpers\Table\Content( 'IPS\blog\Entry', $tableUrl, $where, $this->blog );
		
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'view' ), 'blogTable' );
		if ( \IPS\Settings::i()->blog_allow_grid )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'grid.css', 'blog', 'front' ) );
			$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'view' ), 'rowsGrid' );
		}
		else
		{
			$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'view' ), 'rows' );
		}
		
		$table->title = \IPS\Member::loggedIn()->language()->addToStack('entries_in_this_blog');
        $table->sortBy = \IPS\Request::i()->sortby ?: 'date';

		/* Update views */
		if ( !\IPS\Request::i()->isAjax() )
		{
			$this->blog->updateViews();
		}

		/* Online User Location */
		if( !$this->blog->social_group )
		{
			\IPS\Session::i()->setLocation( $this->blog->url(), array(), 'loc_blog_viewing_blog', array( "blogs_blog_{$this->blog->id}" => TRUE ) );
		}

		if( \IPS\Settings::i()->blog_allow_rss and $this->blog->settings['allowrss'] )
		{
			\IPS\Output::i()->rssFeeds['blog_rss_title'] = \IPS\Http\Url::internal( "app=blog&module=blogs&controller=view&id={$this->blog->_id}", 'front', 'blogs_rss', array( $this->blog->seo_name ) );

			if ( \IPS\Member::loggedIn()->member_id )
			{
				$key = \IPS\Member::loggedIn()->getUniqueMemberHash();

				\IPS\Output::i()->rssFeeds['blog_rss_title'] = \IPS\Output::i()->rssFeeds['blog_rss_title']->setQueryString( array( 'member' => \IPS\Member::loggedIn()->member_id , 'key' => $key ) );
			}
		}

		/* Add JSON-ld */
		\IPS\Output::i()->jsonLd['blog']	= array(
			'@context'		=> "http://schema.org",
			'@type'			=> "Blog",
			'url'			=> (string) $this->blog->url(),
			'name'			=> $this->blog->_title,
			'description'	=> $this->blog->member_id ? $this->blog->desc : \IPS\Member::loggedIn()->language()->addToStack( \IPS\blog\Blog::$titleLangPrefix . $this->blog->_id . \IPS\blog\Blog::$descriptionLangSuffix, TRUE, array( 'striptags' => TRUE, 'escape' => TRUE ) ),
			'commentCount'	=> $this->blog->_comments,
			'interactionStatistic'	=> array(
				array(
					'@type'					=> 'InteractionCounter',
					'interactionType'		=> "http://schema.org/ViewAction",
					'userInteractionCount'	=> $this->blog->num_views
				),
				array(
					'@type'					=> 'InteractionCounter',
					'interactionType'		=> "http://schema.org/FollowAction",
					'userInteractionCount'	=> \IPS\blog\Entry::containerFollowerCount( $this->blog )
				),
				array(
					'@type'					=> 'InteractionCounter',
					'interactionType'		=> "http://schema.org/CommentAction",
					'userInteractionCount'	=> $this->blog->_comments
				),
				array(
					'@type'					=> 'InteractionCounter',
					'interactionType'		=> "http://schema.org/WriteAction",
					'userInteractionCount'	=> $this->blog->_items
				)
			)
		);

		if( $this->blog->coverPhoto()->file )
		{
			\IPS\Output::i()->jsonLd['blog']['image'] = (string) $this->blog->coverPhoto()->file->url;
		}

		if( $this->blog->member_id )
		{
			\IPS\Output::i()->jsonLd['blog']['author'] = array(
				'@type'		=> 'Person',
				'name'		=> \IPS\Member::load( $this->blog->member_id )->name,
				'url'		=> (string) \IPS\Member::load( $this->blog->member_id )->url(),
				'image'		=> \IPS\Member::load( $this->blog->member_id )->get_photo( TRUE, TRUE )
			);
		}

		if( \IPS\Settings::i()->blog_enable_sidebar and $this->blog->sidebar )
		{
			\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate('view')->blogSidebar( $this->blog->sidebar );
		}

		\IPS\Output::i()->breadcrumb = array();
		if ( $club = $this->blog->club() )
		{
			\IPS\core\FrontNavigation::$clubTabActive = TRUE;
			\IPS\Output::i()->breadcrumb = array();
			\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs') );
			\IPS\Output::i()->breadcrumb[] = array( $club->url(), $club->name );

		}
		else
		{
			\IPS\Output::i()->breadcrumb['module'] = array( \IPS\Http\Url::internal( 'app=blog', 'front', 'blogs' ), \IPS\Member::loggedIn()->language()->addToStack( '__app_blog' ) );
		}


		try
		{
		    	foreach( $this->blog->category()->parents() as $parent )
			{
				\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
			}
			\IPS\Output::i()->breadcrumb[] = array( $this->blog->category()->url(), $this->blog->category()->_title );
		} 
		catch (\OutOfRangeException $e) {}
		
		\IPS\Output::i()->breadcrumb[] = array( $this->blog->url(), $this->blog->_title );

		/* Categories */
		$categories = \IPS\blog\Entry\Category::roots( NULL, NULL, array( 'entry_category_blog_id=?', $this->blog->id ) );

		/* Set default search option */
		\IPS\Output::i()->defaultSearchOption = array( 'blog_entry', 'blog_entry_pl' );

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_browse.js', 'blog', 'front' ) );
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'view' )->view( $this->blog, (string) $table, $category );
		\IPS\Output::i()->contextualSearchOptions[ \IPS\Member::loggedIn()->language()->addToStack( 'search_contextual_item_blogs' ) ] = array( 'type' => 'blog_entry', 'nodes' => $this->blog->_id );
	}
	
	/**
	 * Edit blog
	 *
	 * @return	void
	 */
	protected function editBlog()
	{
		if( !$this->blog->canEdit() OR $this->blog->groupblog_ids or $this->blog->club_id )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2B201/2', 403, '' );
		}
	
		\IPS\Session::i()->csrfCheck();
	
		/* Build form */
		$form = new \IPS\Helpers\Form( 'form', 'save', $this->blog->url()->setQueryString( array( 'do' => 'editBlog' ) )->csrf() );
		$form->class .= 'ipsForm_vertical';
	
		$this->blog->form( $form, TRUE );
	
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			if( !$values['blog_name'] )
			{
				$form->elements['']['blog_name']->error	= \IPS\Member::loggedIn()->language()->addToStack('form_required');
	
				\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
				return;
			}
	
			$this->blog->saveForm( $this->blog->formatFormValues( $values ) );
				
			\IPS\Output::i()->redirect( $this->blog->url() );
		}
	
		/* Display form */
		\IPS\Output::i()->title = $this->blog->_title;
		\IPS\Output::i()->breadcrumb[] = array( $this->blog->url(), $this->blog->_title );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_view.js', 'blog', 'front' ) );
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
	
	/**
	 * Delete Blog
	 *
	 * @return	void
	 */
	protected function deleteBlog()
	{
		\IPS\Session::i()->csrfCheck();
		
		if( !$this->blog->canDelete() or $this->blog->club_id )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2B201/3', 403, '' );
		}

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		$this->blog->disabled = TRUE;
		$this->blog->save();

		/* Log */
		\IPS\Session::i()->modLog( 'modlog__action_delete_blog', array( $this->blog->name => FALSE ) );

		\IPS\Task::queue( 'core', 'DeleteOrMoveContent', array( 'class' => 'IPS\blog\Blog', 'id' => $this->blog->id, 'deleteWhenDone' => TRUE ) );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=blog&module=blogs&controller=browse', 'front', 'blogs' ) );
	}
	
	/**
	 * Pin/Unpin Blog
	 *
	 * @return	void
	 */
	protected function changePin()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Do we have permission */
		if ( ( $this->blog->pinned and !\IPS\Member::loggedIn()->modPermission('can_unpin_content') ) or ( !$this->blog->pinned and !\IPS\Member::loggedIn()->modPermission('can_pin_content') ) or $this->blog->club_id )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2B201/4', 403, '' );
		}
		
		$this->blog->pinned = $this->blog->pinned ? FALSE : TRUE;		
		$this->blog->save();
		
		/* Respond or redirect */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->redirect( $this->blog->url() );
		}
	}

	/**
	 * RSS imports
	 *
	 * @return	void
	 */
	protected function rssImport()
	{
		if( !\IPS\Settings::i()->blog_allow_rssimport )
		{
			\IPS\Output::i()->error( 'rss_import_disabled', '2B201/7', 403, '' );
		}
		
		if( !$this->blog->canEdit() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2B201/6', 403, '' );
		}
		
		/* Check for existing feed */
		try
		{
			$existing = \IPS\Db::i()->select( '*', 'core_rss_import', array( 'rss_import_class=? AND rss_import_node_id=?', 'IPS\\blog\\Entry', $this->blog->id ) )->first();
			$feed = \IPS\core\Rss\Import::constructFromData( $existing );
		}
		catch ( \UnderflowException $e )
		{
			$feed = new \IPS\core\Rss\Import;
			$feed->class = 'IPS\\blog\\Entry';
		}

		$form = new \IPS\Helpers\Form;

		$form->add( new \IPS\Helpers\Form\YesNo( 'blog_enable_rss_import', $feed->url ? TRUE : FALSE, FALSE, array( 'togglesOn' => array( 'blog_rss_import_url', 'blog_rss_import_auth_user', 'blog_rss_import_auth_pass', 'blog_rss_import_show_link', 'blog_rss_import_tags' ) ) ) );

		$form->add( new \IPS\Helpers\Form\Url( 'blog_rss_import_url', $feed ? $feed->url : NULL, TRUE, array(), NULL, NULL, NULL, 'blog_rss_import_url' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'blog_rss_import_auth_user', $feed ? $feed->auth_user : NULL, FALSE, array(), NULL, NULL, NULL, 'blog_rss_import_auth_user' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'blog_rss_import_auth_pass', $feed ? $feed->auth_pass : NULL, FALSE, array(), NULL, NULL, NULL, 'blog_rss_import_auth_pass' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'blog_rss_import_show_link', $feed->showlink ?: \IPS\Member::loggedIn()->language()->addToStack('blog_rss_import_show_link_default' ), FALSE, array(), NULL, NULL, NULL, 'blog_rss_import_show_link' ) );

		$options = array( 'autocomplete' => array( 'unique' => TRUE, 'minimized' => FALSE, 'source' => \IPS\Content\Item::definedTags( $this->blog ), 'freeChoice' => ( \IPS\Settings::i()->tags_open_system ? TRUE : FALSE ) ) );
		if ( \IPS\Settings::i()->tags_force_lower )
		{
			$options['autocomplete']['forceLower'] = TRUE;
		}
		if ( \IPS\Settings::i()->tags_min )
		{
			$options['autocomplete']['minItems'] = \IPS\Settings::i()->tags_min;
		}
		if ( \IPS\Settings::i()->tags_max )
		{
			$options['autocomplete']['maxItems'] = \IPS\Settings::i()->tags_max;
		}
		if ( \IPS\Settings::i()->tags_len_min )
		{
			$options['autocomplete']['minLength'] = \IPS\Settings::i()->tags_len_min;
		}
		if ( \IPS\Settings::i()->tags_len_max )
		{
			$options['autocomplete']['maxLength'] = \IPS\Settings::i()->tags_len_max;
		}
		if ( \IPS\Settings::i()->tags_clean )
		{
			$options['autocomplete']['filterProfanity'] = TRUE;
		}
		if ( \IPS\Settings::i()->tags_alphabetical )
		{
			$options['autocomplete']['alphabetical'] = TRUE;
		}
			
		$options['autocomplete']['prefix'] = \IPS\Content\Item::canPrefix( NULL, $this->blog );
		$options['autocomplete']['disallowedCharacters'] = array( '#' ); // @todo Pending \IPS\Http\Url rework, hashes cannot be used in URLs

		/* Language strings for tags description */
		if ( \IPS\Settings::i()->tags_open_system )
		{
			$extralang = array();

			if ( \IPS\Settings::i()->tags_min && \IPS\Settings::i()->tags_max )
			{
				$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_min_max', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->tags_max ), 'pluralize' => array( \IPS\Settings::i()->tags_min ) ) );
			}
			else if( \IPS\Settings::i()->tags_min )
			{
				$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_min', FALSE, array( 'pluralize' => array( \IPS\Settings::i()->tags_min ) ) );
			}
			else if( \IPS\Settings::i()->tags_min )
			{
				$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_max', FALSE, array( 'pluralize' => array( \IPS\Settings::i()->tags_max ) ) );
			}

			if( \IPS\Settings::i()->tags_len_min && \IPS\Settings::i()->tags_len_max )
			{
				$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_len_min_max', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->tags_len_min, \IPS\Settings::i()->tags_len_max ) ) );
			}
			else if( \IPS\Settings::i()->tags_len_min )
			{
				$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_len_min', FALSE, array( 'pluralize' => array( \IPS\Settings::i()->tags_len_min ) ) );
			}
			else if( \IPS\Settings::i()->tags_len_max )
			{
				$extralang[] = \IPS\Member::loggedIn()->language()->addToStack( 'tags_desc_len_max', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->tags_len_max ) ) );
			}

			$options['autocomplete']['desc'] = \IPS\Member::loggedIn()->language()->addToStack('tags_desc') . ( ( \count( $extralang ) ) ? '<br>' . implode( ' ', $extralang ) : '' );
		}
		
		$form->add( new \IPS\Helpers\Form\Text( 'blog_rss_import_tags', $feed ? json_decode( $feed->tags, TRUE ) : array(), \IPS\Settings::i()->tags_min and \IPS\Settings::i()->tags_min_req, $options, NULL, NULL, NULL, 'blog_rss_import_tags' ) );
		
		if ( $values = $form->values() )
		{
			if( $values['blog_enable_rss_import'] )
			{
				try
				{
					$request = $values['blog_rss_import_url']->request();

					if ( $values['blog_rss_import_auth_user'] or $values['blog_rss_import_auth_pass'] )
					{
						$request = $request->login( $values['blog_rss_import_auth_user'], $values['blog_rss_import_auth_pass'] );
					}

					$response = $request->get();

					if ( $response->httpResponseCode == 401 )
					{
						$form->error = \IPS\Member::loggedIn()->language()->addToStack( 'rss_import_auth' );
					}

					$response = $response->decodeXml();
					
					if ( !( $response instanceof \IPS\Xml\Rss ) and !( $response instanceof \IPS\Xml\Atom ) )
					{
						$form->error = \IPS\Member::loggedIn()->language()->addToStack( 'rss_import_invalid' );
					}

					if( !$form->error )
					{
						$feed->node_id = $this->blog->id;
						$feed->url = $values['blog_rss_import_url'];
						$feed->showlink = $values['blog_rss_import_show_link'];
						$feed->auth_user = $values['blog_rss_import_auth_user'];
						$feed->auth_pass = $values['blog_rss_import_auth_pass'];
						$feed->member = $this->blog->owner() ? $this->blog->owner()->member_id : \IPS\Member::loggedIn()->member_id;
						$feed->settings = json_encode( array( 'tags' => $values['blog_rss_import_tags'] ) );
						$feed->save();
						
						try
						{
							$feed->run();
						}
						catch ( \Exception $e ) { }
						
						/* Redirect */
						\IPS\Output::i()->redirect( $this->blog->url() );
					}

				}
				catch ( \IPS\Http\Request\Exception $e )
				{
					$form->error = \IPS\Member::loggedIn()->language()->addToStack( 'form_url_bad' );
				}
				catch ( \Exception $e )
				{
					$form->error = \IPS\Member::loggedIn()->language()->addToStack( 'rss_import_invalid' );
				}
			}
			else
			{
				\IPS\Db::i()->delete( 'core_rss_import', array( 'rss_import_class=? AND rss_import_node_id=?', 'IPS\\blog\\Entry', $this->blog->id ) );

				/* Redirect */
				\IPS\Output::i()->redirect( $this->blog->url() );
			}
		}
				
		/* Display */
		\IPS\Output::i()->output = $form->error ? $form : \IPS\Theme::i()->getTemplate( 'view', 'blog', 'front' )->rssImport( $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) ) );
	}
	
	/**
	 * Get Cover Photo Storage Extension
	 *
	 * @return	string
	 */
	protected function _coverPhotoStorageExtension()
	{
		return 'blog_Blogs';
	}
	
	/**
	 * Set Cover Photo
	 *
	 * @param	\IPS\Helpers\CoverPhoto	$photo	New Photo
	 * @return	void
	 */
	protected function _coverPhotoSet( \IPS\Helpers\CoverPhoto $photo )
	{
		$this->blog->cover_photo = (string) $photo->file;
		$this->blog->cover_photo_offset = (int) $photo->offset;
		$this->blog->save();
	}
	
	/**
	 * Get Cover Photo
	 *
	 * @return	\IPS\Helpers\CoverPhoto
	 */
	protected function _coverPhotoGet()
	{
		return $this->blog->coverPhoto();
	}
	
	/**
	 * Return categories as JSON
	 *
	 * @return	void
	 */
	protected function categoriesJson()
	{
		$cats = array();
		foreach( \IPS\blog\Entry\Category::roots( NULL, NULL, array( 'entry_category_blog_id=?', $this->blog->id ) ) as $meow )
		{
			$cats[] = array(
				'id'   => $meow->id,
				'name' => $meow->name
			);
		}
			
		\IPS\Output::i()->json( array( 'categories' => $cats ) );
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manageCategories()
	{
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_view.js', 'blog', 'front' ) );

		if( !$this->blog->canEdit()  )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2B201/A', 403, '' );
		}

		$form = new \IPS\Helpers\Form;
		$form->addHtml( \IPS\Theme::i()->getTemplate( 'view', 'blog', 'front' )->manageCategories( $this->blog ) );
		$form->hiddenValues['submitted'] = 1;

		if( $values = $form->values() )
		{
			\IPS\Output::i()->redirect( $this->blog->url() );
		}

		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('blog_manage_entry_categories');
		\IPS\Output::i()->output = $form->error ? $form : \IPS\Theme::i()->getTemplate( 'view', 'blog', 'front' )->rssImport( $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) ) );
	}

	/**
	 * Edit a category name
	 *
	 * @return string
	 */
	protected function editCategoryName()
	{
		\IPS\Session::i()->csrfCheck();

		if( !$this->blog->canEdit() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2B201/C', 403, '' );
		}
		
		if( ! \IPS\Request::i()->name )
		{
			\IPS\Output::i()->error( 'blog_error_missing_name', '1B201/B', 403, '' );
		}
			
		/* New category? */
		if ( \IPS\Request::i()->cat === 'new' )
		{
			$newCategory = new \IPS\blog\Entry\Category;
			$newCategory->name = \IPS\Request::i()->name;
			$newCategory->seo_name = \IPS\Http\Url\Friendly::seoTitle( \IPS\Request::i()->name );

			$newCategory->blog_id = $this->blog->id;
			$newCategory->save();
		}
		else
		{
			try
			{
				$category = \IPS\blog\Entry\Category::load( \IPS\Request::i()->cat );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'blog_error_not_find_category', '2B201/D', 403, '' );
			}
	
			if( $category->blog_id !== $this->blog->id )
			{
				\IPS\Output::i()->error( 'blog_error_not_find_category', '2B201/E', 403, '' );
			}
			
			$category->name = \IPS\Request::i()->name;
			$category->save();
		}
		
		\IPS\Output::i()->json( 'OK' );
	}
	
	/**
	 * Delete Category
	 *
	 * @return	void
	 */
	protected function deleteCategory()
	{
		\IPS\Session::i()->csrfCheck();

		if( !$this->blog->canEdit()  )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2B201/8', 403, '' );
		}

		try
		{
			$category = \IPS\blog\Entry\Category::load( \IPS\Request::i()->cat );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->redirect( $this->blog->url() );
		}

		if( $category->blog_id !== $this->blog->id )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2B201/9', 403, '' );
		}

		/* Update Entries */
		\IPS\Db::i()->update( 'blog_entries', array( 'entry_category_id' => NULL ), array( 'entry_category_id=?', $category->id ) );

		$category->delete();

		/* Redirect */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->redirect( $this->blog->url(), 'deleted' );
		}
	}

	/**
	 * Reorder blog entry categories
	 *
	 * @return	void
	 */
	public function categoriesReorder()
	{
		\IPS\Session::i()->csrfCheck();

		if( !$this->blog->canEdit() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2B201/F', 403, '' );
		}

		/* Set order */
		$position = 1;

		if( !\IPS\Request::i()->isAjax() )
		{
			\IPS\Request::i()->ajax_order = explode( ',', \IPS\Request::i()->ajax_order );
		}

		foreach( \IPS\Request::i()->ajax_order as $category )
		{
			$node = \IPS\blog\Entry\Category::load( $category );

			/* No funny business trying to reorder another blog's categories */
			if( $node->blog_id !== $this->blog->id )
			{
				continue;
			}

			$node->position = $position;
			$node->save();

			$position++;
		}

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'ok' );
		}
		else
		{
			\IPS\Output::i()->redirect( $this->blog->url(), 'saved' );
		}
	}
}