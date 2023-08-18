<?php
/**
 * @brief		Upgrader: Custom Upgrade Options
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		3 Mar 2015
 */

$options = array();

foreach( \IPS\Db::i()->select( '*', 'ccs_databases' ) as $database )
{
	/* Show warning about inability to convert html */
	$options[] = new \IPS\Helpers\Form\Custom( '40000_cms_htmlconversion', null, FALSE, array( 'getHtml' => function( $element ) use ( $members ){
		return "";
	} ), function( $val ) {}, NULL, NULL, '40000_cms_htmlconversion' );

	$pages = array();

	foreach( \IPS\Db::i()->select( '*', 'ccs_pages' ) as $page )
	{
		if( $database['database_is_articles'] AND mb_strpos( $page['page_content'], "{parse articles" ) !== FALSE )
		{
			$pages[ $page['page_id'] ] = $page['page_name'] . ' (' . $page['page_seo_name'] . ')';
		}

		if( mb_strpos( $page['page_content'], "{parse database=\"{$database['database_id']}\"" ) !== FALSE OR mb_strpos( $page['page_content'], "{parse database=\"{$database['database_key']}\"" ) !== FALSE )
		{
			$pages[ $page['page_id'] ] = $page['page_name'] . ' (' . $page['page_seo_name'] . ')';
		}
	}

	if( \count( $pages ) > 1 )
	{
		\IPS\Member::loggedIn()->language()->words[ '40000_database_page_' . $database['database_id'] ] = sprintf( \IPS\Member::loggedIn()->language()->get( 'cms_database_title' ), $database['database_name'] );
		\IPS\Member::loggedIn()->language()->words[ '40000_database_page_' . $database['database_id'] . '_desc' ] = \IPS\Member::loggedIn()->language()->get( 'cms_database_desc' );

		$options[]	= new \IPS\Helpers\Form\Radio( '40000_database_page_' . $database['database_id'], 0, TRUE, array( 'options' => $pages ) );
	}
}