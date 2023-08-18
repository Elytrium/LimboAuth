<?php
/**
 * @brief		invite Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		06 Aug 2019
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * invite Widget
 */
class _invite extends \IPS\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'invite';
	
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
		// Use this to perform any set up and to assign a template that is not in the following format:
		$this->template( array( \IPS\Theme::i()->getTemplate( 'widgets', $this->app, 'front' ), $this->key ) );

		parent::init();
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
		$subject = \IPS\Member::loggedIn()->language()->addToStack('block_invite_subject', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->board_name ), 'rawurlencode' => TRUE ) );
		$url = \IPS\Http\Url::internal( "" );

		if( \IPS\Settings::i()->ref_on and \IPS\Member::loggedIn()->member_id )
		{
			$url = $url->setQueryString( array( '_rid' => \IPS\Member::loggedIn()->member_id  ) );
		}

		return $this->output( $subject, urlencode( $url ) );
	}
}