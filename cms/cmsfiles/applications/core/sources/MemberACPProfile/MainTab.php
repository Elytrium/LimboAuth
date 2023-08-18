<?php
/**
 * @brief		ACP Member Profile: Main Tab
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Nov 2017
 */

namespace IPS\core\MemberACPProfile;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: Main Tab
 */
abstract class _MainTab
{
	/**
	 * @brief	Member
	 */
	protected $member;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	void
	 */
	public function __construct( \IPS\Member $member )
	{
		$this->member = $member;
	}
	
	/**
	 * Can View this Tab
	 *
	 * @return	bool
	 */
	public function canView()
	{
		/* Extensions can override */
		return TRUE;
	}
	
	/**
	 * Get Title
	 *
	 * @return	string
	 */
	public static function title()
	{
		$class = \get_called_class();
		$exploded = explode( '\\', $class );
		return \IPS\Member::loggedIn()->language()->addToStack( 'memberACPProfileTitle_' . $exploded[1] . '_' . $exploded[5] );
	}
	
	/**
	 * Get left-column blocks
	 *
	 * @return	array
	 */
	public function leftColumnBlocks()
	{
		return array();
	}
	
	/**
	 * Get main-column blocks
	 *
	 * @return	array
	 */
	public function mainColumnBlocks()
	{
		return array();
	}
		
	/**
	 * Get Output
	 *
	 * @return	string
	 */
	public function output()
	{
		$leftColumnBlocks = array();
		foreach ( $this->leftColumnBlocks() as $class )
		{
			$leftColumnBlocks[] = new $class( $this->member );
		}
		
		$mainColumnBlocks = array();
		foreach ( $this->mainColumnBlocks() as $class )
		{
			$mainColumnBlocks[] = new $class( $this->member );
		}
				
		return \IPS\Theme::i()->getTemplate('memberprofile')->tabTemplate( $leftColumnBlocks, $mainColumnBlocks );
	}
}