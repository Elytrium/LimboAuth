<?php
/**
 * @brief		Subscribers
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		14 Feb 2018
 */

namespace IPS\nexus\modules\admin\subscriptions;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Subscribers
 */
class _subscribers extends \IPS\Dispatcher\Controller
{	
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'subscribers_manage' );
		parent::execute();
	}
	
	/**
	 * Manage 
	 *
	 * @return	void
	 */
	protected function manage()
	{		
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'nexus_member_subscriptions', \IPS\Http\Url::internal( 'app=nexus&module=subscriptions&controller=subscribers' ) );
		$table->joins = array(
			array(
				'select'	=> 'core_members.*',
				'from'		=> 'core_members',
				'where'		=> 'core_members.member_id=nexus_member_subscriptions.sub_member_id'
			),
			array(
				'select'	=> 'nexus_customers.cm_first_name, nexus_customers.cm_last_name',
				'from'		=> 'nexus_customers',
				'where'		=> 'core_members.member_id=nexus_customers.member_id'
			)
		);
		
		$table->include = array( 'photo', 'sub_member_id', 'cm_last_name', 'cm_first_name', 'email', 'sub_package_id', 'sub_active', 'sub_start', 'sub_expire');
		$table->noSort = array( 'photo');
		$table->sortBy = $table->sortBy ?: 'sub_start';
		$table->sortDirection = $table->sortDirection ?: 'desc';
		$table->langPrefix = 'nexus_';
		
		$table->quickSearch = 'email';
		
		$table->parsers = array(
			'photo'	=> function( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate('customers')->rowPhoto( \IPS\Member::constructFromData( $row ) );
			},
			'sub_member_id' => function( $val ) {
				return \IPS\Theme::i()->getTemplate('global', 'nexus')->userLink( \IPS\Member::load( $val ) );
			},
			'sub_package_id' => function( $val ) {
				try
				{
					return \IPS\Theme::i()->getTemplate('subscription', 'nexus')->packageLink( \IPS\nexus\Subscription\Package::load( $val ) );
				}
				catch( \OutOfRangeException $e ) { }
			},
			'sub_start'	=> function( $val ) {
				return \IPS\DateTime::ts( $val );
			},
			'sub_expire'	=> function( $val, $row ) {
				return $val ? \IPS\nexus\Subscription::constructFromData( $row )->_expire : '';
			},
			'sub_active'    => function( $val ) {
				return \IPS\Theme::i()->getTemplate('subscription', 'nexus')->status( $val );
			}
		);
		
		$table->rowButtons = function( $row ) {
			$return = array();
			
			if ( $row['sub_purchase_id'] )
			{
				$return['purchase']	= array(
					'title'	=> 'nexus_subs_view_purchase',
					'icon'	=> 'search',
					'link'	=> \IPS\Http\Url::internal( "app=nexus&module=subscriptions&controller=subscribers&do=findPurchase&id=" . $row['sub_id'] )
				);
			}
			
			return $return;
		};
			
		$table->filters = array(
			'nexus_subs_filter_active'	 => array( 'sub_active=1' ),
			'nexus_subs_filter_inactive' => array( 'sub_active=0' ),
			'nexus_subs_filter_renews'	 => array( 'sub_active=1 and sub_renews=1' )
		);
		
		$table->advancedSearch = array(
			'cm_first_name'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'cm_last_name'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'email'				=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'name'				=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'sub_expire'	 => \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'sub_start'	     => \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'sub_package_id' => array( \IPS\Helpers\Table\SEARCH_NODE, array(
				'class'				=> '\IPS\nexus\Subscription\Package',
				'zeroVal'			=> 'any'
			) ),
		);
		
		/* Breadcrumb? */
		if ( isset( \IPS\Request::i()->nexus_sub_package_id ) )
		{
			try
			{
				$package = \IPS\nexus\Subscription\Package::load( \IPS\Request::i()->nexus_sub_package_id );
				\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=nexus&module=subscriptions&controller=subscriptions&id=" . $package->id ), $package->_title );
			}
			catch( \OutOfRangeException $e ) { }
		}
		
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('r__subscribers');
		\IPS\Output::i()->output = (string) $table;
	}
	
	/**
	 * Find an purchase and redirect to it.
	 *
	 * @return	void
	 */
	protected function findPurchase()
	{
		try
		{
			$sub = \IPS\nexus\Subscription::load( \IPS\Request::i()->id );
			$purchase = \IPS\nexus\Purchase::load( $sub->purchase_id );
			\IPS\Output::i()->redirect( $purchase->acpUrl() );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'nexus_sub_no_purchase', '2X378/2', 404, '' );
		}
	}
	
}