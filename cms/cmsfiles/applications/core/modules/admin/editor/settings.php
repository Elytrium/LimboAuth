<?php
/**
 * @brief		settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Aug 2015
 */

namespace IPS\core\modules\admin\editor;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * settings
 */
class _settings extends \IPS\Dispatcher\Controller
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
		/* Restrictions check */
		\IPS\Dispatcher::i()->checkAcpPermission( 'settings_manage' );
		
		/* Choose active tab */
		$this->tabs = array( 'general' => 'editor_settings_general', 'advanced' => 'editor_settings_advanced' );
		if ( isset( \IPS\Request::i()->tab ) and array_key_exists( \IPS\Request::i()->tab, $this->tabs ) )
		{
			$this->activeTab = \IPS\Request::i()->tab;
		}
		else
		{
			$keys = array_keys( $this->tabs );
			$this->activeTab = array_shift( $keys );
		}
		
		/* Run */
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Work out output */
		$methodToCall		= '_manage' . mb_ucfirst( $this->activeTab );
		$activeTabContents	= $this->$methodToCall();
		
		/* If this is an AJAX request, just return it */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}
		
		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('editor_settings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global' )->tabs( $this->tabs, $this->activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=core&module=editor&controller=settings" ) );
	}
	
	/**
	 * General Settings
	 *
	 * @return	void
	 */
	protected function _manageGeneral()
	{
		$form = new \IPS\Helpers\Form( 'form' );
		$form->add( new \IPS\Helpers\Form\Radio( 'editor_paste_behaviour', \IPS\Settings::i()->editor_paste_behaviour, FALSE, array( 'options' => array(
			'rich'	=> 'editor_paste_behaviour_rich',
			'force' => 'editor_paste_behaviour_force',			
		) ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'editor_paragraph_padding', \IPS\Settings::i()->editor_paragraph_padding, FALSE, array( 'options' => array(
			1		=> 'editor_paragraph_padding_on',
			0		=> 'editor_paragraph_padding_off'
		) ) ) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'enable_bbcode', \IPS\Settings::i()->enable_bbcode, FALSE ) );

		if ( $values = $form->values() )
		{
			$clearCss = ( $values['editor_paragraph_padding'] !== \IPS\Settings::i()->editor_paragraph_padding );
			
			$form->saveAsSettings();
			
			if ( $clearCss )
			{
				\IPS\Theme::deleteCompiledCss();
			}

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=editor&controller=settings&tab=general" ), 'saved' );
		}

		/* Display */
		return $form;
	}
	
	/**
	 * Advanced Settings
	 *
	 * @return	void
	 */
	protected function _manageAdvanced()
	{
		$form = new \IPS\Helpers\Form( 'form' );
		$form->addMessage( \IPS\Member::loggedIn()->language()->addToStack('editor_allowed_formmsg') );
		$form->add( new \IPS\Helpers\Form\Stack( 'editor_allowed_classes', \IPS\Settings::i()->editor_allowed_classes ? explode( ',', \IPS\Settings::i()->editor_allowed_classes ) : array(), FALSE ) );
		$form->add( new \IPS\Helpers\Form\Stack( 'editor_allowed_datacontrollers', \IPS\Settings::i()->editor_allowed_datacontrollers ? explode( ',', \IPS\Settings::i()->editor_allowed_datacontrollers ) : array(), FALSE ) );
		$form->add( new \IPS\Helpers\Form\Stack( 'editor_allowed_iframe_bases', \IPS\Settings::i()->editor_allowed_iframe_bases ? explode( ',', \IPS\Settings::i()->editor_allowed_iframe_bases ) : array(), FALSE, array( 'placeholder' => 'example.com/embed/' ) ) );

		if ( $values = $form->values() )
		{
			$form->saveAsSettings();

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=editor&controller=settings&tab=advanced" ), 'saved' );
		}

		/* Display */
		return $form;
	}
}