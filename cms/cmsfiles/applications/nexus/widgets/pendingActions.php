<?php
/**
 * @brief		Pending Actions Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		19 Sep 2014
 */

namespace IPS\nexus\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Pending Actions Widget
 */
class _pendingActions extends \IPS\Widget
{
	/**
	 * @brief	Options
	 */
	protected static $options = array(
		'transactions'	=> 'pending_transactions',
		'shipments'		=> 'pending_shipments',
		'withdrawals'	=> 'pending_widthdrawals',
		'support'		=> 'open_support_requests',
		'ads'			=> 'pending_advertisements',
	);
	
	/**
	 * @brief	Widget Key
	 */
	public $key = 'pendingActions';
	
	/**
	 * @brief	App
	 */
	public $app = 'nexus';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';
	
	/**
	 * Initialise this widget
	 *
	 * @return void
	 */ 
	public function init()
	{
		if ( !isset( $this->configuration['pendingActions_stuff'] ) )
		{
			$this->configuration['pendingActions_stuff'] = array_keys( static::$options );
		}
		
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'widgets.css', 'nexus', 'front' ) );
		parent::init();
	}
		
	/**
	 * Specify widget configuration
	 *
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	null|\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
	{
		$form = parent::configuration( $form );

 		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'pendingActions_stuff', $this->configuration['pendingActions_stuff'], TRUE, array( 'options' => static::$options ) ) );
 		
 		return $form;
 	} 
 	
	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		if ( !\IPS\Member::loggedIn()->isAdmin() )
		{
			return '';
		}
		
		/* Pending transactions *might* happen some weird way, so always get the count... but only show the count if we have fraud rules set up */
		$pendingTransactions = NULL;
		if ( \in_array( 'transactions', $this->configuration['pendingActions_stuff'] ) and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'transactions_manage' ) )
		{
			$pendingTransactions = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_transactions', array( 't_status=?', \IPS\nexus\Transaction::STATUS_HELD ) )->first();
			if ( !$pendingTransactions and !\IPS\Db::i()->select( 'COUNT(*)', 'nexus_fraud_rules' )->first() )
			{
				$pendingTransactions = NULL;
			}
		}
		
		/* Same with shipments */
		$pendingShipments = NULL;
		if ( \in_array( 'shipments', $this->configuration['pendingActions_stuff'] ) and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'shiporders_manage' ) )
		{
			$pendingShipments = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_ship_orders', array( 'o_status=?', \IPS\nexus\Shipping\Order::STATUS_PENDING ) )->first();
			if ( !$pendingShipments and !\IPS\Db::i()->select( 'COUNT(*)', 'nexus_packages_products', 'p_physical=1' )->first() )
			{
				$pendingShipments = NULL;
			}
		}
		
		/* And advertisements */
		$pendingAdvertisements = NULL;
		if ( \in_array( 'ads', $this->configuration['pendingActions_stuff'] ) and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'promotion', 'advertisements_manage' ) )
		{
			$pendingAdvertisements = \IPS\Db::i()->select( 'COUNT(*)', 'core_advertisements', array( 'ad_active=-1' ) )->first();
			if ( !$pendingAdvertisements and !\IPS\Db::i()->select( 'COUNT(*)', 'nexus_packages_ads' )->first() )
			{
				$pendingAdvertisements = NULL;
			}
		}
		
		/* Withdrawals will only be if enabled */
		$pendingWithdrawals = NULL;
		if ( \in_array( 'withdrawals', $this->configuration['pendingActions_stuff'] ) and \IPS\Settings::i()->nexus_payout and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'payouts_manage' ) )
		{
			$pendingWithdrawals = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_payouts', array( 'po_status=?', \IPS\nexus\Payout::STATUS_PENDING ) )->first();
		}
		
		/* Show support if there are departments we can see */
		$openSupportRequests = NULL;
		if ( \in_array( 'support', $this->configuration['pendingActions_stuff'] ) and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'support', 'requests_manage' ) and \IPS\Db::i()->select( '*', 'nexus_support_departments', array( "( dpt_staff='*' OR " . \IPS\Db::i()->findInSet( 'dpt_staff', \IPS\nexus\Support\Department::staffDepartmentPerms() ) . ')' ) ) )
		{
			$myStream = \IPS\nexus\Support\Stream::myStream();
			$openSupportRequests = $myStream->count( \IPS\Member::loggedIn() );
		}
				
		return $this->output( $pendingTransactions, $pendingShipments, $pendingWithdrawals, $openSupportRequests, $pendingAdvertisements );
	}
}