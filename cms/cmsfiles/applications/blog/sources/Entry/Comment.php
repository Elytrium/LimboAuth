<?php
/**
 * @brief		Blog Entry Comment Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		3 Mar 2013
 */

namespace IPS\blog\Entry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Blog Entry Comment Model
 */
class _Comment extends \IPS\Content\Comment implements \IPS\Content\EditHistory, \IPS\Content\Hideable, \IPS\Content\Searchable, \IPS\Content\Embeddable, \IPS\Content\Anonymous
{
	use \IPS\Content\Reactable, \IPS\Content\Reportable;
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
		
	/**
	 * @brief	[Content\Comment]	Item Class
	 */
	public static $itemClass = 'IPS\blog\Entry';
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'blog_comments';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'comment_';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
			'item'				=> 'entry_id',
			'author'			=> 'member_id',
			'author_name'		=> 'member_name',
			'content'			=> 'text',
			'date'				=> 'date',
			'ip_address'		=> 'ip_address',
			'edit_time'			=> 'edit_time',
			'edit_member_name'	=> 'edit_member_name',
			'edit_show'			=> 'edit_show',
			'approved'			=> 'approved',
			'is_anon'			=> 'is_anon'
	);
	
	/**
	 * @brief	Application
	*/
	public static $application = 'blog';
	
	/**
	 * @brief	Title
	 */
	public static $title = 'blog_entry_comment';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'file-text';
	
	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'blog-entries';
	
	/**
	 * Get items with permission check
	 *
	 * @param	array		$where				Where clause
	 * @param	string		$order				MySQL ORDER BY clause (NULL to order by date)
	 * @param	int|array	$limit				Limit clause
	 * @param	string		$permissionKey		A key which has a value in the permission map (either of the container or of this class) matching a column ID in core_permission_index
	 * @param	mixed		$includeHiddenItems	Include hidden comments? NULL to detect if currently logged in member has permission, -1 to return public content only, TRUE to return unapproved content and FALSE to only return unapproved content the viewing member submitted
	 * @param	int			$queryFlags			Select bitwise flags
	 * @param	\IPS\Member	$member				The member (NULL to use currently logged in member)
	 * @param	bool		$joinContainer		If true, will join container data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinComments		If true, will join comment data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinReviews		If true, will join review data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$countOnly				If true will return the count
	 * @param	array|null	$joins					Additional arbitrary joins for the query
	 * @return	array|NULL|\IPS\Content\Comment		If $limit is 1, will return \IPS\Content\Comment or NULL for no results. For any other number, will return an array.
	 */
	public static function getItemsWithPermission( $where=array(), $order=NULL, $limit=10, $permissionKey='read', $includeHiddenItems=\IPS\Content\Hideable::FILTER_AUTOMATIC, $queryFlags=0, \IPS\Member $member=NULL, $joinContainer=FALSE, $joinComments=FALSE, $joinReviews=FALSE, $countOnly=FALSE, $joins=NULL )
	{
		if ( \in_array( $permissionKey, array( 'view', 'read' ) ) )
		{
			$joinContainer = TRUE;
			$member = $member ?: \IPS\Member::loggedIn();
			if ( $member->member_id )
			{
				$where[] = array( '( blog_blogs.blog_social_group IS NULL OR blog_blogs.blog_member_id=' . $member->member_id . ' OR ( ' . \IPS\Content::socialGroupGetItemsWithPermissionWhere( 'blog_blogs.blog_social_group', $member ) . ' ) )' );
			}
			else
			{
				$where[] = array( "(" . \IPS\Content::socialGroupGetItemsWithPermissionWhere( 'blog_blogs.blog_social_group', $member ) . " OR blog_blogs.blog_social_group IS NULL )" );
			}
			
			if ( \IPS\Settings::i()->clubs )
			{
				$joins[] = array( 'from' => 'core_clubs', 'where' => 'core_clubs.id=blog_blogs.blog_club_id' );
				if ( $member->member_id )
				{
					if ( !$member->modPermission( 'can_access_all_clubs' ) )
					{
						$where[] = array('( blog_blogs.blog_club_id IS NULL OR ' . \IPS\Db::i()->in( 'blog_blogs.blog_club_id', $member->clubs() ) . ' OR core_clubs.type=? OR core_clubs.type=? OR core_clubs.type=? )', \IPS\Member\Club::TYPE_PUBLIC, \IPS\Member\Club::TYPE_READONLY, \IPS\Member\Club::TYPE_OPEN );
					}
				}
				else
				{
					$where[] = array( '( blog_blogs.blog_club_id IS NULL OR core_clubs.type=? OR core_clubs.type=? OR core_clubs.type=? )', \IPS\Member\Club::TYPE_PUBLIC, \IPS\Member\Club::TYPE_READONLY, \IPS\Member\Club::TYPE_OPEN  );
				}
			}
			
		}
		return parent::getItemsWithPermission( $where, $order, $limit, $permissionKey, $includeHiddenItems, $queryFlags, $member, $joinContainer, $joinComments, $joinReviews, $countOnly, $joins );
	}
	
	/**
	 * Check Moderator Permission
	 *
	 * @param	string						$type		'edit', 'hide', 'unhide', 'delete', etc.
	 * @param	\IPS\Member|NULL			$member		The member to check for or NULL for the currently logged in member
	 * @param	\IPS\Node\Model|NULL		$container	The container
	 * @return	bool
	 */
	public static function modPermission( $type, \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		if ( \in_array( $type, array( 'edit', 'delete', 'lock' ) ) and $container and $container->member_id === $member->member_id )
		{
			return $member->group['g_blog_allowownmod'];
		}
		
		return parent::modPermission( $type, $member, $container );
	}
	
	/**
	 * Reaction Type
	 *
	 * @return	string
	 */
	public static function reactionType()
	{
		return 'comment_id';
	}

	/**
	 * Get content for embed
	 *
	 * @param	array	$params	Additional parameters to add to URL
	 * @return	string
	 */
	public function embedContent( $params )
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'embed.css', 'blog', 'front' ) );
		return \IPS\Theme::i()->getTemplate( 'global', 'blog' )->embedEntryComment( $this, $this->item(), $this->item()->container(), $this->url()->setQueryString( $params ) );
	}
}