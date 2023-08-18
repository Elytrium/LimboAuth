<?php
/**
 * @brief		Upload class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Upload class for Form Builder
 */
class _Upload extends FormAbstract
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'storageExtension'	=> 'Profile',										// The file storage extension to use. This is required if postKey is NULL and temporary is FALSE.
	        'storageContainer'  => NULL,                                            // The file storage container to use.
	 		'multiple'			=> TRUE,											// Specifies if the field should allow multiple file uploads. Default is FALSE.
	 		'image'				=> array( 'maxWidth' => 100, 'maxHeight' => 100 ),	// If the upload must be an image, can pass TRUE or an array with max width and height (in which case, image will be resized appropriately). Default is NULL. Max width/height cannot be used in conjunction with temporary uploads. If it can be, but doesn't have to an image, and you still want to specify max width and height, add an "optional" property set to true
	 		'checkImage'		=> TRUE,											// If TRUE, will load uploaded images into the image processing engine to automatically reorient if needed
	 		'allowedFileTypes'	=> array( 'pdf', 'txt' ),							// Allowed file extensions. NULL allows any. Default is NULL.
	 		'maxFileSize'		=> 100,												// Maximum file size in megabytes. NULL is no limit. Default is NULL. Note that there *may* be server limitations regardless of this value which are calculated automatically.
	 		'totalMaxSize'		=> 100,												// If this is a "multiple" upload field, the maximum storage space allowed in total in megabytes.
	 		'maxFiles'			=> NULL,											// Maximum number of files that can be uploaded
	 		'postKey'			=> 'abc',											// If provided, uploads will be treated as post attachments using the given post key
	 		'temporary'			=> TRUE,											// If TRUE, the image will not be moved and the filename returned, rather than an \IPS\File object. This should ONLY be used for files which are genuinely
	 																				 	temporary (e.g. importing skins, languages) as the file will be deleted after the script finished executing. Default is FALSE.
	 		'callback'			=> function() { ... },								// A callback function to run against submitted files
	 		'minimize'			=> TRUE,											// Default is minimized. Pass FALSE to show the maximized field. Cannot be used in conjunction with temporary uploads
	 		'retainDeleted'		=> FALSE,											// By default, if you specify a default value and the user deletes the files specified, the files will be physically deleted. This option overrides this behaviour.
	 		'template'			=> 'core.attachments.fileItem',						// The javascript template key to use when rendering uploaded items. e.g. core.attachments.fileItem
	 		'obscure'			=> TRUE,											// Controls if an md5 hash should be added to the filename. *Must* be TRUE unless the uploaded files are public to all users (like emoticons),
	 		'supportsDelete'	=> TRUE												// Should the uploaded file be direct deletable from the upload field
	 		'allowStockPhotos'	=> FALSE											// If Pixabay enhancement is enabled, this will allow users to select stock art from Pixabay to upload
	 		'canBeModerated'		=> TRUE											// Set to TRUE if this uploader is capable of holding the submitted content for moderation
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'multiple'			=> FALSE,
		'image'				=> NULL,
		'checkImage'		=> TRUE,
		'allowedFileTypes'	=> NULL,
		'maxFileSize'		=> NULL,
		'totalMaxSize'		=> NULL,
		'maxFiles'			=> NULL,
		'postKey'			=> NULL,
		'storageExtension'	=> NULL,
		'storageContainer'  => NULL,
		'temporary'			=> FALSE,
		'callback'			=> NULL,
		'minimize'			=> TRUE,
		'retainDeleted'		=> FALSE,
		'template'			=> 'core.attachments.fileItem',
		'default'			=> NULL,
		'obscure'			=> TRUE,
		'supportsDelete' 	=> TRUE,
		'allowStockPhotos'  => FALSE,
		'canBeModerated'	=> FALSE
	);
	
	/**
	 * @brief	Max chunk size (in MB)
	 */
	protected $maxChunkSize;
	
	/**
	 * @brief	Template
	 */
	public $template;
	
	/**
	 * Constructor
	 * Sets that the max file size based on PHP's limits as well as the specified one
	 *
	 * @see		\IPS\Helpers\Form\FormAbstract::__construct
	 * @param	string		$name					Name
	 * @param	mixed		$defaultValue			Default value
	 * @param	bool		$required				Required?
	 * @param	array		$options				Type-specific options
	 * @param	callback	$customValidationCode	Custom validation code
	 * @param	string		$prefix					HTML to show before input field
	 * @param	string		$suffix					HTML to show after input field
	 * @param	string		$id						The ID to add to the row
	 * @return	void
	 */
	public function __construct( $name, $defaultValue=NULL, $required=FALSE, $options=array(), $customValidationCode=NULL, $prefix=NULL, $suffix=NULL, $id=NULL )
	{
		/* What's PHP's upload limit */
		if ( $maxChunkSize = static::maxChunkSize() )
		{
			$this->maxChunkSize = $maxChunkSize / 1048576;
		}

		/* Can we really use stock photos? */
		if ( ! empty( $options['allowStockPhotos'] ) )
		{
			if ( ! \IPS\Settings::i()->pixabay_enabled )
			{
				$options['allowStockPhotos'] = FALSE;
			}
		}

		/* Work out storage extension */
		if ( isset( $options['postKey'] ) and $options['postKey'] and ( !isset( $options['storageExtension'] ) or !$options['storageExtension'] ) )
		{
			$options['storageExtension'] = 'core_Attachment';
		}
		if ( ( !isset( $options['storageExtension'] ) or !$options['storageExtension'] ) and ( !isset( $options['temporary'] ) or !$options['temporary'] ) )
		{
			throw new \InvalidArgumentException;
		}
		
		/* Does the storage extension support chunking? */
		if ( isset( $options['storageExtension'] ) and $options['storageExtension'] )
		{
			$storageClass = \IPS\File::getClass( $options['storageExtension'] );
			
			/* If the storage engine has a minimum chunk size, but our server can't handle uploads that large, we can't support chunking */
			$supportsChunking = $storageClass::$supportsChunking;
			if ( $storageClass::$supportsChunking and isset( $storageClass::$minChunkSize ) and $storageClass::$minChunkSize > $this->maxChunkSize )
			{
				$supportsChunking = FALSE;
			}
			
			/* If we support chunking... */
			if ( $supportsChunking )
			{
				/* Just need to make sure the storage engine's maximum chunk size is bigger than what our server can handle */
				if ( isset( $storageClass::$maxChunkSize ) and $storageClass::$maxChunkSize < $this->maxChunkSize )
				{
					$this->maxChunkSize = $storageClass::$maxChunkSize;
				}
			}
			/* If we don't... */
			elseif ( !$supportsChunking )
			{
				/* Then we need to set the maximum file size to what our server can handle */
				if ( !isset( $options['maxFileSize'] ) or $this->maxChunkSize < $options['maxFileSize'] )
				{
					$options['maxFileSize'] = $this->maxChunkSize;
				}
			}
		}

		if( isset( $options['maxFileSize'] ) AND $options['maxFileSize'] <= 0 )
		{
			throw new \InvalidArgumentException;
		}

		if( isset( $options['maxFiles'] ) AND $options['maxFiles'] <= 0 )
		{
			throw new \InvalidArgumentException;
		}

		/* If this has to be an image, set the allowed file types */
		if ( isset( $options['image'] ) and !isset( $options['image']['optional'] ) and !isset( $options['allowedFileTypes'] ) )
		{
			$options['allowedFileTypes'] = \IPS\Image::supportedExtensions();
		}
		
		/* Call parent constructor */
		parent::__construct( $name, $defaultValue, $required, $options, $customValidationCode, $prefix, $suffix, $id );
								
		/* Add JS */
		if ( \IPS\IN_DEV )
		{
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'plupload/moxie.js', 'core', 'interface' ) );
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'plupload/plupload.dev.js', 'core', 'interface' ) );
		}
		else
		{
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'plupload/plupload.full.min.js', 'core', 'interface' ) );
		}

		if ( \IPS\Settings::i()->ipb_url_filter_option != 'none' )
		{
			$links = \IPS\Settings::i()->ipb_url_filter_option == "black" ? \IPS\Settings::i()->ipb_url_blacklist : \IPS\Settings::i()->ipb_url_whitelist;
	
			if( $links )
			{
				$linkValues = array();
				$linkValues = explode( "," , $links );
	
				if( \IPS\Settings::i()->ipb_url_filter_option == 'white' )
				{
					$listValues[]	= "http://" . parse_url( \IPS\Settings::i()->base_url, PHP_URL_HOST ) . "/*";
				}
	
				if ( !empty( $linkValues ) )
				{
					\IPS\Output::i()->headJs	= \IPS\Output::i()->headJs . "ips.setSetting( '" . \IPS\Settings::i()->ipb_url_filter_option . "list', " . json_encode( $linkValues ) . " );";
				}
			}
		}
							
		/* Are we processing an AJAX upload? */
		if( isset( $_SERVER['HTTP_X_PLUPLOAD'] ) AND \IPS\Login::compareHashes( $_SERVER['HTTP_X_PLUPLOAD'], md5( $this->name . session_id() ) ) )
		{
			try
			{
				/* Chunked */
				if ( $storageClass::$supportsChunking and method_exists( $storageClass, 'chunkInit' ) and isset( \IPS\Request::i()->chunks ) and \IPS\Request::i()->chunks > 1 )
				{
					/* If this is the FIRST chunk, start the process */		
					if ( \IPS\Request::i()->chunk == 0 )
					{
						$ref = $storageClass->chunkInit( \IPS\Request::i()->name, $this->options['storageContainer'], $this->options['obscure'] );
					}
					else
					{
						$ref = $_SESSION['chunkUploads'][ md5( \IPS\Request::i()->name ) . '-' . $this->name ];
					}
					
					/* Process this chunk */
					$ref = $storageClass->chunkProcess( $ref, $_FILES[ $this->name ]['tmp_name'], \IPS\Request::i()->chunk );
					$_SESSION['chunkUploads'][ md5( \IPS\Request::i()->name ) . '-' . $this->name ] = $ref;
					
					/* If this is the LAST chunk, finish the process */		
					if ( \IPS\Request::i()->chunk == \IPS\Request::i()->chunks - 1 )
					{
						$fileObj = $storageClass->chunkFinish( $ref, $this->options['storageExtension'] );

						$fileArray = array(
							'error'				=> NULL,
							'_skipUploadCheck'	=> TRUE,
							'size'				=> $fileObj->filesize(),
							'name'				=> $fileObj->originalFilename,
							'tmp_name'			=> $_FILES[ $this->name ]['tmp_name']
						);
						
						/* If there is an error, an exception will be thrown and will be caught below like normal */
						\IPS\File::validateUpload( $fileArray, $this->options['allowedFileTypes'], $this->options['maxFileSize'] );

						$exif = NULL;
						if ( $fileObj->isImage() )
						{
							try
							{
								$image = \IPS\Image::create( $fileObj->contents(), $this->options['checkImage'] ?: TRUE );

								if( $image::exifSupported() )
								{
									$image->setExifData( $fileObj->contents() );
									$exif = $image->parseExif();
								}

								$hasBeenResized = $image->resizeToMax( $this->options['image']['maxWidth'] ?? NULL, $this->options['image']['maxHeight'] ?? NULL );

								/* If type JPG, image handler may strip meta data on image output */
								if( $hasBeenResized OR $image->hasBeenRotated OR $image->type == 'jpeg' )
								{
									$fileObj->replace( (string) $image );
								}
							}
							catch ( \Exception $e ) { }
						}

						$insertId = \IPS\Db::i()->insert( 'core_files_temp', array(
							'upload_key'		=> md5( $this->name . session_id() ),
							'filename'			=> $fileObj->originalFilename,
							'mime'				=> \IPS\File::getMimeType( $fileObj->originalFilename ),
							'contents'			=> (string) $fileObj,
							'time'				=> time(),
							'storage_extension' => $this->options['storageExtension'],
							'exif'              => $exif ? json_encode( $exif ) : NULL
						) );

						if ( $this->options['callback'] )
						{
							$callbackFunction = $this->options['callback'];
							$r = $callbackFunction( $fileObj );
							if ( $r !== NULL )
							{
								$insertId = $r;
							}
						}
						
						\IPS\Output::i()->json( array(
							'id'		=> $insertId,
							'key'		=> $_SERVER['HTTP_X_PLUPLOAD'],
							'imagesrc'	=> $fileObj->isImage() ? (string) $fileObj->url : NULL,
							'videosrc'	=> $fileObj->isVideo() ? (string) $fileObj->url : NULL,
							'thumbnail'	=> ( $fileObj->isImage() AND $fileObj->attachmentThumbnailUrl !== NULL and \is_string( $fileObj->attachmentThumbnailUrl ) ) ? \IPS\File::get( $this->options['storageExtension'], $fileObj->attachmentThumbnailUrl )->url : NULL,
							'securityKey' => $fileObj->securityKey
						)	);
					}
					else
					{
						\IPS\Output::i()->json( array( 'chunk' => 'OK' ) );
					}
				}
				/* Not chunked */
				else
				{
					foreach ( $this->processUploads() as $insertId => $fileObj )
					{
						\IPS\Output::i()->json( array(
							'id'		=> $insertId,
							'key'		=> $_SERVER['HTTP_X_PLUPLOAD'],
							'imagesrc'	=> $fileObj->isImage() ? (string) $fileObj->url : NULL,
							'videosrc'	=> $fileObj->isVideo() ? (string) $fileObj->url : NULL,
							'thumbnail'	=> ( $fileObj->isImage() AND $fileObj->attachmentThumbnailUrl !== NULL and \is_string( $fileObj->attachmentThumbnailUrl ) ) ? \IPS\File::get( $this->options['storageExtension'], $fileObj->attachmentThumbnailUrl )->url : NULL,
							'securityKey' => $fileObj->securityKey
						)	);
					}
				}
			}
			catch ( \Exception $e )
			{
				\IPS\Log::log( $e, 'upload_failure' );
				
				$message = $e->getMessage();

				if( $e instanceof \IPS\File\Exception )
				{
					$message = \IPS\Member::loggedIn()->language()->addToStack("files-{$e->getCode()}", FALSE, array( 'sprintf' => array( $e->originalFilename ?: $e->file ) ) );
				}

				$subMessage = NULL;

				if( $e instanceof \IPS\File\Exception AND $extra = $e->extraErrorMessage() )
				{
					if ( \IPS\Member::loggedIn()->isAdmin() and \IPS\Member::loggedIn()->language()->checkKeyExists( $extra . "_admin" ) )
					{
						$subMessage = \IPS\Member::loggedIn()->language()->addToStack( $extra . "_admin" );
					}
					else
					{
						$subMessage = \IPS\Member::loggedIn()->language()->addToStack( $extra );
					}
				}
				else
				{
					if ( \IPS\Member::loggedIn()->isAdmin() and \IPS\Member::loggedIn()->language()->checkKeyExists("uploaderr_{$e->getMessage()}_admin") )
					{
						$subMessage = \IPS\Member::loggedIn()->language()->addToStack( "uploaderr_{$e->getMessage()}_admin" );
					}
					elseif ( \IPS\Member::loggedIn()->language()->checkKeyExists("uploaderr_{$e->getMessage()}") )
					{
						$subMessage = \IPS\Member::loggedIn()->language()->addToStack( "uploaderr_{$e->getMessage()}" );
					}
					else
					{
						$subMessage = \IPS\Member::loggedIn()->language()->addToStack("uploaderr_unspecified" );
					}
				}

				\IPS\Output::i()->json( array(
					'error'	=> $message,
					'extra'	=> $e->getCode(),
					'sub'	=> $subMessage
				)	);
			}
		}
		
		/* Set the template */
		$this->template = array( \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' ), 'upload' );
	}
	
	/**
	 * @brief	Build the HTML once or we lose the custom properties sent
	 */
	protected $builtHtml = NULL;

	/**
	 * Get HTML without row template
	 *
	 * @return	string
	 */
	public function html()
	{
		if( $this->builtHtml !== NULL )
		{
			return $this->builtHtml;
		}

		$uploadKey = md5( $this->name . session_id() );

		/* Put the value in an array if we need to */
		if ( $this->value !== NULL and ! empty( $this->value ) )
		{
			if ( $this->options['multiple'] !== TRUE and !\is_array( $this->value ) )
			{
				$this->value = array( $this->value );
			}
		}

		/* Build JSON version of the existing value, which allows the widget to build the interface */
		$existing = array();
		
		if( $this->value and \count( $this->value ) )
		{
			foreach( $this->value as $id => $file )
			{
				/* If this was attachment data, expand */
				if( \is_array( $file ) )
				{
					$attachment	= $file;
					$file		= $file['fileurl'];
					unset( $attachment['fileurl'] );
				}
				/* If last loop was an array, we need to unset it this loop otherwise variable persists */
				else
				{
					unset( $attachment );
				}

				/* Set existing files */
				if ( $this->options['temporary'] )
				{
					$existing[] = array(
						'id'				=> $id,
						'insertable'	 	=> (bool) $this->options['postKey'],
						'hasThumb'			=> false,
						'originalFileName' 	=> $file,
						'size'				=> \IPS\Output\Plugin\Filesize::humanReadableFilesize( $file, FALSE, TRUE ),
						'sizeRaw'			=> $file,
						'custom'			=> NULL
					);
				}
				else
				{
					try
					{
						$existing[] = array(
							'id'				=> $id,
							'insertable'	 	=> (bool) $this->options['postKey'],
							'hasThumb'			=> ( isset( $attachment ) AND isset( $attachment['attach_is_image'] ) ) ? ( $attachment['attach_is_image'] AND $attachment['attach_thumb_location'] ) : $file->isImage(),
							'originalFileName' 	=> $file->originalFilename,
							'thumbnail'			=> ( isset( $attachment ) AND isset( $attachment['attach_is_image'] ) AND $attachment['attach_is_image'] AND $attachment['attach_thumb_location'] ) ? ( (string) \IPS\File::get( $this->options['storageExtension'], $attachment['attach_thumb_location'] )->url ) : ( $file->isImage() ? (string) $file->url : NULL ),
							'size'				=> \IPS\Output\Plugin\Filesize::humanReadableFilesize( $file->filesize(), FALSE, TRUE ),
							'sizeRaw'			=> $file->filesize(),
							'default'			=> ( isset( $attachment ) AND $attachment['default'] ) ? $attachment['default'] : NULL,
						);
					}
					catch( \UnderflowException $e ){}
				}
			}

			foreach( $this->value as $id => $file )
			{
				$this->value[ $id ]	= ( \is_array( $file ) ) ? $file['fileurl'] : $file;
			}
		}

		/* We want this to use decimals even if locale wants commas for decimal separator, i.e. for uploader */
		$maxFileSize	= $this->options['maxFileSize'] ? number_format( $this->options['maxFileSize'], 2, '.', '' ) : $this->options['maxFileSize'];
		
		/* Do we have maximum image dimensions which we can ask plupload to handle for us client-side? */
		$maxImageDims = NULL;
		if ( $this->options['image'] and isset( $this->options['image']['maxWidth'] ) and isset( $this->options['image']['maxHeight'] ) )
		{
			$maxImageDims = "{$this->options['image']['maxWidth']}x{$this->options['image']['maxHeight']}";
		}

		/* The html() method is called more than once, however $this->value is wiped out so if it was an attachments array all of the array properties are lost */
		$templateFunction = $this->template;
		$this->builtHtml = $templateFunction( $this->name, $this->value, $this->options['minimize'], $maxFileSize, $this->options['maxFiles'], $this->maxChunkSize, $this->options['totalMaxSize'], $this->options['allowedFileTypes'], $uploadKey, $this->options['multiple'], $this->options['postKey'], $this->options['temporary'] or ( \IPS\Lang::vleActive() ), $this->options['template'], $existing, $this->options['default'], $this->options['supportsDelete'], $maxImageDims, $this->options['allowStockPhotos'] );
		return $this->builtHtml;
	}

	/**
	 * Get Value
	 *
	 * @return	\IPS\File|array|NULL
	 * @throws	\LogicException
	 * @throws	\DomainException
	 * @throws	\RuntimeException
	 */
	public function getValue()
	{
		/* Get the files we had already */
		$return = array();
		$tempFiles = iterator_to_array( \IPS\Db::i()->select( 'id,contents,filename,exif,requires_moderation,labels', 'core_files_temp', array( 'upload_key=?', md5( $this->name . session_id() ) ) )->setKeyField('id') );
		if ( $this->options['storageExtension'] )
		{
			$existingName = $this->name . '_existing';
			$keepName = $this->name . '_keep';
			$keep = \IPS\Request::i()->$keepName;
			if ( isset( \IPS\Request::i()->$existingName ) and \is_array( \IPS\Request::i()->$existingName ) )
			{
				foreach ( \IPS\Request::i()->$existingName as $id => $tempId )
				{
					if ( isset( $keep[ $id ] ) )
					{
						if ( $tempId and isset( $tempFiles[ $tempId ] ) )
						{
							$file = \IPS\File::get( $this->options['storageExtension'], $tempFiles[ $tempId ]['contents'] );
							$file->tempId = $tempId;
							/* Reset the original filename to the real file name as $this->originalFilename is generated from the now AWS-safe $this->filename */
							$file->originalFilename = $tempFiles[ $tempId ]['filename'];
							$file->exifData	= $tempFiles[ $tempId ]['exif'] ? json_decode( $tempFiles[ $tempId ]['exif'], TRUE ) : NULL;
							$file->requiresModeration = (bool) $tempFiles[ $tempId ]['requires_moderation'];
							$file->labels = $tempFiles[ $tempId ]['labels'];
							$return[ $id ] = $file;
						}
						else
						{
							if ( $this->options['multiple'] )
							{
								if ( isset( $this->defaultValue[ $id ] ) )
								{
									$return[ $id ] = $this->defaultValue[ $id ];
								}
							}
							elseif ( $id == 0 )
							{
								$return[ $id ] = $this->defaultValue;
							}
						}
					}
					elseif ( !$this->options['retainDeleted'] )
					{
						if ( $tempId and isset( $tempFiles[ $tempId ] ) )
						{
							\IPS\Db::i()->delete( 'core_files_temp', array( 'id=?', $tempId ) );
							
							try
							{
								\IPS\File::get( $this->options['storageExtension'], $tempFiles[ $tempId ]['contents'] )->delete();
							}
							catch ( \Exception $e ) { }
						}
						else
						{
							if ( $this->options['multiple'] )
							{
								if ( isset( $this->defaultValue[ $id ] ) )
								{
									/* Don't delete file if new file upload has same name */
									$okToDelete = TRUE;
									foreach( $tempFiles as $tid => $tmpFile )
									{
										if ( (string) $this->defaultValue[ $id ] == $tmpFile['contents'] )
										{
											$okToDelete = FALSE;
										}
									}
									
									if ( $okToDelete )
									{
										try
										{
											$this->defaultValue[ $id ]->delete();
										}
										catch ( \Exception $e ) { }
									}
								}
							}
							else
							{
								if ( $this->defaultValue )
								{
									try
									{
										if ( \is_array( $this->defaultValue ) )
										{
											foreach( $this->defaultValue as $file )
											{ 
												/* Don't delete file if new file upload has same name */
												$okToDelete = TRUE;
												foreach( $tempFiles as $id => $tmpFile )
												{
													if ( (string) $file == $tmpFile['contents'] )
													{
														$okToDelete = FALSE;
													}
												}
												
												if ( $okToDelete )
												{
													$file->delete();
												}
											}
										}
										else
										{
											/* Don't delete file if new file upload has same name */
											$okToDelete = TRUE;
											foreach( $tempFiles as $id => $tmpFile )
											{
												if ( (string) $this->defaultValue == $tmpFile['contents'] )
												{
													$okToDelete = FALSE;
												}
											}
											
											if ( $okToDelete )
											{
												$this->defaultValue->delete();
											}
										}
									}
									catch ( \Exception $e ) { }
								}
							}
						}
					}
				}
			}
		}
		
		/* Process files from noscript fallback - If this is just an AJAX validate, don't do anything so we still have the files when we actually submit  */
		if ( !\IPS\Request::i()->ajaxValidate )
		{			
			try
			{
				/* We used to use array_merge but this reindexes the array - we want to retain the keys */
				$return = $return + $this->processUploads( "{$this->name}_noscript" );
			}
			catch ( \DomainException $e )
			{
				/* If there is no file and field is not required, then that's fine */
				if( $e->getCode() !== 1 or $this->required )
				{
					/* We have to format the message because there are variables to swap out. */
					if ( \IPS\Member::loggedIn()->language()->checkKeyExists( 'pluploaderr_' . $e->getMessage() ) )
					{
						$message	= \IPS\Member::loggedIn()->language()->get( 'pluploaderr_' . $e->getMessage() );
						$message	= str_replace( '{{max_file_size}}', $this->maxChunkSize, $message );
						if ( \is_array( $this->options['allowedFileTypes'] ) )
						{
							$message	= str_replace( '{{allowed_extensions}}', implode( ', ', $this->options['allowedFileTypes'] ), $message );
						}
						$message	= str_replace( '{{server_error_code}}', $e->getCode(), $message );
									
						throw new \DomainException( $message, $e->getCode() );
					}
					else
					{
						throw $e;
					}
				}
			}
			catch ( \RuntimeException $e )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('upload_error_generic', FALSE, array( 'sprintf' => array( $e->getMessage() ) ) ), $e->getCode() );
			}
		}
		
		/* Check we haven't exceeded the maximum total size */
		if ( $this->options['totalMaxSize'] !== NULL )
		{
			$total = 0;
			foreach ( $return as $file )
			{
				$total += $file->filesize();
			}
			if ( $total > $this->options['totalMaxSize'] * 1048576 )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('uploaderr_total_size', FALSE, array( 'sprintf' => array( \IPS\Output\Plugin\Filesize::humanReadableFilesize( $this->options['totalMaxSize'] * 1048576 ) ) ) ) );
			}
		}

		/* Now fix the array in case we are using the defaultValue which was array( fileurl =>, default => ) */
		$toReturn = array();

		foreach( $return as $k => $v )
		{
			$toReturn[ $k ] = ( \is_array( $v ) AND isset( $v['fileurl'] ) ) ? $v['fileurl'] : $v;
		}

		$return = $toReturn;
		
		/* Return */
		if ( !$this->options['multiple'] )
		{
			return array_pop( $return );
		}

		return $return;
	}
	
	/**
	 * Process Uploads
	 *
	 * @param	string	$fieldName	The field name to look for
	 * @return	array
	 * @throws	\DomainException
	 */
	protected function processUploads( $fieldName=NULL )
	{
		$return = array();

		/* Temporary - just process uploads and return paths */
		if ( $this->options['temporary'] )
		{
			foreach( \IPS\File::normalizeFilesArray( $fieldName ) as $file )
			{
				\IPS\File::validateUpload( $file, $this->options['allowedFileTypes'], $this->options['maxFileSize'] );

				$ext = mb_strtolower( mb_substr( $file['name'], ( mb_strrpos( $file['name'], '.' ) + 1 ) ) );

				/* Don't allow "XSS" in images */
				if( \in_array( $ext, \IPS\File::$safeFileExtensions ) AND \in_array( $ext, \IPS\Image::supportedExtensions() ) )
				{
					if( \IPS\File::checkXssInFile( file_get_contents( $file['tmp_name'] ) ) )
					{
						throw new \DomainException( "SECURITY_EXCEPTION_RAISED", 99 );
					}
				}

				$return[] = $file['tmp_name'];
			}
			
			return $return;
		}
		/* Normal - send to storage extension */
		else
		{
			$options = $this->options;
			$canBeModerated = $options['canBeModerated'];
			$exifData = array();

			$fileObjects = \IPS\File::createFromUploads( $this->options['storageExtension'], $fieldName, $this->options['allowedFileTypes'], $this->options['maxFileSize'], $this->options['totalMaxSize'], 0, function( $contents, $filename, $i ) use ( $options, &$requiresModeration, $canBeModerated, &$exifData )
			{
				$ext = mb_strtolower( mb_substr( $filename, mb_strrpos( $filename, '.' ) + 1 ) );

				/* Do image-specific stuff */
				if ( \in_array( $ext, \IPS\Image::supportedExtensions() ) )
				{
					/* Resize */
					$image = NULL;

					try
					{
						$image = \IPS\Image::create( $contents, $options['checkImage'] ?: TRUE );
						$hasBeenResized = $image->resizeToMax( $options['image']['maxWidth'] ?? NULL, $options['image']['maxHeight'] ?? NULL );

						/* If type JPG, image handler may strip meta data on image output */
						if( $hasBeenResized OR $image->hasBeenRotated OR $image->type == 'jpeg' )
						{
							$exifData[ $i ] = $image->parseExif();
							$contents = (string) $image;
						}
					}
					catch ( \Exception $e ) {}
				}
				
				return $contents;
			}, $this->options['storageContainer'], $this->options['obscure'] );

			foreach ( $fileObjects as $i => $fileObj )
			{
				$exif = NULL;

				if( isset( $exifData[ $i ] ) AND $fileExifData = $exifData[ $i ] OR ( \IPS\Image::exifSupported() and $fileObj->isImage() and $fileExifData = $fileObj->exifData ) )
				{
					$exif	= json_encode( $fileExifData );
				}

				$data = $this->populateTempData( array(
					'upload_key'				=> md5( $this->name . session_id() ),
					'filename'				=> $fileObj->originalFilename,
					'mime'					=> \IPS\File::getMimeType( $fileObj->originalFilename ),
					'contents'				=> (string) $fileObj,
					'time'					=> time(),
					'storage_extension' 	=> $this->options['storageExtension'],
					'exif'					=> $exif
				), $fileObj, $options );

				$insertId = \IPS\Db::i()->insert( 'core_files_temp', $data );

				if ( $this->options['callback'] )
				{
					$callbackFunction = $this->options['callback'];
					$r = $callbackFunction( $fileObj );

					if ( $r !== NULL )
					{
						$insertId = $r;
					}
				}
				
				$return[ $insertId ] = $fileObj;
			}

			return $return;
		}
	}
	
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @return	TRUE
	 */
	public function validate()
	{
		parent::validate();
		
		if ( $this->required and empty( $this->value ) and ( !\IPS\Request::i()->ajaxValidate or !$this->options['temporary'] ) )
		{
			throw new \InvalidArgumentException('form_required');
		}
	}

	/**
	 * Finish populating the data before its inserted
	 *
	 * @param array 	$data		Array of data to change/add to
	 * @param \IPS\File $fileObj	File Object data
	 * @param array		$options	Field options
	 * @return	array
	 */
	protected function populateTempData( array $data, \IPS\File $fileObj, array $options ): array
	{
		return $data;
	}
	
	/**
	 * String Value
	 *
	 * @param	mixed	$value		The value
	 * @return	string|null
	 */
	public static function stringValue( $value )
	{
		if ( \is_array( $value ) )
		{
			return implode( ',', array_map( function( $v )
			{
				if ( \is_object( $v ) )
				{
					return (string) $v->url;
				}
			}, $value ) );
		}
		
		return ( $value ) ? (string) $value->url : NULL;
	}
	
	/**
	 * Get the maximum chunk size (i.e. PHP's limit) in bytes
	 *
	 * @return	int|NULL
	 */
	public static function maxChunkSize()
	{
		if ( $potentialValues = static::maxChunkSizeValues() )
		{
			return min( $potentialValues ); 
		}
		return NULL;
	}
	
	/**
	 * Get the maximum chunk size (i.e. PHP's limit) in bytes
	 *
	 * @return	int|NULL
	 */
	public static function maxChunkSizeValues()
	{
		$potentialValues = array();
		if( (float) ini_get('upload_max_filesize') > 0 )
		{
			$potentialValues['upload_max_filesize']	= \IPS\File::returnBytes( ini_get('upload_max_filesize') );
		}
		if( (float) ini_get('post_max_size') > 0 )
		{
			$potentialValues['post_max_size'] = \IPS\File::returnBytes( ini_get('post_max_size') ) - 1048576;
		}
		if( (float) ini_get('memory_limit') > 0 )
		{
			$potentialValues['memory_limit'] = \IPS\File::returnBytes( ini_get('memory_limit') );
		}
		return $potentialValues;
	}
}