<?php
/**
 * @brief		mostContributions Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		19 Jul 2018
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * mostContributions Widget
 */
class _mostContributions extends \IPS\Widget\StaticCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'mostContributions';
	
	/**
	 * @brief	App
	 */
	public $app = 'core';
		
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

		/* Block title */
		$form->add( new \IPS\Helpers\Form\Translatable( 'widget_feed_title', isset( $this->configuration['language_key'] ) ? NULL : \IPS\Member::loggedIn()->language()->addToStack( 'block_mostContributions' ), FALSE, array( 'app' => 'core', 'key' => ( isset( $this->configuration['language_key'] ) ? $this->configuration['language_key'] : NULL ) ) ) );

		$form->add( new \IPS\Helpers\Form\Number( 'number_to_show', isset( $this->configuration['number_to_show'] ) ? $this->configuration['number_to_show'] : 5, TRUE, array( 'max' => 25 ) ) );

		/* What are we showing? */
		$classes = array();
		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter' ) as $contentRouter )
		{
			foreach ( $contentRouter->classes as $class )
			{
				$exploded = explode( '\\', $class );
				if ( \in_array( 'IPS\Content\Item', class_parents( $class ) ) )
				{
					if ( $class::incrementPostCount() )
					{
						$classes[ $exploded[1] ][] = $class;
					}
					if ( isset( $class::$commentClass ) )
					{
						$commentClass = $class::$commentClass;
						if ( $commentClass::incrementPostCount() )
						{
							$classes[ $exploded[1] ][] = $commentClass;
						}
					}
					if ( isset( $class::$reviewClass ) )
					{
						$reviewClass = $class::$reviewClass;
						if ( $reviewClass::incrementPostCount() )
						{
							$classes[ $exploded[1] ][] = $class::$reviewClass;
						}
					}
				}
				elseif ( \in_array( 'IPS\Content\Comment', class_parents( $class ) ) )
				{
					if ( $class::incrementPostCount() )
					{
						$classes[ $exploded[1] ][] = $class;
					}
				}
			}
		}

		$options = array();
		if( !empty ( $classes ) )
		{
			foreach ( $classes as $app => $areas )
			{
				if ( $app == 'core' )
				{
					continue;
				}

				$options[ '__app_' . $app ] = array();
				foreach ( $areas as $item )
				{
					$options[ '__app_' . $app ][ $item ] = $item::$title;
				}
			}
		}

		$form->add( new \IPS\Helpers\Form\Select( 'most_contributions_area', isset( $this->configuration['most_contributions_area'] ) ? $this->configuration['most_contributions_area'] : "0", FALSE, array( 'options' => $options, 'multiple' => FALSE, 'unlimited' => '0', 'unlimitedLang' => "everything" ), NULL, NULL, NULL, 'most_contributions_area' ) );
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
		if ( !isset( $this->configuration['language_key'] ) )
		{
			$this->configuration['language_key'] = 'widget_title_' . md5( mt_rand() );
		}
		$values['language_key'] = $this->configuration['language_key'];

		\IPS\Lang::saveCustom( 'core', $this->configuration['language_key'], $values['widget_feed_title'] );
		unset( $values['widget_feed_title'] );

 		return $values;
 	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$members = array();
		$area = isset( $this->configuration['most_contributions_area'] ) ? $this->configuration['most_contributions_area'] : NULL;

		/* If we're showing everything that's easy just show the content count */
		if( !$area )
		{
			$contributions = array( 'members' => new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members', array( "member_posts > ?", 0 ), "member_posts DESC", array( 0,5 ) ), 'IPS\Member' ) );
		}
		/* A specific content type? That's more work */
		else
		{
			$contributions = $area::mostContributions( $this->configuration['number_to_show'] );
		}

		if( !\count( $contributions['members'] ) )
		{
			return "";
		}

		if ( isset( $this->configuration['language_key'] ) )
		{
			$title = \IPS\Member::loggedIn()->language()->addToStack( $this->configuration['language_key'], FALSE, array( 'escape' => TRUE ) );
		}
		elseif ( isset( $this->configuration['widget_feed_title'] ) )
		{
			$title = $this->configuration['widget_feed_title'];
		}
		else
		{
			$title = \IPS\Member::loggedIn()->language()->addToStack( 'block_mostContributions' );
		}

		return $this->output( $contributions, $area, $title );
	}

	/**
	 * Before the widget is removed, we can do some clean up
	 *
	 * @return void
	 */
	public function delete()
	{
		\IPS\Lang::deleteCustom( 'core', $this->configuration['language_key'] );
	}
}
