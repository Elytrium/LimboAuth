<?php
/**
 * @brief		Clubs Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		24 Apr 2017
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Clubs Widget
 */
class _clubs extends \IPS\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'clubs';
	
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
 		
 		$form->add( new \IPS\Helpers\Form\Translatable( 'widget_feed_title', isset( $this->configuration['language_key'] ) ? NULL : \IPS\Member::loggedIn()->language()->addToStack( 'my_clubs' ), FALSE, array( 'app' => 'core', 'key' => ( isset( $this->configuration['language_key'] ) ? $this->configuration['language_key'] : NULL ) ) ) );

		$form->add( new \IPS\Helpers\Form\Radio( 'club_filter_type', isset( $this->configuration['club_filter_type'] ) ? $this->configuration['club_filter_type'] : 'mine', TRUE, array( 'options' => array(
			'mine'	=> 'user_clubs',
			'all'	=> 'all_clubs',
		) ) ) );
		
		$fields = \IPS\Member\Club\CustomField::roots();
		foreach ( $fields as $field )
		{
			if ( $field->filterable )
			{
				switch ( $field->type )
				{
					case 'Checkbox':
					case 'YesNo':
						$input = new \IPS\Helpers\Form\CheckboxSet( 'field_' . $field->id, isset( $this->configuration['filters'][ $field->id ] ) ? $this->configuration['filters'][ $field->id ] : array( 1, 0 ), FALSE, array( 'options' => array(
							1			=> 'yes',
							0			=> 'no',
						) ) );
						$input->label = $field->_title;
						$form->add( $input );
						break;
						
					case 'CheckboxSet':
					case 'Radio':
					case 'Select':
						$options = json_decode( $field->extra, TRUE );
						$input = new \IPS\Helpers\Form\CheckboxSet( 'field_' . $field->id, isset( $this->configuration['filters'][ $field->id ] ) ? $this->configuration['filters'][ $field->id ] : array_keys( $options ), FALSE, array( 'options' => $options ) );
						$input->label = $field->_title;
						$form->add( $input );
						break;
				}
			}
		}

		$form->add( new \IPS\Helpers\Form\Radio( 'sort_by', isset( $this->configuration['sort_by'] ) ? $this->configuration['sort_by'] : 'last_activity', TRUE, array( 'options' => array(
			'last_activity'	=> 'clubs_sort_last_activity',
			'members'		=> 'clubs_sort_members',
			'content'		=> 'clubs_sort_content',
			'created'		=> 'clubs_sort_created',
		) ) ) );
		$form->add( new \IPS\Helpers\Form\Number( 'number_to_show', isset( $this->configuration['number_to_show'] ) ? $this->configuration['number_to_show'] : 10, TRUE ) );
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
 		
 		$fields = \IPS\Member\Club\CustomField::roots();
 		foreach ( $fields as $field )
		{
			if ( $field->filterable )
			{					
				switch ( $field->type )
				{
					case 'Checkbox':
					case 'YesNo':
						if ( \count( $values[ 'field_' . $field->id ] ) === 1 )
						{
							$values['filters'][ $field->id ] = array_pop( $values[ 'field_' . $field->id ] );
						}
						unset( $values[ 'field_' . $field->id ] );
						break;
						
					case 'CheckboxSet':
					case 'Radio':
					case 'Select':
						$options = json_decode( $field->extra, TRUE );
						if ( \count( $values[ 'field_' . $field->id ] ) > 0 and \count( $values[ 'field_' . $field->id ] ) < \count( $options ) )
						{
							$values['filters'][ $field->id ] = array();
							foreach ( $values[ 'field_' . $field->id ] as $v )
							{
								$values['filters'][ $field->id ][] = $options[ $v ];
							}
						}
						unset( $values[ 'field_' . $field->id ] );
						break;
				}
			}
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
		if ( \IPS\Settings::i()->clubs and \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'clubs', 'front' ) ) )
		{
			$clubsCount = \IPS\Member\Club::clubs(
				\IPS\Member::loggedIn(),
				isset( $this->configuration['number_to_show'] ) ? $this->configuration['number_to_show'] : 10,
				isset( $this->configuration['sort_by'] ) ? $this->configuration['sort_by'] : 'last_activity',
				!isset( $this->configuration['club_filter_type'] ) or $this->configuration['club_filter_type'] == 'mine',
				isset( $this->configuration['filters'] ) ? $this->configuration['filters'] : array(),
				NULL,
				TRUE
			);
			
			if ( $clubsCount )
			{
				$clubs = \IPS\Member\Club::clubs(
					\IPS\Member::loggedIn(),
					isset( $this->configuration['number_to_show'] ) ? $this->configuration['number_to_show'] : 10,
					isset( $this->configuration['sort_by'] ) ? $this->configuration['sort_by'] : 'last_activity',
					!isset( $this->configuration['club_filter_type'] ) or $this->configuration['club_filter_type'] == 'mine',
					isset( $this->configuration['filters'] ) ? $this->configuration['filters'] : array()
				);

				return $this->output(
					$clubs,
					isset( $this->configuration['language_key'] ) ? \IPS\Member::loggedIn()->language()->addToStack( $this->configuration['language_key'], FALSE, array( 'escape' => TRUE ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'my_clubs' ),
					$this->orientation
				);
			}
		}
		
		return '';
	}
}