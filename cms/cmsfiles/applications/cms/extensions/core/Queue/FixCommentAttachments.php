<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		05 Dec 2019
 */

namespace IPS\cms\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _FixCommentAttachments
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$data['count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_attachments_map', \IPS\Db::i()->like( 'location_key', 'cms_Records' ) )->first();

		if( $data['count'] == 0 )
		{
			return null;
		}

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
	public function run( $data, $offset )
	{
		$completed = 0;

		foreach( \IPS\Db::i()->select( '*', 'core_attachments_map', \IPS\Db::i()->like( 'location_key', 'cms_Records' ), 'attachment_id ASC', array( $offset, \IPS\REBUILD_NORMAL ) ) as $map )
		{
			$completed++;

			/* If id3 has "-review" in it, this is a review attachment and we can skip already */
			if( mb_strpos( $map['id3'], '-review' ) !== FALSE )
			{
				continue;
			}

			/* If id3 already has "-comment" in it, this is a known comment attachment (likely submitted after the upgrade) and we can skip already */
			if( mb_strpos( $map['id3'], '-comment' ) !== FALSE )
			{
				continue;
			}

			/* Otherwise, we need to see if the attachment was used in a comment. If so, update the map, otherwise just continue - record maps didn't change. */
			try
			{
				$comment = \IPS\Db::i()->select( 'comment_post', 'cms_database_comments', array( 'comment_database_id=? AND comment_id=?', $map['id3'], $map['id2'] ) )->first();

				/* We are looking for data-fileid="{attach_id}" */
				if( preg_match( "/data\-fileid=['\"]{$map['attachment_id']}[\"']/ims", $comment ) )
				{
					/* We found it - attachment is for the comment so update the map record */
					\IPS\Db::i()->update( 'core_attachments_map', array( 'id3' => $map['id3'] . '-comment' ), array( 'attachment_id=? AND location_key=? AND id1=? AND id2=?', $map['attachment_id'], $map['location_key'], $map['id1'], $map['id2'] ) );
				}
			}
			catch( \UnderflowException $e )
			{
				continue;
			}
		}

		if( !$completed )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		return $offset + $completed;
	}
	
	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array( 'text' => 'Doing something...', 'complete' => 50 )	Text explaining task and percentage complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function getProgress( $data, $offset )
	{
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack( 'rebuilding_cms_comment_attachments' ), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $offset, 2 ) ) : 100 );
	}
}