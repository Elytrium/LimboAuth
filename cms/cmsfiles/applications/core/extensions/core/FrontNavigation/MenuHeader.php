<?php
/**
 * @brief		Front Navigation Extension: Menu Header
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Core
 * @since		22 Jul 2015
 */

namespace IPS\core\extensions\core\FrontNavigation;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Front Navigation Extension: Menu Header
 */
class _MenuHeader
{
	/**
	 * @brief	The language string for the title
	 */
	protected $title;
	
	/**
	 * Constructor
	 *
	 * @param	string	$title		The language string for the title
	 * @return	void
	 */
	public function __construct( $title )
	{
		$this->title = $title;
	}
		
	/**
	 * Can access?
	 *
	 * @return	bool
	 */
	public function canView()
	{
		return TRUE;
	}
	
	/**
	 * Get Title
	 *
	 * @return	string
	 */
	public function title()
	{
		return \IPS\Member::loggedIn()->language()->addToStack( $this->title );
	}
		
	/**
	 * Children
	 *
	 * @param	bool	$noStore	If true, will skip datastore and get from DB (used for ACP preview)
	 * @return	array
	 */
	public function children( $noStore=FALSE )
	{
		return NULL;
	}
}