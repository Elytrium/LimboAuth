<?php
/**
 * @brief		Image Comment Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Mar 2014
 */

namespace IPS\gallery\Image;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Image Comment Model
 */
class _Comment extends \IPS\Content\Comment implements \IPS\Content\EditHistory, \IPS\Content\Hideable, \IPS\Content\Searchable, \IPS\Content\Embeddable, \IPS\Content\Anonymous
{
	use \IPS\Content\Reactable, \IPS\Content\Reportable;

	/**
	 * @brief	[Content\Comment]	Form Template
	 */
	public static $formTemplate = array( array( 'forms', 'gallery', 'front' ), 'commentTemplate' );

	/**
	 * @brief	[Content\Comment]	Comment Template
	 */
	public static $commentTemplate = array( array( 'global', 'gallery', 'front' ), 'commentContainer' );

	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[Content\Comment]	Item Class
	 */
	public static $itemClass = 'IPS\gallery\Image';
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'gallery_comments';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'comment_';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'item'				=> 'img_id',
		'author'			=> 'author_id',
		'author_name'		=> 'author_name',
		'content'			=> 'text',
		'date'				=> 'post_date',
		'ip_address'		=> 'ip_address',
		'edit_time'			=> 'edit_time',
		'edit_member_name'	=> 'edit_name',
		'edit_show'			=> 'append_edit',
		'approved'			=> 'approved',
		'is_anon'			=> 'is_anon'
	);
	
	/**
	 * @brief	Application
	 */
	public static $application = 'gallery';
	
	/**
	 * @brief	Title
	 */
	public static $title = 'gallery_image_comment';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'camera';
	
	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'gallery-images';
	
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
		if( $itemsWhere = \IPS\gallery\Image::getItemsWithPermissionWhere( $where, $member, $joins ) AND \count( $itemsWhere ) )
		{
			$where[] = $itemsWhere;
		}
		
		return parent::getItemsWithPermission( $where, $order, $limit, $permissionKey, $includeHiddenItems, $queryFlags, $member, $joinContainer, $joinComments, $joinReviews, $countOnly, $joins );
	}
	
	/**
	 * Get URL for doing stuff
	 *
	 * @param	string|NULL		$action		Action
	 * @return	\IPS\Http\Url
	 */
	public function url( $action='find' )
	{
		return parent::url( $action )->setQueryString( 'tab', 'comments' );
	}

	/**
	 * Do stuff after creating (abstracted as comments and reviews need to do different things)
	 *
	 * @return	void
	 */
	public function postCreate()
	{
		parent::postCreate();

		$item	= $this->item();

		if( $item->album_id )
		{
			\IPS\gallery\Album::load( $item->album_id )->setLastComment();
			\IPS\gallery\Album::load( $item->album_id )->save();
		}
	}

	/**
	 * Get snippet HTML for search result display
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$authorData		Basic data about the author. Only includes columns returned by \IPS\Member::columnsForPhoto()
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	array|NULL	$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	array		$reputationData	Array of people who have given reputation and the reputation they gave
	 * @param	int|NULL	$reviewRating	If this is a review, the rating
	 * @param	string		$view			'expanded' or 'condensed'
	 * @return	callable
	 */
	public static function searchResultSnippet( array $indexData, array $authorData, array $itemData, ?array $containerData, array $reputationData, $reviewRating, $view )
	{
		$url = \IPS\Http\Url::internal( \IPS\gallery\Image::$urlBase . $indexData['index_item_id'], 'front', \IPS\gallery\Image::$urlTemplate, \IPS\Http\Url\Friendly::seoTitle( $indexData['index_title'] ?: $itemData[ \IPS\gallery\Image::$databasePrefix . \IPS\gallery\Image::$databaseColumnMap['title'] ] ) );
				
		return \IPS\Theme::i()->getTemplate( 'global', 'gallery', 'front' )->searchResultCommentSnippet( $indexData, $itemData, $itemData['image_small_file_name'], $url, $reviewRating, $view == 'condensed' );
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
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'embed.css', 'gallery', 'front' ) );
		return \IPS\Theme::i()->getTemplate( 'global', 'gallery' )->embedImageComment( $this, $this->item(), $this->url()->setQueryString( $params ) );
	}
}