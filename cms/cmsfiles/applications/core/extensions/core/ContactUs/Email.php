<?php
/**
 * @brief		Contact Us extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
{subpackage}
 * @since		29 Sep 2016
 */

namespace IPS\core\extensions\core\ContactUs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Contact Us extension
 */
class _Email
{
	/**
	 * Process Form
	 *
	 * @param	\IPS\Helpers\Form		$form	    The form
	 * @param	array                   $formFields Additional Configuration Formfields
	 * @param	array                   $options    Type Radio Form Options
	 * @param	array                   $toggles    Type Radio Form Toggles
	 * @param	array                   $disabled   Type Radio Form Disabled Options
	 * @return	void
	 */
	public function process( &$form, &$formFields, &$options, &$toggles, &$disabled  )
	{
		$options['contact_internal'] = 'contact_internal';
		$options['contact_emails'] = 'contact_emails';
		$toggles['contact_emails'] = array( 'contact_emails' );

		$formFields[] = new \IPS\Helpers\Form\Stack( 'contact_emails', explode( ',', \IPS\Settings::i()->contact_emails ), FALSE, array( 'stackFieldType' => 'Email', 'maxItems' => 5 ),NULL ,NULL ,NULL, 'contact_emails' );
	}

	/**
	 * Allows extensions to do something before the form is shown... e.g. add your own custom fields, or redirect the page
	 *
	 * @param	\IPS\Helpers\Form		$form	    The form
	 * @return	void
	 */
	public function runBeforeFormOutput( &$form )
	{

	}

	/**
	 * Handle the Form
	 *
	 * @param	array                   $values     Values from form
	 * @return	bool
	 */
	public function handleForm( array $values )
	{
		if ( \IPS\Settings::i()->contact_type == 'contact_internal' OR \IPS\Settings::i()->contact_type == 'contact_emails' )
		{
			$fromName = ( \IPS\Member::loggedIn()->member_id ) ? \IPS\Member::loggedIn()->name : $values['contact_name'];
			$fromEmail = ( \IPS\Member::loggedIn()->member_id ) ? \IPS\Member::loggedIn()->email : $values['email_address'];
			$content = $values['contact_text'];

			if ( \IPS\Settings::i()->contact_type == 'contact_internal' )
			{
				$sender = \IPS\Settings::i()->email_in;
			}
			else
			{
				$sender = explode( ',', \IPS\Settings::i()->contact_emails );
			}
			$mail = \IPS\Email::buildFromTemplate( 'core', 'contact_form', array( \IPS\Member::loggedIn(), $fromName, $fromEmail, $content ), \IPS\Email::TYPE_TRANSACTIONAL );
			$mail->send( $sender , array(), array(), NULL, $fromName, array( 'Reply-To' => \IPS\Email::encodeHeader( $fromName, ( \IPS\Member::loggedIn()->member_id ? \IPS\Member::loggedIn()->email : $values['email_address'] ) ) ), FALSE );

			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}


}