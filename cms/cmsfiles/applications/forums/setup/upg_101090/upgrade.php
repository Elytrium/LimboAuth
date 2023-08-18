<?php
/**
 * @brief		4.1.19 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		27 Jan 2017
 */

namespace IPS\forums\setup\upg_101090;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.19 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Add index to forums_archive_posts (taking into account it may be in a remote database)
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* If we already have an index on the ip address AND the queued column, we can just move along. */
		if( \IPS\forums\Topic\ArchivedPost::db()->checkForIndex( 'forums_archive_posts', 'archive_ip_address' ) AND 
			\IPS\forums\Topic\ArchivedPost::db()->checkForIndex( 'forums_archive_posts', 'archive_queued' ) )
		{
			return TRUE;
		}

		/* Get the query that needs to be ran */
		$missingIndexes = array();

		if( !\IPS\forums\Topic\ArchivedPost::db()->checkForIndex( 'forums_archive_posts', 'archive_ip_address' ) )
		{
			$missingIndexes[] = \IPS\forums\Topic\ArchivedPost::db()->buildIndex( 'forums_archive_posts', array( 'type' => 'key', 'name' => 'archive_ip_address', 'columns' => array( 'archive_ip_address' ) ) );
		}

		if( !\IPS\forums\Topic\ArchivedPost::db()->checkForIndex( 'forums_archive_posts', 'archive_queued' ) )
		{
			$missingIndexes[] = \IPS\forums\Topic\ArchivedPost::db()->buildIndex( 'forums_archive_posts', array( 'type' => 'key', 'name' => 'archive_queued', 'columns' => array( 'archive_queued', 'archive_content_date' ) ) );
		}

		$query = "ALTER TABLE `" . \IPS\forums\Topic\ArchivedPost::db()->prefix . \IPS\forums\Topic\ArchivedPost::db()->escape_string( 'forums_archive_posts' ) . "` " . implode( ', ', $missingIndexes );

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'forums_archive_posts',
			'query' => $query, 
			'db'	=> \IPS\forums\Topic\ArchivedPost::db()
		) ) );

		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'forums', 'extra' => array( '_upgradeStep' => 2, '_upgradeData' => 0 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Optimizing archived posts";
	}
}