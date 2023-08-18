<?php
/**
 * @brief		4.3.0 Beta 3 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Mar 2018
 */

namespace IPS\core\setup\upg_103003;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.3.0 Beta 3 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Update Reactions Like Mode Setting
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Content\Reaction::updateLikeModeSetting();

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step1CustomTitle()
	{
		return 'Caching reaction preferences';
	}

	/**
	 * Clean up old language strings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		\IPS\Db::i()->delete(
			'core_sys_lang_words',
			array( 
				'word_app IS NOT NULL AND word_app NOT IN(?)',
				\IPS\Db::i()->select( 'app_directory', 'core_applications' )
			)
		);

		return TRUE;
	}
}