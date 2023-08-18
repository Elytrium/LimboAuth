<?php
/**
 * @brief		Submit File Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		08 Oct 2013
 */

namespace IPS\downloads\modules\front\downloads;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Submit File Controller
 */
class _submit extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_submit.js', 'downloads', 'front' ) );

		parent::execute();
	}

	/**
	 * Choose category
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$form = new \IPS\Helpers\Form( 'select_category', 'continue' );
		$form->class = 'ipsForm_vertical ipsForm_noLabels';
		$form->add( new \IPS\Helpers\Form\Node( 'select_category', isset( \IPS\Request::i()->category ) ? \IPS\Request::i()->category : NULL, TRUE, array(
			'url'					=> \IPS\Http\Url::internal( 'app=downloads&module=downloads&controller=submit', 'front', 'downloads_submit' ),
			'class'					=> 'IPS\downloads\Category',
			'permissionCheck'		=> 'add',
			'clubs'					=> \IPS\Settings::i()->club_nodes_in_apps
		) ) );

		if ( \IPS\Member::loggedIn()->group['idm_bulk_submit'] )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'bulk', NULL, FALSE, array( 'label' => "bulk_upload_button" ) ) );
		}

		if ( $values = $form->values() )
		{
			$url = \IPS\Http\Url::internal( 'app=downloads&module=downloads&controller=submit&do=submit', 'front', 'downloads_submit' )->setQueryString( 'category', $values['select_category']->_id );
			if ( isset( $values['bulk'] ) AND $values['bulk'] )
			{
				$url = $url->setQueryString( 'bulk', '1' );
			}
			if( isset( \IPS\Request::i()->_new ) )
			{
				$url = $url->setQueryString(array( '_new' => '1' ) );
			}
					
			\IPS\Output::i()->redirect( $url );
		}

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'select_category' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'submit' )->categorySelector( $form );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'select_category' ) );
	}

	/**
	 * Submit files
	 *
	 * @return	void
	 */
	protected function submit()
	{		
		$steps = array();

		/**
		 * Step 1: Upload files
		 */
		$steps['upload_files'] = function( $data )
		{
			/* Get category data */
			try
			{
				$category = \IPS\downloads\Category::loadAndCheckPerms( \IPS\Request::i()->category );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=downloads&module=downloads&controller=submit&_step=select_category', 'front', 'downloads_submit' ) );
			}

			if( !$category->can('add') OR !\IPS\Member::loggedIn()->group['idm_max_size'] )
			{
				\IPS\Output::i()->error( 'add_files_no_perm', '3D286/1', 403, '' );
			}

			$form = new \IPS\Helpers\Form( 'upload_files', 'continue' );

			$form->class = 'ipsForm_vertical';
			$form->hiddenValues['category'] = $category->_id;
			$form->hiddenValues['postKey'] = ( \IPS\Request::i()->postKey ) ? \IPS\Request::i()->postKey : md5( mt_rand() );

			/* Populate any existing records */
			$files = array();
			$screenshots = array();

			if ( isset( $data['files'] ) )
			{
				foreach ( $data['files'] as $url )
				{
					$files[] = \IPS\File::get( 'downloads_Files', $url );
				}
			}

			if ( isset( $data['screenshots'] ) )
			{
				foreach ( $data['screenshots'] as $url )
				{
					$screenshots[] = \IPS\File::get( 'downloads_Screenshots', $url );
				}
			}
			
			/* Add the fields */
			$maximums = array();

			if( $category->maxfile !== NULL AND !$category->club() )
			{
				$maximums[] = ( $category->maxfile / 1024 );
			}

			if( \IPS\Member::loggedIn()->group['idm_max_size'] !== -1 )
			{
				$maximums[] = ( \IPS\Member::loggedIn()->group['idm_max_size'] / 1024 );
			}

			$form->add( new \IPS\Helpers\Form\Upload( 'files', $files, ( !\IPS\Member::loggedIn()->group['idm_linked_files'] and !\IPS\Member::loggedIn()->group['idm_import_files'] ), array( 'storageExtension' => 'downloads_Files', 'allowedFileTypes' => $category->types, 'maxFileSize' => ( ( \count( $maximums ) ) ? min( $maximums ) : NULL ), 'multiple' => $category->multiple_files, 'minimize' => FALSE ) ) );

			if ( !isset( \IPS\Request::i()->bulk ) )
			{
				if ( \IPS\Member::loggedIn()->group['idm_linked_files'] )
				{
					$form->add( new \IPS\Helpers\Form\Stack( 'url_files', isset( $data['url_files'] ) ? $data['url_files'] : array(), FALSE, array( 'stackFieldType' => 'Url' ), array( 'IPS\downloads\File', 'blacklistCheck' ) ) );
				}

				if ( \IPS\Member::loggedIn()->group['idm_import_files']  )
				{
					$form->add( new \IPS\Helpers\Form\Stack( 'import_files', array(), FALSE, array( 'placeholder' => \IPS\ROOT_PATH ), function( $val )
					{
						if( $val and \is_array( $val ) )
						{
							foreach ( $val as $file )
							{
								if ( is_dir( $file ) )
								{
									throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('err_import_files_dir', FALSE, array( 'sprintf' => array( $file ) ) ) );
								}
								elseif ( !is_file( $file ) )
								{
									throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('err_import_files', FALSE, array( 'sprintf' => array( $file ) ) ) );
								}
							}
						}
					} ) );
				}

				if ( $category->bitoptions['allowss'] )
				{
					$image = TRUE;
					if ( $category->maxdims and $category->maxdims != '0x0' )
					{
						$maxDims = explode( 'x', $category->maxdims );
						$image = array( 'maxWidth' => $maxDims[0], 'maxHeight' => $maxDims[1] );
					}
					$form->add( new \IPS\Helpers\Form\Upload( 'screenshots', $screenshots, ( $category->bitoptions['reqss'] and !\IPS\Member::loggedIn()->group['idm_linked_files'] ), array(
						'storageExtension'	=> 'downloads_Screenshots',
						'image'				=> $image,
						'maxFileSize'		=> $category->maxss ? ( $category->maxss / 1024 ) : NULL,
						'multiple'			=> TRUE,
						'template'			=> "downloads.submit.screenshot",
					) ) );
					if ( \IPS\Member::loggedIn()->group['idm_linked_files'] )
					{
						$form->add( new \IPS\downloads\Form\LinkedScreenshots( 'url_screenshots', isset( $data['url_screenshots'] ) ? array( 'values' => $data['url_screenshots'] ) : array(), FALSE, array( 'stackFieldType' => 'Url' ), array( 'IPS\downloads\File', 'blacklistCheck' ) ) );
					}
				}

				/* Form Elements */
				foreach ( \IPS\downloads\File::formElements( NULL, $category ) as $input )
				{
					$form->add( $input );
				}
				
				/* Version field (we only show this on create */
				if( $category->version_numbers )
				{
					$form->add( new \IPS\Helpers\Form\Text( 'file_version', '1.0.0', ( $category->version_numbers == 2 ) ? TRUE : FALSE, array( 'maxLength' => 32 ) ) );
				}
			}

			if ( $values = $form->values() )
			{				
				/* Check */
				if ( empty( $values['files'] ) and empty( $values['url_files'] ) and empty( $values['import_files'] ) )
				{
					$form->error = \IPS\Member::loggedIn()->language()->addToStack('err_no_files');
					return \IPS\Theme::i()->getTemplate( 'submit' )->submissionForm( $form, $category, $category->message('subterms'), TRUE, ( \IPS\Member::loggedIn()->group['idm_bulk_submit'] && \IPS\Request::i()->bulk ) );
				}
				elseif ( !$category->multiple_files AND \is_array( $values['files'] ) AND ( \count( $values['files'] ?? [] ) + \count( $values['url_files'] ?? [] ) + \count( $values['import_files'] ?? [] ) > 1 ) )
				{
					$form->error = \IPS\Member::loggedIn()->language()->addToStack('err_too_many_files');
					return \IPS\Theme::i()->getTemplate( 'submit' )->submissionForm( $form, $category, $category->message('subterms'), TRUE, ( \IPS\Member::loggedIn()->group['idm_bulk_submit'] && \IPS\Request::i()->bulk ) );
				}
				if ( !isset( \IPS\Request::i()->bulk ) && $category->bitoptions['reqss'] and empty( $values['screenshots'] ) and empty( $values['url_screenshots'] ) )
				{
					$form->error = \IPS\Member::loggedIn()->language()->addToStack('err_no_screenshots');
					return \IPS\Theme::i()->getTemplate( 'submit' )->submissionForm( $form, $category, $category->message('subterms'), TRUE, ( \IPS\Member::loggedIn()->group['idm_bulk_submit'] && \IPS\Request::i()->bulk ) );
				}
												
				/* Get any records we had before in case we need to delete them */
				$existing = iterator_to_array( \IPS\Db::i()->select( '*', 'downloads_files_records', array( 'record_post_key=?', \IPS\Request::i()->postKey ) )->setKeyField( 'record_location' ) );
				
				/* Loop through the values we have */
				$k					= 0;
				$files				= array();
				$linkedFiles		= array();
				$screenshots		= array();
				$linkedScreenshots	= array();

				/* Files may not be an array since we have an option to limit to a single upload */

				if( $values['files'] !== NULL )
				{
					if ( !\is_array( $values['files'] ) )
					{
						$values['files'] = [ $values['files'] ];
					}

					foreach ( $values['files'] as $file )
					{
						$files[ $k ] = (string) $file;
						if ( !isset( $existing[ (string) $file ] ) )
						{
							\IPS\Db::i()->insert( 'downloads_files_records', array(
								'record_post_key' => isset( \IPS\Request::i()->bulk ) ? md5( \IPS\Request::i()->postKey . "-{$k}" ) : \IPS\Request::i()->postKey,
								'record_type' => 'upload',
								'record_location' => (string) $file,
								'record_realname' => $file->originalFilename,
								'record_size' => $file->filesize(),
								'record_time' => time(),
							) );
						}
						$k++;
						unset( $existing[ (string) $file ] );
					}
				}
				if ( isset( $values['import_files'] ) )
				{
					\IPS\File::$copyFiles = TRUE;

					foreach ( $values['import_files'] as $path )
					{
						$file = \IPS\File::create( 'downloads_Files', mb_substr( $path, mb_strrpos( $path, DIRECTORY_SEPARATOR ) + 1 ), NULL, NULL, FALSE, $path );
						
						$files[ $k ] = (string) $file;
						if ( !isset( $existing[ (string) $file ] ) )
						{
							\IPS\Db::i()->insert( 'downloads_files_records', array(
								'record_post_key'	=> isset( \IPS\Request::i()->bulk ) ? md5( \IPS\Request::i()->postKey . "-{$k}" ) : \IPS\Request::i()->postKey,
								'record_type'		=> 'upload',
								'record_location'	=> (string) $file,
								'record_realname'	=> $file->originalFilename,
								'record_size'		=> $file->filesize(),
								'record_time'		=> time(),
							) );
						}
						$k++;
					}

					\IPS\File::$copyFiles = FALSE;
				}
				if ( isset( $values['url_files'] ) )
				{
					foreach ( $values['url_files'] as $url )
					{
						$linkedFiles[] = (string) $url;
						if ( !isset( $existing[ (string) $url ] ) )
						{
							\IPS\Db::i()->insert( 'downloads_files_records', array(
								'record_post_key'	=> \IPS\Request::i()->postKey,
								'record_type'		=> 'link',
								'record_location'	=> (string) $url,
								'record_realname'	=> NULL,
								'record_size'		=> 0,
								'record_time'		=> time(),
							) );
						}
						unset( $existing[ (string) $url ] );
					}
				}
				if ( isset( $values['screenshots'] ) )
				{
					foreach ( $values['screenshots'] as $_key => $file )
					{
						$screenshots[] = (string) $file;
						if ( !isset( $existing[ (string) $file ] ) )
						{
							$noWatermark = NULL;
							if ( \IPS\Settings::i()->idm_watermarkpath )
							{
								try
								{
									$noWatermark = (string) $file;
									$watermark = \IPS\Image::create( \IPS\File::get( 'core_Theme', \IPS\Settings::i()->idm_watermarkpath )->contents() );
									$image = \IPS\Image::create( $file->contents() );
									$image->watermark( $watermark );
									$file = \IPS\File::create( 'downloads_Screenshots', $file->originalFilename, $image );
								}
								catch ( \Exception $e ) { }
							}
							
							\IPS\Db::i()->insert( 'downloads_files_records', array(
								'record_post_key'		=> \IPS\Request::i()->postKey,
								'record_type'			=> 'ssupload',
								'record_location'		=> (string) $file,
								'record_thumb'			=> (string) $file->thumbnail( 'downloads_Screenshots' ),
								'record_realname'		=> $file->originalFilename,
								'record_size'			=> $file->filesize(),
								'record_time'			=> time(),
								'record_no_watermark'	=> $noWatermark,
								'record_default'		=> ( \IPS\Request::i()->screenshots_primary_screenshot AND \IPS\Request::i()->screenshots_primary_screenshot == $_key ) ? 1 : 0
							) );
						}
						unset( $existing[ (string) $file ] );
					}
				}
				if ( isset( $values['url_screenshots'] ) )
				{
					foreach ( $values['url_screenshots'] as $_key => $url )
					{
						$linkedScreenshots[] = (string) $url;
						if ( !isset( $existing[ (string) $url ] ) )
						{
							\IPS\Db::i()->insert( 'downloads_files_records', array(
								'record_post_key'	=> \IPS\Request::i()->postKey,
								'record_type'		=> 'sslink',
								'record_location'	=> (string) $url,
								'record_thumb'		=> NULL,
								'record_realname'	=> NULL,
								'record_size'		=> 0,
								'record_time'		=> time(),
								'record_default'	=> ( \IPS\Request::i()->screenshots_primary_screenshot AND \IPS\Request::i()->screenshots_primary_screenshot == $_key ) ? 1 : 0
							) );
						}
						unset( $existing[ (string) $url ] );
					}
				}
								
				/* Delete any that we don't have any more */
				foreach ( $existing as $location => $file )
				{
					try
					{
						\IPS\File::get( $file['record_type'] === 'upload' ? 'downloads_Files' : 'downloads_Screenshots', $location )->delete();
					}
					catch ( \Exception $e ) { }

					if( $file['record_thumb'] )
					{
						try
						{
							\IPS\File::get( 'downloads_Screenshots', $file['record_thumb'] )->delete();
						}
						catch ( \Exception $e ) { }
					}

					if( $file['record_no_watermark'] )
					{
						try
						{
							\IPS\File::get( 'downloads_Screenshots', $file['record_no_watermark'] )->delete();
						}
						catch ( \Exception $e ) { }
					}
					
					\IPS\Db::i()->delete( 'downloads_files_records', array( 'record_id=?', $file['record_id'] ) );
				}
				

				if ( !isset( \IPS\Request::i()->bulk ) )
				{
					$file = \IPS\downloads\File::createFromForm( array_merge( $data, $values, array( 'postKey' => \IPS\Request::i()->postKey ) ), $category );
					$file->markRead();

					/* Redirect */
					if ( isset( $values['guest_email'] ) )
					{
						$url = \IPS\Http\Url::internal( 'app=core&module=system&controller=register', 'front', 'register' );
						$message = NULL;
					}
					elseif( $file->author()->member_id OR $file->canView() )
					{
						$url		= $file->url();
						$message	= ( isset( $values['import_files'] ) AND \count( $values['import_files'] ) ) ? \IPS\Member::loggedIn()->language()->addToStack('file_imported_removed') : NULL;
					}
					else
					{
						$url		= $category->url();
						$message	= \IPS\Member::loggedIn()->language()->addToStack('file_requires_approval_g');
					}
					
					if ( \IPS\Request::i()->isAjax() )
					{
						\IPS\Output::i()->json( array( 'redirect' => (string) $url ) );
					}
					else
					{
						\IPS\Output::i()->redirect( $url, $message );
					}
				}
				else
				{
					/* This is a bulk file, so we want to go on to the next step */
					return array( 'category' => $category->_id, 'postKey' => \IPS\Request::i()->postKey, 'files' => $files, 'url_files' => $linkedFiles, 'screenshots' => $screenshots, 'url_screenshots' => $linkedScreenshots );
				}
			}
			
			$guestPostBeforeRegister = ( !\IPS\Member::loggedIn()->member_id ) ? ( $category and !$category->can( 'add', \IPS\Member::loggedIn(), FALSE ) ) : NULL;
			$modQueued = \IPS\downloads\File::moderateNewItems( \IPS\Member::loggedIn(), $category, $guestPostBeforeRegister );
			if ( $guestPostBeforeRegister or $modQueued )
			{
				$postingInformation = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->postingInformation( $guestPostBeforeRegister, $modQueued, TRUE );
			}
			else
			{
				$postingInformation = NULL;
			}

			return \IPS\Theme::i()->getTemplate( 'submit' )->submissionForm( $form, $category, $category->message('subterms'), TRUE, ( \IPS\Member::loggedIn()->group['idm_bulk_submit'] && \IPS\Request::i()->bulk ), $postingInformation );
		};

		/**
		 * Step 2: File information (for bulk uploads only)
		 */
		$steps['file_information'] = function ( $data )
		{
			/* Get Category */
			try
			{
				$category = \IPS\downloads\Category::loadAndCheckPerms( $data['category'] );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=downloads&module=downloads&controller=submit', 'front', 'downloads_submit' ) );
			}
			/* Init Form */
			$form = new \IPS\Helpers\Form( 'file_information', 'continue' );
			$existing = array();

			foreach ( $data['files'] as $key => $file )
			{
				/* Header */
				$file = \IPS\File::get( 'downloads_Files', $file );

				try
				{
					$displayName = \IPS\Db::i()->select( 'record_realname', 'downloads_files_records', array( 'record_location=?', (string) $file ) )->first();
				}
				catch( \UnderflowException $e )
				{
					$displayName = $file->originalFilename;
				}

				$form->addTab( $displayName );
				$form->addHeader( $displayName );
				
				/* Form Elements */
				foreach ( \IPS\downloads\File::formElements( NULL, $category, "filedata_{$key}_" ) as $input )
				{
					\IPS\Member::loggedIn()->language()->words[ $input->name ] = \IPS\Member::loggedIn()->language()->addToStack( mb_substr( $input->name, mb_strlen( "filedata_{$key}_" ) ), FALSE );
					if ( !$input->value and \in_array( $input->name, array( "filedata_{$key}_file_title", "filedata_{$key}_file_desc" ) ) )
					{
						$input->value = $displayName;
					}
					$form->add( $input );
				}
				
				/* Screenshots */
				if ( $category->bitoptions['allowss'] )
				{
					$existing[ $key ] = iterator_to_array( new \IPS\File\Iterator( \IPS\Db::i()->select( '*', 'downloads_files_records', array( 'record_post_key=? AND record_type=?', md5( "{$data['postKey']}-{$key}" ), 'ssupload' ) )->setValueField( function( $row ) { return $row['record_no_watermark'] ?: $row['record_location']; } )->setKeyField( function( $row ) { return $row['record_no_watermark'] ?: $row['record_location']; } ), 'downloads_Screenshots' ) );

					$image = TRUE;
					if ( $category->maxdims and $category->maxdims != '0x0' )
					{
						$maxDims = explode( 'x', $category->maxdims );
						$image = array( 'maxWidth' => $maxDims[0], 'maxHeight' => $maxDims[1] );
					}

					$form->add( new \IPS\Helpers\Form\Upload( "screenshots_{$key}", $existing[ $key ], ( $category->bitoptions['reqss'] and !\IPS\Member::loggedIn()->group['idm_linked_files'] ), array(
						'storageExtension'	=> 'downloads_Screenshots',
						'image'				=> $image,
						'maxFileSize'		=> $category->maxss ? ( $category->maxss / 1024 ) : NULL,
						'multiple'			=> TRUE
					) ) );
					\IPS\Member::loggedIn()->language()->words[ "screenshots_{$key}" ] = \IPS\Member::loggedIn()->language()->addToStack( 'screenshots', FALSE );
				}

									
				/* Version field */
				if( $category->version_numbers )
				{
					$form->add( new \IPS\Helpers\Form\Text( "filedata_{$key}_file_version", '1.0.0', ( $category->version_numbers == 2 ) ? TRUE : FALSE, array( 'maxLength' => 32 ) ) );
					\IPS\Member::loggedIn()->language()->words[ "filedata_{$key}_file_version" ] = \IPS\Member::loggedIn()->language()->addToStack( 'file_version', FALSE );
				}
			}


			/* Handle Submissions */
			if ( $values = $form->values() )
			{
				if ( $category->bitoptions['allowss'] )
				{
					foreach ( $data['files'] as $key => $file )
					{
						/* Save Screenshots */
						foreach ( $values["screenshots_{$key}"] as $file )
						{
							$screenshots[] = (string) $file;
							if ( !isset( $existing[ $key ][ (string) $file ] ) )
							{
								$noWatermark = NULL;
								if ( \IPS\Settings::i()->idm_watermarkpath )
								{
									$noWatermark = (string) $file;
									$watermark = \IPS\Image::create( \IPS\File::get( 'core_Theme', \IPS\Settings::i()->idm_watermarkpath )->contents() );
									$image = \IPS\Image::create( $file->contents() );
									$image->watermark( $watermark );
									$file = \IPS\File::create( 'downloads_Screenshots', $file->originalFilename, $image );
								}
								
								\IPS\Db::i()->insert( 'downloads_files_records', array(
									'record_post_key'		=> md5( "{$data['postKey']}-{$key}" ),
									'record_type'			=> 'ssupload',
									'record_location'		=> (string) $file,
									'record_thumb'			=> (string) $file->thumbnail( 'downloads_Screenshots' ),
									'record_realname'		=> $file->originalFilename,
									'record_size'			=> $file->filesize(),
									'record_time'			=> time(),
									'record_no_watermark'	=> $noWatermark
								) );
							}
							else
							{
								unset( $existing[ $key ][ (string) $file ] );
							}
						}
						
						unset( $values["screenshots_{$key}"] );
					
						/* Delete any that we don't have any more */
						foreach ( $existing[ $key ]  as $location => $file )
						{
							try
							{
								$file->delete();
							}
							catch ( \Exception $e ) { }
							
							\IPS\Db::i()->delete( 'downloads_files_records', array( 'record_location=? OR record_no_watermark=?', (string) $file, (string) $file ) );
						}
					}
				}

				/* Create Files */
				foreach ( $data['files'] as $key => $fileUrl )
				{
					/* $values isn't going to work as is here */
					$save = array( 'postKey' => md5( "{$data['postKey']}-{$key}" ) );
					$customFields = [];
					$len = mb_strlen( "filedata_{$key}_" );
					foreach ( $values as $k => $v )
					{
						if ( mb_substr( $k, 0, $len ) == "filedata_{$key}_" )
						{
							$save[ mb_substr( $k, $len ) ] = $v;
						}
						elseif ( mb_substr( $k, 0, $len + 16 ) == "downloads_field_filedata_{$key}_" ) // That's a custom field, because the underscore after the language prefix is hardcoded
						{
							$customFields[] = mb_substr( $k, $len + 16 );
							$save[ 'downloads_field_' . mb_substr( $k, $len + 16 ) ] = $v;
						}
					}
					
					$file = \IPS\downloads\File::createFromForm( $save, $category, FALSE );
					\IPS\File::claimAttachments( "filedata_{$key}_downloads-new-file", $file->id, NULL, 'desc' );
					foreach ( $customFields as $k )
					{
						\IPS\File::claimAttachments( md5( 'IPS\downloads\Field-filedata_' . $key . '_' . $k . '-new' ), $file->id, $k, 'fields' );
					}
					$file->markRead();
				}

				if ( \IPS\Member::loggedIn()->moderateNewContent() OR \IPS\downloads\File::moderateNewItems( \IPS\Member::loggedIn(), $category ) )
				{
					\IPS\downloads\File::_sendUnapprovedNotifications( $category );
				}
				else
				{
					\IPS\downloads\File::_sendNotifications( $category );
				}
			
				/* Redirect */
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->json( array( 'redirect' => (string) $category->url() ) );
				}
				else
				{
					\IPS\Output::i()->redirect( $category->url() );
				}
			}

			return \IPS\Theme::i()->getTemplate( 'submit' )->bulkForm( $form, $category );
		};


		/* Build Wizard */
		$url = \IPS\Http\Url::internal( 'app=downloads&module=downloads&controller=submit&do=submit', 'front', 'downloads_submit' );
		if ( isset( \IPS\Request::i()->category ) and \IPS\Request::i()->category )
		{
			$url = $url->setQueryString( 'category', \IPS\Request::i()->category );
		}
		if ( isset( \IPS\Request::i()->bulk ) and \IPS\Request::i()->bulk  )
		{
			$url = $url->setQueryString( 'bulk', 1 );
		}
		$wizard = new \IPS\Helpers\Wizard( $steps, $url );
		$wizard->template = array( \IPS\Theme::i()->getTemplate( 'submit' ), 'wizardForm' );
		
		/* Online User Location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=downloads&module=downloads&controller=submit', 'front', 'downloads_submit' ), array(), 'loc_downloads_adding_file' );
		
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( isset( \IPS\Request::i()->bulk ) ? 'submit_multiple_files' : 'submit_a_file' );
		if ( \IPS\IN_DEV )
		{
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'plupload/moxie.js', 'core', 'interface' ) );
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'plupload/plupload.dev.js', 'core', 'interface' ) );
		}
		else
		{
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'plupload/plupload.full.min.js', 'core', 'interface' ) );
		}
		
		$category = NULL;
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		if ( isset( \IPS\Request::i()->category ) )
		{
			try
			{
				$category = \IPS\downloads\Category::loadAndCheckPerms( \IPS\Request::i()->category );
				if ( $club = $category->club() )
				{
					\IPS\core\FrontNavigation::$clubTabActive = TRUE;
					\IPS\Output::i()->breadcrumb = array();
					\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs') );
					\IPS\Output::i()->breadcrumb[] = array( $club->url(), $club->name );
					\IPS\Output::i()->breadcrumb[] = array( $category->url(), $category->_title );
				}
			}
			catch ( \OutOfRangeException $e ) { }
		}
		
		\IPS\Output::i()->output = (string) $wizard;
		
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'submit_a_file' ) );
	}
}