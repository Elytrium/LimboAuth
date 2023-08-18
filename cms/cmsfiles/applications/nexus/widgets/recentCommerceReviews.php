<?php
/**
 * @brief		recentCommerceReviews Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	nexus
 * @since		17 Jul 2018
 */

namespace IPS\nexus\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * recentCommerceReviews Widget
 */
class _recentCommerceReviews extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'recentCommerceReviews';
	
	/**
	 * @brief	App
	 */
	public $app = 'nexus';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';
	
	/**
	 * Init widget
	 *
	 * @return	null
	 */
	public function init()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'widgets.css', 'nexus', 'front' ) );
		parent::init();
	}
	
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
		$form->add( new \IPS\Helpers\Form\Node( 'widget_group', isset( $this->configuration['widget_group'] ) ? $this->configuration['widget_group'] : 0, FALSE, array(
			'class'           => '\IPS\nexus\Package\Group',
			'zeroVal'         => 'all',
			'permissionCheck' => 'view',
			'multiple'        => true,
			'subnodes'		  => false,
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
		if ( \is_array( $values['widget_group'] ) )
		{
			$values['widget_group'] = array_keys( $values['widget_group'] );
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

		if ( ! empty( $this->configuration['widget_group'] ) )
		{
			$where['item'][] = array( \IPS\Db::i()->in( 'p_group', $this->configuration['widget_group'] ) );
		}

		$reviews = \IPS\nexus\Package\Review::getItemsWithPermission( $where, NULL, ( isset( $this->configuration['review_count'] ) AND $this->configuration['review_count'] > 0 ) ? $this->configuration['review_count'] : 5, 'read', \IPS\Content\Hideable::FILTER_PUBLIC_ONLY );

		if ( !\count( $reviews ) )
		{
			return "";
		}

		return $this->output( $reviews );
	}
}