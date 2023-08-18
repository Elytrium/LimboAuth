<?php
/**
 * @brief		Templates Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		25 Feb 2013
 */

namespace IPS\cms\modules\admin\pages;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * templates
 */
class _templates extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'template_manage' );
		parent::execute();
	}
	
	/**
	 * Import the IN_DEV templates
	 * 
	 * @return void
	 */
	public function importInDev()
	{
		\IPS\Session::i()->csrfCheck();
		
		\IPS\cms\Theme::importInDev('database');
		\IPS\cms\Theme::importInDev('page');

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=pages&controller=templates' ), 'completed' );
	}

	/**
	 * Import dialog
	 *
	 * @return void
	 */
	public function import()
	{
		$form = new \IPS\Helpers\Form( 'form', 'next' );

		$form->add( new \IPS\Helpers\Form\Upload( 'cms_templates_import', NULL, FALSE, array( 'allowedFileTypes' => array( 'xml' ), 'temporary' => TRUE ), NULL, NULL, NULL, 'cms_templates_import' ) );

		if ( $values = $form->values() )
		{
			if ( $values['cms_templates_import'] )
			{
				/* Move it to a temporary location */
				$tempFile = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
				move_uploaded_file( $values['cms_templates_import'], $tempFile );

				\IPS\Session::i()->log( 'acplogs__cms_imported_templates' );

				/* Initate a redirector */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=pages&controller=templates&do=importProcess' )->csrf()->setQueryString( array( 'file' => $tempFile, 'key' => md5_file( $tempFile ) ) ) );
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=pages&controller=templates&do=manage' ) );
			}
		}

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'admin' )->block( 'cms_templates_import_title', $form, FALSE );
	}

	/**
	 * Import from upload
	 *
	 * @return	void
	 */
	public function importProcess()
	{
		\IPS\Session::i()->csrfCheck();
		
		if ( !file_exists( \IPS\Request::i()->file ) or md5_file( \IPS\Request::i()->file ) !== \IPS\Request::i()->key )
		{
			\IPS\Output::i()->error( 'generic_error', '3T285/3', 403, '' );
		}

		$result = NULL;
		try
		{
			$result = \IPS\cms\Templates::importUserTemplateXml( \IPS\Request::i()->file );
		}
		catch( \Throwable $e )
		{
			@unlink( \IPS\Request::i()->file );
		}

		/* Done */
		if ( $result instanceof \IPS\Http\Url\Internal )
		{
			\IPS\Output::i()->redirect( $result );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=pages&controller=templates&do=manage' ), 'cms_templates_imported' );
		}
	}

	/**
	 * Show and manage conflicts
	 *
	 * @return void
	 */
	public function conflicts()
	{
		$key         = \IPS\Request::i()->key;
		$form        = new \IPS\Helpers\Form( 'form', 'theme_conflict_save' );
		$conflicts   = array();

		/* If this is part of plugin/app installation, set relevant form data */
		if( \IPS\Request::i()->application OR \IPS\Request::i()->plugin )
		{
			if( \IPS\Request::i()->application )
			{
				$form->hiddenValues['application'] = \IPS\Request::i()->application;
			}
			elseif( \IPS\Request::i()->plugin )
			{
				$form->hiddenValues['plugin'] = \IPS\Request::i()->plugin;
			}

			if( \IPS\Request::i()->marketplace )
			{
				$form->hiddenValues['marketplace'] = (int) \IPS\Request::i()->marketplace;
			}

			if( \IPS\Request::i()->lang )
			{
				$form->hiddenValues['lang'] = \IPS\Request::i()->lang;
			}
		}

		/* Get conflict data */
		foreach( \IPS\Db::i()->select( '*', 'cms_template_conflicts', array( 'conflict_key=?', $key ) )->setKeyField( 'conflict_id' ) as $cid => $data )
		{
			$conflicts[ $cid ] = $data;
		}

		require_once \IPS\ROOT_PATH . "/system/3rd_party/Diff/class.Diff.php";

		foreach( $conflicts as $cid => $data )
		{
			try
			{
				$template = \IPS\cms\Templates::load( $data['conflict_item_id'], 'template_id' );

				if ( !\IPS\Login::compareHashes( md5( $data['conflict_content'] ), md5( $template->content ) ) )
				{
					if ( mb_strlen( $data['conflict_content'] ) <= 10000 )
					{
						$conflicts[ $cid ]['diff'] = \Diff::toTable( \Diff::compare( $template->content, $data['conflict_content'] ) );
						$conflicts[ $cid ]['large'] = false;
					}
					else
					{
						$conflicts[ $cid ]['diff'] = \IPS\Theme::i()->getTemplate( 'customization', 'core' )->templateConflictLarge( $template->content, $data['conflict_content'], 'html' );
						$conflicts[ $cid ]['large'] = true;
					}

					$form->add( new \IPS\Helpers\Form\Radio( 'conflict_' . $data['conflict_id'], 'old', false, array('options' => array('old' => '', 'new' => '')) ) );
				}
				else
				{
					unset( $conflicts[ $cid ] );
				}
			}
			catch( \Exception $e )
			{
				unset( $conflicts[ $cid ] );
			}
		}

		if ( $values = $form->values() )
		{
			$conflicts   = array();
			$conflictIds = array();
			$templates = array();

			foreach( $values as $k => $v )
			{
				if ( \substr( $k, 0, 9 ) == 'conflict_' )
				{
					if ( $v == 'new' )
					{
						$conflictIds[ (int) \substr( $k, 9 ) ] = $v;
					}
				}
			}

			if ( \count( $conflictIds ) )
			{
				/* Get conflict data */
				foreach( \IPS\Db::i()->select( '*', 'cms_template_conflicts', \IPS\Db::i()->in( 'conflict_id', array_keys( $conflictIds ) ) )->setKeyField( 'conflict_id' ) as $cid => $data )
				{
					$conflicts[ $data['conflict_item_id'] ] = $data;
				}
			}

			if ( \count( $conflicts ) )
			{
				$templates = iterator_to_array( \IPS\Db::i()->select(
					'*',
					'cms_templates',
					array( \IPS\Db::i()->in( 'template_id', array_keys( $conflicts ) ) )
				)->setKeyField( 'template_id' ) );
			}

			foreach( $templates as $templateid => $template )
			{
				if ( isset( $conflicts[ $template['template_id'] ] ) )
				{
					try
					{
						$templateObj = \IPS\cms\Templates::load( $template['template_id'], 'template_id' );
						$templateObj->params = $conflicts[ $template['template_id'] ]['conflict_data'];
						$templateObj->content = $conflicts[ $template['template_id'] ]['conflict_content'];
						$templateObj->user_edited = (int) $templateObj->isDifferentFromMaster();
						$templateObj->save();
					}
					catch( \Exception $e ) { }
				}
			}

			/* Clear out conflicts for this theme set */
			\IPS\Db::i()->delete( 'cms_template_conflicts', array('conflict_key=?', \IPS\Request::i()->key ) );

			$lang = NULL;
			if( !empty( $values['lang'] ) )
			{
				$lang = $values['lang'] == 'updated' ? 'application_now_updated' : 'application_now_installed';
			}

			if( !empty( $values['marketplace'] ) )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=marketplace&controller=marketplace&do=viewFile&id=' . $values['marketplace'] ), $lang );
			}
			elseif( !empty( $values['application'] ) )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=applications&controller=applications' ), $lang );
			}
			elseif( !empty( $values['plugin'] ) )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=applications&controller=plugins' ), 'plugin_now_installed' );
			}

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=pages&controller=templates&do=manage' ), 'completed' );
		}

		if ( \count( $conflicts ) )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/diff.css', 'core', 'admin' ) );
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'codemirror/codemirror.js', 'core', 'interface' ) );
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'codemirror/codemirror.css', 'core', 'interface' ) );
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'customization/themes.css', 'core', 'admin' ) );
			\IPS\Output::i()->jsFiles  = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_templates.js', 'core', 'admin' ) );

			\IPS\Output::i()->output   = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'templates', 'cms' ), 'templateConflict' ), $conflicts );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=pages&controller=templates&do=manage' ), 'completed' );
		}
	}

	/**
	 * Export templates
	 *
	 * @return void
	 */
	public function export()
	{
		$form = \IPS\cms\Templates::exportForm();

		if ( $values = $form->values() )
		{
			$xml = \IPS\cms\Templates::exportAsXml( $values );

			if( $xml === NULL )
			{
				\IPS\Output::i()->error( 'cms_no_templates_selected', '1T285/1', 403, '' );
			}

			\IPS\Session::i()->log( 'acplogs__cms_exported_templates' );

			\IPS\Output::i()->sendOutput( $xml->outputMemory(), 200, 'application/xml', array( 'Content-Disposition' => \IPS\Output::getContentDisposition( 'attachment', "pages_templates.xml" ) ) );
		}
		
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=cms&module=pages&controller=templates" ), \IPS\Member::loggedIn()->language()->addToStack( 'menu__cms_pages_templates' ) );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('cms_templates_export_title') );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('cms_templates_export_title');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'admin' )->block( 'cms_templates_export_title', $form, FALSE );
	}

	/**
	 * List templates
	 * 
	 * @return void
	 */
	public function manage()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'template_add_edit' );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__cms_pages_templates');

		if ( \IPS\Theme::designersModeEnabled() )
		{
			$link = \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=designersmode' );
			\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->message( \IPS\Member::loggedIn()->language()->addToStack( 'cms_theme_designer_mode_warning', FALSE, array( 'sprintf' => array( $link ) ) ), 'information', NULL, FALSE );
		}
		else
		{
			$request = array(
				't_location' => ( isset( \IPS\Request::i()->t_location ) ) ? \IPS\Request::i()->t_location : NULL,
				't_group'    => ( isset( \IPS\Request::i()->t_group ) ) ? \IPS\Request::i()->t_group : NULL,
				't_key'      => ( isset( \IPS\Request::i()->t_key ) ) ? \IPS\Request::i()->t_key : NULL,
				't_type'     => ( isset( \IPS\Request::i()->t_type ) ) ? \IPS\Request::i()->t_type : 'templates',
			);

			switch ( $request['t_type'] )
			{
				default:
				case 'template':
					$flag = \IPS\cms\Templates::RETURN_ONLY_TEMPLATE;
					break;
				case 'js':
					$flag = \IPS\cms\Templates::RETURN_ONLY_JS;
					break;
				case 'css':
					$flag = \IPS\cms\Templates::RETURN_ONLY_CSS;
					break;
			}

			$templates = \IPS\cms\Templates::buildTree( \IPS\cms\Templates::getTemplates( $flag + \IPS\cms\Templates::RETURN_DATABASE_ONLY ) );

			$current = NULL;

			if ( !empty( $request['t_key'] ) )
			{
				try
				{
					$current = \IPS\cms\Templates::load( $request['t_key'] );
				}
				catch ( \OutOfRangeException $ex )
				{

				}
			}

			/* Load first block */
			if ( $current === NULL )
			{
				foreach ( $templates as $type => $_templates )
				{
					if ( $_templates )
					{
						$test = key( $_templates );

						try
						{
							$current = \IPS\cms\Templates::load( $test );
						}
						catch ( \OutofRangeException $e )
						{
							foreach ( $_templates as $location => $group )
							{
								foreach ( $group as $name => $template )
								{
									$current = $template;
									break 3;
								}
							}
						}
					}
				}
			}

			/* Display */
			\IPS\Output::i()->responsive = FALSE;

			/* A button */
			if ( \IPS\IN_DEV )
			{
				\IPS\Output::i()->sidebar['actions']['add'] = array(
					'icon'  => 'cog',
					'title' => 'content_import_dev_templates',
					'link'  => \IPS\Http\Url::internal( "app=cms&module=pages&controller=templates&do=importInDev" )->csrf(),
					'data'  => array()
				);
			}

			\IPS\Output::i()->sidebar['actions']['download'] = array(
				'icon'  => 'download',
				'title' => 'cms_templates_export_title',
				'link'  => \IPS\Http\Url::internal( "app=cms&module=pages&controller=templates&do=export" ),
			);

			\IPS\Output::i()->sidebar['actions']['upload'] = array(
				'icon'  => 'upload',
				'title' => 'cms_templates_import_title',
				'link'  => \IPS\Http\Url::internal( "app=cms&module=pages&controller=templates&do=import" ),
				'data'  => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('cms_templates_import_title') )
			);

			\IPS\Output::i()->jsFiles  = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'codemirror/diff_match_patch.js', 'core', 'interface' ) );
			\IPS\Output::i()->jsFiles  = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'codemirror/codemirror.js', 'core', 'interface' ) );
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'codemirror/codemirror.css', 'core', 'interface' ) );
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'templates/templates.css', 'cms', 'admin' ) );
			\IPS\Output::i()->jsFiles  = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_templates.js', 'cms' ) );

			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'templates' )->templates( $templates, $current, $request );
		}
	}
	
	/**
	 * Add Container
	 *
	 * @return	void
	 */
	public function addContainer()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'template_add' );
	
		$type = \IPS\Request::i()->type;
	
		/* Build form */
		$form = new \IPS\Helpers\Form();
	
		$form->add( new \IPS\Helpers\Form\Text( 'container_name', NULL, TRUE ) );
		$form->hiddenValues['type'] = $type;
	
		if ( $values = $form->values() )
		{
			$type = \IPS\Request::i()->type;
				
			$newContainer = \IPS\cms\Templates\Container::add( array(
					'name' => $values['container_name'],
					'type' => 'template_' . $type
			) );

			\IPS\Session::i()->log( 'acplogs__cms_template_container', array( $container->title => false ) );
	
			if( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array(
					'id'   => $newContainer->id,
					'name' => $newContainer->title,
				) );
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=pages&controller=templates' ), 'saved' );
			}
		}
	
		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->block( 'content_template_add_container_' . $type, $form, FALSE );
	}
	
	/**
	 * Add Template
	 * This is never used for editing as this is done via the template manager
	 *
	 * @return	void
	 */
	public function addTemplate()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'template_add_edit' );
		
		$type = \IPS\Request::i()->type;
		
		/* Build form */
		$form = new \IPS\Helpers\Form();
		$form->hiddenValues['type'] = $type;
		
		$form->add( new \IPS\Helpers\Form\Text( 'template_title', NULL, TRUE, array( 'regex' => '/^([A-Z_][A-Z0-9_]+?)$/i' ), function ( $val ) {
			/* PHP Keywords cannot be used as template names - so make sure the full template name is not in the list */
			$keywords = array( 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'interface', 'isset', 'list', 'namespace', 'new', 'or', 'print', 'private', 'protected', 'public', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor' );
			
			if ( \in_array( $val, $keywords ) )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'template_reserved_word', FALSE, array( 'htmlsprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $keywords ) ) ) ) );
			}

			try
			{
				$count = \IPS\Db::i()->select( 'COUNT(*)', 'cms_templates', array( "LOWER(template_title)=?", mb_strtolower( str_replace( ' ', '_', $val ) )  ) )->first();
				
				if ( $count )
				{
					throw new \DomainException( 'cms_template_title_exists' );
				}
			}
			catch( \UnderflowException $e ) {}
		} ) );

		/* Very specific */
		if ( $type === 'database' )
		{
			$groups = array(); /* I was sorely tempted to put 'Radiohead', 'Beatles' in there */
			foreach( \IPS\cms\Databases::$templateGroups as $key => $group )
			{
				$groups[ $key ] = \IPS\Member::loggedIn()->language()->addToStack( 'cms_new_db_template_group_' . $key );
			}

			$form->add( new \IPS\Helpers\Form\Select( 'database_template_type', NULL, FALSE, array(
				'options' => $groups
			) ) );

			$databases = array( 0 => \IPS\Member::loggedIn()->language()->addToStack('cms_new_db_assign_to_db_none' ) );
			foreach( \IPS\cms\Databases::databases() as $obj )
			{
				$databases[ $obj->id ] = $obj->_title;
			}

			$form->add( new \IPS\Helpers\Form\Select( 'database_assign_to', NULL, FALSE, array(
				'options' => $databases
			) ) );
		}
		else if ( $type === 'block' )
		{
			$plugins = array();
			foreach ( \IPS\Db::i()->select( "*", 'core_widgets', array( 'embeddable=1') ) as $widget )
			{
				/* Skip disabled applications */
				if ( !\in_array( $widget['app'], array_keys( \IPS\Application::enabledApplications() ) ) )
				{
					continue;
				}

				try
				{
					$plugins[ \IPS\Application::load( $widget['app'] )->_title ][ $widget['app'] . '__' . $widget['key'] ] = \IPS\Member::loggedIn()->language()->addToStack( 'block_' . $widget['key'] );
				}
				catch ( \OutOfRangeException $e ) { }
			}
			
			$form->add( new \IPS\Helpers\Form\Select( 'block_template_plugin_import', NULL, FALSE, array(
					'options' => $plugins
			) ) );
		
			$form->add( new \IPS\Helpers\Form\Node( 'block_template_theme_import', NULL, TRUE, array(
				'class' => '\IPS\Theme'
			) ) );
		}
		else
		{
			/* Page, css, js */
			switch( $type )
			{
				default:
					$flag = \IPS\cms\Theme::RETURN_ONLY_TEMPLATE;
				break;
				case 'page':
					$flag = \IPS\cms\Theme::RETURN_PAGE;
				break;
				case 'js':
					$flag = \IPS\cms\Theme::RETURN_ONLY_JS;
				break;
				case 'css':
					$flag = \IPS\cms\Theme::RETURN_ONLY_CSS;
				break;
			}

			$templates = \IPS\cms\Theme::i()->getRawTemplates( array(), array(), array(), $flag | \IPS\cms\Theme::RETURN_ALL_NO_CONTENT );

			$groups = array();

			if ( isset( $templates['cms'][ $type ] ) )
			{
				foreach( $templates['cms'][ $type ] as $group => $data )
				{
					$groups[ $group ] = \IPS\cms\Templates::readableGroupName( $group );
				}
			}

			if ( ! \count( $groups ) )
			{
				$groups[ $type ] = \IPS\cms\Templates::readableGroupName( $type );
			}

			$form->add( new \IPS\Helpers\Form\Radio( 'theme_template_group_type', 'existing', FALSE, array(
				            'options'  => array( 'existing' => 'theme_template_group_o_existing',
				                                 'new'	    => 'theme_template_group_o_new' ),
				            'toggles'  => array( 'existing' => array( 'group_existing' ),
				                                 'new'      => array( 'group_new' ) )
			            ) ) );

			$form->add( new \IPS\Helpers\Form\Text( 'template_group_new', NULL, FALSE, array( 'regex' => '/^([a-z_][a-z0-9_]+?)?$/' ), function( $val ) {
				/* PHP Keywords cannot be used as template names - so make sure the full template name is not in the list */
				$keywords = array( 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'interface', 'isset', 'list', 'namespace', 'new', 'or', 'print', 'private', 'protected', 'public', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor' );

				if ( \in_array( $val, $keywords ) )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'template_reserved_word', FALSE, array( 'htmlsprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $keywords ) ) ) ) );
				}

				try
				{
					$count = \IPS\Db::i()->select( 'COUNT(*)', 'cms_templates', array( "LOWER(template_group)=?", mb_strtolower( str_replace( ' ', '_', $val ) )  ) )->first();
	
					if ( $count )
					{
						throw new \DomainException( 'cms_template_group_exists' );
					}
				}
				catch( \UnderflowException $e ) {}
			}, NULL, NULL, 'group_new' ) );
			$form->add( new \IPS\Helpers\Form\Select( 'template_group_existing', NULL, FALSE, array( 'options' => $groups ), NULL, NULL, NULL, 'group_existing' ) );
		}

		if ( ! \IPS\Request::i()->isAjax() AND $type !== 'database' )
		{
			$form->add( new \IPS\Helpers\Form\TextArea( 'template_content', NULL ) );
		}
	
		if ( $values = $form->values() )
		{
			$type = \IPS\Request::i()->type;

			if ( $type == 'database' )
			{
				/* We need to copy templates */
				$group     = \IPS\cms\Databases::$templateGroups[ $values['database_template_type' ] ];
				$templates = iterator_to_array( \IPS\Db::i()->select( '*', 'cms_templates', array( 'template_location=? AND template_group=? AND template_user_edited=0 AND template_user_created=0', 'database', $group ) ) );

				foreach( $templates as $template )
				{
					unset( $template['template_id'] );
					$template['template_original_group'] = $template['template_group'];
					$template['template_group'] = str_replace( '-', '_', \IPS\Http\Url\Friendly::seoTitle( $values['template_title'] ) );

					$save = array();
					foreach( $template as $k => $v )
					{
						$k = \mb_substr( $k, 9 );
						$save[ $k ] = $v;
					}

					/* Make sure template tags call the correct group */
					if ( mb_stristr( $save['content'], '{template' ) )
					{
						preg_match_all( '/\{([a-z]+?=([\'"]).+?\\2 ?+)}/', $save['content'], $matches, PREG_SET_ORDER );

						/* Work out the plugin and the values to pass */
						foreach( $matches as $index => $array )
						{
							preg_match_all( '/(.+?)=' . $array[ 2 ] . '(.+?)' . $array[ 2 ] . '\s?/', $array[ 1 ], $submatches );

							$plugin = array_shift( $submatches[ 1 ] );
							if ( $plugin == 'template' )
							{
								$value   = array_shift( $submatches[ 2 ] );
								$options = array();

								foreach ( $submatches[ 1 ] as $k => $v )
								{
									$options[ $v ] = $submatches[ 2 ][ $k ];
								}

								if ( isset( $options['app'] ) and $options['app'] == 'cms' and isset( $options['location'] ) and $options['location'] == 'database' and isset( $options['group'] ) and $options['group'] == $template['template_original_group'] )
								{
									$options['group'] = $template['template_group'];

									$replace = '{template="' . $value . '" app="' . $options['app'] . '" location="' . $options['location'] . '" group="' . $options['group'] . '" params="' . ( isset($options['params']) ? $options['params'] : NULL ) . '"}';

									$save['content'] = str_replace( $matches[$index][0], $replace, $save['content'] );
								}
							}
						}
					}

					$newTemplate = \IPS\cms\Templates::add( $save );
				}

				if ( $values['database_assign_to'] )
				{
					try
					{
						$db   = \IPS\cms\Databases::load( $values['database_assign_to'] );
						$key  = 'template_' . $values['database_template_type'];
						$db->$key = $template['template_group'];
						$db->save();
					}
					catch( \OutOfRangeException $ex ) { }
				}
			}
			else if ( $type === 'block' )
			{
				$save = array(
					'title'	   => str_replace( '-', '_', \IPS\Http\Url\Friendly::seoTitle( $values['template_title'] ) ),
					'params'   => isset( $values['template_params'] ) ? $values['template_params'] : null,
					'location' => $type
				);

				/* Get template */
				list( $widgetApp, $widgetKey ) = explode( '__', $values['block_template_plugin_import'] );

				/* Find it from the normal template system */
				$plugin = \IPS\Widget::load( \IPS\Application::load( $widgetApp ), $widgetKey, mt_rand(), array() );

				$location = $plugin->getTemplateLocation();

				$theme = ( \IPS\IN_DEV ) ? \IPS\Theme::master() : $values['block_template_theme_import'];
				$templateBits  = $theme->getRawTemplates( $location['app'], $location['location'], $location['group'], \IPS\Theme::RETURN_ALL );
				$templateBit   = $templateBits[ $location['app'] ][ $location['location'] ][ $location['group'] ][ $location['name'] ];

				$save['content'] = $templateBit['template_content'];
				$save['params']  = $templateBit['template_data'];
				$save['group']   = $widgetKey;
				$newTemplate = \IPS\cms\Templates::add( $save );
			}
			else
			{
				$save = array( 'title' => $values['template_title'] );

				/* Page, css, js */
				if ( $type == 'js' or $type == 'css' )
				{
					$fileExt = ( $type == 'js' ) ? '.js' : ( $type == 'css' ? '.css' : NULL );
					if ( $fileExt AND ! preg_match( '#' . preg_quote( $fileExt, '#' ) . '$#', $values['template_title'] ) )
					{
						$values['template_title'] .= $fileExt;
					}

					$save['title'] = $values['template_title'];
					$save['type']  = $type;
				}
				
				if ( $type === 'page' AND $values['theme_template_group_type'] == 'existing' AND $values['template_group_existing'] == 'custom_wrappers' )
				{
					$save['params'] = '$html=NULL, $title=NULL';
				}
				
				if ( $type === 'page' AND $values['theme_template_group_type'] == 'existing' AND $values['template_group_existing'] == 'page_builder' )
				{
					$save['params'] = '$page, $widgets';
				}

				$save['group'] = ( $values['theme_template_group_type'] == 'existing' ) ? $values['template_group_existing'] : $values['template_group_new'];

				if ( isset( $values['template_content'] ) )
				{
					$save['content'] = $values['template_content'];
				}

				$save['location'] = $type;

				$newTemplate = \IPS\cms\Templates::add( $save );
			}

			\IPS\Session::i()->log( 'acplogs__cms_template_add', array( $newTemplate->title => false ) );

			/* Done */
			if( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array(
					'id'		=> $newTemplate->id,
					'title'		=> $newTemplate->title,
					'params'	=> $newTemplate->params,
					'desc'		=> $newTemplate->description,
					'container'	=> $newTemplate->container,
					'location'	=> $newTemplate->location
				)	);
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=pages&controller=templates' )->setQueryString( ['id' => $newTemplate->id, 't_location' => $newTemplate->location, 't_type' => $type ] ), 'saved' );
			}
		}
	
		/* Display */
		$title = \strip_tags( \IPS\Member::loggedIn()->language()->get( 'content_template_add_template_' . $type ) );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->block( $title, $form, FALSE );
		\IPS\Output::i()->title  = $title;
	}
	
	/**
	 * Delete a template
	 * This can be either a CSS template or a HTML template
	 *
	 * @return	void
	 */
	public function delete()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'template_delete' );

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		$key    = \IPS\Request::i()->t_key;
		$return = array(
			'template_content' => NULL,
			'template_id' 	   => NULL
		);

		$originalTemplate = \IPS\cms\Templates::load( $key );
		
		try
		{
			\IPS\cms\Templates::load( $key )->delete();
			
			/* Now reload */
			try
			{
				$template = \IPS\cms\Templates::load( $key );
				
				$return['template_location'] = $template->location;
				$return['template_content']  = $template->content;
				$return['template_id']		 = $template->id;
				$return['InheritedValue']    = ( $template->user_added ) ? 'custom' : ( $template->user_edited ? 'changed' : 0 );
			}
			catch( \OutOfRangeException $ex )
			{
				
			}
		}
		catch( \OutOfRangeException $ex )
		{
			\IPS\Output::i()->error( 'node_error', '3T285/4', 500, '' );
		}

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();

		\IPS\Session::i()->log( 'acplogs__cms_template_delete', array( $originalTemplate->title => false ) );

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( $return );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=pages&controller=templates' ), 'completed' );
		}
	}
	
	/**
	 * Show a difference report for an individual template file
	 *
	 * @return	void
	 */
	protected function diffTemplate()
	{
		$customVersion = \IPS\Db::i()->select( '*', 'cms_templates', array( 'template_id=?', (int) \IPS\Request::i()->t_item_id ) )->first();
		
		try 
		{
			$original = \IPS\Db::i()->select( 'template_content', 'cms_templates', array( 'template_location=? and template_group=? and template_title=? and template_master=1', $customVersion['template_location'], $customVersion['template_original_group'], $customVersion['template_title'] ) )->first();
		}
		catch( \UnderflowException $e )
		{
			$original = FALSE;
		}
		
		\IPS\Output::i()->json( $original );
	}
	
	/**
	 * Saves a template
	 * 
	 * @return void
	 */
	public function save()
	{
		\IPS\Session::i()->csrfCheck();

		$key = \IPS\Request::i()->t_key;
		
		$contentKey = 'editor_' . $key;

		$content     = \IPS\Request::i()->$contentKey;
		$description = \IPS\Request::i()->t_description;
		$variables   = isset( \IPS\Request::i()->t_variables ) ? \IPS\Request::i()->t_variables : '';
		
		try
		{
			$obj = \IPS\cms\Templates::load( $key );

			if ( $obj->master )
			{
				/* Do not edit a master bit directly, but overload it */
				$clone = new \IPS\cms\Templates;
				$clone->key = $obj->key;
				$clone->title = \IPS\Request::i()->t_name;
				$clone->content = $content;
				$clone->location = \IPS\Request::i()->t_location;
				$clone->group = empty( \IPS\Request::i()->t_group ) ? null : \IPS\Request::i()->t_group;
				$clone->params = $variables;
				$clone->container = $obj->container;
				$clone->position = $obj->position;
				$clone->user_edited = 1;
				$clone->master = 0;
				$clone->save();
			}
			else
			{
				$obj->location = \IPS\Request::i()->t_location;
				$obj->group = empty( \IPS\Request::i()->t_group ) ? null : \IPS\Request::i()->t_group;
				$obj->title = \IPS\Request::i()->t_name;
				$obj->params = $variables;
				$obj->content = $content;
				$obj->user_edited = 1;
			}

			if( $description )
			{
				$obj->description = $description;
			}
			$obj->save();
			
			$url = array(
				't_location'  => $obj->location,
				't_group'     => $obj->group,
				't_key'       => $key
			);
		}
		catch( \Exception $ex )
		{
			\IPS\Output::i()->json( array( 'msg' => $ex->getMessage() ) );
		}
		
		if ( isset( \IPS\Request::i()->t_type ) and \IPS\Request::i()->t_type !== 'js' )
		{
			/* Test */
			try
			{
				\IPS\Theme::checkTemplateSyntax( $content, $variables );
			}
			catch( \Exception $e )
			{
				\IPS\Output::i()->json( array( 'msg' => \IPS\Member::loggedIn()->language()->get('cms_page_error_bad_syntax') ) );
			}
		}

		/* reload to return new item Id */
		$obj = \IPS\cms\Templates::load( $key );

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();
		
		/* Clear block caches */
		\IPS\cms\Blocks\Block::deleteCompiled();

		\IPS\Session::i()->log( 'acplogs__cms_template_save', array( \IPS\Request::i()->t_name => false ) );
		
		if(  \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'template_id' => $obj->id, 'template_title' => $obj->title, 'template_container' => $obj->container, 'template_user_added' => $obj->user_created ) );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=pages&controller=templates&' . implode( '&', $url ) ), 'completed' );
		}
	}
	
		
	/**
	 * Display template options for a database template group
	 *
	 * @return string
	 */
	public function databaseTemplateGroupOptions()
	{
		$form = new \IPS\Helpers\Form();
		
		$databases = array();
		foreach( \IPS\Db::i()->select( '*', 'cms_databases', array( 'database_template_featured=? or database_template_listing=? or database_template_display=? or database_template_categories=? or database_template_form=?', \IPS\Request::i()->group, \IPS\Request::i()->group, \IPS\Request::i()->group, \IPS\Request::i()->group, \IPS\Request::i()->group ) ) as $database )
		{
			$databases[ $database['database_id'] ] = \IPS\cms\Databases::constructFromData( $database );
		}
		
		if ( \count( $databases ) )
		{
			$names = array();
			foreach( $databases as $db )
			{
				$names[] = $db->_title;
			}
			
			$form->addMessage( \IPS\Member::loggedIn()->language()->addToStack( 'cms_database_template_used_in', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $names ) ) ) ), 'ipsMessage ipsMessage_info' );
		}
		
		$form->add( new \IPS\Helpers\Form\Text( 'cms_database_group_name', \IPS\Request::i()->group, NULL, array( 'regex' => '/^([a-z_][a-z0-9_]+?)?$/' ), function( $val ) {
			/* PHP Keywords cannot be used as template names - so make sure the full template name is not in the list */
			$keywords = array( 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'interface', 'isset', 'list', 'namespace', 'new', 'or', 'print', 'private', 'protected', 'public', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor' );
			
			if ( \in_array( $val, $keywords ) )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'template_reserved_word', FALSE, array( 'htmlsprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $keywords ) ) ) ) );
			}
			
			if ( mb_strtolower( str_replace( ' ', '_', $val ) ) != mb_strtolower( str_replace( ' ', '_', \IPS\Request::i()->group ) ) )
			{
				$count = \IPS\Db::i()->select( 'COUNT(*)', 'cms_templates', array( "LOWER(template_group)=?", mb_strtolower( str_replace( ' ', '_', $val ) ) ) )->first();
				if ( $count )
				{
					throw new \DomainException( 'cms_template_group_exists' );
				}
			}
		} ) );
		$form->addButton( "delete", "link", \IPS\Http\Url::internal( 'app=cms&module=pages&controller=templates&do=deleteTemplateGroup&group=' . \IPS\Request::i()->group . '&t_location=' . \IPS\Request::i()->t_location )->csrf(), 'ipsButton ipsButton_negative', array( 'data-confirm' => 'true' ) );
		
		if ( $values = $form->values() )
		{
			$new = str_replace( ' ', '_', mb_strtolower( $values['cms_database_group_name'] ) );

			if ( $new != \IPS\Request::i()->group )
			{
				\IPS\Db::i()->update( 'cms_templates', array( 'template_group' => $new ), array( 'template_location=? and template_group=?', 'database', \IPS\Request::i()->group ) );
				
				foreach( \IPS\cms\Templates::$databaseDefaults as $field => $template )
				{
					\IPS\Db::i()->update( 'cms_databases', array( 'database_template_' . $field => mb_strtolower( $new ) ), array( 'database_template_' . $field . ' =?', \IPS\Request::i()->group ) );
				}

				unset( \IPS\Data\Store::i()->cms_databases );

				$this->findAndUpdateTemplates( $new, \IPS\Request::i()->group );
			}
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=pages&controller=templates' . ( isset( \IPS\Request::i()->t_location ) ? '&t_location=' . \IPS\Request::i()->t_location : '' ) ), 'saved' );
		}
		
		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->block( \IPS\Member::loggedIn()->language()->addToStack('cms_template_database_options'), $form, FALSE, '', NULL, TRUE );
	}

	/**
	 * Find templates referencing a template and update them
	 *
	 * @param	string	$new	New template group name
	 * @param	string	$old	Old template group name
	 * @return	void
	 */
	protected function findAndUpdateTemplates( $new, $old )
	{
		\IPS\Session::i()->csrfCheck();
		
		foreach( \IPS\Db::i()->select( '*', 'cms_templates', array( 'template_content LIKE ?', '%' . $old . '%' ) ) as $template )
		{
			/* Make sure template tags call the correct group */
			if ( mb_stristr( $template['template_content'], '{template' ) )
			{
				preg_match_all( '/\{([a-z]+?=([\'"]).+?\\2 ?+)}/', $template['template_content'], $matches, PREG_SET_ORDER );

				/* Work out the plugin and the values to pass */
				foreach( $matches as $index => $array )
				{
					preg_match_all( '/(.+?)=' . $array[ 2 ] . '(.+?)' . $array[ 2 ] . '\s?/', $array[ 1 ], $submatches );

					$plugin = array_shift( $submatches[ 1 ] );
					if ( $plugin == 'template' )
					{
						$value   = array_shift( $submatches[ 2 ] );
						$options = array();

						foreach ( $submatches[ 1 ] as $k => $v )
						{
							$options[ $v ] = $submatches[ 2 ][ $k ];
						}

						if ( isset( $options['app'] ) and $options['app'] == 'cms' and isset( $options['location'] ) and $options['location'] == 'database' and isset( $options['group'] ) and $options['group'] == mb_strtolower( $old ) )
						{
							$replace = '{template="' . $value . '" app="' . $options['app'] . '" location="' . $options['location'] . '" group="' . mb_strtolower( $new ) . '" params="' . ( isset( $options['params'] ) ? $options['params'] : NULL ) . '"}';

							\IPS\Db::i()->update( 'cms_templates', array( 'template_content' => str_replace( $matches[$index][0], $replace, $template['template_content'] ) ), array( 'template_id=?', $template['template_id'] ) );
						}
					}
				}
			}
		}
	}
	
	/**
	 * Delete the template group! OH NOES
	 *
	 * @return void
	 */
	public function deleteTemplateGroup()
	{
		\IPS\Session::i()->csrfCheck();
		
		foreach( \IPS\cms\Templates::$databaseDefaults as $field => $template )
		{
			\IPS\Db::i()->update( 'cms_databases', array( 'database_template_' . $field => $template ), array( 'database_template_' . $field . ' =?', \IPS\Request::i()->group ) );
		}
	
		\IPS\Db::i()->delete( 'cms_templates', array( 'template_location=? and template_group=?', 'database', \IPS\Request::i()->group ) );
		
		unset( \IPS\Data\Store::i()->cms_databases );

		\IPS\Session::i()->log( 'acplogs__cms_template_group_delete', array( \IPS\Request::i()->group => false ) );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=cms&module=pages&controller=templates' . ( isset( \IPS\Request::i()->t_location ) ? '&t_location=' . \IPS\Request::i()->t_location : '' ) ), 'deleted' );
	}
}