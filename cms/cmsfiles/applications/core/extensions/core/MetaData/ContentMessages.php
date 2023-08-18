<?php
/**
 * @brief		Meta Data: Content Item Messages
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		04 Dec 2016
 */

namespace IPS\core\extensions\core\MetaData;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Meta Data: Content Item Messages
 */
class _ContentMessages
{
	/**
	 * Can perform an action on a message
	 *
	 * @param	string				$action		The action
	 * @param	\IPS\Content\Item	$item		The content item
	 * @param	\IPS\Member|NULL	$member		The member, or NULL for currently logged in
	 * @return	bool
	 */
	public function canOnMessage( $action, \IPS\Content\Item $item, \IPS\Member $member = NULL )
	{
		if ( !( $item instanceof \IPS\Content\MetaData ) )
		{
			return FALSE;
		}
		
		if ( !\in_array( 'core_ContentMessages', $item::supportedMetaDataTypes() ) )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( !$member->member_id )
		{
			return FALSE;
		}

		if( $action === 'viewHidden' )
		{
			try
			{
				return $item::modPermission( 'view_hidden', $member, $item->container() );
			}
			catch( \BadMethodCallException $e )
			{
				return $item::modPermission( 'view_hidden', $member );
			}
		}
		
		try
		{
			return $item::modPermission( "{$action}_item_message", $member, $item->container() );
		}
		catch( \BadMethodCallException $e )
		{
			return $member->modPermission( "can_{$action}_item_message" );
		}
		
		return FALSE;
	}

	/**
	 * Add Item Message
	 *
	 * @param	string				$message		The message
	 * @param	string|NULL			$color			The message color
	 * @param	\IPS\Content\Item	$item			The content item
	 * @param	\IPS|Member|NULL	$member			User adding the message
	 * @param	bool				$isPublic		Who should see the message
	 * @return	int
	 */
	public function addMessage( $message, $color, \IPS\Content\Item $item, \IPS\Member $member = NULL, bool $isPublic = TRUE )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		$id = $item->addMeta( 'core_ContentMessages', array(
			'message'	=> $message,
			'color'		=> $color,
			'added_by'	=> $member->member_id,
			'is_public' => $isPublic,
			'date'			=> time(),
		) );
		
		return $id;
	}
	
	/**
	 * Edit Item Message
	 *
	 * @param	int					$id			The ID
	 * @param	string				$message	The new message
	 * @param	string|NULL			$color		The message color
	 * @param	\IPS\Content\Item	$item		The content item
	 * @param	\IPS\Member|NULL	$member		The member editing the message, or NULL for currently logged in
	 * @param	bool				$isPublic		Who should see the message
	 * @return	void
	 */
	public function editMessage( $id, $message, $color, \IPS\Content\Item $item, \IPS\Member $member = NULL, bool $isPublic = TRUE )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		$item->editMeta( $id, array(
			'message'	=> $message,
			'color'		=> $color,
			'edited_by'	=> $member->member_id,
			'is_public' => $isPublic
		) );
	}
	
	/**
	 * Delete Item Message
	 *
	 * @param	int					$id			The ID
	 * @param	\IPS\Content\Item	$item		The content item
	 * @param	\IPS\Member|NULL	$member		The member deleting the message
	 */
	public function deleteMessage( $id, \IPS\Content\Item $item, \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		$item->deleteMeta( $id );
	}
	
	/**
	 * Get Item Messages
	 *
	 * @param	\IPS\Content\Item	$item	The content item
	 * @return	array
	 */
	public function getMessages( \IPS\Content\Item $item, \IPS\Member $member = NULL )
	{
		if ( $meta = $item->getMeta() AND isset( $meta['core_ContentMessages'] ) )
		{
			$member = $member ?: \IPS\Member::loggedIn();

			/* None moderators see only public messages */
			if ( !$this->canOnMessage('viewHidden', $item, $member) )
			{
				$a = array_filter( $meta['core_ContentMessages'], function( $message )
				{
					return ( !isset( $message['is_public']) OR $message['is_public'] ) ;
				} );
			return $a;
			}
			else
			{
				return $meta['core_ContentMessages'];
			}

		}
		
		return array();
	}
}