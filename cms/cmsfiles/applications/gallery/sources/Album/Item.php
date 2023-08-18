<?php
/**
 * @brief		Gallery Album Content Item Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		13 Mar 2017
 */

namespace IPS\gallery\Album;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Model
 */
class _Item extends \IPS\Content\Item implements 
\IPS\Content\Permissions,
\IPS\Content\ReadMarkers,
\IPS\Content\Ratings,
\IPS\Content\Searchable, 
\IPS\Content\Shareable, 
\IPS\Content\Embeddable, 
\IPS\Content\Hideable, 
\IPS\Content\Featurable, 
\IPS\Content\Lockable,
\IPS\Content\MetaData
{
	use \IPS\Content\Reactable, \IPS\Content\Reportable, \IPS\Content\Statistics, \IPS\Content\ViewUpdates;

	/**
	 * @brief	Application
	 */
	public static $application = 'gallery';
	
	/**
	 * @brief	Module
	 */
	public static $module = 'gallery';
	
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'gallery_albums';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'album_';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	Node Class
	 */
	public static $containerNodeClass = 'IPS\gallery\Category';
	
	/**
	 * @brief	Review Class
	 */
	public static $reviewClass = 'IPS\gallery\Album\Review';

	/**
	 * @brief	Comment Class
	 */
	public static $commentClass = 'IPS\gallery\Album\Comment';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'title'					=> 'name',
		'content'				=> 'description',
		'container'				=> 'category_id',
		'num_reviews'			=> 'reviews',
		'unapproved_reviews'	=> 'reviews_unapproved',
		'hidden_reviews'		=> 'reviews_hidden',
		'num_comments'			=> 'comments',
		'unapproved_comments'	=> 'comments_unapproved',
		'hidden_comments'		=> 'comments_hidden',
		'rating_total'			=> 'rating_total',
		'rating_hits'			=> 'rating_count',
		'rating_average'		=> 'rating_aggregate',
		'rating'				=> 'rating_aggregate',
		'meta_data'				=> 'meta_data',
		'updated'				=> 'last_img_date',
		'date'					=> 'last_img_date',
		'author'				=> 'owner_id',
		'last_comment'			=> 'last_comment',
		'last_review'			=> 'last_review',
		'featured'				=> 'featured',
		'locked'				=> 'locked',
		'hidden'				=> 'hidden',
		'views'					=> 'views'
	);
	
	/**
	 * @brief	Title
	 */
	public static $title = 'gallery_album';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'file-photo-o';

	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'gallery-album';

	/**
	 * @brief   Used for the datalayer
	 */
	public static $contentType = 'album_item';
	
	/**
	 * Get title
	 *
	 * @return	string
	 */
	public function get_title()
	{
		return $this->name;
	}

	/**
	 * Get album as node
	 *
	 * @return array
	 */
	public function asNode()
	{
		$data = $this->_data;

		foreach( $this->_data as $k => $v )
		{
			$data['album_' . $k ] = $v;
		}

		return \IPS\gallery\Album::constructFromData( $data, FALSE );
	}

	/**
	 * @brief	URL Base
	 */
	public static $urlBase = 'app=gallery&module=gallery&controller=browse&album=';
	
	/**
	 * @brief	URL Base
	 */
	public static $urlTemplate = 'gallery_album';
	
	/**
	 * @brief	SEO Title Column
	 */
	public static $seoTitleColumn = 'name_seo';

	/**
	 * Can review?
	 *
	 * @param	\IPS\Member\NULL	$member							The member (NULL for currently logged in member)
	 * @param	bool				$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 */
	public function canReview( $member=NULL, $considerPostBeforeRegistering = TRUE )
	{
		if ( !$this->use_reviews )
		{
			return FALSE;
		}
		
		return parent::canReview( $member, $considerPostBeforeRegistering );
	}

	/**
	 * Can comment?
	 *
	 * @param	\IPS\Member\NULL	$member							The member (NULL for currently logged in member)
	 * @param	bool				$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 */
	public function canComment( $member=NULL, $considerPostBeforeRegistering = TRUE )
	{
		if ( !$this->use_comments )
		{
			return FALSE;
		}
		
		return parent::canComment( $member, $considerPostBeforeRegistering );
	}

	/**
	 * Supported Meta Data Types
	 *
	 * @return	array
	 */
	public static function supportedMetaDataTypes()
	{
		return array( 'core_ContentMessages' );
	}

	/**
	 * Get image for embed
	 *
	 * @return	\IPS\File|NULL
	 */
	public function embedImage()
	{
		if( $this->cover_img_id OR $this->last_img_id )
		{
			return \IPS\File::get( 'gallery_Images', \IPS\gallery\Image::load( $this->cover_img_id ?: $this->last_img_id )->small_file_name );
		}
		else
		{
			return NULL;
		}
	}

	/**
	 * Get preview image for share services
	 *
	 * @return	string
	 */
	public function shareImage()
	{
		if( $this->cover_img_id OR $this->last_img_id )
		{
			return (string) \IPS\File::get( 'gallery_Images', \IPS\gallery\Image::load( $this->cover_img_id ?: $this->last_img_id )->masked_file_name )->url;
		}
		else
		{
			return '';
		}
	}

	/**
	 * Get items with permission check
	 *
	 * @param	array		$where				Where clause
	 * @param	string		$order				MySQL ORDER BY clause (NULL to order by date)
	 * @param	int|array	$limit				Limit clause
	 * @param	string|NULL	$permissionKey		A key which has a value in the permission map (either of the container or of this class) matching a column ID in core_permission_index or NULL to ignore permissions
	 * @param	mixed		$includeHiddenItems	Include hidden items? NULL to detect if currently logged in member has permission, -1 to return public content only, TRUE to return unapproved content and FALSE to only return unapproved content the viewing member submitted
	 * @param	int			$queryFlags			Select bitwise flags
	 * @param	\IPS\Member	$member				The member (NULL to use currently logged in member)
	 * @param	bool		$joinContainer		If true, will join container data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinComments		If true, will join comment data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinReviews		If true, will join review data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$countOnly			If true will return the count
	 * @param	array|null	$joins				Additional arbitrary joins for the query
	 * @param	mixed		$skipPermission		If you are getting records from a specific container, pass the container to reduce the number of permission checks necessary or pass TRUE to skip conatiner-based permission. You must still specify this in the $where clause
	 * @param	bool		$joinTags			If true, will join the tags table
	 * @param	bool		$joinAuthor			If true, will join the members table for the author
	 * @param	bool		$joinLastCommenter	If true, will join the members table for the last commenter
	 * @param	bool		$showMovedLinks		If true, moved item links are included in the results
	 * @param	array|null	$location			Array of item lat and long
	 * @return	\IPS\Patterns\ActiveRecordIterator|int
	 */
	public static function getItemsWithPermission( $where=array(), $order=NULL, $limit=10, $permissionKey='read', $includeHiddenItems=\IPS\Content\Hideable::FILTER_AUTOMATIC, $queryFlags=0, \IPS\Member $member=NULL, $joinContainer=FALSE, $joinComments=FALSE, $joinReviews=FALSE, $countOnly=FALSE, $joins=NULL, $skipPermission=FALSE, $joinTags=TRUE, $joinAuthor=TRUE, $joinLastCommenter=TRUE, $showMovedLinks=FALSE, $location=NULL )
	{
		if( $permissionKey == 'add' )
		{
			$where[] = static::submitRestrictionWhere( $member, \IPS\gallery\Category::load( $where[0][1] ) );
		}

		$where[] = static::getItemsWithPermissionWhere( $where, $member, $joins );

		return parent::getItemsWithPermission( $where, $order, $limit, $permissionKey, $includeHiddenItems, $queryFlags, $member, $joinContainer, $joinComments, $joinReviews, $countOnly, $joins, $skipPermission, $joinTags, $joinAuthor, $joinLastCommenter, $showMovedLinks );
	}

	/**
	 * Additional WHERE clauses for finding albums the user can submit to
	 *
	 * @param	\IPS\Member|NULL		$member		Member to check
	 * @param	\IPS\gallery\Category	$category	Category we are submitted in
	 * @return	string
	 */
	public static function submitRestrictionWhere( $member, \IPS\gallery\Category $category ) : string
	{
		$member	= $member ?: \IPS\Member::loggedIn();

		/* Guests can't create albums so we can skip all the member specific stuff */
		if( !$member->member_id )
		{
			return '(album_submit_type=' . \IPS\gallery\Album::AUTH_SUBMIT_PUBLIC . ')';
		}
		else
		{
			/* For starters, allow us to submit to public albums and our own albums */
			$wheres	= array(
				'(album_submit_type=' . \IPS\gallery\Album::AUTH_SUBMIT_OWNER . ' and album_owner_id=' . $member->member_id . ')',
				'(album_submit_type=' . \IPS\gallery\Album::AUTH_SUBMIT_PUBLIC . ')'
			);

			/* Now allow us to submit to albums that allow group submissions */
			$wheres[] = '(album_submit_type=' . \IPS\gallery\Album::AUTH_SUBMIT_GROUPS . ' AND ' . \IPS\Db::i()->findInSet( 'album_submit_access', $member->groups ) . ')';

			/* And where we as an individual member are allowed to submit */
			$wheres[] = '(album_submit_type=' . \IPS\gallery\Album::AUTH_SUBMIT_MEMBERS . ' AND (' . \IPS\Db::i()->findInSet( 'album_submit_access', $member->socialGroups() ) . ' OR album_owner_id=' . $member->member_id . ' ) )';

			/* And finally, if we're in a club and we allow anyone in the club to submit, handle that */
			if( $category->club() AND \in_array( $category->club()->id, $member->clubs() ) )
			{
				$wheres[] = '(album_submit_type=' . \IPS\gallery\Album::AUTH_SUBMIT_CLUB . ')';
			}

			return '(album_owner_id=' . $member->member_id . ' OR ' . implode( ' OR ', $wheres ) . ')';
		}
	}

	/**
	 * @brief	Cached groups the member can access
	 */
	protected static $_availableGroups	= array();

	/**
	 * @brief	Cached URLs
	 */
	protected $_url	= array();

	/**
	 * WHERE clause for getItemsWithPermission
	 *
	 * @param	array		$where				Current WHERE clause
	 * @param	\IPS\Member	$member				The member (NULL to use currently logged in member)
	 * @param	bool		$joins				Additional joins
	 * @return	array
	 */
	public static function getItemsWithPermissionWhere( $where, $member, &$joins )
	{
		/* We need to make sure we can access the album */
		$restricted	= array( 0 );
		$member		= $member ?: \IPS\Member::loggedIn();

		if( isset( static::$_availableGroups[ $member->member_id ] ) )
		{
			$restricted	= static::$_availableGroups[ $member->member_id ];
		}
		else
		{
			if( $member->member_id )
			{
				foreach( \IPS\Db::i()->select( '*', 'core_sys_social_group_members', array( 'member_id=?', $member->member_id ) ) as $group )
				{
					$restricted[]	= $group['group_id'];
				}
			}

			static::$_availableGroups[ $member->member_id ]	= $restricted;
		}

		/* If you can edit images in a category you can see private albums in that category. We can only really check globally at this stage, however. */
		if( \IPS\gallery\Image::modPermission( 'edit', $member ) )
		{
			return array( "( gallery_albums.album_type IN(1,2) OR ( gallery_albums.album_type=3 AND ( gallery_albums.album_owner_id=? OR gallery_albums.album_allowed_access IN (" . implode( ',', $restricted ) . ") ) ) )", $member->member_id );
		}
		else
		{
			return array( "( gallery_albums.album_type=1 OR ( gallery_albums.album_type=2 AND gallery_albums.album_owner_id=? ) OR ( gallery_albums.album_type=3 AND ( gallery_albums.album_owner_id=? OR gallery_albums.album_allowed_access IN (" . implode( ',', $restricted ) . ") ) ) )", $member->member_id, $member->member_id );
		}
	}

	/**
	 * Additional WHERE clauses for Follow view
	 *
	 * @param	bool		$joinContainer		If true, will join container data (set to TRUE if your $where clause depends on this data)
	 * @param	array		$joins				Other joins
	 * @return	array
	 */
	public static function followWhere( &$joinContainer, &$joins )
	{
		return array_merge( parent::followWhere( $joinContainer, $joins ), array( static::getItemsWithPermissionWhere( array(), \IPS\Member::loggedIn(), $joins ) ) );
	}

	/**
	 * Move
	 *
	 * @param	\IPS\Node\Model	$container	Container to move to
	 * @param	bool			$keepLink	If TRUE, will keep a link in the source
	 * @return	void
	 * @note	We need to update the image category references too
	 */
	public function move( \IPS\Node\Model $container, $keepLink=FALSE )
	{
		return $this->asNode()->moveTo( $container, $this->container() );
	}

	/**
	 * Search Index Permissions
	 *
	 * @return	string	Comma-delimited values or '*'
	 * 	@li			Number indicates a group
	 *	@li			Number prepended by "m" indicates a member
	 *	@li			Number prepended by "s" indicates a social group
	 */
	public function searchIndexPermissions()
	{
		return $this->asNode()->searchIndexPermissions();
	}

	/**
	 * Columns needed to query for search result / stream view
	 *
	 * @return	array
	 */
	public static function basicDataColumns()
	{
		$return = parent::basicDataColumns();
		$return[] = 'album_last_x_images';
		$return[] = 'album_cover_img_id';

		return $return;
	}

	/**
	 * Query to get additional data for search result / stream view
	 *
	 * @param	array	$items	Item data (will be an array containing values from basicDataColumns())
	 * @return	array
	 */
	public static function searchResultExtraData( $items )
	{
		$imageIds = array();
		foreach ( $items as $itemData )
		{
			if ( $itemData['album_cover_img_id'] )
			{
				$imageIds[] = $itemData['album_cover_img_id'];
			}

			if ( $itemData['album_last_x_images'] )
			{
				$latestImages = json_decode( $itemData['album_last_x_images'], true );

				foreach( $latestImages as $imageId )
				{
					$imageIds[] = $imageId;
				}
			}
		}
		
		if ( \count( $imageIds ) )
		{
			if( \IPS\Dispatcher::hasInstance() )
			{
				\IPS\gallery\Application::outputCss();
			}

			$return = array();
			
			foreach ( \IPS\gallery\Image::getItemsWithPermission( array( array( 'image_id IN(' . implode( ',', $imageIds ) . ')' ) ), NULL, NULL ) as $image )
			{
				if( isset( $return[ $image->album_id] ) AND \count( $return[ $image->album_id ] ) > 19 )
				{
					continue;
				}

				$return[ $image->album_id ][] = $image;
			}
			
			return $return;
		}
		
		return array();
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
		$url	= \IPS\Http\Url::internal( static::$urlBase . $indexData['index_item_id'], 'front', static::$urlTemplate, \IPS\Http\Url\Friendly::seoTitle( $indexData['index_title'] ?: $itemData[ static::$databasePrefix . static::$databaseColumnMap['title'] ] ) );
		$images	= ( isset( $itemData['extra'] ) AND \count( $itemData['extra'] ) ) ? $itemData['extra'] : array();

		return \IPS\Theme::i()->getTemplate( 'global', 'gallery', 'front' )->searchResultAlbumSnippet( $indexData, $itemData, $images, $url, $view == 'condensed' );
	}

	/**
	 * Return the language string key to use in search results
	 *
	 * @note Normally we show "(user) posted a (thing) in (area)" but sometimes this may not be accurate, so this is abstracted to allow
	 *	content classes the ability to override
	 * @param	array 		$authorData		Author data
	 * @param	array 		$articles		Articles language strings
	 * @param	array 		$indexData		Search index data
	 * @param	array 		$itemData		Data about the item
	 * @param   bool        $includeLinks   Include links to member profile
	 * @return	string
	 */
	public static function searchResultSummaryLanguage( $authorData, $articles, $indexData, $itemData, $includeLinks = TRUE )
	{
		if( !\in_array( 'IPS\Content\Comment', class_parents( $indexData['index_class'] ) ) )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( "album_user_own_activity_item", FALSE, array( 'sprintf' => array( $articles['indefinite'] ), 'htmlsprintf' => array( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->userLinkFromData( $authorData['member_id'], $authorData['name'], $authorData['members_seo_name'], $authorData['member_group_id'] ?? \IPS\Settings::i()->guest_group ) ) ) );
		}
		else
		{
			return parent::searchResultSummaryLanguage( $authorData, $articles, $indexData, $itemData );
		}
	}

	/**
	 * @brief	A classname applied to the search result block
	 */
	public static $searchResultClassName = 'cGalleryAlbumSearchResult';

	/**
	 * Are comments supported by this class?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for or NULL to not check permission
	 * @param	\IPS\Node\Model|NULL	$container	The container to check in, or NULL for any container
	 * @return	bool
	 */
	public static function supportsComments( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		if( $container !== NULL )
		{
			return parent::supportsComments() and $container->allow_comments AND ( !$member or $container->can( 'read', $member ) );
		}
		else
		{
			return parent::supportsComments() and ( !$member or \IPS\gallery\Category::countWhere( 'read', $member, array( 'category_allow_comments=1' ) ) );
		}
	}

	/**
	 * Are reviews supported by this class?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for or NULL to not check permission
	 * @param	\IPS\Node\Model|NULL	$container	The container to check in, or NULL for any container
	 * @return	bool
	 */
	public static function supportsReviews( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		if( $container !== NULL )
		{
			return parent::supportsReviews() and $container->allow_reviews AND ( !$member or $container->can( 'read', $member ) );
		}
		else
		{
			return parent::supportsReviews() and ( !$member or \IPS\gallery\Category::countWhere( 'read', $member, array( 'category_allow_reviews=1' ) ) );
		}
	}

	/**
	 * Get template for content tables
	 *
	 * @return	callable
	 */
	public static function contentTableTemplate()
	{
		\IPS\gallery\Application::outputCss();		
		
		return array( \IPS\Theme::i()->getTemplate( 'browse', 'gallery', 'front' ), 'albums' );
	}

	/**
	 * Get available comment/review tabs
	 *
	 * @return	array
	 */
	public function commentReviewTabs()
	{
		$tabs = array();

		if ( $this->container()->allow_reviews AND $this->use_reviews )
		{
			$tabs['reviews'] = \IPS\Member::loggedIn()->language()->addToStack( 'image_review_count', TRUE, array( 'pluralize' => array( $this->mapped('num_reviews') ) ) );
		}
		if ( $this->container()->allow_comments AND $this->use_comments )
		{
			$tabs['comments'] = \IPS\Member::loggedIn()->language()->addToStack( 'image_comment_count', TRUE, array( 'pluralize' => array( $this->mapped('num_comments') ) ) );
		}

		return $tabs;
	}

	/**
	 * Get comment/review output
	 *
	 * @param	string	$tab	Active tab
	 * @return	string
	 */
	public function commentReviews( $tab )
	{
		if ( $tab === 'reviews' AND $this->container()->allow_reviews AND $this->use_reviews )
		{
			return \IPS\Theme::i()->getTemplate('browse')->albumReviews( $this );
		}
		elseif( $tab === 'comments' AND $this->container()->allow_comments AND $this->use_comments )
		{
			return \IPS\Theme::i()->getTemplate('browse')->albumComments( $this );
		}

		return '';
	}

	/**
	 * Reaction Type
	 *
	 * @return	string
	 */
	public static function reactionType()
	{
		return 'album_id';
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
		return \IPS\Theme::i()->getTemplate( 'global', 'gallery' )->embedAlbums( $this, $this->asNode(), $this->url()->setQueryString( $params ) );
	}

	/**
	 * Get the container item class to use for mod permission checks
	 *
	 * @return	string|NULL
	 * @note	By default we will return NULL and the container check will execute against Node::$contentItemClass, however
	 *	in some situations we may need to override this (i.e. for Gallery Albums)
	 */
	protected static function getContainerModPermissionClass()
	{
		return 'IPS\gallery\Album\Item';
	}

	/**
	 * Returns the content images
	 *
	 * @param	int|null	$limit				Number of attachments to fetch, or NULL for all
	 * @param	bool		$ignorePermissions	If set to TRUE, permission to view the images will not be checked
	 * @return	array|NULL
	 * @throws	\BadMethodCallException
	 */
	public function contentImages( $limit = NULL, $ignorePermissions = FALSE )
	{
		$images = array();
		
		foreach( \IPS\gallery\Image::getItemsWithPermission( array( array( 'image_album_id=?', $this->id ) ), NULL, $limit ? $limit : 10, $ignorePermissions ? NULL : 'read' ) as $image )
		{
			$images[] = array( 'gallery_Images' => $image->masked_file_name );
		}
		
		return \count( $images ) ? \array_slice( $images, 0, $limit ) : NULL;
	}

	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();

		/* Recount category info */
		$this->container()->resetCommentCounts();
		$this->container()->save();
	}

	/**
	 * Get the last modification date for the sitemap
	 *
	 * @return \IPS\DateTime|null		Timestamp of the last modification time for the sitemap
	 */
	public function lastModificationDate()
	{
		/* Returns the last last comment date */
		$lastMod = parent::lastModificationDate();

		if( !$lastMod AND $this->last_img_date OR ( $lastMod AND $this->last_img_date AND $this->last_img_date > $lastMod->getTimestamp() ) )
		{
			$lastMod = \IPS\DateTime::ts( $this->last_img_date );
		}

		return $lastMod;
	}

	/**
	 * Return query WHERE clause to use for getItemsWithPermission when excluding club content
	 *
	 * @return array
	 */
	public static function clubAlbumExclusion(): array
	{
		if( \IPS\Settings::i()->club_nodes_in_apps )
		{
			return array();
		}
		else
		{
			return array( array(
				'gallery_albums.album_category_id NOT IN(?)',
				\IPS\Db::i()->select( 'node_id', 'core_clubs_node_map', array( 'node_class=?', 'IPS\gallery\Category' ) )
			) );
		}
	}
}