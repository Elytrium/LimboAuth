<?php
/**
 * @brief		Converter Login Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	convert
 * @since		15 October 2017
 */

namespace IPS\convert;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Standard Internal Database Login Handler
 */
class _Login extends \IPS\Login\Handler
{
	use \IPS\Login\Handler\UsernamePasswordHandler;

	/**
	 * Get title
	 *
	 * @return	string
	 */
	public static function getTitle()
	{
		return 'login_handler_Convert';
	}

	/**
	 * Authenticate
	 *
	 * @param	\IPS\Login	$login				The login object
	 * @param	string		$usernameOrEmail		The username or email address provided by the user
	 * @param	object		$password			The plaintext password provided by the user, wrapped in an object that can be cast to a string so it doesn't show in any logs
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	public function authenticateUsernamePassword( \IPS\Login $login, $usernameOrEmail, $password )
	{
		$type = 'username_or_email';
		switch ( $this->authType() )
		{
			case \IPS\Login::AUTH_TYPE_USERNAME + \IPS\Login::AUTH_TYPE_EMAIL:
				$type = 'username_or_email';
				break;

			case \IPS\Login::AUTH_TYPE_USERNAME:
				$type = 'username';
				break;

			case \IPS\Login::AUTH_TYPE_EMAIL:
				$type = 'email_address';
				break;
		}

		/* Make sure we have the username or email */
		if( !$usernameOrEmail )
		{
			$member = NULL;

			if ( $this->authType() & \IPS\Login::AUTH_TYPE_EMAIL )
			{
				$member = new \IPS\Member;
				$member->email = $usernameOrEmail;
			}

			throw new \IPS\Login\Exception( \IPS\Member::loggedIn()->language()->addToStack( 'login_err_no_account', FALSE, array( 'pluralize' => array( $this->authType() ) ) ), \IPS\Login\Exception::NO_ACCOUNT, NULL, $member );
		}

		/* Get member(s) */
		$where = array();
		$params = array();
		if ( $this->authType() & \IPS\Login::AUTH_TYPE_USERNAME )
		{
			$where[] = 'name=?';
			$params[] = $usernameOrEmail;

			if ( $usernameOrEmail !== \IPS\Request::legacyEscape( $usernameOrEmail ) )
			{
				$where[] = 'name=?';
				$params[] = \IPS\Request::legacyEscape( $usernameOrEmail );
			}
		}
		if ( $this->authType() & \IPS\Login::AUTH_TYPE_EMAIL )
		{
			$where[] = 'email=?';
			$params[] = $usernameOrEmail;

			if ( $usernameOrEmail !== \IPS\Request::legacyEscape( $usernameOrEmail ) )
			{
				$where[] = 'email=?';
				$params[] = \IPS\Request::legacyEscape( $usernameOrEmail );
			}
		}
		$members = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members', array_merge( array( implode( ' OR ', $where ) ), $params ) ), 'IPS\Member' );

		/* If we didn't match any, throw an exception */
		if ( !\count( $members ) )
		{
			$member = NULL;

			if ( $this->authType() & \IPS\Login::AUTH_TYPE_EMAIL )
			{
				$member = new \IPS\Member;
				$member->email = $usernameOrEmail;
			}

			throw new \IPS\Login\Exception( \IPS\Member::loggedIn()->language()->addToStack( 'login_err_no_account', FALSE, array( 'pluralize' => array( $this->authType() ) ) ), \IPS\Login\Exception::NO_ACCOUNT, NULL, $member );
		}

		/* Table switcher for new converters */
		try
		{
			foreach( $members as $member )
			{
				if( $this->authenticatePasswordForMember( $member, $password ) )
				{
					return $member;
				}
			}
		}
		catch( \IPS\Db\Exception $e )
		{
			/* Converter tables no longer exist */
			if( $e->getCode() == 1146 )
			{
				throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
			}
		}

		/* Still here? Throw a password incorrect exception */
		throw new \IPS\Login\Exception( \IPS\Member::loggedIn()->language()->addToStack( 'login_err_bad_password', FALSE, array( 'pluralize' => array( $this->authType() ) ) ), \IPS\Login\Exception::BAD_PASSWORD, NULL, isset( $member ) ? $member : NULL );
	}

	/**
	 *	@brief		Convert app cache
	 */
	protected static $_apps;

	/**
	 * Authenticate
	 *
	 * @param	\IPS\Member	$member				The member
	 * @param	object		$password			The plaintext password provided by the user, wrapped in an object that can be cast to a string so it doesn't show in any logs
	 * @return	bool
	 */
	public function authenticatePasswordForMember( \IPS\Member $member, $password )
	{
		if( static::$_apps === NULL )
		{
			static::$_apps = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'convert_apps', array( 'login=?', 1 ) ), 'IPS\convert\App' );
		}

		foreach( static::$_apps as $app )
		{
			/* Strip underscores from keys */
			$sw = str_replace( "_", "", $app->key );

			/* Get converter classname */
			try
			{
				$application = $app->getSource( FALSE, FALSE );
			}
			/* Converter application class no longer exists, but we want to continue since we may have a login method here */
			catch( \InvalidArgumentException $e )
			{
				$application = NULL;
			}

			/* Check at least one of the login methods exist */
			if ( !method_exists( $this, $sw ) AND ( $application === NULL OR !method_exists( $application, 'login' ) ) )
			{
				continue;
			}

			/* We still want to use the parent methods (no sense in recreating them) so copy conv_password_extra to misc */
			$member->misc = $member->conv_password_extra;

			/* New login method */
			if( class_exists( $application ) AND method_exists( $application, 'login' ) )
			{
				$success = $application::login( $member, (string) $password );
			}
			/* Deprecated method */
			else
			{
				$success = $this->$sw( $member, (string) $password );
			}

			unset( $member->misc );
			unset( $member->changed['misc'] );

			if ( $success )
			{
				/*	Update password and return */
				$member->conv_password			= NULL;
				$member->conv_password_extra	= NULL;
				$member->setLocalPassword( $password );
				$member->save();
				$member->memberSync( 'onPassChange', array( $password ) );

				return $member;
			}
		}

		return FALSE;
	}

	/**
	 * Can this handler process a login for a member?
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	bool
	 */
	public function canProcess( \IPS\Member $member )
	{
		return (bool) $member->conv_password;
	}

	/**
	 * Can this handler process a password change for a member?
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	bool
	 */
	public function canChangePassword( \IPS\Member $member )
	{
		return FALSE;
	}

	/**
	 * Change Password
	 *
	 * @param	\IPS\Member	$member			The member
	 * @param	object		$newPassword		New Password wrapped in an object that can be cast to a string so it doesn't show in any logs
	 * @return	void
	 */
	public function changePassword( \IPS\Member $member, $newPassword )
	{
		$member->setLocalPassword( $newPassword );
		$member->save();
	}

	/**
	 * Show in Account Settings?
	 *
	 * @param	\IPS\Member|NULL	$member	The member, or NULL for if it should show generally
	 * @return	bool
	 */
	public function showInUcp( \IPS\Member $member = NULL )
	{
		return FALSE;
	}

	/**
	 * AEF
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function aef( $member, $password )
	{
		if ( \IPS\Login::compareHashes( $member->conv_password, md5( $member->misc . $password ) ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * BBPress Standalone
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function bbpressstandalone( $member, $password )
	{
		return $this->bbpress( $member, $password );
	}

	/**
	 * BBPress
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function bbpress( $member, $password )
	{
		$success = false;
		$password = html_entity_decode( $password );
		$hash = $member->conv_password;

		if ( \strlen( $hash ) == 32 )
		{
			$success = ( bool ) ( \IPS\Login::compareHashes( $member->conv_password, md5( $password ) ) );
		}

		// Nope, not md5.
		if ( ! $success )
		{
			$hashLibrary = new \IPS\convert\Login\HashCryptPrivate;
			$itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
			$crypt = $hashLibrary->hashCryptPrivate( $password, $hash, $itoa64, 'P' );
			if ( $crypt[ 0 ] == '*' )
			{
				$crypt = crypt( $password, $hash );
			}

			if ( $crypt == $hash )
			{
				$success = true;
			}
		}

		// Nope
		if ( ! $success )
		{
			// No - check against WordPress.
			// Note to self - perhaps push this to main bbpress method.
			$success = \IPS\convert\Software\Core\Wordpress::login( $member, $password );
		}

		return $success;
	}

	/**
	 * BBPress 2.3
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function bbpress23( $member, $password )
	{
		return $this->bbpress( $member, $password );
	}

	/**
	 * Community Server
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function cs( $member, $password )
	{
		$encodedHashPass = base64_encode( pack( "H*", sha1( base64_decode( $member->misc ) . $password ) ) );

		if ( \IPS\Login::compareHashes( $member->conv_password, $encodedHashPass ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * CSAuth
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function csauth( $member, $password )
	{
		$wsdl = 'https://internal.auth.com/Service.asmx?wsdl';
		$dest = 'https://interal.auth.com/Service.asmx';
		$single_md5_pass = md5( $password );

		try
		{
			$client = new SoapClient( $wsdl, array( 'trace' => 1 ) );
			$client->__setLocation( $dest );
			$loginparams = array( 'username' => $member->name, 'password' => $password );
			$result = $client->AuthCS( $loginparams );

			switch( $result->AuthCSResult )
			{
				case 'SUCCESS' :
					return TRUE;
				case 'WRONG_AUTH' :
					return FALSE;
				default :
					return FALSE;
			}
		}
		catch( Exception $ex )
		{
			return FALSE;
		}
	}

	/**
	 * Discuz
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function discuz( $member, $password )
	{
		if ( \IPS\Login::compareHashes( $member->conv_password, md5( md5( $password ) . $member->misc ) ) )
		{
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * FudForum
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function fudforum( $member, $password )
	{
		$success = false;
		$single_md5_pass = md5( $password );
		$hash = $member->conv_password;

		if ( \strlen( $hash ) == 40 )
		{
			$success = ( \IPS\Login::compareHashes( $member->conv_password, sha1( $member->misc . sha1( $password ) ) ) ) ? TRUE : FALSE;
		}
		else
		{
			$success = ( \IPS\Login::compareHashes( $member->conv_password, $single_md5_pass ) ) ? TRUE : FALSE;
		}

		return $success;
	}

	/**
	 * FusionBB
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function fusionbb( $member, $password )
	{
		/* FusionBB Has multiple methods that can be used to check a hash, so we need to cycle through them */

		/* md5( md5( salt ) . md5( pass ) ) */
		if ( \IPS\Login::compareHashes( $member->conv_password, md5( md5( $member->misc ) . md5( $password ) ) ) )
		{
			return TRUE;
		}

		/* md5( md5( salt ) . pass ) */
		if ( \IPS\Login::compareHashes( $member->conv_password, md5( md5( $member->misc ) . $password ) ) )
		{
			return TRUE;
		}

		/* md5( pass ) */
		if ( \IPS\Login::compareHashes( $member->conv_password, md5( $password ) ) )
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Ikonboard
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function ikonboard( $member, $password )
	{
		if ( \IPS\Login::compareHashes( $member->conv_password, crypt( $password, $member->misc ) ) )
		{
			return TRUE;
		}
		else if ( \IPS\Login::compareHashes( $member->conv_password, md5( $password . mb_strtolower( $member->conv_password_extra ) ) ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * Kunena
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function kunena( $member, $password )
	{
		// Kunena authenticates using internal Joomla functions.
		// This is required, however, if the member only converts from
		// Kunena and not Joomla + Kunena.
		return \IPS\convert\Software\Core\Joomla::login( $member, $password );
	}

	/**
	 * PHP Legacy (2.x)
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function phpbblegacy( $member, $password )
	{
		return \IPS\convert\Software\Core\Phpbb::login( $member, $password );
	}

	/**
	 * Vbulletin 5.1
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function vb51connect( $member, $password )
	{
		return \IPS\convert\Software\Core\Vbulletin5::login( $member, $password );
	}

	/**
	 * Vbulletin 5
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function vb5connect( $member, $password )
	{
		return \IPS\convert\Software\Core\Vbulletin5::login( $member, $password );
	}

	/**
	 * Vbulletin 3.8
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function vbulletinlegacy( $member, $password )
	{
		return \IPS\convert\Software\Core\Vbulletin::login( $member, $password );
	}

	/**
	 * Vbulletin 3.6
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function vbulletinlegacy36( $member, $password )
	{
		return \IPS\convert\Software\Core\Vbulletin::login( $member, $password );
	}

	/**
	 * SMF Legacy
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function smflegacy( $member, $password )
	{
		if ( \IPS\Login::compareHashes( $member->conv_password, sha1( mb_strtolower( $member->name ) . $password ) ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * Telligent
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function telligentcs( $member, $password )
	{
		return $this->cs( $member, $password );
	}

	/**
	 * WoltLab 4.x
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function woltlab( $member, $password )
	{
		$testHash = FALSE;

		/* If it's not blowfish, then we don't have a salt for it. */
		if ( !preg_match( '/^\$2[ay]\$(0[4-9]|[1-2][0-9]|3[0-1])\$[a-zA-Z0-9.\/]{53}/', $member->conv_password ) )
		{
			$salt = mb_substr( $member->conv_password, 0, 29 );
			$testHash = crypt( crypt( $password, $salt ), $salt );
		}

		if (	$testHash AND \IPS\Login::compareHashes( $member->conv_password, $testHash ) )
		{
			return TRUE;
		}
		elseif ( $this->woltlablegacy( $member, $password ) )
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * WoltLab 3.x
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function woltlablegacy( $member, $password )
	{
		if ( \IPS\Login::compareHashes( $member->conv_password, sha1( $member->misc . sha1( $member->misc . sha1( $password ) ) ) ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * PHP Fusion
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function phpfusion( $member, $password )
	{
		return ( bool ) \IPS\Login::compareHashes( $member->conv_password, md5( md5( $password ) ) );
	}

	/**
	 * fluxBB
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function fluxbb( $member, $password )
	{
		$success = false;
		$hash = $member->conv_password;

		if ( \strlen( $hash ) == 40 )
		{
			if ( \IPS\Login::compareHashes( $hash, sha1( $member->misc . sha1( $password ) ) ) )
			{
				$success = TRUE;
			}
			elseif ( \IPS\Login::compareHashes( $hash, sha1( $password ) ) )
			{
				$success = TRUE;
			}
		}
		else
		{
			$success = ( \IPS\Login::compareHashes( $hash, md5( $password ) ) ) ? TRUE : FALSE;
		}

		return $success;
	}

	/**
	 * Simplepress Forum
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string	$password	Password from form
	 * @return	bool
	 */
	protected function simplepress( $member, $password )
	{
		return \IPS\convert\Software\Core\Wordpress::login( $member, $password );
	}
}