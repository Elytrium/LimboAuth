<?php
/**
 * @brief		Database Records API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		21 Feb 2020
 */

namespace IPS\cms\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Database Records API
 */
class _records extends \IPS\Content\Api\ItemController
{
	/**
	 * Class
	 */
	protected $class = NULL;
	
	/**
	 * Get endpoint data
	 *
	 * @param	array	$pathBits	The parts to the path called
	 * @param	string	$method		HTTP method verb
	 * @return	array
	 * @throws	\RuntimeException
	 */
	protected function _getEndpoint( $pathBits, $method = 'GET' )
	{
		if ( !\count( $pathBits ) )
		{
			throw new \RuntimeException;
		}
		
		$database = array_shift( $pathBits );
		if ( !\count( $pathBits ) )
		{
			return array( 'endpoint' => 'index', 'params' => array( $database ) );
		}
		
		$nextBit = array_shift( $pathBits );
		if ( \intval( $nextBit ) != 0 )
		{
			if ( \count( $pathBits ) )
			{
				return array( 'endpoint' => 'item_' . array_shift( $pathBits ), 'params' => array( $database, $nextBit ) );
			}
			else
			{				
				return array( 'endpoint' => 'item', 'params' => array( $database, $nextBit ) );
			}
		}
				
		throw new \RuntimeException;
	}
		
	/**
	 * GET /cms/records/{database_id}
	 * Get list of records
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, only records the authorized user can view will be included
	 * @param		int		$database			Database ID
	 * @apiparam	string	ids			        Comma-delimited list of record IDs
	 * @apiparam	string	categories			Comma-delimited list of category IDs
	 * @apiparam	string	authors				Comma-delimited list of member IDs - if provided, only records started by those members are returned
	 * @apiparam	int		locked				If 1, only records which are locked are returned, if 0 only unlocked
	 * @apiparam	int		hidden				If 1, only records which are hidden are returned, if 0 only not hidden
	 * @apiparam	int		pinned				If 1, only records which are pinned are returned, if 0 only not pinned
	 * @apiparam	int		featured			If 1, only records which are featured are returned, if 0 only not featured
	 * @apiparam	string	sortBy				What to sort by. Can be 'date' for creation date, 'title', 'updated' or leave unspecified for ID
	 * @apiparam	string	sortDir				Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page				Page number
	 * @apiparam	int		perPage				Number of results per page - defaults to 25
	 * @throws		2T306/1	INVALID_DATABASE	The database ID does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\Api\PaginatedResponse<IPS\cms\Records>
	 */
	public function GETindex( $database )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			if ( $this->member and !$database->can( 'view', $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			$this->class = 'IPS\cms\Records' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T306/1', 404 );
		}	
		
		/* Where clause */
		$where = array();
		
		/* Return */
		return $this->_list( $where, 'categories' );
	}
	
	/**
	 * GET /cms/records/{database_id}/{record_id}
	 * View details about a specific record
	 *
	 * @param		int		$database		Database ID Number
	 * @param		int		$record			Record ID Number
	 * @throws		2T306/2	INVALID_DATABASE	The database ID does not exist or the authorized user does not have permission to view it
	 * @throws		2T306/3	INVALID_ID			The record ID does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\cms\Records
	 */
	public function GETitem( $database, $record )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			if ( $this->member and !$database->can( 'view', $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			
			$this->class = 'IPS\cms\Records' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T306/2', 404 );
		}
		
		/* Return */
		try
		{
			$className = $this->class;
			$record = $className::load( $record );
			if ( $this->member and !$record->can( 'read', $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			
			return new \IPS\Api\Response( 200, $record->apiOutput( $this->member ) );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T306/3', 404 );
		}
	}
	
	/**
	 * POST /cms/records/{database_id}
	 * Create a record
	 *
	 * @note	For requests using an OAuth Access Token for a particular member, any parameters the user doesn't have permission to use are ignored (for example, locked will only be honoured if the authenticated user has permission to lock records).
	 * @param		int					$database			Database ID Number
	 * @reqapiparam	int					category			The ID number of the category the record should be created in. If the database does not use categories, this is not required
	 * @reqapiparam	int					author				The ID number of the member creating the record (0 for guest) Required for requests made using an API Key or the Client Credentials Grant Type. For requests using an OAuth Access Token for a particular member, that member will always be the author
	 * @reqapiparam	object				fields				Field values. Keys should be the field ID, and the value should be the value. For fields of the Upload type, the type of the value must also be an object itself, with each key set as the filename and the value set as the raw file contents. For requests using an OAuth Access Token for a particular member, values will be sanatised where necessary. For requests made using an API Key or the Client Credentials Grant Type values will be saved unchanged.
	 * @apiparam	string				prefix				Prefix tag
	 * @apiparam	string				tags				Comma-separated list of tags (do not include prefix)
	 * @apiparam	datetime			date				The date/time that should be used for the record date. If not provided, will use the current date/time. Ignored for requests using an OAuth Access Token for a particular member.
	 * @apiparam	string				ip_address			The IP address that should be stored for the record. If not provided, will use the IP address from the API request. Ignored for requests using an OAuth Access Token for a particular member.
	 * @apiparam	int					locked				1/0 indicating if the record should be locked
	 * @apiparam	int					hidden				0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @apiparam	int					pinned				1/0 indicating if the record should be pinned
	 * @apiparam	int					featured			1/0 indicating if the record should be featured
	 * @apiparam	bool				anonymous			If 1, the item will be posted anonymously.
	 * @throws		2T306/4				INVALID_DATABASE	The database ID does not exist
	 * @throws		1T306/5				NO_CATEGORY			The category ID does not exist
	 * @throws		1T306/6				NO_AUTHOR			The author ID does not exist
	 * @throws		2T306/G				NO_PERMISSION		The authorized user does not have permission to create a record in that category
	 * @throws		1T306/D				TITLE_CONTENT_REQUIRED	No title or content fields were supplied
	 * @throws		1S306/E				UPLOAD_FIELD_NOT_OBJECT	Field of type Upload was supplied without being set as an object
	 * @throws		1T306/F				UPLOAD_FIELD_NO_FILES	Field of type Upload was supplied without any files attached
	 * @throws		1T306/G				UPLOAD_FIELD_MULTIPLE_NOT_ALLOWED	Field of type Upload and a maximum of 1 file was supplied with multiple files
	 * @throws		1T306/H				UPLOAD_FIELD_IMAGE_NOT_SUPPORTED	Field of type Upload was supplied with an image was of an unsupported extension
	 * @throws		1T306/I				UPLOAD_FIELD_IMAGES_ONLY	Field of type Upload set to be image only was supplied with a non-image attachment
	 * @throws		1T306/J				UPLOAD_FIELD_EXTENSION_NOT_ALLOWED	Field of type Upload was supplied with one or more attachments of a non-supported file extension
	 * @return		\IPS\cms\Records
	 */
	public function POSTindex( $database )
	{		
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T306/4', 404 );
		}
				
		/* Get category */
		try
		{
			$categoryClass = 'IPS\cms\Categories' . $database->id;

			if ( $database->use_categories )
			{
				$category = $categoryClass::load( \IPS\Request::i()->category );
			}
			else
			{
				$category = $categoryClass::load( $database->default_category );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'NO_CATEGORY', '1T306/5', 400 );
		}
		
		/* Get author */
		if ( $this->member )
		{
			if ( !$category->can( 'add', $this->member ) )
			{
				throw new \IPS\Api\Exception( 'NO_PERMISSION', '2T306/G', 403 );
			}
			$author = $this->member;
		}
		else
		{
			if ( \IPS\Request::i()->author )
			{
				$author = \IPS\Member::load( \IPS\Request::i()->author );
				if ( !$author->member_id )
				{
					throw new \IPS\Api\Exception( 'NO_AUTHOR', '1T306/6', 400 );
				}
			}
			else
			{
				if ( \IPS\Request::i()->author === 0 ) 
				{
					$author = new \IPS\Member;
					$author->name = \IPS\Request::i()->author_name;
				}
				else 
				{
					throw new \IPS\Api\Exception( 'NO_AUTHOR', '1T306/6', 400 );
				}
			}
		}

		try
		{
			$record = $this->_create( $category, $author );
		}
		catch( \DomainException $e )
		{
			throw new \IPS\Api\Exception( $e->getMessage(), '1T306/D', 400 );
		}

		/* Sync Topic */
		$class = $this->class;
		if ( !$class::$skipTopicCreation and \IPS\Application::appIsEnabled('forums') and $record->_forum_record and $record->_forum_forum and ! $record->hidden() and ! $record->record_future_date )
		{
			try
			{
				$record->syncTopic();
			}
			catch( \Exception $ex ) { }
		}

		/* Refresh category and database caches */
		$record->container()->setLastComment();
		$record->container()->setLastReview();
		$record->container()->save();

		/* Do it */
		return new \IPS\Api\Response( 201, $record->apiOutput( $this->member ) );
	}
	
	/**
	 * POST /cms/records/{database_id}/{record_id}
	 * Edit a record
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, any parameters the user doesn't have permission to use are ignored (for example, locked will only be honoured if the authenticated user has permission to lock topics).
	 * @param		int					$database		Database ID Number
	 * @param		int					$record			Record ID Number
	 * @apiparam	int					category		The ID number of the category the record should be created in. If the database does not use categories, this is not required
	 * @apiparam	int					author			The ID number of the member creating the record (0 for guest). Ignored for requests using an OAuth Access Token for a particular member.
	 * @reqapiparam	object				fields				Field values. Keys should be the field ID, and the value should be the value. For fields of the Upload type, the type of the value must also be an object itself, with each key set as the filename and the value set as the raw file contents. For requests using an OAuth Access Token for a particular member, values will be sanatised where necessary. For requests made using an API Key or the Client Credentials Grant Type values will be saved unchanged.
	 * @apiparam	string				prefix			Prefix tag
	 * @apiparam	string				tags			Comma-separated list of tags (do not include prefix)
	 * @apiparam	string				ip_address		The IP address that should be stored for the record. If not provided, will use the IP address from the API request. Ignored for requests using an OAuth Access Token for a particular member.
	 * @apiparam	int					locked			1/0 indicating if the record should be locked
	 * @apiparam	int					hidden			0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @apiparam	int					pinned			1/0 indicating if the record should be pinned
	 * @apiparam	int					featured		1/0 indicating if the record should be featured
	 * @apiparam	bool				anonymous		If 1, the item will be posted anonymously.
	 * @throws		2T306/9				INVALID_DATABASE	The database ID does not exist
	 * @throws		2T306/6				INVALID_ID		The record ID is invalid or the authorized user does not have permission to view it
	 * @throws		1T306/7				NO_CATEGORY		The category ID does not exist or the authorized user does not have permission to post in it
	 * @throws		1T306/8				NO_AUTHOR		The author ID does not exist
	 * @throws		2T306/H				NO_PERMISSION	The authorized user does not have permission to edit the record
	 * @throws		1S306/E				UPLOAD_FIELD_NOT_OBJECT	Field of type Upload was supplied without being set as an object
	 * @throws		1T306/F				UPLOAD_FIELD_NO_FILES	Field of type Upload was supplied without any files attached
	 * @throws		1T306/G				UPLOAD_FIELD_MULTIPLE_NOT_ALLOWED	Field of type Upload and a maximum of 1 file was supplied with multiple files
	 * @throws		1T306/H				UPLOAD_FIELD_IMAGE_NOT_SUPPORTED	Field of type Upload was supplied with an image was of an unsupported extension
	 * @throws		1T306/I				UPLOAD_FIELD_IMAGES_ONLY	Field of type Upload set to be image only was supplied with a non-image attachment
	 * @throws		1T306/J				UPLOAD_FIELD_EXTENSION_NOT_ALLOWED	Field of type Upload was supplied with one or more attachments of a non-supported file extension
	 * @return		\IPS\cms\Records
	 */
	public function POSTitem( $database, $record )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			$this->class = 'IPS\cms\Records' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T306/9', 404 );
		}
		
		/* Load record */
		try
		{
			$className = $this->class;
			$record = $className::load( $record );
			if ( $this->member and !$record->can( 'read', $this->member ) )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T306/6', 404 );
		}
		if ( $this->member and !$record->canEdit( $this->member ) )
		{
			throw new \IPS\Api\Exception( 'NO_PERMISSION', '2T306/H', 403 );
		}
			
		/* New category */
		if ( $database->use_categories and isset( \IPS\Request::i()->category ) and \IPS\Request::i()->category != $record->category_id and ( !$this->member or $record->canMove( $this->member ) ) )
		{
			try
			{
				$categoryClass = 'IPS\cms\Categories' . $database->id;

				$newCategory = $categoryClass::load( \IPS\Request::i()->category );
				if ( $this->member and !$newCategory->can( 'add', $this->member ) )
				{
					throw new \OutOfRangeException;
				}
				
				$record->move( $newCategory );
			}
			catch ( \OutOfRangeException $e )
			{
				throw new \IPS\Api\Exception( 'NO_CATEGORY', '1T306/7', 400 );
			}
		}
		
		/* New author */
		if ( !$this->member and isset( \IPS\Request::i()->author ) )
		{				
			try
			{
				$member = \IPS\Member::load( \IPS\Request::i()->author );
				if ( !$member->member_id )
				{
					throw new \OutOfRangeException;
				}
				
				$record->changeAuthor( $member );
			}
			catch ( \OutOfRangeException $e )
			{
				throw new \IPS\Api\Exception( 'NO_AUTHOR', '1T306/8', 400 );
			}
		}
		
		/* Everything else */
		$this->_createOrUpdate( $record, 'edit' );
		
		/* Save and return */
		$record->save();

		/* Sync Topic */
		$class = $this->class;
		if ( !$class::$skipTopicCreation and \IPS\Application::appIsEnabled('forums') and $record->_forum_record and $record->_forum_forum and ! $record->hidden() and ! $record->record_future_date )
		{
			try
			{
				$record->syncTopic();
			}
			catch( \Exception $ex ) { }
		}

		return new \IPS\Api\Response( 200, $record->apiOutput( $this->member ) );
	}

	/**
	 * Create or update record
	 *
	 * @param	\IPS\Content\Item	$item	The item
	 * @param	string				$type	add or edit
	 * @return	\IPS\Content\Item
	 */
	protected function _createOrUpdate( \IPS\Content\Item $item, $type='add' )
	{
		$thumbImages = array();
		/* Set field values */
		if ( isset( \IPS\Request::i()->fields ) )
		{
			$fieldsClass = str_replace( 'Records', 'Fields', \get_class( $item ) );
			foreach ( $fieldsClass::data() as $key => $field )
			{
				if ( isset( \IPS\Request::i()->fields[ $field->id ] ) )
				{
					if ( !$this->member or $field->can( $type, $this->member ) )
					{
						$key = "field_{$field->_id}";

						$value = \IPS\Request::i()->fields[ $field->id ];
						if ( $field->type === 'Editor' and $this->member )
						{
							$value = \IPS\Text\Parser::parseStatic( $value, TRUE, NULL, $this->member, 'cms_Records' );
						}
						elseif ( $field->type === 'Upload' )
						{
							$multiple = $field->is_multiple;
							$imageOnly = ( isset( $field->extra[ 'type' ] ) AND $field->extra[ 'type' ] === 'image' );
							$extensions = \is_array( $field->allowed_extensions ) ? $field->allowed_extensions : array();

							/* Did they meet the api parameter type requirement (the field must be an object) */
							if ( !\is_array( \IPS\Request::i()->fields[ $field->id ] ) )
							{
								throw new \IPS\Api\Exception( 'UPLOAD_FIELD_NOT_OBJECT', '1S306/E', 400 );
							}

							/* Are we actually uploading files? */
							if ( empty( \IPS\Request::i()->fields[ $field->id ] ) )
							{
								throw new \IPS\Api\Exception( 'UPLOAD_FIELD_NO_FILES', '1T306/F', 400 );
							}

							/* Can we upload more than one file? */
							if ( !$multiple AND ( \count( \IPS\Request::i()->fields[ $field->id ] ) > 1 ) )
							{
								throw new \IPS\Api\Exception( 'UPLOAD_FIELD_MULTIPLE_NOT_ALLOWED', '1T306/G', 400 );
							}
							
							/* Does each file meet the criteria? */
							$files = array();
							foreach ( array_keys( \IPS\Request::i()->fields[ $field->id ] ) as $name )
							{
								$files[] = $name;
								$components = explode( '.', $name );
								$extension = array_pop( $components );

								/* Can we upload a non image? */
								if ( $imageOnly )
								{
									/* Is the image type supported? */
									if ( !\in_array( $extension, \IPS\Image::supportedExtensions() ) )
									{
										throw new \IPS\Api\Exception( 'UPLOAD_FIELD_IMAGE_NOT_SUPPORTED', '1T306/H', 400 );
									}

									/* Is there a max image dimensions specified? */
									$maxSize = ( isset( $field->extra[ 'maxsize' ] ) AND \is_array( $field->extra[ 'maxsize' ] ) ) ? $field->extra[ 'maxsize' ] : array( 0, 0 );

									try
									{
										/* Is it valid image data? */
										$image = \IPS\Image::create( $_POST[ 'fields' ][ $field->id ][ $name ] );

										/* Resize if too large */
										$image->resizeToMax( (int) $maxSize[0] ?: NULL, (int) $maxSize[1] ?: NULL );
										$_POST[ 'fields' ][ $field->id ][ $name ] = (string) $image;
									}
									catch ( \InvalidArgumentException $e )
									{
										throw new \IPS\Api\Exception( 'UPLOAD_FIELD_IMAGES_ONLY', '1T306/I', 400 );
									}
								}

								/* Can we add a file of this type? */
								if ( ( \count( $extensions ) > 0 ) AND !\in_array( '.' . $extension, $extensions ) )
								{
									throw new \IPS\Api\Exception( 'UPLOAD_FIELD_EXTENSION_NOT_ALLOWED', '1T306/J', 400 );
								}
							}

							$urls = array();
							foreach ( $files as $name )
							{
								$file = \IPS\File::create( 'cms_Records', $name, $_POST[ 'fields' ][ $field->id ][ $name ] );
								$urls[] = (string) $file;

								/* Do we have to create a thumbnail? */
								if ( $imageOnly AND isset( $field->extra['thumbsize'] ) AND \is_array( $field->extra['thumbsize'] ) AND ( (int) $field->extra['thumbsize'][0] OR (int) $field->extra['thumbsize'][1] ) )
								{
									$thumbImages[$name] = array( $file, $field );
								}
							}
							$value = implode( ',', $urls );
						}
						
						$item->$key = $value;
					}
				}
			}
		}

		$date = ( !$this->member and \IPS\Request::i()->date ) ? new \IPS\DateTime( \IPS\Request::i()->date ) : \IPS\DateTime::create();

		if( !$item->record_saved )
		{
			$item->record_saved		= $date->getTimestamp();
		}

		if( !$item->record_updated )
		{
			$item->record_updated		= $item->record_saved;
		}

		if( $type == 'add' AND ( !$item->_title OR !$item->_content ) )
		{
			throw new \DomainException( 'TITLE_CONTENT_REQUIRED' );
		}
		
		/* Set FURL */
		if ( isset( \IPS\Request::i()->fields['fields'][ $item->database()->field_title ] ) )
		{
			$item->record_dynamic_furl = \IPS\Http\Url\Friendly::seoTitle( \IPS\Request::i()->fields['fields'][ $item->database()->field_title ] );
		}

		$item = parent::_createOrUpdate( $item, $type );
		$item->save();

		/* Upload fields specified with images needing a thumbnail? */
		foreach ( $thumbImages as $name => $imageData )
		{
			$file = $imageData[0];
			$field = $imageData[1];
			$thumbWidth = ( isset( $field->extra['thumbsize'][0] ) ) ? (int) $field->extra[ 'thumbsize' ][0] : NULL;
			$thumbHeight = ( isset( $field->extra['thumbsize'][1] ) ) ? (int) $field->extra[ 'thumbsize' ][1] : NULL;
			$thumbnail = $file->thumbnail( 'cms_Records', $thumbWidth ?: NULL, $thumbHeight ?: NULL );
			\IPS\Db::i()->insert( 'cms_database_fields_thumbnails', array(
				'thumb_original_location' => (string) $file,
				'thumb_location'		  => (string) $thumbnail,
				'thumb_field_id'		  => $field->id,
				'thumb_database_id'		  => $field->database_id,
				'thumb_record_id'		  => $item->primary_id_field,
			) );
		}

		/* Pass up */
		return $item;
	}
	
	/**
	 * GET /cms/records/{database_id}/{record_id}/comments
	 * Get comments on a record
	 *
	 * @param		int					$database		Database ID Number
	 * @param		int					$record			Record ID Number
	 * @apiparam	int		hidden		If 1, only comments which are hidden are returned, if 0 only not hidden
	 * @apiparam	string	sortDir		Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page		Page number
	 * @apiparam	int		perPage		Number of results per page - defaults to 25
	 * @throws		2T306/C		INVALID_DATABASE	The database ID does not exist or the authorized user does not have permission to view it
	 * @throws		2T306/D		INVALID_ID	The entry ID does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\Api\PaginatedResponse<IPS\cms\Records\Comment>
	 */
	public function GETitem_comments( $database, $record )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			if ( $this->member and !$database->can( 'view', $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			
			$this->class = 'IPS\cms\Records' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T306/C', 404 );
		}
		
		/* Return */
		try
		{
			return $this->_comments( $record, 'IPS\cms\Records\Comment' . $database->id );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T306/D', 404 );
		}
	}
	
	/**
	 * GET /cms/records/{database_id}/{record_id}/reviews
	 * Get reviews on a record
	 *
	 * @param		int					$database		Database ID Number
	 * @param		int					$record			Record ID Number
	 * @apiparam	int		hidden		If 1, only comments which are hidden are returned, if 0 only not hidden
	 * @apiparam	string	sortDir		Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page		Page number
	 * @apiparam	int		perPage		Number of results per page - defaults to 25
	 * @throws		2T306/E		INVALID_DATABASE	The database ID does not exist or the authorized user does not have permission to view it
	 * @throws		2T306/F		INVALID_ID	The entry ID does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\Api\PaginatedResponse<IPS\cms\Records\Review>
	 */
	public function GETitem_reviews( $database, $record )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			if ( $this->member and !$database->can( 'view', $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			
			$this->class = 'IPS\cms\Records' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T306/E', 404 );
		}
		
		/* Return */
		try
		{
			return $this->_comments( $record, 'IPS\cms\Records\Review' . $database->id );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T306/F', 404 );
		}
	}
		
	/**
	 * DELETE /cms/records/{database_id}/{record_id}
	 * Delete an entry
	 *
	 * @param		int			$database		Database ID Number
	 * @param		int			$record			Record ID Number
	 * @throws		2T306/A		INVALID_DATABASE	The database ID does not exist or the authorized user does not have permission to view it
	 * @throws		2T306/B		INVALID_ID			The entry ID does not exist
	 * @throws		2T306/I		NO_PERMISSION		The authorized user does not have permission to delete the record.
	 * @return		void
	 */
	public function DELETEitem( $database, $record )
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			if ( $this->member and !$database->can( 'view', $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			
			$this->class = 'IPS\cms\Records' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '2T306/A', 404 );
		}
		
		/* Load record */
		try
		{
			$className = $this->class;
			$record = $className::load( $record );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2T306/B', 404 );
		}
		if ( $this->member and !$record->canDelete( $this->member ) )
		{
			throw new \IPS\Api\Exception( 'NO_PERMISSION', '2T306/I', 404 );
		}
		
		/* Delete and return */
		$record->delete();
		return new \IPS\Api\Response( 200, NULL );
	}

	/**
	 * DELETE /cms/records/{database_id}/{record_id}/react
	 * Deletes a reaction
	 *
	 * @param		int			$database		Database ID Number
	 * @param		int			$record			Record ID Number
	 * @apiparam	int		id			ID of the reaction to add
	 * @apiparam	int     author      ID of the member reacting
	 * @return		\IPS\cms\Records
	 * @throws		1T306/K		INVALID_DATABASE	The database ID does not exist or the authorized user does not have permission to view it
	 * @throws		1T306/L		NO_REACTION	The reaction ID does not exist
	 * @throws		1T306/M		REACT_ERROR	Error adding the reaction
	 * @throws		1T306/N		INVALID_ID	Object ID does not exist
	 * @note		If the author has already reacted to this content, any existing reaction will be removed first
	 */
	public function DELETEitem_react( $database, $record ): \IPS\Api\Response
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			if ( $this->member and !$database->can( 'view', $this->member ) )
			{
				throw new \OutOfRangeException;
			}

			$this->class = 'IPS\cms\Records' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '1T306/K', 404 );
		}

		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->author );
		}
		catch( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'NO_AUTHOR', '1T306/L', 404 );
		}

		try
		{
			$class = $this->class;
			$object = $class::load( $id );

			$object->removeReaction( $member );

			return new \IPS\Api\Response( 200, $object->apiOutput( $this->member ) );
		}
		catch ( \DomainException $e )
		{
			throw new \IPS\Api\Exception( $e->getMessage(), '1T306/M', 403 );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '1T306/N', 404 );
		}
	}

	/**
	 * POST /cms/records/{database_id}/{record_id}/react
	 * Add a reaction
	 *
	 * @param		int			$database		Database ID Number
	 * @param		int			$record			Record ID Number
	 * @apiparam	int		id			ID of the reaction to add
	 * @apiparam	int     author      ID of the member reacting
	 * @return		\IPS\cms\Records
	 * @throws		1T306/O		INVALID_DATABASE	The database ID does not exist or the authorized user does not have permission to view it
	 * @throws		1T306/P		NO_REACTION	The reaction ID does not exist
	 * @throws		1T306/Q		NO_AUTHOR	The author ID does not exist
	 * @throws		1T306/R		REACT_ERROR	Error adding the reaction
	 * @throws		1T306/S		INVALID_ID	Object ID does not exist
	 * @note		If the author has already reacted to this content, any existing reaction will be removed first
	 */
	public function POSTitem_react( $database, $record ): \IPS\Api\Response
	{
		/* Load database */
		try
		{
			$database = \IPS\cms\Databases::load( $database );
			if ( $this->member and !$database->can( 'view', $this->member ) )
			{
				throw new \OutOfRangeException;
			}

			$this->class = 'IPS\cms\Records' . $database->id;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_DATABASE', '1T306/O', 404 );
		}

		try
		{
			$reaction = \IPS\Content\Reaction::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'NO_REACTION', '1T306/P', 404 );
		}

		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->author );
		}
		catch( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'NO_AUTHOR', '1T306/Q', 404 );
		}

		try
		{
			$class = $this->class;
			$object = $class::load( $record );
			$object->react( $reaction, $member );

			return new \IPS\Api\Response( 200, $object->apiOutput( $this->member ) );
		}
		catch ( \DomainException $e )
		{
			throw new \IPS\Api\Exception( 'REACT_ERROR_' . $e->getMessage(), '1T306/R', 403 );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '1T306/S', 404 );
		}
	}
}
