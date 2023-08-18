<?php
/**
 * @brief		File Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		06 May 2013
 */

namespace IPS\core\modules\admin\overview;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Settings
 */
class _files extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;

	/**
	 * Manage Attachment Types
	 *
	 * @return	void
	 */
	protected function manage()
	{		
		\IPS\Dispatcher::i()->checkAcpPermission( 'files_view' );
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('uploaded_files');
		
		\IPS\Output::i()->sidebar['actions'] = array();

		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'overview', 'files_settings' ) )
		{
			\IPS\Output::i()->sidebar['actions']['settings'] = array(
				'icon'	=> 'cog',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=overview&controller=files&do=settings' ),
				'title'	=> 'storage_settings',
			);

			\IPS\Output::i()->sidebar['actions']['images'] = array(
				'icon'	=> 'cog',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=overview&controller=files&do=imagesettings' ),
				'title'	=> 'image_settings',
			);
		}

		/*		@todo - This needs fixing but has been temporarily been disabled
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'overview', 'orphaned_files' ) )
		{
			\IPS\Output::i()->sidebar['actions']['orphaned'] = array(
				'icon'	=> 'cog',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=overview&controller=files&do=orphaned' ),
				'title'	=> 'orphaned_files',
				'data'	=> array( 'confirm' => '', 'confirmMessage' => \IPS\Member::loggedIn()->language()->addToStack('orphaned_files_confirm') )
			);
		}*/
		
		$table = new \IPS\Helpers\Table\Db( 'core_attachments', \IPS\Http\Url::internal( 'app=core&module=overview&controller=files' ) );
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'dashboard' ), 'fileTable' );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'dashboard' ), 'fileTableRows' );
		$table->filters = array(
			'images'	=> "attach_is_image=1",
			'files'		=> "attach_is_image=0",
		);

		$table->include = array( 'attach_id', 'attach_file', 'attach_filesize', 'attach_date', 'attach_member_id' );
		$table->rowClasses	= array( 'attach_file' => array( 'ipsTable_wrap' ) );

		if ( $table->filter !== 'images' )
		{
			$table->include[] = 'attach_hits';
		}
		$table->noSort = array( 'attach_type', 'attach_id' );
		$table->quickSearch = 'attach_file';
		$table->parsers = array(
			'attach_file'	=> function( $val, $row ) use ( $table )
			{
				if ( $row['attach_is_image'] and $table->filter === 'images' )
				{
					$url = \IPS\Http\Url::external( \IPS\File::get( 'core_Attachment', $row['attach_location'] )->url );
					return "<a href='{$url}' target='_blank' rel='noopener'><img src='{$url}' style='max-height:200px'></a>";
				}
				else
				{
					$url = \IPS\Http\Url::external( \IPS\Settings::i()->base_url . "applications/core/interface/file/attachment.php" )->setQueryString( 'id', $row['attach_id'] );
					if ( $row['attach_security_key'] )
					{
						$url = $url->setQueryString( 'key', $row['attach_security_key'] );
					}
					$val = htmlentities( $val, ENT_DISALLOWED, 'UTF-8', FALSE );
					return "<a href='{$url}' target='_blank' rel='noreferrer'>{$val}</a>";
				}
			},
			'attach_filesize' => function( $val )
			{
				if ( $val < 1024 )
				{
					return "{$val}B";
				}
				elseif ( $val < 1048576 )
				{
					return round( ( $val / 1024 ), 2 ) . 'KB';
				}
				elseif ( $val < 1073741824 )
				{
					return round( ( $val / 1048576 ), 2 ) . 'MB';
				}
				else
				{
					return round( ( $val / 1073741824 ), 2 ) . 'GB';
				}
			},
			'attach_date' => function( $val )
			{
				return \IPS\DateTime::ts( $val );
			},
			'attach_hits' => function( $val, $row )
			{
				return $row['attach_is_image'] ? '' : $val;
			},
			'attach_member_id' => function( $val )
			{
				if ( $val == 0 )
				{
					return \IPS\Member::load( $val )->name;
				}
				else
				{
					return "<a href='" . \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=view&id=' . $val ) . "'>" . htmlentities( \IPS\Member::load( $val )->name, ENT_DISALLOWED, 'UTF-8', FALSE ) . '</a>';
			
				}
			}
		);
		$table->advancedSearch = array(
			'attach_file'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'attach_ext'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'attach_hits'		=> \IPS\Helpers\Table\SEARCH_NUMERIC,
			'attach_date'		=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'attach_member_id'	=> \IPS\Helpers\Table\SEARCH_MEMBER,
			'attach_filesize'	=> \IPS\Helpers\Table\SEARCH_NUMERIC,
		);
		$table->rowButtons = function( $row )
		{
			$buttons = array();
			$buttons['view'] = array(
				'icon'	=> 'search',
				'title'	=> 'attach_view_locations',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=lookup&id={$row['attach_id']}" ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => $row['attach_file'] )
			);
			
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'overview', 'files_delete' ) )
			{
				$buttons['delete'] = array(
					'icon'	=> 'times-circle',
					'title'	=> 'delete',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=delete&id={$row['attach_id']}" ),
					'data'		=> array( 'delete' => '' ),
				);
			}
			
			return $buttons;
		};

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'dashboard/files.css', 'core', 'admin' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_files.js', 'core', 'admin' ) );
		\IPS\Output::i()->output = (string) $table;
	}
	
	/**
	 * Lookup attachment locations
	 *
	 * @return	void
	 */
	public function lookup()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'files_view' );
		
		$loadedExtensions = array();
		$locations = array();
		
		foreach ( \IPS\Db::i()->select( '*', 'core_attachments_map', array( 'attachment_id=?', \intval( \IPS\Request::i()->id ) ) ) as $map )
		{
			if ( !isset( $loadedExtensions[ $map['location_key'] ] ) )
			{
				$exploded = explode( '_', $map['location_key'] );
				try
				{
					$extensions = \IPS\Application::load( $exploded[0] )->extensions( 'core', 'EditorLocations' );
					if ( isset( $extensions[ $exploded[1] ] ) )
					{
						$loadedExtensions[ $map['location_key'] ] = $extensions[ $exploded[1] ];
					}
				}
				catch ( \OutOfRangeException $e ){ }
			}
			
			if ( isset( $loadedExtensions[ $map['location_key'] ] ) AND method_exists( $loadedExtensions[ $map['location_key'] ], 'attachmentLookup' ) )
			{
				try
				{
					if ( $url = $loadedExtensions[ $map['location_key'] ]->attachmentLookup( $map['id1'], $map['id2'], $map['id3'] ) )
					{
						$locations[] = $url;
					}
				}
				catch ( \LogicException $e ) { }
			}
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( NULL, \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->attachmentLocations( $locations, FALSE ), TRUE, 'ipsPad' );
	}
	
	/**
	 * Delete attachment
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'files_delete' );

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		try
		{
			$attachment = \IPS\Db::i()->select( '*', 'core_attachments', array( 'attach_id=?', \IPS\Request::i()->id ) )->first();
			
			try
			{
				\IPS\File::get( 'core_Attachment', $attachment['attach_location'] )->delete();
				\IPS\File::get( 'core_Attachment', $attachment['attach_thumb_location'] )->delete();
			}
			catch ( \Exception $e ) { }
			
			\IPS\Db::i()->delete( 'core_attachments', array( 'attach_id=?', \IPS\Request::i()->id ) );
			
			\IPS\Session::i()->log( 'acplogs__file_deleted', array( $attachment['attach_file'] => FALSE ) );
		}
		catch ( \UnderflowException $e ) { }
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=overview&controller=files" ) );
	}

	/**
	 * Image Settings
	 * 
	 * @return	void
	 */
	protected function imagesettings()
	{
		/* Init */
		\IPS\Dispatcher::i()->checkAcpPermission( 'files_settings' );

		$form = new \IPS\Helpers\Form;

		$form->add( new \IPS\Helpers\Form\Radio( 'image_suite', class_exists( 'Imagick', FALSE ) ? \IPS\Settings::i()->image_suite : 'gd', TRUE, array(
			'options' => array( 'gd' => 'imagesuite_gd', 'imagemagick' => 'imagesuite_imagemagick' ),
			'toggles' => array( 'imagemagick' => array( 'image_jpg_quality', 'imagick_strip_exif' ), 'gd' => array( 'image_jpg_quality', 'image_png_quality_gd' ) ),
			'disabled'=> class_exists( 'Imagick', FALSE ) ? array() : array( 'imagemagick' )
		) ) );

		$form->add( new \IPS\Helpers\Form\Number( 'image_jpg_quality', \IPS\Settings::i()->image_jpg_quality, FALSE, array( 'min' => 0, 'max' => 100, 'range' => TRUE, 'step' => 1 ), NULL, NULL, NULL, 'image_jpg_quality' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'image_png_quality_gd', \IPS\Settings::i()->image_png_quality_gd, FALSE, array( 'min' => 0, 'max' => 9, 'range' => TRUE, 'step' => 1 ), NULL, NULL, NULL, 'image_png_quality_gd' ) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'imagick_strip_exif', \IPS\Settings::i()->imagick_strip_exif, FALSE, array(), NULL, NULL, NULL, 'imagick_strip_exif' ) );

		if ( $values = $form->values() )
		{
			$form->saveAsSettings();

			\IPS\Session::i()->log( 'acplogs__image_settings_updated' );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=overview&controller=files&do=imagesettings' ), 'saved' );
		}

		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('image_settings');
		\IPS\Output::i()->output = $form;
	}

	/**
	 * Upload Settings
	 * 
	 * @return	void
	 */
	protected function settings()
	{
		/* Init */
		\IPS\Dispatcher::i()->checkAcpPermission( 'files_settings' );
		$activeTab = isset( \IPS\Request::i()->tab ) ? \IPS\Request::i()->tab : 'settings';
				
		/* Settings form */
		if ( $activeTab === 'settings' )
		{
			$settings = json_decode( \IPS\Settings::i()->upload_settings, TRUE );
			
			$configurations	= array();
			$cicDisabled	= FALSE;

			if( \IPS\CIC )
			{
				$cicDisabled = array();
			}

			foreach ( \IPS\Db::i()->select( '*', 'core_file_storage' ) as $row )
			{
				$handlers	= \IPS\File::storageHandlers( $row );
				$classname	= $handlers[ $row['method'] ];
				$configurations[ $row['id'] ] = $classname::displayName( json_decode( $row['configuration'], TRUE ) );

				if( $row['method'] == 'FileSystem' AND \IPS\CIC )
				{
					$cicDisabled[ $row['id'] ] = $row['id'];
				}
			}
			
			$form = new \IPS\Helpers\Form;
			$form->addMessage( 'filestorage_move_info' );
			foreach ( \IPS\Application::allExtensions( 'core', 'FileStorage', FALSE, NULL, NULL, TRUE ) as $name => $obj )
			{
				$disabled = ( isset( $settings[ "filestorage__{$name}" ] ) and \is_array( $settings[ "filestorage__{$name}" ] ) ) ? TRUE : FALSE;
				$value    = NULL;
				
				if ( isset( $settings[ "filestorage__{$name}" ] ) )
				{
					if ( \is_array( $settings[ "filestorage__{$name}" ] ) )
					{
						$copyOfSettings = $settings[ "filestorage__{$name}" ];
						$value = array_shift( $copyOfSettings );
					}
					else
					{
						$value = $settings[ "filestorage__{$name}" ];
					}
				}

				$toggles = array();

				foreach( $configurations as $id => $title )
				{
					if( $id != $value )
					{
						$toggles[ $id ] = array( 'filestorage_move' );
					}
				}

				$handlerDisabled = $cicDisabled;

				if( isset( $handlerDisabled[ $value ] ) )
				{
					unset( $handlerDisabled[ $value ] );
				}

				$form->add( new \IPS\Helpers\Form\Select( 'filestorage__' . $name, (int) $value, TRUE, array( 'options' => $configurations, 'disabled' => $disabled ?: $handlerDisabled, 'toggles' => $toggles ) ) );
				
				if ( $disabled )
				{
					\IPS\Member::loggedIn()->language()->words[ 'filestorage__' . $name . '_warning' ] = \IPS\Member::loggedIn()->language()->addToStack( 'file_storage_move_in_progress' );
				}
			}

			$form->add( new \IPS\Helpers\Form\YesNo( 'filestorage_move', TRUE, FALSE, array(), NULL, NULL, NULL, 'filestorage_move' ) );
						
			if ( $values = $form->values() )
			{
				/* Block moves to filesystem on CIC */
				foreach ( $values as $k => $v )
				{
					if ( isset( $settings[ $k ] ) AND !\is_array( $settings[ $k ] ) )
					{
						if( $settings[ $k ] != $v AND $k != 'filestorage_move' AND \IPS\CIC AND \in_array( $v, $cicDisabled ) )
						{
							\IPS\Output::i()->error( 'file_storage_cic_filesystem', '3C158/7', 403, '' );
						}
					}
				}

				if( isset( $values['filestorage_move'] ) AND $values['filestorage_move'] )
				{
					$rebuild = FALSE;

	                /* Queue theme first */
	                if( isset( $values['filestorage__core_Theme'] ) and $settings['filestorage__core_Theme'] != $values['filestorage__core_Theme'] )
	                {
	                    $rebuild = TRUE;
	                    $extension = new \IPS\core\extensions\core\FileStorage\Theme;
	                    
	                    \IPS\Task::queue( 'core', 'MoveFiles', array( 'storageExtension' => 'filestorage__core_Theme', 'oldConfiguration' => $settings[ 'filestorage__core_Theme' ], 'newConfiguration' => $values[ 'filestorage__core_Theme' ], 'count' => $extension->count() ), 1 );
						
						/* Add to allowed storage methods so when moving files, we can accept old config or new config if move is in progress. Important: order is array(x, y) x is the new location (pos 0), y is the old location (pos 1)*/
						$values['filestorage__core_Theme'] = array( $values['filestorage__core_Theme'], $settings['filestorage__core_Theme'] );
	                }
					$totalCount = 0;
					foreach ( $values as $k => $v )
					{
						if ( isset( $settings[$k] ) AND !\is_array( $settings[$k] ) )
						{
							if ( $settings[ $k ] != $v and $k != 'filestorage__core_Theme' AND $k != 'filestorage_move' )
							{
								/* Do we need to move files at all? */
								$configurations = \IPS\File::getStore();
								$exploded = explode( '_', $k );
								$classname = "IPS\\{$exploded[2]}\\extensions\\core\\FileStorage\\{$exploded[3]}";
								$extension = new $classname;
								$currentClass = \IPS\File::getClass( \intval( $settings[ $k ] ) );
								$newClass = \IPS\File::getClass( \intval( $v ) );
								
								if ( ( isset( $configurations[ $v ] ) and isset( $configurations[ $settings[ $k ] ] ) ) and ( \get_class( $currentClass ) == \get_class( $newClass ) ) and ! $newClass::moveCheck( $newClass->configuration, $currentClass->configuration ) )
								{
									$rebuild = FALSE;
								}
								else
								{
									$rebuild = TRUE;
								}

								if ( $rebuild )
								{
									$count  = $extension->count();
									
									if ( $count )
									{
										$totalCount += $count;
										\IPS\Task::queue( 'core', 'MoveFiles', array( 'storageExtension' => $k, 'oldConfiguration' => $settings[ $k ], 'newConfiguration' => $values[ $k ], 'count' => $count ), 2 );
										
										/* Add to allowed storage methods so when moving files, we can accept old config or new config if move is in progress. Important: order is array(x, y) x is the new location (pos 0), y is the old location (pos 1)*/
										$values[ $k ] = array( $v, $settings[ $k ] );
									}
								}
							}
						}
					}
					
					if( $rebuild )
					{
						\IPS\Task::queue( 'core', 'DeleteMovedFiles', array( 'delete' => true, 'count' => $totalCount ), 5, array( 'delete' ) ); /* We use a key in the data array just to trigger the code that deletes duplicate tasks */
					}
				}

				/* Update the settings */
				\IPS\Settings::i()->changeValues( array( 'upload_settings' => json_encode( $values ) ) );

				/* Clear guest page caches */
				\IPS\Data\Cache::i()->clearAll();

				\IPS\Session::i()->log( 'acplogs__files_config_moved', array() );
				
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=overview&controller=files&do=settings&tab=settings'), 'saved' );
			}
			
			$activeTabContents = $form;
		}
		/* Or configurations table */
		else
		{
			$table = new \IPS\Helpers\Table\Db( 'core_file_storage', \IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=settings&tab=configurations" ) );
			$table->include = array( 'filestorage_method' );
			$table->noSort = array( 'filestorage_method' );
			$table->mainColumn = 'filestorage_method';
			$table->parsers = array( 'filestorage_method' => function( $val, $row )
			{
				$handlers	= \IPS\File::storageHandlers( $row );
				$classname	= $handlers[ $row['method'] ];
				$title		= $classname::displayName( json_decode( $row['configuration'], TRUE ) );

				if( \IPS\CIC AND $row['method'] == 'FileSystem' )
				{
					return \IPS\Theme::i()->getTemplate( 'dashboard' )->filesystemNotCic( $title );
				}

				return $title;
			} );
			
			$table->rootButtons = array( 'add' => array(
				'icon'	=> 'plus',
				'title'	=> 'add',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=configurationForm" )
			) );
			
			$table->rowButtons = function( $row )
			{
				return array(
					'edit'	=> array(
						'icon'	=> 'pencil',
						'title'	=> 'edit',
						'link'	=> \IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=configurationForm&id={$row['id']}" )
					),
					'log'	=> array(
						'icon'	=> 'search',
						'title'	=> 'file_config_log_title',
						'link'	=> \IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=configurationLog&id={$row['id']}" )
					),
					'delete'	=> array(
						'icon'	=> 'times-circle',
						'title'	=> 'delete',
						'link'	=> \IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=configurationForm&id={$row['id']}&delete=1" )->csrf()
					),
				);
			};
			
			$activeTabContents = $table;
		}

		/* Add a button for settings */
		\IPS\Output::i()->sidebar['actions'] = array(
				'settings'	=> array(
						'title'		=> 'settings',
						'icon'		=> 'cog',
						'link'		=> \IPS\Http\Url::internal( 'app=core&module=overview&controller=files&do=fileLogSettings' ),
						'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('settings') )
				),
		);
		
		/* Display */
		if ( \IPS\Request::i()->isAjax() and !isset( \IPS\Request::i()->ajaxValidate ) )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('storage_settings');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->tabs( array( 'settings' => 'filestorage_settings', 'configurations' => 'filestorage_configurations' ), $activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=core&module=overview&controller=files&do=settings" ) );
		}
	}

	/**
	 * Settings
	 *
	 * @return	void
	 */
	protected function fileLogSettings()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Interval( 'file_log_pruning', \IPS\Settings::i()->file_log_pruning, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'file_log_pruning' ) );
	
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplog__filelog_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=overview&controller=files&do=settings' ), 'saved' );
		}
	
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('filelog_settings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'filelog_settings', $form, FALSE );
	}
	
	/**
	 * Add/Edit Configuration
	 *
	 * @return	void
	 */
	protected function configurationForm()
	{
		/* Get existing */
		$current = NULL;
		$currentHandlerSettings = array();
		$createNewAndMove = FALSE;
		if ( isset( \IPS\Request::i()->id ) )
		{
			try
			{
				$current = \IPS\Db::i()->select( '*', 'core_file_storage', array( 'id=?', \intval( \IPS\Request::i()->id ) ) )->first();
				$currentHandlerSettings = json_decode( $current['configuration'], TRUE );
				
				if ( \in_array( \intval( \IPS\Request::i()->id ), array_filter( json_decode( \IPS\Settings::i()->upload_settings, TRUE ), function( $key ){ return $key != 'filestorage_move'; }, ARRAY_FILTER_USE_KEY ) ) )
				{
					if ( isset( \IPS\Request::i()->delete ) )
					{
						\IPS\Output::i()->error( 'file_storage_in_use', '1C158/2', 403, '' );
					}
					else
					{
						$createNewAndMove = TRUE;
					}
				}
				else
				{
					foreach ( \IPS\Db::i()->select( 'data', 'core_queue', array( '`key`=?', 'MoveFiles' ) ) as $data )
					{
						$data = json_decode( $data, TRUE );
						if ( $data['oldConfiguration'] == \IPS\Request::i()->id )
						{
							\IPS\Output::i()->error( 'file_storage_move_out', '1C158/3', 403, '' );
						}
						elseif ( $data['newConfiguration'] == \IPS\Request::i()->id )
						{
							\IPS\Output::i()->error( 'file_storage_move_in', '1C158/4', 403, '' );
						}
					}
					
					if ( isset( \IPS\Request::i()->delete ) )
					{
						\IPS\Session::i()->csrfCheck();
						
						\IPS\Db::i()->delete( 'core_file_storage', array( 'id=?', \intval( \IPS\Request::i()->id ) ) );
						unset( \IPS\Data\Store::i()->storageConfigurations );
	
						\IPS\Session::i()->log( 'acplogs__files_config_removed', array() );
						\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=overview&controller=files&do=settings&tab=configurations' ) );
					}
				}
			}
			catch ( \UnderflowException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2C158/1', 404, '' );
			}
		}
		
		/* Get handlers */
		$handlers = array();
		$handlerSettings = array();
		$toggles = array();
		foreach ( \IPS\File::storageHandlers( $current ) as $key => $class )
		{
			$handlers[ $key ] = 'filehandler__' . $key;
			foreach ( $class::settings( $currentHandlerSettings ) as $k => $v )
			{
				if ( \is_array( $v ) )
				{
					$settingClass = '\IPS\Helpers\Form\\' . $v['type'];
					
					$default = isset( $currentHandlerSettings[ $k ] ) ? str_replace( '{root}', \IPS\ROOT_PATH, $currentHandlerSettings[ $k ] ) : NULL;
					if ( isset( $v['default'] ) and !$default )
					{
						$default = str_replace( '{root}', \IPS\ROOT_PATH, $v['default'] );
					}
					
					$handlerSettings[ $key ][ $k ] = new $settingClass( "filehandler__{$key}_{$k}", $default, FALSE, isset( $v['options'] ) ? $v['options'] : array(), isset( $v['validate'] ) ? $v['validate'] : NULL, isset( $v['prefix'] ) ? $v['prefix'] : NULL, isset( $v['suffix'] ) ? $v['suffix'] : NULL, "{$key}_{$k}" );
				}
				else
				{
					$settingClass = '\IPS\Helpers\Form\\' . $v;
					$handlerSettings[ $key ][ $k ] = new $settingClass( "filehandler__{$key}_{$k}", isset( $currentHandlerSettings[ $k ] ) ? $currentHandlerSettings[ $k ] : NULL, FALSE, array(), NULL, NULL, NULL, "{$key}_{$k}" );
				}
				$toggles[ $key ][ $k ] = "{$key}_{$k}";
			}
		}
		
		/* Build form */
		$form = new \IPS\Helpers\Form;
		$form->hiddenValues['configurationId'] = isset( \IPS\Request::i()->id ) ? \IPS\Request::i()->id : 0;
		if ( isset( \IPS\Request::i()->id ) AND \in_array( \intval( \IPS\Request::i()->id ), json_decode( \IPS\Settings::i()->upload_settings, TRUE ) ) and $current['method'] !== 'FileSystem')
		{
			$form->addMessage( 'files_edit_existing_and_used', 'ipsMessage ipsMessage_info' );
		}

		$form->add( new \IPS\Helpers\Form\Radio( 'filestorage_method', $current ? $current['method'] : ( \IPS\CIC ? 'Amazon' : 'FileSystem' ), TRUE, array( 'options' => $handlers, 'toggles' => $toggles ) ) );
		foreach ( $handlerSettings as $handlerKey => $settings )
		{
			foreach ( $settings as $setting )
			{
				$form->add( $setting );
			}
		}
		
		if ( $createNewAndMove )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'filestorage_move', TRUE ) );
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			try
			{
				if ( isset( $toggles[ $values['filestorage_method'] ] ) )
				{
					foreach ( $toggles[ $values['filestorage_method'] ] as $k => $v )
					{
						$currentHandlerSettings[ $k ] = ( \IPS\ROOT_PATH !== '/' ) ? str_replace( \IPS\ROOT_PATH, '{root}', $values[ 'filehandler__' . $v ] ) : $values[ 'filehandler__' . $v ];
					}
				}

				$classname = \IPS\File::storageHandlers( $current )[ $values['filestorage_method'] ];
				if ( method_exists( $classname, 'testSettings' ) )
				{
					$classname::testSettings( $currentHandlerSettings );
				}
				
				$existingWithSameConfig = false;
				/* Make sure there are no other configurations that are exactly the same */
				foreach( \IPS\Db::i()->select( '*', 'core_file_storage', array( 'method=?', $values['filestorage_method'] ) ) as $existing )
				{
					if ( $current and $current['id'] == $existing['id'] )
					{
						continue;
					}
					
					$existingWithSameConfig = true;
					$existingConfiguration = json_decode( $existing['configuration'], true );
					foreach( $existingConfiguration as $k => $v )
					{
						$v = str_replace( \IPS\ROOT_PATH, '{root}', $v );
						
						if ( array_key_exists( $k, $currentHandlerSettings ) )
						{
							if ( $v != $currentHandlerSettings[ $k ] )
							{
								$existingWithSameConfig = false;
							}
						}
					}
				}

				if ( $existingWithSameConfig )
				{
					/* Let's not allow this to be saved as someone can start a move to the same location, which will end up deleting the files */
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'file_config_is_the_same_as_existing', FALSE ) );
				}

				/* Do we really need to create and move? */
				if ( $current AND $createNewAndMove )
				{
					$currentConf      = json_decode( $current['configuration'], TRUE );
					$createNewAndMove = $classname::moveCheck( $currentHandlerSettings, $currentConf );
				}

				if ( $current === NULL or $createNewAndMove )
				{
					$insertId = \IPS\Db::i()->insert( 'core_file_storage', array(
						'method'		=> $values['filestorage_method'],
						'configuration'	=> json_encode( $currentHandlerSettings ),
					) );
					unset( \IPS\Data\Store::i()->storageConfigurations );

					/* Log the storage addition */
					\IPS\Session::i()->log( 'acplogs__files_config_added' );

					if ( $createNewAndMove )
					{
						$settings = json_decode( \IPS\Settings::i()->upload_settings, TRUE );
						$totalCount = 0;
						foreach ( $settings as $k => $v )
						{
							if ( $v == $current['id'] )
							{
								if ( $values['filestorage_move'] )
								{
									$exploded = explode( '_', $k );
									try
									{
										$classname = "IPS\\{$exploded[2]}\\extensions\\core\\FileStorage\\{$exploded[3]}";
	
										if( \IPS\Application::appIsEnabled( $exploded[2] ) AND class_exists( $classname ) )
										{
											$extension = new $classname;
											$count = $extension->count();
											$totalCount += $count;
											\IPS\Task::queue( 'core', 'MoveFiles', array( 'storageExtension' => $k, 'oldConfiguration' => $v, 'newConfiguration' => $insertId, 'count' => $count ), 2 );
										}
										
										$settings[ $k ] = $insertId;
									}
									catch( \Exception $e ){}
								}
								else
								{
									$settings[ $k ] = $insertId;
								}
							}
						}
						
						\IPS\Settings::i()->changeValues( array( 'upload_settings' => json_encode( $settings ) ) );
						
						if ( $values['filestorage_move'] )
						{
							\IPS\Task::queue( 'core', 'DeleteMovedFiles', array( 'delete' => true, 'count' => $totalCount ), 5, array( 'delete' ) ); /* We use a key in the data array just to trigger the code that deletes duplicate tasks */
						}
					}
				}
				else
				{
					\IPS\Db::i()->update( 'core_file_storage', array( 'configuration' => json_encode( $currentHandlerSettings ) ), array( 'id=?', $current['id'] ) );
					unset( \IPS\Data\Store::i()->storageConfigurations );
				}
				
				if ( $current !== NULL and ! $createNewAndMove )
				{
					/* Log the storage change */
					\IPS\Session::i()->log( 'acplogs__files_config_changed' );

					$classname::getClass( $current['id'] )->settingsUpdated();
				}
				
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=overview&controller=files&do=settings&tab=configurations' ), 'saved' );
			}
			catch ( \LogicException $e )
			{
				$msg = $e->getMessage();
				$form->error = \IPS\Member::loggedIn()->language()->addToStack( $msg );
			}
		}
		
		\IPS\Output::i()->jsFiles  = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_files.js', 'core' ) );
		\IPS\Output::i()->globalControllers[]  = 'core.admin.files.form';
		
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('storage_settings');
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Determine if a move is needed (ajax method)
	 *
	 * @return void
	 */
	protected function checkMoveNeeded()
	{
		try
		{
			$current = \IPS\Db::i()->select( '*', 'core_file_storage', array( 'id=?', \intval( \IPS\Request::i()->id ) ) )->first();
		}
		catch( \UnderflowException $e )
		{
			\IPS\Output::i()->json( array( 'needsMoving' => FALSE ) );
		}
		
		$currentConf = json_decode( $current['configuration'], TRUE );
		$needsMoving = FALSE;
		
		if ( \in_array( \intval( \IPS\Request::i()->id ), json_decode( \IPS\Settings::i()->upload_settings, TRUE ) ) )
		{
			foreach ( $currentConf as $k => $v )
			{
				$checkKey = 'filehandler__' . $current['method'] . '_' . $k;
				
				if ( isset( \IPS\Request::i()->$checkKey ) )
				{
					$currentHandlerSettings[ $k ] = str_replace( \IPS\ROOT_PATH, '{root}', \IPS\Request::i()->$checkKey );
				}
			}

			$handlers	= \IPS\File::storageHandlers( $current );
			$classname	= $handlers[ $current['method'] ];
			
			/* Do we really need to create and move? */
			$needsMoving = $classname::moveCheck( $currentHandlerSettings, $currentConf );
		}
		
		\IPS\Output::i()->json( array( 'needsMoving' => (boolean) $needsMoving ) );
	}
	
	/**
	 * View logs for this configuration
	 *
	 * @return	void
	 */
	protected function configurationLog()
	{
		$method = \IPS\Db::i()->select( '*', 'core_file_storage', array( 'id=?', \intval( \IPS\Request::i()->id ) ) )->first();
		$title  = \IPS\Member::loggedIn()->language()->addToStack( 'file_config_log', FALSE, array( 'sprintf' => array( $method['method'] ) ) );

		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_file_logs', \IPS\Http\Url::internal( 'app=core&module=overview&controller=files&do=configurationLog&id=' . \IPS\Request::i()->id ), array( array( 'log_configuration_id=?', \IPS\Request::i()->id ) ) );
		$table->langPrefix  = 'files_';
		$table->title       = $title;
		$table->quickSearch = 'log_filename';
		$table->sortBy      = 'log_date';

		$table->filters		= array(
			'files_log_filter_error'   => array('log_type=?', 'error' ),
			'files_log_filter_move'    => array('log_type=?', 'move' )
		);

		$table->include = array( 'log_date', 'log_type', 'log_action', 'log_msg', 'log_filename' );

		$table->parsers = array(
			'log_filename' => function( $val, $row )
			{
				return ( ! empty( $row['log_container'] ) ? $row['log_container'] . '/' : '' ) . $val;
			},
			'log_date' => function( $val )
			{
				return \IPS\DateTime::ts( $val )->localeDate();
			},
			'log_type' => function( $val )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'files_type_' . $val );
			},
			'log_action' => function( $val )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'files_action_' . $val );
			}
		);

		/* Display */
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "&app=core&module=overview&controller=files&do=settings&tab=configurations" ), 'filestorage_settings' );
		\IPS\Output::i()->output = (string) $table;
		\IPS\Output::i()->title  = $title;
	}

	/**
	 * Remove orphaned files
	 *
	 * @return	void
	 */
	protected function orphaned()
	{
		\IPS\Session::i()->csrfCheck();
		
		foreach( \IPS\Db::i()->select( '*', 'core_file_storage', NULL, 'id' ) as $row )
		{
			\IPS\Task::queue( 'core', 'FindOrphanedFiles', array( 'configurationId' => $row['id'] ), 4, array( 'configurationId' ) );
		}
	
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=overview&controller=files" ), 'orphaned_files_tasks_added' );
	}

	/**
	 * Multimod
	 *
	 * @return	void
	 */
	protected function multimod()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'files_delete' );

		\IPS\Session::i()->csrfCheck();

		if( !isset( \IPS\Request::i()->multimod ) OR !\is_array( \IPS\Request::i()->multimod ) OR !\count( \IPS\Request::i()->multimod ) )
		{
			\IPS\Output::i()->error( 'nothing_mm_selected', '2C158/6', 403, '' );
		}

		foreach ( \IPS\Db::i()->select( '*', 'core_attachments', \IPS\Db::i()->in( 'attach_id', array_keys( \IPS\Request::i()->multimod ) ) ) as $attachment )
		{
			\IPS\File::get( 'core_Attachment', $attachment['attach_location'] )->delete();

			if( $attachment['attach_thumb_location'] )
			{
				\IPS\File::get( 'core_Attachment', $attachment['attach_thumb_location'] )->delete();
			}

			\IPS\Session::i()->log( 'acplogs__file_deleted', array( $attachment['attach_file'] => FALSE ) );
		}

		\IPS\Db::i()->delete( 'core_attachments', \IPS\Db::i()->in( 'attach_id', array_keys( \IPS\Request::i()->multimod ) ) );

		$url = \IPS\Http\Url::internal( "app=core&module=overview&controller=files" );

		if( \IPS\Request::i()->listResort )
		{
			$url = $url->setQueryString( array( 'listResort' => 1, 'sortby' => \IPS\Request::i()->sortby, 'sortdirection' => \IPS\Request::i()->sortdirection ) )->csrf();
		}
		\IPS\Output::i()->redirect( $url, 'deleted' );
	}
}