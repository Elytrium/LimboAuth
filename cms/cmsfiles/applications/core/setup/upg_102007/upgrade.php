<?php
/**
 * @brief		4.2.2 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		02 Aug 2017
 */

namespace IPS\core\setup\upg_102007;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.2.2 Upgrade Code
 */
class _Upgrade
{
	/**
	 * If (our) chat is installed, we need to uninstall it completely
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$applications = \IPS\Application::applications();

		if( isset( $applications['chat'] ) )
		{
			try
			{
				/* Delete the app */
				$applications['chat']->delete();
			}
			catch( \Exception $e )
			{
				/* It may be a legacy record, in which case we just want to delete it */
				\IPS\Db::i()->delete( 'core_applications', array( 'app_directory=?', 'chat' ) );
			}

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			/* Clear datastore */
			\IPS\Data\Store::i()->clearAll();
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
		return "Removing Chat";
	}
}