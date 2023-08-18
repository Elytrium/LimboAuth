<?php
/**
 * @brief		Invoice Abstract Item Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		10 Feb 2014
 */

namespace IPS\nexus\Invoice;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Invoice Abstract Item Interface
 */
abstract class _Item
{
	/**
	 * @brief	Can use coupons?
	 */
	public static $canUseCoupons = TRUE;
	
	/**
	 * @brief	Can use account credit?
	 */
	public static $canUseAccountCredit = TRUE;
	
	/**
	 * @brief	string	Name
	 */
	public $name;
	
	/**
	 * @brief	int	Quantity
	 */
	public $quantity = 1;
	
	/**
	 * @brief	\IPS\nexus\Money	Price
	 */
	public $price;
	
	/**
	 * @brief	int|NULL	ID
	 */
	public $id;
	
	/**
	 * @brief	\IPS\nexus\Tax		Tax Class
	 */
	public $tax;
	
	/**
	 * @brief	Payment Methods IDs
	 */
	public $paymentMethodIds;
	
	/**
	 * @brief	Key/Value array of extra details to display on the invoice
	 */
	public $details = array();
	
	/**
	 * @brief	Key/Value array of extra details to store on the purchase
	 */
	public $purchaseDetails = array();
	
	/**
	 * @brief	Physical?
	 */
	public $physical = FALSE;
	
	/**
	 * @brief	Shipping Methods IDs
	 */
	public $shippingMethodIds;
	
	/**
	 * @brief	Chosen Shipping Methods ID
	 */
	public $chosenShippingMethodId;
	
	/**
	 * @brief	Weight
	 */
	public $weight;
	
	/**
	 * @brief	Length
	 */
	public $length;
	
	/**
	 * @brief	Width
	 */
	public $width;
	
	/**
	 * @brief	Height
	 */
	public $height;
		
	/**
	 * @brief	Pay To member
	 */
	public $payTo;
	
	/**
	 * @brief	Application
	 */
	public static $application;
	
	/**
	 * @brief	Commission percentage
	 */
	public $commission = 0;
	
	/**
	 * @brief	Commission fee
	 */
	public $fee = 0;
	
	/**
	 * @brief	Extra
	 */
	public $extra;
	
	/**
	 * @brief	Group With Parent
	 */
	public $groupWithParent = FALSE;

	/**
	 * Constructor
	 *
	 * @param	string				$name	Name
	 * @param	\IPS\nexus\Money	$price	Price
	 * @return	void
	 */
	public function __construct( $name, \IPS\nexus\Money $price )
	{
		$this->name = $name;
		$this->price = $price;
	}
	
	/**
	 * Get (can be used to override static properties like icon and title in an instance)
	 *
	 * @param	string	$k	Property
	 * @return	mixed
	 */
	public function __get( $k )
	{
		$k = mb_substr( $k, 1 );
		return static::$$k;
	}
	
	/**
	 * Get line price without tax
	 *
	 * @return	\IPS\nexus\Money
	 */
	public function linePrice()
	{
		return new \IPS\nexus\Money( $this->price->amount->multiply( new \IPS\Math\Number("{$this->quantity}") ), $this->price->currency );
	}
	
	/**
	 * Get tax rate
	 *
	 * @param	\IPS\GeoLocation|NULL	$location	Location to use for tax rate or NULL to use specified billing address
	 * @return	\IPS\Math\Number
	 */
	public function taxRate( \IPS\GeoLocation $location = NULL )
	{
		return new \IPS\Math\Number( $this->tax ? $this->tax->rate( $location ) : '0' );
	}
	
	/**
	 * Get item price with tax
	 *
	 * @param	\IPS\GeoLocation|NULL	$location	Location to use for tax rate or NULL to use specified billing address
	 * @return	\IPS\nexus\Money
	 */
	public function grossPrice( \IPS\GeoLocation $location = NULL )
	{
		return new \IPS\nexus\Money( $this->price->amount->add( $this->price->amount->multiply( $this->taxRate( $location ) ) ), $this->price->currency );
	}
	
	/**
	 * Get line price with tax
	 *
	 * @param	\IPS\GeoLocation|NULL	$location	Location to use for tax rate or NULL to use specified billing address
	 * @return	\IPS\nexus\Money
	 */
	public function grossLinePrice( \IPS\GeoLocation $location = NULL )
	{
		return new \IPS\nexus\Money( $this->linePrice()->amount->add( $this->linePrice()->amount->multiply( $this->taxRate( $location ) ) ), $this->price->currency );
	}
	
	/**
	 * Get recipient amounts
	 *
	 * @return	array
	 */
	public function recipientAmounts()
	{
		$return = array();
		
		if ( $this->payTo )
		{
			$linePrice = $this->linePrice();
			$currency = $linePrice->currency;
						
			$commission = $this->price->amount->percentage( $this->commission );
			$lineComission = $commission->multiply( new \IPS\Math\Number("{$this->quantity}") );
			
			$return['site_commission_unit'] = new \IPS\nexus\Money( $commission, $currency );
			$return['site_commission_line'] = new \IPS\nexus\Money( $lineComission, $currency );
			
			$return['recipient_unit'] = new \IPS\nexus\Money( $this->price->amount->subtract( $return['site_commission_unit']->amount ), $currency );
			$return['recipient_line'] = new \IPS\nexus\Money( $linePrice->amount->subtract( $return['site_commission_line']->amount ), $currency );
			
			$fee = $this->fee ? $this->fee->amount : new \IPS\Math\Number('0');
			$siteTotal = $return['site_commission_line']->amount->add( $fee );
			$recipientTotal = $linePrice->amount->subtract( $siteTotal );
			$return['site_total'] = new \IPS\nexus\Money( $siteTotal, $currency );
			$return['recipient_final'] = new \IPS\nexus\Money( $recipientTotal->isGreaterThanZero() ? $recipientTotal : 0, $currency );
		}
		else
		{
			$return['site_total'] = $this->linePrice()->amount;
		}
		
		return $return;
	}
	
	/**
	 * Get amount for recipient (on line price)
	 *
	 * @return	\IPS\nexus\Money
	 * @throws	\BadMethodCallException
	 */
	public function amountForRecipient()
	{
		if ( !$this->payTo )
		{
			throw new \BadMethodCallException;
		}
		
		$recipientAmount = $this->recipientAmounts();
		return $recipientAmount['recipient_final'];
	}
	
	/**
	 * Image
	 *
	 * @return |IPS\File|NULL
	 */
	public function image()
	{
		return NULL;
	}
	
	/**
	 * On Paid
	 *
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	void
	 */
	public function onPaid( \IPS\nexus\Invoice $invoice )
	{
		
	}
	
	/**
	 * On Unpaid description
	 *
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	array
	 */
	public function onUnpaidDescription( \IPS\nexus\Invoice $invoice )
	{
		return array();
	}
	
	/**
	 * On Unpaid
	 *
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @param	string				$status		Status
	 * @return	void
	 */
	public function onUnpaid( \IPS\nexus\Invoice $invoice, $status )
	{
		
	}
	
	/**
	 * On Invoice Cancel (when unpaid)
	 *
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	void
	 */
	public function onInvoiceCancel( \IPS\nexus\Invoice $invoice )
	{
		
	}
	
	/**
	 * Check for member
	 * If a user initially checks out as a guest and then logs in during checkout, this method
	 * is ran to check the items they are purchasing can be bought.
	 * Is expected to throw a DomainException with an error message to display to the user if not valid
	 *
	 * @param	\IPS\Member	$member	The new member
	 * @return	void
	 * @throws	\DomainException
	 */
	public function memberCanPurchase( \IPS\Member $member )
	{
		
	}
	
	/**
	 * Requires Billing Address
	 *
	 * @return	bool
	 * @throws	\DomainException
	 */
	public function requiresBillingAddress()
	{
		return \in_array( 'other', explode( ',', \IPS\Settings::i()->nexus_require_billing ) );
	}
	
	/**
	 * Client Area URL
	 *
	 * @return |IPS\Http\Url|NULL
	 */
	public function url()
	{
		return NULL;
	}
	
	/**
	 * ACP URL
	 *
	 * @return |IPS\Http\Url|NULL
	 */
	public function acpUrl()
	{
		return NULL;
	}
	
	/**
	 * Is this item the same as another item in the cart?
	 * Used to decide when an item is added to the cart if we should just increase the quantity of this item instead  of creating a new item.
	 *
	 * @param	\IPS\nexus\Invoice\Item		$item	The other item
	 * @return	bool
	 */
	public function isSameAsOtherItem( $item )
	{
		/* If one is set to be associated with something in the cart and the other is set to be associated
		with an existing purchase, trying to compare those will throw an error */
		if ( isset( $item->parent ) and isset( $this->parent ) )
		{			
			if ( \gettype( $this->parent ) != \gettype( $item->parent ) )
			{
				return FALSE;
			}
		}
		
		/* Assume the quantities are the same */
		$cloned = clone $item;
		$cloned->quantity = $this->quantity;
		
		/* Compare */
		return ( $cloned == $this );
	}
	
	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse		string								name			Item name
	 * @apiresponse		string								itemApp			Key for application. For example, 'nexus' for products and renewals; 'downloads' for Downloads files
	 * @apiresponse		string								itemType		Key for item type. For example, 'package' for products; 'file' for Downloads files.
	 * @apiresponse		int									itemId			The ID for the item. For example, the product ID or the file ID.
	 * @apiresponse		string								itemUrl			Any relevant URL (for example, for Downloads files, this will be the URL to view the file)
	 * @apiresponse		string								itemImage		If the item has a relevant image (for exmaple, product image, Downloads file screenshot), the URL to it
	 * @apiresponse		int									quantity		The quantity being purchased
	 * @apiresponse		\IPS\nexus\Money					itemPrice		Item price, before tax
	 * @apiresponse		\IPS\nexus\Money					linePrice		Line price, before tax
	 * @apiresponse		\IPS\nexus\Tax						taxClassId		If the item should be taxed, the Tax Class that applies
	 * @apiresponse		bool								physical		If the item is physical
	 * @apiresponse		float								weight			If the item is physical, the weight in kilograms
	 * @apiresponse		float								length			If the item is physical, the length in metres
	 * @apiresponse		float								width			If the item is physical, the width in metres
	 * @apiresponse		float								height			If the item is physical, the height in metres
	 * @apiresponse		\IPS\nexus\Purchase\RenewalTerm		renewalTerm		If the item renews, the renewal term
	 * @apiresponse		datetime							expireDate		If the item has been set to expire at a certain date but not automatically renew, the dare it will expire
	 * @apiresponse		object								details			The values for any custom package fields
	 * @apiresponse		\IPS\nexus\Purchase					parentPurchase	If when the item has been purchased it will be a child of an existing purchase, the parent purchase
	 * @apiresponse		int									parentItem		If when the item has been purchased it will be a child of another item on the same invoice, the ID number of the item that will be the parent
	 * @apiresponse		bool								groupWithParent	If when the item has been purchased it will have its renewals grouped with its parent
	 * @apiresponse		\IPS\nexus\Customer					payTo			If the payment for this item goes to another user (for example for Downloads files), the user who will receive the payment
	 * @apiresponse		float								commission		If the payment for this item goes to another user (for example for Downloads files), the percentage of the price that will be retained by the site (in addition to fee)
	 * @apiresponse		\IPS\nexus\Money					fee				If the payment for this item goes to another user (for example for Downloads files), the fee that will be deducted by the site (in addition to commission)
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		return array(
			'name'				=> $this->name,
			'itemApp'			=> $this->appKey,
			'itemType'			=> $this->typeKey,
			'itemId'			=> $this->id,
			'itemUrl'			=> $this->url() ? ( (string) $this->url() ) : null,
			'itemImage'			=> $this->image() ? ( (string) $this->image()->url ) : null,
			'quantity'			=> $this->quantity,
			'itemPrice'			=> $this->price->apiOutput( $authorizedMember ),
			'linePrice'			=> $this->linePrice()->apiOutput( $authorizedMember ),
			'taxClass'			=> $this->tax ? $this->tax->apiOutput( $authorizedMember ) : null,
			'physical'			=> $this->physical,
			'weight'			=> isset( $this->weight ) ? $this->weight->kilograms : null,
			'length'			=> isset( $this->length ) ? $this->length->metres : null,
			'width'				=> isset( $this->width ) ? $this->width->metres : null,
			'height'			=> isset( $this->height ) ? $this->height->metres : null,
			'renewalTerm'		=> isset( $this->renewalTerm ) ? $this->renewalTerm->apiOutput( $authorizedMember ) : null,
			'expireDate'		=> isset( $this->expireDate ) ? $this->expireDate->rfc3339() : null,
			'details'			=> $this->details,
			'parentPurchase'	=> ( isset( $this->parent ) and $this->parent instanceof \IPS\nexus\Purchase ) ? $this->parent->apiOutput( $authorizedMember ) : null,
			'parentItem'		=> ( isset( $this->parent ) and \is_int( $this->parent ) ) ? $this->parent : null,
			'groupWithParent'	=> isset( $this->groupWithParent ) ? $this->groupWithParent : false,
			'payTo'				=> $this->payTo ? $this->payTo->apiOutput( $authorizedMember ) : null,
			'commission'		=> $this->commission,
			'fee'				=> $this->fee ? $this->fee->apiOutput( $authorizedMember ) : null,
		);
	}
}