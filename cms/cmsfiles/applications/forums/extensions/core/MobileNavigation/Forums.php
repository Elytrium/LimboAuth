<?php
/**
 * @brief		Mobile Navigation Extension: Forums
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		12 Jun 2019
 */

namespace IPS\forums\extensions\core\MobileNavigation;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Mobile Navigation Extension: Topics
 */
class _Forums extends \IPS\core\MobileNavigation\MobileNavigationAbstract
{
	/**
	 * Get Type Title which will display in the AdminCP Menu Manager
	 *
	 * @return	string
	 */
	public static function typeTitle()
	{
		return \IPS\Member::loggedIn()->language()->addToStack('mobilenavigation_Forums');
	}
	
	/**
	 * Can the currently logged in user access the content this item links to?
	 *
	 * @return	bool
	 */
	public function canAccessContent()
	{
		return \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'forums', 'forums', 'front' ) );
	}
	
	/**
	 * Get Title
	 *
	 * @return	string
	 */
	public function title()
	{
		return \IPS\Member::loggedIn()->language()->addToStack('mobilenavigation_Forums');
	}
	
	/**
	 * Get Link
	 *
	 * @return	\IPS\Http\Url
	 */
	public function link()
	{
		return \IPS\Http\Url::internal( "app=forums&module=forums&controller=index&method=table", 'front', 'forums' );
	}

	/**
	 * Return icon
	 *
	 * @return	string|null
	 */
	public function icon()
	{
		return 'LIST_ROWS';
	}
}