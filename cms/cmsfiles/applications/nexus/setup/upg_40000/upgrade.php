<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		10 Feb 2014
 */

namespace IPS\nexus\setup\upg_40000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Convert gateways
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Update nexus_paymethods and nexus_payouts to adjust the gateway keys, if we have any gateways set up */
		if( \IPS\Db::i()->select( 'COUNT(*)', 'nexus_gateways' )->first() > 0 )
		{
			\IPS\Db::i()->update( 'nexus_paymethods', array( 'm_gateway' => \IPS\Db::i()->select( 'g_key', 'nexus_gateways', array( 'g_id=m_gateway' ) ) ) );
			\IPS\Db::i()->update( 'nexus_payouts', array( 'po_gateway' => \IPS\Db::i()->select( 'g_key', 'nexus_gateways', array( 'g_id=po_gateway' ) ) ) );
		}
		
		/* We need to fix the gateway keys for payouts for our own internal gateways - 3rd party gateways should do the same in their own upgrade routines */
		\IPS\Db::i()->update( 'nexus_payouts', array( 'po_gateway' => 'PayPal' ), array( 'po_gateway=?', 'paypal' ) );
		\IPS\Db::i()->update( 'nexus_payouts', array( 'po_gateway' => 'Manual' ), array( 'po_gateway=?', 'manual' ) );
		
		\IPS\Db::i()->update( 'nexus_paymethods', array( 'm_gateway' => 'TwoCheckout' ), array( 'm_gateway=?', '2checkout' ) );
		\IPS\Db::i()->update( 'nexus_paymethods', array( 'm_gateway' => 'AuthorizeNet' ), array( 'm_gateway=?', 'authnet' ) );
		\IPS\Db::i()->update( 'nexus_paymethods', array( 'm_gateway' => 'Manual' ), array( 'm_gateway=?', 'manual' ) );
		\IPS\Db::i()->update( 'nexus_paymethods', array( 'm_gateway' => 'PayPal' ), array( 'm_gateway=?', 'paypal' ) );
		\IPS\Db::i()->update( 'nexus_paymethods', array( 'm_gateway' => 'PayPal' ), array( 'm_gateway=?', 'paypalpro' ) );
		\IPS\Db::i()->update( 'nexus_paymethods', array( 'm_gateway' => 'Stripe' ), array( 'm_gateway=?', 'stripe' ) );
		\IPS\Db::i()->delete( 'nexus_paymethods', array( 'm_gateway=?', 'sagepay' ) );

		\IPS\Db::i()->dropTable('nexus_gateways');
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Updating commerce payment gateways";
	}

	/**
	 * Convert addresses
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{			
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		
		$select = \IPS\Db::i()->select( '*', 'nexus_customers', "cm_address_1<>''", 'member_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{
			foreach ( $select as $customer )
			{
				$address = new \IPS\GeoLocation;
				$address->addressLines[0] = $customer['cm_address_1'];
				if ( $customer['cm_address_2'] )
				{
					$address->addressLines[1] = $customer['cm_address_2'];
				}
				
				$state = $customer['cm_state'];
				
				/* 4.x requires the full state name to be stored. */
				if ( $customer['cm_state'] AND $customer['cm_country'] )
				{
					if ( isset( \IPS\nexus\Customer\Address::$stateCodes[ $customer['cm_country'] ] ) )
					{
						if ( isset( \IPS\nexus\Customer\Address::$stateCodes[ $customer['cm_country'] ][ $customer['cm_state'] ] ) )
						{
							$state = \IPS\nexus\Customer\Address::$stateCodes[ $customer['cm_country'] ][ $customer['cm_state'] ];
						}
					}
				}
				
				$address->city = $customer['cm_city'];
				$address->region = $state;
				$address->postalCode = $customer['cm_zip'];
				$address->country = $customer['cm_country'];
				
				\IPS\Db::i()->insert( 'nexus_customer_addresses', array(
					'member'			=> $customer['member_id'],
					'address'			=> json_encode( $address ),
					'primary_billing'	=> 1,
					'primary_shipping'	=> 1,
				) );
				
				\IPS\Db::i()->update( 'nexus_invoices', array( 'i_billcountry' => $customer['cm_country'] ), array( 'i_member=?', $customer['member_id'] ) );
			}
			
			return $offset + 100;
		}
		else
		{
			\IPS\Db::i()->dropColumn( 'nexus_customers', array( 'cm_address_1', 'cm_address_2', 'cm_city', 'cm_state', 'cm_zip', 'cm_country' ) );
			\IPS\Db::i()->delete( 'nexus_customer_fields', "f_column IN('cm_first_name', 'cm_last_name', 'cm_address_1', 'cm_address_2', 'cm_city', 'cm_state', 'cm_zip', 'cm_country')" );
			\IPS\Db::i()->update( 'nexus_customer_fields', array( 'f_type' => 'Tel' ), array( 'f_column=?', 'cm_phone' ) );

			$columns = array( 'cm_first_name', 'cm_last_name', 'cm_address_1', 'cm_address_2', 'cm_city', 'cm_state', 'cm_zip', 'cm_country', 'cm_phone' );
			$toRemove = array();

			foreach( $columns as $column )
			{
				if ( \IPS\Db::i()->checkForColumn( 'core_members' , $column ) )
				{
					$toRemove[] = $column;
				}
			}

			if( !empty( $toRemove ) )
			{
				\IPS\Db::i()->dropColumn( 'core_members', $toRemove );
			}
			
			unset( $_SESSION['_step2Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( \IPS\Db::i()->checkForColumn( 'nexus_customers', 'cm_address_1' ) )
		{
			if( !isset( $_SESSION['_step2Count'] ) )
			{
				$_SESSION['_step2Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_customers', "cm_address_1<>''" )->first();
			}

			$message = "Upgrading commerce addresses (Upgraded so far: " . ( ( $limit > $_SESSION['_step2Count'] ) ? $_SESSION['_step2Count'] : $limit ) . ' out of ' . $_SESSION['_step2Count'] . ')';
		}
		else
		{
			$message = "Upgraded all commerce addresses";
		}

		return $message;
	}

	/**
	 * Convert currencies
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		if ( ! isset( \IPS\Request::i()->run_anyway ) )
		{
			\IPS\Db::i()->addColumn( 'nexus_package_base_prices', array(
				'name'	=> \IPS\Settings::i()->nexus_currency,
				'type'	=> 'FLOAT'
			) );
			
			\IPS\Db::i()->update( 'nexus_fraud_rules', "f_amount_unit=CONCAT( '{\"" . \IPS\Settings::i()->nexus_currency . "\":', f_amount_unit, '}' )", 'f_amount_unit>0' );
			\IPS\Db::i()->update( 'nexus_fraud_rules', array( 'f_amount_unit' => '[]' ), 'f_amount_unit=0' );
			
			\IPS\Db::i()->update( 'nexus_referral_rules', "rrule_by_purchases_unit=CONCAT( '{\"" . \IPS\Settings::i()->nexus_currency . "\":', rrule_by_purchases_unit, '}' )", 'rrule_by_purchases_unit>0' );
			
			\IPS\Db::i()->update( 'nexus_referral_rules', "rrule_purchase_amount_unit=CONCAT( '{\"" . \IPS\Settings::i()->nexus_currency . "\":', rrule_purchase_amount_unit, '}' )", 'rrule_purchase_amount_unit>0' );
			\IPS\Db::i()->update( 'nexus_referral_rules', array( 'rrule_purchase_amount_unit' => '' ), 'rrule_purchase_amount_unit=0' );
			
			\IPS\Db::i()->update( 'nexus_referral_rules', "rrule_commission_limit=CONCAT( '{\"" . \IPS\Settings::i()->nexus_currency . "\":', rrule_commission_limit, '}' )", 'rrule_commission_limit>0' );
			\IPS\Db::i()->update( 'nexus_referral_rules', array( 'rrule_commission_limit' => '[]' ), 'rrule_commission_limit=0' );
	
			\IPS\Db::i()->update( 'nexus_referrals', "amount=CONCAT( '{\"" . \IPS\Settings::i()->nexus_currency . "\":', amount, '}' )" );
	
	        \IPS\Db::i()->addColumn( 'nexus_support_departments', array(
	            'name'	=> \IPS\Settings::i()->nexus_currency,
	            'type'	=> 'FLOAT'
	        ) );
			\IPS\Db::i()->update( 'nexus_support_departments', "dpt_ppi=CONCAT( '{\"" . \IPS\Settings::i()->nexus_currency . "\":{\"amount\":', dpt_ppi, ',\"currency\":\"', '" . \IPS\Settings::i()->nexus_currency . "', '\"}}' )", "dpt_ppi<>'*'" );
			
			\IPS\Db::i()->update( 'nexus_transactions', array( 't_currency' => \IPS\Settings::i()->nexus_currency ) );
			\IPS\Db::i()->update( 'nexus_invoices', array( 'i_currency' => \IPS\Settings::i()->nexus_currency ) );
			\IPS\Db::i()->update( 'nexus_purchases', array( 'ps_renewal_currency' => \IPS\Settings::i()->nexus_currency ) );
			\IPS\Db::i()->update( 'nexus_payouts', array( 'po_currency' => \IPS\Settings::i()->nexus_currency ) );
			\IPS\Db::i()->update( 'nexus_donate_goals', array( 'd_currency' => \IPS\Settings::i()->nexus_currency ) );
		}
		
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'core_members',
			'query' => "UPDATE " . \IPS\Db::i()->prefix . "core_members SET cm_credits=CONCAT( '{\"" . \IPS\Settings::i()->nexus_currency . "\":', cm_credits, '}' );"
		) ) );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'nexus', 'extra' => array( '_upgradeStep' => 4 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Upgrading commerce currencies";
	}

	/**
	 * Convert nexus_approve_all to a fraud rule
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		if ( \IPS\Settings::i()->nexus_approve_all )
		{
			\IPS\Db::i()->insert( 'nexus_fraud_rules', array(
				'f_name'			=> "Manually Approve All Transactions",
				'f_amount_unit'		=> '[]',
				'f_methods'			=> '*',
				'f_country'			=> '*',
				'f_trans_okay_unit'	=> 0,
				'f_action'			=> 'hold',
				'f_order'			=> 1,
				'f_coupon'			=> 0
			) );
		}
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step4CustomTitle()
	{
		return "Upgrading commerce fraud rules";
	}

	/**
	 * Convert nexus_review_rates to nexus_reviews.review_vote_data
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step5()
	{		
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		
		try
		{
			$review = \IPS\Db::i()->select( '*', 'nexus_reviews', 'review_votes>0', 'review_id', array( $offset, 20 ) )->first();
			
			$data = iterator_to_array( \IPS\Db::i()->select( '*', 'nexus_review_rates', array( 'rr_review=?', $review['review_id'] ) )->setKeyField('rr_member')->setValueField('rr_rate') );
			
			\IPS\Db::i()->update( 'nexus_reviews', array( 'review_vote_data' => json_encode( $data ) ), array( 'review_id=?', $review['review_id'] ) );
			
			return $offset + 20;
		}
		catch ( \UnderflowException $e )
		{		
			\IPS\Db::i()->dropTable('nexus_review_rates');

			unset( $_SESSION['_step5Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step5CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step5Count'] ) )
		{
			$_SESSION['_step5Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_reviews', 'review_votes>0' )->first();
		}

		return "Upgrading commerce review ratings (Upgraded so far: " . ( ( $limit > $_SESSION['_step5Count'] ) ? $_SESSION['_step5Count'] : $limit ) . ' out of ' . $_SESSION['_step5Count'] . ')';
	}

	/**
	 * Convert Transfer Fee
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step6()
	{
		if( isset( \IPS\Settings::i()->idm_nexus_transfee ) )
		{
			$transFee = array();
			$transFee[ \IPS\Settings::i()->nexus_currency ] = array( 'amount' => \IPS\Settings::i()->idm_nexus_transfee, 'currency' => \IPS\Settings::i()->nexus_currency );

			\IPS\Settings::i()->changeValues( array( 'idm_nexus_transfee' => json_encode( $transFee ) ) );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step6CustomTitle()
	{
		return "Upgrading transfer fee";
	}

	/**
	 * Convert purchase types
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step7()
	{
		\IPS\Db::i()->update( 'nexus_purchases', array( 'ps_type' => 'package' ), \IPS\Db::i()->in( 'ps_type', array( 'product', 'hosting', 'ad', 'dedi' ) ) );
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step7CustomTitle()
	{
		return "Upgrading commerce purchases";
	}

	/**
	 * Convert server nameservers
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step8()
	{
		foreach ( \IPS\Db::i()->select( '*', 'nexus_hosting_servers' ) as $server )
		{
			$nameservers = explode( '<br />', $server['server_nameservers'] );
			
			\IPS\Db::i()->update( 'nexus_hosting_servers', array(
				'server_nameservers'	=> implode( ',', $nameservers ),
				'server_extra'			=> json_encode( \unserialize( $server['server_extra'] ) ),
			), array( 'server_id=?', $server['server_id'] ) );
		}
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step8CustomTitle()
	{
		return "Upgrading commerce hosting nameservers";
	}

	/**
	 * Convert ad packages
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step9()
	{
		\IPS\Db::i()->update( 'nexus_packages_ads', "p_exempt=p_exempt!=1;" );
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step9CustomTitle()
	{
		return "Upgrading commerce ad packages";
	}

	/**
	 * Translatables: Settings
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step10()
	{
		\IPS\Lang::saveCustom( 'nexus', 'network_status_text_val', \IPS\Text\Parser::parseStatic( \IPS\Settings::i()->network_status_text, TRUE, NULL, NULL, TRUE, TRUE, TRUE ) );
		\IPS\Lang::saveCustom( 'nexus', 'nexus_com_rules_val', \IPS\Text\Parser::parseStatic( \IPS\Settings::i()->nexus_com_rules_alt, TRUE, NULL, NULL, TRUE, TRUE, TRUE ) );
		\IPS\Lang::saveCustom( 'nexus', 'nexus_tax_explain_val', \IPS\Text\Parser::parseStatic( \IPS\Settings::i()->nexus_tax_explain, TRUE, NULL, NULL, TRUE, TRUE, TRUE ) );

		\IPS\Db::i()->delete( 'core_sys_conf_settings', "conf_key IN( 'network_status_text_val', 'nexus_com_rules_val', 'nexus_tax_explain_val', 'nexus_ca_home' )" );
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step10CustomTitle()
	{
		return "Upgrading commerce settings";
	}

	/**
	 * Translatables: Methods
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step11()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_paymethods', NULL, 'm_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Lang::saveCustom( 'nexus', "nexus_paymethod_{$row['m_id']}", $row['m_name'] );
				
				if ( $row['m_gateway'] === 'Manual' )
				{
					$conf = \unserialize( $row['m_settings'] );
					\IPS\Lang::saveCustom( 'nexus', "nexus_gateway_{$row['m_id']}_ins", $conf['details'] );
				}
				
				\IPS\Db::i()->update( 'nexus_paymethods', array(
					'm_settings'		=> json_encode( \unserialize( $row['m_settings'] ) ),
				), array( 'm_id=?', $row['m_id'] ) );
			}
			
			return $offset + 100;
		}
		else
		{
			unset( $_SESSION['_step11Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step11CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step11Count'] ) )
		{
			$_SESSION['_step11Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_paymethods' )->first();
		}

		return "Upgrading commerce payment methods (Upgraded so far: " . ( ( $limit > $_SESSION['_step11Count'] ) ? $_SESSION['_step11Count'] : $limit ) . ' out of ' . $_SESSION['_step11Count'] . ')';
	}

	/**
	 * Translatables: Donation Goals
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step12()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_donate_goals', NULL, 'd_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Lang::saveCustom( 'nexus', "nexus_donategoal_{$row['d_id']}", $row['d_name'] );
				\IPS\Lang::saveCustom( 'nexus', "nexus_donategoal_{$row['d_id']}_desc", \IPS\Text\Parser::parseStatic( $row['d_desc'], TRUE, NULL, NULL, TRUE, TRUE, TRUE ) );
			}
			
			return $offset + 100;
		}
		else
		{
			unset( $_SESSION['_step12Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step12CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step12Count'] ) )
		{
			$_SESSION['_step12Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_donate_goals' )->first();
		}

		return "Upgrading commerce donation goals (Upgraded so far: " . ( ( $limit > $_SESSION['_step12Count'] ) ? $_SESSION['_step12Count'] : $limit ) . ' out of ' . $_SESSION['_step12Count'] . ')';
	}

	/**
	 * Translatables: Package Groups
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step13()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_package_groups', NULL, 'pg_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Lang::saveCustom( 'nexus', "nexus_pgroup_{$row['pg_id']}", $row['pg_name'] );
				\IPS\Lang::saveCustom( 'nexus', "nexus_pgroup_{$row['pg_id']}_desc", '' );
			}
			
			return $offset + 100;
		}
		else
		{
			unset( $_SESSION['_step13Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step13CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step13Count'] ) )
		{
			$_SESSION['_step13Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_package_groups' )->first();
		}

		return "Upgrading commerce package groups (Upgraded so far: " . ( ( $limit > $_SESSION['_step13Count'] ) ? $_SESSION['_step13Count'] : $limit ) . ' out of ' . $_SESSION['_step13Count'] . ')';
	}

	/**
	 * Translatables: Packages
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step14()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_packages', NULL, 'p_id', array( $offset, 50 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Lang::saveCustom( 'nexus', "nexus_package_{$row['p_id']}", $row['p_name'] );
				\IPS\Lang::saveCustom( 'nexus', "nexus_package_{$row['p_id']}_assoc", $row['p_assoc_error'] );
				
				/* Previously, we were passing these values through \IPS\Text\LegacyParser::parseStatic(), however this is *also* done as a part of rebuildNonContentPosts for nexus_Admin, so they have been removed from here */
				\IPS\Lang::saveCustom( 'nexus', "nexus_package_{$row['p_id']}_desc", $row['p_desc'] );
				\IPS\Lang::saveCustom( 'nexus', "nexus_package_{$row['p_id']}_page", $row['p_page'] );
				
				$renewOptions = \unserialize( $row['p_renew_options'] );
				if( \is_array( $renewOptions ) AND \count( $renewOptions ) )
				{
					foreach ( $renewOptions as $k => $v )
					{
						$renewOptions[ $k ]['cost'] = array( \IPS\Settings::i()->nexus_currency => array( 'amount' => $renewOptions[ $k ]['price'], 'currency' => \IPS\Settings::i()->nexus_currency ) );
						unset( $renewOptions[ $k ]['price'] );
					}
				}
				
				$discounts = \unserialize( $row['p_discounts'] );
				if( \is_array( $discounts ) AND \count( $discounts ) )
				{
					foreach ( $discounts as $type => $_discounts )
					{
						foreach ( $_discounts as $k => $v )
						{
							if ( isset( $v['price'] ) )
							{
								$discounts[ $type ][ $k ]['price'] = array( \IPS\Settings::i()->nexus_currency => $v['price'] );
							}
						}
					}
				}
				
				\IPS\Db::i()->update( 'nexus_packages', array(
					'p_base_price'		=> json_encode( array( \IPS\Settings::i()->nexus_currency => array( 'amount' => $row['p_base_price'], 'currency' => \IPS\Settings::i()->nexus_currency ) ) ),
					'p_renew_options'	=> json_encode( $renewOptions ),
					'p_discounts'		=> json_encode( $discounts ),
					'p_reviews'			=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_reviews', array( 'review_product=?', $row['p_id'] ) )
				), array( 'p_id=?', $row['p_id'] ) );
				
				\IPS\Db::i()->insert( 'nexus_package_base_prices', array(
					'id'								=> $row['p_id'],
					\IPS\Settings::i()->nexus_currency	=> $row['p_base_price']
				) );
			}
			
			return $offset + 50;
		}
		else
		{
			unset( $_SESSION['_step14Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step14CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step14Count'] ) )
		{
			$_SESSION['_step14Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_packages' )->first();
		}

		return "Upgrading commerce packages (Upgraded so far: " . ( ( $limit > $_SESSION['_step14Count'] ) ? $_SESSION['_step14Count'] : $limit ) . ' out of ' . $_SESSION['_step14Count'] . ')';
	}

	/**
	 * Translatables: Support Departments
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step15()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_support_departments', NULL, 'dpt_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Lang::saveCustom( 'nexus', "nexus_department_{$row['dpt_id']}", $row['dpt_name'] );
			}
			
			return $offset + 100;
		}
		else
		{
			unset( $_SESSION['_step15Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step15CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step15Count'] ) )
		{
			$_SESSION['_step15Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_departments' )->first();
		}

		return "Upgrading commerce support departments (Upgraded so far: " . ( ( $limit > $_SESSION['_step15Count'] ) ? $_SESSION['_step15Count'] : $limit ) . ' out of ' . $_SESSION['_step15Count'] . ')';
	}

	/**
	 * Translatables: Shipping Rates
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step16()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_shipping', NULL, 's_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Lang::saveCustom( 'nexus', "nexus_shiprate_{$row['s_id']}", $row['s_name'] );
				\IPS\Lang::saveCustom( 'nexus', "nexus_shiprate_de_{$row['s_id']}", '' );
				
				\IPS\Db::i()->update( 'nexus_shipping', array(
					's_rates'	=> json_encode( \unserialize( $row['s_rates'] ) ),
				), array( 's_id=?', $row['s_id'] ) );
			}
			
			return $offset + 100;
		}
		else
		{
			unset( $_SESSION['_step16Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step16CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step16Count'] ) )
		{
			$_SESSION['_step16Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_shipping' )->first();
		}

		return "Upgrading commerce shipping rates (Upgraded so far: " . ( ( $limit > $_SESSION['_step16Count'] ) ? $_SESSION['_step16Count'] : $limit ) . ' out of ' . $_SESSION['_step16Count'] . ')';
	}

	/**
	 * Upgrade Support Severities
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step17()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_support_severities', NULL, 'sev_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Lang::saveCustom( 'nexus', "nexus_severity_{$row['sev_id']}", $row['sev_name'] );
			}
			
			/* 3.x did not allow for uploading an icon, but rather looped over a specific directory - we need to convert these */
			if ( $row['sev_icon'] )
			{
				$path = \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY . '/applications_addon/ips/nexus/skin_cp/images/severities/' . $row['sev_icon'] . '.png';
				if ( file_exists( $path ) )
				{
					try
					{
						$url = \IPS\File::create( 'nexus_Support', $row['sev_icon'] . '.png', @file_get_contents( $path ) );
						
						\IPS\Db::i()->update( 'nexus_support_severities', array( 'sev_icon' => (string) $url ), array( "sev_id=?", $row['sev_id'] ) );
					}
					catch( \Exception $e )
					{
						\IPS\Db::i()->update( 'nexus_support_severities', array( 'sev_icon' => NULL ), array( "sev_id=?", $row['sev_id'] ) );
					}
				}
				else
				{
					\IPS\Db::i()->update( 'nexus_support_severities', array( 'sev_icon' => NULL ), array( "sev_id=?", $row['sev_id'] ) );
				}
			}
			
			return $offset + 100;
		}
		else
		{
			unset( $_SESSION['_step17Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step17CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step17Count'] ) )
		{
			$_SESSION['_step17Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_severities' )->first();
		}

		return "Upgrading commerce support severities (Upgraded so far: " . ( ( $limit > $_SESSION['_step17Count'] ) ? $_SESSION['_step17Count'] : $limit ) . ' out of ' . $_SESSION['_step17Count'] . ')';
	}

	/**
	 * Translatables: Support Statuses
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step18()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_support_statuses', NULL, 'status_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Lang::saveCustom( 'nexus', "nexus_status_{$row['status_id']}_admin", $row['status_name'] );
				\IPS\Lang::saveCustom( 'nexus', "nexus_status_{$row['status_id']}_front", $row['status_public_name'] );
				\IPS\Lang::saveCustom( 'nexus', "nexus_status_{$row['status_id']}_set", $row['status_public_set'] );
			}
			
			return $offset + 100;
		}
		else
		{
			unset( $_SESSION['_step18Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step18CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step18Count'] ) )
		{
			$_SESSION['_step18Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_statuses' )->first();
		}

		return "Upgrading commerce support statuses (Upgraded so far: " . ( ( $limit > $_SESSION['_step18Count'] ) ? $_SESSION['_step18Count'] : $limit ) . ' out of ' . $_SESSION['_step18Count'] . ')';
	}

	/**
	 * Translatables: Tax Rates
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step19()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_tax', NULL, 't_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Lang::saveCustom( 'nexus', "nexus_tax_{$row['t_id']}", $row['t_name'] );
				
				$unserialized = \unserialize( $row['t_rate'] );
				$save = array();
				
				foreach ( $unserialized as $rate )
				{		
					$rateToSave = array( 'locations' => array(), 'rate' => \floatval( $rate['rate'] ) );
					if ( $rate['applies'] === '*' )
					{
						$rateToSave['locations'] = '*';
					}
					else
					{
						foreach ( $rate['applies'] as $location => $subLocations )
						{
							if ( \count( $subLocations ) )
							{
								$rateToSave['locations'][ $location ] = $subLocations;
							}
							else
							{
								$rateToSave['locations'][ $location ] = '*';
							}
						}
					}
					$save[] = $rateToSave;
				}
				
				\IPS\Db::i()->update( 'nexus_tax', array(
					't_rate'	=> json_encode( $save ),
				), array( 't_id=?', $row['t_id'] ) );
			}
			
			return $offset + 100;
		}
		else
		{
			unset( $_SESSION['_step19Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step19CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step19Count'] ) )
		{
			$_SESSION['_step19Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_tax' )->first();
		}

		return "Upgrading commerce tax rates (Upgraded so far: " . ( ( $limit > $_SESSION['_step19Count'] ) ? $_SESSION['_step19Count'] : $limit ) . ' out of ' . $_SESSION['_step19Count'] . ')';
	}

	/**
	 * Translatables: Support Stock Actions
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step20()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_support_stock_actions', NULL, 'action_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Lang::saveCustom( 'nexus', "nexus_stockaction_{$row['action_id']}", $row['action_name'] );

				\IPS\Db::i()->update( 'nexus_support_stock_actions', array( 'action_message' => \IPS\Text\Parser::parseStatic( $row['action_message'], TRUE, NULL, NULL, TRUE, TRUE, TRUE ), 'action_show_in' => '*' ), array( 'action_id=?', $row['action_id'] ) );
			}
			
			return $offset + 100;
		}
		else
		{
			unset( $_SESSION['_step20Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step20CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step20Count'] ) )
		{
			$_SESSION['_step20Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_stock_actions' )->first();
		}

		return "Upgrading commerce support stock actions (Upgraded so far: " . ( ( $limit > $_SESSION['_step20Count'] ) ? $_SESSION['_step20Count'] : $limit ) . ' out of ' . $_SESSION['_step20Count'] . ')';
	}

	/**
	 * Translatables: Customer Fields
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step21()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_customer_fields', NULL, 'f_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Lang::saveCustom( 'nexus', "nexus_ccfield_{$row['f_id']}", $row['f_name'] );

				$type = 'Text';
				$multiple = FALSE;
				switch ( $row['f_type'] )
				{
					case 'dropdown';
					case 'drop';
						$type = 'Select';
						break;

					case 'multi':
						$type = 'Select';
						$multiple = TRUE;
						break;

					case 'area':
						$type = 'TextArea';
						break;

					case 'user':
						$type = 'UserPass';
						break;

					case 'ftp':
						$type = 'Ftp';
						break;
				}
				
				\IPS\Db::i()->update( 'nexus_customer_fields', array(
					/* Use array_map to remove any occurances of \r after string split */
					'f_extra'		=> json_encode( array_map( 'trim', explode( "\n", $row['f_extra'] ) ) ),
					'f_type'		=> $type,
					'f_multiple'	=> $multiple,
				), array( 'f_id=?', $row['f_id'] ) );
			}
			
			return $offset + 100;
		}
		else
		{
			unset( $_SESSION['_step21Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step21CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step21Count'] ) )
		{
			$_SESSION['_step21Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_customer_fields' )->first();
		}

		return "Upgrading commerce customer fields (Upgraded so far: " . ( ( $limit > $_SESSION['_step21Count'] ) ? $_SESSION['_step21Count'] : $limit ) . ' out of ' . $_SESSION['_step21Count'] . ')';
	}

	/**
	 * Translatables: Package Fields
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step22()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_package_fields', NULL, 'cf_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Lang::saveCustom( 'nexus', "nexus_pfield_{$row['cf_id']}", $row['cf_name'] );
				
				$type = 'Text';
				$multiple = FALSE;
				switch ( $row['cf_type'] )
				{
					case 'dropdown';
					case 'drop';
						$type = 'Select';
						break;
					
					case 'multi':
						$type = 'Select';
						$multiple = TRUE;
						break;
						
					case 'area':
						$type = 'TextArea';
						break;
						
					case 'user':
						$type = 'UserPass';
						break;
						
					case 'ftp':
						$type = 'Ftp';
						break;
				}
				
				\IPS\Db::i()->update( 'nexus_package_fields', array(
					'cf_type'		=> $type,
					'cf_extra'		=> json_encode( explode( '<br />', $row['cf_extra'] ) ),
					'cf_multiple'	=> $multiple,
				), array( 'cf_id=?', $row['cf_id'] ) );
			}
			
			return $offset + 100;
		}
		else
		{
			unset( $_SESSION['_step22Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step22CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step22Count'] ) )
		{
			$_SESSION['_step22Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_package_fields' )->first();
		}

		return "Upgrading commerce package fields (Upgraded so far: " . ( ( $limit > $_SESSION['_step22Count'] ) ? $_SESSION['_step22Count'] : $limit ) . ' out of ' . $_SESSION['_step22Count'] . ')';
	}

	/**
	 * Translatables: Support Fields
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step23()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_support_fields', NULL, 'sf_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Lang::saveCustom( 'nexus', "nexus_cfield_{$row['sf_id']}", $row['sf_name'] );
				
				$type = 'Text';
				$multiple = FALSE;
				switch ( $row['cf_type'] )
				{
					case 'dropdown';
					case 'drop';
						$type = 'Select';
						break;
					
					case 'multi':
						$type = 'Select';
						$multiple = TRUE;
						break;
						
					case 'area':
						$type = 'TextArea';
						break;
						
					case 'user':
						$type = 'UserPass';
						break;
						
					case 'ftp':
						$type = 'Ftp';
						break;
				}
				
				\IPS\Db::i()->update( 'nexus_support_fields', array(
					'sf_type'		=> $type,
					'sf_extra'		=> json_encode( explode( '<br />', $row['sf_extra'] ) ),
					'sf_multiple'	=> $multiple,
				), array( 'sf_id=?', $row['sf_id'] ) );
			}
			
			return $offset + 100;
		}
		else
		{
			unset( $_SESSION['_step23Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step23CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step23Count'] ) )
		{
			$_SESSION['_step23Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_fields' )->first();
		}

		return "Upgrading commerce support fields (Upgraded so far: " . ( ( $limit > $_SESSION['_step23Count'] ) ? $_SESSION['_step23Count'] : $limit ) . ' out of ' . $_SESSION['_step23Count'] . ')';
	}

	/**
	 * Serialized: Support Request Field Values
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step24()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_support_requests', NULL, 'r_id', array( $offset, 500 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Db::i()->update( 'nexus_support_requests', array(
					'r_notify'	=> json_encode( \unserialize( $row['r_notify'] ) ),
					'r_cfields'	=> json_encode( \unserialize( $row['r_cfields'] ) )
				), array( 'r_id=?', $row['r_id'] ) );
			}
			
			return $offset + 500;
		}
		else
		{
			unset( $_SESSION['_step24Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step24CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step24Count'] ) )
		{
			$_SESSION['_step24Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_requests' )->first();
		}

		return "Upgrading commerce support request field values (Upgraded so far: " . ( ( $limit > $_SESSION['_step24Count'] ) ? $_SESSION['_step24Count'] : $limit ) . ' out of ' . $_SESSION['_step24Count'] . ')';
	}

	/**
	 * Serialized: Coupons
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step25()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_coupons', NULL, 'c_id', array( $offset, 500 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Db::i()->update( 'nexus_coupons', array( 'c_used_by' => json_encode( \unserialize( $row['c_used_by'] ) ) ), array( 'c_id=?', $row['c_id'] ) );
			}
			
			return $offset + 500;
		}
		else
		{
			unset( $_SESSION['_step25Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step25CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step25Count'] ) )
		{
			$_SESSION['_step25Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_coupons' )->first();
		}

		return "Upgrading commerce coupons (Upgraded so far: " . ( ( $limit > $_SESSION['_step25Count'] ) ? $_SESSION['_step25Count'] : $limit ) . ' out of ' . $_SESSION['_step25Count'] . ')';
	}

	/**
	 * Serialized: EOM
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step27()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_eom', NULL, 'eom_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Db::i()->update( 'nexus_eom', array( 'eom_notify' => json_encode( explode( ',', $row['eom_notify'] ) ) ), array( 'eom_id=?', $row['eom_id'] ) );
			}
			
			return $offset + 100;
		}
		else
		{
			unset( $_SESSION['_step27Count'] );
			return TRUE;
		}
	}	

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step27CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step27Count'] ) )
		{
			$_SESSION['_step27Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_eom' )->first();
		}

		return "Upgrading commerce EOM (Upgraded so far: " . ( ( $limit > $_SESSION['_step27Count'] ) ? $_SESSION['_step27Count'] : $limit ) . ' out of ' . $_SESSION['_step27Count'] . ')';
	}

	/**
	 * Serialized: Hosting Errors
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step28()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_hosting_errors', NULL, 'e_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Db::i()->update( 'nexus_hosting_errors', array( 'e_extra' => json_encode( \unserialize( $row['e_extra'] ) ) ), array( 'e_id=?', $row['e_id'] ) );
			}
			
			return $offset + 100;
		}
		else
		{
			unset( $_SESSION['_step28Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step28CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step28Count'] ) )
		{
			$_SESSION['_step28Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_hosting_errors' )->first();
		}

		return "Upgrading commerce hosting error logs (Upgraded so far: " . ( ( $limit > $_SESSION['_step28Count'] ) ? $_SESSION['_step28Count'] : $limit ) . ' out of ' . $_SESSION['_step28Count'] . ')';
	}

	/**
	 * Serialized: Invoices
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step29()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_invoices', NULL, 'i_id', array( $offset, 500 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Db::i()->update( 'nexus_invoices', array(
					'i_items'			=> json_encode( \unserialize( $row['i_items'] ) ),
					'i_status_extra'	=> json_encode( \unserialize( $row['i_status_extra'] ) )
				), array( 'i_id=?', $row['i_id'] ) );
			}
			
			return $offset + 500;
		}
		else
		{
			unset( $_SESSION['_step29Count'] );
			return TRUE;
		}
	}	

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step29CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step29Count'] ) )
		{
			$_SESSION['_step29Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_invoices' )->first();
		}

		return "Upgrading commerce invoices (Upgraded so far: " . ( ( $limit > $_SESSION['_step29Count'] ) ? $_SESSION['_step29Count'] : $limit ) . ' out of ' . $_SESSION['_step29Count'] . ')';
	}

	/**
	 * Serialized: License Keys
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step30()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_licensekeys', NULL, 'lkey_key', array( $offset, 500 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Db::i()->update( 'nexus_licensekeys', array(
					'lkey_activate_data' => json_encode( \unserialize( $row['lkey_activate_data'] ) ),
				), array( 'lkey_key=?', $row['lkey_key'] ) );
			}
			
			return $offset + 500;
		}
		else
		{
			unset( $_SESSION['_step30Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step30CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step30Count'] ) )
		{
			$_SESSION['_step30Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_licensekeys' )->first();
		}

		return "Upgrading commerce license keys (Upgraded so far: " . ( ( $limit > $_SESSION['_step30Count'] ) ? $_SESSION['_step30Count'] : $limit ) . ' out of ' . $_SESSION['_step30Count'] . ')';
	}

	/**
	 * Serialized: Payouts
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step31()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_payouts', NULL, 'po_id', array( $offset, 500 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Db::i()->update( 'nexus_payouts', array(
					'po_data' => json_encode( \unserialize( $row['po_data'] ) ),
				), array( 'po_id=?', $row['po_id'] ) );
			}
			
			return $offset + 500;
		}
		else
		{
			unset( $_SESSION['_step31Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step31CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step31Count'] ) )
		{
			$_SESSION['_step31Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_payouts' )->first();
		}

		return "Upgrading commerce payout requests (Upgraded so far: " . ( ( $limit > $_SESSION['_step31Count'] ) ? $_SESSION['_step31Count'] : $limit ) . ' out of ' . $_SESSION['_step31Count'] . ')';
	}

	/**
	 * Serialized: Product Options
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step32()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_product_options', NULL, 'opt_id', array( $offset, 500 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Db::i()->update( 'nexus_product_options', array(
					'opt_values' => json_encode( \unserialize( $row['opt_values'] ) ),
				), array( 'opt_id=?', $row['opt_id'] ) );
			}
			
			return $offset + 500;
		}
		else
		{
			unset( $_SESSION['_step32Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step32CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step32Count'] ) )
		{
			$_SESSION['_step32Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_product_options' )->first();
		}

		return "Upgrading commerce product options (Upgraded so far: " . ( ( $limit > $_SESSION['_step32Count'] ) ? $_SESSION['_step32Count'] : $limit ) . ' out of ' . $_SESSION['_step32Count'] . ')';
	}

	/**
	 * Serialized: Purchases
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step33()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_purchases', NULL, 'ps_id', array( $offset, 500 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				$extra = \unserialize( $row['ps_extra'] );
				if ( isset( $extra['nexus'] ) )
				{
					$extra = $extra['nexus'];
				}
				
				\IPS\Db::i()->update( 'nexus_purchases', array(
					'ps_custom_fields'		=> json_encode( \unserialize( $row['ps_custom_fields'] ) ),
					'ps_extra'				=> json_encode( $extra ),
					'ps_grouped_renewals'	=> $row['ps_grouped_renewals'] ? json_encode( \unserialize( $row['ps_grouped_renewals'] ) ) : '',
				), array( 'ps_id=?', $row['ps_id'] ) );
			}
			
			return $offset + 500;
		}
		else
		{
			unset( $_SESSION['_step33Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step33CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step33Count'] ) )
		{
			$_SESSION['_step33Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases' )->first();
		}

		return "Upgrading commmerce purchases (Upgraded so far: " . ( ( $limit > $_SESSION['_step33Count'] ) ? $_SESSION['_step33Count'] : $limit ) . ' out of ' . $_SESSION['_step33Count'] . ')';
	}

	/**
	 * Serialized: Shipments
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step34()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_ship_orders', NULL, 'o_id', array( $offset, 500 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Db::i()->update( 'nexus_ship_orders', array(
					'o_items'		=> json_encode( \unserialize( $row['o_items'] ) ),
					'o_extra'		=> $row['o_extra'] ? json_encode( \unserialize( $row['o_extra'] ) ) : '',
				), array( 'o_id=?', $row['o_id'] ) );
			}
			
			return $offset + 500;
		}
		else
		{
			unset( $_SESSION['_step34Count'] );
			return TRUE;
		}
	}	

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step34CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step34Count'] ) )
		{
			$_SESSION['_step34Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_ship_orders' )->first();
		}

		return "Upgrading commerce shipments (Upgraded so far: " . ( ( $limit > $_SESSION['_step34Count'] ) ? $_SESSION['_step34Count'] : $limit ) . ' out of ' . $_SESSION['_step34Count'] . ')';
	}

	/**
	 * Serialized: Transactions
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step35()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_transactions', NULL, 't_id', array( $offset, 500 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				\IPS\Db::i()->update( 'nexus_transactions', array(
					't_extra'		=> json_encode( \unserialize( $row['t_extra'] ) ),
					't_fraud'		=> json_encode( \unserialize( $row['t_fraud'] ) ),
				), array( 't_id=?', $row['t_id'] ) );
			}
			
			return $offset + 500;
		}
		else
		{
			unset( $_SESSION['_step35Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step35CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step35Count'] ) )
		{
			$_SESSION['_step35Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_transactions' )->first();
		}

		return "Upgrading commerce transactions (Upgraded so far: " . ( ( $limit > $_SESSION['_step35Count'] ) ? $_SESSION['_step35Count'] : $limit ) . ' out of ' . $_SESSION['_step35Count'] . ')';
	}
	
	/**
	 * Finish - This is run after all apps have been upgraded
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 * @note	We opted not to let users run this immediately during the upgrade because of potential issues (it taking a long time and users stopping it or getting frustrated) but we can revisit later
	 */
	public function finish()
    {
	   \IPS\Task::queue( 'core', 'RebuildPosts', array( 'class' => 'IPS\nexus\Package\Review' ), 2 );
	   \IPS\Task::queue( 'core', 'RebuildPosts', array( 'class' => 'IPS\nexus\Support\Reply' ), 2 );
	   \IPS\Task::queue( 'core', 'RebuildNonContentPosts', array( 'extension' => 'nexus_Admin' ), 2 );
	   \IPS\Task::queue( 'core', 'RebuildNonContentPosts', array( 'extension' => 'nexus_Customer' ), 2 );

        return TRUE;
    }
}