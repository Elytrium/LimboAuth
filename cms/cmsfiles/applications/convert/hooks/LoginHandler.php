//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

abstract class convert_hook_LoginHandler extends _HOOK_CLASS_
{
	/**
	 * Get all handler classes
	 */
	public static function handlerClasses()
	{
		$handlers = parent::handlerClasses();
		$handlers[] = 'IPS\convert\Login';
		return $handlers;
	}
}
