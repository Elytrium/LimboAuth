<?php
/**
 * @brief		4.1.17 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		15 Nov 2016
 */

namespace IPS\downloads\setup\upg_101071;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.17 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Remove duplicate strings which are now located in the core application
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		
		$stringsToRemove = array(
			'ctags_predefined_desc',
			'ctags_predefined_unlimited',
			'ctags_predefined',
			'ctags_noprefixes',
			'ctags_disabled'
		);

		\IPS\Db::i()->delete( 'core_sys_lang_words', \IPS\Db::i()->in( 'word_key', $stringsToRemove ) );

		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}