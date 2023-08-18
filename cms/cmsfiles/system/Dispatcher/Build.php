<?php
/**
 * @brief		Build/Tools Dispatcher
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		2 Apr 2013
 */

namespace IPS\Dispatcher;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Build/Tools Dispatcher
 */
class _Build extends \IPS\Dispatcher
{
	/**
	 * @brief Controller Location
	 */
	public $controllerLocation = 'front';

	/**
	 * @brief Application
	 */
	public $application        = 'core';

	/**
	 * @brief Module
	 */
	public $module		       = 'system';
	
	/**
	 * @brief Step
	 */
	public $step = 1;
	
	/**
	 * Initiator
	 *
	 * @return	void
	 */
	public function init()
	{
		$modules = \IPS\Application\Module::modules();
		$this->application = \IPS\Application::load('core');
		$this->module      = $modules['core']['front']['system'];
		$this->controller  = 'build';
		
		return true;
	}

	/**
	 * Run
	 *
	 * @return	void
	 */
	public function run()
	{
		if ( isset( \IPS\Request::i()->force ) )
		{
			if ( isset( \IPS\Data\Store::i()->builder_building ) )
			{
				unset( \IPS\Data\Store::i()->builder_building );
			}
		}
		else
		{
			if ( isset( \IPS\Data\Store::i()->builder_building ) and ! empty( \IPS\Data\Store::i()->builder_building ) )
			{
				/* We're currently rebuilding */
				if ( time() - \IPS\Data\Store::i()->builder_building < 180  )
				{
					print "Builder is already running. To force a rebuild anyway, add &force=1 on the end of your URL";
					exit();
				}
			}
			
			\IPS\Data\Store::i()->builder_building = time();
		}
				
		\IPS\Settings::i()->changeValues( array( 'site_online' => 0 ) );
	}
	
	/**
	 * Done
	 *
	 * @return	void
	 */
	public function buildDone()
	{
		if ( isset( \IPS\Data\Store::i()->builder_building ) )
		{
			unset( \IPS\Data\Store::i()->builder_building );
		}
		
		\IPS\Settings::i()->changeValues( array( 'site_online' => 1 ) );
	}
}