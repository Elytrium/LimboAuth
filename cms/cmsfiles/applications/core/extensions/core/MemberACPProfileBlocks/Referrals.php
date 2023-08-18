<?php
/**
 * @brief		ACP Member Profile Block
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		30 Sep 2019
 */

namespace IPS\core\extensions\core\MemberACPProfileBlocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile Block
 */
class _Referrals extends \IPS\core\MemberACPProfile\Block
{
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output( $edit = FALSE )
	{
		if( !\IPS\Settings::i()->ref_on )
		{
			return "";
		}
		
		$url = $this->member->acpUrl()->setQueryString( array( 'do' => 'editBlock', 'block' => \get_class( $this ) ) );
		$referCount = \IPS\Db::i()->select( 'COUNT(*)', 'core_referrals', array( 'referred_by=?', $this->member->member_id ) )->first();
		$referrals = new \IPS\Helpers\Table\Db( 'core_referrals', $url, array( 'referred_by=?', $this->member->member_id ) );
		$referrals->langPrefix = 'ref_';
		$referrals->include = array( 'member_id' );

		if ( \IPS\Application::appIsEnabled( 'nexus' ) )
		{
			$referrals->include[] = 'amount';
		}

		$referrals->sortBy = $referrals->sortBy ?: 'member_id';
		$referrals->parsers = array( 'member_id' => function ($v)
		{
			try
			{
				return \IPS\Theme::i()->getTemplate( 'global' )->userLink( \IPS\Member::load( $v ) );
			}
			catch ( \OutOfRangeException $e )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'deleted_member' );
			}
		}, 'email' => function ($v, $row)
		{
			try
			{
				return htmlspecialchars( \IPS\Member::load( $row[ 'member_id' ] )->email, ENT_DISALLOWED, 'UTF-8', FALSE );
			}
			catch ( \OutOfRangeException $e )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'deleted_member' );
			}
		}, 'amount' => function ($v)
		{
			$return = array();
			if ( $v )
			{
				foreach ( json_decode( $v, TRUE ) as $currency => $amount )
				{
					$return[] = new \IPS\nexus\Money( $amount, $currency );
				}
			}
			else
			{
				$return[] = new \IPS\nexus\Money( 0, \IPS\nexus\Customer::load( $this->member->member_id )->defaultCurrency() );
			}
			return implode( '<br>', $return );
		} );

		if( $edit )
		{
			return \IPS\Theme::i()->getTemplate( 'members', 'core' )->referralPopup( $referrals );
		}
		else
		{
			$referrals->include[] = 'email';
			$referrals->limit = 2;
			$referrals->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'members', 'core' ), 'referralsOverview' );
			$referrals->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'members', 'core' ), 'referralsOverviewRows' );

			return \IPS\Theme::i()->getTemplate( 'members', 'core' )->referralsTable( $this->member, $referrals, \IPS\Member::loggedIn()->language()->addToStack( 'num_refer_count', FALSE, array( 'pluralize' => array( $referCount ) ) ), 'referrers' );
		}
	}

	/**
	 * Edit Window
	 *
	 * @return	string
	 */
	public function edit()
	{
		return $this->output( TRUE );
	}
}