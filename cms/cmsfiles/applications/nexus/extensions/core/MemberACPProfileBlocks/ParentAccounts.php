<?php
/**
 * @brief		ACP Member Profile: Parent Accounts Block
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		5 Dec 2017
 */

namespace IPS\nexus\extensions\core\MemberACPProfileBlocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: Customer Statistics Block
 */
class _ParentAccounts extends \IPS\core\MemberACPProfile\Block
{
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output()
	{
		$parents = array();
		foreach ( \IPS\Db::i()->select( 'main_id', 'nexus_alternate_contacts', array( 'alt_id=?', $this->member->member_id ) ) as $row )
		{
			$parents[] = \IPS\nexus\Customer::load( $row );
		}
		
		if ( \count( $parents ) )
		{
			return \IPS\Theme::i()->getTemplate( 'customers', 'nexus' )->parentAccounts( $parents );
		}
	}
}