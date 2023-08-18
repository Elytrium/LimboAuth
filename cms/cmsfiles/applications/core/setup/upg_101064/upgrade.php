<?php
/**
 * @brief		4.1.16 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 Sep 2016
 */

namespace IPS\core\setup\upg_101064;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.16 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Set the site secret key
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 * @note	The \IPS namespace is intentionally left off the constant, since it is removed in this version and we don't import constants in constants.php into the \IPS namespace unless they're also defined in init.php
	 */
	public function step1()
	{
		/* The setting won't have been inserted yet at this point, so insert it with the key set */
		\IPS\Db::i()->replace( 'core_sys_conf_settings', array(
			'conf_key'		=> 'site_secret_key',
			'conf_value'	=> ( \defined( 'SITE_SECRET_KEY' ) AND SITE_SECRET_KEY ) ? SITE_SECRET_KEY : md5( \IPS\Settings::i()->sql_pass . \IPS\Settings::i()->board_url . \IPS\Settings::i()->sql_database ),
			'conf_default'	=> '',
			'conf_app'		=> 'core'
		)	);

		return true;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Updating image proxy key";
	}
	
	/**
	 * Create Security Questions
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\Lang::saveCustom( 'core', 'security_question_1', 'In what city or town does your nearest sibling live?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_2', 'What is your favorite children\'s book?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_3', 'What was the name of your first pet?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_4', 'What was your childhood nickname?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_5', 'Who was your childhood hero?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_6', 'What is your dream job?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_7', 'What is your father\'s middle name?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_8', 'What is your mother\'s maiden name?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_9', 'Who was your favorite singer or band in high school?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_10', 'What was the first name of your first boss?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_11', 'In what city did your parents first meet?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_12', 'What is the first name of your best friend in high school?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_13', 'What was the first film you saw in theater?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_14', 'What was the first thing you learned to cook?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_15', 'Where did you go the first time you flew on a plane?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_16', 'What is the name of the street you grew up on?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_17', 'What is the name of the first beach you visited?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_18', 'What was the first album you purchased?' );
		\IPS\Lang::saveCustom( 'core', 'security_question_19', 'What is the name of your favorite sports team?' );
		
		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Creating security questions";
	}	

	/**
	 * Finish - This is run after all apps have been upgraded
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 * @note	We opted not to let users run this immediately during the upgrade because of potential issues (it taking a long time and users stopping it or getting frustrated) but we can revisit later
	 */
	public function finish()
	{
		/* Rebuild search index */
		\IPS\Content\Search\Index::i()->rebuild();
	}
}