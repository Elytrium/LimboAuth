<?php
/**
 * @brief		Custom Package
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		07 Aug 2014
 */

namespace IPS\nexus\extensions\nexus\Item;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Custom Package
 */
class _CustomPackage extends \IPS\nexus\extensions\nexus\Item\Package
{
	/**
	 * @brief	Application
	 */
	public static $application = 'nexus';
	
	/**
	 * @brief	Application
	 */
	public static $type = 'custom';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'asterisk';
	
	/**
	 * @brief	Title
	 */
	public static $title = 'custom';
	
	/**
	 * Generate Invoice Form
	 *
	 * @param	\IPS\Helpers\Form	$form		The form
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	void
	 */
	public static function form( \IPS\Helpers\Form $form, \IPS\nexus\Invoice $invoice )
	{
		$groups = array();
		foreach ( \IPS\Member\Group::groups() as $group )
		{
			$groups[ $group->g_id ] = $group->name;
		}
		
		$types = array();
		$typeFields = array();
		$typeFieldToggles = array();
		$formId = 'form_new';
		foreach ( \IPS\nexus\Package::packageTypes() as $key => $class )
		{
			$forceShow = TRUE;
			$types[ mb_strtolower( $key ) ] = 'p_type_' . $key;
			
			foreach ( $class::acpFormFields( new $class, TRUE ) as $group => $fields )
			{
				foreach ( $fields as $field )
				{
					if ( $field->name === 'p_show' )
					{
						$forceShow = FALSE;
					}							
					if ( !$field->htmlId )
					{
						$field->htmlId = $field->name;
						$typeFieldToggles[ mb_strtolower( $key ) ][] = $field->htmlId;		
					}	
					$typeFields[ $group ][] = $field;
				}
			}
			
			if ( $forceShow )
			{
				$typeFieldToggles[ mb_strtolower( $key ) ] = array_merge( isset( $typeFieldToggles[ mb_strtolower( $key ) ] ) ? $typeFieldToggles[ mb_strtolower( $key ) ] : array(), array( "{$formId}_tab_package_client_area", "{$formId}_header_package_associations", "{$formId}_header_package_associations_desc", 'p_associate', "{$formId}_header_package_renewals", 'p_renews', 'p_support_severity', 'p_lkey' ) );
			}
		}
		
		$form->addTab('package_settings');
		$form->add( new \IPS\Helpers\Form\Radio( 'p_type', 'product', TRUE, array( 'options' => $types, 'toggles' => $typeFieldToggles ) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'p_name', NULL, TRUE ) );
		
		foreach ( $typeFields['package_settings'] as $field )
		{
			$form->add( $field );
		}
		
		$form->addTab( 'package_pricing' );
		$form->add( new \IPS\Helpers\Form\Number( 'p_base_price', 0, TRUE, array( 'decimals' => TRUE ), NULL, NULL, $invoice->currency ) );
		$form->add( new \IPS\Helpers\Form\Node( 'p_tax', 0, FALSE, array( 'class' => 'IPS\nexus\Tax', 'zeroVal' => 'do_not_tax' ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'p_renews', FALSE, FALSE, array( 'togglesOn' => array( 'p_renew_options', 'p_renew' ) ), NULL, NULL, NULL, 'p_renews' ) );
		$form->add( new \IPS\nexus\Form\RenewalTerm( 'p_renew_options', NULL, FALSE, array( 'currency' => $invoice->currency ), NULL, NULL, NULL, 'p_renew_options' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'p_renew', FALSE, FALSE, array( 'togglesOn' => array( 'p_renewal_days', 'p_renewal_days_advance' ) ), NULL, NULL, NULL, 'p_renew' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'p_renewal_days', -1, FALSE, array( 'unlimited' => -1, 'unlimitedLang' => 'any_time' ), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('days_before_expiry'), 'p_renewal_days' ) );
		$form->add( new \IPS\Helpers\Form\Interval( 'p_renewal_days_advance', -1, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => -1 ), NULL, NULL, NULL, 'p_renewal_days_advance' ) );
		
		$form->addTab( 'package_benefits' );
		unset( $groups[ \IPS\Settings::i()->guest_group ] );
		$form->add( new \IPS\Helpers\Form\Select( 'p_primary_group', '*', FALSE, array( 'options' => $groups, 'unlimited' => '*', 'unlimitedLang' => 'do_not_change', 'unlimitedToggles' => array( 'p_return_primary' ), 'unlimitedToggleOn' => FALSE ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'p_return_primary', TRUE, FALSE, array(), NULL, NULL, NULL, 'p_return_primary' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'p_secondary_group', '*', FALSE, array( 'options' => $groups, 'multiple' => TRUE, 'unlimited' => '*', 'unlimitedLang' => 'do_not_change', 'unlimitedToggles' => array( 'p_return_secondary' ), 'unlimitedToggleOn' => FALSE ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'p_return_secondary', TRUE, FALSE, array(), NULL, NULL, NULL, 'p_return_secondary' ) );
		$form->add( new \IPS\Helpers\Form\Node( 'p_support_severity', 0, FALSE, array( 'class' => 'IPS\nexus\Support\Severity', 'zeroVal' => 'none' ), NULL, NULL, NULL, 'p_support_severity' ) );
				
		foreach ( $typeFields['package_benefits'] as $field )
		{
			$form->add( $field );
		}
		
		$form->addTab('package_client_area_display');
		$form->add( new \IPS\Helpers\Form\Editor( 'p_page', NULL, FALSE, array(
			'app'			=> 'nexus',
			'key'			=> 'Admin',
			'autoSaveKey'	=> "nexus-new-pkg-pg",
			'attachIds'		=> NULL, 'minimize' => 'p_page_placeholder'
		), NULL, NULL, NULL, 'p_desc_editor' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'p_support', FALSE, FALSE, array( 'togglesOn' => array( 'p_support_department' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Node( 'p_support_department', 0, FALSE, array( 'class' => 'IPS\nexus\Support\Department', 'zeroVal' => 'none' ), NULL, NULL, NULL, 'p_support_department' ) );
		
	}
	
	
	/**
	 * Generate Invoice Form: Second Step
	 *
	 * @param	array				$values		Form values from previous step
	 * @param	\IPS\Helpers\Form	$form		The form
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	bool
	 */
	public static function formSecondStep( array $values, \IPS\Helpers\Form $form, \IPS\nexus\Invoice $invoice )
	{
		$types		= \IPS\nexus\Package::packageTypes();
		$classname	= $types[ mb_ucfirst( $values['p_type'] ) ];
		
		if ( method_exists( $classname, 'generateInvoiceForm' ) )
		{
			if ( isset( \IPS\Request::i()->customPackageId ) )
			{
				$package = \IPS\nexus\Package::load( \IPS\Request::i()->customPackageId );
			}
			else
			{
				$package = static::_createPackage( $values, $invoice );
			}
			
			$form->hiddenValues['customPackageId'] = $package->id;
			$package->generateInvoiceForm( $form, '' );
			
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Create the package
	 *
	 * @param	array				$values		Values from form
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	\IPS\nexus\Package
	 */
	protected static function _createPackage( array $values, \IPS\nexus\Invoice $invoice )
	{
		/* Init */
		$package = new \IPS\nexus\Package;
		$package->custom = $invoice->member->member_id;

		/* Figure out tax */
		$tax = NULL;

		try
		{
			if( $values['p_tax'] )
			{
				$tax = $values['p_tax'];
			}
		}
		catch( \OutOfRangeException $e ){}

		/* Set default values for stuff we didn't have */
		$values['p_member_groups'] = array();
		$values['p_notify'] = array();
		$values['p_usergroup_discounts'] = array();
		$values['p_loyalty_discounts'] = array();
		$values['p_bulk_discounts'] = array();
		$values['p_images'] = array();
		$values['p_group'] = new \StdClass;
		$values['p_group']->id = 0;
		$renewTerm = $values['p_renews'] ? 
			new \IPS\nexus\Purchase\RenewalTerm( 
				new \IPS\nexus\Money( \IPS\Request::i()->p_renew_options['amount'], ( isset( \IPS\Request::i()->p_renew_options['currency'] ) ) ? \IPS\Request::i()->p_renew_options['currency'] : $invoice->currency ), 
				new \DateInterval( 'P' . \IPS\Request::i()->p_renew_options['term'] . mb_strtoupper( \IPS\Request::i()->p_renew_options['unit'] ) ), 
				$tax,
				FALSE 
			) : NULL;
		$values['p_renew_options'] = array();
		
		/* Save */
		$package->saveForm( $package->formatFormValues( $values ) );
		
		/* Custom-specific */
		$package = \IPS\nexus\Package::load( $package->id );
		$package->name = $values['p_name'];
		$package->base_price = json_encode( array( $invoice->currency => array( 'amount' => $values['p_base_price'], 'currency' => $invoice->currency ) ) );
		if ( $values['p_renews'] )
		{
			$term = $renewTerm->getTerm();
			$package->renew_options = json_encode( array( array(
					'cost'	=> $renewTerm->cost,
					'term'	=> $term['term'],
					'unit'	=> $term['unit'],
					'add'	=> FALSE
				) ) );
		}
		else
		{
			$package->renew_options = NULL;
		}
		$package->store = 0;
		$package->page = $values['p_page'];
		$package->save();
		
		/* Return */
		return $package;
	}
	
	/**
	 * Create From Form
	 *
	 * @param	array				$values		Values from form
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	\IPS\nexus\extensions\nexus\Item\CustomPackage
	 */
	public static function createFromForm( array $values, \IPS\nexus\Invoice $invoice )
	{		
		/* Create the package */
		if ( isset( \IPS\Request::i()->customPackageId ) )
		{
			$package = \IPS\nexus\Package::load( \IPS\Request::i()->customPackageId );
		}
		else
		{
			$package = static::_createPackage( $values, $invoice );
		}

		/* Figure out tax */
		$tax = NULL;

		try
		{
			if( $package->tax )
			{
				$tax = \IPS\nexus\Tax::load( $package->tax );
			}
		}
		catch( \OutOfRangeException $e ){}
				
		/* Work stuff out */
		$basePrice = json_decode( $package->base_price, TRUE );
		if ( $package->renew_options )
		{
			$renewTerm = json_decode( $package->renew_options, TRUE );
			$renewTerm = array_pop( $renewTerm );
			$renewTerm = new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( $renewTerm['cost']['amount'], $renewTerm['cost']['currency'] ), new \DateInterval( 'P' . $renewTerm['term'] . mb_strtoupper( $renewTerm['unit'] ) ), $tax, FALSE );
		}
		else
		{
			$renewTerm = NULL;
		}
				
		/* Now create an item */
		$item = new static( $package->name, new \IPS\nexus\Money( $basePrice[ $invoice->currency ]['amount'], $invoice->currency ) );
		$item->renewalTerm = $renewTerm;
		$item->quantity = 1;
		$item->id = $package->id;
		$item->tax = $tax;
		if ( $package instanceof \IPS\nexus\Package\Product and $package->physical )
		{
			$item->physical = TRUE;
			$item->weight = new \IPS\nexus\Shipping\Weight( $package->weight );
			$item->length = new \IPS\nexus\Shipping\Length( $package->length );
			$item->width = new \IPS\nexus\Shipping\Length( $package->width );
			$item->height = new \IPS\nexus\Shipping\Length( $package->height );
			if ( $package->shipping !== '*' )
			{
				$item->shippingMethodIds = explode( ',', $package->shipping );
			}
		}
		$package->acpAddToInvoice( $item, $values, '', $invoice );
		
		/* And return */
		return $item;
	}
	
	/** 
	 * ACP Edit Form
	 *
	 * @param	\IPS\nexus\Purchase				$purchase	The purchase
	 * @param	\IPS\Helpers\Form				$form		The form
	 * @param	\IPS\nexus\Purchase\RenewalTerm	$renewals	The renewal term
	 * @return	string
	 */
	public static function acpEdit( \IPS\nexus\Purchase $purchase, \IPS\Helpers\Form $form, $renewals )
	{
		$form->addTab( 'basic_settings' );
		parent::acpEdit( $purchase, $form, $renewals );
		
		$package = \IPS\nexus\Package::load( $purchase->item_id );
		$typeFields = $package->acpFormFields( $package, TRUE, TRUE );
		
		$groups = array();
		foreach ( \IPS\Member\Group::groups() as $group )
		{
			$groups[ $group->g_id ] = $group->name;
		}
		
		if ( isset( $typeFields['package_settings'] ) )
		{
			foreach ( $typeFields['package_settings'] as $field )
			{
				$form->add( $field );
			}
		}
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'p_renew', $package->renewal_days != 0, FALSE, array( 'togglesOn' => array( 'p_renewal_days', 'p_renewal_days_advance' ) ), NULL, NULL, NULL, 'p_renew' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'p_renewal_days', $package->renewal_days, FALSE, array( 'unlimited' => -1, 'unlimitedLang' => 'any_time' ), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('days_before_expiry'), 'p_renewal_days' ) );
		$form->add( new \IPS\Helpers\Form\Interval( 'p_renewal_days_advance', $package->renewal_days_advance, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => -1 ), NULL, NULL, NULL, 'p_renewal_days_advance' ) );

		$form->addTab( 'package_benefits' );
		unset( $groups[ \IPS\Settings::i()->guest_group ] );
		$form->add( new \IPS\Helpers\Form\Select( 'p_primary_group', $package->primary_group ?: '*', FALSE, array( 'options' => $groups, 'unlimited' => '*', 'unlimitedLang' => 'do_not_change', 'unlimitedToggles' => array( 'p_return_primary' ), 'unlimitedToggleOn' => FALSE ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'p_return_primary', $package->return_primary, FALSE, array(), NULL, NULL, NULL, 'p_return_primary' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'p_secondary_group', $package->secondary_group ? explode( ',', $package->member_groups ) : '*', FALSE, array( 'options' => $groups, 'multiple' => TRUE, 'unlimited' => '*', 'unlimitedLang' => 'do_not_change', 'unlimitedToggles' => array( 'p_return_secondary' ), 'unlimitedToggleOn' => FALSE ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'p_return_secondary', $package->return_secondary, FALSE, array(), NULL, NULL, NULL, 'p_return_secondary' ) );
		$form->add( new \IPS\Helpers\Form\Node( 'p_support_severity', 0, $package->support_severity ?: 0, array( 'class' => 'IPS\nexus\Support\Severity', 'zeroVal' => 'none' ), NULL, NULL, NULL, 'p_support_severity' ) );
				
		if ( isset( $typeFields['package_benefits'] ) )
		{
			foreach ( $typeFields['package_benefits'] as $field )
			{
				$form->add( $field );
			}
		}
		
		$form->addTab('package_client_area_display');
		$form->add( new \IPS\Helpers\Form\Editor( 'p_page', $package->page, FALSE, array(
			'app'			=> 'nexus',
			'key'			=> 'Admin',
			'autoSaveKey'	=> "nexus-new-pkg-pg",
			'attachIds'		=> NULL, 'minimize' => 'p_page_placeholder'
		), NULL, NULL, NULL, 'p_desc_editor' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'p_support', $package->support, FALSE, array( 'togglesOn' => array( 'p_support_department' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Node( 'p_support_department', $package->support ?: 0, FALSE, array( 'class' => 'IPS\nexus\Support\Department', 'zeroVal' => 'none' ), NULL, NULL, NULL, 'p_support_department' ) );
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
		parent::acpEditSave( $purchase, $values );
		
		$package = \IPS\nexus\Package::load( $purchase->item_id );
		
		$resetLkey = FALSE;
		$updateLkey = FALSE;
		$deleteLkey = FALSE;
		
		$package->show = $values['p_show'];
		$package->renewal_days = $values['p_renew'] ? $values['p_renewal_days'] : 0;
		$package->renewal_days_advance = $values['p_renew'] ? $values['p_renewal_days_advance'] : 0;
		$package->primary_group = $values['p_primary_group'] == '*' ? 0 : $values['p_primary_group'];
		$package->return_primary = $values['p_return_primary'];
		$package->secondary_group = $values['p_secondary_group'] == '*' ? '*' : implode( ',', $values['p_secondary_group'] );
		$package->return_secondary = $values['p_return_secondary'];
		$package->support_severity = $values['p_support_severity'] ? $values['p_support_severity']->id : 0;
		
		if ( $values['p_lkey'] != $package->lkey )
		{
			$package->lkey = $values['p_lkey'];
			
			if ( $values['p_lkey'] )
			{
				$resetLkey = TRUE;
			}
			else
			{
				$deleteLkey = TRUE;
			}
		}
		if ( $values['p_lkey_identifier'] != $package->lkey_identifier )
		{
			$package->lkey_identifier = $values['p_lkey_identifier'];
			$updateLkey = TRUE;
		}
		if ( $values['p_lkey_uses'] != $package->lkey_uses )
		{
			$package->lkey_uses = $values['p_lkey_uses'];
			$updateLkey = TRUE;
		}
		
		$package->page = $values['p_page'];
		$package->support = ( $values['p_support'] and $values['p_support_department'] ) ? $values['p_support_department']->id : 0;
		$package->save();

		if ( $resetLkey or $updateLkey or $deleteLkey )
		{
			$licenseTypes = \IPS\nexus\Purchase\LicenseKey::licenseKeyTypes();

			if ( $resetLkey or $deleteLkey )
			{
				try
				{
					$purchase->licenseKey()->delete();
				}
				catch ( \OutOfRangeException $e ) { }
				
				if ( $resetLkey )
				{
					$class = $licenseTypes[ mb_ucfirst( $package->lkey ) ];
					$licenseKey = new $class;
				}
			}
			elseif ( $updateLkey )
			{
				try
				{
					$licenseKey = $purchase->licenseKey();
				}
				catch ( \OutOfRangeException $e )
				{
					$class = $licenseTypes[ mb_ucfirst( $package->lkey ) ];
					$licenseKey = new $class;
				}
			}
			
			if ( $resetLkey or $updateLkey )
			{
				$licenseKey->identifier = $package->lkey_identifier;
				$licenseKey->purchase = $purchase;
				$licenseKey->max_uses = $package->lkey_uses;
				$licenseKey->save();
			}
		} 
	}
	
	/**
	 * Show Purchase Record?
	 *
	 * @return	bool
	 */
	public function showPurchaseRecord()
	{
		return \IPS\nexus\Package::load( $this->id )->showPurchaseRecord();
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
		// Custom packages do not have any purchase restrictions
	}
}