<?php
/**
 * @brief		Core AJAX Responders
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Mar 2013
 */

namespace IPS\core\modules\admin\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Core AJAX Responders
 */
class _ajax extends \IPS\core\modules\front\system\ajax
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Save ACP Tabs
	 *
	 * @return	void
	 */
	protected function saveTabs()
	{
		\IPS\Session::i()->csrfCheck();
		
		if ( \is_array( \IPS\Request::i()->tabOrder ) )
		{
			$tabs	= array();

			foreach( \IPS\Request::i()->tabOrder as $topLevelTab )
			{
				$tabs[ str_replace( "tab_", "", $topLevelTab ) ]	= ( isset( \IPS\Request::i()->menuOrder[ $topLevelTab ] ) ) ? \IPS\Request::i()->menuOrder[ $topLevelTab ] : array();
			}
			
			$tabs = json_encode( $tabs );

			\IPS\Db::i()->insert( 'core_acp_tab_order', array( 'id' => \IPS\Member::loggedIn()->member_id, 'data' => $tabs ), TRUE );
			
			\IPS\Request::i()->setCookie( 'acpTabs', $tabs );
		}
		
		\IPS\Output::i()->json( 'ok' );
	}
	
	/**
	 * Save search keywords
	 *
	 * @return	void
	 */
	protected function searchKeywords()
	{
		\IPS\Session::i()->csrfCheck();
		
		if ( \IPS\IN_DEV )
		{
			$url = base64_decode( \IPS\Request::i()->url );
			$qs = array();
			parse_str( $url, $qs );
			
			\IPS\Db::i()->delete( 'core_acp_search_index', array( 'url=?', $url ) );
			
			$inserts = array();

			foreach ( \IPS\Request::i()->keywords as $word )
			{
				$inserts[] = array(
					'url'			=> $url,
					'keyword'		=> $word,
					'app'			=> $qs['app'],
					'lang_key'		=> \IPS\Request::i()->lang_key,
					'restriction'	=> \IPS\Request::i()->restriction ?: NULL
				);
			}
			
			if( \count( $inserts ) )
			{
				\IPS\Db::i()->insert( 'core_acp_search_index', $inserts );
			}

			$keywords = array();
			foreach ( \IPS\Db::i()->select( '*', 'core_acp_search_index', array( 'app=?', $qs['app'] ), 'url ASC, keyword ASC' ) as $word )
			{
				$keywords[ $word['url'] ]['lang_key'] = $word['lang_key'];
				$keywords[ $word['url'] ]['restriction'] = $word['restriction'];
				$keywords[ $word['url'] ]['keywords'][] = $word['keyword'];
			}

			/* Make sure the keywords are unique */
			foreach( $keywords as $url => $entry )
			{
				$keywords[ $url ]['keywords'] = array_unique( $entry['keywords'] );
			}

			\file_put_contents( \IPS\ROOT_PATH . "/applications/{$qs['app']}/data/acpsearch.json", json_encode( $keywords, JSON_PRETTY_PRINT ) );
		}
		
		\IPS\Output::i()->json( 'ok' );
	}
}