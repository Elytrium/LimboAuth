<?php
/**
 * @brief		Club Membership Fee
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		02 Jan 2018
 */

namespace IPS\core\extensions\nexus\Item;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ClubMembership
 */
class _ClubMembership extends \IPS\nexus\Invoice\Item\Purchase
{
	/**
	 * @brief	Application
	 */
	public static $application = 'core';
	
	/**
	 * @brief	Application
	 */
	public static $type = 'club';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'users';
	
	/**
	 * @brief	Title
	 */
	public static $title = 'club_membership_item';
	
	/**
	 * Image
	 *
	 * @return |IPS\File|NULL
	 */
	public function image()
	{
		try
		{
			if ( $photo = \IPS\Member\Club::load( $this->id )->profile_photo )
			{
				return \IPS\File::get( 'core_Clubs', $photo );
			}
		}
		catch ( \Exception $e ) {}
		
		return NULL;
	}
		
	/**
	 * Image
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return |IPS\File|NULL
	 */
	public static function purchaseImage( \IPS\nexus\Purchase $purchase )
	{
		try
		{
			if ( $photo = \IPS\Member\Club::load( $purchase->item_id )->profile_photo )
			{
				return \IPS\File::get( 'core_Clubs', $photo );
			}
		}
		catch ( \Exception $e ) {}
		
		return NULL;
	}
	
	/**
	 * Get Client Area Page HTML
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	array( 'packageInfo' => '...', 'purchaseInfo' => '...' )
	 */
	public static function clientAreaPage( \IPS\nexus\Purchase $purchase )
	{
		try
		{
			$club = \IPS\Member\Club::load( $purchase->item_id );
			
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/clubs.css', 'core', 'front' ) );
			if ( \IPS\Theme::i()->settings['responsive'] )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/clubs_responsive.css', 'core', 'front' ) );
			}
			return array( 'packageInfo' => \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->clubClientArea( $club ) );
		}
		catch ( \OutOfRangeException $e ) { }
		
		return NULL;
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
		try
		{
			$club = \IPS\Member\Club::load( $purchase->item_id );
			
			return array( 'view_club' => array(
				'icon'	=> 'users',
				'title'	=> 'view_club',
				'link'	=> $club->url(),
				'target'=> '_blank'
			) );
		}
		catch ( \OutOfRangeException $e ) { }
		
		return array();
	}
		
	/**
	 * URL
	 *
	 * @return \IPS\Http\Url|NULL
	 */
	public function url()
	{
		try
		{
			return \IPS\Member\Club::load( $this->id )->url();
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * ACP URL
	 *
	 * @return \IPS\Http\Url|NULL
	 */
	public function acpUrl()
	{
		try
		{
			return \IPS\Member\Club::load( $this->id )->url();
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/** 
	 * Get renewal payment methods IDs
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	array|NULL
	 */
	public static function renewalPaymentMethodIds( \IPS\nexus\Purchase $purchase )
	{
		if ( \IPS\Settings::i()->clubs_paid_gateways )
		{
			return explode( ',', \IPS\Settings::i()->clubs_paid_gateways );
		}
		else
		{
			return NULL;
		}
	}

	/**
	 * Purchase can be renewed?
	 *
	 * @param	\IPS\nexus\Purchase $purchase	The purchase
	 * @return	boolean
	 */
	public static function canBeRenewed( \IPS\nexus\Purchase $purchase )
	{
		try
		{
			if ( !$purchase->member->canAccessModule( \IPS\Application\Module::get( 'core', 'clubs', 'front' ) ) )
			{
				return FALSE;
			}
			
			return \in_array( \IPS\Member\Club::load( $purchase->item_id )->memberStatus( $purchase->member ), array( \IPS\Member\Club::STATUS_MEMBER, \IPS\Member\Club::STATUS_MODERATOR, \IPS\Member\Club::STATUS_LEADER, \IPS\Member\Club::STATUS_EXPIRED, \IPS\Member\Club::STATUS_EXPIRED_MODERATOR ) );
		}
		catch ( \OutOfRangeException $e ) {}

		return FALSE;
	}

	/**
	 * Can Renew Until
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @param	bool				$admin		If TRUE, is for ACP. If FALSE, is for front-end.
	 * @return	\IPS\DateTime|bool				TRUE means can renew as much as they like. FALSE means cannot renew at all. \IPS\DateTime means can renew until that date
	 */
	public static function canRenewUntil( \IPS\nexus\Purchase $purchase, $admin )
	{
		if( $admin )
		{
			return TRUE;
		}

		return static::canBeRenewed( $purchase );
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
		try
		{
			$club = \IPS\Member\Club::load( $purchase->item_id );
			$club->addMember( $purchase->member, \IPS\Member\Club::STATUS_MEMBER, TRUE, NULL, NULL, TRUE );
			$club->recountMembers();

		}
		catch ( \Exception $e ) {}
	}
	
	/**
	 * On Purchase Expired
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public static function onExpire( \IPS\nexus\Purchase $purchase )
	{		
		try
		{
			$club = \IPS\Member\Club::load( $purchase->item_id );
									
			switch ( $club->memberStatus( $purchase->member ) )
			{
				case $club::STATUS_MEMBER:
					$club->addMember( $purchase->member, \IPS\Member\Club::STATUS_EXPIRED, TRUE );
					break;
					
				case $club::STATUS_MODERATOR:
					$club->addMember( $purchase->member, \IPS\Member\Club::STATUS_EXPIRED_MODERATOR, TRUE );
					break;
			}
			$club->recountMembers();
		}
		catch ( \Exception $e ) { }
	}
	
	/**
	 * On Purchase Canceled
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public static function onCancel( \IPS\nexus\Purchase $purchase )
	{
		try
		{
			$club = \IPS\Member\Club::load( $purchase->item_id );
			
			switch ( $club->memberStatus( $purchase->member ) )
			{
				case $club::STATUS_MEMBER:
				case $club::STATUS_MODERATOR:
				case $club::STATUS_EXPIRED:
				case $club::STATUS_EXPIRED_MODERATOR:
					$club->removeMember( $purchase->member );
					break;
			}
		}
		catch ( \Exception $e ) {}
	}
	
	/**
	 * Warning to display to admin when cancelling a purchase
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The Purchase
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
		static::onCancel( $purchase );
	}
	
	/**
	 * On Purchase Reactivated (renewed after being expired or reactivated after being canceled)
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public static function onReactivate( \IPS\nexus\Purchase $purchase )
	{
		try
		{
			$club = \IPS\Member\Club::load( $purchase->item_id );
			
			switch ( $club->memberStatus( $purchase->member ) )
			{
				case $club::STATUS_EXPIRED_MODERATOR:
					$club->addMember( $purchase->member, \IPS\Member\Club::STATUS_MODERATOR, TRUE );
					break;
					
				default:
					$club->addMember( $purchase->member, \IPS\Member\Club::STATUS_MEMBER, TRUE );
					break;
			}
			$club->recountMembers();
		}
		catch ( \Exception $e ) {}
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
		try
		{
			$club = \IPS\Member\Club::load( $purchase->item_id );
		}
		catch ( \OutOfRangeException $e )
		{
			return;
		}
									
		switch ( $club->memberStatus( $newCustomer ) )
		{
			/* If they are already a member, we can't really transfer a different membership to them... */
			case \IPS\Member\Club::STATUS_MEMBER:
			case \IPS\Member\Club::STATUS_INVITED_BYPASSING_PAYMENT:
			case \IPS\Member\Club::STATUS_EXPIRED:
			case \IPS\Member\Club::STATUS_EXPIRED_MODERATOR:
			case \IPS\Member\Club::STATUS_MODERATOR:
			case \IPS\Member\Club::STATUS_LEADER:
				throw new \DomainException( 'club_cannot_transfer_membership' );
			
			/* Buf if they're *not* a member we can */
			case NULL:
			case \IPS\Member\Club::STATUS_INVITED:
			case \IPS\Member\Club::STATUS_REQUESTED:
			case \IPS\Member\Club::STATUS_WAITING_PAYMENT:
			case \IPS\Member\Club::STATUS_DECLINED:
			case \IPS\Member\Club::STATUS_BANNED:
				
				/* Remove the old member's record */
				$club->removeMember( $purchase->member );
				
				/* Now if the purchase isn't cancelled... */
				if ( !$purchase->cancelled )
				{
					/* Remove any request/invitation for the new member and add the new record */
					$club->removeMember( $newCustomer );
					$club->addMember( $newCustomer, $purchase->active ? \IPS\Member\Club::STATUS_MEMBER : \IPS\Member\Club::STATUS_EXPIRED, FALSE, \IPS\Member::loggedIn() );
				}
				
				/* Recount */
				$club->recountMembers();
		}
	}
	
	/**
	 * Generate Invoice Form
	 *
	 * @param	\IPS\Helpers\Form	$form		The form
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	void
	 */
	public static function form( \IPS\Helpers\Form $form, \IPS\nexus\Invoice $invoice )
	{
		$form->add( new \IPS\Helpers\Form\Url( 'url_to_club', NULL, TRUE, array(), function( $value )
		{
			try
			{
				$club = \IPS\Member\Club::loadFromUrl( $value );
			}
			catch ( \Exception $e )
			{
				throw new \DomainException('url_to_club_invalid');
			}
			if ( !$club->isPaid() )
			{
				throw new \DomainException('url_to_club_free');
			}			
		} ) );
	}
	
	/**
	 * Create From Form
	 *
	 * @param	array				$values	Values from form
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	\IPS\core\extensions\nexus\Item\ClubMembership
	 */
	public static function createFromForm( array $values, \IPS\nexus\Invoice $invoice )
	{
		$club = \IPS\Member\Club::loadFromUrl( $values['url_to_club'] );
		
		$fee = $club->joiningFee( $invoice->currency );
		
		$item = new \IPS\core\extensions\nexus\Item\ClubMembership( $club->name, $fee );
		$item->id = $club->id;
		try
		{
			$item->tax = \IPS\Settings::i()->clubs_paid_tax ? \IPS\nexus\Tax::load( \IPS\Settings::i()->clubs_paid_tax ) : NULL;
		}
		catch ( \OutOfRangeException $e ) { }
		if ( \IPS\Settings::i()->clubs_paid_gateways )
		{
			$item->paymentMethodIds = explode( ',', \IPS\Settings::i()->clubs_paid_gateways );
		}
		$item->renewalTerm = $club->renewalTerm( $fee->currency );
		$item->payTo = $club->owner;
		$item->commission = \IPS\Settings::i()->clubs_paid_commission;
		if ( $fees = \IPS\Settings::i()->clubs_paid_transfee and isset( $fees[ $fee->currency ] ) )
		{
			$item->fee = new \IPS\nexus\Money( $fees[ $fee->currency ]['amount'], $fee->currency );
		}
		
		return $item;
	}
}