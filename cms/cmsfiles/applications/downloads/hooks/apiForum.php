//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class downloads_hook_apiForum extends _HOOK_CLASS_
{
	/**
	 * Delete
	 *
	 * @param	int			$id						ID Number
	 * @param	int|NULL	$deleteChildrenOrMove	-1 to delete all child nodes, or the new parent node ID to move the children to
	 * @throws	2F363/8     The node ID does not exist
	 * @throws	2F363/9     The target node cannot be deleted because it is in use by a Downloads Category
	 * @return	\IPS\Api\Response
	 */
	protected function _delete( $id, $deleteChildrenOrMove = NULL )
    {
	    /* Load forum and verify that it is not used for comments */
        $nodeClass = $this->class;
        if ( \IPS\Request::i()->subnode )
        {
            $nodeClass = $nodeClass::$subnodeClass;
        }

        try
        {
            $node = $nodeClass::load( $id );
        }
        catch ( \OutOfRangeException $e )
        {
	        throw new \IPS\Api\Exception( 'INVALID_ID', '2F363/8', 404 );
        }

        if ( $dbCategory = $node->isUsedByADownloadsCategory() )
        {
            throw new \IPS\Api\Exception( 'FORUM_USED_BY_DOWNLOADS', '2F363/9', 403 );
        }
        else
        {
            return parent::_delete( $id, $deleteChildrenOrMove );
        }
    }
}
