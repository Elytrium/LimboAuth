<?php
/**
 * @brief		activeUsers Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		19 Nov 2013
 */

namespace IPS\core\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * activeUsers Widget
 */
class _activeUsers extends \IPS\Widget\StaticCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'activeUsers';
	
	/**
	 * @brief	App
	 */
	public $app = 'core';
	
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * @brief	Cache Expiration
	 * @note	We only let this cache be valid for up to 60 seconds
	 */
	public $cacheExpiration = 60;

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

		/* We can't run the URL related logic if we have no dispatcher because this class could also be initialized by the CLI cron job */
		if( \IPS\Dispatcher::hasInstance() )
		{
			$parts = parse_url( (string) \IPS\Request::i()->url()->setPage() );
			
			if ( \IPS\Settings::i()->htaccess_mod_rewrite )
			{
				$this->url = $parts['scheme'] . "://" . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . $parts['path'];
			}
			else
			{
				$this->url = $parts['scheme'] . "://" . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . $parts['path'] . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
			}

			if( $member->member_id AND !$member->isOnlineAnonymously() )
			{
				\IPS\Output::i()->jsVars['member_id']				= $member->member_id;
				\IPS\Output::i()->jsVars['member_url']				= (string) $member->url();
				\IPS\Output::i()->jsVars['member_hovercardUrl']		= (string) $member->url()->setQueryString( 'do', 'hovercard' );
				\IPS\Output::i()->jsVars['member_formattedName']	= str_replace( '"', '\\"', \IPS\Member\Group::load( $member->member_group_id )->formatName( $member->name ) );
			}

			$theme = $member->skin ?: \IPS\Theme::defaultTheme();
			$this->cacheKey = "widget_{$this->key}_" . $this->uniqueKey . '_' . md5( $this->url . '_' . json_encode( $configuration ) . "_" . $member->language()->id . "_" . $theme . "_" . $orientation . '-' . (int) \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'members', 'front' ) ) );
		}
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
		
		$members = \IPS\Session\Store::i()->getOnlineMembersByLocation( \IPS\Dispatcher::i()->application->directory, \IPS\Dispatcher::i()->module->key, \IPS\Dispatcher::i()->controller, \IPS\Request::i()->id, $this->url );

		$memberCount = \count( $members );
		
		/* If it's on the sidebar (rather than at the bottom), we want to limit it to 60 so we don't take too much space */
		if ( $this->orientation === 'vertical' and \count( $members ) >= 60 )
		{
			$members = \array_slice( $members, 0, 60 );
		}

		/* Display */
		return $this->output( $members, $memberCount );
	}
}