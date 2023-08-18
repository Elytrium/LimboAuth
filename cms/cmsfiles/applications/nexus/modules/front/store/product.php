<?php
/**
 * @brief		View product
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
 * View product
 */
class _product extends \IPS\Content\Controller
{
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\nexus\Package\Item';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_store.js', 'nexus', 'front' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'store.css', 'nexus' ) );

		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'store_responsive.css', 'nexus', 'front' ) );
		}
		
		\IPS\Output::i()->pageCaching = FALSE;
		
		parent::execute();
	}

	/**
	 * View
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Init */
		if ( !isset( $_SESSION['cart'] ) )
		{
			$_SESSION['cart'] = array();
		}
		$memberCurrency = ( ( isset( \IPS\Request::i()->cookie['currency'] ) and \in_array( \IPS\Request::i()->cookie['currency'], \IPS\nexus\Money::currencies() ) ) ? \IPS\Request::i()->cookie['currency'] : \IPS\nexus\Customer::loggedIn()->defaultCurrency() );
		
		/* Load Package */
		$item = parent::manage();
		if ( !$item )
		{
			\IPS\Output::i()->error( 'node_error', '2X240/1', 404, '' );
		}
		$package = \IPS\nexus\Package::load( $item->id );
		
		/* Do we have any in the cart already (this will affect stock level)? */
		$inCart = array();
		foreach ( $_SESSION['cart'] as $itemInCart )
		{
			if ( $itemInCart->id === $package->id )
			{
				$optionValues = array();
				foreach( $package->optionIdKeys() as $id )
				{
					$optionValues[ $id ] = $itemInCart->details[$id];
				}
				$optionValues = json_encode( $optionValues );
				if ( !isset( $inCart[ $optionValues ] ) )
				{
					$inCart[ $optionValues ] = 0;
				}
				$inCart[ $optionValues ] += $itemInCart->quantity;
			}
		}
						
		/* Showing just the form to purchase, or full product page? */
		if ( \IPS\Request::i()->purchase )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('store')->purchaseForm( $package, $item, $this->_getForm( $package, $inCart, TRUE ) );	
		}
		
		/* No - show the full page */
		else
		{
			/* We need to create a dummy item so we can work out ship times/prices */
			$itemDataForShipping = NULL;
			if ( $package->physical )
			{
				try
				{
					$itemDataForShipping = $package->createItemForCart( $package->price() );
				}
				catch ( \OutOfBoundsException $e ) { }
			}
			
			/* If physical, get available shipping methods */
			$shippingMethods = array();
			$locationType = 'none';
			if ( $package->physical )
			{
				/* Where are we shipping to? */
				$shipAddress = NULL;
				if ( \IPS\Member::loggedIn()->member_id and $primaryBillingAddress = \IPS\nexus\Customer::loggedIn()->primaryBillingAddress() )
				{
					$shipAddress = $primaryBillingAddress;
					$locationType = 'address';
				}
				if ( !$shipAddress )
				{
					try
					{
						$shipAddress = \IPS\GeoLocation::getByIp( \IPS\Request::i()->ipAddress() );
						$locationType = 'geo';
					}
					catch ( \Exception $e ) { }
				}
				
				/* Standard */
				$where = NULL;
				if ( $package->shipping != '*' )
				{
					$where = \IPS\Db::i()->in( 's_id', explode( ',', $package->shipping ) );
				}
				foreach ( \IPS\nexus\Shipping\FlatRate::roots( NULL, NULL, $where ) as $rate )
				{
					if ( ( $shipAddress and $rate->isAvailable( $shipAddress, array( $itemDataForShipping ), $memberCurrency ) ) or ( $rate->locations === '*' ) )
					{	
						$shippingMethods[] = $rate;
					}
				}
				
				/* Easypost */
				if ( $shipAddress and \IPS\Settings::i()->easypost_api_key and \IPS\Settings::i()->easypost_show_rates and ( $package->shipping == '*' or \in_array( 'easypost', explode( ',', $package->shipping ) ) ) )
				{
					try
					{
						$fromAddress = \IPS\GeoLocation::buildFromJson( \IPS\Settings::i()->easypost_address ?: \IPS\Settings::i()->site_address );
						
						$length = new \IPS\nexus\Shipping\Length( $package->length );
						$width = new \IPS\nexus\Shipping\Length( $package->width );
						$height = new \IPS\nexus\Shipping\Length( $package->height );
						$weight = new \IPS\nexus\Shipping\Weight( $package->weight );
						
						$easyPost = \IPS\nexus\Shipping\EasyPostRate::getRates( $length->float('in'), $width->float('in'), $height->float('in'), $weight->float('oz'), \IPS\nexus\Customer::loggedIn(), $shipAddress, $memberCurrency );
						if ( isset( $easyPost['rates'] ) )
						{
							foreach ( $easyPost['rates'] as $rate )
							{
								if ( $rate['currency'] === $memberCurrency )
								{
									$shippingMethods[] = new \IPS\nexus\Shipping\EasyPostRate( $rate );
								}
							}
						}
					}
					catch ( \IPS\Http\Request\Exception $e ) { }
				}
			}
						
			/* Do we have renewal terms? */
			$renewalTerm = NULL;
			$renewOptions = $package->renew_options ? json_decode( $package->renew_options, TRUE ) : array();
			$initialTerm = NULL;
			if ( \count( $renewOptions ) )
			{
				$renewalTerm = TRUE;
				if ( \count( $renewOptions ) === 1 )
				{
					$renewalTerm = array_pop( $renewOptions );
					$renewalTerm = new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( $renewalTerm['cost'][ $memberCurrency ]['amount'], $memberCurrency ), new \DateInterval( 'P' . $renewalTerm['term'] . mb_strtoupper( $renewalTerm['unit'] ) ), $package->tax ? \IPS\nexus\Tax::load( $package->tax ) : NULL, $renewalTerm['add'] );
				}
				if ( $package->initial_term )
				{
					$term = mb_substr( $package->initial_term, 0, -1 );
					switch( mb_substr( $package->initial_term, -1 ) )
					{
						case 'D':
							$initialTerm = \IPS\Member::loggedIn()->language()->pluralize( \IPS\Member::loggedIn()->language()->get('renew_days'), array( $term ) );
							break;
						case 'M':
							$initialTerm = \IPS\Member::loggedIn()->language()->pluralize( \IPS\Member::loggedIn()->language()->get('renew_months'), array( $term ) );
							break;
						case 'Y':
							$initialTerm = \IPS\Member::loggedIn()->language()->pluralize( \IPS\Member::loggedIn()->language()->get('renew_years'), array( $term ) );
							break;
					}
				}
			}
			
			/* Display */
			$formKey = "package_{$package->id}_submitted";
			if ( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->$formKey ) )
			{
				\IPS\Output::i()->sendOutput( $this->_getForm( $package, $inCart, TRUE ), 500 );
			}
			else
			{
				/* Set default search */
				\IPS\Output::i()->defaultSearchOption = array( 'nexus_package_item', "nexus_package_item_el" );		
				
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('store')->package( $package, $item, $this->_getForm( $package, $inCart, TRUE ), array_sum( $inCart ), $shippingMethods, $itemDataForShipping, $locationType, $renewalTerm, $initialTerm );	
			}

			try
			{
				$price = $package->price();
			}
			catch( \OutOfBoundsException $e )
			{
				$price = NULL;
			}

			/* Facebook Pixel */
			\IPS\core\Facebook\Pixel::i()->ViewContent = array(
				'content_ids' => array( $package->id ),
				'content_type' => 'product'
			);

			/* A product MUST have an offer, so if there's no price (i.e. due to currency configuration) don't even output */
			if( $price !== NULL )
			{
				\IPS\Output::i()->jsonLd['package']	= array(
					'@context'		=> "http://schema.org",
					'@type'			=> "Product",
					'name'			=> $package->_title,
					'description'	=> $item->truncated( TRUE, NULL ),
					'category'		=> $item->container()->_title,
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

				/* Stock status */
				if( $package->physical )
				{
					if( $package->stockLevel() === 0 )
					{
						\IPS\Output::i()->jsonLd['package']['offers']['availability'] = 'http://schema.org/OutOfStock';
					}
					else
					{
						\IPS\Output::i()->jsonLd['package']['offers']['availability'] = 'http://schema.org/InStock';
					}
				}

				if( $package->image )
				{
					\IPS\Output::i()->jsonLd['package']['image'] = (string) $package->image;
					\IPS\Output::i()->metaTags['og:image'] = (string)$package->image;
				}

				if( $package->reviewable AND $item->averageReviewRating() )
				{
					\IPS\Output::i()->jsonLd['package']['aggregateRating'] = array(
						'@type'			=> 'AggregateRating',
						'ratingValue'	=> $item->averageReviewRating(),
						'ratingCount'	=> $item->reviews
					);
				}

				if( isset( $length ) )
				{
					\IPS\Output::i()->jsonLd['package']['depth'] = array( '@type' => 'Distance', 'name' => $length->string() );
					\IPS\Output::i()->jsonLd['package']['width'] = array( '@type' => 'Distance', 'name' => $width->string() );
					\IPS\Output::i()->jsonLd['package']['height'] = array( '@type' => 'Distance', 'name' => $height->string() );
					\IPS\Output::i()->jsonLd['package']['weight'] = array( '@type' => 'QuantitativeValue', 'name' => $weight->string() );
				}
			}
		}		
	}

	/**
	 * Get form
	 *
	 * @param	\IPS\nexus\Package	$package	The package
	 * @param	array				$inCart		The number in the cart already for each of the field combinations
	 * @param	bool				$verticalForm	Whether to output a vertical form (true) or not
	 * @return	string
	 */
	protected function _getForm( \IPS\nexus\Package $package, $inCart, $verticalForm = FALSE )
	{
		/* Is this a subscription package that we've already bought? */
		if ( $package->subscription )
		{
			try
			{
				$purchase = \IPS\nexus\Purchase::constructFromData( \IPS\Db::i()->select( '*', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=? AND ps_cancelled=0 AND ps_member=?', 'nexus', 'package', $package->id, \IPS\Member::loggedIn()->member_id ) )->first() );
				return \IPS\Theme::i()->getTemplate( 'store', 'nexus' )->subscriptionPurchase( $purchase );
			}
			catch ( \UnderflowException $e ) {}
		}
		
		/* Get member's currency */
		$memberCurrency = ( ( isset( \IPS\Request::i()->cookie['currency'] ) and \in_array( \IPS\Request::i()->cookie['currency'], \IPS\nexus\Money::currencies() ) ) ? \IPS\Request::i()->cookie['currency'] : \IPS\nexus\Customer::loggedIn()->defaultCurrency() );
				
		/* Init form */		
		$form = new \IPS\Helpers\Form( "package_{$package->id}", 'add_to_cart' );

		if ( $verticalForm )
		{
			$form->class = 'ipsForm_vertical';
		}
		
		/* Package-dependant fields */
		$package->storeForm( $form, $memberCurrency );
		
		/* Are we in stock? */
		if ( $package->stock != -1 and $package->stock != -2 and ( $package->stock - array_sum( $inCart ) ) <= 0 )
		{
			$form->actionButtons = array();
			$form->addButton( 'out_of_stock', 'submit', NULL, 'ipsButton ipsButton_primary', array( 'disabled' => 'disabled' ) );
		}
		
		/* And is it available for our currency */
		else
		{
			try
			{
				$price = $package->price();
			}
			catch ( \OutOfBoundsException $e )
			{
				$form->actionButtons = array();
				$form->addButton( 'currently_unavailable', 'submit', NULL, 'ipsButton ipsButton_primary', array( 'disabled' => 'disabled' ) );
			}
		}

		/* Associate */
		if ( \count( $package->associablePackages() ) )
		{
			$associableIds = array_keys( $package->associablePackages() );
			$associableOptions = array();
			foreach ( $_SESSION['cart'] as $k => $item )
			{
				if ( \in_array( $item->id, $associableIds ) )
				{
					for ( $i = 0; $i < $item->quantity; $i++ )
					{
						$name = $item->name;
						if ( \count( $item->details ) )
						{
							$customFields = \IPS\nexus\Package\CustomField::roots();
							$stickyFields = array();
							foreach ( $item->details as $_k => $v )
							{
								if ( $v and isset( $customFields[ $_k ] ) and $customFields[ $_k ]->sticky )
								{
									$stickyFields[] = $v;
								}
							}
							if ( \count( $stickyFields ) )
							{
								$name .= ' (' . implode( ' &middot; ', $stickyFields ) . ')';
							}
						}
						$associableOptions['in_cart']["0.{$k}"] = $name;
					}
				}
			}
			foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_purchases', array( array( 'ps_member=? AND ps_app=? AND ps_type=?', \IPS\nexus\Customer::loggedIn()->member_id, 'nexus', 'package' ), \IPS\Db::i()->in( 'ps_item_id', $associableIds ) ) ), 'IPS\nexus\Purchase' ) as $purchase )
			{
				$associableOptions['existing_purchases']["1.{$purchase->id}"] = $purchase->name;
			}
			
			if ( !empty( $associableOptions ) )
			{
				if ( !$package->force_assoc )
				{
					array_unshift( $associableOptions, 'do_not_associate' );
				}
				$form->add( new \IPS\Helpers\Form\Select( 'associate_with', NULL, $package->force_assoc, array( 'options' => $associableOptions ) ) );
			}
			elseif ( $package->force_assoc )
			{
				return \IPS\Member::loggedIn()->language()->addToStack("nexus_package_{$package->id}_assoc");
			}
		}
		
		/* Renewal options */
		$renewOptions = $package->renew_options ? json_decode( $package->renew_options, TRUE ) : array();
		if ( \count( $renewOptions ) > 1 )
		{
			$sortedRenewOptions = array();
			foreach ( $renewOptions as $k => $option )
			{
				if ( isset( $renewOptions[ $k ]['cost'][ $memberCurrency ] ) )
				{
					$sortedRenewOptions[ $k ] = new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( $option['cost'][ $memberCurrency ]['amount'], $memberCurrency ), new \DateInterval( 'P' . $option['term'] . mb_strtoupper( $option['unit'] ) ), $package->tax ? \IPS\nexus\Tax::load( $package->tax ) : NULL, $option['add'], $package->grace_period ? new \DateInterval( 'P' . $package->grace_period . 'D' ) : NULL );
				}
			}
			uasort( $sortedRenewOptions, function( $a, $b ) {
				return $a->days() - $b->days();
			} );
			
			$options = array();
			$first = NULL;
			foreach ( $sortedRenewOptions as $k => $term )
			{
				$saving = NULL;
				if ( \IPS\Settings::i()->nexus_show_renew_option_savings != 'none' )
				{
					if ( $first === NULL )
					{
						$first = $term;
					}
					else
					{
						$saving = $term->diff( $first, \IPS\Settings::i()->nexus_show_renew_option_savings == 'percent' );
					}
				}
				
				if ( $saving and ( ( \IPS\Settings::i()->nexus_show_renew_option_savings == 'percent' and $saving->isGreaterThanZero() ) or ( \IPS\Settings::i()->nexus_show_renew_option_savings == 'amount' and $saving->amount->isGreaterThanZero() ) ) )
				{
					if ( \IPS\Settings::i()->nexus_show_renew_option_savings == 'percent' )
					{
						$options[ $k ] = \IPS\Member::loggedIn()->language()->addToStack( 'renewal_amount_with_pc_saving', FALSE, array( 'sprintf' => array( $term->toDisplay(), $saving->round(1) ) ) );
					}
					else
					{
						$options[ $k ] = \IPS\Member::loggedIn()->language()->addToStack( 'renewal_amount_with_cost_saving', FALSE, array( 'sprintf' => array( $term->toDisplay(), $saving ) ) );
					}
				}
				else
				{
					$options[ $k ] = $term->toDisplay();
				}
			}
			
			ksort( $options );

			$form->add( new \IPS\Helpers\Form\Radio( 'renewal_term', NULL, TRUE, array( 'options' => $options ) ) );
		}
				
		/* Custom Fields */
		$customFields = \IPS\nexus\Package\CustomField::roots( NULL, NULL, array( array( 'cf_purchase=1' ), array( \IPS\Db::i()->findInSet( 'cf_packages', array( $package->id ) ) ) ) );
		foreach ( $customFields as $field )
		{
			if( \IPS\Request::i()->isAjax() && isset( \IPS\Request::i()->stockCheck ) )
			{
				$field->required = FALSE; // Otherwise a required text field (for example) can block the price changing when a different radio (for example) value is selected
			}
			
			$form->add( $field->buildHelper() );
		}
		
		/* Is this the validation for the additional page? */
		if ( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->additionalPageCheck ) )
		{
			if ( $additionalPage = $package->storeAdditionalPage( $_POST ) )
			{
				\IPS\Output::i()->httpHeaders['X-IPS-FormNoSubmit'] = "true";
				\IPS\Output::i()->json( $additionalPage );
			}
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Custom fields */
			$details = array();
			$editorUploadIds = array();
			foreach ( $customFields as $field )
			{
				if ( isset( $values[ 'nexus_pfield_' . $field->id ] ) )
				{
					$class = $field->buildHelper();
					if ( $class instanceof \IPS\Helpers\Form\Upload )
					{
						$details[ $field->id ] = (string) $values[ 'nexus_pfield_' . $field->id ];
					}
					else
					{
						$details[ $field->id ] = $class::stringValue( $values[ 'nexus_pfield_' . $field->id ] );
					}
					
					if ( !isset( \IPS\Request::i()->stockCheck ) and $field->type === 'Editor' )
					{
						$uploadId = \IPS\Db::i()->insert( 'nexus_cart_uploads', array(
							'session_id'	=> \IPS\Session::i()->id,
							'time'			=> time()
						) );
						$field->claimAttachments( $uploadId, 'cart' );
						$editorUploadIds[] = $uploadId;
					}
				}
			}
			$optionValues = $package->optionValues( $details );
						
			/* Stock check */
			$quantity = isset( $values['quantity'] ) ? $values['quantity'] : 1;
			try
			{
				$data = $package->optionValuesStockAndPrice( $optionValues, TRUE );
			}
			catch ( \UnderflowException $e )
			{
				\IPS\Output::i()->error( 'product_options_price_error', '3X240/2', 500, 'product_options_price_error_admin' );
			}
			$inCartForThisFieldCombination = isset( $inCart[ json_encode( $optionValues ) ] ) ? $inCart[ json_encode( $optionValues ) ] : 0;
			
			if ( \IPS\Request::i()->isAjax() && isset( \IPS\Request::i()->stockCheck ) )
			{
				/* Stock */
				if ( $data['stock'] == -1 )
				{
					$return = array(
						'stock'	=> '',
						'okay'	=> true
					);
				}
				else
				{					
					$return = array(
						'stock'	=> \IPS\Member::loggedIn()->language()->addToStack( 'x_in_stock', FALSE, array( 'pluralize' => array( $data['stock'] - $inCartForThisFieldCombination ) ) ),
						'okay'	=> ( $data['stock'] - $inCartForThisFieldCombination > 0 ) ? true : false,
					);
				}
							
				/* Price */	
				$_data = $package->optionValuesStockAndPrice( $optionValues, FALSE );
				$normalPrice = $_data['price'];
				
				/* Renewals */
				$renewOptions = $package->renew_options ? json_decode( $package->renew_options, TRUE ) : array();
				if ( !empty( $renewOptions ) )
				{
					$term = ( isset( \IPS\Request::i()->renewal_term ) and isset( $renewOptions[ \IPS\Request::i()->renewal_term ] ) ) ? $renewOptions[ \IPS\Request::i()->renewal_term ] : array_shift( $renewOptions );

					switch( $term['unit'] )
					{
						case 'd':
							$initialTerm = \IPS\Member::loggedIn()->language()->pluralize( \IPS\Member::loggedIn()->language()->get('renew_days'), array( $term['term'] ) );
							break;
						case 'm':
							$initialTerm = \IPS\Member::loggedIn()->language()->pluralize( \IPS\Member::loggedIn()->language()->get('renew_months'), array( $term['term'] ) );
							break;
						case 'y':
							$initialTerm = \IPS\Member::loggedIn()->language()->pluralize( \IPS\Member::loggedIn()->language()->get('renew_years'), array( $term['term'] ) );
							break;
					}

					$return['initialTerm'] = sprintf( \IPS\Member::loggedIn()->language()->get('package_initial_term_title'), $initialTerm );

					if ( $term['add'] )
					{
						$data['price']->amount = $data['price']->amount->add( new \IPS\Math\Number( number_format( $term['cost'][ $memberCurrency ]['amount'], \IPS\nexus\Money::numberOfDecimalsForCurrency( $memberCurrency ), '.', '' ) ) );
						$normalPrice->amount = $normalPrice->amount->add( new \IPS\Math\Number( number_format( $term['cost'][ $memberCurrency ]['amount'], \IPS\nexus\Money::numberOfDecimalsForCurrency( $memberCurrency ), '.', '' ) ) );
					}
					
					$return['renewal'] = ( new \IPS\nexus\Purchase\RenewalTerm(
						new \IPS\nexus\Money(
							( new \IPS\Math\Number( number_format( $term['cost'][ $memberCurrency ]['amount'], \IPS\nexus\Money::numberOfDecimalsForCurrency( $memberCurrency ), '.', '' ) ) )
								->add( ( new \IPS\Math\Number( number_format( $_data['renewalAdjustment'], \IPS\nexus\Money::numberOfDecimalsForCurrency( $memberCurrency ), '.', '' ) ) ) )
						, $memberCurrency ),
						new \DateInterval( 'P' . $term['term'] . mb_strtoupper( $term['unit'] ) ),
						$package->tax ? \IPS\nexus\Tax::load( $package->tax ) : NULL, $term['add']
					) )->toDisplay();
				}
				else
				{
					$return['renewal'] = '';
				}
				
				/* Include tax? */
				if ( \IPS\Settings::i()->nexus_show_tax and $package->tax )
				{
					try
					{
						$taxRate = new \IPS\Math\Number( \IPS\nexus\Tax::load( $package->tax )->rate( \IPS\nexus\Customer::loggedIn()->estimatedLocation() ) );
						
						$data['price']->amount = $data['price']->amount->add( $data['price']->amount->multiply( $taxRate ) );
						$normalPrice->amount = $normalPrice->amount->add( $normalPrice->amount->multiply( $taxRate ) );
					}
					catch ( \OutOfRangeException $e ) { }
				}
				
				/* Format and return */
				if ( $data['price']->amount->compare( $normalPrice->amount ) !== 0 )
				{
					$return['price'] = \IPS\Theme::i()->getTemplate( 'store', 'nexus' )->priceDiscounted( $normalPrice, $data['price'], FALSE, FALSE, NULL );
				}
				else
				{
					$return['price'] = \IPS\Theme::i()->getTemplate( 'store', 'nexus' )->price( $data['price'], FALSE, FALSE, NULL );
				}
				\IPS\Output::i()->json( $return );
			}
			elseif ( $data['stock'] != -1 and ( $data['stock'] - $inCartForThisFieldCombination ) < $quantity )
			{
				$form->error = \IPS\Member::loggedIn()->language()->addToStack( 'not_enough_in_stock', FALSE, array( 'sprintf' => array( $data['stock'] - $inCartForThisFieldCombination ) ) );
				return (string) $form;
			}
			
			if ( ( !isset( \IPS\Request::i()->additionalPageCheck ) or !\IPS\Request::i()->isAjax() ) and $additionalPage = $package->storeAdditionalPage( $_POST ) )
			{
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->json( array( 'dialog' => $additionalPage ) );
				}
				else
				{
					return $additionalPage;
				}
			}
						
			/* Work out renewal term */
			$renewalTerm = NULL;
			if ( \count( $renewOptions ) )
			{
				if ( \count( $renewOptions ) === 1 )
				{
					$chosenRenewOption = array_pop( $renewOptions );
				}
				else
				{
					$chosenRenewOption = $renewOptions[ $values['renewal_term'] ];
				}
				
				$renewalTerm = new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( ( new \IPS\Math\Number( number_format( $chosenRenewOption['cost'][ $memberCurrency ]['amount'], 2, '.', '' ) ) )->add( new \IPS\Math\Number( number_format( $data['renewalAdjustment'], 2, '.', '' ) ) ), $memberCurrency ), new \DateInterval( 'P' . $chosenRenewOption['term'] . mb_strtoupper( $chosenRenewOption['unit'] ) ), $package->tax ? \IPS\nexus\Tax::load( $package->tax ) : NULL, $chosenRenewOption['add'], $package->grace_period ? new \DateInterval( 'P' . $package->grace_period . 'D' ) : NULL );
			}
			
			/* Associations */
			$parent = NULL;
			if ( isset( $values['associate_with'] ) and $values['associate_with'] )
			{
				$exploded = explode( '.', $values['associate_with'] );
				if ( $exploded[0] )
				{
					$parent = \IPS\nexus\Purchase::load( $exploded[1] );
				}
				else
				{
					$parent = (int) $exploded[1];
				}
			}
			
			/* Actually add to cart */
			$cartId = $package->addItemsToCartData( $details, $quantity, $renewalTerm, $parent, $values );
			\IPS\Db::i()->update( 'nexus_cart_uploads', array( 'item_id' => $cartId ), \IPS\Db::i()->in( 'id', $editorUploadIds ) );

			/* Redirect or AJAX */
			if ( \IPS\Request::i()->isAjax() )
			{
				/* Upselling? */
				$upsell = \IPS\nexus\Package\Item::getItemsWithPermission( array( array( 'p_upsell=1' ), array( \IPS\Db::i()->findInSet( 'p_associable', array( $package->id ) ) ) ), 'p_position' );
				
				/* Send */
				\IPS\Output::i()->json( array( 'dialog' => \IPS\Theme::i()->getTemplate('store')->cartReview( $package, $quantity, $upsell ), 'cart' => \IPS\Theme::i()->getTemplate('store')->cartHeader(), 'css' => \IPS\Output::i()->cssFiles ) );
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=store&controller=cart&added=' . $package->id, 'front', 'store_cart' ) );	
			}			
		}
		
		
		return (string) $form;
	}
}