<?php
/**
 * @brief		File
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		05 Aug 2014
 */

namespace IPS\downloads\extensions\nexus\Item;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File
 */
class _File extends \IPS\nexus\Invoice\Item\Purchase
{
	/**
	 * @brief	Application
	 */
	public static $application = 'downloads';
	
	/**
	 * @brief	Application
	 */
	public static $type = 'file';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'download';
	
	/**
	 * @brief	Title
	 */
	public static $title = 'file';
	
	/**
	 * Image
	 *
	 * @return |IPS\File|NULL
	 */
	public function image()
	{
		try
		{
			return \IPS\downloads\File::load( $this->id )->primary_screenshot;
		}
		catch ( \Exception $e )
		{
			return NULL;
		}
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
			return \IPS\downloads\File::load( $purchase->item_id )->primary_screenshot;
		}
		catch ( \Exception $e )
		{
			return NULL;
		}
	}

	/**
	 * Client Area Action
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	string
	 */
	public static function clientAreaAction( \IPS\nexus\Purchase $purchase )
	{
		if( \IPS\Request::i()->act == 'reactivate' AND $purchase->can_reactivate )
		{
			/* Cannot renew, do not set renewal periods */
			$file = \IPS\downloads\File::load( $purchase->item_id );
			if( !$file->container()->can( 'download', $purchase->member ) )
			{
				return parent::clientAreaAction( $purchase );
			}

			try
			{
				$file = \IPS\downloads\File::load( $purchase->item_id );
			}
			catch ( \OutOfRangeException $e )
			{
				return parent::clientAreaAction( $purchase );
			}
			$renewalCosts = json_decode( $file->renewal_price, TRUE );

			/* If invoice doesn't exist, or currency no longer exists, use default */
			try
			{
				$currency = $purchase->original_invoice->currency;
				if( !isset( $renewalCosts[ $currency ] ) )
				{
					throw \OutOfRangeException();
				}
			}
			catch( \OutOfRangeException $e )
			{
				$currency = \IPS\nexus\Customer::loggedIn()->defaultCurrency();
			}

			$tax = NULL;
			if ( $purchase->tax )
			{
				try
				{
					$tax = \IPS\nexus\Tax::load( $purchase->tax );
				}
				catch ( \Exception $e ) { }
			}

			\IPS\Session::i()->csrfCheck();

			$purchase->renewals = new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( $renewalCosts[ $currency ]['amount'], $currency ), new \DateInterval( 'P' . $file->renewal_term . mb_strtoupper( $file->renewal_units ) ), $tax );
			$purchase->cancelled = FALSE;
			$purchase->save();

			$purchase->member->log( 'purchase', array( 'type' => 'info', 'id' => $purchase->id, 'name' => $purchase->name, 'info' => 'change_renewals', 'to' => array( 'cost' => $purchase->renewals->cost->amount, 'currency' => $purchase->renewals->cost->currency, 'term' => $purchase->renewals->getTerm() ) ) );

			if ( !$purchase->active and $cycles = $purchase->canRenewUntil( NULL, TRUE ) AND $cycles !== FALSE )
			{
				$url = $cycles === 1 ? $purchase->url()->setQueryString( 'do', 'renew' )->csrf() : $purchase->url()->setQueryString( 'do', 'renew' );
				\IPS\Output::i()->redirect( $url );
			}
			else
			{
				\IPS\Output::i()->redirect( $purchase->url() );
			}
		}
	}
	
	/**
	 * Get Client Area Page HTML
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	array	array( 'packageInfo' => '...', 'purchaseInfo' => '...' )
	 */
	public static function clientAreaPage( \IPS\nexus\Purchase $purchase )
	{
		try
		{
			$file = \IPS\downloads\File::load( $purchase->item_id );

			/* Reactivate */
			$reactivateUrl = NULL;
			if ( $file->container()->can( 'download', $purchase->member ) and !$purchase->renewals and $file->renewal_term and $file->renewal_units and $file->renewal_price and $purchase->can_reactivate and ( !$purchase->billing_agreement or $purchase->billing_agreement->canceled ) )
			{
				$reactivateUrl = \IPS\Http\Url::internal( "app=nexus&module=clients&controller=purchases&id={$purchase->id}&do=extra&act=reactivate", 'front', 'clientspurchaseextra', \IPS\Http\Url::seoTitle( $purchase->name ) )->csrf();
			}
			
			return array( 'packageInfo' => \IPS\Theme::i()->getTemplate( 'nexus', 'downloads' )->fileInfo( $file ), 'purchaseInfo' => \IPS\Theme::i()->getTemplate( 'nexus', 'downloads' )->filePurchaseInfo( $file, $reactivateUrl ) );
		}
		catch ( \OutOfRangeException $e ) { }
		
		return NULL;
	}
	
	/**
	 * Get ACP Page HTML
	 *
	 * @return	string
	 */
	public static function acpPage( \IPS\nexus\Purchase $purchase )
	{
		try
		{
			$file = \IPS\downloads\File::load( $purchase->item_id );
			return \IPS\Theme::i()->getTemplate( 'nexus', 'downloads' )->fileInfo( $file );
		}
		catch ( \OutOfRangeException $e ) { }
		
		return NULL;
	}
	
	/**
	 * URL
	 *
	 * @return |IPS\Http\Url|NULL
	 */
	public function url()
	{
		try
		{
			return \IPS\downloads\File::load( $this->id )->url();
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * ACP URL
	 *
	 * @return |IPS\Http\Url|NULL
	 */
	public function acpUrl()
	{
		return $this->url();
	}
	
	/** 
	 * Get renewal payment methods IDs
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	array|NULL
	 */
	public static function renewalPaymentMethodIds( \IPS\nexus\Purchase $purchase )
	{
		if ( \IPS\Settings::i()->idm_nexus_gateways )
		{
			return explode( ',', \IPS\Settings::i()->idm_nexus_gateways );
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
			$file = \IPS\downloads\File::load( $purchase->item_id );

			/* File is viewable and basic download permission check is good */
			if( $file->canView( $purchase->member ) AND $file->container()->can( 'download', $purchase->member ) )
			{
				return TRUE;
			}
		}
		catch ( \OutOfRangeException $e ) {}

		return FALSE;
	}

	/**
	 * Can Renew Until
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @param	bool					$admin		If TRUE, is for ACP. If FALSE, is for front-end.
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
}