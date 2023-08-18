<?php
/**
 * @brief		Read/Unread Tracking Interface for Content Models
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		8 Jul 2013
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Read/Unread Tracking Interface for Content Models
 *
 * @note	Content classes will gain special functionality by implementing this interface
 */
interface ReadMarkers
{
	/**
	 * The maximum number of IDs to store in core_item_markers.item_read_array
	 *
	 * As a JSON-encoded array of ID => Timestamp values, the storage required
	 * is (14+l)n + 1 bytes, where l is the length in bytes of any given ID and n is the number of IDs.
	 * We store the value in a MEDIUMTEXT field, so the maximum length is 16 megabytes. Assuming
	 * l is always 5 (NB: but it isn't), this means this constant could be increased to a theoretic
	 * maximum of 883,011.
	 */
	const STORAGE_CUTOFF = 100000;
}