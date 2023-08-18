<?php
/**
 * @brief		ACP Member Profile: Invoices
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
 * @brief	ACP Member Profile: Invoices
 */
class _Invoices extends \IPS\core\MemberACPProfile\Block
{
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output()
	{
		$invoiceCount = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_invoices', array( 'i_member=?', $this->member->member_id ) )->first();
		$invoices = \IPS\nexus\Invoice::table( array( 'i_member=?', $this->member->member_id ), $this->member->acpUrl()->setQueryString( 'tab', 'invoices' ), 'c' );
		$invoices->limit = 15;
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'invoices_add' ) )
		{
			$invoices->rootButtons = array(
				'add'	=> array(
					'link'	=> \IPS\Http\Url::internal( "app=nexus&module=payments&controller=invoices&do=generate&member={$this->member->member_id}" ),
					'title'	=> 'add',
					'icon'	=> 'plus',
				)
			);
		}
		$invoices->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'customers', 'nexus' ), 'invoicesTable' );
		$invoices->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'customers', 'nexus' ), 'invoicesTableRows' );
		
		return \IPS\Theme::i()->getTemplate( 'customers', 'nexus' )->invoices( $this->member, $invoices, $invoiceCount );
	}
}