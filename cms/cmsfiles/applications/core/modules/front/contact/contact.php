<?php
/**
 * @brief		Contact Form
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Nov 2013
 */

namespace IPS\core\modules\front\contact;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Contact Form
 */
class _contact extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Is this for displaying "content"? Affects if advertisements may be shown
	 */
	public $isContentPage = FALSE;

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( !\IPS\Member::loggedIn()->canUseContactUs() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S333/1', 403, '' );
		}

		/* Execute */
		return parent::execute();
	}
	/**
	 * Method
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Get extensions */
		$extensions = \IPS\Application::allExtensions( 'core', 'ContactUs', FALSE, 'core', 'InternalEmail', TRUE );

		/* Don't let robots index this page, it has no value */
		\IPS\Output::i()->metaTags['robots'] = 'noindex';
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';

		$form = new \IPS\Helpers\Form( 'contact', 'send' );
		$form->class = 'ipsForm_vertical';
		
		$form->add( new \IPS\Helpers\Form\Editor( 'contact_text', NULL, TRUE, array(
				'app'			=> 'core',
				'key'			=> 'Contact',
				'autoSaveKey'	=> 'contact-' . \IPS\Member::loggedIn()->member_id,
		) ) );
		
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			$form->add( new \IPS\Helpers\Form\Text( 'contact_name', NULL, TRUE ) );
			$form->add( new \IPS\Helpers\Form\Email( 'email_address', NULL, TRUE, array( 'bypassProfanity' => \IPS\Helpers\Form\Text::BYPASS_PROFANITY_ALL ) ) );
			if ( \IPS\Settings::i()->bot_antispam_type !== 'none' and \IPS\Settings::i()->guest_captcha )
			{
				$form->add( new \IPS\Helpers\Form\Captcha );
			}
		}
		foreach ( $extensions as $k => $class )
		{
			$class->runBeforeFormOutput( $form );
		}
		
		if ( $values = $form->values() )
		{
			foreach ( $extensions as $k => $class )
			{
				if ( $handled = $class->handleForm( $values ) )
				{
					break;
				}
			}

			if( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( 'OK' );
			}

			\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'message_sent' );
			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'system' )->contactDone();
		}
		else
		{
			\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'contact' );
			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'system' )->contact( $form );	
		}	
		
	}
}