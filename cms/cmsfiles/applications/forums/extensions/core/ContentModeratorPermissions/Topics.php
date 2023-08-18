<?php
/**
 * @brief		Moderator Permissions: Topics
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		16 Jan 2014
 */

namespace IPS\forums\extensions\core\ContentModeratorPermissions;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Moderator Permissions: Topics
 */
class _Topics
{
	/**
	 * Get Permissions
	 *
	 * @param	array	$toggles	Toggle data
	 * @code
	 	return array(
	 		'key'	=> 'YesNo',	// Can just return a string with type
	 		'key'	=> array(	// Or an array for more options
	 			'YesNo',			// Type
	 			array( ... ),		// Options (as defined by type's class)
	 			'prefix',			// Prefix
	 			'suffix',			// Suffix
	 		),
	 		...
	 	);
	 * @endcode
	 * @return	array
	 */
	public function getPermissions( $toggles )
	{
		$return = array();
		
		if ( \IPS\Db::i()->select( 'COUNT(*)', 'forums_forums', 'can_view_others=0 AND club_id IS NULL' )->first() )
		{
			$return['can_read_all_topics'] = 'YesNo';
		}

		if ( \IPS\Db::i()->select( 'COUNT(*)', 'forums_forums', '(' . \IPS\Db::i()->bitwiseWhere( \IPS\forums\Forum::$bitOptions['forums_bitoptions'], 'bw_enable_answers' ) . ') OR ( ' . \IPS\Db::i()->bitwiseWhere( \IPS\forums\Forum::$bitOptions['forums_bitoptions'], 'bw_enable_answers_moderator' ) . ' )' )->first() )
		{
			$return['can_set_best_answer'] = 'YesNo';
		}
		if ( \IPS\Db::i()->select( 'COUNT(*)', 'forums_topic_mmod' )->first() )
		{
			$return['can_use_saved_actions'] = 'YesNo';
		}
		
		return $return;
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
		if ( $changed === '*' or array_key_exists( 'can_read_all_topics', $changed ) OR array_key_exists( 'forums', $changed ) )
		{
			$deleteFirst = TRUE;
			if ( $changed === '*' or !empty( $changed['can_read_all_topics'] ) )
			{
				$deleteFirst = FALSE;
			}
			
			$this->reindexAuthorOnlyForums( $deleteFirst );
		}
	}
	
	/**
	 * After change
	 *
	 * @param	array	$moderator	The moderator
	 * @return	void
	 */
	public function onDelete( $moderator )
	{
		$this->reindexAuthorOnlyForums( TRUE );
	}
	
	/**
	 * Reindex forums where members can only see their own topics
	 *
	 * @param	bool	$deleteFirst	If TRUE, will delete first
	 * @return	void
	 */
	public function reindexAuthorOnlyForums( $deleteFirst )
	{
		foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'forums_forums', 'can_view_others=0 AND club_id IS NULL' ), 'IPS\forums\Forum' ) as $forum )
		{
			if ( $deleteFirst )
			{
				\IPS\Content\Search\Index::i()->removeClassFromSearchIndex( 'IPS\forums\Topic', $forum->id );
				\IPS\Content\Search\Index::i()->removeClassFromSearchIndex( 'IPS\forums\Topic\Post', $forum->id );
			}
			\IPS\Task::queue( 'core', 'RebuildSearchIndex', array( 'class' => 'IPS\forums\Topic', 'container' => $forum->id ), 5, 'container' );
			\IPS\Task::queue( 'core', 'RebuildSearchIndex', array( 'class' => 'IPS\forums\Topic\Post', 'container' => $forum->id ), 5, 'container' );
		}
	}
}