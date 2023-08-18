<?php
/**
 * @brief		Records Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		8 April 2014
 */

namespace IPS\cms\modules\admin\databases;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * records
 */
class _records extends \IPS\Dispatcher\Controller
{	
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		$this->url = $this->url->setQueryString( array( 'database_id' => \IPS\Request::i()->database_id ) );

		\IPS\Dispatcher::i()->checkAcpPermission( 'databases_use' );
		\IPS\Dispatcher::i()->checkAcpPermission( 'records_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* There may be no database ID if the admin only has permission to view records and not the database list itself */
		if ( !\IPS\Request::i()->database_id )
		{
			foreach ( \IPS\cms\Databases::databases() as $database )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=databases&controller=records&do=manage&database_id=' . $database->_id ) );
			}
		}
		
		$database = \IPS\cms\Databases::load( \IPS\Request::i()->database_id );
		$title    = \IPS\Member::loggedIn()->language()->addToStack('content_record_db_title', TRUE, array( 'sprintf' => array( $database->_title ) ) );
		
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'cms_custom_database_' . \IPS\Request::i()->database_id, \IPS\Http\Url::internal( 'app=cms&module=databases&controller=records' ) );
		$table->langPrefix  = 'content_db_table_';
		$table->title       = $title;
		$table->quickSearch = 'field_' . $database->field_title;
		$table->baseUrl     = $table->baseUrl->setQueryString( array( 'database_id' => \IPS\Request::i()->database_id ) );
		
		/* Only specify these if we are not re-ordering via the table headers, which are set in the Table contructor */
		if ( ! $table->sortBy )
		{
			$table->sortBy		  = $database->field_sort ? $database->field_sort : 'record_last_comment';
		}
		
		if ( ! $table->sortDirection )
		{
			$table->sortDirection = $database->field_direction ? $database->field_direction : 'desc';
		}
		
		$table->filters		= array(
			'content_db_table_filter_approved'   => array('record_approved=?', 1 ),
			'content_db_table_filter_unapproved' => array('record_approved=?', 0 ),
			'content_db_table_filter_hidden'	 => array('record_approved=?', -1 ),
			'content_db_table_filter_pinned'	 => array('record_pinned=?'  , 1 )
		 );

		if ( $database->use_categories )
		{
			$table->include = array(
				'primary_id_field',
				'field_' . $database->field_title,
				'record_publish_date',
				'category_id'
			);
		}
		else
		{
			$table->include = array(
				'primary_id_field',
				'field_' . $database->field_title,
				'record_publish_date'
			);
		}

        /* Add title header */
		\IPS\Member::loggedIn()->language()->words['content_db_table_field_' . $database->field_title ] = \IPS\Member::loggedIn()->language()->addToStack( 'content_db_table_title' );

		$table->advancedSearch = array(
				'category_id'	=> array( \IPS\Helpers\Table\SEARCH_NODE, array(
						'class'		      => '\IPS\cms\Categories' . $database->id,
						'disabled'	      => false,
						'zeroVal'         => 'content_db_table_as_no_cat'
					),
				),
				'record_comments'=> array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => array(
						'null' => 'content_db_table_as_comments_null',
						1      => 'content_db_table_as_comments_yes',
						2 	   => 'content_db_table_as_comments_no'
					)
				) ),
		);

		/* Buttons */
		if ( $database->use_categories )
		{
			\IPS\Output::i()->sidebar['actions']['add'] = array(
				'primary'	=> true,
				'title'	=> 'add',
				'icon'	=> 'plus',
				'link'	=> \IPS\Http\Url::internal( 'app=cms&module=databases&controller=records&do=select&database_id=' . \IPS\Request::i()->database_id ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('database_select_category' ) )
			);
		}
		else
		{
			\IPS\Output::i()->sidebar['actions']['add'] = array(
				'primary'	=> true,
				'title'	=> 'add',
				'icon'	=> 'plus',
				'link'	=> \IPS\Http\Url::internal( 'app=cms&module=databases&controller=records&do=form&database_id=' . \IPS\Request::i()->database_id )
			);
		}

		/* Buttons */
		$table->rowButtons = function( $row )
		{
			$return = array();

			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'cms', 'databases', 'records_edit' ) )
			{
				$return['edit'] = array(
					'title' => 'edit',
					'icon' => 'pencil',
					'link' => \IPS\Http\Url::internal('app=cms&module=databases&controller=records&database_id=' . \IPS\Request::i()->database_id . '&id=' . $row['primary_id_field'] . '&do=form'),
					'data' => array()
				);
			}

			$return['move']	= array(
						'title'	=> 'move',
						'icon'	=> 'arrow-right',
						'link'	=> \IPS\Http\Url::internal( 'app=cms&module=databases&controller=records&database_id=' . \IPS\Request::i()->database_id  . '&do=select&move=' . $row['primary_id_field'] ),
						'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('database_select_category' ) )
			);

			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'cms', 'databases', 'records_delete' ) )
			{
				$return['delete']	= array(
							'title'	=> 'delete',
							'icon'	=> 'times-circle',
							'link'	=> \IPS\Http\Url::internal( 'app=cms&module=databases&controller=records&database_id=' . \IPS\Request::i()->database_id . '&id=' . $row['primary_id_field'] . '&do=delete' ),
							'data'	=> array( 'delete' => '' )
				);
			}
			return $return;
		};

		$table->parsers = array(
			'field_' . $database->field_title => function( $val, $row ) use ($database)
			{
				$class = '\IPS\cms\Records' . $database->id;
				$val   = $class::load( $row['primary_id_field'] )->_title;

				return \IPS\Theme::i()->getTemplate( 'records', 'cms', 'admin' )->title( $row, $val );
			},
			'category_id'	=> function( $val ) use ($database)
			{
				if ( $val )
				{
					try
					{
						$class = '\IPS\cms\Categories' . $database->id;
						
						return \IPS\Theme::i()->getTemplate( 'records', 'cms', 'admin' )->category( $class::load( $val ) );
					}
					catch( \OutOfRangeException $e )
					{
						return '';
					}
				}
				return '';
			},
			'record_publish_date' => function( $val, $row )
			{
				$val = ( ! empty( $val ) ) ? $val : $row['record_saved'];
				return \IPS\DateTime::ts( $val )->localeDate();
			}
		);

		/* Add a button for managing DB settings */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'cms', 'databases', 'databases_edit' ) )
		{
			\IPS\Output::i()->sidebar['actions']['databasemanage'] = array(
				'title'		=> 'cms_manage_database',
				'icon'		=> 'wrench',
				'link'		=> \IPS\Http\Url::internal( 'app=cms&module=databases&controller=databases&do=form&id=' . $database->_id ),
				'data'	    => NULL
			);
		}

		\IPS\Output::i()->sidebar['actions']['databasepermissions'] = array(
			'title'		=> 'cms_database_permissions',
			'icon'		=> 'lock',
			'link'		=> \IPS\Http\Url::internal( 'app=cms&module=databases&controller=databases&do=permissions&id=' . $database->_id ),
			'data'	    => NULL
		);

		/* Reset languages */
		\IPS\Member::loggedIn()->language()->words[ $table->langPrefix . $database->field_title ] = \IPS\Member::loggedIn()->language()->addToStack($table->langPrefix . 'title');
		
		/* Display */
		\IPS\Output::i()->output = (string) $table;
		\IPS\Output::i()->title  = $title;
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'records_delete' );

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		$recordClass  = '\IPS\cms\Records' . \IPS\Request::i()->database_id;
		
		try
		{
			$record = $recordClass::load( \IPS\Request::i()->id );
			$record->delete();
			
			\IPS\Session::i()->modLog( 'acplogs__cms_deleted_record', array( $record->mapped('title') => FALSE, 'content_db_' . $record->database()->_id => TRUE ) );
		}
		catch( \OutofRangeException $ex )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2T253/1', 403, '' );
		}	

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=databases&controller=records&database_id=' . \IPS\Request::i()->database_id ), 'record_deleted' );
	}

	/**
	 * Show the pre add record form. This is used when no category is set.
	 *
	 * @return	void
	 */
	protected function select()
	{
		$move  = isset( \IPS\Request::i()->move ) ? \IPS\Request::i()->move : NULL;
		$catClass = 'IPS\cms\Categories' . \IPS\Request::i()->database_id;
		$recordClass = 'IPS\cms\Records' . \IPS\Request::i()->database_id;
		$category    = NULL;
		$record      = NULL;

		if ( $move )
		{
			try
			{
				$record   = $recordClass::load( $move );
				$category = $catClass::load( $record->category_id );
			}
			catch( \OutOfRangeException $e ) { }
		}

		$form  = new \IPS\Helpers\Form( 'select_category', 'continue' );
		$form->class = 'ipsForm_vertical ipsForm_noLabels';
		$form->add( new \IPS\Helpers\Form\Node( 'category', $category, TRUE, array(
			'url'					=> \IPS\Http\Url::internal( 'app=cms&module=databases&controller=records&do=select&database_id=' . \IPS\Request::i()->database_id . ( ( $move ) ? '&move=' . $move : '' ) ),
			'class'					=> $catClass,
			'multiple'              => false,
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
			if ( $move and $record )
			{
				$record->move( $values['category'] );
				
				\IPS\Session::i()->log( 'acplogs__cms_moved_record', array(
					$record->mapped('title')	=> FALSE,
					$category->_title			=> TRUE,
					$values['category']->_title	=> TRUE,
					$record->database()->_title	=> TRUE
				) );

				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=databases&controller=records&database_id=' . \IPS\Request::i()->database_id ), 'completed' );
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=databases&controller=records&do=form&database_id=' . \IPS\Request::i()->database_id . '&category_id=' . $values['category']->id ) );
			}
		}

		\IPS\Output::i()->title	 = \IPS\Member::loggedIn()->language()->addToStack( 'database_select_category' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'records' )->categorySelector( $form );
	}

	/**
	 * Add/Edit
	 *
	 * @return	void
	 */
	public function form()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'records_edit' );
		
		$database = \IPS\cms\Databases::load( \IPS\Request::i()->database_id );
		$title    = \IPS\Member::loggedIn()->language()->addToStack('content_record_db_title', TRUE, array( 'sprintf' => array( $database->_title ) ) );
		
		$current = NULL;
		$customValues = array();
		
		$class = 'IPS\cms\Records' . \IPS\Request::i()->database_id;
		
		if ( \IPS\Request::i()->id )
		{
			$current = $class::load( \IPS\Request::i()->id );
			$customValues = $current->fieldValues();
		}
		
		$fieldsClass = 'IPS\cms\Fields' . \IPS\Request::i()->database_id;
		$catClass    = 'IPS\cms\Categories' . \IPS\Request::i()->database_id;
		$container   = ( $current ) ? $catClass::load( $current->category_id ) : ( isset( \IPS\Request::i()->category_id ) ? $catClass::load( \IPS\Request::i()->category_id ) : NULL );
		$manuallyHandledFields = array( 'record_publish_date', 'record_expiry_date', 'record_allow_comments', 'record_comment_cutoff', 'record_meta_keywords', 'record_meta_description' );

		$form = new \IPS\Helpers\Form();
		
		$form->addTab( 'content_database_record_tab_content' );
		
		$formElements	= $class::formElements( $current, $container );
		$customFields	= $class::$customFields;

		foreach( $formElements as $name => $field )
		{
			if ( \in_array( $name, $manuallyHandledFields ) )
			{
				continue;
			}

			$form->add( $field );
		}
		
		/* Now custom fields */
		foreach( $customFields as $id => $obj )
		{
			if ( $database->field_title === 'field_' . $obj->_id )
			{
				continue;
			}
			
			$form->add( $obj );
		}

		$form->addTab( 'content_database_record_tab_publish' );
		
		if ( isset( $formElements['record_publish_date'] ) )
		{
			$form->add( $formElements['record_publish_date'] );
		}

		if ( isset( $formElements['record_expiry_date'] ) )
		{
			$form->add( $formElements['record_expiry_date'] );
		}
		
		$currentMember = ( $current ? \IPS\Member::load( $current->member_id ) : NULL );
		$options = array(
				'me'	   => 'record_author_choice_me',
				'notme'    => 'record_author_choice_notme'
		);
		
		if ( $currentMember and ! $current->member_id )
		{
			$options['guest'] = 'record_author_choice_guest';
		}
				
		$form->add( new \IPS\Helpers\Form\Radio( 'record_author_choice', $currentMember ? ( $currentMember->member_id === \IPS\Member::loggedIn()->member_id ? 'me' : ( $currentMember->member_id ? 'notme' : 'guest' ) )  : 'me', FALSE, array(
				'options' => $options,
				'toggles' => array(
						'notme' => array( 'record_member_id' )
				)
		), NULL, NULL, NULL, 'record_author_choice' ) );
		
		$form->add( new \IPS\Helpers\Form\Member( 'record_member_id', ( $currentMember and $currentMember->member_id ) ? $currentMember : NULL, FALSE, array(), function( $val ) {
				if( !$val AND \IPS\Request::i()->record_author_choice == 'notme' )
				{
					throw new \DomainException( 'form_required' );
				}
		}, NULL, NULL, 'record_member_id' ) );

		if ( isset( $formElements['record_allow_comments'] ) )
		{
			$form->addHeader( 'content_database_record_tab_comments' );

			$form->add( $formElements['record_allow_comments'] );

			if ( isset( $formElements['record_comment_cutoff'] ) )
			{
				$form->add( $formElements['record_comment_cutoff'] );
			}
		}
		
		if ( \IPS\Member::loggedIn()->modPermission('can_content_edit_meta_tags') )
		{
			$form->addTab( 'content_database_record_tab_meta' );
			$form->add( $formElements['record_meta_keywords'] );
			$form->add( $formElements['record_meta_description'] );
		}
		
		if ( $values = $form->values() )
		{
			$recordClass = '\IPS\cms\Records' . \IPS\Request::i()->database_id;
			$fieldClass  = '\IPS\cms\Fields' . \IPS\Request::i()->database_id;
			
			$new = false;
			if ( empty( $current ) )
			{
				$new      = true;
				$category = NULL;

				if ( ! $database->use_categories )
				{
					$catClass = 'IPS\cms\Categories' . $database->id;
					$category = $catClass::load( $database->get__default_category() );
				}
				else
				{
					if ( isset( \IPS\Request::i()->category_id ) )
					{
						$category = $catClass::load( \IPS\Request::i()->category_id );
					}
				}

				$current = $recordClass::createFromForm( $values, $category );
			}
			else
			{
				/* Claim attachments */
				foreach( $fieldClass::data() as $key => $field )
				{
					if ( mb_ucfirst( $field->type ) === 'Editor' )
					{
						\IPS\File::claimAttachments( 'RecordField_' . ( $new ? 'new' : $current->primary_id_field ) . '_' . $field->id , $current->primary_id_field, $field->id, \IPS\Request::i()->database_id );
					}
				}
			}
			
			/* Other data */
			$current->record_meta_keywords    = $values['record_meta_keywords'];
			$current->record_meta_description = $values['record_meta_description'];
			
			$changeAuthor = FALSE;
			if ( ! empty( \IPS\Request::i()->id ) )
			{
				if ( $values['record_author_choice'] === 'guest' )
				{
					if ( $current->member_id )
					{
						$values['record_member_id'] = new \IPS\Member;
						$changeAuthor = TRUE;
					}
				}
				else if ( ! $current->member_id and $values['record_author_choice'] !== 'guest' )
				{
					$values['record_member_id'] = ( $values['record_author_choice'] === 'me' ) ? \IPS\Member::loggedIn() : $values['record_member_id'];
					$changeAuthor = TRUE;
				}
				else if ( $values['record_author_choice'] === 'me' )
				{
					$values['record_member_id'] = \IPS\Member::loggedIn();
					$changeAuthor = TRUE;
				}
				else if ( $values['record_author_choice'] === 'notme' and ! empty( $values['record_member_id'] ) )
				{
					$changeAuthor = TRUE;
				}
			}
			
			/* Just editing, thanks */
			if ( ! $new )
			{
				$current->processForm( $values );
				$current->processAfterEdit( $values );

				if ( isset( $recordClass::$databaseColumnMap['date'] ) and isset( $values[ $recordClass::$formLangPrefix . 'date' ] ) )
				{
					$column = $recordClass::$databaseColumnMap['date'];

					if ( $values[ $recordClass::$formLangPrefix . 'date' ] instanceof \IPS\DateTime )
					{
						$current->$column = $values[ $recordClass::$formLangPrefix . 'date' ]->getTimestamp();
					}
					else
					{
						$current->$column = time();
					}
				}
			}
			
			$current->save();
			
			if ( $new )
			{
				\IPS\Session::i()->log( 'acplogs__cms_added_record', array( $current->mapped('title') => FALSE, $current->database()->_title => TRUE ) );
			}
			else
			{
				\IPS\Session::i()->modLog( 'acplogs__cms_edited_record', array( $current->mapped('title') => FALSE, $current->database()->_title => TRUE ) );
			}
			
			if ( $changeAuthor )
			{
				try
				{
					$current->changeAuthor( $values['record_member_id'] );
				}
				catch( \LogicException $ex )
				{
					/* If the database isn't attached to a page, a call to url() when logging an author change throws this */
				}
			}

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=databases&controller=records&database_id=' . \IPS\Request::i()->database_id ), 'saved' );
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->block( $current ? $title : 'add', $form, FALSE );
		\IPS\Output::i()->title  = $title;
	}
	
	/**
	 * Display the category tree
	 *
	 * @return void
	 */
	public function categoryTree()
	{
		$class = '\IPS\cms\Categories' . \IPS\Request::i()->database_id;
		try
		{
			$category = $class::load( \IPS\Request::i()->id );
			$parents = iterator_to_array( $category->parents() );
			
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->block( \IPS\Member::loggedIn()->language()->addToStack('content_tree_title'), \IPS\Theme::i()->getTemplate( 'records', 'cms', 'admin' )->categoryTree( $category, $parents ), FALSE );
			\IPS\Output::i()->title  = \IPS\Member::loggedIn()->language()->addToStack('content_tree_title');
			
		}
		catch( \Exception $ex )
		{
			
		}
	}
}