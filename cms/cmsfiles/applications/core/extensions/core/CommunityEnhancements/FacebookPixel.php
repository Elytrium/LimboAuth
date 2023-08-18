<?php
/**
 * @brief		Community Enhancement: Facebook Pixel
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		16 May 2017
 */

namespace IPS\core\extensions\core\CommunityEnhancements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Community Enhancement: Facebook Pixel
 */
class _FacebookPixel
{
	/**
	 * @brief	IPS-provided enhancement?
	 */
	public $ips	= FALSE;

	/**
	 * @brief	Enhancement is enabled?
	 */
	public $enabled	= FALSE;

	/**
	 * @brief	Enhancement has configuration options?
	 */
	public $hasOptions	= TRUE;

	/**
	 * @brief	Icon data
	 */
	public $icon	= "facebook.png";

	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->enabled = ( \IPS\Settings::i()->fb_pixel_enabled and \IPS\Settings::i()->fb_pixel_id );
	}
	
	/**
	 * Edit
	 *
	 * @return	void
	 */
	public function edit()
	{
		$validation = function( $val ) {
			if ( $val and !\IPS\Request::i()->fb_pixel_id )
			{
				throw new \DomainException('fb_pixel_id_req');
			}
		};
		
		$form = new \IPS\Helpers\Form;		
		$form->add( new \IPS\Helpers\Form\Text( 'fb_pixel_id', \IPS\Settings::i()->fb_pixel_id ? \IPS\Settings::i()->fb_pixel_id : '', FALSE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'fb_pixel_enabled', \IPS\Settings::i()->fb_pixel_enabled, FALSE, array(), $validation ) );
		$form->add( new \IPS\Helpers\Form\Number( 'fb_pixel_delay', \IPS\Settings::i()->fb_pixel_delay, FALSE, array(), $validation, NULL, \IPS\Member::loggedIn()->language()->addToStack('fb_pixel_delay_seconds') ) );
		
		if ( $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplog__enhancements_edited', array( 'enhancements__core_FacebookPixel' => TRUE ) );
			\IPS\Output::i()->inlineMessage	= \IPS\Member::loggedIn()->language()->addToStack('saved');
		}
		
		\IPS\Output::i()->sidebar['actions'] = array(
			'help'	=> array(
				'title'		=> 'learn_more',
				'icon'		=> 'question-circle',
				'link'		=> \IPS\Http\Url::ips( 'docs/facebookpixel' ),
				'target'	=> '_blank'
			),
		);
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'enhancements__core_FacebookPixel', $form );
	}
	
	/**
	 * Enable/Disable
	 *
	 * @param	$enabled	bool	Enable/Disable
	 * @return	void
	 */
	public function toggle( $enabled )
	{
		if ( $enabled )
		{
			if ( \IPS\Settings::i()->fb_pixel_id )
			{
				\IPS\Settings::i()->changeValues( array( 'fb_pixel_enabled' => 1 ) );
			}
			else
			{
				throw new \DomainException;
			}
		}
		else
		{
			\IPS\Settings::i()->changeValues( array( 'fb_pixel_enabled' => 0 ) );
		}
	}
}