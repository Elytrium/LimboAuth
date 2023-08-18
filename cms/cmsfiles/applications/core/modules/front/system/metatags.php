<?php
/**
 * @brief		Live meta tag editor
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		4 Sept 2013
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Live meta tag editor
 */
class _metatags extends \IPS\Dispatcher\Controller
{
	/**
	 * Redirect the request appropriately
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$this->_checkPermissions();

		$_SESSION['live_meta_tags']	= TRUE;

		\IPS\Output::i()->redirect( \IPS\Http\Url::external( \IPS\Settings::i()->base_url ) );
	}

	/**
	 * Save a meta tag
	 *
	 * @return	void
	 */
	protected function save()
	{
		/* Check permissions and CSRF */
		$this->_checkPermissions();

		\IPS\Session::i()->csrfCheck();

		/* Delete any existing database entries, as we are about to re-insert */
		\IPS\Db::i()->delete( 'core_seo_meta', array( 'meta_url=?', \IPS\Request::i()->meta_url ) );
		\IPS\Db::i()->delete( 'core_seo_meta', array( 'meta_url=?', trim( \IPS\Request::i()->meta_url, '/' ) ) );

		/* Start save array */
		$save	= array(
			'meta_url'		=> \IPS\Request::i()->meta_url,
			'meta_title'	=> \IPS\Request::i()->meta_tag_title,
		);

		$_tags	= array();

		$metaTagNames		= \IPS\Request::i()->meta_tag_name;
		$metaTagCustomNames = \IPS\Request::i()->meta_tag_name_other;
		$metaTagValues		= \IPS\Request::i()->meta_tag_content;

		/* Remove any default meta tags that have not been edited - don't save them permanently */
		if( isset( \IPS\Request::i()->defaultMetaTag ) )
		{
			foreach( \IPS\Request::i()->defaultMetaTag as $k => $v )
			{
				if( ( $key = array_search( $k, $metaTagNames ) ) !== FALSE AND $metaTagValues[ $key ] == $v )
				{
					unset( $metaTagNames[ $key ], $metaTagCustomNames[ $key ], $metaTagValues[ $key ] );
				}
				elseif( ( $key = array_search( $k, $metaTagCustomNames ) ) !== FALSE AND $metaTagValues[ $key ] == $v )
				{
					unset( $metaTagNames[ $key ], $metaTagCustomNames[ $key ], $metaTagValues[ $key ] );
				}
			}
		}

		/* If we asked to remove a default meta tag, store specially so we can do so */
		if( isset( \IPS\Request::i()->deleteDefaultMeta ) )
		{
			foreach( \IPS\Request::i()->deleteDefaultMeta as $v )
			{
				$_tags[ $v ] = NULL;

				if( ( $key = array_search( $v, $metaTagNames ) ) !== FALSE )
				{
					unset( $metaTagNames[ $key ], $metaTagCustomNames[ $key ], $metaTagValues[ $key ] );
				}
				elseif( ( $key = array_search( $v, $metaTagCustomNames ) ) !== FALSE )
				{
					unset( $metaTagNames[ $key ], $metaTagCustomNames[ $key ], $metaTagValues[ $key ] );
				}
			}
		}

		/* Store the new meta tags */
		if( \is_array( $metaTagNames ) )
		{
			foreach( $metaTagNames as $k => $v )
			{
				if( $v AND ( $v != 'other' OR !empty( $metaTagCustomNames[ $k ] ) ) AND !isset( $_tags[ $v != 'other' ? $v : $metaTagCustomNames[ $k ] ] ) )
				{
					$_tags[ ( $v != 'other' ) ? $v : $metaTagCustomNames[ $k ] ]	= $metaTagValues[ $k ];
				}
			}
		}

		/* Save the meta tags, if there are any to save */
		if( \count( $_tags ) OR \IPS\Request::i()->meta_tag_title != '' )
		{
			$save['meta_tags']	= json_encode( $_tags );

			\IPS\Db::i()->insert( 'core_seo_meta', $save );
		}

		unset( \IPS\Data\Store::i()->metaTags );

		/* Send back to the page */
		if( \IPS\Request::i()->isAjax() )
		{
			return;
		}

		\IPS\Output::i()->redirect( \IPS\Http\Url::external( \IPS\Settings::i()->base_url . \IPS\Request::i()->url ) );
	}

	/**
	 * Stop editing meta tags
	 *
	 * @return	void
	 */
	protected function end()
	{
		\IPS\Session::i()->csrfCheck();
		
		$_SESSION['live_meta_tags']	= FALSE;

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( \IPS\Request::i()->url ) );
	}

	/**
	 * Check permissions to use the tool
	 *
	 * @return	void
	 */
	protected function _checkPermissions()
	{
		if( !\IPS\Member::loggedIn()->member_id OR !\IPS\Member::loggedIn()->isAdmin() )
		{
			\IPS\Output::i()->error( 'meta_editor_no_admin', '2C155/1', 403, '' );
		}

		if( !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'promotion', 'seo_manage' ) )
		{
			\IPS\Output::i()->error( 'meta_editor_no_acpperm', '3C155/2', 403, '' );
		}
	}

	/**
	 * Output the web manifest file
	 *
	 * @return	void
	 */
	protected function manifest()
	{
		if( \IPS\IN_DEV === FALSE AND isset( \IPS\Data\Store::i()->manifest ) )
		{
			$output = \IPS\Data\Store::i()->manifest;
		}
		else
		{
			$manifest = json_decode( \IPS\Settings::i()->manifest_details, TRUE );

			$output	= array(
				'scope'				=> rtrim( \IPS\Settings::i()->base_url, '/' ) . '/',
				'name'				=> \IPS\Settings::i()->board_name,
				'theme_color'		=> \IPS\Theme::i()->settings['header']
			);

			foreach( $manifest as $k => $v )
			{
				if( $v )
				{
					$output[ $k ] = $v;
				}
			}

			$homeScreen = json_decode( \IPS\Settings::i()->icons_homescreen, TRUE ) ?? array();
			$homeScreenMaskable = json_decode( \IPS\Settings::i()->icons_homescreen_maskable, TRUE ) ?? array();

			foreach( $homeScreen as $k => $v )
			{
				if( mb_strpos( $k, 'android' ) !== FALSE )
				{
					if( !isset( $output['icons'] ) )
					{
						$output['icons']	= array();
					}

					$file = \IPS\File::get( 'core_Icons', $v['url'] );

					$output['icons'][] = array(
						'src'	=> (string) $file->url,
						'type'	=> \IPS\File::getMimeType( $file->originalFilename ),
						'sizes'	=> $v['width'] . 'x' . $v['height'],
						'purpose' => 'any'
					);;
				}
			}

			foreach( $homeScreenMaskable as $k => $v )
			{
				if( mb_strpos( $k, 'android' ) !== FALSE )
				{
					if( !isset( $output['icons'] ) )
					{
						$output['icons']	= array();
					}

					$file = \IPS\File::get( 'core_Icons', $v['url'] );

					$output['icons'][] = array(
						'src'	=> (string) $file->url,
						'type'	=> \IPS\File::getMimeType( $file->originalFilename ),
						'sizes'	=> $v['width'] . 'x' . $v['height'],
						'purpose' => 'maskable'
					);;
				}
			}

			\IPS\Data\Store::i()->manifest = $output;
		}

		$cacheHeaders	= ( \IPS\IN_DEV !== true AND \IPS\Theme::designersModeEnabled() !== true ) ? \IPS\Output::getCacheHeaders( time(), 86400 ) : array();
		
		\IPS\Output::i()->sendOutput( json_encode( $output, JSON_PRETTY_PRINT ), 200, 'application/manifest+json', $cacheHeaders );
	}

	/**
	 * Output the IE browserconfig.xml file
	 *
	 * @return	void
	 */
	protected function iebrowserconfig()
	{
		if( \IPS\IN_DEV === FALSE AND isset( \IPS\Data\Store::i()->iebrowserconfig ) )
		{
			$output = \IPS\Data\Store::i()->iebrowserconfig;
		}
		else
		{
			$manifest = json_decode( \IPS\Settings::i()->manifest_details, TRUE );

			/* Init */
			$xml = new \XMLWriter;
			$xml->openMemory();
			$xml->setIndent( TRUE );
			$xml->startDocument( '1.0', 'UTF-8' );
			$xml->startElement( 'browserconfig' );
			$xml->startElement( 'msapplication' );
			$xml->startElement( 'tile' );

			if( isset( $manifest['theme_color'] ) AND $manifest['theme_color'] )
			{
				$xml->writeElement( 'TileColor', $manifest['theme_color'] );
			}

			$homeScreen = json_decode( \IPS\Settings::i()->icons_homescreen, TRUE ) ?? array();

			foreach( $homeScreen as $k => $v )
			{
				if( mb_strpos( $k, 'msapplication' ) !== FALSE AND $k !== 'msapplication-TileImage' )
				{
					$file = \IPS\File::get( 'core_Icons', $v['url'] );

					$xml->startElement( str_replace( 'msapplication-', '', $k ) );
					$xml->startAttribute( 'src' );
					$xml->text( (string) $file->url );
					$xml->endAttribute();
					$xml->endElement();
				}
			}

			$xml->endElement();
			$xml->endElement();
			$xml->endElement();
			$xml->endDocument();

			$output	= $xml->outputMemory();

			\IPS\Data\Store::i()->iebrowserconfig = $output;
		}

		$cacheHeaders	= ( \IPS\IN_DEV !== true AND \IPS\Theme::designersModeEnabled() !== true ) ? \IPS\Output::getCacheHeaders( time(), 86400 ) : array();
		
		\IPS\Output::i()->sendOutput( $output, 200, 'application/xml', $cacheHeaders );
	}
}