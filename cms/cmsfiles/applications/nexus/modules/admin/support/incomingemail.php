<?php
/**
 * @brief		Incoming Email Setup
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		16 Apr 2014
 */

namespace IPS\nexus\modules\admin\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Incoming Email
 */
class _incomingemail extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'incomingemail_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$table = new \IPS\Helpers\Table\Db( 'core_incoming_emails', \IPS\Http\Url::internal( 'app=nexus&module=support&controller=settings&tab=incomingemail' ) );
		$table->include = array( 'rule_criteria_value' );
		$table->noSort = array( 'rule_criteria_value' );
		$table->parsers = array(
			'rule_criteria_value'		=> function( $val, $row )
			{
				$_langCf	= 'ie_cf_' . $row['rule_criteria_field'];
				$_langCt	= 'ie_ct_' . $row['rule_criteria_type'];
				return \IPS\Member::loggedIn()->language()->addToStack( $_langCf ) . ' ' . \IPS\Member::loggedIn()->language()->addToStack( $_langCt ) . ' "' . $val . '"';
			}
		);
		$table->sortBy = $table->sortBy ?: 'rule_added_date';
		$table->sortDirection = $table->sortDirection ?: 'asc';

		$table->rootButtons	= array(
			'add'	=> array(
				'icon'		=> 'plus',
				'title'		=> 'incomingemail_add',
				'link'		=> \IPS\Http\Url::internal( 'app=nexus&module=support&controller=settings&tab=incomingemail&do=incomingEmailForm' ),
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('incomingemail_add') )
			)
		);

		$table->rowButtons		= function( $row )
		{
			return array(
				'edit'		=> array(
					'icon'		=> 'pencil',
					'title'		=> 'edit',
					'link'		=> \IPS\Http\Url::internal( 'app=nexus&module=support&controller=settings&tab=incomingemail&do=incomingEmailForm&id=' ) . $row['rule_id'],
					'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit') )
				),
				'delete'	=> array(
					'icon'		=> 'times-circle',
					'title'		=> 'delete',
					'link'		=> \IPS\Http\Url::internal( 'app=nexus&module=support&controller=settings&tab=incomingemail&do=incomingEmailDelete&id=' ) . $row['rule_id'],
					'data'		=> array( 'delete' => '' ),
				)
			);
		};
		
		if ( isset( \IPS\Request::i()->type ) )
		{
			if ( \IPS\Request::i()->type === 'piping' )
			{
				$content = \IPS\Theme::i()->getTemplate('incomingemailsetup')->piping();
			}
			elseif ( \IPS\Request::i()->type === 'pop3' )
			{
				$content = \IPS\Theme::i()->getTemplate('incomingemailsetup')->pop3( $this->_pop3() );
			}
			elseif ( \IPS\Request::i()->type === 'sendgrid' )
			{
				$content = \IPS\Theme::i()->getTemplate('incomingemailsetup')->sendgrid();
			}
			else
			{
				$content = \IPS\Theme::i()->getTemplate('incomingemailsetup')->splash( (string) $table );
			}
		}
		else
		{
			$content = NULL;
			if ( \IPS\Settings::i()->pop3_server )
			{
				try
				{
					$task = \IPS\Db::i()->select( '*', 'core_tasks', array( 'app=? AND `key`=? AND enabled=1', 'core', 'pop' ) )->first();
					$pop3Form = $this->_pop3();
					$pop3Form->addButton( 'disable_pop3', 'link', \IPS\Http\Url::internal( 'app=nexus&module=support&controller=incomingemail&do=disablePop3' )->csrf(), '', array( 'data-confirm' => '' ) );
					$content = \IPS\Theme::i()->getTemplate('incomingemailsetup')->usingpop3( $pop3Form, (string) $table );
				}
				catch ( \UnderflowException $e ) { }
			}
			
			$content = $content ?: \IPS\Theme::i()->getTemplate('incomingemailsetup')->splash( (string) $table );
		}
		
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_support.js', 'nexus', 'admin' ) );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('incomingemailsetup')->template( $content );
	}
	
	/**
	 * Disable POP3
	 *
	 * @return	void
	 */
	protected function disablePop3()
	{
		\IPS\Session::i()->csrfCheck();
		\IPS\Settings::i()->changeValues( array( 'pop3_server' => '', 'pop3_port' => '', 'pop3_user' => '', 'pop3_password' => '' ) );		
		\IPS\Session::i()->log( 'acplogs__pop3_settings' );
		\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 0 ), array( 'app=? AND `key`=?', 'core', 'pop' ) );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=support&controller=settings&tab=incomingemail') );
	}
	
	/**
	 * POP3
	 *
	 * @return	\IPS\Helpers\Form
	 */
	protected function _pop3()
	{	
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Text( 'pop3_server', \IPS\Settings::i()->pop3_server, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Number( 'pop3_port', \IPS\Settings::i()->pop3_port, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'pop3_user', \IPS\Settings::i()->pop3_user, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'pop3_password', \IPS\Settings::i()->pop3_password, FALSE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'pop3_tls', \IPS\Settings::i()->pop3_tls ) );
		
		if ( $values = $form->values() )
		{
			try
			{
				$pop3 = new \IPS\Email\Incoming\PopImap( $values['pop3_server'], $values['pop3_tls'], $values['pop3_port'], $values['pop3_user'], $values['pop3_password'] );
				
				$form->saveAsSettings();
				
				\IPS\Session::i()->log( 'acplogs__pop3_settings' );
				\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 1 ), array( 'app=? AND `key`=?', 'core', 'pop' ) );
				
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=support&controller=settings&tab=incomingemail') );
			}
			catch ( \IPS\Email\Incoming\PopImapException $e )
			{
				$form->error = \IPS\Member::loggedIn()->language()->addToStack( $e->getMessage() );
			}
		}
		
		return $form;
	}

	/**
	 * Add/Edit Incoming Email Rule Form
	 *
	 * @return void
	 */
	protected function incomingEmailForm()
	{
		/* Load Rule */
		$rule = NULL;
		if ( \IPS\Request::i()->id )
		{
			try
			{
				$rule = \IPS\Db::i()->select( '*', 'core_incoming_emails', array( 'rule_id=?', \IPS\Request::i()->id ) )->first();
			}
			catch ( \UnderflowException $e ) { }
		}
		
		/* Build form */
		$form	= new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Custom( 'rule_criteria_value', array(
			'criteria_field'	=> $rule ? $rule['rule_criteria_field'] : '',
			'criteria_type'		=> $rule ? $rule['rule_criteria_type'] : '',
			'criteria_value'	=> $rule ? $rule['rule_criteria_value'] : ''
		), FALSE, array( 'getHtml'	=> function( $element )
		{
			return \IPS\Theme::i()->getTemplate( 'advancedsettings', 'core' )->ruleCriteriaForm( $element->name, $element->defaultValue['criteria_field'], $element->defaultValue['criteria_type'], $element->defaultValue['criteria_value'] );
		} ) ) );

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$save = array(
				'rule_criteria_field'	=> $values['rule_criteria_value']['criteria_field'],
				'rule_criteria_type'	=> $values['rule_criteria_value']['criteria_type'],
				'rule_criteria_value'	=> $values['rule_criteria_value']['criteria_value'],
				'rule_app'				=> '',
			);

			if ( $rule )
			{
				\IPS\Db::i()->update( 'core_incoming_emails', $save, array( 'rule_id=?', $rule['rule_id'] ) );
				\IPS\Session::i()->log( 'acplogs__incomingemail_edited' );
			}
			else
			{
				$save['rule_added_date']	= time();
				$save['rule_added_by']		= \IPS\Member::loggedIn()->member_id;

				\IPS\Db::i()->insert( 'core_incoming_emails', $save );
				\IPS\Session::i()->log( 'acplogs__incomingemail_added' );
			}

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=support&controller=settings&tab=incomingemail&tab=incomingemail' ), 'saved' );
		}

		/* Display */
		\IPS\Output::i()->output = $form;
	}

	/**
	 * Delete Incoming Email Rule
	 *
	 * @return void
	 */
	protected function incomingEmailDelete()
	{
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		\IPS\Db::i()->delete( 'core_incoming_emails', array( 'rule_id=?', \IPS\Request::i()->id ) );
		\IPS\Session::i()->log( 'acplogs__incomingemail_deleted' );

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=nexus&module=support&controller=settings&tab=incomingemail&tab=incomingemail" ) );
	}
}