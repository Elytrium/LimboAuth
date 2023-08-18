<?php
/**
 * @brief		Content Router extension: Blog
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		04 Mar 2014
 */

namespace IPS\blog\extensions\core\ContentRouter;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Content Router extension: Entries
 */
class _Blog
{	
	/**
	 * @brief	Owned Node Classes
	 */
	public $ownedNodes = array( 'IPS\blog\Blog' );
	
	/**
	 * @brief	Content Item Classes
	 */
	public $classes = array();
	
	/**
	 * @brief	Can be shown in similar content
	 */
	public $similarContent = TRUE;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Member|\IPS\Member\Group|NULL	$member		If checking access, the member/group to check for, or NULL to not check access
	 * @return	void
	 */
	public function __construct( $member = NULL )
	{
		if ( $member === NULL or $member->canAccessModule( \IPS\Application\Module::get( 'blog', 'blogs', 'front' ) ) )
		{
			$this->classes[] = 'IPS\blog\Entry';
		}
	}
}