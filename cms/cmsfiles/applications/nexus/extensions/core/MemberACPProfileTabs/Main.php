<?php
/**
 * @brief		ACP Member Profile: Customer Tab
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Nov 2017
 */

namespace IPS\nexus\extensions\core\MemberACPProfileTabs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: Customer Tab
 */
class _Main extends \IPS\core\MemberACPProfile\MainTab
{
	/**
	 * Constructor
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	void
	 */
	public function __construct( \IPS\Member $member )
	{
		$this->member = \IPS\nexus\Customer::load( $member->member_id );
	}
	
	/**
	 * Can view this Tab
	 *
	 * @return	bool
	 */
	public function canView()
	{
		return (bool) \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'customers_view' );
	}
	
	/**
	 * Get left-column blocks
	 *
	 * @return	array
	 */
	public function leftColumnBlocks()
	{
		return array(
			'IPS\nexus\extensions\core\MemberACPProfileBlocks\AccountInformation',
		);
	}
	
	/**
	 * Get main-column blocks
	 *
	 * @return	array
	 */
	public function mainColumnBlocks()
	{
		$return = array();

		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'customers_view_statistics' ) )
		{
			$return[] = 'IPS\nexus\extensions\core\MemberACPProfileBlocks\Statistics';
		}

		$return[] = 'IPS\nexus\extensions\core\MemberACPProfileBlocks\ParentAccounts';
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'customer_notes_view' ) )
		{
			$return[] = 'IPS\nexus\extensions\core\MemberACPProfileBlocks\Notes';
		}
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'purchases_view' ) )
		{
			$return[] = 'IPS\nexus\extensions\core\MemberACPProfileBlocks\Purchases';
		}
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'invoices_manage' ) )
		{
			$return[] = 'IPS\nexus\extensions\core\MemberACPProfileBlocks\Invoices';
		}
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'support', 'requests_manage' ) )
		{
			$return[] = 'IPS\nexus\extensions\core\MemberACPProfileBlocks\Support';
		}
		
		return $return;
	}
	
	/**
	 * Get Output
	 *
	 * @return	string
	 */
	public function output()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'customer.css', 'nexus', 'admin' ) );

		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'customer_responsive.css', 'nexus', 'admin' ) );
		}

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'support.css', 'nexus', 'admin' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_customer.js', 'nexus', 'admin' ) );
		
		return parent::output();
	}
}