<?php
/**
 * @brief		Admin CP Group Form
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		12 Oct 2021
 */

namespace IPS\nexus\extensions\core\GroupForm;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Admin CP Group Form
 */
class _PreventDeletion
{
	/**
	 * Process Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @param	\IPS\Member\Group		$group	Existing Group
	 * @return	void
	 */
	public function process( &$form, $group )
	{		

	}
	
	/**
	 * Save
	 *
	 * @param	array				$values	Values from form
	 * @param	\IPS\Member\Group	$group	The group
	 * @return	void
	 */
	public function save( $values, &$group )
	{

	}

	/**
	 * Can this group be deleted?
	 *
	 * @param	\IPS\Member\Group	$group	The group
	 * @return	void
	 */
	public function canDelete( $group ) : bool
	{
		// Is this group used for group promotion after a product purchase?
		try
		{
			\IPS\Db::i()->select( '*', 'nexus_packages', ['p_primary_group=? OR ' . \IPS\Db::i()->findInSet('p_secondary_group', [$group->g_id] ), $group->g_id ] )->first();
			return FALSE;
		}
		catch(\UnderflowException $e )
		{
		}

		// Is this group used for group promotion after a subscription purchase?
		try
		{
			\IPS\Db::i()->select( '*', 'nexus_member_subscription_packages', ['sp_primary_group=? OR ' . \IPS\Db::i()->findInSet('sp_secondary_group', [$group->g_id] ), $group->g_id ] )->first();
			return FALSE;
		}
		catch(\UnderflowException $e )
		{
		}

		return TRUE;
	}
}