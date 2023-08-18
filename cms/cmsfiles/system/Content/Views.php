<?php
/**
 * @brief		Interface for Tracking Views
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		8 Jan 2014
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Interface for Tracking Views
 *
 * @note	Content classes will gain special functionality by implementing this interface
 * @note	Originally we were just checking for a mapped 'views' column, but decided to use an interface for consistency
 * @deprecated	Use ViewUpdates trait instead.
 */
interface Views { }