<?php
/**
 * @brief		subscriptions Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	nexus
 * @since		16 Feb 2018
 */

namespace IPS\nexus\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * subscriptions Widget
 */
class _subscriptions extends \IPS\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'subscriptions';
	
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
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'subscriptions.css', 'nexus' ) );
		parent::init();
	}
	
	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		/* If we already have one, don't show it */
		if ( \IPS\nexus\Subscription::loadActiveByMember( \IPS\Member::loggedIn() ) )
		{
			return '';
		}
		
		return $this->output();
	}
}