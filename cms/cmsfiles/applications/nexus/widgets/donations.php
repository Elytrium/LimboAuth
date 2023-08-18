<?php
/**
 * @brief		donations Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		07 Apr 2015
 */

namespace IPS\nexus\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * donations Widget
 */
class _donations extends \IPS\Widget\StaticCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'donations';
	
	/**
	 * @brief	App
	 */
	public $app = 'nexus';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	public function __construct( $uniqueKey, array $configuration, $access=null, $orientation=null )
	{
		parent::__construct( $uniqueKey, $configuration, $access, $orientation );

		$member = \IPS\Member::loggedIn();

		$theme = $member->skin ?: \IPS\Theme::defaultTheme();
		$this->cacheKey = "widget_{$this->key}_" . $this->uniqueKey . '_' . md5( json_encode( $configuration ) . "_" . $member->language()->id . "_" . $member->member_id ? 1 : 0 . "_" . $theme . "_" . $orientation );
	}

	/**
	 * Initialise this widget
	 *
	 * @return void
	 */ 
	public function init()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'donations.css', 'nexus' ) );
		return parent::init();
	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		return $this->output();
	}
}