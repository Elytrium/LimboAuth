<?php
/**
 * @brief		Front-end Dispatcher
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS\Dispatcher;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Dispatcher that doesn't really dispatch but sets things up for external scripts like Pages external blocks
 */
class _External extends \IPS\Dispatcher\Standard
{
	/**
	 * Controller Location
	 */
	public $controllerLocation = 'front';
	
	/**
	 * Init
	 *
	 * @return	void
	 */
	public function init()
	{
		/* Base CSS */
		static::baseCss();

		/* Base JS */
		static::baseJs();
		
		/* Run global init */
		try
		{
			parent::init();
			
			/* Don't update sessions for this hit as it will wipe location data */
			\IPS\Session::i()->noUpdate();
		}
		catch ( \DomainException $e )
		{	
			\IPS\Output::i()->error( $e->getMessage(), '2S100/' . $e->getCode(), $e->getCode() === 4 ? 403 : 404, '' );
		}
	}

	/**
	 * Output the basic javascript files every page needs
	 *
	 * @return void
	 */
	protected static function baseJs()
	{
		parent::baseJs();

		/* Stuff for output */
		if ( !\IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->globalControllers[] = 'core.front.core.app';
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front.js' ) );
		}
	}

	/**
	 * Base CSS
	 *
	 * @return	void
	 */
	public static function baseCss()
	{
		parent::baseCss();

		/* Stuff for output */
		if ( !\IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'core.css', 'core', 'front' ) );
			if ( \IPS\Theme::i()->settings['responsive'] )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'core_responsive.css', 'core', 'front' ) );
			}
		}
	}
}