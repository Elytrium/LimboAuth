<?php
/**
 * @brief		Content Router extension: Clubs
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		26 Apr 2017
 */

namespace IPS\core\extensions\core\ContentRouter;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Content Router extension: Clubs
 */
class _Clubs
{
	/**
	 * @brief	Content Item Classes
	 */
	public $classes = array();
	
	/**
	 * @brief	Can be shown in similar content
	 */
	public $similarContent = FALSE;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Member|IPS\Member\Group|NULL	$memberOrGroup	If checking access, the member/group to check for, or NULL to not check access
	 * @return	void
	 */
	public function __construct( $memberOrGroup = NULL )
	{
		if ( $memberOrGroup === NULL or $memberOrGroup->canAccessModule( \IPS\Application\Module::get( 'core', 'clubs', 'front' ) ) )
		{
			$this->embeddableContent[] = 'IPS\Member\Club';
		}
	}
}