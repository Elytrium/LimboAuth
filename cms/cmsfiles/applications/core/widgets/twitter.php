<?php
/**
 * @brief		twitter Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		30 Sep 2019
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * twitter Widget
 */
class _twitter extends \IPS\Widget\StaticCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'twitter';
	
	/**
	 * @brief	App
	 */
	public $app = 'core';
		
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

 		$form->add( new \IPS\Helpers\Form\Text( 'twitter_name', ( isset( $this->configuration['twitter_name'] ) ) ? $this->configuration['twitter_name'] : NULL, TRUE ) );
 		$form->add( new \IPS\Helpers\Form\Radio( 'twitter_style', ( isset( $this->configuration['twitter_style'] ) ) ? $this->configuration['twitter_style'] : 'light', FALSE, array( 'options' => array(
	 		'light'		=> 'twitter_light',
	 		'dark'		=> 'twitter_dark'
 		) ) ) );
 		$form->add( new \IPS\Helpers\Form\Color( 'twitter_color', ( isset( $this->configuration['twitter_color'] ) ) ? $this->configuration['twitter_color'] : NULL ), FALSE );
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
 		return $values;
 	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		if ( isset( $this->configuration['twitter_name'] ) )
		{
			$locale = explode( \IPS\Member::loggedIn()->language()->bcp47(), '-' );
			return $this->output( $this->configuration['twitter_name'], $locale[0], $this->configuration['twitter_style'] ?? 'light', $this->configuration['twitter_color'] ?? NULL );
		}
		
		return '';
	}
}