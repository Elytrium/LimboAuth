//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class cms_hook_apiForum extends _HOOK_CLASS_
{
	/**
	 * Delete
	 *
	 * @param	int			$id						ID Number
	 * @param	int|NULL	$deleteChildrenOrMove	-1 to delete all child nodes, or the new parent node ID to move the children to
	 * @throws	2F363/7 	The node ID does not exist
	 * @throws	2F363/6     The target node cannot be deleted because it is in use by a CMS Database
	 * @return	\IPS\Api\Response
	 */
    protected function _delete( $id, $deleteChildrenOrMove = NULL )
    {
        $nodeClass = $this->class;

        try
        {
            $node = $nodeClass::load( $id );
        }
        catch ( \OutOfRangeException $e )
        {
            throw new \IPS\Api\Exception( 'INVALID_ID', '2F363/7', 404 );
        }

        /* isUsedByCms in cms/hooks/Forum.php */
        if ( $db = $node->isUsedByCms() )
        {
            throw new \IPS\Api\Exception( 'FORUM_USED_BY_DATABASE', '2F363/6', 403 );
        }
        else
        {
            return parent::_delete( $id, $deleteChildrenOrMove );
        }
    }
}
