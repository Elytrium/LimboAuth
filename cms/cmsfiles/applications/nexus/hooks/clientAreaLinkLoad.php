//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class nexus_hook_clientAreaLinkLoad extends _HOOK_CLASS_
{
	/**
	 * Constructor
	 * Gets stores which are always needed to save individual queries
	 *
	 * @return	void
	 */
	public function __construct()
	{
		parent::__construct();
		$this->templateLoad[] = array( 'nexus', 'front', 'store' );
	}
}