<?php
/**
 * @brief		Trait for login handlers which redirect the user after clicking a button
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 May 2017
 */

namespace IPS\Login\Handler;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Abstract Login Handler for handlers which redirect the user after clicking a button
 */
trait ButtonHandler
{
	/**
	 * Get type
	 *
	 * @return	int
	 */
	public function type()
	{
		return \IPS\Login::TYPE_BUTTON;
	}
	
	/**
	 * Get button
	 *
	 * @return	string
	 */
	public function button()
	{
		return \IPS\Theme::i()->getTemplate( 'login', 'core', 'global' )->loginButton( $this );
	}
		
	/**
	 * Authenticate
	 *
	 * @param	\IPS\Login	$login				The login object
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	abstract public function authenticateButton( \IPS\Login $login );
	
	/**
	 * Get the button color
	 *
	 * @return	string
	 */
	abstract public function buttonColor();
	
	/**
	 * Get the button icon
	 *
	 * @return	string
	 */
	abstract public function buttonIcon();
	
	/**
	 * Get button text
	 *
	 * @return	string
	 */
	abstract public function buttonText();

	/**
	 * Get button class
	 *
	 * @return	string
	 */
	public function buttonClass()
	{
		return '';
	}
	
	/**
	 * Get logo to display in user cp sidebar
	 *
	 * @return	\IPS\Http\Url
	 */
	public function logoForUcp()
	{
		return $this->buttonIcon();
	}

}