<?php
/**
 * @brief		ACP Live Search Extension: Members
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Sept 2013
 */

namespace IPS\core\extensions\core\LiveSearch;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Members
 */
class _Members
{
	/**
	 * @brief	Cutoff to start doing LIKE 'string%' instead of LIKE '%string%'
	 */
	protected static $inlineSearchCutoff	= 1000000;

	/**
	 * Check we have access
	 *
	 * @return	bool
	 */
	public function hasAccess()
	{
		/* Check Permissions */
		return \IPS\Member::loggedIn()->hasAcpRestriction( "core","members" );
	}
	
	/**
	 * Get the search results
	 *
	 * @param	string	$searchTerm	Search Term
	 * @return	array 	Array of results
	 */
	public function getResults( $searchTerm )
	{
		/* Check we have access */
		if( !$this->hasAccess() )
		{
			return array();
		}

		/* Init */
		$results = array();
		$searchTerm = mb_strtolower( $searchTerm );
		
		/* Perform the search */
		$members = \IPS\Db::i()->select( "*", 'core_members', \IPS\Db::i()->like( array( 'name', 'email' ), $searchTerm, TRUE, TRUE, static::canPerformInlineSearch() ), NULL, 50 ); # Limit to 50 so it doesn't take too long to run

		/* Format results */
		foreach ( $members as $member )
		{
			$member = \IPS\Member::constructFromData( $member );
			
			$results[] = \IPS\Theme::i()->getTemplate('livesearch')->member( $member );
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
		return \IPS\Dispatcher::i()->application->directory == 'core' and \IPS\Dispatcher::i()->module->key == 'members' and \IPS\Dispatcher::i()->controller != 'groups';
	}

	/**
	 * Determine if it's safe to perform a partial inline search
	 *
	 * @note	If we have more than 1,000,000 member records we will do a LIKE 'string%' search instead of LIKE '%string%'
	 * @return	bool
	 */
	public static function canPerformInlineSearch()
	{
		/* If the data store entry is present, read it first */
		if( isset( \IPS\Data\Store::i()->safeInlineSearch ) )
		{
			/* We are over the threshold, return FALSE now */
			if( \IPS\Data\Store::i()->safeInlineSearch == false )
			{
				return FALSE;
			}
			else
			{
				/* If we haven't checked in 24 hours we should do so again */
				if( \IPS\Data\Store::i()->safeInlineSearch > ( time() - ( 60 * 60 * 24 ) ) )
				{
					return TRUE;
				}
			}
		}

		/* Get our member count */
		$totalMembers = \IPS\Db::i()->select( 'COUNT(*)', 'core_members' )->first();

		/* If we have more members than our cutoff, just set a flag as we don't need to recheck this periodically. The total will never materially dip to where we can start performing inline searches again, and worst case scenario the upgrader/support tool would clear the cache anyways. */
		if( $totalMembers > static::$inlineSearchCutoff )
		{
			\IPS\Data\Store::i()->safeInlineSearch = false;
			return FALSE;
		}
		else
		{
			/* Otherwise we store a timestamp so we can recheck periodically */
			\IPS\Data\Store::i()->safeInlineSearch = time();
			return TRUE;
		}	
	}
}