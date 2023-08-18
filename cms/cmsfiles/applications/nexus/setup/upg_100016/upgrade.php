<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		19 Feb 2015
 */

namespace IPS\nexus\setup\upg_100016;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Convert Commerce ads to normal ads
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach( \IPS\Db::i()->select( '*', 'core_sys_lang_words', \IPS\Db::i()->in( 'word_key', "word_key LIKE 'nexus_package_%'" ) ) as $word )
		{
			// To finish
			if( mb_strpos( $word['word_key'], '_desc' ) === FALSE AND mb_strpos( $word['word_key'], '_assoc' ) === FALSE AND mb_strpos( $word['word_key'], '_page' ) === FALSE )
			{
				\IPS\Lang::saveCustom( 'nexus', $word['word_key'], strip_tags( $word['word_custom'] ) );
			}
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Fixing package titles";
	}
	
	/**
	 * We changed the values for this setting
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		switch ( \IPS\Settings::i()->nexus_sout_from )
		{
			case 0:
				\IPS\Settings::i()->changeValues( array( 'nexus_sout_from' => 'staff' ) );
				break;
			
			case 1:
				\IPS\Settings::i()->changeValues( array( 'nexus_sout_from' => 'dpt' ) );
				break;
		}
		\IPS\Settings::i()->clearCache();
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Fixing settings";
	}
}