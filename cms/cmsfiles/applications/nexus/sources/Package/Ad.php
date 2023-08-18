<?php
/**
 * @brief		Advertisement Package
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
 * Advertisement Package
 */
class _Ad extends \IPS\nexus\Package
{
	/**
	 * @brief	Database Table
	 */
	protected static $packageDatabaseTable = 'nexus_packages_ads';
	
	/**
	 * @brief	Which columns belong to the local table
	 */
	protected static $packageDatabaseColumns = array( 'p_locations', 'p_exempt', 'p_expire', 'p_expire_unit', 'p_max_height', 'p_max_width' );
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'newspaper-o';
	
	/**
	 * @brief	Title
	 */
	public static $title = 'advertisement';
		
	/* !ACP Package Form */
	
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
		
		if ( !$customEdit ) // If we're editing a custom package, they can get to this by editing the advertisement
		{
			$locations	= array(
				'ad_global_header'	=> array(),
				'ad_global_footer'	=> array(),
				'ad_sidebar'		=> array(),
			);
			foreach ( \IPS\Application::allExtensions( 'core', 'AdvertisementLocations', FALSE, 'core' ) as $key => $extension )
			{
				$result	= $extension->getSettings( array() );
				$locations = array_merge( $locations, $result['locations'] );
			}
			$locations['_ad_custom_'] = array('p_locations_custom');
			$locationValues = array();
			$customLocations = array();
			if ( $package->id )
			{
				$locationValues = explode( ',', $package->locations );
				$customLocations = array_diff( $locationValues, array_keys( $locations ) );
				if ( !empty( $customLocations ) )
				{
					$locationValues[] = '_ad_custom_';
				}
				$locationValues = array_intersect( $locationValues, array_keys( $locations ) );
			}
			$return['package_settings']['p_locations'] = new \IPS\Helpers\Form\CheckboxSet( 'p_locations', $locationValues, TRUE, array( 'options' => array_combine( array_keys( $locations ), array_keys( $locations ) ), 'toggles' => $locations, 'userSuppliedInput' => '_ad_custom_' ) );
			$return['package_settings']['p_locations_custom'] = new \IPS\Helpers\Form\Stack( 'p_locations_custom', $customLocations, FALSE, array(), NULL, NULL, NULL, 'p_locations_custom' );
			
			$return['package_settings']['p_exempt'] = new \IPS\Helpers\Form\CheckboxSet( 'p_exempt', $package->id ? ( $package->exempt == '*' ? '*' : explode( ',', $package->exempt ) ) : '*', FALSE, array( 'options' => \IPS\Member\Group::groups(), 'parse' => 'normal', 'multiple' => TRUE, 'unlimited' => '*', 'unlimitedLang' => 'everyone', 'impliedUnlimited' => TRUE ) );
	
			$return['package_settings']['p_expire'] = new \IPS\Helpers\Form\Custom( 'p_expire', array( 'value' => ( $package->id ) ? $package->expire : -1, 'type' => ( $package->id ) ? $package->expire_unit : 'i' ), FALSE, array(
				'getHtml'	=> function( $element )
				{
					return \IPS\Theme::i()->getTemplate( 'promotion', 'core' )->imageMaximums( $element->name, $element->value['value'], $element->value['type'] );
				},
				'formatValue' => function( $element )
				{
					if( !\is_array( $element->value ) AND $element->value == -1 )
					{
						return array( 'value' => -1, 'type' => 'i' );
					}
	
					return array( 'value' => $element->value['value'], 'type' => $element->value['type'] );
				}
			) );
			
			if ( !$custom )
			{
				$return['package_settings']['p_max_dims'] = new \IPS\Helpers\Form\WidthHeight( 'p_max_dims', array( $package->max_width, $package->max_height ), FALSE, array( 'unlimited' => array( 0, 0 ), 'resizableDiv' => FALSE ) );
			}
		}
				
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
		if( isset( $values['p_exempt'] ) )
		{
			$values['p_exempt'] = \is_array( $values['p_exempt'] ) ? implode( ',', $values['p_exempt'] ) : $values['p_exempt'];
		}
				
		if ( isset( $values['p_expire'] ) and \is_array( $values['p_expire'] ) )
		{
			$values['expire'] = \intval( $values['p_expire']['value'] );
			$values['expire_unit'] = $values['p_expire']['type'];
			unset( $values['p_expire'] );
		}
		
		if ( isset( $values['p_max_dims'] ) )
		{
			$values['max_width'] = (int) $values['p_max_dims'][0];
			$values['max_height'] = (int) $values['p_max_dims'][1];
			unset( $values['p_max_dims'] );
		}
		
		if ( isset( $values['p_locations'] ) )
		{
			$locations = $values['p_locations'];
			$customKey = array_search( '_ad_custom_', $locations );
			if ( $customKey !== FALSE )
			{
				unset( $locations[ $customKey ] );
				$locations = array_merge( $locations, $values['p_locations_custom'] );
			}
			$values['p_locations'] = implode( ',', $locations );
		}
		unset( $values['p_locations_custom'] );
		
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
			'locations',
			'exempt',
			'expire',
			'expire_unit',
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
		if ( $purchase->extra['ad'] )
		{
			try
			{
				$ad = \IPS\Db::i()->select( '*','core_advertisements', array( 'ad_id=?', $purchase->extra['ad'] ) )->first();
			}
			catch ( \UnderflowException $e )
			{
				return parent::updatePurchase( $purchase, $changes, $cancelBillingAgreementIfNecessary );
			}
			
			$update = array();
			foreach ( array( 'locations', 'exempt', 'expire', 'expire_unit' ) as $k )
			{
				if ( array_key_exists( $k, $changes ) )
				{
					switch ( $k )
					{
						case 'locations':
							$i = 'ad_location';
							break;
						case 'exempt':
							$i = 'ad_exempt';
							break;
						case 'expire':
							$i = 'ad_maximum_value';
							break;
						case 'expire_unit':
							$i = 'ad_maximum_unit';
							break;
					}
					
					if ( $ad[ $i ] == $changes[ $k ] )
					{
						$update[ $i ] = $this->$k;
					}
				}
			}
			if ( !empty( $update ) )
			{
				\IPS\Db::i()->update( 'core_advertisements', $update, array( 'ad_id=?', $purchase->extra['ad'] ) );
			}
		}
		
		return parent::updatePurchase( $purchase, $changes, $cancelBillingAgreementIfNecessary );
	}
	
	/* !Store */
	
	/**
	 * Store Form
	 *
	 * @param	\IPS\Helpers\Form	$form			The form
	 * @param	string				$memberCurrency	The currency being used
	 * @return	void
	 */
	public function storeForm( \IPS\Helpers\Form $form, $memberCurrency )
	{
		$form->add( new \IPS\Helpers\Form\Url( 'advertisement_url', NULL, TRUE ) );
		
		$maxDims = TRUE;
		if ( $this->max_height or $this->max_width )
		{
			$maxDims = array();
			if ( $this->max_width )
			{
				$maxDims['maxWidth'] = $this->max_width;
			}
			if ( $this->max_height )
			{
				$maxDims['maxHeight'] = $this->max_height;
			}
		}
		
		if ( !isset( \IPS\Request::i()->stockCheck ) ) // The stock check will attempt to save the upload which we don't want to do until the form is actually submitted
		{
			$form->add( new \IPS\Helpers\Form\Upload( 'advertisement_image', NULL, TRUE, array( 'storageExtension' => 'nexus_Ads', 'image' => $maxDims ) ) );
		}
		
		if ( $this->max_height and $this->max_width )
		{
			\IPS\Member::loggedIn()->language()->words['advertisement_image_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'advertisement_image_max_wh', FALSE, array( 'sprintf' => array( $this->max_width, $this->max_height ) ) );
		}
		elseif ( $this->max_height )
		{
			\IPS\Member::loggedIn()->language()->words['advertisement_image_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'advertisement_image_max_h', FALSE, array( 'sprintf' => array( $this->max_height ) ) );
		}
		elseif ( $this->max_width )
		{
			\IPS\Member::loggedIn()->language()->words['advertisement_image_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'advertisement_image_max_w', FALSE, array( 'sprintf' => array( $this->max_width ) ) );
		}
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'ad_image_more', FALSE, FALSE, array( 'togglesOn' => array( 'ad_image_small', 'ad_image_medium' ) ), NULL, NULL, NULL, 'ad_image_more' ) );
		$form->add( new \IPS\Helpers\Form\Upload( 'ad_image_small', NULL, FALSE, array( 'image' => TRUE, 'storageExtension' => 'nexus_Ads' ), NULL, NULL, NULL, 'ad_image_small' ) );
		$form->add( new \IPS\Helpers\Form\Upload( 'ad_image_medium', NULL, FALSE, array( 'image' => TRUE, 'storageExtension' => 'nexus_Ads' ), NULL, NULL, NULL, 'ad_image_medium' ) );
	}
	
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
		$item->extra['image'] = json_encode( array( 'large' => (string) $values['advertisement_image'], 'medium' => (string) $values['ad_image_medium'], 'small' => (string) $values['ad_image_small'] ) );
		$item->extra['link'] = (string) $values['advertisement_url'];
	}
	
	/* !Client Area */
	
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
	 * Get Client Area Page HTML
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	array( 'packageInfo' => '...', 'purchaseInfo' => '...' )
	 */
	public function clientAreaPage( \IPS\nexus\Purchase $purchase )
	{	
		$parent = parent::clientAreaPage( $purchase );
		
		try
		{
			$advertisement = \IPS\core\Advertisement::load( $purchase->extra['ad'] );
			
			return array(
				'packageInfo'	=> $parent['packageInfo'],
				'purchaseInfo'	=> $parent['purchaseInfo'] . \IPS\Theme::i()->getTemplate('purchases')->advertisement( $purchase, $advertisement ),
			);
		}
		catch ( \OutOfRangeException $e )
		{
			return $parent;
		}
	}
	
	/* !ACP */
	
	/**
	 * ACP Generate Invoice Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @param	string				$k		The key to add to the field names
	 * @return	void
	 */
	public function generateInvoiceForm( \IPS\Helpers\Form $form, $k )
	{
		$form->attributes['data-bypassValidation'] = true;
		$field = new \IPS\Helpers\Form\Url( 'advertisement_url' . $k, NULL, TRUE );
		$field->label = \IPS\Member::loggedIn()->language()->addToStack('advertisement_url');
		$form->add( $field );
				
		$field = new \IPS\Helpers\Form\Upload( 'ad_image' . $k, NULL, TRUE, array( 'image' => TRUE, 'storageExtension' => 'nexus_Ads' ) );
		$field->label = \IPS\Member::loggedIn()->language()->addToStack('ad_image');
		$form->add( $field );
		
		$field = new \IPS\Helpers\Form\YesNo( 'ad_image_more' . $k, FALSE, FALSE, array( 'togglesOn' => array( 'ad_image_small' . $k, 'ad_image_medium' . $k ) ) );
		$field->label = \IPS\Member::loggedIn()->language()->addToStack('ad_image_more');
		$form->add( $field );
		
		$field = new \IPS\Helpers\Form\Upload( 'ad_image_small' . $k, NULL, FALSE, array( 'image' => TRUE, 'storageExtension' => 'nexus_Ads' ), NULL, NULL, NULL, 'ad_image_small' . $k );
		$field->label = \IPS\Member::loggedIn()->language()->addToStack('ad_image_small');
		$form->add( $field );
		
		$field = new \IPS\Helpers\Form\Upload( 'ad_image_medium' . $k, NULL, FALSE, array( 'image' => TRUE, 'storageExtension' => 'nexus_Ads' ), NULL, NULL, NULL, 'ad_image_medium' . $k );
		$field->label = \IPS\Member::loggedIn()->language()->addToStack('ad_image_medium');
		$form->add( $field );		
	}
	
	/**
	 * ACP Add to invoice
	 *
	 * @param	\IPS\nexus\extensions\nexus\Item\Package	$item			The item
	 * @param	array										$values			Values from form
	 * @param	string										$k				The key to add to the field names
	 * @param	\IPS\nexus\Invoice							$invoice		The invoice
	 * @return	void
	 */
	public function acpAddToInvoice( \IPS\nexus\extensions\nexus\Item\Package $item, array $values, $k, \IPS\nexus\Invoice $invoice )
	{
		$item->extra['image'] = json_encode( array( 'large' => (string) $values[ 'ad_image' . $k ], 'medium' => (string) $values[ 'ad_image_medium' . $k ], 'small' => (string) $values[ 'ad_image_small' . $k ] ) );
		$item->extra['link'] = (string) $values[ 'advertisement_url' . $k ];
	}
	
	/**
	 * Get ACP Page HTML
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	Purchase record
	 * @return	string
	 */
	public function acpPage( \IPS\nexus\Purchase $purchase )
	{
		try
		{
			return \IPS\Theme::i()->getTemplate( 'purchases' )->advertisement( $purchase, \IPS\core\Advertisement::load( $purchase->extra['ad'] ) );
		}
		catch ( \OutOfRangeException $e ) { }
	}
	
	/**
	 * Get ACP Page Buttons
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @param	\IPS\Http\Url		$url		The page URL
	 * @return	array
	 */
	public function acpButtons( \IPS\nexus\Purchase $purchase, $url )
	{
		$return = parent::acpButtons( $purchase, $url );
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'promotion', 'advertisements_edit' ) and isset( $purchase->extra['ad'] ) )
		{
			$return['edit_advertisement'] = array(
				'icon'	=> 'list-alt',
				'title'	=> 'edit_advertisement',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=promotion&controller=advertisements&do=form&id=' . $purchase->extra['ad'] ),
			);
		}
		
		return $return;
	}
	
	/* !Actions */
	
	/**
	 * On Purchase Generated
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	void
	 */
	public function onPurchaseGenerated( \IPS\nexus\Purchase $purchase, \IPS\nexus\Invoice $invoice )
	{		
		$insertId = \IPS\Db::i()->insert( 'core_advertisements', array(
			'ad_location'			=> $this->locations,
			'ad_html'				=> NULL,
			'ad_images'				=> $purchase->extra['image'],
			'ad_link'				=> $purchase->extra['link'],
			'ad_impressions'		=> 0,
			'ad_clicks'				=> 0,
			'ad_exempt'				=> $this->exempt === '*' ? '*' : json_encode( explode( ',', $this->exempt ) ),
			'ad_active'				=> -1,
			'ad_html_https'			=> NULL,
			'ad_start'				=> $purchase->start->getTimestamp(),
			'ad_end'				=> $purchase->expire ? $purchase->expire->getTimestamp() : 0,
			'ad_maximum_value'		=> $this->expire,
			'ad_maximum_unit'		=> $this->expire_unit,
			'ad_additional_settings'=> json_encode( array() ),
			'ad_html_https_set'		=> 0,
			'ad_member'				=> $purchase->member->member_id,
			'ad_type'				=> \IPS\core\Advertisement::AD_IMAGES,
		) );
		
		$extra = $purchase->extra;
		$extra['ad'] = $insertId;
		$purchase->extra = $extra;
		$purchase->save();
		
		\IPS\core\AdminNotification::send( 'nexus', 'Advertisement', NULL, TRUE, $purchase );

		parent::onPurchaseGenerated( $purchase, $invoice );
	}
		
	/**
	 * On Expiration Date Change
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public function onExpirationDateChange( \IPS\nexus\Purchase $purchase )
	{
		try
		{
			$ad = \IPS\core\Advertisement::load( $purchase->extra['ad'] );
			$ad->end = $purchase->expire ? $purchase->expire->getTimestamp() : 0;
						
			if ( ( !$ad->end or $ad->end > time() ) )
			{
				if ( $ad->maximum_value > -1 AND $ad->maximum_value )
				{					
					if ( $ad->maximum_unit == 'i' )
					{
						if ( $ad->impressions < $ad->maximum_value )
						{
							$ad->active = ( $ad->active == -1 ) ? -1 : 1;
						}
						else
						{
							$ad->active = 0;
						}
					}
					else
					{
						if ( $ad->clicks < $ad->maximum_value )
						{
							$ad->active = ( $ad->active == -1 ) ? -1 : 1;
						}
						else
						{
							$ad->active = 0;
						}
					}
				}
				else
				{
					$ad->active = ( $ad->active == -1 ) ? -1 : 1;
				}
			}
			else
			{
				$ad->active = 0;
			}
			
			$ad->save();
		}
		catch ( \OutOfRangeException $e ) { }
		
		parent::onExpirationDateChange( $purchase );
	}
	
	/**
	 * On Purchase Expired
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public function onExpire( \IPS\nexus\Purchase $purchase )
	{
		try
		{
			$ad = \IPS\core\Advertisement::load( $purchase->extra['ad'] );
			$ad->active = 0;			
			$ad->save();
		}
		catch ( \OutOfRangeException $e ) { }
		
		parent::onExpire( $purchase );
	}
	
	/**
	 * On Purchase Canceled
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public function onCancel( \IPS\nexus\Purchase $purchase )
	{
		try
		{
			$ad = \IPS\core\Advertisement::load( $purchase->extra['ad'] );
			$ad->active = 0;			
			$ad->save();
		}
		catch ( \OutOfRangeException $e ) { }
		
		parent::onCancel( $purchase );
	}
	
	/**
	 * On Purchase Deleted
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public function onDelete( \IPS\nexus\Purchase $purchase )
	{
		try
		{
			\IPS\core\Advertisement::load( $purchase->extra['ad'] )->delete();
		}
		catch ( \OutOfRangeException $e ) { }
		
		parent::onDelete( $purchase );
	}
	
	/**
	 * On Purchase Reactivated (renewed after being expired or reactivated after being canceled)
	 *
	 * @param	\IPS\nexus\Purchase	$purchase	The purchase
	 * @return	void
	 */
	public function onReactivate( \IPS\nexus\Purchase $purchase )
	{
		$this->onExpirationDateChange( $purchase );
		
		parent::onReactivate( $purchase );
	}
	
	/**
	 * On Transfer (is ran before transferring)
	 *
	 * @param	\IPS\nexus\Purchase	$purchase		The purchase
	 * @param	\IPS\Member			$newCustomer	New Customer
	 * @return	void
	 */
	public function onTransfer( \IPS\nexus\Purchase $purchase, \IPS\Member $newCustomer )
	{
		try
		{
			$ad = \IPS\core\Advertisement::load( $purchase->extra['ad'] );
			$ad->member = $newCustomer->member_id;			
			$ad->save();
		}
		catch ( \OutOfRangeException $e ) { }
		
		parent::onTransfer( $purchase, $newCustomer );
	}
	
	/**
	 * On Upgrade/Downgrade
	 *
	 * @param	\IPS\nexus\Purchase							$purchase				The purchase
	 * @param	\IPS\nexus\Package							$newPackage				The package to upgrade to
	 * @param	int|NULL|\IPS\nexus\Purchase\RenewalTerm	$chosenRenewalOption	The chosen renewal option
	 * @return	void
	 */
	public function onChange( \IPS\nexus\Purchase $purchase, \IPS\nexus\Package $newPackage, $chosenRenewalOption = NULL )
	{
		try
		{
			$ad = \IPS\core\Advertisement::load( $purchase->extra['ad'] );
			$ad->location = $newPackage->locations;
			$ad->exempt = $newPackage->exempt === '*' ? '*' : json_encode( explode( ',', $newPackage->exempt ) );
			$ad->maximum_value = $newPackage->expire;
			$ad->maximum_unit = $newPackage->expire_unit;
			$ad->save();
		}
		catch ( \OutOfRangeException $e ) { }

		parent::onChange( $purchase, $newPackage );
	}
}