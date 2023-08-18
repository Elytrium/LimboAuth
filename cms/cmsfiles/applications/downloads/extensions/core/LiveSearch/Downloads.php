<?php
/**
 * @brief		ACP Live Search Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		07 Feb 2014
 */

namespace IPS\downloads\extensions\core\LiveSearch;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Live Search Extension
 */
class _downloads
{
	/**
	 * Check we have access
	 *
	 * @return	bool
	 */
	public function hasAccess()
	{
		/* Check Permissions */
		return \IPS\Member::loggedIn()->hasAcpRestriction( 'downloads', 'downloads', 'categories_manage' );
	}
	
	/**
	 * Get the search results
	 *
	 * @param	string	$searchTerm	Search Term
	 * @return	array 	Array of results
	 */
	public function getResults( $searchTerm )
	{
		if( !$this->hasAccess() )
		{
			return array();
		}

		/* Init */
		$results = array();
		$searchTerm = mb_strtolower( $searchTerm );
		
		/* Perform the search */
		$categories = \IPS\Db::i()->select(
						"*",
						'downloads_categories',
						array( "cclub_id IS NULL AND word_custom LIKE CONCAT( '%', ?, '%' ) AND lang_id=?", $searchTerm, \IPS\Member::loggedIn()->language()->id ),
						NULL,
						NULL
				)->join(
						'core_sys_lang_words',
						"word_key=CONCAT( 'downloads_category_', cid )"
					);
		
		/* Format results */
		foreach ( $categories as $category )
		{
			$category = \IPS\downloads\Category::constructFromData( $category );
			
			$results[] = \IPS\Theme::i()->getTemplate( 'livesearch', 'downloads', 'admin' )->category( $category );
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
		return \IPS\Dispatcher::i()->application->directory == 'downloads';
	}
}