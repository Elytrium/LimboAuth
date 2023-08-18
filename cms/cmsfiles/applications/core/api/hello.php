<?php
/**
 * @brief		Hello API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Dec 2015
 */

namespace IPS\core\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Hello API
 */
class _hello extends \IPS\Api\Controller
{
	/**
	 * GET /core/hello
	 * Get basic information about the community.
	 *
	 * @return	array
	 * @apiresponse	string	communityName	The name of the community
	 * @apiresponse	string	communityUrl	The community URL
	 * @apiresponse	string	ipsVersion		The Invision Community version number
	 * @apiresponse	array	applications	The installed IPS Applications
	 */
	public function GETindex()
	{
		return new \IPS\Api\Response( 200, array(
			'communityName'	=> \IPS\Settings::i()->board_name,
			'communityUrl'	=> \IPS\Settings::i()->base_url,
			'ipsVersion'	=> \IPS\Application::load('core')->version,
			'ipsApplications' => array_filter( array_keys( \IPS\Application::applications() ), function( $k ) { return \in_array( $k, \IPS\IPS::$ipsApps ); }  )
			) );
	}
}