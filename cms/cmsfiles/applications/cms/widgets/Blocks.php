<?php
/**
 * @brief		Custom Blocks Block
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		17 Oct 2014
 */

namespace IPS\cms\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Custom block Widget
 */
class _Blocks extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'Blocks';
	
	/**
	 * @brief	App
	 */
	public $app = 'cms';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';
	
	/**
	 * Constructor
	 *
	 * @param	string				$uniqueKey				Unique key for this specific instance
	 * @param	array				$configuration			Widget custom configuration
	 * @param	null|string|array	$access					Array/JSON string of executable apps (core=sidebar only, content=IP.Content only, etc)
	 * @param	null|string			$orientation			Orientation (top, bottom, right, left)
	 * @return	void
	 */
	public function __construct( $uniqueKey, array $configuration, $access=null, $orientation=null )
	{
		try
		{
			if (  isset( $configuration['cms_widget_custom_block'] ) )
			{
				$block = \IPS\cms\Blocks\Block::load( $configuration['cms_widget_custom_block'], 'block_key' );
				if ( $block->type === 'custom' AND ! $block->cache )
				{
					$this->neverCache = TRUE;
				}
				else if ( $block->type === 'plugin' )
				{
					try
					{
						/* loads and JS and CSS needed */
						$block->orientation = $orientation;
						$block->widget()->init();
					}
					catch( \Exception $e ) { }
				}
			}
		}
		catch( \Exception $e ) { }
		
		parent::__construct( $uniqueKey, $configuration, $access, $orientation );
	}
	
	/**
	 * Specify widget configuration
	 *
	 * @param   \IPS\Helpers\Form   $form       Form Object
	 * @return	null|\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
 	{
		$form = parent::configuration( $form );
		
		/* A block may be deleted on the back end */
		$block = NULL;
		try
		{
			if ( isset( $this->configuration['cms_widget_custom_block'] ) )
			{
				$block = \IPS\cms\Blocks\Block::load( $this->configuration['cms_widget_custom_block'], 'block_key' );
			}
		}
		catch( \OutOfRangeException $e ) { }
		
	    $form->add( new \IPS\Helpers\Form\Node( 'cms_widget_custom_block', $block, FALSE, array(
            'class' => '\IPS\cms\Blocks\Container',
            'showAllNodes' => TRUE,
            'permissionCheck' => function( $node )
                {
	                if ( $node instanceof \IPS\cms\Blocks\Container )
	                {
		                return FALSE;
	                }

	                return TRUE;
                }
        ) ) );

	    return $form;
 	}

	/**
	 * Pre config
	 *
	 * @param   array   $values     Form values
	 * @return  array
	 */
	public function preConfig( $values )
	{
		$newValues = $values;

		if ( isset( $values['cms_widget_custom_block'] ) )
		{
			$newValues['cms_widget_custom_block'] = $values['cms_widget_custom_block']->key;
		}

		return $newValues;
	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		if ( isset( $this->configuration['cms_widget_custom_block'] ) )
		{
			return (string) \IPS\cms\Blocks\Block::display( $this->configuration['cms_widget_custom_block'], $this->orientation );
		}

		return '';
	}
}