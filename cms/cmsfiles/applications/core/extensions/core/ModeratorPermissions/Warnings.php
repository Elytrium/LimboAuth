<?php
/**
 * @brief		Moderator Permissions: Warnings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		24 Apr 2013
 */

namespace IPS\core\extensions\core\ModeratorPermissions;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Moderator Permissions: Warnings
 */
class _Warnings
{
	/**
	 * Get Permissions
	 *
	 * @code
	 	return array(
	 		'key'	=> 'YesNo',	// Can just return a string with type
	 		'key'	=> array(	// Or an array for more options
	 			'YesNo'				// Type
	 			array( ... )		// Options (as defined by type's class)
	 			'prefix',			// Prefix
	 			'suffix'			// Suffix
	 		),
	 		...
	 	);
	 * @endcode
	 * @return	array
	 */
	public function getPermissions()
	{
		return array(
			'mod_see_warn'				=> array( 'YesNo', array( 'togglesOn' => array( 'mod_can_warn', 'mod_revoke_warn' ) ) ),
			'mod_can_warn'				=> array( 'YesNo', array( 'togglesOn' => array( 'warning_custom_noaction', 'warnings_enable_other', 'warn_mod_day' ) ) ),
			'mod_revoke_warn'			=> 'YesNo',
			'warning_custom_noaction'	=> 'YesNo',
			'warnings_enable_other'		=> 'YesNo',
			'warn_mod_day'				=> array( 'Number', array(), NULL, \IPS\Member::loggedIn()->language()->addToStack('per_day') )
		);
	}

	/**
	 * Pre-save
	 *
	 * @note	This can be used to adjust the values submitted on the form prior to saving
	 * @param	array	$values		The submitted form values
	 * @return	void
	 */
	public function preSave( &$values )
	{
		if( $values['mod_use_restrictions'] != 'no' )
		{
			if( !$values['mod_see_warn'] )
			{
				$values['mod_can_warn']		= FALSE;
				$values['mod_revoke_warn']	= FALSE;
			}
		}
	}

	/**
	 * After change
	 *
	 * @param	array	$moderator	The moderator
	 * @param	array	$changed	Values that were changed
	 * @return	void
	 */
	public function onChange( $moderator, $changed )
	{
		
	}
	
	/**
	 * After delete
	 *
	 * @param	array	$moderator	The moderator
	 * @return	void
	 */
	public function onDelete( $moderator )
	{
		
	}
}