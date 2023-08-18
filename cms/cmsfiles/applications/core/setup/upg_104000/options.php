<?php
/**
 * @brief		Upgrader: Custom Upgrade Options
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Oct 2018
 */

$options = array();

/* Are we using elasticsearch? Make sure it is 5.6.0 or greater, and warn that it will be disabled if not. */
if( \IPS\Settings::i()->search_method == 'elastic' AND \IPS\Settings::i()->search_elastic_server )
{
	try
	{
		$response = \IPS\Http\Url::external( rtrim( \IPS\Settings::i()->search_elastic_server, '/' ) )->request()->get()->decodeJson();
	}
	catch ( \Exception $e )
	{
		/* If there's an exception, the server may be down temporarily or something - let's not disable in that case */
	}

	if ( isset( $response ) AND ( !isset( $response['version']['number'] ) OR version_compare( $response['version']['number'], \IPS\Content\Search\Elastic\Index::MINIMUM_VERSION, '<' ) ) )
	{
		/* Get the list of admins here to display and then output */
		$options[] = new \IPS\Helpers\Form\Custom( '104000_es_version', null, FALSE, array( 'getHtml' => function( $element ) use ( $response ){
			$minimumVersion	= \IPS\Content\Search\Elastic\Index::MINIMUM_VERSION;
			$yourVersion	= $response['version']['number'];

			return "The new minimum supported version of Elasticsearch is {$minimumVersion}, however your server is currently running {$yourVersion}. By continuing, your search engine will revert back to MySQL searching.";
		}, 'formatValue' => function( $element ){ return true; } ), function( $val ) { return TRUE; }, NULL, NULL, '104000_es_version' );
	}
}
