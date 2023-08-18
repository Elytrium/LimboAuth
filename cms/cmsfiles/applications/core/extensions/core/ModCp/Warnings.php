<?php
/**
 * @brief		Moderator Control Panel Extension: Recent Warnings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 May 2014
 */

namespace IPS\core\extensions\core\ModCp;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Recent Warnings
 */
class _Warnings
{
	/**
	 * Returns the primary tab key for the navigation bar
	 *
	 * @return	string
	 */
	public function getTab()
	{
		if ( ! \IPS\Member::loggedIn()->modPermission('mod_see_warn') OR ! \IPS\Settings::i()->warn_on )
		{
			return null;
		}
		
		return 'recent_warnings';
	}
	
	/**
	 * Get content to display
	 *
	 * @return	string
	 */
	public function manage()
	{
		if ( ! \IPS\Member::loggedIn()->modPermission('mod_see_warn') )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C224/1', 403, '' );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'modcp_recent_warnings' );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'modcp_recent_warnings' ) );
		$table = new \IPS\Helpers\Table\Content( 'IPS\core\Warnings\Warning', \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=recent_warnings', 'front', 'modcp_recent_warnings' ) );
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate('modcp'), 'recentWarningsTable' );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate('modcp'), 'recentWarningsRows' );
		
		return (string) $table;
	}
}