<?php
/**
 * @brief		subscriptions
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		09 Feb 2018
 */

namespace IPS\nexus\modules\admin\subscriptions;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * subscriptions
 */
class _subscriptions extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\nexus\Subscription\Package';
	
	/**
	 * Fetch any additional HTML for this row
	 *
	 * @param	object	$node	Node returned from $nodeClass::load()
	 * @return	NULL|string
	 */
	public function _getRowHtml( $node )
	{
		$active = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_member_subscriptions', array( 'sub_package_id=? and sub_active=1', $node->id ) )->first();
		$inactive = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_member_subscriptions', array( 'sub_package_id=? and sub_active=0', $node->id ) )->first();
		
		return \IPS\Theme::i()->getTemplate( 'subscription', 'nexus' )->rowHtml( $node, $node->priceBlurb(), $active, $inactive );
	}
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'subscriptions_manage' );

		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function manage()
	{	
		if ( \IPS\Settings::i()->nexus_subs_enabled )
		{
			\IPS\Output::i()->sidebar['actions']['settings'] = array(
					'primary'	=> false,
					'title'	=> 'settings',
					'icon'	=> 'cog',
					'link'	=> \IPS\Http\Url::internal('app=nexus&module=subscriptions&controller=subscriptions&do=settings')
				);
				
			parent::manage();
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'subscription' )->disabled();
		}
	}
	
	/**
	 * Convert a package to a subscription
	 *
	 * @return void
	 */
	public function convertToSubscription()
	{
		try
		{
			$package = \IPS\nexus\Package::load( \IPS\Request::i()->id );
			if ( $package->deleteOrMoveQueued() )
			{
				throw new \OutOfRangeException;
			}
		}
		catch( \OutOfRangeException $ex )
		{
			\IPS\Output::i()->error( 'node_error', '2X393/2', 404, '' );
		}
		
		$renewOptions = array();
		if ( $package->renew_options and $_renewOptions = json_decode( $package->renew_options, TRUE ) and \is_array( $_renewOptions ) )
		{
			foreach ( $_renewOptions as $option )
			{
				$costs = array();
				foreach ( $option['cost'] as $cost )
				{
					$costs[ $cost['currency'] ] = new \IPS\nexus\Money( $cost['amount'], $cost['currency'] );
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
				
				/* Catch any invalid renewal terms, these can occasionally appear from legacy IP.Subscriptions */
				try
				{
					$renewOptions[] = new \IPS\nexus\Purchase\RenewalTerm( $costs, new \DateInterval( "P{$option['term']}" . mb_strtoupper( $option['unit'] ) ), $tax, $option['add'] );
				}
				catch( \Exception $ex) {}
			}
		}
		
		$useRenewals = array_pop( $renewOptions );
		
		$form = new \IPS\Helpers\Form;
		$form->addHeader('nexus_subs_review_pricing');
		$form->add( new \IPS\nexus\Form\Money( 'sp_price', $package->base_price, TRUE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'sp_renews', !empty( $useRenewals ), FALSE, array( 'togglesOn' => array( 'sp_renew_options' ) ), NULL, NULL, NULL, 'sp_renews' ) );
		$form->add( new \IPS\nexus\Form\RenewalTerm( 'sp_renew_options', $useRenewals, NULL, array( 'allCurrencies' => TRUE ), NULL, NULL, NULL, 'sp_renew_options' ) );
		$form->add( new \IPS\Helpers\Form\Node( 'sp_tax', (int) $package->tax, FALSE, array( 'class' => 'IPS\nexus\Tax', 'zeroVal' => 'do_not_tax' ) ) );
		$form->addHeader('nexus_subs_after_conversion');
		$form->add( new \IPS\Helpers\Form\YesNo( 'sp_after_conversion_delete', FALSE, FALSE ) );
		
		if ( $values = $form->values() )
		{
			$sub = new \IPS\nexus\Subscription\Package;
			$sub->enabled = 1;
			$sub->tax = $values['sp_tax'] ? $values['sp_tax']->id : 0;
			$sub->gateways = ( isset( $values['sp_gateways'] ) and \is_array( $values['sp_gateways'] ) ) ? implode( ',', array_keys( $values['sp_gateways'] ) ) : '*';
			$sub->price = json_encode( $values['sp_price'] );
			
			foreach( array( 'primary_group', 'secondary_group') as $thingsWotAreTheSame )
			{
				$sub->$thingsWotAreTheSame = $package->$thingsWotAreTheSame;
			}
			
			/* Renewal options */
			if ( $values['sp_renews'] )
			{
				$renewOptions = array();
				$option = $values['sp_renew_options'];
				$term = $option->getTerm();
				
				$sub->renew_options = json_encode( array(
					'cost'	=> $option->cost,
					'term'	=> $term['term'],
					'unit'	=> $term['unit']
				) );
			}
			else
			{
				$sub->renew_options = '';
			}
			
			$sub->save();

			/* Language stuffs */
			\IPS\Lang::copyCustom( 'nexus', "nexus_package_{$package->id}", "nexus_subs_{$sub->id}" );
			\IPS\Lang::copyCustom( 'nexus', "nexus_package_{$package->id}_desc", "nexus_subs_{$sub->id}_desc" );
			
			/* Purchases */
			foreach( \IPS\Db::i()->select( '*', 'nexus_purchases', array( 'ps_app=? and ps_type=? and ps_active=1 and ps_cancelled=0 and ps_item_id=?', 'nexus', 'package', $package->id ) ) as $purchase )
			{
				try
				{
					$customer = \IPS\nexus\Customer::load( $purchase['ps_member'] );
					
					\IPS\Db::i()->update( 'nexus_purchases', array( 'ps_type' => 'subscription', 'ps_item_id' => $sub->id ), array( 'ps_id=?', $purchase['ps_id'] ) );
					
					$subscription = $sub->addMember( $customer );
					$subscription->purchase_id = $purchase['ps_id'];
					$subscription->invoice_id = $purchase['ps_original_invoice'];
					$subscription->expire = $purchase['ps_expire'];
					$subscription->start = $purchase['ps_start'];
					$subscription->save();
				}
				catch( \Exception $e ) { }
			}
			
			/* Delete original product */
			if ( $values['sp_after_conversion_delete'] )
			{
				$package->delete();
			}
			
			\IPS\Session::i()->log( 'acplogs__nexus_sub_converted', array( "nexus_subs_{$sub->id}" => TRUE ) );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=subscriptions&controller=subscriptions'), 'nexus_package_converted_lovely' );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('nexus_subs_convert');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'subscription', 'nexus' )->convert( $form, $package );
	}
	
	/**
	 * Enable
	 *
	 * @return	void
	 */
	public function enable()
	{
		\IPS\Session::i()->csrfCheck();
		
		\IPS\Settings::i()->changeValues( array( 'nexus_subs_enabled' => true ) );
		
		\IPS\Session::i()->log( 'acplog__subscription_settings' );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=subscriptions&controller=subscriptions') );
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{	
		/* Load package */
		try
		{
			$package = \IPS\nexus\Subscription\Package::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			return parent::delete();
		}
				
		/* Are there any purchases of this product? */
		if ( !isset( \IPS\Request::i()->confirmImplications ) and \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=?', 'nexus', 'subscription', $package->id ) )->first() )
		{
			\IPS\Output::i()->bypassCsrfKeyCheck = TRUE;
			$options = array(
				'product_delete_confirm'	=> \IPS\Http\Url::internal( "app=nexus&module=subscriptions&controller=subscriptions&do=delete&wasConfirmed=1&id={$package->_id}&confirmImplications=1" )->csrf(),
			);
			if ( $package->enabled )
			{
				$options['product_delete_hide'] = \IPS\Http\Url::internal( "app=nexus&module=subscriptions&controller=subscriptions&do=hide&id={$package->_id}" )->csrf();
			}
			$options['cancel'] = \IPS\Http\Url::internal( "app=nexus&module=subscriptions&controller=subscriptions" );
			
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->decision( 'subscription_delete_blurb', $options );
			return;
		}
				
		/* If not, just handle the delete as normal */		
		return parent::delete();		
	}
	
	/**
	 * Hide from store
	 *
	 * @return	void
	 */
	public function hide()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Load package */
		try
		{
			$package = \IPS\nexus\Subscription\Package::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '1X393/2', 404, '' );
		}
		
		/* Do it */
		$package->enabled = FALSE;
		$package->save();
		
		\IPS\Settings::i()->log( 'aplogs__nexus_sub_hidden', array( "nexus_subs_{$sub->id}" => TRUE ) );
		
		/* Redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=nexus&module=subscriptions&controller=subscriptions" ) );
	}
	
	/**
	 * Add a member for free!
	 *
	 * @return void
	 */
	protected function addMember()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Member( 'nexus_subs_member_to_add', NULL, TRUE, array(), function( $val )
		{
			if ( $val instanceof \IPS\Member )
			{
				$sub = \IPS\nexus\Subscription::loadByMember( $val, false );

				/* We have cannot have duplicate active subscriptions, so error out */
				if( $sub !== NULL )
				{
					throw new \InvalidArgumentException( 'nexus_subs_add_member_already_subscribed' );
				}
			}
		}, NULL, NULL, 'nexus_subs_member_to_add' ) );

		$package = \IPS\nexus\Subscription\Package::load( \IPS\Request::i()->id );
		if( $term = $package->renewalTerm() )
		{

			$newOption = json_decode( $package->renew_options, TRUE );

			$costs = array();
			foreach ( $newOption['cost'] as $data )
			{
				$costs[] = new \IPS\nexus\Money( $data['amount'], $data['currency'] );
			}

			switch ( $newOption['unit'] )
			{
				case 'd':
					$term = \IPS\Member::loggedIn()->language()->addToStack('renew_days', FALSE, array( 'pluralize' => array( $newOption['term'] ) ) );
					break;
				case 'm':
					$term = \IPS\Member::loggedIn()->language()->addToStack('renew_months', FALSE, array( 'pluralize' => array( $newOption['term'] ) ) );
					break;
				case 'y':
					$term = \IPS\Member::loggedIn()->language()->addToStack('renew_years', FALSE, array( 'pluralize' => array( $newOption['term'] ) ) );
					break;
			}

			$form->add( new \IPS\Helpers\Form\Radio( 'nexus_subs_free_period', NULL, TRUE, [
				'options' => [
					0 => 'nexus_subs_forever',
					1 => \IPS\Member::loggedIn()->language()->addToStack( 'nexus_subs_until_renew', FALSE, [ 'sprintf' => [ $newOption['term'], $term, \IPS\Member::loggedIn()->language()->formatList( $costs, \IPS\Member::loggedIn()->language()->get('or_list_format') ), $term ] ] )
				]
			] ) );
		}
		
		if ( $values = $form->values() )
		{
			$customer = \IPS\nexus\Customer::load( $values['nexus_subs_member_to_add']->member_id );
			$sub = \IPS\nexus\Subscription\Package::load( \IPS\Request::i()->id )->addMember( $customer, TRUE, (bool) $values['nexus_subs_free_period'] );
			$sub->added_manually = 1;
			$sub->save();
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=subscriptions&controller=subscriptions'), 'nexus_sub_member_added' );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('nexus_subs_add_member');
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Manage Settings
	 *
	 * @return	void
	 */
	protected function settings()
	{
		$groups = array();
		foreach ( \IPS\Member\Group::groups( FALSE, FALSE ) as $group )
		{
			$groups[ $group->g_id ] = $group->name;
		}
		
		$form = new \IPS\Helpers\Form;

		$form->addHeader('subscription_basic_settings');
		$form->add( new \IPS\Helpers\Form\YesNo( 'nexus_subs_enabled', \IPS\Settings::i()->nexus_subs_enabled, FALSE, array(), NULL, NULL, NULL, 'nexus_subs_enabled' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'nexus_subs_register', (int) \IPS\Settings::i()->nexus_subs_register, FALSE, [
			'options' => [
				0 => 'nexus_subs_register_none',
				1 => 'nexus_subs_register_reg',
				2 => 'nexus_subs_register_always'
			]
		], NULL, NULL, NULL, 'nexus_subs_register' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'nexus_subs_show_public', \IPS\Settings::i()->nexus_subs_show_public, FALSE, array(), NULL, NULL, NULL, 'nexus_subs_show_public' ) );
		$form->add( new \IPS\Helpers\Form\Interval( 'nexus_subs_invoice_grace', \IPS\Settings::i()->nexus_subs_invoice_grace ?: 0, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS ), NULL, NULL, NULL, 'nexus_subs_invoice_grace' ) );
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'nexus_subs_exclude_groups', explode( ',', \IPS\Settings::i()->nexus_subs_exclude_groups ), FALSE, array( 'options' => $groups, 'multiple' => TRUE ) ) );


		$form->addHeader('package_upgrade_downgrade');
		$form->add( new \IPS\Helpers\Form\YesNo( 'nexus_subs_upgrade_toggle', \IPS\Settings::i()->nexus_subs_upgrade > -1, FALSE, array( 'togglesOn' => array( 'nexus_subs_upgrade' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'nexus_subs_upgrade', \IPS\Settings::i()->nexus_subs_upgrade, FALSE, array( 'options' => array(
			0	=> 'p_upgrade_charge_none',
			1	=> 'p_upgrade_charge_full',
			2	=> 'p_upgrade_charge_prorate'
		) ), NULL, NULL, NULL, 'nexus_subs_upgrade' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'nexus_subs_downgrade_toggle', \IPS\Settings::i()->nexus_subs_downgrade > -1, FALSE, array( 'togglesOn' => array( 'nexus_subs_downgrade' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'nexus_subs_downgrade', \IPS\Settings::i()->nexus_subs_downgrade, FALSE, array( 'options' => array(
			0	=> 'p_downgrade_refund_none',
			1	=> 'p_downgrade_refund_full',
			2	=> 'p_downgrade_refund_prorate'
		)), NULL, NULL, NULL, 'nexus_subs_downgrade' ) );
		
		if ( $values = $form->values() )
		{
			if ( ! $values['nexus_subs_upgrade_toggle'] )
			{
				$values['nexus_subs_upgrade'] = -1;
			}
			
			if ( ! $values['nexus_subs_downgrade_toggle'] )
			{
				$values['nexus_subs_downgrade'] = -1;
			}
			
			foreach( array( 'nexus_subs_upgrade_toggle', 'nexus_subs_downgrade_toggle' ) as $field )
			{
				unset( $values[ $field ] );
			}
			
			$values['nexus_subs_exclude_groups'] = implode( ',', $values['nexus_subs_exclude_groups'] );

			$form->saveAsSettings( $values );
			
			\IPS\Session::i()->log( 'acplog__nexus_subs_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=subscriptions&controller=subscriptions') );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('settings');
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Redirect after save
	 *
	 * @param	\IPS\Node\Model	$old			A clone of the node as it was before or NULL if this is a creation
	 * @param	\IPS\Node\Model	$new			The node now
	 * @param	string			$lastUsedTab	The tab last used in the form
	 * @return	void
	 */
	protected function _afterSave( ?\IPS\Node\Model $old, \IPS\Node\Model $new, $lastUsedTab = FALSE )
	{
		$changes = array();
		if ( $old )
		{
			foreach ( array( 'tax', 'renew_options', 'primary_group', 'secondary_group' ) as $k )
			{
				if ( $old->$k != $new->$k )
				{
					$changes[ $k ] = $old->$k;
				}
			}
		}

		/* If something has changed, see if anyone has purchased */
		$purchases = 0;

		if( \count( $changes ) )
		{
			$purchases = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_member_subscriptions', array( 'sub_package_id=? AND sub_active=1 and sub_added_manually=0', $new->id ) )->first();
		}		

		/* Only show this screen if the package has been purchased. Otherwise even just copying a package and saving asks if you want to update
			existing purchases unnecessarily */
		if ( !empty( $changes ) AND $purchases )
		{		
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->decision( 'product_change_blurb', array(
				'product_change_blurb_existing'	=> \IPS\Http\Url::internal( "app=nexus&module=subscriptions&controller=subscriptions&do=updateExisting&id={$new->_id}" )->setQueryString( 'changes', json_encode( $changes ) )->csrf(),
				'product_change_blurb_new'		=> $this->url->setQueryString( array( 'root' => ( $new->parent() ? $new->parent()->_id : '' ) ) ),
			) );
		}
		else
		{
			return parent::_afterSave( $old, $new, $lastUsedTab );
		}
	}
	
	/**
	 * Update Existing Purchases
	 *
	 * @return	void
	 */
	public function updateExisting()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$package = \IPS\nexus\Subscription\Package::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '1X393/1', 404, '' );
		}
		
		$changes = json_decode( \IPS\Request::i()->changes, TRUE );
				
		if ( !isset( \IPS\Request::i()->processing ) )
		{
			if ( isset( $changes['renew_options'] ) )
			{
				\IPS\Output::i()->bypassCsrfKeyCheck = TRUE;
				$matrix = new \IPS\Helpers\Form\Matrix( 'matrix', 'continue' );
				$matrix->manageable = FALSE;
				
				$newOptions = array( '-' => \IPS\Member::loggedIn()->language()->addToStack('do_not_change') );
				$newOption = json_decode( $package->renew_options, TRUE );

				$costs = array();
				foreach ( $newOption['cost'] as $data )
				{
					$costs[] = new \IPS\nexus\Money( $data['amount'], $data['currency'] );
				}
				
				switch ( $newOption['unit'] )
				{
					case 'd':
						$term = \IPS\Member::loggedIn()->language()->addToStack('renew_days', FALSE, array( 'pluralize' => array( $newOption['term'] ) ) );
						break;
					case 'm':
						$term = \IPS\Member::loggedIn()->language()->addToStack('renew_months', FALSE, array( 'pluralize' => array( $newOption['term'] ) ) );
						break;
					case 'y':
						$term = \IPS\Member::loggedIn()->language()->addToStack('renew_years', FALSE, array( 'pluralize' => array( $newOption['term'] ) ) );
						break;
				}
				
				$newOptions['o'] = \IPS\Member::loggedIn()->language()->addToStack( 'renew_option', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $costs, \IPS\Member::loggedIn()->language()->get('or_list_format') ), $term ) ) );
				$newOptions['z'] = \IPS\Member::loggedIn()->language()->addToStack('remove_renewal_no_expire_leave');
				$newOptions['y'] = \IPS\Member::loggedIn()->language()->addToStack('remove_renewal_no_expire_reactivate');
				$newOptions['x'] = \IPS\Member::loggedIn()->language()->addToStack('remove_renewal_expire');
				$matrix->columns = array(
					'customers_currently_paying' => function( $key, $value, $data )
					{
						return $data[0];
					},
					'now_pay' => function( $key, $value, $data ) use ( $newOptions )
					{
						return new \IPS\Helpers\Form\Select( $key, $data[1], TRUE, array( 'options' => $newOptions, 'noDefault' => TRUE ) );
					},
				);
				
				if ( $changes['renew_options'] )
				{
					$oldOption = json_decode( $changes['renew_options'], TRUE );
					$costs = array();
					
					foreach ( $oldOption['cost'] as $data )
					{
						$costs[] = new \IPS\nexus\Money( $data['amount'], $data['currency'] );
					}
					
					switch ( $oldOption['unit'] )
					{
						case 'd':
							$term = \IPS\Member::loggedIn()->language()->addToStack('renew_days', FALSE, array( 'pluralize' => array( $oldOption['term'] ) ) );
							break;
						case 'm':
							$term = \IPS\Member::loggedIn()->language()->addToStack('renew_months', FALSE, array( 'pluralize' => array( $oldOption['term'] ) ) );
							break;
						case 'y':
							$term = \IPS\Member::loggedIn()->language()->addToStack('renew_years', FALSE, array( 'pluralize' => array( $oldOption['term'] ) ) );
							break;
					}
					
					$matrix->rows[ 1 ] = array( \IPS\Member::loggedIn()->language()->addToStack( 'renew_option', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $costs, \IPS\Member::loggedIn()->language()->get('or_list_format') ), $term ) ) ), "o" );
				}
				
				if ( $values = $matrix->values() )
				{
					$data = json_decode( $changes['renew_options'], TRUE );
					$changes['renew_options'] = array();
					if( !empty( $data ) )
					{
						$changes['renew_options'] = array( 'old' => $data, 'new' => $values[1]['now_pay'] );
					}
				}
				else
				{					
					\IPS\Output::i()->output .= $matrix;
					return;
				}
			}
		}

		if ( ( isset( $changes['renew_options'] ) or isset( $changes['tax'] ) ) and !isset( \IPS\Request::i()->ba ) )
		{
			$needBaPrompt = FALSE;
			$canChangeOptions = FALSE;
			if ( isset( $changes['renew_options'] ) and !\in_array( $changes['renew_options']['new'], array( '-', 'x', 'y', 'z' ) ) )
			{
				$needBaPrompt = TRUE;
				$canChangeOptions = TRUE;
			}
			if ( isset( $changes['tax'] ) )
			{
				$needBaPrompt = TRUE;
			}
			
			if ( $needBaPrompt and $withBillingAgreement = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=? AND ps_billing_agreement>0 AND ba_canceled=0', 'nexus', 'subscription', $package->id ) )->join( 'nexus_billing_agreements', 'ba_id=ps_billing_agreement' )->first() )
			{
				$options = array(
					'change_renew_ba_skip'			=> \IPS\Http\Url::internal( "app=nexus&module=subscriptions&controller=subscriptions&do=updateExisting" )->setQueryString( array(
						'id'		=> \IPS\Request::i()->id,
						'changes'	=> json_encode( $changes ),
						'processing'=> 1,
						'ba'		=> 0
					) )->csrf(),
					'change_renew_ba_cancel'		=> \IPS\Http\Url::internal( "app=nexus&module=subscriptions&controller=subscriptions&do=updateExisting" )->setQueryString( array(
						'id'		=> \IPS\Request::i()->id,
						'changes'	=> json_encode( $changes ),
						'processing'=> 1,
						'ba'		=> 1
					) )->csrf()
				);
				
				if ( $canChangeOptions )
				{
					$options['change_renew_ba_go_back'] = \IPS\Http\Url::internal( "app=nexus&module=subscriptions&controller=subscriptions&do=updateExisting" )->setQueryString( array(
						'id'		=> \IPS\Request::i()->id,
						'changes'	=> \IPS\Request::i()->changes,
					) )->csrf();
				}
				
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->decision( 'change_renew_ba_blurb', $options );
				return;
			}			
		}
				
		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect(
			\IPS\Http\Url::internal( "app=nexus&module=subscriptions&controller=subscriptions&do=updateExisting&id=1&changes=secondary_group" )->setQueryString( array(
				'id'		=> \IPS\Request::i()->id,
				'changes'	=> json_encode( $changes ),
				'processing'=> 1,
				'ba'		=> isset( \IPS\Request::i()->ba ) ? \IPS\Request::i()->ba : 0
			) )->csrf(),
			function( $data ) use ( $package, $changes )
			{
				if( !\is_array( $data ) )
				{
					$data['offset'] = 0;
					$data['lastId'] = 0;
				}

				$select = \IPS\Db::i()->select( '*', 'nexus_purchases', array( "ps_id>? and ps_app=? and ps_type=? and ps_item_id=?", $data['lastId'], 'nexus', 'subscription', $package->id ), 'ps_id', 1 );
				
				try
				{
					$purchase = \IPS\nexus\Purchase::constructFromData( $select->first() );
					$total = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( "ps_app=? and ps_type=? and ps_item_id=?", 'nexus', 'subscription', $package->id ) )->first();
					
					$package->updatePurchase( $purchase, $changes, \IPS\Request::i()->ba );

					return array( [ 'offset' => ++$data['offset'], 'lastId' => $purchase->id ], \IPS\Member::loggedIn()->language()->get('processing'), 100 / $total * $data['offset'] );
				}
				catch ( \UnderflowException $e )
				{
					return NULL;
				}
				
			},
			function() use ( $package )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=nexus&module=subscriptions&controller=subscriptions" )->setQueryString( array( 'root' => ( $package->parent() ? $package->parent()->_id : '' ) ) ) );
			}
		);
	}

}