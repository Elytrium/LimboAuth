<?php
/**
 * @brief		4.3.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Converter
 * @since		16 Oct 2017
 */

namespace IPS\convert\setup\upg_103000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.3.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Install new login handler
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Install new login handler */
		try
		{
			\IPS\Db::i()->select( '*', 'core_login_methods', array( "login_classname=?", 'IPS\\convert\\Login' ) )->first();
		}
		catch( \UnderflowException $e )
		{
			$position = \IPS\Db::i()->select( 'MAX(login_order)', 'core_login_methods' )->first();

			$handler = new \IPS\convert\Login;
			$handler->classname = 'IPS\\convert\\Login';
			$handler->order = $position + 1;
			$handler->acp = TRUE;
			$handler->settings = array( 'auth_types' => \IPS\Login::AUTH_TYPE_EMAIL );
			$handler->enabled = TRUE;
			$handler->register = FALSE;
			$handler->save();

			\IPS\Lang::saveCustom( 'core', "login_method_{$handler->id}", 'Converter' );
		}

		/* Remove legacy login handler */
		\IPS\Db::i()->delete( 'core_login_handlers', array( 'login_key=?', 'Convert' ) );

		return TRUE;
	}
}