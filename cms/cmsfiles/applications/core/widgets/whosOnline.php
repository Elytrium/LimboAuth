<?php
/**
 * @brief		whosOnline Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		28 Jul 2014
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * whosOnline Widget
 */
class _whosOnline extends \IPS\Widget\StaticCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'whosOnline';
	
	/**
	 * @brief	App
	 */
	public $app = 'core';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

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
		
		$member = \IPS\Member::loggedIn() ?: new \IPS\Member;

		if( $member->member_id AND !$member->isOnlineAnonymously() )
		{
			\IPS\Output::i()->jsVars['member_id']				= $member->member_id;
			\IPS\Output::i()->jsVars['member_url']				= (string) $member->url();
			\IPS\Output::i()->jsVars['member_hovercardUrl']		= (string) $member->url()->setQueryString( 'do', 'hovercard' );
			\IPS\Output::i()->jsVars['member_formattedName']	= str_replace( '"', '\\"', \IPS\Member\Group::load( $member->member_group_id )->formatName( $member->name ) );
		}

		$theme = $member->skin ?: \IPS\Theme::defaultTheme();
		$this->cacheKey = "widget_{$this->key}_" . $this->uniqueKey . '_' . md5( json_encode( $configuration ) . "_" . $member->language()->id . "_" . $theme . "_" . $orientation . '-' . (int) \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'online', 'front' ) ) );
	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		/* Do we have permission? */
		if ( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'online', 'front' ) ) )
		{
			return "";
		}
		
		/* Init */
		$members     = array();
		$anonymous   = 0;
		
		$users = \IPS\Session\Store::i()->getOnlineUsers( \IPS\Session\Store::ONLINE_MEMBERS, 'desc', NULL, NULL, TRUE );
		foreach( $users as $row )
		{
			switch ( $row['login_type'] )
			{
				/* Not-anonymous Member */
				case \IPS\Session\Front::LOGIN_TYPE_MEMBER:
					if ( $row['member_name'] )
					{
						$members[ $row['member_id'] ] = $row;
					}
					break;
					
				/* Anonymous member */
				case \IPS\Session\Front::LOGIN_TYPE_ANONYMOUS:
					$anonymous += 1;
					break;
			}
		}
		$memberCount = \count( $members );

		/* Get an accurate guest count */
		$guests = \IPS\Session\Store::i()->getOnlineUsers( \IPS\Session\Store::ONLINE_GUESTS | \IPS\Session\Store::ONLINE_COUNT_ONLY, 'desc', NULL, NULL, TRUE );
		
		/* If it's on the sidebar (rather than at the bottom), we want to limit it to 60 so we don't take too much space */
		if ( $this->orientation === 'vertical' and \count( $members ) >= 60 )
		{
			$members = \array_slice( $members, 0, 60 );
		}

		/* Display */
		return $this->output( $members, $memberCount, $guests, $anonymous );
	}
}