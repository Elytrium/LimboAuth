//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class cms_hook_Parser extends _HOOK_CLASS_
{
	/**
	 * Get URL bases (whout schema) that we'll allow iframes from
	 *
	 * @return	array
	 */
	protected static function allowedIFrameBases()
	{
		$return = parent::allowedIFrameBases();
		
		/* If the CMS root URL is not inside the IPS4 directory, then embeds will fails as the src will not be allowed */
		if ( \IPS\Settings::i()->cms_root_page_url )
		{
			$pages = iterator_to_array( \IPS\Db::i()->select( 'database_page_id', 'cms_databases', array( 'database_page_id > 0' ) ) );

			foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'cms_pages', array( \IPS\Db::i()->in( 'page_id', $pages ) ) ), 'IPS\cms\Pages\Page' ) as $page )
			{
				$return[] = str_replace( array( 'http://', 'https://' ), '', $page->url() );
			}
		}

		return $return;
	}
}
