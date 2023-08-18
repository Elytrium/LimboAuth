<?php
/**
 * @brief		featuredProduct Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	nexus
 * @since		18 Jul 2018
 */

namespace IPS\nexus\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * featuredProduct Widget
 */
class _featuredProduct extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'featuredProduct';
	
	/**
	 * @brief	App
	 */
	public $app = 'nexus';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';
	
	/**
	 * Initialise this widget
	 *
	 * @return void
	 */ 
	public function init()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'widgets.css', 'nexus' ) );
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
		
		$value = 0;
		if ( isset( $this->configuration['package'] ) )
		{
			if ( \is_array( $this->configuration['package'] ) )
			{
				$value = $this->configuration['package'];
			}
			else
			{
				$value = array( $this->configuration['package'] );
			}
		}
		
		$form->add( new \IPS\Helpers\Form\Node( 'package', $value, FALSE, array(
			'class'           => '\IPS\nexus\Package',
			'permissionCheck' => function( $node )
			{
				if ( $node->canView() and $node->store )
				{
					return TRUE;
				}
				return FALSE;
			},
			'multiple'        => true,
			'subnodes'		  => false,
		) ) );

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
		if ( \is_array( $values['package'] ) )
		{
			$save = array();
			foreach( $values['package'] AS $pkg )
			{
				$save[] = $pkg->id;
			}
			$values['package'] = $save;
		}
		else
		{
			$values['package'] = array( $values['package']->id );
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
		//Load the product
		$packages = array();
		if( isset( $this->configuration['package'] ) )
		{
			if ( \is_array( $this->configuration['package'] ) )
			{
				$packages = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_packages', array( array( 'p_store=1' ), array( \IPS\Db::i()->in( 'p_id', $this->configuration['package'] ) ) ) ), 'IPS\nexus\Package' );
			}
			else
			{
				try
				{
					$packages = array( \IPS\nexus\Package::load( $this->configuration['package'] ) );
				}
				catch ( \OutOfRangeException $e ){}
			}
		}

		if ( !\count( $packages ) )
		{
			return "";
		}

		return $this->output( $packages );
	}
}