<?php
/**
 * @brief		Member subscription Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		9 Feb 2018
 */

namespace IPS\nexus\Subscription;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member Subscription Model
 */
class _Package extends \IPS\Node\Model
{
	/* !ActiveRecord */
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array( 'nexus_sub_count' );
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'nexus_member_subscription_packages';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'sp_';
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
		
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'nexus_subs_';
	
	/**
	 * @brief	[Node] Description suffix.  If specified, will look for a language key with "{$titleLangPrefix}_{$id}_{$descriptionLangSuffix}" as the key
	 */
	public static $descriptionLangSuffix = '_desc';
	
	/* !Node */

	/**
	 * @brief	[Node] App for permission index
	 */
	public static $permApp = 'nexus';

	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'menu__nexus_subscriptions_subscriptions';
			
	/**
	 * @brief	[Node] ACP Restrictions
	 * @code
	 	array(
	 		'app'		=> 'core',				// The application key which holds the restrictrions
	 		'module'	=> 'foo',				// The module key which holds the restrictions
	 		'map'		=> array(				// [Optional] The key for each restriction - can alternatively use "prefix"
	 			'add'			=> 'foo_add',
	 			'edit'			=> 'foo_edit',
	 			'permissions'	=> 'foo_perms',
	 			'delete'		=> 'foo_delete'
	 		),
	 		'all'		=> 'foo_manage',		// [Optional] The key to use for any restriction not provided in the map (only needed if not providing all 4)
	 		'prefix'	=> 'foo_',				// [Optional] Rather than specifying each  key in the map, you can specify a prefix, and it will automatically look for restrictions with the key "[prefix]_add/edit/permissions/delete"
	 * @endcode
	 */
	protected static $restrictions = array(
		'app'		=> 'nexus',
		'module'	=> 'subscriptions',
		'prefix' 	=> 'subscriptions_'
	);

	/**
	 * @brief	URL Base
	 */
	protected static $urlBase = 'app=nexus&module=subscriptions&controller=subscriptions&id=';

	/**
	 * @brief	URL Template
	 */
	protected static $urlTemplate = 'nexus_subscription';
	
	/**
	 * @brief	SEO Title Column
	 */
	protected static $seoTitleColumn = '';

    /**
     * [ActiveRecord] Duplicate
     *
     * @return	void
     */
    public function __clone()
    {
        if ( $this->skipCloneDuplication === TRUE )
        {
            return;
        }

        $old = $this;

        parent::__clone();

        /* Copy across images */
		if ( $old->image )
		{
			try
			{
				$file = \IPS\File::get( 'nexus_Products', $old->image );
				$this->image = (string) \IPS\File::create( 'nexus_Products', $file->originalFilename, $file->contents() );
				$this->save();
			}
			catch( \Exception $e ) {}
		}
    }

	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
    public function delete()
	{
		try
		{
			if( $this->image )
			{
				\IPS\File::get( 'nexus_Products', $this->image )->delete();
			}
		}
		catch( \Exception $e ){}

		\IPS\Task::queue( 'nexus', 'DeleteSubscriptions', [ 'id' => $this->id ] );

		parent::delete();
	}

	/**
	 * [Node] Get buttons to display in tree
	 * Example code explains return value
	 *
	 * @code
	 	array(
	 		array(
	 			'icon'	=>	'plus-circle', // Name of FontAwesome icon to use
	 			'title'	=> 'foo',		// Language key to use for button's title parameter
	 			'link'	=> \IPS\Http\Url::internal( 'app=foo...' )	// URI to link to
	 			'class'	=> 'modalLink'	// CSS Class to use on link (Optional)
	 		),
	 		...							// Additional buttons
	 	);
	 * @endcode
	 * @param	string	$url		Base URL
	 * @param	bool	$subnode	Is this a subnode?
	 * @return	array
	 */
	public function getButtons( $url, $subnode=FALSE )
	{
		$buttons = array();
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'subscriptions', 'subscriptions_manage' ) )
		{
			$buttons['add_member'] = array(
				'icon'	=> 'plus',
				'title'	=> 'nexus_subs_add_member',
				'link'	=> $url->setQueryString( array( 'do' => 'addMember', 'id' => $this->id ) ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('nexus_subs_add_member') )
			);
		}
		
		$buttons = array_merge( $buttons, parent::getButtons( $url, $subnode ) );
		
		if ( isset( $buttons['delete'] ) )
		{
			unset( $buttons['delete']['data']['delete'] );
			$buttons['delete']['data']['confirm'] = TRUE;
		}
				
		return $buttons;
	}
	
	/**
	 * Fetch the cover image uRL
	 *
	 * @return \IPS\File | NULL
	 */
	public function get__image()
	{
		return ( $this->image ) ? \IPS\File::get( 'nexus_Products', $this->image ) : NULL;
	}
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$groups = array();
		foreach ( \IPS\Member\Group::groups() as $group )
		{
			$groups[ $group->g_id ] = $group->name;
		}
		$groupsExcludingGuestsAndAdmins = array();
		foreach ( \IPS\Member\Group::groups( FALSE, FALSE ) as $group )
		{
			$groupsExcludingGuestsAndAdmins[ $group->g_id ] = $group->name;
		}
		
		$renewInterval = NULL;
		$renewalTerm = NULL;
		$renewalCosts = array();
		if ( $this->renew_options and $_renewOptions = json_decode( $this->renew_options, TRUE ) and \is_array( $_renewOptions ) )
		{
			foreach ( $_renewOptions['cost'] as $cost )
			{
				$renewalCosts[ $cost['currency'] ] = new \IPS\nexus\Money( $cost['amount'], $cost['currency'] );
			}
			
			try
			{
				$renewInterval = new \DateInterval( "P{$_renewOptions['term']}" . mb_strtoupper( $_renewOptions['unit'] ) );
				$renewalTerm = new \IPS\nexus\Purchase\RenewalTerm( $renewalCosts, $renewInterval, NULL );
			}
			catch( \Exception $ex ) { } // Catch any invalid renewal terms, these can occasionally appear from legacy IP.Subscriptions
		}
		
		$initialPrice = NULL;
		if ( $this->price )
		{
			$initialInterval = $renewInterval;
			$initialPrices = [];
			$costs = json_decode( $this->price, TRUE );
			if ( isset( $costs['cost'] ) )
			{
				$initialInterval = new \DateInterval( 'P' . $costs['term'] . mb_strtoupper( $costs['unit'] ) );
				$costs = $costs['cost'];
			}
			foreach ( $costs as $price )
			{
				$initialPrices[ $price['currency'] ] = new \IPS\nexus\Money( $price['amount'], $price['currency'] );
			}
			
			if ( $initialPrices == $renewalCosts )
			{
				$renewalTerm = NULL;
			}
			
			$initialPrice = new \IPS\nexus\Purchase\RenewalTerm( $initialPrices, $initialInterval );
		}
		
		$form->addHeader('subscription_basic_settings');
		$form->add( new \IPS\Helpers\Form\Translatable( 'sp_name', NULL, TRUE, array( 'app' => 'nexus', 'key' => $this->id ? "nexus_subs_{$this->id}" : NULL ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'sp_enabled', $this->id ? $this->enabled : TRUE, FALSE ) );
		$form->addHeader( 'nexus_subs_cost' );
		$form->add( new \IPS\nexus\Form\RenewalTerm( 'sp_price', $initialPrice, NULL, array( 'allCurrencies' => TRUE, 'initialTerm' => TRUE, 'unlimitedTogglesOn' => [ 'sp_renew_upgrade' ], 'unlimitedTogglesOff' => [ 'sp_renew_options' ] ), NULL, NULL, NULL, 'sp_initial_term' ) );
		$form->add( new \IPS\nexus\Form\RenewalTerm( 'sp_renew_options', $renewalTerm, NULL, array( 'allCurrencies' => TRUE, 'nullLang' => 'term_same_as_initial' ), NULL, NULL, NULL, 'sp_renew_options' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'sp_renew_upgrade', $this->renew_upgrade ?: 0, FALSE, array( 'options' => array(
				0 => 'sp_renew_upgrade_none',
				1 => 'sp_renew_upgrade_full',
				2 => 'sp_renew_upgrade_partial'
			) ), NULL, NULL, NULL, 'sp_renew_upgrade' ) );	
		$form->add( new \IPS\Helpers\Form\Node( 'sp_tax', (int) $this->tax, FALSE, array( 'class' => 'IPS\nexus\Tax', 'zeroVal' => 'do_not_tax' ) ) );
		$form->add( new \IPS\Helpers\Form\Node( 'sp_gateways', ( !$this->gateways or $this->gateways === '*' ) ? 0 : explode( ',', $this->gateways ), FALSE, array( 'class' => 'IPS\nexus\Gateway', 'multiple' => TRUE, 'zeroVal' => 'any' ) ) );
		

		$form->addHeader( 'nexus_subs_groups' );
		$form->add( new \IPS\Helpers\Form\Select( 'sp_primary_group', $this->primary_group ?: '*', FALSE, array( 'options' => $groupsExcludingGuestsAndAdmins, 'unlimited' => '*', 'unlimitedLang' => 'do_not_change', 'unlimitedToggles' => array( 'p_return_primary' ), 'unlimitedToggleOn' => FALSE ) ) );
		$form->add( new \IPS\Helpers\Form\Select( 'sp_secondary_group', $this->secondary_group ? explode( ',', $this->secondary_group ) : '*', FALSE, array( 'options' => $groupsExcludingGuestsAndAdmins, 'multiple' => TRUE, 'unlimited' => '*', 'unlimitedLang' => 'do_not_change', 'unlimitedToggles' => array( 'p_return_secondary' ), 'unlimitedToggleOn' => FALSE ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'sp_return_primary', $this->return_primary, FALSE, array(), NULL, NULL, NULL, 'sp_return_primary' ) );


		$form->addHeader('nexus_subs_display');
		$form->add( new \IPS\Helpers\Form\Upload( 'sp_image', ( ( $this->id AND $this->image ) ? \IPS\File::get( 'nexus_Products', $this->image ) : NULL ), FALSE, array( 'storageExtension' => 'nexus_Products', 'image' => TRUE, 'allowStockPhotos' => TRUE ), NULL, NULL, NULL, 'sp_image' ) );
		/*$form->add( new \IPS\Helpers\Form\YesNo( 'sp_featured', $this->featured, FALSE, array(), NULL, NULL, NULL, 'sp_featured' ) );*/
		$form->add( new \IPS\Helpers\Form\Translatable( 'sp_desc', NULL, FALSE, array(
			'app' => 'nexus',
			'key' => $this->id ? "nexus_subs_{$this->id}_desc" : NULL,
			'editor'	=> array(
				'app'			=> 'nexus',
				'key'			=> 'Admin',
				'autoSaveKey'	=> ( $this->id ? "nexus-sub-{$this->id}" : "nexus-new-sub" ),
				'attachIds'		=> $this->id ? array( $this->id, NULL, 'sub' ) : NULL, 'minimize' => 'p_desc_placeholder'
			)
		), NULL, NULL, NULL, 'p_desc_editor' ) );
	}
	
	/**
	 * [Node] Save Add/Edit Form
	 *
	 * @param	array	$values	Values from the form
	 * @return	void
	 */
	public function saveForm( $values )
	{		
		if ( !$this->id )
		{	
			$this->save();
			unset( static::$multitons[ $this->id ] );
			
			\IPS\File::claimAttachments( 'nexus-new-sub', $this->id, NULL, 'sub', TRUE );
				
			$obj = static::load( $this->id );
			return $obj->saveForm( $obj->formatFormValues( $values ) );			
		}
		
		$return = parent::saveForm( $values );
		
		return $return;
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if ( !$this->id )
		{
			return $values;
		}
		
		/* Translatables */
		foreach ( array( 'name' => '', 'desc' => '_desc' ) as $key => $suffix )
		{
			if ( isset( $values[ 'sp_' . $key ] ) )
			{
				\IPS\Lang::saveCustom( 'nexus', "nexus_subs_{$this->id}{$suffix}", $values[ 'sp_' . $key ] );
			}
			unset( $values[ 'sp_' . $key ] );
		}

		/* Normalise */
		$originalPrice = NULL;
		if( isset( $values['sp_price'] ) )
		{
			$originalPrice = $values['sp_price'];
			if ( $values['sp_price']->interval )
			{
				$term = $values['sp_price']->getTerm();
				$values['sp_price'] = json_encode( array(
					'cost'	=> $values['sp_price']->cost,
					'term'	=> $term['term'],
					'unit'	=> $term['unit']
				) );
			}
			else
			{
				$values['sp_price'] = json_encode( $values['sp_price']->cost );
			}
		}

		if( isset( $values['sp_primary_group'] ) )
		{
			$values['sp_primary_group'] = $values['sp_primary_group'] == '*' ? 0 : $values['sp_primary_group'];
		}

		if( isset( $values['sp_secondary_group'] ) )
		{
			$values['sp_secondary_group'] = $values['sp_secondary_group'] == '*' ? '' : implode( ',', $values['sp_secondary_group'] );
		}
		
		if( isset( $values['sp_tax'] ) )
		{
			$values['sp_tax'] = $values['sp_tax'] ? $values['sp_tax']->id : 0;
		}
		
		if( isset( $values['sp_gateways'] ) )
		{
			$values['sp_gateways'] = ( isset( $values['sp_gateways'] ) and \is_array( $values['sp_gateways'] ) ) ? implode( ',', array_keys( $values['sp_gateways'] ) ) : '*';
		}

		/* Renewal options */
		if( isset( $values['sp_renew_options'] ) )
		{
			if ( $values['sp_renew_options'] )
			{
				$renewOptions = array();
				$option = $values['sp_renew_options'];
				$term = $option->getTerm();
				
				$values['sp_renew_options'] = json_encode( array(
					'cost'	=> $option->cost,
					'term'	=> $term['term'],
					'unit'	=> $term['unit']
				) );
			}
			else
			{
				$values['sp_renew_options'] = '';
			}
		}
		elseif ( isset( $values['sp_price'] ) and $originalPrice->interval )
		{
			$values['sp_renew_options'] = $values['sp_price'];
			$values['sp_price'] = json_encode( $originalPrice->cost );
		}
	
		if ( isset( $values['sp_image'] ) )
		{
			$values['sp_image'] = (string) $values['sp_image'];
		}
		
		return $values;
	}
	
	/**
	 * Price
	 *
	 * @param	string|NULL	$currency	Desired currency, or NULL to choose based on member's chosen currency
	 * @return	\IPS\nexus\Money|NULL
	 */
	public function price( $currency = NULL )
	{
		if ( !$currency )
		{
			$currency = ( isset( \IPS\Request::i()->cookie['currency'] ) and \in_array( \IPS\Request::i()->cookie['currency'], \IPS\nexus\Money::currencies() ) ) ? \IPS\Request::i()->cookie['currency'] : \IPS\nexus\Customer::loggedIn()->defaultCurrency();
		}
		
		$costs = json_decode( $this->price, TRUE );
		if ( isset( $costs['cost'] ) )
		{
			$costs = $costs['cost'];
		}

		if ( \is_array( $costs ) and isset( $costs[ $currency ]['amount'] ) )
		{
			return new \IPS\nexus\Money( $costs[ $currency ]['amount'], $currency );
		}
	
		return NULL;
	}
	
	/**
	 * Joining fee
	 *
	 * @param	string|NULL	$currency	Desired currency, or NULL to choose based on member's chosen currency
	 * @return	\IPS\nexus\Money|NULL
	 * @throws	\OutOfRangeException
	 */
	public function renewalTerm( $currency = NULL )
	{
		if ( $this->renew_options and $renewal = json_decode( $this->renew_options, TRUE ) )
		{
			$renewalPrices = $renewal['cost'];
			if ( !$currency )
			{
				$currency = ( isset( \IPS\Request::i()->cookie['currency'] ) and \in_array( \IPS\Request::i()->cookie['currency'], \IPS\nexus\Money::currencies() ) ) ? \IPS\Request::i()->cookie['currency'] : \IPS\nexus\Customer::loggedIn()->defaultCurrency();
			}
			
			if ( isset( $renewalPrices[ $currency ] ) )
			{
				$grace = NULL;
				if ( \IPS\Settings::i()->nexus_subs_invoice_grace )
				{
					$grace = new \DateInterval( 'P' . \IPS\Settings::i()->nexus_subs_invoice_grace . 'D' );
				}
				
				$tax = NULL;
				if ( $this->tax )
				{
					try
					{
						$tax = \IPS\nexus\Tax::load( $this->tax );
					} 
					catch( \OutOfRangeException $e ) { }
				}
				
				return new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( $renewalPrices[ $currency ]['amount'], $currency ), new \DateInterval( 'P' . $renewal['term'] . mb_strtoupper( $renewal['unit'] ) ), $tax, FALSE, $grace );
			}
			else
			{
				throw new \OutOfRangeException;
			}
		}
		
		return NULL;
	}
	
	/**
	 * Price Info
	 *
	 * @return	array|NULL
	 */
	public function priceInfo()
	{
		/* Base Price */
		$price = $this->price();

		/* Price may not have been defined in our currency - abort if we don't have one */
		if( $price === NULL )
		{
			return $price;
		}
		
		/* Renewal Term */
		$renewalTerm = NULL;
		$initialTerm = NULL;
		try
		{
			$renewalTerm = $this->renewalTerm( $price->currency );
			
			/* Is the initial term different? */
			$priceInfo = json_decode( $this->price, TRUE );
			if ( isset( $priceInfo['term'] ) )
			{
				$initialTerm = new \IPS\nexus\Purchase\RenewalTerm( $price, new \DateInterval( 'P' . $priceInfo['term'] . mb_strtoupper( $priceInfo['unit'] ) ) );
			}
		}
		catch ( \OutOfRangeException $e ) {}
		
		/* If we can encompass the primary price and renewal term together, do that */
		$priceIsZero = $price->amount->isZero();
		if ( $renewalTerm and $price->amount->compare( $renewalTerm->cost->amount ) === 0 )
		{
			$price = $renewalTerm->toDisplay();
			$renewalTerm = NULL;
		}
		elseif ( $price )
		{
			if ( \IPS\Settings::i()->nexus_show_tax and $this->tax )
			{
				try
				{
					$taxRate = new \IPS\Math\Number( \IPS\nexus\Tax::load( $this->tax )->rate( \IPS\nexus\Customer::loggedIn()->estimatedLocation() ) );
					
					$price->amount = $price->amount->add( $price->amount->multiply( $taxRate ) );
				}
				catch ( \OutOfRangeException $e ) { }
			}
		}

		/* Return */
		return array(
			'primaryPrice'					=> $priceIsZero ? \IPS\Member::loggedIn()->language()->addToStack('nexus_sub_cost_free') : $price,
			'primaryPriceIsZero'			=> $priceIsZero,
			'primaryPriceDiscountedFrom'	=> NULL,
			'initialTerm'					=> $initialTerm ? $initialTerm->getTermUnit() : NULL,
			'renewalPrice'					=> $renewalTerm ? $renewalTerm->toDisplay() : NULL,
		);
	}
	
	/**
	 * Price Blurb
	 *
	 * @return	string|NULL
	 */
	public function priceBlurb()
	{
		$priceInfo = $this->priceInfo();
		
		if ( $priceInfo['primaryPrice'] )
		{
			if ( $priceInfo['renewalPrice'] and $priceInfo['initialTerm'] )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'nexus_sub_cost_plus_renewal', FALSE, array( 'sprintf' => array( $priceInfo['primaryPrice'], $priceInfo['initialTerm'], $priceInfo['renewalPrice'] ) ) );
			}
			elseif ( $priceInfo['renewalPrice'] )
			{
				return $priceInfo['renewalPrice'];
			}
			else
			{
				return $priceInfo['primaryPrice'];
			}
		}
		else
		{
			return \IPS\Member::loggedIn()->language()->addToStack('nexus_sub_cost_unavailable');
		}		
	}
	
	/** 
	 * Cost to upgrade to this package (may return negative value for refund)
	 *
	 * @param	\IPS\nexus\Subscription\Package	$package	The currently subscribed package
	 * @param	\IPS\Member						$member		The member. 
	 * @return	\IPS\nexus\Money|NULL
	 * @throws	\InvalidArgumentException
	 */
	public function costToUpgrade( \IPS\nexus\Subscription\Package $package, \IPS\Member $member )
	{
		/* Fetch purchase */
		$purchase = NULL;
		foreach ( \IPS\nexus\extensions\nexus\Item\Subscription::getPurchases( $member, $package->id, TRUE, TRUE ) as $row )
		{
			if ( !$row->cancelled OR ( $row->cancelled AND $row->can_reactivate ) )
			{
				$purchase = $row;
				break;
			}
		}

		if ( $purchase === NULL )
		{
			return NULL;
		}
		
		try
		{
			$currency = $purchase->original_invoice->currency;
		}
		catch ( \Exception $e )
		{
			$currency = $purchase->member->defaultCurrency();
		}

		$priceOfExistingPackage = json_decode( $package->price, TRUE );
		if ( isset( $priceOfExistingPackage['cost'] ) )
		{
			$priceOfExistingPackage = $priceOfExistingPackage['cost'];
		}
		$priceOfExistingPackage = $priceOfExistingPackage[ $currency ]['amount'];
		
		$priceOfThisPackage = json_decode( $this->price, TRUE );
		if ( isset( $priceOfThisPackage['cost'] ) )
		{
			$priceOfThisPackage = $priceOfThisPackage['cost'];
		}
		$priceOfThisPackage = $priceOfThisPackage[ $currency ]['amount'];
		$renewalOptionsOnNewPackage = json_decode( $this->renew_options, TRUE );

		/* It's a non-recurring subscription */
		if ( empty( $renewalOptionsOnNewPackage ) )
		{
			switch( $this->renew_upgrade )
			{
				case 0:
					return NULL;
				
				case 1:
					return new \IPS\nexus\Money( $priceOfThisPackage, $currency );
					
				case 2:
					return new \IPS\nexus\Money( $priceOfThisPackage - $priceOfExistingPackage, $currency );
			}
		}
		
		/* It's a recurring subscription */
		if ( $priceOfThisPackage >= $priceOfExistingPackage )
		{
			$type = \IPS\Settings::i()->nexus_subs_upgrade;
		}
		else
		{
			$type = \IPS\Settings::i()->nexus_subs_downgrade;
		}

		switch ( $type )
		{
			case -1:
				return NULL; /* nope */
				
			case 0:
				return new \IPS\nexus\Money( 0, $currency );
			
			case 1:
				/* If the purchase is expired, charge the full amount */
				if( !$purchase->active )
				{
					$newPrice = new \IPS\nexus\Money( $priceOfThisPackage, $currency );

					/* If the package has a free trial, charge the renewal fee instead to stop endless free trials */
					if( $newPrice->amount->isZero() )
					{
						return new \IPS\nexus\Money( $renewalOptionsOnNewPackage['cost'][ $currency ]['amount'], $currency );
					}

					return new \IPS\nexus\Money( $priceOfThisPackage, $currency );
				}

				return new \IPS\nexus\Money( $priceOfThisPackage - $priceOfExistingPackage, $currency );
			
			case 2:
				if ( !$purchase->renewals )
				{
					return new \IPS\nexus\Money( 0, $currency );
				}
				if ( !$renewalOptionsOnNewPackage )
				{
					throw new \InvalidArgumentException;
				}

				/* What is the closest renewal option on the new package? We'll use that one */
				$renewalOptionsInDays = array();
				$term = ( new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( $renewalOptionsOnNewPackage['cost'][ $currency ]['amount'], $currency ), new \DateInterval( 'P' . $renewalOptionsOnNewPackage['term'] . mb_strtoupper( $renewalOptionsOnNewPackage['unit'] ) ), $purchase->renewals->tax ) );
				$renewalOptionsInDays[ $term->days() ] = $term;

				$closestRenewalOption = null;
				$numberOfDaysInCurrentRenewalTerm = $purchase->renewals->days();
				foreach ( $renewalOptionsInDays as $days => $term )
				{
					if ( $closestRenewalOption === null or abs( $numberOfDaysInCurrentRenewalTerm - $closestRenewalOption ) > abs( $days - $numberOfDaysInCurrentRenewalTerm ) )
					{
						$closestRenewalOption = $days;
					}
				}
				$renewalTermToUse = $renewalOptionsInDays[ $closestRenewalOption ];


				/* If the purchase is not active, it is the full cost */
				if( !$purchase->active )
				{
					return new \IPS\nexus\Money( $renewalTermToUse->cost->amount, $currency );
				}

				/* Current Period Start Date */
				$startOfCurrentPeriod = $purchase->expire->sub( $purchase->renewals->interval );

				/* DateInterval of time between subscription start and now */
				$timeSinceStartOfCurrentPeriod = $startOfCurrentPeriod->diff( \IPS\DateTime::create() );
				$numberOfDaysInCurrentRenewalTerm = new \IPS\Math\Number( (string) round( $numberOfDaysInCurrentRenewalTerm ) );

				/* Count Days in Period */
				$daysUsedInPeriod = new \IPS\Math\Number( (string)\IPS\DateTime::intervalToDays( $timeSinceStartOfCurrentPeriod ) );
				$daysRemainingInPeriod = new \IPS\Math\Number( (string) $numberOfDaysInCurrentRenewalTerm->subtract( $daysUsedInPeriod ) );

				/* Currency Worth of value used in current sub */
				$valueUsedOfSubscription = $purchase->renewals->cost->amount->divide( $numberOfDaysInCurrentRenewalTerm )->multiply( $daysUsedInPeriod );

				if ( !$daysRemainingInPeriod->isGreaterThanZero() )
				{
					return new \IPS\nexus\Money( new \IPS\Math\Number('0'), $currency );
				}
				elseif( $priceOfExistingPackage > $priceOfThisPackage )
				{
					/* If this is a downgrade, calculate a refund */
					$refund = $purchase->renewals->cost->amount->subtract( $valueUsedOfSubscription );
					return new \IPS\nexus\Money( $refund->multiply( new \IPS\Math\Number( "-1" ) ), $currency );
				}
				else
				{
					return new \IPS\nexus\Money( $renewalTermToUse->cost->amount->subtract( $valueUsedOfSubscription ), $currency );
				}
		}
	}

	/**
	 * Cost to upgrade to this package (may return negative value for refund)
	 *
	 * @param	\IPS\nexus\Subscription\Package	$package	The currently subscribed package
	 * @param	\IPS\Member						$member		The member.
	 * @return	\IPS\nexus\Money|NULL
	 * @throws	\InvalidArgumentException
	 */
	public function costToUpgradeIncludingTax( \IPS\nexus\Subscription\Package $package, \IPS\Member $member )
	{
		$upgradeCost = $this->costToUpgrade( $package, $member );

		if( $upgradeCost !== NULL AND $upgradeCost->amount->isGreaterThanZero() AND \IPS\Settings::i()->nexus_show_tax and $this->tax )
		{
			try
			{
				$taxRate = new \IPS\Math\Number( \IPS\nexus\Tax::load( $this->tax )->rate( \IPS\nexus\Customer::loggedIn()->estimatedLocation() ) );

				$upgradeCost->amount = $upgradeCost->amount->add( $upgradeCost->amount->multiply( $taxRate ) );
			}
			catch ( \OutOfRangeException $e ) { }
		}

		return $upgradeCost;
	}
	
	/**
	 * Create the upgrade/downgrade invoice or refund
	 *
	 * @param	\IPS\nexus\Purchase 			$purchase
	 * @param	\IPS\nexus\Subscription\Package $newPackage
	 * @param	bool										$skipCharge				If TRUE, an upgrade charges and downgrade refunds will not be issued
	 * @return	\IPS\nexus\Invoice|void												An invoice if an upgrade charge has to be paid, or void if not
	 */
	public function upgradeDowngrade( \IPS\nexus\Purchase $purchase, \IPS\nexus\Subscription\Package $newPackage, $skipCharge = FALSE )
	{
		/* Right, that's all the "I'll tamper with the URLs for a laugh" stuff out of the way... */
		$oldPackage = \IPS\nexus\Subscription\Package::load( $purchase->item_id );
		$costToUpgrade = $newPackage->costToUpgrade( $oldPackage, $purchase->member );
		
		/* Charge / Refund */
		if ( !$skipCharge )
		{
			/* Upgrade Charge */
			if ( $costToUpgrade->amount->isGreaterThanZero() )
			{
				$item = new \IPS\nexus\extensions\nexus\Item\SubscriptionUpgrade( sprintf( $purchase->member->language()->get( 'upgrade_charge_item' ), $purchase->member->language()->get( "nexus_subs_{$this->id}" ), $purchase->member->language()->get( "nexus_subs_{$newPackage->id}" ) ), $costToUpgrade );
				$item->tax = $newPackage->tax ? \IPS\nexus\Tax::load( $newPackage->tax ) : NULL;
				$item->id = $purchase->id;
				$item->extra = array( 'newPackage' => $newPackage->id, 'oldPackage' => $this->id );
	
				if ( $newPackage->gateways and $newPackage->gateways != '*' )
				{
					$item->paymentMethodIds = explode( ',', $newPackage->gateways );
				}
	
				$invoice = new \IPS\nexus\Invoice;
				$invoice->member = $purchase->member;
				$invoice->currency = $costToUpgrade->currency;
				$invoice->addItem( $item );
				$invoice->return_uri = "app=nexus&module=subscriptions&controller=subscriptions";
				$invoice->renewal_ids = array( $purchase->id );
				$invoice->save();
				return $invoice;
			}
			elseif ( !$costToUpgrade->amount->isPositive() )
			{
				$credits = $purchase->member->cm_credits;
				$credits[ $costToUpgrade->currency ]->amount = $credits[ $costToUpgrade->currency ]->amount->add( $costToUpgrade->amount->multiply( new \IPS\Math\Number( '-1' ) ) );
				$purchase->member->cm_credits = $credits;
				$purchase->member->save();
			}
		}
		
		/* Get old renewal term details here */
		$oldTerm = NULL;
		$oldRenewalOptions = json_decode( $oldPackage->renew_options, TRUE );
		$oldTerm = $oldRenewalOptions;
		
		/* Work out the new renewal term */
		$term = NULL;
		$renewalOptions = json_decode( $newPackage->renew_options, TRUE );
		$term = $rawTerm = $renewalOptions;
		
		if ( $term )
		{
			try
			{
				$currency = $purchase->original_invoice->currency;
			}
			catch ( \OutOfRangeException $e )
			{
				$currency = $purchase->member->defaultCurrency();
			}

			/* Check Tax exists */
			$tax = NULL;
			try
			{
				$tax = $newPackage->tax ? \IPS\nexus\Tax::load( $newPackage->tax ) : NULL;
			}
			catch( \OutOfRangeException $e ) {}
			$term = new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( $term['cost'][$currency]['amount'], $currency ), new \DateInterval( 'P' . $term['term'] . mb_strtoupper( $term['unit'] ) ), $tax );
		}

		/* Remove usergroups */
		$this->_removeUsergroups( $purchase->member );
				
		/* If we didn't have an expiry date before, but the new package has a renewal term, set an expiry date */
		if ( !$purchase->expire and $term )
		{
			$purchase->expire = \IPS\DateTime::create()->add( $term->interval );
		}
		/* OR if we did have an expiry date, but the new package does not have a renewal term, remove it */
		elseif ( !$term )
		{
			$purchase->expire = NULL;
		}
		/* We have a term, but the unit is different from the existing package */
		elseif ( $purchase->expire and $term and $oldTerm and ( $rawTerm['unit'] != $oldTerm['unit'] ) )
		{
			/* If there is an upgrade cost, the expire date should be from when the current billing period started. */
			if( $costToUpgrade->amount->isGreaterThanZero() AND \IPS\Settings::i()->nexus_subs_upgrade > 0 )
			{
				$startOfCurrentPeriod = $purchase->expire->sub( new \DateInterval( 'P' . $oldTerm['term'] . mb_strtoupper( $oldTerm['unit'] ) ) );
				$purchase->expire = $startOfCurrentPeriod < $purchase->start ? $purchase->start->add( $term->interval ) : $startOfCurrentPeriod->add( $term->interval );
			}
			else
			{
				$difference = $purchase->expire->getTimestamp() - time();

				if ( $difference > 0 )
				{
					$newExpire = \IPS\DateTime::ts( ( time() - $difference ) );
					$newExpire = $newExpire->add( $term->interval );

					if ( $newExpire->getTimestamp() < time() )
					{
						$newExpire = \IPS\DateTime::create()->add( $term->interval );
					}

					$purchase->expire = $newExpire;
				}
			}
		}
		/* Purchase expired in the past, so it needs one renewal term adding */
		elseif( $purchase->expire->getTimestamp() < time() AND $term )
		{
			$purchase->expire = \IPS\DateTime::create()->add( $term->interval );
		}
				
		/* Update Purchase */
		$purchase->name = \IPS\Member::loggedIn()->language()->get( "nexus_subs_{$newPackage->id}" );
		$purchase->cancelled = FALSE;
		$purchase->item_id = $newPackage->id;
		$purchase->renewals = $term;
		$purchase->save();
				
		/* Re-add usergroups */
		$newPackage->_addUsergroups( $purchase->member );
		
		/* Cancel any pending invoices */
		if ( $pendingInvoice = $purchase->invoice_pending )
		{
			$pendingInvoice->status = \IPS\nexus\invoice::STATUS_CANCELED;
			$pendingInvoice->save();
			$purchase->invoice_pending = NULL;
			$purchase->save();
		}
		
		/* Change the subscription itself */
		try
		{
			\IPS\nexus\Subscription::load( $purchase->id, 'sub_purchase_id' )->changePackage( $newPackage, $purchase->expire );
		}
		catch( \Exception $e )
		{
			\IPS\Log::log( "Change Package error (" . $e->getCode() . ") " . $e->getMessage(), 'subscriptions' );
		}
	}
	
	/**
	 * Renew a member to the subscription package
	 *
	 * @param	\IPS\nexus\Customer			$member		The cutomer innit
	 * @return  \IPS\nexus\Subscription		The new subscription object added
	 */
	public function renewMember( $member )
	{
		try
		{
			$expires = 0;
			$renews  = 0;
		
			/* Get the most recent active subscription */
			$sub = \IPS\nexus\Subscription::loadByMemberAndPackage( $member, $this, FALSE );
			
			if ( $this->renew_options and $renewal = json_decode( $this->renew_options, TRUE ) )
			{
				$nextExpiration = \IPS\DateTime::ts( $sub->expire, TRUE );
				$nextExpiration->add( new \DateInterval( 'P' . $renewal['term'] . mb_strtoupper( $renewal['unit'] ) ) );
				$expires = $nextExpiration->getTimeStamp();
				$renews = 1;
			}
			
			$sub->active = 1;
			$sub->cancelled = 0;
			$sub->expire = $expires;
			$sub->renews = $renews;
			$sub->save();
			
			/* Member groups may have changed in the package itself */
			$this->_removeUsergroups( $member );
			$this->_addUsergroups( $member );
			
			return $sub;
		}
		catch( \Exception $ex )
		{
			return $this->addMember( $member );
		}
	}

	/**
	 * Adds a member to the subscription package
	 *
	 * @param	\IPS\nexus\Customer	        $member		        The customer innit
	 * @param   bool                        $generatePurchase   Generate purchase when adding member (for manual subscriptions)
	 * @param   bool                        $purchaseRenews     Does the purchase renew, or is it forever
	 * @return  \IPS\nexus\Subscription		                    The new subscription object added
	 */
	public function addMember( \IPS\nexus\Customer $member, bool $generatePurchase=FALSE, bool $purchaseRenews=FALSE ): \IPS\nexus\Subscription
	{
		try
		{
			$sub = \IPS\nexus\Subscription::loadByMemberAndPackage( $member, $this, FALSE );
		}
		catch ( \OutOfRangeException $e )
		{
			$sub = new \IPS\nexus\Subscription;
			$sub->package_id = $this->id;
			$sub->member_id = $member->member_id;
		}
		
		$expires = 0;
		$renews  = 0;
		if ( $this->renew_options and $renewal = json_decode( $this->renew_options, TRUE ) )
		{
			$start = \IPS\DateTime::ts( time(), TRUE );
			$start->add( new \DateInterval( 'P' . $renewal['term'] . mb_strtoupper( $renewal['unit'] ) ) );
			$expires = $start->getTimeStamp();
			$renews = 1;
		}
		
		/* Create a new one */
		$sub->active = 1;
		$sub->cancelled = 0;
		$sub->start = time();
		$sub->expire = $expires;
		$sub->renews = $renews;
		$sub->save();

		/* Do we need to generate a purchase to track this sub? */
		if( $generatePurchase )
		{
			$purchase = new \IPS\nexus\Purchase;
			$purchase->member = $member;
			$purchase->name = $member->language()->get( static::$titleLangPrefix . $this->id );
			$purchase->app = 'nexus';
			$purchase->type = 'subscription';
			$purchase->item_id = $this->id;
			$purchase->tax = $this->tax;
			$purchase->show = 1;

			if( $purchaseRenews )
			{
				if ( $renewalTerm = $this->renewalTerm() )
				{
					$purchase->renewals = $renewalTerm;
				}
				$purchase->expire = \IPS\DateTime::ts( $expires );
			}
			else
			{
				$sub->expire = 0;
				$sub->renews = 0;
			}

			$purchase->save();

			/* update sub with purchase id */
			$sub->purchase_id = $purchase->id;
			$sub->save();
		}
		
		$this->_addUsergroups( $member );
		
		return $sub;
	}
	
	/**
	 * Expires a member
	 *
	 * @param	\IPS\nexus\Customer		$member		The cutomer innit
	 * @return void
	 */
	public function expireMember( $member )
	{
		/* Run before marking it inactive or it won't find the row in _removeUsergroups */
		$this->_removeUsergroups( $member );
		
		/* Make any previous subscriptions inactive */
		\IPS\nexus\Subscription::markInactiveByUser( $member );
	}
	
	/**
	 * Cancels a member
	 *
	 * @param	\IPS\nexus\Customer		$member		The cutomer innit
	 * @return void
	 */
	public function cancelMember( $member )
	{
		/* Run before marking it inactive or it won't find the row in _removeUsergroups */
		$this->_removeUsergroups( $member );
		
		/* Make any previous subscriptions inactive */
		\IPS\nexus\Subscription::markInactiveByUser( $member );
		
		/* Cancel purchase */
		foreach ( \IPS\nexus\extensions\nexus\Item\Subscription::getPurchases( $member, $this->id ) as $purchase )
		{
			if ( $purchase->active )
			{
				$purchase->active = FALSE;
				$purchase->save();
			}
		}
	}
	
	/**
	 * Removes a member
	 *
	 * @param	\IPS\nexus\Customer		$member		The cutomer innit
	 * @return void
	 */
	public function removeMember( $member )
	{
		/* Run before marking it inactive or it won't find the row in _removeUsergroups */
		$this->_removeUsergroups( $member );
		
		try
		{
			\IPS\nexus\Subscription::loadByMemberAndPackage( $member, $this, FALSE )->delete();
		}
		catch( \OutOfRangeException $ex ) {}
	}
		
	/* !Usergroups */
	
	/**
	 * Add user groups
	 *
	 * @param	\IPS\nexus\Customer	$member	The customer
	 * @return	void
	 */
	public function _addUsergroups( $member )
	{
		$previousGroup = 0;
		$previousSecondary = '';
		$newSecondary = '';
		
		/* Primary Group */
		if ( $this->primary_group and $this->primary_group != $member->member_group_id and !\in_array( $member->member_group_id, explode( ',', \IPS\Settings::i()->nexus_subs_exclude_groups ) ) )
		{
			/* Hang on, are we about to boot someone out the ACP? */
			if ( ! ( $member->isAdmin() and !\in_array( $this->primary_group, array_keys( \IPS\Member::administrators()['g'] ) ) ) )
			{
				/* Only do this if the target group exists */
				try
				{
					$group = \IPS\Member\Group::load( $this->primary_group);
					/* Save the current group */
					$previousGroup = $member->member_group_id;

					/* And update to the new group */
					$member->member_group_id = $this->primary_group;
					$member->members_bitoptions['ignore_promotions'] = true;
					$member->save();
					$member->logHistory( 'core', 'group', array( 'type' => 'primary', 'by' => 'subscription', 'action' => 'add', 'id' => $this->id, 'old' => $previousGroup, 'new' => $member->member_group_id ) );
				}
				catch( \OutOfRangeException $e )
				{

				}
			}
		}
		
		/* Secondary Groups */
		$secondary = array_filter( explode( ',', $this->secondary_group ), function( $v ){ return (bool) $v; } );

		$current_secondary = $member->mgroup_others ? explode( ',', $member->mgroup_others ) : array();
		$newSecondary = $current_secondary;
		if ( !empty( $secondary ) )
		{
			foreach ( $secondary as $gid )
			{
				if ( !\in_array( $gid, $newSecondary ) )
				{
					/* Only do this if the target group exists */
					try
					{
						$group = \IPS\Member\Group::load( $gid );
						$newSecondary[] = $gid;
					}
					catch( \OutOfRangeException $e )
					{

					}
				}
			}
		}
		
		if ( $current_secondary != $newSecondary )
		{
			$previousSecondary = $member->mgroup_others;
			$member->mgroup_others = ',' . implode( ',', $newSecondary ) . ',';
			$member->save();
			$member->logHistory( 'core', 'group', array( 'type' => 'secondary', 'by' => 'subscription', 'action' => 'add', 'id' => $this->id, 'old' => $previousSecondary, 'new' => $newSecondary ) );
		}

		\IPS\Db::i()->update( 'nexus_member_subscriptions', array( 'sub_previous_group' => $previousGroup, 'sub_previous_secondary_groups' => $previousSecondary ), array( 'sub_active=1 and sub_package_id=? and sub_member_id=?', $this->id, $member->member_id ) );
	}
	
	/**
	 * Remove user groups
	 *
	 * @param	\IPS\nexus\Customer	$member	The customer
	 * @return	void
	 */
	public function _removeUsergroups( $member )
	{
		if ( ! $this->return_primary )
		{
			return NULL;
		}
		
		/* Fetch purchase */
		$purchase = NULL;
		foreach ( \IPS\nexus\extensions\nexus\Item\Subscription::getPurchases( $member, $this->id ) as $row )
		{
			/* Don't check for cancelled here, as the purchase will be cancelled before we get here */
			if ( $row->active )
			{
				$purchase = $row;
				break;
			}
		}
		
		try
		{
			$sub = \IPS\Db::i()->select( '*', 'nexus_member_subscriptions', array( 'sub_active=1 and sub_package_id=? and sub_member_id=?', $this->id, $member->member_id ) )->first();
		}
		catch( \UnderflowException $e )
		{
			return NULL;
		}
		
		/* We only want to move them back if they haven't been moved again since */
		if ( $member->member_group_id == $this->primary_group )
		{
			$oldGroup = $member->member_group_id;
			
			/* Have we made other purchases that have changed their primary group? */
			try
			{
				if( $purchase !== NULL )
				{
					$next = \IPS\Db::i()->select( array( 'ps_id', 'ps_name', 'p_primary_group' ), 'nexus_purchases', array( 'ps_member=? AND ps_app=? AND ps_type=? AND ps_active=1 AND p_primary_group<>0 AND ps_id<>?', $member->member_id, 'nexus', 'package', $purchase->id ) )
						->join( 'nexus_packages', 'p_id=ps_item_id' )
						->first();
				}
				else
				{
					$next = \IPS\Db::i()->select( array( 'ps_id', 'ps_name', 'p_primary_group' ), 'nexus_purchases', array( 'ps_member=? AND ps_app=? AND ps_type=? AND ps_active=1 AND p_primary_group<>0', $member->member_id, 'nexus', 'package' ) )
						->join( 'nexus_packages', 'p_id=ps_item_id' )
						->first();
				}

				/* Make sure this group exists */
				try
				{
					\IPS\Member\Group::load( $next['p_primary_group'] );
				}
				catch( \OutOfRangeException $e )
				{
					throw new \UnderflowException;
				}

				$member->member_group_id = $next['p_primary_group'];
				$member->save();
				$member->logHistory( 'core', 'group', array( 'type' => 'primary', 'by' => 'purchase', 'action' => 'change', 'remove_id' => $next['ps_id'], 'ps_name' => $next['ps_id'], 'id' => $next['ps_id'], 'name' => $next['ps_name'], 'old' => $oldGroup, 'new' => $member->member_group_id ) );
			}
			/* No, move them to their original group */
			catch ( \UnderflowException $e )
			{
				/* Does this group exist? */
				try
				{
					\IPS\Member\Group::load( $sub['sub_previous_group'] );
					$member->member_group_id = $sub['sub_previous_group'];
				}
				catch ( \OutOfRangeException $e )
				{
					$member->member_group_id = \IPS\Settings::i()->member_group;
				}
									
				/* Save */
				$member->members_bitoptions['ignore_promotions'] = false;
				$member->save();
				$member->logHistory( 'core', 'group', array( 'type' => 'primary', 'by' => 'subscription', 'action' => 'remove', 'id' => $this->id, 'old' => $oldGroup, 'new' => $member->member_group_id ) );
			}
		}

		// Secondary groups
		$secondaryGroupsAwardedByThisPurchase = array_unique( array_filter( explode( ',', $this->secondary_group ) ) );
		$membersSecondaryGroups = $member->mgroup_others ? array_unique( array_filter( explode( ',', $member->mgroup_others ) ) ) : array();
		if ( isset( $sub['sub_previous_secondary_groups'] ) and $sub['sub_previous_secondary_groups'] !== NULL )
		{			
			/* Work some stuff out */
			$currentSecondaryGroups = $membersSecondaryGroups;
			$membersPreviousSecondaryGroupsBeforeThisPurchase = array_unique( array_filter( explode( ',', $sub['sub_previous_secondary_groups'] ) ) );
			
			/* Have we made other purchases that have added secondary groups? */
			$secondaryGroupsAwardedByOtherPurchases = array();

			if( $purchase !== NULL )
			{
				$query = \IPS\Db::i()->select( 'p_secondary_group', 'nexus_purchases', array( 'ps_member=? AND ps_app=? AND ps_type=? AND ps_active=1 AND p_secondary_group IS NOT NULL AND p_secondary_group<>? AND ps_id<>?', $member->member_id, 'nexus', 'package', '', $purchase->id ) )->join( 'nexus_packages', 'p_id=ps_item_id' );
			}
			else
			{
				$query = \IPS\Db::i()->select( 'p_secondary_group', 'nexus_purchases', array( 'ps_member=? AND ps_app=? AND ps_type=? AND ps_active=1 AND p_secondary_group IS NOT NULL AND p_secondary_group<>?', $member->member_id, 'nexus', 'package', '' ) )->join( 'nexus_packages', 'p_id=ps_item_id' );
			}

			foreach ( $query as $secondaryGroups )
			{
				$secondaryGroupsAwardedByOtherPurchases = array_merge( $secondaryGroupsAwardedByOtherPurchases, array_filter( explode( ',', $secondaryGroups ) ) );
			}

			$secondaryGroupsAwardedByOtherPurchases = array_unique( $secondaryGroupsAwardedByOtherPurchases );
			
			/* Loop through */
			foreach ( $secondaryGroupsAwardedByThisPurchase as $groupId )
			{
				/* If we had this group before we made this purchase, we're going to keep it */
				if ( \in_array( $groupId, $membersPreviousSecondaryGroupsBeforeThisPurchase ) )
				{
					continue;
				}
				
				/* If we are being awarded this group by a different purchase, we're also going to keep it */
				if ( \in_array( $groupId, $secondaryGroupsAwardedByOtherPurchases ) )
				{
					continue;
				}
				
				/* If we're still here, remove it */
				unset( $membersSecondaryGroups[ array_search( $groupId, $membersSecondaryGroups ) ] );
			}

			/* And make sure only valid groups are saved */
			$membersSecondaryGroups = array_filter( $membersSecondaryGroups, function( $group ){
				try
				{
					\IPS\Member\Group::load( $group );
					return TRUE;
				}
				catch( \OutOfRangeException $e )
				{
					return FALSE;
				}
			});

			/* Save */
			$member->mgroup_others = implode( ',', $membersSecondaryGroups );
			$member->save();
			$member->logHistory( 'core', 'group', array( 'type' => 'secondary', 'by' => 'subscription', 'action' => 'remove', 'id' => $this->id, 'old' => $currentSecondaryGroups, 'new' => $membersSecondaryGroups ) );
		}
		else if ( $secondaryGroupsAwardedByThisPurchase )
		{
			$currentSecondaryGroups = $membersSecondaryGroups;
			foreach( $membersSecondaryGroups as $group )
			{
				if ( \in_array( $group, $secondaryGroupsAwardedByThisPurchase ) )
				{
					unset( $membersSecondaryGroups[ array_search( $group, $membersSecondaryGroups ) ] );
				}
			}

			/* And make sure only valid groups are saved */
			$membersSecondaryGroups = array_filter( $membersSecondaryGroups, function( $group ){
				try
				{
					\IPS\Member\Group::load( $group );
					return TRUE;
				}
				catch( \OutOfRangeException $e )
				{
					return FALSE;
				}
			});
			
			$member->mgroup_others = implode( ',', $membersSecondaryGroups );
			$member->save();
			$member->logHistory( 'core', 'group', array( 'type' => 'secondary', 'by' => 'subscription', 'action' => 'remove', 'id' => $this->id, 'old' => $currentSecondaryGroups, 'new' => $membersSecondaryGroups ) );
		}
	}
	
	/**
	 * Determines whether this package can be converted or not.
	 *
	 * @param	\IPS\nexus\Package	$package	The package we wish to convert
	 * @return boolean
	 */
	public static function canConvert( \IPS\nexus\Package $package )
	{
		if ( ! $package->physical and ! $package->lkey )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Update existing purchases
	 *
	 * @param	\IPS\nexus\Purchase	$purchase							The purchase
	 * @param	array				$changes							The old values
	 * @param	bool				$cancelBillingAgreementIfNecessary	If making changes to renewal terms, TRUE will cancel associated billing agreements. FALSE will skip that change
	 * @return	void
	 */
	public function updatePurchase( \IPS\nexus\Purchase $purchase, $changes, $cancelBillingAgreementIfNecessary=FALSE )
	{
		if ( array_key_exists( 'tax', $changes ) )
		{
			if ( !$purchase->billing_agreement or $cancelBillingAgreementIfNecessary )
			{
				if ( $billingAgreement = $purchase->billing_agreement )
				{
					try
					{
						$billingAgreement->cancel();
						$billingAgreement->save();
					}
					catch ( \Exception $e ) { }
				}
				
				$purchase->tax = $this->tax;
				$purchase->save();
			}
		}
		
		if ( array_key_exists( 'renew_options', $changes ) and !empty( $changes['renew_options'] ) )
		{
			$newRenewTerms = json_decode( $this->renew_options, TRUE );

			if( !\is_array( $newRenewTerms ) )
			{
				$newRenewTerms = array();
			}
						
			switch ( $changes['renew_options']['new'] )
			{
				case 'z':
					$purchase->renewals = NULL;
					$purchase->save();
					if ( $billingAgreement = $purchase->billing_agreement )
					{
						try
						{
							$billingAgreement->cancel();
							$billingAgreement->save();
						}
						catch ( \Exception $e ) { }
					}
					break;
				case 'y':
					$purchase->renewals = NULL;
					$purchase->active = TRUE;
					$purchase->save();
					if ( $billingAgreement = $purchase->billing_agreement )
					{
						try
						{
							$billingAgreement->cancel();
							$billingAgreement->save();
						}
						catch ( \Exception $e ) { }
					}
					break;
				case 'x':
					$purchase->renewals = NULL;
					$purchase->active = FALSE;
					$purchase->save();
					if ( $billingAgreement = $purchase->billing_agreement )
					{
						try
						{
							$billingAgreement->cancel();
							$billingAgreement->save();
						}
						catch ( \Exception $e ) { }
					}
					break;
				case '-':
					// do nothing
					break;
				default:
					if ( $changes['renew_options']['new'] === 'o' )
					{
						if ( !$purchase->billing_agreement or $cancelBillingAgreementIfNecessary )
						{
							if ( $billingAgreement = $purchase->billing_agreement )
							{
								try
								{
									$billingAgreement->cancel();
									$billingAgreement->save();
								}
								catch ( \Exception $e ) { }
							}
							
							
							$tax = NULL;
							if ( $purchase->tax )
							{
								try
								{
									$tax = \IPS\nexus\Tax::load( $purchase->tax );
								}
								catch ( \OutOfRangeException $e ) { }
							}
							
							$currency = $purchase->renewal_currency ?: $purchase->member->defaultCurrency( );

							$purchase->renewals = new \IPS\nexus\Purchase\RenewalTerm(
								new \IPS\nexus\Money( $newRenewTerms['cost'][ $currency ]['amount'], $currency ), 
								new \DateInterval( 'P' . $newRenewTerms['term'] . mb_strtoupper( $newRenewTerms['unit'] ) ),
								$tax
							);
							$purchase->save();
						}
					}
					break;
			}
		}
		
		if ( array_key_exists( 'primary_group', $changes ) or array_key_exists( 'secondary_group', $changes ) AND ( !$purchase->expire OR $purchase->expire->getTimestamp() > time() ) )
		{
			$this->_removeUsergroups( $purchase->member );
			$this->_addUsergroups( $purchase->member );
		}
	}
}