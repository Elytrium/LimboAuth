<?php
/**
 * @brief		Browse Store
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		29 Apr 2014
 */

namespace IPS\nexus\modules\front\store;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Browse Store
 */
class _store extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Products per page
	 */
	protected static $productsPerPage = 50;
	
	/**
	 * @brief	Currency
	 */
	protected $currency;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		/* Set CSS */
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'store.css', 'nexus' ) );
		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'store_responsive.css', 'nexus', 'front' ) );
		}
		
		/* Work out currency */
		if ( isset( \IPS\Request::i()->currency ) and \in_array( \IPS\Request::i()->currency, \IPS\nexus\Money::currencies() ) )
		{
			if ( isset( \IPS\Request::i()->csrfKey ) and \IPS\Login::compareHashes( (string) \IPS\Session::i()->csrfKey, (string) \IPS\Request::i()->csrfKey ) )
			{
				$_SESSION['cart'] = array();
				\IPS\Request::i()->setCookie( 'currency', \IPS\Request::i()->currency );

				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=store&controller=store', 'front', 'store' ) );
			}
			$this->currency = \IPS\Request::i()->currency;
		}
		else
		{
			$this->currency = \IPS\nexus\Customer::loggedIn()->defaultCurrency();
		}
		
		\IPS\Output::i()->pageCaching = FALSE;
		
		/* Pass up */
		parent::execute();
	}

	/**
	 * Browse Store
	 *
	 * @return	void
	 */
	protected function manage()
	{		
		/* If we have a category, display it */
		if ( isset( \IPS\Request::i()->cat ) )
		{
			return $this->_categoryView();
		}
		
		/* Otherwise, display the index */
		else
		{
			$this->_indexView();
		}
	}
	
	/**
	 * Show a category
	 *
	 * @return	void
	 */
	protected function _categoryView()
	{
		/* Load category */
		try
		{
			$category = \IPS\nexus\Package\Group::loadAndCheckPerms( \IPS\Request::i()->cat );
		}
		catch ( \OutofRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '1X241/1', 404, '' );
		}
		$url = $category->url();
		
		/* Set initial stuff for fetching packages */
		$currentPage = isset( \IPS\Request::i()->page ) ? \intval( \IPS\Request::i()->page ) : 1;
		if( $currentPage < 1 )
		{
			$currentPage = 1;
		}
		$where = array(
			array( 'p_group=?', $category->id ),
			array( 'p_store=1' ),
			array( "( p_member_groups='*' OR " . \IPS\Db::i()->findInSet( 'p_member_groups', \IPS\Member::loggedIn()->groups ) . ' )' )
		);
		$havePackagesWhichAcceptReviews = (bool) \IPS\Db::i()->select( 'COUNT(*)', 'nexus_packages', array_merge( $where, array( array( 'p_reviewable=1' ) ) ) )->first();
		$havePackagesWhichUseStockLevels = (bool) \IPS\Db::i()->select( 'COUNT(*)', 'nexus_packages', array_merge( $where, array( array( 'p_stock<>-1' ) ) ) )->first();
		$joins = array();
				
		/* Apply Filters */
		if ( isset( \IPS\Request::i()->filter ) and \is_array( \IPS\Request::i()->filter ) )
		{
			$url = $url->setQueryString( 'filter', \IPS\Request::i()->filter );
			foreach ( \IPS\Request::i()->filter as $filterId => $allowedValues )
			{
				$where[] = array( \IPS\Db::i()->findInSet( "filter{$filterId}.pfm_values", array_map( 'intval', explode( ',', $allowedValues ) ) ) );
				$joins[] = array( 'table' => array( 'nexus_package_filters_map', "filter{$filterId}" ), 'on' => array( "filter{$filterId}.pfm_package=p_id AND filter{$filterId}.pfm_filter=?", $filterId ) );
			}
		}
		foreach ( array( 'minPrice' => '>', 'maxPrice' => '<' ) as $k => $op )
		{
			if ( isset( \IPS\Request::i()->$k ) and \is_numeric( \IPS\Request::i()->$k ) and \floatval( \IPS\Request::i()->$k ) > 0 )
			{
				$url = $url->setQueryString( $k, \IPS\Request::i()->$k );
				$joins['nexus_package_base_prices'] = array( 'table' => 'nexus_package_base_prices', 'on' => array( 'id=p_id' ) );
				$where[] = array( $this->currency . $op . '=?', \floatval( \IPS\Request::i()->$k ) );
			}
		}
		if ( isset( \IPS\Request::i()->minRating ) and \is_numeric( \IPS\Request::i()->minRating ) and \floatval( \IPS\Request::i()->minRating ) > 0 )
		{
			$url = $url->setQueryString( 'minRating', \IPS\Request::i()->minRating );
			$where[] = array( 'p_rating>=?', \intval( \IPS\Request::i()->minRating ) );
		}
		if ( isset( \IPS\Request::i()->inStock ) )
		{
			$url = $url->setQueryString( 'inStock', \IPS\Request::i()->inStock );
			$where[] = array( '( p_stock>0 OR ( p_stock=-2 AND (?)>0 ) )', \IPS\Db::i()->select( 'MAX(opt_stock)', 'nexus_product_options', 'opt_package=p_id' ) );
		}
		
		/* Figure out the sorting */
		switch ( \IPS\Request::i()->sortby )
		{
			case 'name':
				$joins['core_sys_lang_words'] = array( 'table' => 'core_sys_lang_words', 'on' => array( "word_app='nexus' AND word_key=CONCAT( 'nexus_package_', p_id ) AND lang_id=?", \IPS\Member::loggedIn()->language()->id ) );
				$sortBy = 'word_custom';
				break;
				
			case 'price_low':
			case 'price_high':
				$joins['nexus_package_base_prices'] = array( 'table' => 'nexus_package_base_prices', 'on' => array( 'id=p_id' ) );
				$sortBy = \IPS\Request::i()->sortby == 'price_low' ? $this->currency : ( $this->currency . ' DESC' );
				break;
				
			case 'rating':
				$sortBy = 'p_rating DESC';
				break;
				
			default:
				$sortBy = 'p_position';
				break;
		}
		
		/* Fetch the packages */
		$select = \IPS\Db::i()->select( '*', 'nexus_packages', $where, $sortBy, array( ( $currentPage - 1 ) * static::$productsPerPage, static::$productsPerPage ) );
		foreach ( $joins as $join )
		{
			$select->join( $join['table'], $join['on'] );
		}
		$packages = new \IPS\Patterns\ActiveRecordIterator( $select, 'IPS\nexus\Package' );

		/* Get packages count */
		$select = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_packages', $where );
		foreach ( $joins as $join )
		{
			$select->join( $join['table'], $join['on'] );
		}
		$totalCount = $select->first();
		
		/* Pagination */
		$totalPages = ceil( $totalCount / static::$productsPerPage );
		if ( $totalPages and $currentPage > $totalPages )
		{
			\IPS\Output::i()->redirect( $category->url()->setPage( 'page', $totalPages ), NULL, 303 );
		} 
		$pagination = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $category->url(), $totalPages, $currentPage, static::$productsPerPage );
		
		/* Other stuff we need for the view */
		$subcategories = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_package_groups', array( 'pg_parent=?', $category->id ), 'pg_position ASC' ), 'IPS\nexus\Package\Group' );
		$packagesWithCustomFields = array();
		foreach ( iterator_to_array( \IPS\Db::i()->select( 'cf_packages', 'nexus_package_fields', 'cf_purchase=1' ) ) as $ids )
		{
			$packagesWithCustomFields = array_merge( $packagesWithCustomFields, array_filter( explode( ',', $ids ) ) );
		}
		
		/* List or grid? */
		if ( \IPS\Request::i()->view )
		{
			\IPS\Session::i()->csrfCheck();
			\IPS\Request::i()->setCookie( 'storeView', ( \IPS\Request::i()->view == 'list' ) ? 'list' : 'grid', \IPS\DateTime::ts( time() )->add( new \DateInterval( 'P1Y' ) ) );
			\IPS\Request::i()->cookie['storeView'] = ( \IPS\Request::i()->view == 'list' ) ? 'list' : 'grid';
		}
		
		/* Output */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array(
				'contents' 	=> \IPS\Theme::i()->getTemplate('store')->categoryContents( $category, $subcategories, $packages, $pagination, $packagesWithCustomFields, $totalCount ),
				'sidebar'	=> \IPS\Theme::i()->getTemplate('store')->categorySidebar( $category, $subcategories, $url, \count( $packages ), $this->currency, $havePackagesWhichAcceptReviews, $havePackagesWhichUseStockLevels )
			)) ;
			return;
		}
		else
		{
			foreach ( $category->parents() as $parent )
			{
				\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
			}
			\IPS\Output::i()->breadcrumb[] = array( $category->url(), $category->_title );
			\IPS\Output::i()->title = $category->_title;
			\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate('store')->categorySidebar( $category, $subcategories, $url, \count( $packages ), $this->currency, $havePackagesWhichAcceptReviews, $havePackagesWhichUseStockLevels );	

			/* Set default search */
			\IPS\Output::i()->defaultSearchOption = array( 'nexus_package_item', "nexus_package_item_el" );	

			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('store')->category( $category, $subcategories, $packages, $pagination, $packagesWithCustomFields, $totalCount );
			\IPS\Output::i()->globalControllers[] = 'nexus.front.store.category';
		}

		/* JSON-LD
			@note Google does not like categories marked up and instead recommends marking up each product separately.
			@link https://developers.google.com/search/docs/guides/intro-structured-data#multiple-entities-on-the-same-page */
		foreach( $packages as $package )
		{
			$item	= \IPS\nexus\Package\Item::load( $package->id );

			try
			{
				$price = $package->price();
			}
			catch( \OutOfBoundsException $e )
			{
				$price = NULL;
			}

			/* A product MUST have an offer, so if there's no price (i.e. due to currency configuration) don't even output */
			if( $price !== NULL )
			{
				\IPS\Output::i()->jsonLd['package' . $package->id ]	= array(
					'@context'		=> "http://schema.org",
					'@type'			=> "Product",
					'name'			=> $package->_title,
					'description'	=> $item->truncated( TRUE, NULL ),
					'category'		=> $category->_title,
					'url'			=> (string) $package->url(),
					'sku'			=> $package->id,
					'offers'		=> array(
										'@type'			=> 'Offer',
										'price'			=> $price->amountAsString(),
										'priceCurrency'	=> $price->currency,
										'seller'		=> array(
															'@type'		=> 'Organization',
															'name'		=> \IPS\Settings::i()->board_name
														),
									),
				);

				/* Stock levels */
				if( $package->physical )
				{
					if( $package->stockLevel() === 0 )
					{
						\IPS\Output::i()->jsonLd['package' . $package->id ]['offers']['availability'] = 'http://schema.org/OutOfStock';
					}
					else
					{
						\IPS\Output::i()->jsonLd['package' . $package->id ]['offers']['availability'] = 'http://schema.org/InStock';
					}
				}

				if( $package->image )
				{
					\IPS\Output::i()->jsonLd['package' . $package->id ]['image'] = (string) $package->image;
				}

				if( $package->reviewable and $item->averageReviewRating() )
				{
					\IPS\Output::i()->jsonLd['package' . $package->id ]['aggregateRating'] = array(
						'@type'			=> 'AggregateRating',
						'ratingValue'	=> $item->averageReviewRating(),
						'ratingCount'	=> $item->reviews
					);
				}
			}
		}		
	}
		
	/**
	 * Set a price filter
	 *
	 * @return	void
	 */
	protected function priceFilter()
	{
		if ( !isset( \IPS\Request::i()->maxPrice ) )
		{
			\IPS\Request::i()->maxPrice = 0;
		}
		
		$form = new \IPS\Helpers\Form( 'price_filter', 'filter' );
		$form->class = 'ipsForm_vertical';
		$form->add( new \IPS\Helpers\Form\Number( 'minPrice', FALSE, 0, array( 'decimals' => \IPS\nexus\Money::numberOfDecimalsForCurrency( $this->currency ) ), NULL, NULL, $this->currency ) );
		$form->add( new \IPS\Helpers\Form\Number( 'maxPrice', FALSE, 0, array( 'decimals' => \IPS\nexus\Money::numberOfDecimalsForCurrency( $this->currency ), 'unlimited' => 0 ), NULL, NULL, $this->currency ) );
		
		if ( $values = $form->values() )
		{
			$url = \IPS\Request::i()->url()->setQueryString( 'do', NULL );
			if ( $values['minPrice'] )
			{
				$url = $url->setQueryString( 'minPrice', $values['minPrice'] );
			}
			else
			{
				$url = $url->setQueryString( 'minPrice', NULL );
			}
			if ( $values['maxPrice'] )
			{
				$url = $url->setQueryString( 'maxPrice', $values['maxPrice'] );
			}
			else
			{
				$url = $url->setQueryString( 'maxPrice', NULL );
			}
			\IPS\Output::i()->redirect( $url );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('price_filter');
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
	
	/**
	 * Show the index
	 *
	 * @return	void
	 */
	protected function _indexView()
	{
		/* New Products */
		$newProducts = array();
		$nexus_store_new = explode( ',', \IPS\Settings::i()->nexus_store_new );
		if ( $nexus_store_new[0] )
		{
			$newProducts = \IPS\nexus\Package\Item::getItemsWithPermission( array( array( 'p_date_added>?', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $nexus_store_new[1] . 'D' ) )->getTimestamp() ) ), 'p_date_added DESC', $nexus_store_new[0] );
		}
		
		/* Popular Products */
		$popularProducts = array();
		$nexus_store_popular = explode( ',', \IPS\Settings::i()->nexus_store_popular );
		if ( $nexus_store_popular[0] )
		{
			$where = array();
			$where[] = array( 'ps_app=? AND ps_type=? AND ps_start>?', 'nexus', 'package', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $nexus_store_popular[1] . 'D' ) )->getTimestamp() );
			$where[] = "( p_member_groups='*' OR " . \IPS\Db::i()->findInSet( 'p_member_groups', \IPS\Member::loggedIn()->groups ) . ' )';
			$where[] = array( 'p_store=?', 1 );

			$popularIds = \IPS\Db::i()->select( 'nexus_purchases.ps_item_id', 'nexus_purchases', $where, 'COUNT(ps_item_id) DESC', $nexus_store_popular[0], 'ps_item_id' )->join( 'nexus_packages', 'ps_item_id=p_id' );
			if( \count( $popularIds ) )
			{
				$popularProducts = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( 'nexus_packages.*', 'nexus_packages', array( \IPS\Db::i()->in( 'p_id', iterator_to_array( $popularIds ) ) ), 'FIELD(p_id, ' . implode( ',', iterator_to_array($popularIds) ) . ')' ), 'IPS\nexus\Package' );
			}
		}
					
		/* Display */
		\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate('store')->categorySidebar( );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('store')->index( \IPS\nexus\Customer::loggedIn()->cm_credits[ $this->currency ], $newProducts, $popularProducts );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('module__nexus_store');
	}
	
	/**
	 * Registration Packages
	 *
	 * @return	void
	 */
	public function register()
	{
		\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('store')->register( \IPS\nexus\Package\Item::getItemsWithPermission( array( array( 'p_reg=1' ) ), 'pg_position, p_position', 10, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, NULL, TRUE ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('sign_up');
	}
		
	/**
	 * View Cart
	 *
	 * @return	void
	 */
	protected function cart()
	{
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('store')->cart();
	}
}