<?php
/**
 * @brief		File Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		8 Oct 2013
 */

namespace IPS\downloads;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Model
 */
class _File extends \IPS\Content\Item implements
\IPS\Content\Permissions,
\IPS\Content\Tags,
\IPS\Content\Followable,
\IPS\Content\ReadMarkers,
\IPS\Content\Hideable, \IPS\Content\Featurable, \IPS\Content\Pinnable, \IPS\Content\Lockable,
\IPS\Content\Shareable,
\IPS\Content\Searchable,
\IPS\Content\Embeddable,
\IPS\Content\MetaData,
\IPS\Content\EditHistory,
\IPS\Content\Anonymous
{
	use \IPS\Content\Reactable, \IPS\Content\Reportable, \IPS\Content\Statistics, \IPS\Content\ViewUpdates, \IPS\Content\ItemTopic
	{
		\IPS\Content\ItemTopic::changeAuthor as topicChangeAuthor;
	}
	
	/**
	 * @brief	Application
	 */
	public static $application = 'downloads';
	
	/**
	 * @brief	Module
	 */
	public static $module = 'downloads';
	
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'downloads_files';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'file_';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	Node Class
	 */
	public static $containerNodeClass = 'IPS\downloads\Category';
	
	/**
	 * @brief	Comment Class
	 */
	public static $commentClass = 'IPS\downloads\File\Comment';
	
	/**
	 * @brief	Review Class
	 */
	public static $reviewClass = 'IPS\downloads\File\Review';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'container'				=> 'cat',
		'author'				=> 'submitter',
		'author_name'			=> 'author_name',
		'views'					=> 'views',
		'title'					=> 'name',
		'content'				=> 'desc',
		'num_comments'			=> 'comments',
		'unapproved_comments'	=> 'unapproved_comments',
		'hidden_comments'		=> 'hidden_comments',
		'num_reviews'			=> 'reviews',
		'unapproved_reviews'	=> 'unapproved_reviews',
		'hidden_reviews'		=> 'hidden_reviews',
		'last_comment'			=> 'last_comment',
		'last_review'			=> 'last_review',
		'date'					=> 'submitted',
		'updated'				=> 'updated',
		'rating'				=> 'rating',
		'approved'				=> 'open',
		'approved_by'			=> 'approver',
		'approved_date'			=> 'approvedon',
		'pinned'				=> 'pinned',
		'featured'				=> 'featured',
		'locked'				=> 'locked',
		'ip_address'			=> 'ipaddress',
		'meta_data'				=> 'meta_data',
		'edit_time'				=> 'edit_time',
		'edit_member_name'		=> 'edit_name',
		'edit_show'				=> 'append_edit',
		'edit_reason'			=> 'edit_reason',
		'is_anon'				=> 'is_anon',
		'last_comment_anon'		=> 'last_comment_anon'
	);
	
	/**
	 * @brief	Title
	 */
	public static $title = 'downloads_file';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'download';
	
	/**
	 * @brief	Form Lang Prefix
	 */
	public static $formLangPrefix = 'file_';
	
	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'downloads-file';
	
	/**
	 * Columns needed to query for search result / stream view
	 *
	 * @return	array
	 */
	public static function basicDataColumns()
	{
		$return = parent::basicDataColumns();
		$return[] = 'file_primary_screenshot';
		$return[] = 'file_version';
		$return[] = 'file_downloads';
		$return[] = 'file_cost';
		$return[] = 'file_nexus';
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
		$screenshotIds = array();
		foreach ( $items as $itemData )
		{
			if ( $itemData['file_primary_screenshot'] )
			{
				$screenshotIds[] = $itemData['file_primary_screenshot'];
			}
		}
		
		if ( \count( $screenshotIds ) )
		{
			return iterator_to_array( \IPS\Db::i()->select( array( 'record_file_id', 'record_location', 'record_thumb' ), 'downloads_files_records', \IPS\Db::i()->in( 'record_id', $screenshotIds ) )->setKeyField( 'record_file_id' ) );
		}
		
		return array();
	}
		
	/**
	 * Set name
	 *
	 * @param	string	$name	Name
	 * @return	void
	 */
	public function set_name( $name )
	{
		$this->_data['name'] = $name;
		$this->_data['name_furl'] = \IPS\Http\Url\Friendly::seoTitle( $name );
	}

	/**
	 * Get SEO name
	 *
	 * @return	string
	 */
	public function get_name_furl()
	{
		if( !$this->_data['name_furl'] )
		{
			$this->name_furl	= \IPS\Http\Url\Friendly::seoTitle( $this->name );
			$this->save();
		}

		return $this->_data['name_furl'] ?: \IPS\Http\Url\Friendly::seoTitle( $this->name );
	}

	/**
	 * Get primary screenshot ID
	 *
	 * @return	int|null
	 */
	public function get__primary_screenshot()
	{
		return ( isset( $this->_data['primary_screenshot'] ) ) ? $this->_data['primary_screenshot'] : NULL;
	}

	/**
	 * @brief	Cached URLs
	 */
	protected $_url	= array();
	
	/**
	 * @brief	URL Base
	 */
	public static $urlBase = 'app=downloads&module=downloads&controller=view&id=';
	
	/**
	 * @brief	URL Base
	 */
	public static $urlTemplate = 'downloads_file';
	
	/**
	 * @brief	SEO Title Column
	 */
	public static $seoTitleColumn = 'name_furl';
	
	/**
	 * Get URL for last comment page
	 *
	 * @return	\IPS\Http\Url
	 */
	public function lastCommentPageUrl()
	{
		return parent::lastCommentPageUrl()->setQueryString( 'tab', 'comments' );
	}
	
	/**
	 * Get URL for last review page
	 *
	 * @return	\IPS\Http\Url
	 */
	public function lastReviewPageUrl()
	{
		return parent::lastReviewPageUrl()->setQueryString( 'tab', 'reviews' );
	}
	
	/**
	 * Get template for content tables
	 *
	 * @return	callable
	 */
	public static function contentTableTemplate()
	{
		/* Load our CSS */
		\IPS\downloads\Application::outputCss();
		return array( \IPS\Theme::i()->getTemplate( 'browse', 'downloads', 'front' ), 'rows' );
	}

	/**
	 * HTML to manage an item's follows 
	 *
	 * @return	callable
	 */
	public static function manageFollowRows()
	{		
		return array( \IPS\Theme::i()->getTemplate( 'global', 'downloads', 'front' ), 'manageFollowRow' );
	}

	/**
	 * Files
	 */
	protected $_files = array();

	/**
	 * Get files
	 *
	 * @param	int|NULL	$version		If provided, will get the file records for a specific previous version (downloads_filebackup.b_id)
	 * @param	bool		$includeLinks	If true, will include linked files
	 * @param	bool		$pendingVersion	Only include files from a pending new version
	 * @return	\IPS\File\Iterator
	 */
	public function files( $version=NULL, $includeLinks=TRUE, $pendingVersion=FALSE )
	{
		if( isset( $this->_files[ (int) $version ] ) )
		{
			return $this->_files[ (int) $version ];
		}

		$where = $includeLinks ? array( array( 'record_file_id=? AND ( record_type=? OR record_type=? )', $this->id, 'upload', 'link' ) ) : array( array( 'record_file_id=? AND record_type=?', $this->id, 'upload' ) );
		if ( $version !== NULL )
		{
			try
			{
				$backup = \IPS\Db::i()->select( 'b_records', 'downloads_filebackup', array( 'b_id=?', $version ) )->first();
				$where[] = \IPS\Db::i()->in( 'record_id', explode( ',', $backup ) );
			}
			catch( \UnderflowException $e )
			{
				/* Default to current version if the previous version does not exist */
				$where[] = array( 'record_backup=0' );
			}
		}
		else
		{
			$where[] = array( 'record_backup=0' );
		}

		/* Exclude future versions */
		$where[] = array( 'record_hidden=?', $pendingVersion );
						
		$iterator = \IPS\Db::i()->select( '*', 'downloads_files_records', $where )->setKeyField( 'record_id' );
		$iterator = new \IPS\File\Iterator( $iterator, 'downloads_Files', 'record_location', FALSE, 'record_realname', 'record_size' );

		$this->_files[ (int) $version ]	= $iterator;
		return $this->_files[ (int) $version ];
	}
	
	/**
	 * Total filesize
	 */
	protected $_filesize = NULL;
		
	/**
	 * Get Total filesize
	 *
	 * @return	int
	 */
	public function filesize()
	{
		if ( $this->_filesize === NULL )
		{
			$this->_filesize = \IPS\Db::i()->select( 'SUM(record_size)', 'downloads_files_records', array( 'record_file_id=? AND record_type=? AND record_backup=0', $this->id, 'upload' ) )->first();
		}
		
		return $this->_filesize;
	}
	
	/**
	 * Is this a paid file?
	 *
	 * @return	bool
	 */
	public function isPaid()
	{
		if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->idm_nexus_on )
		{
			if ( $this->nexus )
			{
				return TRUE;
			}
			
			if ( $this->cost )
			{
				$costs = json_decode( $this->cost, TRUE );
				if ( \is_array( $costs ) )
				{
					foreach ( $costs as $currency => $data )
					{
						if ( $data['amount'] )
						{
							return TRUE;
						}
					}
				}
				else
				{
					return TRUE;
				}
			}
		}
		
		return FALSE;
	}
	
	/**
	 * Is Purchasable?
	 *
	 * @param	bool	$checkPaid	Check if the file is a paid file
	 * @return	bool
	 */
	public function isPurchasable( $checkPaid = TRUE )
	{
		/* If it's not a paid file, then it's not purchasable */
		if ( $checkPaid and !$this->isPaid() )
		{
			return FALSE;
		}
		
		return (bool) $this->purchasable;
	}
	
	/**
	 * Can enable purchases?
	 *
	 * @param	\IPS\Member|NULL	$member	Member to check, or NULL for currently logged in member
	 * @return	bool
	 */
	public function canEnablePurchases( \IPS\Member $member = NULL )
	{
		if ( !$this->isPaid() )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		if( !(bool) $member->modPermission('can_make_purchasable') )
		{
			return FALSE;
		}

		/* If there isn't an existing product record, do not allow purchases to be re-enabled */
		if ( $this->nexus )
		{
			$productIds	= explode( ',', $this->nexus );
			$hasProduct	= false;

			foreach ( $productIds as $productId )
			{
				try
				{
					\IPS\nexus\Package::load( $productId );

					$hasProduct = true;
					break;
				}
				catch ( \OutOfRangeException $e ) { }
			}

			if( !$hasProduct )
			{
				return FALSE;
			}
		}

		return TRUE;
	}
	
	/**
	 * Can disable purchases?
	 *
	 * @param	\IPS\Member|NULL	$member	Member to check, or NULL for currently logged in member
	 * @return	bool
	 */
	public function canDisablePurchases( \IPS\Member $member = NULL )
	{
		if ( !$this->isPaid() )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		return (bool) $member->modPermission('can_make_unpurchasable');
	}
	
	/**
	 * Get Price
	 *
	 * @return	\IPS\nexus\Money|NULL
	 */
	public function price()
	{
		return static::_price( $this->cost, $this->nexus );
	}
	
	/**
	 * Get Price
	 *
	 * @param	float	$cost				The cost
	 * @param	string	$nexusPackageIds	Comma-delimited list of associated package IDs
	 * @return	\IPS\nexus\Money|NULL
	 * @throws	\OutOfRangeException		If the file does not have a price in the desired currency
	 */
	public static function _price( $cost, $nexusPackageIds )
	{
		if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->idm_nexus_on )
		{
			if ( $nexusPackageIds )
			{
				$packages = explode( ',', $nexusPackageIds );
				try
				{
					if ( \count( $packages ) === 1 )
					{
						return \IPS\nexus\Package::load( $nexusPackageIds )->priceToDisplay();
					}
					else
					{
						return \IPS\nexus\Package::lowestPriceToDisplay( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_packages', \IPS\Db::i()->in( 'p_id', $packages ) ), 'IPS\nexus\Package' ) );
					}
				}
				catch ( \OutOfRangeException $e ) { }
				catch ( \OutOfBoundsException $e ) { }

				return NULL;
			}
			
			if ( $cost )
			{
				$currency = ( isset( \IPS\Request::i()->cookie['currency'] ) and \in_array( \IPS\Request::i()->cookie['currency'], \IPS\nexus\Money::currencies() ) ) ? \IPS\Request::i()->cookie['currency'] : \IPS\nexus\Customer::loggedIn()->defaultCurrency();
				
				/* If $cost is an empty JSON array, the conditional will evaluate false thus resulting in [] being passed to \IPS\nexus\Money (which will fail). */
				$costs = json_decode( $cost, TRUE );
				if ( \is_array( $costs ) )
				{
					if ( isset( $costs[ $currency ]['amount'] ) and $costs[ $currency ]['amount'] )
					{
						return new \IPS\nexus\Money( $costs[ $currency ]['amount'], $currency );
					}
				}
				else
				{
					return new \IPS\nexus\Money( $cost, $currency );
				}
			}
		}
		
		return NULL;
	}
	
	/**
	 * @brief	Number of purchases
	 */
	protected static $purchaseCounts;
	
	/**
	 * Get number of purchases
	 *
	 * @return	array
	 */
	public function purchaseCount()
	{
		if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->idm_nexus_on AND !$this->nexus )
		{
			if ( static::$purchaseCounts === NULL )
			{
				static::$purchaseCounts = iterator_to_array( \IPS\Db::i()->select( 'COUNT(*) AS count, ps_item_id', 'nexus_purchases', array( array( 'ps_app=? AND ps_type=?', 'downloads', 'file' ), \IPS\Db::i()->in( 'ps_item_id', array_keys( static::$multitons ) ) ), NULL, NULL, 'ps_item_id' )->setKeyField('ps_item_id')->setValueField('count') );
				foreach ( array_keys( static::$multitons ) as $k )
				{
					if ( !isset( static::$purchaseCounts[ $k ] ) )
					{
						static::$purchaseCounts[ $k ] = 0;
					}
				}
			}
			
			if ( !isset( static::$purchaseCounts[ $this->id ] ) )
			{
				static::$purchaseCounts[ $this->id ] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=?', 'downloads', 'file', $this->id ) )->first();
			}
			
			return static::$purchaseCounts[ $this->id ];
		}
		
		return NULL;
	}
	
	/**
	 * Get Renewal Term
	 *
	 * @return	\IPS\nexus\Purchase\RenewalTerm|NULL
	 */
	public function renewalTerm()
	{
		if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->idm_nexus_on and $this->renewal_term )
		{
			$renewalPrice = json_decode( $this->renewal_price, TRUE );
			$renewalPrice = \is_array( $renewalPrice ) ? $renewalPrice[ \IPS\nexus\Customer::loggedIn()->defaultCurrency() ] : array( 'currency' => \IPS\nexus\Customer::loggedIn()->defaultCurrency(), 'amount' => $renewalPrice );
			
			$tax = NULL;
			try
			{
				$tax = \IPS\Settings::i()->idm_nexus_tax ? \IPS\nexus\Tax::load( \IPS\Settings::i()->idm_nexus_tax ) : NULL;
			}
			catch ( \OutOfRangeException $e ) { }
			
			return new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( $renewalPrice['amount'], $renewalPrice['currency'] ), new \DateInterval( "P{$this->renewal_term}" . mb_strtoupper( $this->renewal_units ) ), $tax );
		}
		
		return NULL;
	}

	/**
	 * @brief	Cache of normal screenshots
	 */
	protected $_screenshotsNormal = NULL;

	/**
	 * @brief	Cache of thumbnails
	 */
	protected $_screenshotsThumbs = NULL;

	/**
	 * @brief	Cache of original screenshot images
	 */
	protected $_screenshotsOriginal = NULL;
	
	/**
	 * Get screenshots
	 *
	 * @param	int			$type			0 = Normal, 1 = Thumbnails, 2 = No watermark
	 * @param	bool		$includeLinks	If true, will include linked files
	 * @param	int|NULL	$version		If provided, will get the file records for a specific previous version (downloads_filebackup.b_id)
	 * @param 	bool		$pendingVersion	Only show screenshots from new pending version
	 * @return	\IPS\File\Iterator
	 */
	public function screenshots( $type=0, $includeLinks=TRUE, $version = NULL, $pendingVersion=FALSE )
	{
		$fileSizeField = null;
		switch ( $type )
		{
			case 0:
				if( $this->_screenshotsNormal !== NULL AND $pendingVersion === FALSE )
				{
					return $this->_screenshotsNormal;
				}
				$valueField = 'record_location';
				$property	= "_screenshotsNormal";
				$fileSizeField = 'record_size';
				break;
			case 1:
				if( $this->_screenshotsThumbs !== NULL AND $pendingVersion === FALSE )
				{
					return $this->_screenshotsThumbs;
				}
				$valueField = function( $row ) { return ( $row['record_type'] == 'sslink' ) ? 'record_location' : 'record_thumb'; };
				$property	= "_screenshotsThumbs";
				$fileSizeField = FALSE;
				break;
			case 2:
				if( $this->_screenshotsOriginal !== NULL AND $pendingVersion === FALSE )
				{
					return $this->_screenshotsOriginal;
				}
				$valueField = function( $row ) { return $row['record_no_watermark'] ? 'record_no_watermark' : 'record_location'; };
				$property	= "_screenshotsOriginal";
				break;
			default:
				throw new \InvalidArgumentException;
		}
		
		$where = array( array( 'record_file_id=?', $this->id ) );
		
		if ( $includeLinks )
		{
			$where[] = array( '( record_type=? OR record_type=? )', 'ssupload', 'sslink' );
		}
		else
		{
			$where[] = array( 'record_type=?', 'ssupload' );
		}
		
		if ( $version !== NULL )
		{
			$backup = \IPS\Db::i()->select( 'b_records', 'downloads_filebackup', array( 'b_id=?', $version ) )->first();
			$where[] = \IPS\Db::i()->in( 'record_id', explode( ',', $backup ) );
		}
		else
		{
			$where[] = array( 'record_backup=0' );
		}

		/* Ignore future versions */
		$where[] = array( 'record_hidden=?', $pendingVersion );

		$iterator = \IPS\Db::i()->select( 'record_id, record_location, record_thumb, record_no_watermark, record_default, record_type, record_realname, record_size', 'downloads_files_records', $where )->setKeyField( 'record_id' );
		$iterator = new \IPS\File\Iterator( $iterator, 'downloads_Screenshots', $valueField, FALSE, 'record_realname', $fileSizeField );
		$iterator = new \CachingIterator( $iterator, \CachingIterator::FULL_CACHE );

		/* Do not cache if loading pending version data */
		if( $pendingVersion === TRUE )
		{
			return $iterator;
		}

		$this->$property	= $iterator;
		return $this->$property;
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
		$count = 0;
		$images = array();

		foreach( $this->screenshots( 0, FALSE ) as $image )
		{
			if ( $count >= $limit )
			{
				break;
			}
			
			$images[] = array( 'downloads_Screenshots' => (string) $image );
			$count++;
		}

		if( $count < $limit )
		{
			$contentImages = parent::contentImages( $limit, $ignorePermissions ) ?: array();
			$images = array_merge( $images, $contentImages );
		}

		return \count( $images ) ? \array_slice( $images, 0, $limit ) : NULL;
	}
	
	/**
	 * @brief Cached primary screenshot
	 */
	protected $_primaryScreenshot	= FALSE;

	/**
	 * Get primary screenshot
	 *
	 * @return	\IPS\File|NULL
	 */
	public function get_primary_screenshot()
	{
		if( $this->_primaryScreenshot !== FALSE )
		{
			return $this->_primaryScreenshot;
		}
		
		/* isset() here returns FALSE if the value is null, which then results in the else block here never being triggered to pull the first screenshot it finds */
		if ( array_key_exists( 'primary_screenshot', $this->_data ) )
		{
			$screenshots = $this->screenshots();
			if ( $this->_data['primary_screenshot'] and isset( $screenshots[ $this->_data['primary_screenshot'] ] ) )
			{
				$this->_primaryScreenshot	= $screenshots[ $this->_data['primary_screenshot'] ];
				return $this->_primaryScreenshot;
			}
			else
			{
				foreach ( $screenshots as $id => $screenshot )
				{
					if ( !$this->_data['primary_screenshot'] or $id === $this->_data['primary_screenshot'] )
					{
						$this->_primaryScreenshot	= $screenshot;
						return $this->_primaryScreenshot;
					}
				}
			}
		}

		$this->_primaryScreenshot	= NULL;
		return $this->_primaryScreenshot;
	}

	/**
	 * @brief Cached primary screenshot thumb
	 */
	protected $_primaryScreenshotThumb	= FALSE;

	/**
	 * Get primary screenshot thumbnail
	 *
	 * @return \IPS\File|NULL
	 */
	public function get_primary_screenshot_thumb()
	{
		if( $this->_primaryScreenshotThumb !== FALSE )
		{
			return $this->_primaryScreenshotThumb;
		}

		$screenshots = $this->screenshots( 1 );
		if ( isset( $this->_data['primary_screenshot'] ) and isset( $screenshots[ $this->_data['primary_screenshot'] ] ) )
		{
			$this->_primaryScreenshotThumb	= $screenshots[ $this->_data['primary_screenshot'] ];
			return $this->_primaryScreenshotThumb;
		}
		else
		{
			foreach( $screenshots as $id => $screenshot )
			{
				if ( !$this->_data['primary_screenshot'] or $id === $this->_data['primary_screenshot'] )
				{
					$this->_primaryScreenshotThumb	= $screenshot;
					return $this->_primaryScreenshotThumb;
				}
			}
		}

		$this->_primaryScreenshotThumb	= NULL;
		return $this->_primaryScreenshotThumb;
	}
		
	/**
	 * @brief	Custom Field Cache
	 */
	protected $_customFields = NULL;
	
	/**
	 * @brief	Field Data Cache
	 */
	protected $_fieldData = NULL;
	
	/**
	 * Get custom field values
	 *
	 * @param	bool	$topic	Are we returning the custom fields for the topic? If so we need to apply the display formatting.
	 * @return	array
	 */
	public function customFields( $topic = FALSE )
	{
		$return = array();
		$fields = $this->container()->cfields;

		if( $topic === TRUE AND $this->_fieldData === NULL )
		{
			$this->_fieldData	= iterator_to_array( \IPS\Db::i()->select( '*', 'downloads_cfields', array( 'cf_topic=?', 1 ) )->setKeyField( 'cf_id' ) );
		}

		try
		{
			if ( $this->_customFields === NULL )
			{
				$this->_customFields = \IPS\Db::i()->select( '*', 'downloads_ccontent', array( 'file_id=?', $this->id ) )->first();
			}
			
			foreach ( $this->_customFields as $k => $v )
			{
				$fieldId = str_replace( 'field_', '', $k );

				/* If we're getting fields for the topic we need to skip any that are set to not be included */
				if( $topic === TRUE and !isset( $this->_fieldData[ $fieldId ] ) )
				{
					continue;
				}

				if ( array_key_exists( $fieldId, $fields ) )
				{
					if( $topic === TRUE )
					{
						$thisField = \IPS\downloads\Field::constructFromData( $this->_fieldData[ $fieldId ] );

						if( isset( $this->_fieldData[ $fieldId ] ) )
						{
							$v	= str_replace( '{content}', htmlspecialchars( $v, ENT_DISALLOWED, 'UTF-8', FALSE ), $thisField->displayValue( $v ) );
							$v	= str_replace( '{member_id}', \IPS\Member::loggedIn()->member_id, $v );
							$v	= str_replace( '{title}', \IPS\Member::loggedIn()->language()->addToStack( 'downloads_field_' . $fieldId ), $v );
						}
						else
						{
							$v	= htmlspecialchars( $v, ENT_DISALLOWED, 'UTF-8', FALSE );
						}
					}

					$return[ $k ] = $v;
				}
			}
		}
		catch( \UnderflowException $e ){}
		
		return $return;
	}
	
	/**
	 * Get available comment/review tabs
	 *
	 * @return	array
	 */
	public function commentReviewTabs()
	{
		$tabs = array();
		if ( $this->container()->bitoptions['reviews'] )
		{
			$tabs['reviews'] = \IPS\Member::loggedIn()->language()->addToStack( 'file_review_count', TRUE, array( 'pluralize' => array( $this->mapped('num_reviews') ) ) );
		}
		if ( $this->container()->bitoptions['comments'] )
		{
			$tabs['comments'] = \IPS\Member::loggedIn()->language()->addToStack( 'file_comment_count', TRUE, array( 'pluralize' => array( $this->mapped('num_comments') ) ) );
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
		if ( $tab === 'reviews' )
		{
			return \IPS\Theme::i()->getTemplate('view')->reviews( $this );
		}
		elseif( $tab === 'comments' )
		{
			return \IPS\Theme::i()->getTemplate('view')->comments( $this );
		}
		
		return '';
	}
	
	/**
	 * Should new items be moderated?
	 *
	 * @param	\IPS\Member		$member							The member posting
	 * @param	\IPS\Node\Model	$container						The container
	 * @param	bool			$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public static function moderateNewItems( \IPS\Member $member, \IPS\Node\Model $container = NULL, $considerPostBeforeRegistering = FALSE )
	{
		if ( $container and $container->bitoptions['moderation'] and !$member->group['g_avoid_q'] )
		{
			return !static::modPermission( 'approve', $member, $container );
		}
		
		return parent::moderateNewItems( $member, $container, $considerPostBeforeRegistering );
	}
	
	/**
	 * Should new comments be moderated?
	 *
	 * @param	\IPS\Member	$member							The member posting
	 * @param	bool		$considerPostBeforeRegistering	If TRUE, and $member is a guest, will check if a newly registered member would be moderated
	 * @return	bool
	 */
	public function moderateNewComments( \IPS\Member $member, $considerPostBeforeRegistering = FALSE )
	{
		$commentClass = static::$commentClass;
		return ( $this->container()->bitoptions['comment_moderation'] and !$member->group['g_avoid_q'] ) or parent::moderateNewComments( $member, $considerPostBeforeRegistering );
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
		return ( $this->container()->bitoptions['reviews_mod'] and !$member->group['g_avoid_q'] ) or parent::moderateNewReviews( $member, $considerPostBeforeRegistering );
	}
	
	/**
	 * Can view?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for or NULL for the currently logged in member
	 * @return	bool
	 */
	public function canView( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		if ( !$this->container()->open and !$member->isAdmin() )
		{
			return FALSE;
		}
		return parent::canView( $member );
	}
	
	/**
	 * Can edit?
	 * Authors can always edit their own files
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canEdit( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		if ( !$member->member_id )
		{
			return FALSE;
		}

		return ( $member->member_id == $this->author()->member_id ) or parent::canEdit( $member );
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
		if ( !$member->isAdmin() )
		{
			$where[] = array( 'downloads_categories.copen=1' );
			$joinContainer = TRUE;
		}
				
		return parent::getItemsWithPermission( $where, $order, $limit, $permissionKey, $includeHiddenItems, $queryFlags, $member, $joinContainer, $joinComments, $joinReviews, $countOnly, $joins, $skipPermission, $joinTags, $joinAuthor, $joinLastCommenter, $showMovedLinks );
	}
	
	/**
	 * Can a given member create this type of content?
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @param	bool		$showError	If TRUE, rather than returning a boolean value, will display an error
	 * @return	bool
	 */
	public static function canCreate( \IPS\Member $member, \IPS\Node\Model $container=NULL, $showError=FALSE )
	{
		if ( $member->idm_block_submissions )
		{
			if ( $showError )
			{
				\IPS\Output::i()->error( 'err_submissions_blocked', '1D168/1', 403, '' );
			}
			
			return FALSE;
		}

		return parent::canCreate( $member, $container, $showError );
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
		return parent::canReview( $member, $considerPostBeforeRegistering ) and !$this->mustDownloadBeforeReview( $member );
	}
	
	/**
	 * Member has to download before they can review?
	 *
	 * @param	\IPS\Member\NULL	$member		The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function mustDownloadBeforeReview( \IPS\Member $member = NULL )
	{
		if ( $this->container()->bitoptions['reviews_download'] )
		{
			try
			{
				\IPS\Db::i()->select( '*', 'downloads_downloads', array( 'dfid=? AND dmid=?', $this->id, $member ? $member->member_id : \IPS\Member::loggedIn()->member_id ) )->first();
			}
			catch ( \UnderflowException $e )
			{
				return TRUE;
			}
		}
		return FALSE;
	}
	
	/**
	 * Can change author?
	 *
	 * @param	\IPS\Member\NULL	$member	The member (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canChangeAuthor( \IPS\Member $member = NULL )
	{
		return static::modPermission( 'edit', $member, $this->container() );
	}

	/**
	 * Change Author
	 *
	 * @param	\IPS\Member	$newAuthor	The new author
	 * @param	bool		$log		If TRUE, action will be logged to moderator log
	 * @return	void
	 */
	public function changeAuthor( \IPS\Member $newAuthor, $log=TRUE )
	{
		if ( \IPS\Application::appIsEnabled( 'nexus' ) )
		{
			\IPS\Db::i()->update( 'nexus_purchases', array( 'ps_pay_to' => $newAuthor->member_id ), array( 'ps_app=? AND ps_type=? AND ps_item_id=?', 'downloads', 'file', $this->id ) );
		}
		
		$this->topicChangeAuthor( $newAuthor, $log );
	}
	
	/**
	 * @brief	Can download?
	 */
	protected $canDownload = NULL;
	
	/**
	 * Can the member download this file?
	 *
	 * @param	\IPS\Member|NULL	$member		The member to check or NULL for currently logged in member
	 * @return	bool
	 */
	public function canDownload( \IPS\Member $member = NULL )
	{
		if ( $this->canDownload === NULL )
		{
			try
			{
				$this->downloadCheck( NULL, $member );
				$this->canDownload = TRUE;
			}
			catch ( \DomainException $e )
			{
				$this->canDownload = FALSE;
			}
		}
		
		return $this->canDownload;
	}

	/**
	 * @brief	Requires download confirmation? Used to determine if CSRF key should be included in download URL.
	 */
	protected $requiresDownloadConfirmation = NULL;
	
	/**
	 * Can the member download this file?
	 *
	 * @param	\IPS\Member|NULL	$member		The member to check or NULL for currently logged in member
	 * @return	bool
	 */
	public function requiresDownloadConfirmation( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		if ( $this->requiresDownloadConfirmation === NULL )
		{
			$this->requiresDownloadConfirmation = FALSE;

			$category = $this->container();
			
			if( $category->message('disclaimer') and \in_array( $category->disclaimer_location, [ 'download', 'both' ] ) )
			{
				$this->requiresDownloadConfirmation = TRUE;
			}

			$version = \IPS\Request::i()->changelog ?? \IPS\Request::i()->version ?? NULL;
			$files = $this->files( $version );

			if( \count( $files ) > 1 AND !isset( \IPS\Request::i()->r ) )
			{
				$this->requiresDownloadConfirmation = TRUE;
			}

			if( $member->group['idm_wait_period'] AND ( !$this->isPaid() OR $member->group['idm_paid_restrictions'] ) )
			{
				$this->requiresDownloadConfirmation = TRUE;
			}
		}
		
		return $this->requiresDownloadConfirmation;
	}
	
	/**
	 * Can the member buy this file?
	 *
	 * @param	\IPS\Member|NULL	$member		The member to check or NULL for currently logged in member
	 * @return	bool
	 */
	public function canBuy( \IPS\Member $member = NULL )
	{
		/* Is this a paid file? */
		if ( !$this->isPaid() )
		{
			return FALSE;
		}
		
		/* Init */
		$member = $member ?: \IPS\Member::loggedIn();
		$restrictions = json_decode( $member->group['idm_restrictions'], TRUE );

        /* File author */
        if( $member == $this->author() )
        {
            return FALSE;
        }
		
		/* Basic permission check */
		if ( !$this->container()->can( 'download', $member ) )
		{
			/* Hold on - if we're a guest and buying means we'll have to register which will put us in a group with permission, we can continue */
			if ( $member->member_id or !$this->container()->can( 'download', \IPS\Member\Group::load( \IPS\Settings::i()->member_group ) ) )
			{
				return FALSE;
			}
		}
		
		/* If restrictions aren't applying to Paid files, stop here */
		if ( !$member->group['idm_paid_restrictions'] )
		{
			return TRUE;
		}
		
		/* Minimum posts */
		if ( $member->member_id and $restrictions['min_posts'] and $restrictions['min_posts'] > $member->member_posts )
		{
			return FALSE;
		}

		/* Is this an associated file ? */
		if ( $this->nexus )
		{
			$productIds = explode( ',', $this->nexus );

			foreach ( $productIds as $productId )
			{
				try
				{
					$package = \IPS\nexus\Package::load( $productId );

					try
					{
						/* The method does not return anything, but throws an exception if we cannot buy */
						$package->memberCanPurchase( $member );
					}
					catch ( \DomainException $e )
					{
						return FALSE;
					}
				}
				catch ( \OutOfRangeException $e )
				{
					return FALSE;
				}
			}
		}
		
		return TRUE;
	}
	
	/**
	 * Purchases that can be renewed
	 *
	 * @param	\IPS\Member|NULL	$member		The member to check or NULL for currently logged in member
	 * @return	array
	 */
	public function purchasesToRenew( \IPS\Member $member = NULL )
	{
		/** return an empty array if we don't have commerce */
		if ( !\IPS\Application::appIsEnabled( 'nexus' ) )
		{
			return array();
		}
		$member = $member ?: \IPS\Member::loggedIn();
		
		$return = array();

		foreach ( \IPS\downloads\extensions\nexus\Item\File::getPurchases( \IPS\nexus\Customer::load( $member->member_id ), $this->id ) as $purchase )
		{
			if ( !$purchase->active and $purchase->canRenewUntil() !== FALSE )
			{
				$return[] = $purchase;
			}
		}
		return $return;
	}
	
	/**
	 * Download check
	 *
	 * @param	array|NULL			$record		Specific record to download
	 * @param	\IPS\Member|NULL	$member		The member to check or NULL for currently logged in member
	 * @return	void
	 * @throws	\DomainException
	 */
	public function downloadCheck( array $record = NULL, \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		$restrictions = json_decode( $member->group['idm_restrictions'], TRUE );
		
		/* Basic permission check */
		if ( !$this->container()->can( 'download', $member ) )
		{
			throw new \DomainException( $this->container()->message('npd') ?: 'download_no_perm' );
		}

		/* If the file is hidden and this isn't a moderator, they can't access regardless of 'view' permission */
		if ( $this->hidden() and !static::canViewHiddenItems( $member, $this->containerWrapper() ) and ( $this->hidden() !== 1 or $this->author()->member_id !== $member->member_id ) )
		{
			throw new \DomainException( $this->container()->message('npd') ?: 'download_no_perm' );
		}
		
		/* Paid? */
		if ( $this->isPaid() )
		{
			/* Guests can't download paid files */
			if ( !$member->member_id )
			{
				throw new \DomainException( $this->container()->message('npd') ?: 'download_no_perm' );
			}

			if ( !$member->group['idm_bypass_paid'] and $member->member_id != $this->author()->member_id )
			{
				if ( $this->cost )
				{
					if ( !\count( \IPS\downloads\extensions\nexus\Item\File::getPurchases( \IPS\nexus\Customer::load( $member->member_id ), $this->id, FALSE ) ) )
					{
						throw new \DomainException( 'file_not_purchased' );
					}
				}
				elseif ( $this->nexus )
				{
					if ( !\count( \IPS\nexus\extensions\nexus\Item\Package::getPurchases( \IPS\nexus\Customer::load( $member->member_id ), explode( ',', $this->nexus ), FALSE ) ) )
					{
						throw new \DomainException( 'file_not_purchased' );
					}
				}
			}
			
			/* If restrictions aren't applying to Paid files, stop here */
			if ( !$member->group['idm_paid_restrictions'] )
			{
				return;
			}
		}

		
		/* Minimum posts */
		if ( $member->member_id and isset( $restrictions['min_posts'] ) and $restrictions['min_posts'] and $restrictions['min_posts'] > $member->member_posts )
		{
			throw new \DomainException( $member->language()->addToStack( 'download_min_posts', FALSE, array( 'pluralize' => array( $restrictions['min_posts'] ) ) ) );
		}
		
		/* Simultaneous downloads */
		if ( isset( $restrictions['min_posts'] ) AND $restrictions['limit_sim'] )
		{
			if ( $this->getCurrentDownloadSessions( $member ) >= $restrictions['limit_sim'] )
			{
				throw new \DomainException( $member->language()->addToStack( 'max_simultaneous_downloads', FALSE, array( 'pluralize' => array( $restrictions['limit_sim'] ) ) ) );
			}
		}
				
		/* For bandwidth checks, we need a record. If we don't have one - use the one with the smallest filesize */
		if ( !$record )
		{
			$it = $this->files();
			foreach ( $it as $file )
			{
				$data = $it->data();
				if ( !$record or $record['record_size'] > $data['record_size'] )
				{
					$record = $data;
				}
			}
		}
		
		/* Bandwidth & Download limits */
		$logWhere = $member->member_id ? array( 'dmid=?', $member->member_id ) : array( 'dip=?', \IPS\Request::i()->ipAddress() );
		foreach ( array( 'daily' => 'P1D', 'weekly' => 'P1W', 'monthly' => 'P1M' ) as $k => $interval )
		{
			$timePeriodWhere = array( $logWhere, array( 'dtime>?', \IPS\DateTime::create()->sub( new \DateInterval( $interval ) )->getTimestamp() ) );
			
			/* Bandwidth */
			if ( isset( $restrictions[ $k . '_bw' ] ) AND $restrictions[ $k . '_bw' ] )
			{
				$usedThisPeriod = \IPS\Db::i()->select( 'SUM(dsize)', 'downloads_downloads', $timePeriodWhere )->first();
				if ( ( $record['record_size'] + $usedThisPeriod ) > ( $restrictions[ $k . '_bw' ] * 1024 ) )
				{
					if ( $record['record_size'] > ( $restrictions[ $k . '_bw' ] * 1024 ) )
					{
						throw new \DomainException( $member->language()->addToStack( 'bandwidth_limit_' . $k . '_never', FALSE, array( 'sprintf' => array( \IPS\Output\Plugin\Filesize::humanReadableFilesize( $restrictions[ $k . '_bw' ] * 1024 ), \IPS\Output\Plugin\Filesize::humanReadableFilesize( $record['record_size'] ) ) ) ) );
					}
					else
					{
						$date = new \IPS\DateTime;
						foreach ( \IPS\Db::i()->select( '*', 'downloads_downloads', $timePeriodWhere, 'dtime ASC' ) as $log )
						{
							$usedThisPeriod -= $log['dsize'];
							if ( ( $record['record_size'] + $usedThisPeriod ) < ( $restrictions[ $k . '_bw' ] * 1024 ) )
							{
								$date = \IPS\DateTime::ts( $log['dtime'] );
								break;
							}
						}
												
						throw new \DomainException( $member->language()->addToStack( 'bandwidth_limit_' . $k, FALSE, array( 'sprintf' => array( \IPS\Output\Plugin\Filesize::humanReadableFilesize( $restrictions[ $k . '_bw' ] * 1024 ), (string) $date->add( new \DateInterval( $interval ) ) ) ) ) );
					}
				}
			}
			
			/* Download */
			if ( isset( $restrictions[ $k . '_dl' ] ) AND $restrictions[ $k . '_dl' ] )
			{
				try
				{
					$downloadsThisPeriod = \IPS\Db::i()->select( 'COUNT(*)', 'downloads_downloads', $timePeriodWhere )->first();
				}
				catch( \UnderflowException $e )
				{
					$downloadsThisPeriod = 0;
				}

				if( $downloadsThisPeriod >= $restrictions[ $k . '_dl' ] )
				{
					throw new \DomainException( $member->language()->addToStack( 'download_limit_' . $k, FALSE, array( 'pluralize' => array( $restrictions[ $k . '_dl' ] ), 'sprintf' => array( (string) \IPS\DateTime::ts( \IPS\Db::i()->select( 'dtime', 'downloads_downloads', $timePeriodWhere, 'dtime ASC', array( 0, 1 ) )->first() )->add( new \DateInterval( $interval ) ) ) ) ) );
				}
			}
		}
	}

	/**
	 * @brief Cached number of current download sessions
	 */
	protected $_currentDownloadSessions = array();

	/**
	 * Get the current number of download sessions
	 *
	 * @param	\IPS\Member		$member		Member to check
	 * @return int
	 */
	public function getCurrentDownloadSessions( $member )
	{
		if( !array_key_exists( $member->member_id, $this->_currentDownloadSessions ) )
		{
			$this->_currentDownloadSessions[ $member->member_id ] = \IPS\Db::i()->select( 'COUNT(*)', 'downloads_sessions', array( array( 'dsess_start > ?', time() - ( 60 * 15 ) ), $member->member_id ? array( 'dsess_mid=?', $member->member_id ) : array( 'dsess_ip=?', \IPS\Request::i()->ipAddress() ) ) )->first();
		}

		return $this->_currentDownloadSessions[ $member->member_id ];
	}
	
	/**
	 * Can view downloaders?
	 *
	 * @param	\IPS\Member|NULL	$member		The member to check or NULL for currently logged in member
	 * @return	bool
	 */
	public function canViewDownloaders( \IPS\Member $member = NULL )
	{
		if ( $this->container()->log === 0 )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		if ( $member->member_id == $this->author()->member_id and $this->container()->bitoptions['submitter_log'] )
		{
			return TRUE;
		}
				
		return $member->group['idm_view_downloads'];
	}

	/**
	 * Get elements for add/edit form
	 *
	 * @param	\IPS\Content\Item|NULL	$item		The current item if editing or NULL if creating
	 * @param	\IPS\Node\Model|NULL 	$container	Container (e.g. forum) ID, if appropriate
	 * @param	string|NULL				$bulkKey	If we are submitting multiple files at once, a key that is used to differentiate between which fields are for which files
	 * @return	array
	 */
	public static function formElements( $item=NULL, \IPS\Node\Model $container=NULL, $bulkKey = '' )
	{
		/* Init */
		$return = [];
		foreach ( parent::formElements( $item, $container ) as $k => $input )
		{
			$input->name = "{$bulkKey}{$input->name}";
			$return[ $k ] = $input;
		}

		/* Description */
		$return['description'] = new \IPS\Helpers\Form\Editor( "{$bulkKey}file_desc", $item ? $item->desc : NULL, TRUE, array( 'app' => 'downloads', 'key' => 'Downloads', 'autoSaveKey' => ( $item ? "downloads-file-{$item->id}" : "{$bulkKey}downloads-new-file" ), 'attachIds' => ( $item === NULL ? NULL : array( $item->id, NULL, 'desc' ) ) ), '\IPS\Helpers\Form::floodCheck' );

		/* Edit Log Fields need to be under the editor */
		$editReason = NULL;
		if( isset( $return['edit_reason']) )
		{
			$editReason = $return['edit_reason'];
			unset( $return['edit_reason'] );
			$return['edit_reason'] = $editReason;
		}

		$logEdit = NULL;
		if( isset( $return['log_edit']) )
		{
			$logEdit = $return['log_edit'];
			unset( $return['log_edit'] );
			$return['log_edit'] = $logEdit;
		}

		/* Primary screenshot */
		if ( $item )
		{
			$screenshotOptions = array();
			foreach ( \IPS\Db::i()->select( '*', 'downloads_files_records', array( 'record_file_id=? AND record_type=? AND record_backup=0', $item->id, 'ssupload' ) ) as $ss )
			{
				$screenshotOptions[ $ss['record_id'] ] = \IPS\File::get( 'downloads_Screenshots', $ss['record_location'] )->url;
			}

			if ( \count( $screenshotOptions ) > 1 )
			{
				$return['primary_screenshot'] = new \IPS\Helpers\Form\Radio( "{$bulkKey}file_primary_screenshot", $item->_primary_screenshot, FALSE, array( 'options' => $screenshotOptions, 'parse' => 'image' ) );
			}
		}
		
		/* Nexus Integration */
		if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->idm_nexus_on and \IPS\Member::loggedIn()->group['idm_add_paid'] )
		{
			$options = array(
				'free'		=> 'file_free',
				'paid'		=> 'file_paid',
			);
			if ( \IPS\Member::loggedIn()->isAdmin() AND \count( \IPS\nexus\Package::roots() ) > 0 )
			{
				$options['nexus'] = 'file_associate_nexus';
			}
			
			$return['file_cost_type'] = new \IPS\Helpers\Form\Radio( "{$bulkKey}file_cost_type", $item ? ( $item->cost ? 'paid' : ( $item->nexus ? 'nexus' : 'free' ) ) : 'free', TRUE, array(
				'options'	=> $options,
				'toggles'	=> array(
					'paid'		=> array( "{$bulkKey}file_cost", "{$bulkKey}file_renewals" ),
					'nexus'		=> array( "{$bulkKey}file_nexus" )
				)
			) );
			
			$commissionBlurb = NULL;
			$fees = NULL;
			if ( $_fees = json_decode( \IPS\Settings::i()->idm_nexus_transfee, TRUE ) )
			{
				$fees = array();
				foreach ( $_fees as $fee )
				{
					$fees[] = (string) ( new \IPS\nexus\Money( $fee['amount'], $fee['currency'] ) );
				}
				$fees = \IPS\Member::loggedIn()->language()->formatList( $fees, \IPS\Member::loggedIn()->language()->get('or_list_format') );
			}
			if ( \IPS\Settings::i()->idm_nexus_percent and $fees )
			{
				$commissionBlurb = \IPS\Member::loggedIn()->language()->addToStack( 'file_cost_desc_both', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->idm_nexus_percent, $fees ) ) );
			}
			elseif ( \IPS\Settings::i()->idm_nexus_percent )
			{
				$commissionBlurb = \IPS\Member::loggedIn()->language()->addToStack('file_cost_desc_percent', FALSE, array( 'sprintf' => \IPS\Settings::i()->idm_nexus_percent ) );
			}
			elseif ( $fees )
			{
				$commissionBlurb = \IPS\Member::loggedIn()->language()->addToStack('file_cost_desc_fee', FALSE, array( 'sprintf' => $fees ) );
			}

			$minimums = json_decode( \IPS\Settings::i()->idm_nexus_mincost, true );
			$minCosts = [];
			foreach( $minimums as $currency => $cost )
			{
				if( ( new \IPS\Math\Number( $minimums[ $currency ]['amount'] ) )->isGreaterThanZero() )
				{
					$minCosts[] = (string) ( new \IPS\nexus\Money( $minimums[ $currency ]['amount'], $currency ) );
				}
			}

			if( \count( $minCosts ) )
			{
				$commissionBlurb .= \IPS\Member::loggedIn()->language()->addToStack('file_cost_desc_minimum', FALSE, array( 'sprintf' => \IPS\Member::loggedIn()->language()->formatList( $minCosts ) ) );
			}
			
			\IPS\Member::loggedIn()->language()->words['file_cost_desc'] = $commissionBlurb;

			$return['file_cost'] = new \IPS\nexus\Form\Money( "{$bulkKey}file_cost", $item ? json_decode( $item->cost, TRUE ) : array(), NULL, array(), function( $val ) use ( $minimums, $bulkKey ) {
				foreach( $val as $currency => $money )
				{
					if( isset( $minimums[ $currency ]['amount'] ) AND $money->amount->compare( new \IPS\Math\Number( $minimums[ $currency ]['amount'] ) ) === -1 )
					{
						throw new \DomainException('file_cost_too_low');
					}
				}
			}, NULL, NULL, "{$bulkKey}file_cost" );
			$return['file_renewals']  = new \IPS\Helpers\Form\Radio( "{$bulkKey}file_renewals", $item ? ( $item->renewal_term ? 1 : 0 ) : 0, TRUE, array(
				'options'	=> array( 0 => 'file_renewals_off', 1 => 'file_renewals_on' ),
				'toggles'	=> array( 1 => array( "{$bulkKey}file_renewal_term" ) )
			), NULL, NULL, NULL, "{$bulkKey}file_renewals" );
			\IPS\Member::loggedIn()->language()->words['file_renewal_term_desc'] = $commissionBlurb;
			$renewTermForEdit = NULL;
			if ( $item and $item->renewal_term )
			{
				$renewPrices = array();
				foreach ( json_decode( $item->renewal_price, TRUE ) as $currency => $data )
				{
					$renewPrices[ $currency ] = new \IPS\nexus\Money( $data['amount'], $currency );
				}
				$renewTermForEdit = new \IPS\nexus\Purchase\RenewalTerm( $renewPrices, new \DateInterval( 'P' . $item->renewal_term . mb_strtoupper( $item->renewal_units ) ) );
			}
			$return['file_renewal_term'] = new \IPS\nexus\Form\RenewalTerm( "{$bulkKey}file_renewal_term", $renewTermForEdit, NULL, array( 'allCurrencies' => TRUE ), NULL, NULL, NULL, "{$bulkKey}file_renewal_term" );

			if ( \IPS\Member::loggedIn()->isAdmin() AND \count( \IPS\nexus\Package::roots() ) > 0 )
			{
				$return['file_nexus'] = new \IPS\Helpers\Form\Node( "{$bulkKey}file_nexus", $item ? $item->nexus : array(), FALSE, array( 'class' => '\IPS\nexus\Package', 'multiple' => TRUE ), NULL, NULL, NULL, "{$bulkKey}file_nexus" );
			}
		}
		
		/* Custom Fields */
		$customFieldValues = $item ? $item->customFields() : array();
		foreach ( $container->cfields as $k => $field )
		{
			$_id = $field->id;
			$field->id = "{$bulkKey}{$field->id}";
			if ( $field->type === 'Editor' )
			{
				if ( $field->allow_attachments AND $item )
				{
					$attachIds = array( $item->id, $_id, 'fields' );
					$field::$editorOptions = array_merge( $field::$editorOptions, array( 'attachIds' => $attachIds ) );
				}
			}
			$helper = $field->buildHelper( isset( $customFieldValues[ "field_{$k}" ] ) ? $customFieldValues[ "field_{$k}" ] : NULL, NULL, $item );
			$helper->label = \IPS\Member::loggedIn()->language()->addToStack( 'downloads_field_' . $_id );
			$field->id = $_id;
			if ( $field->type === 'Editor' )
			{
				if ( $field->allow_attachments AND !$item )
				{
					$field::$editorOptions = array_merge( $field::$editorOptions, array( 'attachIds' => NULL ) );
				}
			}
			$return[] = $helper;
		}

		if( $item )
		{
			$return['versioning']	= new \IPS\Helpers\Form\Custom( "{$bulkKey}file_versioning_info", NULL, FALSE, array( 'getHtml' => function( $element ) use ( $item )
			{
				return \IPS\Theme::i()->getTemplate( 'submit' )->editDetailsInfo( $item );
			} ) );
		}
		
		return $return;
	}
	
	/**
	 * Process create/edit form
	 *
	 * @param	array				$values	Values from form
	 * @return	void
	 */
	public function processForm( $values )
	{
		$new = $this->_new;

		parent::processForm( $values );

		if ( !$new )
		{
			$oldContent = $this->desc;
		}
		$this->desc	= $values['file_desc'];
		
		$imageUploads = isset( $values['files'] ) ? $values['files'] : [];
		$editorLookups = $new ? [ ( isset( $values['_bulkKey'] ) ? $values['_bulkKey'] : '' ) . 'downloads-new-file'] : [ [ $this->id, NULL, 'desc' ] ];
		if ( isset( $values['screenshots'] ) and $values['screenshots'] )
		{
			$imageUploads = array_merge( $imageUploads, $values['screenshots'] );
		}
		foreach ( $this->container()->cfields as $field )
		{
			$helper = $field->buildHelper( NULL, NULL, $new ? NULL : $this );
			if ( $helper instanceof \IPS\Helpers\Form\Upload )
			{
				if ( \is_array( $values["downloads_field_{$field->id}"] ) )
				{
					$imageUploads = array_merge( $imageUploads, $values["downloads_field_{$field->id}"] );
				}
				elseif ( $values["downloads_field_{$field->id}"] )
				{
					$imageUploads[] = $values["downloads_field_{$field->id}"];
				}
			}
			elseif ( $helper instanceof \IPS\Helpers\Form\Editor )
			{
				$editorLookups[] = $new ? md5( 'IPS\downloads\Field-' . ( isset( $values['_bulkKey'] ) ? $values['_bulkKey'] : '' ) . $field->id . '-new' ) : [ $this->id, $field->id, 'fields' ];
			}
		}
		$sendFilterNotifications = $this->checkProfanityFilters( FALSE, !$new, NULL, NULL, 'downloads_Downloads', $editorLookups, $imageUploads );

		if ( !$new AND $sendFilterNotifications === FALSE )
		{
			$this->sendAfterEditNotifications( $oldContent );
		}

		if( isset( $values['file_primary_screenshot'] ) )
		{
			$this->primary_screenshot	= (int) $values['file_primary_screenshot'];
		}
		
		if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->idm_nexus_on and \IPS\Member::loggedIn()->group['idm_add_paid'] )
		{
			switch ( $values['file_cost_type'] )
			{
				case 'free':
					$this->cost = NULL;
					$this->renewal_term = 0;
					$this->renewal_units = NULL;
					$this->renewal_price = NULL;
					$this->nexus = NULL;
					break;
				
				case 'paid':
					$this->cost = json_encode( $values['file_cost'] );
					if ( $values['file_renewals'] and $values['file_renewal_term'] )
					{						
						$term = $values['file_renewal_term']->getTerm();
						$this->renewal_term = $term['term'];
						$this->renewal_units = $term['unit'];
						$this->renewal_price = json_encode( $values['file_renewal_term']->cost );
					}
					else
					{
						$this->renewal_term = 0;
						$this->renewal_units = NULL;
						$this->renewal_price = NULL;
					}
					$this->nexus = NULL;
					break;
				
				case 'nexus':
					$this->cost = NULL;
					$this->renewal_term = 0;
					$this->renewal_units = NULL;
					$this->renewal_price = NULL;
					$this->nexus = implode( ',', array_keys( $values['file_nexus'] ) );
					break;
			}
		}
		
		$this->save();
		$cfields = array();
		foreach ( $this->container()->cfields as $field )
		{
			$helper							 = $field->buildHelper( NULL, NULL, $new ? NULL : $this );
			if ( $helper instanceof \IPS\Helpers\Form\Upload )
			{
				$cfields[ "field_{$field->id}" ] = (string) $values[ "downloads_field_{$field->id}" ];
			}
			else
			{
				$cfields[ "field_{$field->id}" ] = $helper::stringValue( $values[ "downloads_field_{$field->id}" ] );
			}
			
			if ( $helper instanceof \IPS\Helpers\Form\Editor )
			{
				$field->claimAttachments( $this->id, 'fields' );
			}
		}
		
		if ( !empty( $cfields ) )
		{
			\IPS\Db::i()->insert( 'downloads_ccontent', array_merge( array( 'file_id' => $this->id, 'updated' => time() ), $cfields ), TRUE );
		}
		
		/* Update Category */
		$this->container()->setLastFile( ( $new and $this->open ) ? $this : NULL );
		$this->container()->save();
	}
	
	/**
	 * Process created object BEFORE the object has been created
	 *
	 * @param	array	$values	Values from form
	 * @return	void
	 */
	protected function processBeforeCreate( $values )
	{
		/* Set version */
		$this->version = ( isset( $values['file_version'] ) ) ? $values['file_version'] : NULL;
		
		/* Try to set the primary screenshot */
		try
		{
			$this->primary_screenshot = \IPS\Db::i()->select( 'record_id', 'downloads_files_records', array( 'record_post_key=? AND ( record_type=? or record_type=? ) AND record_backup=0', $values['postKey'], 'ssupload', 'sslink' ), 'record_default DESC, record_id ASC' )->first();
		}
		catch ( \Exception $e ) { }

		parent::processBeforeCreate( $values );
	}
	
	/**
	 * Process created object AFTER the object has been created
	 *
	 * @param	\IPS\Content\Comment|NULL	$comment	The first comment
	 * @param	array						$values		Values from form
	 * @return	void
	 */
	protected function processAfterCreate( $comment, $values )
	{
		\IPS\File::claimAttachments( 'downloads-new-file', $this->id, NULL, 'desc' );
		
		if ( $this->_primary_screenshot )
		{
			\IPS\Db::i()->update( 'downloads_files_records', array( 'record_default' => 1 ), array( 'record_id=?', $this->_primary_screenshot ) );
		}
		\IPS\Db::i()->update( 'downloads_files_records', array( 'record_file_id' => $this->id, 'record_post_key' => NULL ), array( 'record_post_key=?', $values['postKey'] ) );
		$this->size = (int) \IPS\Db::i()->select( 'SUM(record_size)', 'downloads_files_records', array( 'record_file_id=? AND record_type=? AND record_backup=0', $this->id, 'upload' ) )->first();
		$this->save();
		
		parent::processAfterCreate( $comment, $values );
	}
	
	/**
	 * Process after the object has been edited on the front-end
	 *
	 * @param	array	$values		Values from form
	 * @return	void
	 */
	public function processAfterEdit( $values )
	{
		parent::processAfterEdit( $values );

		\IPS\Request::i()->setClearAutosaveCookie( 'downloads-file-' . $this->id );
		foreach ( $this->container()->cfields as $field )
		{
			\IPS\Request::i()->setClearAutosaveCookie( md5( 'IPS\downloads\Field-' . $field->id . '-' . $this->id ) );
		}
	}

	/**
	 * Log for deletion later
	 *
	 * @param	\IPS\Member|NULL 	$member	The member, NULL for currently logged in, or FALSE for no member
	 * @return	void
	 */
	public function logDelete( $member = NULL )
	{
		parent::logDelete( $member );

		if ( $topic = $this->topic() and $this->container()->bitoptions['topic_delete'] )
		{
			$topic->logDelete( $member );
		}

		if ( $this->hasPendingVersion() )
		{
			\IPS\downloads\File\PendingVersion::load( $this->id, 'pending_file_id' )->delete();
		}
	}
	
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		if ( $topic = $this->topic() and $this->container()->bitoptions['topic_delete'] )
		{
			$topic->delete();
		}
		
		if ( \IPS\Application::appIsEnabled( 'nexus' ) )
		{
			\IPS\Db::i()->update( 'nexus_purchases', array( 'ps_cancelled' => TRUE, 'ps_can_reactivate' => FALSE ), array( 'ps_app=? AND ps_type=? AND ps_item_id=?', 'downloads', 'file', $this->id ) );
		}
		
		parent::delete();
				
		foreach ( new \IPS\File\Iterator( \IPS\Db::i()->select( 'record_location', 'downloads_files_records', array( 'record_file_id=? AND record_type=?', $this->id, 'upload' ) ), 'downloads_Files' ) as $file )
		{
			try
			{
				$file->delete();
			}
			catch ( \Exception $e ) { }
		}

		foreach ( new \IPS\File\Iterator( \IPS\Db::i()->select( 'record_location', 'downloads_files_records', array( 'record_file_id=? AND record_type=?', $this->id, 'ssupload' ) ), 'downloads_Screenshots' ) as $file )
		{
			try
			{
				$file->delete();
			}
			catch ( \Exception $e ) { }
		}

		foreach ( new \IPS\File\Iterator( \IPS\Db::i()->select( 'record_thumb', 'downloads_files_records', array( 'record_file_id=? AND record_type=? AND record_thumb IS NOT NULL', $this->id, 'ssupload' ) ), 'downloads_Screenshots' ) as $file )
		{
			try
			{
				$file->delete();
			}
			catch ( \Exception $e ) { }
		}

		foreach ( new \IPS\File\Iterator( \IPS\Db::i()->select( 'record_no_watermark', 'downloads_files_records', array( 'record_file_id=? AND record_type=? AND record_no_watermark IS NOT NULL', $this->id, 'ssupload' ) ), 'downloads_Screenshots' ) as $file )
		{
			try
			{
				$file->delete();
			}
			catch ( \Exception $e ) { }
		}
		
		\IPS\Db::i()->delete( 'downloads_ccontent', array( 'file_id=?', $this->id ) );
		\IPS\Db::i()->delete( 'downloads_downloads', array( 'dfid=?', $this->id ) );
		\IPS\Db::i()->delete( 'downloads_filebackup', array( 'b_fileid=?', $this->id ) );
		\IPS\Db::i()->delete( 'downloads_files_records', array( 'record_file_id=?', $this->id ) );
		\IPS\Db::i()->delete( 'downloads_files_notify', array( 'notify_file_id=?', $this->id ) );

		/* Delete pending version */
		if( $this->hasPendingVersion() )
		{
			\IPS\downloads\File\PendingVersion::load($this->id, 'pending_file_id' )->delete();
		}

		/* Update Category */
		$this->container()->setLastFile();
		$this->container()->save();
	}

	/**
	 * Delete Records
	 *
	 * @param 	int|array		$ids		Must be INT if url and handler are provided
	 * @param 	string			$url		Record location
	 * @param	string			$handler	File storage handler key
	 */
	public function deleteRecords( $ids, string $url=NULL, string $handler=NULL )
	{
		if( $ids AND $handler AND $url )
		{
			try
			{
				if( !\IPS\Db::i()->select( 'COUNT(record_id)', 'downloads_files_records', array( 'record_id<>? AND record_location=?', $ids, $url ) )->first() )
				{
					\IPS\File::get( $handler, $url )->delete();
				}
			}
			catch ( \Exception $e ) { }
			\IPS\Db::i()->delete( 'downloads_files_records', array( 'record_id=? AND record_location=?', $ids, $url ) );
		}
		else
		{
			if( $ids !== NULL AND !\is_array( $ids ) )
			{
				$ids = array( $ids );
			}

			\IPS\Db::i()->delete( 'downloads_files_records', array( \IPS\Db::i()->in( 'record_id', $ids ) ) );
		}
	}

	/**
	 * URL Blacklist Check
	 *
	 * @param	array	$val	URLs to check
	 * @return	void
	 * @throws	\DomainException
	 */
	public static function blacklistCheck( $val )
	{
		if ( \is_array( $val ) )
		{
			foreach ( explode( ',', \IPS\Settings::i()->idm_link_blacklist ) as $blackListedDomain )
			{
				foreach ( array_filter( $val ) as $url )
				{
					if ( \is_string( $url ) )
					{
						$url = \IPS\Http\Url::external( $url );
					}
					
					if ( mb_substr( $url->data['host'], -mb_strlen( $blackListedDomain ) ) == $blackListedDomain )
					{
						throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'err_url_file_blacklist', FALSE, array( 'sprintf' => $blackListedDomain ) ) );
					}
				}
			}
		}
	}

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
			return parent::supportsComments() and $container->bitoptions['comments'] AND ( !$member or $container->can( 'read', $member ) );
		}
		else
		{
			return parent::supportsComments() and ( !$member or \IPS\downloads\Category::countWhere( 'read', $member, array( 'cbitoptions & 4' ) ) );
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
			return parent::supportsReviews() and $container->bitoptions['reviews'] AND ( !$member or $container->can( 'read', $member ) );
		}
		else
		{
			return parent::supportsReviews() and ( !$member or \IPS\downloads\Category::countWhere( 'read', $member, array( 'cbitoptions & 256' ) ) );
		}
	}
	
	/**
	 * Save the current files/screenshots into the backup in preparation for storing a new version
	 *
	 * @return	void
	 */
	public function saveVersion()
	{
		/* Move the old details into a backup record */
		$b_id = \IPS\Db::i()->insert( 'downloads_filebackup', array(
			'b_fileid'		=> $this->id,
			'b_filetitle'	=> $this->name,
			'b_filedesc'	=> $this->desc,
			'b_hidden'		=> FALSE,
			'b_backup'		=> $this->published,
			'b_updated'		=> time(),
			'b_records'		=> implode( ',', iterator_to_array( \IPS\Db::i()->select( 'record_id', 'downloads_files_records', array( 'record_file_id=? AND record_backup=0 AND record_hidden=0', $this->id ) ) ) ),
			'b_version'		=> $this->version,
			'b_changelog'	=> $this->changelog,
		) );
		
		/* Fetch the existing locations to prevent backups with the same file name as the current file from removing the disk files */
		$locations = array();
		$thumbs = array();
		$watermarks = array();
		foreach( \IPS\Db::i()->select( '*', 'downloads_files_records', array( 'record_file_id=? AND record_backup=0 AND record_hidden=0', $this->id ) ) as $file )
		{
			$locations[] = $file['record_location'];
			
			if ( $file['record_thumb'] )
			{
				$thumbs[] = $file['record_thumb'];
			}
			
			if ( $file['record_no_watermark'] )
			{
				$watermarks[] = $file['record_no_watermark'];
			}
		}
		
		/* Update the attachment map for this version (NULL means the current version, anything else means previous version). */
		\IPS\File::claimAttachments( "downloads-{$this->id}-changelog", $this->id, $b_id, 'changelog' );
		
		/* Set the old records to be backups */
		\IPS\Db::i()->update( 'downloads_files_records', array( 'record_backup' => TRUE ), array( 'record_file_id=? AND record_backup=0 AND record_hidden=0', $this->id ) );
						
		/* Delete any old versions we no longer keep */
		$category = $this->container();
		if ( $category->versioning !== NULL )
		{
			$count = \IPS\Db::i()->select( 'COUNT(*)', 'downloads_filebackup', array( 'b_fileid=?', $this->id ) )->first();
			if ( ( $count - $category->versioning + 1 ) > 0 )
			{
				foreach ( \IPS\Db::i()->select( '*', 'downloads_filebackup', array( 'b_fileid=?', $this->id ), 'b_backup ASC', $count - $category->versioning + 1 ) as $backUp )
				{
					foreach ( \IPS\Db::i()->select( '*', 'downloads_files_records', \IPS\Db::i()->in( 'record_id', explode( ',', $backUp['b_records'] ) ) ) as $k => $file )
					{
						try
						{
							if ( !\in_array( $file['record_location'], $locations ) )
							{
								$file = \IPS\File::get( $file['record_type'] == 'upload' ? 'downloads_Files' : 'downloads_Screenshots', $file['record_location'] )->delete();
							}
						}
						catch ( \Exception $e ) { }

						if( $file['record_type'] == 'ssupload' )
						{
							if( $file['record_thumb'] )
							{
								try
								{
									if ( !\in_array( $file['record_thumb'], $thumbs ) )
									{
										$file = \IPS\File::get( 'downloads_Screenshots', $file['record_thumb'] )->delete();
									}
								}
								catch ( \Exception $e ) { }
							}

							if( $file['record_no_watermark'] )
							{
								try
								{
									if ( !\in_array( $file['record_no_watermark'], $watermarks ) )
									{
										$file = \IPS\File::get( 'downloads_Screenshots', $file['record_no_watermark'] )->delete();
									}
								}
								catch ( \Exception $e ) { }
							}
						}
					}
					
					\IPS\Db::i()->delete( 'downloads_files_records', \IPS\Db::i()->in( 'record_id', explode( ',', $backUp['b_records'] ) ) );
					\IPS\Db::i()->delete( 'downloads_filebackup', array( 'b_id=?', $backUp['b_id'] ) );
				}
			}
		}

	}
		
	/* !Tags */
	
	/**
	 * Can tag?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	bool
	 */
	public static function canTag( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return parent::canTag( $member, $container ) and ( $container === NULL or !$container->tags_disabled );
	}
	
	/**
	 * Can use prefixes?
	 *
	 * @param	\IPS\Member|NULL		$member		The member to check for (NULL for currently logged in member)
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	bool
	 */
	public static function canPrefix( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return parent::canPrefix( $member, $container ) and ( $container === NULL or !$container->tags_noprefixes );
	}
	
	/**
	 * Defined Tags
	 *
	 * @param	\IPS\Node\Model|NULL	$container	The container to check if tags can be used in, if applicable
	 * @return	array
	 */
	public static function definedTags( \IPS\Node\Model $container = NULL )
	{
		if ( $container and $container->tags_predefined )
		{
			return explode( ',', $container->tags_predefined );
		}
		
		return parent::definedTags( $container );
	}
	
	/* !Followers */
	
	/**
	 * Users to receive immediate notifications (bulk)
	 *
	 * @param	\IPS\downloads\Category	$category	The category the files were posted in.
	 * @param	\IPS\Member|NULL		$member		The member posting the files or NULL for currently logged in member.
	 * @param	int|array				$limit		LIMIT clause
	 * @param	bool					$countOnly	Only return the count
	 * @return	\IPS\Db\Select
	 */
	public static function _notificationRecipients( $category, $member=NULL, $limit=array( 0, 25 ), $countOnly=FALSE )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		/* Do we only want the count? */
		if( $countOnly )
		{
			$count	= 0;
			$count	+= $member->followersCount( 3, array( 'immediate' ), NULL );
			$count	+= static::containerFollowerCount( $category, 3, array( 'immediate' ), NULL );

			return $count;
		}

		$memberFollowers = $member->followers( 3, array( 'immediate' ), NULL, NULL );

		if( $memberFollowers !== NULL )
		{
			$unions	= array( 
				static::containerFollowers( $category, 3, array( 'immediate' ), NULL, NULL ),
				$memberFollowers
			);

			return \IPS\Db::i()->union( $unions, 'follow_added', $limit );
		}
		else
		{
			return static::containerFollowers( $category, static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS, array( 'immediate' ), NULL, $limit, 'follow_added' );
		}
	}
	
	/**
	 * Send Notifications (bulk)
	 *
	 * @param	\IPS\downloads\Category	$category	The category the files were posted in.
	 * @param	\IPS\Member|NULL		$member		The member posting the images, or NULL for currently logged in member.
	 * @return	void
	 */
	public static function _sendNotifications( $category, $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		try
		{
			$count = static::_notificationRecipients( $category, $member, NULL, TRUE );
		}
		catch( \BadMethodCallException $e )
		{
			return;
		}
		
		$categoryIdColumn	= $category::$databaseColumnId;
		
		if ( $count > \IPS\NOTIFICATION_BACKGROUND_THRESHOLD )
		{
			$queueData = array(
				'followerCount'		=> $count,
				'category_id'		=> $category->$categoryIdColumn,
				'member_id'			=> $member->member_id
			);
			
			\IPS\Task::queue( 'downloads', 'Follow', $queueData, 2 );
		}
		else
		{
			static::_sendNotificationsBatch( $category, $member );
		}
	}
	
	/**
	 * Send Unapproved Notification (bulk)(
	 *
	 * @param	\IPS\downloads\Category	$category	The category the files were posted too.
	 * @param	\IPS\Member|NULL		$member		The member posting the images, or NULL for currently logged in member.
	 * @return	void
	 */
	public static function _sendUnapprovedNotifications( $category, $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		$moderators = array( 'g' => array(), 'm' => array() );
		foreach( \IPS\Db::i()->select( '*', 'core_moderators' ) AS $mod )
		{
			$canView = FALSE;
			if ( $mod['perms'] == '*' )
			{
				$canView = TRUE;
			}
			if ( $canView === FALSE )
			{
				$perms = json_decode( $mod['perms'], TRUE );
				
				if ( isset( $perms['can_view_hidden_content'] ) AND $perms['can_view_hidden_content'] )
				{
					$canView = TRUE;
				}
				else if ( isset( $perms['can_view_hidden_' . static::$title ] ) AND $perms['can_view_hidden_' . static::$title ] )
				{
					$canView = TRUE;
				}
			}
			if ( $canView === TRUE )
			{
				$moderators[ $mod['type'] ][] = $mod['id'];
			}
		}
		
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'unapproved_content_bulk', $category, array( $category, $member, $category::$contentItemClass ), array( $member->member_id ) );
		foreach ( \IPS\Db::i()->select( '*', 'core_members', ( \count( $moderators['m'] ) ? \IPS\Db::i()->in( 'member_id', $moderators['m'] ) . ' OR ' : '' ) . \IPS\Db::i()->in( 'member_group_id', $moderators['g'] ) . ' OR ' . \IPS\Db::i()->findInSet( 'mgroup_others', $moderators['g'] ) ) as $moderator )
		{
			$notification->recipients->attach( \IPS\Member::constructFromData( $moderator ) );
		}
		$notification->send();
	}
	
	/**
	 * Send Notification Batch (bulk)
	 *
	 * @param	\IPS\downloads\Category	$category	The category the files were posted too.
	 * @param	\IPS\Member|NULL		$member		The member posting the images, or NULL for currently logged in member.
	 * @param	int						$offset		Offset
	 * @return	int|NULL				New Offset or NULL if complete
	 */
	public static function _sendNotificationsBatch( $category, $member=NULL, $offset=0 )
	{
		/* Check notification initiator spam status */
		if( ( $member instanceof \IPS\Member ) AND $member->members_bitoptions['bw_is_spammer'] )
		{
			/* Initiator is flagged as spammer, don't send notifications */
			return NULL;
		}

		$member				= $member ?: \IPS\Member::loggedIn();
		
		$followIds = array();
		$followers = iterator_to_array( static::_notificationRecipients( $category, $member, array( $offset, static::NOTIFICATIONS_PER_BATCH ) ) );

		if( !\count( $followers ) )
		{
			return NULL;
		}

		$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'new_content_bulk', $category, array( $category, $member, $category::$contentItemClass ), array( $member->member_id ) );
		
		foreach( $followers AS $follower )
		{
			$followMember = \IPS\Member::load( $follower['follow_member_id'] );
			if ( $followMember != $member and $category->can( 'view', $followMember ) )
			{
				$followIds[] = $follower['follow_id'];
				$notification->recipients->attach( $followMember );
			}
		}
		
		\IPS\Db::i()->update( 'core_follow', array( 'follow_notify_sent' => time() ), \IPS\Db::i()->in( 'follow_id', $followIds ) );
		$notification->send();
		
		return $offset + static::NOTIFICATIONS_PER_BATCH;
	}
	
	/**
	 * @brief	Is first time approval
	 */
	protected $firstTimeApproval = FALSE;
	
	/**
	 * Unhide
	 *
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @return	void
	 */
	public function unhide( $member=NULL )
	{
		if ( $this->hidden() === 1 )
		{
			$this->firstTimeApproval = TRUE;
		}
		
		parent::unhide( $member );
	}
	
	/**
	 * Send Approved Notification
	 *
	 * @return	void
	 */
	public function sendApprovedNotification()
	{
		if ( $this->firstTimeApproval )
		{
			$this->sendNotifications();
		}
		else
		{
			$this->sendUpdateNotifications();
		}
		$this->sendAuthorApprovalNotification();
	}
	
	/**
	 * Send notifications that the file has been updated
	 *
	 * @return	void
	 */
	public function sendUpdateNotifications()
	{		
		$count = \IPS\Db::i()->select( 'count(*)', 'downloads_files_notify', array( 'notify_file_id=?', $this->id ) )->first();

		if ( $count )
		{
			$idColumn = static::$databaseColumnId;
			\IPS\Task::queue( 'downloads', 'Notify', array( 'file' => $this->$idColumn, 'notifyCount' => $count ), 2 );
		}
	}

	/**
	 * Syncing when uploading a new version.
	 *
	 * @param	array	$values	Values from the form
	 * @return	void
	 */
	public function processAfterNewVersion( $values )
	{
		/* This method is mainly used to ensure a topic linked to a file download stays updated when uploading a new version (like when editing it normally),
			however it also accepts the values from the form so hook authors can overload and manipulate the data from it. */
		if ( \IPS\Application::appIsEnabled('forums') AND $this->topic() )
		{
			/* And we need to make sure the "cached" values for primary screenshot and thumbnail are cleared so the updated topic always has the latest */
			$this->_primaryScreenshot = FALSE;
			$this->_primaryScreenshotThumb = FALSE;
			$this->syncTopic();
		}
		\IPS\Api\Webhook::fire( 'downloads_new_version', array( $this ) );
	}
	
	/* !Embeddable */
	
	/**
	 * Get image for embed
	 *
	 * @return	\IPS\File|NULL
	 */
	public function embedImage()
	{
		return $this->primary_screenshot_thumb ?: NULL;
	}

	/**
	 * Get content for embed
	 *
	 * @param	array	$params	Additional parameters to add to URL
	 * @return	string
	 */
	public function embedContent( $params )
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'embed.css', 'downloads', 'front' ) );
		return \IPS\Theme::i()->getTemplate( 'global', 'downloads' )->embedFile( $this, $this->url()->setQueryString( $params ), $this->embedImage() );
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
		$screenshot = NULL;
		if ( isset( $itemData['extra'] ) )
		{
			$screenshot = isset( $itemData['extra']['record_thumb'] ) ? $itemData['extra']['record_thumb'] : $itemData['extra']['record_location'];
		}
		$url = \IPS\Http\Url::internal( static::$urlBase . $indexData['index_item_id'], 'front', static::$urlTemplate, \IPS\Http\Url\Friendly::seoTitle( $indexData['index_title'] ?: $itemData[ static::$databasePrefix . static::$databaseColumnMap['title'] ] ) );
		
		$price = static::_price( $itemData['file_cost'], $itemData['file_nexus'] );
		
		return \IPS\Theme::i()->getTemplate( 'global', 'downloads', 'front' )->searchResultFileSnippet( $indexData, $itemData, $screenshot, $url, $price, $view == 'condensed' );
	}

	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @param	array	$backup	If provided, will output for a particular version - provide row from downloads_filebackup
	 * @return	array
	 * @apiresponse	int							id						ID number
	 * @apiresponse	string						title					Title
	 * @apiresponse	\IPS\downloads\Category		category				Category
	 * @apiresponse	\IPS\Member					author					Author
	 * @apiresponse	datetime					date					When the file was created
	 * @apiresponse	datetime					updated					When the file was last updated
	 * @apiresponse	string						description				Description
	 * @apiresponse	string						version					Current version number
	 * @apiresponse	string						changelog				Description of what changed between this version and the previous one
	 * @apiresponse	\IPS\File					primaryScreenshot		The primary screenshot
	 * @apiresponse	[\IPS\File]					screenshots				Screenshots
	 * @apiresponse	[\IPS\File]					screenshotsThumbnails	Screenshots in Thumbnail size
	 * @apiresponse	\IPS\File					primaryScreenshot		The primary screenshot
	 * @apiresponse	\IPS\File					primaryScreenshotThumb	The primary screenshot
	 * @apiresponse	int							downloads				Number of downloads
	 * @apiresponse	int							comments				Number of comments
	 * @apiresponse	int							reviews					Number of reviews
	 * @apiresponse	int							views					Number of views
	 * @apiresponse	string						prefix					The prefix tag, if there is one
	 * @apiresponse	[string]					tags					The tags
	 * @apiresponse	bool						locked					File is locked
	 * @apiresponse	bool						hidden					File is hidden
	 * @apiresponse	bool						pinned					File is pinned
	 * @apiresponse	bool						featured				File is featured
	 * @apiresponse	string						url						URL
	 * @apiresponse	\IPS\forums\Topic			topic					The topic
	 * @apiresponse	bool						isPaid					Is the file paid?
	 * @apiresponse	[float]						prices					Prices (key is currency, value is price). Does not consider associated packages.
	 * @apiresponse	bool						canDownload				If the authenticated member can download. Will be NULL for requests made using an API Key or the Client Credentials Grant Type
	 * @apiresponse	bool						canBuy					Can purchase the file
	 * @apiresponse	bool						canReview				Can review the file
	 * @apiresponse	float						rating					File rating
	 * @apiresponse	int							purchases				Number of purchases
	 * @apiresponse array						renewalTerm				File renewal term
	 * @apiresponse	bool						hasPendingVersion		Whether file has a new version pending. Will be NULL for client requests where the authorized member cannot upload new versions.
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL, $backup=NULL )
	{
		$return = array(
			'id'						=> $this->id,
			'title'						=> $backup ? $backup['b_filetitle'] : $this->name,
			'category'					=> $this->container()->apiOutput( $authorizedMember ),
			'author'					=> $this->author()->apiOutput( $authorizedMember ),
			'date'						=> \IPS\DateTime::ts( $backup ? $backup['b_backup'] :  $this->submitted )->rfc3339(),
			'updated'					=> \IPS\DateTime::ts( $this->updated )->rfc3339(),
			'description'				=> $backup ? $backup['b_filedesc'] : \IPS\Text\Parser::removeLazyLoad( $this->content() ),
			'version'					=> $backup ? $backup['b_version'] : $this->version,
			'changelog'					=> $backup ? $backup['b_changelog'] : $this->changelog,
			'screenshots'				=> array_values( array_map( function( $file ) use ( $authorizedMember ) {
				return $file->apiOutput( $authorizedMember );
			}, iterator_to_array( $this->screenshots( 0, TRUE, $backup ? $backup['b_id'] : NULL ) ) ) ),
			'screenshotsThumbnails'		=> array_values( array_map( function( $file ) use ( $authorizedMember ) {
				return $file->apiOutput( $authorizedMember );
			}, iterator_to_array( $this->screenshots( 1, TRUE, $backup ? $backup['b_id'] : NULL ) ) ) ),
			'primaryScreenshot'			=> $this->primary_screenshot ? ( $this->primary_screenshot->apiOutput( $authorizedMember ) ) : null,
			'primaryScreenshotThumb'	=> $this->primary_screenshot_thumb ? ( $this->primary_screenshot_thumb->apiOutput( $authorizedMember ) ) : null,
			'downloads'					=> $this->downloads,
			'comments'					=> $this->comments,
			'reviews'					=> $this->reviews,
			'views'						=> $this->views,
			'prefix'					=> $this->prefix(),
			'tags'						=> $this->tags(),
			'locked'					=> (bool) $this->locked(),
			'hidden'					=> (bool) $this->hidden(),
			'pinned'					=> (bool) $this->mapped('pinned'),
			'featured'					=> (bool) $this->mapped('featured'),
			'url'						=> (string) $this->url(),
			'topic'						=> $this->topic() ? $this->topic()->apiOutput( $authorizedMember ) : NULL,
			'isPaid'					=> $this->isPaid(),
			'isPurchasable'				=> $this->isPurchasable(),
			'prices'					=> ( $this->isPaid() and $this->cost ) ? array_map( function( $price ) { return $price['amount']; }, json_decode( $this->cost, TRUE ) ) : NULL,
			'canDownload'				=> $authorizedMember ? (bool) $this->canDownload( $authorizedMember ) : NULL,
			'canBuy'					=> $authorizedMember ? (bool) $this->canBuy( $authorizedMember ) : NULL,
			'canReview'					=> (bool) $authorizedMember ? ( $this->canReview( $authorizedMember ) AND !$this->hasReviewed( $authorizedMember ) ) : NULL,
			'rating'					=> $this->averageReviewRating(),
			'purchases'					=> $this->purchaseCount(),
			'renewalTerm'				=> $this->isPaid() ? $this->renewalTerm() : NULL,
			'hasPendingVersion'			=> ( !$authorizedMember OR $this->canEdit( $authorizedMember ) ) ? $this->hasPendingVersion() : NULL
		);

		return $return;
	}

	/**
	 * Message explaining to guests that if they log in they can download
	 *
	 * @return	string|NULL
	 */
	public function downloadTeaser()
	{
		/* If we're a guest and log in, can we download? */
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			$testUser = new \IPS\Member;
			$testUser->member_group_id = \IPS\Settings::i()->member_group;
			$this->canDownload = NULL;
			if ( $this->canDownload( $testUser ) )
			{
				return \IPS\Theme::i()->getTemplate( 'view', 'downloads', 'front' )->downloadTeaser();
			}
		}

		return NULL;
	}
	
	/* !Reactions */
	
	/**
	 * Reaction Type
	 *
	 * @return	string
	 */
	public static function reactionType()
	{
		return 'file_id';
	}
	
	/**
	 * Supported Meta Data Types
	 *
	 * @return	array
	 */
	public static function supportedMetaDataTypes()
	{
		return array( 'core_FeaturedComments', 'core_ContentMessages' );
	}

	/**
	 * Give a content item the opportunity to filter similar content
	 * 
	 * @note Intentionally blank but can be overridden by child classes
	 * @return array|NULL
	 */
	public function similarContentFilter()
	{
		if( $this->topicid )
		{
			return array(
				array( '!(tag_meta_app=? and tag_meta_area=? and tag_meta_id=?)', 'forums', 'forums', $this->topicid )
			);
		}

		return NULL;
	}

	/**
	 * Return size and downloads count when this content type is inserted as an attachment via the "Insert other media" button on an editor.
	 *
	 * @note Most content types do not support this, and those that do will need to override this method to return the appropriate info
	 * @return array
	 */
	public function getAttachmentInfo()
	{
		return array(
			'size' => \IPS\Output\Plugin\Filesize::humanReadableFilesize( $this->size, FALSE, TRUE ),
			'downloads' => \IPS\Member::loggedIn()->language()->formatNumber( $this->downloads )
		);
	}

	/**
	 * Get the topic title
	 *
	 * @return string
	 */
	function getTopicTitle()
	{
		return $this->container()->_topic_prefix . $this->name . $this->container()->_topic_suffix;
	}

	/**
	 * Get the topic content
	 *
	 * @return mixed
	 */
	function getTopicContent()
	{
		return \IPS\Theme::i()->getTemplate( 'submit', 'downloads', 'front' )->topic( $this );
	}

	/**
	 * Subscribed?
	 *
	 * @return bool
	 */
	function subscribed( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		try
		{
			\IPS\Db::i()->select( '*', 'downloads_files_notify', array( 'notify_member_id=? and notify_file_id=?', $member->member_id, $this->id ) )->first();

			return TRUE;
		}
		catch ( \UnderflowException $e )
		{
			return FALSE;
		}
	}

	/**
	 * @brief	Cache for pending version check
	 */
	protected $_pendingVersion = NULL;

	/**
	 * Check whether file has a pending new version
	 *
	 * @return	bool
	 */
	public function hasPendingVersion(): bool
	{
		if( $this->_pendingVersion !== NULL )
		{
			return $this->_pendingVersion;
		}

		try
		{
			\IPS\Db::i()->select( 'pending_id', 'downloads_files_pending', array( 'pending_file_id=?', $this->id ) )->first();
			$this->_pendingVersion = TRUE;
		}
		catch ( \UnderflowException $e )
		{
			$this->_pendingVersion = FALSE;
		}

		return $this->_pendingVersion;
	}

	/**
	 * Add form elements to 'new version' form
	 *
	 * @param	\IPS\Helpers\Form		$form
	 * @return	void
	 */
	public function newVersionFormElements( \IPS\Helpers\Form &$form )
	{

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
		if ( $action == 'delete' )
		{
			/* Delete pending version */
			if( $this->hasPendingVersion() )
			{
				\IPS\downloads\File\PendingVersion::load($this->id, 'pending_file_id' )->delete();
				$this->_pendingVersion = FALSE;
			}
		}

		return parent::modAction( $action, $member, $reason, $immediately );
	}

	/**
	 * Can the member delete the pending version
	 * 
	 * @param \IPS\Member|NULL $member
	 * @return bool
	 */
	public function canDeletePendingVersion( \IPS\Member $member = NULL): bool
	{
		if( !$this->hasPendingVersion() )
		{
			return FALSE;
		}

		if( $this->canEdit($member) )
		{
			return TRUE;
		}

		return FALSE;
	}
}