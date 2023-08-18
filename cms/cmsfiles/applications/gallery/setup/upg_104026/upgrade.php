<?php
/**
 * @brief		4.4.4 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		03 May 2019
 */

namespace IPS\gallery\setup\upg_104026;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.4.4 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Rebuild search index
	 *
	 * @return	void
	 */
	public function finish()
	{
		\IPS\Content\Search\Index::i()->removeApplicationContent( \IPS\Application::load('gallery') );
		\IPS\Task::queue( 'core', 'RebuildSearchIndex', array( 'class' => 'IPS\gallery\Image' ), 5, TRUE );
		\IPS\Task::queue( 'core', 'RebuildSearchIndex', array( 'class' => 'IPS\gallery\Image\Comment' ), 5, TRUE );
		\IPS\Task::queue( 'core', 'RebuildSearchIndex', array( 'class' => 'IPS\gallery\Image\Review' ), 5, TRUE );

		\IPS\Task::queue( 'core', 'RebuildSearchIndex', array( 'class' => 'IPS\gallery\Album\Item' ), 5, TRUE );
		\IPS\Task::queue( 'core', 'RebuildSearchIndex', array( 'class' => 'IPS\gallery\Album\Comment' ), 5, TRUE );
		\IPS\Task::queue( 'core', 'RebuildSearchIndex', array( 'class' => 'IPS\gallery\Album\Review' ), 5, TRUE );
	}
}