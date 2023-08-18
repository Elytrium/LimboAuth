//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class downloads_hook_Forums extends _HOOK_CLASS_
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

		if ( isset( $buttons['delete'] ) AND $this->isUsedByADownloadsCategory() )
		{
			unset( $buttons['delete']['data'] );
		}

		return $buttons;
	}

	/**
	 * Is this Forum used by any downloads app category ?
	 *
	 * @return bool|\IPS\downloads\Category
	 */
	public function isUsedByADownloadsCategory()
	{
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'downloads_categories' ), 'IPS\downloads\Category' ) AS $category )
		{
			if ( $category->forum_id and $category->forum_id == $this->id )
			{
				return $category;
			}
		}
		return FALSE;
	}

}
