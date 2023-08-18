<?php
/**
 * @brief		Member Sync
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		31 Mar 2014
 */

namespace IPS\nexus\extensions\core\MemberSync;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member Sync
 */
class _Nexus
{	
	/**
	 * Account Created
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	void
	 */
	public function onCreateAccount( $member )
	{
		if ( $member->email )
		{
			\IPS\Db::i()->update( 'nexus_support_requests', array( 'r_member' => $member->member_id ), array( 'r_email=? AND r_member=0', $member->email ) );
			\IPS\Db::i()->update( 'nexus_support_replies', array( 'reply_member' => $member->member_id ), array( 'reply_email=? AND reply_member=0', $member->email ) );
		}
	}
	
	/**
	 * Member has logged on
	 *
	 * @param	\IPS\Member	$member		Member that logged in
	 * @return	void
	 */
	public function onLogin( $member )
	{
		if ( isset( \IPS\Request::i()->cookie['cm_reg'] ) and \IPS\Request::i()->cookie['cm_reg'] )
		{			
			try
			{
				$invoice = \IPS\nexus\Invoice::load( \IPS\Request::i()->cookie['cm_reg'] );
				
				\IPS\Request::i()->setCookie( 'cm_reg', 0 );
				
				if ( !$invoice->member->member_id )
				{
					$invoice->member = $member;
					$invoice->save();
				}
				
				if ( $invoice->member->member_id === $member->member_id )
				{
					\IPS\Output::i()->redirect( $invoice->checkoutUrl() );
				}
			}
			catch ( \Exception $e )
			{
				\IPS\Request::i()->setCookie( 'cm_reg', 0 );
			}
		}
	}
	
	/**
	 * Member is merged with another member
	 *
	 * @param	\IPS\Member	$member		Member being kept
	 * @param	\IPS\Member	$member2	Member being removed
	 * @return	void
	 */
	public function onMerge( $member, $member2 )
	{
		\IPS\Db::i()->update( 'nexus_customer_addresses', array( 'member' => $member->member_id ), array( '`member`=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'nexus_customer_cards', array( 'card_member' => $member->member_id ), array( 'card_member=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'nexus_invoices', array( 'i_member' => $member->member_id ), array( 'i_member=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'nexus_purchases', array( 'ps_member' => $member->member_id ), array( 'ps_member=?', $member2->member_id ) );		
		\IPS\Db::i()->update( 'nexus_purchases', array( 'ps_pay_to' => $member->member_id ), array( 'ps_pay_to=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'nexus_transactions', array( 't_member' => $member->member_id ), array( 't_member=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'nexus_alternate_contacts', array( 'main_id' => $member->member_id ), array( 'main_id=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'nexus_alternate_contacts', array( 'alt_id' => $member->member_id ), array( 'alt_id=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'nexus_support_streams', array( 'stream_owner' => $member->member_id ), array( 'stream_owner=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'nexus_billing_agreements', array( 'ba_member' => $member->member_id ), array( 'ba_member=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'nexus_support_requests', array( 'r_member' => $member->member_id ), array( 'r_member=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'nexus_support_requests', array( 'r_last_reply_by' => $member->member_id ), array( 'r_last_reply_by=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'nexus_support_replies', array( 'reply_member' => $member->member_id ), array( 'reply_member=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'nexus_support_ratings', array( 'rating_from' => $member->member_id ), array( 'rating_from=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'nexus_support_ratings', array( 'rating_staff' => $member->member_id ), array( 'rating_staff=?', $member2->member_id ) );

		\IPS\Db::i()->delete( 'nexus_customer_spend', array( 'spend_member_id=?', $member2->member_id ) );
	
		\IPS\Db::i()->delete( 'nexus_alternate_contacts', array( 'main_id = alt_id' ) );

		/* Account Credit */
		$customerToKeep = \IPS\nexus\Customer::load( $member->member_id );
		$creditToKeep = $customerToKeep->cm_credits;
		$creditToMerge = \IPS\nexus\Customer::load( $member2->member_id )->cm_credits;

		foreach( \IPS\nexus\Money::currencies() as $currency )
		{
			if( isset( $creditToMerge[$currency] ) )
			{
				if( isset( $creditToKeep[$currency] ) )
				{
					$creditToKeep[$currency]->amount = $creditToKeep[$currency]->amount->add( $creditToMerge[$currency]->amount );
				}
				else
				{
					$creditToKeep[$currency] = $creditToMerge[$currency];
				}
			}
		}
		$customerToKeep->cm_credits = $creditToKeep;
		$customerToKeep->save();
		
		/* Subscription packages */
		if ( $keepSub = \IPS\nexus\Subscription::loadActiveByMember( $member ) )
		{
			if ( $dropSub = \IPS\nexus\Subscription::loadActiveByMember( $member2 ) AND $package = \IPS\nexus\Subscription\Package::load( $dropSub->package_id ) )
			{
				$package->removeMember(  \IPS\nexus\Customer::load( $member2->member_id ) );
			}
		}

		/* Recount total spend */
		$customerToKeep->recountTotalSpend();
	}
	
	/**
	 * Member is deleted
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	void
	 */
	public function onDelete( $member )
	{
		\IPS\Db::i()->delete( 'nexus_customer_addresses', array( '`member`=?', $member->member_id ) );
		\IPS\Db::i()->delete( 'nexus_customer_cards', array( 'card_member=?', $member->member_id ) );
		\IPS\Db::i()->delete( 'nexus_customers', array( 'member_id=?', $member->member_id ) );
		\IPS\Db::i()->delete( 'nexus_alternate_contacts', array( 'main_id=?', $member->member_id ) );
		\IPS\Db::i()->delete( 'nexus_alternate_contacts', array( 'alt_id =?', $member->member_id ) );
		\IPS\Db::i()->delete( 'nexus_support_request_log', array( "rlog_member=?", $member->member_id ) );
		\IPS\Db::i()->update( 'nexus_support_requests', array( 'r_staff' => 0 ), array( "r_staff=?", $member->member_id ) );
		
		foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_purchases', array( 'ps_member=?', $member->member_id ) ), 'IPS\nexus\Purchase' ) as $purchase )
		{
			$purchase->delete();
		}

		foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_billing_agreements', array( 'ba_member=?', $member->member_id ) ), '\IPS\nexus\Customer\BillingAgreement' ) as $agreement )
		{
			try
			{
				$agreement->cancel();
			}
			catch ( \Exception $e ) {}
		}
		
		\IPS\Db::i()->update( 'nexus_purchases', array( 'ps_pay_to' => 0 ), array( 'ps_pay_to=?', $member->member_id ) );
		\IPS\Db::i()->delete( 'nexus_support_streams', array( 'stream_owner=?', $member->member_id ) );
		
		/* Subscriptions (mop up orphaned members) */
		\IPS\Db::i()->delete( 'nexus_member_subscriptions', array( "sub_member_id=?", $member->member_id ) );

		\IPS\Db::i()->delete( 'nexus_customer_spend', array( 'spend_member_id=?', $member->member_id ) );
	}

	/**
	 * Member account has been updated
	 *
	 * @param	$member		\IPS\Member	Member updating profile
	 * @param	$changes	array		The changes
	 * @return	void
	 */
	public function onProfileUpdate( $member, $changes )
	{
		$refresh		= FALSE;
		$adminGroups	= array_keys( \IPS\Member::administrators()['g'] );
				
		if ( $member->inGroup( $adminGroups ) )
		{
			$refresh = TRUE;
		}
		
		/* Moving out of an admin group? */
		if ( $refresh === FALSE AND ( isset( $changes['member_group_id'] ) OR isset( $changes['mgroup_others'] ) ) )
		{
			if ( isset( $changes['member_group_id'] ) AND \in_array( $changes['member_group_id'], $adminGroups ) )
			{
				$refresh = TRUE;
			}
			
			if ( isset( $changes['mgroup_others'] ) )
			{
				foreach( explode( ',', $changes['mgroup_others'] ) AS $group )
				{
					if ( \in_array( $group, $adminGroups ) )
					{
						$refresh = TRUE;
						break;
					}
				}
			}
		}
		
		if ( $refresh === TRUE and isset( \IPS\Data\Store::i()->supportStaff ) )
		{
			unset( \IPS\Data\Store::i()->supportStaff );
		}
	}
}
