<?php
/**
 * @brief		Editor Toolbars
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		30 Apr 2013
 */

namespace IPS\core\modules\admin\editor;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Editor Toolbars
 */
class _toolbar extends \IPS\Dispatcher\Controller
{
	protected static $defaultPlugins = array(
		'basicstyles',
		'button',
		'clipboard',
		'colorbutton',
		'dialog',
		'dialogui',
		'divarea',
		'elementspath',
		'enterkey',
		'entities',
		'floatingspace',
		'floatpanel',
		'font',
		'htmlwriter',
		'indent',
		'indentblock',
		'indentlist',
		'index.html',
		'ipsautogrow',
		'ipsautolink',
		'ipsautosave',
		'ipscode',
		'ipscontextmenu',
		'ipsemoticon',
		'ipsimage',
		'ipslink',
		'ipsmentions',
		'ipspage',
		'ipspaste',
		'ipspreview',
		'ipsquote',
		'ipssource',
		'ipsspoiler',
		'ipsgiphy',
		'ipsstockreplies',
		'justify',
		'lineutils',
		'list',
		'listblock',
		'menu',
		'panel',
		'panelbutton',
		'removeformat',
		'richcombo',
		'sourcearea',
		'toolbar',
		'undo',
		'widget',	
	);
	
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'toolbar_manage' );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'customization/toolbars.css', 'core', 'admin' ) );
		\IPS\Output::i()->responsive = FALSE;
		parent::execute();
	}

	/**
	 * Editor Toolbars
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$config = array(
			'desktop'	=> array( array( 'name' => 'row1', 'items' => array() ) ),
			'tablet'	=> array( array( 'name' => 'row1', 'items' => array() ) ),
			'phone'		=> array( array( 'name' => 'row1', 'items' => array() ) ),
		);
		
		$_config = json_decode( \IPS\Settings::i()->ckeditor_toolbars, true );

		foreach ( array( 'desktop', 'tablet', 'phone' ) as $device )
		{
			if ( isset( $_config[ $device ] ) )
			{
				$config[ $device ] = $_config[ $device ];
			}
		}
		
		$dummy = new \IPS\Helpers\Form\Editor( 'editor', NULL, FALSE, array( 'autoSaveKey' => md5( mt_rand() ), 'allButtons' => TRUE ) );
						
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_editor_toolbar');
		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('admin_customization.js', 'core', 'admin') );
		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, array( \IPS\Http\Url::internal( 'applications/core/interface/ckeditor/ckeditor/skins/' . \IPS\Theme::i()->editor_skin . '/editor.css', 'none' ) ) );
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'customization' )->editor( $config, $dummy );
		
		\IPS\Output::i()->sidebar['actions'] = array(
			'add'	=> array(
				'icon'	=> 'plus-circle',
				'title'	=> 'editor_new_plugin',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=editor&controller=toolbar&do=addPlugin' ),
			),
			'revert'	=> array(
				'icon'	=> 'refresh',
				'title'	=> 'editor_revert',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=editor&controller=toolbar&do=revert" )->csrf(),
				'data'	=> array( 'confirm' => '', 'confirmSubMessage' => \IPS\Member::loggedIn()->language()->addToStack('editor_revert_confirm' ) )
			)
		);
	}

	/**
	 * Save
	 *
	 * @return	void
	 */
	protected function save()
	{
		\IPS\Session::i()->csrfCheck();
		
		$save = array();
		$toolbars = json_decode( \IPS\Request::i()->toolbars, TRUE );
				
		foreach ( array( 'desktop', 'tablet', 'phone' ) as $device )
		{
			$i = 1;
			foreach ( $toolbars[ $device ] as $row )
			{
				if ( !empty( $row ) )
				{
					$save[ $device ][] = array(
						'name'	=> "row{$i}",
						'items'	=> array_filter( $row )
					);
					$save[ $device ][] = '/';
					$i++;
				}
			}
		}

		\IPS\Settings::i()->changeValues( array( 'ckeditor_toolbars' => json_encode( $save ) ) );
	}
	
	/**
	 * Button Permissions
	 *
	 * @return	void
	 */
	protected function permissions()
	{
		/* Get current settings */
		$button = \IPS\Request::i()->button;
		$current = json_decode( \IPS\Settings::i()->ckeditor_permissions, TRUE );
		
		/* Get areas */
		$filteredAreas = array_filter( 
			\IPS\Application::allExtensions( 'core', 'EditorLocations', FALSE ), 
			function( $elem ) {
				return ( !isset( $elem::$buttonLocation ) OR $elem::$buttonLocation !== FALSE );
			} 
		);
		$areas = array_combine( 
			array_keys( $filteredAreas ), 
			array_map( 
				function( $v )
				{
					return 'editor__' . $v;
				}, 
				array_keys( $filteredAreas ) 
			) 
		);

		/* Build Form */
		$b64Button	= base64_encode( $button );
		$form = new \IPS\Helpers\Form;
		
		/* Custom Plugin */
		if ( preg_match( '/^custom-(\w{32})$/i', $button, $matches ) and !\IPS\IN_DEV and !\IPS\NO_WRITES )
		{
			if ( \IPS\CIC2 )
			{
				$code = file_get_contents( \IPS\SITE_FILES_PATH . "/ckeditor_custom/{$matches[0]}/plugin.js" );
	
				$option = file_exists( \IPS\SITE_FILES_PATH . "/ckeditor_custom/{$matches[0]}/dialogs/{$matches[0]}.js" );
				if ( $option )
				{
					$code = file_get_contents( \IPS\SITE_FILES_PATH . "/ckeditor_custom/{$matches[0]}/dialogs/{$matches[0]}.js" );
				}
			}
			else
			{
				$code = file_get_contents( \IPS\ROOT_PATH . "/applications/core/interface/ckeditor/ckeditor/plugins/{$matches[0]}/plugin.js" );
	
				$option = file_exists( \IPS\ROOT_PATH . "/applications/core/interface/ckeditor/ckeditor/plugins/{$matches[0]}/dialogs/{$matches[0]}.js" );
				if ( $option )
				{
					$code = file_get_contents( \IPS\ROOT_PATH . "/applications/core/interface/ckeditor/ckeditor/plugins/{$matches[0]}/dialogs/{$matches[0]}.js" );
				}
			}

			$type = 'inline';
			if ( mb_strpos( $code, 'ips.utils.defaultEditorPlugins.singleblock' ) !== FALSE )
			{
				$type = 'singleblock';
			}
			if ( mb_strpos( $code, 'ips.utils.defaultEditorPlugins.block' ) !== FALSE )
			{
				$type = 'block';
			}

			if ( $type == 'block' or $type == 'singleblock' )
			{
				preg_match( "/ips.utils.defaultEditorPlugins.{$type}\( '{$matches[0]}', '(.+?)', ([{\[].*[}\]]), (\".*\"), (true|false), (\".*\")/", $code, $matches2 );

				$attributes = array();
				foreach ( json_decode( $matches2[2] ) as $k => $v )
				{
					$attributes[] = "{$k}='{$v}'";
				}
				$attributes = \count( $attributes ) ? ( ' ' . implode( ' ', $attributes ) ) : '';
				$content = ( $matches2[4] === 'true' ) ? '{content}' : '';
				$matches2[3] = json_decode( $matches2[3] );
				$matches2[5] = json_decode( $matches2[5] );
				$code = "<{$matches2[1]}{$attributes}>{$matches2[3]}{$content}{$matches2[5]}</{$matches2[1]}>";
			}
			else
			{
				preg_match( "/ips.utils.defaultEditorPlugins.inline\( '{$matches[0]}', \"(.*)\"/", $code, $matches2 );
				$code = json_decode( '"' . $matches2[1] . '"' );

			}
			
			$form->addTab('custom_button');
			$form->add( new \IPS\Helpers\Form\Translatable( 'editor_button_name', NULL, TRUE, array( 'app' => 'core', 'key' => 'editorbutton_' . $matches[0] ) ) );
			$form->add( new \IPS\Helpers\Form\Upload( 'editor_button_image', NULL, FALSE, array( 'image' => TRUE, 'multiple' => FALSE, 'allowedFileTypes' => array( 'png' ), 'temporary' => TRUE ) ) );
			$form->add( new \IPS\Helpers\Form\Radio( 'editor_button_type', $type, TRUE, array( 'options' => array( 'inline' => 'editor_button_type_inline', 'singleblock' => 'editor_button_type_singleblock', 'block' => 'editor_button_type_block' ) ) ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'editor_button_option_on', $option, FALSE, array( 'togglesOn' => array( 'editor_button_option_label' ) ) ) );
			$form->add( new \IPS\Helpers\Form\Translatable( 'editor_button_option_label', NULL, FALSE, array( 'app' => 'core', 'key' => 'editoroption_' . $matches[0] ), NULL, NULL, NULL, 'editor_button_option_label' ) );			
			$form->add( new \IPS\Helpers\Form\Codemirror( 'editor_button_html', $code, TRUE, array( 'placeholder' => "<div class='{option}'>{content}</div>" ) ) );
			$form->addTab('permissions');
		}
		
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'editor_permission_groups', isset( $current[ $button ] ) ? $current[ $button ]['groups'] : '*', TRUE, array( 'options' => \IPS\Member\Group::groups(), 'parse' => 'normal', 'multiple' => TRUE, 'unlimited' => '*', 'unlimitedLang' => 'everyone', 'impliedUnlimited' => TRUE ), NULL, NULL, NULL, $b64Button ) );
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'editor_permission_areas', isset( $current[ $button ] ) ? $current[ $button ]['areas'] : '*', TRUE, array( 'options' => $areas, 'multiple' => TRUE, 'unlimited' => '*', 'unlimitedLang' => 'everywhere', 'impliedUnlimited' => TRUE ), NULL, NULL, NULL, 'l' . $b64Button ) );
		
		/* If this is a custom one, add a delete button */
		if ( \in_array( mb_strtolower( $button ), explode( ',', mb_strtolower( \IPS\Settings::i()->ckeditor_extraPlugins ) ) ) or preg_match( '/^custom-\w{32}$/i', $button ) )
		{
			$form->addButton( 'delete', 'link', (string) \IPS\Http\Url::internal( "app=core&module=editor&controller=toolbar&do=deletePlugin&key={$button}" ), 'ipsButton ipsButton_negative' );
		}
		
		/* Handle Saves */
		if ( $values = $form->values() )
		{
			if ( preg_match( '/^custom-(\w{32})$/i', $button, $matches ) and !\IPS\IN_DEV and !\IPS\NO_WRITES )
			{
				$this->_saveCustomPlugin( $matches[0], $values );
			}
			
			$current[ $button ] = array(
				'groups'	=> $values['editor_permission_groups'],
				'areas'		=> $values['editor_permission_areas'],
			);
			\IPS\Settings::i()->changeValues( array( 'ckeditor_permissions' => json_encode( $current ) ) );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();
			
			/* IPS Cloud Sync */
			\IPS\IPS::resyncIPSCloud('Updated editor configuration');

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=editor&controller=toolbar') );
		}
				
		/* Display */
		\IPS\Output::i()->title = \IPS\Request::i()->title;
		\IPS\Output::i()->output = $form;
		
	}
	
	/**
	 * Add Plugin
	 *
	 * @return	void
	 */
	protected function addPlugin()
	{
		/* If IN_DEV, we cannot manage plugins */
		if ( \IPS\IN_DEV )
		{
			\IPS\Output::i()->error( 'editor_in_dev', '1C120/1', 403, '' );
		}
		
		/* We also need writes */
		if ( \IPS\NO_WRITES )
		{
			\IPS\Output::i()->error( 'no_writes', '1C120/A', 403, '' );
		}
		
		/* Custom */
		$form = new \IPS\Helpers\Form;
		if ( \IPS\Request::i()->tab === 'custom' )
		{
			/* Build Form */
			$form->add( new \IPS\Helpers\Form\Translatable( 'editor_button_name', NULL, TRUE ) );
			$form->add( new \IPS\Helpers\Form\Upload( 'editor_button_image', NULL, TRUE, array( 'image' => TRUE, 'multiple' => FALSE, 'allowedFileTypes' => array( 'png' ), 'temporary' => TRUE ) ) );
			$form->add( new \IPS\Helpers\Form\Radio( 'editor_button_type', NULL, TRUE, array( 'options' => array( 'inline' => 'editor_button_type_inline', 'singleblock' => 'editor_button_type_singleblock', 'block' => 'editor_button_type_block' ) ) ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'editor_button_option_on', NULL, FALSE, array( 'togglesOn' => array( 'editor_button_option_label' ) ) ) );
			$form->add( new \IPS\Helpers\Form\Translatable( 'editor_button_option_label', NULL, FALSE, array(), NULL, NULL, NULL, 'editor_button_option_label' ) );
			$form->add( new \IPS\Helpers\Form\Codemirror( 'editor_button_html', NULL, TRUE, array( 'placeholder' => "<div class='{option}'>{content}</div>" ) ) );
			
			/* Handle Submissions */
			if ( $values = $form->values() )
			{
				/* Write */
				$key = 'custom-' . md5( mt_rand() );
				$this->_saveCustomPlugin( $key, $values );	
				
				/* Save */
				$extraPlugins = explode( ',', \IPS\Settings::i()->ckeditor_extraPlugins );
				$extraPlugins[] = $key;
				\IPS\Settings::i()->changeValues( array( 'ckeditor_extraPlugins' => implode( ',', array_filter( array_unique( $extraPlugins ) ) ) ) );

				/* Clear guest page caches */
				\IPS\Data\Cache::i()->clearAll();
				
				/* IPS Cloud Sync */
				\IPS\IPS::resyncIPSCloud('Added custom editor button');
				
				/* Redirect */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=editor&controller=toolbar' ), 'saved' );
			}
		}
		/* CKEditor Plugin */
		else
		{
			/* Build Form */
			if ( class_exists( 'ZipArchive', FALSE ) )
			{
				if ( !\IPS\CIC2 AND !is_writable( \IPS\ROOT_PATH . '/applications/core/interface/ckeditor/ckeditor/plugins' ) )
				{
					\IPS\Output::i()->error( 'editor_plugin_nowrite', '4C120/2', 403, '' );
				}
				$form->add( new \IPS\Helpers\Form\Upload( 'editor_plugin_zip', NULL, TRUE, array( 'allowedFileTypes' => array( 'zip' ), 'temporary' => TRUE ) ) );
				\IPS\Member::loggedIn()->language()->words['editor_plugin_zip_desc'] = sprintf( \IPS\Member::loggedIn()->language()->get( 'editor_plugin_zip_desc' ), \IPS\Helpers\Form\Editor::ckeditorVersion() );
			}
			else
			{
				$plugins = array();
				$path = \IPS\ROOT_PATH . '/applications/core/interface/ckeditor/ckeditor/plugins';
				if ( \IPS\CIC2 )
				{
					$path = \IPS\SITE_FILES_PATH . '/ckeditor_custom';
				}
				foreach ( new \DirectoryIterator( $path ) as $f )
				{
					if ( !$f->isDot() and $f->isDir() and $f !== 'index.html' and !\in_array( (string) $f, array_merge( static::$defaultPlugins, explode( ',', \IPS\Settings::i()->ckeditor_extraPlugins ) ) ) )
					{
						$plugins[ (string) $f ] = (string) $f;
					}
				}
				$form->add( new \IPS\Helpers\Form\Select( 'editor_plugin_folder', NULL, TRUE, array( 'options' => $plugins ) ) );
				\IPS\Member::loggedIn()->language()->words['editor_plugin_folder_desc'] = sprintf( \IPS\Member::loggedIn()->language()->get( 'editor_plugin_folder_desc' ), \IPS\Helpers\Form\Editor::ckeditorVersion() );
			}
			
			/* Handle Submissions */
			if ( $values = $form->values() )
			{
				/* Get the folder */
				if ( isset( $values['editor_plugin_zip'] ) )
				{									
					/* Not all plugins are created equal - some are created with the contents of the zip file as pluginname/plugincontents, whereas others are just the plugin contents in the root */
					foreach( \IPS\File::normalizeFilesArray( $_FILES['editor_plugin_zip'] ) AS $file )
					{
						$fileName = $file['name'];
						break;
					}
					
					$tmpName = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
					move_uploaded_file( $values['editor_plugin_zip'], $tmpName );
					
					/* Get the plugin name */
					$inRoot = TRUE;
					$zip = zip_open( $tmpName );
					while( $resource = zip_read( $zip ) )
					{
						$name = mb_substr( zip_entry_name( $resource ), 0, -1 );
						$name = explode( '/', $name );
						$name = array_shift( $name );
						if ( strstr( mb_strtolower( $fileName ), mb_strtolower( $name ) ) !== FALSE )
						{
							$inRoot = FALSE;
							break;
						}
					}
					zip_close( $zip );
					
					/* Cloud2 handles extraction */
					if ( \IPS\CIC2 )
					{
						\IPS\Cicloud\file( "IPScustomcke_{$name}.zip", \file_get_contents( $tmpName ) );
						
						do
						{
							if ( isset( \IPS\Data\Store::i()->syncCompleted ) AND \IPS\Data\Store::i()->syncCompleted )
							{
								break;
							}
						}
						while( TRUE );

						$pluginDirectory = \IPS\SITE_FILES_PATH . '/ckeditor_custom/' . $name;
					}
					else
					{
						/* If the content of the plugin is in the root of the zip, then we need to manually create the folder and key */
						$extractTo = \IPS\ROOT_PATH . '/applications/core/interface/ckeditor/ckeditor/plugins';
						if ( $inRoot === TRUE )
						{
							/* Remove the .zip extension from the filename */
							$name = explode( '_', $fileName );
							$name = array_shift( $name );
							$extractTo .= '/' . $name;
						}
						
						$pluginDirectory = \IPS\ROOT_PATH . '/applications/core/interface/ckeditor/ckeditor/plugins/' . $name;
	
						/* Check it isn't already installed */
						if ( \in_array( $name, array_merge( static::$defaultPlugins, explode( ',', \IPS\Settings::i()->ckeditor_extraPlugins ) ) ) )
						{
							$form->error = \IPS\Member::loggedIn()->language()->addToStack('editor_plugin_already_installed');
							goto displayForm;
						}
						
						/* Extract it */
						$zip = new \ZipArchive;
						$zip->open( $tmpName );
						$zip->extractTo( $extractTo );
						$zip->close();
						
						/* Delete the temp file */
						unlink( $tmpName );
					}
				}
				else
				{
					$name = $values['editor_plugin_folder'];
					
					$path = \IPS\ROOT_PATH . '/applications/core/interface/ckeditor/ckeditor/plugins/' . $name . '/plugin.js';
					if ( \IPS\CIC2 )
					{
						$path = \IPS\SITE_FILES_PATH . '/ckeditor_custom/' . $name . '/plugin.js';
					}
					if ( !file_exists( $path ) )
					{
						\IPS\Output::i()->error( 'editor_bad_plugin_directory', '1C120/9', 400, '' );
					}
				}
				
				unset( \IPS\Data\Store::i()->syncCompleted );
				
				/* See if we can sniff out requirements for this plugin. */
				$path = \IPS\ROOT_PATH . '/applications/core/interface/ckeditor/ckeditor/plugins/' . $name . '/plugin.js';
				if ( \IPS\CIC2 )
				{
					$path = \IPS\SITE_FILES_PATH . '/ckeditor_custom/' . $name . '/plugin.js';
				}
				if ( !file_exists( $path ) )
				{
					/* If we're on Cloud2, sleep for a few seconds to make sure the plugin isn't just still being transferred */
					if( \IPS\CIC2 )
					{
						sleep(5);
					}

					if( !file_exists( $path ) )
					{
						/* If we just extracted some files remove them as the plugin was apparently not valid */
						if ( isset( $values['editor_plugin_zip'] ) )
						{
							if( \is_dir( $pluginDirectory ) )
							{
								if ( \IPS\CIC2 )
								{
									\IPS\Cicloud\folderDelete( 'ckeditor_custom/' . $name );
								}
								else
								{
									try
									{
										$iterator = new \RecursiveDirectoryIterator( $pluginDirectory, \FilesystemIterator::SKIP_DOTS );
										foreach ( new \RecursiveIteratorIterator( $iterator, \RecursiveIteratorIterator::CHILD_FIRST ) as $file )
										{  
											if ( $file->isDir() )
											{  
												@rmdir( $file->getPathname() );  
											}
											else
											{  
												@unlink( $file->getPathname() );  
											}  
										}
										$handle = opendir( $pluginDirectory );
										closedir ( $pluginDirectory );
										@rmdir( $pluginDirectory );
									}
									catch( \UnexpectedValueException $e ){}
								}
							}
						}

						\IPS\Output::i()->error( 'editor_bad_plugin', '1C120/B', 400, '' );
					}
				}

				$file = \file_get_contents( $path );
				preg_match( "#requires: \'(.+?)\',#i", $file, $matches );
				if ( isset( $matches[1] ) )
				{
					$required = explode( ',', $matches[1] );
					
					$rewriteFileWithIpsContextMenuRequirement = FALSE;
					if ( \in_array( 'contextmenu', $required ) )
					{
						$required[ array_search( 'contextmenu', $required ) ] = 'ipscontextmenu';
						$rewriteFileWithIpsContextMenuRequirement = TRUE;
					}
					
					$current = array_merge( static::$defaultPlugins, explode( ',', \IPS\Settings::i()->ckeditor_extraPlugins ) );
					$missing = array_diff( $required, $current );
					if ( \count( $missing ) > 0 )
					{
						\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->addToStack( 'editor_missing_requirements', FALSE, array( 'sprintf' => array( \count( $missing ), implode( ', ', $missing ) ) ) ), '2C120/5', 404, '' );
					}
					
					if ( $rewriteFileWithIpsContextMenuRequirement )
					{
						$file = preg_replace_callback( "#requires: \'(.+?)\',#i", function( $matches )
						{
							return str_replace( 'contextmenu', 'ipscontextmenu', $matches[0] );
						}, $file );

						if( \IPS\CIC2 )
						{
							sleep(5); // @Mark
							\IPS\Cicloud\file( 'plugin.js', $file, 'ckeditor_custom/' . $name );
						}
						else
						{
							\file_put_contents( \IPS\ROOT_PATH . '/applications/core/interface/ckeditor/ckeditor/plugins/' . $name . '/plugin.js', $file );	
						}				
					}
				}
								
				/* Save */
				$extraPlugins = explode( ',', \IPS\Settings::i()->ckeditor_extraPlugins );
				$extraPlugins[] = $name;
				
				\IPS\Settings::i()->changeValues( array( 'ckeditor_extraPlugins' => implode( ',', array_filter( array_unique( $extraPlugins ) ) ) ) );

				/* Clear guest page caches */
				\IPS\Data\Cache::i()->clearAll();
				
				/* IPS Cloud Sync */
				\IPS\IPS::resyncIPSCloud('Added CKEditor plugin');

				/* Redirect */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=editor&controller=toolbar' ), 'saved' );
			}
		}
				
		/* If this is an AJAX request, just return it */
		displayForm:
		if( \IPS\Request::i()->isAjax() )
		{
			if ( \IPS\Request::i()->existing )
			{
				\IPS\Output::i()->output = $form;
				return;
			}
		}
		
		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('editor_new_plugin');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global' )->tabs( array( 'ckeditor' => \IPS\Member::loggedIn()->language()->addToStack('editor_button_ckeditor'), 'custom' => \IPS\Member::loggedIn()->language()->addToStack('editor_button_custom') ), \IPS\Request::i()->tab ?: 'ckeditor', $form, \IPS\Http\Url::internal( "app=core&module=editor&controller=toolbar&do=addPlugin&existing=1" ) );
	}
	
	/**
	 * Save custom plugin
	 *
	 * @param	string	$key	The key
	 * @param	array	$values	Values from form
	 * @return	void
	 */
	protected function _saveCustomPlugin( $key, $values )
	{
		/* Work out the plugin file contents */
		if ( $values['editor_button_type'] === 'block' or $values['editor_button_type'] === 'singleblock' )
		{
			if ( !preg_match( '/^<(.+?)([^>]*?)>(.*?)<\/\1>$/s', trim( $values['editor_button_html'] ), $matches ) )
			{
				$values['editor_button_html'] = "<div>{$values['editor_button_html']}</div>";
				preg_match( '/^<(.+?)([^>]*?)>(.*?)<\/\1>$/s', trim( $values['editor_button_html'] ), $matches );
			}

			$attributes = array();

			if ( $matches[2] )
			{
				preg_match_all( '/(.+?)=([\'"])(.+?)\\2/', trim( $matches[2] ), $attribMatches );

				foreach ( $attribMatches[1] as $k => $v )
				{
					$attributes[ trim( $v ) ] = $attribMatches[3][ $k ];
				}
			}

			/* Figure out css classes */
			$cssClasses = array();
			preg_match_all( '/class=([\'"])(.+?)\\1/i', trim( $matches[0] ), $cssMatches );

			if( $cssMatches[2] )
			{
				foreach ( $cssMatches[2] as $match )
				{
					$classes = explode( ' ', $match );
					$cssClasses = array_merge( $cssClasses, $classes );
				}
			}

			/* Automatically detect and allow custom css classes */
			if( \count( $cssClasses ) )
			{
				$settingClasses = \IPS\Settings::i()->editor_allowed_classes ? explode( ',', \IPS\Settings::i()->editor_allowed_classes ) : array();
				$settingClasses = array_unique( array_merge( $settingClasses, $cssClasses ) );

				\IPS\Settings::i()->changeValues( array( 'editor_allowed_classes' => implode( ',', $settingClasses ) ) );
			}

			/* Figure out data-controllers classes */
			$dataControllers = array();
			preg_match_all( '/data-controller=([\'"])(.+?)\\1/i', trim( $matches[0] ), $controllerMatches );

			if( $controllerMatches[2] )
			{
				foreach ( $controllerMatches[2] as $match )
				{
					$controllers = array_map( 'trim', explode( ',', $match ) );
					$dataControllers = array_merge( $dataControllers, $controllers );
				}
			}

			/* Automatically detect and allow custom css classes */
			if( \count( $dataControllers ) )
			{
				$settingControllers = \IPS\Settings::i()->editor_allowed_datacontrollers ? explode( ',', \IPS\Settings::i()->editor_allowed_datacontrollers ) : array();
				$settingControllers = array_unique( array_merge( $settingControllers, $dataControllers ) );

				\IPS\Settings::i()->changeValues( array( 'editor_allowed_datacontrollers' => implode( ',', $settingControllers ) ) );
			}

			$attributes = json_encode( $attributes );

			$contentPos = mb_strpos( $matches[3], '{content}' );
			if ( $contentPos === FALSE )
			{
				$before = json_encode( $matches[3] );
				$content = 'false';
				$after = '""';
			}
			else
			{
				$before = json_encode( mb_substr( $matches[3], 0, $contentPos ) );
				$content = 'true';
				$after = json_encode( mb_substr( $matches[3], $contentPos + 9 ) );
			}
			
			$dialogFile = NULL;
			$dialogLine = '';
			if ( $values['editor_button_option_on'] )
			{
				\IPS\Lang::saveCustom( 'core', "editoroption_{$key}", $values['editor_button_option_label'], TRUE );
				
				$command = "new CKEDITOR.dialogCommand( '{$key}' )";
				$dialogLine = "CKEDITOR.dialog.add( '{$key}', this.path + 'dialogs/{$key}.js' );";
				
				$dialogFile = <<<JS
CKEDITOR.dialog.add( '{$key}', function( editor ) {
return {
title: ips.getString('editorbutton_{$key}'),
minWidth: 400,
minHeight: 200,
contents: [
    {
        id: 'tab',
        label: ips.getString('editorbutton_{$key}'),
        elements: [
            {
                type: 'text',
                id: 'option',
                label: ips.getString('editoroption_{$key}')
            }
        ]
    }
],
onOk: function() {
    var cmd = ips.utils.defaultEditorPlugins.{$values['editor_button_type']}( '{$key}', '{$matches[1]}', {$attributes}, {$before}, {$content}, {$after}, this.getValueOf( 'tab', 'option' ) );
    cmd.exec( editor );
}
};
});
JS;
			}
			else
			{
				$command = "ips.utils.defaultEditorPlugins.{$values['editor_button_type']}( '{$key}', '{$matches[1]}', {$attributes}, {$before}, {$content}, {$after} )";
			}
			
			
			$pluginFile = <<<JS
(function() {
CKEDITOR.plugins.add( '{$key}', {
icons: '{$key}',
init: function( editor ) {
	editor.addCommand( '{$key}', {$command} );
	editor.ui.addButton && editor.ui.addButton( '{$key}', {
		label: ips.getString('editorbutton_{$key}'),
		command: '{$key}',
		toolbar: ''
	});
	{$dialogLine}
}
});
})();
JS;

		}
		else
		{
			$dialogLine = '';
			$dialogFile = NULL;
			$html = json_encode( $values['editor_button_html'] );
			
			if ( $values['editor_button_option_on'] )
			{
				\IPS\Lang::saveCustom( 'core', "editoroption_{$key}", $values['editor_button_option_label'], TRUE );
				
				$command = "new CKEDITOR.dialogCommand( '{$key}' )";
				$dialogLine = "CKEDITOR.dialog.add( '{$key}', this.path + 'dialogs/{$key}.js' );";
				
				$dialogFile = <<<JS
CKEDITOR.dialog.add( '{$key}', function( editor ) {
return {
title: ips.getString('editorbutton_{$key}'),
minWidth: 400,
minHeight: 200,
contents: [
    {
        id: 'tab',
        label: ips.getString('editorbutton_{$key}'),
        elements: [
            {
                type: 'text',
                id: 'option',
                label: ips.getString('editoroption_{$key}')
            }
        ]
    }
],
onOk: function() {
    var cmd = ips.utils.defaultEditorPlugins.inline( '{$key}', {$html}.replace( /\{option\}/g, this.getValueOf( 'tab', 'option' ) ) );
    cmd.exec( editor );
}
};
});
JS;
			}
			else
			{
				$command = "ips.utils.defaultEditorPlugins.inline( '{$key}', {$html} )";
			}
				
			$pluginFile = <<<JS
(function() {
CKEDITOR.plugins.add( '{$key}', {
icons: '{$key}',
init: function( editor ) {
	editor.addCommand( '{$key}', {$command} );
	editor.ui.addButton && editor.ui.addButton( '{$key}', {
		label: ips.getString('editorbutton_{$key}'),
		command: '{$key}',
		toolbar: ''
	});
	{$dialogLine}
}
});
})();
JS;

		}
										
		/* Write it */
		if ( \IPS\CIC2 )
		{
			\IPS\Cicloud\file( 'plugin.js', $pluginFile, "ckeditor_custom/{$key}" );
			if ( $values['editor_button_image'] )
			{
				\IPS\Cicloud\file( "{$key}.png", \file_get_contents( $values['editor_button_image'] ), "ckeditor_custom/{$key}/icons" );
			}
			
			if ( $dialogFile )
			{
				\IPS\Cicloud\file( "{$key}.js", $dialogFile, "ckeditor_custom/{$key}/dialogs" );
			}
		}
		else
		{
			$dir = \IPS\ROOT_PATH . '/applications/core/interface/ckeditor/ckeditor/plugins/' . $key;
			if ( !is_dir( $dir ) )
			{
				mkdir( $dir );
				chmod( $dir, \IPS\IPS_FOLDER_PERMISSION );
			}
			\file_put_contents( $dir . '/plugin.js', $pluginFile );
			if ( $values['editor_button_image'] )
			{
				if ( !is_dir( $dir . '/icons' ) )
				{
					mkdir( $dir . '/icons' );
					chmod( $dir . '/icons', \IPS\IPS_FOLDER_PERMISSION );
				}
				\file_put_contents( "{$dir}/icons/{$key}.png", file_get_contents( $values['editor_button_image'] ) );
			}
			if ( $dialogFile )
			{
				if ( !is_dir( $dir . '/dialogs' ) )
				{
					mkdir( $dir . '/dialogs' );
					chmod( $dir . '/dialogs', \IPS\IPS_FOLDER_PERMISSION );
				}
				\file_put_contents( "{$dir}/dialogs/{$key}.js", $dialogFile );
			}
		}
		
		/* Save name */
		\IPS\Lang::saveCustom( 'core', "editorbutton_{$key}", $values['editor_button_name'], TRUE );
	}
	
	/**
	 * Delete Plugin
	 *
	 * @return	void
	 */
	public function deletePlugin()
	{
		/* Check */
		if ( \IPS\IN_DEV )
		{
			\IPS\Output::i()->error( 'editor_in_dev', '1C120/4', 403, '' );
		}
		if ( \IPS\NO_WRITES )
		{
			\IPS\Output::i()->error( 'no_writes', '1C120/C', 403, '' );
		}

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		/* Check it's not required for something else */
		foreach ( new \DirectoryIterator( \IPS\ROOT_PATH . '/applications/core/interface/ckeditor/ckeditor/plugins' ) as $file )
		{
			if ( $file->isDir() and mb_substr( $file, 0, 1 ) !== '.' and file_exists( \IPS\ROOT_PATH . '/applications/core/interface/ckeditor/ckeditor/plugins/' . $file . '/plugin.js' ) )
			{
				$jsFile = file_get_contents( \IPS\ROOT_PATH . '/applications/core/interface/ckeditor/ckeditor/plugins/' . $file . '/plugin.js' );
				if ( preg_match( '/requires:\"(.+?)\"/i', $jsFile, $matches ) )
				{
					if ( \in_array( mb_strtolower( \IPS\Request::i()->key ), explode( ',', mb_strtolower( $matches[1] ) ) ) )
					{
						\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->addToStack( 'editor_plugin_requirements', FALSE, array( 'sprintf' => array( $file ) ) ), '1C120/3', 403, '' );
					}
				}
			}
		}
		
		/* Delete it */
		$key = mb_strtolower( \IPS\Request::i()->key );
		$dir = \IPS\ROOT_PATH . '/applications/core/interface/ckeditor/ckeditor/plugins/' . $key;
		if ( is_dir( $dir ) )
		{
			$this->_recursiveRmDir( $dir );
		}
				
		/* Remove from plugin list */
		$extraPlugins = explode( ',', mb_strtolower( \IPS\Settings::i()->ckeditor_extraPlugins ) );
		foreach ( $extraPlugins as $k => $v )
		{
			if ( $v == $key )
			{
				unset( $extraPlugins[ $k ] );
			}
		}
		\IPS\Settings::i()->changeValues( array( 'ckeditor_extraPlugins' => implode( ',', array_filter( array_unique( $extraPlugins ) ) ) ) );

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();
		
		/* IPS Cloud Sync */
		\IPS\IPS::resyncIPSCloud('Deleted editor plugin');

		/* Redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=editor&controller=toolbar' ), 'saved' );
	}
	
	/**
	 * Revert Editor back to default
	 *
	 * @return	void
	 */
	public function revert()
	{
		\IPS\Session::i()->csrfCheck();
		
		if ( !empty( \IPS\Settings::i()->ckeditor_extraPlugins ) and !\IPS\NO_WRITES )
		{
			$extraPlugins	= explode( ',', \IPS\Settings::i()->ckeditor_extraPlugins );
			$dir			= \IPS\ROOT_PATH . '/applications/core/interface/ckeditor/ckeditor/plugins/';
			foreach( $extraPlugins AS $key )
			{
				if ( \IPS\CIC2 )
				{
					if ( is_dir( \IPS\SITE_FILES_PATH . '/ckeditor_custom/' . $key ) )
					{
						/* If this is cloud we only need the key */
						$this->_recursiveRmDir( $key );
					}
				}
				else
				{
					if ( is_dir( $dir . $key ) )
					{
						$this->_recursiveRmDir( $dir . $key );
					}
				}
			}
			
			\IPS\Settings::i()->changeValues( array( 'ckeditor_extraPlugins' => '' ) );
		}
		
		\IPS\Settings::i()->changeValues( array( 'ckeditor_toolbars' => '' ) );

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();
		
		/* IPS Cloud Sync */
		\IPS\IPS::resyncIPSCloud('Reverted editor configuration');

		/* Redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=editor&controller=toolbar" ), 'saved' );
	}
	
	/**
	 * Recursive Subdirectory Removal
	 *
	 * @param	$dir	The directory
	 * @return	void
	 */
	protected function _recursiveRmDir( $dir )
	{
		if ( is_dir( $dir ) )
		{
			foreach( new \GlobIterator( $dir . '/*' ) AS $file )
			{
				if ( $file->isDir() )
				{
					$this->_recursiveRmDir( $dir . '/' . $file->getFilename() );
				}
				else
				{
					@unlink( $dir . '/' . $file->getFilename() );
				}
			}

			$handle = opendir( $dir );
			closedir ( $handle );
			@rmdir( $dir );
		}
	}
}