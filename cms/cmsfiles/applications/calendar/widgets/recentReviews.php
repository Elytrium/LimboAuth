<?php
/**
 * @brief		Recent event reviews Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		19 Feb 2014
 */

namespace IPS\calendar\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Recent event reviews Widget
 */
class _recentReviews extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'recentReviews';
	
	/**
	 * @brief	App
	 */
	public $app = 'calendar';
		
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
 		
 		/* Container */
		$form->add( new \IPS\Helpers\Form\Node( 'widget_calendar', isset( $this->configuration['widget_calendar'] ) ? $this->configuration['widget_calendar'] : 0, FALSE, array(
			'class'           => '\IPS\calendar\Calendar',
			'zeroVal'         => 'all',
			'permissionCheck' => 'view',
			'multiple'        => true
		) ) );
 		
		$form->add( new \IPS\Helpers\Form\Number( 'review_count', isset( $this->configuration['review_count'] ) ? $this->configuration['review_count'] : 5, TRUE ) );

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
 		if ( \is_array( $values['widget_calendar'] ) )
 		{
	 		$values['widget_calendar'] = array_keys( $values['widget_calendar'] );
 		}
 		
 		return $values;
 	}
 	
	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$where = array();
		
		if ( ! empty( $this->configuration['widget_calendar'] ) )
		{
			$where['item'][] = array( \IPS\Db::i()->in( 'event_calendar_id', $this->configuration['widget_calendar'] ) );
		}
		
		$reviews = \IPS\calendar\Event\Review::getItemsWithPermission( $where, NULL, ( isset( $this->configuration['review_count'] ) AND $this->configuration['review_count'] > 0 ) ? $this->configuration['review_count'] : 5, 'read', \IPS\Content\Hideable::FILTER_PUBLIC_ONLY );

		return $this->output( $reviews );
	}
}