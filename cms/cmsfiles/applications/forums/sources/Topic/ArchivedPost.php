<?php
/**
 * @brief		Archived Post Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		24 Jan 2014
 */

namespace IPS\forums\Topic;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Post Model
 */
class _ArchivedPost extends Post
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	Title
	 */
	public static $archiveTitle = 'archivedpost';
	
	/**
	 * @brief	[ActiveRecord] Database Connection
	 * @return	\IPS\Db
	 */
	public static function db()
	{
		/* Connect to the remote DB if needed */
		return ( !\IPS\Settings::i()->archive_remote_sql_host ) ? \IPS\Db::i() : \IPS\Db::i( 'archive', array(
			'sql_host'		=> \IPS\Settings::i()->archive_remote_sql_host,
			'sql_user'		=> \IPS\Settings::i()->archive_remote_sql_user,
			'sql_pass'		=> \IPS\Settings::i()->archive_remote_sql_pass,
			'sql_database'	=> \IPS\Settings::i()->archive_remote_sql_database,
			'sql_port'		=> \IPS\Settings::i()->archive_sql_port,
			'sql_socket'	=> \IPS\Settings::i()->archive_sql_socket,
			'sql_tbl_prefix'=> \IPS\Settings::i()->archive_sql_tbl_prefix,
			'sql_utf8mb4'	=> isset( \IPS\Settings::i()->sql_utf8mb4 ) ? \IPS\Settings::i()->sql_utf8mb4 : FALSE
		) );
	}
		
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'forums_archive_posts';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'archive_';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'item'				=> 'topic_id',
		'author'			=> 'author_id',
		'author_name'		=> 'author_name',
		'content'			=> 'content',
		'date'				=> 'content_date',
		'ip_address'		=> 'ip_address',
		'edit_time'			=> 'edit_time',
		'edit_show'			=> 'append_edit',
		'edit_member_name'	=> 'edit_name',
		'edit_reason'		=> 'post_edit_reason',
		'hidden'			=> 'queued',
		'first'				=> 'is_first'
	);

	/**
	 * @brief	Bitwise values for post_bwoptions field
	 */
	public static $bitOptions = array(
		'post_bwoptions' => array(
			'post_bwoptions' => array(
				'best_answer'	=> 1
			)
		)
	);

	/**
	 * @brief	Database Column ID
	 */
	public static $databaseColumnId = 'id';

	/**
	 * Post count for member
	 *
	 * @param	\IPS\Member	$member								The member
	 * @param	bool		$includeNonPostCountIncreasing		If FALSE, will skip any posts which would not cause the user's post count to increase
	 * @param	bool		$includeHiddenAndPendingApproval	If FALSE, will skip any hidden posts, or posts pending approval
	 * @return	int
	 */
	public static function memberPostCount( \IPS\Member $member, bool $includeNonPostCountIncreasing = FALSE, bool $includeHiddenAndPendingApproval = TRUE )
	{
		$where = [];
		$where[] = [ 'archive_author_id=?', $member->member_id ];

		try
		{
			if ( !$includeNonPostCountIncreasing )
			{
				$where[] = [ static::db()->in( 'archive_forum_id', iterator_to_array( \IPS\Db::i()->select( 'id', 'forums_forums', 'inc_postcount=1' ) ) ) ];
			}
			if ( !$includeHiddenAndPendingApproval )
			{
				$where[] = [ 'archive_queued=0' ];
			}

			return static::db()->select( 'COUNT(*)', static::$databaseTable, $where )->first();
		}
		catch ( \IPS\Db\Exception $e )
		{
			return 0;
		}

	}

	/**
	 * Joins (when loading comments)
	 *
	 * @param	\IPS\Content\Item	$item			The item
	 * @return	array
	 */
	public static function joins( \IPS\Content\Item $item )
	{
		$return = parent::joins( $item );
		
		unset( $return['author'] );
		unset( $return['author_pfields'] );
		
		return $return;
	}
	
	/**
	 * Can edit?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canEdit( $member=NULL )
	{
		return FALSE;
	}
	
	/**
	 * Can hide?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canHide( $member=NULL )
	{
		return FALSE;
	}
	
	/**
	 * Can unhide?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 */
	public function canUnhide( $member=NULL )
	{
		return FALSE;
	}
	
	/**
	 * Can delete?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canDelete( $member=NULL )
	{
		return FALSE;
	}
	
	/**
	 * Can split?
	 *
	 * @param	\IPS\Member|NULL	$member The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canSplit( $member=NULL )
	{
		return FALSE;
	}
	
	/**
	 * Can react?
	 *
	 * @note	This method is also ran to check if a member can "unrep"
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canReact( \IPS\Member $member = NULL )
	{
		return FALSE;
	}
}
