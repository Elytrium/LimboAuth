<?php
/**
 * @brief		Content Router extension: Status
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Core
 * @since		24 Feb 2014
 */

namespace IPS\core\extensions\core\ContentRouter;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Content Router extension: Statuses
 */
class _Status
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
	 * @param	\IPS\Member|IPS\Member\Group|NULL	$member		If checking access, the member/group to check for, or NULL to not check access
	 * @return	void
	 */
	public function __construct( $member = NULL )
	{
		if ( \IPS\Settings::i()->profile_comments and ( $member === NULL or ( $member->canAccessModule( \IPS\Application\Module::get( 'core', 'members', 'front' ) ) AND $member->canAccessModule( \IPS\Application\Module::get( 'core', 'status', 'front' ) ) ) ) )
		{
			$this->classes[] = 'IPS\core\Statuses\Status';
		}
	}

	/**
	 * Use a custom table helper when building content item tables
	 *
	 * @param	string			$className	The content item class
	 * @param	\IPS\Http\Url	$url		The URL to use for the table
	 * @param	array			$where		Custom where clause to pass to the table helper
	 * @return	\IPS\Helpers\Table|void		Custom table helper class to use
	 */
	public function customTableHelper( $className, $url, $where=array() )
	{
		if( !\in_array( $className, $this->classes ) AND $className != 'IPS\core\Statuses\Status' )
		{
			return new \IPS\Helpers\Table\Content( $className, $url, $where );
		}

		$table = new \IPS\Helpers\Table\Content( $className, $url, $where );
		$table->classes[]	= "ipsPad";
		$table->classes[]	= "cStatusUpdates";

		return $table;
	}
}