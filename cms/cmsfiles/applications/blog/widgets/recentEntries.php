<?php
/**
 * @brief		Recent Entries Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		10 Mar 2014
 */

namespace IPS\blog\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * recentEntries Widget
 */
class _recentEntries extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'recentEntries';
	
	/**
	 * @brief	App
	 */
	public $app = 'blog';
		
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
 		
		$form->add( new \IPS\Helpers\Form\Number( 'number_to_show', isset( $this->configuration['number_to_show'] ) ? $this->configuration['number_to_show'] : 5, TRUE ) );
		return $form;
 	} 

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$entries = \IPS\blog\Entry::getItemsWithPermission( array( array( 'entry_status!=?', 'draft' ) ), NULL, isset( $this->configuration['number_to_show'] ) ? $this->configuration['number_to_show'] : 5, 'read', \IPS\Content\Hideable::FILTER_PUBLIC_ONLY );
		if ( \count( $entries ) )
		{
			return $this->output( $entries );
		}
		else
		{
			return '';
		}

	}
}