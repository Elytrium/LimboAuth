<?php
/**
 * @brief		4.5.0 Beta 12 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		06 Aug 2020
 */

namespace IPS\core\setup\upg_105032;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.5.0 Beta 12 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Check if we need to send the notification about marketplace setup
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$sendNotification = FALSE;

		if ( !$sendNotification and array_filter( array_keys( \IPS\Application::applications() ), function( $k ) { return !\in_array( $k, \IPS\IPS::$ipsApps ); } ) )
		{
			$sendNotification = TRUE;
		}
		if ( !$sendNotification and \IPS\Plugin::plugins() )
		{
			$sendNotification = TRUE;
		}
		if ( !$sendNotification and array_filter( \IPS\Theme::themes(), function( $t ) { return $t->isCustomized(); } ) )
		{
			$sendNotification = TRUE;
		}
		if ( !$sendNotification and \IPS\Db::i()->select( 'COUNT(*)', 'core_sys_lang_words', array( 'word_custom IS NOT NULL AND word_export=1' ) )->first() )
		{
			$sendNotification = TRUE;
		}

		if ( $sendNotification )
		{
			\IPS\core\AdminNotification::send( 'core', 'ConfigurationError', 'marketplaceSetup', FALSE, NULL, TRUE );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step1CustomTitle()
	{
		return "Checking Marketplace resources";
	}
}