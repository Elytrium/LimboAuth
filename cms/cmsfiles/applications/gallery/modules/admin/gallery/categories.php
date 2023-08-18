<?php
/**
 * @brief		Categories
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Mar 2014
 */

namespace IPS\gallery\modules\admin\gallery;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Categories
 */
class _categories extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\gallery\Category';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'categories_manage' );
		parent::execute();
	}


	protected function form()
	{
		parent::form();

		if ( \IPS\Request::i()->id )
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('edit_category') . ': ' . \IPS\Output::i()->title;
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('add_category');
		}
	}

	/**
	 * Build the form to mass move content
	 *
	 * @param	\IPS\Helpers\Form	$form	The form helper object
	 * @param	mixed			$data		Data from the wizard helper
	 * @param	string			$nodeClass	Node class
	 * @param	\IPS\Node\Model	$node		Node we are working with
	 * @param	string			$contentItemClass	Content item class (if there is one)
	 * @return \IPS\Helpers\Form
	 */
	protected function _buildMassMoveForm( $form, $data, $nodeClass, $node, $contentItemClass )
	{
		/* Add an option to move or delete albums - we won't worry about filters */
		$form->addHeader('node_mass_move_delete_albums');
		$form->add( new \IPS\Helpers\Form\Node( 'node_move_subalbums', isset( $data['moveToAlbums'] ) ? $data['moveToClassAlbums']::load( $data['moveToAlbums'] ) : 0, TRUE, array( 'class' => $nodeClass, 'disabled' => array( $node->_id ), 'disabledLang' => 'node_move_delete', 'clubs' => TRUE, 'zeroVal' => 'node_delete_content', 'subnodes' => FALSE, 'permissionCheck' => function( $node )
		{
			return $node->can('add');
		} ) ) );

		return parent::_buildMassMoveForm( $form, $data, $nodeClass, $node, $contentItemClass );
	}

	/**
	 * Process the mass move form submission
	 *
	 * @param	array			$values		Values from form submission
	 * @param	mixed			$data		Data from the wizard helper
	 * @param	string			$nodeClass	Node class
	 * @param	\IPS\Node\Model	$node		Node we are working with
	 * @param	string			$contentItemClass	Content item class (if there is one)
	 * @return	array	Wizard helper data
	 */
	protected function _processMassMoveForm( $values, $data, $nodeClass, $node, $contentItemClass )
	{
		$data = parent::_processMassMoveForm( $values, $data, $nodeClass, $node, $contentItemClass );

		/* What are we doing with the albums? */
		if ( \is_object( $values['node_move_subalbums'] ) )
		{
			$data['moveToClassAlbums'] = \get_class( $values['node_move_subalbums'] );
			$data['moveToAlbums'] = $values['node_move_subalbums']->_id;
		}
		else
		{
			unset( $data['moveToClassAlbums'] );
			unset( $data['moveToAlbums'] );
		}

		return $data;
	}

	/**
	 * Actually perform the mass move operation (called from the wizard helper)
	 *
	 * @param	mixed			$data		Data from the wizard helper
	 * @param	\IPS\Node\Model	$node		Node we are working with
	 * @param	string			$contentItemClass	Content item class (if there is one)
	 * @return mixed
	 */
	protected function _performMassMove( $data, $node, $contentItemClass )
	{
		/* If we have confirmed the move, just handle albums now and then pass to the parent */
		if ( isset( \IPS\Request::i()->confirm ) )
		{
			\IPS\Session::i()->csrfCheck();
			
			/* Loop over our albums */
			foreach( \IPS\gallery\Album\Item::getItemsWithPermission( array( array( 'album_category_id=?', $node->_id ) ), NULL, 1000, NULL, TRUE, 0, NULL, FALSE, FALSE, FALSE, FALSE, NULL, TRUE, FALSE, FALSE, FALSE ) as $album )
			{
				$album = $album->asNode();

				/* Are we moving albums? */
				if ( isset( $data['moveToAlbums'] ) )
				{
					$album->moveTo( $data['moveToClassAlbums']::load( $data['moveToAlbums'] ) );
				}
				else
				{
					/* Store the task for deleting */
					\IPS\Task::queue( 'core', 'DeleteOrMoveContent', array( 'class' => 'IPS\gallery\Category', 'id' => $album->_id, 'deleteWhenDone' => FALSE ) );
				}
			}

			if ( isset( $data['moveToAlbums'] ) )
			{
				$newNode = $data['moveToClassAlbums']::load( $data['moveToAlbums'] );
				\IPS\Session::i()->log( 'acplog__catalbum_mass_move', array( $this->title => TRUE, $node->titleForLog() => FALSE, $newNode->titleForLog() => FALSE ) );
			}
			else
			{
				\IPS\Session::i()->log( 'acplog__catalbum_mass_delete', array( $this->title => TRUE, $node->titleForLog() => FALSE ) );
			}

			/* Then let the parent take over */
			return parent::_performMassMove( $data, $node, $contentItemClass );
		}
		/* Otherwise show a slightly more useful confirmation message */
		else
		{
			$albumCount			= \IPS\gallery\Album\Item::getItemsWithPermission( array( array( 'album_category_id=?', $node->_id ) ), NULL, 1000, NULL, TRUE, 0, NULL, FALSE, FALSE, FALSE, TRUE, NULL, TRUE, FALSE, FALSE, FALSE );

			$itemDeletionWhere		= $node->massMoveorDeleteWhere( $data );
			$itemDeletionWhere[]	= array( 'image_album_id=?', 0 );

			return \IPS\Theme::i()->getTemplate( 'global', 'gallery' )->nodeMoveDeleteContent( $this->url->setQueryString( array( 'do' => 'massManageContent', 'id' => $node->_id ) )->csrf(), \IPS\Member::loggedIn()->language()->addToStack( $contentItemClass::$title . '_pl_lc' ), $node->getContentItems( NULL, NULL, $itemDeletionWhere, TRUE ), isset( $data['moveTo'] ) ? $data['moveToClass']::load( $data['moveTo'] ) : NULL, $albumCount, isset( $data['moveToAlbums'] ) ? $data['moveToClassAlbums']::load( $data['moveToAlbums'] ) : NULL );
		}
	}
}