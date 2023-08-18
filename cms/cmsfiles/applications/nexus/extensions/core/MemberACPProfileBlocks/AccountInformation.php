<?php
/**
 * @brief		ACP Member Profile Block
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		05 Dec 2017
 */

namespace IPS\nexus\extensions\core\MemberACPProfileBlocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile Block
 */
class _AccountInformation extends \IPS\core\MemberACPProfile\TabbedBlock
{
	/**
	 * Get Tab Names
	 *
	 * @return	string
	 */
	public function tabs()
	{
		$tabs = array(
			'overview'	=> array(
				'icon'		=> 'ellipsis-h',
				'count'		=> 0
			)
		);
		if ( \count( \IPS\nexus\Gateway::cardStorageGateways() ) )
		{
			$tabs['cards'] = array(
				'icon'		=> 'credit-card',
				'count'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_customer_cards', array( 'card_member=?', $this->member->member_id ) )->first()
			);
		}
		if ( \count( \IPS\nexus\Gateway::billingAgreementGateways() ) )
		{
			$tabs['paypal'] = array(
				'icon'		=> 'paypal',
				'count'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_billing_agreements', array( 'ba_member=? AND ba_canceled=0', $this->member->member_id ) )->first()
			);
		}
		$tabs['alts'] = array(
			'icon'		=> 'user',
			'count'		=> \IPS\Db::i()->select( 'COUNT(*)', 'nexus_alternate_contacts', array( 'main_id=?', $this->member->member_id ) )->first()
		);

		return $tabs;
	}
	
	/**
	 * Get output: OVERVIEW
	 *
	 * @return	string
	 */
	protected function _overview()
	{
		/* Sparkline */
		$sparkline = NULL;

		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'customers_view_statistics' ) )
		{
			$rows = array();
			$oneYearAgo = \IPS\DateTime::create()->sub( new \DateInterval( 'P1Y' ) );
			$date = clone $oneYearAgo;
			$endOfLastMonth = mktime( 23, 59, 59, date( 'n' ) - 1, date( 't' ), date( 'Y' ) );
			while ( $date->getTimestamp() < $endOfLastMonth )
			{
				foreach ( \IPS\nexus\Money::currencies() as $currency )
				{
					$rows[$date->format( 'n Y' )][$currency] = 0;
				}
				$date->add( new \DateInterval( 'P1M' ) );
			}
			$sparkline = new \IPS\Helpers\Chart;
			foreach ( \IPS\Db::i()->select( 'DATE_FORMAT( FROM_UNIXTIME(t_date), \'%c %Y\' ) AS time, SUM(t_amount)-SUM(t_partial_refund) AS amount, t_currency', 'nexus_transactions', array(array("t_member=? AND ( t_status=? OR t_status=? ) AND t_method>0 AND t_date>? AND t_date<?", $this->member->member_id, \IPS\nexus\Transaction::STATUS_PAID, \IPS\nexus\Transaction::STATUS_PART_REFUNDED, $oneYearAgo->getTimestamp(), time())), NULL, NULL, array('time', 't_currency') ) as $row )
			{
				if ( isset( $rows[$row['time']][$row['t_currency']] ) ) // Currency may no longer exist
				{
					$rows[$row['time']][$row['t_currency']] += $row['amount'];
				}
			}
			$sparkline->addHeader( \IPS\Member::loggedIn()->language()->addToStack( 'date' ), 'date' );
			foreach ( \IPS\nexus\Money::currencies() as $currency )
			{
				$sparkline->addHeader( $currency, 'number' );
			}
			foreach ( $rows as $time => $row )
			{
				$datetime = new \IPS\DateTime;
				$datetime->setTime( 0, 0, 0 );
				$exploded = explode( ' ', $time );
				$datetime->setDate( $exploded[1], $exploded[0], 1 );

				foreach ( $row as $currency => $value )
				{
					$row[$currency] = number_format( $value, 2, '.', '' );
				}

				$sparkline->addRow( array_merge( array($datetime), $row ) );
			}

			$sparkline = $sparkline->render( 'AreaChart', array(
				'areaOpacity' => 0.4,
				'backgroundColor' => '#fff',
				'colors' => array('#10967e'),
				'chartArea' => array(
					'left' => 0,
					'top' => 0,
					'width' => '100%',
					'height' => '100%',
				),
				'hAxis' => array(
					'baselineColor' => '#F3F3F3',
					'gridlines' => array(
						'count' => 0,
					)
				),
				'height' => 60,
				'legend' => array(
					'position' => 'none',
				),
				'lineWidth' => 1,
				'vAxis' => array(
					'baselineColor' => '#F3F3F3',
					'gridlines' => array(
						'count' => 0,
					)
				),
			) );
		}
		
		/* Primary Billing Address */
		$primaryBillingAddress = $this->member->primaryBillingAddress();
		$addressCount = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_customer_addresses', array( '`member`=?', $this->member->member_id ) )->first();
				
		/* Display */
		return \IPS\Theme::i()->getTemplate( 'customers', 'nexus' )->accountInformationOverview( $this->member, $sparkline, $primaryBillingAddress, $addressCount );
	}
	
	/**
	 * Get output: STORED PAYMENT METHODS
	 *
	 * @param	bool	$edit	Edit view?
	 * @return	string
	 */
	protected function _cards( $edit = FALSE )
	{
		$cards = array();
		foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_customer_cards', array( 'card_member=?', $this->member->member_id ), NULL, $edit ? NULL : 10 ), 'IPS\nexus\Customer\CreditCard' ) as $card )
		{
			try
			{
				$cardData = $card->card;
				$cards[ $card->id ] = array(
					'id'			=> $card->id,
					'card_type'		=> $cardData->type,
					'card_member'	=> $card->member->member_id,
					'card_number'	=> $cardData->lastFour ?: $cardData->number,
					'card_expire'	=> ( !\is_null( $cardData->expMonth ) AND !\is_null( $cardData->expYear ) ) ? str_pad( $cardData->expMonth , 2, '0', STR_PAD_LEFT ). '/' . $cardData->expYear : NULL
				);
			}
			catch ( \Exception $e ) { }
		}
		$cards = new \IPS\Helpers\Table\Custom( $cards, $this->member->acpUrl()->setQueryString( 'view', 'cards' ) );
		
		if ( \IPS\nexus\Gateway::cardStorageGateways( TRUE ) )
		{
			$cards->rootButtons = array(
				'add'	=> array(
					'link'	=> \IPS\Http\Url::internal("app=nexus&module=customers&controller=view&id={$this->member->member_id}")->setQueryString( 'do', 'addCard' ),
					'title'	=> 'add',
					'icon'	=> 'plus',
					'data'	=> array( 'ipsDialog' => true, 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('add_card') )
				)
			);
		}
		$cards->rowButtons = function( $row )
		{
			return array(
				'delete'	=> array(
					'link'	=> \IPS\Http\Url::internal("app=nexus&module=customers&controller=view&id={$this->member->member_id}")->setQueryString( array( 'do' => 'deleteCard', 'card_id' => $row['id'] ) ),
					'title'	=> 'delete',
					'icon'	=> 'times-circle',
					'data'	=> array( 'delete' => '' )
				)
			);
		};
		
		if ( $edit )
		{
			$cards->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'customers', 'nexus' ), 'cardsTable' );
			$cards->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'customers', 'nexus' ), 'cardsTableRows' );

			return \IPS\Theme::i()->getTemplate( 'customers', 'nexus' )->customerPopup( $cards );
		}
		else
		{
			$cards->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'customers', 'nexus' ), 'cardsOverview' );
			$cards->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'customers', 'nexus' ), 'cardsOverviewRows' );
			
			$cardCount = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_customer_cards', array( 'card_member=?', $this->member->member_id ) )->first();
			return \IPS\Theme::i()->getTemplate( 'customers', 'nexus' )->accountInformationTablePreview( $this->member, $cards, \IPS\Member::loggedIn()->language()->addToStack( 'num_credit_card', FALSE, array( 'pluralize' => array( $cardCount ) ) ), 'cards' );
		}
	}
	
	/**
	 * Get output: BILLING AGREEMENTS
	 *
	 * @param	bool	$edit	Edit view?
	 * @return	string
	 */
	protected function _paypal( $edit = FALSE )
	{
		$billingAgreementCount = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_billing_agreements', array( 'ba_member=? AND ba_canceled=0', $this->member->member_id ) )->first();
		$billingAgreements = array();
		foreach ( \IPS\Db::i()->select( '*', 'nexus_billing_agreements', array( 'ba_member=? AND ba_canceled=0', $this->member->member_id ), NULL, $edit ? NULL : 10 ) as $billingAgreement )
		{
			$billingAgreements[ $billingAgreement['ba_id'] ] = array(
				'id'						=> $billingAgreement['ba_id'],
				'gw_id'						=> $billingAgreement['ba_gw_id'],
				'started'					=> $billingAgreement['ba_started'],
				'next_cycle'				=> $billingAgreement['ba_next_cycle'],
			);
		}
		$billingAgreements = new \IPS\Helpers\Table\Custom( $billingAgreements, $this->member->acpUrl()->setQueryString( 'view', 'billingagreements' ) );
		$billingAgreements->parsers = array(
			'started'	=> function( $val ) {
				return $val ? \IPS\DateTime::ts( $val )->relative() : null;
			},
			'next_cycle'	=> function( $val ) {
				return $val ? \IPS\DateTime::ts( $val )->relative() : null;
			},
		);
		$billingAgreements->rowButtons = function( $row, $id )
		{
			return array(
				'view'	=> array(
					'link'	=> \IPS\Http\Url::internal("app=nexus&module=payments&controller=billingagreements&id={$id}"),
					'title'	=> 'view',
					'icon'	=> 'search',
				)
			);
		};
		if ( $edit )
		{
			$billingAgreements->exclude = array( 'id', 'last_transaction_currency' );
			$billingAgreements->langPrefix = 'ba_';
			return \IPS\Theme::i()->getTemplate( 'customers', 'nexus' )->customerPopup( $billingAgreements );
		}
		else
		{
			$billingAgreements->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'customers', 'nexus' ), 'billingAgreementsOverview' );
			$billingAgreements->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'customers', 'nexus' ), 'billingAgreementsOverviewRows' );
			
			return \IPS\Theme::i()->getTemplate( 'customers', 'nexus' )->accountInformationTablePreview( $this->member, $billingAgreements, \IPS\Member::loggedIn()->language()->addToStack( 'num_billing_agreements', FALSE, array( 'pluralize' => array( $billingAgreementCount ) ) ), 'paypal' );
		}
	}
	
	/**
	 * Get output: ALTERNATE CONTACTS
	 *
	 * @param	bool	$edit	Edit view?
	 * @return	string
	 */
	protected function _alts( $edit = FALSE )
	{
		$altContactCount = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_alternate_contacts', array( 'main_id=?', $this->member->member_id ) )->first();
		$alternativeContacts = new \IPS\Helpers\Table\Db( 'nexus_alternate_contacts', $this->member->acpUrl()->setQueryString( 'view', 'alternatives' ), array( 'main_id=?', $this->member->member_id ) );
		$alternativeContacts->langPrefix = 'altcontactTable_';
		$alternativeContacts->include = array( 'alt_id', 'purchases', 'billing', 'support' );
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'customers_edit_details' ) )
		{
			$alternativeContacts->parsers = array(
				'alt_id'	=> function( $val )
				{
					return  \IPS\Theme::i()->getTemplate( 'global', 'nexus' )->userLink( \IPS\nexus\Customer::load( $val ) );
				},
				'email'		=> function ( $val, $row )
				{
					return htmlspecialchars( \IPS\nexus\Customer::load( $row['alt_id'] )->email, ENT_DISALLOWED, 'UTF-8', FALSE );
				},
				'purchases'	=> function( $val )
				{
					return implode( '<br>', array_map( function( $id )
					{
						try
						{
							return \IPS\Theme::i()->getTemplate( 'purchases', 'nexus' )->link( \IPS\nexus\Purchase::load( $id ) );
						}
						catch ( \OutOfRangeException $e )
						{
							return '';
						}
					}, explode( ',', $val ) ) );
				},
				'billing'	=> function( $val )
				{
					return $val ? "<i class='fa fa-check'></i>" : "<i class='fa fa-times'></i>";
				},
				'support'	=> function( $val )
				{
					return $val ? "<i class='fa fa-check'></i>" : "<i class='fa fa-times'></i>";
				}
			);
			
			$alternativeContacts->rootButtons = array(
				'add'	=> array(
					'link'	=> \IPS\Http\Url::internal("app=nexus&module=customers&controller=view&id={$this->member->member_id}")->setQueryString( 'do', 'alternativeContactForm' ),
					'title'	=> 'add',
					'icon'	=> 'plus',
					'data'	=> array( 'ipsDialog' => true, 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('altcontact_add') )
				)
			);
			$alternativeContacts->rowButtons = function( $row )
			{
				return array(
					'edit'	=> array(
						'link'	=> \IPS\Http\Url::internal("app=nexus&module=customers&controller=view&id={$this->member->member_id}")->setQueryString( array( 'do' => 'alternativeContactForm', 'alt_id' => $row['alt_id'] ) ),
						'title'	=> 'edit',
						'icon'	=> 'pencil',
						'data'	=> array( 'ipsDialog' => true )
					),
					'delete'	=> array(
						'link'	=> \IPS\Http\Url::internal("app=nexus&module=customers&controller=view&id={$this->member->member_id}")->setQueryString( array( 'do' => 'deleteAlternativeContact', 'alt_id' => $row['alt_id'] ) ),
						'title'	=> 'delete',
						'icon'	=> 'times-circle',
						'data'	=> array( 'delete' => '' )
					)
				);
			};
		}
		if ( $edit )
		{
			return \IPS\Theme::i()->getTemplate( 'customers', 'nexus' )->customerPopup( $alternativeContacts );
		}
		else
		{
			$alternativeContacts->include[] = 'email';
			$alternativeContacts->limit = 2;
			$alternativeContacts->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'customers', 'nexus' ), 'altContactsOverview' );
			$alternativeContacts->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'customers', 'nexus' ), 'altContactsOverviewRows' );
			
			return \IPS\Theme::i()->getTemplate( 'customers', 'nexus' )->accountInformationTablePreview( $this->member, $alternativeContacts, \IPS\Member::loggedIn()->language()->addToStack( 'num_alternate_contacts', FALSE, array( 'pluralize' => array( $altContactCount ) ) ), 'alts' );
		}
	}

	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function tabOutput( $tab )
	{
		$method = "_{$tab}";
		return $this->$method();
	}
	
	/**
	 * Edit Window
	 *
	 * @return	string
	 */
	public function edit()
	{
		if ( array_key_exists( \IPS\Request::i()->type, $this->tabs() ) )
		{
			$method = "_" . \IPS\Request::i()->type;
			return $this->$method( TRUE );
		}
		return parent::edit();
	}
	
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output()
	{
		$tabs = $this->tabs();
		if ( !\count( $tabs ) )
		{
			return '';
		} 
		$tabKeys = array_keys( $tabs );
		$activeTabKey = ( isset( \IPS\Request::i()->block['nexus_AccountInformation'] ) and array_key_exists( \IPS\Request::i()->block['nexus_AccountInformation'], $tabs ) ) ? \IPS\Request::i()->block['nexus_AccountInformation'] : array_shift( $tabKeys );
		
		$activeSubscription = FALSE;
		if ( \IPS\Settings::i()->nexus_subs_enabled )
		{
			$activeSubscription = \IPS\nexus\Subscription::loadActiveByMember( $this->member );
		}
		
		return \IPS\Theme::i()->getTemplate( 'customers', 'nexus' )->accountInformation( $this->member, $tabs, $activeTabKey, $this->tabOutput( $activeTabKey ), $activeSubscription );
	}
}