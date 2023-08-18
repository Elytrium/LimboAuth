<?php
/**
 * @brief		Editor Extension: Admin CP Settings, etc.
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		2 Aug 2013
 */

namespace IPS\core\extensions\core\EditorLocations;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Editor Extension: Admin CP Settings, etc.
 */
class _Admin
{
	/**
	 * Can we use HTML in this editor?
	 *
	 * @param	\IPS\Member					$member	The member
	 * @param	\IPS\Helpers\Form\Editor	$field	The editor field
	 * @return	bool|null	NULL will cause the default value (based on the member's permissions) to be used, and is recommended in most cases. A boolean value will override that.
	 */
	public function canUseHtml( $member, $field )
	{
		if ( $field->options['autoSaveKey'] === 'acp-support-request' )
		{
			return FALSE;
		}
		
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
		if ( $field->options['autoSaveKey'] === 'acp-support-request' )
		{
			return FALSE;
		}
		
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
		switch ( $id3 )
		{
			case 'appdisabled':
				return \IPS\Http\Url::internal( 'app=core&module=applications&controller=applications&id=' . \IPS\Application::load( $id1, 'app_id' )->directory . '&do=enableToggle', 'admin' );
				break;
				
			case 'bulkmail':
				return \IPS\Http\Url::internal( 'app=core&module=bulkmail&controller=bulkmail&do=preview&id=' . $id1, 'admin' );
				break;
			
			case 'site_offline_message':
				return \IPS\Http\Url::internal( 'app=core&module=settings&controller=general&searchResult=site_offline_message', 'admin' );
				break;
			
			case 'gl_guidelines':
				return \IPS\Http\Url::internal( 'app=core&module=settings&controller=terms&searchResult=gl_guidelines', 'admin' );
				break;
			
			case 'privacy_text':
				return \IPS\Http\Url::internal( 'app=core&module=settings&controller=terms&searchResult=privacy_text', 'admin' );
				break;
				
			case 'reg_rules':
				return \IPS\Http\Url::internal( 'app=core&module=settings&controller=terms&searchResult=reg_rules', 'admin' );
				break;
			
			case 'announcement':
				return \IPS\core\Announcements\Announcement::load( $id1 );
				break;
				
			case 'forumsSavedAction':
				return \IPS\Http\Url::internal( 'app=forums&module=forums&controller=savedActions&do=form&id=' . $id1, 'admin' );
				break;

			case 'editor_stored_replies':
				return \IPS\Http\Url::internal( 'app=core&module=editor&controller=storedreplies&do=form&id=' . $id1, 'admin' );
				break;
		}
		
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
		/* We will do everything except bulk mails first */
		if( !$offset )
		{
			/* Language bits */
			foreach( \IPS\Db::i()->select( '*', 'core_sys_lang_words', "word_key IN('guidelines_value', 'reg_rules_value', 'privacy_text_value')" ) as $word )
			{
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

			/* Site offline message setting */
			try
			{
				if( $callback == 'parseImageProxy' )
				{
					$newMessage = \IPS\Text\Parser::removeImageProxy( \IPS\Settings::i()->site_offline_message );
				}
				elseif( $callback == 'parseLazyLoad' )
				{
					$newMessage = \IPS\Text\Parser::parseLazyLoad( \IPS\Settings::i()->site_offline_message, $this->_lazyLoadStatus );
				}
				else
				{
					$newMessage = $callback( \IPS\Settings::i()->site_offline_message );
				}
			}
			catch( \InvalidArgumentException $e )
			{
				if( $callback[1] == 'parseStatic' AND $e->getcode() == 103014 )
				{
					$newMessage	= preg_replace( "#\[/?([^\]]+?)\]#", '', \IPS\Settings::i()->site_offline_message );
				}
				else
				{
					throw $e;
				}
			}

			if( $newMessage !== FALSE )
			{
				\IPS\Settings::i()->changeValues( array( 'site_offline_message' => $newMessage ) );
			}

			/* Application disabled messages */
			foreach( \IPS\Db::i()->select( '*', 'core_applications' ) as $application )
			{
				try
				{
					if( $callback == 'parseImageProxy' )
					{
						$rebuilt = \IPS\Text\Parser::removeImageProxy( $application['app_disabled_message'] );
					}
					elseif( $callback == 'parseLazyLoad' )
					{
						$rebuilt = \IPS\Text\Parser::parseLazyLoad( $application['app_disabled_message'], $this->_lazyLoadStatus );
					}
					else
					{
						$rebuilt	= $callback( $application['app_disabled_message'] );
					}
				}
				catch( \InvalidArgumentException $e )
				{
					if( $callback[1] == 'parseStatic' AND $e->getcode() == 103014 )
					{
						$rebuilt	= preg_replace( "#\[/?([^\]]+?)\]#", '', $application['app_disabled_message'] );
					}
					else
					{
						throw $e;
					}
				}

				if( $rebuilt !== FALSE )
				{
					\IPS\Db::i()->update( 'core_applications', array( 'app_disabled_message' => $rebuilt ), 'app_id=' . $application['app_id'] );
				}
			}

			/* Forum multimod */
			if( \IPS\Db::i()->checkForTable( 'forums_topic_mmod' ) )
			{
				foreach( \IPS\Db::i()->select( '*', 'forums_topic_mmod' ) as $mmod )
				{
					try
					{
						if( $callback == 'parseImageProxy' )
						{
							$rebuilt = \IPS\Text\Parser::removeImageProxy( $mmod['topic_reply_content'] );
						}
						elseif( $callback == 'parseLazyLoad' )
						{
							$rebuilt = \IPS\Text\Parser::parseLazyLoad( $mmod['topic_reply_content'], $this->_lazyLoadStatus );
						}
						else
						{
							$rebuilt = $callback( $mmod['topic_reply_content'] );
						}
					}
					catch( \InvalidArgumentException $e )
					{
						if( $callback[1] == 'parseStatic' AND $e->getcode() == 103014 )
						{
							$rebuilt	= preg_replace( "#\[/?([^\]]+?)\]#", '', $mmod['topic_reply_content'] );
						}
						else
						{
							throw $e;
						}
					}

					if( $rebuilt !== FALSE )
					{
						\IPS\Db::i()->update( 'forums_topic_mmod', array( 'topic_reply_content' => $rebuilt ), 'mm_id=' . $mmod['mm_id'] );
					}
				}
			}
		}

		/* Now do bulk mails */
		$did	= 0;

		foreach( \IPS\Db::i()->select( '*', 'core_bulk_mail', null, 'mail_id ASC', array( $offset, $max ) ) as $mail )
		{
			$did++;

			/* Update */
			try
			{
				if( $callback == 'parseImageProxy' )
				{
					$rebuilt = \IPS\Text\Parser::removeImageProxy( $mail['mail_content'] );
				}
				elseif( $callback == 'parseLazyLoad' )
				{
					$rebuilt = \IPS\Text\Parser::parseLazyLoad( $mail['mail_content'], $this->_lazyLoadStatus );
				}
				else
				{
					$rebuilt = $callback( $mail['mail_content'] );
				}
				
			}
			catch( \InvalidArgumentException $e )
			{
				if( $callback[1] == 'parseStatic' AND $e->getcode() == 103014 )
				{
					$rebuilt	= preg_replace( "#\[/?([^\]]+?)\]#", '', $mail['mail_content'] );
				}
				else
				{
					throw $e;
				}
			}

			if( $rebuilt !== FALSE )
			{
				\IPS\Db::i()->update( 'core_bulk_mail', array( 'mail_content' => $rebuilt ), array( 'mail_id=?', $mail['mail_id'] ) );
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
		$count	= 4;

		$count	+= \IPS\Db::i()->select( 'COUNT(*) as count', 'core_applications' )->first();

		if( \IPS\Db::i()->checkForTable( 'forums_topic_mmod' ) )
		{
			$count	+= \IPS\Db::i()->select( 'COUNT(*) as count', 'forums_topic_mmod' )->first();
		}

		$count	+= \IPS\Db::i()->select( 'COUNT(*) as count', 'core_bulk_mail' )->first();

		return $count;
	}
}