<?php
/**
 * @brief		Base mutator class for content
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 May 2019
 */

namespace IPS\Content\Api\GraphQL;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Base mutator class for comments
 */
abstract class _ContentMutator
{
	/**
	 * Add attachments to content
	 *
	 * @param	string		$postKey		Post key
	 * @param	string		$content		Post content
	 * @return	void
	 * @throws	\DomainException	Size is too large
	 */
	protected function _addAttachmentsToContent( $postKey, &$content )
	{
		$maxTotalSize = \IPS\Helpers\Form\Editor::maxTotalAttachmentSize( \IPS\Member::loggedIn(), 0 ); // @todo Currently set to 0 because editing is not supported. Once editing is supported by GraphQL, that will need to set the correct value
				
		$fileAttachments = array();
		$totalSize = 0;
		foreach ( \IPS\Db::i()->select( '*', 'core_attachments', array( 'attach_post_key=?', $postKey ) ) as $attachment )
		{
			if ( $maxTotalSize !== NULL )
			{
				$totalSize += $attachment['attach_filesize'];
				if ( $totalSize > $maxTotalSize )
				{
					throw new \DomainException;
				}
			}
			
			$ext = mb_substr( $attachment['attach_file'], mb_strrpos( $attachment['attach_file'], '.' ) + 1 );
			if ( \in_array( mb_strtolower( $ext ), \IPS\File::$videoExtensions ) )
			{
				$content .= \IPS\Theme::i()->getTemplate( 'editor', 'core', 'global' )->attachedVideo( $attachment['attach_location'], \IPS\Http\Url::baseUrl( \IPS\Http\Url::PROTOCOL_RELATIVE ) . "applications/core/interface/file/attachment.php?id=" . $attachment['attach_id'], $attachment['attach_file'], \IPS\File::getMimeType( $attachment['attach_file'] ), $attachment['attach_id'] );
			}
			elseif ( $attachment['attach_is_image'] )
			{
				if ( $attachment['attach_thumb_location'] )
				{
					$ratio = round( ( $attachment['attach_thumb_height'] / $attachment['attach_thumb_width'] ) * 100, 2 );
					$width = $attachment['attach_thumb_width'];
				}
				else
				{
					$ratio = round( ( $attachment['attach_img_height'] / $attachment['attach_img_width'] ) * 100, 2 );
					$width = $attachment['attach_img_width'];
				}
				
				$content .= str_replace( '<fileStore.core_Attachment>', \IPS\File::getClass('core_Attachment')->baseUrl(), \IPS\Theme::i()->getTemplate( 'editor', 'core', 'global' )->attachedImage( $attachment['attach_location'], $attachment['attach_thumb_location'] ? $attachment['attach_thumb_location'] : $attachment['attach_location'], $attachment['attach_file'], $attachment['attach_id'], $width, $ratio ) );
			}
			else
			{
				$fileAttachments[] = \IPS\Theme::i()->getTemplate( 'editor', 'core', 'global' )->attachedFile( \IPS\Http\Url::baseUrl() . "applications/core/interface/file/attachment.php?id=" . $attachment['attach_id'] . ( $attachment['attach_security_key'] ? "&key={$attachment['attach_security_key']}" : '' ), $attachment['attach_file'], FALSE, $attachment['attach_ext'], $attachment['attach_id'], $attachment['attach_security_key'] );
			}
		}
		
		if( \count( $fileAttachments ) )
		{
			$content .= "<p>" . implode( ' ', $fileAttachments ) . "</p>";
		}
				
		\IPS\Db::i()->update( 'core_attachments', array( 'attach_post_key' => '' ), array( 'attach_post_key=?', $postKey ) );
	}
}