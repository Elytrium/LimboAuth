<?php
/**
 * @brief		Multi-Factor Authentication
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		30 Nov 2016
 */

namespace IPS\core\modules\admin\settings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Multi-Factor Authentication
 */
class _mfa extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'mfa_manage' );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$activeTabContents = '';
		$tabs = array(
			'handlers' 	=> 'mfa_handlers',
			'settings'	=> 'mfa_settings'
		);
		$activeTab = ( isset( \IPS\Request::i()->tab ) and array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'handlers';
		
		if ( $activeTab === 'handlers' )
		{
			$activeTabContents = $this->_manageHandlers();
		}
		else
		{
			$activeTabContents = $this->_manageSettings();
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_settings_mfa');
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->tabs( $tabs, $activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=core&module=settings&controller=mfa" ) );
		}
	}
	
	/**
	 * Manage Handlers
	 *
	 * @return	string
	 */
	protected function _manageHandlers()
	{
		/* Create the tree */
		$url = \IPS\Http\Url::internal( "app=core&module=settings&controller=mfa&tab=handlers" );
		$tree = new \IPS\Helpers\Tree\Tree(
			$url,
			NULL,
			function() use( $url ) {
				$return = array();
				
				foreach ( \IPS\MFA\MFAHandler::handlers() as $key => $handler )
				{
					$return[] = \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row(
						$url,
						$key,
						\IPS\Member::loggedIn()->language()->addToStack("mfa_{$key}_title"),
						FALSE,
						array(
							'settings' => array(
								'icon'	=> 'cog',
								'title'	=> 'settings',
								'link'	=> $url->setQueryString( array( 'do' => 'settings', 'key' => $key ) ),
							)
						),
						\IPS\Member::loggedIn()->language()->addToStack("mfa_{$key}_desc"),
						NULL,
						NULL,
						FALSE,
						$handler->isEnabled()
					);
				}
				
				return $return;
			},
			NULL,
			NULL,
			NULL
		);
		
		/* Return */
		return \IPS\Theme::i()->getTemplate( 'forms' )->blurb( 'mfa_blurb', TRUE, TRUE ) . $tree;
	}
	
	/**
	 * Enable/Disable Toggle
	 *
	 * @return	void
	 */
	protected function enableToggle()
	{
		\IPS\Session::i()->csrfCheck();
		
		$key = \IPS\Request::i()->id;
		$handlers = \IPS\MFA\MFAHandler::handlers();
		if ( !isset( $handlers[ $key ] ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C345/1', 404, '' );
		}
		
		try
		{
			$handlers[ $key ]->toggle( \IPS\Request::i()->status );
			
			if ( \IPS\Request::i()->status )
			{
				\IPS\Session::i()->log( 'acplogs__mfa_handler_enabled', array( "mfa_{$key}_title" => TRUE ) );
			}
			else
			{
				\IPS\Session::i()->log( 'acplogs__mfa_handler_disabled', array( "mfa_{$key}_title" => TRUE ) );
			}
			
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json('OK');
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=settings&controller=mfa" ) );
			}
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=settings&controller=mfa&tab=handlers&do=settings&key=" . $key ) );
		}
	}
	
	/**
	 * Handler Settings
	 *
	 * @return	void
	 */
	protected function settings()
	{
		$key = \IPS\Request::i()->key;
		$handlers = \IPS\MFA\MFAHandler::handlers();
		if ( !isset( $handlers[ $key ] ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C345/2', 404, '' );
		}
		
		$output = $handlers[ $key ]->acpSettings();
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack("mfa_{$key}_title");
		\IPS\Output::i()->output = $output;
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal('app=core&module=settings&controller=mfa&tab=handlers'), \IPS\Member::loggedIn()->language()->addToStack('menu__core_settings_mfa') );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack("mfa_{$key}_title") );
	}
	
	/**
	 * Manage Settings
	 *
	 * @return	string
	 */
	protected function _manageSettings()
	{
		$form = new \IPS\Helpers\Form;
		
		$form->addHeader('mfa_header_setup');
		$groups = array_combine( array_keys( \IPS\Member\Group::groups() ), array_map( function( $_group ) { return (string) $_group; }, \IPS\Member\Group::groups() ) );
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'mfa_required_groups', \IPS\Settings::i()->mfa_required_groups == '*' ? '*' : explode( ',', \IPS\Settings::i()->mfa_required_groups ), FALSE, array(
			'multiple'			=> TRUE,
			'options'			=> $groups,
			'unlimited'			=> '*',
			'unlimitedLang'		=> 'everyone',
			'impliedUnlimited'	=> TRUE
		) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'mfa_required_prompt', \IPS\Settings::i()->mfa_required_prompt, FALSE, array(
			'options'	=> array(
				'immediate'	=> 'mfa_prompt_immediate',
				'access'	=> 'mfa_prompt_access',
			)
		), NULL, NULL, NULL, 'mfa_required_prompt' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'mfa_optional_prompt', \IPS\Settings::i()->mfa_optional_prompt, FALSE, array(
			'options'	=> array(
				'immediate'	=> 'mfa_prompt_immediate',
				'access'	=> 'mfa_prompt_access',
				'none'		=> 'mfa_prompt_none',
			),
			'toggles'	=> array(
				'immediate'	=> array( 'security_questions_opt_out_warning' ),
				'access'	=> array( 'security_questions_opt_out_warning' ),
			)
		), NULL, NULL, NULL, 'mfa_optional_prompt' ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'security_questions_opt_out_warning', NULL, FALSE, array( 'app' => 'core', 'key' => 'security_questions_opt_out_warning_value' ), NULL, NULL, NULL, 'security_questions_opt_out_warning' ) );

		$form->addHeader('mfa_header_authentication');
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'security_questions_areas', \IPS\Settings::i()->security_questions_areas ? explode( ',', \IPS\Settings::i()->security_questions_areas ) : array_keys( \IPS\MFA\MFAHandler::areas() ), FALSE, array( 'options' => \IPS\MFA\MFAHandler::areas() ), NULL, NULL, NULL, 'security_questions_areas' ) );
		$form->add( new \IPS\Helpers\Form\Interval( 'security_questions_timer', \IPS\Settings::i()->security_questions_timer, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::MINUTES, 'unlimited' => 0, 'unlimitedLang' => 'security_questions_timer_session' ) ) );

		$form->addHeader('mfa_header_recovery');
		$form->add( new \IPS\Helpers\Form\Number( 'security_questions_tries', \IPS\Settings::i()->security_questions_tries, FALSE, array( 'min' => 1 ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'mfa_lockout_behaviour', \IPS\Settings::i()->mfa_lockout_behaviour, FALSE, array(
			'options'	=> array(
				'lock'		=> 'mfa_lockout_behaviour_lock',
				'email'		=> 'mfa_lockout_behaviour_email',
				'contact'	=> 'mfa_lockout_behaviour_contact',
			),
			'toggles'	=> array(
				'lock'		=> array( 'mfa_lockout_time' )
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\Interval( 'mfa_lockout_time', \IPS\Settings::i()->mfa_lockout_time, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::MINUTES, 'min' => 1 ), NULL, NULL, NULL, 'mfa_lockout_time' ) );
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'mfa_forgot_behaviour', explode( ',', \IPS\Settings::i()->mfa_forgot_behaviour ), FALSE, array(
			'options' => array(
				'email'		=> 'mfa_forgot_behaviour_email',
				'contact'	=> 'mfa_forgot_behaviour_contact',
			)
		) ) );

		
		if ( $values = $form->values() )
		{
			\IPS\Lang::saveCustom( 'core', 'security_questions_opt_out_warning_value', $values['security_questions_opt_out_warning'] );
			unset( $values['security_questions_opt_out_warning'] );
			
			$values['mfa_required_groups'] = ( $values['mfa_required_groups'] == '*' ) ? '*' : implode( ',', $values['mfa_required_groups'] );
			$values['mfa_forgot_behaviour'] = implode( ',', $values['mfa_forgot_behaviour'] );
			$values['security_questions_areas'] = implode( ',', $values['security_questions_areas'] );
			
			$form->saveAsSettings( $values );			
			
			\IPS\Session::i()->log( 'acplogs__mfa_settings_updated' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=mfa&tab=settings' ), 'saved' );
		}
		
		return (string) $form;
	}

	/**
	 * Reset security answers
	 *
	 * @return	void
	 */
	public function resetSecurityAnswers()
	{
		\IPS\Session::i()->csrfCheck();

		\IPS\Db::i()->delete( 'core_security_answers' );
		\IPS\Db::i()->update( 'core_members', "members_bitoptions2=members_bitoptions2 &~ 512" );

		/* Log MFA reset */
		\IPS\Session::i()->log( 'acplogs__mfa_questions_reset' );

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=settings&controller=mfa&tab=handlers&do=settings&key=questions" ), 'acplogs__mfa_questions_reset' );
	}
}