<?php
/**
 * @brief		Purchase Model
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
 * Purchase Model
 */
class _Purchase extends \IPS\Node\Model
{
	/**
	 * Tree
	 *
	 * @param	\IPS\Http\Url					$url			URL
	 * @param	array							$where			Where clause
	 * @param	string							$ref			Referer
	 * @param	\IPS\nexus\Purchase|NULL		$root			Root (NULL for all root purchases)
	 * @param	bool							$includeRoot	Show the root?
	 * @return	\IPS\Helpers\Tree\Tree
	 */
	public static function tree( \IPS\Http\Url $url, array $where, $ref = 'c', \IPS\nexus\Purchase $root = NULL, $includeRoot = TRUE )
	{		
		$where[] = array( 'ps_show=1' );
				
		return new \IPS\Helpers\Tree\Tree(
			$url,
			'Purchases',
			function( $limit ) use ( $url, $where, $ref, $root, $includeRoot )
			{
				if ( $root )
				{
					if ( $includeRoot )
					{
						$where[] = array( 'ps_id=?', $root->id );
					}
					else
					{
						$where[] = array( 'ps_parent=?', $root->id );
					}
				}
				else
				{
					$where[] = array( 'ps_parent=0' );
				}
				
				$rows = array();				
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_purchases', $where, 'ps_start DESC', $limit ), 'IPS\nexus\Purchase' ) as $purchase )
				{
					$rows[ $purchase->id ] = $purchase->treeRow( $url, $ref );
				}
				return $rows;
			},
			function( $id ) use ( $url, $ref )
			{
				return \IPS\nexus\Purchase::load( $id )->treeRow( $url, $ref );
			},
			function( $id )
			{
				return \IPS\nexus\Purchase::load( $id )->parent();
			},
			function( $id ) use ( $url, $ref, $where )
			{
				$rows = array();
				foreach ( \IPS\nexus\Purchase::load( $id )->children( NULL, NULL, TRUE, NULL, $where ) as $child )
				{
					$rows[ $child->id ] = $child->treeRow( $url, $ref );
				}
				return $rows;
			},
			NULL,
			FALSE,
			FALSE,
			FALSE,
			NULL,
			function()
			{
				$where[] = array( 'ps_parent=0' );
				return \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', $where )->first();
			}
		);
	}
	
	/**
	 * Tree Row
	 *
	 * @param	\IPS\Http\Url	$url	URL
	 * @param	string			$ref	Referer
	 * @return	string
	 */
	public function treeRow( $url, $ref )
	{				
		$childCount = $this->childrenCount( NULL, NULL, TRUE, array( array( 'ps_show=1' ) ) );
		
		$hasCustomFields = FALSE;
		foreach ( $this->custom_fields as $k => $v )
		{
			try
			{
				if ( trim( \IPS\nexus\Package\CustomField::load( $k )->displayValue( $v, TRUE ) ) )
				{
					$hasCustomFields = TRUE;
					break;
				}
			}
			catch( \OutOfRangeException $e ) {}
		}

		try
		{
			$extension = $this->extension();
			$description = $extension::getPurchaseNameInfo( $this );

			if( empty( $description ) )
			{
				$description[] = \IPS\Member::loggedIn()->language()->addToStack( 'purchase_number', FALSE, array( 'sprintf' => array( $this->id ) ) );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			$description = array();
		}
		if ( $this->grouped_renewals )
		{
			$description[] = \IPS\Member::loggedIn()->language()->addToStack('purchase_grouped');
		}
		elseif ( $this->renewals )
		{
			$renewals = (string) $this->renewals;
			if ( $this->renewals->tax )
			{
				$renewals .= \IPS\Member::loggedIn()->language()->addToStack( 'plus_tax_rate', FALSE, array( 'sprintf' => array( $this->renewals->tax->_title ) ) );
			}
			$description[] = $renewals;
		}

		$description = implode( ' &middot; ', $description );

		return \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row(
			$url,
			$this->id,
			( $hasCustomFields and !$childCount ) ? \IPS\Theme::i()->getTemplate( 'purchases', 'nexus' )->link( $this, FALSE, FALSE, TRUE ) : $this->_name,
			$childCount,
			array_merge( array(
				'view'	=> array(
					'link'	=> $this->acpUrl()->setQueryString( 'popup', true ),
					'title'	=> 'view',
					'icon'	=> 'search',
				)
			), $this->buttons( $ref ) ),
			$description,
			$this->getIcon(),
			NULL,
			$this->id == \IPS\Request::i()->root,
			NULL,
			NULL,
			!$this->active ? ( $this->cancelled ? array( 'style5', 'purchase_canceled' ) : array( 'style6', 'purchase_expired' ) ) : NULL,
			$hasCustomFields
		);
	}
	
	/* ActiveRecord */
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'nexus_purchases';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'ps_';
	
	/* !Node */
	
	/**
	 * @brief	Node Title
	 */
	public static $nodeTitle = 'purchases';
	
	/**
	 * @brief	[Node] Parent ID Database Column
	 */
	public static $databaseColumnParent = 'parent';
	
	/**
	 * @brief	[Node] If the node can be "owned", the owner "type" (typically "member" or "group") and the associated database column
	 */
	public static $ownerTypes = array(
		'member'	=> 'member'
	);
	
	/**
	 * Get title
	 *
	 * @return	string
	 */
	public function get__title()
	{
		return $this->name;
	}
	
	/* !Columns */
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->_data['active'] = TRUE; // do directly so it doesn't call onReactivate
		$this->start = new \IPS\DateTime;
		$this->renewal_price = 0;
		$this->renewal_currency = '';
		$this->invoice_pending = NULL;
	}
	
	/**
	 * @brief	Name with sticky fields
	 */
	protected $nameWithStickyFields;
	
	/**
	 * Get name
	 *
	 * @return	string
	 */
	public function get_name()
	{
		if ( !$this->nameWithStickyFields )
		{
			$this->nameWithStickyFields = $this->_data['name'];
			try
			{
				$extension = $this->extension();
				$info = $extension::getPurchaseNameInfo( $this );
				if ( \count( $info ) )
				{
					$this->nameWithStickyFields .= ' (' . implode( ' &middot; ', $info ) . ')';
				}
			}
			catch ( \OutOfRangeException $e ) { }
		}
		return $this->nameWithStickyFields;
	}
	
	/**
	 * Get name without sticky fields
	 *
	 * @return	string
	 */
	public function get__name()
	{
		return $this->_data['name'];
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
	 * Set active
	 *
	 * @param	bool	$active	Is active?
	 * @return	void
	 */
	public function set_active( $active )
	{	
		if ( $this->id )
		{
			if ( $this->_data['active'] and !$active )
			{
				$this->_data['active'] = FALSE;
				$this->onExpire();
			}
			elseif ( !$this->_data['active'] and $active )
			{
				$this->_data['active'] = TRUE;
				$this->onReactivate();
			}
		}
		
		$this->_data['active'] = $active;
	}
	
	/**
	 * Set cancelled
	 *
	 * @param	bool	$cancelled	Is cancelled?
	 * @return	void
	 */
	public function set_cancelled( $cancelled )
	{
		if ( $cancelled )
		{
			$this->_data['active'] = FALSE; // We call directly so onExpire doesn't run (as onCancel will run)
			$this->onCancel();
		}
		else
		{
			if ( !$this->expire or $this->expire->getTimestamp() > time() )
			{
				$this->active = TRUE;
				if ( $this->_data['cancelled'] )
				{
					$this->onReactivate();
				}
			}
		}
		
		$this->_data['cancelled'] = $cancelled;
	}
	
	/**
	 * Get start date
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_start()
	{
		return \IPS\DateTime::ts( $this->_data['start'] );
	}
	
	/**
	 * Set start date
	 *
	 * @param	\IPS\DateTime	$date	The invoice date
	 * @return	void
	 */
	public function set_start( \IPS\DateTime $date )
	{
		$this->_data['start'] = $date->getTimestamp();
	}
	
	/**
	 * Get expire date
	 *
	 * @return	\IPS\DateTime|NULL
	 */
	public function get_expire()
	{
		return ( isset( $this->_data['expire'] ) and $this->_data['expire'] ) ? \IPS\DateTime::ts( $this->_data['expire'] ) : NULL;
	}
	
	/**
	 * Set expire date
	 *
	 * @param	\IPS\DateTime|NULL	$date	The invoice date
	 * @return	void
	 */
	public function set_expire( \IPS\DateTime $date = NULL )
	{
		if ( $date === NULL )
		{
			$this->_data['expire'] = 0;
			$this->active = !$this->cancelled;
		}
		else
		{	
			$this->_data['expire'] = $date->getTimestamp();
			$this->active = ( !$this->cancelled and $date->add( new \DateInterval( 'PT' . \intval( $this->grace_period ) . 'S' ) )->getTimestamp() > time() );
		}
		if ( $this->id )
		{
			$this->onExpirationDateChange();
		}
	}
	
	/**
	 * Get renewal term
	 *
	 * @return	\IPS\nexus\Purchase\RenewalTerm|NULL
	 */
	public function get_renewals()
	{
		if( isset( $this->_data['renewals'] ) and $this->_data['renewals'] )
		{
			$tax = NULL;
			if ( $this->_data['tax'] )
			{
				try
				{
					$tax = \IPS\nexus\Tax::load( $this->_data['tax'] );
				}
				catch ( \Exception $e ) { }
			}
			
			return new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( $this->_data['renewal_price'], $this->_data['renewal_currency'] ), new \DateInterval( 'P' . $this->_data['renewals'] . mb_strtoupper( $this->_data['renewal_unit'] ) ), $tax );
		}
		return NULL;
	}
	
	/**
	 * Set renewal term
	 *
	 * @param	\IPS\nexus\Purchase\RenewalTerm|NULL	$term	The renewal term
	 * @return	void
	 */
	public function set_renewals( \IPS\nexus\Purchase\RenewalTerm $term = NULL )
	{
		if ( $term === NULL OR $term->cost->amount->isZero() )
		{
			$this->_data['renewals'] = 0;
		}
		else
		{
			$data = $term->getTerm();
			$this->_data['renewals'] = $data['term'];
			$this->_data['renewal_unit'] = $data['unit'];
			$this->_data['renewal_price'] = $term->cost->amount;
			$this->_data['renewal_currency'] = $term->cost->currency;
			$this->_data['tax'] = $term->tax ? $term->tax->id : 0;
		}
	}
	
	/**
	 * Get custom fields
	 *
	 * @return	mixed
	 */
	public function get_custom_fields()
	{
		return json_decode( $this->_data['custom_fields'], TRUE ) ?: array();
	}
	
	/**
	 * Set custom fields
	 *
	 * @param	mixed	$customFields	The data
	 * @return	void
	 */
	public function set_custom_fields( $customFields )
	{
		$this->_data['custom_fields'] = json_encode( $customFields );
	}
	
	/**
	 * Get extra information
	 *
	 * @return	mixed
	 */
	public function get_extra()
	{
		return json_decode( $this->_data['extra'], TRUE );
	}
	
	/**
	 * Set extra information
	 *
	 * @param	mixed	$extra	The data
	 * @return	void
	 */
	public function set_extra( $extra )
	{
		$this->_data['extra'] = json_encode( $extra );
	}
		
	/**
	 * Set parent purchase
	 *
	 * @param	\IPS\nexus\Purchase
	 * @return	void
	 */
	public function set_parent( \IPS\nexus\Purchase $purchase = NULL )
	{
		$this->_data['parent'] = $purchase ? $purchase->id : 0;
	}
	
	/**
	 * Get pending invoice
	 *
	 * @return	\IPS\nexus\Invoice
	 */
	public function get_invoice_pending()
	{
		try
		{
			return $this->_data['invoice_pending'] ? \IPS\nexus\Invoice::load( $this->_data['invoice_pending'] ) : NULL;
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Set pending invoice
	 *
	 * @param	\IPS\nexus\Invoice
	 * @return	void
	 */
	public function set_invoice_pending( \IPS\nexus\Invoice $invoice = NULL )
	{
		$this->_data['invoice_pending'] = $invoice ? $invoice->id : 0;
		
		if ( !$invoice )
		{
			$this->_data['invoice_warning_sent'] = FALSE;
		}
	}
	
	/**
	 * Get member to receive payments
	 *
	 * @return	\IPS\Member
	 */
	public function get_pay_to()
	{
		try
		{
			return $this->_data['pay_to'] ? \IPS\nexus\Customer::load( $this->_data['pay_to'] ) : NULL;
		}
		catch ( \Exception $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Set member to receive payments
	 *
	 * @param	\IPS\Member
	 * @return	void
	 */
	public function set_pay_to( \IPS\Member $member )
	{
		$this->_data['pay_to'] = $member->member_id;
	}
	
	/**
	 * Get fee to commission on renewal charges
	 *
	 * @return	\IPS\nexus\Money|NULL
	 */
	public function get_fee()
	{
		if ( $this->_data['fee'] and $this->renewals )
		{
			try
			{
				return new \IPS\nexus\Money( $this->_data['fee'], $this->renewals->cost->currency );
			}
			catch ( \Exception $e ) { }
		}
		return NULL;
	}
	
	/**
	 * Set fee to commission on renewal charges
	 *
	 * @param	\IPS\nexus\Money|NULL	$fee	The fee
	 * @return	void
	 */
	public function set_fee( \IPS\nexus\Money $fee = NULL )
	{
		$this->_data['fee'] = $fee ? $fee->amount : NULL;
	}
	
	/**
	 * Get original invoice
	 *
	 * @return	\IPS\nexus\Invoice
	 */
	public function get_original_invoice()
	{
		return \IPS\nexus\Invoice::load( $this->_data['original_invoice'] );
	}
	
	/**
	 * Set original invoice
	 *
	 * @param	\IPS\nexus\Invoice
	 * @return	void
	 */
	public function set_original_invoice( \IPS\nexus\Invoice $invoice )
	{
		$this->_data['original_invoice'] = $invoice->id;
	}
	
	/**
	 * Get grouped renewals information
	 *
	 * @return	array
	 */
	public function get_grouped_renewals()
	{
		return $this->_data['grouped_renewals'] ? json_decode( $this->_data['grouped_renewals'], TRUE ) : NULL;
	}
	
	/**
	 * Set grouped renewals information
	 *
	 * @param	array|NULL	$data	The data
	 * @return	void
	 */
	public function set_grouped_renewals( $data )
	{
		$this->_data['grouped_renewals'] = !\is_null( $data ) ? json_encode( $data ) : NULL;
	}
	
	/**
	 * Get billing agreement
	 *
	 * @return	\IPS\nexus\Customer\BillingAgreement|NULL
	 */
	public function get_billing_agreement()
	{
		if ( $this->_data['billing_agreement'] )
		{
			try
			{
				return \IPS\nexus\Customer\BillingAgreement::load( $this->_data['billing_agreement'] );
			}
			catch ( \OutOfRangeException $e )
			{
				$this->_data['billing_agreement'] = NULL;
			}
		}
		return NULL;
	}
	
	/**
	 * Set billing agreement
	 *
	 * @param	\IPS\nexus\Customer\BillingAgreement|NULL	$billingAgreement	The billing agreement
	 * @return	void
	 */
	public function set_billing_agreement( \IPS\nexus\Customer\BillingAgreement $billingAgreement = NULL )
	{
		$this->_data['billing_agreement'] = $billingAgreement === NULL ? NULL : $billingAgreement->id;
	}
	
	/* !Properties and syncing */
	
	/**
	 * @brief	Extension
	 */
	protected $extension;
	
	/** 
	 * Get extension
	 *
	 * @return	\IPS\nexus\Invoice\Item\Purchase
	 * @throws	\OutOfRangeException
	 */
	protected function extension()
	{
		if ( $this->extension === NULL )
		{
			/* Load the app. \IPS\Application::load() throws UnexpectedValueException 
				if the Application.php does not exist, which we'll standardize to
				OutOfRangeException so areas calling this method only need to check
				for that */
			try
			{
				$app = \IPS\Application::load( $this->app );
			}
			catch ( \UnexpectedValueException $e )
			{
				throw new \OutOfRangeException;
			}
			
			/* Find the extension */
			foreach ( $app->extensions( 'nexus', 'Item', FALSE ) as $ext )
			{
				if ( $ext::$type == $this->type )
				{
					$this->extension = $ext;
					break;
				}
			}
			
			/* Don't have it? */
			if ( !$this->extension )
			{
				throw new \OutOfRangeException;
			}
		}
		return $this->extension;
	}
	
	/** 
	 * Get icon
	 *
	 * @return	string
	 */
	public function getIcon()
	{
		try
		{
			$extension = $this->extension();
			return $extension::getIcon( $this );
		}
		catch ( \OutOfRangeException $e )
		{
			return 'question';
		}
	}
	
	/** 
	 * Get icon
	 *
	 * @return	string
	 */
	public function getTypeTitle()
	{
		try
		{
			$extension = $this->extension();
			return $extension::getTypeTitle( $this );
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/** 
	 * Get image
	 *
	 * @return	string
	 */
	public function image()
	{
		try
		{
			$extension = $this->extension();
			return $extension::purchaseImage( $this );
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/** 
	 * Get ACP Page HTML
	 *
	 * @return	string
	 */
	public function acpPage()
	{
		try
		{
			$extension = $this->extension();
			return $extension::acpPage( $this );
		}
		catch ( \OutOfRangeException | \BadMethodCallException $e )
		{
			return '';
		}
	}
	
	/** 
	 * ACP Edit Form
	 *
	 * @param	\IPS\Helpers\Form				$form		The form
	 * @param	\IPS\nexus\Purchase\RenewalTerm	$renewals	The renewal term
	 * @return	string
	 */
	public function acpEdit( \IPS\Helpers\Form $form, $renewals )
	{
		try
		{
			$extension = $this->extension();
			return $extension::acpEdit( $this, $form, $renewals );
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/** 
	 * ACP Edit Save
	 *
	 * @param	array	$values		Values from form
	 * @return	string
	 */
	public function acpEditSave( array $values )
	{
		try
		{
			$extension = $this->extension();
			return $extension::acpEditSave( $this, $values );
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/** 
	 * Get Client Area Page HTML
	 *
	 * @return	array
	 */
	public function clientAreaPage()
	{
		try
		{
			$extension = $this->extension();
			return $extension::clientAreaPage( $this );
		}
		catch ( \OutOfRangeException | \BadMethodCallException $e )
		{
			return array( 'packageInfo' => '', 'purchaseInfo' => '' );
		}
	}
	
	/** 
	 * Perform ACP Action
	 *
	 * @return	void
	 */
	public function acpAction()
	{
		try
		{
			$extension = $this->extension();
			return $extension::acpAction( $this );
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/** 
	 * Perform Client Area Action
	 *
	 * @return	void
	 */
	public function clientAreaAction()
	{
		try
		{
			$extension = $this->extension();
			return $extension::clientAreaAction( $this );
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/** 
	 * Get ACP Support View HTML
	 *
	 * @return	string
	 */
	public function acpSupportView()
	{
		try
		{
			$extension = $this->extension();
			return $extension::acpSupportView( $this );
		}
		catch ( \OutOfRangeException $e )
		{
			return '';
		}
	}
	
	/**
	 * Support Severity
	 *
	 * @return	\IPS\nexus\Support\Severity|NULL
	 */
	public function supportSeverity()
	{
		try
		{
			$extension = $this->extension();
			return $extension::supportSeverity( $this );
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/** 
	 * Get renewal payment methods IDs
	 *
	 * @return	array|NULL
	 */
	public function renewalPaymentMethodIds()
	{
		try
		{
			$extension = $this->extension();
			return $extension::renewalPaymentMethodIds( $this );
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
			
	/**
	 * Call on*
	 *
	 * @param	string	$method	Method
	 * @param	array	$params	Params
	 * @return	mixed
	 */
	public function __call( $method, $params )
	{
		if ( mb_substr( $method, 0, 2 ) === 'on' )
		{
			try
			{
				return \call_user_func_array( array( $this->extension(), $method ), array_merge( array( $this ), $params ) );
			}
			catch ( \OutOfRangeException $e ) { }
		}
		throw new \BadMethodCallException;
	}
	
	/**
	 * Purchase can be reactivated in the ACP?
	 *
	 * @param	\IPS\nexus\Purchase $purchase	The purchase
	 * @param	NULL				$error		Error to show, passed by reference
	 * @return	bool
	 */
	public function canAcpReactivate( &$error=NULL )
	{
		$extension = $this->extension();
		return $extension::canAcpReactivate( $this, $error );
	}
	
	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse		int									id				ID number
	 * @apiresponse		string								name			Name
	 * @apiresponse		string								itemApp			Key for application. For example, 'nexus' for products and renewals; 'downloads' for Downloads files
	 * @apiresponse		string								itemType		Key for item type. For example, 'package' for products; 'file' for Downloads files.
	 * @apiresponse		int									itemId			The ID for the item. For example, the product ID or the file ID.
	 * @apiresponse		\IPS\nexus\Customer					customer		Customer
	 * @apiresponse		datetime							purchased		Purchased date
	 * @apiresponse		datetime							expires			Expiration date
	 * @apiresponse		bool								active			If purchase is currently active (not expired)
	 * @apiresponse		bool								canceled		If purchase has been canceled
	 * @apiresponse		\IPS\nexus\Purchase\RenewalTerm		renewalTerm		Renewal term
	 * @apiresponse		object								customFields	Values for custom fields
	 * @apiresponse		\IPS\nexus\Purchase					parent			Parent purchase
	 * @apiresponse		bool								show			If this purchase shows in the client area and AdminCP
	 * @apiresponse		string								licenseKey		License key
	 * @apiresponse		string								image			If the item has a relevant image (for exmaple, product image, Downloads file screenshot), the URL to it
	 * @apiresponse		string								url				The URL for the customer to view this purchase in the client area
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		$parent = NULL;
		if ( $this->parent )
		{
			try
			{
				$parent = static::load( $this->parent )->apiOutput( $authorizedMember );
			}
			catch ( \OutOfRangeException $e ) {}
		}
		
		return array(
			'id'			=> $this->id,
			'name'			=> $this->name,
			'itemApp'		=> $this->app,
			'itemType'		=> $this->type,
			'itemId'		=> $this->item_id,
			'customer'		=> $this->member->apiOutput( $authorizedMember ),
			'purchased'		=> $this->start->rfc3339(),
			'expires'		=> $this->expire ? $this->expire->rfc3339() : null,
			'active'		=> (bool) $this->active,
			'canceled'		=> (bool) $this->cancelled,
			'renewalTerm'	=> $this->renewals ? $this->renewals->apiOutput( $authorizedMember ) : null,
			'customFields'	=> $this->custom_fields,
			'parent'		=> $parent,
			'show'			=> (bool) $this->show,
			'licenseKey'	=> $this->licenseKey() ? $this->licenseKey()->key : null,
			'image'			=> $this->image() ? ( (string) $this->image()->url ) : null,
			'url'			=> (string) $this->url(),
		);
	}
	
	/* !License Keys */
	
	/**
	 * @brief	License key
	 */
	public $licenseKey;
	
	/**
	 * Get license key
	 *
	 * @return	\IPS\nexus\Purchase\LicenseKey|NULL
	 */
	public function licenseKey()
	{
		if ( $this->licenseKey === NULL )
		{
			try
			{
				$this->licenseKey = \IPS\nexus\Purchase\LicenseKey::load( $this->id, 'lkey_purchase' );
			}
			catch ( \OutOfRangeException $e )
			{
				$this->licenseKey = FALSE;
			}
		}
		return $this->licenseKey ?: NULL;
	}
	
	/**
	 * onExpire
	 *
	 * @return	void
	 */
	public function onExpire()
	{
		if ( $licenseKey = $this->licenseKey() )
		{
			$licenseKey->active = FALSE;
			$licenseKey->save();
		}
		
		return $this->__call( 'onExpire', array() );
	}
	
	/**
	 * onCancel
	 *
	 * @return	void
	 */
	public function onCancel()
	{
		if ( $licenseKey = $this->licenseKey() )
		{
			$licenseKey->active = FALSE;
			$licenseKey->save();
		}
		
		return $this->__call( 'onCancel', array() );
	}
	
	/**
	 * onReactivate
	 *
	 * @return	void
	 */
	public function onReactivate()
	{
		if ( $licenseKey = $this->licenseKey() )
		{
			$licenseKey->active = TRUE;
			$licenseKey->save();
		}
		
		return $this->__call( 'onReactivate', array() );
	}
	
	/**
	 * onDelete
	 *
	 * @return	void
	 */
	public function onDelete()
	{
		if ( $licenseKey = $this->licenseKey() )
		{
			$licenseKey->delete();
		}
		
		return $this->__call( 'onDelete', array() );
	}
	
	/* !Client Area */
	
	/**
	 * Can view?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canView( $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		/* Show only purchases from active applications */
		try
		{
			$app = \IPS\Application::load( $this->app );
		}
		catch ( \UnexpectedValueException $e )
		{
			return FALSE;
		}
		
		if( !$app->canAccess() )
		{
			return FALSE;
		}

		if ( $this->member->member_id === $member->member_id or array_key_exists( $member->member_id, iterator_to_array( $this->member->alternativeContacts( array( \IPS\Db::i()->findInSet( 'purchases', array( $this->id ) ) ) ) ) ) )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Admin can change expire date / renewal term?
	 *
	 * @return	bool
	 */
	public function canChangeExpireDate()
	{
		if ( $this->billing_agreement and !$this->billing_agreement->canceled )
		{
			return FALSE;
		}
		
		try
		{
			$extension = $this->extension();
			return $extension::canChangeExpireDate( $this );
		}
		catch ( \OutOfRangeException $e )
		{
			return TRUE;
		}
	}
	
	/**
	 * Can Renew Until
	 *
	 * @param	\IPS\Member|NULL	$member		The member to check (NULL for currently logged in member)
	 * @param	bool				$inCycles	Get in cycles rather than a date?
	 * @param	bool				$admin		If TRUE, is for ACP. If FALSE, is for front-end.
	 * @return	\IPS\DateTime|bool	TRUE means can renew as much as they like. FALSE means cannot renew at all. \IPS\DateTime (or int if $inCycles is TRUE) means can renew until that date
	 */
	public function canRenewUntil( \IPS\Member $member = NULL, $inCycles = FALSE, $admin = FALSE )
	{
		/* Can this purchase be renewed at all? */
		if ( !$this->canBeRenewed() )
		{
			return FALSE;
		}

		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( !$admin and $this->member->member_id !== $member->member_id and !array_key_exists( $member->member_id, iterator_to_array( $this->member->alternativeContacts( array( \IPS\Db::i()->findInSet( 'purchases', array( $this->id ) ) . ' AND billing=1' ) ) ) ) )
		{
			return FALSE;
		}
		
		if ( $this->cancelled or !$this->renewals or ( $this->billing_agreement and !$this->billing_agreement->canceled ) )
		{
			return FALSE;
		}
		
		if ( !$admin and $pendingInvoice = $this->invoice_pending and $pendingInvoice->status === $pendingInvoice::STATUS_PENDING )
		{
			return FALSE;
		}
				
		try
		{
			$extension = $this->extension();
			$date = $extension::canRenewUntil( $this, $admin );
					
			if ( $inCycles and $date instanceof \IPS\DateTime )
			{
				$now = \IPS\DateTime::create();
				$cycles = 0;
				while ( $now->add( $this->renewals->interval )->getTimestamp() < $date->getTimestamp() )
				{
					$cycles++;
				}
				return $cycles;
			}
			else
			{
				return $date;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			return FALSE;
		}
	}

	/**
	 * Purchase can be renewed?
	 *
	 * @return	boolean|NULL
	 */
	public function canBeRenewed()
	{
		try
		{
			$extension = $this->extension();
			return $extension::canBeRenewed( $this );
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Can Cancel
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canCancel( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		if ( $this->member->member_id !== $member->member_id and !array_key_exists( $member->member_id, iterator_to_array( $this->member->alternativeContacts( array( \IPS\Db::i()->findInSet( 'purchases', array( $this->id ) ) . ' AND billing=1' ) ) ) ) )
		{
			return FALSE;
		}
		
		return !$this->grouped_renewals and ( !$this->billing_agreement or $this->billing_agreement->canceled ) and $this->renewals and $this->active;
	}

	/**
	 * @brief	Cached URL
	 */
	protected $_url	= NULL;

	/**
	 * Get URL
	 *
	 * @return	\IPS\Http\Url
	 */
	public function url()
	{
		if( $this->_url === NULL )
		{
			$this->_url = \IPS\Http\Url::internal( "app=nexus&module=clients&controller=purchases&do=view&id={$this->id}", 'front', 'clientspurchase', array( \IPS\Http\Url\Friendly::seoTitle( $this->name ) ) );
		}

		return $this->_url;
	}
	
	/* !Actions */
		
	/**
	 * Transfer
	 *
	 * @param	\IPS\Member	$newCustomer
	 * @return	void
	 */
	public function transfer( \IPS\Member $newCustomer )
	{
		$this->onTransfer( $newCustomer );
		$this->member = $newCustomer;
		$this->save();
		
		foreach ( $this->children() as $child )
		{
			$child->transfer( $newCustomer );
		}
	}
	
	/**
	 * Delete
	 *
	 * @param	bool	$deleteChildren		If TRUE, child purchases will also be deleted. If FALSE, they will become unassociated.
	 * @return	void
	 */
	public function delete( $deleteChildren = TRUE )
	{
		\IPS\File::unclaimAttachments( 'nexus_Purchases', $this->id, NULL, 'purchase' );
		
		foreach ( $this->children( NULL ) as $child )
		{
			if ( $deleteChildren )
			{
				$child->delete();
			}
			else
			{
				$child->parent = NULL;
				$child->save();
			}
		}

		try
		{
			$this->onDelete();
		}
		catch ( \BadMethodCallException $e ) {}

		parent::delete();
	}
	
	/* !Grouping */
	
	/**
	 * Group with parent
	 *
	 * @return	void
	 * @throws	\LogicException
	 */
	public function groupWithParent()
	{		
		/* Get the parent */
		$parent = $this->parent();
		if ( !$parent )
		{
			throw new \BadMethodCallException('no_parent');
		}
				
		/* If we have a renewal term, we need to merge it with the parent... */
		if ( $this->renewals )
		{
			/* Remember what our term is */
			$term = $this->renewals->getTerm();
			$this->grouped_renewals = array( 'term' => $term['term'], 'unit' => $term['unit'], 'price' => $this->renewals->cost->amount, 'currency' => $this->renewals->cost->currency, 'tax' => $this->renewals->tax ? $this->renewals->tax->id : 0 );
			$this->save();
			
			/* Add our term to the parent... */
			if ( !$this->cancelled )
			{
				/* If the parent also has a renewal term, merge them */
				if ( $parent->renewals )
				{
					$parent->renewals = new \IPS\nexus\Purchase\RenewalTerm( $parent->renewals->add( $this->renewals ), $parent->renewals->interval, $parent->renewals->tax );
					$parent->save();
				}
				/* Otherwise just set the parent to this */
				else
				{
					$parent->renewals = $this->renewals;
					if ( !$parent->expire )
					{
						$parent->expire = $this->expire;
					}
					$parent->save();
				}
			}
			
			/* Cancel any pending invoices as they're no longer valid */
			if ( $invoice = $parent->invoice_pending )
			{
				$invoice->status = $invoice::STATUS_CANCELED;
				$invoice->save();
			}
			if ( $invoice = $this->invoice_pending )
			{
				$invoice->status = $invoice::STATUS_CANCELED;
				$invoice->save();
			}
		}
		else
		{
			$this->grouped_renewals = array();
		}
		
		/* Update this purchase */
		$this->expire = NULL;
		$this->save();
	}
	
	/**
	 * Ungroup from parent
	 *
	 * @return	void
	 * @throws	\LogicException
	 */
	public function ungroupFromParent()
	{		
		/* Get the parent */
		$parent = $this->parent();
		if ( !$parent )
		{
			throw new \BadMethodCallException('no_parent');
		}
		
		/* If we have a renewal term, we need to remove it from the parent... */
		if ( $this->renewals )
		{
			/* Restore parent renewal term */
			if ( $parent->renewals and !$this->cancelled )
			{
				$newRenewalPrice = $parent->renewals->subtract( $this->renewals );
				if ( $newRenewalPrice->amount->isZero() )
				{
					$parent->renewals = NULL;
				}
				else
				{
					$parent->renewals = new \IPS\nexus\Purchase\RenewalTerm( $newRenewalPrice, $parent->renewals->interval, $parent->renewals->tax );
				}
				$parent->save();
			}
			
			/* Restore this purchase renewal term */
			$groupedRenewals = $this->grouped_renewals;
			if ( $groupedRenewals['term'] and $groupedRenewals['unit'] )
			{
				try
				{
					$tax = ( isset( $groupedRenewals['tax'] ) and $groupedRenewals['tax'] ) ? \IPS\nexus\Tax::load( $groupedRenewals['tax'] ) : NULL;
				}
				catch ( \OutOfRangeException $e )
				{
					$tax = NULL;
				}
				$this->renewals = new \IPS\nexus\Purchase\RenewalTerm(
					new \IPS\nexus\Money( $groupedRenewals['price'], isset( $groupedRenewals['currency'] ) ? $groupedRenewals['currency'] : $this->member->defaultCurrency() ),
					new \DateInterval( 'P' . $groupedRenewals['term'] . mb_strtoupper( $groupedRenewals['unit'] ) ),
					$tax
				);
			}
			$this->expire = $parent->expire;
		}
		
		/* Ungroup */
		$this->grouped_renewals = NULL;
		$this->save();
		
		/* Cancel any pending invoices as they're no longer valid */
		if ( $invoice = $parent->invoice_pending )
		{
			$invoice->status = $invoice::STATUS_CANCELED;
			$invoice->save();
		}
		if ( $invoice = $this->invoice_pending )
		{
			$invoice->status = $invoice::STATUS_CANCELED;
			$invoice->save();
		}
	}
	
	/* !ACP Display */
		
	/**
	 * Get the URL of the AdminCP page for this node
	 *
	 * @param   string|NULL  $do The "do" query parameter of the url (e.g. 'form', 'permissions', etc).
	 *
	 * @return \IPS\Http\Url | NULL
	 */
	public function acpUrl( $do="form" )
	{
		return \IPS\Http\Url::internal( "app=nexus&module=customers&controller=purchases&do=view&id={$this->id}", 'admin' );
	}
	
	/**
	 * ACP Buttons
	 *
	 * @param	string	$ref	Referer
	 * @return	array
	 */
	public function buttons( $ref='v' )
	{
		$return = array();
		
		$url = $this->acpUrl()->setQueryString( 'r', $ref );
		
		if( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'purchases_edit' ) )
		{
			$return['edit'] = array(
				'icon'	=> 'pencil',
				'title'	=> 'edit',
				'link'	=> $url->setQueryString( 'do', 'edit' ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit') )
			);
		}
		
		$extension = NULL;
		try
		{
			$extension = $this->extension();
			$return = array_merge( $return, $extension::acpButtons( $this, $url ) );
		}
		catch ( \OutOfRangeException $e ) { }
		
		$parent = $this->parent();
				
		if ( !$this->grouped_renewals and ( !$this->billing_agreement or $this->billing_agreement->canceled ) and $this->renewals and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'invoices_add' ) )
		{
			$return['renew'] = array(
				'icon'	=> 'refresh',
				'title'	=> 'generate_renewal_invoice',
				'link'	=> $url->setQueryString( 'do', 'renew' ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('generate_renewal_invoice') )
			);
		}
		
		if ( ( !$this->billing_agreement or $this->billing_agreement->canceled ) and $this->parent() and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'purchases_edit' ) )
		{
			if ( !$this->grouped_renewals )
			{
				$return['group'] = array(
					'icon'	=> 'compress',
					'title'	=> 'group_with_parent',
					'link'	=> $url->setQueryString( 'do', 'group' )->csrf(),
					'data'	=> array( 'confirm' => '', 'confirmSubMessage' => \IPS\Member::loggedIn()->language()->addToStack('group_with_parent_info') )
				);
			}
			else
			{
				$return['group'] = array(
					'icon'	=> 'expand',
					'title'	=> 'ungroup_from_parent',
					'link'	=> $url->setQueryString( 'do', 'ungroup' )->csrf(),
					'data'	=> array( 'confirm' => '', 'confirmSubMessage' => \IPS\Member::loggedIn()->language()->addToStack('ungroup_from_parent_info') )
				);
			}
		}
		
		if( ( !$this->billing_agreement or $this->billing_agreement->canceled ) and !$this->grouped_renewals and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'purchases_transfer' ) )
		{
			$return['transfer'] = array(
				'icon'	=> 'user',
				'title'	=> 'transfer',
				'link'	=> $url->setQueryString( 'do', 'transfer' ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('transfer') )
			);
		}
		
		if ( ( !$this->billing_agreement or $this->billing_agreement->canceled ) and ( !$this->grouped_renewals or !$parent or !$parent->billing_agreement or $parent->billing_agreement->canceled ) and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'purchases_cancel' ) )
		{
			if ( $this->cancelled )
			{
				if ( $extension AND $extension::canAcpReactivate( $this ) )
				{
					$return['reactivate'] = array(
						'icon'	=> 'check',
						'title'	=> 'reactivate',
						'link'	=> $url->setQueryString( 'do', 'reactivate' )->csrf(),
						'data'	=> array( 'confirm' => '' )
					);
				}
			}
			else
			{
				$return['cancel'] = array(
					'icon'	=> 'times',
					'title'	=> 'cancel',
					'link'	=> $url->setQueryString( 'do', 'cancel' ),
					'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('cancel') )
				);
			}
		}
				
		if ( ( !$this->billing_agreement or $this->billing_agreement->canceled ) and ( !$this->grouped_renewals or !$parent or !$parent->billing_agreement or $parent->billing_agreement->canceled ) and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'purchases_delete' ) )		
		{
			$return['delete'] = array(
				'icon'	=> 'times-circle',
				'title'	=> 'delete',
				'link'	=> $url->setQueryString( 'do', 'delete' ),
				'data'	=> array( 'delete' => '' )
			);
		}
		
		return $return;
	}
}