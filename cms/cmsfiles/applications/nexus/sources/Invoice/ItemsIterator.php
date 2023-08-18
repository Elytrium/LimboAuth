<?php
/**
 * @brief		Invoice Items Iterator
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		24 Mar 2014
 */

namespace IPS\nexus\Invoice;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Invoice Items Iterator
 */
class _ItemsIterator extends \ArrayIterator
{
	/**
	 * @brief	Currency
	 */
	public $currency;
	
	/**
	 * @brief	Class Names
	 */
	protected static $classnames = NULL;
	
	/**
	 * Convert array into object
	 *
	 * @param	array	$data	Data
	 * @return	\IPS\nexus\Invoice\Item
	 */
	public function arrayToObject( $data )
	{
		if ( \is_object( $data ) )
		{
			return $data;
		}
				
		/* Load correct class */
		if ( $data['act'] === 'renewal' )
		{
			$obj = new \IPS\nexus\Invoice\Item\Renewal( $data['itemName'], new \IPS\nexus\Money( $data['cost'], $this->currency ) );
		}
		else
		{
			if ( static::$classnames === NULL )
			{
				static::$classnames = array();
				foreach ( \IPS\Application::allExtensions( 'nexus', 'Item', FALSE, NULL, NULL, FALSE ) as $ext )
				{
					static::$classnames[ $ext::$application ][ $ext::$type ] = $ext;
				}
			}
			if ( $data['app'] === 'nexus' and \in_array( $data['type'], array( 'product', 'ad' ) ) )
			{
				$data['type'] = 'package';
			}
			
			$class = isset( static::$classnames[ $data['app'] ][ $data['type'] ] ) ? static::$classnames[ $data['app'] ][ $data['type'] ] : NULL;
			if ( !$class )
			{
				$class = 'IPS\nexus\extensions\nexus\Item\MiscellaneousCharge';
			}
			$obj = new $class( $data['itemName'], new \IPS\nexus\Money( number_format( $data['cost'], \IPS\nexus\Money::numberOfDecimalsForCurrency( $this->currency ), '.', '' ), $this->currency ) );
		}
		
		/* Basic information */
		$obj->quantity = $data['quantity'];
		$obj->id = $data['itemID'];
		$obj->appKey = $data['app'];
		$obj->typeKey = $data['type'];
		
		/* Details */
		$details = array();
		$purchaseDetails = array();
		if ( isset( $data['cfields'] ) and $data['cfields'] )
		{
			foreach( $data['cfields'] AS $key => $value )
			{
				$purchaseDetails[ $key ] = $value;
				try
				{
					$field = \IPS\nexus\Package\CustomField::load( $key );
					if ( $field->invoice )
					{
						switch( $field->type )
						{
							case 'UserPass':
								$value = json_decode( \IPS\Text\Encrypt::fromTag( $value )->decrypt(), TRUE );
								$display = array();
								if ( $value['un'] )
								{
									$display[] = $value['un'];
								}
								
								if ( $value['pw'] )
								{
									$display[] = $value['pw'];
								}
								
								$details[ $key ] = implode( ' &sdot; ', $display );
								break;
							
							case 'Ftp':
								$value = json_decode( \IPS\Text\Encrypt::fromTag( $value )->decrypt(), TRUE );
								$details[ $key ] = (string) \IPS\Http\Url::createFromComponents(
									$value['server'],
									( isset( $value['protocol'] ) ) ? "{$value['protocol']}://" : 'ftp://',
									( isset( $value['path'] ) ) ? $value['path'] : NULL,
									NULL,
									( isset( $value['port'] ) ) ? $value['port'] : NULL,
									( isset( $value['un'] ) ) ? $value['un'] : NULL,
									( isset( $value['pw'] ) ) ? $value['pw'] : NULL
								);
								break;
								
							default:
								$details[ $key ] = $value;
								break;
						}
					}
				}
				catch( \OutOfRangeException $e ) {}
			}
		}

		$obj->details = $details;
		$obj->purchaseDetails = $purchaseDetails;
		
		/* Tax */
		if ( isset( $data['tax'] ) AND $data['tax'] )
		{
			try
			{
				$obj->tax = \IPS\nexus\Tax::load( $data['tax'] );
			}
			catch ( \Exception $e ) { }
		}
		
		/* Renewal terms */
		if ( isset( $data['renew_term'] ) and $data['renew_term'] )
		{
			$obj->renewalTerm = new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( $data['renew_cost'], $this->currency ), new \DateInterval( 'P' . $data['renew_term'] . mb_strtoupper( $data['renew_units'] ) ), $obj->tax, FALSE, isset( $data['grace_period'] ) ? new \DateInterval( 'PT' . $data['grace_period'] . 'S' ) : NULL );
			if ( isset( $data['initial_interval_term'] ) and $data['initial_interval_term'] )
			{
				$obj->initialInterval = new \DateInterval( 'P' . $data['initial_interval_term'] . mb_strtoupper( $data['initial_interval_unit'] ) );
			}
		}
		
		/* Expire Date */
		if ( isset( $data['expires'] ) and $data['expires'] )
		{
			$obj->expireDate = \IPS\DateTime::ts( $data['expires'] );
		}
		
		/* Available methods */
		if ( isset( $data['methods'] ) and $data['methods'] !== '*' )
		{
			$obj->paymentMethodIds = $data['methods'];
		}
		
		/* Shipping */
		if ( isset( $data['physical'] ) AND $data['physical'] )
		{
			$obj->physical = TRUE;
			if ( isset( $data['shipping'] ) and $data['shipping'] !== '*' )
			{
				$obj->shippingMethodIds = $data['shipping'];
			}
			$obj->weight = new \IPS\nexus\Shipping\Weight( $data['weight'] );
			$obj->length = new \IPS\nexus\Shipping\Length( $data['length'] );
			$obj->width = new \IPS\nexus\Shipping\Length( $data['width'] );
			$obj->height = new \IPS\nexus\Shipping\Length( $data['height'] );
			
			if ( isset( $data['chosen_shipping'] ) and $data['chosen_shipping'] )
			{
				$obj->chosenShippingMethodId = $data['chosen_shipping'];
			}
		}
		
		/* Parent */
		if ( isset( $data['assoc'] ) )
		{
			if ( $data['assocBought'] )
			{
				try
				{
					$obj->parent = \IPS\nexus\Purchase::load( $data['assoc'] );
				}
				catch ( \OutOfRangeException $e ) { }
			}
			else
			{
				$obj->parent = $data['assoc'];
			}
			
			$obj->groupWithParent = isset( $data['groupParent'] ) ? $data['groupParent'] : FALSE;
		}
		
		/* Paying another member? */
		if ( isset( $data['payTo'] ) and $data['payTo'] )
		{
			try
			{
				$obj->payTo = \IPS\nexus\Customer::load( $data['payTo'] );
				if ( isset( $data['commission'] ) )
				{
					$obj->commission = ( $data['commission'] <= 100 ) ? $data['commission'] : 100;
				}
				if ( isset( $data['fee'] ) )
				{
					$obj->fee = new \IPS\nexus\Money( $data['fee'], $this->currency );
				}
			}
			catch ( \Exception $e ) { }
		}
		
		/* URIs? */
		if ( isset( $data['itemURI'] ) )
		{
			$obj->itemURI = $data['itemURI'];
		}
		if ( isset( $data['adminURI'] ) )
		{
			$obj->adminURI = $data['adminURI'];
		}
		
		/* Extra */
		if ( isset( $data['extra'] ) )
		{
			$obj->extra = $data['extra'];
		}
		
		return $obj;
	}
	
	/**
	 * Get current
	 *
	 * @return	\IPS\Patterns\ActiveRecord
	 */
	public function current()
	{
		return $this->arrayToObject( parent::current() );
	}
	
	/**
	 * Get offset
	 *
	 * @param	string	$index	Index
	 * @return	mixed
	 */
	public function offsetGet( $index )
	{
		return $this->arrayToObject( parent::offsetGet( $index ) );
	}
}