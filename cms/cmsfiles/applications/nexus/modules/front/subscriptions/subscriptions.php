<?php
/**
 * @brief		subscriptions
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		09 Feb 2018
 */

namespace IPS\nexus\modules\front\subscriptions;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * subscriptions
 */
class _subscriptions extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		/* Work out currency */
		if ( isset( \IPS\Request::i()->currency ) and \in_array( \IPS\Request::i()->currency, \IPS\nexus\Money::currencies() ) )
		{
			if ( isset( \IPS\Request::i()->csrfKey ) and \IPS\Login::compareHashes( (string) \IPS\Session::i()->csrfKey, (string) \IPS\Request::i()->csrfKey ) )
			{
				$_SESSION['cart'] = array();
				\IPS\Request::i()->setCookie( 'currency', \IPS\Request::i()->currency );

				$url = \IPS\Http\Url::internal( 'app=nexus&module=subscriptions&controller=subscriptions', 'front', 'nexus_subscriptions' );

				if( isset( \IPS\Request::i()->register ) )
				{
					$url = $url->setQueryString( 'register', (int) \IPS\Request::i()->register );
				}

				\IPS\Output::i()->redirect( $url );
			}
		}

		parent::execute();
	}
	
	/**
	 * Show the subscription packages
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( ! \IPS\Settings::i()->nexus_subs_enabled )
		{ 
			\IPS\Output::i()->error( 'nexus_no_subs', '2X379/1', 404, '' );
		}

		/* Send no-cache headers for this page, required for guest sign-ups */
		\IPS\Output::i()->pageCaching = FALSE;

		/* Create the table */
		$table = new \IPS\nexus\Subscription\Table( \IPS\Http\Url::internal( 'app=nexus&module=subscriptions&controller=subscriptions', 'front', 'nexus_subscriptions' ) );
		$current = \IPS\nexus\Subscription::loadByMember( \IPS\Member::loggedIn(), FALSE );

		if ( $current )
		{
			$table->activeSubscription = $current;
		}

		if ( isset( \IPS\Request::i()->purchased ) and $table->activeSubscription )
		{
			try
			{
				$invoice = \IPS\nexus\Invoice::load( $table->activeSubscription->invoice_id );

				/* Fire the pixel event */
				\IPS\core\Facebook\Pixel::i()->Purchase = array( 'value' => $invoice->total->amount, 'currency' => $invoice->total->currency );
				\IPS\Output::i()->inlineMessage = \IPS\Member::loggedIn()->language()->addToStack( 'nexus_subs_paid_flash_msg');
			}
			catch( \Exception $e ) { }
		}

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_subscriptions.js', 'nexus', 'front' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'subscriptions.css', 'nexus' ) );

		\IPS\Output::i()->breadcrumb['module'] = array( \IPS\Http\Url::internal( 'app=nexus&module=subscriptions&controller=subscriptions', 'front', 'nexus_subscriptions' ), \IPS\Member::loggedIn()->language()->addToStack('nexus_front_subscriptions') );

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('nexus_front_subscriptions');
		\IPS\Output::i()->output = $table;
	}
	
	/**
	 * Change packages. It allows you to change packages. I mean again, the whole concept of PHPDoc seems to point out the obvious. A bit like GPS navigation for your front room. There's the sofa. There's the cat.
	 *
	 * @return void just like life, it is meaningless and temporary so live in the moment, enjoy each day and eat chocolate unless you have an allergy in which case don't. See your GP before starting any new diet.
	 */
	protected function change()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$newPackage = \IPS\nexus\Subscription\Package::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'nexus_no_subs_package', '2X379/2', 404, '' );
		}

		/* Is the subscription purchasable ? */
		if ( !$newPackage->enabled )
		{
			\IPS\Output::i()->error( 'node_error', '2X379/7', 403, '' );
		}

		try
		{
			$subscription = \IPS\nexus\Subscription::loadByMember( \IPS\Member::loggedIn(), FALSE );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'nexus_no_subs_subs', '2X379/3', 404, '' );
		}

		/* Fetch purchase */
		$purchase = NULL;
		if( $subscription )
		{
			foreach ( \IPS\nexus\extensions\nexus\Item\Subscription::getPurchases( \IPS\nexus\Customer::loggedIn(), $subscription->package->id, TRUE, TRUE ) as $row )
			{
				if ( !$row->cancelled OR ( $row->cancelled AND $row->can_reactivate ) )
				{
					$purchase = $row;
					break;
				}
			}
		}
		
		if ( $purchase === NULL )
		{
			\IPS\Output::i()->error( 'nexus_sub_no_purchase', '2X379/4', 404, '' );
		}

		/* We cannot process changes if an active Billing Agreement is in place */
		if( $purchase->billing_agreement and !$purchase->billing_agreement->canceled )
		{
			\IPS\Output::i()->error( 'nexus_sub_no_change_ba', '2X379/B', 404, '' );
		}
		
		/* Right, that's all the "I'll tamper with the URLs for a laugh" stuff out of the way... */
		$upgradeCost = $newPackage->costToUpgrade( $subscription->package, \IPS\nexus\Customer::loggedIn() );
		
		if ( $upgradeCost === NULL )
		{
			\IPS\Output::i()->error( 'nexus_no_subs_nocost', '2X379/5', 404, '' );
		}
		
		$invoice = $subscription->package->upgradeDowngrade( $purchase, $newPackage );
		
		if ( $invoice )
		{
			\IPS\Output::i()->redirect( $invoice->checkoutUrl() );
		}
		
		$purchase->member->log( 'subscription', array( 'type' => 'change', 'id' => $purchase->id, 'old' => $purchase->name, 'name' => $newPackage->titleForLog(), 'system' => FALSE ) );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=subscriptions&controller=subscriptions', 'front', 'nexus_subscriptions' ) );
	}
		
	/**
	 * Purchase
	 *
	 * @return	void
	 */
	protected function purchase()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();

		/* Send no-cache headers for this page, required for guest sign-ups */
		\IPS\Output::i()->pageCaching = FALSE;
		
		/* Already purchased a subscription */
		if ( $current = \IPS\nexus\Subscription::loadByMember( \IPS\nexus\Customer::loggedIn(), FALSE ) AND ( $current->purchase AND ( !$current->purchase->cancelled OR $current->purchase->can_reactivate ) ) )
		{
			\IPS\Output::i()->error( 'nexus_subs_already_got_package', '2X379/6', 403, '' );
		}
				
		$package = \IPS\nexus\Subscription\Package::load( \IPS\Request::i()->id );

		/* Is the subscription purchasable ? */
		if ( !$package->enabled )
		{
			\IPS\Output::i()->error( 'node_error', '2X379/7', 403, '' );
		}

		$price = $package->price();
		
		$item = new \IPS\nexus\extensions\nexus\Item\Subscription( \IPS\nexus\Customer::loggedIn()->language()->get( $package->_titleLanguageKey ), $price );
		$item->id = $package->id;
		try
		{
			$item->tax = \IPS\nexus\Tax::load( $package->tax );
		}
		catch ( \OutOfRangeException $e ) { }
		if ( $package->gateways !== '*' )
		{
			$item->paymentMethodIds = explode( ',', $package->gateways );
		}
		$item->renewalTerm = $package->renewalTerm( $price->currency );
		if ( $package->price and $costs = json_decode( $package->price, TRUE ) and isset( $costs['cost'] ) )
		{
			$item->initialInterval = new \DateInterval( 'P' . $costs['term'] . mb_strtoupper( $costs['unit'] ) );
		}
		
		/* Generate the invoice */
		$invoice = new \IPS\nexus\Invoice;
		$invoice->currency = $price->currency;
		$invoice->member = \IPS\nexus\Customer::loggedIn();
		$invoice->addItem( $item );
		$invoice->return_uri = "app=nexus&module=subscriptions&controller=subscriptions&purchased=1";
		$invoice->save();
		
		/* Take them to it */
		\IPS\Output::i()->redirect( $invoice->checkoutUrl() );
	}
	
	/**
	 * Reactivate
	 *
	 * @return	void
	 */
	protected function reactivate()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Get subscription and purchase */
		try
		{
			$package = \IPS\nexus\Subscription\Package::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'nexus_no_subs_package', '2X379/2', 404, '' );
		}
		try
		{
			$subscription = \IPS\nexus\Subscription::loadByMemberAndPackage( \IPS\Member::loggedIn(), $package, FALSE );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'nexus_no_subs_subs', '2X379/8', 404, '' );
		}
		$purchase = NULL;
		foreach ( \IPS\nexus\extensions\nexus\Item\Subscription::getPurchases( \IPS\nexus\Customer::loggedIn(), $subscription->package->id, TRUE, TRUE ) as $row )
		{
			if ( $row->can_reactivate )
			{
				$purchase = $row;
				break;
			}
		}
		if ( $purchase === NULL )
		{
			\IPS\Output::i()->error( 'nexus_sub_no_purchase', '2X379/9', 404, '' );
		}
		
		/* Set renewal terms */
		try
		{
			$currency = $purchase->original_invoice->currency;
		}
		catch ( \Exception $e )
		{
			$currency = $purchase->member->defaultCurrency();
		}
		
		$purchase->renewals = $package->renewalTerm( $currency );
		$purchase->cancelled = FALSE;
		$purchase->save();
		$purchase->member->log( 'purchase', array( 'type' => 'info', 'id' => $purchase->id, 'name' => $purchase->name, 'info' => 'change_renewals', 'to' => array( 'cost' => $purchase->renewals->cost->amount, 'currency' => $purchase->renewals->cost->currency, 'term' => $purchase->renewals->getTerm() ) ) );

		/* Either send to renewal invoice or just back to subscriptions list */
		if ( !$purchase->active and $cycles = $purchase->canRenewUntil( NULL, TRUE ) AND $cycles !== FALSE )
		{
			$url = $cycles === 1 ? $purchase->url()->setQueryString( 'do', 'renew' )->csrf() : $purchase->url()->setQueryString( 'do', 'renew' );
			\IPS\Output::i()->redirect( $url );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=subscriptions&controller=subscriptions', 'front', 'nexus_subscriptions' ) );
		}
	}
}