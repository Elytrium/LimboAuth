<?php
/**
 * @brief		Abstract Mobile Navigation Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Core
 * @since		12 Jun 2019
 */

namespace IPS\core\MobileNavigation;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Mobile Navigation Extension: Custom Item
 */
abstract class _MobileNavigationAbstract
{
	/**
	 * Allow multiple instances?
	 *
	 * @return	bool
	 */
	public static function allowMultiple()
	{
		return FALSE;
	}
	
	/**
	 * Get configuration fields
	 *
	 * @param	array	$existingConfiguration	The existing configuration, if editing an existing item
	 * @param	int		$id						The ID number of the existing item, if editing
	 * @return	array
	 */
	public static function configuration( $existingConfiguration, $id = NULL )
	{
		return array();
	}
	
	/**
	 * Parse configuration fields
	 *
	 * @param	array	$configuration	The values received from the form
	 * @param	int		$id				The ID number of the existing item, if editing
	 * @return	array
	 */
	public static function parseConfiguration( $configuration, $id )
	{
		return $configuration;
	}
	
	/**
	 * @brief	The configuration
	 */
	protected $configuration;
	
	/**
	 * @brief	The ID number
	 */
	public $id;
	
	/**
	 * @brief	The permissions
	 */
	public $permissions;
	
	/**
	 * Constructor
	 *
	 * @param	array	$configuration	The configuration
	 * @param	int		$id				The ID number
	 * @param	string	$permissions	The permissions (* or comma-delimited list of groups)
	 * @return	void
	 */
	public function __construct( $configuration, $id, $permissions )
	{
		$this->configuration = $configuration;
		$this->id = $id;
		$this->permissions = $permissions;
	}
	
	/**
	 * Permissions can be inherited?
	 *
	 * @return	bool
	 */
	public static function permissionsCanInherit()
	{
		return TRUE;
	}
	
	/**
	 * Can this item be used at all?
	 * For example, if this will link to a particular feature which has been diabled, it should
	 * not be available, even if the user has permission
	 *
	 * @return	bool
	 */
	public static function isEnabled()
	{
		return TRUE;
	}
	
	/**
	 * Can the currently logged in user access the content this item links to?
	 *
	 * @return	bool
	 */
	public function canAccessContent()
	{
		return TRUE;
	}
	
	/**
	 * Can the currently logged in user see this menu item?
	 *
	 * @return	bool
	 */
	public function canView()
	{
		if ( static::isEnabled() )
		{
			if ( $this->permissions === NULL ) // NULL indicates "Show this item to users who can access its content."
			{
				return $this->canAccessContent();
			}
			else
			{
				return $this->permissions == '*' ? TRUE : \IPS\Member::loggedIn()->inGroup( explode( ',', $this->permissions ) );
			}
		}
		return FALSE;
	}
		
	/**
	 * Get Title
	 *
	 * @return	string
	 */
	abstract public function title();	
	
	/**
	 * Get Link
	 *
	 * @return	\IPS\Http\Url
	 */
	abstract public function link();

	/**
	 * Get icon
	 *
	 * @return	string|null
	 */
	public function icon()
	{
		return NULL;
	}
}