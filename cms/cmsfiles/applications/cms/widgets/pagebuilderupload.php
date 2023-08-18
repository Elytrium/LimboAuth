<?php
/**
 * @brief		pagebuilderupload Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Feb 2020
 */

namespace IPS\cms\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * pagebuilderupload Widget
 */
class _pagebuilderupload extends \IPS\Widget\StaticCache implements \IPS\Widget\Builder
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'pagebuilderupload';
	
	/**
	 * @brief	App
	 */
	public $app = 'cms';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';
	
	/**
	 * Specify widget configuration
	 *
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	null|\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
	{
 		$form = parent::configuration( $form );
 		
 		$images = array();
 		$captions = array();
 		$urls = array();
 		
 		if ( ! empty( $this->configuration['pagebuilderupload_upload'] ) )
 		{
	 		foreach( explode( ',', $this->configuration['pagebuilderupload_upload'] ) as $img )
			{
				$images[] = \IPS\File::get( 'core_Attachment', $img );
			}
 		}
 		
 		if ( ! empty( $this->configuration['pagebuilderupload_captions'] ) )
 		{
	 		foreach( $this->configuration['pagebuilderupload_captions'] as $caption )
			{
				$captions[] = $caption;
			}
 		}
 		
 		if ( ! empty( $this->configuration['pagebuilderupload_urls'] ) )
 		{
	 		foreach( json_decode( $this->configuration['pagebuilderupload_urls'], TRUE ) as $url )
			{
				$urls[] = $url;
			}
 		}
 		
 		$form->add( new \IPS\Helpers\Form\Upload( 'pagebuilderupload_upload', $images, FALSE, array( 'multiple' => true, 'storageExtension' => 'core_Attachment', 'allowStockPhotos' => TRUE, 'image' => true ) ) );
 		$form->add( new \IPS\Helpers\Form\YesNo( 'pagebuilderupload_slideshow', ( isset( $this->configuration['pagebuilderupload_slideshow'] ) ? $this->configuration['pagebuilderupload_slideshow'] : FALSE ) ) );
 		$form->add( new \IPS\Helpers\Form\Stack( 'pagebuilderupload_captions', $captions, FALSE, array( 'stackFieldType' => 'Text' ) ) );
		$form->add( new \IPS\Helpers\Form\Stack( 'pagebuilderupload_urls', $urls, FALSE, array( 'stackFieldType' => 'Url' ) ) );
		
		$form->add( new \IPS\Helpers\Form\Number( 'pagebuilderupload_height', ( isset( $this->configuration['pagebuilderupload_height'] ) ? $this->configuration['pagebuilderupload_height'] : 300 ), FALSE, array( 'unlimited' => 0 ) ) );
 		return $form;
 	}

	/**
	 * Before the widget is removed, we can do some clean up
	 *
	 * @return void
	 */
	public function delete()
	{
		foreach( explode( ',', $this->configuration['pagebuilderupload_upload'] ) as $img )
		{
			try
			{
				\IPS\File::get( 'core_Attachment', $img )->delete();
			}
			catch( \Exception $e ) { }
		}
	}
 	
 	 /**
 	 * Ran before saving widget configuration
 	 *
 	 * @param	array	$values	Values from form
 	 * @return	array
 	 */
 	public function preConfig( $values )
 	{
	 	$images = array();
	 	$urls = array();
	 	
	 	foreach( $values['pagebuilderupload_upload'] as $img )
	 	{
		 	$images[] = (string) $img;
	 	}
	 	
	 	foreach( $values['pagebuilderupload_urls'] as $url )
	 	{
		 	$urls[] = (string) $url;
	 	}
	 	
	 	$values['pagebuilderupload_upload'] = implode( ',', $images );
	 	$values['pagebuilderupload_urls'] = json_encode( $urls );
 		return $values;
 	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */ 
	public function render()
	{
		$images = array();
		$captions = ( isset( $this->configuration['pagebuilderupload_captions'] ) ) ? $this->configuration['pagebuilderupload_captions'] : array();
		$urls = ( isset( $this->configuration['pagebuilderupload_urls'] ) ) ? json_decode( $this->configuration['pagebuilderupload_urls'], TRUE ) : array();
		$autoPlay = ( isset( $this->configuration['pagebuilderupload_slideshow'] ) ) ? $this->configuration['pagebuilderupload_slideshow'] : FALSE;
		$maxHeight = ( isset( $this->configuration['pagebuilderupload_height'] ) ) ? $this->configuration['pagebuilderupload_height'] : FALSE;
		
		if ( isset( $this->configuration['pagebuilderupload_upload'] ) )
		{
			foreach( explode( ',', $this->configuration['pagebuilderupload_upload'] ) as $img )
			{
				$images[] = (string) \IPS\File::get( 'core_Attachment', $img )->url;
			}

			return $this->output( ( \count( $images ) === 1 ? $images[0] : $images ), $captions, $urls, $autoPlay, $maxHeight );
		}
		
		return '';
	}
}