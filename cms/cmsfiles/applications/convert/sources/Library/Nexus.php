<?php

/**
 * @brief		Converter Library Nexus Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		6 May 2016
 */

namespace IPS\convert\Library;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Invision Commerce (Nexus) Converter
 * @note	We must extend the Core Library here so we can access methods like convertAttachment, convertFollow, etc
 */
class _Nexus extends \IPS\convert\Library
{
	/**
	 * @brief	Application
	 */
	public $app = 'nexus';

	/**
	 * Returns an array of items that we can convert, including the amount of rows stored in the Community Suite as well as the recommend value of rows to convert per cycle
	 *
	 * @param	bool	$rowCounts		enable row counts
	 * @return	array
	 */
	public function menuRows( $rowCounts=FALSE )
	{
		$return		= array();
		$extraRows 	= $this->software->extraMenuRows();

		foreach( $this->getConvertableItems() as $k => $v )
		{
			switch( $k )
			{
				case 'convertNexusCustomerFields':
					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_customer_fields',
						'step_method'	=> 'convertNexusCustomerFields',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_customer_fields' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array(),
						'link_type'		=> 'nexus_customer_fields'
					);
					break;
				
				case 'convertNexusCustomers':
					$dependencies = array();
					if ( \in_array( 'convertNexusCustomerFields', $this->getConvertableItems() ) )
					{
						$dependencies[] = 'convertNexusCustomerFields';
					}
					
					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_customers',
						'step_method'	=> 'convertNexusCustomers',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_customers' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> $dependencies,
						'link_type'		=> 'nexus_customers',
					);
					break;
				
				case 'convertNexusCustomerAddresses':
					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_customer_addresses',
						'step_method'	=> 'convertNexusCustomerAddresses',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_customer_addresses' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array( 'convertNexusCustomers' ),
						'link_type'		=> 'nexus_customer_addresses',
					);
					break;
				
				case 'convertNexusCustomerHistory':
					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_customer_history',
						'step_method'	=> 'convertNexusCustomerHistory',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'core_member_history', array( 'log_app=?', 'nexus' ) ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array( 'convertNexusCustomers' ),
						'link_type'		=> 'nexus_customer_history',
					);
					break;
				
				case 'convertNexusAlternateContacts':
					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_alternate_contacts',
						'step_method'	=> 'convertNexusAlternateContacts',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_alternate_contacts' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array( 'convertNexusCustomers' ),
						'link_type'		=> 'nexus_alternate_contacts'
					);
					break;
				
				case 'convertNexusNotes':
					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_notes',
						'step_method'	=> 'convertNexusNotes',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_notes' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array( 'convertNexusCustomers' ),
						'linK_type'		=> 'nexus_notes'
					);
					break;

				case 'convertNexusPaymentMethods':
					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_payment_methods',
						'step_method'	=> 'convertNexusPaymentMethods',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_paymethods' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array(),
						'link_type'		=> 'nexus_payment_methods'
					);
					break;
				
				case 'convertNexusPackageGroups':
					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_package_groups',
						'step_method'	=> 'convertNexusPackageGroups',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_package_groups' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array(),
						'link_type'		=> 'nexus_package_groups'
					);
					break;
				
				case 'convertNexusPackageFields':
					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_package_fields',
						'step_method'	=> 'convertNexusPackageFields',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_package_fields' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array(),
						'link_type'		=> 'nexus_package_fields'
					);
					break;
				
				case 'convertNexusPackages':
					$dependencies = array();
					foreach( array( 'convertNexusPackageGroups', 'convertNexusPackageFields' ) as $dependency )
					{
						$canConvert = $this->getConvertableItems();
						if ( isset( $canConvert[ $dependency ] ) )
						{
							$dependencies[] = $dependency;
						}
					}
					
					$return[ $k ] = array(
						'step_title'		=> 'convert_nexus_packages',
						'step_method'		=> 'convertNexusPackages',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_packages' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 100,
						'dependencies'		=> $dependencies,
						'link_type'			=> 'nexus_packages',
						'requires_rebuild'	=> TRUE
					);
					break;
				
				case 'convertNexusReviews':
					$return[ $k ] = array(
						'step_title'		=> 'convert_nexus_reviews',
						'step_method'		=> 'convertNexusReviews',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_reviews' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertNexusPackages' ),
						'link_type'			=> 'nexus_reviews',
						'requires_rebuild'	=> TRUE
					);
					break;
				
				case 'convertNexusCoupons':
					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_coupons',
						'step_method'	=> 'convertNexusCoupons',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_coupons' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array( 'convertNexusPackages' ),
						'link_type'		=> 'nexus_coupons'
					);
					break;

				case 'convertNexusSubscriptionPackages':
					$return[ $k ] = [
						'step_title'	=> 'convert_nexus_subscription_packages',
						'step_method'	=> 'convertNexusSubscriptionPackages',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_member_subscription_packages' ),
						'source_rows'	=> [ 'table' => $v['table'], 'where' => $v['where'] ],
						'per_cycle'		=> 200,
						'dependencies'	=> [],
						'link_type'		=> 'nexus_subscription_package'
					];
					break;
				
				case 'convertNexusInvoices':
					$dependencies = array();
					foreach( array( 'convertNexusCustomers', 'convertNexusPackages', 'convertNexusSubscriptionPackages' ) as $dependency )
					{
						$canConvert = $this->getConvertableItems();
						if ( isset( $canConvert[ $dependency ] ) )
						{
							$dependencies[] = $dependency;
						}
					}

					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_invoices',
						'step_method'	=> 'convertNexusInvoices',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_invoices' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> $dependencies,
						'link_type'		=> 'nexus_invoices',
					);
					break;
				
				case 'convertNexusTransactions':

					$dependencies = array();
					foreach( array( 'convertNexusCustomers', 'convertNexusInvoices', 'convertNexusPaymentMethods' ) as $dependency )
					{
						$canConvert = $this->getConvertableItems();
						if ( isset( $canConvert[ $dependency ] ) )
						{
							$dependencies[] = $dependency;
						}
					}

					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_transactions',
						'step_method'	=> 'convertNexusTransactions',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_transactions' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> $dependencies,
						'link_type'		=> 'nexus_transactions',
					);
					break;
				
				case 'convertNexusPurchases':
					$dependencies = array();
					foreach( array( 'convertNexusCustomers', 'convertNexusPackages', 'convertNexusInvoices', 'convertNexusSubscriptionPackages' ) as $dependency )
					{
						$canConvert = $this->getConvertableItems();
						if ( isset( $canConvert[ $dependency ] ) )
						{
							$dependencies[] = $dependency;
						}
					}

					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_purchases',
						'step_method'	=> 'convertNexusPurchases',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> $dependencies,
						'link_type'		=> 'nexus_purchases'
					);
					break;
				
				case 'convertNexusFraudRules':
					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_fraud_rules',
						'step_method'	=> 'convertNexusFraudRules',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_fraud_rules' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array(),
						'link_type'		=> 'nexus_fraud_rules',
					);
					break;
				
				case 'convertNexusSupportDepartments':
					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_support_departments',
						'step_method'	=> 'convertNexusSupportDepartments',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_departments' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array( 'convertNexusPackages' ),
					);
					break;
				
				case 'convertNexusSupportFields':
					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_support_fields',
						'step_method'	=> 'convertNexusSupportFields',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_fields' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array( 'convertNexusSupportDepartments' ),
						'link_type'		=> 'nexus_support_fields',
					);
					break;
				
				case 'convertNexusSupportStatuses':
					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_support_statuses',
						'step_method'	=> 'convertNexusSupportStatuses',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_statuses' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array(),
						'link_type'		=> 'nexus_support_statuses',
					);
					break;
				
				case 'convertNexusSupportSeverities':
					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_support_severities',
						'step_method'	=> 'convertNexusSupportSeverities',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_severities' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array( 'convertNexusSupportDepartments' ),
						'link_type'		=> 'nexus_support_severities'
					);
					break;
				
				case 'convertNexusSupportStockActions':
					$return[ $k ] = array(
						'step_title'	=> 'convert_nexus_support_stock_actions',
						'step_method'	=> 'convertNexusSupportStockActions',
						'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_stock_actions' ),
						'source_rows'	=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'		=> 200,
						'dependencies'	=> array( 'convertNexusSupportDepartments' ),
						'link_type'		=> 'nexus_support_stock_actions'
					);
					break;
				
				case 'convertNexusSupportRequests':
					$return[ $k ] = array(
						'step_title'		=> 'convert_nexus_support_requests',
						'step_method'		=> 'convertNexusSupportRequests',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_requests' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertNexusCustomers', 'convertNexusSupportDepartments', 'convertNexusSupportFields' ),
						'link_type'			=> 'nexus_support_requests',
						'requires_rebuild'	=> TRUE
					);
					break;
				
				case 'convertNexusSupportReplies':
					$return[ $k ] = array(
						'step_title'		=> 'convert_nexus_support_replies',
						'step_method'		=> 'convertNexusSupportReplies',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_replies' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertNexusSupportRequests' ),
						'link_type'			=> 'nexus_support_replies',
						'requires_rebuild'	=> TRUE
					);
					break;
			}

			/* Append any extra steps immediately to retain ordering */
			if( isset( $v['extra_steps'] ) )
			{
				foreach( $v['extra_steps'] as $extra )
				{
					$return[ $extra ] = $extraRows[ $extra ];
				}
			}
		}

		/* Run the queries if we want row counts */
		if( $rowCounts )
		{
			$return = $this->getDatabaseRowCounts( $return );
		}

		return $return;
	}
	
	/**
	 * Returns an array of tables that need to be truncated when Empty Local Data is used
	 *
	 * @param	string	$method	Method to truncate
	 * @return	array
	 */
	protected function truncate( $method )
	{
		$return		= array();
		$classname	= \get_class( $this->software );

		if( $classname::canConvert() === NULL )
		{
			return array();
		}
		
		foreach( $classname::canConvert() as $k => $v )
		{
			switch( $k )
			{
				case 'convertNexusCustomerFields':
					$return['convertNexusCustomerFields'] = array( 'nexus_customer_fields' => NULL );
					break;
				
				case 'convertNexusCustomers':
					$return['convertNexusCustomers'] = array( 'nexus_customers' => array( "member_id<>?", \IPS\Member::loggedIn()->member_id ) );
					break;
				
				case 'convertNexusCustomerAddresses':
					$return['convertNexusCustomerAddresses'] = array( 'nexus_customer_addresses' => NULL );
					break;
				
				case 'convertNexusCustomerHistory':
					$return['convertNexusCustomerHistory'] = array( 'core_member_history' => array( 'log_app=?', 'nexus' ) );
					break;
				
				case 'convertNexusAlternateContacts':
					$return['convertNexusAlternateContacts'] = array( 'nexus_alternate_contacts' => NULL );
					break;
				
				case 'convertNexusNotes':
					$return['convertNexusNotes'] = array( 'nexus_notes' => NULL );
					break;
				
				case 'convertNexusPackageGroups':
					$return['convertNexusPackageGroups'] = array( 'nexus_package_groups' => NULL );
					break;
				
				case 'convertNexusPackageFields':
					$return['convertNexusPackageFields'] = array( 'nexus_package_fields' => NULL );
					break;
				
				case 'convertNexusPackages':
					$return['convertNexusPackages'] = array(
						'nexus_packages'			=> NULL,
						'nexus_package_images'		=> NULL,
						'nexus_package_base_prices'	=> NULL,
						'nexus_packages_ads'		=> NULL,
						'nexus_packages_hosting'	=> NULL,
						'nexus_packages_products'	=> NULL,
					);
					break;

				case 'convertNexusSubscriptionPackages':
					$return['convertNexusSubscriptionPackages'] = [ 'nexus_member_subscription_packages' => NULL ];
					break;

				case 'convertNexusPaymentMethods':
					$return['convertNexusPaymentMethods'] = array( 'nexus_paymethods' => NULL );
					break;

				case 'convertNexusReviews':
					$return['convertNexusReviews'] = array( 'nexus_reviews' => NULL );
					break;
				
				case 'convertNexusCoupons':
					$return['convertNexusCoupons'] = array( 'nexus_coupons' => NULL );
					break;
				
				case 'convertNexusInvoices':
					$return['convertNexusInvoices'] = array( 'nexus_invoices' => NULL );
					break;
				
				case 'convertNexusTransactions':
					$return['convertNexusTransactions'] = array( 'nexus_transactions' => NULL );
					break;
				
				case 'convertNexusPurchases':
					$return['convertNexusPurchases'] = array( 'nexus_purchases' => NULL );
					break;
				
				case 'convertNexusFraudRules':
					$return['convertNexusFraudRules'] = array( 'nexus_fraud_rules' => NULL );
					break;
				
				case 'convertNexusSupportDepartments':
					$return['convertNexusSupportDepartments'] = array( 'nexus_support_departments' => NULL );
					break;
				
				case 'convertNexusSupportFields':
					$return['convertNexusSupportFields'] = array( 'nexus_support_fields' => NULL );
					break;
				
				case 'convertNexusSupportStatuses':
					$return['convertNexusSupportStatuses'] = array( 'nexus_support_statuses' => NULL );
					break;
				
				case 'convertNexusSupportSeverities':
					$return['convertNexusSupportSeverities'] = array( 'nexus_support_severities' => NULL );
					break;
				
				case 'convertNexusSupportStockActions':
					$return['convertNexusSupportStockActions'] = array( 'nexus_support_stock_actions' => NULL );
					break;
				
				case 'convertNexusSupportRequests':
					$return['convertNexusSupportRequests'] = array( 'nexus_support_requests' => NULL );
					break;
				
				case 'convertNexusSupportReplies':
					$return['convertNexusSupportReplies'] = array( 'nexus_support_replies' => NULL );
					break;
			}
		}

		return $return[ $method ];
	}
	
	/**
	 * This is how the insert methods will work - basically like 3.x, but we should be using the actual classes to insert the data unless there is a real world reason not too.
	 * Using the actual routines to insert data will help to avoid having to resynchronize and rebuild things later on, thus resulting in less conversion time being needed overall.
	 * Anything that parses content, for example, may need to simply insert directly then rebuild via a task over time, as HTML Purifier is slow when mass inserting content.
	 */
	
	/**
	 * A note on logging -
	 * If the data is missing and it is unlikely that any source software would be able to provide this, we do not need to log anything and can use default data (for example, group_layout in convertLeaderGroups).
	 * If the data is missing and it is likely that a majority of the source software can provide this, we should log a NOTICE and use default data (for example, a_casesensitive in convertAcronyms).
	 * If the data is missing and it is required to convert the item, we should log a WARNING and return FALSE.
	 * If the conversion absolutely cannot proceed at all (filestorage locations not writable, for example), then we should log an ERROR and throw an \IPS\convert\Exception to completely halt the process and redirect to an error screen showing the last logged error.
	 */
	
	/**
	 * Convert a customer
	 *
	 * @param	array		$info	Data to insert
	 * @param	array		$fields	Custom Field Data
	 * @return	int|bool	The ID of the inserted customer, or FALSE on failure
	 */
	public function convertNexusCustomer( $info, $fields=array() )
	{
		if ( !isset( $info['member_id'] ) )
		{
			$this->software->app->log( 'nexus_customer_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		else
		{
			$sourceId = $info['member_id'];
			
			try
			{
				$info['member_id'] = $this->software->app->getLink( $info['member_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'nexus_customer_missing_member', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['member_id'] );
				return FALSE;
			}
		}
		
		if ( !isset( $info['cm_first_name'] ) )
		{
			$info['cm_first_name'] = '';
		}
		
		if ( !isset( $info['cm_last_name'] ) )
		{
			$info['cm_last_name'] = '';
		}
		
		if ( !isset( $info['cm_phone'] ) )
		{
			$info['cm_phone'] = '';
		}
		
		if ( isset( $info['cm_profiles'] ) )
		{
			if ( \is_array( $info['cm_profiles'] ) )
			{
				$info['cm_profiles'] = json_encode( $info['cm_profiles'] );
			}
		}
		else
		{
			$info['cm_profiles'] = '';
		}
		
		$info = array_merge( $info, $this->_formatCustomerFields( $fields ) );

		\IPS\Db::i()->replace( 'nexus_customers', $info );
		$this->software->app->addLink( $info['member_id'], $sourceId, 'nexus_customers' );
		
		return $info['member_id'];
	}
	
	/**
	 * Format Customer Fields for saving
	 *
	 * @param	array		$fields	The fields
	 * @return	array		Formatted Fields
	 */
	protected function _formatCustomerFields( $fields )
	{
		$return = array();
		foreach( $fields as $key => $value )
		{
			if ( \is_array( $value ) )
			{
				$value = json_encode( $value );
			}
			
			try
			{
				$id = $this->software->app->getLink( str_replace( 'field_', '', $key ), 'nexus_customer_fields' );
				
				$return['field_' . $id] = $value;
			}
			catch( \OutOfRangeException $e )
			{
				continue;
			}
		}
		
		return $return;
	}

	/**
	 * @brief	Allowed currencies
	 */
	protected $_allowedCurrencies = array( 'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD',
		'BDT', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BTN', 'BWP', 'BYN', 'BYR', 'BZD', 'CAD', 'CDF', 'CHF',
		'CLF', 'CLP', 'CNY', 'COP', 'CRC', 'CUC', 'CUP', 'CVE', 'CZK', 'DJF', 'DKK', 'DOP', 'DZD', 'EGP', 'ERN', 'ETB', 'EUR',
		'FJD', 'FKP', 'GBP', 'GEL', 'GGP', 'GHS', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR',
		'ILS', 'IMP', 'INR', 'IQD', 'IRR', 'ISK', 'JEP', 'JMD', 'JOD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KPW', 'KRW', 'KWD',
		'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'LYD', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRO', 'MUR',
		'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'OMR', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR',
		'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SDG', 'SEK', 'SGD', 'SHP', 'SLL', 'SOS', 'SPL*',
		'SRD', 'STD', 'SVC', 'SYP', 'SZL', 'THB', 'TJS', 'TMT', 'TND', 'TOP', 'TRY', 'TTD', 'TVD', 'TWD', 'TZS', 'UAH', 'UGX',
		'USD', 'UYU', 'UZS', 'VEF', 'VND', 'VUV', 'WST', 'XAF', 'XCD', 'XDR', 'XOF', 'XPF', 'YER', 'ZAR', 'ZMW', 'ZWD' );


	/**
	 * Convert a currency
	 *
	 * @param	array		Info
	 * @return	int|bool	The newly inserted currency, or FALSE on failure
	 */
	public function convertNexusCurrency( $info )
	{
		if ( !isset( $info['code'] ) )
		{
			$this->software->app->log( 'nexus_currency_missing_code', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		/* Normalise the currency code */
		$info['code'] = \strtoupper( $info['code'] );

		if ( !\in_array( $info['code'], $this->_allowedCurrencies ) )
		{
			$this->software->app->log( 'nexus_currency_not_allowed', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		$currencies = json_decode( \IPS\Settings::i()->nexus_currency, TRUE );

		/* Currencies are a little odd in that they don't really have a link */
		if( isset( $currencies[ $info['code'] ] ) )
		{
			return $info['code'];
		}

		/* Add our currency */
		$currencies[ $info['code'] ] = array();

		/* save */
		\IPS\Settings::i()->changeValues( array( 'nexus_currency' => json_encode( $currencies ) ) );

		return $info['code'];
	}
	
	/**
	 * Convert a note
	 *
	 * @param	array		$info	Info
	 * @return	int|bool	The newly inserted note ID, or FALSE on failure
	 */
	public function convertNexusNote( $info )
	{
		$hasId = TRUE;
		if ( !isset( $info['note_id'] ) )
		{
			$hasId = FALSE;
		}
		
		if ( isset( $info['note_member'] ) )
		{
			try
			{
				$info['note_member'] = $this->software->app->getLink( $info['note_member'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( "nexus_note_missing_member", __METHOD__, \IPS\convert\App::LOG_WARNING, ( $hasId ) ? $info['note_id'] : NULL );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'nexus_note_missing_member', __METHOD__, \IPS\convert\App::LOG_WARNING, ( $hasId ) ? $info['note_id'] : NULL );
			return FALSE;
		}
		
		if ( empty( $info['note_text'] ) )
		{
			$this->software->app->log( 'nexus_note_missing_content', __METHOD__, \IPS\convert\App::LOG_WARNING, ( $hasId ) ? $info['note_id'] : NULL );
			return FALSE;
		}
		
		if ( isset( $info['note_author'] ) )
		{
			try
			{
				$info['note_author'] = $this->software->app->getLink( $info['note_author'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['note_author'] = 0;
			}
		}
		else
		{
			$info['note_author'] = 0;
		}
		
		if ( isset( $info['note_date'] ) )
		{
			if ( $info['note_date'] instanceof \IPS\DateTime )
			{
				$info['note_date'] = $info['note_date']->getTimestamp();
			}
		}
		else
		{
			$info['note_date'] = time();
		}
		
		if ( $hasId )
		{
			$id = $info['note_id'];
			unset( $info['note_id'] );
		}
		
		$inserted_id = \IPS\Db::i()->insert( 'nexus_notes', $info );
		
		if ( $hasId )
		{
			$this->software->app->addLink( $inserted_id, $id, 'nexus_notes' );
		}
		
		return $inserted_id;
	}
	
	/**
	 * Convert a package group
	 *
	 * @param	array		$info		Info
	 * @param	string|NULL	$filepath	Path to the groups cover photo, or NULL
	 * @param	string|NULL	$filedata	Raw filedata for the groups cover photo, or NULL
	 * @return	int|bool	THe ID of the newly inserted package group, or FALSE on failure
	 */
	public function convertNexusPackageGroup( $info, $filepath=NULL, $filedata=NULL )
	{
		if ( !isset( $info['pg_id'] ) )
		{
			$this->software->app->log( 'nexus_package_group_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( isset( $info['pg_name'] ) )
		{
			$name = $info['pg_name'];
			unset( $info['pg_name'] );
		}
		else
		{
			$name = "Untitled Package Group {$info['pg_id']}";
		}
		
		if ( !isset( $info['pg_seo_name'] ) )
		{
			$info['pg_seo_name'] = \IPS\Http\Url::seoTitle( $name );
		}
		
		if ( !isset( $info['pg_position'] ) )
		{
			$position = \IPS\Db::i()->select( 'MAX(pg_position)', 'nexus_package_groups' )->first();
			
			$info['pg_position'] = $position + 1;
		}
		
		if ( isset( $info['pg_parent'] ) )
		{
			$info['pg_conv_parent'] = $info['pg_parent'];
		}
		else
		{
			$info['pg_parent'] = 0;
		}
		
		if ( isset( $info['pg_image'] ) AND ( !\is_null( $filepath ) OR !\is_null( $filedata ) ) )
		{
			if ( \is_null( $filedata ) AND !\is_null( $filepath ) )
			{
				$filedata = @file_get_contents( rtrim( $filepath, '/' ) . '/' . $info['pg_image'] );
				unset( $filepath );
			}
			
			if ( $filedata )
			{
				try
				{
					$file = \IPS\File::create( 'nexus_PackageGroups', $info['pg_image'], $filedata );
					$info['pg_image'] = (string) $file;
				}
				catch( \Exception $e )
				{
					$info['pg_image'] = '';
				}
			}
			else
			{
				$info['pg_image'] = '';
			}
		}
		else
		{
			$info['pg_image'] = '';
		}
		
		$id = $info['pg_id'];
		unset( $info['pg_id'] );
		
		$insertedId = \IPS\Db::i()->insert( 'nexus_package_groups', $info );
		$this->software->app->addLink( $insertedId, $id, 'nexus_package_groups' );

		/* Custom Lang */
		\IPS\Lang::saveCustom( 'nexus', "nexus_pgroup_{$insertedId}", $name );
		
		\IPS\Db::i()->update( 'nexus_package_groups', array( 'pg_parent' => $insertedId ), array( "pg_conv_parent=?", $id ) );
		
		return $insertedId;
	}

	/**
	 * Convert a pay method
	 *
	 * @param	array		$info		Info
	 * @return	int|bool	The ID of the newly inserted payment gateway, or FALSE on failure
	 */
	public function convertNexusPaymentMethod( $info )
	{
		/* !Required: ID */
		if ( !isset( $info['m_id'] ) )
		{
			$this->software->app->log( 'nexus_payment_method_missing_id', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		/* !Required: Method */
		if ( !isset( $info['m_gateway'] ) )
		{
			$this->software->app->log( 'nexus_payment_method_missing_gateway', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['m_id'] );
			return FALSE;
		}

		$info['m_gateway'] = $this->_isPaymentMethodSupported( $info['m_gateway'] );

		if( $info['m_gateway'] === FALSE )
		{
			$this->software->app->log( 'nexus_payment_method_not_supported', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['m_id'] );
			return FALSE;
		}

		if ( isset( $info['m_name'] ) )
		{
			$name = $info['m_name'];
			unset( $info['m_name'] );
		}
		else
		{
			$name = $info['m_gateway'];
		}

		$gateway = array(
			'm_gateway' 	=> '',
			'm_settings'	=> '{"client_id":"","secret":""}',
			'm_active'		=> 0,
			'm_position'	=> 0,
			'm_countries'	=> '*'
		);

		$originalId = $info['m_id'];
		unset( $info['m_id'], $info['m_name'] );

		/* Save Product Data */
		$insertedId = \IPS\Db::i()->insert( 'nexus_paymethods', $this->_getValues( $gateway, $info ) );

		/* Custom lang strings */
		\IPS\Lang::saveCustom( 'nexus', "nexus_paymethod_{$insertedId}", $name );

		/* Add Link */
		$this->software->app->addLink( $insertedId, $originalId, 'nexus_payment_methods' );

		return $insertedId;
	}

	/**
	 * Convert a package
	 *
	 * @param	array			$info		Info
	 * @param	string|NULL		$filePath	Path to images
	 * @return	int|bool	The ID of the newly inserted package, or FALSE on failure
	 */
	public function convertNexusPackage( $info, $filePath=NULL )
	{
		/* !Required: ID */
		if ( !isset( $info['p_id'] ) )
		{
			$this->software->app->log( 'nexus_package_missing_id', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		if ( isset( $info['p_name'] ) )
		{
			$name = $info['p_name'];
			unset( $info['p_name'] );
		}
		else
		{
			$name = "Untitled Package {$info['p_id']}";
		}

		if ( isset( $info['p_description'] ) )
		{
			$description = $info['p_description'];
			unset( $info['p_description'] );
		}
		else
		{
			$description = '';
		}

		/* !Required: Price */
		if( !isset( $info['p_base_price'] ) )
		{
			$this->software->app->log( 'nexus_package_missing_price', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['p_id'] );
			return FALSE;
		}

		/* !Required: Package Group */
		if( !isset( $info['p_group'] ) )
		{
			$this->software->app->log( 'nexus_package_missing_group', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['p_id'] );
			return FALSE;
		}
		$info['p_group'] = $this->software->app->getLink( $info['p_group'], 'nexus_package_groups' );

		if( isset( $info['p_support_severity'] ) )
		{
			$info['p_support_severity'] = $this->software->app->getLink( $info['p_support_severity'], 'nexus_support_severities' );
		}

		/* Viewable User Groups */
		if( isset( $info['p_member_group'] ) AND $info['p_member_group'] != '*' )
		{
			$e = explode( ',', $info['p_member_group'] );
			$newGroups = [];

			array_walk( $info['p_member_group'], function( &$group, $key, &$newGroups )
			{
				try
				{
					$newGroups[] = $this->software->app->getLink( $group, 'core_groups', TRUE );
				}
				catch( \OutOfRangeException $ex ) { }
			}, $newGroups );

			$info['p_member_group'] = $info['p_member_group'];
		}

		/* Primary User Group */
		if( isset( $info['p_primary_group'] ) )
		{
			$info['p_primary_group'] = $this->software->app->getLink( $info['p_primary_group'], 'core_groups', TRUE );
		}

		/* Secondary User Groups */
		if( isset( $info['p_secondary_group'] ) )
		{
			$e = explode( ',', $info['p_secondary_group'] );
			$newSecondaryGroups = [];

			array_walk( $info['p_secondary_group'], function( $group, $key, &$newSecondaryGroups )
			{
				try
				{
					$newSecondaryGroups[] = $this->software->app->getLink( $group, 'core_groups', TRUE );
				}
				catch( \OutOfRangeException $ex ) { }
			}, $newSecondaryGroups );
		}

		/* Associable Packages */
		if( isset( $info['p_associable'] ) )
		{
			try
			{
				$info['p_associable'] = $this->software->app->getLink( $info['p_associable'], 'nexus_packages' );
			}
			catch( \OutOfRangeException $ex )
			{
				$info['conv_p_associable'] = $info['p_associable'];
				unset( $info['p_associable'] );
			}
		}

		if ( !isset( $info['p_position'] ) )
		{
			try
			{
				$position = \IPS\Db::i()->select( 'MAX(p_position)', 'nexus_packages' )->first();
				$info['p_position'] = $position + 1;
			}
			catch( \UnderflowException $e ) { }
		}

		/* Base Price */
		array_walk( $info['p_base_price'], function( &$value, $currency )
		{
			$price = new \IPS\nexus\Money( $value, $currency );
			$value = [ 'amount' => $price->amount, 'currency' => $currency ];
		});
		$basePrice = $info['p_base_price'];
		unset( $info['p_base_price'] );

		/* Renewal costs & terms */
		$renewals = array();
		if( isset( $info['p_renew_options'] ) AND \count( $info['p_renew_options'] ) )
		{
			foreach ( $info['p_renew_options'] as $currency => $options )
			{
				$newOptions = array(
					'cost' => [],
					'term' => $options['term'],
					'unit' => $options['unit'],
					'add' => isset( $options['add'] ) ? $options['add'] : false
				);

				foreach ( $options['cost'] as $currency => $cost )
				{
					$price = new \IPS\nexus\Money( $cost, $currency );
					$newOptions['cost'][ $currency ] = [ 'amount' => $price->amount, 'currency' => $currency ];
				}

				$renewals[] = $newOptions;
			}

			$info['p_renew_options'] = json_encode( $renewals );
		}

		/* Discounts */
		//@TODO

		$productImages = isset( $info['p_images'] ) ? $info['p_images'] : NULL;
		unset( $info['p_images'] );

		$package = array(
			'p_name' => $name,
			'p_seo_name' => \IPS\Http\url::seoTitle( $name ),
			'p_group' => 0,
			'p_stock' => -1,
			'p_reg' => 0,
			'p_store' => 1,
			'p_member_groups' => '*',
			'p_allow_upgrading' => 0,
			'p_upgrade_charge' => 0,
			'p_allow_downgrading' => 0,
			'p_downgrade_refund' => 0,
			'p_base_price' => json_encode( $basePrice ),
			'p_tax' => 0,
			'p_renewal_days' => 0,
			'p_primary_group' => 0,
			'p_secondary_group' => '',
			'p_return_primary' => 1,
			'p_return_secondary' => 0,
			'p_position' => 0,
			'p_associable' => '',
			'p_force_assoc' => 0,
			'p_assoc_error' => NULL,
			'p_discounts' => '[]',
			'p_page' => NULL,
			'p_support' => 0,
			'p_support_department' => 0,
			'p_support_severity' => 0,
			'p_featured' => 0,
			'p_upsell' => 0,
			'p_notify' => '',
			'p_type' => 'product',
			'p_custom' => 0,
			'p_reviewable' => 0,
			'p_review_moderate' => 0,
			'p_image' => NULL,
			'p_methods' => '*',
			'p_renew_options' => '',
			'p_group_renewals' => 0,
			'p_rebuild_thumb' => 0,
			'p_renewal_days_advance' => -1,
			'p_date_added' => time(),
			'p_reviews' => 0,
			'p_rating' => 0,
			'p_unapproved_reviews' => NULL,
			'p_hidden_reviews' => NULL,
			'p_grace_period' => 0,
			'p_conv_associable' => 0
		);

		/* Save Package Data */
		$insertedId = \IPS\Db::i()->insert( 'nexus_packages', $this->_getValues( $package, $info ) );
		$originalId = $info['p_id'];
		unset( $info['p_id'] );

		/* Images */
		$images = NULL;

		if( $productImages )
		{
			$default = 0;
			foreach( $productImages as $image )
			{
				if ( ( isset( $image['data'] ) AND \is_null( $image['data'] ) ) AND !\is_null( $filePath ) )
				{
					$filedata = @file_get_contents( rtrim( $filePath, '/' ) . '/' . $image['filename'] );
					unset( $filepath );
				}

				if ( $filedata )
				{
					try
					{
						$file = \IPS\File::create( 'nexus_Products', $image['filename'], $filedata );
						$images[] = [ 'image_product' => $insertedId, 'image_location' => (string) $file, 'image_primary' => ( $default ? 0 : 1 ) ];
						$default++;
					}
					catch( \Exception $e ) {}
				}
			}

			/* Insert Images */
			if( \is_array( $images ) AND \count( $images ) )
			{
				\IPS\Db::i()->insert( 'nexus_package_images', $images );
			}
		}

		/* Product Data */
		if( $package['p_type'] == 'product' )
		{
			$product = array(
						'p_id' => $insertedId,
						'p_physical' => 0,
						'p_subscription' => 0,
						'p_shipping' => '*',
						'p_weight' => 0,
						'p_lkey' => 0,
						'p_lkey_identifier' => 'name',
						'p_lkey_uses' => -1,
						'p_show' => 1,
						'p_length' => 0,
						'p_width' => 0,
						'p_height' => 0
					);

			/* Save Product Data */
			\IPS\Db::i()->insert( 'nexus_packages_products', $this->_getValues( $product, $info ) );

			/* Add Link */
			$this->software->app->addLink( $insertedId, $originalId, 'nexus_packages_products' );
		}

		/* Base Price */
		$insert = [ 'id' => $insertedId ];
		foreach( $basePrice as $currency => $amount )
		{
			/* check the currency is set up */
			$this->convertNexusCurrency( [ 'code' => $currency ] );

			/* Check the table */
			if ( !\IPS\Db::i()->checkForColumn( 'nexus_package_base_prices', $currency ) )
			{
				\IPS\Db::i()->addColumn( 'nexus_package_base_prices', array(
					'name' => $currency,
					'type' => 'FLOAT'
				) );
			}
			$insert[ $currency ] = (string) $amount['amount'];
		}
		\IPS\Db::i()->insert( 'nexus_package_base_prices', $insert );

		/* Custom lang strings */
		\IPS\Lang::saveCustom( 'nexus', "nexus_package_{$insertedId}", $name );
		\IPS\Lang::saveCustom( 'nexus', "nexus_package_{$insertedId}_desc", $description );

		/* Add Link */
		$this->software->app->addLink( $insertedId, $originalId, 'nexus_packages' );

		/* Update associations */
		\IPS\Db::i()->update( 'nexus_packages', array( 'p_associable' => $insertedId ), array( 'p_conv_associable=?', $originalId ) );

		return $insertedId;
	}

	/**
	 * Convert a subscription package
	 *
	 * @param	array			$info		Info
	 * @param	string|NULL		$filePath	Path to images
	 * @return	int|bool	The ID of the newly inserted package, or FALSE on failure
	 */
	public function convertNexusSubscriptionPackage( $info, $filePath=NULL )
	{
		/* !Required: ID */
		if ( !isset( $info['sp_id'] ) )
		{
			$this->software->app->log( 'nexus_package_missing_id', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		if ( isset( $info['sp_name'] ) )
		{
			$name = $info['sp_name'];
			unset( $info['sp_name'] );
		}
		else
		{
			$name = "Untitled Subscription {$info['sp_id']}";
		}

		if ( isset( $info['sp_description'] ) )
		{
			$description = $info['sp_description'];
			unset( $info['sp_description'] );
		}
		else
		{
			$description = '';
		}

		/* !Required: Price */
		if( !isset( $info['sp_price'] ) )
		{
			$this->software->app->log( 'nexus_subscription_missing_price', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['sp_id'] );
			return FALSE;
		}

		/* Primary User Group */
		if( isset( $info['sp_primary_group'] ) )
		{
			$info['sp_primary_group'] = $this->software->app->getLink( $info['sp_primary_group'], 'core_groups', TRUE );
		}

		/* Secondary User Groups */
		if( isset( $info['sp_secondary_group'] ) )
		{
			$e = explode( ',', $info['sp_secondary_group'] );
			$newSecondaryGroups = [];

			array_walk( $info['sp_secondary_group'], function( $group, $key, &$newSecondaryGroups )
			{
				try
				{
					$newSecondaryGroups[] = $this->software->app->getLink( $group, 'core_groups', TRUE );
				}
				catch( \OutOfRangeException $ex ) { }
			}, $newSecondaryGroups );
		}

		if ( !isset( $info['sp_position'] ) )
		{
			try
			{
				$position = \IPS\Db::i()->select( 'MAX(p_position)', 'nexus_subscription_packages' )->first();
				$info['sp_position'] = $position + 1;
			}
			catch( \UnderflowException $e ) { }
		}

		/* Base Price */
		if( isset( $info['sp_price'] ) AND \count( $info['sp_price'] ) )
		{
			$newOptions = array(
				'cost' => [],
				'term' => $info['sp_price']['term'],
				'unit' => $info['sp_price']['unit'],
				'add' => isset( $info['sp_price']['add'] ) ? $info['sp_price']['add'] : false
			);

			foreach ( $info['sp_price']['cost'] as $currency => $cost )
			{
				$price = new \IPS\nexus\Money( $cost, $currency );
				$newOptions['cost'][ $currency ] = [ 'amount' => $price->amount, 'currency' => $currency ];
			}
		}

		$basePrice = $newOptions;
		unset( $info['sp_price'] );

		/* Renewal costs & terms */
		if( isset( $info['sp_renew_options'] ) AND \count( $info['sp_renew_options'] ) )
		{
			$newOptions = array(
				'cost' => [],
				'term' => $info['sp_renew_options']['term'],
				'unit' => $info['sp_renew_options']['unit'],
				'add' => isset( $info['sp_renew_options']['add'] ) ? $info['sp_renew_options']['add'] : false
			);

			foreach ( $info['sp_renew_options']['cost'] as $currency => $cost )
			{
				$price = new \IPS\nexus\Money( $cost, $currency );
				$newOptions['cost'][ $currency ] = [ 'amount' => $price->amount, 'currency' => $currency ];
			}

			$info['sp_renew_options'] = json_encode( $newOptions );
		}

		$image = isset( $info['sp_image'] ) ? $info['sp_image'] : NULL;
		unset( $info['sp_image'] );

		$package = array(
			'sp_reg_show' => 0,
			'sp_featured' => 0,
			'sp_price' => json_encode( $basePrice ),
			'sp_tax' => 0,
			'sp_primary_group' => 0,
			'sp_secondary_group' => '',
			'sp_return_primary' => 1,
			'sp_position' => 0,
			'sp_image' => NULL,
			'sp_gateways' => '*',
			'sp_renew_options' => '',
			'sp_count_active' => 0,
			'sp_count_inactive' => 0
		);

		/* Images */
		if( $image )
		{
			if ( ( isset( $image['data'] ) AND \is_null( $image['data'] ) ) AND !\is_null( $filePath ) )
			{
				$filedata = @file_get_contents( rtrim( $filePath, '/' ) . '/' . $image['filename'] );
				unset( $filepath );
			}

			if ( $filedata )
			{
				try
				{
					$info['sp_image'] = (string) \IPS\File::create( 'nexus_Products', $image['filename'], $filedata );
				}
				catch( \Exception $e ) {}
			}
		}

		/* Save Package Data */
		$insertedId = \IPS\Db::i()->insert( 'nexus_member_subscription_packages', $this->_getValues( $package, $info ) );
		$originalId = $info['sp_id'];
		unset( $info['sp_id'] );

		/* Custom lang strings */
		\IPS\Lang::saveCustom( 'nexus', "nexus_subs_{$insertedId}", $name );
		\IPS\Lang::saveCustom( 'nexus', "nexus_subs_{$insertedId}_desc", $description );

		/* Add Link */
		$this->software->app->addLink( $insertedId, $originalId, 'nexus_members_subs_pkg' );

		return $insertedId;
	}

	/**
	 * Convert an invoice
	 *
	 * @param	array		$info		Info
	 * @param	string		$currency	Invoice Currency
	 * @return	int|bool	The ID of the newly inserted invoice, or FALSE on failure
	 */
	public function convertNexusInvoice( $info, $currency )
	{
		/* !Required: ID */
		if ( !isset( $info['i_id'] ) )
		{
			$this->software->app->log( 'nexus_invoices_missing_id', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		/* !Required: Currency */
		if ( !isset( $currency ) OR !$currency = $this->convertNexusCurrency( [ 'code' => $currency ] ) )
		{
			$this->software->app->log( 'nexus_invoice_currency_invalid', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		/* !Required: Items */
		if ( !isset( $info['i_items'] ) OR !\is_array( $info['i_items'] ) )
		{
			$this->software->app->log( 'nexus_invoices_missing_items', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['i_id'] );
			return FALSE;
		}

		/* Default Currency */
		$price = new \IPS\nexus\Money( $info['i_total'], $currency );
		$info['i_total'] = $price->amount;

		/* ITEMS */
		$newItems = array();
		foreach( $info['i_items'] as $item )
		{
			try
			{
				$item['itemID'] = $this->software->app->getLink( $item['itemID'], $item['item_type'] ?? 'nexus_packages' );
				unset( $item['item_type'] );
				$newItems[] = $item;
			}
			catch( \OutOfRangeException $e ) {}
		}

		if( !\count( $newItems ) )
		{
			$this->software->app->log( 'nexus_invoices_missing_mapped_items', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['i_id'] );
			return FALSE;
		}

		$info['i_items'] = json_encode( $newItems );

		/* Find member */
		try
		{
			$info['i_member'] = $this->software->app->getLink( $info['i_member'], 'core_members', TRUE );
		}
		catch( \Exception $e )
		{
			unset( $info['i_member'] );
		}

		if( isset( $info['i_status_extra'] ) )
		{
			$info['i_status_extra'] = json_encode( $info['i_status_extra'] );
		}

		/* Invoice */
		$invoice = array(
			'i_status' => \IPS\nexus\Invoice::STATUS_PENDING,
			'i_title' => 'Converted Invoice '. $info['i_id'],
			'i_member' => 0,
			'i_items' => '[]',
			'i_total' => $price->amount,
			'i_date' => time(),
			'i_return_uri' => '',
			'i_paid' => 0,
			'i_status_extra' => '[]',
			'i_discount' => 0,
			'i_renewal_ids' => '',
			'i_po' => '',
			'i_notes' => NULL,
			'i_shipaddress' => NULL,
			'i_billaddress' => NULL,
			'i_currency' => $currency,
			'i_guest_data' => NULL,
			'i_billcountry' => NULL
 		);

		/* Save Invoice Data */
		$insertedId = \IPS\Db::i()->insert( 'nexus_invoices', $this->_getValues( $invoice, $info ) );

		/* Add Link */
		$this->software->app->addLink( $insertedId, $info['i_id'], 'nexus_invoices' );

		return $insertedId;
	}

	/**
	 * Convert a transaction
	 *
	 * @param	array		$info		Info
	 * @param	string		$currency	Transaction Currency
	 * @return	int|bool	The ID of the newly inserted transaction, or FALSE on failure
	 */
	public function convertNexusTransaction( $info, $currency )
	{
		/* !Required: ID */
		if ( !isset( $info['t_id'] ) )
		{
			$this->software->app->log( 'nexus_transactions_missing_id', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		/* !Required: MemberID */
		if ( !isset( $info['t_member'] ) )
		{
			$this->software->app->log( 'nexus_transactions_missing_member_id', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['t_id'] );
			return FALSE;
		}

		/* !Required: InvoiceID */
		if ( !isset( $info['t_invoice'] ) )
		{
			$this->software->app->log( 'nexus_transactions_missing_invoice_id', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['t_id'] );
			return FALSE;
		}

		/* !Required: Amount */
		if ( !isset( $info['t_amount'] ) )
		{
			$this->software->app->log( 'nexus_transactions_missing_amount', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['t_id'] );
			return FALSE;
		}

		/* !Required: Currency */
		if ( !isset( $currency ) OR !$currency = $this->convertNexusCurrency( [ 'code' => $currency ] ) )
		{
			$this->software->app->log( 'nexus_transaction_currency_invalid', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		/* Map IDs */
		try
		{
			$info['t_invoice'] = $this->software->app->getLink( $info['t_invoice'], 'nexus_invoices' );
		}
		catch( \OutOfRangeException $ex )
		{
			$this->software->app->log( 'nexus_transactions_missing_invoice', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['t_invoice'] );
			return FALSE;
		}

		try
		{
			$info['t_member'] = $this->software->app->getLink( $info['t_member'], 'core_members', TRUE );
		}
		catch( \OutOfRangeException $ex)
		{
			$this->software->app->log( 'nexus_transactions_missing_member', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['t_id'] );
			return FALSE;
		}

		/* !Payment Method */
		if( isset( $info['t_method'] ) )
		{
			try
			{
				$info['t_method'] = $this->software->app->getLink( $info['t_method'], 'nexus_payment_methods' );
			}
			catch ( \OutOfRangeException $ex )
			{
				$info['t_method'] = 0;
			}
		}

		/* Default Currency */
		$price = new \IPS\nexus\Money( $info['t_amount'], $currency );
		$info['t_amount'] = $price->amount;

		$transaction = array(
			't_member' => 0,
			't_invoice' => 0,
			't_method' => 0,
			't_status' => \IPS\nexus\Transaction::STATUS_PENDING,
			't_amount' => 0,
			't_date' => time(),
			't_extra' => '[]',
			't_fraud' => '',
			't_gw_id' => '',
			't_ip' => '::1',
			't_fraud_blocked' => 0,
			't_currency' => $currency,
			't_partial_refund' => 0.000,
			't_credit' => 0.000,
			't_auth' => NULL,
			't_billing_agreement' => NULL
		);

		/* Save Transaction Data */
		$insertedId = \IPS\Db::i()->insert( 'nexus_transactions', $this->_getValues( $transaction, $info ) );

		/* Add Link */
		$this->software->app->addLink( $insertedId, $info['t_id'], 'nexus_transactions' );

		return $insertedId;
	}

	/**
	 * Convert a purchase
	 *
	 * @param	array		$info	Info
	 * @param	string		$currency	Transaction Currency
	 * @return	int|bool	The ID of the newly inserted purchase, or FALSE on failure
	 */
	public function convertNexusPurchase( $info, $currency )
	{
		/* !Required: ID */
		if ( !isset( $info['ps_id'] ) )
		{
			$this->software->app->log( 'nexus_purchases_missing_id', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		/* !Required: MemberID */
		if ( !isset( $info['ps_member'] ) )
		{
			$this->software->app->log( 'nexus_purchases_missing_member_id', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['ps_id'] );
			return FALSE;
		}

		/* !Required: InvoiceID */
		if ( !isset( $info['ps_original_invoice'] ) )
		{
			$this->software->app->log( 'nexus_purchases_missing_invoice_id', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['ps_id'] );
			return FALSE;
		}

		/* !Required: Currency */
		if ( !isset( $currency ) OR !$currency = $this->convertNexusCurrency( [ 'code' => $currency ] ) )
		{
			$this->software->app->log( 'nexus_purchase_currency_invalid', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		/* Map IDs */
		try
		{
			$info['ps_original_invoice'] = $this->software->app->getLink( $info['ps_original_invoice'], 'nexus_invoices' );
		}
		catch( \OutOfRangeException $ex)
		{
			$this->software->app->log( 'nexus_purchases_missing_invoice', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['ps_id'] );
			return FALSE;
		}

		$info['ps_item_id'] = $this->software->app->getLink( $info['ps_item_id'], $info['ps_link_type'] ?? 'nexus_packages' );
		
		try
		{
			$info['ps_member'] = $this->software->app->getLink( $info['ps_member'], 'core_members', TRUE );
		}
		catch( \OutOfRangeException $ex)
		{
			$this->software->app->log( 'nexus_purchases_missing_member', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['ps_id'] );
			return FALSE;
		}

		/* Commission payments */
		if( isset( $info['ps_pay_to'] ) )
		{
			$info['ps_pay_to'] = $this->software->app->getLink( $info['ps_pay_to'], 'core_members', TRUE );
		}

		/* Parent Purchases */
		if( isset( $info['ps_parent'] ) )
		{
			try
			{
				$info['ps_parent'] = $this->software->app->getLink( $info['ps_parent'], 'nexus_purchases' );
			}
			catch( \OutOfRangeException $ex )
			{
				$info['ps_conv_parent'] = $info['ps_parent'];
				unset( $info['ps_parent'] );
			}
		}

		if( !isset( $info['ps_extra'] ) )
		{
			$info['ps_extra'] = array();
		}

		/* Old Primary Group */
		if( isset( $info['ps_extra']['nexus']['old_primary_group'] ) )
		{
			$info['ps_extra']['nexus']['old_primary_group'] = $this->software->app->getLink( $info['ps_extra']['nexus']['old_primary_group'], 'core_groups', TRUE );
		}

		/* Old Secondary User Groups */
		if( isset( $info['ps_extra']['nexus']['old_secondary_groups'] ) AND \is_array( $info['ps_extra']['nexus']['old_secondary_groups'] ) )
		{
			$newSecondaryGroups = [];

			array_walk( $info['ps_extra']['nexus']['old_secondary_groups'], function( $group, $key, &$newSecondaryGroups )
			{
				try
				{
					$newSecondaryGroups[] = $this->software->app->getLink( $group, 'core_groups', TRUE );
				}
				catch( \OutOfRangeException $ex ) { }
			}, $newSecondaryGroups );

			$info['ps_extra']['nexus']['old_secondary_groups'] = $newSecondaryGroups;
		}

		$info['ps_extra'] = json_encode( $info['ps_extra'] );

		/* Default Currency */
		if( isset( $info['ps_renewal_price'] ) )
		{
			$price = new \IPS\nexus\Money( $info['ps_renewal_price'], $currency );
			$info['ps_renewal_price'] = $price->amount;
		}

		$purchase = array(
			'ps_member' => 0,
			'ps_name' => 'Converted Purchase ' . $info['ps_id'],
			'ps_active' => 0,
			'ps_cancelled' => 0,
			'ps_start' => 0,
			'ps_expire' => 0,
			'ps_renewals' => 0,
			'ps_renewal_price' => 0.00,
			'ps_renewal_unit' => 0,
			'ps_app' => 'nexus',
			'ps_type' => 'package',
			'ps_item_id' => 0,
			'ps_item_uri' => '',
			'ps_admin_uri' => '',
			'ps_custom_fields' => '[]',
			'ps_extra' => '[]',
			'ps_parent' => 0,
			'ps_invoice_pending' => 0,
			'ps_invoice_warning_sent' => 1,
			'ps_pay_to' => NULL,
			'ps_commission' => NULL,
			'ps_original_invoice' => 0,
			'ps_tax' => 0,
			'ps_can_reactivate' => 1,
			'ps_grouped_renewals' => '',
			'ps_renewal_currency' => $currency,
			'ps_show' => 1,
			'ps_grace_period' => 0,
			'ps_billing_agreement' => NULL,
			'ps_conv_parent' => 0
		);

		/* Save Purchase Data */
		$insertedId = \IPS\Db::i()->insert( 'nexus_purchases', $this->_getValues( $purchase, $info ) );

		/* Add Link */
		$this->software->app->addLink( $insertedId, $info['ps_id'], 'nexus_purchases' );

		/* Update associations */
		\IPS\Db::i()->update( 'nexus_purchases', array( 'ps_parent' => $insertedId ), array( 'ps_conv_parent=?', $info['ps_id'] ) );

		return $insertedId;
	}

	/**
	 * Get values from an array that exist in the source array
	 *
	 * @param	array		$defaults	Default populated array
	 * @param	array		$source		User-supplied array
	 * @return	array
	 */
	protected function _getValues( array $defaults, array $source )
	{
		array_walk( $defaults, function( &$value, $key, $source )
		{
			if( isset( $source[ $key ] ) )
			{
				$value = $source[ $key ];
			}
		}, $source );

		return $defaults;
	}

	/**
	 * Check whether a given payment method is supported by Commerce
	 *
	 * @param $method
	 * @return bool
	 */
	protected function _isPaymentMethodSupported( $method )
	{
		$availableGateways = \IPS\nexus\Gateway::gateways();
		$methodKey = array_search( $method, array_keys( array_change_key_case( $availableGateways, CASE_LOWER ) ) );

		if( $methodKey !== FALSE )
		{
			return array_keys( $availableGateways )[ $methodKey ];
		}

		return FALSE;
	}
}