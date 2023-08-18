<?php
/**
 * @brief		ACP Member Profile: Purchases
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
 * @brief	ACP Member Profile: Purchases
 */
class _Purchases extends \IPS\core\MemberACPProfile\TabbedBlock
{
	/**
	 * Purchase tree
	 */
	protected $_purchases = NULL;

	/**
	 * Get Tab Names
	 *
	 * @return	string
	 */
	public function tabs()
	{
		$tabs = array();

		$activeCount = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_member=? AND ps_show=1 AND ps_active=1 AND ps_parent = 0', $this->member->member_id ) )->first();
		$tabs['active'] = array(
				'icon'		=> 'credit-card',
				'count'		=> $activeCount,
		);

		$expiredCount = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_member=? AND ps_active = 0 AND ps_cancelled = 0 AND ps_parent = 0 AND ps_expire <?', $this->member->member_id, \IPS\DateTime::create()->getTimestamp() ) )->first();
		$tabs['expired'] = array(
				'icon'		=> 'credit-card',
				'count'		=> $expiredCount,
		);

		$canceledCount = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', array( 'ps_member=? AND ps_cancelled=1 AND ps_parent = 0', $this->member->member_id ) )->first();
		$tabs['canceled'] = array(
			'icon'		=> 'credit-card',
			'count'		=> $canceledCount,
		);

		return $tabs;
	}
	
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output()
	{
		$tabs = $this->tabs();
		if ( !\count( $tabs ) )
		{
			return '';
		}
		$tabKeys = array_keys( $tabs );
		$activeTabKey = ( isset( \IPS\Request::i()->block['nexus_Purchase'] ) and array_key_exists( \IPS\Request::i()->block['nexus_Purchases'], $tabs ) ) ? \IPS\Request::i()->block['nexus_Purchases'] : array_shift( $tabKeys );

		return \IPS\Theme::i()->getTemplate( 'customers', 'nexus' )->purchases( $this->member, $tabs, $activeTabKey, $this->tabOutput( $activeTabKey ) );
	}

	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function tabOutput( $activeTabKey )
	{
		if ( $this->_purchases === NULL )
		{
			$where = array();
			$where[] = array( 'ps_member=?', $this->member->member_id );

			switch ( $activeTabKey )
			{
				case 'active':
					$where[] = array( 'ps_active=1' );
					break;
				case 'canceled':
					$where[] = array( 'ps_cancelled=1' );
					break;
				case 'expired':
					$where[] = array( 'ps_active = 0 and ps_cancelled = 0 and ps_expire <?', \IPS\DateTime::create()->getTimestamp() );
					break;
			}

			$this->_purchases = \IPS\nexus\Purchase::tree( $this->member->acpUrl()->setQueryString( 'blockKey', 'nexus_Purchases' ), $where );
			$this->_purchases->getTotalRoots = function()
			{
				return NULL;
			};
		}
		return $this->_purchases;
	}
}