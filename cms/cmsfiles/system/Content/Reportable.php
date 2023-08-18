<?php
/**
 * @brief		Reportable Trait
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 December 2017
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Reportable Trait
 */
trait Reportable
{
	/**
	 * Cached report data for checking if item has already been reported
	 */
	public $reportData = null;
	
	/**
	 * Something has been moderated
	 *
	 * @param	string		$method		What just happened? A question I ask myself often
	 * @return void
	 */
	public function moderated( $method )
	{
		/* If something has been moderated, we should lock down the index to prevent it from being auto_moderated again */
		try
		{
			$idColumn = static::$databaseColumnId;
			\IPS\core\Reports\Report::loadByClassAndId( \get_class( $this ), $this->$idColumn )->lockAutoModeration();
		}
		catch( \Exception $e ) { }
	}
}