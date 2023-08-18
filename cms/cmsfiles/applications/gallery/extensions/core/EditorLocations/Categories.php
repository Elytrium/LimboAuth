<?php
/**
 * @brief		Editor Extension: Categories
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Mar 2014
 */

namespace IPS\gallery\extensions\core\EditorLocations;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Editor Extension: Categories
 */
class _Categories
{
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
		try
		{
			$category = \IPS\gallery\Category::load( $id1 );
			return $category->can( 'view', $member );
		}
		catch ( \OutOfRangeException $e )
		{
			return FALSE;
		}
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
		return \IPS\gallery\Category::load( $id1 );
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
		$did	= 0;

		/* Language bits */
		foreach( \IPS\Db::i()->select( '*', 'core_sys_lang_words', "word_key LIKE 'gallery_album_%_desc' OR word_key LIKE 'gallery_category_%_desc' OR word_key LIKE 'gallery_category_%_permerror' OR word_key LIKE 'gallery_category_%_rules'", 'word_id ASC', array( $offset, $max ) ) as $word )
		{
			$did++;
			
			try
			{
				if( $callback == 'parseImageProxy' )
				{
					$rebuilt = \IPS\Text\Parser::removeImageProxy( $word['word_custom'], $this->proxyUrl );
				}
				elseif( $callback == 'parseLazyLoad' )
				{
					$rebuilt = \IPS\Text\Parser::parseLazyLoad( $word['word_custom'], $this->_lazyLoadStatus );
				}
				else
				{
					$rebuilt = $callback( $word['word_custom'] );
				}
			}
			catch( \InvalidArgumentException $e )
			{
				if( $callback[1] == 'parseStatic' AND $e->getcode() == 103014 )
				{
					$rebuilt	= preg_replace( "#\[/?([^\]]+?)\]#", '', $word['word_custom'] );
				}
				else
				{
					throw $e;
				}
			}

			if( $rebuilt !== FALSE )
			{
				\IPS\Db::i()->update( 'core_sys_lang_words', array( 'word_custom' => $rebuilt ), 'word_id=' . $word['word_id'] );
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
		return \IPS\Db::i()->select( 'COUNT(*)', 'core_sys_lang_words', "word_key LIKE 'gallery_album_%_desc' OR word_key LIKE 'gallery_category_%_desc' OR word_key LIKE 'gallery_category_%_permerror' OR word_key LIKE 'gallery_category_%_rules'" )->first();
	}
}