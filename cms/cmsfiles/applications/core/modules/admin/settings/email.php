<?php
/**
 * @brief		email
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Apr 2013
 */

namespace IPS\core\modules\admin\settings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * email
 */
class _email extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'email_manage' );
		parent::execute();
	}

	/**
	 * Email Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Build the settings form */
		$messageHtml = '';

		$settingsForm = new \IPS\Helpers\Form( 'settings_form' );
		$settingsForm->addHeader( 'basic_settings' );
		$settingsForm->add( new \IPS\Helpers\Form\Email( 'email_out', \IPS\Settings::i()->email_out, TRUE, array(), NULL, NULL, NULL, 'email_out' ) );
		$settingsForm->add( new \IPS\Helpers\Form\Email( 'email_in', \IPS\Settings::i()->email_in, TRUE ) );
		$settingsForm->add( new \IPS\Helpers\Form\Color( 'email_color', \IPS\Settings::i()->email_color, TRUE ) );
		$settingsForm->add( new \IPS\Helpers\Form\Upload( 'email_logo', \IPS\Settings::i()->email_logo ? \IPS\File::get( 'core_Theme', \IPS\Settings::i()->email_logo ) : NULL, FALSE, array( 'image' => TRUE, 'storageExtension' => 'core_Theme' ) ) );
		$settingsForm->add( new \IPS\Helpers\Form\YesNo( 'social_links_in_email', \IPS\Settings::i()->social_links_in_email, FALSE, array(), NULL, NULL, NULL, 'social_links_in_email' ) );

		if ( \IPS\Settings::i()->promote_community_enabled )
		{
			$settingsForm->add( new \IPS\Helpers\Form\YesNo( 'our_picks_in_email', \IPS\Settings::i()->our_picks_in_email, FALSE, array(), NULL, NULL, NULL, 'our_picks_in_email' ) );
		}
		
		$settingsForm->add( new \IPS\Helpers\Form\YesNo( 'email_truncate', ( \IPS\Settings::i()->email_truncate ), FALSE, array(), NULL, NULL, NULL, 'email_truncate' ) );
		$settingsForm->add( new \IPS\Helpers\Form\YesNo( 'email_log_do', ( \IPS\Settings::i()->prune_log_emailstats != 0 ), FALSE, array( 'togglesOn' => array( 'prune_log_emailstats' ) ), NULL, NULL, NULL, 'email_log_do' ) );
		$settingsForm->add( new \IPS\Helpers\Form\Interval( 'prune_log_emailstats', \IPS\Settings::i()->prune_log_emailstats ?: 60, FALSE, array( 'valueAs' => 'd', 'unlimited' => '-1', 'unlimitedLang' => 'never' ), NULL, NULL, NULL, 'prune_log_emailstats' ) );
		if ( !\IPS\DEMO_MODE )
		{
			$settingsForm->addHeader( 'advanced_settings' );
					
			$method = \IPS\Settings::i()->mail_method;
			$disabled = array();
	
			if ( \IPS\Settings::i()->sendgrid_api_key AND \IPS\Settings::i()->sendgrid_use_for > 0  )
			{
				if ( \IPS\Settings::i()->sendgrid_use_for == 2 )
				{
					$method = 'sendgrid';
				}
			}
			else
			{
				$disabled[] = 'sendgrid';
			}
	
			/* We previously renamed this to 'php' */
			if( $method == 'mail' )
			{
				$method = 'php';
			}

			$settingsForm->add( new \IPS\Helpers\Form\Radio( 'mail_method', $method, TRUE, array(
				'options' 	=> array( 'php' => ( \IPS\CIC ? 'mail_method_cic' : 'mail_method_php' ), 'smtp' => 'mail_method_smtp', 'sendgrid' => 'mail_method_sendgrid' ),
				'disabled'	=> $disabled,
				'toggles' => array(
					'php'		=> array( 'php_mail_extra' ),
					'smtp'		=> array( 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_helo', 'smtp_protocol' ),
				)
			) ) );
			$options = array( 'plain' => 'smtp_plaintext', 'ssl' => 'smtp_ssl', 'tls' => 'smtp_tls' );
			$settingsForm->add( new \IPS\Helpers\Form\Text( 'smtp_host', \IPS\Settings::i()->smtp_host, FALSE, array(), NULL, NULL, NULL, 'smtp_host' ) );
			$settingsForm->add( new \IPS\Helpers\Form\Select( 'smtp_protocol', \IPS\Settings::i()->smtp_protocol, FALSE, array( 'options' => $options ), NULL, NULL, NULL, 'smtp_protocol' ) );
			$settingsForm->add( new \IPS\Helpers\Form\Number( 'smtp_port', \IPS\Settings::i()->smtp_port, FALSE, array(), NULL, NULL, NULL, 'smtp_port' ) );
			$settingsForm->add( new \IPS\Helpers\Form\Text( 'smtp_user', \IPS\Settings::i()->smtp_user, FALSE, array(), NULL, NULL, NULL, 'smtp_user' ) );
			$settingsForm->add( new \IPS\Helpers\Form\Password( 'smtp_pass', \IPS\Settings::i()->smtp_pass, FALSE, array( 'enforceMaxLimit' => FALSE ), NULL, NULL, NULL, 'smtp_pass' ) );
			if ( !\IPS\CIC )
			{
				$settingsForm->add( new \IPS\Helpers\Form\Text( 'php_mail_extra', \IPS\Settings::i()->php_mail_extra, FALSE, array(), NULL, NULL, NULL, 'php_mail_extra' ) );
			}
		}
		if ( $values = $settingsForm->values() )
		{
			$sendTestEmail = FALSE;

			foreach( array( 'mail_method', 'smtp_host', 'smtp_port', 'smtp_pass', 'smtp_user', 'php_mail_extra', 'smtp_protocol' ) as $setting )
			{
				if( $values[ $setting ] != \IPS\Settings::i()->$setting )
				{
					$sendTestEmail = TRUE;
				}
			}

			if( !$values['email_log_do'] )
			{
				$values['prune_log_emailstats'] = 0;
			}

			unset( $values['email_log_do'] );
			
			if ( $values['mail_method'] != 'sendgrid' and \IPS\Settings::i()->sendgrid_use_for == 2 )
			{
				$values['sendgrid_use_for'] = 1;
			}
			elseif ( $values['mail_method'] == 'sendgrid' )
			{
				$values['sendgrid_use_for'] = 2;
			}

			if ( $values['email_logo'] )
			{
				$values['email_logo'] = (string) $values['email_logo'];
			}

			$settingsForm->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplogs__email_settings' );

			if( $sendTestEmail )
			{
				$email			= \IPS\Email::buildFromContent( \IPS\Member::loggedIn()->language()->get('email_test_subject'), \IPS\Member::loggedIn()->language()->addToStack('email_test_message'), \IPS\Member::loggedIn()->language()->addToStack('email_test_message'), \IPS\Email::TYPE_TRANSACTIONAL );
				
				try
				{
					$result = $email->_send( \IPS\Member::loggedIn(), array(), array(), \IPS\Settings::i()->email_out );
					$messageHtml = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->message( 'email_test_okay_auto', 'success' );

					/* Sent successfully, remove notification */
					\IPS\core\AdminNotification::remove( 'core', 'ConfigurationError', 'failedMail' );
					\IPS\Db::i()->update( 'core_mail_error_logs', array( 'mlog_notification_sent' => TRUE ) );
				}
				catch ( \Exception $e )
				{
					$messageHtml = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->message( 'email_test_error_auto', 'error', $e->getMessage() );
				}
			}
		}
		
		
		/* Build the test form */
		$testFormHtml = '';
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'settings', 'email_test' ) )
		{
			\IPS\Output::i()->sidebar['actions']['test'] = array(
				'title'		=> 'email_test',
				'icon'		=> 'cog',
				'link'		=> \IPS\Http\Url::internal( '#testForm' ),
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('email_test'), 'ipsDialog-content' => '#testForm' )
			);
			
			$testForm = new \IPS\Helpers\Form( 'test_form' );
			$testForm->add( new \IPS\Helpers\Form\Email( 'from', \IPS\Settings::i()->email_out, TRUE ) );
			$testForm->add( new \IPS\Helpers\Form\Email( 'to', \IPS\Member::loggedIn()->email, TRUE ) );
			$testForm->add( new \IPS\Helpers\Form\Text( 'subject', \IPS\Member::loggedIn()->language()->addToStack('email_test_subject'), TRUE ) );
			$testForm->add( new \IPS\Helpers\Form\TextArea( 'message', \IPS\Member::loggedIn()->language()->addToStack('email_test_message'), TRUE ) );
			if ( $values = $testForm->values() )
			{
				try
				{
					$email = \IPS\Email::buildFromContent( $values['subject'], $values['message'], $values['message'], \IPS\Email::TYPE_TRANSACTIONAL );
					$result = $email->_send( $values['to'], array(), array(), $values['from'] );
					
					/* Sent successfully, remove notification */
					\IPS\core\AdminNotification::remove( 'core', 'ConfigurationError', 'failedMail' );
					\IPS\Db::i()->update( 'core_mail_error_logs', array( 'mlog_notification_sent' => TRUE ) );

					$messageHtml = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->message( 'email_test_okay', 'success' );
				}
				catch ( \Exception $exception )
				{
					$content = $exception->getMessage();
					if ( !( $exception instanceof \IPS\Email\Outgoing\Exception ) )
					{
						$content = \get_class( $exception ) . ": " . $exception->getMessage() . " (" . $exception->getCode() . ")";
					}
					$messageHtml = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->message( $content, 'error' );
				}
			}

			$testFormHtml = \IPS\Theme::i()->getTemplate( 'global' )->block( 'email_test', $testForm, FALSE, 'ipsJS_hide', 'testForm' );
		}
		
		/* Add a button for logs */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'settings', 'email_errorlog' ) )
		{
			\IPS\Output::i()->sidebar['actions']['errorLog'] = array(
				'title'		=> 'emailerrorlogs',
				'icon'		=> 'exclamation-triangle',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=email&do=errorLog' ),
			);
		}
		
		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('email_settings');
		\IPS\Output::i()->output	= $messageHtml;
		\IPS\Output::i()->output	.= \IPS\Theme::i()->getTemplate( 'global' )->block( 'email_settings', $settingsForm );
		\IPS\Output::i()->output	.= $testFormHtml;
	}
	
	/**
	 * Error Log
	 *
	 * @return	void
	 */
	protected function errorLog()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'email_errorlog' );
		
		/* Add a button for settings */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'settings', 'email_errorlog_prune' ) )
		{
			\IPS\Output::i()->sidebar['actions'] = array(
				'settings'	=> array(
					'title'		=> 'prunesettings',
					'icon'		=> 'cog',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=email&do=errorLogSettings' ),
					'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('prunesettings') )
				),
			);
		}

		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('emailerrorlogs');
		\IPS\Output::i()->output	= (string) static::emailErrorLogTable( \IPS\Http\Url::internal( 'app=core&module=settings&controller=email&do=errorLog' ) );
	}
	
	/**
	 * Get email error log table
	 *
	 * @param	\IPS\Http\Url	$url	Base URL for table
	 * @param 	array			$where	Where Array
	 * @return	\IPS\Helpers\Table\Db
	 */
	public static function emailErrorLogTable( \IPS\Http\Url $url, array $where=array() ): \IPS\Helpers\Table\Db
	{
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_mail_error_logs', $url, $where );
		$table->langPrefix = 'emailerrorlogs_';
		
		/* Columns we need */
		$table->include	= array( 'mlog_to', 'mlog_subject', 'mlog_date', 'mlog_msg' );
		$table->rowClasses = array( 'mlog_subject' => array( 'ipsTable_wrap' ), 'mlog_msg' => array( 'ipsTable_wrap' ) );

		$table->sortBy	= $table->sortBy ?: 'mlog_date';
		$table->sortDirection	= $table->sortDirection ?: 'DESC';
		$table->noSort	= array( 'mlog_msg' );
		
		/* Search */
		$table->quickSearch = 'mlog_to';
		$table->advancedSearch = array(
				'mlog_to'			=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
				'mlog_from'			=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
				'mlog_subject'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
				'mlog_msg'			=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
				'mlog_content'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
		);
		
		/* Custom parsers */
		$table->parsers = array(
			'mlog_date'				=> function( $val, $row )
			{
				return \IPS\DateTime::ts( $val );
			},
			'mlog_msg'				=> function( $val, $row )
			{
				if( !$val )
				{
					$val = \IPS\Member::loggedIn()->language()->get('phpmail_not_sent');
				}
				else
				{
					if( $data = json_decode( $val, true ) )
					{
						/* We may not have a 'message' key if this is an older SendGrid log entry */
						if( isset( $data['message'] ) )
						{
							$val = \is_string( $data['message'] ) ? 
								( ( isset( $data['details'] ) AND $data['details'] ) ? \IPS\Member::loggedIn()->language()->addToStack( $data['message'], FALSE, array( 'sprintf' => $data['details'] ) ) : \IPS\Member::loggedIn()->language()->addToStack( $data['message'] ) ) :
								$val;
						}
					}
				}

				return \IPS\Theme::i()->getTemplate( 'logs' )->emailErrorLog( nl2br( htmlspecialchars( $val, ENT_DISALLOWED, 'UTF-8', FALSE ) ), $row );
			},
			'mlog_content'				=> function( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate( 'logs' )->emailErrorBody( $val, $row );
			},
		);

		/* Row buttons */
		$table->rowButtons = function( $row )
		{
			$return = array();

			$return['view'] = array(
				'icon'		=> 'search',
				'title'		=> 'view_email_error_body',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=email&do=errorLogView&id=' ) . $row['mlog_id'],
				'hotkey'	=> 'Return',
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('emailerrorlogs_mlog_content') )
			);

			$return['resend'] = array(
				'icon'		=> 'refresh',
				'title'		=> 'resend_email_error',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=email&do=errorLogResend&id=' . $row['mlog_id'] )->csrf(),
			);

			return $return;
		};
		
		/* Return */
		return $table;
	}

	/**
	 * Attempt to resend the email that failed
	 *
	 * @return void
	 */
	public function errorLogResend()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'email_errorlog' );
		\IPS\Session::i()->csrfCheck();
		
		$id			= (int) \IPS\Request::i()->id;
		$log		= \IPS\Db::i()->select( '*', 'core_mail_error_logs', array( 'mlog_id=?', $id ) )->first();
		$emailData	= json_decode( $log['mlog_resend_data'], true );
		
		try
		{
			$fromName = NULL;
			if ( isset( $emailData['headers']['From'] ) and preg_match( '/=\?UTF-8\?B\?(.+?)?= /', $emailData['headers']['From'], $matches ) )
			{
				$fromName = base64_decode( $matches[1] );
			}

			/* Reset basic headers for failed mails */
			unset( $emailData['headers']['MIME-Version'] );
			unset( $emailData['headers']['Content-Type'] );
			unset( $emailData['headers']['Content-Transfer-Encoding'] );
			
			$email = \IPS\Email::buildFromContent( $log['mlog_subject'], $emailData['body']['html'], $emailData['body']['plain'], isset( $emailData['type'] ) ? $emailData['type'] : \IPS\Email::TYPE_TRANSACTIONAL );
			$email->_send(
				array_map( 'trim', explode( ',', $log['mlog_to'] ) ),
				isset( $emailData['headers']['Cc'] ) ? array_map( 'trim', explode( ',', $emailData['headers']['Cc'] ) ) : array(),
				isset( $emailData['headers']['Bcc'] ) ? array_map( 'trim', explode( ',', $emailData['headers']['Bcc'] ) ) : array(),
				$log['mlog_from'],
				$fromName,
				$emailData['headers']
			);
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->error( $e->getMessage(), '4C143/1', 500, '' );
		}

		\IPS\Db::i()->delete( 'core_mail_error_logs', array( 'mlog_id=?', $id ) );

		/* Remove notification since this was re-sent with success and it is below the recent threshold */
		if( \IPS\Email::countFailedMail( \IPS\DateTime::create()->sub( new \DateInterval( 'P3D' ) ), FALSE, TRUE ) <= 3 )
		{
			\IPS\core\AdminNotification::remove( 'core', 'ConfigurationError', 'failedMail' );
			\IPS\Db::i()->update( 'core_mail_error_logs', array( 'mlog_notification_sent' => TRUE ), array( 'mlog_id=?', $id ) );
		}

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=settings&controller=email&do=errorLog" ), 'emailerror_resent' );
	}

	/**
	 * View a failed email details
	 *
	 * @return void
	 */
	public function errorLogView()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'email_errorlog' );
		
		$id		= (int) \IPS\Request::i()->id;
		$log	= \IPS\Db::i()->select( '*', 'core_mail_error_logs', array( 'mlog_id=?', $id ) )->first();

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('logs')->emailErrorBody( $log );
	}

	/**
	 * Error log Prune Settings
	 *
	 * @return	void
	 */
	protected function errorLogSettings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'email_errorlog_prune' );
		
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Interval( 'prune_log_email_error', \IPS\Settings::i()->prune_log_email_error, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'prune_log_email_error' ) );
		
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplog__emailerrorlog_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=email&do=errorLog' ), 'saved' );
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('emailerrorlogssettings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'emailerrorlogssettings', $form, FALSE );
	}
}
