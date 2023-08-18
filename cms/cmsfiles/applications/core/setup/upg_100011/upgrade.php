<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 Jan 2015
 */

namespace IPS\core\setup\upg_100011;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Beta 6 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 2
	 * Fix the imported ipb3 words
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$existingApplications = array_keys( \IPS\Application::applications() );
		\IPS\Db::i()->delete( 'core_sys_lang_words', 'word_plugin <> NULL AND ' . \IPS\Db::i()->in( 'word_app', $existingApplications, TRUE ) );

		return TRUE;
	}

	/**
	 * Step 3
	 * Remove search cleanup task
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\Db::i()->delete('core_tasks', array('`key`=? and app=?', 'searchcleanup', 'core' ) );

		return TRUE;
	}
}