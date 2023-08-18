<?php
/**
 * @brief		Record View
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		17 Apr 2014
 */

namespace IPS\cms\modules\front\database;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Record View
 */
class _record extends \IPS\Content\Controller
{
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = NULL;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url|NULL	$url		The base URL for this controller or NULL to calculate automatically
	 * @return	void
	 */
	public function __construct( $url=NULL )
	{
		static::$contentModel = 'IPS\cms\Records' . \IPS\cms\Databases\Dispatcher::i()->databaseId;
		
		parent::__construct( \IPS\cms\Databases\Dispatcher::i()->url );
	}
	
	/**
	 * View Record
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Load record */
		try
		{
			$record = parent::manage();
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2T252/1', 403, '' );
		}
		
		if ( $record === NULL )
		{
			\IPS\Output::i()->error( 'node_error', '2T252/2', 404, '' );
		}

		if ( \IPS\Request::i()->view )
		{
			$this->_doViewCheck();
		}

		/* Sort out comments and reviews */
		$tabs  = $record->commentReviewTabs();
		$_tabs = array_keys( $tabs );
		$tab   = isset( \IPS\Request::i()->tab ) ? \IPS\Request::i()->tab : array_shift( $_tabs );
		$activeTabContents = $record->commentReviews( $tab );
		$comments = \count( $tabs ) ? \IPS\cms\Theme::i()->getTemplate( $record->container()->_template_display, 'cms', 'database' )->commentsAndReviewsTabs( \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $tab, $activeTabContents, $record->url(), 'tab', FALSE, TRUE ), md5( $record->url() ) ) : NULL;

		if ( \IPS\Request::i()->isAjax() and !isset( \IPS\Request::i()->rating_submitted ) and ( isset( \IPS\Request::i()->page ) OR isset( \IPS\Request::i()->tab ) ) and $activeTabContents )
		{
			\IPS\Output::i()->sendOutput( $activeTabContents, 200, 'text/html' );
		}

		$class = '\IPS\cms\Categories' . $record::$customDatabaseId;
		$category = $class::load( $record->category_id );
		$FieldsClass  = '\\IPS\\cms\\Fields'  . $record::$customDatabaseId;
		$updateFields = $FieldsClass::fields( $record->fieldValues(), 'edit', $record->container(), $FieldsClass::FIELD_DISPLAY_COMMENTFORM, $record );
		$form         = null;
		
		/* We need edit permission to change the record */
		if ( \count( $updateFields ) and $record->canEdit() )
		{
			$form = new \IPS\Helpers\Form( 'update_record', 'update', $record->url()->setQueryString( array( 'd' => $record::$customDatabaseId ) ) );
			$form->class = 'ipsForm_vertical';

			$hasAdditionalFields = false;
			foreach( $updateFields as $id => $field )
			{
				$form->add( $field );
				if( $id != $record::database()->field_title AND $id != $record::database()->field_content )
				{
					$hasAdditionalFields = true;
				}
			}

			/* The comment is only added for fields that are NOT the title/content. So don't show this checkbox if the only field available is one of those. */
			if( $hasAdditionalFields AND $record->canComment() )
			{
				$form->add( new \IPS\Helpers\Form\Checkbox( 'record_display_field_change', TRUE, FALSE ) );
			}
			
			if ( $values = $form->values() )
			{
				/* Custom fields */
				$customValues = array();
				$fieldsClass  = 'IPS\cms\Fields' . $record::$customDatabaseId;
				
				foreach( $values as $k => $v )
				{
					if ( mb_substr( $k, 0, 14 ) === 'content_field_' )
					{
						$customValues[ mb_substr( $k, 8 ) ] = $v;
					}
				}

				if ( \count( $customValues ) )
				{
					if ( isset( $values['record_display_field_change'] ) AND $values['record_display_field_change'] )
					{
						$record->addCommentWhenFiltersChanged( $values );
					}
					else
					{
						/* Set excludes for custom field updates. We'll send the notifications later, once we know the new content of the fields. */
						$record->setFieldQuoteAndMentionExcludes();
					}

					foreach( $fieldsClass::fields( $customValues, 'edit', $record->category_id ? $category : NULL, $FieldsClass::FIELD_DISPLAY_COMMENTFORM ) as $key => $field )
					{
						$key = 'field_' . $key;
						$record->$key = $field::stringValue( isset( $values[ $field->name ] ) ? $values[ $field->name ] : NULL );
					}
					
					/* Send custom field update notifications */
					if ( !isset( $values['record_display_field_change'] ) OR !$values['record_display_field_change'] )
					{
						$record->sendFieldQuoteAndMentionNotifications();
					}

					$record->save();
					$record->processAfterEdit( $values );

					$fieldObjects = $fieldsClass::data( NULL, $record->category_id ? $category : NULL );

					foreach( $fieldObjects as $id => $row )
					{
						if ( $row->type == 'Item' )
						{
							$record->processItemFieldData( $row );
						}
					}
					\IPS\Output::i()->redirect( $record->url() );
				}
			}
		}
		
		if ( $record->record_meta_keywords )
		{
			\IPS\Output::i()->metaTags['keywords'] = $record->record_meta_keywords;
		}
		
		if ( $record->record_meta_description )
		{
			\IPS\Output::i()->metaTags['description'] = $record->record_meta_description;
			\IPS\Output::i()->metaTags['og:description'] = $record->record_meta_description;
		}

		/* Set record URL as canonical tag */
		if ( $record::database()->canonical_flag == 1 and ( isset( \IPS\Request::i()->page ) and \IPS\Request::i()->page > 1 ) )
		{
			\IPS\Output::i()->linkTags['canonical'] = $record->url()->setPage( 'page', \IPS\Request::i()->page );
		}
		else
		{
			\IPS\Output::i()->linkTags['canonical'] = $record->url();
		}

		/* Update location */
		if( $record->database()->use_categories )
		{
			\IPS\Session::i()->setLocation( $record->url(), $record->onlineListPermissions(), 'loc_cms_viewing_db_record', array( $record->_title => FALSE, 'content_db_' . $record->database()->id => TRUE ,'content_cat_name_' . $category->id => TRUE ) );
		}
		else
		{
			\IPS\Session::i()->setLocation( $record->url(), $record->onlineListPermissions(), 'loc_cms_viewing_db_record_no_cats', array( $record->_title => FALSE, 'content_db_' . $record->database()->id => TRUE ) );
		}

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'records/record.css', 'cms', 'front' ) );

		/* Next unread */
		try
		{
			$nextUnread	= $record->containerHasUnread();
		}
		catch( \Exception $e )
		{
			$nextUnread	= NULL;
		}
		
		if ( $record->record_image )
		{
			\IPS\Output::i()->metaTags['og:image'] = (string) \IPS\File::get( 'cms_Records', $record->record_image )->url;
		}

		/* Add Json-LD */
		$jsonLdText = $record->truncated( TRUE, NULL );

		\IPS\Output::i()->jsonLd['article']	= array(
			'@context'		=> "http://schema.org",
			'@type'			=> "Article",
			'url'			=> (string) $record->url(),
			'discussionUrl'	=> (string) $record->url(),
			'mainEntityOfPage'	=> (string) $record->url(),
			'name'			=> $record->_title,
			'headline'		=> $record->_title,
			'text'			=> $jsonLdText,
			'articleBody'	=> $jsonLdText,
			'dateCreated'	=> \IPS\DateTime::ts( $record->record_saved )->format( \IPS\DateTime::ISO8601 ),
			'datePublished'	=> \IPS\DateTime::ts( $record->record_publish_date ?: $record->record_saved )->format( \IPS\DateTime::ISO8601 ),
			'dateModified'	=> \IPS\DateTime::ts( $record->record_edit_time ?: ( $record->record_publish_date ?: $record->record_saved ) )->format( \IPS\DateTime::ISO8601 ),
			'pageStart'		=> 1,
			'pageEnd'		=> $record->commentPageCount(),
			'author'		=> array(
				'@type'		=> 'Person',
				'name'		=> (string) \IPS\Member::load( $record->member_id )->name,
				'image'		=> (string) \IPS\Member::load( $record->member_id )->get_photo( TRUE, TRUE )
			),
			'publisher'		=> array(
				'@id' => \IPS\Settings::i()->base_url . '#organization',
				'member'	=> array(
					'@type'		=> "Person",
					'name'		=> \IPS\Member::load( $record->member_id )->name,
					'image'		=> (string) \IPS\Member::load( $record->member_id )->get_photo( TRUE, TRUE ),
				)
			),
			'interactionStatistic'	=> array(
				array(
					'@type'					=> 'InteractionCounter',
					'interactionType'		=> "http://schema.org/ViewAction",
					'userInteractionCount'	=> $record->record_views
				),
				array(
					'@type'					=> 'InteractionCounter',
					'interactionType'		=> "http://schema.org/FollowAction",
					'userInteractionCount'	=> $record::containerFollowerCount( $record->container() )
				),
			),
		);

		/* Do we have a real author? */
		if( $record->member_id )
		{
			\IPS\Output::i()->jsonLd['article']['author']['url']	= (string) \IPS\Member::load( $record->member_id )->url();
			\IPS\Output::i()->jsonLd['article']['publisher']['member']['url'] = (string) \IPS\Member::load( $record->member_id )->url();
		}

		$logo = NULL;
		if( \IPS\Theme::i()->logo['front'] )
		{
			try
			{
				$logo = \IPS\File::get( 'core_Theme', \IPS\Theme::i()->logo['front']['url'] )->url;
			}
				/* File doesn't exist */
			catch ( \RuntimeException $e ){}
			catch ( \DomainException $e ){}
		}
		\IPS\Output::i()->jsonLd['article']['publisher']['logo'] = array(
			'@type'		=> 'ImageObject',
			'url'		=> $logo ? (string) $logo : (string) \IPS\Member::load( $record->member_id )->get_photo( TRUE, TRUE ),
		);

		/* Image is required */
		if( $record->record_image )
		{
			try
			{
				$imageObj	= \IPS\File::get( 'cms_Records', $record->record_image );

				\IPS\Output::i()->jsonLd['article']['image'] = array(
					'@type'		=> 'ImageObject',
					'url'		=> (string) $imageObj->url
				);
			}
			/* File doesn't exist */
			catch ( \RuntimeException $e ){}
			catch ( \DomainException $e ){}
		}
		else
		{
			$photoVars = explode( 'x', \IPS\THUMBNAIL_SIZE );
			
			\IPS\Output::i()->jsonLd['article']['image'] = array(
				'@type'		=> 'ImageObject',
				'url'		=> \IPS\Member::load( $record->member_id )->get_photo( TRUE, TRUE ),
				'width'		=> $photoVars[0],
				'height'	=> $photoVars[1]
			);
		}

		if( $record::database()->options['reviews'] or $record->container()->allow_rating )
		{
			\IPS\Output::i()->jsonLd['article']['interactionStatistic'][]	= array(
				'@type'					=> 'InteractionCounter',
				'interactionType'		=> "http://schema.org/ReviewAction",
				'userInteractionCount'	=> $record->record_reviews
			);
		}

		if( $record::database()->options['comments'] )
		{
			\IPS\Output::i()->jsonLd['article']['commentCount'] = $record->record_comments;
			\IPS\Output::i()->jsonLd['article']['interactionStatistic'][]	= array(
				'@type'					=> 'InteractionCounter',
				'interactionType'		=> "http://schema.org/CommentAction",
				'userInteractionCount'	=> $record->record_comments
			);
		}

		/* Set default search to this record */
		if ( ! $record::database()->search )
		{
			\IPS\Output::i()->defaultSearchOption = array( 'all', 'search_everything' );
		}
		else
		{
			$type = mb_strtolower( str_replace( '\\', '_', mb_substr( \get_class( $record ), 4 ) ) );
			\IPS\Output::i()->defaultSearchOption = array( $type, "{$type}_pl" );
			
			\IPS\Output::i()->contextualSearchOptions = array();
			\IPS\Output::i()->contextualSearchOptions[ \IPS\Member::loggedIn()->language()->addToStack( 'search_contextual_item', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $record::$title ) ) ) ) ] = array( 'type' => $type, 'item' => $record->_id );
	
			try
			{
				$container = $record->container();
				\IPS\Output::i()->contextualSearchOptions[ \IPS\Member::loggedIn()->language()->addToStack( 'search_contextual_item_categories' ) ] = array( 'type' => mb_strtolower( str_replace( '\\', '_', mb_substr( \get_class( $record ), 4 ) ) ), 'nodes' => $container->_id );
			}
			catch ( \BadMethodCallException $e ) { }
		}

		\IPS\cms\Databases\Dispatcher::i()->output .= \IPS\cms\Theme::i()->getTemplate( $record->container()->_template_display, 'cms', 'database' )->record( $record, $comments, $form, $nextUnread );
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
		$database = \IPS\cms\Databases::load( \IPS\cms\Databases\Dispatcher::i()->databaseId );
		if ( $database->use_categories )
		{
			parent::_setBreadcrumbAndTitle( $item, $link );
		}
		else
		{
			\IPS\Output::i()->breadcrumb[] = array( $link ? $item->url() : NULL, $item->mapped('title') );

			$title = ( isset( \IPS\Request::i()->page ) and \IPS\Request::i()->page > 1 ) ? \IPS\Member::loggedIn()->language()->addToStack( 'title_with_page_number', FALSE, array( 'sprintf' => array( $item->mapped('title') . ' - ' . $database->pageTitle(), \IPS\Request::i()->page ) ) ) : $item->mapped('title') . ' - ' . $database->pageTitle();
			\IPS\Output::i()->title = $title;
		}
	}

	/**
	 * View check
	 *
	 * @return	void
	 */
	protected function _doViewCheck()
	{
		try
		{
			$class	= static::$contentModel;
			$topic	= $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			switch( \IPS\Request::i()->view )
			{
				case 'getnewpost':
					\IPS\Output::i()->redirect( $topic->url( 'getNewComment' ) );
				break;
				
				case 'getlastpost':
					\IPS\Output::i()->redirect( $topic->url( 'getLastComment' ) );
				break;
			}
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2F173/F', 403, '' );
		}
	}
	
	/**
	 * Revisions
	 *
	 * @return	void
	 */
	protected function revisions()
	{
		$recordClass  = '\IPS\cms\Records' . \IPS\cms\Databases\Dispatcher::i()->databaseId;
		
		try
		{
			$record   = $recordClass::loadAndCheckPerms( \IPS\Request::i()->id );
			$category = $record->container();
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'module_no_permission', '2T252/4', 403, '' );
		}
		
		if ( ! $record->canManageRevisions() )
		{
			\IPS\Output::i()->error( 'module_no_permission', '2T252/5', 403, '' );
		}

		$title = \IPS\Member::loggedIn()->language()->addToStack('content_revision_record_title', FALSE, array( 'sprintf' => array( $record->_title ) ) );
		
		$table = new \IPS\Helpers\Table\Db( 'cms_database_revisions', $record->url('revisions'), array( 'revision_database_id=? and revision_record_id=?', $record::$customDatabaseId, $record->_id ) );
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'revisions', 'cms', 'front' ), 'table' );
		$table->rowsTemplate  = array( \IPS\Theme::i()->getTemplate( 'revisions', 'cms', 'front' ), 'rows' );
		$table->title = $title;
		$table->include = array( 'revision_id', 'revision_date', 'revision_data', 'revision_member_id' );
		$table->mainColumn = 'revision_date';
		$table->sortBy = $table->sortBy ?: 'revision_date';
		$table->sortDirection = $table->sortDirection ?: 'desc';
		
		/* Parsers */
		$table->parsers = array(
				'revision_member_id' => function( $val )
				{
					return \IPS\Member::load( $val );
				},
				'revision_date' => function( $val )
				{
					return \IPS\DateTime::ts( $val )->relative();
				},
				'revision_data' => function( $val, $row ) use ( $record )
				{
					return \IPS\cms\Records\Revisions::load( $row['revision_id'] )->getDiffHtmlTables( $record::$customDatabaseId, $record, true );
				}
		);

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/diff.css', 'core', 'admin' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'codemirror/diff_match_patch.js', 'core', 'interface' ) );
		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_records.js', 'cms' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'codemirror/codemirror.js', 'core', 'interface' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'codemirror/codemirror.css', 'core', 'interface' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'records/record.css', 'cms', 'front' ) );
		
		/* Output */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( $table ), 200, 'text/html' );
		}
		else
		{
			try
			{
				foreach( $category->parents() AS $parent )
				{
					\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
				}
				\IPS\Output::i()->breadcrumb[] = array( $category->url(), $category->_title );
			}
			catch( \Exception $e ) {}
			
			\IPS\Output::i()->breadcrumb[] = array( $record->url(), $record->_title );
			
			\IPS\Output::i()->title   					= $title;
			\IPS\cms\Databases\Dispatcher::i()->output .= (string) $table;
		}
	}
	
	/**
	 * Delete Revision
	 *
	 * @return	void
	 */
	protected function revisionDelete()
	{
		\IPS\Session::i()->csrfCheck();

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		$recordClass  = '\IPS\cms\Records' . \IPS\cms\Databases\Dispatcher::i()->databaseId;
	
		try
		{
			$record   = $recordClass::loadAndCheckPerms( \IPS\Request::i()->id );
			$category = $record->container();
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'module_no_permission', '2T252/6', 403, '' );
		}
	
		try
		{
			$revision = \IPS\cms\Records\Revisions::load( \IPS\Request::i()->revision_id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'module_no_permission', '2T252/7', 403, '' );
		}
	
		if ( ! $record->canManageRevisions() )
		{
			\IPS\Output::i()->error( 'module_no_permission', '2T252/8', 403, '' );
		}
		
		$revision->delete();
		
		if ( isset( \IPS\Request::i()->ajax ) )
		{
			\IPS\Output::i()->redirect( $record->url() );
		}
		else
		{
			\IPS\Output::i()->redirect( $record->url('revisions') );
		}
	}
	
	/**
	 * View Revision
	 *
	 * @return	void
	 */
	protected function revisionView()
	{
		$recordClass  = '\IPS\cms\Records' . \IPS\cms\Databases\Dispatcher::i()->databaseId;
	
		try
		{
			$record   = $recordClass::loadAndCheckPerms( \IPS\Request::i()->id );
			$category = $record->container();
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'module_no_permission', '2T252/9', 403, '' );
		}
		
		try
		{
			$revision = \IPS\cms\Records\Revisions::load( \IPS\Request::i()->revision_id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'module_no_permission', '2T252/A', 403, '' );
		}
	
		if ( ! $record->canManageRevisions() )
		{
			\IPS\Output::i()->error( 'module_no_permission', '2T252/B', 403, '' );
		}

		$title        = \IPS\Member::loggedIn()->language()->addToStack('content_revision_record_title', FALSE, array( 'sprintf' => array( $record->_title ) ) );
		$fieldsClass  = 'IPS\cms\Fields' .  $record::$customDatabaseId;
		$customFields = $fieldsClass::data( 'view', $category );
		$conflicts    = array();
		$form         = new \IPS\Helpers\Form( 'form', 'content_revision_restore' );

		/* Add a "cancel" button that will take you back to the previous page */
		array_unshift( $form->actionButtons, \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->button( 'cancel', 'link', $record->url()->setQueryString( array( 'do' => 'revisions', 'd' => $record::$customDatabaseId ) ), 'ipsButton ipsButton_link', array( 'tabindex' => '3', 'accesskey' => 'c' ) ) );

		/* Build up our data set */
		$conflicts = $revision->getDiffHtmlTables( $record::$customDatabaseId, $record, true );

		/* If there is only one change, then clicking restore naturally means to revert that single change, so we don't need a form */
		if( \count( $conflicts ) === 1 )
		{
			foreach( $conflicts as $conflict )
			{
				$form->hiddenValues[ 'conflict_' . $conflict['field']->id ] = 'old';
			}
		}
		/* Otherwise if multiple fields have changes to compare, let the admin decide what to do */
		else
		{
			foreach( $conflicts as $conflict )
			{
				$form->add( new \IPS\Helpers\Form\Radio( 'conflict_' . $conflict['field']->id, 'no', false, array( 'options' => array( 'old' => '', 'new' => '' ) ) ) );
			}
		}

		if ( $values = $form->values() )
		{
			foreach( $values as $k => $v )
			{
				if ( $v === 'old' )
				{
					$fieldId = mb_substr( $k, 9 );
					$key     = 'field_' . $fieldId;
					$record->$key = $revision->get( $key );
				}
				
				\IPS\Session::i()->modLog( 'modlog__content_revision_restored', array( $record->_title => FALSE, $revision->id => FALSE ) );
				
				$record->save();
				$revision->delete();
				
				\IPS\Output::i()->redirect( $record->url(), 'content_revision_restored' );
			}
		}
		
		try
		{
			foreach( $category->parents() AS $parent )
			{
				\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
			}
			\IPS\Output::i()->breadcrumb[] = array( $category->url(), $category->_title );
		}
		catch( \Exception $e ) {}
		
		\IPS\Output::i()->breadcrumb[] = array( $record->url(), $record->_title );
		\IPS\Output::i()->breadcrumb[] = array( $record->url()->setQueryString( array( 'do' => 'revisions', 'd' => $record::$customDatabaseId ) ), $title );
			
		\IPS\Output::i()->title   = $title;
		
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/diff.css', 'core', 'admin' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'codemirror/diff_match_patch.js', 'core', 'interface' ) );
		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_records.js', 'cms' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'codemirror/codemirror.js', 'core', 'interface' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'codemirror/codemirror.css', 'core', 'interface' ) );
			
		\IPS\cms\Databases\Dispatcher::i()->output   = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'revisions', 'cms' ), 'view' ), $record, $revision, $conflicts );
	}
	
	/**
	 * Edit Item
	 *
	 * @return	void
	 */
	protected function edit()
	{
		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_records.js', 'cms' ) );
		
		$recordClass  = '\IPS\cms\Records' . \IPS\cms\Databases\Dispatcher::i()->databaseId;
		$fieldsClass  = '\IPS\cms\Fields' . \IPS\cms\Databases\Dispatcher::i()->databaseId;
		$database     = \IPS\cms\Databases::load( \IPS\cms\Databases\Dispatcher::i()->databaseId );
		try
		{
			$record       = $recordClass::loadAndCheckPerms( \IPS\Request::i()->id );
			$category     = $record->container();
				
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'module_no_permission', '2T252/C', 403, '' );
		}
		
		$title        = \IPS\Member::loggedIn()->language()->addToStack( 'content_record_form_edit_record', FALSE, array( 'sprintf' => array( $record->_title ) ) );
		$formElements = $recordClass::formElements( $record, $category );

		// We check if the form has been submitted to prevent the user loosing their content
		if ( isset( \IPS\Request::i()->form_submitted ) )
		{
			if ( ! $record->couldEdit() )
			{
				\IPS\Output::i()->error( 'module_no_permission', '2T252/G', 403, '' );
			}
		}
		else
		{
			if ( ! $record->canEdit() )
			{
				\IPS\Output::i()->error( 'module_no_permission', '2T252/D', 403, '' );
			}
		}
		
		$form = new \IPS\Helpers\Form( 'form', isset( \IPS\Member::loggedIn()->language()->words[ $recordClass::$formLangPrefix . '_save' ] ) ? $recordClass::$formLangPrefix . '_save' : 'save' );
		$form->class = 'ipsForm_vertical';
			
		foreach( $formElements as $name => $field )
		{
			$form->add( $field );
		}
		
		$hasModOptions = FALSE;
		
		if ( $recordClass::modPermission( 'lock', NULL, $category ) or
			 $recordClass::modPermission( 'pin', NULL, $category ) or 
			 $record->canHide() or 
			 $recordClass::modPermission( 'feature', NULL, $category ) or
			 $fieldsClass::fixedFieldFormShow( 'record_allow_comments' ) or
			 $fieldsClass::fixedFieldFormShow( 'record_expiry_date' ) or
			 $fieldsClass::fixedFieldFormShow( 'record_comment_cutoff' ) or
			 \IPS\Member::loggedIn()->modPermission('can_content_edit_meta_tags') )
		{
			$hasModOptions = TRUE;
		}
		
		if ( $values = $form->values() )
		{
			$record->processForm( $values );
			$record->processAfterEdit( $values );

			if ( isset( $recordClass::$databaseColumnMap['date'] ) and isset( $values[ $recordClass::$formLangPrefix . 'date' ] ) )
			{
				$column = $recordClass::$databaseColumnMap['date'];

				if ( $values[ $recordClass::$formLangPrefix . 'date' ] instanceof \IPS\DateTime )
				{
					$record->$column = $values[ $recordClass::$formLangPrefix . 'date' ]->getTimestamp();
				}
			}

			$record->save();

			\IPS\Session::i()->modLog( 'modlog__item_edit', array( $record::$title => FALSE, $record->url()->__toString() => FALSE, $record::$title => TRUE, $record->mapped( 'title' ) => FALSE ), $record );

			\IPS\Output::i()->redirect( $record->url() );
		}
		
		\IPS\Output::i()->allowDefaultWidgets = FALSE;
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\cms\Pages\Page::$currentPage->getWidgets();
		\IPS\cms\Databases\Dispatcher::i()->output = $form->customTemplate( array( \IPS\cms\Theme::i()->getTemplate( $database->template_form, 'cms', 'database' ), 'recordForm' ), NULL, $category, $database, \IPS\cms\Pages\Page::$currentPage, $title, $hasModOptions );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( $title );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'records/form.css', 'cms', 'front' ) );
		
		try
		{
			if ( $database->use_categories )
			{
				foreach( $category->parents() AS $parent )
				{
					\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
				}
				\IPS\Output::i()->breadcrumb[] = array( $category->url(), $category->_title );
			}
		}
		catch( \Exception $e ) {}
		
		\IPS\Output::i()->breadcrumb[] = array( $record->url(), $record->mapped('title') );
	}
	
	/**
	 * Mark Database Record Read
	 *
	 * @return	void
	 */
	public function markRead()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$record = $this->_getRecord();
			$record->markRead();
			\IPS\Output::i()->redirect( $record->url() );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'module_no_permission', '2F173/C', 403, 'module_no_permission_guest' );
		}
	}
	
	/**
	 * Return a record based on query string 'id' param
	 * 
	 * @return \IPS\cms\Records
	 */
	public function _getRecord()
	{
		$recordClass  = '\IPS\cms\Records' . \IPS\cms\Databases\Dispatcher::i()->databaseId;

		try
		{
			$record = $recordClass::loadAndCheckPerms( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'module_no_permission', '2T252/E', 403, '' );
		}
		
		return $record;
	}
	
	/* IP.Board integration */
	
	/**
	 * Hide Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	public function _hide( $commentClass, $comment, $item  )
	{
		return $this->_doSomething( '_hide', $commentClass, $comment, $item );
	}
	
	/**
	 * Unhide Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	public function _unhide( $commentClass, $comment, $item  )
	{
		return $this->_doSomething( '_unhide', $commentClass, $comment, $item );
	}
	
	/**
	 * Split Comment
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _split( $commentClass, $comment, $item )
	{
		return $this->_doSomething( '_split', $commentClass, $comment, $item );
	}
	
	/**
	 * Edit Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _edit( $commentClass, $comment, $item )
	{
		return $this->_doSomething( '_edit', $commentClass, $comment, $item );
	}
	
	/**
	 * Report Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _report( $commentClass, $comment, $item )
	{
		return $this->_doSomething( '_report', $commentClass, $comment, $item );
	}
	
	/**
	 * Edit Log
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	public function _editlog( $commentClass, $comment, $item )
	{
		return $this->_doSomething( '_editlog', $commentClass, $comment, $item );
	}
	
	/**
	 * Delete Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _delete( $commentClass, $comment, $item )
	{
		return $this->_doSomething( '_delete', $commentClass, $comment, $item );
	}
	
	/**
	 * Rep Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _react( $commentClass, $comment, $item )
	{
		return $this->_doSomething( '_react', $commentClass, $comment, $item );
	}
	
	/**
	 * Show Comment/Review Rep
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _showReactions( $commentClass, $comment, $item )
	{
		return $this->_doSomething( '_showReactions', $commentClass, $comment, $item );
	}
	
	/**
	 * Do something that needs to be overriden from the Content controller
	 *
	 * @param	string					$method			The method name
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _doSomething( $method, $commentClass, $comment, $item )
	{
		$record = $this->_getRecord();

		if ( $record->useForumComments() AND isset( \IPS\Request::i()->comment) )
		{
			$commentClass = 'IPS\cms\Records\CommentTopicSync' . $record::$customDatabaseId;
			$comment      = $commentClass::load( \IPS\Request::i()->comment );
			$item         = $record;
		}

		try
		{
			return parent::$method( $commentClass, $comment, $item );
		}
		catch( \LogicException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2T252/F', 403, '' );
		}
	}
}