<?php
/**
 * @brief		Sign In With Apple Login Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		2 Jun 2020
 */

namespace IPS\Login\Handler\OAuth2;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Sign In With Apple Login Handler
 */
class _Apple extends \IPS\Login\Handler\OAuth2\OpenID
{
	/**
	 * Get title
	 *
	 * @return	string
	 */
	public static function getTitle()
	{
		return 'login_handler_Apple';
	}

	/**
	 * Should client credentials be sent as an "Authorisation" header, or as POST data?
	 *
	 * @return	string
	 */
	protected function _authenticationType()
	{
		return static::AUTHENTICATE_POST;
	}
	
	/**
	 * ACP Settings Form
	 *
	 * @return	array	List of settings to save - settings will be stored to core_login_methods.login_settings DB field
	 * @code
	 	return array( 'savekey'	=> new \IPS\Helpers\Form\[Type]( ... ), ... );
	 * @endcode
	 */
	public function acpForm()
	{
		\IPS\Member::loggedIn()->language()->words['oauth_client_id'] = \IPS\Member::loggedIn()->language()->addToStack('login_apple_services_id');

		$return = array();
		$return[] = array( 'login_handler_apple_settings', 'login_handler_Apple_info' );

		$accountManagementSettings = array();
		$active = 'return';
		
		foreach ( parent::acpForm() as $k => $v )
		{
			if ( $v === 'account_management_settings' )
			{
				$active = 'accountManagementSettings';
			}
			if ( !\is_string( $v ) and !\is_array( $v ) and $k !== 'client_secret' )
			{
				${$active}[ $k ] = $v;
			}
		}

		$return['apple_team_id'] = new \IPS\Helpers\Form\Text( 'apple_team_id', isset( $this->settings['apple_team_id'] ) ? $this->settings['apple_team_id'] : NULL, NULL, array(), NULL, NULL, NULL, 'apple_team_id' );
		$return['apple_key_id'] = new \IPS\Helpers\Form\Text( 'apple_key_id', isset( $this->settings['apple_key_id'] ) ? $this->settings['apple_key_id'] : NULL, NULL, array(), NULL, NULL, NULL, 'apple_key_id' );
		$return['apple_key'] = new \IPS\Helpers\Form\Upload( 'apple_key',  ( isset( $this->settings['apple_key'] ) and $this->settings['apple_key'] ) ? \IPS\File::get( 'core_Login', $this->settings['apple_key'] ) : NULL, TRUE, array( 'storageExtension' => 'core_Login', 'allowedFileTypes' => ['p8'] ), NULL, NULL, NULL, 'apple_key' );;
		
		$return[] = 'account_management_settings';
		foreach ( $accountManagementSettings as $k => $v )
		{
			$return[ $k ] = $v;
		}

		return $return;
	}
	
	/**
	 * Save Handler Settings
	 *
	 * @param	array	$values	Values from form
	 * @return	array
	 */
	public function acpFormSave( &$values )
	{
		$return = parent::acpFormSave( $values );
		$return['apple_key'] = (string) $return['apple_key'];
		
		return $return;
	}

	/**
	 * Get the button color
	 *
	 * @return	string
	 */
	public function buttonColor()
	{
		return '#000000';
	}
	
	/**
	 * Get the button icon
	 *
	 * @return	string
	 */
	public function buttonIcon()
	{
		return 'apple';
	}
	
	/**
	 * Get button text
	 *
	 * @return	string
	 */
	public function buttonText()
	{
		return 'login_apple';
	}

	/**
	 * Get button class
	 *
	 * @return	string
	 */
	public function buttonClass()
	{
		return 'ipsSocial_apple';
	}
	
	/**
	 * Get logo to display in information about logins with this method
	 * Returns NULL for methods where it is not necessary to indicate the method, e..g Standard
	 *
	 * @return	\IPS\Http\Url
	 */
	public function logoForDeviceInformation()
	{
		return \IPS\Theme::i()->resource( 'logos/login/Apple.png', 'core', 'interface' );
	}
	
	/**
	 * Grant Type
	 *
	 * @return	string
	 */
	protected function grantType()
	{
		return 'authorization_code';
	}
	
	/**
	 * Get scopes to request
	 *
	 * @param	array|NULL	$additional	Any additional scopes to request
	 * @return	array
	 */
	protected function scopesToRequest( $additional=NULL )
	{
		$return = array( 'name email' );

		return $return;
	}

	/**
	 * Authorization Endpoint
	 *
	 * @param	\IPS\Login	$login	The login object
	 * @return	\IPS\Http\Url
	 */
	protected function authorizationEndpoint( \IPS\Login $login )
	{
		$return = \IPS\Http\Url::external( 'https://appleid.apple.com/auth/authorize' )->setQueryString( 'response_mode', 'form_post' );
		
		return $return;
	}
	
	/**
	 * Token Endpoint
	 *
	 * @return	\IPS\Http\Url
	 */
	protected function tokenEndpoint()
	{
		return \IPS\Http\Url::external( 'https://appleid.apple.com/auth/token' );
	}

	/**
	 * Get authenticated user's identifier (may not be a number)
	 *
	 * @param	string	$accessToken	Access Token
	 * @return	string
	 */
	protected function authenticatedUserId( $accessToken )
	{
		$claims = $this->getClaimsfromIdToken( $this->_getIdToken( $accessToken ) );
		
		return ( isset( $claims['sub'] ) ) ? $claims['sub'] : NULL;
	}
	
	/**
	 * Get authenticated user's username
	 * May return NULL if server doesn't support this
	 *
	 * @param	string	$accessToken	Access Token
	 * @return	string|NULL
	 */
	protected function authenticatedUserName( $accessToken )
	{
		$name = NULL;
		
		if( isset( $_SESSION['oauth_user'] ) )
		{
			$session = json_decode( $_SESSION['oauth_user'], true );
			
			if( isset( $session['name'] ) )
			{
				$name = implode( " ", $session['name'] );
			}
		}
		
		return $name;
	}
	
	/**
	 * Get authenticated user's email address
	 * May return NULL if server doesn't support this
	 *
	 * @param	string	$accessToken	Access Token
	 * @return	string|NULL
	 */
	protected function authenticatedEmail( $accessToken )
	{
		$claims = $this->getClaimsfromIdToken( $this->_getIdToken( $accessToken )  );
		
		return ( isset( $claims['email'] ) ) ? $claims['email'] : NULL;
	}

	/**
	 * Client Secret
	 *
	 * @return	string | NULL
	 * @throws	\IPS\Login\Exception
	 */
	public function clientSecret()
	{
		$key = NULL;
		
		if ( isset( $this->settings['apple_key'] ) and $this->settings['apple_key'] )
		{
			try
			{
				$key = \IPS\File::get( 'core_Login', $this->settings['apple_key'] )->contents();
			}
			catch ( \Exception $e )
			{
				return NULL;
			}
		}
		
		if( !$key )
		{
			return NULL;
		}
		
		$kid = $this->settings['apple_key_id'];
		$iss = $this->settings['apple_team_id'];
		$sub = $this->settings['client_id'];
		
		$header = array(
            'alg' => 'ES256',
            'kid' => $kid
        );
		
        $data = array(
            'iss' => $iss,
            'iat' => time(),
            'exp' => time() + 3600,
            'aud' => 'https://appleid.apple.com',
            'sub' => $sub
        );

        $pKey = openssl_pkey_get_private( $key );
        
		if ( !$pKey )
		{
           throw new \IPS\Login\Exception( 'login_apple_invalid_key', \IPS\Login\Exception::INTERNAL_ERROR );
        }

        $payload = $this->baseURL64encode( json_encode( $header ) ) . '.' . $this->baseURL64encode( json_encode( $data ) );

        $signature = '';
        $success = openssl_sign( $payload, $signature, $pKey, OPENSSL_ALGO_SHA256 );
        if ( !$success )
		{
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		$rawSignature = $this->fromDER( $signature, 64 );

        return $payload. '.' . $this->baseURL64encode( $rawSignature );
	}
	
	/**
	 * Syncing Options
	 *
	 * @param	\IPS\Member	$member			The member we're asking for (can be used to not show certain options iof the user didn't grant those scopes)
	 * @param	bool		$defaultOnly	If TRUE, only returns which options should be enabled by default for a new account
	 * @return	array
	 */
	public function syncOptions( \IPS\Member $member, $defaultOnly = FALSE )
	{
		$return = array();
		$scopes = $this->authorizedScopes( $member );

		if ( ( !isset( $this->settings['update_email_changes'] ) or $this->settings['update_email_changes'] === 'optional' ) and ( $scopes and \in_array( 'email', $scopes ) ) )
		{
			$return[] = 'email';
		}
		
		return $return;
	}
	
	/**
	 * Process an ID Token
	 *
	 * @param	array		$iDToken 	ID Token
	 * @return	array
	 */
	protected function getClaimsfromIdToken ( $iDToken )
	{
		$claims = explode( '.', $iDToken )[1];
		$claims = json_decode( base64_decode( $claims ), true );
		
		return $claims;
	}
	
	/**
	 * Convert Key From DER
	 *
	 * @param string $der
	 * @param int    $partLength
	 *
	 * @return string
	 * @throws	\IPS\Login\Exception
	 */
	public static function fromDER( string $der, int $partLength )
	{
		$hex = unpack( 'H*', $der )[1];
		
		if ( '30' !== mb_substr( $hex, 0, 2, '8bit' ) )
		{ 
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		if ( '81' === mb_substr( $hex, 2, 2, '8bit' ) )
		{
			$hex = mb_substr( $hex, 6, null, '8bit' );
		}
		else
		{
			$hex = mb_substr( $hex, 4, null, '8bit' );
		}
		
		if ( '02' !== mb_substr( $hex, 0, 2, '8bit' ) )
		{
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		$Rl = hexdec( mb_substr( $hex, 2, 2, '8bit' ) );
		$R = self::retrievePositiveInteger( mb_substr( $hex, 4, $Rl * 2, '8bit' ) );
		$R = str_pad( $R, $partLength, '0', STR_PAD_LEFT );
		$hex = mb_substr( $hex, 4 + $Rl * 2, null, '8bit' );
		
		if ( '02' !== mb_substr( $hex, 0, 2, '8bit' ) )
		{
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		$Sl = hexdec( mb_substr( $hex, 2, 2, '8bit' ) );
		$S = self::retrievePositiveInteger( mb_substr( $hex, 4, $Sl * 2, '8bit' ) );
		$S = str_pad( $S, $partLength, '0', STR_PAD_LEFT );
		
		return pack( 'H*', $R . $S );
	}

	/**
	 * Prepare Integer
	 *
	 * @param string $data
	 * @return string
	 */
	protected static function preparePositiveInteger( string $data )
	{
		if ( mb_substr( $data, 0, 2, '8bit') > '7f' )
		{
			return '00' . $data;
		}
		while ( '00' === mb_substr( $data, 0, 2, '8bit' ) && mb_substr( $data, 2, 2, '8bit' ) <= '7f' )
		{
			$data = mb_substr( $data, 2, null, '8bit' );
		}
		
		return $data;
	}

	/**
	 * Retrieve Integer
	 *
	 * @param string $data
	 * @return string
	 */
	protected static function retrievePositiveInteger( string $data )
	{
		while ( '00' === mb_substr( $data, 0, 2, '8bit' ) && mb_substr( $data, 2, 2, '8bit' ) > '7f' )
		{
			$data = mb_substr( $data, 2, null, '8bit' );
		}
		
		return $data;
	}

	/**
	 * Encode text in baseurl64 format 
	 *
	 * @param string $data
	 * @return string
	 */
	protected function baseURL64encode( $data )
	{
		$encoded = strtr( base64_encode( $data ), '+/', '-_' );
		
		return rtrim( $encoded, '=' );
	}
	
	/**
	 * Delete files
	 *
	 * @return	void
	 */
	public function delete()
	{
		if ( isset( $this->settings['apple_key'] ) )
		{
			try
			{
				\IPS\File::get( 'core_Login',  $this->settings['apple_key'] )->delete();
			}
			catch( \Exception $e ){}
		}
		
		parent::delete();
	}
}