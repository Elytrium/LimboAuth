<?php
/**
 * @brief		Multi Factor Authentication Handler for Google Authenticator
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		2 Sep 2016
 */

namespace IPS\MFA\GoogleAuthenticator;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Multi Factor Authentication Handler for Google Authenticator
 */
class _Handler extends \IPS\MFA\MFAHandler
{
	/**
	 * @brief	Key
	 */
	protected $key = 'google';
	
	/* !Setup */
	
	/**
	 * Handler is enabled
	 *
	 * @return	bool
	 */
	public function isEnabled()
	{
		return \IPS\Settings::i()->googleauth_enabled;
	}
	
	/**
	 * Member *can* use this handler (even if they have not yet configured it)
	 *
	 * @return	bool
	 */
	public function memberCanUseHandler( \IPS\Member $member )
	{
		return \IPS\Settings::i()->googleauth_groups == '*' or $member->inGroup( explode( ',', \IPS\Settings::i()->googleauth_groups ) );
	}
	
	/**
	 * Member has configured this handler
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public function memberHasConfiguredHandler( \IPS\Member $member )
	{
		return isset( $member->mfa_details['google'] );
	}
		
	/**
	 * Show a setup screen
	 *
	 * @param	\IPS\Member		$member						The member
	 * @param	bool			$showingMultipleHandlers	Set to TRUE if multiple options are being displayed
	 * @param	\IPS\Http\Url	$url						URL for page
	 * @return	string
	 */
	public function configurationScreen( \IPS\Member $member, $showingMultipleHandlers, \IPS\Http\Url $url )
	{
		/* Generate a secret */
		if ( isset( \IPS\Request::i()->secret ) )
		{
			$secret = \IPS\Request::i()->secret;
		}
		else
		{
			if ( \function_exists( 'random_bytes' ) )
			{
				$randomString = random_bytes( 16 );
			}
			elseif ( \function_exists( 'mcrypt_create_iv' ) )
			{
				$randomString = mcrypt_create_iv( 16, MCRYPT_DEV_URANDOM );
			}
			elseif ( \function_exists( 'openssl_random_pseudo_bytes' ) )
			{
				$randomString = openssl_random_pseudo_bytes( 16 );
			}
			else
			{
				$randomString = \substr( md5( uniqid( microtime(), true ) ) . md5( uniqid( microtime(), true ) ), 0, 16 );
			}
			$validChars = array( 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',  'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '2', '3', '4', '5', '6', '7', '=' );
			$secret = '';
			for ( $i = 0; $i < 16; ++$i )
			{
	            $secret .= $validChars[ \ord( $randomString[ $i ] ) & 31 ];
	        }
	    }
		
		/* Generate QR code */
		$qrCode = \IPS\Http\Url::external("https://chart.googleapis.com/chart")->setQueryString( array(
			'cht'	=> 'qr',
			'chs'	=> '200x200',
			'chl'	=> "otpauth://totp/{$member->email}?secret={$secret}&issuer=" . rawurlencode( \IPS\Settings::i()->board_name ),
		) );
		
		/* Display */
		return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->googleAuthenticatorSetup( $qrCode, $secret, $showingMultipleHandlers );
	}
	
	/**
	 * Submit configuration screen. Return TRUE if was accepted
	 *
	 * @param	\IPS\Member		$member	The member
	 * @return	bool
	 */
	public function configurationScreenSubmit( \IPS\Member $member )
	{
		if ( \IPS\Request::i()->google_authenticator_setup_code )
		{
			if ( static::checkSubmittedCode( \IPS\Request::i()->google_authenticator_setup_code, \IPS\Request::i()->secret, $member ) )
			{
				$mfaDetails = $member->mfa_details;
				$mfaDetails['google'] = \IPS\Request::i()->secret;
				$member->mfa_details = $mfaDetails;
				$member->save();

				/* Log MFA Enable */
				$member->logHistory( 'core', 'mfa', array( 'handler' => $this->key, 'enable' => TRUE ) );

				return TRUE;
			}
		}
		return FALSE;
	}
	
	/* !Authentication */
	
	/**
	 * Get the form for a member to authenticate
	 *
	 * @param	\IPS\Member		$member		The member
	 * @param	\IPS\Http\Url	$url		URL for page
	 * @return	string
	 */
	public function authenticationScreen( \IPS\Member $member, \IPS\Http\Url $url )
	{
		try
		{
			$waitUntil = ( \IPS\Db::i()->select( 'time', 'core_googleauth_used_codes', array( '`member`=?', $member->member_id ), 'time DESC', 1 )->first() * 30 ) + 30;
		}
		catch ( \UnderflowException $e )
		{
			$waitUntil = NULL;
		}
				
		return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->googleAuthenticatorAuth( $waitUntil );
	}
	
	/**
	 * Submit authentication screen. Return TRUE if was accepted
	 *
	 * @param	\IPS\Member		$member	The member
	 * @return	string
	 */
	public function authenticationScreenSubmit( \IPS\Member $member )
	{
		if ( \IPS\Request::i()->google_authenticator_auth_code )
		{
			if ( $codeTime = static::checkSubmittedCode( \IPS\Request::i()->google_authenticator_auth_code, $member->mfa_details['google'], $member ) )
			{
				\IPS\Db::i()->insert( 'core_googleauth_used_codes', array(
					'member'	=> $member->member_id,
					'time'		=> $codeTime
				) );
				return TRUE;
			}
			return FALSE;
		}
		return FALSE;
	}
	
	/* !ACP */
	
	/**
	 * Toggle
	 *
	 * @param	bool	$enabled	On/Off
	 * @return	bool
	 */
	public function toggle( $enabled )
	{
		\IPS\Settings::i()->changeValues( array( 'googleauth_enabled' => $enabled ) );
	}
	
	/**
	 * ACP Settings
	 *
	 * @return	string
	 */
	public function acpSettings()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'googleauth_groups', \IPS\Settings::i()->googleauth_groups == '*' ? '*' : explode( ',', \IPS\Settings::i()->googleauth_groups ), FALSE, array(
			'multiple'		=> TRUE,
			'options'		=> array_combine( array_keys( \IPS\Member\Group::groups() ), array_map( function( $_group ) { return (string) $_group; }, \IPS\Member\Group::groups() ) ),
			'unlimited'		=> '*',
			'unlimitedLang'	=> 'everyone',
			'impliedUnlimited' => TRUE
		) ) );
		
		if ( $values = $form->values() )
		{
			$values['googleauth_groups'] = ( $values['googleauth_groups'] == '*' ) ? '*' : implode( ',', $values['googleauth_groups'] );
			$form->saveAsSettings( $values );	
			\IPS\Session::i()->log( 'acplogs__mfa_handler_enabled', array( "mfa_google_title" => TRUE ) );		
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=mfa' ), 'saved' );
		}
		
		return (string) $form;
	}
	
	/* !Misc */
	
	/**
	 * If member has configured this handler, disable it
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public function disableHandlerForMember( \IPS\Member $member )
	{
		$mfaDetails = $member->mfa_details;
		unset( $mfaDetails['google'] );
		$member->mfa_details = $mfaDetails;
		$member->save();

		/* Log MFA Disable */
		$member->logHistory( 'core', 'mfa', array( 'handler' => $this->key, 'enable' => FALSE ) );
	}
	
	/* !Helper Methods */
	
	/**
	 * Verify a submitted code with a ±30 seconds leeway
	 *
	 * @param	string			$submittedCode		The code that was submitted
	 * @param	string			$secret				The secret key
	 * @param	\IPS\Member		$member				The member this is for
	 * @return	int|FALSE
	 */
	protected static function checkSubmittedCode( $submittedCode, $secret, $member )
	{
		$submittedCode = str_replace( ' ', '', $submittedCode );
				
		$validTimes = array( new \IPS\DateTime(), ( new \IPS\DateTime() )->add( new \DateInterval('PT30S') ), ( new \IPS\DateTime() )->sub( new \DateInterval('PT30S') ) );
		$blockedTimes = iterator_to_array( \IPS\Db::i()->select( 'time', 'core_googleauth_used_codes', array( '`member`=?', $member->member_id ) ) );

		$allowedCodes = array();
		foreach ( $validTimes as $time )
		{
			$codeTime = floor( $time->getTimestamp() / 30 );
			if ( !\in_array( $codeTime, $blockedTimes ) )
			{
				$allowedCodes[ static::getCodeForSecretAtTime( $secret, $time ) ] = $codeTime;
			}
		}
		
		foreach ( $allowedCodes as $code => $time )
		{
			if ( \IPS\Login::compareHashes( (string) $code, $submittedCode ) )
			{
				return $time;
			}
		}
		
		return FALSE;
	}
	
	/**
	 * Get the code
	 *
	 * @param	string			$secret		The secret key for the user
	 * @param	\IPS\DateTime	$time	Timestamp
	 * @return	string
	 */
	protected static function getCodeForSecretAtTime( $secret, \IPS\DateTime $time )
	{
		/* Decode secret key */
		$secret = str_split( str_replace( '=', '', $secret ) );
		$chars = array( 'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25, 2 => 26, 3 => 27, 4 => 28, 5 => 29, 6 => 30, 7 => 31, '=' => 32 );		
		$decodedSecretKey = '';
		for ( $i = 0; $i < 16; $i += 8 )
		{
			$block = '';
            for ( $j = 0; $j < 8; ++$j )
            {
                $block .= str_pad( base_convert( $chars[ $secret[ $i + $j ] ], 10, 2 ), 5, '0', STR_PAD_LEFT );
            }
            $eightBits = str_split( $block, 8 );
            for ( $z = 0; $z < \count( $eightBits ); ++$z )
            {
                $decodedSecretKey .=  ( ( $y = \chr( base_convert( $eightBits[ $z ], 2, 10) ) ) || \ord( $y ) == 48) ? $y : '';
            }
		}
		        
        /* Hash the timestamp with the secret key */
        $hash = hash_hmac('SHA1', \chr(0).\chr(0).\chr(0).\chr(0).pack('N*', floor( $time->getTimestamp() / 30 ) ), $decodedSecretKey, true);
        
        /* Unpack it */
        $value = unpack( 'N', \substr( $hash, \ord( \substr( $hash, -1 ) ) & 0x0F, 4 ) );
        $value = $value[1];
        
        /* Get 32 bits */
        $value = $value & 0x7FFFFFFF;
        return str_pad( $value % pow( 10, 6 ), 6, '0', STR_PAD_LEFT );
	}
}