<?php
/**
 * @brief		LDAP Login Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		2 June 2017
 */

namespace IPS\Login\Handler;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * LDAP Database Login Handler
 */
class _LDAP extends \IPS\Login\Handler
{
	/**
	 * @brief	Can we have multiple instances of this handler?
	 */
	public static $allowMultiple = TRUE;
	
	use UsernamePasswordHandler;
	
	/* !ACP Form */
	
	/**
	 * Get title
	 *
	 * @return	string
	 */
	public static function getTitle()
	{
		return 'login_handler_Ldap';
	}
	
	/**
	 * ACP Settings Form
	 *
	 * @param	string	$url	URL to redirect user to after successful submission
	 * @return	array	List of settings to save - settings will be stored to core_login_methods.login_settings DB field
	 * @code
	 	return array( 'savekey'	=> new \IPS\Helpers\Form\[Type]( ... ), ... );
	 * @endcode
	 */
	public function acpForm()
	{
		$id = $this->id ?: 'new';

		$return = array(
			'ldap_header',
			'server_protocol'	=> new \IPS\Helpers\Form\Radio( 'ldap_server_protocol', isset( $this->settings['server_protocol'] ) ? $this->settings['server_protocol'] : 3, TRUE, array( 'options' => array( 3 => 'V3', 2 => 'V2' ) ) ),
			'server_host'		=> new \IPS\Helpers\Form\Text( 'ldap_server_host', isset( $this->settings['server_host'] ) ? $this->settings['server_host'] : NULL, TRUE, array( 'placeholder' => 'ldap.example.com' ) ),
			'server_port'		=> new \IPS\Helpers\Form\Number( 'ldap_server_port', isset( $this->settings['server_port'] ) ? $this->settings['server_port'] : 389, TRUE ),
			'server_user'		=> new \IPS\Helpers\Form\Text( 'ldap_server_user', isset( $this->settings['server_user'] ) ? $this->settings['server_user'] : NULL ),
			'server_pass'		=> new \IPS\Helpers\Form\Text( 'ldap_server_pass', isset( $this->settings['server_pass'] ) ? $this->settings['server_pass'] : NULL ),
			'opt_referrals'		=> new \IPS\Helpers\Form\YesNo( 'ldap_opt_referrals', isset( $this->settings['opt_referrals'] ) ? $this->settings['opt_referrals'] : TRUE, TRUE ),
			'ldap_directory',
			'base_dn'			=> new \IPS\Helpers\Form\Text( 'ldap_base_dn', isset( $this->settings['base_dn'] ) ? $this->settings['base_dn'] : NULL, TRUE, array( 'placeholder' => 'dc=example,dc=com' ) ),
			'uid_field'			=> new \IPS\Helpers\Form\Text( 'ldap_uid_field', isset( $this->settings['uid_field'] ) ?  $this->settings['uid_field'] : 'uid', TRUE ),
			'un_suffix'			=> new \IPS\Helpers\Form\Text( 'ldap_un_suffix', isset( $this->settings['un_suffix'] ) ? $this->settings['un_suffix'] : NULL, FALSE, array( 'placeholder' => '@example.com' ) ),
			'name_field'		=> new \IPS\Helpers\Form\Text( 'ldap_name_field', $this->_nameField() ?: 'cn' ),
			'email_field'		=> new \IPS\Helpers\Form\Text( 'ldap_email_field', isset( $this->settings['email_field'] ) ? $this->settings['email_field'] : 'mail' ),
			'filter'			=> new \IPS\Helpers\Form\Text( 'ldap_filter', isset( $this->settings['filter'] ) ? $this->settings['filter'] : NULL, FALSE, array( 'placeholder' => 'ou=your_department' ) ),
			'login_settings',
			'auth_types'	=> new \IPS\Helpers\Form\Select( 'login_auth_types', isset( $this->settings['auth_types'] ) ? $this->settings['auth_types'] : ( \IPS\Login::AUTH_TYPE_EMAIL ), TRUE, array( 'options' => array(
				\IPS\Login::AUTH_TYPE_USERNAME + \IPS\Login::AUTH_TYPE_EMAIL => 'username_or_email',
				\IPS\Login::AUTH_TYPE_EMAIL	=> 'email_address',
				\IPS\Login::AUTH_TYPE_USERNAME => 'username',
			), 'toggles' => array( \IPS\Login::AUTH_TYPE_USERNAME + \IPS\Login::AUTH_TYPE_EMAIL => array( 'form_' . $id . '_login_auth_types_warning' ), \IPS\Login::AUTH_TYPE_USERNAME => array( 'form_' . $id . '_login_auth_types_warning' ) ) ) ),
			'pw_required'		=> new \IPS\Helpers\Form\YesNo( 'ldap_pw_required', isset( $this->settings['pw_required'] ) ? $this->settings['pw_required'] : TRUE ),
		);
		if ( \IPS\Settings::i()->allow_forgot_password == 'normal' or \IPS\Settings::i()->allow_forgot_password == 'handler' )
		{
			$return['forgot_password_url'] = new \IPS\Helpers\Form\Url( 'handler_forgot_password_url', isset( $this->settings['forgot_password_url'] ) ? $this->settings['forgot_password_url'] : NULL );
			\IPS\Member::loggedIn()->language()->words['handler_forgot_password_url_desc'] = \IPS\Member::loggedIn()->language()->addToStack( \IPS\Settings::i()->allow_forgot_password == 'normal' ? 'handler_forgot_password_url_desc_normal' : 'handler_forgot_password_url_deschandler' );
		}
		
		$return[] = 'account_management_settings';
		$return['sync_name_changes'] = new \IPS\Helpers\Form\Radio( 'login_sync_name_changes', isset( $this->settings['sync_name_changes'] ) ? $this->settings['sync_name_changes'] : 1, FALSE, array( 'options' => array(
			1	=> 'login_sync_changes_yes',
			0	=> 'login_sync_changes_no',
		) ) );
		if ( \IPS\Settings::i()->allow_email_changes == 'normal' )
		{
			$return['sync_email_changes'] = new \IPS\Helpers\Form\Radio( 'login_sync_email_changes', isset( $this->settings['sync_email_changes'] ) ? $this->settings['sync_email_changes'] : 1, FALSE, array( 'options' => array(
				1	=> 'login_sync_changes_yes',
				0	=> 'login_sync_changes_no',
			) ) );
		}
		if ( \IPS\Settings::i()->allow_password_changes == 'normal' )
		{
			$return['sync_password_changes'] = new \IPS\Helpers\Form\Radio( 'login_sync_password_changes', isset( $this->settings['sync_password_changes'] ) ? $this->settings['sync_password_changes'] : 1, FALSE, array( 'options' => array(
				1	=> 'login_sync_changes_yes',
				0	=> 'login_sync_password_changes_no',
			) ) );
		}
		
		$return['show_in_ucp'] = new \IPS\Helpers\Form\Radio( 'login_handler_show_in_ucp', isset( $this->settings['show_in_ucp'] ) ? $this->settings['show_in_ucp'] : 'disabled', FALSE, array(
			'options' => array(
				'always'		=> 'login_handler_show_in_ucp_always',
				'loggedin'		=> 'login_handler_show_in_ucp_loggedin',
				'disabled'		=> 'login_handler_show_in_ucp_disabled'
			),
			'toggles' => array(
				'always'		=> array( 'login_update_name_changes_inc_optional', 'login_update_email_changes_inc_optional' ),
				'loggedin'		=> array( 'login_update_name_changes_inc_optional', 'login_update_email_changes_inc_optional' ),
				'disabled'		=> array( 'login_update_name_changes_no_optional', 'login_update_email_changes_no_optional' ),
			)
		) );
		
		$nameChangesDisabled = array();
		if ( $forceNameHandler = static::handlerHasForceSync( 'name', $this ) )
		{
			$nameChangesDisabled[] = 'force';
			\IPS\Member::loggedIn()->language()->words['login_update_changes_yes_name_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'login_update_changes_yes_disabled', FALSE, array( 'sprintf' => $forceNameHandler->_title ) );
		}
		$emailChangesDisabled = array();
		if ( $forceEmailHandler = static::handlerHasForceSync( 'email', $this ) )
		{
			$emailChangesDisabled[] = 'force';
			\IPS\Member::loggedIn()->language()->words['login_update_changes_yes_email_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'login_update_changes_yes_disabled', FALSE, array( 'sprintf' => $forceEmailHandler->_title ) );
		}
		
		$return['update_name_changes_inc_optional'] = new \IPS\Helpers\Form\Radio( 'login_update_name_changes_inc_optional', isset( $this->settings['update_name_changes'] ) ? $this->settings['update_name_changes'] : 'disabled', FALSE, array( 'options' => array(
			'force'		=> 'login_update_changes_yes_name',
			'optional'	=> 'login_update_changes_optional',
			'disabled'	=> 'login_update_changes_no',
		), 'disabled' => $nameChangesDisabled ), NULL, NULL, NULL, 'login_update_name_changes_inc_optional' );
		$return['update_name_changes_no_optional'] = new \IPS\Helpers\Form\Radio( 'login_update_name_changes_no_optional', ( isset( $this->settings['update_name_changes'] ) and $this->settings['update_name_changes'] != 'optional' ) ? $this->settings['update_name_changes'] : 'disabled', FALSE, array( 'options' => array(
			'force'		=> 'login_update_changes_yes_name',
			'disabled'	=> 'login_update_changes_no',
		), 'disabled' => $nameChangesDisabled ), NULL, NULL, NULL, 'login_update_name_changes_no_optional' );
		$return['update_email_changes_inc_optional'] = new \IPS\Helpers\Form\Radio( 'login_update_email_changes_inc_optional', isset( $this->settings['update_email_changes'] ) ? $this->settings['update_email_changes'] : 'force', FALSE, array( 'options' => array(
			'force'		=> 'login_update_changes_yes_email',
			'optional'	=> 'login_update_changes_optional',
			'disabled'	=> 'login_update_changes_no',
		), 'disabled' => $emailChangesDisabled ), NULL, NULL, NULL, 'login_update_email_changes_inc_optional' );
		$return['update_email_changes_no_optional'] = new \IPS\Helpers\Form\Radio( 'login_update_email_changes_no_optional', ( isset( $this->settings['update_email_changes'] ) and $this->settings['update_email_changes'] != 'optional' ) ? $this->settings['update_email_changes'] : 'force', FALSE, array( 'options' => array(
			'force'		=> 'login_update_changes_yes_email',
			'disabled'	=> 'login_update_changes_no',
		), 'disabled' => $emailChangesDisabled ), NULL, NULL, NULL, 'login_update_email_changes_no_optional' );
		\IPS\Member::loggedIn()->language()->words['login_update_name_changes_inc_optional'] = \IPS\Member::loggedIn()->language()->addToStack('login_update_name_changes');
		\IPS\Member::loggedIn()->language()->words['login_update_name_changes_no_optional'] = \IPS\Member::loggedIn()->language()->addToStack('login_update_name_changes');
		\IPS\Member::loggedIn()->language()->words['login_update_email_changes_inc_optional'] = \IPS\Member::loggedIn()->language()->addToStack('login_update_email_changes');
		\IPS\Member::loggedIn()->language()->words['login_update_email_changes_no_optional'] = \IPS\Member::loggedIn()->language()->addToStack('login_update_email_changes');
		
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
		$_values = $values;
		
		$settings = parent::acpFormSave( $values );
				
		if ( $_values['login_handler_show_in_ucp'] == 'never' )
		{
			$settings['update_name_changes'] = $_values['login_update_name_changes_no_optional'];
			$settings['update_email_changes'] = $_values['login_update_email_changes_no_optional'];
		}
		else
		{
			$settings['update_name_changes'] = $_values['login_update_name_changes_inc_optional'];
			$settings['update_email_changes'] = $_values['login_update_email_changes_inc_optional'];
		}
		
		unset( $settings['update_name_changes_inc_optional'] );
		unset( $settings['update_name_changes_no_optional'] );
		unset( $settings['update_email_changes_inc_optional'] );
		unset( $settings['update_email_changes_no_optional'] );		
				
		return $settings;
	}
	
	/**
	 * Test Settings
	 *
	 * @return	bool
	 * @throws	\LogicException
	 */
	public function testSettings()
	{
		if ( !\extension_loaded('ldap') )
		{
			throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack( 'login_ldap_err' ) );
		}
		
		try
		{
			$this->_ldap();
		}
		catch ( LDAP\Exception $e )
		{
			throw new \InvalidArgumentException( $e->getMessage() ?: \IPS\Member::loggedIn()->language()->addToStack('login_ldap_err_connect' ) );
		}
		
		return TRUE;
	}
	
	/* !Authentication */

	/**
	 * Authenticate
	 *
	 * @param	\IPS\Login	$login				The login object
	 * @param	string		$usernameOrEmail	The username or email address provided by the user
	 * @param	object		$password			The plaintext password provided by the user, wrapped in an object that can be cast to a string so it doesn't show in any logs
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	public function authenticateUsernamePassword( \IPS\Login $login, $usernameOrEmail, $password )
	{
		try
		{
			$result = NULL;
			
			if( $usernameOrEmail )
			{
				/* Try email address */
				if ( $this->authType() & \IPS\Login::AUTH_TYPE_EMAIL )
				{
					$result = $this->_getUserWithFilter( $this->settings['email_field'] . '=' . ldap_escape( $usernameOrEmail, NULL, LDAP_ESCAPE_FILTER ) );
				}
				
				/* Try username */
				if ( !$result and $this->authType() & \IPS\Login::AUTH_TYPE_USERNAME )
				{
					$result = $this->_getUserWithFilter( $this->_nameField() . '=' . ldap_escape( $usernameOrEmail . $this->settings['un_suffix'], NULL, LDAP_ESCAPE_FILTER ) );
				}
			}
						
			/* Don't have anything? */
			if ( !$result )
			{
				$member = NULL;

				if ( $this->authType() & \IPS\Login::AUTH_TYPE_EMAIL )
				{
					$member = new \IPS\Member;
					$member->email = $usernameOrEmail;
				}

				throw new \IPS\Login\Exception( \IPS\Member::loggedIn()->language()->addToStack( 'login_err_no_account', FALSE, array( 'pluralize' => array( $this->authType() ) ) ), \IPS\Login\Exception::NO_ACCOUNT, NULL, $member );
			}
			
			/* Get a local account if one exists */
			$attrs = @ldap_get_attributes( $this->_ldap(), $result );
			if ( !$attrs )
			{
				throw new LDAP\Exception( ldap_error( $this->_ldap() ), ldap_errno( $this->_ldap() ) );
			}
			$nameField = $this->_nameField();
			$name = ( $nameField and isset( $attrs[ $nameField ] ) ) ? $attrs[ $nameField ][0] : NULL;
			$email = ( $this->settings['email_field'] and isset( $attrs[ $this->settings['email_field'] ] ) ) ? $attrs[ $this->settings['email_field'] ][0] : NULL;
			$member = NULL;
			try
			{
				$link = \IPS\Db::i()->select( '*', 'core_login_links', array( 'token_login_method=? AND token_identifier=?', $this->id, $attrs[ $this->settings['uid_field'] ][0] ) )->first();
				$member = \IPS\Member::load( $link['token_member'] );
				
				/* If the user never finished the linking process, or the account has been deleted, discard this access token */
				if ( !$link['token_linked'] or !$member->member_id )
				{
					\IPS\Db::i()->delete( 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $link['token_member'] ) );
					$member = NULL;
				}
			}
			catch ( \UnderflowException $e ) { }
						
			/* Verify password */
			if ( !$this->_passwordIsValid( $result, $password ) )
			{
				throw new \IPS\Login\Exception( \IPS\Member::loggedIn()->language()->addToStack( 'login_err_bad_password', FALSE, array( 'pluralize' => array( $this->authType() ) ) ), \IPS\Login\Exception::BAD_PASSWORD, NULL, $member );
			}
			
			/* Create account if we don't have one */
			if ( $member )
			{
				return $member;
			}
			else
			{				
				try
				{
					if ( $login->type === \IPS\Login::LOGIN_UCP )
					{
						$exception = new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::MERGE_SOCIAL_ACCOUNT );
						$exception->handler = $this;
						$exception->member = $login->reauthenticateAs;
						throw $exception;
					}
					
					$member = $this->createAccount( $name, $email );
					
					\IPS\Db::i()->insert( 'core_login_links', array(
						'token_login_method'	=> $this->id,
						'token_member'			=> $member->member_id,
						'token_identifier'		=> $attrs[ $this->settings['uid_field'] ][0],
						'token_linked'			=> 1,
					) );
					
					$member->logHistory( 'core', 'social_account', array(
						'service'		=> static::getTitle(),
						'handler'		=> $this->id,
						'account_id'	=> $this->userId( $member ),
						'account_name'	=> $this->userProfileName( $member ),
						'linked'		=> TRUE,
						'registered'	=> TRUE
					) );
					
					if ( $syncOptions = $this->syncOptions( $member, TRUE ) )
					{
						$profileSync = array();
						foreach ( $syncOptions as $option )
						{
							$profileSync[ $option ] = array( 'handler' => $this->id, 'ref' => NULL, 'error' => NULL );
						}
						$member->profilesync = $profileSync;
						$member->save();
					}
					
					return $member;
				}
				catch ( \IPS\Login\Exception $exception )
				{
					if ( $exception->getCode() === \IPS\Login\Exception::MERGE_SOCIAL_ACCOUNT )
					{
						try
						{
							$identifier = \IPS\Db::i()->select( 'token_identifier', 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $exception->member->member_id ) )->first();

							if( $identifier != $attrs[ $this->settings['uid_field'] ][0] )
							{
								$exception->setCode( \IPS\Login\Exception::LOCAL_ACCOUNT_ALREADY_MERGED );
								throw $exception;
							}
						}
						catch( \UnderflowException $e )
						{
							\IPS\Db::i()->insert( 'core_login_links', array(
								'token_login_method'	=> $this->id,
								'token_member'			=> $exception->member->member_id,
								'token_identifier'		=> $attrs[ $this->settings['uid_field'] ][0],
								'token_linked'			=> 0,
							) );
						}
					}
					
					throw $exception;
				}
			}
		}
		catch ( LDAP\Exception $e )
		{
			\IPS\Log::log( $e, 'ldap' );
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}
	}
		
	/**
	 * Authenticate
	 *
	 * @param	\IPS\Member	$member				The member
	 * @param	object		$password			The plaintext password provided by the user, wrapped in an object that can be cast to a string so it doesn't show in any logs
	 * @return	bool
	 */
	public function authenticatePasswordForMember( \IPS\Member $member, $password )
	{
		try
		{
			$linkedId = \IPS\Db::i()->select( 'token_identifier', 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $member->member_id ) )->first();
			if ( $result = $this->_getUserWithFilter( $this->settings['uid_field'] . '=' . ldap_escape( $linkedId, NULL, LDAP_ESCAPE_FILTER ) ) )
			{
				return $this->_passwordIsValid( $result, $password );
			}
		}
		catch ( \UnderflowException $e ) { }
		
		return FALSE;
	}
	
	/* !Utility Methods */
	
	protected $_ldap;
	
	/**
	 * Get LDAP Connection
	 *
	 * @return	bool
	 * @throws	\IPS\Login\Handler\LDAP\Exception
	 */
	protected function _ldap()
	{
		if ( !$this->_ldap )
		{
			$this->_ldap = ldap_connect( $this->settings['server_host'], ( isset( $this->settings['server_port'] ) and $this->settings['server_port'] ) ? \intval( $this->settings['server_port'] ) : 389 );
			if ( !$this->_ldap )
			{
				throw new LDAP\Exception;
			}
			
			@ldap_set_option( $this->_ldap, LDAP_OPT_PROTOCOL_VERSION, $this->settings['server_protocol'] );
			@ldap_set_option( $this->_ldap, LDAP_OPT_REFERRALS, (bool) $this->settings['opt_referrals'] );
			
			if ( !@ldap_bind( $this->_ldap, ( isset( $this->settings['server_user'] ) and $this->settings['server_user'] ) ? $this->settings['server_user'] : NULL, ( isset( $this->settings['server_pass'] ) and $this->settings['server_pass'] ) ? $this->settings['server_pass'] : NULL ) )
			{
				throw new LDAP\Exception( ldap_error( $this->_ldap ), ldap_errno( $this->_ldap ) );
			}
		}
		
		return $this->_ldap;
	}
	
	/**
	 * Get name field
	 *
	 * @return	string
	 */
	public function _nameField()
	{
		return isset( $this->settings['name_field'] ) ? $this->settings['name_field'] : ( isset( $this->settings['uid_field'] ) ?  $this->settings['uid_field'] : NULL );
	}
	
	/**
	 * Get a user
	 *
	 * @param	string	$filter		Filter
	 * @return	resource|NULL
	 */
	protected function _getUserWithFilter( $filter )
	{		
		/* Add any additional filter */
		if ( $this->settings['filter'] )
		{
			$filter = ( mb_substr( $this->settings['filter'], 0, 1 ) === '(' ) ? "(&({$filter}){$this->settings['filter']})" : "(&({$filter})({$this->settings['filter']}))";
		}
		
		/* Search */
		$search = @ldap_search( $this->_ldap(), $this->settings['base_dn'], $filter );
		if ( !$search )
		{
			throw new LDAP\Exception( ldap_error( $this->_ldap() ), ldap_errno( $this->_ldap() ) );
		}
		
		/* Get result */
		$result = @ldap_first_entry( $this->_ldap(), $search );
		if ( !$result )
		{
			if ( $errno = ldap_errno( $this->_ldap ) )
			{
				throw new LDAP\Exception( ldap_error( $this->_ldap() ), $errno );
			}
			else
			{
				return NULL;
			}
		}
		
		return $result;
	}
	
	/**
	 * Password is valid
	 *
	 * @param	resource		$result				The resource from LDAP
	 * @param	object		$providedPassword	The plaintext password provided by the user, wrapped in an object that can be cast to a string so it doesn't show in any logs
	 * @return	bool
	 */
	protected function _passwordIsValid( $result, $providedPassword )
	{
		return (bool) @ldap_bind( $this->_ldap(), ldap_get_dn( $this->_ldap(), $result ), ( $this->settings['pw_required'] ? ( (string) $providedPassword ) : '' ) );
	}
	
	/* !Other Login Handler Methods */

	/**
	 * Can this handler process a password change for a member? 
	 *
	 * @return	bool
	 */
	public function canChangePassword( \IPS\Member $member )
	{
		if ( !isset( $this->settings['sync_password_changes'] ) or $this->settings['sync_password_changes'] )
		{
			return $this->canProcess( $member );
		}
		return FALSE;
	}
	
	/**
	 * Can this handler sync passwords?
	 *
	 * @return	bool
	 */
	public function canSyncPassword()
	{
		return (bool) ( isset( $this->settings['sync_password_changes'] ) AND $this->settings['sync_password_changes'] );
	}
	
	/**
	 * Email is in use?
	 * Used when registering or changing an email address to check the new one is available
	 *
	 * @param	string				$email		Email Address
	 * @param	\IPS\Member|NULL	$exclude	Member to exclude
	 * @return	bool|NULL Boolean indicates if email is in use (TRUE means is in use and thus not registerable) or NULL if this handler does not support such an API
	 */
	public function emailIsInUse( $email, \IPS\Member $exclude=NULL )
	{
		if ( $this->settings['email_field'] )
		{
			if ( $result = $this->_getUserWithFilter( $this->settings['email_field'] . '=' . ldap_escape( $email, NULL, LDAP_ESCAPE_FILTER )) )
			{
				if ( $exclude )
				{
					try
					{
						$linkedId = \IPS\Db::i()->select( 'token_identifier', 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $exclude->member_id ) )->first();
						
						if ( $attrs = @ldap_get_attributes( $this->_ldap(), $result ) and $attrs[ $this->settings['uid_field'] ][0] == $linkedId )
						{
							return FALSE;
						}
					}
					catch ( \UnderflowException $e ) { }
				}
				
				return TRUE;
			}
		}
		
		return FALSE;
	}
	
	/**
	 * Username is in use?
	 * Used when registering or changing an username to check the new one is available
	 *
	 * @param	string				$username	Username
	 * @param	\IPS\Member|NULL	$exclude	Member to exclude
	 * @return	bool|NULL			Boolean indicates if username is in use (TRUE means is in use and thus not registerable) or NULL if this handler does not support such an API
	 */
	public function usernameIsInUse( $username, \IPS\Member $exclude=NULL )
	{
		if ( $this->_nameField() )
		{
			if ( $result = $this->_getUserWithFilter( $this->_nameField() . '=' . ldap_escape( $username, NULL, LDAP_ESCAPE_FILTER ) ) )
			{
				if ( $exclude )
				{
					try
					{
						$linkedId = \IPS\Db::i()->select( 'token_identifier', 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $exclude->member_id ) )->first();
						
						if ( $attrs = @ldap_get_attributes( $this->_ldap(), $result ) and $attrs[ $this->settings['uid_field'] ][0] == $linkedId )
						{
							return FALSE;
						}
					}
					catch ( \UnderflowException $e ) { }
				}
				
				return TRUE;
			}
		}
		
		return FALSE;
	}
	
	/**
	 * Change Email Address
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	string		$oldEmail	Old Email Address
	 * @param	string		$newEmail	New Email Address
	 * @return	void
	 */
	public function changeEmail( \IPS\Member $member, $oldEmail, $newEmail )
	{
		if ( !isset( $this->settings['sync_email_changes'] ) or $this->settings['sync_email_changes'] )
		{
			try
			{
				$linkedId = \IPS\Db::i()->select( 'token_identifier', 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $member->member_id ) )->first();
				if ( $result = $this->_getUserWithFilter( $this->settings['uid_field'] . '=' . ldap_escape( $linkedId, NULL, LDAP_ESCAPE_FILTER ) ) )
				{
					if ( !@ldap_modify( $this->_ldap(), ldap_get_dn( $this->_ldap(), $result ), array( $this->settings['email_field'] => $newEmail ) ) )
					{
						$e = new LDAP\Exception( ldap_error( $this->_ldap() ), ldap_errno( $this->_ldap() ) );
						\IPS\Log::log( $e, 'ldap' );
					}
				}
			}
			catch ( \UnderflowException $e ) { }
		}
	}
	
	/**
	 * Change Password
	 *
	 * @param	\IPS\Member	$member			The member
	 * @param	string		$newPassword		New Password, wrapped in an object that can be cast to a string so it doesn't show in any logs
	 * @return	void
	 */
	public function changePassword( \IPS\Member $member, $newPassword )
	{
		if ( !isset( $this->settings['sync_password_changes'] ) or $this->settings['sync_password_changes'] )
		{
			try
			{
				$linkedId = \IPS\Db::i()->select( 'token_identifier', 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $member->member_id ) )->first();
				if ( $result = $this->_getUserWithFilter( $this->settings['uid_field'] . '=' . ldap_escape( $linkedId, NULL, LDAP_ESCAPE_FILTER ) ) )
				{
					if ( !@ldap_modify( $this->_ldap(), ldap_get_dn( $this->_ldap(), $result ), array( 'userPassword' => "{SHA}" . base64_encode( pack( "H*", sha1( $newPassword ) ) ) ) ) )
					{
						$e = new LDAP\Exception( ldap_error( $this->_ldap() ), ldap_errno( $this->_ldap() ) );
						\IPS\Log::log( $e, 'ldap' );
					}
				}
			}
			catch ( \UnderflowException $e ) { }
		}
	}
	
	/**
	 * Change Username
	 *
	 * @param	\IPS\Member	$member			The member
	 * @param	string		$oldUsername	Old Username
	 * @param	string		$newUsername	New Username
	 * @return	void
	 */
	public function changeUsername( \IPS\Member $member, $oldUsername, $newUsername )
	{
		if ( !isset( $this->settings['sync_name_changes'] ) or $this->settings['sync_name_changes'] )
		{
			try
			{
				$linkedId = \IPS\Db::i()->select( 'token_identifier', 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $member->member_id ) )->first();
				if ( $result = $this->_getUserWithFilter( $this->settings['uid_field'] . '=' . ldap_escape( $linkedId, NULL, LDAP_ESCAPE_FILTER ) ) )
				{
					if ( !@ldap_modify( $this->_ldap(), ldap_get_dn( $this->_ldap(), $result ), array( $this->_nameField() => $newUsername ) ) )
					{
						$e = new LDAP\Exception( ldap_error( $this->_ldap() ), ldap_errno( $this->_ldap() ) );
						\IPS\Log::log( $e, 'ldap' );
					}
				}
			}
			catch ( \UnderflowException $e ) { }
		}
	}
	
	/**
	 * Forgot Password URL
	 *
	 * @return	\IPS\Http\Url|NULL
	 */
	public function forgotPasswordUrl()
	{
		return ( isset( $this->settings['forgot_password_url'] ) and $this->settings['forgot_password_url'] ) ? \IPS\Http\Url::external( $this->settings['forgot_password_url'] ) : NULL;
	}
	
	
	/**
	 * Get user's profile name
	 * May return NULL if server doesn't support this
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	string|NULL
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\DomainException		General error where it is safe to show a message to the user
	 * @throws	\RuntimeException		Unexpected error from service
	 */
	public function userProfileName( \IPS\Member $member )
	{
		if ( $nameField = $this->_nameField() )
		{
			if ( !( $link = $this->_link( $member ) ) )
			{
				throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			if ( $result = $this->_getUserWithFilter( $this->settings['uid_field'] . '=' . ldap_escape( $link['token_identifier'], NULL, LDAP_ESCAPE_FILTER ) ) )
			{
				if ( $attrs = @ldap_get_attributes( $this->_ldap(), $result ) and isset( $attrs[ $nameField ] ) )
				{
					return $attrs[ $nameField ][0];
				}
				else
				{
					throw new \RuntimeException;
				}
			}
			else
			{
				throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
			}
		}
		
		return NULL;
	}
	
	/**
	 * Get user's email address
	 * May return NULL if server doesn't support this
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	string|NULL
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\DomainException		General error where it is safe to show a message to the user
	 * @throws	\RuntimeException		Unexpected error from service
	 */
	public function userEmail( \IPS\Member $member )
	{
		if ( $this->settings['email_field'] )
		{
			if ( !( $link = $this->_link( $member ) ) )
			{
				throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			if ( $result = $this->_getUserWithFilter( $this->settings['uid_field'] . '=' . ldap_escape( $link['token_identifier'], NULL, LDAP_ESCAPE_FILTER ) ) )
			{
				if ( $attrs = @ldap_get_attributes( $this->_ldap(), $result ) and isset( $attrs[ $this->settings['email_field'] ] ) )
				{
					return $attrs[ $this->settings['email_field'] ][0];
				}
				else
				{
					throw new \RuntimeException;
				}
			}
			else
			{
				throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
			}
		}
		
		return NULL;
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
		
		if ( isset( $this->settings['email_field'] ) and $this->settings['email_field'] and isset( $this->settings['update_email_changes'] ) and $this->settings['update_email_changes'] === 'optional' )
		{
			$return[] = 'email';
		}
		
		if ( $this->_nameField() and isset( $this->settings['update_name_changes'] ) and $this->settings['update_name_changes'] === 'optional' )
		{
			$return[] = 'name';
		}
				
		return $return;
	}

	/**
	 * Has any sync options
	 *
	 * @return	bool
	 */
	public function hasSyncOptions()
	{
		return TRUE;
	}
}