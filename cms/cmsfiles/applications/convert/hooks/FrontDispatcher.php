//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class convert_hook_FrontDispatcher extends _HOOK_CLASS_
{
	/**
	  * Check whether the URL we visited is correct and route appropriately
	  *
	  * @return void
	  */
	protected function checkUrl()
	{
		/* Let the parent do its thing first */
		try
		{
			parent::checkUrl();
		}
		/* If we are here, the URL was not valid. Let's see if we need to do anything for converted sites */
		catch( \OutOfRangeException $e )
		{
			$application = \IPS\Application::load('convert');
			$application::checkRedirects();

			/* If we are still here, let the exception bubble up */
			throw $e;
		}
	}
}
