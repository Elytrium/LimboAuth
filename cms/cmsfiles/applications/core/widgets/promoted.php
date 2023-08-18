<?php
/**
 * @brief		Promoted Content Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 March 2017
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Promoted Content Widget
 */
class _promoted extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'promoted';
	
	/**
	 * @brief	App
	 */
	public $app = 'core';
	
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * Initialize widget
	 *
	 * @return	null
	 */
	public function init()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/promote.css', 'core' ) );

		if ( \IPS\Theme::i()->settings['responsive'] )
  		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/promote_responsive.css', 'core' ) );
		}

		parent::init();
	}

	/**
	 * Specify widget configuration
	 *
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
 	{
 		$form = parent::configuration( $form );
 		
		$form->add( new \IPS\Helpers\Form\Number( 'toshow', isset( $this->configuration['toshow'] ) ? $this->configuration['toshow'] : 5, TRUE ) );
		
		return $form;
 	}
 	
	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$limit = isset ( $this->configuration['toshow'] ) ? $this->configuration['toshow'] : 5;
		$stream = \IPS\core\Promote::internalStream( $limit );

		if ( ! \count( $stream ) )
		{
			return '';
		}

		return $this->output( $stream );
	}
}