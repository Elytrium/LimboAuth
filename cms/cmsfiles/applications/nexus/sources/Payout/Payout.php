<?php
/**
 * @brief		Payout Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		10 Feb 2014
 */

namespace IPS\nexus;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Payout Model
 */
abstract class _Payout extends \IPS\Patterns\ActiveRecord
{	
	const STATUS_COMPLETE = 'done';
	const STATUS_PENDING  = 'pend';
	const STATUS_CANCELED = 'canc';
	const STATUS_PROCESSING = 'wait';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'nexus_payouts';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'po_';
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		$classname = \IPS\nexus\Gateway::payoutGateways()[ $data['po_gateway'] ];
		
		/* Initiate an object */
		$obj = new $classname;
		$obj->_new = FALSE;
		
		/* Import data */
		foreach ( $data as $k => $v )
		{
			if( static::$databasePrefix AND mb_strpos( $k, static::$databasePrefix ) === 0 )
			{
				$k = \substr( $k, \strlen( static::$databasePrefix ) );
			}

			$obj->_data[ $k ] = $v;
		}
		$obj->changed = array();
						
		/* Return */
		return $obj;
	}
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->date = new \IPS\DateTime;
		$this->status = static::STATUS_PENDING;
		$this->gateway = preg_replace( '/.+?\\\([A-Z]+?)\\\Payout$/i', '$1', \get_called_class() );
	}
	
	/**
	 * @brief	Requires manual approval?
	 */
	public static $requiresApproval = FALSE;
	
	/**
	 * Get payouts table
	 *
	 * @param	array			$where	Where clause
	 * @param	\IPS\Http\Url	$ref	URL to display table on
	 * @return	\IPS\Helpers\Table\Db
	 */
	public static function table( $where, \IPS\Http\Url $url )
	{
		$table = new \IPS\Helpers\Table\Db( 'nexus_payouts', $url, $where );
		$table->include = array( 'po_status', 'po_id', 'po_gateway', 'po_member', 'po_amount', 'po_date' );
		$table->parsers = array(
			'po_status'	=> function( $val )
			{
				return \IPS\Theme::i()->getTemplate( 'payouts', 'nexus' )->status( $val );
			},
			'po_member'	=> function ( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate('global')->userLink( \IPS\Member::load( $val ) );
			},
			'po_amount'	=> function( $val, $row )
			{
				return (string) new \IPS\nexus\Money( $val, $row['po_currency'] );
			},
			'po_date'	=> function( $val )
			{
				return \IPS\DateTime::ts( $val );
			}
		);
		$table->filters = array(
			'postatus_pend'	=> array( 'po_status=?', 'pend' ),
		);
		$table->advancedSearch = array(
			'po_status'	=> array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => array(
				\IPS\nexus\Payout::STATUS_COMPLETE	=> 'postatus_' . \IPS\nexus\Payout::STATUS_COMPLETE,
				\IPS\nexus\Payout::STATUS_PENDING	=> 'postatus_' . \IPS\nexus\Payout::STATUS_PENDING,
				\IPS\nexus\Payout::STATUS_CANCELED	=> 'postatus_' . \IPS\nexus\Payout::STATUS_CANCELED,
			), 'multiple' => TRUE ) ),
			'po_member'	=> \IPS\Helpers\Table\SEARCH_MEMBER,
			'po_amount'	=> \IPS\Helpers\Table\SEARCH_NUMERIC,
			'po_date'	=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
		);
		$table->rowButtons = function( $row )
		{
			return array_merge( array(
				'view'	=> array(
					'icon'	=> 'search',
					'link'	=> \IPS\Http\Url::internal( "app=nexus&module=payments&controller=payouts&do=view&id={$row['po_id']}" ),
					'title'	=> 'view',
				),
			), \IPS\nexus\Payout::constructFromData( $row )->buttons( 't' ) );
		};
		$table->sortBy = $table->sortBy ?: 'po_date';
		
		return $table;
	}
	
	/**
	 * Get amount
	 *
	 * @return	\IPS\nexus\Money
	 */
	public function get_amount()
	{		
		return new \IPS\nexus\Money( $this->_data['amount'], $this->_data['currency'] );
	}
	
	/**
	 * Set amount
	 *
	 * @param	\IPS\nexus\Money	$amount	The total
	 * @return	void
	 */
	public function set_amount( \IPS\nexus\Money $amount )
	{
		$this->_data['amount'] = $amount->amount;
		$this->_data['currency'] = $amount->currency;
	}
	
	/**
	 * Get member
	 *
	 * @return	\IPS\Member
	 */
	public function get_member()
	{
		return \IPS\nexus\Customer::load( $this->_data['member'] );
	}
	
	/**
	 * Set member
	 *
	 * @param	\IPS\Member
	 * @return	void
	 */
	public function set_member( \IPS\Member $member )
	{
		$this->_data['member'] = $member->member_id;
	}
		
	/**
	 * Get date
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_date()
	{
		return \IPS\DateTime::ts( $this->_data['date'] );
	}
	
	/**
	 * Set date
	 *
	 * @param	\IPS\DateTime	$date	The invoice date
	 * @return	void
	 */
	public function set_date( \IPS\DateTime $date )
	{
		$this->_data['date'] = $date->getTimestamp();
	}
	
	/**
	 * Get completed date
	 *
	 * @return	\IPS\DateTime|NULL
	 */
	public function get_completed()
	{
		return $this->_data['completed'] ? \IPS\DateTime::ts( $this->_data['completed'] ) : NULL;
	}
	
	/**
	 * Set completed date
	 *
	 * @param	\IPS\DateTime	$date	The invoice date
	 * @return	void
	 */
	public function set_completed( \IPS\DateTime $date )
	{
		$this->_data['completed'] = $date->getTimestamp();
	}
	
	/**
	 * Get approving member
	 *
	 * @return	\IPS\Member
	 */
	public function get_processed_by()
	{
		return $this->_data['processed_by'] ? \IPS\nexus\Customer::load( $this->_data['processed_by'] ) : NULL;
	}
	
	/**
	 * Set approving member
	 *
	 * @param	\IPS\Member
	 * @return	void
	 */
	public function set_processed_by( \IPS\Member $member )
	{
		$this->_data['processed_by'] = $member->member_id;
	}
	
	/**
	 * ACP Buttons
	 *
	 * @param	string	$ref	Referer
	 * @return	array
	 */
	public function buttons( $ref='v' )
	{
		$url = $this->acpUrl()->setQueryString( array( 'r' => $ref, 'filter' => \IPS\Request::i()->filter ) );
		$return = array();
		
		if ( $this->status === static::STATUS_PENDING )
		{
			if( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'payouts_process' ) )
			{
				$return['approve'] = array(
					'title'		=> 'approve',
					'icon'		=> 'check',
					'link'		=> $url->setQueryString( 'do', 'process' )->csrf(),
					'data'		=> array( 'confirm' => '' )
				);
			}
			if( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'payouts_cancel' ) )
			{
				$return['cancel'] = array(
					'title'		=> 'cancel',
					'icon'		=> 'times',
					'link'		=> $url->setQueryString( 'do', 'cancel' )->csrf(),
					'data'		=> array(
						'confirm'			=> '',
						'confirmMessage'	=> \IPS\Member::loggedIn()->language()->addToStack('payout_cancel_confirm'),
						'confirmType'		=> 'verify',
						'confirmIcon'		=> 'question',
						'confirmButtons'	=> json_encode( array(
							'yes'				=>	\IPS\Member::loggedIn()->language()->addToStack('yes'),
							'no'				=>	\IPS\Member::loggedIn()->language()->addToStack('no'),
							'cancel'			=>	\IPS\Member::loggedIn()->language()->addToStack('cancel'),
						) )
					)
				);
			}
		}
		
		if( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'payouts_delete' ) )
		{
			$return['delete'] = array(
				'title'		=> 'delete',
				'icon'		=> 'times-circle',
				'link'		=> $url->setQueryString( 'do', 'delete' ),
				'data'		=> array( 'delete' => '' )
			);
		}
		
		return $return;
	}
	
	/**
	 * ACP URL
	 *
	 * @return	\IPS\Http\Url
	 */
	public function acpUrl()
	{
		return \IPS\Http\Url::internal( "app=nexus&module=payments&controller=payouts&do=view&id={$this->id}", 'admin' );
	}

	/**
	 * Mark the payout as completed.
	 * Moved this out of the controllers because there are times when
	 * the payout may not be processed immediately (e.g. via PayPal batch)
	 *
	 * @return void
	 */
	public function markCompleted()
	{
		$this->status = static::STATUS_COMPLETE;
		$this->completed = new \IPS\DateTime;
		$this->save();

		/* Notify member */
		\IPS\Email::buildFromTemplate( 'nexus', 'payoutComplete', array( $this ), \IPS\Email::TYPE_TRANSACTIONAL )->send( $this->member );
	}
	
	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse			int						id				ID number
	 * @apiresponse			string					status			Status: 'done' = Payment sent; 'pend' = Pending; 'canc' = Canceled
	 * @apiresponse			\IPS\nexus\Money		amount			Amount
	 * @apiresponse			string					gateway			The gateway that will process the withdrawal
	 * @apiresponse			string					data			The data provided by the member for the process. For example, if the gateway is PayPal, this will be their PayPal email address
	 * @apiresponse			datetime				requestedDate	Date withdrawal was requested
	 * @apiresponse			datetime				completedDate	Date withdrawal was completed
	 * @clientapiresponse	string					gatewayId		Any ID number provided by the gateway to identify the transaction on their end
	 * @apiresponse			\IPS\nexus\Customer		customer		Customer
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		return array(
			'id'				=> $this->id,
			'status'			=> $this->status,
			'amount'			=> $this->amount->apiOutput( $authorizedMember ),
			'member'			=> $this->member->apiOutput( $authorizedMember ),
			'gateway'			=> $this->gateway,
			'data'				=> $this->data,
			'requestedDate'		=> $this->date->rfc3339(),
			'completedDate'		=> $this->completed ? $this->completed->rfc3339() : null,
			'gatewayId'			=> $this->gw_id,
		);
	}
}