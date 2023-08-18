<?php
/**
 * @brief		Gallery Submission
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Mar 2014
 */

namespace IPS\gallery\modules\front\gallery;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Gallery Submission
 */
class _submit extends \IPS\Dispatcher\Controller
{
	/**
	 * Manage addition of gallery images
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Init */
		$url = \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=submit', 'front', 'gallery_submit' );

		if( isset( \IPS\Request::i()->_pi ) )
		{
			$url = $url->setQueryString( '_pi', \IPS\Request::i()->_pi );
		}

		/* Init our form variables and check container */
		$container	= $this->chooseContainerForm( $url );
		$images		= NULL;
		$errors		= array();

		/* If we've submitted that and have our values we need, it's time to show the upload form */
		if( \is_array( $container ) )
		{
			$url = $url->setQueryString( 'category', $container['category']->_id );

			if ( $container['album'] )
			{
				$url = $url->setQueryString( 'album', $container['album']->_id );
			}
			else
			{
				$url = $url->setQueryString( 'noAlbum', 1 );
			}
			
			if ( isset( $container['guest_email'] ) )
			{
				$url = $url->setQueryString( 'guest_email', $container['guest_email'] );
			}

			$images = $this->chooseImagesForm( $url, $container );

			if( isset( $images['errors'] ) )
			{
				$errors = $images['errors'];
			}

			$images	= $images['html'];
		}

		/* Are we in da club? */
		$club = NULL;

		if ( \is_array( $container ) AND isset( $container['category'] ) AND $container['category'] )
		{
			try
			{
				if ( $club = $container['category']->club() )
				{
					\IPS\core\FrontNavigation::$clubTabActive = TRUE;
					\IPS\Output::i()->breadcrumb = array();
					\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs') );
					\IPS\Output::i()->breadcrumb[] = array( $club->url(), $club->name );
					\IPS\Output::i()->breadcrumb[] = array( $container['category']->url(), $container['category']->_title );
					
					if ( \IPS\Settings::i()->clubs_header == 'sidebar' )
					{
						\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->header( $club, $container['category'], 'sidebar' );
					}
				}
			}
			catch ( \OutOfRangeException $e ) {}
		}

		/* Set online user location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=submit', 'front', 'gallery_submit' ), array(), 'loc_gallery_adding_image' );

		/* Output */
		\IPS\gallery\Application::outputCss();
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'submit.css' ) );
		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'submit_responsive.css', 'gallery', 'front' ) );
		}

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-ui.js', 'core', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_submit.js', 'gallery' ) );	
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('add_gallery_image');
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( ( \IPS\Member::loggedIn()->group['g_movies'] ) ? 'add_gallery_image_movies' : 'add_gallery_image' ) );

		if( \IPS\Request::i()->isAjax() && isset( \IPS\Request::i()->noWrapper ) )
		{
			$tagsField		= NULL;
			$imageTagsField = $tagsField;

			/* Tags */
			if ( \is_array( $container ) AND isset( $container['category'] ) AND $container['category'] AND \IPS\gallery\Image::canTag( NULL, $container['category'] ) )
			{
				$tagsField		= \IPS\gallery\Image::tagsFormField( NULL, $container['category'] );
				$imageTagsField	= $tagsField;

				if( $tagsField )
				{
					$tagsField = $tagsField->html();

					$imageTagsField->name	= $imageTagsField->name . '_DEFAULT';
					$imageTagsField			= $imageTagsField->html();
				}

				\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $tagsField );
			}

			\IPS\Output::i()->json( array( 
				'container'		=> \is_string( $container ) ? $container : NULL, 
				'containerInfo'	=> \is_string( $container ) ? '' : \IPS\Theme::i()->getTemplate( 'submit' )->container( $container ), 
				'images'		=> $images,
				'imageTags'		=> preg_replace( '/data-ipsAutocomplete(?!\-)/', '', $imageTagsField ),
				'tagsField'		=> $tagsField,
				'imageErrors'	=> $errors,
			) );
		}
		else
		{
			/* We create a dummy generic form so that we can output its elements and then clone them using fancy javascript */
			$allImagesForm = new \IPS\Helpers\Form( 'all_images_form', 'submit' );
			$allImagesForm->add( new \IPS\Helpers\Form\TextArea( 'image_credit_info', NULL, FALSE ) );
			$allImagesForm->add( new \IPS\Helpers\Form\Text( 'image_copyright', NULL, FALSE, array( 'maxLength' => 255 ) ) );

			if( \IPS\Member::loggedIn()->member_id )
			{
				$allImagesForm->add( new \IPS\Helpers\Form\YesNo( 'image_auto_follow', (bool) \IPS\Member::loggedIn()->auto_follow['content'], FALSE, array(), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack( 'image_auto_follow_suffix' ) ) );
			}

			if( \IPS\Settings::i()->gallery_nsfw )
			{
				$allImagesForm->add( new \IPS\Helpers\Form\YesNo( 'image_nsfw', FALSE, FALSE, array(), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack( 'image_nsfw_suffix' ) ) );
			}

			/* Tags */
			if ( \is_array( $container ) AND isset( $container['category'] ) AND $container['category'] AND \IPS\gallery\Image::canTag( NULL, $container['category'] ) )
			{
				if( $tagsField = \IPS\gallery\Image::tagsFormField( NULL, $container['category'] ) )
				{
					$allImagesForm->add( $tagsField );
				}
			}

			$formElements = \IPS\gallery\Image::formElements( NULL, ( isset( $container['category'] ) ) ? $container['category'] : NULL );

			foreach( $formElements as $element )
			{
				if( $element->name == 'image_tags' )
				{
					if ( !\is_array( $container ) OR !isset( $container['category'] ) OR !$container['category'] OR !\IPS\gallery\Image::canTag( NULL, $container['category'] ) )
					{
						continue;
					}
				}

				$element->name	= $element->name . '_DEFAULT';
				$allImagesForm->add( $element );
			}

			$allImagesForm->add( new \IPS\Helpers\Form\TextArea( 'image_textarea_DEFAULT', NULL, FALSE ) );

			/* These fields are conditional and will not always show for each image */
			$allImagesForm->add( new \IPS\Helpers\Form\YesNo( "image_gps_show_DEFAULT", \IPS\Settings::i()->gallery_maps_default, FALSE ) );
			$allImagesForm->add( new \IPS\Helpers\Form\Upload( "image_thumbnail_DEFAULT", NULL, FALSE, array( 
				'storageExtension'	=> 'gallery_Images', 
				'image'				=> TRUE,
				'maxFileSize'		=> \IPS\Member::loggedIn()->group['g_max_upload'] ? ( \IPS\Member::loggedIn()->group['g_max_upload'] / 1024 ) : NULL,
				'canBeModerated'	=> TRUE
			) ) );

			/* And output */
			$category = ( \is_array( $container ) AND isset( $container['category'] ) AND $container['category'] ) ? $container['category'] : NULL;
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'submit' )->wrapper( $container, $images, $club, $allImagesForm );
		}
	}
	
	/**
	 * Step 1: Choose the container
	 *
	 * @param	\IPS\Http\Url	$url	The URL
	 * @return	string|array
	 */
	public function chooseContainerForm( $url )
	{
		/* Have we chosen a category? */
		$category = NULL;

		if ( isset( \IPS\Request::i()->category ) )
		{
			try
			{
				$category = \IPS\gallery\Category::loadAndCheckPerms( \IPS\Request::i()->category, 'add' );
			}
			catch ( \OutOfRangeException $e ) { }
		}

		/* What about an album? */
		$album = NULL;

		if ( isset( \IPS\Request::i()->album ) )
		{
			try
			{
				$album = \IPS\gallery\Album::loadAndCheckPerms( \IPS\Request::i()->album, 'add' );

				/* If it's an album, make sure we grab the current category */
				try
				{
					$category = \IPS\gallery\Category::loadAndCheckPerms( $album->category_id, 'add' );
				}
				catch ( \OutOfRangeException $e ) { }
			}
			catch ( \OutOfRangeException $e ) { }
		}
		
		/* If we have chosen an album we can return now */
		if( $category AND $album )
		{
			return array( 'category' => $category, 'album' => $album, 'guest_email' => isset( \IPS\Request::i()->guest_email ) ? \IPS\Request::i()->guest_email : NULL );
		}

		/* If we have chosen no album specifically, we can just return now */
		if ( isset( \IPS\Request::i()->noAlbum ) AND \IPS\Request::i()->noAlbum AND $category AND $category->allow_albums != 2 )
		{
			return array( 'category' => $category, 'album' => NULL, 'guest_email' => isset( \IPS\Request::i()->guest_email ) ? \IPS\Request::i()->guest_email : NULL );
		}

		/* If we haven't selected a category yet... */
		if ( !$category )
		{
			/* If there's only one category automatically select it, otherwise show the form */
			$category = \IPS\gallery\Category::theOnlyNode();

			if( !$category )
			{
				$chooseCategoryForm = new \IPS\Helpers\Form( 'choose_category', 'continue' );
				$chooseCategoryForm->add( new \IPS\Helpers\Form\Node( 'image_category', $category ?: NULL, TRUE, array(
					'url'					=> $url,
					'class'					=> 'IPS\gallery\Category',
					'permissionCheck'		=> function( $node ){
						if( $node->can('add') and ( $node->allow_albums != 2 or \IPS\Member::loggedIn()->group['g_create_albums'] or \IPS\gallery\Album::loadForSubmit( $node ) ) )
						{
							return TRUE;
						}
						return FALSE;
					},
					'clubs'					=> \IPS\Settings::i()->club_nodes_in_apps
				) ) );

				if ( $chooseCategoryFormValues = $chooseCategoryForm->values() )
				{
					$category = $chooseCategoryFormValues['image_category'];
				}
				else
				{
					return (string) $chooseCategoryForm->customTemplate( array( \IPS\Theme::i()->getTemplate('submit'), 'chooseCategory' ) );
				}
			}
		}
		
		/* Do we have any posting information to show? */
		$guestEmailError = NULL;
		if ( isset( \IPS\Request::i()->guest_email_submit ) )
		{
			if ( !\IPS\Request::i()->guest_email )
			{
				$guestEmailError = 'form_required';
			}
			else
			{
				try
				{
					\IPS\Helpers\Form\Email::validateEmail( \IPS\Request::i()->guest_email, TRUE );
					\IPS\Request::i()->_pi = TRUE;
				}
				catch ( \Exception $e )
				{
					$guestEmailError = $e->getMessage();
				}
			}
		}
		if ( !isset( \IPS\Request::i()->_pi ) )
		{			
			$guestPostBeforeRegister = ( !\IPS\Member::loggedIn()->member_id ) ? ( $category and !$category->can( 'add', \IPS\Member::loggedIn(), FALSE ) ) : NULL;
			$modQueued = \IPS\gallery\Image::moderateNewItems( \IPS\Member::loggedIn(), $category, $guestPostBeforeRegister );
			if ( $guestPostBeforeRegister or $modQueued )
			{
				return \IPS\Theme::i()->getTemplate('submit')->postingInformation( $guestPostBeforeRegister, $modQueued, $url->setQueryString( array( 'category' => $category->_id ) ), $guestEmailError );
			}
		}
					
		/* Can we create an album in this category? */
		$canCreateAlbum		= ( $category->allow_albums and \IPS\Member::loggedIn()->group['g_create_albums'] );
		$maximumAlbums		= \IPS\Member::loggedIn()->group['g_album_limit'];
		$currentAlbumCount	= \IPS\Db::i()->select( 'COUNT(*)', 'gallery_albums', array( 'album_owner_id=?', \IPS\Member::loggedIn()->member_id ) )->first();

		/* If we can, build a form */
		$createAlbumForm	= NULL;
		if ( $canCreateAlbum and ( !$maximumAlbums or $maximumAlbums > $currentAlbumCount ) )
		{
			/* Build the create form... */
			$createAlbumForm = new \IPS\Helpers\Form( 'new_album', 'create_new_album' );
			$createAlbumForm->class .= 'ipsForm_vertical';
			$createAlbumForm->hiddenValues['category'] = $category->_id;
			$createAlbumForm->hiddenValues['_pi'] = \IPS\Request::i()->_pi;

			$album	= new \IPS\gallery\Album;
			$album->form( $createAlbumForm );
			unset( $createAlbumForm->elements['']['album_category'] );
			
			/* And when we submit it, create an album... */
			if ( $createAlbumFormValues = $createAlbumForm->values() )
			{
				unset( $createAlbumFormValues['category'], $createAlbumFormValues['_pi'] );
				$createAlbumFormValues['album_category'] = $category;
				$album->saveForm( $album->formatFormValues( $createAlbumFormValues ) );
				
				\IPS\Api\Webhook::fire( 'galleryAlbum_create', $album, $album->webhookFilters() );

				\IPS\Member::loggedIn()->achievementAction( 'core', 'NewContentItem', $album->asItem() );

				return array( 'category' => $category, 'album' => $album, 'guest_email' => isset( \IPS\Request::i()->guest_email ) ? \IPS\Request::i()->guest_email : NULL );
			}
			
			/* Otherwise, display it*/
			$createAlbumForm = $createAlbumForm->customTemplate( array( \IPS\Theme::i()->getTemplate( 'submit', 'gallery' ), 'createAlbum' ) );
		}
		
		/* Can we choose an existing album? */
		$existingAlbumForm	= NULL;
		$albumsInCategory	= \IPS\Member::loggedIn()->member_id ? \IPS\gallery\Album::loadForSubmit( $category ) : array();

		if ( \count( $albumsInCategory ) )
		{
			/* Build the existing album form... */
			$existingAlbumForm = new \IPS\Helpers\Form( 'choose_album', 'choose_selected_album' );
			$existingAlbumForm->class .= 'ipsForm_vertical';
			$existingAlbumForm->hiddenValues['category'] = $category->_id;
			$existingAlbumForm->hiddenValues['_pi'] = \IPS\Request::i()->_pi;
			$albums = array();
			foreach( $albumsInCategory as $id => $album )
			{
				$albums[ $id ] = $album->_title;
			}
			$existingAlbumForm->add( new \IPS\Helpers\Form\Radio( 'existing_album', NULL, FALSE, array( 'options' => $albums, 'noDefault' => TRUE ), NULL, NULL, NULL, 'set_album_owner' ) );
			
			/* When we submit it, we can continue... */
			if ( $existingAlbumFormValues = $existingAlbumForm->values() )
			{
				return array( 'category' => $category, 'album' => \IPS\gallery\Album::loadAndCheckPerms( $existingAlbumFormValues['existing_album'], 'add' ), 'guest_email' => isset( \IPS\Request::i()->guest_email ) ? \IPS\Request::i()->guest_email : NULL );
			}
			
			/* Otherwise, display it */
			$existingAlbumForm = $existingAlbumForm->customTemplate( array( \IPS\Theme::i()->getTemplate( 'submit', 'gallery' ), 'existingAlbumForm' ), $category );
		}
		
		/* If there's nothing we can do, we can just continue */
		if ( !$canCreateAlbum and !$albumsInCategory )
		{
			if ( $category->allow_albums == 2 )
			{
				\IPS\Output::i()->error( 'node_error_no_perm', '2G376/2', 403, '' );
			}
			return array( 'category' => $category, 'album' => NULL, 'guest_email' => isset( \IPS\Request::i()->guest_email ) ? \IPS\Request::i()->guest_email : NULL );
		}
		/* Otherwise, ask the user what they want to do */
		else
		{
			return \IPS\Theme::i()->getTemplate('submit')->chooseAlbum( $category, $createAlbumForm, $canCreateAlbum, $maximumAlbums, $existingAlbumForm );
		}
	}
	
	/**
	 * Step 2: Upload images and configure details
	 *
	 * @param	string	$url	The URL
	 * @param	array	$data	The current data
	 * @return	string|array
	 */
	public function chooseImagesForm( $url, $data )
	{				
		$album		= $data['album'];
		$category	= $data['category'];

		/* How many images are allowed? */
		$maxNumberOfImages = NULL;
		if ( $album and \IPS\Member::loggedIn()->group['g_img_album_limit'] )
		{
			$maxNumberOfImages = \IPS\Member::loggedIn()->group['g_img_album_limit'] - ( $album->count_imgs + $album->count_imgs_hidden );
		}

		/* Limit uploads to what we know we can process on this server */
		if( $maxVars = @ini_get( 'max_input_vars') )
		{
			$maxNumberOfImages = round( ( $maxVars / 2 ) - 10, 0 );
		}

		/* Init form */
		$form = new \IPS\Helpers\Form( 'upload_images', 'continue', $url );
		$form->class = 'ipsForm_vertical';

		/* These form fields are not displayed to the user, however the fancy uploader process populates them via javascript */
		$form->add( new \IPS\Helpers\Form\TextArea( 'credit_all', NULL, FALSE ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'copyright_all', NULL, FALSE ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'tags_all', NULL, FALSE ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'prefix_all', NULL, FALSE ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'images_order', NULL, FALSE ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'images_info', NULL, FALSE ) );

		if( \IPS\Settings::i()->gallery_nsfw )
		{
			$form->add( new \IPS\Helpers\Form\Number( 'nsfw_all', NULL, FALSE ) );
		}

		if( \IPS\Member::loggedIn()->member_id )
		{
			$form->add( new \IPS\Helpers\Form\Number( 'images_autofollow_all', 1, FALSE ) );
		}

		/* Add upload field */
		$maxFileSizes = array();
		$options = array(
			'storageExtension'	=> 'gallery_Images',
			'image'				=> TRUE,
			'checkImage'		=> FALSE,
			'multiple'			=> TRUE,
			'minimize'			=> FALSE,
			'template'			=> "gallery.submit.imageItem",
			'canBeModerated'	=> TRUE
		);

		if( $maxNumberOfImages )
		{
			$options['maxFiles'] = $maxNumberOfImages;
		}

		$unlimitedMaxSize = TRUE;

		if ( \IPS\Member::loggedIn()->group['g_max_upload'] )
		{
			$maxFileSizes['image'] = \IPS\Member::loggedIn()->group['g_max_upload'] / 1024;
			$unlimitedMaxSize = FALSE;
		}
		if ( \IPS\Member::loggedIn()->group['g_movies'] )
		{
			$options['image'] = NULL;
			$options['allowedFileTypes'] = array_merge( \IPS\Image::supportedExtensions(), array( 'flv', 'f4v', 'wmv', 'mpg', 'mpeg', 'mp4', 'mkv', 'm4a', 'm4v', '3gp', 'mov', 'avi', 'webm', 'ogg', 'ogv' ) );
			if ( \IPS\Member::loggedIn()->group['g_movie_size'] )
			{
				$maxFileSizes['movie'] = \IPS\Member::loggedIn()->group['g_movie_size'] / 1024;
				$unlimitedMaxSize = FALSE;
			}
			else
			{
				$unlimitedMaxSize = TRUE;
			}
		}
		if ( \count( $maxFileSizes ) AND !$unlimitedMaxSize )
		{
			$options['maxFileSize'] = max( $maxFileSizes );
		}

		$uploader = new \IPS\Helpers\Form\Upload( 'images', array(), TRUE, $options, function( $val ) use ( $maxNumberOfImages, $maxFileSizes ) {
			if ( $maxNumberOfImages !== NULL and \count( $val ) > $maxNumberOfImages )
			{
				if ( $maxNumberOfImages < 1 )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'gallery_images_no_more' ) );
				}
				else
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'gallery_images_too_many', FALSE, array( 'pluralize' => array( $maxNumberOfImages ) ) ) );
				}
			}

			foreach ( $val as $file )
			{
				$ext = mb_substr( $file->filename, ( mb_strrpos( $file->filename, '.' ) + 1 ) );
				if ( \in_array( mb_strtolower( $ext ), \IPS\Image::supportedExtensions() ) )
				{
					/* The size was saved as kb, then divided by 1024 above to figure out how many MB to allow. So now we have '2' for 2MB for instance, so we need
						to multiply that by 1024*1024 in order to get the byte size again */
					if ( isset( $maxFileSizes['image'] ) and $file->filesize() > ( $maxFileSizes['image'] * 1048576 ) )
					{
						throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'upload_image_too_big', FALSE, array( 'sprintf' => array( \IPS\Output\Plugin\Filesize::humanReadableFilesize( $maxFileSizes['image'] * 1048576 ) ) ) ) );
					}
				}
				elseif ( isset( $maxFileSizes['movie'] ) and $file->filesize() > ( $maxFileSizes['movie'] * 1048576 ) )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'upload_movie_too_big', FALSE, array( 'sprintf' => array( \IPS\Output\Plugin\Filesize::humanReadableFilesize( $maxFileSizes['movie'] * 1048576 ) ) ) ) );
				}
			}
		} );

		$uploader->template = array( \IPS\Theme::i()->getTemplate( 'forms', 'gallery', 'front' ), 'imageUpload' );
		$form->add( $uploader );

		/* Add tag fields so we can validate it */
		if( isset( \IPS\Request::i()->images_info ) AND \IPS\gallery\Image::canTag( NULL, $category ) )
		{
			$imagesData		= json_decode( \IPS\Request::i()->images_info, true );

			foreach( \IPS\Request::i()->images_existing as $imageId )
			{
				if( $tagsField = \IPS\gallery\Image::tagsFormField( NULL, $category ) )
				{
					$tagsFieldName		= $tagsField->name . '_' . $imageId;
					$tagsField->name	= $tagsFieldName;
					$tagsPrefix			= NULL;
					$tagsValue			= NULL;

					foreach( $imagesData as $_imageData )
					{
						if( $_imageData['name'] == 'image_tags_' . $imageId )
						{
							$tagsValue = $_imageData['value'];
						}

						if( $_imageData['name'] == 'image_tags_' . $imageId . '_prefix' )
						{
							$tagsPrefix = $_imageData['value'];
						}
					}

					if( !$tagsValue )
					{
						$tagsValue	= \IPS\Request::i()->tags_all;
						$tagsPrefix	= \IPS\Request::i()->prefix_all;
					}

					$checkboxInput	= $tagsFieldName . '_freechoice_prefix';
					$prefixinput	= $tagsFieldName . '_prefix';

					\IPS\Request::i()->$tagsFieldName	= ( \is_array( $tagsValue ) ) ? implode( "\n", $tagsValue ) : $tagsValue;
					\IPS\Request::i()->$checkboxInput	= 1;
					\IPS\Request::i()->$prefixinput		= $tagsPrefix;

					$form->add( $tagsField );
				}
			}
		}

		$imagesWithIssues = array();

		/* Process submission */
		if ( $values = $form->values() )
		{
			return array( 'html' => $this->processUploads( $values, $url, $data ) );
		}
		elseif( isset( \IPS\Output::i()->httpHeaders['X-IPS-FormError'] ) AND \IPS\Output::i()->httpHeaders['X-IPS-FormError'] == 'true' )
		{
			foreach ( $form->elements as $elements )
			{
				foreach ( $elements as $_name => $element )
				{
					if ( !$element->valueSet )
					{
						$element->setValue( FALSE, TRUE );
					}

					if( !empty( $element->error ) )
					{
						if( $element->name == 'images' )
						{
							$fieldName	= 'images';
							$fieldId	= 0;
						}
						else
						{
							$delim		= mb_strrpos( $element->name, '_' );
							$fieldName	= mb_substr( $element->name, 0, $delim );
							$fieldId	= mb_substr( $element->name, $delim + 1 );
						}

						$fieldError	= $element->error;

						if( isset( $imagesWithIssues[ $fieldId ] ) )
						{
							$imagesWithIssues[ $fieldId ][ $fieldName ] = \IPS\Member::loggedIn()->language()->addToStack( $fieldError );
						}
						else
						{
							$imagesWithIssues[ $fieldId ] = array( $fieldName => \IPS\Member::loggedIn()->language()->addToStack( $fieldError ) );
						}
					}
				}
			}
		}
		
		/* Display */
		return array( 'html' => \IPS\Theme::i()->getTemplate( 'submit' )->uploadImages( $form, $category ), 'errors' => $imagesWithIssues );
	}
	
	/**
	 * Process the uploaded files
	 *
	 * @param	array 	$values		Values from the form submission
	 * @param	string	$url	The URL
	 * @param	array	$data	The current data
	 * @return	string
	 * @note	This returns a multiredirector instance which processes all of the images
	 */
	public function processUploads( $values, $url, $data )
	{
		/* Get any records we had before in case we need to delete them */
		$existing = iterator_to_array( \IPS\Db::i()->select( '*', 'gallery_images_uploads', array( 'upload_session=?', session_id() ) )->setKeyField( 'upload_location' ) );

		/* Get our image order first, as that's the order we want to loop through in */
		$imageOrder = json_decode( $values['images_order'], true );

		/* Get the image info (caption, etc.) - note this data has NOT been sanitized at this point */
		$imagesData = json_decode( $values['images_info'], true );

		/* Loop through the values we have */
		$images		= array();
		$inserts	= array();
		$i			= 0;

		foreach ( $values['images'] as $image )
		{
			$i++;

			$imageData = array();

			if( \is_array( $imagesData ) )
			{
				foreach( $imagesData as $dataEntry )
				{
					if( mb_strpos( $dataEntry['name'], '_' . $image->tempId ) !== FALSE )
					{
						$imageData[ str_replace( '_' . $image->tempId, '', str_replace( 'filedata__', '', $dataEntry['name'] ) ) ] = $dataEntry['value'];
					}
				}
			}

			/* Set the global values if they're not overridden */
			if( !isset( $imageData['image_copyright'] ) OR !$imageData['image_copyright'] )
			{
				$imageData['image_copyright'] = $values['copyright_all'];
			}

			if( !isset( $imageData['image_credit_info'] ) OR !$imageData['image_credit_info'] )
			{
				$imageData['image_credit_info'] = $values['credit_all'];
			}

			if( !isset( $imageData['image_tags'] ) OR !$imageData['image_tags'] )
			{
				$imageData['image_tags']		= $values['tags_all'];
				$imageData['image_tags_prefix']	= $values['prefix_all'];
			}

			if( \IPS\Member::loggedIn()->member_id )
			{
				$imageData['image_auto_follow'] = $values['images_autofollow_all'];
			}

			if( \IPS\Settings::i()->gallery_nsfw and !isset( $imageData['image_nsfw'] ) )
			{
				$imageData['image_nsfw'] = $values['nsfw_all'];
			}

			/* Fix descriptions */
			$imageData['image_description'] = ( isset( $imageData['image_textarea'] ) AND $imageData['image_textarea'] ) ? $imageData['image_textarea'] : ( ( isset( $imageData['image_description'] ) ) ? $imageData['image_description'] : '' );
			
			/* Will we need to moderate this? */
			$imageData['requires_moderation'] = $image->requiresModeration;

			/* Image scanner labels */
			$imageData['labels'] = $image->labels;

			if ( !isset( $existing[ (string) $image ] ) )
			{
				$inserts[] = array(
					'upload_session'	=> session_id(),
					'upload_member_id'	=> (int) \IPS\Member::loggedIn()->member_id,
					'upload_location'	=> (string) $image,
					'upload_file_name'	=> $image->originalFilename,
					'upload_date'		=> time(),
					'upload_order'		=> ( \is_array( $imageOrder ) ) ? array_search( $image->tempId, $imageOrder ) : $i,
					'upload_data'		=> json_encode( $imageData ),
					'upload_exif'		=> $image->exifData ? json_encode( $image->exifData ) : NULL
				);
			}

			unset( $existing[ (string) $image ], $image );
		}

		/* Insert them into the database */
		if( \count( $inserts ) )
		{
			\IPS\Db::i()->insert( 'gallery_images_uploads', $inserts );
		}

		/* Delete any that we don't have any more */
		foreach ( $existing as $location => $file )
		{
			try
			{
				\IPS\File::get( 'gallery_Images', $location )->delete();
			}
			catch ( \Exception $e ) { }
			
			\IPS\Db::i()->delete( 'gallery_images_uploads', array( 'upload_session=? and upload_location=?', $file['upload_session'], $file['upload_location'] ) );
		}

		/* Get the total number of images now as it will decrease each cycle moving forward */
		$totalImages = \IPS\Db::i()->select( 'count(*)', 'gallery_images_uploads', array( 'upload_session=?', session_id() ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();

		$url = $url->setQueryString( 'totalImages', $totalImages );
		if ( isset( $data['guest_email'] ) )
		{
			$url = $url->setQueryString( 'guest_email', $data['guest_email'] );
		}

		/* Now return the multiredirector */
		return $this->saveImages( $url );
	}

	/**
	 * Wizard step: Process the saved data to create an album and save images
	 *
	 * @param	string|NULL	$url	The URL
	 * @return	string
	 */
	public function saveImages( $url=NULL )
	{
		/* Process */
		$url = $url ? $url->setQueryString( 'do', 'saveImages' ) : \IPS\Request::i()->url()->stripQueryString( array( 'mr' ) );
		
		/* Return the multiredirector */
		$multiRedirect = (string) new \IPS\Helpers\MultipleRedirect( $url,
			/* Function to process each image */
			function( $offset ) use ( $url )
			{
				$offset = \intval( $offset );
				
				$existing = \IPS\Db::i()->select( '*', 'gallery_images_uploads', array( 'upload_session=?', session_id() ), 'upload_order ASC', array( 0, 1 ), NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->setKeyField( 'upload_location' );

				/* Get category and album data */
				$data = $this->chooseContainerForm( $url );

				foreach( $existing as $location => $file )
				{

					/* Start with the basic data */
					$values = array(
						'category'		=> $data['category']->_id,
						'imageLocation'	=> $location,
						'album'			=> $data['album'] ? $data['album']->_id : NULL,
						'guest_email'	=> isset( $data['guest_email'] ) ? $data['guest_email'] : NULL
					);
					
					/* Get the data from the row and set */
					$fileData = json_decode( $file['upload_data'], TRUE );
					if ( isset( $fileData['requires_moderation'] ) )
					{
						$values['imageRequiresModeration'] = $fileData['requires_moderation'];
						unset( $fileData['requires_moderation'] );
					}

					if( \count( $fileData ) )
					{
						foreach( $fileData as $k => $v )
						{
							$values[ preg_replace("/^filedata_[0-9]*_/i", '', $k ) ]	= $v;
						}	
					}

					if( isset( $values['image_tags'] ) AND $values['image_tags'] AND !\is_array( $values['image_tags'] ) )
					{
						$values['image_tags']	= explode( "\n", $values['image_tags'] );
					}
					
					/* If no title was saved, use the original file name */
					if( !isset( $values['image_title'] ) )
					{
						$values['image_title'] = $file['upload_file_name'];
					}

					/* Fix thumbnail reference if this is a video */
					if( isset( $values['image_thumbnail'] ) )
					{
						$thumbnailReset = FALSE;

						foreach( $values as $key => $value )
						{
							if( mb_strpos( $key, 'image_thumbnail_existing' ) === 0 )
							{
								try
								{
									$thumb = \IPS\Db::i()->select( '*', 'core_files_temp', array( 'id=?', $value ) )->first();

									$values['image_thumbnail'] = $thumb['contents'];
									$values['image_thumbnail_requires_moderation'] = $thumb['requires_moderation'];
									$thumbnailReset = TRUE;
									\IPS\Db::i()->delete( 'core_files_temp', array( 'id=?', $value ) );
									break;
								}
								catch( \UnderflowException $e ){}
							}
						}

						if( !$thumbnailReset )
						{
							unset( $values['image_thumbnail'] );
						}
					}

					/* If GPS is supported but the admin did not specify whether to show the map or not, then default to showing it */
					$image = \IPS\File::get( 'gallery_Images', $location );

					$exif = $file['upload_exif'] ? json_decode( $file['upload_exif'], true ) : NULL;
					$values['_exif'] = $exif;

					if( \IPS\GeoLocation::enabled() and $exif )
					{
						if( isset( $exif['GPS.GPSLatitudeRef'] ) && isset( $exif['GPS.GPSLatitude'] ) && isset( $exif['GPS.GPSLongitudeRef'] ) && isset( $exif['GPS.GPSLongitude'] ) )
						{
							$values['image_gps_show'] = ( isset( $values['image_gps_show'] ) ) ? (int) ( isset( $values['image_gps_show_checkbox'] ) ) : \IPS\Settings::i()->gallery_maps_default;
						}
					}

					/* We will create a dummy form to sanitize the elements */
					$formElements	= \IPS\gallery\Image::formElements();
					$testValuesForm	= new \IPS\Helpers\Form;

					foreach( $formElements as $key => $element )
					{
						/* If this is a guest posting before registration, we can't check the CAPTCHA as none is added to the form */
						if( $key == 'captcha' )
						{
							continue;
						}

						$testValuesForm->add( $element );

						$name = 'image_' . $key;

						if( isset( $values[ $name ] ) )
						{
							if( $name == 'image_description' )
							{
								\IPS\Request::i()->filedata__image_description = $values[ $name ];
							}
							else
							{
								\IPS\Request::i()->$name	= ( \is_array( $values[ $name ] ) ) ? implode( "\n", $values[ $name ] ) : $values[ $name ];
							}

							if( $name == 'image_tags' )
							{
								$checkboxInput	= $name . '_freechoice_prefix';
								$prefixinput	= $name . '_prefix';

								\IPS\Request::i()->$checkboxInput	= 1;
								\IPS\Request::i()->$prefixinput		= ( isset( $values[ $name . '_prefix' ] ) ) ? $values[ $name . '_prefix' ] : '';

								unset( $values[ $name . '_prefix' ] );
							}
							elseif( $name == 'image_auto_follow' AND \IPS\Member::loggedIn()->member_id )
							{
								$checkboxInput	= $name . '_checkbox';

								\IPS\Request::i()->$checkboxInput	= $values[ $name ];
							}
							elseif( $name == 'image_nsfw' )
							{
								$checkboxInput	= $name . '_checkbox';

								\IPS\Request::i()->$checkboxInput	= $values[$checkboxInput] ?? $values[$name];
								\IPS\Request::i()->$name			= $values[$checkboxInput] ?? $values[$name];
							}

							unset( $values[ $name ] );
						}
					}

					$submitted = "{$testValuesForm->id}_submitted";

					\IPS\Request::i()->$submitted	= true;
					\IPS\Request::i()->csrfKey		= \IPS\Session::i()->csrfKey;

					if( $cleaned = $testValuesForm->values() )
					{
						foreach( $cleaned as $k => $v )
						{
							$values[ str_replace( 'filedata__', '', $k ) ] = $v;
						}
					}

					/* And now create the images */
					$image	= \IPS\gallery\Image::createFromForm( $values, $data['category'], FALSE );
					$image->markRead();
					
					/* Delete that file */
					\IPS\Db::i()->delete( 'gallery_images_uploads', array( 'upload_unique_id=?', $file['upload_unique_id'] ) );

					/* Go to next */
					return array( ++$offset, \IPS\Member::loggedIn()->language()->addToStack('processing'), number_format( 100 / ( \IPS\Request::i()->totalImages ?: $offset ) * $offset, 2 ) );
				}

				/* Update last image info */
				$data['category']->setLastImage();
				$data['category']->save();

				/* And Album */
				if( $data['album'] )
				{
					$data['album']->setLastImage();
					$data['album']->save();
				}
				
				return NULL;
			},
			
			/* Function to call when done */
			function() use( $url )
			{
				if ( isset( \IPS\Request::i()->guest_email ) )
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=register', 'front', 'register' ) );
				}
				elseif ( \IPS\Request::i()->totalImages === 1 )
				{
					/* If we are only sending one image, send a normal notification and award points */
					$image = \IPS\gallery\Image::constructFromData( \IPS\Db::i()->select( '*', 'gallery_images', NULL, 'image_id DESC', 1 )->first() );
					if ( !$image->hidden() )
					{
						$image->sendNotifications();
					}
					else if( !\in_array( $image->hidden(), array( -1, -3 ) ) )
					{
						$image->sendUnapprovedNotification();
					}
										
					/* Then redirect */
					\IPS\Output::i()->redirect( $image->url() );
				}
				else
				{
					/* Get category and album data */
					$data = $this->chooseContainerForm( $url );

					if ( \IPS\Member::loggedIn()->moderateNewContent() OR \IPS\gallery\Image::moderateNewItems( \IPS\Member::loggedIn(), $data['category'] ) )
					{
						\IPS\gallery\Image::_sendUnapprovedNotifications( $data['category'], $data['album'] );
					}
					else
					{
						\IPS\gallery\Image::_sendNotifications( $data['category'], $data['album'] );
					}
					
					\IPS\Output::i()->redirect( $data['album'] ? $data['album']->url() : $data['category']->url() );
				}
			}
		);
		
		/* Display redirect */
		return \IPS\Theme::i()->getTemplate( 'submit' )->processing( $multiRedirect );	
	}

	/**
	 * Determine whether the uploaded image has GPS information embedded
	 *
	 * @return void
	 */
	protected function checkGps()
	{
		/* If the service is not enabled just return now */
		if( !\IPS\GeoLocation::enabled() )
		{
			\IPS\Output::i()->json( array( 'hasGeo' => 0 ) );
		}

		try
		{
			$temporaryImage = \IPS\Db::i()->select( '*', 'core_files_temp', array( 'storage_extension=? AND id=?', 'gallery_Images', \IPS\Request::i()->imageId ) )->first();
		}
		catch( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2G376/1', 404, '' );
		}

		if( \IPS\Image::exifSupported() and mb_strpos( $temporaryImage['mime'], 'image' ) === 0 )
		{
			$exif	= \IPS\Image::create( \IPS\File::get( $temporaryImage['storage_extension'], $temporaryImage['contents'] )->contents() )->parseExif();

			if( \count( $exif ) )
			{
				if( isset( $exif['GPS.GPSLatitudeRef'] ) && isset( $exif['GPS.GPSLatitude'] ) && isset( $exif['GPS.GPSLongitudeRef'] ) && isset( $exif['GPS.GPSLongitude'] ) )
				{
					\IPS\Output::i()->json( array( 'hasGeo' => 1 ) );
				}
			}
		}

		\IPS\Output::i()->json( array( 'hasGeo' => 0 ) );
	}
}