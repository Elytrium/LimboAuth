<?php
/**
 * @brief		4.4.2 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		27 Feb 2019
 */

namespace IPS\core\setup\upg_104015;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.4.2 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Clean up database
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Recreate the output cache table */
		if ( \IPS\OUTPUT_CACHE_METHOD == 'RemoteDatabase' )
		{
			\IPS\Output\Cache::i()->recreateTable();
		}
		
		\IPS\Db::i()->delete( 'core_sys_lang_words', array( \IPS\Db::i()->in( 'word_key', array( 'all_activity_votes', 'activity_voted' ) ) ) );
		
		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Cleaning up the database";
	}
}