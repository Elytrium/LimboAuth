<?php
/**
 * @brief		Product Package
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		29 Apr 2014
 */

namespace IPS\nexus\Package;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Product Package
 */
class _Product extends \IPS\nexus\Package
{
	/**
	 * @brief	Database Table
	 */
	protected static $packageDatabaseTable = 'nexus_packages_products';
	
	/**
	 * @brief	Which columns belong to the local table
	 */
	protected static $packageDatabaseColumns = array( 'p_physical', 'p_subscription', 'p_shipping', 'p_weight', 'p_lkey', 'p_lkey_identifier', 'p_lkey_uses', 'p_show', 'p_length', 'p_width', 'p_height' );
	
	/**
	 * ACP Fields
	 *
	 * @param	\IPS\nexus\Package	$package	The package
	 * @param	bool				$custom		If TRUE, is for a custom package
	 * @param	bool				$customEdit	If TRUE, is editing a custom package
	 * @return	array
	 */
	public static function acpFormFields( \IPS\nexus\Package $package, $custom=FALSE, $customEdit=FALSE )
	{
		$return = array();
		$formId = $package->id ? "form_{$package->id}" : 'form_new';
		
		if ( !$customEdit ) // After the package has been created, these are unimportant
		{
			$return['package_settings']['physical'] = new \IPS\Helpers\Form\YesNo( 'p_physical', $package->type === 'product' ? $package->physical : FALSE, FALSE, array( 'disableCopy' => TRUE, 'togglesOn' => array( 'p_weight', 'p_length', 'p_width', 'p_height', 'p_shipping' ), 'togglesOff' => array( 'elProductTaxWarningContainer' ) ) );
			$return['package_settings']['weight'] = new \IPS\nexus\Form\Weight( 'p_weight', $package->type === 'product' ? new \IPS\nexus\Shipping\Weight( $package->weight ) : NULL, FALSE, array( 'disableCopy' => TRUE ), NULL, NULL, NULL, 'p_weight' );
			$return['package_settings']['length'] = new \IPS\nexus\Form\Length( 'p_length', $package->type === 'product' ? new \IPS\nexus\Shipping\Length( $package->length ) : NULL, FALSE, array( 'disableCopy' => TRUE ), NULL, NULL, NULL, 'p_length' );
			$return['package_settings']['width'] = new \IPS\nexus\Form\Length( 'p_width', $package->type === 'product' ? new \IPS\nexus\Shipping\Length( $package->width ) : NULL, FALSE, array( 'disableCopy' => TRUE ), NULL, NULL, NULL, 'p_width' );
			$return['package_settings']['height'] = new \IPS\nexus\Form\Length( 'p_height', $package->type === 'product' ? new \IPS\nexus\Shipping\Length( $package->height ) : NULL, FALSE, array( 'disableCopy' => TRUE ), NULL, NULL, NULL, 'p_height' );
		
			$availableShippingMethods = array();
			foreach ( \IPS\nexus\Shipping\FlatRate::roots() as $rate )
			{
				$availableShippingMethods[ $rate->_id ] = $rate->_title;
			}
			if ( \IPS\Settings::i()->easypost_api_key and \IPS\Settings::i()->easypost_show_rates )
			{
				$availableShippingMethods['easypost'] = 'enhancements__nexus_EasyPost';
			}
			$return['package_settings']['shipping'] = new \IPS\Helpers\Form\CheckboxSet( 'p_shipping', ( $package->type === 'product' and $package->shipping !== '*' ) ? explode( ',', $package->shipping ) : '*', FALSE, array( 'options' => $availableShippingMethods, 'multiple' => TRUE, 'unlimited' => '*', 'impliedUnlimited' => TRUE ), NULL, NULL, NULL, 'p_shipping' );
		}
		
		$return['package_settings']['show'] = new \IPS\Helpers\Form\YesNo( 'p_show', $package->type === 'product' ? $package->show : TRUE, FALSE, array( 'togglesOn' => array( "{$formId}_tab_package_client_area", "{$formId}_header_package_associations", "{$formId}_header_package_associations_desc", 'p_associate', "{$formId}_header_package_renewals", 'p_renews', 'p_support_severity', 'p_lkey' ) ) );
		
		if ( !$custom )
		{		
			$return['store_permissions']['subscription'] = new \IPS\Helpers\Form\YesNo( 'p_subscription', $package->type === 'product' ? !$package->subscription : TRUE );
		}
			
		$licenseKeyOptions = array();
		$licenseKeyToggles = array();
		foreach ( \IPS\nexus\Purchase\LicenseKey::licenseKeyTypes() as $key => $class )
		{
			$licenseKeyOptions[ mb_strtolower( $key ) ] = 'lkey_' . $key;
			$licenseKeyToggles[ mb_strtolower( $key ) ] = array( 'p_lkey_identifier', 'p_lkey_uses' );
		}
		if ( !empty( $licenseKeyOptions ) )
		{ 
			array_unshift( $licenseKeyOptions, 'lkey_none' );
			$return['package_benefits']['lkey'] = new \IPS\Helpers\Form\Radio( 'p_lkey', $package->type === 'product' ? $package->lkey : 0, FALSE, array( 'options' => $licenseKeyOptions, 'toggles' => $licenseKeyToggles ), NULL, NULL, NULL, 'p_lkey' );
		}
		
		$return['package_benefits']['lkey_uses'] = new \IPS\Helpers\Form\Number( 'p_lkey_uses', $package->type === 'product' ? $package->lkey_uses : -1, FALSE, array( 'unlimited' => -1 ) );
		
		$identifierOptions = array(
			'name'		=> 'lkey_identifier_name',
			'email'		=> 'lkey_identifier_email',
			'username'	=> 'lkey_identifier_username',
		);
		if ( $package->id )
		{
			foreach ( \IPS\nexus\Package\CustomField::roots( NULL, NULL, array( array( \IPS\Db::i()->findInSet( 'cf_packages', array( $package->id ) ) ) ) ) as $field )
			{
				$identifierOptions[ $field->id ] = $field->_title;
			}
		}
		
		$return['package_benefits']['lkey_identifier'] = new \IPS\Helpers\Form\Select( 'p_lkey_identifier', $package->type === 'product' ? $package->lkey_identifier : '0', FALSE, array( 'options' => $identifierOptions, 'unlimited' => '0', 'unlimitedLang' => 'lkey_identifier_none' ), NULL, NULL, NULL, 'p_lkey_identifier' );
		
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
		if( isset( $values['p_subscription'] ) )
		{
			$values['p_subscription'] = isset( $values['p_subscription'] ) ? !$values['p_subscription'] : FALSE;
		}
		
		if( isset( $values['p_weight'] ) )
		{
			$values['p_weight'] = \is_object( $values['p_weight'] ) ? $values['p_weight']->kilograms : 0;
		}
		else
		{
			$values['p_weight'] = 0;
		}

		if( isset( $values['p_length'] ) )
		{
			$values['p_length'] = \is_object( $values['p_length'] ) ? $values['p_length']->metres : 0;
		}
		else
		{
			$values['p_length'] = 0;
		}

		if( isset( $values['p_width'] ) )
		{
			$values['p_width'] = \is_object( $values['p_width'] ) ? $values['p_width']->metres : 0;
		}
		else
		{
			$values['p_width'] = 0;
		}

		if( isset( $values['p_height'] ) )
		{
			$values['p_height'] = \is_object( $values['p_height'] ) ? $values['p_height']->metres : 0;
		}
		else
		{
			$values['p_height'] = 0;
		}
		
		if( isset( $values['p_shipping'] ) )
		{
			$values['p_shipping'] = \is_array( $values['p_shipping'] ) ? implode( ',', $values['p_shipping'] ) : '*';
		}

		return parent::formatFormValues( $values );
	}
	
	/**
	 * Updateable fields
	 *
	 * @return	array
	 */
	public static function updateableFields()
	{
		return array_merge( parent::updateableFields(), array(
			'lkey',
			'lkey_identifier',
			'lkey_uses',
			'show'
		) );
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
		if ( array_key_exists( 'lkey', $changes ) )
		{
			$oldKey = NULL;

			$lKey = $purchase->licenseKey();

			if ( $lKey )
			{
				$lKey->delete();
			}

			$licenseTypes = \IPS\nexus\Purchase\LicenseKey::licenseKeyTypes();

			if( class_exists( $licenseTypes[ mb_ucfirst( $this->lkey ) ]) )
			{
				$class = $licenseTypes[ mb_ucfirst( $this->lkey ) ];
				$licenseKey = new $class;
				$licenseKey->identifier = $this->lkey_identifier;
				$licenseKey->purchase = $purchase;
				$licenseKey->max_uses = $this->lkey_uses;
				$licenseKey->save();
			}

		}
		elseif ( array_key_exists( 'lkey_identifier', $changes ) or array_key_exists( 'lkey_uses', $changes ) )
		{
			$licenseKey = $purchase->licenseKey();

			if( $licenseKey )
			{
				$licenseKey->identifier = $this->lkey_identifier;
				$licenseKey->max_uses = $this->lkey_uses;
				$licenseKey->save();
			}
		}
		
		if ( array_key_exists( 'show', $changes ) )
		{
			$purchase->show = $this->show;
			$purchase->save();
		}
		
		return parent::updatePurchase( $purchase, $changes, $cancelBillingAgreementIfNecessary );
	}
	
	/* !Actions */
	
	/**
	 * Add To Cart
	 *
	 * @param	\IPS\nexus\extensions\nexus\Item\Package	$item			The item
	 * @param	array										$values			Values from form
	 * @param	string										$memberCurrency	The currency being used
	 * @return	array	Additional items to add
	 */
	public function addToCart( \IPS\nexus\extensions\nexus\Item\Package $item, array $values, $memberCurrency )
	{
		if ( $this->subscription )
		{
			if ( $item->quantity > 1 )
			{
				\IPS\Output::i()->error( 'err_subscription_qty', '1X247/2', 403, '' );
			}
			
			if ( $this->_memberHasPurchasedSubscription( \IPS\Member::loggedIn() ) )
			{
				\IPS\Output::i()->error( 'err_subscription_bought', '1X247/1', 403, '' );
			}
			
			if ( isset( $_SESSION['cart'] ) )
			{
				foreach ( $_SESSION['cart'] as $_item )
				{
					if ( $_item->id === $this->id )
					{
						\IPS\Output::i()->error( 'err_subscription_in_cart', '1X247/3', 403, '' );
					}
				}
			}
		}
		
		return parent::addToCart( $item, $values, $memberCurrency );
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
		if ( $this->subscription and $this->_memberHasPurchasedSubscription( $member ) )
		{
			throw new \DomainException( $member->language()->addToStack( 'err_subscription_bought_login', FALSE, array( 'sprintf' => array( $member->language()->addToStack( "nexus_package_{$this->id}" ) ) ) ) );
		}
		if ( ! ( $this->member_groups == "*" or !empty( ( array_intersect( explode( ",", $this->member_groups ), $member->groups ) ) ) ) )
		{
			throw new \DomainException( $member->language()->addToStack( 'err_group_cant_purchase', FALSE, array( 'sprintf' => array( $member->language()->addToStack( "nexus_package_{$this->id}" ) ) ) ) );
		}
	}
	
	/**
	 * Check if a member has purchased this subscription product
	 *
	 * @param	\IPS\Member	$member	The new member
	 * @return	bool
	 */
	protected function _memberHasPurchasedSubscription( \IPS\Member $member )
	{
		return (bool) \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=? AND ps_cancelled=0 AND ps_member=?', 'nexus', 'package', $this->id, $member->member_id ) )->first();
	}
	
	/**
	 * Show Purchase Record?
	 *
	 * @return	bool
	 */
	public function showPurchaseRecord()
	{
		return $this->show;
	}
	
	/**
	 * Get ACP Page HTML
	 *
	 * @return	string
	 */
	public function acpPage( \IPS\nexus\Purchase $purchase )
	{
		if ( $this->lkey and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'lkeys_view' ) )
		{
			if ( $lkey = $purchase->licenseKey() )
			{
				return \IPS\Theme::i()->getTemplate('purchases')->lkey( $lkey );
			}
			else
			{
				return \IPS\Theme::i()->getTemplate('purchases')->noLkey( $purchase );
			}
		}
	}
	
	/**
	 * ACP Action
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public function acpAction( \IPS\nexus\Purchase $purchase )
	{
		switch ( \IPS\Request::i()->act )
		{
			case 'lkeyReset':
				\IPS\Dispatcher::i()->checkAcpPermission( 'lkeys_reset' );
				\IPS\Session::i()->csrfCheck();
				
				$oldKey = NULL;
				try
				{
					if ( $old = $purchase->licenseKey() )
					{
						$oldKey = $old->key;
						$old->delete();
					}
				}
				catch ( \OutOfRangeException $e ) { }
				
				/* Invalidate License Key Cache so old data is not loaded */
				$purchase->licenseKey = NULL;
				
				$licenseTypes = \IPS\nexus\Purchase\LicenseKey::licenseKeyTypes();
				$class = $licenseTypes[ mb_ucfirst( $this->lkey ) ];
				$licenseKey = new $class;
				$licenseKey->identifier = $this->lkey_identifier;
				$licenseKey->purchase = $purchase;
				$licenseKey->max_uses = $this->lkey_uses;
				$licenseKey->save();
				
				$purchase->member->log( 'lkey', array( 'type' => 'reset', 'key' => $oldKey, 'new' => $licenseKey->key, 'ps_id' => $purchase->id, 'ps_name' => $purchase->name ) );
				break;
				
			default:
				return parent::acpAction( $purchase );
		}
	}
	
	/**
	 * On Purchase Generated
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	void
	 */
	public function onPurchaseGenerated( \IPS\nexus\Purchase $purchase, \IPS\nexus\Invoice $invoice )
	{
		if ( $this->lkey )
		{
			$licenseTypes = \IPS\nexus\Purchase\LicenseKey::licenseKeyTypes();
			$class = $licenseTypes[ mb_ucfirst( $this->lkey ) ];
			$licenseKey = new $class;
			$licenseKey->identifier = $this->lkey_identifier;
			$licenseKey->purchase = $purchase;
			$licenseKey->max_uses = $this->lkey_uses;
			$licenseKey->save();
		}
		
		parent::onPurchaseGenerated( $purchase, $invoice );
	}
	
	/**
	 * Warning to display to admin when cancelling a purchase
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	string
	 */
	public function onCancelWarning( \IPS\nexus\Purchase $purchase )
	{
		return NULL;
	}
}