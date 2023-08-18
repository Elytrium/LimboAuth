<?php
/**
 * @brief		Front Navigation Extension: Menu Button
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
 * Front Navigation Extension: Menu Button
 */
class _MenuButton
{
	/**
	 * @brief	The language string for the title
	 */
	protected $title;
	
	/**
	 * @brief	The target URL
	 */
	protected $link;
	
	/**
	 * Constructor
	 *
	 * @param	string			$title		The language string for the title
	 * @param	\IPS\Http\Url	$link		The target URL
	 * @return	void
	 */
	public function __construct( $title, \IPS\Http\Url $link )
	{
		$this->title = $title;
		$this->link = $link;
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
	 * Get Link
	 *
	 * @return	\IPS\Http\Url
	 */
	public function link()
	{
		return $this->link;
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