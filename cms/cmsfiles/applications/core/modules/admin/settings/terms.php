<?php
/**
 * @brief		Manage Terms & Privacy Policy
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		07 Jun 2013
 */

namespace IPS\core\modules\admin\settings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * terms
 */
class _terms extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'terms_manage' );
		parent::execute();
	}

	/**
	 * Manage Terms & Privacy Policy
	 *
	 * @return	void
	 */
	protected function manage()
	{
 		$form = new \IPS\Helpers\Form;

 		static::buildForm( $form );
		
		if ( $values = $form->values() )
		{
			/* What were our previous values? */
			$existingPrivacyPolicy = iterator_to_array( \IPS\Db::i()->select( 'word_custom', 'core_sys_lang_words', array( 'word_key=?', 'privacy_text_value' ) ) );
			$existingRegistrationTerms = iterator_to_array( \IPS\Db::i()->select( 'word_custom', 'core_sys_lang_words', array( 'word_key=?', 'reg_rules_value' ) ) );
			
			/* Save */
			$values = static::processForm( $values );

			$form->saveAsSettings( $values );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();
			
			/* Log */
			\IPS\Session::i()->log( 'acplogs__terms_edited' );
			
			/* Do we need to ask the admin if they want to ask members to reconfirm? */
			$changedPrivacyPolicy = $existingPrivacyPolicy != iterator_to_array( \IPS\Db::i()->select( 'word_custom', 'core_sys_lang_words', array( 'word_key=?', 'privacy_text_value' ) ) );
			$changedRegistrationTerms = $existingRegistrationTerms != iterator_to_array( \IPS\Db::i()->select( 'word_custom', 'core_sys_lang_words', array( 'word_key=?', 'reg_rules_value' ) ) );
			if ( $changedPrivacyPolicy or $changedRegistrationTerms )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=settings&controller=terms&do=reconfirm')->setQueryString( array(
					'privacy'	=> \intval( $changedPrivacyPolicy ),
					'reg'		=> \intval( $changedRegistrationTerms )
				) ) );
			}
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_settings_terms');
		\IPS\Output::i()->output	.= \IPS\Theme::i()->getTemplate( 'global' )->block( 'menu__core_settings_terms', $form );
	}

	/**
	 * Process the text storage from submitting the form
	 *
	 * @param	array	$values		Values from form
	 * @return	array
	 */
	public static function processForm( $values )
	{
		foreach ( array( 'gl_guidelines' => 'guidelines_value', 'privacy_text' => 'privacy_text_value', 'reg_rules' => 'reg_rules_value', 'guest_terms_bar_text' => 'guest_terms_bar_text_value', 'cookie_3rdpartynotice' => 'cookie_3rdpartynotice_value' ) as $k => $v )
		{
			/* Guest terms bar text has special replacements that need to be swapped to sprintf parameters */
			if ( $k == 'guest_terms_bar_text' and $values['guest_terms_bar'] )
			{
				foreach( $values[ $k ] AS $lang_id => $text )
				{
					$values[ $k ][ $lang_id ] = str_replace( array( '{terms}', '{privacy}', '{guidelines}', '{cookies}' ), array( '%1$s', '%2$s', '%3$s', '%4$s' ), $text );
				}
			}
			
			\IPS\Lang::saveCustom( 'core', $v, $values[ $k ] );
			unset( $values[ $k ] );
		}

		/* Update the essential cookie name list */
		unset( \IPS\Data\Store::i()->essentialCookieNames );

		return $values;
	}

	/**
	 * Build the form
	 *
	 * @param	\IPS\Form	$form	Form to add settings to
	 * @return	void
	 */
	public static function buildForm( $form )
	{
		$form->addHeader( 'terms_guidelines' );
		$form->add( new \IPS\Helpers\Form\Radio( 'gl_type', \IPS\Settings::i()->gl_type, FALSE, array(
				'options' => array(
						'internal' => 'gl_internal',
						'external' => 'gl_external',
						'none' => "gl_none" ),
				'toggles' => array(
						'internal'	=> array( 'gl_guidelines_id' ),
						'external'	=> array( 'gl_link' ),
						'none'		=> array(),
				)
		) ) );
		
		$form->add( new \IPS\Helpers\Form\Translatable( 'gl_guidelines', NULL, FALSE, array( 'app' => 'core', 'key' => 'guidelines_value', 'editor' => array( 'app' => 'core', 'key' => 'Admin', 'autoSaveKey' => 'Guidelines', 'attachIds' => array( NULL, NULL, 'gl_guidelines' ) ) ), NULL, NULL, NULL, 'gl_guidelines_id' ) );
		$form->add( new \IPS\Helpers\Form\Url( 'gl_link', \IPS\Settings::i()->gl_link, FALSE, array(), NULL, NULL, NULL, 'gl_link'  ) );
		$form->addHeader( 'terms_privacy');
		$form->add( new \IPS\Helpers\Form\Radio( 'privacy_type', \IPS\Settings::i()->privacy_type, FALSE, array(
				'options' => array(
						'internal' => 'privacy_internal',
						'external' => 'privacy_external',
						'none' => "privacy_none" ),
				'toggles' => array(
						'internal'	=> array( 'privacy_text_id', 'privacy_show_processors' ),
						'external'	=> array( 'privacy_link' ),
						'none'		=> array(),
				)
		) ) );
		
		$form->add( new \IPS\Helpers\Form\Translatable( 'privacy_text', NULL, FALSE, array( 'app' => 'core', 'key' => 'privacy_text_value', 'editor' => array( 'app' => 'core', 'key' => 'Admin', 'autoSaveKey' => 'Privacy', 'attachIds' => array( NULL, NULL, 'privacy_text' ) ) ), NULL, NULL, NULL, 'privacy_text_id' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'privacy_show_processors', \IPS\Settings::i()->privacy_show_processors, FALSE, array(), NULL, NULL, NULL, 'privacy_show_processors' ) );
		
		
		
		$form->add( new \IPS\Helpers\Form\Url( 'privacy_link', \IPS\Settings::i()->privacy_link, FALSE, array(), NULL, NULL, NULL, 'privacy_link' ) );
			
		$form->addHeader( 'terms_registration' );
		$form->add( new \IPS\Helpers\Form\Translatable( 'reg_rules', NULL, FALSE, array( 'app' => 'core', 'key' => 'reg_rules_value', 'editor' => array( 'app' => 'core', 'key' => 'Admin', 'autoSaveKey' => 'RegistrationRules', 'attachIds' => array( NULL, NULL, 'reg_rules' ) ) ), NULL, NULL, NULL, 'reg_rules_id' ) );
		
		$form->addHeader( 'guest_terms_options' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'guest_terms_bar', \IPS\Settings::i()->guest_terms_bar, FALSE, array( 'togglesOn' => array( 'guest_terms_bar_text' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'guest_terms_bar_text', NULL, FALSE, array( 'app' => 'core', 'key' => 'guest_terms_bar_text_value', 'sprintf' => array( '{terms}', '{privacy}', '{guidelines}', '{cookies}' ) ), NULL, NULL, NULL, 'guest_terms_bar_text' ) );

		$form->addHeader( 'terms_cookies' );
		$form->add( new \IPS\Helpers\Form\Translatable( 'cookie_3rdpartynotice', NULL, FALSE, array( 'app' => 'core', 'key' => 'cookie_3rdpartynotice_value', 'editor' => array( 'app' => 'core', 'key' => 'Admin', 'autoSaveKey' => '3rdPartyCookies', 'attachIds' => array( NULL, NULL, 'privacy_text' ) ) ) ));
	}
	
	/**
	 * Ask the admin if they want users to re-confirm
	 *
	 * @return	void
	 */
	protected function reconfirm()
	{
		$form = new \IPS\Helpers\Form;
		
		$form->addMessage( 'admin_reconfirm_blurb' );
				
		if ( \IPS\Request::i()->reg )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'admin_reconfirm_reg_terms', FALSE ) );
		}
		
		if ( \IPS\Request::i()->privacy )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'admin_reconfirm_privacy', FALSE ) );
		}
		
		if ( $values = $form->values() )
		{
			if ( isset( $values['admin_reconfirm_reg_terms'] ) and $values['admin_reconfirm_reg_terms'] )
			{
				\IPS\Member::updateAllMembers( array( "members_bitoptions2 = members_bitoptions2 | " . \IPS\Member::$bitOptions['members_bitoptions']['members_bitoptions2']['must_reaccept_terms'] ) );
			}
			if ( isset( $values['admin_reconfirm_privacy'] ) and $values['admin_reconfirm_privacy'] )
			{
				\IPS\Member::updateAllMembers( array( "members_bitoptions2 = members_bitoptions2 | " . \IPS\Member::$bitOptions['members_bitoptions']['members_bitoptions2']['must_reaccept_privacy'] ) );
			}
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=settings&controller=terms') );
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_settings_terms');
		\IPS\Output::i()->output	= $form;
	}
}