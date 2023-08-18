<?php
/**
 * @brief		Meta Data: ItemModeration
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		01 May 2020
 */

namespace IPS\core\extensions\core\MetaData;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Meta Data: ItemModeration
 */
class _ItemModeration
{
	/**
	 * Check if an item is set to require approval for new comments.
	 *
	 * @param	\IPS\Content\Item	$item			The item.
	 * @param	\IPS\Member|\IPS\Member\Group|NULL	If set, will check if this member or group can bypass moderation.
	 * @return	bool
	 */
	public function enabled( \IPS\Content\Item $item, $memberOrGroup=NULL ): bool
	{
		/* Extract our meta data */
		$meta = $item->getMeta();
		
		/* Is it set in the meta? */
		if ( isset( $meta['core_ItemModeration'] ) )
		{
			/* This is only set once per item. */
			$data = \array_shift( $meta['core_ItemModeration'] );
			
			if ( $data['enabled'] AND $memberOrGroup !== NULL )
			{
				if ( $memberOrGroup instanceof \IPS\Member )
				{
					$check = $memberOrGroup->group['g_avoid_q'];
				}
				elseif ( $memberOrGroup instanceof \IPS\Member\Group )
				{
					$check = $memberOrGroup->g_avoid_q;
				}
				
				if ( $check )
				{
					return FALSE;
				}
				else
				{
					return TRUE;
				}
			}
			else
			{
				return (bool) $data['enabled'];
			}
		}
		
		/* Not set, so just return */
		return FALSE;
	}
	
	/**
	 * Can Toggle
	 *
	 * @param	\IPS\Content\Item		$item	The item
	 * @param	\IPS\Member|NULL			$member	The member to check, or NULL for currently logged in member.
	 * @return	bool
	 */
	public function canToggle( \IPS\Content\Item $item, ?\IPS\Member $member = NULL ): bool
	{
		if ( !( $item instanceof \IPS\Content\MetaData ) )
		{
			throw new \BadMethodCallException;
		}
		
		if ( !\in_array( 'core_ItemModeration', $item::supportedMetaDataTypes() ) )
		{
			return FALSE;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		
		try
		{
			return $item::modPermission( 'toggle_item_moderation', $member, $item->container() );
		}
		catch( \BadMethodCallException $e )
		{
			return $member->modPermission( 'can_toggle_item_moderation_content' );
		}
	}
	
	/**
	 * Enable
	 *
	 * @param	\IPS\Content\Item	$item	The item
	 * @param	\IPS\Member|NULL		$member	The member enabling moderation, or NULL for currently logged in member.
	 * @return	void
	 */
	public function enable( \IPS\Content\Item $item, ?\IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		$meta = $item->getMeta();
		
		/* If it's set in the data, update it. */
		if ( isset( $meta['core_ItemModeration'] ) )
		{
			$id = \array_shift( \array_keys( $meta['core_ItemModeration'] ) );
			$item->editMeta( $id, array(
				'enabled'	=> true,
				'member'	=> $member->member_id
			) );
		}
		/* Otherwise add it */
		else
		{
			$item->addMeta( 'core_ItemModeration', array(
				'enabled'	=> true,
				'member'	=> $member->member_id
			) );
		}
	}
	
	/**
	 * Disable
	 *
	 * @param	\IPS\Content\Item	$item	The item
	 * @return	void
	 * @throws	\OutOfRangeException
	 */
	public function disable( \IPS\Content\Item $item )
	{
		$meta = $item->getMeta();
		
		if ( isset( $meta['core_ItemModeration'] ) )
		{
			/* Technically, this should only be stored once, but for sanity reasons just loop and remove any extras that may have slipped in */
			foreach( $meta['core_ItemModeration'] AS $id => $data )
			{
				$item->deleteMeta( $id );
			}
		}
		else
		{
			/* Not set, so throw */
			throw new \OutOfRangeException;
		}
	}
}