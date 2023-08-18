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
class _RedirectForm
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
		$formFields[] = new \IPS\Helpers\Form\Url( 'contact_redirect', \IPS\Settings::i()->contact_redirect, FALSE, array( ),NULL ,NULL ,NULL, 'contact_redirect' );
		$options['contact_redirect'] = 'contact_redirect';
		$toggles['contact_redirect'] = array( 'contact_redirect' );
	}

	/**
	 * Allows extensions to do something before the form is shown... e.g. add your own custom fields, or redirect the page
	 *
	 * @param	\IPS\Helpers\Form		$form	    The form
	 * @return	void
	 */
	public function runBeforeFormOutput( \IPS\Helpers\Form &$form )
	{
		if ( \IPS\Settings::i()->contact_type == 'contact_redirect' AND \IPS\Settings::i()->contact_redirect != '' )
		{
			\IPS\Output::i()->redirect( \IPS\Settings::i()->contact_redirect );
		}
	}

	/**
	 * Handle the Form
	 *
	 * @param	array                   $values     Values from form
	 * @return	bool
	 */
	public function handleForm( $values )
	{
		return FALSE;
	}

}