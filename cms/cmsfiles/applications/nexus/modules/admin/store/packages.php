<?php
/**
 * @brief		Packages
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		29 Apr 2014
 */

namespace IPS\nexus\modules\admin\store;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Packages
 */
class _packages extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\nexus\Package\Group';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'packages_manage' );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_store.js', 'nexus', 'admin' ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__nexus_store_packages');
		parent::execute();
	}
		
	/**
	 * Get Root Buttons
	 *
	 * @return	array
	 */
	public function _getRootButtons()
	{
		$buttons = parent::_getRootButtons();
		
		if ( isset( $buttons['add'] ) )
		{
			$buttons['add']['title'] = 'create_new_group';
		}
		
		return $buttons;
	}
	
	/**
	 * Fetch any additional HTML for this row
	 *
	 * @param	object	$node	Node returned from $nodeClass::load()
	 * @return	NULL|string
	 */
	public function _getRowHtml( $node )
	{
		if ( $node instanceof \IPS\nexus\Package and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'purchases_view' ) )
		{
			$active = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=? AND ps_active=1', 'nexus', 'package', $node->_id ) )->first();
			$expired = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=? AND ps_active=0 AND ps_cancelled=0', 'nexus', 'package', $node->_id ) )->first();
			$canceled = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=? AND ps_active=0 AND ps_cancelled=1', 'nexus', 'package', $node->_id ) )->first();
			
			return \IPS\Theme::i()->getTemplate( 'store', 'nexus' )->productRowHtml( $node, $active, $expired, $canceled );
		}
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
		if ( !( $new instanceof \IPS\nexus\Package ) )
		{
			return parent::_afterSave( $old, $new, $lastUsedTab );
		}
				
		$changes = array();
		if ( $old )
		{
			foreach ( $new::updateableFields() as $k )
			{
				if ( $old->$k != $new->$k )
				{
					$changes[ $k ] = $old->$k;
				}
			}
		}

		/* Clear cache */
		unset( \IPS\Data\Store::i()->nexusPackagesWithReviews );

		/* If something has changed, see if anyone has purchased */
		$purchases = 0;

		if( \count( $changes ) )
		{
			$purchases = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=?', 'nexus', 'package', $new->id ) )->first();
		}		
		
		/* Only show this screen if the package has been purchased. Otherwise even just copying a package and saving asks if you want to update
			existing purchases unnecessarily */
		if ( !empty( $changes ) AND $purchases )
		{		
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->decision( 'product_change_blurb', array(
				'product_change_blurb_existing'	=> \IPS\Http\Url::internal( "app=nexus&module=store&controller=packages&do=updateExisting&id={$new->_id}" )->setQueryString( 'changes', json_encode( $changes ) )->csrf(),
				'product_change_blurb_new'		=> $this->url->setQueryString( array( 'root' => ( $new->parent() ? $new->parent()->_id : '' ) ) ),
			) );
		}
		else
		{
			return parent::_afterSave( $old, $new, $lastUsedTab );
		}
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		/* If &subnode=1 is not in the URL, we are deleting a group and not a package, so we don't need to check the package */
		if( !\IPS\Request::i()->subnode )
		{
			return parent::delete();
		}

		/* Load package */
		try
		{
			$package = \IPS\nexus\Package::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			return parent::delete();
		}
		
		/* Are there any purchases of this product? */
		$active = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=? AND ps_active=1', 'nexus', 'package', $package->_id ) )->first();
		$expiredRenewable = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=? AND ps_active=0 AND ps_cancelled=0 AND ps_renewals>0', 'nexus', 'package', $package->_id ) )->first();
		$expredNonRenewable = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=? AND ps_active=0 AND ps_cancelled=0 AND ps_renewals=0', 'nexus', 'package', $package->_id ) )->first();
		$canceledCanBeReactivated = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=? AND ps_active=0 AND ps_cancelled=1 AND ps_can_reactivate=1', 'nexus', 'package', $package->_id ) )->first();
		$canceledCannotBeReactivated = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=? AND ps_active=0 AND ps_cancelled=1 AND ps_can_reactivate=0', 'nexus', 'package', $package->_id ) )->first();
		if ( $active or $expiredRenewable or $canceledCanBeReactivated )
		{			
			$upgradeTo = FALSE;
			$prices = json_decode( $package->base_price, TRUE );
			if ( $package->renew_options )
			{
				$renewalOptions = json_decode( $package->renew_options, TRUE );
				if ( !empty( $renewalOptions ) )
				{
					$option = array_shift( $renewalOptions );						
					if ( $option['add'] )
					{
						foreach ( $prices as $currency => $_price )
						{
							$prices[ $currency ]['amount'] += ( $option['cost'][ $currency ]['amount'] );
						}
					}
				}
			}
			foreach ( $package->parent()->children() as $_package )
			{
				if ( $_package->id === $package->id )
				{
					continue;
				}
				
				$_prices = json_decode( $_package->base_price, TRUE );
				if ( $_package->renew_options )
				{
					$renewalOptions = json_decode( $_package->renew_options, TRUE );
					if ( !empty( $renewalOptions ) )
					{
						$option = array_shift( $renewalOptions );						
						if ( $option['add'] )
						{
							foreach ( $_prices as $currency => $_price )
							{
								$_prices[ $currency ]['amount'] += ( $option['cost'][ $currency ]['amount'] );
							}
						}
					}
				}
				
				foreach ( $_prices as $currency => $_price )
				{
					if ( ( $_price['amount'] <= $prices[ $currency ]['amount'] and $_package->allow_upgrading ) or ( $_price['amount'] > $prices[ $currency ]['amount'] and $_package->allow_downgrading ) )
					{
						$upgradeTo = TRUE;
						break 2;
					}
				}
			}
			
			\IPS\Output::i()->title = $package->_title;
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'store' )->productDeleteWarning( $package, $active, $expiredRenewable, $expredNonRenewable, $canceledCanBeReactivated, $canceledCannotBeReactivated, $upgradeTo );
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
			$package = \IPS\nexus\Package::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X249/3', 404, '' );
		}
		
		/* Do it */
		$package->store = FALSE;
		$package->reg = FALSE;
		$package->save();
		
		\IPS\Session::i()->log( 'acplogs__nexus_package_hidden', array( 'nexus_package_' . $package->id => TRUE ) );
		
		/* Redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=nexus&module=store&controller=packages" )->setQueryString( array( 'root' => ( $package->parent() ? $package->parent()->_id : '' ) ) ) );
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
			$package = \IPS\nexus\Package::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X249/1', 404, '' );
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
				$existingRenewOptions = json_decode( $package->renew_options, TRUE );

				if( !\is_array( $existingRenewOptions ) )
				{
					$existingRenewOptions = array();
				}

				foreach ( $existingRenewOptions as $k => $newOption )
				{
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
					
					$newOptions[ "o{$k}" ] = \IPS\Member::loggedIn()->language()->addToStack( 'renew_option', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $costs, \IPS\Member::loggedIn()->language()->get('or_list_format') ), $term ) ) );
				}
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
					foreach ( json_decode( $changes['renew_options'], TRUE ) as $k => $oldOption )
					{
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
						
						$matrix->rows[ $k ] = array( \IPS\Member::loggedIn()->language()->addToStack( 'renew_option', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $costs, \IPS\Member::loggedIn()->language()->get('or_list_format') ), $term ) ) ), "o{$k}" );
					}
				}
				$matrix->rows['x'] = array( 'any_other_amount', '-' );
				
				if ( $values = $matrix->values() )
				{	
					$renewOptions = json_decode( $changes['renew_options'], TRUE );
					$changes['renew_options'] = array();
					if( !empty( $renewOptions ) )
					{
						foreach ( $renewOptions as $k => $data )
						{
							$changes['renew_options'][ $k ] = array( 'old' => $data, 'new' =>  $values[ $k ]['now_pay'] );
						}
					}

					$changes['renew_options']['x'] = array( 'old' => 'x', 'new' => $values['x']['now_pay'] );
				}
				else
				{					
					\IPS\Output::i()->output .= $matrix;
					return;
				}
			}
		}
				
		if ( ( isset( $changes['tax'] ) or isset( $changes['renew_options'] ) ) and !isset( \IPS\Request::i()->ba ) )
		{
			$needBaPrompt = FALSE;
			$canChangeOptions = FALSE;
			if ( isset( $changes['renew_options'] ) )
			{
				foreach ( $changes['renew_options'] as $ro )
				{
					if ( !\in_array( $ro['new'], array( '-', 'x', 'y', 'z' ) ) )
					{
						$canChangeOptions = TRUE;
						$needBaPrompt = TRUE;
						break;
					}
				}
			}
			if ( isset( $changes['tax'] ) )
			{
				$needBaPrompt = TRUE;
			}
			
			if ( $needBaPrompt and $withBillingAgreement = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=? AND ps_billing_agreement>0 AND ba_canceled=0', 'nexus', 'package', $package->id ) )->join( 'nexus_billing_agreements', 'ba_id=ps_billing_agreement' )->first() )
			{
				$options = array(
					'change_renew_ba_skip'			=> \IPS\Http\Url::internal( "app=nexus&module=store&controller=packages&do=updateExisting" )->setQueryString( array(
						'id'		=> \IPS\Request::i()->id,
						'changes'	=> json_encode( $changes ),
						'processing'=> 1,
						'ba'		=> 0
					) )->csrf(),
					'change_renew_ba_cancel'		=> \IPS\Http\Url::internal( "app=nexus&module=store&controller=packages&do=updateExisting" )->setQueryString( array(
						'id'		=> \IPS\Request::i()->id,
						'changes'	=> json_encode( $changes ),
						'processing'=> 1,
						'ba'		=> 1
					) )->csrf()
				);
				if ( $canChangeOptions )
				{
					$options['change_renew_ba_go_back'] = \IPS\Http\Url::internal( "app=nexus&module=store&controller=packages&do=updateExisting" )->setQueryString( array(
						'id'		=> \IPS\Request::i()->id,
						'changes'	=> \IPS\Request::i()->changes,
					) )->csrf();
				}
				
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->decision( 'change_renew_ba_blurb', $options );
				return;
			}			
		}
				
		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect(
			\IPS\Http\Url::internal( "app=nexus&module=store&controller=packages&do=updateExisting&id=1&changes=secondary_group" )->setQueryString( array(
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

				$select = \IPS\Db::i()->select( '*', 'nexus_purchases', array( "ps_id>? and ps_app=? and ps_type=? and ps_item_id=?", $data['lastId'], 'nexus', 'package', $package->id ), 'ps_id', 1 );
				
				try
				{
					$purchase = \IPS\nexus\Purchase::constructFromData( $select->first() );
					$total = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( "ps_app=? and ps_type=? and ps_item_id=?", 'nexus', 'package', $package->id ) )->first();
					
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
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=nexus&module=store&controller=packages" )->setQueryString( array( 'root' => ( $package->parent() ? $package->parent()->_id : '' ) ) ) );
			}
		);
	}
	
	/**
	 * Build Product Options Table
	 *
	 * @return	array
	 */
	public function productoptions()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'packages_edit' );
		
		if ( !\IPS\Request::i()->fields or !\IPS\Request::i()->package )
		{
			\IPS\Output::i()->sendOutput('');
			return;
		}
		
		try
		{
			$package = \IPS\nexus\Package::load( \IPS\Request::i()->package );
		
			$fields = iterator_to_array( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_package_fields', \IPS\Db::i()->in( 'cf_id', explode( ',', \IPS\Request::i()->fields ) ) ), 'IPS\nexus\Package\CustomField' ) );
			$allTheOptions = array();
			foreach ( $fields as $field )
			{
				$options = array();
				foreach ( json_decode( $field->extra, TRUE ) as $option )
				{
					$options[] = json_encode( array( $field->id, $option ) );
				}
				$allTheOptions[ $field->id ] = $options;
			}
			$_rows = $this->arraycartesian( $allTheOptions );
			
			$rows = array();
			foreach ( $_rows as $_options )
			{
				$options = array();
				foreach ( $_options as $encoded )
				{
					$decoded = json_decode( $encoded, TRUE );
					$options[ $decoded[0] ] = $decoded[1];
				}
				$rows[ json_encode( $options ) ] = $options;
			}
			
			$existingValues = iterator_to_array( \IPS\Db::i()->select( '*', 'nexus_product_options', array( 'opt_package=?', $package->id ) )->setKeyField( 'opt_values' ) );
									
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate('store')->productOptionsTable( $fields, $rows, $existingValues, \IPS\Request::i()->renews ) );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->sendOutput( $e->getMessage(), 500 );
		}
	}
	
	/**
	 * Little function from the PHP manual comments
	 *
	 * @param	array	$_	Array
	 * @return	array
	 */
	protected function arraycartesian($_) {
	    if(\count($_) == 0)
	        return array(array());
		foreach($_ as $k=>$a) {
	    	unset($_[$k]);
	    	break;
	    }
	    $c = $this->arraycartesian($_);
	    $r = array();
	    foreach($a as $v)
	        foreach($c as $p)
	            $r[] = array_merge(array($v), $p);
	    return $r;
	}
	
	/**
	 * View Purchases
	 *
	 * @return	void
	 */
	protected function viewPurchases()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'purchases_view', 'nexus', 'customers' );
		
		try
		{
			$package = \IPS\nexus\Package::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X249/2', 404, '' );
		}
		
		$table = new \IPS\Helpers\Table\Db( 'nexus_purchases', \IPS\Http\Url::internal( "app=nexus&module=store&controller=packages&do=viewPurchases&id={$package->id}" ), array( array( 'ps_app=? AND ps_type=? AND ps_item_id=?', 'nexus', 'package', $package->id ) ) );
		$table->include = array( 'ps_id', 'ps_member', 'purchase_status', 'ps_start', 'ps_expire', 'ps_renewals' );
		$table->quickSearch = 'ps_id';
		$table->advancedSearch = array(
			'ps_member'	=> \IPS\Helpers\Table\SEARCH_MEMBER,
			'ps_start'	=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'ps_expire'	=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
		);
		$table->noSort = array( 'purchase_status' );
		
		if ( $package->renew_options or \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_app=? AND ps_type=? AND ps_item_id=? AND ps_active=0 AND ps_cancelled=0', 'nexus', 'package', $package->_id ) )->first() )
		{
			$table->filters = array( 'purchase_tab_active' => 'ps_active=1', 'purchase_tab_expired' => 'ps_active=0 AND ps_cancelled=0', 'purchase_tab_canceled' => 'ps_active=0 AND ps_cancelled=1' );
		}
		else
		{
			$table->filters = array( 'purchase_tab_active' => 'ps_active=1', 'purchase_tab_canceled' => 'ps_active=0' );
		}
		
		$table->parsers = array(
			'ps_member'	=> function( $val ) {
				try
				{
					return \IPS\Theme::i()->getTemplate('global', 'nexus')->userLink( \IPS\Member::load( $val ) );
				}
				catch ( \OutOfRangeException $e )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('deleted_member');
				}
			},
			'purchase_status' => function( $val, $row ) {
				$purchase = \IPS\nexus\Purchase::constructFromData( $row );
				if ( $purchase->cancelled )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('purchase_canceled');
				}
				elseif ( !$purchase->active )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('purchase_expired');
				}
				elseif ( $purchase->grace_period and ( $purchase->expire and $purchase->expire->getTimestamp() < time() ) )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('purchase_in_grace_period');
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack('purchase_active');
				}
			},
			'ps_start'	=> function( $val ) {
				return \IPS\DateTime::ts( $val );
			},
			'ps_expire'	=> function( $val ) {
				return $val ? \IPS\DateTime::ts( $val ) : '--';
			},
			'ps_renewals' => function( $val, $row ) {
				$purchase = \IPS\nexus\Purchase::constructFromData( $row );
				return $purchase->grouped_renewals ? \IPS\Member::loggedIn()->language()->addToStack('purchase_grouped') : ( (string) ( $purchase->renewals ?: '--' ) );
			}
		);
		$table->rowButtons = function( $row ) {
			$purchase = \IPS\nexus\Purchase::constructFromData( $row );
			return array_merge( array(
				'view'	=> array(
					'link'	=> $purchase->acpUrl()->setQueryString( 'popup', true ),
					'title'	=> 'view',
					'icon'	=> 'search',
				)
			), $purchase->buttons() );
		};
		
		\IPS\Output::i()->title = $package->_title;
		\IPS\Output::i()->output = $table;
		\IPS\Output::i()->sidebar['actions'] = array(
			'cancel_purchases'	=> array(
				'icon'	=> 'arrow-right',
				'title'	=> 'mass_change_all_purchases',
				'link'	=> \IPS\Http\Url::internal( "app=nexus&module=store&controller=packages" )->setQueryString( array( 'do' => 'massManagePurchases', 'id' => $package->_id ) ),
				'data'	=> array(
					'ipsDialog'			=> TRUE,
					'ipsDialog-title'	=> $package->_title
				)
			),
		);
	}
	
	/**
	 * Mass Change/Cancel Purchases
	 *
	 * @return	void
	 */
	protected function massManagePurchases()
	{
		try
		{
			$package = \IPS\nexus\Package::load( \IPS\Request::i()->id );
			if ( $package->deleteOrMoveQueued() )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X249/4', 404, '' );
		}
		
		$form = new \IPS\Helpers\Form( 'form', 'go' );
		$form->addMessage('mass_change_purchases_explain');
		
		$options = array();
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'purchases_edit' ) )
		{
			$options['change'] = 'mass_change_purchases_change';
			$options['expire'] = 'mass_change_purchases_expire';
		}
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'purchases_cancel' ) )
		{
			$options['cancel'] = 'mass_change_purchases_cancel';
		}
		
		$upgradeDowngradeOptions = array();
		$toggles = array();
		$renewFields = array();
		$desciptions = [];
		foreach ( $package->parent()->children() as $siblingPackage )
		{
			if ( $package->id === $siblingPackage->id )
			{
				continue;
			}
			
			$upgradeDowngradeOptions[ $siblingPackage->id ] = $siblingPackage->_title;
			
			$renewOptions = json_decode( $siblingPackage->renew_options, TRUE ) ?: array();
			$renewalOptions = array();
			$renewFieldsDescriptions = array();
			foreach ( $renewOptions as $k => $option )
			{
				$renewTermObject = new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( 0, '' ), new \DateInterval( 'P' . $option['term'] . mb_strtoupper( $option['unit'] ) ) );
				
				$costs = array();
				foreach ( $option['cost'] as $cost )
				{
					$costs[] =  new \IPS\nexus\Money( $cost['amount'], $cost['currency'] );
				}
				
				$renewalOptions[ $k ] = sprintf( \IPS\Member::loggedIn()->language()->get( 'renew_option'), implode( ' / ', $costs ), $renewTermObject->getTermUnit() );
			}
			
			if ( $renewalOptions )
			{
				$desciptions[ $siblingPackage->id ] = \IPS\Member::loggedIn()->language()->formatList( $renewalOptions, \IPS\Member::loggedIn()->language()->get('or_list_format') );
				$renewFields[] = new \IPS\Helpers\Form\Radio( 'renew_option_' . $siblingPackage->id, NULL, NULL, array( 'options' => $renewalOptions, 'descriptions' => $renewFieldsDescriptions, 'parse' => 'normal' ), NULL, NULL, NULL, 'renew_option_' . $siblingPackage->id );
				\IPS\Member::loggedIn()->language()->words['renew_option_' . $siblingPackage->id] = \IPS\Member::loggedIn()->language()->addToStack( 'renewal_term', FALSE );
				$toggles[ $siblingPackage->id ] = array( 'renew_option_' . $siblingPackage->id );
			}
			else
			{
				$desciptions[ $siblingPackage->id ] = \IPS\Member::loggedIn()->language()->addToStack('mass_change_purchases_no_renewals');
			}
		}
		
		$form->add( new \IPS\Helpers\Form\Radio( 'cancel_type', NULL, TRUE, array(
			'options'	=> $options,
			'toggles'	=> array(
				'change' => array( 'mass_change_purchases_to', 'mass_change_purchases_override' ),
				'expire' => array( 'ps_can_reactivate' ),
				'cancel' => array( 'ps_can_reactivate' ),
			),
			'disabled'	=> $upgradeDowngradeOptions ? [] : ['change']
		) ) );
		if ( !$upgradeDowngradeOptions )
		{
			\IPS\Member::loggedIn()->language()->words['mass_change_purchases_change_desc'] = \IPS\Member::loggedIn()->language()->addToStack('cancel_type_change_no_siblings');
		}
		
		$form->add( new \IPS\Helpers\Form\Radio( 'mass_change_purchases_to', NULL, NULL, array( 'options' => $upgradeDowngradeOptions, 'descriptions' => $desciptions, 'toggles' => $toggles, 'parse' => 'normal' ), NULL, NULL, NULL, 'mass_change_purchases_to' ) );
		foreach ( $renewFields as $field )
		{
			$form->add( $field );
		}
		$form->add( new \IPS\Helpers\Form\YesNo( 'mass_change_purchases_override', TRUE, NULL, array(), NULL, NULL, NULL, 'mass_change_purchases_override' ) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'ps_can_reactivate', NULL, FALSE, array(), NULL, NULL, NULL, 'ps_can_reactivate' ) );
		
		if ( $values = $form->values() )
		{
			// Note: Maybe we cannot upgrade/downgrade cancelled purchases? Reflect that in the form if so
			// Note: Must cancel billing agreements when upgrading/downgrading purchases. Reflect that in the form
			
			$values['id'] = $package->_id;
			$values['admin'] = \IPS\Member::loggedIn()->member_id;
			\IPS\Task::queue( 'nexus', 'MassChangePurchases', $values );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=nexus&module=store&controller=packages" )->setQueryString( array( 'root' => ( $package->parent() ? $package->parent()->_id : '' ) ) ), 'mass_change_purchases_confirm' );
		}
		
		\IPS\Output::i()->title = $package->_title;
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Show Email Preview
	 *
	 * @return	void
	 */
	public function emailPreview()
	{
		\IPS\Session::i()->csrfCheck();
		
		$functionName = 'emailPreview_' . mt_rand();
		\IPS\Theme::makeProcessFunction( \IPS\Request::i()->value, $functionName, '$purchase' );
		
		$dummyPurchase = new \IPS\nexus\Purchase;
		$dummyPurchase->name = \IPS\Member::loggedIn()->language()->addToStack('p_email_preview_example');
		$dummyPurchase->member = \IPS\Member::loggedIn();
		$dummyPurchase->expire = \IPS\DateTime::create()->add( new \DateInterval('P1M') );
		$dummyPurchase->renewals = new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( 10, \IPS\nexus\Customer::loggedIn()->defaultCurrency() ), new \DateInterval('P1M') );
		$dummyPurchase->custom_fields = array_fill( 0, \IPS\Db::i()->select( 'MAX(cf_id)', 'nexus_package_fields' )->first(), \IPS\Member::loggedIn()->language()->addToStack('p_email_preview_example') );
		$dummyPurchase->licenseKey = new \IPS\nexus\Purchase\LicenseKey\Standard;
		$dummyPurchase->licenseKey->key = 'XXXX-XXXX-XXXX-XXXX';
		
		try
		{
			$themeFunction = 'IPS\\Theme\\'. $functionName;
			$output = \IPS\Email::buildFromContent( 'Test', $themeFunction( $dummyPurchase ), NULL, \IPS\Email::TYPE_TRANSACTIONAL )->compileContent( 'html', \IPS\Member::loggedIn() );
		}
		catch ( \Exception $e )
		{
			$output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( $e->getMessage(), 'error', $e->getMessage(), TRUE, TRUE );
		}
		\IPS\Output::i()->sendOutput( $output );
	}

	/**
	 * Build the form to mass move content
	 *
	 * @param	\IPS\Helpers\Form	$form	The form helper object
	 * @param	mixed			$data		Data from the wizard helper
	 * @param	string			$nodeClass	Node class
	 * @param	\IPS\Node\Mode	$node		Node we are working with
	 * @param	string			$contentItemClass	Content item class (if there is one)
	 * @return \IPS\Helpers\Form
	 */
	protected function _buildMassMoveForm( $form, $data, $nodeClass, $node, $contentItemClass )
	{
		$form->addHeader('node_mass_move_delete_then');
		$moveToClass = isset( $data['moveToClass'] ) ? $data['moveToClass'] : $nodeClass;
		$form->add( new \IPS\Helpers\Form\Node( 'node_move_products', isset( $data['moveTo'] ) ? $moveToClass::load( $data['moveTo'] ) : 0, TRUE, array( 'class' => $nodeClass, 'disabledIds' => array( $node->_id ), 'disabledLang' => 'node_move_delete', 'zeroVal' => 'products_delete_content', 'subnodes' => FALSE ) ) );

		return $form;
	}

	/**
	 * Process the mass move form submission
	 *
	 * @param	array			$values		Values from form submission
	 * @param	mixed			$data		Data from the wizard helper
	 * @param	string			$nodeClass	Node class
	 * @param	\IPS\Node\Mode	$node		Node we are working with
	 * @param	string			$contentItemClass	Content item class (if there is one)
	 * @return	array	Wizard helper data
	 */
	protected function _processMassMoveForm( $values, $data, $nodeClass, $node, $contentItemClass )
	{
		$data['deleteWhenDone'] = FALSE;
		$data['class'] = \get_class( $node );
		$data['id'] = $node->_id;
		
		if ( \is_object( $values['node_move_products'] ) )
		{
			$data['moveToClass'] = \get_class( $values['node_move_products'] );
			$data['moveTo'] = $values['node_move_products']->_id;
		}
		else
		{
			unset( $data['moveToClass'] );
			unset( $data['moveTo'] );
		}

		return $data;
	}
}