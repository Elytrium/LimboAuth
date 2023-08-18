<?php
/**
 * @brief		ACP Member Profile: MFA Block
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Nov 2017
 */

namespace IPS\core\extensions\core\MemberACPProfileBlocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: MFA Block
 */
class _MFA extends \IPS\core\MemberACPProfile\Block
{
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output()
	{
		if ( !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_mfa' ) )
		{
			return '';
		}
		
		$mfaEnabled = FALSE;
		$configuredHandlers = array();
		$hasSecurityQuestions = FALSE;
		$showEditButton = ( \IPS\Settings::i()->mfa_required_groups != '*' and !$this->member->inGroup( explode( ',', \IPS\Settings::i()->mfa_required_groups ) ) );
		foreach ( \IPS\MFA\MFAHandler::handlers() as $key => $handler )
		{
			if ( $handler->isEnabled() and $handler->memberCanUseHandler( $this->member ) )
			{
				$mfaEnabled = TRUE;
				
				if ( $handler->memberHasConfiguredHandler( $this->member ) )
				{
					if ( $handler instanceof \IPS\MFA\SecurityQuestions\Handler )
					{
						$hasSecurityQuestions = TRUE;
					}
					else
					{
						$configuredHandlers[ $key ] = $handler;
					}
				}
				
				if ( !$showEditButton and \count( $handler->acpConfiguration( $this->member ) ) )
				{
					$showEditButton = TRUE;
				}
			}
		}
		
		if ( $mfaEnabled )
		{
			return \IPS\Theme::i()->getTemplate('memberprofile')->mfa( $this->member, $configuredHandlers, $hasSecurityQuestions, $showEditButton );
		}
		return '';
	}
	
	/**
	 * Edit Window
	 *
	 * @return	string
	 */
	function edit()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'member_mfa' );
		
		/* Get all the fields we'll need */
		$fields = array();
		$optOutToggles = array();
		foreach ( \IPS\MFA\MFAHandler::handlers() as $key => $handler )
		{
			if ( $handler->isEnabled() and $handler->memberCanUseHandler( $this->member ) )
			{
				foreach ( $handler->acpConfiguration( $this->member ) as $id => $field )
				{
					$fields[] = $field;
				}
				$optOutToggles[] = "mfa_{$key}_title";
			}
		}
		
		/* Build form */
		$form = new \IPS\Helpers\Form;
		if ( \IPS\Settings::i()->mfa_required_groups != '*' and !$this->member->inGroup( explode( ',', \IPS\Settings::i()->mfa_required_groups ) ) )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'mfa_opt_out_admin', $this->member->members_bitoptions['security_questions_opt_out'], FALSE, array( 'togglesOff' => $optOutToggles ) ) );
		}
		foreach ( $fields as $id => $field )
		{
			if ( $field instanceof \IPS\Helpers\Form\Matrix )
			{
				$form->addMatrix( $field->id, $field );
			}
			else
			{
				$form->add( $field );
			}
		} 
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Reset the failure count and unlock if necessary */
			$this->member->failed_mfa_attempts = 0;
			$mfaDetails = $this->member->mfa_details;
			if ( isset( $mfaDetails['_lockouttime'] ) )
			{
				unset( $mfaDetails['_lockouttime'] );
				$this->member->mfa_details = $mfaDetails;
			}
			
			/* Did we opt out? */
			if ( isset( $values['mfa_opt_out_admin'] ) )
			{
				/* Opt-Out: Disable all handlers */
				if ( $values['mfa_opt_out_admin'] )
				{
					if ( !$this->member->members_bitoptions['security_questions_opt_out'] )
					{
						$this->member->members_bitoptions['security_questions_opt_out'] = TRUE;
						
						foreach ( \IPS\MFA\MFAHandler::handlers() as $key => $handler )
						{
							if ( $handler->memberHasConfiguredHandler( $this->member ) )
							{
								$handler->disableHandlerForMember( $this->member );
							}
						}
						
						$this->member->logHistory( 'core', 'mfa', array( 'handler' => 'questions', 'enable' => FALSE, 'optout' => TRUE ) );
						$this->member->save();
					}
				}
				/* Opt-In */
				elseif ( $this->member->members_bitoptions['security_questions_opt_out'] )
				{
					$this->member->members_bitoptions['security_questions_opt_out'] = FALSE;
					$this->member->save();
					$this->member->logHistory( 'core', 'mfa', array( 'handler' => 'questions', 'enable' => FALSE, 'optout' => FALSE ) );
				}
			}
			
			/* Save each of the handlers */
			foreach ( \IPS\MFA\MFAHandler::handlers() as $key => $handler )
			{
				if ( $handler->isEnabled() and $handler->memberCanUseHandler( $this->member ) )
				{
					$handler->acpConfigurationSave( $this->member, $values );
				}
			}
			
			/* Log and Redirect */
			\IPS\Session::i()->log( 'acplog__members_edited_mfa', array( $this->member->name => FALSE ) );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=view&id={$this->member->member_id}" ), 'saved' );
		}
		
		/* Display */
		return $form;
	}
}