<?php
/**
 * @brief		referrals
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		07 Aug 2019
 */

namespace IPS\core\modules\admin\membersettings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Referrals
 */
class _referrals extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Call
	 *
	 * @param	string	$method	Method name
	 * @param	mixed	$args	Method arguments
	 * @return	void
	 */
	public function __call( $method, $args )
	{
		$tabs = array();
		if( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'membersettings', 'referrals_manage' ) )
		{
			$tabs['refersettings'] = 'settings';
		}
		if( \IPS\Settings::i()->ref_on and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'membersettings', 'referrals_manage' ) )
		{
			$tabs['referralbanners'] = 'referral_banners';
		}
		if( \IPS\Settings::i()->ref_on and \IPS\Application::appIsEnabled( 'nexus' ) )
		{
			$tabs['referralcommission'] = 'referral_commission';
		}
		if ( isset( \IPS\Request::i()->tab ) and isset( $tabs[ \IPS\Request::i()->tab ] ) )
		{
			$activeTab = \IPS\Request::i()->tab;
		}
		else
		{
			$_tabs = array_keys( $tabs ) ;
			$activeTab = array_shift( $_tabs );
		}

		$classname = 'IPS\core\modules\admin\membersettings\\' . $activeTab;
		$class = new $classname;
		$class->url = \IPS\Http\Url::internal("app=core&module=membersettings&controller=referrals&tab={$activeTab}");
		$class->execute();

		if ( $method !== 'manage' or \IPS\Request::i()->isAjax() )
		{
			return;
		}

		\IPS\Output::i()->sidebar['actions'] = array(
			'history'	=> array(
				'title'		=> 'referral_history',
				'icon'		=> 'clock-o',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=referrals&do=history' ),
				),
		);

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_membersettings_referrals');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, \IPS\Output::i()->output, \IPS\Http\Url::internal( "app=core&module=membersettings&controller=referrals" ) );
	}

	/**
	 * Referral History
	 *
	 * @return	void
	 */
	protected function history()
	{
		$table = new \IPS\Helpers\Table\Db( 'core_referrals', \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=referrals&do=history' ) );
		$table->langPrefix = 'referrals_';

		/* Columns we need */
		$table->include = array( 'photo', 'member_id', 'referred_by', 'joined' );
		if( \IPS\Application::appIsEnabled( 'nexus' ) )
		{
			$table->include[] = 'amount';
		}

		$table->sortBy = $table->sortBy ?: 'joined';
		$table->sortDirection = $table->sortDirection ?: 'DESC';
		$table->noSort = array( 'photo', 'member_id', 'referred_by' );

		$table->joins = array(
			array( 'select' => 'm.*', 'from' => array( 'core_members', 'm' ), 'where' => "core_referrals.member_id=m.member_id" )
		);

		$table->parsers = array(
			'photo'				=> function( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( \IPS\Member::constructFromData( $row ), 'tiny' );

			},
			'member_id'	=> function( $val, $row )
			{
				if ( $val )
				{
					return \IPS\Theme::i()->getTemplate( 'global', 'core', 'admin' )->userLink( \IPS\Member::constructFromData( $row ) );
				}
				else
				{
					return \IPS\Theme::i()->getTemplate( 'members', 'core', 'admin' )->memberReserved( \IPS\Member::load( $val ) );
				}
			},
			'referred_by'	=> function( $val, $row )
			{
				if ( $val )
				{
					return \IPS\Theme::i()->getTemplate( 'global', 'core', 'admin' )->userLink( \IPS\Member::load( $val ) );
				}
				else
				{
					return \IPS\Theme::i()->getTemplate( 'members', 'core', 'admin' )->memberReserved( \IPS\Member::load( $val ) );
				}
			},
			'joined'	=> function( $val, $row )
			{
				return \IPS\DateTime::ts( $val )->localeDate();
			},
			'amount' => function ( $val, $row )
			{
				$return = array();
				if ( $val )
				{
					foreach ( json_decode( $val, TRUE ) as $currency => $amount )
					{
						$return[] = new \IPS\nexus\Money( $amount, $currency );
					}
				}
				else
				{
					$return[] = \IPS\Member::loggedIn()->language()->addToStack('none');
				}
				return implode( '<br>', $return );
			}
		);

		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('referrals');
		\IPS\Output::i()->output	= (string) $table;
	}
}