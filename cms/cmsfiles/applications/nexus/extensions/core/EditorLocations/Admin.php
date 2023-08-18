<?php
/**
 * @brief		Editor Extension: Admin
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		18 Mar 2014
 */

namespace IPS\nexus\extensions\core\EditorLocations;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Editor Extension: Admin
 */
class _Admin
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
		if ( mb_substr( $field->options['autoSaveKey'], -6 ) == '-email' )
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
		try
		{
			switch ( $id3 )
			{				
				case 'pkg':
				case 'pkg-assoc':
				case 'pkg-email':
					return \IPS\nexus\Package\Item::load( $id1 )->canView( $member );
					
				case 'pkg-pg':
					$customer = \IPS\nexus\Customer::load( $member->member_id );
					if ( \count( \IPS\nexus\extensions\nexus\Item\Package::getPurchases( $customer, $id1, FALSE ) ) )
					{
						$options = array( 'type' => 'attach', 'id' => $attachment['attach_id'], 'name' => $attachment['attach_file'] );
						if ( \IPS\Request::i()->referrer() )
						{
							try
							{
								$purchase = \IPS\nexus\Purchase::loadFromUrl( \IPS\Request::i()->referrer() );

								if( !$purchase->can( 'view', $member ) )
								{
									throw new \LogicException;
								}

								$options['ps_id'] = $purchase->id;
								$options['ps_name'] = $purchase->name;
							}
							catch ( \LogicException $e ) { }
						}
						
						$customer->log( 'download', $options );
						return TRUE;
					}
					else
					{
						return FALSE;
					}
					
				case 'invoice-header';
				case 'invoice-footer':
				case 'pgroup':
					return TRUE;
				
				case 'network_status_text':
					return (bool) \IPS\Settings::i()->network_status;
			};
		}
		catch ( \OutOfRangeException $e )
		{
			return FALSE;
		}
		
		return FALSE;
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
			case 'pkg':
			case 'pkg-assoc':
			case 'pkg-pg':
			case 'pkg-email':
				return \IPS\Http\Url::internal( "app=nexus&module=store&controller=packages&subnode=1&do=form&id={$id1}", 'admin' );
				
			case 'pgroup':
				return \IPS\Http\Url::internal( "app=nexus&module=store&controller=packages&do=form&id={$id1}", 'admin' );
				
			case 'invoice-header';
			case 'invoice-footer':
				return \IPS\Http\Url::internal( 'app=nexus&module=payments&controller=invoices&do=settings', 'admin' );
		};
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
		/* We will do everything except pages first */
		if( !$offset )
		{
			/* Language bits */
			foreach( \IPS\Db::i()->select( '*', 'core_sys_lang_words', \IPS\Db::i()->in( 'word_key', array( 'nexus_com_rules_val', 'network_status_text_val' ) ) . " OR word_key LIKE 'nexus_donategoal_%_desc' OR word_key LIKE 'nexus_gateway_%_ins' OR word_key LIKE 'nexus_pgroup_%_desc' OR word_key LIKE 'nexus_package_%_desc' OR word_key LIKE 'nexus_package_%_page' OR word_key LIKE 'nexus_department_%_desc'", 'word_id ASC', array( $offset, $max ) ) as $word )
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
			
			/* Settings */
			foreach ( array( 'nexus_invoice_header', 'nexus_invoice_footer' ) as $k )
			{
				try
				{
					if( $callback == 'parseImageProxy' )
					{
						$newMessage = \IPS\Text\Parser::removeImageProxy( \IPS\Settings::i()->$k );
					}
					elseif( $callback == 'parseLazyLoad' )
					{
						$newMessage = \IPS\Text\Parser::parseLazyLoad( \IPS\Settings::i()->$k, $this->_lazyLoadStatus );
					}
					else
					{
						$newMessage = $callback( \IPS\Settings::i()->$k );
					}
				}
				catch( \InvalidArgumentException $e )
				{
					if( $callback[1] == 'parseStatic' AND $e->getcode() == 103014 )
					{
						$newMessage	= preg_replace( "#\[/?([^\]]+?)\]#", '', \IPS\Settings::i()->$k );
					}
					else
					{
						throw $e;
					}
				}
	
				if( $newMessage !== FALSE )
				{
					\IPS\Settings::i()->changeValues( array( $k => $newMessage ) );
				}
			}
		}

		/* Now do packages */
		$did	= 0;

		foreach( \IPS\Db::i()->select( '*', 'nexus_packages', null, 'p_id ASC', array( $offset, $max ) ) as $package )
		{
			$did++;

			/* Update */
			try
			{
				if( $callback == 'parseImageProxy' )
				{
					$rebuilt = \IPS\Text\Parser::removeImageProxy( $package['p_page'] );
				}
				elseif( $callback == 'parseLazyLoad' )
				{
					$rebuilt = \IPS\Text\Parser::parseLazyLoad( $package['p_page'], $this->_lazyLoadStatus );
				}
				else
				{
					$rebuilt = $callback( $package['p_page'], NULL, FALSE, 'nexus_Admin', $package['p_id'], NULL, 'pkg-pg' );
				}
			}
			catch( \InvalidArgumentException $e )
			{
				if( $callback[1] == 'parseStatic' AND $e->getcode() == 103014 )
				{
					$rebuilt	= preg_replace( "#\[/?([^\]]+?)\]#", '', $package['p_page'] );
				}
				else
				{
					throw $e;
				}
			}

			if( $rebuilt !== FALSE )
			{
				\IPS\Db::i()->update( 'nexus_packages', array( 'p_page' => $rebuilt ), array( 'p_id=?', $package['p_id'] ) );
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
		$count	= 2;

		$count	+= \IPS\Db::i()->select( 'COUNT(*) as count', 'core_sys_lang_words', \IPS\Db::i()->in( 'word_key', array( 'nexus_com_rules_val', 'network_status_text_val' ) ) . " OR word_key LIKE 'nexus_donategoal_%_desc' OR word_key LIKE 'nexus_gateway_%_ins' OR word_key LIKE 'nexus_pgroup_%_desc' OR word_key LIKE 'nexus_package_%_desc' OR word_key LIKE 'nexus_package_%_page' OR word_key LIKE 'nexus_department_%_desc'" )->first();

		$count	+= \IPS\Db::i()->select( 'COUNT(*) as count', 'nexus_packages' )->first();

		return $count;
	}
}