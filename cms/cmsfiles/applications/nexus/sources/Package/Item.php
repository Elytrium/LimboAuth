<?php
/**
 * @brief		Nexus Package Content Item Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		29 Apr 2014
 */

namespace IPS\nexus\Package;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Package Item Model
 */
class _Item extends \IPS\Content\Item implements \IPS\Content\Featurable, \IPS\Content\Shareable, \IPS\Content\Embeddable, \IPS\Content\MetaData, \IPS\Content\Searchable
{
	/**
	 * @brief	Application
	 */
	public static $application = 'nexus';
	
	/**
	 * @brief	Module
	 */
	public static $module = 'store';
	
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'nexus_packages';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'p_';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	Node Class
	 */
	public static $containerNodeClass = 'IPS\nexus\Package\Group';
	
	/**
	 * @brief	Review Class
	 */
	public static $reviewClass = 'IPS\nexus\Package\Review';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'title'					=> 'name',
		'container'				=> 'group',
		'featured'				=> 'featured',
		'num_reviews'			=> 'reviews',
		'unapproved_reviews'	=> 'unapproved_reviews',
		'hidden_reviews'		=> 'hidden_reviews',
		'rating'				=> 'rating',
		'meta_data'				=> 'meta_data',
		'date'					=> 'date_added',
		'updated'				=> 'date_updated'
	);
	
	/**
	 * @brief	Title
	 */
	public static $title = 'product';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'archive';
	
	/**
	 * @brief	Include In Sitemap
	 */
	public static $includeInSitemap = FALSE;
	
	/**
	 * @brief	Can this content be moderated normally from the front-end (will be FALSE for things like Pages and Commerce Products)
	 */
	public static $canBeModeratedFromFrontend = FALSE;
	
	/**
	 * Get title
	 *
	 * @return	string
	 */
	public function get_title()
	{
		return \IPS\Member::loggedIn()->language()->addToStack("nexus_package_{$this->id}");
	}
	
	/**
	 * Get description
	 *
	 * @return	string
	 */
	public function content()
	{
		return \IPS\Member::loggedIn()->language()->get("nexus_package_{$this->id}_desc"); // Has to be get() rather than addToStack() so we can reliably strips tags, etc.
	}
	
	/**
	 * Can view?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for or NULL for the currently logged in member
	 * @return	bool
	 */
	public function canView( $member=NULL )
	{
		if ( !$this->store )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		return $this->member_groups === '*' or $member->inGroup( explode( ',', $this->member_groups ) );
	}

	/**
	 * Delete Package Data
	 *
	 * @return	void
	 */
	public function delete()
	{
		/* Delete Images */
		foreach( \IPS\Db::i()->select( '*', 'nexus_package_images', array( 'image_product=?', $this->id ) ) as $image )
		{
			try
			{
				\IPS\File::get( 'nexus_Products', $image['image_location'] )->delete();
			}
			catch ( \Exception $e ) { }
		}
		\IPS\Db::i()->delete( 'nexus_package_images', array( 'image_product=?', $this->id ) );

		/* Delete Product Options */
		\IPS\Db::i()->delete( 'nexus_product_options', array( 'opt_package=?', $this->id ) );

		/* Delete Base Prices */
		\IPS\Db::i()->delete( 'nexus_package_base_prices', array( 'id=?', $this->id ) );

		parent::delete();
	}
	
	/**
	 * Get items with permisison check
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
		$member = $member ?: \IPS\Member::loggedIn();

    	$where[] = "( p_member_groups='*' OR " . \IPS\Db::i()->findInSet( 'p_member_groups', $member->groups ) . ' )';
		$where[] = array( 'p_store=?', 1 );

    	$return = parent::getItemsWithPermission( $where, $order, $limit, $permissionKey, $includeHiddenItems, $queryFlags, $member, $joinContainer, $joinComments, $joinReviews, $countOnly, $joins, $skipPermission, $joinTags, $joinAuthor, $joinLastCommenter, $showMovedLinks );
		$return->classname = 'IPS\nexus\Package';
		return $return;
	}

	/**
	 * @brief	Cached URLs
	 */
	protected $_url	= array();

	/**
	 * Get URL
	 *
	 * @param	string|NULL		$action		Action
	 * @return	\IPS\Http\Url
	 */
	public function url( $action=NULL )
	{
		$_key	= md5( $action );

		if( !isset( $this->_url[ $_key ] ) )
		{
			$this->_url[ $_key ] = \IPS\Http\Url::internal( "app=nexus&module=store&controller=product&id={$this->id}", 'front', 'store_product', \IPS\Http\Url\Friendly::seoTitle( \IPS\Member::loggedIn()->language()->get( 'nexus_package_' . $this->id ) ) );
		
			if ( $action )
			{
				$this->_url[ $_key ] = $this->_url[ $_key ]->setQueryString( 'do', $action );
			}
		}
	
		return $this->_url[ $_key ];
	}

	/**
	 * Can review?
	 *
	 * @param	\IPS\Member\NULL	$member							The member (NULL for currently logged in member)
	 * @param	bool				$considerPostBeforeRegistering	If TRUE, and $member is a guest, will return TRUE if "Post Before Registering" feature is enabled
	 * @return	bool
	 */
	public function canReview( $member=NULL, $considerPostBeforeRegistering = TRUE )
	{
		if ( !$this->reviewable )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( !parent::canReview( $member, $considerPostBeforeRegistering ) )
		{
			return FALSE;
		}
		
		if ( !\IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=? AND ps_member=?', 'nexus', 'package', $this->id, $member->member_id ) )->first() )
		{
			return FALSE;
		}
		
		return TRUE;
	}
	
	/**
	 * Should new reviews be moderated?
	 *
	 * @param	\IPS\Member	$member							The member posting
	 * @param	bool		$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public function moderateNewReviews( \IPS\Member $member, $considerPostBeforeRegistering = FALSE )
	{
		if ( $this->review_moderate )
		{
			return TRUE;
		}
		
		return parent::moderateNewReviews( $member, $considerPostBeforeRegistering );
	}
	
	/**
	 * Images
	 *
	 * @return	\IPS\File\Iterator
	 */
	public function images()
	{
		return new \IPS\File\Iterator( \IPS\Db::i()->select( 'image_location', 'nexus_package_images', array( 'image_product=?', $this->id ),'image_primary desc' ), 'nexus_Products', NULL, TRUE );
	}
	
	/* !Embeddable */
	
	/**
	 * Get content for embed
	 *
	 * @param	array	$params	Additional parameters to add to URL
	 * @return	string
	 */
	public function embedContent( $params )
	{
		$memberCurrency = ( ( isset( \IPS\Request::i()->cookie['currency'] ) and \in_array( \IPS\Request::i()->cookie['currency'], \IPS\nexus\Money::currencies() ) ) ? \IPS\Request::i()->cookie['currency'] : \IPS\nexus\Customer::loggedIn()->defaultCurrency() );
		$package = \IPS\nexus\Package::load( $this->id );

		/* Do we have renewal terms? */
		$renewalTerm = NULL;
		$renewOptions = $package->renew_options ? json_decode( $package->renew_options, TRUE ) : array();
		if ( \count( $renewOptions ) )
		{
			$renewalTerm = TRUE;
			if ( \count( $renewOptions ) === 1 )
			{
				$renewalTerm = array_pop( $renewOptions );
				$renewalTerm = new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( $renewalTerm['cost'][ $memberCurrency ]['amount'], $memberCurrency ), new \DateInterval( 'P' . $renewalTerm['term'] . mb_strtoupper( $renewalTerm['unit'] ) ), $package->tax ? \IPS\nexus\Tax::load( $package->tax ) : NULL, $renewalTerm['add'] );
			}
		}

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'embed.css', 'nexus', 'front' ) );
		return \IPS\Theme::i()->getTemplate( 'global', 'nexus' )->embedProduct( $this, $renewalTerm, $this->url()->setQueryString( $params ), $this->embedImage() );
	}
		
	/**
	 * Get image for embed
	 *
	 * @return	\IPS\File|NULL
	 */
	public function embedImage()
	{
		$product = \IPS\nexus\Package::load( $this->id );
		return $product->_data['image'] ? \IPS\File::get( 'nexus_Products', $product->_data['image'] ) : NULL;
	}

	/**
	 * Get mapped value
	 *
	 * @param	string	$key	date,content,ip_address,first
	 * @return	mixed
	 */
	public function mapped( $key )
	{
		if ( $key === 'title' )
		{
			return $this->title;
		}
		elseif ( $key === 'date' )
		{
			return $this->date_added;
		}
		return parent::mapped($key);
	}
	
	/**
	 * Supported Meta Data Types
	 *
	 * @return	array
	 */
	public static function supportedMetaDataTypes()
	{
		return array();
	}
	
	/* !Search */
	
	/**
	 * Title for search index
	 *
	 * @return	string
	 */
	public function searchIndexTitle()
	{
		$titles = array();
		foreach ( \IPS\Lang::languages() as $lang )
		{
			try
			{
				$titles[] = $lang->get( "nexus_package_{$this->id}" );
			}
			catch( \UnderflowException $e )
			{
			}
		}
		return implode( ' ', $titles );
	}
	
	/**
	 * Content for search index
	 *
	 * @return	string
	 */
	public function searchIndexContent()
	{
		$descriptions = array();
		foreach ( \IPS\Lang::languages() as $lang )
		{
			try
			{
				$descriptions[] = $lang->get("nexus_package_{$this->id}_desc");
			}
			catch ( \UnderflowException $e ) { }
		}
		return implode( ' ', $descriptions );
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
		return $this->store ? $this->member_groups : '';
	}
	
	/**
	 * Columns needed to query for search result / stream view
	 *
	 * @return	array
	 */
	public static function basicDataColumns()
	{
		return array( 'p_id', 'p_base_price', 'p_reviews', 'p_discounts', 'p_renew_options', 'p_tax', 'p_stock', 'p_initial_term' );
	}
	
	/**
	 * Get URL from index data
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	string|NULL	$action			Action
	 * @return	\IPS\Http\Url
	 */
	public static function urlFromIndexData( $indexData, $itemData, $action = NULL )
	{		
		return \IPS\Http\Url::internal( "app=nexus&module=store&controller=product&id={$indexData['index_item_id']}", 'front', 'store_product', \IPS\Member::loggedIn()->language()->addToStack( 'nexus_package_' . $indexData['index_item_id'], FALSE, array( 'seotitle' => TRUE ) ) );
	}
	
	/**
	 * Get HTML for search result display
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$authorData		Basic data about the author. Only includes columns returned by \IPS\Member::columnsForPhoto()
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	array|NULL	$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	array		$reputationData	Array of people who have given reputation and the reputation they gave
	 * @param	int|NULL	$reviewRating	If this is a review, the rating
	 * @param	bool		$iPostedIn		If the user has posted in the item
	 * @param	string		$view			'expanded' or 'condensed'
	 * @param	bool		$asItem	Displaying results as items?
	 * @param	bool		$canIgnoreComments	Can ignore comments in the result stream? Activity stream can, but search results cannot.
	 * @param	array		$template	Optional custom template
	 * @param	array		$reactions	Reaction Data
	 * @return	string
	 */
	public static function searchResult( array $indexData, array $authorData, array $itemData, ?array $containerData, array $reputationData, $reviewRating, $iPostedIn, $view, $asItem, $canIgnoreComments=FALSE, $template=NULL, $reactions=array() )
	{
		$indexData['index_title'] = \IPS\Member::loggedIn()->language()->addToStack( 'nexus_package_' . $indexData['index_item_id'] );
		return parent::searchResult( $indexData, $authorData, $itemData, $containerData, $reputationData, $reviewRating, $iPostedIn, $view, $asItem, $canIgnoreComments, $template, $reactions );
	}
	
	
	/**
	 * Query to get additional data for search result / stream view
	 *
	 * @param	array	$items	Item data (will be an array containing values from basicDataColumns())
	 * @return	array
	 */
	public static function searchResultExtraData( $items )
	{
		$images = iterator_to_array( \IPS\Db::i()->select( array( 'image_product', 'image_location' ), 'nexus_package_images', array( array( \IPS\Db::i()->in( 'image_product', array_keys( $items ) ) ), array( 'image_primary=1' ) ) )->setKeyField( 'image_product' )->setValueField( 'image_location' ) );
		
		$taxIds = array();
		foreach ( $items as $k => $data )
		{
			if ( $data['p_tax'] )
			{
				$taxIds[ $data['p_tax'] ] = $data['p_tax'];
			}
		}
		
		$taxData = array();
		if ( $taxIds )
		{
			$taxData = iterator_to_array( \IPS\Db::i()->select( '*', 'nexus_tax', \IPS\Db::i()->in( 't_id', $taxIds ) )->setKeyField('t_id') );
		}
				
		$return = array();
		foreach ( $items as $k => $data )
		{
			$return[ $k ]['image'] = isset( $images[ $k ] ) ? $images[ $k ] : NULL;
			$return[ $k ]['tax'] = ( $data['p_tax'] and isset( $taxData[ $data['p_tax'] ] ) ) ? $taxData[ $data['p_tax'] ] : NULL;
		}
		
		return $return;
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
		$url = static::urlFromIndexData( $indexData, $itemData );
		
		/* Work out the price to display */
		$customer = \IPS\nexus\Customer::loggedIn();
		$currency = ( isset( \IPS\Request::i()->cookie['currency'] ) and \in_array( \IPS\Request::i()->cookie['currency'], \IPS\nexus\Money::currencies() ) ) ? \IPS\Request::i()->cookie['currency'] : $customer->defaultCurrency();
		$renewOptions = $itemData['p_renew_options'] ? json_decode( $itemData['p_renew_options'], TRUE ) : array();
		$priceInfo = \IPS\nexus\Package::fullPriceInfoFromData( $customer, $currency, $indexData['index_item_id'], json_decode( $itemData['p_base_price'], TRUE ), json_decode( $itemData['p_discounts'], TRUE ), $renewOptions, $itemData['p_stock'], $itemData['extra']['tax'] ? \IPS\nexus\Tax::constructFromData( $itemData['extra']['tax'] ) : NULL, $itemData['p_initial_term'] ? new \DateInterval("P{$itemData['p_initial_term']}") : NULL );
		
		/* Display */
		return \IPS\Theme::i()->getTemplate( 'global', 'nexus', 'front' )->searchResultProductSnippet( $indexData, $itemData, isset( $itemData['extra']['image'] ) ? $itemData['extra']['image'] : NULL, $url, $priceInfo, $view == 'condensed' );
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
		/* Commerce Items have no own content type, so we can use only the general mod permissions */
		$member = $member ?: \IPS\Member::loggedIn();
		if ( \in_array( $type, array( 'hide', 'unhide', 'delete' ) ) and $container )
		{
			if ( $member->modPermission( "can_{$type}" ) )
			{
				return TRUE;
			}
		}

		return parent::modPermission( $type, $member, $container );
	}

	/**
	 * Should posting this increment the poster's post count?
	 *
	 * @param	\IPS\Node\Model|NULL	$container	Container
	 * @return	void
	 */
	public static function incrementPostCount( \IPS\Node\Model $container = NULL )
	{
		return FALSE;
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
	 * Do Moderator Action
	 *
	 * @param	string				$action	The action
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @param	string|NULL			$reason	Reason (for hides)
	 * @param	bool				$immediately	Delete immediately
	 * @return	void
	 * @throws	\OutOfRangeException|\InvalidArgumentException|\RuntimeException
	 */
	public function modAction( $action, \IPS\Member $member = NULL, $reason = NULL, $immediately = FALSE )
	{
		throw new \InvalidArgumentException;
	}

	/**
	 * Can move?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canMove( $member=NULL )
	{
		return FALSE;
	}

	/**
	 * Returns the meta description
	 *
	 * @param	string|NULL	$return	Specific description to use (useful for paginated displays to prevent having to run extra queries)
	 * @return	string
	 * @throws	\BadMethodCallException
	 */
	public function metaDescription( $return = NULL )
	{
		return \IPS\Member::loggedIn()->language()->addToStack("nexus_package_{$this->id}_desc", FALSE, array( 'striptags' => TRUE, 'removeNewlines' => TRUE ) );
	}

	/**
	 * Get container
	 *
	 * @return	\IPS\Node\Model
	 * @note	Certain functionality requires a valid container but some areas do not use this functionality (e.g. messenger)
	 * @throws	\OutOfRangeException|\BadMethodCallException
	 */
	public function container()
	{
		if( $this->custom > 0 )
		{
			throw new \BadMethodCallException;
		}

		return parent::container();
	}
}
