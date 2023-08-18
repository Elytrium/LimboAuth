<?php
/**
 * @brief		Front Navigation Extension: Leaderboard
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		8th Nov 2016
 */

namespace IPS\core\extensions\core\FrontNavigation;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Front Navigation Extension: Leaderboard
 */
class _Leaderboard extends \IPS\core\FrontNavigation\FrontNavigationAbstract
{
	/**
	 * Get Type Title which will display in the AdminCP Menu Manager
	 *
	 * @return	string
	 */
	public static function typeTitle()
	{
		return \IPS\Member::loggedIn()->language()->addToStack('leaderboard_title');
	}
		
	/**
	 * Can the currently logged in user access the content this item links to?
	 *
	 * @return	bool
	 */
	public function canAccessContent()
	{
		return \IPS\Settings::i()->reputation_leaderboard_on and \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'discover' ) ) and \IPS\Settings::i()->reputation_enabled;
	}
	
	/**
	 * Get Title
	 *
	 * @return	string
	 */
	public function title()
	{
		return \IPS\Member::loggedIn()->language()->addToStack('leaderboard_title');
	}
	
	/**
	 * Get Link
	 *
	 * @return	\IPS\Http\Url
	 */
	public function link()
	{
		switch ( \IPS\Settings::i()->reputation_leaderboard_default_tab )
		{
			default:
			case 'leaderboard':
				return \IPS\Http\Url::internal( "app=core&module=discover&controller=popular&tab=leaderboard", 'front', 'leaderboard_leaderboard' );
			break;
			case 'history':
				return \IPS\Http\Url::internal( "app=core&module=discover&controller=popular&tab=history", 'front', 'leaderboard_history' );
			break;
			case 'members':
				return \IPS\Http\Url::internal( "app=core&module=discover&controller=popular&tab=members", 'front', 'leaderboard_members' );
			break;
		}
	}
	
	/**
	 * Is Active?
	 *
	 * @return	bool
	 */
	public function active()
	{
		return \IPS\Dispatcher::i()->application->directory === 'core' and \IPS\Dispatcher::i()->module->key === 'discover' and \IPS\Dispatcher::i()->controller == 'popular';
	}
}