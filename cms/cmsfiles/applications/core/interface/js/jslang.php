<?php
/**
 * @brief		Return JS language strings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

define('REPORT_EXCEPTIONS', TRUE);
require_once '../../../../init.php';

\IPS\Output::i()->pageCaching = FALSE;

$langId	= \intval( \IPS\Request::i()->langId );
$_lang	= array();

foreach ( \IPS\Db::i()->select( '*', 'core_sys_lang_words', array( 'lang_id=? AND word_js=?', $langId, TRUE ) ) as $row )
{
	$_lang[ $row['word_key'] ] = $row['word_custom'] ?: $row['word_default'];
}

if ( \IPS\IN_DEV )
{
	foreach ( \IPS\Application::applications() as $app )
	{
		if( \IPS\Application::appIsEnabled( $app->directory ) )
		{
			if ( file_exists( \IPS\ROOT_PATH . "/applications/{$app->directory}/dev/jslang.php" ) )
			{
				require \IPS\ROOT_PATH . "/applications/{$app->directory}/dev/jslang.php";
				$_lang = array_merge( $_lang, $lang );
			}
		}
	}
	foreach ( \IPS\Plugin::plugins() as $plugin )
	{
		if ( file_exists( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/jslang.php" ) )
		{
			require \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/jslang.php";
			$_lang = array_merge( $_lang, $lang );
		}
	}
}

$cacheHeaders	= ( \IPS\IN_DEV !== true AND \IPS\Theme::designersModeEnabled() !== true ) ? \IPS\Output::getCacheHeaders( time(), 360 ) : array();

/* Display */
\IPS\Output::i()->sendOutput( 'ips.setString( ' . json_encode( $_lang ) . ')', 200, 'text/javascript', $cacheHeaders );