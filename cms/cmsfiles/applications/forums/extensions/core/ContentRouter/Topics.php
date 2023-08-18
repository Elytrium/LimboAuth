<?php
/**
 * @brief		Content Router extension: Topics
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		15 Jan 2014
 */

namespace IPS\forums\extensions\core\ContentRouter;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Content Router extension: Topics
 */
class _Topics
{	
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
	 * @param	\IPS\Member|IPS\Member\Group|NULL	$member		If checking access, the member/group to check for, or NULL to not check access
	 * @return	void
	 */
	public function __construct( $member = NULL )
	{
		if ( $member === NULL or $member->canAccessModule( \IPS\Application\Module::get( 'forums', 'forums', 'front' ) ) )
		{
			$this->classes[] = 'IPS\forums\Topic';
		}
	}
}