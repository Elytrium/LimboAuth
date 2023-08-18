<?php
/**
 * @brief		Awaiting validation dashboard plugin
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @deprecated	This block was removed but we need to continue shipping the file to overwrite the old one if present
 */

namespace IPS\core\extensions\core\Dashboard;

if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Awaiting validation class
 */
class _AwaitingValidation
{
	/**
	 * Can we view?
	 *
	 * @return bool
	 */
	public function canView()
	{
		return FALSE;
	}

	/**
	 * Block HTML
	 *
	 * @return string
	 */
	public function getBlock()
	{
		return '';
	}
}