<?php
/**
 * @brief		Login Methods
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 May 2017
 */

namespace IPS\core\modules\admin\settings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Login Methods
 */
class _login extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\Login\Handler';
	
	/**
	 * Show the "add" button in the page root rather than the table root
	 */
	protected $_addButtonInRoot = FALSE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'login_access' );
		return parent::execute();
	}
	
	/**
	 * Get Root Buttons
	 *
	 * @return	array
	 */
	public function _getRootButtons()
	{
		$buttons = parent::_getRootButtons();
		
		if ( isset( $buttons['add'] ) )
		{
			$buttons['add']['link'] = $buttons['add']['link']->setQueryString( '_new', 1 );
		}
		
		return $buttons;
	}
	
	/**
	 * Manage
	 *
	 * @return void
	 */
	protected function manage()
	{
		/* Work out tabs */
		$tabs = array();
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'settings', 'login_manage' ) )
		{
			$tabs['handlers'] = 'login_handlers';
		}
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'settings', 'login_settings' ) )
		{
			$tabs['settings'] = 'login_settings';
		}
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'settings', 'registration_settings' ) )
		{
			$tabs['registration'] = 'registration_settings';
		}
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'settings', 'login_account_management' ) )
		{
			$tabs['accountsettings'] = 'account_management_settings';
		}
		
		/* Get active tab */
		if ( isset( \IPS\Request::i()->tab ) and isset( $tabs[ \IPS\Request::i()->tab ] ) )
		{
			$activeTab = \IPS\Request::i()->tab;
		}
		else
		{
			$_tabs = array_keys( $tabs ) ;
			$activeTab = array_shift( $_tabs );
		}
		
		/* Get active tab contents */
		$output = '';
		switch ( $activeTab )
		{
			case 'handlers':
				\IPS\Dispatcher::i()->checkAcpPermission( 'login_manage' );
				parent::manage();
				$output = \IPS\Output::i()->output;
				break;
			case 'settings':
				$output = $this->_settings();
				break;
			case 'registration':
				$output = $this->_registration();
				break;
			case 'accountsettings':
				$output = $this->_accountsettings();
				break;
		}
		
		/* Output */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_settings_login');
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $output;
		}
		else
		{
			\IPS\Output::i()->sidebar['actions'] = array(
				'forcelogout'	=> array(
					'title'		=> 'force_all_logout',
					'icon'		=> 'lock',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=login&do=forceLogout' )->csrf(),
					'data'		=> array( 'confirm' => '' ),
				),
			);
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, $output, \IPS\Http\Url::internal( "app=core&module=settings&controller=login" ) );
		}
	}
		
	/**
	 * Add/Edit Form
	 *
	 * @return void
	 */
	protected function form()
	{
		if ( \IPS\Request::i()->id )
		{
			return parent::form();
		}
		else
		{
			\IPS\Output::i()->output = (string) new \IPS\Helpers\Wizard( array(
				'login_handler'	=> function( $data )
				{
					$options = array();
					foreach ( \IPS\Login\Handler::handlerClasses() as $class )
					{
						if ( !$class::$allowMultiple )
						{
							foreach ( \IPS\Login\Handler::roots() as $handler )
							{
								if ( $handler instanceof $class )
								{
									continue 2;
								}
							}
						}
						$options[ $class ] = $class::getTitle();
					}
					
					$form = new \IPS\Helpers\Form( 'login_handler_1', 'continue' );
					$form->add( new \IPS\Helpers\Form\Radio( 'login_handler', TRUE, NULL, array( 'options' => $options ), function( $val )
					{
						$val::testCompatibility();
					} ) );
					if ( $values = $form->values() )
					{
						return array( 'handler' => $values['login_handler'] );
					}
					return $form;
				},
				'login_details'	=> function( $data )
				{
					$node = new $data['handler'];
					$node->classname = $data['handler'];
					$form = $this->_addEditForm( $node );
					if ( $values = $form->values() )
					{
						try
						{
							$node->settings = array();
							$node->order = \IPS\Db::i()->select( 'MAX(login_order)', $node::$databaseTable  )->first() + 1;
							$node->saveForm( $node->formatFormValues( $values ) );
											
							\IPS\Session::i()->log( 'acplog__node_created', array( $this->title => TRUE, $node->titleForLog() => FALSE ) );
			
							\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=settings&controller=login') );
						}
						catch ( \LogicException $e )
						{
							$form->error = $e->getMessage();
						}
					}
					return $form;
				}
			), \IPS\Http\Url::internal('app=core&module=settings&controller=login&do=form') );
		}
	}
	
	/**
	 * Toggle Enabled/Disable
	 * Overridden so we can check the settings are okay before we enable
	 *
	 * @return	void
	 */
	protected function enableToggle()
	{
		\IPS\Session::i()->csrfCheck();
		
		$loginMethod = \IPS\Login\Handler::load( \IPS\Request::i()->id );
		
		if ( \IPS\Request::i()->status )
		{
			try
			{
				$loginMethod->testSettings();
			}
			catch ( \Exception $e )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=settings&controller=login&do=form&id={$loginMethod->id}" ) );
			}
		}
		else
		{
			$this->_disableCheck( $loginMethod );
		}

		/* Clear caches */
		unset( \IPS\Data\Store::i()->loginMethods );
		\IPS\Data\Cache::i()->clearAll();

		/* Toggle */
		return parent::enableToggle();
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	protected function delete()
	{
		/* Check it's okay */
		$loginMethod = \IPS\Login\Handler::load( \IPS\Request::i()->id );
		$this->_disableCheck( $loginMethod );
		
		/* Do it */
		parent::delete();
		
		/* Clear caches */
		unset( \IPS\Data\Store::i()->loginMethods );
		\IPS\Data\Cache::i()->clearAll();
	}
	
	/**
	 * If a particular method is disabled/deleted - will we still be able to log in?
	 *
	 * @param	\IPS\Login\Handler	$methodToRemove	Handler to be disabled/deleted
	 * @return	void
	 */
	protected function _disableCheck( \IPS\Login\Handler $methodToRemove )
	{
		foreach ( \IPS\Login::methods() as $method )
		{
			if ( $method != $methodToRemove and $method->canProcess( \IPS\Member::loggedIn() ) and $method->acp )
			{
				return true;
			}
		}
		\IPS\Output::i()->error( 'login_handler_cannot_disable', '1C166/5', 403, '' );
	}
	
	/**
	 * Login Settings
	 *
	 * @return string
	 */
	protected function _settings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'login_settings' );
		
		/* Build Form */
		$form = new \IPS\Helpers\Form();
		$form->addHeader( 'ipb_bruteforce_attempts' );
		$form->add( new \IPS\Helpers\Form\Number( 'ipb_bruteforce_attempts', \IPS\Settings::i()->ipb_bruteforce_attempts, FALSE, array( 'min' => 0, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), \IPS\Member::loggedIn()->language()->addToStack('failed_logins'), 'ipb_bruteforce_attempts' ) );
		$form->add( new \IPS\Helpers\Form\Interval( 'ipb_bruteforce_period', \IPS\Settings::i()->ipb_bruteforce_period, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::MINUTES, 'min' => 0, 'max' => 10000, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'ipb_bruteforce_period' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'ipb_bruteforce_unlock', \IPS\Settings::i()->ipb_bruteforce_unlock, FALSE, array(), NULL, NULL, NULL, 'ipb_bruteforce_unlock' ) );
		$form->addHeader( 'login_settings' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'new_device_email', \IPS\Settings::i()->new_device_email, FALSE ) );
		
		/* Save */
		if ( $values = $form->values() )
		{
			/* unlock all locked members if we have disabled the locking */
			if ( \IPS\Settings::i()->ipb_bruteforce_attempts > 0 AND $values['ipb_bruteforce_attempts'] == 0 )
			{
				\IPS\Member::updateAllMembers( array( 'failed_logins' => NULL , 'failed_login_count' => 0 ) );

				/* disable task */
				\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 0 ), "`key`='unlockmembers'" );
			}
			else if ( $values['ipb_bruteforce_attempts'] > 0 )
			{
				/* enable task */
				\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 1 ), "`key`='unlockmembers'" );
			}

			$form->saveAsSettings( $values );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			\IPS\Session::i()->log( 'acplogs__login_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=login&tab=settings' ), 'saved' );
		}
		
		/* Display */
		return (string) $form;
	}
	
	/**
	 * HTTPS check
	 *
	 * @return null
	 */
	protected function httpsCheck()
	{
		try
		{
			$response = \IPS\Http\Url::external( 'https://' . mb_substr( \IPS\Settings::i()->base_url, 7 ) )->request()->get();
			\IPS\Output::i()->output = $response;
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->message( $e->getMessage() ?: '500_error_title', 'error' );
		}
	}
	
	/**
	 * Registration Settings
	 *
	 * @return string
	 */
	protected function _registration()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'registration_settings' );
		
		/* Build Form */
		$form = new \IPS\Helpers\Form();
		$form->addHeader('registration_standard_settings');
		$form->addMessage('registration_standard_settings_blurb');
		$form->add( new \IPS\Helpers\Form\Radio( 'allow_reg', \IPS\Login::registrationType(), FALSE, array(
			'options' 	=>  array(
				'normal'	=> 'allow_reg_normal',
				'full'		=> 'allow_reg_full',
				'redirect'	=> 'allow_reg_redirect',
				'disabled'	=> 'allow_reg_disabled'
			),
			'toggles'	=> array(
				'normal'    => array( 'allow_reg_normal_warning' ),
				'full'		=> array( 'minimum_age', 'use_coppa' ),
				'redirect'	=> array( 'allow_reg_target' )
			),
			'disabled'	=> \IPS\Login\Handler::findMethod( 'IPS\Login\Handler\Standard' ) ? array() : array( 'normal', 'full' )
		) ) );
	
		/* Do we have required custom fields? */
		if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_pfields_data', array( 'pf_not_null != 0 and pf_show_on_reg=1' ) )->first() )
		{
			\IPS\Member::loggedIn()->language()->words['allow_reg_normal_warning'] = \IPS\Member::loggedIn()->language()->addToStack( 'allow_reg_normal_desc_required_warning' );
		}
		
		$form->add( new \IPS\Helpers\Form\Url( 'allow_reg_target', \IPS\Settings::i()->allow_reg_target, NULL, array(), function( $val )
		{
			if ( !$val and \IPS\Request::i()->allow_red === 'redirect' )
			{
				throw new \DomainException('form_required');
			}
		}, NULL, NULL, 'allow_reg_target' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'minimum_age', \IPS\Settings::i()->minimum_age, FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'any_age' ), NULL, NULL, NULL, 'minimum_age' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'use_coppa', \IPS\Settings::i()->use_coppa, FALSE, array( 'togglesOn' => array( 'coppa_fax', 'coppa_address' ) ), NULL, NULL, NULL, 'use_coppa' ) );
		$form->add( new \IPS\Helpers\Form\Tel( 'coppa_fax', \IPS\Settings::i()->coppa_fax, FALSE, array(), NULL, NULL, NULL, 'coppa_fax' ) );
		$form->add( new \IPS\Helpers\Form\Address( 'coppa_address', \IPS\GeoLocation::buildFromJson( \IPS\Settings::i()->coppa_address ), FALSE, array(), NULL, NULL, NULL, 'coppa_address' ) );
		$form->addHeader('registration_global_settings');
		$form->addMessage('registration_global_settings_blurb');
		$form->add( new \IPS\Helpers\Form\Radio( 'reg_auth_type', \IPS\Settings::i()->reg_auth_type, FALSE, array(
			'options'	=> array( 'user' => 'reg_auth_type_user', 'admin' => 'reg_auth_type_admin', 'admin_user' => 'reg_auth_type_admin_user', 'none' => 'reg_auth_type_none' ),
			'toggles'	=> array( 'user' => array( 'validate_day_prune' ), 'admin_user' => array( 'validate_day_prune' ) )
		), NULL, NULL, NULL, 'reg_auth_type' ) );
		$form->add( new \IPS\Helpers\Form\Interval( 'validate_day_prune', \IPS\Settings::i()->validate_day_prune, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'validate_day_prune' ) );
		$form->add( new \IPS\Helpers\Form\Stack( 'allowed_reg_email', explode( ',', \IPS\Settings::i()->allowed_reg_email ), FALSE, array( 'placeholder' => 'mycompany.com' ), function( $value ) {
			if( isset( $value[0] ) AND mb_stripos( $value[0], '@' ) )
			{
				throw new \DomainException( 'allowed_reg_email_email_detected' );
			}
		}, NULL, NULL ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'force_reg_terms', \IPS\Settings::i()->force_reg_terms, FALSE, array( 'options' => array(
			'0'	=> 'force_reg_terms_0',
			'1'	=> 'force_reg_terms_1',
		) ) ) );

		/* Save */
		if ( $values = $form->values() )
		{
			/* Save */
			if ( isset( $values['allowed_reg_email'] ) and \is_array( $values['allowed_reg_email'] ) )
			{
				$values['allowed_reg_email'] = implode( ',', $values['allowed_reg_email'] );
			}
			
			if ( isset( $values['coppa_address'] ) AND ( $values['coppa_address'] instanceof \IPS\GeoLocation ) )
			{
				$values['coppa_address'] = json_encode( $values['coppa_address'] );
			}
			
			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplogs__registration_settings' );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();
			
			/* Redirect */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=login&tab=registration' ), 'saved' );
		}
		
		/* Display */
		return (string) $form;
	}
	
	/**
	 * Account Management Settings
	 *
	 * @return string
	 */
	protected function _accountsettings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'login_account_management' );
		
		/* Build Form */
		$form = new \IPS\Helpers\Form();
		$form->addHeader( 'security_header_accounts' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'password_strength_meter', \IPS\Settings::i()->password_strength_meter, FALSE, array( 'togglesOn' => array( 'password_strength_meter_enforce' ) ), NULL, NULL, NULL, 'password_strength_meter' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'password_strength_meter_enforce', \IPS\Settings::i()->password_strength_meter_enforce, FALSE, array( 'togglesOn' => array( 'password_strength_option' ) ), NULL, NULL, NULL, 'password_strength_meter_enforce' ) );
		$strengthOptions = array(
			'3' => 'strength_3',
			'4' => 'strength_4',
			'5' => 'strength_5',
		);
		$form->add( new \IPS\Helpers\Form\Radio( 'password_strength_option', \IPS\Settings::i()->password_strength_option, FALSE, array( 'options' => $strengthOptions ), NULL, NULL, NULL, 'password_strength_option' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'device_management', \IPS\Settings::i()->device_management, FALSE ) );
		$form->addHeader('account_management_email_pass');
		$form->addMessage('account_management_email_pass_blurb');
		$form->add( new \IPS\Helpers\Form\Radio( 'allow_email_changes', \IPS\Settings::i()->allow_email_changes, FALSE, array(
			'options'	 => array(
				'normal'	=> 'allow_email_changes_normal',
				'redirect'	=> 'allow_email_changes_redirect',
				'disabled'	=> 'allow_email_changes_disabled',
			),
			'toggles'	=> array(
				'redirect'	=> array( 'allow_email_changes_target' )
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\Url( 'allow_email_changes_target', \IPS\Settings::i()->allow_email_changes_target, NULL, array(), function( $val )
		{
			if ( !$val and \IPS\Request::i()->allow_email_changes === 'redirect' )
			{
				throw new \DomainException('form_required');
			}
		}, NULL, NULL, 'allow_email_changes_target' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'allow_password_changes', \IPS\Settings::i()->allow_password_changes, FALSE, array(
			'options'	 => array(
				'normal'	=> 'allow_password_changes_normal',
				'redirect'	=> 'allow_password_changes_redirect',
				'disabled'	=> 'allow_password_changes_disabled',
			),
			'toggles'	=> array(
				'redirect'	=> array( 'allow_password_changes_target' )
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\Url( 'allow_password_changes_target', \IPS\Settings::i()->allow_password_changes_target, NULL, array(), function( $val )
		{
			if ( !$val and \IPS\Request::i()->allow_password_changes === 'redirect' )
			{
				throw new \DomainException('form_required');
			}
		}, NULL, NULL, 'allow_password_changes_target' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'allow_forgot_password', \IPS\Settings::i()->allow_forgot_password, FALSE, array(
			'options'	 => array(
				'normal'	=> 'allow_forgot_password_normal',
				'handler'	=> 'allow_forgot_password_handler',
				'redirect'	=> 'allow_forgot_password_redirect',
				'disabled'	=> 'allow_forgot_password_disabled',
			),
			'toggles'	=> array(
				'redirect'	=> array( 'allow_forgot_password_target' )
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\Url( 'allow_forgot_password_target', \IPS\Settings::i()->allow_forgot_password_target, NULL, array(), function( $val )
		{
			if ( !$val and \IPS\Request::i()->allow_forgot_password === 'redirect' )
			{
				throw new \DomainException('form_required');
			}
		}, NULL, NULL, 'allow_forgot_password_target' ) );

		/* Save */
		if ( $values = $form->values() )
		{
			$values['password_strength_meter_enforce'] = $values['password_strength_meter'] ? $values['password_strength_meter_enforce'] : FALSE;
			
			$form->saveAsSettings( $values );

			\IPS\Session::i()->log( 'acplogs__account_management_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=login&tab=accountsettings' ), 'saved' );
		}
		
		/* Display */
		return (string) $form;
	}
	
	/**
	 * Force all users to be logged out
	 *
	 * @return	void
	 */
	protected function forceLogout()
	{
		\IPS\Session::i()->csrfCheck();
		
		\IPS\Db::i()->update( 'core_members_known_devices', array( 'login_key' => NULL ) );
		\IPS\Db::i()->delete( 'core_sessions' );

		\IPS\Session::i()->log( 'acplogs__logout_force' );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=login' ), 'logged_out_force' );
	}
}