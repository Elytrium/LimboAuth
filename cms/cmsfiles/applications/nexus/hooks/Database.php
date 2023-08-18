//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class nexus_hook_Database extends _HOOK_CLASS_
{
	/**
	 * Constructor
	 * Gets stores which are always needed to save individual queries
	 *
	 */
	public function __construct()
	{
		$this->initLoad[] = 'nexusPackagesWithReviews';

		/* Hand over to normal method */
		parent::__construct();
	}
}
