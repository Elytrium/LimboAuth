<?php
/**
 * @brief		Custom table helper for gallery images to override move menu
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Apr 2014
 */

namespace IPS\gallery\Image;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Custom table helper for gallery images to override move menu
 */
class _Table extends \IPS\Helpers\Table\Content
{
	/**
	 * Constructor
	 *
	 * @param	array					$class				Database table
	 * @param	\IPS\Http\Url			$baseUrl			Base URL
	 * @param	array|null				$where				WHERE clause (To restrict to a node, use $container instead)
	 * @param	\IPS\Node\Model|NULL	$container			The container
	 * @param	bool|null				$includeHidden		Flag to pass to getItemsWithPermission() method for $includeHiddenContent, defaults to NULL
	 * @param	string|NULL				$permCheck			Permission key to check
	 * @param	bool					$honorPinned		Honor pinned status (show pinned items first)
	 * @return	void
	 */
	public function __construct( $class, \IPS\Http\Url $baseUrl, $where=NULL, \IPS\Node\Model $container=NULL, $includeHidden=\IPS\Content\Hideable::FILTER_AUTOMATIC, $permCheck='view', $honorPinned=TRUE )
	{
		/* Are we changing the thumbnail viewing size? */
		if( isset( \IPS\Request::i()->thumbnailSize ) )
		{
			\IPS\Session::i()->csrfCheck();

			\IPS\Request::i()->setCookie( 'thumbnailSize', \IPS\Request::i()->thumbnailSize, \IPS\DateTime::ts( time() )->add( new \DateInterval( 'P1Y' ) ) );

			/* Do a 303 redirect to prevent indexing of the CSRF link */
			\IPS\Output::i()->redirect( \IPS\Request::i()->url(), '', 303 );
		}

		return parent::__construct( $class, $baseUrl, $where, $container, $includeHidden, $permCheck, $honorPinned );
	}

	/**
	 * Get rows
	 *
	 * @param	array	$advancedSearchValues	Values from the advanced search form
	 * @return	array
	 */
	public function getRows( $advancedSearchValues )
	{
		$this->sortOptions['title'] = $this->sortOptions['title'] . ' ASC, image_id ';

		return parent::getRows( $advancedSearchValues );
	}

	/**
	 * Build the form to move images
	 *
	 * @param	\IPS\Node\Model		$currentContainer	Current image container
	 * @param	string				$class 				Class to use
	 * @param	array 				$extraAlbumOptions	Additional options to use for the album helper (e.g. to force owner)
	 * @param	\IPS\Member|NULL		$member				Member to check, or NULL for currently logged in member.
	 * @return	\IPS\Helpers\Form
	 */
	static public function buildMoveForm( $currentContainer, $class, $extraAlbumOptions=array(), $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		$form = new \IPS\Helpers\Form( 'form', 'move' );
		$form->class = 'ipsForm_vertical';
		if ( \IPS\gallery\Category::canOnAny('add') and \IPS\gallery\Album::canOnAny('add') )
		{
			$options = array( 'category' => 'image_category', 'album' => 'image_album' );
			$toggles = array( 'category' => array( 'move_to_category' ), 'album' => array( 'move_to_album' ) );
			$extraFields = array();
			
			if ( \IPS\gallery\Image::modPermission( 'edit', NULL, NULL ) and \IPS\Db::i()->select( 'COUNT(*)', 'gallery_categories', array( array( 'category_allow_albums>0' ), array( '(' . \IPS\Db::i()->findInSet( 'core_permission_index.perm_' . \IPS\gallery\Category::$permissionMap['add'], \IPS\Member::loggedIn()->groups ) . ' OR ' . 'core_permission_index.perm_' . \IPS\gallery\Category::$permissionMap['add'] . '=? )', '*' ) ) )->join( 'core_permission_index', array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=" . \IPS\gallery\Category::$databaseTable . "." . \IPS\gallery\Category::$databasePrefix . \IPS\gallery\Category::$databaseColumnId, \IPS\gallery\Category::$permApp, \IPS\gallery\Category::$permType ) )->first() )
			{
				$options['new_album'] = 'move_to_new_album';

				foreach ( \IPS\gallery\Album::formFields( NULL, TRUE, \IPS\Request::i()->move_to === 'new_album' ) as $field )
				{
					if ( !$field->htmlId )
					{
						$field->htmlId = $field->name . '_id';
					}
					$toggles['new_album'][] = $field->htmlId;
					
					$extraFields[] = $field;
				}
			}
			
			$form->add( new \IPS\Helpers\Form\Radio( 'move_to', NULL, TRUE, array( 'options' => $options, 'toggles' => $toggles ) ) );
			foreach ( $extraFields as $field )
			{
				$form->add( $field );
			}
		}

		$form->add( new \IPS\Helpers\Form\Node( 'move_to_category', NULL, NULL, array( 
			'clubs'				=> true, 
			'class'				=> 'IPS\\gallery\\Category', 
			'permissionCheck'	=> function( $node ) use ( $currentContainer, $class, $member )
			{
				/* If the image is in the same category already, we can't move it there */
				if( $currentContainer instanceof \IPS\gallery\Category and $currentContainer->id == $node->id )
				{
					return false;
				}

				/* If the category requires albums, we cannot move images directly to it */
				if( $node->allow_albums == 2 )
				{
					return false;
				}

				/* If the category is a club, check mod permissions appropriately */
				try
				{
					/* If the item is in a club, only allow moving to other clubs that you moderate */
					if ( $currentContainer and \IPS\IPS::classUsesTrait( $currentContainer, 'IPS\Content\ClubContainer' ) and $currentContainer->club()  )
					{
						return $class::modPermission( 'move', \IPS\Member::loggedIn(), $node ) and $node->can( 'add' ) ;
					}
				}
				catch( \OutOfBoundsException $e ) { }

				/* Can we add in this category? */
				if ( $node->can( 'add', $member ) )
				{
					return true;
				}
				
				return false;
			}
		), function( $val ) {
			if ( !$val and isset( \IPS\Request::i()->move_to ) and \IPS\Request::i()->move_to == 'category' )
			{
				throw new \DomainException('form_required');
			}
		}, NULL, NULL, 'move_to_category' ) );

		$form->add( new \IPS\Helpers\Form\Node( 'move_to_album', NULL, NULL, array_merge( array( 
			'class' 				=> 'IPS\\gallery\\Album', 
			'permissionCheck' 		=> function( $node ) use ( $currentContainer, $member )
			{
				/* If the image is in the same album already, we can't move it there */
				if( $currentContainer instanceof \IPS\gallery\Album and $currentContainer->id == $node->id )
				{
					return false;
				}

				/* Do we have permission to add? */
				if( !$node->can( 'add', $member ) )
				{
					return false;
				}

				/* Have we hit an images per album limit? */
				if( $node->owner()->group['g_img_album_limit'] AND ( $node->count_imgs + $node->count_imgs_hidden ) >= $node->owner()->group['g_img_album_limit'] )
				{
					return false;
				}
				
				return true;
			}
		), $extraAlbumOptions ), function( $val ) {
			if ( !$val and isset( \IPS\Request::i()->move_to ) and \IPS\Request::i()->move_to == 'album' )
			{
				throw new \DomainException('form_required');
			}
		}, NULL, NULL, 'move_to_album' ) );

		return $form;
	}

	/**
	 * Get the form to move items
	 *
	 * @return string|array
	 */
	protected function getMoveForm()
	{
		$class = $this->class;
		$params = array();

		$currentContainer = $this->container;

		$form = static::buildMoveForm( $currentContainer, $class );
		
		if ( $values = $form->values() )
		{
			if ( isset( $values['move_to'] ) )
			{
				if ( $values['move_to'] == 'new_album' )
				{
					$albumValues = $values;
					unset( $albumValues['move_to'] );
					unset( $albumValues['move_to_category'] );
					unset( $albumValues['move_to_album'] );
					
					$target = new \IPS\gallery\Album;
					$target->saveForm( $target->formatFormValues( $albumValues ) );
					$target->save();
				}
				else
				{						
					$target = ( \IPS\Request::i()->move_to == 'category' ) ? $values['move_to_category'] : $values['move_to_album'];
				}
			}
			else
			{
				$target = isset( $values['move_to_category'] ) ? $values['move_to_category'] : $values['move_to_album'];
			}

			$params[] = $target;
			$params[] = FALSE;

			return $params;
		}

		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( \IPS\Output::i()->output  );
		}
		else
		{
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Output::i()->title, \IPS\Output::i()->output, array( 'app' => \IPS\Dispatcher::i()->application->directory, 'module' => \IPS\Dispatcher::i()->module->key, 'controller' => \IPS\Dispatcher::i()->controller ) ), 200, 'text/html' );
		}
		return;
	}
}