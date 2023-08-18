//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class cms_hook_Forum extends _HOOK_CLASS_
{


	/**
	 * [Node] Get buttons to display in tree
	 *
	 * @param	string	$url		Base URL
	 * @param	bool	$subnode	Is this a subnode?
	 * @return	array
	 */
	public function getButtons( $url, $subnode=FALSE )
	{
		$buttons = parent::getButtons( $url, $subnode );

		if ( isset( $buttons['delete'] ) AND $this->isUsedByCms() )
		{
			unset( $buttons['delete']['data'] );
		}

		return $buttons;
	}

	/**
	 * Is this Forum used by any cms category for record/comment topics?
	 *
	@return bool|\IPS\cms\Databases
	 */
	public function isUsedByCms()
	{
		foreach ( \IPS\cms\Databases::databases() as $database )
		{
			if ( $database->forum_record and $database->forum_forum and $database->forum_forum == $this->id )
			{
				return $database;
			}
		}
		return FALSE;
	}

}
