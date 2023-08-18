<?php
/**
 * @brief		ACP Live Search Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 Apr 2017
 */

namespace IPS\core\extensions\core\LiveSearch;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Live Search Extension
 */
class _Clubs
{
	/**
	 * Check we have access
	 *
	 * @return	bool
	 */
	public function hasAccess()
	{
		/* Check Permissions */
		return \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'clubs', 'clubs_manage' );
	}

	/**
	 * Get the search results
	 *
	 * @param	string	$searchTerm	Search Term
	 * @return	array 	Array of results
	 */
	public function getResults( $searchTerm )
	{
		/* Init */
		$results = array();
		$searchTerm = mb_strtolower( $searchTerm );

		/* Start with categories */
		if( $this->hasAccess() )
		{
			/* Perform the search */
			$clubs = \IPS\Db::i()->select(
							"*",
							'core_clubs',
							array( "name LIKE CONCAT( '%', ?, '%' )", $searchTerm ),
							NULL,
							NULL
						);
			
			/* Format results */
			foreach ( $clubs as $club )
			{
				$club = \IPS\Member\Club::constructFromData( $club );
				
				$results[] = \IPS\Theme::i()->getTemplate( 'livesearch', 'core', 'admin' )->club( $club );
			}
		}

		return $results;
	}
	
	/**
	 * Is default for current page?
	 *
	 * @return	bool
	 */
	public function isDefault()
	{
		return \IPS\Dispatcher::i()->application->directory == 'core' and \IPS\Dispatcher::i()->module->key == 'clubs';
	}
}