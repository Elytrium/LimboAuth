<?php
/**
 * @brief		External Database Login Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		1 June 2017
 */

namespace IPS\Login\Handler;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Standard Internal Database Login Handler
 */
class _ExternalDatabase extends \IPS\Login\Handler
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
		return 'login_handler_External';
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
			'login_external_conn',
			'sql_host'		=>  new \IPS\Helpers\Form\Text( 'login_external_host', ( isset( $this->settings['sql_host'] ) and $this->settings['sql_host'] ) ? $this->settings['sql_host'] : 'localhost', TRUE ),
			'sql_user'		=>  new \IPS\Helpers\Form\Text( 'login_external_user', isset( $this->settings['sql_user'] ) ? $this->settings['sql_user'] : NULL, TRUE ),
			'sql_pass'		=>  new \IPS\Helpers\Form\Text( 'login_external_pass', isset( $this->settings['sql_pass'] ) ? $this->settings['sql_pass'] : NULL, FALSE ),
			'sql_database'	=>  new \IPS\Helpers\Form\Text( 'login_external_database', isset( $this->settings['sql_database'] ) ? $this->settings['sql_database'] : NULL, TRUE ),
			'sql_port'		=>  new \IPS\Helpers\Form\Number( 'login_external_port', isset( $this->settings['sql_port'] ) ? $this->settings['sql_port'] : 3306, FALSE ),
			'sql_socket'	=>  new \IPS\Helpers\Form\Text( 'login_external_socket', isset( $this->settings['sql_socket'] ) ? $this->settings['sql_socket'] : NULL, FALSE ),
			'login_external_schema',
			'db_table'		=>  new \IPS\Helpers\Form\Text( 'login_external_table', isset( $this->settings['db_table'] ) ? $this->settings['db_table'] : NULL, TRUE ),
			'db_col_id'		=>  new \IPS\Helpers\Form\Text( 'login_external_id', isset( $this->settings['db_col_id'] ) ? $this->settings['db_col_id'] : NULL, FALSE ),
			'db_col_user'	=>  new \IPS\Helpers\Form\Text( 'login_external_username', isset( $this->settings['db_col_user'] ) ? $this->settings['db_col_user'] : NULL, FALSE, array(), function( $val )
			{
				if ( !$val and \IPS\Request::i()->login_auth_types & \IPS\Login::AUTH_TYPE_USERNAME )
				{
					throw new \DomainException('login_external_username_err');
				}
			} ),
			'db_col_email'	=>  new \IPS\Helpers\Form\Text( 'login_external_email', isset( $this->settings['db_col_email'] ) ? $this->settings['db_col_email'] : NULL, FALSE, array(), function( $val )
			{
				if ( !$val and \IPS\Request::i()->login_auth_types & \IPS\Login::AUTH_TYPE_EMAIL )
				{
					throw new \DomainException('login_external_email_err');
				}
			} ),
			'db_col_pass'	=>  new \IPS\Helpers\Form\Text( 'login_external_password', isset( $this->settings['db_col_pass'] ) ? $this->settings['db_col_pass'] : NULL, TRUE ),
			'db_encryption'	=>  new \IPS\Helpers\Form\Radio( 'login_external_encryption', ( isset( $this->settings['db_encryption'] ) and $this->settings['db_encryption'] ) ? $this->settings['db_encryption'] : NULL, TRUE, array(
				'options'	=> array(
					'password_hash'	=> 'login_external_encryption_password_hash',
					'md5'			=> 'MD5',
					'sha1'			=> 'SHA1',
					'plaintext'		=> 'login_external_encryption_plain',
					'other'			=> 'login_external_encryption_other',
				),
				'toggles'	=> array(
					'other'			=> array( 'db_encryption_hash', 'db_encryption_validate' )
				)
			) ),
			'db_encryption_hash'	=> new \IPS\Helpers\Form\Codemirror( 'login_external_encryption_hash', isset( $this->settings['db_encryption_hash'] ) ? $this->settings['db_encryption_hash'] : 'return password_hash( $providedPassword );', NULL, array(
				'mode' => 'php',
				'tags' => array( '$providedPassword' => \IPS\Member::loggedIn()->language()->addToStack('login_external_encryption_custom_password') )
			), function( $val )
			{
				try
				{
					$result = eval( 'function _' . md5( mt_rand() ) . '() { ' . $val . ' }' );
				}
				catch ( \Exception $e )
				{
					throw new \DomainException( $e->getMessage() );
				}
				catch ( \Throwable $e )
				{
					throw new \DomainException( $e->getMessage() );
				}
			}, NULL, NULL, 'db_encryption_hash' ),
			'db_encryption_validate'	=> new \IPS\Helpers\Form\Codemirror( 'login_external_encryption_validate', isset( $this->settings['db_encryption_validate'] ) ? $this->settings['db_encryption_validate'] : 'return password_verify( $providedPassword, $row[\'password\'] );', NULL, array(
				'mode' => 'php',
				'tags' => array( '$row' => \IPS\Member::loggedIn()->language()->addToStack('login_external_encryption_custom_row'), '$providedPassword' => \IPS\Member::loggedIn()->language()->addToStack('login_external_encryption_custom_password') )
			), function( $val )
			{
				try
				{
					$result = eval( 'function _' . md5( mt_rand() ) . '() { ' . $val . ' }' );
				}
				catch ( \Exception $e )
				{
					throw new \DomainException( $e->getMessage() );
				}
				catch ( \Throwable $e )
				{
					throw new \DomainException( $e->getMessage() );
				}
			}, NULL, NULL, 'db_encryption_validate' ),
			'db_extra'		=>  new \IPS\Helpers\Form\Text( 'login_external_extra', isset( $this->settings['db_extra'] ) ? $this->settings['db_extra'] : NULL ),
			'login_settings',
			'auth_types'	=> new \IPS\Helpers\Form\Select( 'login_auth_types', isset( $this->settings['auth_types'] ) ? $this->settings['auth_types'] : ( \IPS\Login::AUTH_TYPE_EMAIL ), TRUE, array( 'options' => array(
				\IPS\Login::AUTH_TYPE_USERNAME + \IPS\Login::AUTH_TYPE_EMAIL => 'username_or_email',
				\IPS\Login::AUTH_TYPE_EMAIL	=> 'email_address',
				\IPS\Login::AUTH_TYPE_USERNAME => 'username',
			), 'toggles' => array( \IPS\Login::AUTH_TYPE_USERNAME + \IPS\Login::AUTH_TYPE_EMAIL => array( 'form_' . $id . '_login_auth_types_warning' ), \IPS\Login::AUTH_TYPE_USERNAME => array( 'form_' . $id . '_login_auth_types_warning' ) ) ) ),
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

		$settings['forgot_password_url'] = (string) $settings['forgot_password_url'];
		
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
	 * @throws	\IPS\Db\Exception
	 */
	public function testSettings()
	{
		$select = array( $this->settings['db_col_pass'] );
				
		if ( $this->settings['db_col_user'] )
		{
			$select[] = $this->settings['db_col_user'];
		}
		
		if ( $this->settings['db_col_email'] )
		{
			$select[] = $this->settings['db_col_email'];
		}
		
		try
		{
			$result = $this->_externalDb()->select( implode( ',', $select ), $this->settings['db_table'], ( isset( $this->settings['db_extra'] ) AND $this->settings['db_extra'] != '' ) ? array( $this->settings['db_extra'] ) : NULL )->first();
		}
		catch ( \UnderflowException $e )
		{
			// It's possible that no users exist, which is fine
		}
		
		return TRUE;
	}
	
	/* !Authentication */
	
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
		/* Fetch result */
		try
		{
			if( !$usernameOrEmail )
			{
				throw new \UnderflowException;
			}

			$result = $this->_getRowFromExternalDb( $usernameOrEmail );
		}
		catch ( \IPS\Db\Exception $e )
		{
			throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
		}
		catch ( \UnderflowException $e )
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
		$name = $this->settings['db_col_user'] ? $result[ $this->settings['db_col_user'] ] : NULL;
		$email = $this->settings['db_col_email'] ? $result[ $this->settings['db_col_email'] ] : NULL;
		$member = NULL;
		if ( $this->settings['db_col_id'] )
		{
			try
			{
				$link = \IPS\Db::i()->select( '*', 'core_login_links', array( 'token_login_method=? AND token_identifier=?', $this->id, $result[ $this->settings['db_col_id'] ] ) )->first();
				$member = \IPS\Member::load( $link['token_member'] );
				
				/* If the user never finished the linking process, or the account has been deleted, discard this access token */
				if ( !$link['token_linked'] or !$member->member_id )
				{
					\IPS\Db::i()->delete( 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $link['token_member'] ) );
					$member = NULL;
				}
			}
			catch ( \UnderflowException $e ) { }
		}
		else
		{
			if ( $name )
			{
				$_member = \IPS\Member::load( $name, 'name' );
				if ( $_member->member_id )
				{
					$member = $_member;
				}
			}
			if ( $email )
			{
				$_member = \IPS\Member::load( $email, 'email' );
				if ( $_member->member_id )
				{
					$member = $_member;
				}
			}		
		}
				
		/* Verify password */
		if( !$this->_passwordIsValid( $result, $password ) )
		{
			throw new \IPS\Login\Exception( \IPS\Member::loggedIn()->language()->addToStack( 'login_err_bad_password', FALSE, array( 'pluralize' => array( $this->authType() ) ) ), \IPS\Login\Exception::BAD_PASSWORD, NULL, $member );
		}
						
		/* Create account if we don't have one */
		if ( $member )
		{
			return $member;
		}
		elseif ( $this->settings['db_col_id'] )
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
					'token_identifier'		=> $result[ $this->settings['db_col_id'] ],
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

						if( $identifier != $result[ $this->settings['db_col_id'] ] )
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
							'token_identifier'		=> $result[ $this->settings['db_col_id'] ],
							'token_linked'			=> 0,
						) );
					}
				}
				
				throw $exception;
			}
		}
		else
		{
			return $this->createAccount( $name, $email );
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
		if ( $this->authType() & \IPS\Login::AUTH_TYPE_EMAIL )
		{
			try
			{
				$result = $this->_getRowFromExternalDb( $member->email );
				
				if( $this->_passwordIsValid( $result, $password ) )
				{
					return TRUE;
				}
			}
			catch ( \Exception $e ) { }
		}
		
		if ( $this->authType() & \IPS\Login::AUTH_TYPE_USERNAME )
		{
			try
			{
				$result = $this->_getRowFromExternalDb( $member->name );
				
				if( $this->_passwordIsValid( $result, $password ) )
				{
					return TRUE;
				}
			}
			catch ( \Exception $e ) { }
		}
		
		return FALSE;
	}
	
	/**
	 * Get row from external database
	 *
	 * @param	string		$usernameOrEmail	The username or email address provided by the user
	 * @return	array
	 * @throws	\UnderflowException
	 * @throws	\IPS\Db\Exception
	 */
	public function _getRowFromExternalDb( $usernameOrEmail )
	{
		$where = array();

		/* Build where clause */
		switch ( $this->authType() )
		{
			case \IPS\Login::AUTH_TYPE_USERNAME:
				$where[] = array( "{$this->settings['db_col_user']}=?", $usernameOrEmail );
				break;
			
			case \IPS\Login::AUTH_TYPE_EMAIL:
				$where[] = array( "{$this->settings['db_col_email']}=?", $usernameOrEmail );
				break;
			
			case \IPS\Login::AUTH_TYPE_USERNAME + \IPS\Login::AUTH_TYPE_EMAIL:
				$where[] = array( "({$this->settings['db_col_user']}=? OR {$this->settings['db_col_email']}=?)", $usernameOrEmail, $usernameOrEmail );
				break;
				
		}
		if ( $this->settings['db_extra'] )
		{
			$where[] = array( $this->settings['db_extra'] );
		}

		/* Fetch */
		return $this->_externalDb()->select( '*', $this->settings['db_table'], $where )->first();
	}
	
	/* !Other Login Handler Methods */
	
	/**
	 * Can this handler process a login for a member? 
	 *
	 * @return	bool
	 */
	public function canProcess( \IPS\Member $member )
	{
		if ( $this->settings['db_col_id'] )
		{
			return parent::canProcess( $member );
		}
		else
		{
			if ( $this->authTypes & \IPS\Login::AUTH_TYPE_USERNAME and $member->name and $this->usernameIsInUse( $member->name ) )
			{
				return TRUE;
			}
			if ( $this->authTypes & \IPS\Login::AUTH_TYPE_EMAIL and $member->email and $this->emailIsInUse( $member->email ) )
			{
				return TRUE;
			}
			return FALSE;
		}
	}
	
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
		$where = array();
		$where[] = array( "{$this->settings['db_col_email']}=?", $email );
		
		if ( $exclude )
		{
			if ( $this->settings['db_col_id'] )
			{
				try
				{
					$linkedId = \IPS\Db::i()->select( 'token_identifier', 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $exclude->member_id ) )->first();
					$where[] = array( "{$this->settings['db_col_id']}<>?", $linkedId );
				}
				catch ( \UnderflowException $e ) { }
			}
			else
			{
				return NULL;
			}
		}
		
		try
		{
			$this->_externalDb()->select( $this->settings['db_col_email'], $this->settings['db_table'], $where )->first();
			return TRUE;
		}
		catch ( \UnderflowException $e )
		{
			return FALSE;
		}
		catch ( \IPS\Db\Exception $e )
		{
			return NULL;
		}
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
		$where = array();
		$where[] = array( "{$this->settings['db_col_user']}=?", $username );
		
		if ( $exclude )
		{
			if ( $this->settings['db_col_id'] )
			{
				try
				{
					$linkedId = \IPS\Db::i()->select( 'token_identifier', 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $exclude->member_id ) )->first();
					$where[] = array( "{$this->settings['db_col_id']}<>?", $linkedId );
				}
				catch ( \UnderflowException $e ) { }
			}
			else
			{
				return NULL;
			}
		}
		
		try
		{
			$result = $this->_externalDb()->select( $this->settings['db_col_user'], $this->settings['db_table'], $where )->first();
			return TRUE;
		}
		catch ( \UnderflowException $e )
		{
			return FALSE;
		}
		catch ( \IPS\Db\Exception $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Change Email Address
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	string		$oldEmail	Old Email Address
	 * @param	string		$newEmail	New Email Address
	 * @return	void
	 * @throws	\IPS\Db\Exception
	 */
	public function changeEmail( \IPS\Member $member, $oldEmail, $newEmail )
	{
		if ( $this->settings['db_col_email'] and ( !isset( $this->settings['sync_email_changes'] ) or $this->settings['sync_email_changes'] ) )
		{
			$where = array( $this->settings['db_col_email'] . '=?', $oldEmail );
			if ( $this->settings['db_col_id'] )
			{
				try
				{
					$linkedId = \IPS\Db::i()->select( 'token_identifier', 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $member->member_id ) )->first();
					$where = array( "{$this->settings['db_col_id']}=?", $linkedId );
				}
				catch ( \UnderflowException $e ) { }
			}
			$this->_externalDb()->update( $this->settings['db_table'], array( $this->settings['db_col_email'] => $newEmail ), $where );
		}
	}
	
	/**
	 * Change Password
	 *
	 * @param	\IPS\Member	$member			The member
	 * @param	string		$newPassword		New Password, wrapped in an object that can be cast to a string so it doesn't show in any logs
	 * @return	void
	 * @throws	\IPS\Db\Exception
	 */
	public function changePassword( \IPS\Member $member, $newPassword )
	{
		if ( !isset( $this->settings['sync_password_changes'] ) or $this->settings['sync_password_changes'] )
		{
			if ( $this->settings['db_col_id'] )
			{
				try
				{
					$linkedId = \IPS\Db::i()->select( 'token_identifier', 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $member->member_id ) )->first();
					$where = array( "{$this->settings['db_col_id']}=?", $linkedId );
				}
				catch ( \UnderflowException $e )
				{
					return;
				}
			}
			else
			{
				$where = '1=0';
				switch ( $this->authTypes )
				{
					case \IPS\Login::AUTH_TYPE_USERNAME:
						$where = array( "{$this->settings['db_col_user']}=?", $member->name );
						break;
					
					case \IPS\Login::AUTH_TYPE_EMAIL:
						$where = array( "{$this->settings['db_col_email']}=?", $member->email );
						break;
						
					case \IPS\Login::AUTH_TYPE_USERNAME + \IPS\Login::AUTH_TYPE_EMAIL:
						$where = array( "{$this->settings['db_col_email']}=? OR {$this->settings['db_col_user']}=?", $member->email, $member->name );
						break;
				}
			}
			
			$this->_externalDb()->update( $this->settings['db_table'], array( $this->settings['db_col_pass'] => $this->_encryptedPassword( $newPassword ) ), $where );
		}
	}
	
	/**
	 * Change Username
	 *
	 * @param	\IPS\Member	$member			The member
	 * @param	string		$oldUsername	Old Username
	 * @param	string		$newUsername	New Username
	 * @return	void
	 * @throws	\IPS\Db\Exception
	 */
	public function changeUsername( \IPS\Member $member, $oldUsername, $newUsername )
	{
		if ( $this->settings['db_col_user'] and ( !isset( $this->settings['sync_name_changes'] ) or $this->settings['sync_name_changes'] ) )
		{
			$where = array( $this->settings['db_col_user'] . '=?', $oldUsername );
			if ( $this->settings['db_col_id'] )
			{
				try
				{
					$linkedId = \IPS\Db::i()->select( 'token_identifier', 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $member->member_id ) )->first();
					$where = array( "{$this->settings['db_col_id']}=?", $linkedId );
				}
				catch ( \UnderflowException $e ) { }
			}
			$this->_externalDb()->update( $this->settings['db_table'], array( $this->settings['db_col_user'] => $newUsername ), $where );
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
	 * Force Password Reset URL
	 *
	 * @param	\IPS\Member			$member		The member
	 * @param	\IPS\Http\Url|NULL	$ref		Referrer
	 * @return	\IPS\Http\Url|NULL
	 */
	public function forcePasswordResetUrl( \IPS\Member $member, ?\IPS\Http\Url $ref = NULL ): ?\IPS\Http\Url
	{
		return $member->passwordResetForced( $ref );
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
		if ( isset( $this->settings['db_col_user'] ) and $this->settings['db_col_user'] )
		{
			$result = NULL;
			
			try
			{
				if ( $this->settings['db_col_id'] )
				{
					$linkedId = \IPS\Db::i()->select( 'token_identifier', 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $member->member_id ) )->first();
					$result = $this->_externalDb()->select( '*', $this->settings['db_table'], array( "{$this->settings['db_col_id']}=?", $linkedId ) )->first();;
				}
				else
				{
					if ( $this->authType() & \IPS\Login::AUTH_TYPE_EMAIL )
					{
						$result = $this->_getRowFromExternalDb( $member->email );
					}
					
					if ( !$result and $this->authType() & \IPS\Login::AUTH_TYPE_USERNAME )
					{
						$result = $this->_getRowFromExternalDb( $member->name );
					}
				}
			}
			catch ( \Exception $e )
			{
				throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			if ( $result )
			{
				return $result[ $this->settings['db_col_user'] ];
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
		if ( isset( $this->settings['db_col_email'] ) and $this->settings['db_col_email'] )
		{
			$result = NULL;
			
			try
			{
				if ( $this->settings['db_col_id'] )
				{
					$linkedId = \IPS\Db::i()->select( 'token_identifier', 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $member->member_id ) )->first();
					$result = $this->_externalDb()->select( '*', $this->settings['db_table'], array( "{$this->settings['db_col_id']}=?", $linkedId ) )->first();
				}
				else
				{
					if ( $this->authType() & \IPS\Login::AUTH_TYPE_EMAIL )
					{
						$result = $this->_getRowFromExternalDb( $member->email );
					}
					
					if ( !$result and $this->authType() & \IPS\Login::AUTH_TYPE_USERNAME )
					{
						$result = $this->_getRowFromExternalDb( $member->name );
					}
				}
			}
			catch ( \Exception $e )
			{
				throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			if ( $result )
			{
				return $result[ $this->settings['db_col_email'] ];
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
		
		if ( isset( $this->settings['db_col_email'] ) and $this->settings['db_col_email'] and isset( $this->settings['update_email_changes'] ) and $this->settings['update_email_changes'] === 'optional' )
		{
			$return[] = 'email';
		}
		
		if ( isset( $this->settings['db_col_user'] ) and $this->settings['db_col_user'] and isset( $this->settings['update_name_changes'] ) and $this->settings['update_name_changes'] === 'optional' )
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
	
	/* !Utility Methods */
	
	/**
	 * Get DB Connection
	 *
	 * @return	bool
	 * @throws	\IPS\Db\Exception
	 */
	protected function _externalDb()
	{
		return \IPS\Db::i( 'external_login_' . $this->id, $this->settings );
	}
	
	/**
	 * Password is valid
	 *
	 * @param	array	$row					The member row
	 * @param	object	$providedPassword	The plaintext password provided by the user, wrapped in an object that can be cast to a string so it doesn't show in any logs
	 * @return	bool
	 */
	protected function _passwordIsValid( $row, $providedPassword )
	{
		switch ( $this->settings['db_encryption'] )
		{
			case 'password_hash':
				return password_verify( $providedPassword, $row[ $this->settings['db_col_pass'] ] );
				
			case 'other':
				try
				{
					return @eval( $this->settings['db_encryption_validate'] );
				}
				catch ( \Exception $e )
				{
					\IPS\Log::log( $e, 'external_login' );
					throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
				}
				catch ( \Throwable $e )
				{
					\IPS\Log::log( $e, 'external_login' );
					throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
				}
			
			default:
				return \IPS\Login::compareHashes( $row[ $this->settings['db_col_pass'] ], $this->_encryptedPassword( $providedPassword ) );
		}
	}
	
	/**
	 * Encrypted password
	 *
	 * @param	object	$providedPassword	The plaintext password provided by the user, wrapped in an object that can be cast to a string so it doesn't show in any logs
	 * @return	string
	 */
	protected function _encryptedPassword( $providedPassword )
	{
		$providedPassword = (string) $providedPassword;
		
		switch ( $this->settings['db_encryption'] )
		{
			case 'md5':
				return md5( $providedPassword );
				
			case 'sha1':
				return sha1( $providedPassword );
				
			case 'password_hash':
				return password_hash( $providedPassword, PASSWORD_DEFAULT );
				
			case 'other':
				try
				{
					return @eval( $this->settings['db_encryption_hash'] );
				}
				catch ( \Exception $e )
				{
					\IPS\Log::log( $e, 'external_login' );
					throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
				}
				catch ( \Throwable $e )
				{
					\IPS\Log::log( $e, 'external_login' );
					throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
				}
			
			default:
				return $providedPassword;
		}
	}
}