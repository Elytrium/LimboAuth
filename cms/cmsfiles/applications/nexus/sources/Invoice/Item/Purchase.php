<?php
/**
 * @brief		Invoice Item Class for Purchases
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		10 Feb 2014
 */

namespace IPS\nexus\Invoice\Item;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Invoice Item Class for Purchases
 */
abstract class _Purchase extends \IPS\nexus\Invoice\Item
{
	/**
	 * @brief	string	Act (new/charge)
	 */
	public static $act = 'new';
	
	/**
	 * @brief	Requires login to purchase?
	 */
	public static $requiresAccount = TRUE;
		
	/**
	 * @brief	\DateInterval	Length granted by initial purchase before normal renewal term starts (or NULL to match renewal term)
	 */
	public $initialInterval;

	/**
	 * @brief	\IPS\nexus\Purchase\RenewalTerm	Renewal Term
	 */
	public $renewalTerm;
	
	/**
	 * @brief	\IPS\DateTime	Expiry Date (only if the purchase needs to expire but not renew)
	 */
	public $expireDate;
	
	/**
	 * @brief	\IPS\nexus\Purchase|int	The parent purchase or item ID
	 */
	public $parent = NULL;
	
	/**
	 * @brief	bool	Group with parent?
	 */
	public $groupWithParent = FALSE;
	
	/**
	 * Get Icon
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	string
	 */
	public static function getIcon( \IPS\nexus\Purchase $purchase )
	{
		return static::$icon;
	}
	
	/**
	 * Get Title
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	string
	 */
	public static function getTypeTitle( \IPS\nexus\Purchase $purchase )
	{
		return static::$title;
	}
	
	/**
	 * Image
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return |IPS\File|NULL
	 */
	public static function purchaseImage( \IPS\nexus\Purchase $purchase )
	{
		return NULL;
	}
	
	/**
	 * Get purchases made by a customer of this item
	 *
	 * @param	\IPS\nexus\Customer	$customer			The customer
	 * @param	int|array|NULL		$id					Item ID(s)
	 * @param	bool				$includeInactive	Include expired purchases?
	 * @param	bool				$includeCanceled	Include canceled purchases?
	 * @return	\IPS\Patterns\ActiveRecordIterator
	 */
	public static function getPurchases( \IPS\nexus\Customer $customer, $id = NULL, $includeInactive = TRUE, $includeCanceled = FALSE )
	{
		$where = array( array( 'ps_app=? AND ps_type=? AND ps_member=?', static::$application, static::$type, $customer->member_id ) );
		if ( $id !== NULL )
		{
			if ( \is_array( $id ) )
			{
				$where[] = array( \IPS\Db::i()->in( 'ps_item_id', $id ) );
			}
			else
			{
				$where[] = array( 'ps_item_id=?', $id );
			}
		}
		if ( !$includeInactive )
		{
			$where[] = array( 'ps_active=1' );
		}
		if ( !$includeCanceled )
		{
			$where[] = array( 'ps_cancelled=0' );
		}

		return new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_purchases', $where ), 'IPS\nexus\Purchase' );
	}
	
	/**
	 * Get additional name info
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	array
	 */
	public static function getPurchaseNameInfo( \IPS\nexus\Purchase $purchase )
	{
		return array();
	}
	
	/**
	 * Get ACP Page HTML
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	string
	 */
	public static function acpPage( \IPS\nexus\Purchase $purchase )
	{
		return '';
	}
	
	/**
	 * Get ACP Page Buttons
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @param	\IPS\Http\Url		$url		The page URL
	 * @return	array
	 */
	public static function acpButtons( \IPS\nexus\Purchase $purchase, \IPS\Http\Url $url )
	{
		return array();
	}
	
	/**
	 * ACP Action
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public static function acpAction( \IPS\nexus\Purchase $purchase )
	{
		
	}
	
	/** 
	 * ACP Edit Form
	 *
	 * @param	\IPS\nexus\Purchase				$purchase	The purchase
	 * @param	\IPS\Helpers\Form				$form	The form
	 * @param	\IPS\nexus\Purchase\RenewalTerm	$renewals	The renewal term
	 * @return	string
	 */
	public static function acpEdit( \IPS\nexus\Purchase $purchase, \IPS\Helpers\Form $form, $renewals )
	{
		$form->add( new \IPS\Helpers\Form\Text( 'ps_name', $purchase->_name, TRUE, array( 'maxLength' => 128 ) ) );
		
		if ( !$purchase->grouped_renewals and ( !$purchase->billing_agreement or $purchase->billing_agreement->canceled ) )
		{
			$form->add( new \IPS\Helpers\Form\Date( 'ps_expire', $purchase->expire ?: 0, FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'does_not_expire', 'disabled' => !$purchase->canChangeExpireDate() ) ) );
		}
		
		if ( !$purchase->billing_agreement or $purchase->billing_agreement->canceled )
		{
			$form->add( new \IPS\nexus\Form\RenewalTerm( 'ps_renewals', $renewals, FALSE, array( 'lockTerm' => !$purchase->canChangeExpireDate() ) ) );
			$form->add( new \IPS\Helpers\Form\Interval( 'ps_grace_period', $purchase->grace_period / 86400, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'max' => \IPS\Settings::i()->cm_invoice_expireafter ?: NULL, 'min' => NULL ), NULL, NULL, NULL ) );
		}
		
		if ( !$purchase->grouped_renewals )
		{
			$form->add( new \IPS\Helpers\Form\Node( 'ps_parent', $purchase->parent(), FALSE, array( 'class' => 'IPS\nexus\Purchase', 'forceOwner' => $purchase->member, 'zeroVal' => 'no_parent', 'disabledIds' => array( $purchase->id ) ) ) );
		}
	}
	
	/** 
	 * ACP Edit Save
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @param	array				$values		Values from form
	 * @return	string
	 */
	public static function acpEditSave( \IPS\nexus\Purchase $purchase, array $values )
	{
		$purchase->name = $values['ps_name'];

		/* Figure out tax for renewals */
		$tax = NULL;

		try
		{
			if( $purchase->tax )
			{
				$tax = \IPS\nexus\Tax::load( $purchase->tax );
			}
		}
		catch( \OutOfRangeException $e ){}

		if( $tax AND $values['ps_renewals'] )
		{
			$values['ps_renewals']->tax = $tax;
		}

		/* Then save */
		if ( $purchase->grouped_renewals )
		{
			$purchase->ungroupFromParent();
			if ( !$purchase->billing_agreement or $purchase->billing_agreement->canceled )
			{
				$purchase->renewals = $values['ps_renewals'];
			}
			$purchase->save();
			$purchase->groupWithParent();
		}
		else
		{
			if ( !$purchase->billing_agreement or $purchase->billing_agreement->canceled )
			{
				$purchase->expire = ( $values['ps_expire'] ?: NULL );
				$purchase->renewals = $values['ps_renewals'];
				$purchase->grace_period = $values['ps_grace_period'] ? ( $values['ps_grace_period'] * 86400 ) : 0;
			}
			$purchase->parent = $values['ps_parent'] ?: NULL;
			$purchase->save();
		}
	}
	
	/**
	 * Get Client Area Page HTML
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	string
	 */
	public static function clientAreaPage( \IPS\nexus\Purchase $purchase )
	{
		return '';
	}
	
	/**
	 * Get Client Area Page HTML
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	string
	 */
	public static function clientAreaAction( \IPS\nexus\Purchase $purchase )
	{
		
	}
	
	/**
	 * Admin can change expire date / renewal term?
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	bool
	 */
	public static function canChangeExpireDate( \IPS\nexus\Purchase $purchase )
	{
		return TRUE;
	}

	/**
	 * Purchase can be renewed?
	 *
	 * @param	\IPS\nexus\Purchase $purchase	The purchase
	 * @return	boolean
	 */
	public static function canBeRenewed( \IPS\nexus\Purchase $purchase )
	{
		return TRUE;
	}
	
	/**
	 * Purchase can be reactivated in the ACP?
	 *
	 * @param	\IPS\nexus\Purchase $purchase	The purchase
	 * @param	NULL				$error		Error to show, passed by reference
	 * @return	bool
	 */
	public static function canAcpReactivate( \IPS\nexus\Purchase $purchase, &$error=NULL )
	{
		return TRUE;
	}
	
	/**
	 * Can Renew Until
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @param	bool				$admin		If TRUE, is for ACP. If FALSE, is for front-end.
	 * @return	\IPS\DateTime|bool	TRUE means can renew as much as they like. FALSE means cannot renew at all. \IPS\DateTime means can renew until that date
	 */
	public static function canRenewUntil( \IPS\nexus\Purchase $purchase, $admin )
	{
		return TRUE;
	}
	
	/**
	 * Get ACP Support View HTML
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	string
	 */
	public static function acpSupportView( \IPS\nexus\Purchase $purchase )
	{
		return '';
	}
	
	/**
	 * Support Severity
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	\IPS\nexus\Support\Severity|NULL
	 */
	public static function supportSeverity( \IPS\nexus\Purchase $purchase )
	{
		return NULL;
	}
	
	/** 
	 * Get renewal payment methods IDs
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	array|NULL
	 */
	public static function renewalPaymentMethodIds( \IPS\nexus\Purchase $purchase )
	{
		return NULL;
	}
	
	/**
	 * On Purchase Generated
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	void
	 */
	public static function onPurchaseGenerated( \IPS\nexus\Purchase $purchase, \IPS\nexus\Invoice $invoice )
	{
		
	}
	
	/**
	 * On Renew (Renewal invoice paid. Is not called if expiry data is manually changed)
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @param	int					$cycles		Cycles
	 * @return	void
	 */
	public static function onRenew( \IPS\nexus\Purchase $purchase, $cycles )
	{
		
	}
	
	/**
	 * On Expiration Date Change
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public static function onExpirationDateChange( \IPS\nexus\Purchase $purchase )
	{
		
	}
	
	/**
	 * On expire soon
	 * If returns TRUE, the normal expire warning email will not be sent
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public static function onExpireWarning( \IPS\nexus\Purchase $purchase )
	{
		return FALSE;
	}
	
	/**
	 * On Purchase Expired
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public static function onExpire( \IPS\nexus\Purchase $purchase )
	{
		
	}
	
	/**
	 * On Purchase Canceled
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public static function onCancel( \IPS\nexus\Purchase $purchase )
	{
		
	}
	
	/**
	 * Warning to display to admin when cancelling a purchase
	 *
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	string
	 */
	public static function onCancelWarning( \IPS\nexus\Purchase $purchase )
	{
		return NULL;
	}
	
	/**
	 * On Purchase Deleted
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public static function onDelete( \IPS\nexus\Purchase $purchase )
	{
		
	}
	
	/**
	 * On Purchase Reactivated (renewed after being expired or reactivated after being canceled)
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public static function onReactivate( \IPS\nexus\Purchase $purchase )
	{
		
	}
	
	/**
	 * On Transfer (is ran before transferring)
	 *
	 * @param	\IPS\nexus\Purchase	$purchase		The purchase
	 * @param	\IPS\Member			$newCustomer	New Customer
	 * @return	void
	 */
	public static function onTransfer( \IPS\nexus\Purchase $purchase, \IPS\Member $newCustomer )
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
		return \in_array( 'product', explode( ',', \IPS\Settings::i()->nexus_require_billing ) );
	}
	
	/**
	 * Show Purchase Record?
	 *
	 * @return	bool
	 */
	public function showPurchaseRecord()
	{
		return TRUE;
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
		// You can't compare DateInterval objects, it just throws an exception, so we have to
		// manually figure out if the renewal terms have any differences
		
		if ( $item instanceof \IPS\nexus\Invoice\Item\Purchase )
		{
			if ( $item->renewalTerm xor $this->renewalTerm )
			{
				return FALSE;
			}
			elseif ( !$item->renewalTerm and !$this->renewalTerm )
			{
				return parent::isSameAsOtherItem( $item );
			}
			else
			{
				if ( $item->renewalTerm and $this->renewalTerm )
				{
					if ( $item->renewalTerm->getTerm() != $this->renewalTerm->getTerm() )
					{
						return FALSE;
					}
					if ( $item->renewalTerm->cost != $item->renewalTerm->cost )
					{
						return FALSE;
					}
				}
				
				$clonedThis = clone $this;
				$clonedThis->renewalTerm = NULL;
				$clonedOther = clone $item;
				$clonedOther->renewalTerm = NULL;
				
				return $clonedThis->isSameAsOtherItem( $clonedOther );
			}
		}
		else
		{
			return FALSE;
		}
	}
}