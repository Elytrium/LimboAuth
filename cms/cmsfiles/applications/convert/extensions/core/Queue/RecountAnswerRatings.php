<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	convert
 * @since		19 Nov 2016
 */

namespace IPS\convert\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _RecountAnswerRatings
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data	Data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$data['count'] = \IPS\Db::i()->select( 'count(pid)', 'forums_posts' )->first();

		if( $data['count'] == 0 )
		{
			return NULL;
		}

		$data['completed'] = 0;

		return $data;
	}

	/**
	 * Run Background Task
	 *
	 * @param	mixed						$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int							$offset	Offset
	 * @return	int							New offset
	 * @throws	\IPS\Task\Queue\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( &$data, $offset )
	{
		if ( !class_exists( 'IPS\forums\Topic\Post' ) OR !\IPS\Application::appisEnabled( 'forums' ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$last = NULL;

		/* If the app doesn't exist, stop now */
		try
		{
			$app = \IPS\convert\App::load( $data['app'] );
		}
		catch( \OutOfRangeException $e )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		/* Loop over distinct post IDs in forums_answer_ratings - while using the DISTINCT flag can be slower, there generally aren't many of these */
		foreach( \IPS\Db::i()->select( 'post', 'forums_answer_ratings', array( "post>?", $offset ), "post ASC", array( 0, \IPS\REBUILD_NORMAL ), NULL, NULL, \IPS\Db::SELECT_DISTINCT ) AS $pid )
		{
			$last = $pid;
			$data['completed']++;

			/* Is this converted content? */
			try
			{
				/* Just checking, we don't actually need anything */
				$app->checkLink( $pid, 'forums_posts' );
			}
			catch( \OutOfRangeException $e )
			{
				continue;
			}

			/* Rebuild count */
			\IPS\Db::i()->update( 'forums_posts', array(
				'post_field_int'	=> (int) \IPS\Db::i()->select( 'SUM(rating)', 'forums_answer_ratings', array( 'post=?', $pid ) )->first()
			), array( 'pid=?', $pid ) );
		}

		if( $last === NULL )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		return $last;
	}

	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array	Text explaining task and percentage complete
	 */
	public function getProgress( $data, $offset )
	{
		return array( 'text' =>  \IPS\Member::loggedIn()->language()->addToStack( 'queue_recounting_answer_ratings' ), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $data['completed'], 2 ) ) : 100 );
	}
}