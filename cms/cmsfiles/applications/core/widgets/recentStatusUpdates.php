<?php
/**
 * @brief		recentStatusUpdates Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		04 Jun 2014
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * recentStatusUpdates Widget
 */
class _recentStatusUpdates extends \IPS\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'recentStatusUpdates';
	
	/**
	 * @brief	App
	 */
	public $app = 'core';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * @brief	cacheKey
	 */
	public $cacheKey = "";

	/**
	 * Constructor
	 *
	 * @param	String				$uniqueKey				Unique key for this specific instance
	 * @param	array				$configuration			Widget custom configuration
	 * @param	null|string|array	$access					Array/JSON string of executable apps (core=sidebar only, content=IP.Content only, etc)
	 * @param	null|string			$orientation			Orientation (top, bottom, right, left)
	 * @return	void
	 */
	public function __construct( $uniqueKey, array $configuration, $access=null, $orientation=null )
	{
		parent::__construct( $uniqueKey, $configuration, $access, $orientation );

		/* We need to adjust the key for profile posts setting */
		$this->cacheKey = "widget_{$this->key}_" . $this->uniqueKey . '_' . md5( json_encode( $configuration ) . "_" . \IPS\Member::loggedIn()->language()->id . "_" . \IPS\Member::loggedIn()->skin . "_" . json_encode( \IPS\Member::loggedIn()->groups ) . "_" . $orientation . "_" . \IPS\core\Statuses\Status::canCreateFromCreateMenu() );
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
		if ( !\IPS\Settings::i()->profile_comments )
		{
			return '';
		}

		if( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'status', 'front' ) ) )
		{
			return '';
		}
		
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_core.js', 'core' ) );
		
		$statuses = new \IPS\Patterns\ActiveRecordIterator(
			\IPS\Db::i()->select(
					'*',
					'core_member_status_updates',
					array( 'status_approved = 1' ),
					'status_date DESC',
					array( 0, isset( $this->configuration['number_to_show'] ) ? $this->configuration['number_to_show'] : 5  )
			),
			'\IPS\core\Statuses\Status'
		);

		return $this->output( $statuses );
	}
}