<?php
/**
 * @brief		Base API Graph Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		1 Sept 2022
 */

namespace IPS\Api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Base API Controller
 */
class _GraphQL
{

	public function __construct()
	{
		/* This space intentionally left blank */
	}

	/**
	 * Execute
	 *
	 * @param	string				$query		The query to execute
	 * @param	array				$variables	Variables to include in query
	 * @param \IPS\Member|NULL $member Member to check or NULL for currently logged in member.
	 * @return 	array 				GraphQL response
	 */
	public static function execute( string $query, array $variables = [], ?\IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		/* Register our GraphQL library */
		\IPS\IPS::$PSR0Namespaces['GraphQL'] = \IPS\ROOT_PATH . "/system/3rd_party/graphql-php";

		/* Execute! */
		$result = \GraphQL\GraphQL::executeQuery(
			new \GraphQL\Type\Schema([
				'query' => \IPS\Api\GraphQL\TypeRegistry::query(),
				'mutation' => \IPS\Api\GraphQL\TypeRegistry::mutation()
			]),
			$query,
			NULL, // $rootValue
			[
				'member'	=> $member
			], // $context
			$variables
		);

		/* Convert result into JSON and send */
		$output = $result->toArray( ( \IPS\IN_DEV OR \IPS\DEBUG_GRAPHQL ) ? \GraphQL\Error\DebugFlag::INCLUDE_DEBUG_MESSAGE | \GraphQL\Error\DebugFlag::INCLUDE_TRACE : false );
		return $output;
	}
}