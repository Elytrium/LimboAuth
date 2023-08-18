<?php
/**
 * @brief		Editor Extension: Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Feb 2018
 */

namespace IPS\core\extensions\core\EditorLocations;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Editor Extension: Widget, Used for all the core widgets which need an editor
 */
class _Widget
{
	/**
	 * Array containing all widgets utilizing this Extension
	 */
	protected static $widgets = array(
		'guestSignUp',
		'newsletter',
	);

	/**
	 * Can we use HTML in this editor?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool|null	NULL will cause the default value (based on the member's permissions) to be used, and is recommended in most cases. A boolean value will override that.
	 */
	public function canUseHtml( $member )
	{
		return NULL;
	}
	
	/**
	 * Can we use attachments in this editor?
	 *
	 * @param	\IPS\Member					$member	The member
	 * @param	\IPS\Helpers\Form\Editor	$field	The editor field
	 * @return	bool|null	NULL will cause the default value (based on the member's permissions) to be used, and is recommended in most cases. A boolean value will override that.
	 */
	public function canAttach( $member, $field )
	{
		return NULL;
	}
	
	/**
	 * Permission check for attachments
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	int|null	$id1		Primary ID
	 * @param	int|null	$id2		Secondary ID
	 * @param	string|null	$id3		Arbitrary data
	 * @param	array		$attachment	The attachment data
	 * @param	bool		$viewOnly	If true, just check if the user can see the attachment rather than download it
	 * @return	bool
	 */
	public function attachmentPermissionCheck( $member, $id1, $id2, $id3, $attachment, $viewOnly=FALSE )
	{
		return TRUE;
	}
	
	/**
	 * Attachment lookup
	 *
	 * @param	int|null	$id1	Primary ID
	 * @param	int|null	$id2	Secondary ID
	 * @param	string|null	$id3	Arbitrary data
	 * @return	\IPS\Http\Url|\IPS\Content|\IPS\Node\Model
	 * @throws	\LogicException
	 */
	public function attachmentLookup( $id1, $id2, $id3 )
	{
		return NULL;
	}

	/**
	 * Rebuild attachment images in non-content item areas
	 *
	 * @param	int|null	$offset	Offset to start from
	 * @param	int|null	$max	Maximum to parse
	 * @return	int			Number completed
	 * @note	This method is optional and will only be called if it exists
	 */
	public function rebuildAttachmentImages( $offset, $max )
	{
		return $this->performRebuild( $offset, $max, array( 'IPS\Text\Parser', 'rebuildAttachmentUrls' ) );
	}

	/**
	 * Rebuild content post-upgrade
	 *
	 * @param	int|null	$offset	Offset to start from
	 * @param	int|null	$max	Maximum to parse
	 * @return	int			Number completed
	 * @note	This method is optional and will only be called if it exists
	 */
	public function rebuildContent( $offset, $max )
	{
		return $this->performRebuild( $offset, $max, array( 'IPS\Text\LegacyParser', 'parseStatic' ) );
	}

	/**
	 * @brief	Use the cached image URL instead of the original URL
	 */
	protected $proxyUrl	= FALSE;

	/**
	 * Rebuild content to add or remove image proxy
	 *
	 * @param	int|null		$offset		Offset to start from
	 * @param	int|null		$max		Maximum to parse
	 * @param	bool			$proxyUrl	Use the cached image URL instead of the original URL
	 * @return	int			Number completed
	 * @note	This method is optional and will only be called if it exists
	 */
	public function rebuildImageProxy( $offset, $max, $proxyUrl = FALSE )
	{
		$this->proxyUrl = $proxyUrl;
		return $this->performRebuild( $offset, $max, 'parseImageProxy' );
	}

	/**
	 * @brief	Store lazy loading status ( true = enabled )
	 */
	protected $_lazyLoadStatus = null;

	/**
	 * Rebuild content to add or remove lazy loading
	 *
	 * @param	int|null		$offset		Offset to start from
	 * @param	int|null		$max		Maximum to parse
	 * @param	bool			$status		Enable/Disable lazy loading
	 * @return	int			Number completed
	 * @note	This method is optional and will only be called if it exists
	 */
	public function rebuildLazyLoad( $offset, $max, $status=TRUE )
	{
		$this->_lazyLoadStatus = $status;

		return $this->performRebuild( $offset, $max, 'parseLazyLoad' );
	}

	/**
	 * Perform rebuild - abstracted as the call for rebuildContent() and rebuildAttachmentImages() is nearly identical
	 *
	 * @param	int|null	$offset		Offset to start from
	 * @param	int|null	$max		Maximum to parse
	 * @param	callable	$callback	Method to call to rebuild content
	 * @return	int			Number completed
	 */
	protected function performRebuild( $offset, $max, $callback )
	{
		$did = 0;

		$areas = array( 'core_widget_areas' );
		if ( \IPS\Application::appIsEnabled('cms') )
		{
			$areas[] = 'cms_page_widget_areas';
		}

		foreach ( $areas as $table )
		{
			foreach ( \IPS\Db::i()->select( '*', $table ) as $area )
			{
				$did++;

				$widgetsColumn = $table == 'core_widget_areas' ? 'widgets' : 'area_widgets';
				$whereClause = $table == 'core_widget_areas' ? array( 'id=? AND area=?', $area['id'], $area['area'] ) : array( 'area_page_id=? AND area_area=?', $area['area_page_id'], $area['area_area'] );

				$widgets = json_decode( $area[ $widgetsColumn ], TRUE );
				$update = FALSE;

				foreach ( $widgets as $k => $widget )
				{
					if ( \in_array( $widget['key'], static::$widgets ) )
					{
						$appOrPlugin = isset( $widget['plugin'] ) ? \IPS\Plugin::load( $widget['plugin'] ) : \IPS\Application::load( $widget['app'] );

						$widgetObject = \IPS\Widget::load( $appOrPlugin, $widget['key'], $widget['unique'] );
						$key = $widgetObject::$editorKey;
						$string =$widgetObject::$editorLangKey;

						if ( isset( $widget['configuration'][ $key ] ) AND \is_array( $widget['configuration'][ $key ] ) )
						{
							foreach( $widget['configuration'][ $key ] as $contentKey => $content )
							{
								if( $rebuilt = $this->_rebuildWidgetContent( $content, $callback ) )
								{
									$widgets[ $k ]['configuration'][ $key ][ $contentKey ] = $rebuilt;
									$update = TRUE;
								}
							}
						}
						elseif ( isset( $widget['configuration'][ $key ] ) )
						{
							$rebuilt = $this->_rebuildWidgetContent( $widget['configuration'][ $key ], $callback );

							if( $rebuilt !== NULL )
							{
								$widgets[ $k ]['configuration'][ $key ] = $rebuilt;
								$update = TRUE;
							}
						}

						if ( $update )
						{
							/* Rebuild language bits */
							foreach( \IPS\Db::i()->select( '*', 'core_sys_lang_words', array( 'word_key=?', $string ), 'word_id ASC', array( $offset, $max ) ) as $word )
							{
								$rebuilt = $this->_rebuildWidgetContent( $word['word_custom'], $callback );
								\IPS\Db::i()->update( 'core_sys_lang_words', array( 'word_custom' => $rebuilt ), array( 'word_id=?', $word['word_id'] ) );
							}
						}

					}
				}
				if ( $update )
				{
					\IPS\Db::i()->update( $table, array( $widgetsColumn => json_encode( $widgets ) ), $whereClause );
				}
			}
		}

		return $did;
	}

	/**
	 * Total content count to be used in progress indicator
	 *
	 * @return	int			Total Count
	 */
	public function contentCount()
	{
		$count	= 0;

		$areas = array( 'core_widget_areas' );
		if ( \IPS\Application::appIsEnabled('cms') )
		{
			$areas[] = 'cms_page_widget_areas';
		}

		foreach ( $areas as $table )
		{
			foreach ( \IPS\Db::i()->select( '*', $table ) as $area )
			{
				$widgetsColumn = $table == 'core_widget_areas' ? 'widgets' : 'area_widgets';

				$widgets = json_decode( $area[ $widgetsColumn ], TRUE );

				foreach ( $widgets as $k => $widget )
				{
					if ( \in_array( $widget['key'], static::$widgets ) )
					{
						$count++;
					}
				}
			}
		}

		return $count;
	}

	/**
	 * Rebuild Widget Content
	 *
	 * @param 	string		$content	Content to rebuild
	 * @param	callable	$callback	Method to call to rebuild content
	 * @return	string|null				Rebuilt content
	 */
	protected function _rebuildWidgetContent( $content, $callback )
	{
		$rebuilt = NULL;
		try
		{
			if( $callback == 'parseImageProxy' )
			{
				$rebuilt = \IPS\Text\Parser::removeImageProxy( $content, $this->proxyUrl );
			}
			elseif( $callback == 'parseLazyLoad' )
			{
				$rebuilt = \IPS\Text\Parser::parseLazyLoad( $content, $this->_lazyLoadStatus );
			}
			else
			{
				$rebuilt = $callback( $content );
			}
		}
		catch( \InvalidArgumentException $e )
		{
			if( $callback[1] == 'parseStatic' AND $e->getcode() == 103014 )
			{
				$rebuilt	= preg_replace( "#\[/?([^\]]+?)\]#", '', $content );
			}
			else
			{
				throw $e;
			}
		}

		return $rebuilt;
	}
}