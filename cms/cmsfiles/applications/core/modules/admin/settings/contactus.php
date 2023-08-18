<?php
/**
 * @brief		contactus
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		29 Sep 2016
 */

namespace IPS\core\modules\admin\settings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * contactus
 */
class _contactus extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'contactus_manage' );
		parent::execute();
	}

	/**
	 * Manage Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$form = $this->_getConfigForm();

		if ( $values = $form->values( true ) )
		{
			/* If we unselect 'everyone' and save, an empty string is passed through, so the default value is picked up in Settings::changeValues(),
				which is the 'everyone' preference...the end result is that everyone gets rechecked when you uncheck it. To counter that, we'll store an invalid
				value here. */
			if( $values['contact_access'] === '' )
			{
				$values['contact_access'] = '-1';
			}

			$form->saveAsSettings( $values );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			\IPS\Session::i()->log( 'acplogs__contactus_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=contactus' ), 'saved' );

		}

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'r__contactus' );
		\IPS\Output::i()->output	.= \IPS\Theme::i()->getTemplate( 'global' )->block( '', $form );
	}

	/**
	 * Build the configuration form
	 *
	 * @return \IPS\Helpers\Form
	 */
	protected function _getConfigForm()
	{
		$form = new \IPS\Helpers\Form;
		$options = array();
		$toggles = array();
		$disabled = array();
		$formFields = array();

		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'contact_access', ( \IPS\Settings::i()->contact_access == '*' ) ? '*' : explode( ',', \IPS\Settings::i()->contact_access ), FALSE, array(
			'options' 	=> array_combine( array_keys( \IPS\Member\Group::groups() ), array_map( function( $_group ) { return (string) $_group; }, \IPS\Member\Group::groups() ) ),
			'multiple' 	=> true,
			'unlimited'		=> '*',
			'unlimitedLang'	=> 'everyone',
			'impliedUnlimited' => TRUE
		), NULL, NULL, NULL, 'contact_access' ) );

		/* Get extensions */
		$extensions = \IPS\Application::allExtensions( 'core', 'ContactUs', FALSE, 'core', 'InternalEmail', TRUE );

		foreach ( $extensions as $k => $class )
		{
			$class->process( $form, $formFields, $options, $toggles, $disabled );
		}

		$form->add( new \IPS\Helpers\Form\Radio( 'contact_type', \IPS\Settings::i()->contact_type, FALSE, array(
			'options' => $options,
			'toggles' => $toggles,
			'disabled' => $disabled
		) ) );

		foreach ( $formFields AS $field )
		{
			$form->add( $field );
		}

		return $form;
	}
	
	// Create new methods with the same name as the 'do' parameter which should execute it
}