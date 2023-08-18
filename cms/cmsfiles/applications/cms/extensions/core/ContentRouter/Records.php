<?php
/**
 * @brief		Content Router extension: Records
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Board
 * @since		17 Apr 2014
 */

namespace IPS\cms\extensions\core\ContentRouter;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Content Router extension: Records
 */
class _Records
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
		try
		{
			foreach ( \IPS\cms\Databases::databases() as $id => $database )
			{
				if( $database->page_id )
				{
					if ( !$member or $database->can( 'view', $member ) )
					{
						$this->classes[] = 'IPS\cms\Records' . $id;
					}
				}
			}
		}
		catch ( \Exception $e ) {} // If you have not upgraded pages but it is installed, this throws an error
	}
}