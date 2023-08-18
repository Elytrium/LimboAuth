//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class downloads_hook_Forum extends _HOOK_CLASS_
{
	/**
	 * Delete
	 *
	 * @return	void
	 */
	protected function delete()
	{
		/* Load forum and verify that it is not used for comments */
		$nodeClass = $this->nodeClass;
		if ( \IPS\Request::i()->subnode )
		{
			$nodeClass = $nodeClass::$subnodeClass;
		}

		try
		{
			$node = $nodeClass::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S101/J', 404, '' );
		}

		/* Is any downloads category synced with this forum? */
		if ( $dbCategory = $node->isUsedByADownloadsCategory() )
		{
			\IPS\Member::loggedIn()->language()->words['downloads_forum_used'] = sprintf( \IPS\Member::loggedIn()->language()->get('downloads_forum_used'), $dbCategory->_title );
			\IPS\Output::i()->error( 'downloads_forum_used', '1D372/1', 403, '' );
		}

		return parent::delete();
	}

}
