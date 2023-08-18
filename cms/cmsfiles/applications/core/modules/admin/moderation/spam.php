<?php
/**
 * @brief		Spam Prevention Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Apr 2013
 */

namespace IPS\core\modules\admin\moderation;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Spam Prevention Settings
 */
class _spam extends \IPS\Dispatcher\Controller
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
		/* Get tab content */
		$this->activeTab = \IPS\Request::i()->tab ?: 'captcha';

		\IPS\Dispatcher::i()->checkAcpPermission( 'spam_manage' );
		parent::execute();
	}

	/**
	 * Manage Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Work out output */
		$methodFunction = '_manage' . mb_ucfirst( $this->activeTab );
		$activeTabContents = $this->$methodFunction();
		
		/* If this is an AJAX request, just return it */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}
		
		/* Build tab list */
		$tabs = array();
		$tabs['captcha']	= 'spamprevention_captcha';
		$tabs['flagging']	= 'spamprevention_flagging';
		$tabs['service']	= 'enhancements__core_SpamMonitoring';

		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'moderation', 'qanda_manage' ) and \in_array( \IPS\Login::registrationType(), array( 'normal', 'full' ) ) )
		{
			$tabs['qanda']		= 'qanda_settings';
		}
				
		/* Add a button for logs */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'moderation', 'spam_service_log' ) )
		{
			\IPS\Output::i()->sidebar['actions']['errorLog'] = array(
					'title'		=> 'spamlogs',
					'icon'		=> 'exclamation-triangle',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&do=serviceLogs' ),
			);
		}

		/* Add a button for whitelist */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'moderation', 'spam_whitelist_manage' ) )
		{
			\IPS\Output::i()->sidebar['actions']['whitelist'] = array(
					'title'		=> 'spam_whitelist',
					'icon'		=> 'shield',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&do=whitelist' ),
			);
		}
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'membertools_delete' ) )
		{
			\IPS\Output::i()->sidebar['actions']['delete_guest_content'] = array(
					'title'		=> 'member_delete_guest_content',
					'icon'		=> 'trash-o',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=deleteGuestContent' ),
					'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('member_delete_guest_content') )
			);
		}
			
		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_moderation_spam');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global' )->tabs( $tabs, $this->activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=core&module=moderation&controller=spam" ) );
	}

	/**
	 * Return the CAPTCHA options - abstracted for third parties
	 *
	 * @param	string	$type	'options' for the select options, 'toggles' for the toggles
	 * @return	array
	 */
	protected function getCaptchaOptions( $type='options' )
	{
		switch( $type )
		{
			case 'options':
				return array( 'none' => 'captcha_type_none', 'invisible' => 'captcha_type_invisible', 'recaptcha2' => 'captcha_type_recaptcha2', 'keycaptcha' => 'captcha_type_keycaptcha', 'hcaptcha' => 	'captcha_type_hcaptcha' );
				break;

			case 'toggles':
				return array(
					'none'			=> array( 'bot_antispam_type_warning' ),
					'recaptcha2'	=> array( 'guest_captcha', 'recaptcha2_public_key', 'recaptcha2_private_key' ),
					'invisible'		=> array( 'guest_captcha', 'recaptcha2_public_key', 'recaptcha2_private_key' ),
					'keycaptcha'	=> array( 'guest_captcha', 'keycaptcha_privatekey' ),
					'hcaptcha'		=> array( 'guest_captcha', 'hcaptcha_sitekey', 'hcaptcha_secret')
				);
				break;
		}

		return array();
	}

	/**
	 * Show CAPTCHA settings
	 *
	 * @return	string	HTML to display
	 */
	protected function _manageCaptcha()
	{
		/* Build Form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Radio( 'bot_antispam_type', \IPS\Settings::i()->bot_antispam_type, TRUE, array(
			'options'	=> $this->getCaptchaOptions( 'options' ),
			'toggles'	=> $this->getCaptchaOptions( 'toggles' ),
		), NULL, NULL, NULL, 'bot_antispam_type' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'recaptcha2_public_key', \IPS\Settings::i()->recaptcha2_public_key, FALSE, array(), NULL, NULL, NULL, 'recaptcha2_public_key' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'recaptcha2_private_key', \IPS\Settings::i()->recaptcha2_private_key, FALSE, array(), NULL, NULL, NULL, 'recaptcha2_private_key' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'keycaptcha_privatekey', \IPS\Settings::i()->keycaptcha_privatekey, FALSE, array(), NULL, NULL, NULL, 'keycaptcha_privatekey' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'hcaptcha_sitekey', \IPS\Settings::i()->hcaptcha_sitekey, FALSE, array(), NULL, NULL, NULL, 'hcaptcha_sitekey' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'hcaptcha_secret', \IPS\Settings::i()->hcaptcha_secret, FALSE, array(), NULL, NULL, NULL, 'hcaptcha_secret' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'guest_captcha', \IPS\Settings::i()->guest_captcha, FALSE, array(), NULL, NULL, NULL, 'guest_captcha' ) );

		/* Save values */
		if ( $form->values() )
		{
			$form->saveAsSettings();

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			\IPS\Session::i()->log( 'acplogs__spamprev_settings' );
		}

		return $form;
	}

	/**
	 * Show spammer flagging settings
	 *
	 * @return	string	HTML to display
	 */
	protected function _manageFlagging()
	{
		/* Build Form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'spm_option', explode( ',', \IPS\Settings::i()->spm_option ), FALSE, array(
			'options' 	=> array( 'disable' => 'spm_option_disable', 'unapprove' => 'spm_option_unapprove', 'delete' => 'spm_option_delete', 'ban' => 'spm_option_ban' ),
		) ) );
		
		/* Save values */
		if ( $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplogs__spamprev_settings' );
		}

		return $form;
	}

	/**
	 * Show IPS Spam Service settings
	 *
	 * @return	string	HTML to display
	 */
	protected function _manageService()
	{
		$licenseData = \IPS\IPS::licenseKey();
		
		/* Build Form */
		$actions = array( 1 => 'spam_service_act_1', 5 => 'spam_service_act_5', 2 => 'spam_service_act_2', 3 => 'spam_service_act_3', 4 => 'spam_service_act_4' );
		$days = json_decode( \IPS\Settings::i()->spam_service_days, TRUE );

		$form = new \IPS\Helpers\Form;
		$form->addHeader( 'enhancements__core_SpamMonitoring' );

		$disabled = FALSE;
		if( !$licenseData or !isset( $licenseData['products']['spam'] ) or !$licenseData['products']['spam'] or ( !$licenseData['cloud'] AND strtotime( $licenseData['expires'] ) < time() ) )
		{
			$disabled = TRUE;
			if( !\IPS\Settings::i()->ipb_reg_number )
			{
				\IPS\Member::loggedIn()->language()->words['spam_service_enabled_desc'] = \IPS\Member::loggedIn()->language()->addToStack( '__ipbmafia__nulling_null_alert' ); //Changing error message to custom
			}
			else
			{
				\IPS\Member::loggedIn()->language()->words['spam_service_enabled_desc'] = \IPS\Member::loggedIn()->language()->addToStack( '__ipbmafia__nulling_null_alert' ); //Changing error message to custom
			}
		}
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'spam_service_enabled', 0, FALSE, array( 'disabled' => $disabled, 'togglesOn' => array( 'spam_service_send_to_ips', 'spam_service_action_0', 'spam_service_action_1', 'spam_service_action_2', 'spam_service_action_3', 'spam_service_action_4' ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'spam_service_send_to_ips', 0, FALSE, array( 'disabled' => $disabled ), NULL, NULL, NULL, 'spam_service_send_to_ips' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'spam_service_action_1', \IPS\Settings::i()->spam_service_action_1, FALSE, array( 'disabled' => $disabled, 'options' => $actions, 'toggles' => array( '5' => array( 'spam_service_action_1_num' ) ) ), NULL, NULL, NULL, 'spam_service_action_1' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'spam_service_action_1_num', ( isset( $days[1] ) ? $days[1] : -1 ), FALSE, array( 'unlimited' => -1, 'unlimitedLang' => 'spam_service_action_unlimited_days'), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('days'), 'spam_service_action_1_num' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'spam_service_action_2', \IPS\Settings::i()->spam_service_action_2, FALSE, array( 'disabled' => $disabled, 'options' => $actions, 'toggles' => array( '5' => array( 'spam_service_action_2_num' ) ) ), NULL, NULL, NULL, 'spam_service_action_2' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'spam_service_action_2_num', ( isset( $days[2] ) ? $days[2] : -1 ), FALSE, array( 'unlimited' => -1, 'unlimitedLang' => 'spam_service_action_unlimited_days'), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('days'), 'spam_service_action_2_num' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'spam_service_action_3', \IPS\Settings::i()->spam_service_action_3, FALSE, array( 'disabled' => $disabled, 'options' => $actions, 'toggles' => array( '5' => array( 'spam_service_action_3_num' ) ) ), NULL, NULL, NULL, 'spam_service_action_3' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'spam_service_action_3_num', ( isset( $days[3] ) ? $days[3] : -1 ), FALSE, array( 'unlimited' => -1, 'unlimitedLang' => 'spam_service_action_unlimited_days'), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('days'), 'spam_service_action_3_num' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'spam_service_action_4', \IPS\Settings::i()->spam_service_action_4, FALSE, array( 'disabled' => $disabled, 'options' => $actions, 'toggles' => array( '5' => array( 'spam_service_action_4_num' ) ) ), NULL, NULL, NULL, 'spam_service_action_4' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'spam_service_action_4_num', ( isset( $days[4] ) ? $days[4] : -1 ), FALSE, array( 'unlimited' => -1, 'unlimitedLang' => 'spam_service_action_unlimited_days'), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('days'), 'spam_service_action_4_num' ) );

		$form->add( new \IPS\Helpers\Form\Select( 'spam_service_action_0', \IPS\Settings::i()->spam_service_action_0, FALSE, array( 'disabled' => $disabled, 'options' => $actions ), NULL, NULL, NULL, 'spam_service_action_0' ) );

		if ( $values = $form->values() )
		{
			$values['spam_service_days'] = array();
			foreach( array( 1, 2, 3, 4 ) as $num )
			{
				if ( $values['spam_service_action_' . $num ] == 5 )
				{
					$values['spam_service_days'][ $num ] = $values['spam_service_action_' . $num . '_num' ];
				}

				unset( $values['spam_service_action_' . $num . '_num' ] );
			}
			
			$values['spam_service_days'] = json_encode( $values['spam_service_days'] );

			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplog__enhancements_edited', array( 'enhancements__core_SpamMonitoring' => TRUE ) );
		}

		return $form;
	}

	/**
	 * Show question and answer challenge settings
	 *
	 * @return	string	HTML to display
	 */
	protected function _manageQanda()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'qanda_manage' );

		/* Create the table */
		$table					= new \IPS\Helpers\Table\Db( 'core_question_and_answer', \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&tab=qanda' ) );
		$table->include			= array( 'qa_question' );
		$table->joins			= array(
										array( 'select' => 'w.word_custom', 'from' => array( 'core_sys_lang_words', 'w' ), 'where' => "w.word_key=CONCAT( 'core_question_and_answer_', core_question_and_answer.qa_id ) AND w.lang_id=" . \IPS\Member::loggedIn()->language()->id )
									);
		$table->parsers			= array(
										'qa_question'		=> function( $val, $row )
										{
											return ( $row['word_custom'] ? $row['word_custom'] : $row['qa_question'] );
										}
									);
		$table->mainColumn		= 'qa_question';
		$table->sortBy			= $table->sortBy ?: 'qa_question';
		$table->quickSearch		= array( 'word_custom', 'qa_question' );
		$table->sortDirection	= $table->sortDirection ?: 'asc';
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'moderation', 'qanda_add' ) )
		{
			$table->rootButtons	= array(
				'add'	=> array(
					'icon'		=> 'plus',
					'title'		=> 'qanda_add_question',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&do=question' ),
				)
			);
		}

		$table->rowButtons		= function( $row )
		{
			$return	= array();
			
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'moderation', 'qanda_edit' ) )
			{
				$return['edit'] = array(
					'icon'		=> 'pencil',
					'title'		=> 'edit',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&do=question&id=' ) . $row['qa_id'],
				);
			}
			
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'moderation', 'qanda_delete' ) )
			{
				$return['delete'] = array(
					'icon'		=> 'times-circle',
					'title'		=> 'delete',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&do=delete&id=' ) . $row['qa_id'],
					'data'		=> array( 'delete' => '' ),
				);
			}
			
			return $return;
		};

		return (string) $table;
	}

	/**
	 * Add/Edit Form
	 *
	 * @return void
	 */
	protected function question()
	{
		/* Init */
		$id			= 0;
		$question	= array();

		/* Start the form */
		$form	= new \IPS\Helpers\Form;

		/* Load question */
		try
		{
			$id	= \intval( \IPS\Request::i()->id );
			$form->hiddenValues['id'] = $id;
			$question	= \IPS\Db::i()->select( '*', 'core_question_and_answer', array( 'qa_id=?', $id ) )->first();

			\IPS\Dispatcher::i()->checkAcpPermission( 'qanda_edit' );
		}
		catch ( \UnderflowException $e )
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'qanda_add' );
		}

		$form->add( new \IPS\Helpers\Form\Translatable( 'qa_question', NULL, TRUE, array( 'app' => 'core', 'key' => ( $id ? "core_question_and_answer_{$id}" : NULL ) ) ) );
		$form->add( new \IPS\Helpers\Form\Stack( 'qa_answers', $id ? json_decode( $question['qa_answers'], TRUE ) : array(), TRUE ) );

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$save = array(
				'qa_answers'	=> json_encode( $values['qa_answers'] ),
			);
			
			if ( $id )
			{
				\IPS\Db::i()->update( 'core_question_and_answer', $save, array( 'qa_id=?', $question['qa_id'] ) );

				\IPS\Session::i()->log( 'acplogs__question_edited' );
			}
			else
			{
				$id	= \IPS\Db::i()->insert( 'core_question_and_answer', $save );
				\IPS\Session::i()->log( 'acplogs__question_added' );
			}
				
			\IPS\Lang::saveCustom( 'core', "core_question_and_answer_{$id}", $values['qa_question'] );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&tab=qanda' ), 'saved' );
		}

		/* Display */
		\IPS\Output::i()->title	 		= \IPS\Member::loggedIn()->language()->addToStack('qanda_settings');
		\IPS\Output::i()->breadcrumb[]	= array( NULL, \IPS\Output::i()->title );
		\IPS\Output::i()->output 		= \IPS\Theme::i()->getTemplate( 'global' )->block( \IPS\Output::i()->title, $form );
	}

	/**
	 * Delete
	 *
	 * @return void
	 */
	protected function delete()
	{
		$id = \intval( \IPS\Request::i()->id ); 
		\IPS\Dispatcher::i()->checkAcpPermission( 'qanda_delete' );

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		\IPS\Db::i()->delete( 'core_question_and_answer', array( 'qa_id=?', $id ) );
		\IPS\Session::i()->log( 'acplogs__question_deleted' );
		
		\IPS\Lang::deleteCustom( 'core', "core_question_and_answer_{$id}" );

		/* And redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=moderation&controller=spam&tab=qanda" ) );
	}
	
	/**
	 * Spam Service Log
	 *
	 * @return	void
	 */
	protected function serviceLogs()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'spam_service_log' );
		
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_spam_service_log', \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&do=serviceLogs' ) );
	
		$table->langPrefix = 'spamlogs_';
	
		/* Columns we need */
		$table->include = array( 'log_date', 'log_code', 'email_address', 'ip_address' );
	
		$table->sortBy	= $table->sortBy ?: 'log_date';
		$table->sortDirection	= $table->sortDirection ?: 'DESC';
	
		/* Search */
		$table->advancedSearch = array(
				'email_address'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
				'ip_address'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
				'log_code'			=> \IPS\Helpers\Table\SEARCH_NUMERIC,
		);

		$table->quickSearch = 'email_address';
	
		/* Custom parsers */
		$table->parsers = array(
				'log_date'				=> function( $val, $row )
				{
					return \IPS\DateTime::ts( $val )->localeDate();
				},
		);
	
		/* Add a button for settings */
		\IPS\Output::i()->sidebar['actions'] = array(
				'settings'	=> array(
						'title'		=> 'prunesettings',
						'icon'		=> 'cog',
						'link'		=> \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&do=serviceLogSettings' ),
						'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('prunesettings') )
				),
		);
	
		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('spamlogs');
		\IPS\Output::i()->output	= (string) $table;
	}
	
	/**
	 * Prune Settings
	 *
	 * @return	void
	 */
	protected function serviceLogSettings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'spam_service_log' );
		
		$form = new \IPS\Helpers\Form;
	
		$form->add( new \IPS\Helpers\Form\Interval( 'prune_log_spam', \IPS\Settings::i()->prune_log_spam, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'prune_log_spam' ) );
	
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplog__spamlog_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&do=serviceLogs' ), 'saved' );
		}
	
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('spamlogssettings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'spamlogssettings', $form, FALSE );
	}

	/**
	 * Spam defense whitelist
	 *
	 * @return	void
	 */
	protected function whitelist()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'spam_whitelist_manage' );
		$table = new \IPS\Helpers\Table\Db( 'core_spam_whitelist', \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&do=whitelist' ) );

		$table->filters = array(
				'spam_whitelist_ip'		=> 'whitelist_type=\'ip\'',
				'spam_whitelist_domain'	=> 'whitelist_type=\'domain\''
		);

		$table->include    = array( 'whitelist_type', 'whitelist_content', 'whitelist_reason', 'whitelist_date' );
		$table->mainColumn = 'whiteist_content';
		$table->rowClasses = array( 'whitelist_reason' => array( 'ipsTable_wrap' ) );

		$table->sortBy        = $table->sortBy        ?: 'whitelist_date';
		$table->sortDirection = $table->sortDirection ?: 'asc';
		$table->quickSearch   = 'whitelist_content';
		$table->advancedSearch = array(
			'whitelist_reason'	=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'whitelist_date'	=> \IPS\Helpers\Table\SEARCH_DATE_RANGE
		);

		/* Custom parsers */
		$table->parsers = array(
				'whitelist_date'			=> function( $val, $row )
				{
					return \IPS\DateTime::ts( $val )->localeDate();
				},
				'whitelist_type'			=> function( $val, $row )
				{
					switch( $val )
					{
						default:
						case 'ip':
							return \IPS\Member::loggedIn()->language()->addToStack('spam_whitelist_ip_select');
						break;
						case 'domain':
							return \IPS\Member::loggedIn()->language()->addToStack('spam_whitelist_domain_select');
						break;
					}
				}
		);

		/* Row buttons */
		$table->rowButtons = function( $row )
		{
			$return = array();

			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'moderation', 'spam_whitelist_edit' ) )
			{
				$return['edit'] = array(
							'icon'		=> 'pencil',
							'title'		=> 'edit',
							'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit') ),
							'link'		=> \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&do=whitelistForm&id=' ) . $row['whitelist_id'],
				);
			}

			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'moderation', 'spam_whitelist_delete' ) )
			{
				$return['delete'] = array(
							'icon'		=> 'times-circle',
							'title'		=> 'delete',
							'link'		=> \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&do=whitelistDelete&id=' ) . $row['whitelist_id'],
							'data'		=> array( 'delete' => '' ),
				);
			}

			return $return;
		};

		/* Add an add button for whitelist */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'moderation', 'spam_whitelist_add' ) )
		{
			\IPS\Output::i()->sidebar['actions'] = array(
				'add'	=> array(
					'primary'	=> TRUE,
					'icon'		=> 'plus',
					'title'		=> 'spam_whitelist_add',
					'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('spam_whitelist_add') ),
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&do=whitelistForm' )
				)
			);
		}

        /* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('spam_whitelist');
		\IPS\Output::i()->output	= (string) $table;
	}

	/**
	 * Whitelist add/edit form
	 *
	 * @return	void
	 */
	protected function whitelistForm()
	{
		$current = NULL;
		if ( \IPS\Request::i()->id )
		{
			$current = \IPS\Db::i()->select( '*', 'core_spam_whitelist', array( 'whitelist_id=?', \IPS\Request::i()->id ) )->first();

			\IPS\Dispatcher::i()->checkAcpPermission( 'spam_whitelist_edit' );
		}
		else
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'spam_whitelist_add' );
		}

		/* Build form */
		$form = new \IPS\Helpers\Form();
		$form->add( new \IPS\Helpers\Form\Select( 'whitelist_type', $current ? $current['whitelist_type'] : NULL, TRUE,
				array(
					'options' => array(
						'ip'    => 'spam_whitelist_ip_select',
						'domain' => 'spam_whitelist_domain_select'
					),
					'toggles' => array(
						'ip' => array( 'whitelist_ip_content' ),
						'domain' => array( 'whitelist_domain_content' )
					)
			) ) );

		$form->add( new \IPS\Helpers\Form\Text( 'whitelist_ip_content', $current ? $current['whitelist_content'] : NULL, TRUE, array(), NULL, NULL, NULL, 'whitelist_ip_content' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'whitelist_domain_content', $current ? $current['whitelist_content'] : NULL, TRUE, array( 'placeholder' => 'mycompany.com' ), function( $value ) {
			if( isset( $value ) AND mb_stripos( $value, '@' ) )
			{
				throw new \DomainException( 'whitelist_domain_email_detected' );
			}
		}, NULL, NULL, 'whitelist_domain_content' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'whitelist_reason', $current ? $current['whitelist_reason'] : NULL ) );

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$whitelistContent = $values['whitelist_type'] == 'ip' ? $values['whitelist_ip_content'] : $values['whitelist_domain_content'];
			$save = array(
				'whitelist_type'    => $values['whitelist_type'],
				'whitelist_content' => $whitelistContent,
				'whitelist_reason'  => $values['whitelist_reason'],
				'whitelist_date'	=> time()
			);

			if ( $current )
			{
				unset( $save['whitelist_date'] );
				\IPS\Db::i()->update( 'core_spam_whitelist', $save, array( 'whitelist_id=?', $current['whitelist_id'] ) );
				\IPS\Session::i()->log( 'acplog__spam_whitelist_edited', array( 'spam_whitelist_' . $save['whitelist_type'] . '_select' => TRUE, $save['whitelist_content'] => FALSE ) );
			}
			else
			{
				\IPS\Db::i()->insert( 'core_spam_whitelist', $save );
				\IPS\Session::i()->log( 'acplog__spam_whitelist_created', array( 'spam_whitelist_' . $save['whitelist_type'] . '_select' => TRUE, $save['whitelist_content'] => FALSE ) );
			}

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&do=whitelist' ), 'saved' );
		}

		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( $current ? $current['whitelist_content'] : 'add', $form, FALSE );
	}

	/**
	 * Delete whitelist entry
	 *
	 * @return	void
	 */
	protected function whitelistDelete()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'spam_whitelist_delete' );

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		try
		{
			$current = \IPS\Db::i()->select( '*', 'core_spam_whitelist', array( 'whitelist_id=?', \IPS\Request::i()->id ) )->first();
			\IPS\Session::i()->log( 'acplog__spam_whitelist_deleted', array( 'whitelist_filter_' . $current['whitelist_type'] . '_select' => TRUE, $current['whitelist_content'] => FALSE ) );
			\IPS\Db::i()->delete( 'core_spam_whitelist', array( 'whitelist_id=?', \IPS\Request::i()->id ) );
		}
		catch ( \UnderflowException $e ) { }

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&do=whitelist' ) );
	}
}