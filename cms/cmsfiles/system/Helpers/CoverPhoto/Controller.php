<?php
/**
 * @brief		Cover Photo Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 May 2014
 */

namespace IPS\Helpers\CoverPhoto;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Cover Photo Controller
 */
abstract class _Controller extends \IPS\Dispatcher\Controller
{		
	/**
	 * Upload Cover Photo
	 *
	 * @return	void
	 */
	protected function coverPhotoUpload()
	{	
		$photo = $this->_coverPhotoGet();
		if ( !$photo->editable )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S216/1', 403, '' );
		}

		$form = new \IPS\Helpers\Form( 'coverPhoto' );
		$form->class = 'ipsForm_vertical ipsForm_noLabels';
		$form->add( new \IPS\Helpers\Form\Upload( 'cover_photo', NULL, TRUE, array( 'image' => [ 'maxWidth' => NULL, 'maxHeight' => NULL ], 'allowStockPhotos' => TRUE, 'minimize' => FALSE, 'maxFileSize' => ( $photo->maxSize and $photo->maxSize != -1 ) ? $photo->maxSize / 1024 : NULL, 'storageExtension' => $this->_coverPhotoStorageExtension() ) ) );
		if ( $values = $form->values() )
		{
			try
			{
				$photo->delete();
			}
			catch ( \Exception $e ) { }
			
			$this->_coverPhotoSet( new \IPS\Helpers\CoverPhoto( $values['cover_photo'], 0 ), 'new' );
			\IPS\Output::i()->redirect( $this->_coverPhotoReturnUrl()->setQueryString( array( '_position' => 1 ) ) );
		}
		
		if ( \IPS\Dispatcher::hasInstance() and \IPS\Dispatcher::i()->controllerLocation == 'admin' )
		{
			\IPS\Output::i()->output = $form;
		}
		else
		{
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
		}
	}
	
	/**
	 * Remove Cover Photo
	 *
	 * @return	void
	 */
	protected function coverPhotoRemove()
	{
		\IPS\Session::i()->csrfCheck();
		$photo = $this->_coverPhotoGet();
		if ( !$photo->editable )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S216/2', 403, '' );
		}
		
		try
		{
			$photo->delete();
		}
		catch ( \Exception $e ) { }
		
		$this->_coverPhotoSet( new \IPS\Helpers\CoverPhoto( NULL, 0 ), 'remove' );
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->redirect( $this->_coverPhotoReturnUrl() );
		}
	}
	
	/**
	 * Reposition Cover Photo
	 *
	 * @return	void
	 */
	protected function coverPhotoPosition()
	{
		\IPS\Session::i()->csrfCheck();
		$photo = $this->_coverPhotoGet();
		if ( !$photo->editable )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S216/3', 403, '' );
		}
		
		$photo->offset = \IPS\Request::i()->offset;
		$this->_coverPhotoSet( $photo, 'reposition' );
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->redirect( $this->_coverPhotoReturnUrl() );
		}
	}
	
	/**
	 * Get Cover Photo Storage Extension
	 *
	 * @return	string
	 */
	abstract protected function _coverPhotoStorageExtension();
	
	/**
	 * Set Cover Photo
	 *
	 * @param	\IPS\Helpers\CoverPhoto	$photo	New Photo
	 * @return	void
	 */
	abstract protected function _coverPhotoSet( \IPS\Helpers\CoverPhoto $photo );
	
	/**
	 * Get Cover Photo
	 *
	 * @return	\IPS\Helpers\CoverPhoto
	 */
	abstract protected function _coverPhotoGet();
	
	/**
	 * Get URL to return to after editing cover photo
	 *
	 * @return	\IPS\Http\Url
	 */
	protected function _coverPhotoReturnUrl()
	{
		return \IPS\Request::i()->referrer() ?: \IPS\Request::i()->url()->stripQueryString( array( 'do', 'csrfKey' ) );
	}
}