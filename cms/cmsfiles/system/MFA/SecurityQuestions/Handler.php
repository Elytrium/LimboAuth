<?php
/**
 * @brief		Multi Factor Authentication Handler for Security Questions
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		2 Sep 2016
 */

namespace IPS\MFA\SecurityQuestions;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Multi Factor Authentication Handler for Security Questions
 */
class _Handler extends \IPS\MFA\MFAHandler
{	
	/**
	 * @brief	Key
	 */
	protected $key = 'questions';
	
	/* !Setup */
	
	/**
	 * Handler is enabled
	 *
	 * @return	bool
	 */
	public function isEnabled()
	{
		return \IPS\Settings::i()->security_questions_enabled;
	}
		
	/**
	 * Member *can* use this handler (even if they have not yet configured it)
	 *
	 * @param	\IPS\Member		$member		Member to check
	 * @return	bool
	 */
	public function memberCanUseHandler( \IPS\Member $member )
	{
		return \IPS\Settings::i()->security_questions_groups == '*' or $member->inGroup( explode( ',', \IPS\Settings::i()->security_questions_groups ) );
	}
	
	/**
	 * Member has configured this handler
	 *
	 * @param	\IPS\Member		$member		Member to check
	 * @return	bool
	 */
	public function memberHasConfiguredHandler( \IPS\Member $member )
	{
		return $member->members_bitoptions['has_security_answers'];
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
		$securityQuestions = array();
		foreach ( Question::roots() as $question )
		{
			$securityQuestions[ $question->id ] = $question->_title;
		}
				
		return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->securityQuestionsSetup( $securityQuestions, $showingMultipleHandlers );
	}
	
	/**
	 * Submit configuration screen. Return TRUE if was accepted
	 *
	 * @param	\IPS\Member		$member	The member
	 * @return	bool
	 */
	public function configurationScreenSubmit( \IPS\Member $member )
	{
		$answers = array();
		
		$isReconfiguring = $this->memberHasConfiguredHandler( $member );
		
		foreach ( \IPS\Request::i()->security_question as $k => $v )
		{
			$answers[ $v ] = array(
				'answer_question_id'	=> $v,
				'answer_member_id'		=> $member->member_id,
				'answer_answer'			=> \IPS\Text\Encrypt::fromPlaintext( \IPS\Request::i()->security_answer[ $k ] )->tag()
			);
		}
		
		if ( \count( $answers ) >= \IPS\Settings::i()->security_questions_number )
		{		
			\IPS\Db::i()->delete( 'core_security_answers', array( 'answer_member_id=?', $member->member_id ) );
			\IPS\Db::i()->insert( 'core_security_answers', $answers );
			
			$member->members_bitoptions['has_security_answers'] = TRUE;
			$member->save();

			/* Log MFA Enable */
			$member->logHistory( 'core', 'mfa', array( 'handler' => $this->key, 'enable' => TRUE, 'reconfigure' => $isReconfiguring ) );

			return TRUE;
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
			$chosenAnswer = \IPS\Db::i()->select( '*', 'core_security_answers', array( 'answer_member_id=? AND answer_is_chosen=1', $member->member_id ) )->first();
			$chosenQuestion = Question::load( $chosenAnswer['answer_question_id'] );
		}
		catch ( \Exception $e )
		{
			$chosenQuestion = NULL;
			foreach ( \IPS\Db::i()->select( '*', 'core_security_answers', array( 'answer_member_id=?', $member->member_id ), 'RAND()' ) as $chosenAnswer )
			{
				try
				{
					$chosenQuestion = Question::load( $chosenAnswer['answer_question_id'] );
					\IPS\Db::i()->update( 'core_security_answers', array( 'answer_is_chosen' => 1 ), array( 'answer_member_id=? AND answer_question_id=?', $member->member_id, $chosenQuestion->id ) );
					break;
				}
				catch ( \OutOfRangeException $e ) { }
			}
			if ( !$chosenQuestion )
			{
				return NULL;
			}
		}
		
		return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->securityQuestionsAuth( $chosenQuestion );
	}
	
	/**
	 * Submit authentication screen. Return TRUE if was accepted
	 *
	 * @param	\IPS\Member		$member	The member
	 * @return	string
	 */
	public function authenticationScreenSubmit( \IPS\Member $member )
	{
		try
		{
			$authenticated = \IPS\Request::i()->security_answer == \IPS\Text\Encrypt::fromTag( \IPS\Db::i()->select( 'answer_answer', 'core_security_answers', array( 'answer_member_id=? AND answer_is_chosen=1', $member->member_id ) )->first() )->decrypt();
			
			if( $authenticated )
			{
				\IPS\Db::i()->update( 'core_security_answers', array( 'answer_is_chosen' => 0 ), array( 'answer_member_id=? AND answer_is_chosen=?', $member->member_id, 1 ) );
			}

			return $authenticated;
		}
		catch ( \UnderflowException $e )
		{
			return FALSE;
		}
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
		if( $enabled )
		{
			/* Check we have some questions */
			if ( !count( \IPS\MFA\SecurityQuestions\Question::roots() ) )
			{
				throw new \DomainException( 'no_questions' );
			}
		}

		\IPS\Settings::i()->changeValues( array( 'security_questions_enabled' => $enabled ) );
	}
	
	/**
	 * ACP Settings
	 *
	 * @return	string
	 */
	public function acpSettings()
	{
		/* Init */
		$activeTabContents = '';
		$tabs = array(
			'settings' 	=> 'security_question_settings',
			'questions'	=> 'security_questions_questions'
		);
		$activeTab = ( isset( \IPS\Request::i()->tab ) and array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'handlers';
		
		/* Settings Form */
		if ( $activeTab === 'settings' )
		{
			$form = new \IPS\Helpers\Form;
			$form->add( new \IPS\Helpers\Form\CheckboxSet( 'security_questions_groups', \IPS\Settings::i()->security_questions_groups == '*' ? '*' : explode( ',', \IPS\Settings::i()->security_questions_groups ), FALSE, array(
				'multiple'		=> TRUE,
				'options'		=> array_combine( array_keys( \IPS\Member\Group::groups() ), array_map( function( $_group ) { return (string) $_group; }, \IPS\Member\Group::groups() ) ),
				'unlimited'		=> '*',
				'unlimitedLang'	=> 'everyone',
				'impliedUnlimited' => TRUE
			), NULL, NULL, NULL, 'security_questions_groups' ) );
			$form->add( new \IPS\Helpers\Form\Radio( 'security_questions_prompt', \IPS\Settings::i()->security_questions_prompt, FALSE, array( 'options' => array( 'register' => 'security_questions_prompt_register', 'optional' => 'security_questions_prompt_optional', 'access' => 'security_questions_prompt_access' ) ), NULL, NULL, NULL, 'security_questions_prompt' ) );
			$form->add( new \IPS\Helpers\Form\Number( 'security_questions_number', \IPS\Settings::i()->security_questions_number, FALSE, array( 'min' => 1, 'max' => \IPS\Db::i()->select( 'COUNT(*)', 'core_security_questions' )->first() ?: NULL ), NULL, NULL, NULL, 'security_questions_number' ) );

			$form->addMessage('nexus_mfa_reset_answers');

			if ( $values = $form->values() )
			{
				$values['security_questions_groups'] = ( $values['security_questions_groups'] == '*' ) ? '*' : implode( ',', $values['security_questions_groups'] );
				$form->saveAsSettings( $values );			
				\IPS\Session::i()->log( 'acplogs__mfa_handler_enabled', array( "mfa_questions_title" => TRUE ) );
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=mfa' ), 'saved' );
			}
			
			$activeTabContents = (string) $form;
		}
		
		/* Questions table */
		else
		{
			$controller = new \IPS\core\modules\admin\settings\securityquestions;
			$controller->execute();
			$activeTabContents = \IPS\Output::i()->output;
		}
		
		
		/* Output */
		if( \IPS\Request::i()->isAjax() )
		{
			return $activeTabContents;
		}
		else
		{
			return \IPS\Theme::i()->getTemplate( 'global' )->tabs( $tabs, $activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=core&module=settings&controller=mfa&tab=handlers&do=settings&key=questions" ) );
		}
	}
	
	/**
	 * Configuration options when editing member account in ACP
	 *
	 * @param	\IPS\Member			$member		The member
	 * @return	array
	 */
	public function acpConfiguration( \IPS\Member $member )
	{
		$return = array();
		$return[] = new \IPS\Helpers\Form\YesNo( "mfa_{$this->key}_title", $this->memberHasConfiguredHandler( $member ), FALSE, array( 'togglesOn' => array( 'security_question_matrix' ) ) );
		
		$securityQuestions = array();
		foreach ( \IPS\MFA\SecurityQuestions\Question::roots() as $question )
		{
			$securityQuestions[ $question->id ] = $question->_title;
		}
		
		$matrix = new \IPS\Helpers\Form\Matrix('security_question_matrix');
		$matrix->columns = array(
			'security_question_q'	=> function( $key, $value, $data ) use ( $securityQuestions )
			{
				return new \IPS\Helpers\Form\Select( $key, $value, FALSE, array( 'options' => $securityQuestions ) );
			},
			'security_question_a'	=> function( $key, $value, $data )
			{
				return new \IPS\Helpers\Form\Text( $key, $value );
			},
		);
		
		foreach ( $member->securityAnswers() as $questionId => $answer )
		{
			$matrix->rows[] = array(
				'security_question_q'	=> $questionId,
				'security_question_a'	=> \IPS\Text\Encrypt::fromTag( $answer )->decrypt()
			);
		}
		
		$return['security_answers'] = $matrix;
		
		return $return;
		
	}
	
	/**
	 * Save configuration when editing member account in ACP
	 *
	 * @param	\IPS\Member		$member		The member
	 * @param	array			$values		Values from form
	 * @return	array
	 */
	public function acpConfigurationSave( \IPS\Member $member, $values )
	{		
		if ( isset( $values["mfa_{$this->key}_title"] ) and !$values["mfa_{$this->key}_title"] )
		{
			if ( $this->memberHasConfiguredHandler( $member ) )
			{
				$this->disableHandlerForMember( $member );
			}
			return;
		}
		
		\IPS\Db::i()->delete( 'core_security_answers', array( 'answer_member_id=?', $member->member_id ) );
		
		$toInsert = array();
		
		foreach ( $values['security_question_matrix'] as $row )
		{
			if ( $row['security_question_a'] )
			{
				$toInsert[ $row['security_question_q'] ] = array(
					'answer_question_id'	=> $row['security_question_q'],
					'answer_member_id'		=> $member->member_id,
					'answer_answer'			=> (string) \IPS\Text\Encrypt::fromPlaintext( $row['security_question_a'] )->tag()
				);
			}
		}
		
		if ( \count( $toInsert ) )
		{
			\IPS\Db::i()->insert( 'core_security_answers', $toInsert );
		}
		
		if ( \count( $toInsert ) >= \IPS\Settings::i()->security_questions_number )
		{
			$member->members_bitoptions['has_security_answers'] = TRUE;
		}
		else
		{
			$member->members_bitoptions['has_security_answers'] = FALSE;
		}
	}
	
	/* !Misc */
	
	/**
	 * If member has configured this handler, disable it
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	void
	 */
	public function disableHandlerForMember( \IPS\Member $member )
	{
		\IPS\Db::i()->delete( 'core_security_answers', array( 'answer_member_id=?', $member->member_id ) );
		$member->members_bitoptions['has_security_answers'] = FALSE;
		$member->save();

		/* Log MFA Disable */
		$member->logHistory( 'core', 'mfa', array( 'handler' => $this->key, 'enable' => FALSE ) );
	}
}