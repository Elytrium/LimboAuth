<?php
/**
 * @brief		License Key API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		01 Apr 2015
 */

\define('REPORT_EXCEPTIONS', TRUE);
require_once '../../../../init.php';
\IPS\Dispatcher\External::i();

if ( \IPS\NEXUS_LKEY_API_ALLOW_IP_OVERRIDE )
{
	$_SERVER['REMOTE_ADDR'] = isset( \IPS\Request::i()->ip ) ? \IPS\Request::i()->ip : $_SERVER['REMOTE_ADDR'];
}

/**
 * API Exception
 */
class ApiException extends \Exception { }

/**
 * API class to verify license keys
 */
class Api
{
	/**
	 * Output Error
	 *
	 * @param	int		$code			Status code
	 * @param	string	$message		Status message
	 * @return	void
	 */
	public function error( $code, $message )
	{
		\IPS\Output::i()->pageCaching = FALSE;
		\IPS\Output::i()->sendOutput( json_encode( array( 'errorCode' => $code, 'errorMessage' => $message ) ), 400, 'application/json' );
	}
	
	/**
	 * Get key
	 *
	 * @param	bool	$validateIdentifier	Should the identifier be validated?
	 * @return	\IPS\nexus\Purchase\LicenseKey
	 */
	protected function getKey( $validateIdentifier=TRUE )
	{
		try
		{	
			$key = \IPS\nexus\Purchase\LicenseKey::load( isset( \IPS\Request::i()->key ) ? \IPS\Request::i()->key : NULL );
			
			if ( $key->key !== \IPS\Request::i()->key )
			{
				throw new ApiException( 'BAD_KEY_OR_ID', 105 );
			}
			
			if ( $validateIdentifier and $identifier = $this->getIdentifier( $key ) and ( !isset( \IPS\Request::i()->identifier ) or $identifier != \IPS\Request::i()->identifier ) )
			{
				throw new ApiException( 'BAD_KEY_OR_ID', 101 );
			}
			
			if ( !$key->active )
			{
				throw new ApiException( 'INACTIVE', 102 );
			}
			if ( $key->purchase->cancelled )
			{
				throw new ApiException( 'INACTIVE', 103 );
			}
			if ( !$key->purchase->active )
			{
				throw new ApiException( 'INACTIVE', 104 );
			}
						
			return $key;
		}
		catch ( \OutOfRangeException $e )
		{
			throw new ApiException( 'BAD_KEY_OR_ID', 101 );
		}
	}
	
	/**
	 * Get identifier
	 *
	 * @param	\IPS\nexus\Purchase\LicenseKey	$key	The license key
	 * @return	string|NULL
	 */
	protected function getIdentifier( \IPS\nexus\Purchase\LicenseKey $key )
	{
		$identifier = NULL;
		switch ( $key->identifier )
		{
			case 'name':
				$identifier = \IPS\nexus\Customer::load( $key->member )->cm_name;
				break;
				
			case 'email':
				$identifier = \IPS\nexus\Customer::load( $key->member )->email;
				break;
				
			case 'username':
				$identifier = \IPS\nexus\Customer::load( $key->member )->name;
				break;
		
			default:
				$cfields = $key->purchase->custom_fields;
				if ( isset( $cfields[ $key->identifier ] ) )
				{
					$identifier = $cfields[ $key->identifier ];
				}
				break;
		}
		return $identifier;
	}
	
	/**
	 * Activate
	 *
	 * @return	void
	 */
	public function activate()
	{
		$key = $this->getKey( FALSE );
		
		if ( $key->max_uses != -1 and $key->uses >= $key->max_uses )
		{
			throw new ApiException( 'MAX_USES', 201 );
		}
		
		$identifier = $this->getIdentifier( $key );
		$providedIdentifier = isset( \IPS\Request::i()->identifier ) ? \IPS\Request::i()->identifier : NULL;
		if ( isset( \IPS\Request::i()->setIdentifier ) and \IPS\Request::i()->setIdentifier )
		{
			if ( $identifier != $providedIdentifier )
			{
				if ( $identifier or \in_array( $key->identifier, array( 'name', 'email', 'username' ) ) )
				{
					throw new ApiException( 'BAD_KEY_OR_ID', 101 );
				}
				else
				{
					$cfields = $key->purchase->custom_fields;
					$cfields[ $key->identifier ] = \IPS\Request::i()->setIdentifier;
					$key->purchase->custom_fields = $cfields;
					$key->purchase->save();
				}
			}
		}
		elseif( $identifier != $providedIdentifier )
		{
			throw new ApiException( 'BAD_KEY_OR_ID', 101 );
		}
		
		if ( \defined( 'NEXUS_LKEY_API_ALLOW_IP_OVERRIDE' ) )
		{
			$ip = $this->params['ip'] ? $this->params['ip'] : $_SERVER['REMOTE_ADDR'];
		}
		else
		{
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		
		$activateData = $key->activate_data;
		$k = empty( $activateData ) ? 0 : max( array_keys( $activateData ) );
		$k++;
		$activateData[ $k ] = array(
			'activated'		=> time(),
			'ip'			=> $ip,
			'last_checked'	=> 0,
			'extra'			=> isset( \IPS\Request::i()->extra ) ? json_decode( \IPS\Request::i()->extra ) : NULL,
		);
		
		$key->activate_data = $activateData;
		$key->uses++;
		$key->save();
		
		\IPS\nexus\Customer::load( $key->member )->log( 'lkey', array( 'type' => 'activated', 'key' => $key->key, 'ps_id' => $key->purchase->id ), FALSE );
		
		\IPS\Output::i()->pageCaching = FALSE;
		\IPS\Output::i()->sendOutput( json_encode( array( 'response' => 'OKAY', 'usage_id' => $k ) ), 200, 'application/json' );
	}
	
	/**
	 * Check
	 *
	 * @return	void
	 */
	public function check()
	{
		try
		{
			$key = $this->getKey();
		}
		catch ( ApiException $e )
		{
			switch ( $e->getCode() )
			{
				case 102:
				case 103:
					\IPS\Output::i()->sendOutput( json_encode( array( 'status' => 'INACTIVE' ) ), 200, 'application/json', \IPS\Output::getCacheHeaders( time(), 360 ) );
				case 104:
					\IPS\Output::i()->sendOutput( json_encode( array( 'status' => 'EXPIRED' ) ), 200, 'application/json', \IPS\Output::getCacheHeaders( time(), 360 ) );
					
				default:
					throw $e;
			}
		}
		
		$activateData = $key->activate_data;
		if ( !isset( \IPS\Request::i()->usage_id ) or !isset( $activateData[ \IPS\Request::i()->usage_id ] ) )
		{
			throw new ApiException( 'BAD_USAGE_ID', 303 );
		}
		if ( \IPS\NEXUS_LKEY_API_CHECK_IP and $activateData[ \IPS\Request::i()->usage_id ]['ip'] != $_SERVER['REMOTE_ADDR'] )
		{
			throw new ApiException( 'BAD_IP', 304 );
		}
		
		$activateData[ \IPS\Request::i()->usage_id ]['last_checked'] = time();
		$key->activate_data = $activateData;
		$key->save();
		
		\IPS\Output::i()->sendOutput( json_encode( array( 'status' => 'ACTIVE', 'uses' => $key->uses, 'max_uses' => $key->max_uses ) ), 200, 'application/json', \IPS\Output::getCacheHeaders( time(), 360 ) );
	}
	
	/**
	 * Get Information
	 *
	 * @return	void
	 */
	public function info()
	{
		$key = $this->getKey();
		
		$children = array();
		foreach ( $key->purchase->children( NULL ) as $child )
		{
			$children[ $child->id ] = array(
				'id'		=> $child->id,
				'name'		=> $child->name,
				'app'		=> $child->app,
				'type'		=> $child->type,
				'item_id'	=> $child->item_id,
				'active'	=> $child->cancelled ? 0 : $child->active,
				'start'		=> $child->start,
				'expire'	=> $child->expire,
				'lkey'		=> $child->licenseKey() ? $child->licenseKey()->key : NULL,
			);
		}
		
		\IPS\Output::i()->sendOutput( json_encode( array(
			'key'				=> $key->key,
			'identifier'		=> $this->getIdentifier( $key ),
			'generated'			=> $key->generated->getTimestamp(),
			'expires'			=> $key->purchase->expire ? $key->purchase->expire->getTimestamp() : NULL,
			'usage_data'		=> $key->activate_data,
			'purchase_id'		=> $key->purchase->id,
			'purchase_name'		=> $key->purchase->name,
			'purchase_pkg'		=> $key->purchase->item_id,
			'purchase_active'	=> $key->purchase->cancelled ? FALSE : $key->purchase->active,
			'purchase_start'	=> $key->purchase->start->getTimestamp(),
			'purchase_expire'	=> $key->purchase->expire ? $key->purchase->expire->getTimestamp() : NULL,
			'purchase_children'	=> $children,
			'customer_name'		=> \IPS\nexus\Customer::load( $key->member )->cm_name,
			'customer_email'	=> \IPS\nexus\Customer::load( $key->member )->email,
			'uses'				=> $key->uses,
			'max_uses'			=> $key->max_uses
		) ), 200, 'application/json', \IPS\Output::getCacheHeaders( time(), 360 ) );
	}
	
	/**
	 * Update extra information
	 *
	 * @return	void
	 */
	public function updateExtra()
	{
		$key = $this->getKey();
		
		$activateData = $key->activate_data;
		if ( !isset( \IPS\Request::i()->usage_id ) or !isset( $activateData[ \IPS\Request::i()->usage_id ] ) )
		{
			throw new ApiException( 'BAD_USAGE_ID', 303 );
		}
		if ( \IPS\NEXUS_LKEY_API_CHECK_IP and $activateData[ \IPS\Request::i()->usage_id ]['ip'] != $_SERVER['REMOTE_ADDR'] )
		{
			throw new ApiException( 'BAD_IP', 304 );
		}
						
		$activateData[ \IPS\Request::i()->usage_id ]['extra'] = isset( \IPS\Request::i()->extra ) ? json_decode( \IPS\Request::i()->extra ) : NULL;
		$key->activate_data = $activateData;
		$key->save();
		
		\IPS\Output::i()->pageCaching = FALSE;
		\IPS\Output::i()->sendOutput( json_encode( array( 'status' => 'OKAY' ) ), 200, 'application/json' );
	}
}

$api = new api;
if ( !\IPS\NEXUS_LKEY_API_DISABLE )
{
	foreach ( array( 'activate', 'check', 'info', 'updateExtra' ) as $k )
	{
		if ( isset( \IPS\Request::i()->$k ) )
		{
			try
			{
				$api->$k();
			}
			catch ( ApiException $e )
			{
				$api->error( $e->getMessage(), $e->getCode() );
			}
			catch ( Exception $e )
			{
				$api->error( 0, 'INTERNAL_ERROR' );
			}
		} 
	}
	$api->error( 0, 'BAD_METHOD' );
}
else
{
	$api->error( 0, 'API_DISABLED' );
}