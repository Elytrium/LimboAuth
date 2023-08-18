<?php
/**
 * @brief		topic
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		03 Mar 2017
 */

namespace IPS\cms\modules\front\database;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * topic
 */
class _topic extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( !\IPS\Member::loggedIn()->modPermission( 'can_copy_topic_database' ) )
		{
			\IPS\Output::i()->error( 'node_error', '2T353/1', 403, '' );
		}
		
		try
		{
			$this->topic = \IPS\forums\Topic::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2T353/3', 404, '' );
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
		$wizard = new \IPS\Helpers\Wizard( array(
			'database'	=> array( $this, '_database' ),
			'category'	=> array( $this, '_category' ),
		), \IPS\Http\Url::internal( "app=cms&module=database&controller=topic&id=" . \IPS\Request::i()->id, 'front', 'topic_copy', $this->topic->title_seo ), FALSE );

		/* Set Breadcrumb */
		foreach ( $this->topic->container()->parents() as $parent )
		{
			\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
		}

		\IPS\Output::i()->breadcrumb[] = array( $this->topic->container()->url(), $this->topic->container()->_title );

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'copy_topic_to_database' );
		\IPS\Output::i()->output = (string) $wizard;
	}
	
	/**
	 * Database
	 *
	 * @param	mixed	$data	Data
	 * @return void
	 */
	public function _database( $data )
	{
		$form = new \IPS\Helpers\Form( 'database', 'copy_select_database', \IPS\Http\Url::internal( "app=cms&module=database&controller=topic&id=" . \IPS\Request::i()->id, 'front', 'topic_copy', $this->topic->title_seo ) );
		$form->class = 'ipsForm_vertical';
		$form->hiddenValues['topic_id'] = \IPS\Request::i()->id;
		$form->add( new \IPS\Helpers\Form\Node( 'database', NULL, TRUE, array( 'class' => '\IPS\cms\Databases', 'permissionCheck' => function( $row )
		{
			/* If this database does not have a title or content field, we cannot copy. */
			if ( !$row->field_title OR !$row->field_content )
			{
				return FALSE;
			}
			
			/* If this database is not on a page, then we cannot copy. */
			if ( !$row->page_id )
			{
				return FALSE;
			}
			
			return $row->can( 'add' );
		} ) ) );
		if ( $values = $form->values() )
		{
			return array(
				'topic_id'		=> $values['topic_id'],
				'database_id'	=> $values['database']->_id
			);
		}
		
		return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
	
	/**
	 * Category
	 *
	 * @param	mixed	$data	Data
	 * @return	void
	 */
	public function _category( $data )
	{
		/* Do we need to even bother? */
		$database = \IPS\cms\Databases::load( $data['database_id'] );
		
		if ( !$database->use_categories )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=cms&module=database&controller=topic&do=form&id={$data['topic_id']}", 'front', 'topic_copy', $this->topic->title_seo )->setQueryString( array( 'database_id' => $data['database_id'], 'category_id' => $database->default_category ) ) );
		}
		
		$catClass = 'IPS\cms\Categories' . $data['database_id'];
		
		$form = new \IPS\Helpers\Form( 'category', 'copy_select_category', \IPS\Http\Url::internal( "app=cms&module=database&controller=topic&id=" . \IPS\Request::i()->id, 'front', 'topic_copy', $this->topic->title_seo ) );
		$form->class = 'ipsForm_vertical';
		$form->hiddenValues['topic_id'] = $data['topic_id'];
		$form->hiddenValues['database_id'] = $data['database_id'];
		$form->add( new \IPS\Helpers\Form\Node( 'select_category', NULL, TRUE, array( 'class' => $catClass, 'permissionCheck' => 'add' ) ) );
		
		if ( $values = $form->values() )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=cms&module=database&controller=topic&do=form&id={$values['topic_id']}", 'front', 'topic_copy', $this->topic->title_seo )->setQueryString( array( 'database_id' => $values['database_id'], 'category_id' => $values['select_category']->_id ) ) );
		}
		
		return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
	
	/**
	 * Form
	 *
	 * @return	void
	 */
	protected function form()
	{
		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_records.js', 'cms' ) );
		
		try
		{
			$database	= \IPS\cms\Databases::load( \IPS\Request::i()->database_id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2T353/2', 404, '' );
		}
		
		try
		{
			$page = \IPS\cms\Pages\Page::load( $database->page_id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2T353/4', 404, '' );
		}
		
		$recordClass	= 'IPS\cms\Records' . $database->_id;
		$commentClass	= 'IPS\cms\Comments' . $database->_id;
		$fieldClass		= 'IPS\cms\Fields' . $database->_id;
		$catClass		= 'IPS\cms\Categories' . $database->_id;
		$category		= $catClass::load( \IPS\Request::i()->category_id );
		
		$titleField		= "field_{$database->field_title}";
		$contentField	= "field_{$database->field_content}";
		
		$fakeRecord = new $recordClass;
		$fakeRecord->$titleField	= $this->topic->mapped('title');
		$fakeRecord->$contentField	= $this->topic->content();
		$fakeRecord->category_id	= \IPS\Request::i()->category_id;

		if( $fakeRecord->_forum_record AND $fakeRecord->_forum_comments )
		{
			\IPS\Member::loggedIn()->language()->words['copy_comments_desc']	= sprintf( \IPS\Member::loggedIn()->language()->get( 'copy_comments_assoc_desc' ), $recordClass::_definiteArticle(), $recordClass::_definiteArticle() );
		}
		else
		{
			\IPS\Member::loggedIn()->language()->words['copy_comments_desc']	= sprintf( \IPS\Member::loggedIn()->language()->get( 'copy_comments_desc' ), $recordClass::_definiteArticle() );
		}
		
		\IPS\Member::loggedIn()->language()->words['copy_author_desc']		= sprintf( \IPS\Member::loggedIn()->language()->get( 'copy_author_desc' ), $recordClass::_definiteArticle() );

		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_vertical';
		$form->hiddenValues['record_category_id'] = $category->_id;
		
		foreach( $recordClass::formElements( $fakeRecord, $category ) AS $key => $element )
		{
			/* Skip these */
			if ( \in_array( $key, array( 'record_edit_reason', 'record_edit_show' ) ) )
			{
				continue;
			}
			
			$form->add( $element );
		}
		
		if ( $database->options['comments'] )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'copy_comments', FALSE, FALSE, array( 'togglesOn' => array( 'comments_show_message' ) ) ) );

			$form->add( new \IPS\Helpers\Form\YesNo( 'comments_show_message', TRUE, FALSE, array( 'togglesOn' => array( 'comments_meta_message', 'comments_meta_color' ) ), NULL, NULL, NULL, 'comments_show_message' ) );
			$form->add( new \IPS\Helpers\Form\Editor( 'comments_meta_message', \IPS\Member::loggedIn()->language()->addToStack('default_copyposts_message'), FALSE, array( 'app' => 'core', 'key' => 'Meta', 'autoSaveKey' => "meta-message-new", 'attachIds' => NULL ), NULL, NULL, NULL, 'comments_meta_message' ) );
			$form->add( new \IPS\Helpers\Form\Custom( 'comments_meta_color', 'none', FALSE, array( 'getHtml' => function( $element )
			{
				return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'front' )->colorSelection( $element->name, $element->value );
			} ), NULL, NULL, NULL, 'comments_meta_color' ) );
		}
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'copy_author', TRUE ) );
		
		if ( $values = $form->values() )
		{
			$comments	= FALSE;
			if ( array_key_exists( 'copy_comments', $values ) )
			{
				$comments	= $values['copy_comments'];
				unset( $values['copy_comments'] );
			}

			/* Determine if we are showing a meta message after */
			$metaMessage = NULL;

			/* We will only be sowing the message if we are copying comments */
			if( $comments )
			{
				if ( array_key_exists( 'comments_show_message', $values ) )
				{
					$metaMessage = array( 'show' => $values['comments_show_message'], 'message' => $values['comments_meta_message'], 'color' => $values['comments_meta_color'] );
				}
			}
			unset( $values['comments_show_message'], $values['comments_meta_message'], $values['comments_meta_color'] );
			
			/* Figure out author */
			$author		= $values['copy_author'];
			unset( $values['copy_author'] );

			/* If we are copying comments and we use the forums for comments, skip creating a topic as we will just reassocciate */
			if( $comments AND $fakeRecord->_forum_record AND $fakeRecord->_forum_comments )
			{
				$recordClass::$skipTopicCreation = true;
			}
			
			$record = $recordClass::createFromForm( $values, $category );
			
			if ( $author )
			{
				$record->changeAuthor( $this->topic->author() );
			}

			if( $metaMessage !== NULL )
			{
				if( $metaMessage['show'] )
				{
					$id = $record->addMessage( $metaMessage['message'], $metaMessage['color'] );
					\IPS\File::claimAttachments( "meta-message-new", $id, NULL, 'core_ContentMessages' );
				}
			}
			
			/* If the record syncs with the forums and we are copying topics, just associate with the existing topic */
			if( $comments AND $record->_forum_record AND $record->_forum_comments )
			{
				$record->record_topicid = $this->topic->tid;
				$record->save();
				
				/* Reload using the proper class so first and last comment data can be rebuilt properly */
				$class = 'IPS\cms\Records\RecordsTopicSync' . $database->_id;
				$class::load( $this->topic->tid )->rebuildFirstAndLastCommentData();

				try
				{
					/* If the database is on a page, go to the record */
					\IPS\Output::i()->redirect( $record->url() );
				}
				catch( \LogicException $e )
				{
					/* If it is NOT then go back to the topic */
					\IPS\Output::i()->redirect( $this->topic->url() );
				}
			}
			elseif ( $comments )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=cms&module=database&controller=topic&do=comments&id={$this->topic->tid}", 'front', 'topic_copy', $this->topic->title_seo )->csrf()->setQueryString( array( 'record_id' => $record->primary_id_field, 'database_id' => $database->_id ) ) );
			}
			else
			{
				try
				{
					/* If the database is on a page, go to the record */
					\IPS\Output::i()->redirect( $record->url() );
				}
				catch( \LogicException $e )
				{
					/* If it is NOT then go back to the topic */
					\IPS\Output::i()->redirect( $this->topic->url() );
				}
			}
		}
		
		$hasModOptions = FALSE;
		
		if ( $recordClass::modPermission( 'lock', NULL, $category ) or
			 $recordClass::modPermission( 'pin', NULL, $category ) or
			 $recordClass::modPermission( 'hide', NULL, $category ) or
			 $recordClass::modPermission( 'feature', NULL, $category ) or
			 $fieldClass::fixedFieldFormShow( 'record_allow_comments' ) or
			 $fieldClass::fixedFieldFormShow( 'record_expiry_date' ) or
			 $fieldClass::fixedFieldFormShow( 'record_comment_cutoff' ) or
			 \IPS\Member::loggedIn()->modPermission('can_content_edit_meta_tags') )
		{
			$hasModOptions = TRUE;
		}
		
		array_shift( \IPS\Output::i()->breadcrumb );
		$container	= NULL;
		try
		{
			$container = $this->topic->container();
			foreach ( $container->parents() as $parent )
			{
				\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
			}
			\IPS\Output::i()->breadcrumb[] = array( $container->url(), $container->_title );
		}
		catch ( \Exception $e ) { }
		\IPS\Output::i()->breadcrumb[] = array( $this->topic->url(), $this->topic->mapped( 'title' ) );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'copy_topic_to_database' ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'copy_topic_to_database' );
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\cms\Theme::i()->getTemplate( $database->template_form, 'cms', 'database' ), 'recordForm' ), NULL, $category, $database, $page, \IPS\Member::loggedIn()->language()->addToStack( 'copy_topic_to_database' ), $hasModOptions );
	}
	
	/**
	 * Comments
	 *
	 * @return	void
	 */
	protected function comments()
	{
		\IPS\Session::i()->csrfCheck();
		
		$mr = new \IPS\Helpers\MultipleRedirect( \IPS\Http\Url::internal( "app=cms&module=database&controller=topic&id=" . \IPS\Request::i()->id . "&do=comments", 'front', 'topic_copy', $this->topic->title_seo )->csrf()->setQueryString( array( 'database_id' => \IPS\Request::i()->database_id, 'record_id' => \IPS\Request::i()->record_id ) ), function( $data ) {
			$database		= \IPS\cms\Databases::load( \IPS\Request::i()->database_id );
			$recordClass	= 'IPS\cms\Records' . \IPS\Request::i()->database_id;
			$commentClass	= $recordClass::$commentClass;
			$record			= $recordClass::load( \IPS\Request::i()->record_id );
			$total			= \IPS\Db::i()->select( 'COUNT(*)', 'forums_posts', array( "topic_id=? AND new_topic=? AND queued!=?", \IPS\Request::i()->id, 0, 2 ) )->first();
			
			$done = 0;
			foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'forums_posts', array( "topic_id=? AND new_topic=? AND queued!=?", \IPS\Request::i()->id, 0, -2 ), "pid ASC", array( $data, 100 ) ), 'IPS\forums\Topic\Post' ) AS $post )
			{
				$commentClass::create( $record, $post->content(), FALSE, NULL, TRUE, $post->author(), \IPS\DateTime::create(), $post->ip_address, $post->hidden() );
				$done++;
			}
			
			if ( !$done )
			{
				return NULL;
			}
			
			return array( $data + $done, \IPS\Member::loggedIn()->language()->addToStack( 'copying_comments' ), 100 / $total * ( $data + 100 ) );
			
		}, function() {
			$recordClass	= 'IPS\cms\Records' . \IPS\Request::i()->database_id;
			$record			= $recordClass::load( \IPS\Request::i()->record_id );
			
			try
			{
				\IPS\Output::i()->redirect( $record->url() );
			}
			catch( \LogicException $e )
			{
				\IPS\Output::i()->redirect( \IPS\forums\Topic::load( \IPS\Request::i()->id )->url() );
			}
		} );
		
		$topic = \IPS\forums\Topic::load( \IPS\Request::i()->id );
		
		array_shift( \IPS\Output::i()->breadcrumb );
		$container	= NULL;
		try
		{
			$container = $topic->container();
			foreach ( $container->parents() as $parent )
			{
				\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
			}
			\IPS\Output::i()->breadcrumb[] = array( $container->url(), $container->_title );
		}
		catch ( \Exception $e ) { }
		\IPS\Output::i()->breadcrumb[] = array( $topic->url(), $topic->mapped( 'title' ) );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'copy_topic_to_database' ) );
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'copying_comments' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->genericBlock( (string) $mr, \IPS\Member::loggedIn()->language()->addToStack( 'copying_comments' ), 'ipsBox ipsPad' );
	}
}