<?php
/**
 * @brief		Shipping Order Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		19 Mar 2014
 */

namespace IPS\nexus\Shipping;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Shipping Order Model
 */
class _Order extends \IPS\Patterns\ActiveRecord
{
	const STATUS_SHIPPED	= 'done';
	const STATUS_PENDING	= 'pend';
	const STATUS_CANCELED	= 'canc';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'nexus_ship_orders';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'o_';
	
	/**
	 * Get table of shipping orders
	 *
	 * @param	\IPS\Http\Url	$url	URL that the table will eb shown on
	 * @return	\IPS\Helpers\Table\Db
	 */
	public static function table( \IPS\Http\Url $url )
	{
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'nexus_ship_orders', $url );
		$table->sortBy = $table->sortBy ?: 'o_date';
		
		/* Format Columns */		
		$table->include = array( 'o_status', 'o_method', 'o_invoice', 'o_date' );
		$table->parsers = array(
			'o_status'	=> function( $val )
			{
				return \IPS\Theme::i()->getTemplate( 'shiporders', 'nexus' )->status( $val );
			},
			'o_method'	=> function( $val, $row )
			{
				if ( $row['o_api_service'] )
				{
					return $row['o_api_service'];
				}

				try
				{
					return \IPS\nexus\Shipping\FlatRate::load( $val )->_title;
				}
				catch ( \Exception $e )
				{
					return '';
				}
			},
			'o_invoice'	=> function( $val )
			{
				try
				{
					return \IPS\Theme::i()->getTemplate( 'invoices', 'nexus' )->link( \IPS\nexus\Invoice::load( $val ) );
				}
				catch ( \Exception $e )
				{
					return '';
				}
			},
			'o_date'	=> function( $val )
			{
				return (string) \IPS\DateTime::ts( $val );
			}
		);
		
		/* Filters */
		$table->filters = array(
			'sstatus_pend'	=> array( 'o_status=?', 'pend' ),
		);
		
		/* Search */
		$methods = array();
		foreach( \IPS\nexus\Shipping\FlatRate::roots() as $method )
		{
			$methods[ $method->id ] = $method->_title;
		}
		$table->advancedSearch = array(
			'o_status'		=> array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => array(
				\IPS\nexus\Shipping\Order::STATUS_SHIPPED => 'sstatus_done',
				\IPS\nexus\Shipping\Order::STATUS_PENDING => 'sstatus_pend',
				\IPS\nexus\Shipping\Order::STATUS_CANCELED => 'sstatus_canc',
			), 'multiple' => TRUE ) ),
			'o_method'		=> array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => $methods, 'multiple' => TRUE ) ),
			'o_date'		=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'o_shipped_date'=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'o_tracknumber'	=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
		);
		$table->quickSearch = 'o_tracknumber';
		
		/* Buttons */
		$table->rowButtons = function( $row )
		{
			return array_merge( array(
				'view'	=> array(
					'icon'	=> 'search',
					'title'	=> 'shipment_view',
					'link'	=> \IPS\Http\Url::internal( "app=nexus&module=payments&controller=shipping&do=view&id={$row['o_id']}" )
				)
			), \IPS\nexus\Shipping\Order::constructFromData( $row )->buttons( 't' ) );
		};
		
		/* Return */
		return $table;
	}
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->status = static::STATUS_PENDING;
		$this->date = new \IPS\DateTime;
	}
	
	/**
	 * Get invoice
	 *
	 * @return	\IPS\nexus\Invoice
	 */
	public function get_invoice()
	{
		return \IPS\nexus\Invoice::load( $this->_data['invoice'] );
	}
	
	/**
	 * Set invoice
	 *
	 * @param	\IPS\nexus\Invoice	$invoice	Invoice to associate with order
	 * @return	void
	 */
	public function set_invoice( \IPS\nexus\Invoice $invoice )
	{
		$this->_data['invoice'] = $invoice->id;
	}
	
	/**
	 * Get data (name, address, phone number)
	 *
	 * @return	mixed
	 */
	public function get_data()
	{
		return json_decode( $this->_data['data'], TRUE );
	}
	
	/**
	 * Set data (name, address, phone number)
	 *
	 * @param	mixed	$data	The data
	 * @return	void
	 */
	public function set_data( $data )
	{
		$this->_data['data'] = json_encode( $data );
	}
	
	/**
	 * Get method
	 *
	 * @return	\IPS\nexus\Shipping\FlatRate
	 */
	public function get_method()
	{
		try
		{
			return \IPS\nexus\Shipping\FlatRate::load( $this->_data['method'] );
		}
		catch ( \Exception $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Set method
	 *
	 * @param	\IPS\nexus\Shipping\FlatRate	$method	Flatrate method to use
	 * @return	void
	 */
	public function set_method( \IPS\nexus\Shipping\FlatRate $method )
	{
		$this->_data['method'] = $method->id;
	}
	
	/**
	 * Get items
	 *
	 * @return	array
	 */
	public function get_items()
	{
		return json_decode( $this->_data['items'], TRUE );
	}
	
	/**
	 * Set items
	 *
	 * @note	Is protected, as addItem should be used
	 * @see		\IPS\nexus\Invoice::addItem()
	 * @param	array	$items	The items
	 * @return	void
	 */
	protected function set_items( array $items )
	{
		$this->_data['items'] = json_encode( $items );
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
	 * Get shipped date
	 *
	 * @return	\IPS\DateTime|NULL
	 */
	public function get_shipped_date()
	{
		return $this->_data['shipped_date'] ? \IPS\DateTime::ts( $this->_data['shipped_date'] ) : NULL;
	}
	
	/**
	 * Set shipped date
	 *
	 * @param	\IPS\DateTime	$date	The invoice date
	 * @return	void
	 */
	public function set_shipped_date( \IPS\DateTime $date )
	{
		$this->_data['shipped_date'] = $date->getTimestamp();
	}
		
	/**
	 * Get label
	 *
	 * @return	\IPS\Http\Url|string|NULL
	 */
	public function get_label()
	{
		try
		{
			return $this->_data['label'] ? \IPS\Http\Url::external( $this->_data['label'] ) : NULL;
		}
		catch ( \InvalidArgumentException $e )
		{
			return $this->_data['label'];
		}
	}
	
	/**
	 * Item Count
	 *
	 * @return	int
	 */
	public function itemCount()
	{
		$count = 0;
		foreach ( $this->items as $item )
		{
			$count += $item['quantity'];
		}
		return $count;
	}
	
	/**
	 * Send Notification
	 *
	 * @return	void
	 */
	public function sendNotification()
	{		
		$email = \IPS\Email::buildFromTemplate( 'nexus', 'shipment', array( $this ), \IPS\Email::TYPE_TRANSACTIONAL );
		$email->send( $this->invoice->member );
	}
	
	/**
	 * @brief	Address
	 */
	protected $_address;
	
	/**
	 * Get address
	 *
	 * @return	\IPS\GeoLocation
	 */
	public function address()
	{
		if ( !$this->_address )
		{
			$data = $this->data;
			
			if ( isset( $data['address'] ) )
			{
				$this->_address = \IPS\GeoLocation::buildFromJson( json_encode( $data['address'] ) );
			}
			else
			{
				$this->_address = new \IPS\GeoLocation;
				$this->_address->addressLines[0] = $data['address_1'];
				if ( isset( $data['address_2'] ) and $data['address_2'] )
				{
					$this->_address->addressLines[1] = $data['address_2'];
				}
				$this->_address->city = $data['city'];
				$this->_address->state = $data['state'];
				$this->_address->zip = $data['zip'];
				$this->_address->country = $data['country'];
			}
		}
		
		return $this->_address;
	}
	
	/**
	 * Tracking URL
	 *
	 * @return	\IPS\Http\Url|NULL
	 */
	public function trackingUrl()
	{
		if ( $this->status === static::STATUS_SHIPPED and $this->service )
		{
			try
			{
				return \IPS\Http\Url::external( $this->service );
			}
			catch ( \InvalidArgumentException $e )
			{
				return NULL;
			}
		}
		return NULL;
	}
	
	/**
	 * ACP "View Invoice" URL
	 *
	 * @return	\IPS\Http\Url
	 */
	public function acpUrl()
	{
		return \IPS\Http\Url::internal( "app=nexus&module=payments&controller=shipping&do=view&id={$this->id}", 'admin' );
	}
	
	/**
	 * ACP Buttons
	 *
	 * @param	string	$ref	Referer
	 * @return	array
	 */
	public function buttons( $ref='v' )
	{
		$url = $this->acpUrl()->setQueryString( 'r', $ref );
		$return = array();
		
		if ( $this->status !== static::STATUS_SHIPPED and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'shiporders_edit' ) )
		{
			$return['ship'] = array(
				'title'		=> 'shipment_ship',
				'icon'		=> 'check',
				'link'		=> $url->setQueryString( 'do', 'ship' ),
				'data'		=> \IPS\Settings::i()->easypost_api_key ? NULL : array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('shipment_ship') )
			);

			if ( $this->status !== static::STATUS_CANCELED )
			{
				$return['cancel'] = array(
					'title'		=> 'cancel',
					'icon'		=> 'times',
					'link'		=> $url->setQueryString( 'do', 'cancel' )->csrf(),
					'data'		=> array( 'confirm' => '' )
				);
			}
		}
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'shiporders_delete' ) )
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
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse		int								id				ID number
	 * @apiresponse		string							status			Status: 'done' = Shipped; 'pend' = Waiting to be shipped; 'canc' = Canceled
	 * @apiresponse		int								invoiceId		Invoice ID Number
	 * @apiresponse		\IPS\nexus\Shipping\FlatRate	method			The shipment method (may be null if using EasyPost)
	 * @apiresponse		object							items			The items in the shipment and their quantities
	 * @apiresponse		datetime						requestDate		When the shipment was requested
	 * @apiresponse		datetime						shipDate		When the shipment was shipped
	 * @apiresponse		\IPS\GeoLocation				address			The delivery address
	 * @apiresponse		string							trackingUrl		The URL to view tracking information, if available
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		$items = array();
		foreach ( $this->items as $item )
		{
			if ( isset( $items[ $item['name'] ] ) )
			{
				$items[ $item['name'] ] += $item['quantity'];
			}
			else
			{
				$items[ $item['name'] ] = $item['quantity'];
			}
		}
				
		return array(
			'id'			=> $this->id,
			'status'		=> $this->status,
			'invoiceId'		=> $this->invoice->id,
			'method'		=> $this->method ? $this->method->apiOutput( $authorizedMember ) : null,
			'items'			=> $items,
			'requestDate'	=> $this->date->rfc3339(),
			'shipDate'		=> $this->shipped_date ? $this->shipped_date->rfc3339() : null,
			'address'		=> $this->address()->apiOutput( $authorizedMember ),
			'trackingUrl'	=> $this->trackingUrl() ? ( (string) $this->trackingUrl() ) : null
		);
	}
}