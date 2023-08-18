<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		28 Aug 2019
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
class _MoveSingleRecord
{
	/**
	 * @brief Number of content items to rebuild per cycle
	 */
	public $rebuild	= \IPS\REBUILD_SLOW;
	
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$data['done']		= 0;
		$data['lastId']		= 0;
		$recordClass		= '\IPS\cms\Records' . $data['databaseId'];
		$record				= $recordClass::load( $data['recordId'] );
		
		if ( $data['to'] === 'forums' )
		{
			$data['count'] = \IPS\Db::i()->select( 'COUNT(*)', 'cms_database_comments', array( "comment_database_id=? AND comment_record_id=?", $data['databaseId'], $record->primary_id_field ) )->first();
		}
		else
		{
			$data['count'] = \IPS\Db::i()->select( 'COUNT(*)', 'forums_posts', array( "topic_id=? AND new_topic=0", $record->record_topicid ) )->first();
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
		$done = 0;
		$recordClass	= '\IPS\cms\Records' . $data['databaseId'];
		$commentClass	= '\IPS\cms\Records\Comment' . $data['databaseId'];
		$record			= $recordClass::load( $data['recordId'] );
		$map			= array(
			'user'				=> 'author_id',
			'date'				=> 'post_date',
			'ip_address'		=> 'ip_address',
			'post'				=> 'post',
			'approved'			=> 'queued',
			'author'			=> 'author_name',
			'edit_date'			=> 'edit_time',
			'edit_reason'		=> 'post_edit_reason',
			'edit_member_name'	=> 'edit_name',
		);
		
		if ( $data['to'] === 'forums' )
		{
			$it = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'cms_database_comments', array( "comment_id>? AND comment_database_id=? AND comment_record_id=?", $data['lastId'], $data['databaseId'], $data['recordId'] ), 'comment_id ASC', $this->rebuild ), $commentClass );
		}
		else
		{
			$it = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'forums_posts', array( "pid>? AND new_topic=0 AND topic_id=?", $data['lastId'], $record->record_topicid ), 'pid ASC', $this->rebuild ), 'IPS\forums\Topic\Post' );
			$map = \array_flip( $map );
		}
		
		foreach( $it AS $old )
		{
			if ( $data['to'] === 'forums' )
			{
				$new			= new \IPS\forums\Topic\Post;
				$new->topic_id	= $record->record_topicid;
				$newIdColumn	= 'pid';
				$oldIdColumn	= 'id';
			}
			else
			{
				$new			= new $commentClass;
				$new->record_id	= $data['recordId'];
				$newIdColumn	= 'id';
				$oldIdColumn	= 'pid';
			}
			
			foreach( $map AS $k => $v )
			{
				switch( $k )
				{
					case 'approved':
						if ( $old->$k == 1 )
						{
							$new->$v = 0;
						}
						else if ( $old->$k == 0 )
						{
							$new->$v = 1;
						}
						else
						{
							$new->$v = -1;
						}
						break;
					
					case 'queued':
						if ( $old->$k == 0 )
						{
							$new->$v = 1;
						}
						elseif ( $old->$k == 1 )
						{
							$new->$v = 0;
						}
						else
						{
							$new->$v = -1;
						}
						break;
					
					default:
						$new->$v = $old->$k ?? '';
						break;
				}
			}
			
			$new->save();
			
			\IPS\Content\Search\Index::i()->index( $new );
			
			$old->delete();
			
			$data['lastId'] = $old->$oldIdColumn;
			$data['done']++;
			$done++;
		}
		
		$record->syncRecordFromTopic( $record->topic( FALSE ) );
		
		if ( !$done )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		return $done;
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
		$recordClass	= '\IPS\cms\Records' . $data['databaseId'];
		$record			= $recordClass::load( $data['recordId'] );

		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack( 'moving_database_comments_single', FALSE, array( 'sprintf' => array( $record->url(), $record->mapped('title') ) ) ), 'complete' => 100 / $data['count'] * $data['done'] );
	}
}