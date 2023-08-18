<?php
/**
 * @brief		pagebuildertext Widget
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
 * pagebuildertext Widget
 */
class _pagebuildertext extends \IPS\Widget\StaticCache implements \IPS\Widget\Builder
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'pagebuildertext';
	
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

 		$form->add( new \IPS\Helpers\Form\TextArea( 'pagebuilder_text', ( isset( $this->configuration['pagebuilder_text'] ) ? $this->configuration['pagebuilder_text'] : '' ), FALSE, array( 'rows' => 10 ) ) );
 		return $form;
 	} 

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		if ( ! empty( $this->configuration['pagebuilder_text'] ) )
		{
			return $this->output( $this->configuration['pagebuilder_text'] );
		}
		
		return '';
	}
}