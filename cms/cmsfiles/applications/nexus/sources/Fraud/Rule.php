<?php
/**
 * @brief		Fraud Rule Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		11 Mar 2014
 */

namespace IPS\nexus\Fraud;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Fraud Rule Node
 */
class _Rule extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'nexus_fraud_rules';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'f_';
		
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'order';
		
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'fraud_rules';
	
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
		'module'	=> 'payments',
		'all'		=> 'fraud_manage'
	);
	
	/**
	 * [Node] Get title
	 *
	 * @return	string
	 */
	protected function get__title()
	{
		return $this->name;
	}
	
	/**
	 * [Node] Get description
	 *
	 * @return	string
	 */
	protected function get__description()
	{
		$conditions = array();
		$results = array();
		$warning = NULL;
		
		/* Preceeding rules */
		foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_fraud_rules', array( 'f_order<?', $this->order ), 'f_order' ), 'IPS\nexus\Fraud\Rule' ) as $otherRule )
		{
			if ( !\count( $conditions ) )
			{
				$conditions[] = \IPS\Member::loggedIn()->language()->addToStack('f_has_preceeding');
			}
			
			if ( $otherRule->isSubsetOf( $this ) )
			{
				$warning = $otherRule;
				break;
			}
		}
		
		/* Amount */
		if ( $this->amount )
		{
			$amounts = array();
			foreach ( $this->amount_unit as $currency => $amount )
			{
				$amounts[] = (string) new \IPS\nexus\Money( $amount, $currency );
			}
			
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_amount', FALSE, array( 'sprintf' => array( $this->_gle( $this->amount ), implode( ' ' . \IPS\Member::loggedIn()->language()->addToStack('or') . ' ', $amounts ) ) ) );
		}
		
		/* Methods */
		if ( $this->methods != '*' )
		{
			$paymentMethods = array();
			foreach ( \IPS\nexus\Gateway::roots() as $gateway )
			{
				$paymentMethods[ $gateway->id ] = $gateway->_title;
			}
			
			$_paymentMethods = array();
			foreach ( explode( ',', $this->methods ) as $m )
			{
				$_paymentMethods[] = $paymentMethods[ $m ];
			}
			
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_methods', FALSE, array( 'sprintf' => array( implode( ' ' . \IPS\Member::loggedIn()->language()->addToStack('or') . ' ', $_paymentMethods ) ) ) );
		}
		
		/* Products */
		if ( $this->products and $this->products != '*' )
		{
			$packages = array();
			foreach ( explode( ',', $this->products ) as $id )
			{
				try
				{
					$packages[] = \IPS\nexus\Package::load( $id )->_title;
				}
				catch ( \OutOfRangeException $e ) { }
			}
			
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_products', FALSE, array( 'sprintf' => array( implode( ' ' . \IPS\Member::loggedIn()->language()->addToStack('or') . ' ', $packages ) ) ) );
		}

		/* Subscriptions */
		if ( $this->subscriptions and $this->subscriptions != '*' )
		{
			$subscriptions = array();
			foreach ( explode( ',', $this->subscriptions ) as $id )
			{
				try
				{
					$subscriptions[] = \IPS\nexus\Subscription\Package::load( $id )->_title;
				}
				catch ( \OutOfRangeException $e ) { }
			}

			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_subscriptions', FALSE, array( 'sprintf' => array( implode( ' ' . \IPS\Member::loggedIn()->language()->addToStack('or') . ' ', $subscriptions ) ) ) );
		}
		
		/* Coupon */
		if ( $this->coupon )
		{
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( $this->coupon == 1 ? 'f_blurb_coupon_y' : 'f_blurb_coupon_n' );
		}
		
		/* Countries */
		if ( $this->country != '*' )
		{			
			$countries = array();
			foreach ( explode( ',', $this->country ) as $m )
			{
				$countries[] = \IPS\Member::loggedIn()->language()->addToStack( 'country-' . $m );
			}
			
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_countries', FALSE, array( 'sprintf' => array( implode( ' ' . \IPS\Member::loggedIn()->language()->addToStack('or') . ' ', $countries ) ) ) );
		}
		
		/* Email */
		if ( $this->email )
		{
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_email', FALSE, array( 'sprintf' => array( $this->_cer( $this->email ), $this->email_unit ) ) );
		}
		
		/* IP Address */
		if ( $this->ip )
		{
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_ip', FALSE, array( 'sprintf' => array( $this->_cer( $this->ip ), $this->ip_unit ) ) );
		}
		
		/* Customer registration date */
		if ( $this->customer_reg )
		{
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_customer_reg', FALSE, array( 'sprintf' => array( $this->_gle( $this->customer_reg ), $this->customer_reg_unit ) ) );
		}
		
		/* Customer groups */
		if ( $this->customer_groups and $this->customer_groups != '*' )
		{
			$groups = array();
			foreach ( explode( ',', $this->customer_groups ) as $id )
			{
				try
				{
					$groups[] = \IPS\Member\Group::load( $id )->name;
				}
				catch ( \OutOfRangeException $e ) { }
			}
			
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_customer_groups', FALSE, array( 'sprintf' => array( implode( ' ' . \IPS\Member::loggedIn()->language()->addToStack('or') . ' ', $groups ) ) ) );
		}
		
		/* Custom fields */
		$customFields = $this->custom_fields ? json_decode( $this->custom_fields, TRUE ) : array();
		if ( $customFields )
		{
			foreach ( \IPS\core\ProfileFields\Field::roots( NULL, NULL ) as $field )
			{
				if ( isset( $customFields["field_member_{$field->id}"] ) )
				{
					if ( $condition = $this->_descriptionForCustom( $field, $customFields["field_member_{$field->id}"] ) )
					{
						$conditions[] = $condition;
					}
				}
			}
			foreach ( \IPS\nexus\Customer\CustomField::roots( NULL, NULL ) as $field )
			{
				if ( isset( $customFields["field_customer_{$field->id}"] ) )
				{
					if ( $condition = $this->_descriptionForCustom( $field, $customFields["field_customer_{$field->id}"] ) )
					{
						$conditions[] = $condition;
					}
				}
			}
		}
		
		/* Approved transactions */
		if ( $this->trans_okay )
		{
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_trans_okay', FALSE, array( 'sprintf' => array( $this->_gle( $this->trans_okay ), $this->trans_okay_unit ) ) );
		}
		
		/* Fraud Blocked transactions */
		if ( $this->trans_fraud )
		{
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_trans_fraud', FALSE, array( 'sprintf' => array( $this->_gle( $this->trans_fraud ), $this->trans_fraud_unit ) ) );
		}
		
		/* Refused /Refunded transactions */
		if ( $this->trans_fail )
		{
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_trans_fail', FALSE, array( 'sprintf' => array( $this->_gle( $this->trans_fail ), $this->trans_fail_unit ) ) );
		}
		
		/* Previously Spent */
		if ( $this->customer_spend )
		{
			$amounts = array();
			foreach ( json_decode( $this->customer_spend_unit, TRUE ) as $currency => $amount )
			{
				$amounts[] = (string) new \IPS\nexus\Money( $amount, $currency );
			}
			
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_customer_spend', FALSE, array( 'sprintf' => array( $this->_gle( $this->customer_spend ), implode( ' ' . \IPS\Member::loggedIn()->language()->addToStack('or') . ' ', $amounts ) ) ) );
		}
		
		/* Last successful transaction */
		if ( $this->last_okay_trans )
		{
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_last_okay_trans', FALSE, array( 'sprintf' => array( $this->_gle( $this->last_okay_trans ), $this->last_okay_trans_unit ) ) );
		}
		
		/* MaxMind */
		if ( \IPS\Settings::i()->maxmind_key )
		{
			/* Score */
			if ( $this->maxmind )
			{
				$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_maxmind', FALSE, array( 'sprintf' => array( $this->_gle( $this->maxmind ), $this->maxmind_unit ) ) );
			}
			
			/* Proxy score */
			if ( $this->f_maxmind_proxy )
			{
				$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_maxmind_proxy', FALSE, array( 'sprintf' => array( $this->_gle( $this->maxmind_proxy ), $this->maxmind_proxy_unit ) ) );
			}
			
			/* Other */
			foreach ( array( 'maxmind_address_match', 'maxmind_address_valid', 'maxmind_phone_match', 'maxmind_freeemail', 'maxmind_riskyemail' ) as $k )
			{
				if ( $this->$k )
				{
					$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( $this->$k == 1 ? "f_blurb_{$k}_y" : "f_blurb_{$k}_y" );
				}
			}
		}
		
		/* Action */
		$results[] = "&rarr; " . \IPS\Member::loggedIn()->language()->addToStack( 'f_action_' . $this->action );
		
		/* Return */
		return \IPS\Theme::i()->getTemplate( 'store' )->fraudRuleDesc( $conditions, $results, $warning );//implode( \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_join' ), $conditions ) . '<br>' . implode( \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_join' ), $results );
	}
	
	/**
	 * Get description for a custom field's value
	 *
	 * @param	\IPS\CustomField	$field	The field
	 * @param	mixed				$value	The value
	 * @return	string|null
	 */
	protected function _descriptionForCustom( $field, $value )
	{
		switch ( $field->type )
		{
			case 'Checkbox':
			case 'YesNo':
				if ( $value )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( $value == 1 ? 'f_blurb_custom_bool_y' : 'f_blurb_custom_bool_n', FALSE, array( 'sprintf' => array( $field->_title ) ) );
				}
				break;
			case 'CheckboxSet':
			case 'Radio':
			case 'Select':
				if ( $value and $value != '*' )
				{
					$options = array();
					$availableOptions = json_decode( $field->content, TRUE );
					foreach ( $value as $k )
					{
						if ( isset( $availableOptions[ $k ] ) )
						{
							$options[] = $availableOptions[ $k ];
						}
					}
					return \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_custom_discrete', FALSE, array( 'sprintf' => array( $field->_title, implode( ' ' . \IPS\Member::loggedIn()->language()->addToStack('or') . ' ', $options ) ) ) );
				}
				break;
			case 'Codemirror':
			case 'Color':
			case 'Editor':
			case 'Email':
			case 'Password':
			case 'Tel':
			case 'Text':
			case 'TextArea':
			case 'Url':
				if ( $value and $value[0] )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_custom_text', FALSE, array( 'sprintf' => array( $field->_title, $this->_cer( $value[0] ), $value[1] ) ) );
				}
				break;
			case 'Number':
			case 'Rating':
				if ( $value and $value[0] )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'f_blurb_custom_numeric', FALSE, array( 'sprintf' => array( $field->_title, $this->_gle( $value[0] ), $value[1] ) ) );
				}
				break;
		}
		
		return NULL;
	}
	
	/**
	 * Get greater than/less than/equal to language string
	 *
	 * @param	string	$value	g, l or e
	 * @return	string
	 */
	protected function _gle( $value )
	{
		switch ( $value )
		{
			case 'g':
				$lang = 'gt';
				break;
			case 'l':
				$lang = 'lt';
				break;
			case 'e':
				$lang = 'exactly';
				break;
		}
		return mb_strtolower( \IPS\Member::loggedIn()->language()->addToStack( $lang ) );
	}
	
	/**
	 * Get contains / is / matches regular expression language string
	 *
	 * @param	string	$value	g, l or e
	 * @return	string
	 */
	protected function _cer( $value )
	{
		switch ( $value )
		{
			case 'c':
				$lang = 'contains';
				break;
			case 'e':
				$lang = 'exactly';
				break;
			case 'r':
				$lang = 'ie_ct_regx';
				break;
		}
		return mb_strtolower( \IPS\Member::loggedIn()->language()->addToStack( $lang ) );
	}
	
	/**
	 * Get amount unit
	 *
	 * @return	array
	 */
	public function get_amount_unit()
	{
		return ( isset( $this->_data['amount_unit'] ) and $this->_data['amount_unit'] ) ? json_decode( $this->_data['amount_unit'], TRUE ) : array();
	}
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{		
		$paymentMethods = array();
		foreach ( \IPS\nexus\Gateway::roots() as $gateway )
		{
			$paymentMethods[ $gateway->id ] = $gateway->_title;
		}
		
		$countries = array();
		foreach ( \IPS\GeoLocation::$countries as $k => $v )
		{
			$countries[ $v ] = 'country-' . $v;
		}
				
		$yesNoEither = array( 0 => 'any_value', 1 => 'yes', -1 => 'no' );
		
		$form->addTab( 'fraud_rule_settings' );
		$form->add( new \IPS\Helpers\Form\Text( 'f_name', $this->name, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'f_action', $this->action ?: 'hold', TRUE, array( 'options' => array( 'okay' => 'f_action_okay', 'hold' => 'f_action_hold', 'fail' => 'f_action_fail' ) ) ) );
		
		$form->addTab( 'fraud_rule_transaction' );
		$form->add( $this->_combine( 'f_amount', 'IPS\nexus\Form\Money' ) );		
		$form->add( new \IPS\Helpers\Form\Node( 'f_products', ( !$this->products or $this->products === '*' ) ? 0 : explode( ',', $this->products ), FALSE, array( 'class' => 'IPS\nexus\Package', 'multiple' => TRUE, 'zeroVal' => 'any' ) ) );
		$form->add( new \IPS\Helpers\Form\Node( 'f_subscriptions', ( !$this->subscriptions or $this->subscriptions === '*' ) ? 0 : explode( ',', $this->subscriptions ), FALSE, array( 'class' => 'IPS\nexus\Subscription\Package', 'multiple' => TRUE, 'zeroVal' => 'any' ) ) );
		$form->add( new \IPS\Helpers\Form\Node( 'f_methods', ( !$this->methods or $this->methods === '*' ) ? 0 : explode( ',', $this->methods ), FALSE, array( 'class' => 'IPS\nexus\Gateway', 'multiple' => TRUE, 'zeroVal' => 'any' ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'f_coupon', $this->coupon, FALSE, array( 'options' => $yesNoEither ) ) );

		$form->addTab( 'fraud_rule_customer' );
		$form->addHeader( 'fraud_customer_account' );
		$form->add( $this->_combine( 'f_customer_reg', 'IPS\Helpers\Form\Number', array(), \IPS\Member::loggedIn()->language()->addToStack('f_customer_reg_suffix') ) );
		$groups = array();
		foreach ( \IPS\Member\Group::groups() as $group )
		{
			$groups[ $group->g_id ] = $group->name;
		}
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'f_customer_groups', ( !$this->customer_groups or $this->customer_groups === '*' ) ? '*' : explode( ',', $this->customer_groups ), FALSE, array( 'options' => $groups, 'multiple' => TRUE, 'class' => 'ipsField_long', 'unlimited' => '*', 'unlimitedLang' => 'any', 'impliedUnlimited' => TRUE ) ) );
		$form->add( $this->_combine( 'f_email', 'IPS\Helpers\Form\Text' ) );
		$form->add( $this->_combine( 'f_ip', 'IPS\Helpers\Form\Text' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'f_country', ( !$this->country or $this->country === '*' ) ? '*' : explode( ',', $this->country ), FALSE, array( 'options' => $countries, 'multiple' => TRUE, 'class' => 'ipsField_long', 'unlimited' => '*', 'unlimitedLang' => 'any' ) ) );
	
		$customFieldValues = $this->custom_fields ? json_decode( $this->custom_fields, TRUE ) : array();
		$memberFields = \IPS\core\ProfileFields\Field::roots( NULL, NULL, "pf_type NOT IN('Address','Date','Member','Poll','Upload')" );
		$customerFields = \IPS\nexus\Customer\CustomField::roots( NULL, NULL, "f_type NOT IN('Address','Date','Member','Poll','Upload')" );
		if ( \count( $memberFields ) or \count( $customerFields ) )
		{
			$form->addHeader( 'fraud_customer_fields' );
			foreach ( $memberFields as $field )
			{
				if ( $formField = $this->_formFieldForCustom( $field, "field_member_{$field->id}", isset( $customFieldValues["field_member_{$field->id}"] ) ? $customFieldValues["field_member_{$field->id}"] : NULL ) )
				{
					$form->add( $formField );
				}
			}
			foreach ( $customerFields as $field )
			{
				if ( $formField = $this->_formFieldForCustom( $field, "field_customer_{$field->id}", isset( $customFieldValues["field_customer_{$field->id}"] ) ? $customFieldValues["field_customer_{$field->id}"] : NULL ) )
				{
					$form->add( $formField );
				}
			}
		}
	
		$form->addHeader( 'fraud_customer_prevtrans' );
		$form->add( $this->_combine( 'f_trans_okay', 'IPS\Helpers\Form\Number' ) );
		$form->add( $this->_combine( 'f_trans_fraud', 'IPS\Helpers\Form\Number' ) );
		$form->add( $this->_combine( 'f_trans_fail', 'IPS\Helpers\Form\Number' ) );
		$form->add( $this->_combine( 'f_customer_spend', 'IPS\nexus\Form\Money' ) );
		$form->add( $this->_combine( 'f_last_okay_trans', 'IPS\Helpers\Form\Number', array(), \IPS\Member::loggedIn()->language()->addToStack('f_last_okay_trans_suffix') ) );
		
		$form->addTab( 'fraud_rule_maxmind' );
		if ( \IPS\Settings::i()->maxmind_key )
		{
			$form->add( $this->_combine( 'f_maxmind', 'IPS\Helpers\Form\Number', array( 'min' => 0, 'max' => 100, 'decimals' => 2 ) ) );
			$form->add( $this->_combine( 'f_maxmind_proxy', 'IPS\Helpers\Form\Number', array( 'min' => 0, 'max' => 4, 'decimals' => 2 ) ) );
			$form->add( new \IPS\Helpers\Form\Radio( 'f_maxmind_address_match', $this->maxmind_address_match, FALSE, array( 'options' => $yesNoEither ) ) );
			$form->add( new \IPS\Helpers\Form\Radio( 'f_maxmind_address_valid', $this->maxmind_address_valid, FALSE, array( 'options' => $yesNoEither ) ) );
			$form->add( new \IPS\Helpers\Form\Radio( 'f_maxmind_phone_match', $this->maxmind_phone_match, FALSE, array( 'options' => $yesNoEither, 'toggles' => array( 1 => array( 'f_maxmind_phone_match_warning' ), -1 => array( 'f_maxmind_phone_match_warning' ) ) ), NULL, NULL, NULL, 'f_maxmind_phone_match' ) );
			$form->add( new \IPS\Helpers\Form\Radio( 'f_maxmind_freeemail', $this->maxmind_freeemail, FALSE, array( 'options' => $yesNoEither ) ) );
			$form->add( new \IPS\Helpers\Form\Radio( 'f_maxmind_riskyemail', $this->maxmind_riskyemail, FALSE, array( 'options' => $yesNoEither ) ) );
		}
	}
	
	/**
	 * Create appropriate form field for a custom field
	 *
	 * @param	\IPS\CustomField	$field	The field
	 * @param	string				$name	The name to use
	 * @param	mixed				$value	The value
	 * @return	\IPS\Helpers\Form\FormAbstract
	 */
	protected function _formFieldForCustom( $field, $name, $value=NULL )
	{
		switch ( $field->type )
		{
			case 'Checkbox':
			case 'YesNo':
				$formField = new \IPS\Helpers\Form\Radio( $name, $value, FALSE, array( 'options' => array( 0 => 'any_value', 1 => 'yes', -1 => 'no' ) ) );
				break;
			case 'CheckboxSet':
			case 'Radio':
			case 'Select':
				$formField = new \IPS\Helpers\Form\Select( $name, ( \is_array( $value ) and $value ) ? $value : '*', FALSE, array( 'options' => json_decode( $field->content, TRUE ), 'multiple' => TRUE, 'class' => 'ipsField_long', 'unlimited' => '*', 'unlimitedLang' => 'any', 'returnLabels' => TRUE ) );
				break;
			case 'Codemirror':
			case 'Color':
			case 'Editor':
			case 'Email':
			case 'Password':
			case 'Tel':
			case 'Text':
			case 'TextArea':
			case 'Url':
				$formField = $this->_combine( $name, 'IPS\Helpers\Form\Text', array(), NULL, $value );
				break;
			case 'Number':
			case 'Rating':
				$formField = $this->_combine( $name, 'IPS\Helpers\Form\Number', array(), NULL, $value );
				break;
			default:
				return NULL;
		}
		
		$formField->label = $field->_title;
		
		return $formField;
	}
	
	/**
	 * Combine two fields
	 *
	 * @param	string		$name			Field name
	 * @param	bool		$field2Class	Classname for second field
	 * @param	array		$_options		Additional options for second field
	 * @param	string|null	$field2Suffix	Suffix
	 * @param	mixed		$valueOverride	Value to use
	 * @return	\IPS\Helpers\Form\Custom
	 */
	public function _combine( $name, $field2Class, $_options=array(), $field2Suffix=NULL, $valueOverride=NULL )
	{
		$field1Key = mb_substr( $name, 2 );
		$field2Key = $field1Key . '_unit';
		
		$validate = NULL;
		if ( \in_array( $field2Class, array( 'IPS\nexus\Form\Money', 'IPS\Helpers\Form\Number' ) ) )
		{
			$options = array(
				'options' => array(
					''	=> 'any_value',
					'g'	=> 'gt',
					'e'	=> 'exactly',
					'l'	=> 'lt'
				),
				'toggles' => array(
					'g'	=> array( $name . '_unit' ),
					'e'	=> array( $name . '_unit' ),
					'l'	=> array( $name . '_unit' ),
				)
			);
		}
		else
		{
			$options = array(
				'options' => array(
					''	=> 'any_value',
					'c'	=> 'contains',
					'e'	=> 'exactly',
					'r'	=> 'ie_ct_regx',
				),
				'toggles' => array(
					'c'	=> array( $name . '_unit' ),
					'e'	=> array( $name . '_unit' ),
					'r'	=> array( $name . '_unit' ),
				)
			);
			
			$validate = function( $v ) use ( $name )
			{
				$k = $name . '_type';
				if ( isset( \IPS\Request::i()->$k ) and \IPS\Request::i()->$k === 'r' )
				{
					if ( @preg_match( $v, null ) === FALSE )
					{
						throw new \DomainException( 'f_invalid_regex' );
					}
				}
			};
		}
		
		$field1 = new \IPS\Helpers\Form\Select( $name . '_type', \is_array( $valueOverride ) ? $valueOverride[0] : $this->$field1Key, FALSE, $options, NULL, NULL, NULL );
		$field2 = new $field2Class( $field1Key . '_unit', \is_array( $valueOverride ) ? $valueOverride[1] : $this->$field2Key, FALSE, $_options, $validate, NULL, $field2Suffix );
		
		return new \IPS\Helpers\Form\Custom( $name, array( $this->$field1Key, $this->$field2Key ), FALSE, array(
			'getHtml'	=> function() use ( $name, $field1, $field2 )
			{
				return \IPS\Theme::i()->getTemplate( 'forms', 'nexus', 'global' )->combined( $name, $field1, $field2 );
			},
			'formatValue'	=> function() use ( $field1, $field2 )
			{
				return array( $field1->value, $field2->value );
			},
			'validate'		=> function() use( $name, $field1, $field2 )
			{
				$field1->validate();
				$field2->validate();
			} 
		) );
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		foreach ( array( 'f_amount', 'f_email', 'f_trans_okay', 'f_trans_fraud', 'f_trans_fail', 'f_maxmind', 'f_maxmind_proxy', 'f_customer_reg', 'f_customer_spend', 'f_last_okay_trans', 'f_ip' ) as $k )
		{
			if ( isset( $values[ $k ] ) )
			{
				$values[ $k . '_unit' ] = $values[ $k ][1];
				$values[ $k ] = $values[ $k ][0];
			}
		}
		
		foreach ( array( 'f_methods', 'f_products', 'f_subscriptions' ) as $k )
		{
			if( isset( $values[ $k ] ) )
			{
				$values[ $k ] = \is_array( $values[ $k ] ) ? implode(',', array_keys( $values[ $k ] ) ) : '*';
			}
		}
		
		foreach ( array( 'f_country', 'f_customer_groups' ) as $k )
		{
			if( isset( $values[ $k ] ) )
			{
				$values[ $k ] = \is_array( $values[ $k ] ) ? implode(',', $values[ $k ] ) : '*';
			}
		}
		
		foreach ( array( 'f_amount_unit', 'f_customer_spend_unit' ) as $k )
		{
			if( isset( $values[ $k ] ) )
			{
				$amounts = array();
				foreach ( $values[ $k ] as $amount )
				{
					$amounts[ $amount->currency ] = $amount->amount;
				}
				$values[ $k ] = json_encode( $amounts );
			}
		}
		
		$customFields = array();
		foreach ( \IPS\core\ProfileFields\Field::roots( NULL, NULL, "pf_type NOT IN('Address','Date','Member','Poll','Upload')" ) as $field )
		{
			$customFields["field_member_{$field->id}"] = $values["field_member_{$field->id}"];
			unset( $values["field_member_{$field->id}"] );
		}
		foreach ( \IPS\nexus\Customer\CustomField::roots( NULL, NULL, "f_type NOT IN('Address','Date','Member','Poll','Upload')" ) as $field )
		{
			$customFields["field_customer_{$field->id}"] = $values["field_customer_{$field->id}"];
			unset( $values["field_customer_{$field->id}"] );
		}
		$values['f_custom_fields'] = json_encode( $customFields );
						
		return $values;
	}
	
	/** 
	 * Check if rule matches transaction
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	The transaction
	 * @return	bool
	 */
	public function matches( \IPS\nexus\Transaction $transaction )
	{		
		/* Amount */
		if ( $this->amount )
		{
			$amounts = $this->amount_unit;
			if ( !isset( $amounts[ $transaction->currency ] ) or !$this->_checkCondition( $transaction->amount->amount, $this->amount, $amounts[ $transaction->currency ] ) )
			{
				return FALSE;
			}
		}
		
		/* Methods */
		if ( $this->methods != '*' )
		{
			if ( !\in_array( $transaction->method->id, explode(',', $this->methods ) ) )
			{
				return FALSE;
			}
		}
		
		/* Products */
		if ( $this->products and $this->products != '*' )
		{
			$match = FALSE;
			foreach ( $transaction->invoice->items as $item )
			{
				if ( $item instanceof \IPS\nexus\extensions\nexus\Item\Package and \in_array( $item->id, explode( ',', $this->products ) ) )
				{
					$match = TRUE;
					break;
				}
			}
			
			if ( !$match )
			{
				return FALSE;
			}
		}

		/* Subscriptions */
		if ( $this->subscriptions and $this->subscriptions != '*' )
		{
			$match = FALSE;
			foreach ( $transaction->invoice->items as $item )
			{
				if ( $item instanceof \IPS\nexus\extensions\nexus\Item\Subscription and \in_array( $item->id, explode( ',', $this->subscriptions ) ) )
				{
					$match = TRUE;
					break;
				}
			}

			if ( !$match )
			{
				return FALSE;
			}
		}
		
		/* Coupon */
		if ( $this->coupon )
		{
			$couponUsed = FALSE;
			foreach ( $transaction->invoice->items as $item )
			{
				if ( $item instanceof \IPS\nexus\extensions\nexus\Item\CouponDiscount )
				{
					$couponUsed = TRUE;
					break;
				}
			}
			
			if ( $this->coupon == 1 and !$couponUsed )
			{
				return FALSE;
			}
			if ( $this->coupon == -1 and $couponUsed )
			{
				return FALSE;
			}
		}
		
		/* Country */
		if ( $this->country != '*' and $transaction->invoice->billaddress )
		{
			if ( !\in_array( $transaction->invoice->billaddress->country, explode(',', $this->country ) ) )
			{
				return FALSE;
			}
		}
		
		/* Email */
		if ( $this->email )
		{
			if ( !$this->_checkCondition( $transaction->member->email, $this->email, $this->email_unit ) )
			{
				return FALSE;
			}
		}
		
		/* IP Address */
		if ( $this->ip )
		{
			if ( !$this->_checkCondition( $transaction->ip, $this->ip, $this->ip_unit ) )
			{
				return FALSE;
			}
		}
		
		/* Customer registration date */
		if ( $this->customer_reg )
		{
			$daysAgo = ( time() - $transaction->member->joined->getTimestamp() ) / 86400;
			if ( !$this->_checkCondition( $daysAgo, $this->customer_reg, $this->customer_reg_unit ) )
			{
				return FALSE;
			}
		}
		
		/* Customer groups */
		if ( $this->customer_groups and $this->customer_groups != '*' )
		{
			if ( !$transaction->member->inGroup( explode( ',', $this->customer_groups ) ) )
			{
				return FALSE;
			}
		}
		
		/* Custom fields */
		$customFields = $this->custom_fields ? json_decode( $this->custom_fields, TRUE ) : array();
		if ( $customFields )
		{
			$memberValues = array();
			try
			{
				$memberValues = \IPS\Db::i()->select( '*', 'core_pfields_content', array( 'member_id = ?', \intval( $transaction->member->member_id ) ) )->first();
			}
			catch ( \UnderflowException $e ) {}
			foreach ( \IPS\core\ProfileFields\Field::roots( NULL, NULL ) as $field )
			{
				if ( isset( $customFields["field_member_{$field->id}"] ) )
				{
					if ( !$this->_customFieldMatches( $field, $customFields["field_member_{$field->id}"], isset( $memberValues["field_{$field->id}"] ) ? $memberValues["field_{$field->id}"] : NULL ) )
					{
						return FALSE;
					}
				}
			}
			
			foreach ( \IPS\nexus\Customer\CustomField::roots( NULL, NULL ) as $field )
			{
				if ( isset( $customFields["field_customer_{$field->id}"] ) )
				{
					$k = $field->column;
					if ( !$this->_customFieldMatches( $field, $customFields["field_customer_{$field->id}"], isset( $transaction->member->$k ) ? $transaction->member->$k : NULL ) )
					{
						return FALSE;
					}
				}
			}
		}
		
		/* Approved transactions */
		if ( $this->trans_okay )
		{
			if ( !$this->_checkCondition( \IPS\Db::i()->select( 'COUNT(*)', 'nexus_transactions', array( 't_member=? AND t_status=?', $transaction->member->member_id, \IPS\nexus\Transaction::STATUS_PAID ) )->first(), $this->trans_okay, $this->trans_okay_unit ) )
			{
				return FALSE;
			}
		}
		
		/* Fraud Blocked transactions */
		if ( $this->trans_fraud )
		{
			if ( !$this->_checkCondition( \IPS\Db::i()->select( 'COUNT(*)', 'nexus_transactions', array( 't_member=? AND t_status=? AND t_fraud_blocked<>0', $transaction->member->member_id, \IPS\nexus\Transaction::STATUS_REFUSED ) )->first(), $this->trans_fraud, $this->trans_fraud_unit ) )
			{
				return FALSE;
			}
		}
		
		/* Refused/Refunded transactions */
		if ( $this->trans_fail )
		{
			if ( !$this->_checkCondition( \IPS\Db::i()->select( 'COUNT(*)', 'nexus_transactions', array( 't_member=? AND ( t_status=? OR t_status=? )', $transaction->member->member_id, \IPS\nexus\Transaction::STATUS_REFUSED, \IPS\nexus\Transaction::STATUS_REFUNDED ) )->first(), $this->trans_fail, $this->trans_fail_unit ) )
			{
				return FALSE;
			}
		}
		
		/* Previously Spent */
		if ( $this->customer_spend )
		{
			$match = FALSE;
			$amounts = json_decode( $this->customer_spend_unit, TRUE );
			
			$spent = array();
			foreach ( \IPS\Db::i()->select( 't_currency, ( SUM(t_amount)-SUM(t_partial_refund) ) AS amount', 'nexus_transactions', array( 't_member=? AND ( t_status=? OR t_status=? ) AND t_method>0', $transaction->member->member_id, \IPS\nexus\Transaction::STATUS_PAID, \IPS\nexus\Transaction::STATUS_PART_REFUNDED ), NULL, NULL, 't_currency' ) as $amount )
			{
				$spent[ $amount['t_currency'] ] = $amount['amount'];
			}
			
			foreach ( $amounts as $currency => $requirement )
			{
				$value = isset( $spent[ $currency ] ) ? $spent[ $currency ] : 0;
								
				if ( $this->_checkCondition( \floatval( $value ), $this->customer_spend, \floatval( $requirement ) ) )
				{
					$match = TRUE;
					break;
				}
			}
			
			if ( !$match )
			{
				return FALSE;
			}
		}
		
		/* Last successful transaction */
		if ( $this->last_okay_trans )
		{
			try
			{
				$daysAgo = ( time() - \IPS\Db::i()->select( 't_date', 'nexus_transactions', array( 't_member=? AND t_status=?', $transaction->member->member_id, \IPS\nexus\Transaction::STATUS_PAID ), 't_date DESC' )->first() ) / 86400;
				if ( !$this->_checkCondition( $daysAgo, $this->last_okay_trans, $this->last_okay_trans_unit ) )
				{
					return FALSE;
				}
			}
			catch ( \UnderflowException $e )
			{
				if ( $this->last_okay_trans == 'g' or $this->last_okay_trans == 'e' )
				{
					return FALSE;
				}
			}
		}
		
		/* MaxMind */
		if ( \IPS\Settings::i()->maxmind_key and ( $this->maxmind or $this->maxmind_proxy or $this->maxmind_address_match or $this->maxmind_address_valid or $this->maxmind_phone_match or $this->maxmind_freeemail or $this->maxmind_riskyemail ) )
		{
			/* If there was an error, we cannot check any of these */
			$maxMind = $transaction->fraud;
			if ( $maxMind->error() )
			{
				return FALSE;
			}
			
			/* Score */
			if ( $this->maxmind )
			{				
				if ( !$this->_checkCondition( $maxMind->riskScore !== NULL ? $maxMind->riskScore : round( $maxMind->score * 10 ), $this->maxmind, $this->maxmind_unit ) )
				{
					return FALSE;
				}
			}
			
			/* Proxy score */
			if ( $this->maxmind_proxy )
			{
				if ( !$this->_checkCondition( $maxMind->proxyScore, $this->maxmind_proxy, $this->maxmind_proxy_unit ) )
				{
					return FALSE;
				}
			}
			
			/* Address match */
			if ( $this->maxmind_address_match )
			{
				if ( !$this->_checkCondition( $maxMind->countryMatch, 'mm', $this->maxmind_address_match ) )
				{
					return FALSE;
				}
			}
			
			/* Address valid */
			if ( $this->maxmind_address_valid )
			{
				if ( !$this->_checkCondition( $maxMind->cityPostalMatch, 'mm', $this->maxmind_address_valid ) )
				{
					return FALSE;
				}
			}
			
			/* Phone match */
			if ( $this->maxmind_phone_match )
			{
				if ( !$this->_checkCondition( $maxMind->custPhoneInBillingLoc, 'mm', $this->maxmind_phone_match ) )
				{
					return FALSE;
				}
			}
			
			/* Free email */
			if ( $this->maxmind_freeemail )
			{
				if ( !$this->_checkCondition( $maxMind->freeMail, 'mm', $this->maxmind_freeemail ) )
				{
					return FALSE;
				}
			}
			
			/* High-risk email */
			if ( $this->maxmind_riskyemail )
			{
				if ( !$this->_checkCondition( $maxMind->carderEmail, 'mm', $this->maxmind_riskyemail ) )
				{
					return FALSE;
				}
			}
		}
		
		/* Still here? Return true */
		return TRUE;		
	}
	
	/**
	 * Determine if a custom field matches a set value
	 *
	 * @param	\IPS\CustomField	$field			The field
	 * @param	mixed				$valueToCheck	The value to compare against
	 * @param	mixed				$memberValue	The value the member has provided
	 * @return	bool
	 */
	protected function _customFieldMatches( $field, $valueToCheck, $memberValue )
	{
		switch ( $field->type )
		{
			case 'Checkbox':
			case 'YesNo':
				if ( $valueToCheck != 0 )
				{
					if ( $memberValue === NULL )
					{
						return FALSE;
					}
					if ( $valueToCheck == 1 and !$memberValue )
					{
						return FALSE;
					}
					if ( $valueToCheck == -1 and $memberValue )
					{
						return FALSE;
					}
				}
				break;
				
			case 'CheckboxSet':
			case 'Radio':
			case 'Select':
				if ( $valueToCheck and $valueToCheck != '*' )
				{
					if ( $memberValue === NULL )
					{
						return FALSE;
					}
					return \in_array( $memberValue, $valueToCheck );
				}
				break;
				
			case 'Codemirror':
			case 'Color':
			case 'Editor':
			case 'Email':
			case 'Password':
			case 'Tel':
			case 'Text':
			case 'TextArea':
			case 'Url':
			case 'Number':
			case 'Rating':
				if ( $valueToCheck[0] )
				{
					if ( $memberValue === NULL )
					{
						return FALSE;
					}
					return $this->_checkCondition( $memberValue, $valueToCheck[0], $valueToCheck[1] );
				}
		}
		
		return TRUE;
	}
	
	/**
	 * Check condition
	 *
	 * @param	mixed	$a			First parameter
	 * @param	string	$operator	Operator (g = greater than, e = equal to, l = less than, c = contains, mm = MaxMind)
	 * @param	mixed	$b			Second parameter
	 * @return	bool
	 */
	protected function _checkCondition( $a, $operator, $b )
	{
		if ( $a instanceof \IPS\Math\Number and !( $b instanceof \IPS\Math\Number ) )
		{
			$b = new \IPS\Math\Number( "{$b}" );
		}
		if ( !( $a instanceof \IPS\Math\Number ) and $b instanceof \IPS\Math\Number )
		{
			$a = new \IPS\Math\Number( "{$a}" );
		}
		
		switch ( $operator )
		{
			case 'g':
				if ( $a instanceof \IPS\Math\Number )
				{
					return $a->compare( $b ) === 1;
				}
				else
				{
					return $a > $b;
				}
			case 'e':
				if ( $a instanceof \IPS\Math\Number )
				{
					return $a->compare( $b ) === 0;
				}
				else
				{
					return $a == $b;
				}
			case 'l':
				if ( $a instanceof \IPS\Math\Number )
				{
					return $a->compare( $b ) === -1;
				}
				else
				{
					return $a < $b;
				}
			case 'c':
				return mb_strpos( $a, $b ) !== FALSE;
			case 'r':
				return preg_match( $b, $a );
			case 'mm':
				$a = mb_strtolower( $a );
				if ( $a === 'yes' )
				{
					return $b == 1;
				}
				elseif ( $a === 'no' )
				{
					return $b == -1;
				}
				else
				{
					return FALSE;
				}
				break;
		}
		return FALSE;
	}
	
	/** 
	 * Check if one rule is a super-set of another
	 *
	 * @param	\IPS\nexus\Fraud\Rule	$other	Other rule
	 * @return	bool
	 */
	public function isSubsetOf( \IPS\nexus\Fraud\Rule $other )
	{
		/* Amount */
		if ( $this->amount != $other->amount )
		{
			return FALSE;
		}
		elseif ( $this->amount xor $other->amount )
		{
			if ( !$this->amount )
			{
				return FALSE;
			}
		}
		elseif ( $this->amount and $other->amount )
		{
			$otherAmounts = $other->amount_unit;
			foreach ( $this->amount_unit as $currency => $amount )
			{
				if ( $this->amount == 'e' )
				{
					if ( $amount != $otherAmounts[ $currency ] )
					{
						return FALSE;
					}
				}
				elseif ( $this->amount == 'g' )
				{
					if ( $amount < $otherAmounts[ $currency ] )
					{
						return FALSE;
					}
				}
				elseif ( $this->amount == 'l' )
				{
					if ( $amount > $otherAmounts[ $currency ] )
					{
						return FALSE;
					}
				}
			}
		}
		
		/* Diffs */
		foreach ( array(
			'methods'			=> array_keys( \IPS\nexus\Gateway::roots() ),
			'products'			=> iterator_to_array( \IPS\Db::i()->select( 'p_id', 'nexus_packages' ) ),
			'subscriptions'		=> iterator_to_array( \IPS\Db::i()->select( 'sp_id', 'nexus_member_subscription_packages' ) ),
			'country'			=> array_values( \IPS\GeoLocation::$countries ),
			'customer_groups'	=> array_keys( \IPS\Member\Group::groups() )
		) as $k => $allValues )
		{
			if ( !$this->_subsetCheckDiff( $this->$k, $other->$k, $allValues ) )
			{
				return FALSE;
			}
		}
						
		/* Yes/No */
		foreach ( array( 'coupon', 'maxmind_address_match', 'maxmind_address_valid', 'maxmind_phone_match', 'maxmind_freeemail', 'maxmind_riskyemail' ) as $k )
		{
			if ( !$this->_subsetCheckBool( $this->$k, $other->$k ) )
			{
				return FALSE;
			}
		}		
		
		/* Textual */
		foreach ( array( 'email', 'ip' ) as $k )
		{
			$unitK = "{$k}_unit";
			if ( !$this->_subsetCheckText( $this->$k, $this->$unitK, $other->$k, $other->$unitK ) )
			{
				return FALSE;
			}
		}
		
		/* Numeric */
		foreach ( array( 'customer_reg', 'trans_okay', 'trans_fraud', 'trans_fail', 'customer_spend', 'last_okay_trans', 'maxmind', 'maxmind_proxy' ) as $k )
		{
			$unitK = "{$k}_unit";
			if ( !$this->_subsetCheckNumeric( $this->$k, $this->$unitK, $other->$k, $other->$unitK ) )
			{
				return FALSE;
			}
		}
		
		/* Custom fields */
		$thisCustomFields = $this->custom_fields ? json_decode( $this->custom_fields, TRUE ) : array();
		$otherCustomFields = $other->custom_fields ? json_decode( $other->custom_fields, TRUE ) : array();
		if ( $thisCustomFields or $otherCustomFields )
		{
			foreach ( \IPS\core\ProfileFields\Field::roots( NULL, NULL ) as $field )
			{
				if ( isset( $thisCustomFields["field_member_{$field->id}"] ) or isset( $otherCustomFields["field_member_{$field->id}"] ) )
				{
					if ( !$this->_subsetCheckCustom( $field, isset( $thisCustomFields["field_member_{$field->id}"] ) ? $thisCustomFields["field_member_{$field->id}"] : NULL, isset( $otherCustomFields["field_member_{$field->id}"] ) ? $otherCustomFields["field_member_{$field->id}"] : NULL ) )
					{
						return FALSE;
					}
				}
			}
			foreach ( \IPS\nexus\Customer\CustomField::roots( NULL, NULL ) as $field )
			{
				if ( isset( $thisCustomFields["field_customer_{$field->id}"] ) or isset( $otherCustomFields["field_customer_{$field->id}"] ) )
				{
					if ( !$this->_subsetCheckCustom( $field, isset( $thisCustomFields["field_customer_{$field->id}"] ) ? $thisCustomFields["field_customer_{$field->id}"] : NULL, isset( $otherCustomFields["field_customer_{$field->id}"] ) ? $otherCustomFields["field_customer_{$field->id}"] : NULL ) )
					{
						return FALSE;
					}
				}
			}
		}
		
		/* Still here - return TRUE */
		return TRUE;
	}
	
	/** 
	 * Check if one rule is a super-set of another: Custom field
	 *
	 * @param	\IPS\CustomField	$field	The field
	 * @param	mixed				$value1	The value from one fraud rule
	 * @param	mixed				$value2	The value from a second fraud rule
	 * @return	bool
	 */
	protected function _subsetCheckCustom( $field, $value1, $value2 )
	{
		switch ( $field->type )
		{
			case 'Checkbox':
			case 'YesNo':
				return $this->_subsetCheckBool( $value1, $value2 );

			case 'CheckboxSet':
			case 'Radio':
			case 'Select':
				return $this->_subsetCheckDiff( $value1, $value2, json_decode( $field->content, TRUE ) );
				
			case 'Codemirror':
			case 'Color':
			case 'Editor':
			case 'Email':
			case 'Password':
			case 'Tel':
			case 'Text':
			case 'TextArea':
			case 'Url':
				return $this->_subsetCheckText(
					isset( $value1[0] ) ? $value1[0] : NULL,
					isset( $value1[1] ) ? $value1[1] : NULL,
					isset( $value2[0] ) ? $value2[0] : NULL,
					isset( $value2[1] ) ? $value2[1] : NULL
				);
				
			case 'Number':
			case 'Rating':
				return $this->_subsetCheckNumeric(
					isset( $value1[0] ) ? $value1[0] : NULL,
					isset( $value1[1] ) ? $value1[1] : NULL,
					isset( $value2[0] ) ? $value2[0] : NULL,
					isset( $value2[1] ) ? $value2[1] : NULL
				);
		}
	}
	
	/** 
	 * Check if one rule is a super-set of another: Boolean
	 *
	 * @param	mixed		$value1	The value from one fraud rule
	 * @param	mixed		$value2	The value from a second fraud rule
	 * @return	bool
	 */
	protected function _subsetCheckBool( $value1, $value2 )
	{
		return $value1 == $value2;
	}
	
	/** 
	 * Check if one rule is a super-set of another: Select/Radio/CheckboxSet
	 *
	 * @param	mixed		$value1		The value from one fraud rule
	 * @param	mixed		$value2		The value from a second fraud rule
	 * @param	array		$allValues	The values that * indicates
	 * @return	bool
	 */
	protected function _subsetCheckDiff( $value1, $value2, $allValues )
	{
		if ( ( $value1 and $value1 != '*' ) or ( $value2 and $value2 != '*' ) )
		{
			if ( !$value1 or $value1 === '*' )
			{
				$thisValues = $allValues;
			}
			else
			{
				$thisValues = \is_array( $value1 ) ? $value1 : explode( ',', $value1 );
			}
			
			if ( !$value2 or $value2 === '*' )
			{
				$otherValues = $allValues;
			}
			else
			{
				$otherValues = \is_array( $value2 ) ? $value2 : explode( ',', $value2 );
			}
			
			$diff = array_diff( $thisValues, $otherValues );
			
			if ( !empty( $diff ) )
			{
				return FALSE;
			}
		}
		
		return TRUE;
	}
	
	/** 
	 * Check if one rule is a super-set of another: Text field
	 *
	 * @param	mixed		$value1Type		The type of check (e/c/r/NULL) from one fraud rule
	 * @param	mixed		$value1Value	The text value from one fraud rule
	 * @param	mixed		$value2Type		The type of check (e/c/r/NULL) from a second fraud rule
	 * @param	mixed		$value2Value	The text value from a second fraud rule
	 * @return	bool
	 */
	protected function _subsetCheckText( $value1Type, $value1Value, $value2Type, $value2Value )
	{
		if ( $value1Type != $value2Type )
		{
			return FALSE;
		}
		elseif ( $value1Type xor $value2Type )
		{
			if ( !$value1Type )
			{
				return FALSE;
			}
		}
		elseif ( $value1Type and $value2Type )
		{
			if ( $value1Type == 'e' or $value1Type == 'r' )
			{
				if ( $value1Value != $value2Value )
				{
					return FALSE;
				}
			}
			elseif ( $value1Type == 'c' )
			{
				if ( mb_strpos( $value2Value, $value1Value ) !== FALSE )
				{
					return FALSE;
				}
			}
		}
		
		return TRUE;
	}
	
	/** 
	 * Check if one rule is a super-set of another: Numeric field
	 *
	 * @param	mixed		$value1Type		The type of check (g/l/e/NULL) from one fraud rule
	 * @param	mixed		$value1Value	The text value from one fraud rule
	 * @param	mixed		$value2Type		The type of check (g/l/e/NULL) from a second fraud rule
	 * @param	mixed		$value2Value	The text value from a second fraud rule
	 * @return	bool
	 */
	protected function _subsetCheckNumeric( $value1Type, $value1Value, $value2Type, $value2Value )
	{
		if ( $value1Type != $value2Type )
		{
			return FALSE;
		}
		elseif ( $value1Type xor $value2Type )
		{
			if ( !$value1Type )
			{
				return FALSE;
			}
		}
		elseif ( $value1Type and $value2Type )
		{
			if ( $value1Type == 'e' )
			{
				if ( $value1Value != $value2Value )
				{
					return FALSE;
				}
			}
			elseif ( $value1Type == 'g' )
			{
				if ( $value1Value < $value2Value )
				{
					return FALSE;
				}
			}
			elseif ( $value1Type == 'l' )
			{
				if ( $value1Value > $value2Value )
				{
					return FALSE;
				}
			}
		}
		
		return TRUE;
	}
}