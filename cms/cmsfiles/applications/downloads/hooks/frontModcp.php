//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class downloads_hook_frontModcp extends _HOOK_CLASS_
{

	/**
	 * Get hidden content types
	 *
	 * @return	array
	 */
	protected function _getContentTypes(): array
	{
		$parent = parent::_getContentTypes();
		unset( $parent['downloads_downloads']['downloads_file_pendingversion'] );

		return $parent;
	}
}
