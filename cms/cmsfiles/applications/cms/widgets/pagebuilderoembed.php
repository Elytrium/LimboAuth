<?php
/**
 * @brief		oembed Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		30 Sep 2019
 */

namespace IPS\cms\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * oembed Widget
 */
class _pagebuilderoembed extends \IPS\Widget\StaticCache implements \IPS\Widget\Builder
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'pagebuilderoembed';
	
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

 		$form->add( new \IPS\Helpers\Form\Url( 'video_url', ( isset( $this->configuration['video_url'] )  )? $this->configuration['video_url'] : NULL, TRUE, array(), function( $url ) {
	 		if ( \IPS\Text\Parser::embeddableMedia( \IPS\Http\Url::external( $url ) ) === NULL )
	 		{
		 		throw new \DomainException('video_cannot_embed');
	 		}
 		} ) );
 		return $form;
 	} 
 	
 	 /**
 	 * Ran before saving widget configuration
 	 *
 	 * @param	array	$values	Values from form
 	 * @return	array
 	 */
 	public function preConfig( $values )
 	{
	 	$values['video_url'] = (string) $values['video_url'];
 		return $values;
 	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		try
		{
			if ( isset( $this->configuration['video_url'] ) AND $embed = \IPS\Text\Parser::embeddableMedia( \IPS\Http\Url::external( $this->configuration['video_url'] ) ) )
			{
				return $this->output( $embed );
			}
		}
		catch( \UnexpectedValueException $e ){}
		
		return '';
	}
}