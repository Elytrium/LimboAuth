<?php
/**
 * @brief		4.5.0 Beta 2 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Jun 2020
 */

namespace IPS\core\setup\upg_105015;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.5.0 Beta 2 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Update settings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Settings::i()->changeValues( array( 'username_characters' => \IPS\Settings::i()->username_characters ? ( '/^[' . str_replace( '\-', '-', preg_quote( \IPS\Settings::i()->username_characters, '/' ) ) . ']*$/iu' ) : '/^(([\p{L}\p{M}\p{N}_\.\-,]+) ?)+$/u' ) );

		/* Convert existing VSE themes to full custom themes as the VSE isn't compatible with the older version */
		\IPS\Db::i()->update( 'core_themes', array( 'set_by_skin_gen' => 0 ) );

		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}