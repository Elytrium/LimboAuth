<?php
/**
 * @brief		Editor Extension: Forums
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		08 Jan 2014
 */

namespace IPS\forums\extensions\core\EditorLocations;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Editor Extension: Forums
 */
class _Forums
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
	 * Can whatever is posted in this editor be moderated?
	 * If this returns TRUE, we must ensure the content is ran through word, link and image filters
	 *
	 * @param	\IPS\Member					$member	The member
	 * @param	\IPS\Helpers\Form\Editor	$field	The editor field
	 * @return	bool
	 */
	public function canBeModerated( $member, $field )
	{
		/* Creating/editing a forum */
		if ( preg_match( '/^forums\-(?:forum|rules|permerror)\-\d+$/', $field->options['autoSaveKey'] ) or preg_match( '/^forums\-new\-(?:forum|rules|permerror)\d*$/', $field->options['autoSaveKey'] ) )
		{
			return FALSE;
		}
		/* Creating/editing a topic/post */
		elseif ( preg_match( '/^(?:newContentItem|contentEdit|editComment|reply)\-forums\/forums\-\d+/', $field->options['autoSaveKey'] ) )
		{
			return TRUE;
		}
		/* Merging multiple posts */
		elseif ( mb_substr( $field->options['autoSaveKey'], 0, 10 ) === 'mod-merge-' )
		{
			return TRUE;
		}
		/* Unknown */
		else
		{
			if ( \IPS\IN_DEV )
			{
				throw new \RuntimeException( 'Unknown canBeModerated: ' . $field->options['autoSaveKey'] );
			}
			return FALSE;
		}
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
			if ( $id3 )
			{
				return \IPS\forums\Forum::load( $id1 )->can( 'attachments', $member );
			}
			elseif ( $id2 )
			{
				$topic = \IPS\forums\Topic::load( $id1 );

				if( $topic->isArchived() )
				{
					$post = \IPS\forums\Topic\ArchivedPost::load( $id2 );
				}
				else
				{
					$post = \IPS\forums\Topic\Post::load( $id2 );
				}
				
				if ( !$post->canView( $member ) )
				{
					return FALSE;
				}
				
				return $viewOnly or $post->container()->can( 'attachments', $member );
			}
			else
			{
				$topic = \IPS\forums\Topic::load( $id1 );
				
				if ( !$topic->canView( $member ) )
				{
					return FALSE;
				}
				
				return $viewOnly or $topic->container()->can( 'attachments', $member );
			}
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
		if ( $id3 )
		{
			return \IPS\forums\Forum::load( $id1 );
		}
		elseif ( $id2 )
		{
			$topic = \IPS\forums\Topic::load( $id1 );

			if( $topic->isArchived() )
			{
				return \IPS\forums\Topic\ArchivedPost::load( $id2 );
			}
			else
			{
				return \IPS\forums\Topic\Post::load( $id2 );
			}
		}
		else
		{
			return \IPS\forums\Topic::load( $id1 );
		}
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
		foreach( \IPS\Db::i()->select( '*', 'core_sys_lang_words', "word_key LIKE 'forums_forum_%_desc' OR word_key LIKE 'forums_forum_%_rules' OR word_key LIKE 'forums_forum_%_permerror'", 'word_id ASC', array( $offset, $max ) ) as $word )
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
		return \IPS\Db::i()->select( 'COUNT(*)', 'core_sys_lang_words', "word_key LIKE 'forums_forum_%_desc' OR word_key LIKE 'forums_forum_%_rules' OR word_key LIKE 'forums_forum_%_permerror'" )->first();
	}
}