<?php
/**
 * @brief		Nexus Application Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		10 Feb 2014
 */
 
namespace IPS\nexus;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Nexus Application Class
 */
class _Application extends \IPS\Application
{
	/**
	 * Init - catches legacy PayPal IPN messages
	 *
	 * @return	void
	 */
	public function init()
	{
		if ( !\IPS\Request::i()->isAjax() )
		{
			if ( \IPS\Settings::i()->gateways_counts and $decoded = json_decode( \IPS\Settings::i()->gateways_counts, TRUE ) and isset( $decoded['Stripe'] ) and $decoded['Stripe'] > 0 )
			{
				\IPS\Output::i()->jsFiles[] = 'https://js.stripe.com/v3/';
			}
		}

		if ( \IPS\Request::i()->app == 'nexus' and \IPS\Request::i()->module == 'payments' and \IPS\Request::i()->section == 'receive' and \IPS\Request::i()->validate == 'paypal' )
		{
			if ( ( \IPS\Request::i()->txn_type == 'subscr_payment' or \IPS\Request::i()->txn_type == 'recurring_payment' ) and \IPS\Request::i()->payment_status == 'Completed' )
			{
				try
				{
					$saveSubscription = FALSE;

					/* Get the subscription */
					try
					{
						if( \IPS\Request::i()->txn_type == 'subscr_payment' )
						{
							$subscription = \IPS\Db::i()->select( '*', 'nexus_subscriptions', array( 's_gateway_key=? AND s_id=?', 'paypal', \IPS\Request::i()->subscr_id ) )->first();    
						}
						else                            
						{
							$subscription = \IPS\Db::i()->select( '*', 'nexus_subscriptions', array( 's_gateway_key=? AND s_id=?', 'paypalpro', \IPS\Request::i()->recurring_payment_id ) )->first();
						}
						$items = \unserialize( $subscription['s_items'] );
						if ( !\is_array( $items ) or empty( $items ) )
						{
							/* Don't exit here, throw an underflow exception so we can see if this is a legacy subscription */
							throw new \UnderflowException( 'NO_ITEMS' );
						}
					}
					catch( \UnderflowException $e )
					{
						/* Was this from the old IP.Subscriptions? */
						if ( isset( \IPS\Request::i()->old ) or mb_strstr( \IPS\Request::i()->custom, '-' ) !== FALSE )
						{
							if ( mb_strstr( \IPS\Request::i()->custom, '-' ) !== FALSE )
							{
								$exploded	= explode( '-', \IPS\Request::i()->custom );
								$memberId	= \intval( $exploded[0] );
								$purchaseId	= \intval( $exploded[1] );

								$item = \IPS\Db::i()->select( '*', 'nexus_purchases', array( "ps_id=?", $purchaseId ), 'ps_id' )->first();
							}
							else
							{
								$memberId	= \intval( \IPS\Request::i()->custom );

								$item = \IPS\Db::i()->select( '*', 'nexus_purchases', array( "ps_member=? AND ps_name=?", $memberId, \IPS\Request::i()->item_name ), 'ps_start DESC' )->first();
							}
				
							/* If the purchase isn't valid or was cancelled just exit */
							if ( !$item['ps_id'] or $item['ps_cancelled'] )
							{
								exit;
							}

							/* We need $items below, not $item */
							$items = array( $item['ps_id'] );

							/* Grab the first paypal method we can find */
							$method		= \IPS\Db::i()->select( 'm_id', 'nexus_paymethods', array( 'm_gateway=?', 'PayPal' ) )->first();

							/* We are here because the subscription record doesn't exist.  The 3.x code just silently and automatically added one, so do the same now.
								Note that we will need the transaction ID to save the subscription, but we can set the array parameters that will be referenced below. */
							$subscription = array(
								's_gateway_key'	=> 'paypal',
								's_id'			=> \IPS\Request::i()->subscr_id,
								's_start_trans'	=> 0, 	// We will set this later after saving the transaction
								's_method'		=> $method,
								's_member'		=> $memberId,
							);

							$saveSubscription = TRUE;
						}
						else
						{
							/* Just let the exception bubble up and get caught */
							throw $e;
						}
					}
					
					/* If the gateway still exists, fetch it */
					$gateway = NULL;
					try
					{
						$gateway = \IPS\nexus\Gateway::load( $subscription['s_method'] );
					}
					catch ( \OutOfRangeException $e ) { }
										
					/* Check this was actually a PayPal IPN message */					
					try
					{
						$response = \IPS\Http\Url::external( 'https://www.' . ( \IPS\NEXUS_TEST_GATEWAYS ? 'sandbox.' : '' ) . 'paypal.com/cgi-bin/webscr/' )->request()->setHeaders( array( 'Accept' => 'application/x-www-form-urlencoded' ) )->post( array_merge( array( 'cmd' => '_notify-validate' ), $_POST ) );
						if ( (string) $response !== 'VERIFIED' )
						{
							exit;
						}
					}
					catch ( \Exception $e )
					{
						exit;
					}
					
					/* Has an invoice already been generated? */
					$_items = $items;
					try
					{
						$invoice = \IPS\nexus\Invoice::constructFromData( \IPS\Db::i()->select( '*', 'nexus_invoices', array( 'i_member=? AND i_status=?', $subscription['s_member'], 'pend' ), 'i_date DESC', 1 )->first() );
						foreach ( $invoice->items as $item )
						{
							if ( $item instanceof \IPS\nexus\Invoice\Item\Renewal and \in_array( $item->id, $_items ) )
							{
								unset( $_items[ array_search( $item->id, $_items ) ] );
							}
						}
					}
					catch ( \UnderflowException $e ) { }
					
					/* No, create one */
					if ( \count( $_items ) )
					{
						$invoice = new \IPS\nexus\Invoice;
						$invoice->member = \IPS\nexus\Customer::load( $subscription['s_member'] );
						foreach ( $items as $purchaseId )
						{
							try
							{
								$purchase = \IPS\nexus\Purchase::load( $purchaseId );
								if ( $purchase->renewals and !$purchase->cancelled )
								{
									$invoice->addItem( \IPS\nexus\Invoice\Item\Renewal::create( $purchase ) );
								}
							}
							catch ( \OutOfRangeException $e ) { }
						}
						if ( !\count( $invoice->items ) )
						{
							exit;
						}
						$invoice->save();
					}
					
					/* And then log a transaction */
					$transaction = new \IPS\nexus\Transaction;
					$transaction->member = $invoice->member;
					$transaction->invoice = $invoice;
					if ( $gateway )
					{
						$transaction->method = $gateway;
					}
					$transaction->amount = new \IPS\nexus\Money( $_POST['mc_gross'], $_POST['mc_currency'] );
					$transaction->gw_id = $_POST['txn_id'];
					$transaction->approve();

					/* If this was a legacy subscription payment, we'll need to save the subscription now that we have a trans ID */
					if( $saveSubscription === TRUE )
					{
						$subscription['s_start_trans']	= $transaction->id;

						\IPS\Db::i()->insert( 'nexus_subscriptions', $subscription );
					}
				}
				catch ( \UnderflowException $e ) { }
			}
			
			exit;
		}
	}

	/**
	 * Install Other
	 *
	 * @return	void
	 */
	public function installOther()
	{
		/* Disable support module for new installations */
		\IPS\Db::i()->update( 'core_modules', array( 'sys_module_visible' => 0 ), array( 'sys_module_application=? AND sys_module_key=? AND sys_module_area=?', 'nexus', 'support', 'admin' ) );
	}

	/**
	 * Returns the ACP Menu JSON for this application.
	 *
	 * @return array
	 */
	public function acpMenu()
	{
		$menu = parent::acpMenu();

		if( $m = \IPS\Application\Module::get( 'nexus', 'support', 'admin' ) AND $m->visible )
		{
			$menu['support'] = array(
				'requests' => array(
					'tab'	=> 'nexus',
					'controller'	=> 'requests',
					'do'	=> '',
					'restriction'	=> 'requests_manage',
            		'subcontrollers' => 'request'
				),
				'reports'	=> array(
					'tab'	=> 'nexus',
					'controller'	=> 'reports',
					'do'	=> '',
					'restriction'	=> 'reports_manage'
				),
				'settings'	=> array(
					'tab'	=> 'nexus',
					'controller'	=> 'settings',
					'do'	=> '',
					'restriction'	=> 'settings_manage'
				),
				'volume'	=> array(
					'tab'	=> 'stats',
					'controller'	=> 'volume',
					'do'	=> '',
					'restriction'	=> 'volume_manage'
				),
			);
		}

		return $menu;

	}

	/**
	 * @brief Cached menu counts
	 */
	protected $menuCounts = array();

	/**
	 * ACP Menu Numbers
	 *
	 * @param	array	$queryString	Query String
	 * @return	int
	 */
	public function acpMenuNumber( $queryString )
	{
		parse_str( $queryString, $queryString );
		switch ( $queryString['controller'] )
		{
			case 'transactions':
				if( !isset( $this->menuCounts['transactions'] ) )
				{
					$this->menuCounts['transactions'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_transactions', array( \IPS\Db::i()->in( 't_status', array( \IPS\nexus\Transaction::STATUS_HELD, \IPS\nexus\Transaction::STATUS_WAITING, \IPS\nexus\Transaction::STATUS_REVIEW, \IPS\nexus\Transaction::STATUS_DISPUTED ) ) ) )->first();
				}
				return $this->menuCounts['transactions'];
				break;
			
			case 'shipping':
				if( !isset( $this->menuCounts['shipping'] ) )
				{
					$this->menuCounts['shipping'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_ship_orders', array( 'o_status=?', \IPS\nexus\Shipping\Order::STATUS_PENDING ) )->first();
				}

				return $this->menuCounts['shipping'];
				break;
			
			case 'payouts':
				if( !isset( $this->menuCounts['payouts'] ) )
				{
					$this->menuCounts['payouts'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_payouts', array( 'po_status=?', \IPS\nexus\Payout::STATUS_PENDING ) )->first();
				}

				return $this->menuCounts['payouts'];
				break;
			
			case 'requests':
				if( !isset( $this->menuCounts['requests'] ) )
				{
					$myStream = \IPS\nexus\Support\Stream::myStream();
					$this->menuCounts['requests'] = $myStream->count( \IPS\Member::loggedIn() );
				}

				return $this->menuCounts['requests'];
				break;
		}
	}
	
	/**
	 * Cart count
	 *
	 * @return	int
	 */
	public static function cartCount()
	{
		$count = 0;
		foreach ( $_SESSION['cart'] as $item )
		{
			$count += $item->quantity;
		}
		return $count;
	}

	/**
	 * [Node] Get Icon for tree
	 *
	 * @note	Return the class for the icon (e.g. 'globe')
	 * @return	string|null
	 */
	protected function get__icon()
	{
		return 'shopping-cart';
	}

    /**
     * A list of whitelisted modules which should be accessible without the subscription check.
     *
     * @var string[]
     */
    static $bypassSubscriptionCheckControllers = ['store', 'checkout', 'subscriptions', 'system', 'clients'];

    /**
	 * Do Member Check
	 *
	 * @return	\IPS\Http\Url|NULL
	 */
	public function doMemberCheck(): ?\IPS\Http\Url
	{
		/* These checks do not apply to staff accounts or support account */
		if( \is_array( \IPS\Member::loggedIn()->modPermissions() ) OR \IPS\Member::loggedIn()->isAdmin() OR \IPS\Member::loggedIn()->members_bitoptions['is_support_account'] )
		{
			return NULL;
		}

		if ( \IPS\Settings::i()->nexus_subs_enabled AND \IPS\Settings::i()->nexus_subs_register AND !\in_array( \IPS\Dispatcher::i()->module->key, static::$bypassSubscriptionCheckControllers ) )
		{
			if( !isset( \IPS\Data\Store::i()->nexus_sub_count ) )
			{
				\IPS\Data\Store::i()->nexus_sub_count = \IPS\Db::i()->select( 'count(*)', 'nexus_member_subscription_packages', [ 'sp_enabled=?', 1 ] )->first();
			}

			/* This user didn't join recently, so we don't need to redirect them */
			if( \IPS\Data\Store::i()->nexus_sub_count AND (int) \IPS\Settings::i()->nexus_subs_register === 1 AND !\IPS\Member::loggedIn()->joinedRecently() )
			{
				return NULL;
			}

			if ( \IPS\Data\Store::i()->nexus_sub_count AND !\IPS\nexus\Subscription::loadByMember( \IPS\Member::loggedIn(), (int) \IPS\Settings::i()->nexus_subs_register === 2 ? TRUE : FALSE ) )
			{
				return \IPS\Http\Url::internal( "app=nexus&module=subscriptions&controller=subscriptions&register=" . (int) \IPS\Settings::i()->nexus_subs_register, 'front', 'nexus_subscriptions' );
			}
		}

		if ( \IPS\Settings::i()->nexus_reg_force AND !\in_array( \IPS\Dispatcher::i()->module->key, static::$bypassSubscriptionCheckControllers ) AND \IPS\Member::loggedIn()->joinedRecently() )
		{
			if( !isset( \IPS\Data\Store::i()->nexus_reg_product_count ) )
			{
				\IPS\Data\Store::i()->nexus_reg_product_count = \IPS\nexus\Package::haveRegistrationProducts();
			}

			if ( \IPS\Data\Store::i()->nexus_reg_product_count AND !\IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( "ps_member=? AND ps_active=?", \IPS\Member::loggedIn()->member_id, 1 ) )->first() )
			{
				return \IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=store&controller=store&do=register', 'front', 'store' ) );
			}
		}

		return NULL;
	}
	
	/**
	 * Can view page even when user is a guest when guests cannot access the site
	 *
	 * @param	\IPS\Application\Module	$module			The module
	 * @param	string					$controller		The controller
	 * @param	string|NULL				$do				To "do" parameter
	 * @return	bool
	 */
	public function allowGuestAccess( \IPS\Application\Module $module, $controller, $do )
	{
		if( ( \IPS\Settings::i()->nexus_reg_force OR \IPS\nexus\Package::haveRegistrationProducts() ) AND ( $module->key == 'store' OR $module->key == 'checkout' ) )
		{
			return TRUE;
		}
		
		if ( \IPS\Settings::i()->nexus_subs_register AND ( \in_array( $module->key, [ 'store', 'checkout', 'subscriptions' ] ) ) )
		{
			return TRUE;
		}
		
		if ( \IPS\Settings::i()->cm_ref_on AND $module->key == 'promotion' AND $controller == 'referral' )
		{
			return TRUE;
		}
	}
	
	/**
	 * Default front navigation
	 *
	 * @code
	 	
	 	// Each item...
	 	array(
			'key'		=> 'Example',		// The extension key
			'app'		=> 'core',			// [Optional] The extension application. If ommitted, uses this application	
			'config'	=> array(...),		// [Optional] The configuration for the menu item
			'title'		=> 'SomeLangKey',	// [Optional] If provided, the value of this language key will be copied to menu_item_X
			'children'	=> array(...),		// [Optional] Array of child menu items for this item. Each has the same format.
		)
	 	
	 	return array(
		 	'rootTabs' 		=> array(), // These go in the top row
		 	'browseTabs'	=> array(),	// These go under the Browse tab on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Browse tab may not exist)
		 	'browseTabsEnd'	=> array(),	// These go under the Browse tab after all other items on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Browse tab may not exist)
		 	'activityTabs'	=> array(),	// These go under the Activity tab on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Activity tab may not exist)
		)
	 * @endcode
	 * @return array
	 */
	public function defaultFrontNavigation()
	{
		return array(
			'rootTabs'		=> array(
				array(
					'key'		=> 'Store',
					'children'	=> array(
						array( 'key' => 'Store' ),
						array( 'key' => 'Gifts' ),
						array( 'key' => 'Subscriptions' ),
						array( 'key' => 'Donations' ),
						array( 'key' => 'Orders' ),
						array( 'key' => 'Purchases' ),
						array(
							'app'			=> 'core',
							'key'			=> 'Menu',
							'title'		=> 'default_menu_item_my_details',
							'children'	=> array(
								array( 'key' => 'Info' ),
								array( 'key' => 'Addresses' ),
								array( 'key' => 'Cards' ),
								array( 'key' => 'BillingAgreements' ),
								array( 'key' => 'Credit' ),
								array( 'key' => 'Alternatives' ),
								array( 'key' => 'Referrals' ),
							)
						)
					),
				),
				array(
					'app'		=> 'core',
					'key'		=> 'CustomItem',
					'title'		=> 'default_menu_item_support',
					'config'	=> array( 'menu_custom_item_url' => 'app=nexus&module=support&controller=home', 'internal' => 'support' ),
					'children'	=> array(
						array( 'key' => 'Support' )
					)
				)
			),
			'browseTabs'	=> array(),
			'browseTabsEnd'	=> array(),
			'activityTabs'	=> array()
		);
	}

	/**
	 * Perform some legacy URL parameter conversions
	 *
	 * @return	void
	 */
	public function convertLegacyParameters()
	{
		/* Support legacy subscriptions */
		if( isset( \IPS\Request::i()->app ) AND \IPS\Request::i()->app == 'subscriptions' )
		{
			/* Redirecting isn't necessary, we just need to route the payment to the appropriate area.
				@see \IPS\nexus\Application */
			\IPS\Request::i()->app			= 'nexus';
			\IPS\Request::i()->module		= 'payments';
			\IPS\Request::i()->section		= 'receive';	/* It actually looks for section=receive, so make sure we set that */
			\IPS\Request::i()->controller	= 'receive';	/* We set this just to be complete in case anywhere else only looks at controller */
			\IPS\Request::i()->validate		= 'paypal';
		}
	}

	/**
	 * Returns a list of all existing webhooks and their payload in this app.
	 *
	 * @return array
	 */
	public function getWebhooks(): array
	{
		foreach( [
					 \IPS\nexus\Support\Request::class,
					 \IPS\nexus\Support\Reply::class,
				 ] as $class )
		{
			$key = str_replace( '\\', '', \substr( $class, 3 ) );
			$hooks[$key .'_create'] = $class;
			$hooks[$key .'_delete'] = $class;
			\IPS\Member::loggedIn()->language()->words[ 'webhook_' . $key .'_create' ]     = \IPS\Member::loggedIn()->language()->addToStack('webhook_contentitem_created', FALSE, ['sprintf' => [ $class::_indefiniteArticle() ]]);
			\IPS\Member::loggedIn()->language()->words[ 'webhook_' . $key .'_delete' ]     = \IPS\Member::loggedIn()->language()->addToStack('webhook_contentitem_deleted', FALSE, ['sprintf' => [ $class::_indefiniteArticle() ]]);
		}
		return $hooks;
	}

	/**
	 * Returns a list of essential cookies which are set by this app.
	 * Wildcards (*) can be used at the end of cookie names for PHP set cookies.
	 *
	 * @return string[]
	 */
	public function _getEssentialCookieNames(): array
	{
		return [ 'cm_reg', 'location', 'currency', 'guestTransactionKey' ];
	}

}