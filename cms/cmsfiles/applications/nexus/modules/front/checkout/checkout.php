<?php
/**
 * @brief		Checkout
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		10 Feb 2014
 */

namespace IPS\nexus\modules\front\checkout;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Checkout
 */
class _checkout extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Invoice
	 */
	protected $invoice;
	
	/**
	 * @brief	Does the user need to log in?
	 */
	protected $needsToLogin = FALSE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'checkout.css', 'nexus' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'store.css', 'nexus' ) );

		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'store_responsive.css', 'nexus', 'front' ) );
		}
		
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_checkout.js', 'nexus', 'front' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'global_gateways.js', 'nexus', 'global' ) );
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'module__nexus_checkout' );
		parent::execute();
	}

	/**
	 * Checkout
	 *
	 * @return	void
	 */
	protected function manage()
	{		
		/* Load invoice */
		try
		{
			$this->invoice = \IPS\nexus\Invoice::load( \IPS\Request::i()->id );
			
			if ( !$this->invoice->canView() )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			$msg = 'no_invoice_view';
			if ( !\IPS\Member::loggedIn()->member_id )
			{
				$msg = 'no_invoice_view_guest';
			}
			
			\IPS\Output::i()->error( $msg, '2X196/1', 403, '' );
		}
		$checkoutUrl = $this->invoice->checkoutUrl();
		
		/* Is it paid? */
		if ( $this->invoice->status === \IPS\nexus\Invoice::STATUS_PAID )
		{
			if ( $this->invoice->return_uri )
			{
				\IPS\Output::i()->redirect( $this->invoice->return_uri );
			}
			else
			{
				\IPS\Output::i()->redirect( $this->invoice->url() );
			}
		}
		
		/* Or cancelled or expired? */
		if ( $this->invoice->status !== \IPS\nexus\Invoice::STATUS_PENDING )
		{
			\IPS\Output::i()->redirect( $this->invoice->url() );
		}
		
		/* Do we need to *show* the first step */
		$canSkipFirstStepIfNameAndBillingAddressIsKnown = TRUE;
		if ( \IPS\Member::loggedIn()->member_id )
		{
			$showFirstStep = FALSE;
			if ( $this->invoice->hasItemsRequiringBillingAddress() or $this->invoice->hasPhysicalItems() )
			{
				$showFirstStep = TRUE;
			}

			foreach ( \IPS\nexus\Customer\CustomField::roots() as $field )
			{
				$column = $field->column;
				if ( $field->purchase_show and $field->purchase_require and !$this->invoice->member->$column )
				{
					$showFirstStep = TRUE;
					$canSkipFirstStepIfNameAndBillingAddressIsKnown = FALSE;
					break;
				}
			}		
		}
		else
		{
			$showFirstStep = TRUE;
		}
						
		/* What are the steps? */
		$steps = array();
		if ( $showFirstStep )
		{
			$steps['checkout_customer'] = array( $this, '_customer' );
		}
		if ( $this->invoice->hasPhysicalItems() )
		{
			$steps['checkout_shipping'] = array( $this, '_shipping' );
		}
		$steps['checkout_pay'] = array( $this, '_pay' );
		
		/* Even if we have to show the first step, can we skip it because we already have their name and a primary billing address? */
		if ( $showFirstStep and $canSkipFirstStepIfNameAndBillingAddressIsKnown and \IPS\Member::loggedIn()->member_id and $this->invoice->member->cm_first_name and $this->invoice->member->cm_last_name and !isset( $_SESSION[ 'wizard-' . md5( $checkoutUrl ) . '-step' ] ) )
		{
			if ( $primaryBillingAddress = \IPS\nexus\Customer::loggedIn()->primaryBillingAddress() )
			{
				$this->invoice->billaddress = $primaryBillingAddress;
				$this->invoice->recalculateTotal();
				$this->invoice->save();
				$_SESSION[ 'wizard-' . md5( $checkoutUrl ) . '-step' ] = isset( $steps['checkout_shipping'] ) ? 'checkout_shipping' : 'checkout_pay';
			}
		}
		
		/* Do we need to log in? */
		$this->needsToLogin = ( !\IPS\Member::loggedIn()->member_id and ( $this->invoice->requiresLogin() or \IPS\Settings::i()->nexus_donate_loggedin ) );
		
		/* Facebook Pixel */
		\IPS\core\Facebook\Pixel::i()->InitiateCheckout = true;
		
		/* Do the wizard */
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		if ( isset( \IPS\Output::i()->breadcrumb['module'][0] ) )
		{
			\IPS\Output::i()->breadcrumb['module'][0] = NULL;
		}
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('checkout')->checkoutWrapper( (string) new \IPS\Helpers\Wizard( $steps, $checkoutUrl, ( !isset( $steps['checkout_login'] ) and isset( $steps['checkout_customer'] ) ) ) );
	}
		
	/**
	 * Step: Customer Details
	 *
	 * @return	string
	 */
	public function _customer()
	{
		/* Init */
		$buttonLang = 'continue_to_review';
		$needBillingInfo = ( $this->invoice->hasItemsRequiringBillingAddress() or $this->invoice->hasPhysicalItems() );
		$needTaxStatus = NULL;

		if ( $this->invoice->hasPhysicalItems() )
		{
			$buttonLang = 'continue_to_shipping';
		}

		$form = new \IPS\Helpers\Form( 'customer', $buttonLang, $this->invoice->checkoutUrl()->setQueryString( '_step', 'checkout_customer' ) );
		
		/* Account Information */
		if ( $this->needsToLogin and !\in_array( \IPS\Login::registrationType(), array( 'disabled', 'redirect' ) ) )
		{
			$guestData = $this->invoice->guest_data;
			
			$postBeforeRegister = NULL;
			if ( isset( \IPS\Request::i()->cookie['post_before_register'] ) )
			{
				try
				{
					$postBeforeRegister = \IPS\Db::i()->select( '*', 'core_post_before_registering', array( 'secret=?', \IPS\Request::i()->cookie['post_before_register'] ) )->first();
				}
				catch ( \UnderflowException $e ) { }
			}
			
			/* Add the registration related js stuff to the checkout form for email field validation */
			$form->attributes['data-controller'] = 'core.front.system.register';
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_system.js', 'core', 'front' ), \IPS\Output::i()->js( 'front_templates.js', 'core', 'front' ) );

			if( isset( $_SESSION['coppa_user'] ) )
			{
				if ( \IPS\Settings::i()->minimum_age > 0 )
				{
					$message = \IPS\Member::loggedIn()->language()->addToStack( 'register_denied_age', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->minimum_age ) ) );
					\IPS\Output::i()->error( $message, '2X196/D', 403, '' );
				}
				else
				{
					\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('reg_awaiting_validation');
					return \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system', 'core' )->notCoppaValidated();
				}
			}
			
			if ( \IPS\Settings::i()->minimum_age > 0 OR \IPS\Settings::i()->use_coppa )
			{
				$form->addHeader( 'coppa_title' );
				
				/* We dynamically replace this as we need to show this message, however we do not want to create a "bday_desc" language string which may not be appropriate on other forms */
				\IPS\Member::loggedIn()->language()->words['bday_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'coppa_verification_only' );
				$form->add( new \IPS\Helpers\Form\Date( 'bday', NULL, TRUE, array( 'max' => \IPS\DateTime::create() ) ) );
			}

			$form->addHeader('account_information');
			if ( \IPS\Settings::i()->nexus_checkreg_usernames )
			{
				$form->add( new \IPS\Helpers\Form\Text( 'username', isset( $guestData['member']['name'] ) ? $guestData['name'] : NULL, TRUE, array( 'accountUsername' => TRUE, 'htmlAutocomplete' => "username" ) ) );
			}
			$form->add( new \IPS\Helpers\Form\Email( 'email_address', $guestData ? $guestData['member']['email'] : ( $postBeforeRegister ? $postBeforeRegister['email'] : NULL ), TRUE, array( 'accountEmail' => TRUE, 'maxLength' => 150, 'htmlAutocomplete' => "email" ) ) );
			$form->add( new \IPS\Helpers\Form\Password( 'password', NULL, TRUE, array( 'protect' => TRUE, 'showMeter' => \IPS\Settings::i()->password_strength_meter, 'checkStrength' => TRUE, 'htmlAutocomplete' => "new-password" ) ) );
			$form->add( new \IPS\Helpers\Form\Password( 'password_confirm', NULL, TRUE, array( 'protect' => TRUE, 'confirm' => 'password', 'htmlAutocomplete' => "new-password" ) ) );
			if ( $needBillingInfo )
			{
				$form->addHeader('billing_information');
			}
		}
		
		/* Billing Information */
		if ( $needBillingInfo or ( $this->needsToLogin and !\in_array( \IPS\Login::registrationType(), array( 'disabled', 'redirect' ) ) and !\IPS\Settings::i()->nexus_checkreg_usernames ) )
		{
			$form->add( new \IPS\Helpers\Form\Text( 'cm_first_name', $this->invoice->member->cm_first_name, TRUE, array( 'htmlAutocomplete' => "given-name" ) ) );
			$form->add( new \IPS\Helpers\Form\Text( 'cm_last_name', $this->invoice->member->cm_last_name, TRUE, array( 'htmlAutocomplete' => "family-name" ) ) );
		}
		if ( $needBillingInfo )
		{
			/* Do we need to know if they are a business or consumer? */
			foreach ( $this->invoice->items as $item )
			{
				if ( $tax = $item->tax )
				{
					if ( $tax->type === 'eu' )
					{
						$needTaxStatus = 'eu';
						break;
					}
					if ( $tax->type === 'business' )
					{
						$needTaxStatus = 'business';
					}
				}
			}
			$addressHelperClass = $needTaxStatus ? 'IPS\nexus\Form\BusinessAddress' : 'IPS\Helpers\Form\Address';
			$addressHelperOptions = ( $needTaxStatus === 'eu' ) ? array( 'vat' => TRUE ) : array();

			/* The actual billing address */
			$addresses = \IPS\Db::i()->select( '*', 'nexus_customer_addresses', array( '`member`=?', \IPS\Member::loggedIn()->member_id ) );
			if ( \count( $addresses ) )
			{
				$billing = NULL;
				$options = array();
				foreach ( new \IPS\Patterns\ActiveRecordIterator( $addresses, 'IPS\nexus\Customer\Address' ) as $address )
				{
					$options[ $address->id ] = $address->address->toString('<br>') . ( ( isset( $address->address->business ) and $address->address->business and isset( $address->address->vat ) and $address->address->vat ) ? ( '<br>' . \IPS\Member::loggedIn()->language()->addToStack('cm_checkout_vat_number') . ': ' . mb_strtoupper( preg_replace( '/[^A-Z0-9]/', '', $address->address->vat ) ) ) : '' );
					if ( ( !$this->invoice->billaddress and $address->primary_billing ) or $this->invoice->billaddress == $address->address )
					{
						$billing = $address->id;
					}
				}
				$options[0] = \IPS\Member::loggedIn()->language()->addToStack( 'other' );
				
				$form->add( new \IPS\Helpers\Form\Radio( 'billing_address', $billing, TRUE, array( 'options' => $options, 'toggles' => array( 0 => array( 'new_billing_address' ) ), 'parse' => 'raw' ) ) );
				$newAddress = new $addressHelperClass( 'new_billing_address', !$billing ? $this->invoice->billaddress : NULL, FALSE, $addressHelperOptions, NULL, NULL, NULL, 'new_billing_address' );
				$newAddress->label = ' ';
				$form->add( $newAddress );
			}
			else
			{
				$form->add( new $addressHelperClass( 'new_billing_address', $this->invoice->billaddress, TRUE, $addressHelperOptions ) );
			}
		}
		
		/* Customer Fields */
		$customer = \IPS\nexus\Customer::loggedIn();
		foreach ( \IPS\nexus\Customer\CustomField::roots() as $field )
		{
			if ( $field->purchase_show )
			{
				$column = $field->column;
				$field->not_null = $field->purchase_require;
				$input = $field->buildHelper( $customer->$column );
				$input->appearRequired = $field->purchase_require;
				$form->add( $input );
			}
		}
		
		/* Additional Information */
		if ( $this->needsToLogin and !\in_array( \IPS\Login::registrationType(), array( 'disabled', 'redirect' ) ) )
		{
			$form->addHeader('additional_information');

			/* Custom fields */
			if( \IPS\Login::registrationType() == 'full' )
			{
				$customFields = \IPS\core\ProfileFields\Field::fields( $guestData ? $guestData['profileFields'] : NULL, \IPS\core\ProfileFields\Field::REG );
				if ( \count( $customFields ) )
				{
					foreach ( $customFields as $group => $fields )
					{
						foreach ( $fields as $field )
						{
							$form->add( $field );
						}
					}
					$form->addSeparator();
				}
			}
			
			/* Security Questions */
			if ( \IPS\Settings::i()->security_questions_enabled and \in_array( \IPS\Settings::i()->security_questions_prompt, array( 'register', 'optional' ) ) )
			{
				$numberOfQuestions = \IPS\Settings::i()->security_questions_number ?: 3;
				$securityQuestions = array();
				foreach ( \IPS\MFA\SecurityQuestions\Question::roots() as $securityQuestion )
				{
					$securityQuestions[ $securityQuestion->id ] = $securityQuestion->_title;
				}
				
				$form->addMessage( \IPS\Member::loggedIn()->language()->addToStack('security_questions_setup_blurb', FALSE, array( 'pluralize' => array( $numberOfQuestions ) ) ) );
				if ( \IPS\Settings::i()->security_questions_prompt === 'optional' )
				{
					$securityOptoutToggles = array();
					foreach ( range( 1, min( $numberOfQuestions, \count( $securityQuestions ) ) ) as $i )
					{
						$securityOptoutToggles[] = 'security_question_q_' . $i;
						$securityOptoutToggles[] = 'security_question_a_' . $i;
					}
					
					$optOutCheckbox = new \IPS\Helpers\Form\Checkbox( 'security_questions_optout_title', FALSE, FALSE, array( 'togglesOff' => $securityOptoutToggles ) );
					if ( \IPS\Member::loggedIn()->language()->checkKeyExists('security_questions_opt_out_warning_value') )
					{
						$optOutCheckbox->description = \IPS\Member::loggedIn()->language()->addToStack('security_questions_opt_out_warning_value');
					}
					$form->add( $optOutCheckbox );
				}
				foreach ( range( 1, min( $numberOfQuestions, \count( $securityQuestions ) ) ) as $i )
				{
					$securityValidation = function( $val ) {
						if ( !$val and ( \IPS\Settings::i()->security_questions_prompt === 'register' or !isset( \IPS\Request::i()->security_questions_optout_title_checkbox ) ) )
						{
							throw new \DomainException('form_required');
						}
					};
					
					$questionField = new \IPS\Helpers\Form\Select( 'security_question_q_' . $i, NULL, FALSE, array( 'options' => $securityQuestions ), $securityValidation, NULL, NULL, 'security_question_q_' . $i );
					$questionField->label = \IPS\Member::loggedIn()->language()->addToStack('security_question_q');
		
					$answerField = new \IPS\Helpers\Form\Text( 'security_question_a_' . $i, NULL, NULL, array(), $securityValidation, NULL, NULL, 'security_question_a_' . $i );
					$answerField->label = \IPS\Member::loggedIn()->language()->addToStack('security_question_a');
					
					$form->add( $questionField );
					$form->add( $answerField );
				}
				$form->addSeparator();
			}
			
			/* Q&A */
			if ( \IPS\Settings::i()->nexus_checkreg_captcha )
			{
				$question = FALSE;
				try
				{
					$question = \IPS\Db::i()->select( '*', 'core_question_and_answer', NULL, "RAND()" )->first();
				}
				catch ( \UnderflowException $e ) {}
				
				if( $question )
				{
					$form->hiddenValues['q_and_a_id'] = $question['qa_id'];
				
					$form->add( new \IPS\Helpers\Form\Text( 'q_and_a', NULL, TRUE, array(), function( $val )
					{
						$qanda  = \intval( \IPS\Request::i()->q_and_a_id );
						$pass = true;
					
						if( $qanda )
						{
							$question = \IPS\Db::i()->select( '*', 'core_question_and_answer', array( 'qa_id=?', $qanda ) )->first();
							$answers = json_decode( $question['qa_answers'], true );
				
							if( \count( $answers ) )
							{
								$pass = FALSE;
							
								foreach( $answers as $answer )
								{
									$answer = trim( $answer );
				
									if( mb_strlen( $answer ) AND mb_strtolower( $answer ) == mb_strtolower( $val ) )
									{
										$pass = TRUE;
									}
								}
							}
						}
						else
						{
							$questions = \IPS\Db::i()->select( 'count(*)', 'core_question_and_answer', 'qa_id > 0' )->first();
							if( $questions )
							{
								$pass = FALSE;
							}
						}
						
						if( !$pass )
						{
							throw new \DomainException( 'q_and_a_incorrect' );
						}
					} ) );
					
					\IPS\Member::loggedIn()->language()->words['q_and_a'] = \IPS\Member::loggedIn()->language()->addToStack( 'core_question_and_answer_' . $question['qa_id'], FALSE );
				}
			}
			
			/* Captcha */
			if ( !$guestData )
			{
				$captcha = new \IPS\Helpers\Form\Captcha;
				if ( (string) $captcha !== '' )
				{
					$form->add( $captcha );
				}
			}
			
			/* Misc */
			$form->add( new \IPS\Helpers\Form\Checkbox( 'reg_admin_mails', $guestData ? $guestData['member']['allow_admin_mails'] : ( \IPS\Settings::i()->updates_consent_default == 'enabled' ? TRUE : FALSE ), FALSE ) );
			\IPS\core\modules\front\system\register::buildRegistrationTerm();
			$form->add( new \IPS\Helpers\Form\Checkbox( 'reg_agreed_terms', (bool) $guestData, TRUE, array(), function( $val )
			{
				if ( !$val )
				{
					throw new \InvalidArgumentException('reg_not_agreed_terms');
				}
			} ) );
		}
		
		/* Handle submission */
		if ( $values = $form->values() )
		{
			/* Set guest transaction key cookie so only this guest can view transaction info */
			if( !isset( \IPS\Request::i()->cookie['guestTransactionKey'] ) AND !\IPS\Member::loggedIn()->member_id )
			{
				$guestTransactionKey = \IPS\Login::generateRandomString();
				\IPS\Request::i()->setCookie( 'guestTransactionKey', $guestTransactionKey, \IPS\DateTime::ts( time() )->add( new \DateInterval( 'P30D' ) ) );
			}

			/* If user is a guest create the member object but don't save it */
			if ( $this->needsToLogin )
			{
				/* It shouldn't be possible to get here */
				if ( \in_array( \IPS\Login::registrationType(), array( 'disabled', 'redirect' ) ) )
				{
					\IPS\Output::i()->error( 'reg_disabled', '3X196/A', 403, '' );
				}
				
				/* Did we pass the minimum age requirement? */
				if ( \IPS\Settings::i()->minimum_age > 0 OR \IPS\Settings::i()->use_coppa )
				{
					if ( \IPS\Settings::i()->minimum_age > 0 AND $values['bday']->diff( \IPS\DateTime::create() )->y < \IPS\Settings::i()->minimum_age )
					{
						$_SESSION['coppa_user'] = TRUE;
						
						$message = \IPS\Member::loggedIn()->language()->addToStack( 'register_denied_age', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->minimum_age ) ) );
						\IPS\Output::i()->error( $message, '2X196/E', 403, '' );
					}
					/* We did, but we should check normal COPPA too */
					else if( ( $values['bday']->diff( \IPS\DateTime::create() )->y < 13 ) )
					{
						$_SESSION['coppa_user'] = TRUE;
						return \IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system', 'core' )->notCoppaValidated();
					}
				}
				
				/* Security questions */
				$securityQuestionAnswers = array();
				if ( \IPS\Settings::i()->security_questions_enabled and ( \IPS\Settings::i()->security_questions_prompt === 'register' or ( \IPS\Settings::i()->security_questions_prompt === 'optional' and !$values['security_questions_optout_title'] ) ) )
				{
					foreach ( $values as $k => $v )
					{
						if ( preg_match( '/^security_question_q_(\d+)$/', $k, $matches ) )
						{
							if ( isset( $securityQuestionAnswers[ $v ] ) )
							{
								$form->error = \IPS\Member::loggedIn()->language()->addToStack( 'security_questions_unique', FALSE, array( 'pluralize' => array( \IPS\Settings::i()->security_questions_number ?: 3 ) ) );
								break;
							}
							else
							{
								$securityQuestionAnswers[ $v ] = \IPS\Text\Encrypt::fromPlaintext( $values[ 'security_question_a_' . $matches[1] ] )->tag();
							}
						}
					}
				}
				
				/* Continue... */
				if ( !$form->error )
				{
					/* Set basic details */
					$member = new \IPS\nexus\Customer;
					if ( \IPS\Settings::i()->nexus_checkreg_usernames )
					{
						if ( $needBillingInfo )
						{
							$member->cm_first_name		= $values['cm_first_name'];
							$member->cm_last_name		= $values['cm_last_name'];
						}
						$member->name				= $values['username'];
					}
					else
					{
						$member->cm_first_name		= $values['cm_first_name'];
						$member->cm_last_name		= $values['cm_last_name'];

						/* If this name is available, use that, otherwise append a number to the name to avoid an incomplete account. */
						$i = 0;
						do
						{
							$name = "{$values['cm_first_name']} {$values['cm_last_name']}";
							if ( $i > 0 )
							{
								$name .= $i;
							}
							
							if ( !\IPS\Login::usernameIsInUse( $name ) )
							{
								$member->name = $name;
								break;
							}
							$i++;
						}
						while( TRUE );
					}
					$member->email				= $values['email_address'];
					$member->setLocalPassword( $values['password'] );
					$member->allow_admin_mails  = $values['reg_admin_mails'];
					$member->member_group_id	= \IPS\Settings::i()->member_group;
					
					/* Customer Fields */
					foreach ( \IPS\nexus\Customer\CustomField::roots() as $field )
					{
						if ( $field->purchase_show )
						{
							$column = $field->column;
							$helper = $field->buildHelper();
							$member->$column = $helper::stringValue( $values["nexus_ccfield_{$field->id}"] );
						}
						
						if ( $field->type === 'Editor' )
						{
							$field->claimAttachments( $member->member_id );
						}
					}
					
					/* Custom Fields */
					$profileFields = array();
					foreach ( \IPS\core\ProfileFields\Field::fields( array(), \IPS\core\ProfileFields\Field::REG ) as $group => $fields )
					{
						foreach ( $fields as $id => $field )
						{
							if ( $field instanceof \IPS\Helpers\Form\Upload )
							{
								$profileFields[ "field_{$id}" ] = (string) $values[ $field->name ];
							}
							else
							{
								$profileFields[ "field_{$id}" ] = $field::stringValue( !empty( $values[ $field->name ] ) ? $values[ $field->name ] : NULL );
							}
	
							if ( $fields instanceof \IPS\Helpers\Form\Editor )
							{
								$field->claimAttachments( $member->member_id );
							}
						}
					}
					
					/* Run it through the spam service */
					$spamCode = NULL;
					$spamAction = NULL;
					if( \IPS\Settings::i()->spam_service_enabled )
					{
						$spamAction = $member->spamService( 'register', NULL, $spamCode );
						if( $spamAction == 4 )
						{
							\IPS\Output::i()->error( 'spam_denied_account', '2S129/1', 403, '' );
						}
					}
					
					/* Save on invoice */
					$this->invoice->guest_data = array(
						'member'					=> $member->changed,
						'profileFields' 		=> $profileFields,
						'securityAnswers'		=> $securityQuestionAnswers,
						'spamData'				=> array( 'code' => $spamCode, 'action' => $spamAction ),
						'guestTransactionKey'	=> \IPS\Request::i()->cookie['guestTransactionKey'],
						'pbr'					=> $postBeforeRegister ? $postBeforeRegister['secret'] : NULL,
						'referred_by'			=> isset( \IPS\Request::i()->cookie['referred_by'] ) ? \IPS\Request::i()->cookie['referred_by'] : NULL,
						'agreed_terms'			=> ( bool ) $values['reg_agreed_terms']
					);
				}
			}
			/* Otherwise just update the name and details */
			else
			{
				
				$changes = array();
				if ( $needBillingInfo )
				{
					foreach ( array( 'cm_first_name', 'cm_last_name' ) as $k )
					{
						if ( $values[ $k ] != \IPS\nexus\Customer::loggedIn()->$k )
						{
							$changes['name'] = \IPS\nexus\Customer::loggedIn()->cm_name;
							\IPS\nexus\Customer::loggedIn()->$k = $values[ $k ];
						}
					}
				}
				foreach ( \IPS\nexus\Customer\CustomField::roots() as $field )
				{
					if ( $field->purchase_show )
					{
						$column = $field->column;
						$helper = $field->buildHelper();
						$valueToSave = $helper::stringValue( $values["nexus_ccfield_{$field->id}"] );
						if ( \IPS\nexus\Customer::loggedIn()->$column != $valueToSave )
						{
							$changes['other'][] = array( 'name' => 'nexus_ccfield_' . $field->id, 'value' => $field->displayValue( $valueToSave ), 'old' => $field->displayValue( \IPS\nexus\Customer::loggedIn()->$column ) );
						}
 						\IPS\nexus\Customer::loggedIn()->$column = $valueToSave;
					}
				}
				if ( !empty( $changes ) )
				{
					\IPS\nexus\Customer::loggedIn()->log( 'info', $changes );
				}
				
				/* We only want to do this if it's an actual account */
				if ( \IPS\nexus\Customer::loggedIn()->member_id )
				{
					\IPS\nexus\Customer::loggedIn()->save();
				}
				else
				{
					/* Otherwise, we need to store this as guest data */
					$this->invoice->guest_data = array( 'member' => \IPS\nexus\Customer::loggedIn()->changed, 'profileFields' => array(), 'securityAnswers' => array(), 'guestTransactionKey' => \IPS\Request::i()->cookie['guestTransactionKey'] );
				}
			}
			
			if ( !$form->error )
			{
				/* Save the billing address */
				if ( $needBillingInfo )
				{
					if ( \count( $addresses ) and $values['billing_address'] )
					{
						$this->invoice->billaddress = \IPS\nexus\Customer\Address::load( $values['billing_address'] )->address;
					}
					else
					{
						if( empty( $values['new_billing_address']->addressLines ) or !$values['new_billing_address']->city or !$values['new_billing_address']->country or ( !$values['new_billing_address']->region and array_key_exists( $values['new_billing_address']->country, \IPS\GeoLocation::$states ) ) or !$values['new_billing_address']->postalCode )
						{
							$form->error = \IPS\Member::loggedIn()->language()->addToStack('billing_address_required');
							return $form;
						}
						
						if ( \IPS\Member::loggedIn()->member_id )
						{
							$address = new \IPS\nexus\Customer\Address;
							$address->member = \IPS\Member::loggedIn();
							$address->address = $values['new_billing_address'];
							$address->primary_billing = !\count( $addresses );
							$address->primary_shipping = ( !\count( $addresses ) and !$this->invoice->hasPhysicalItems() );
							$address->save();
							
							\IPS\nexus\Customer::loggedIn()->log( 'address', array( 'type' => 'add', 'details' => json_encode( $values['new_billing_address'] ) ) );
						}
						
						$this->invoice->billaddress = $values['new_billing_address'];
					}
				}
							
				/* Save */
				$this->invoice->recalculateTotal();
				$this->invoice->save();
				return array();
			}
		}
		
		/* If we're not logged in, and we need an account for this purchase, show the login form */
		$login = NULL;
		$loginError = NULL;
		$mfaOutput = NULL;
		if ( $this->needsToLogin )
		{
			/* Two-Factor Authentication */
			if ( isset( \IPS\Request::i()->mfa ) and isset( $_SESSION['processing2FACheckout'] ) and $_SESSION['processing2FACheckout']['invoice'] === $this->invoice->id )
			{
				$member = \IPS\Member::load( $_SESSION['processing2FACheckout']['memberId'] );
				if ( !$member->member_id )
				{
					unset( $_SESSION['processing2FACheckout'] );
					\IPS\Output::i()->redirect( $this->invoice->checkoutUrl() );
				}
				
				$device = \IPS\Member\Device::loadOrCreate( $member );
				$mfaOutput = \IPS\MFA\MFAHandler::accessToArea( 'core', $device->known ? 'AuthenticateFrontKnown' : 'AuthenticateFront', $this->invoice->checkoutUrl()->setQueryString( 'mfa', 1 ), $member );		
				if ( !$mfaOutput )
				{
					/* Set the invoice owner */
					$this->invoice->member = $member;
					$this->invoice->save();
					
					/* Process the login */
					( new \IPS\Login\Success( $member, \IPS\Login\Handler::load( $_SESSION['processing2FACheckout']['handler'] ), $_SESSION['processing2FACheckout']['remember'], $_SESSION['processing2FACheckout']['anonymous'] ) )->process();
										
					/* Redirect */
					\IPS\Output::i()->redirect( $this->invoice->checkoutUrl() );
				}
			}
			
			/* Login */			
			$login = new \IPS\Login( $this->invoice->checkoutUrl() );
			try
			{
				if ( $success = $login->authenticate() )
				{
					/* Process the login */
					if ( $success->mfa() )
					{
						$_SESSION['processing2FACheckout'] = array( 'memberId' => $success->member->member_id, 'invoice' => $this->invoice->id, 'anonymous' => $success->anonymous, 'remember' => $success->rememberMe, 'handler' => $success->handler->id );
						\IPS\Output::i()->redirect( $this->invoice->checkoutUrl()->setQueryString( 'mfa', 1 ) );
					}
					else
					{
						/* Set the invoice owner */
						$this->invoice->member = $success->member;
						$this->invoice->save();
						
						/* Process the login */
						$success->process();
											
						/* Redirect */
						\IPS\Output::i()->redirect( $this->invoice->checkoutUrl() );
					}
				}
			}
			catch ( \IPS\Login\Exception $e )
			{
				$loginError = $e->getMessage();
			}
		}
		

		/* Display */
		return \IPS\Theme::i()->getTemplate('checkout')->customerInformation( $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'checkout', 'nexus' ), 'customerInformationForm' ) ), $login, $loginError, $this->invoice );
	}
		
	/**
	 * Step: Select Shipping
	 *
	 * @return	\IPS\Helpers\Form
	 */
	public function _shipping()
	{
		/* Init */
		$form = new \IPS\Helpers\Form( 'shipping', 'continue_to_review', $this->invoice->checkoutUrl()->setQueryString( '_step', 'checkout_shipping' ) );
		$form->attributes['data-controller'] = 'nexus.front.checkout.billingForm';
		$form->attributes['data-new-billing-address-url'] = $this->invoice->checkoutUrl()->setQueryString( 'do', 'addShippingAddress' );
		
		/* Shipping Address field */
		$primaryShipping = NULL;
		$billingAddress = NULL;
		if ( \IPS\Member::loggedIn()->member_id )
		{
			$addresses = \IPS\Db::i()->select( '*', 'nexus_customer_addresses', array( '`member`=?', \IPS\Member::loggedIn()->member_id ) );
			foreach ( new \IPS\Patterns\ActiveRecordIterator( $addresses, 'IPS\nexus\Customer\Address' ) as $address )
			{
				if ( $address->primary_shipping )
				{
					$primaryShipping = $address->id;
				}
				if ( $this->invoice->billaddress == $address->address )
				{
					$billingAddress = $address->id;
				}
			}
			$form->hiddenValues['shipping_address'] = \IPS\nexus\Customer\Address::load( isset( \IPS\Request::i()->shipping_address ) ? \IPS\Request::i()->shipping_address : ( $primaryShipping ?: $billingAddress ) )->id;
			$this->invoice->shipaddress = \IPS\nexus\Customer\Address::load( isset( \IPS\Request::i()->shipping_address ) ? \IPS\Request::i()->shipping_address : ( $primaryShipping ?: $billingAddress ) )->address;
		}
		elseif ( !$this->invoice->shipaddress )
		{
			$this->invoice->shipaddress = $this->invoice->billaddress;
		}
				
		/* Shipping method field */
		if ( isset( \IPS\Request::i()->shipping_address ) )
		{
			/* Save selected address */
			$this->invoice->shipaddress = \IPS\nexus\Customer\Address::load( \IPS\Request::i()->shipping_address )->address;
			$this->invoice->save();
		}
			
		/* Get shipping methods */
		$shipMethods = array();
		foreach ( \IPS\nexus\Shipping\FlatRate::roots() as $rate )
		{
			if ( $rate->isAvailable( $this->invoice->shipaddress, iterator_to_array( $this->invoice->items ), $this->invoice->currency, $this->invoice ) )
			{
				$shipMethods[ $rate->id ] = $rate;
			}
		}
		
		/* Shipping method field */
		$shippingGroups = array();
		$selected = array();
		$shippingAddressErrors = array();
		foreach ( $this->invoice->items as $k => $item )
		{
			if ( $item->physical )
			{				
				if ( $item->shippingMethodIds )
				{
					$availableMethods = ( \IPS\Settings::i()->easypost_api_key and \IPS\Settings::i()->easypost_show_rates ) ? array_intersect( $item->shippingMethodIds, array_merge( array_keys( $shipMethods ), array( 'easypost' ) ) ) : array_intersect( $item->shippingMethodIds, array_keys( $shipMethods ) );
				}
				else
				{
					$availableMethods = ( \IPS\Settings::i()->easypost_api_key and \IPS\Settings::i()->easypost_show_rates ) ? array_merge( array_keys( $shipMethods ), array( 'easypost' ) ) : array_keys( $shipMethods );
				}
				if ( empty( $availableMethods ) )
				{
					$shippingAddressErrors[] = \IPS\Member::loggedIn()->language()->addToStack( 'checkout_no_ship', FALSE, array( 'sprintf' => array( $item->name ) ) );
				}
				sort( $availableMethods );
				$key = md5( json_encode( $availableMethods ) );
				
				if ( !isset( $shippingGroups[ $key ] ) )
				{
					$shippingGroups[ $key ] = array( 'items' => array(), 'methods' => array() );
					foreach ( $availableMethods as $v )
					{
						$shippingGroups[ $key ]['methods'][ $v ] = ( $v === 'easypost' ? NULL : $shipMethods[ $v ] );
					}
				}
				$shippingGroups[ $key ]['items'][ $k ] = $item;
				
				if ( isset( $item->chosenShippingMethodId ) and $item->chosenShippingMethodId )
				{
					$selected[ $key ] = $item->chosenShippingMethodId;
				}
			}
		}
		if ( \IPS\Settings::i()->easypost_api_key and \IPS\Settings::i()->easypost_show_rates )
		{			
			foreach ( $shippingGroups as $key => $data )
			{
				if ( array_key_exists( 'easypost', $data['methods'] ) )
				{
					unset( $shippingGroups[ $key ]['methods']['easypost'] );
					
					$lengthInInches = 0;
					$widthInInches = 0;
					$heightInInches = 0;
					$weightInOz = 0;
					foreach ( $data['items'] as $item )
					{
						$weightInOz += ( $item->weight->float('oz') * $item->quantity );
						$heightInInches += ( $item->height->float('in') * $item->quantity );

						foreach ( array( 'length', 'width' ) as $k )
						{
							$v = "{$k}InInches";
							if ( $item->$k->float('in') > $$v )
							{
								$$v = $item->$k->float('in');
							}
						}
					}

					try
					{
						$easyPost = \IPS\nexus\Shipping\EasyPostRate::getRates( $lengthInInches, $widthInInches, $heightInInches, $weightInOz, $this->invoice->member, $this->invoice->shipaddress, $this->invoice->currency );
						if ( isset( $easyPost['rates'] ) )
						{
							foreach ( $easyPost['rates'] as $rate )
							{
								if ( $rate['currency'] === $this->invoice->currency )
								{
									$shippingGroups[ $key ]['methods'][ $rate['service'] ] = new \IPS\nexus\Shipping\EasyPostRate( $rate );
								}
							}
						}
					}
					catch ( \IPS\Http\Request\Exception $e ) { }

					
					if ( !\count( $shippingGroups[ $key ]['methods'] ) )
					{
						\IPS\Output::i()->error( 'err_no_shipping_methods', '4X196/6', 403, 'err_no_shipping_methods_admin' );
					}					
				}
			}
		}
		$defaults = array();
		foreach ( $shippingGroups as $key => $data )
		{
			foreach ( $data['methods'] as $_methodId => $_methodData )
			{
				$defaults[ $key ] = $_methodId;
				break;
			}
		}
		$form->add( new \IPS\nexus\Form\Shipping( 'shipping_method', \count( $selected ) ? $selected : $defaults, TRUE, array( 'options' => $shippingGroups, 'currency' => $this->invoice->currency, 'invoice' => $this->invoice ) ) );
		
		/* Submissions */
		if ( $values = $form->values() )
		{
			/* Save new shipping address */
			if ( !$this->invoice->shipaddress and !$form->hiddenValues['shipping_address'] )
			{
				$this->addShippingAddress();
				return \IPS\Output::i()->output;
			}
			elseif ( !isset( $values['shipping_method'] ) )
			{
				\IPS\Output::i()->redirect( $this->invoice->checkoutUrl()->setQueryString( 'shipping_address', $form->hiddenValues['shipping_address'] ) );
			}
			
			/* Remove any existing shipping charges on the invoice */
			foreach ( $this->invoice->items as $k => $v )
			{
				if ( $v instanceof \IPS\nexus\extensions\nexus\Item\ShippingCharge )
				{
					$this->invoice->removeItem( $k );
				}
			}
			
			/* Loop chosen methods */
			foreach ( $values['shipping_method'] as $key => $method )
			{
				/* Set that we've chosen that method for those items */
				foreach ( $shippingGroups[ $key ]['items'] as $k => $item )
				{
					$this->invoice->changeItem( $k, array( 'chosen_shipping' => $method ) );
				}
				
				/* Add the charge to the invoice */
				$_method = $shippingGroups[ $key ]['methods'][ $method ];
				$charge = new \IPS\nexus\extensions\nexus\Item\ShippingCharge( $_method->getName(), $_method->getPrice( $shippingGroups[ $key ]['items'], $this->invoice->currency, $this->invoice ) );
				$charge->id = $method;
				$charge->tax = $_method->getTax();
				$shippingItems[] = $charge;
			}
			
			/* Save */
			foreach ( $shippingItems as $s )
			{
				$this->invoice->addItem( $s );
			}
			$this->invoice->save();
			
			/* Continue */
			return array();
		}
		
		/* Display */
		return \IPS\Theme::i()->getTemplate( 'checkout', 'nexus' )->checkoutShipping( $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'checkout', 'nexus' ), 'checkoutShippingForm' ), $this->invoice->shipaddress, $shippingAddressErrors ), $this->invoice );
	}
	
	/**
	 * Add shipping address
	 *
	 * @return	\IPS\Helpers\Form
	 */
	protected function addShippingAddress()
	{
		if ( !$this->invoice )
		{
			try
			{
				$this->invoice = \IPS\nexus\Invoice::loadAndCheckPerms( \IPS\Request::i()->id );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'no_module_permission', '2X196/7', 403, '' );
			}
		}
		
		$form = new \IPS\Helpers\Form( 'new_shipping_address', 'continue', $this->invoice->checkoutUrl()->setQueryString( 'do', 'addShippingAddress' ) );

		/* Shipping Address field */
		if ( \IPS\Member::loggedIn()->member_id )
		{
			$addresses = \IPS\Db::i()->select( '*', 'nexus_customer_addresses', array( '`member`=?', \IPS\Member::loggedIn()->member_id ) );
			$options = array();
			$primaryShipping = NULL;
			$billingAddress = NULL;
			foreach ( new \IPS\Patterns\ActiveRecordIterator( $addresses, 'IPS\nexus\Customer\Address' ) as $address )
			{
				$options[ $address->id ] = $address->address->toString('<br>');
				if ( $address->primary_shipping )
				{
					$primaryShipping = $address->id;
				}
				if ( $this->invoice->billaddress == $address->address )
				{
					$billingAddress = $address->id;
				}
			}
			$options[0] = 'other';
			$form->add( new \IPS\Helpers\Form\Radio( 'shipping_address', $primaryShipping ?: $billingAddress, TRUE, array( 'options' => $options, 'disabled' => ( isset( \IPS\Request::i()->shipping_address ) AND !isset( \IPS\Request::i()->new_shipping_address_submitted ) ) ), function( $val )
			{
				if ( $val )
				{
					return static::_shippingAddressValidation( \IPS\nexus\Customer\Address::load( $val )->address );
				}
			} ) );
		}
		$form->add( new \IPS\Helpers\Form\Address( 'new_shipping_address', NULL, FALSE, array(), function( $val )
		{
			if ( $val )
			{
				return static::_shippingAddressValidation( $val );
			}
		}, NULL, NULL, 'new_shipping_address' ) );
		
		if ( $values = $form->values() )
		{
			if ( \IPS\Member::loggedIn()->member_id )
			{
				$addressId = $values['shipping_address'];
	
				if ( \intval( $values['shipping_address'] ) === 0 )
				{
					$address = new \IPS\nexus\Customer\Address;
					$address->member = \IPS\Member::loggedIn();
					$address->address = $values['new_shipping_address'];
					$address->save();	
	
					$addressId = $address->id;
					
					\IPS\nexus\Customer::loggedIn()->log( 'address', array( 'type' => 'add', 'details' => json_encode( $values['shipping_address'] ) ) );
				}
							
				\IPS\Output::i()->redirect( $this->invoice->checkoutUrl()->setQueryString( 'shipping_address', $addressId )->setQueryString( '_step', 'checkout_shipping' ) );
			}
			else
			{
				$this->invoice->shipaddress = $values['new_shipping_address'];
				$this->invoice->save();
				\IPS\Output::i()->redirect( $this->invoice->checkoutUrl() );
			}
		}
		
		\IPS\Output::i()->output = \IPS\Member::loggedIn()->member_id ? $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'checkout', 'nexus' ), 'changeShippingAddressForm' ) ) : $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
	
	/**
	 * Shipping Address Validation
	 *
	 * @param	\IPS\Geolocation	$address	The address
	 * @return	void
	 * @throws	\DomainException
	 */
	protected static function _shippingAddressValidation( \IPS\GeoLocation $address )
	{
		if ( \IPS\Settings::i()->easypost_api_key and $address->country === 'US' )
		{
			$phone = NULL;
			foreach ( \IPS\nexus\Customer\CustomField::roots() as $field )
			{
				if ( $field->type === 'Tel' )
				{
					$fieldId = 'nexus_ccfield_' . $field->id;
					$phone = \IPS\Request::i()->$fieldId;
					
					if ( $field->column === 'cm_phone' )
					{
						break;
					}
				}
			}

			$addressLines = $address->addressLines;
			
			$response = \IPS\Http\Url::external( 'https://api.easypost.com/v2/addresses' )->request()->login( \IPS\Settings::i()->easypost_api_key, '' )->post( array( 'address' => array(
				'street1'	=> array_shift( $addressLines ),
				'street2'	=> \count( $addressLines ) ? implode( ', ', $addressLines ) : NULL,
				'city'		=> $address->city,
				'state'		=> $address->region,
				'zip'		=> $address->postalCode,
				'country'	=> $address->country,
				'name'		=> \IPS\Request::i()->cm_first_name . ' ' . \IPS\Request::i()->cm_last_name,
				'phone'		=> $phone,
				'email'		=> \IPS\Member::loggedIn()->email
			) ) )->decodeJson();
			
			$response = \IPS\Http\Url::external( "https://api.easypost.com/v2/addresses/{$response['id']}/verify" )->request()->login( \IPS\Settings::i()->easypost_api_key, '' )->get();
			if ( $response->httpResponseCode != 200 )
			{
				throw new \DomainException('address_couldnt_validate');
			}
		}
		
		return NULL;
	}
	
	/**
	 * Step: Select Payment Method
	 *
	 * @param	array	$data	Wizard data
	 * @return	string
	 */
	public function _pay( $data )
	{		
		/* How much are we paying? */
		$this->invoice->recalculateTotal();
		$amountToPay = $this->invoice->amountToPay( TRUE );
		if ( isset( \IPS\Request::i()->split ) )
		{
			$split = new \IPS\Math\Number( \IPS\Request::i()->split );
			if ( $amountToPay->amount->compare( $split ) === 1 )
			{
				$amountToPay->amount = $split;
			}
		}
		if ( !$amountToPay->amount->isPositive() )
		{
			\IPS\Output::i()->error( 'err_no_methods', '5X196/8', 500, '' );
		}

		foreach ( $this->invoice->items as $item )
		{
			/* Verify whether we're allowed to purchase this */
			if( \IPS\Member::loggedIn()->member_id )
			{
				try
				{
					$item->memberCanPurchase( \IPS\Member::loggedIn() );
				}
				catch ( \DomainException $e )
				{
					\IPS\Output::i()->error( $e->getMessage(), '2X196/H', 403, '' );
				}
			}

			/* Verify stock level one last time. It's possible someone added an item to their cart, then someone else did and checked out and the stock level is now 0. */
			if( $item instanceof IPS\nexus\extensions\nexus\Item\Package )
			{
				$package = \IPS\nexus\Package::load( $item->id );
				$data = $package->optionValuesStockAndPrice( $package->optionValues( $item->details ) );
	
				if ( $data['stock'] != -1 and $data['stock'] < $item->quantity )
				{
					\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->addToStack( 'not_enough_in_stock_checkout', FALSE, array( 'pluralize' => array( $data['stock'] ), 'sprintf' => array( $item->name ) ) ), '1X196/G', 403, '' );
				}
			}
		}
		
		/* Work out recurring payments */
		$recurrings = array();
		$overriddenRenewalTerms = array();
		foreach ( $this->invoice->items as $item )
		{
			if ( $item->groupWithParent and \is_int( $item->parent ) and isset( $item->renewalTerm ) and $item->renewalTerm )
			{
				$parent = $this->invoice->items[ $item->parent ];
				if ( ( isset( $parent->renewalTerm ) and $parent->renewalTerm ) or isset( $overriddenRenewalTerms[ $item->parent ] ) )
				{
					if ( isset( $overriddenRenewalTerms[ $item->parent ] ) )
					{
						$oldTerm = $overriddenRenewalTerms[ $item->parent ];
					}
					else
					{
						$oldTerm = $parent->renewalTerm;
					}
					
					for( $i=0, $j=$item->quantity; $i < $j; $i++ )
					{
						$overriddenRenewalTerms[ $item->parent ] = new \IPS\nexus\Purchase\RenewalTerm( ( isset( $overriddenRenewalTerms[ $item->parent ] ) ) ? $overriddenRenewalTerms[ $item->parent ]->add( $item->renewalTerm ) : $oldTerm->add( $item->renewalTerm ), $oldTerm->interval, $oldTerm->tax );
					}
				}
				else
				{
					$overriddenRenewalTerms[ $item->parent ] = $item->renewalTerm;
				}
			}
		}
		foreach ( $this->invoice->items as $k => $item )
		{
			if ( !$item->groupWithParent )
			{
				$term = NULL;
				$dueDate = NULL;
				if ( isset( $overriddenRenewalTerms[ $k ] ) )
				{
					$term = $overriddenRenewalTerms[ $k ];
					$dueDate = \IPS\DateTime::create()->add( $term->interval );
				}
				elseif ( $item instanceof \IPS\nexus\Invoice\Item\Renewal )
				{
					$term = \IPS\nexus\Purchase::load( $item->id )->renewals;
					
					if ( $expireDate = \IPS\nexus\Purchase::load( $item->id )->expire and $expireDate->getTimestamp() > time() )
					{
						$dueDate = \IPS\nexus\Purchase::load( $item->id )->expire;
					}
					else
					{
						$dueDate = \IPS\DateTime::create();
					}
					
					for ( $i = 0; $i < $item->quantity; $i++ )
					{
						$dueDate = $dueDate->add( $term->interval );
					}
				}
				elseif ( isset( $item->renewalTerm ) and $item->renewalTerm )
				{
					$term = $item->renewalTerm;
					$dueDate = \IPS\DateTime::create()->add( $item->initialInterval ?: $term->interval );
				}

				if ( $term )
				{
					$format = $item->groupWithParent ? 'grouped' : $term->interval->format('%d/%m/%y') . '/' . $term->cost->currency . '/' . ( $term->tax ? $term->tax->id : '0' );
					
					$clonedItem = clone $item;
					$showDueDate = true;
					if ( $item instanceof \IPS\nexus\Invoice\Item\Renewal ) // If they are renewing for more than one cycle now, that's fine, but subsequently they will be moved back to the normal terms
					{
						$clonedItem->quantity = 1;
					}

					/* If there are different expiration dates then we want to display things a little differently */
					if( isset( $item->initialInterval ) AND $item->initialInterval instanceof \DateInterval )
					{
						$clonedItem->expireDate = \IPS\DateTime::create()->add( $item->initialInterval );
						$showDueDate = false;
					}
					else
					{
						$clonedItem->expireDate = \IPS\DateTime::create()->add( $term->interval );
					}

					if ( isset( $recurrings[ $format ] ) )
					{
						$recurrings[ $format ]['items'][] = $clonedItem;
					}
					else
					{
						$recurrings[ $format ] = array( 'items' => array( $clonedItem ), 'term' => new \IPS\nexus\Purchase\RenewalTerm( new \IPS\nexus\Money( 0, $term->cost->currency ), $term->interval, $term->tax ), 'showDueDate' => true );
					}

					$recurrings[ $format ]['term']->cost->amount = $recurrings[ $format ]['term']->cost->amount->add( $term->cost->amount->multiply( new \IPS\Math\Number( "{$clonedItem->quantity}" ) ) );
					$recurrings[ $format ]['dueDate'] = $dueDate;
					if( !$showDueDate )
					{
						$recurrings[ $format ]['showDueDate'] = false;
					}
				}
			}
		}
		
		/* Get available payment methods, removing any that aren't supported by the items on this invoice */
		$paymentMethods = array();
		foreach ( \IPS\nexus\Gateway::roots() as $gateway )
		{
			$paymentMethods[ $gateway->id ] = $gateway;
		}
		$canUseAccountCredit = TRUE;
		foreach ( $this->invoice->items as $item )
		{
			if ( $item->paymentMethodIds )
			{
				foreach ( $paymentMethods as $k => $v )
				{
					if ( \in_array( '*', $item->paymentMethodIds ) ) // This looks odd but in older versions IPS\nexus\extensions\nexus\Item\Subscription::renewalPaymentMethodIds() was mistakenly returning array('*') and since the value is stored in the invoice we have to keep this for compatibility with invoices created at that time
					{
						continue;
					}
					
					if ( !\in_array( $k, $item->paymentMethodIds ) )
					{
						unset( $paymentMethods[ $k ] );
					}
				}				
			}
			
			if ( !$item::$canUseAccountCredit )
			{
				$canUseAccountCredit = FALSE;
			}
		}
		
		/* If there's something to pay, show a form for it... */
		$paymentType = NULL;
		$hiddenValues = array();
		if ( $amountToPay->amount->isGreaterThanZero() )
		{
			$paymentType = 'pay';
			
			/* Remove any payment methods that can't be used for this transaction */
			foreach ( $paymentMethods as $gateway )
			{
				if ( !$gateway->checkValidity( $amountToPay, $this->invoice->billaddress, \IPS\nexus\Customer::loggedIn()->member_id ? \IPS\nexus\Customer::loggedIn() : \IPS\nexus\Customer::constructFromData( $this->invoice->guest_data['member'] ), $recurrings ) )
				{
					unset( $paymentMethods[ $gateway->id ] );
				}
			}
			
			/* If we don't have any available payment methods, show an error */
			if ( \count( $paymentMethods ) === 0 and !$amountToPay->amount->isZero() )
			{
				\IPS\Output::i()->error( 'err_no_methods', '4X196/3', 500, 'err_no_methods_admin' );
			}
													
			/* Build form */
			$elements = array();
			$paymentMethodsToggles = array();
			$showSubmitButton = FALSE;
			foreach ( $paymentMethods as $gateway )
			{
				foreach ( $gateway->paymentScreen( $this->invoice, $amountToPay, NULL, $recurrings ) as $element )
				{
					if ( !$element->htmlId )
					{
						$element->htmlId = $gateway->id . '-' . $element->name;
					}
					$elements[] = $element;
					$paymentMethodsToggles[ $gateway->id ][] = $element->htmlId;
				}
				
				if ( $gateway->showSubmitButton() )
				{
					$showSubmitButton = TRUE;
					$paymentMethodsToggles[ $gateway->id ][] = 'paymentMethodSubmit';
				}
			}
			$paymentMethodOptions = array();
			
			foreach ( $paymentMethods as $k => $v )
			{
				$paymentMethodOptions[ $k ] = $v->_title;
			}

			if ( $canUseAccountCredit and isset( \IPS\nexus\Customer::loggedIn()->cm_credits[ $this->invoice->currency ] ) and \IPS\nexus\Customer::loggedIn()->cm_credits[ $this->invoice->currency ]->amount->isGreaterThanZero() )
			{
				$credit = \IPS\nexus\Customer::loggedIn()->cm_credits[ $this->invoice->currency ]->amount;
				if( $credit >= $amountToPay->amount or ( $amountToPay->amount->subtract( $credit ) > new \IPS\Math\Number( '0.50' ) ) )
				{
					$paymentMethodOptions[0] = \IPS\Member::loggedIn()->language()->addToStack( 'account_credit_with_amount', FALSE, array( 'sprintf' => array( \IPS\nexus\Customer::loggedIn()->cm_credits[ $this->invoice->currency ] ) ) );
					$paymentMethodsToggles[0][] = 'paymentMethodSubmit';
				}
			}
			
			if ( !$paymentMethodOptions )
			{
				$showSubmitButton = TRUE;
			}
		}
		/* If there's nothing to pay now, but there will be something to pay later... if we DON'T have any stored payment methods, we may be able to prompt the user to create one */
		elseif ( $recurrings and array_intersect( array_keys( $paymentMethods ), array_keys( \IPS\nexus\Gateway::cardStorageGateways() ) ) and !\IPS\Db::i()->select( 'COUNT(*)', 'nexus_customer_cards', array( 'card_member=?', \IPS\nexus\Customer::loggedIn()->member_id ) )->first() and $elements = \IPS\nexus\Customer\CreditCard::createFormElements( \IPS\nexus\Customer::loggedIn(), FALSE, $showSubmitButton, $hiddenValues ) )
		{
			$paymentType = 'card';
		}
		/* Otherwise, just allow the user to confirm without paying anything */
		else
		{
			/* But wait - if they have *already* submitted payment, and we are waiting for that to be approved, there's nothing to do right now */
			foreach ( $this->invoice->transactions( [ \IPS\nexus\Transaction::STATUS_HELD, \IPS\nexus\Transaction::STATUS_REVIEW, \IPS\nexus\Transaction::STATUS_GATEWAY_PENDING ] ) as $previousTransaction )
			{
				\IPS\Output::i()->redirect( $previousTransaction->url() );
			}
			
			/* Otherwise they just need to confirm */
			$paymentType = 'none';
			$showSubmitButton = TRUE;
			$elements = [];
		}
		
		/* Build the form */
		$checkoutUrl = $this->invoice->checkoutUrl()->setQueryString( '_step', 'checkout_pay' );
		if ( isset( \IPS\Request::i()->split ) )
		{
			$checkoutUrl = $checkoutUrl->setQueryString( 'split', $amountToPay->amountAsString() );
		}
		$form = new \IPS\Helpers\Form( 'select_method', 'checkout_pay', $checkoutUrl );
		foreach ( $hiddenValues as $k => $v )
		{
			$form->hiddenValues[ $k ] = $v;
		}
		$form->class = 'ipsForm_vertical';
		if ( isset( \IPS\Request::i()->previousTransactions ) )
		{
			$form->hiddenValues['previousTransactions'] = \IPS\Request::i()->previousTransactions;
		}
		else
		{
			if ( $previousTransactions = $this->invoice->transactions() and \count( $previousTransactions ) )
			{
				$previousTransactionIds = array();
				foreach ( $previousTransactions as $previousTransaction )
				{
					$previousTransactionIds[] = $previousTransaction->id;
				}
				$form->hiddenValues['previousTransactions'] = implode( ',', $previousTransactionIds );
			}
		}
		if ( $paymentType === 'pay' and \count( $paymentMethodOptions ) > 1 )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'payment_method', NULL, TRUE, array( 'options' => $paymentMethodOptions, 'toggles' => $paymentMethodsToggles ) ) );
		}
		foreach ( $elements as $element )
		{
			$form->add( $element );
		}
		if ( \IPS\Settings::i()->nexus_tac === 'checkbox' )
		{
			$form->add( new \IPS\Helpers\Form\Checkbox( 'i_agree_to_tac', FALSE, TRUE, array( 'labelHtmlSprintf' => array( "<a href='" . htmlspecialchars( \IPS\Settings::i()->nexus_tac_link, ENT_DISALLOWED, 'UTF-8', FALSE ) . "' target='_blank' rel='noopener'>" . \IPS\Member::loggedIn()->language()->addToStack( 'terms_and_conditions' ) . '</a>' ) ), function( $val )
			{
				if ( !$val )
				{
					throw new \DomainException( 'you_must_agree_to_tac' );
				}
			} ) );
		}
		
		/* Error to show? */
		if ( isset( \IPS\Request::i()->err ) )
		{
			$form->error = \IPS\Request::i()->err;
		}
		
		/* Submitted? */
		$values = $form->values();
		if ( $values !== FALSE )
		{
			/* Actually take a payment */
			if ( $paymentType === 'pay' )
			{
				/* Load gateway */
				$gateway = NULL;
				if ( isset( $values['payment_method'] ) )
				{
					if ( $values['payment_method'] != 0 )
					{
						$gateway = \IPS\nexus\Gateway::load( $values['payment_method'] );
					}
				}
				else
				{
					$gateway = array_pop( $paymentMethods );
				}
							
				/* Do we already have a "waiting" transaction (which means a manual payment, such as by check or bank wire) we don't
					need to create a new one since it'll be exactly the same. We can just take them to the screen for the transaction
					we already have which shows the instructions they need */
				try
				{
					$existingWaitingTransaction = \IPS\Db::i()->select( '*', 'nexus_transactions', array(
						't_member=? AND t_invoice=? AND t_method=? AND t_status=? AND t_amount=? AND t_currency=?',
						\IPS\Member::loggedIn()->member_id,
						$this->invoice->id,
						( $gateway === NULL ) ? 0 : $gateway->_id,
						\IPS\nexus\Transaction::STATUS_WAITING,
						(string) $amountToPay->amount,
						$amountToPay->currency
					) )->first();
	
					\IPS\Output::i()->redirect( \IPS\nexus\Transaction::constructFromData( $existingWaitingTransaction )->url() );
				}
				catch ( \UnderflowException $e ) { }
	
				/* Create a transaction */
				$transaction = new \IPS\nexus\Transaction;
				$transaction->member = \IPS\Member::loggedIn();
				$transaction->invoice = $this->invoice;
				$transaction->amount = $amountToPay;
				$transaction->ip = \IPS\Request::i()->ipAddress();
				
				/* Account Credit? */
				if ( $gateway === NULL )
				{
					$credits = \IPS\nexus\Customer::loggedIn()->cm_credits;
					$inWallet = $credits[ $this->invoice->currency ]->amount;
					if ( $transaction->amount->amount->compare( $inWallet ) === 1 )
					{
						$transaction->amount = new \IPS\nexus\Money( $inWallet, $this->invoice->currency );
					}
					$transaction->status = $transaction::STATUS_PAID;
					$transaction->save();
								
					$credits[ $this->invoice->currency ]->amount = $credits[ $this->invoice->currency ]->amount->subtract( $transaction->amount->amount );
					\IPS\nexus\Customer::loggedIn()->cm_credits = $credits;
					\IPS\nexus\Customer::loggedIn()->save();
					
					$this->invoice->member->log( 'transaction', array(
						'type'			=> 'paid',
						'status'		=> \IPS\nexus\Transaction::STATUS_PAID,
						'id'			=> $transaction->id,
						'invoice_id'	=> $this->invoice->id,
						'invoice_title'	=> $this->invoice->title,
					) );
					
					$transaction->sendNotification();
					
					if ( !$this->invoice->amountToPay()->amount->isGreaterThanZero() )
					{	
						$this->invoice->markPaid();
					}
					
					\IPS\Output::i()->redirect( $transaction->url() );
				}
				/* Nope - gateway */
				else
				{
					$transaction->method = $gateway;
				}			
							
				/* Create a MaxMind request */
				$maxMind = NULL;
				if ( \IPS\Settings::i()->maxmind_key and ( !\IPS\Settings::i()->maxmind_gateways or \IPS\Settings::i()->maxmind_gateways == '*' or \in_array( $transaction->method->id, explode( ',', \IPS\Settings::i()->maxmind_gateways ) ) ) )
				{
					$maxMind = new \IPS\nexus\Fraud\MaxMind\Request;
					$maxMind->setTransaction( $transaction );
				}
				
				/* Authorize */			
				try
				{
					$auth = $gateway->auth( $transaction, $values, $maxMind, $recurrings, 'checkout' );
					if ( \is_array( $auth ) )
					{
						return $this->_webhookRedirector( $auth );
					}
					else
					{				
						$transaction->auth = $auth;
					}
				}
				catch ( \LogicException $e )
				{
					$form->error = $e->getMessage();
					return $form;
				}
				catch ( \RuntimeException $e )
				{
					\IPS\Log::log( $e, 'checkout' );
					
					$form->error = \IPS\Member::loggedIn()->language()->addToStack('gateway_err');
					return $form;
				}
							
				/* Check Fraud Rules and capture */
				try
				{
					$memberJustCreated = $transaction->checkFraudRulesAndCapture( $maxMind );
				}
				catch ( \LogicException $e )
				{
					$form->error = $e->getMessage();
					return $form;
				}
				catch ( \RuntimeException $e )
				{
					\IPS\Log::log( $e, 'checkout' );
					
					$form->error = \IPS\Member::loggedIn()->language()->addToStack('gateway_err');
					return $form;
				}			
				
				/* Logged in? */
				if ( $memberJustCreated )
				{
					\IPS\Session::i()->setMember( $memberJustCreated );
					\IPS\Member\Device::loadOrCreate( $memberJustCreated, FALSE )->updateAfterAuthentication( NULL );
				}
				
				/* Send email receipt */
				$transaction->sendNotification();
				
				/* Show status screen */
				\IPS\Output::i()->redirect( $transaction->url() );
			}

			/* Otherwise, we can just go ahead */
			else
			{
				/* If this is a guest checking our, go ahead and create their account */
				$customer = \IPS\nexus\Customer::loggedIn();
				$memberJustCreated = NULL;
				if ( !$customer->member_id )
				{
					$customer = $this->invoice->createAccountForGuest();
					$memberJustCreated = $customer;
				}
				
				/* Did we want to store a card? */
				if ( $paymentType === 'card' )
				{
					try
					{
						$card = \IPS\nexus\Customer\CreditCard::createFormSubmit( $values, $customer, FALSE );
					}
					catch ( \DomainException $e )
					{
						$form->error = $e->getMessage();
						return $form;
					}
				}
				
				/* Check if there's anything still to pay */
				if ( $this->invoice->amountToPay()->amount->isZero() )
				{
					/* Only mark paid if the invoice itself was worth zero */
					if( $this->invoice->total->amount->isZero() )
					{
						$this->invoice->markPaid();
					}

					/* Redirect */
					$destination = $this->invoice->return_uri ?: $this->invoice->url();
					if ( \IPS\Member::loggedIn()->member_id )
					{
						\IPS\Output::i()->redirect( $destination );
					}
					else
					{
						if ( $memberJustCreated )
						{
							\IPS\Session::i()->setMember( $memberJustCreated );
							\IPS\Member\Device::loadOrCreate( $memberJustCreated, FALSE )->updateAfterAuthentication( NULL );
						}
						
						\IPS\Output::i()->redirect( $destination );
					}
				}
				/* They're waiting for approval - show them a screen to indicate this so they don't try to pay twice */
				else
				{
					foreach ( $this->invoice->transactions( array( \IPS\nexus\Transaction::STATUS_HELD, \IPS\nexus\Transaction::STATUS_REVIEW, \IPS\nexus\Transaction::STATUS_GATEWAY_PENDING ) ) as $transaction )
					{
						\IPS\Output::i()->redirect( $transaction->url() );
					}
					\IPS\Output::i()->error( 'err_no_methods', '5X196/C', 500, '' );
				}
			}
		}
		
		/* Coupons */
		$couponForm = NULL;
		if ( \IPS\Db::i()->select( 'COUNT(*)', 'nexus_coupons' )->first() )
		{
			$canUseCoupons = TRUE;
			foreach ( $this->invoice->items as $item )
			{
				if ( !$item::$canUseCoupons )
				{
					$canUseCoupons = FALSE;
					break;
				}
			}
			
			if ( $canUseCoupons )
			{
				$invoice = $this->invoice;
				$couponForm = new \IPS\Helpers\Form( 'coupon', 'save', $this->invoice->checkoutUrl()->setQueryString( '_step', 'checkout_pay' ) );
				$couponForm->add( new \IPS\Helpers\Form\Custom( 'coupon_code', NULL, TRUE, array(
					'getHtml'	=> function( $field )
					{
						return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->text( $field->name, 'text', $field->value, $field->required, 25 );
					},
					'formatValue'	=> function( $field ) use ( $invoice )
					{
						if ( $field->value )
						{
							try
							{
								return \IPS\nexus\Coupon::load( $field->value, 'c_code' )->useCoupon( $invoice, \IPS\nexus\Customer::loggedIn() );
							}
							catch ( \OutOfRangeException $e )
							{
								throw new \DomainException('coupon_code_invalid');
							}
						}
						return '';
					}
				) ) );
				if ( $values = $couponForm->values() )
				{
					$invoice->addItem( $values['coupon_code'] );
					$invoice->save();
					\IPS\Output::i()->redirect( $invoice->checkoutUrl()->setQueryString( '_step', 'checkout_pay' ) );
				}
			}
		}
		
		/* Display */
		return \IPS\Theme::i()->getTemplate('checkout')->confirmAndPay( $this->invoice, $this->invoice->summary(), $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'checkout', 'nexus' ), 'paymentForm' ), $this->invoice, $amountToPay, $showSubmitButton ), $amountToPay, $couponForm ? $couponForm->customTemplate( array( \IPS\Theme::i()->getTemplate( 'checkout', 'nexus' ), 'couponForm' ) ) : NULL, $recurrings, $overriddenRenewalTerms );
	}
	
	/**
	 * Split Payment
	 *
	 * @return	void
	 */
	public function split()
	{
		/* Load invoice */
		try
		{
			$invoice = \IPS\nexus\Invoice::loadAndCheckPerms( \IPS\Request::i()->id );

			$minSplitAmount = $invoice->canSplitPayment();
			if ( $minSplitAmount === FALSE )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X196/4', 404, '' );
		}
		
		/* What is the max? */
		$maxSplitAmount = \floatval( (string) ( $invoice->amountToPay()->amount->subtract( new \IPS\Math\Number( number_format( $minSplitAmount, \IPS\nexus\Money::numberOfDecimalsForCurrency( $invoice->currency ), '.', '' ) ) ) ) );
				
		/* Build Form */
		$form = new \IPS\Helpers\Form( 'split', 'continue', $invoice->checkoutUrl()->setQueryString( 'do', 'split' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'split_payment_amount', 0, TRUE, array( 'min' => $minSplitAmount, 'max' => $maxSplitAmount, 'decimals' => TRUE ), NULL, NULL, $invoice->currency ) );
		
		/* Handle Submissions */
		if ( $values = $form->values() )
		{
			\IPS\Output::i()->redirect( $invoice->checkoutUrl()->setQueryString( 'split', $values['split_payment_amount'] ) );
		}
		
		/* Display */
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
	
	/**
	 * View Transaction Status
	 *
	 * @return	void
	 */
	public function transaction()
	{
		try
		{
			$transaction = \IPS\nexus\Transaction::load( \IPS\Request::i()->t );
			if ( !$transaction->member->member_id or $transaction->member->member_id !== \IPS\Member::loggedIn()->member_id )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			/* If we're a guest, we may still be able to view for the checkout session */
			if ( !\IPS\Member::loggedIn()->member_id and isset( \IPS\Request::i()->cookie['guestTransactionKey'] ) and isset( $transaction->invoice->guest_data['guestTransactionKey'] ) and \IPS\Login::compareHashes( \IPS\Request::i()->cookie['guestTransactionKey'], $transaction->invoice->guest_data['guestTransactionKey'] ) )
			{
				/* Allowing it as a guest */
				if ( $transaction->member->member_id )
				{
					\IPS\Session::i()->setMember( $transaction->member );
					\IPS\Member\Device::loadOrCreate( $transaction->member, FALSE )->updateAfterAuthentication( NULL );
				}
			}
			else
			{
				\IPS\Output::i()->error( 'node_error', '2X196/5', 403, '' );
			}
		}

		$output = '';
		$checkoutStatus = '';
		
		switch ( $transaction->status )
		{
			case \IPS\nexus\Transaction::STATUS_PAID:
				$complete = ( $transaction->invoice->status === \IPS\nexus\Invoice::STATUS_PAID );
				$purchases = array();
				$checkoutStatus = 'complete';

				if ( $complete )
				{
					if ( $transaction->invoice->return_uri )
					{
						\IPS\Output::i()->redirect( $transaction->invoice->return_uri );
					}
					else
					{
						$purchases = $transaction->invoice->purchasesCreated();
					}
				}
				else
				{
					$checkoutStatus = 'continue';
				}
				
				$output = \IPS\Theme::i()->getTemplate('checkout')->transactionOkay( $transaction, $complete, $purchases );
				break;
				
			case \IPS\nexus\Transaction::STATUS_WAITING:
				$checkoutStatus = 'waiting';
				$output = \IPS\Theme::i()->getTemplate('checkout')->transactionWait( $transaction );
				break;
				
			case \IPS\nexus\Transaction::STATUS_HELD:
				$checkoutStatus = 'hold';
				$output = \IPS\Theme::i()->getTemplate('checkout')->transactionHold( $transaction );
				break;
				
			case \IPS\nexus\Transaction::STATUS_REFUSED:
				$checkoutStatus = 'refused';
				$output = \IPS\Theme::i()->getTemplate('checkout')->transactionFail( $transaction );
				break;
				
			case \IPS\nexus\Transaction::STATUS_GATEWAY_PENDING:
				$checkoutStatus = 'pending';
				$output = \IPS\Theme::i()->getTemplate('checkout')->transactionGatewayPending( $transaction );
				break;
				
			case \IPS\nexus\Transaction::STATUS_PENDING:
				if ( isset( \IPS\Request::i()->pending ) )
				{
					$checkoutStatus = 'pending';
					$output = \IPS\Theme::i()->getTemplate('checkout')->transactionGatewayPending( $transaction );
					break;
				}
			
			default:
				\IPS\Output::i()->redirect( $transaction->invoice->checkoutUrl() );
				break;
		}

		/* Facebook Pixel */
		\IPS\core\Facebook\Pixel::i()->Purchase = array( 'value' => $transaction->invoice->total->amount, 'currency' => $transaction->invoice->total->currency );
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('checkout')->checkoutWrapper( $output, $checkoutStatus );
	}
	
	/**
	 * Wait for the webhook for a transaction to come through before it has been created
	 *
	 * @return	void
	 */
	public function webhook()
	{
		/* Load invoice */
		try
		{
			$this->invoice = \IPS\nexus\Invoice::load( \IPS\Request::i()->id );
			
			if ( !$this->invoice->canView() )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			/* If we're a geust, we may still be able to view for the checkout session */
			if ( !\IPS\Member::loggedIn()->member_id and isset( \IPS\Request::i()->cookie['guestTransactionKey'] ) and isset( $this->invoice->guest_data['guestTransactionKey'] ) and \IPS\Login::compareHashes( \IPS\Request::i()->cookie['guestTransactionKey'], $this->invoice->guest_data['guestTransactionKey'] ) )
			{
				// Allowing it as a guest
			}
			else
			{
				\IPS\Output::i()->error( 'node_error', '2X196/9', 403, '' );
			}
		}
		
		/* Have we decided to give up waiting and just show a pending screen? */
		if ( isset( \IPS\Request::i()->pending ) )
		{
			$checkoutStatus = 'pending';
			$output = \IPS\Theme::i()->getTemplate('checkout')->transactionGatewayPending( NULL, $this->invoice );
			\IPS\core\Facebook\Pixel::i()->Purchase = array( 'value' => $this->invoice->total->amount, 'currency' => $this->invoice->total->currency );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('checkout')->checkoutWrapper( $output, $checkoutStatus );
			return;
		}
		
		/* Nope - show a redirector */
		\IPS\Output::i()->output = $this->_webhookRedirector( isset( \IPS\Request::i()->exclude ) ? explode( ',', \IPS\Request::i()->exclude ) : array() );
		return;
	}
	
	/**
	 * Get a redirector that points to do=webhook
	 *
	 * @param	array	$exclude		Transaction IDs to exclude
	 * @return	\IPS\Helpers\MultipleRedirect
	 */
	protected function _webhookRedirector( $exclude )
	{		
		return new \IPS\Helpers\MultipleRedirect(
			$this->invoice->checkoutUrl()->setQueryString( array( 'do' => 'webhook', 'exclude' => implode( ',', $exclude ) ) ),
			function( $data ) use ( $exclude ) {	
				if ( $data === NULL )
				{
					return array( time(), \IPS\Member::loggedIn()->language()->addToStack('processing_your_payment') );
				}
				else
				{
					/* Do we have any transactions yet? */
					foreach ( $this->invoice->transactions( array( \IPS\nexus\Transaction::STATUS_PAID, \IPS\nexus\Transaction::STATUS_HELD, \IPS\nexus\Transaction::STATUS_REFUSED ), $exclude ? array( array( \IPS\Db::i()->in( 't_id', $exclude, TRUE ) ) ) : array() ) as $transaction )
					{
						\IPS\Output::i()->redirect( $transaction->url() );
					}
					
					$giveUpTime = ( $data + 60 );
					if ( time() > $giveUpTime )
					{
						return NULL;
					}
					else
					{
						sleep(5);
						return array( $data, \IPS\Member::loggedIn()->language()->addToStack('processing_your_payment') );
					}
				}
			},
			function() {
				\IPS\Output::i()->redirect( $this->invoice->checkoutUrl()->setQueryString( array( 'do' => 'webhook', 'pending' => 1 ) ) );
			}
		);
	}

	/**
	 * Virtual Stripe/Apple Domain Verification File
	 *
	 * @return void
	 */
	protected function appleVerification()
	{
		if( $file = \IPS\nexus\Gateway::getStripeAppleVerificationFile() )
		{
			\IPS\Output::i()->sendOutput( $file->contents(), 200, 'text/plain' );
		}
		\IPS\Output::i()->error( 'node_error', '2X196/5', 403, '' );
	}
}