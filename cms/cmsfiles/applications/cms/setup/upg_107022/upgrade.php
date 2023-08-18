<?php
/**
 * @brief		4.7.1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		10 Aug 2022
 */

namespace IPS\cms\setup\upg_107022;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.7.1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Set record published date to fix issue in 4.7.1. Only fix records created after the beta was released.
	 *
	 * @return	bool|array 	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach( \IPS\Db::i()->select( '*', 'cms_databases' ) as $database )
		{
			\IPS\Db::i()->query('UPDATE `' . \IPS\Db::i()->prefix .'cms_custom_database_' . $database['database_id'] . '` SET record_publish_date = record_saved, record_future_date = 1 WHERE record_publish_date = 0 AND record_saved > 1659372642 ' );
		}

		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}