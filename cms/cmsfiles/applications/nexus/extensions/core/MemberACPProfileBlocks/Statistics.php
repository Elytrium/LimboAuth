<?php
/**
 * @brief		ACP Member Profile: Customer Statistics Block
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
class _Statistics extends \IPS\core\MemberACPProfile\LazyLoadingBlock
{
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function lazyOutput()
	{
		$standing = array();
		$time = time();
		foreach ( \IPS\nexus\Money::currencies() as $currency )
		{
			/* Total spent */
			$comparisons = \IPS\Db::i()->select( 'AVG(xval) AS avg, MAX(xval) AS max, MIN(xval) AS min', \IPS\Db::i()->select( "(SUM(t_amount)-SUM(t_partial_refund)) as xval", 'nexus_transactions', array( '( t_status=? OR t_status=? ) AND t_method>0 AND t_currency=? AND t_member<>0', \IPS\nexus\Transaction::STATUS_PAID, \IPS\nexus\Transaction::STATUS_PART_REFUNDED, $currency ), NULL, NULL, 't_member' ), 'xval>0' )->first();
			$val = \IPS\Db::i()->select( 'SUM(t_amount)-SUM(t_partial_refund)', 'nexus_transactions', array( 't_member=? AND ( t_status=? OR t_status=? ) AND t_method>0 AND t_currency=?', $this->member->member_id, \IPS\nexus\Transaction::STATUS_PAID, \IPS\nexus\Transaction::STATUS_PART_REFUNDED, $currency ) )->first();
			$standing[ \IPS\Member::loggedIn()->language()->addToStack( 'total_spent_currency', FALSE, array( 'sprintf' => array( $currency ) ) ) ] = array(
				'value'		=> new \IPS\nexus\Money( $val ?: '0', $currency ),
				'avg'		=> new \IPS\nexus\Money( $comparisons['avg'], $currency ),
				'highest'	=> new \IPS\nexus\Money( $comparisons['max'], $currency ),
				'lowest'	=> new \IPS\nexus\Money( $comparisons['min'], $currency ),
				'lowval'	=> $comparisons['min'],
				'highval'	=> $comparisons['max'],
				'avgval'	=> $comparisons['avg'],
				'thisval'	=> $val,
				'avgpct'	=> $comparisons['max'] ? round( ( ( $comparisons['avg'] - $comparisons['min'] ) / $comparisons['max'] ) * 100, 1 ) : 0,
				'thispct'	=> $comparisons['max'] ? round( ( ( $val - $comparisons['min'] ) / $comparisons['max'] ) * 100, 1 ) : 0,
				'type'		=> 'totalspent'
			);
			
			/* Average monthly spend */
			$sum = "( SUM(t_amount) / ( ( {$time} - core_members.joined ) / 2592000 ) )";
			$where = array( array( "( t_status=? OR t_status=? ) AND ({$time} - core_members.joined > 2592000) AND t_currency=?", \IPS\nexus\Transaction::STATUS_PAID, \IPS\nexus\Transaction::STATUS_PART_REFUNDED, $currency ) );
			try
			{
				$val = \IPS\Db::i()->select( "{$sum} as sum", 'nexus_transactions', array_merge( $where, array( array( 't_member=?', $this->member->member_id ) ) ), 'joined', NULL, 'joined' )->join( 'core_members', 'member_id=t_member' )->first();
			}
			catch( \UnderflowException $e )
			{
				$val = 0;
			}
			$comparisons = \IPS\Db::i()->select( 'AVG(xval) AS avg, MAX(xval) AS max, MIN(xval) AS min', \IPS\Db::i()->select( "{$sum} as xval", 'nexus_transactions', array_merge( $where, array( array( 't_member<>0' ) ) ), NULL, NULL, array( 't_member', 'joined' ) ), 'xval>0' )->join( 'core_members', 'member_id=t_member' )->first();
			$standing[ \IPS\Member::loggedIn()->language()->addToStack( 'average_spend_currency', FALSE, array( 'sprintf' => array( $currency ) ) ) ] = array(
				'value'		=> new \IPS\nexus\Money( $val, $currency ),
				'avg'		=> new \IPS\nexus\Money( $comparisons['avg'], $currency ),
				'highest'	=> new \IPS\nexus\Money( $comparisons['max'], $currency ),
				'lowest'	=> new \IPS\nexus\Money( $comparisons['min'], $currency ),
				'lowval'	=> $comparisons['min'],
				'highval'	=> $comparisons['max'],
				'avgval'	=> $comparisons['avg'],
				'thisval'	=> $val,
				'avgpct'	=> $comparisons['max'] ? round( ( ( $comparisons['avg'] - $comparisons['min'] ) / $comparisons['max'] ) * 100, 1 ) : 0,
				'thispct'	=> $comparisons['max'] ? round( ( ( $val - $comparisons['min'] ) / $comparisons['max'] ) * 100, 1 ) : 0,
				'type'		=> 'avgspent'
			);			
		}
		
		$sum = "ROUND( ( AVG( i_paid - i_date ) / 86400 ), 2 )";
		$where = array( array( "i_status=? AND i_date>0 AND i_paid>0", \IPS\nexus\Invoice::STATUS_PAID ) );
		$val = \IPS\Db::i()->select( "{$sum} as sum", 'nexus_invoices', array_merge( $where, array( array( 'i_member=?', $this->member->member_id ) ) ) )->join( 'core_members', 'member_id=i_member' )->first();
		$comparisons = \IPS\Db::i()->select( 'AVG(xval) AS avg, MAX(xval) AS max, MIN(xval) AS min', \IPS\Db::i()->select( "{$sum} as xval", 'nexus_invoices', array_merge( $where, array( array( 'i_member<>0' ) ) ), NULL, NULL, array( 'i_member', 'joined' ) ), 'xval>0' )->join( 'core_members', 'member_id=i_member' )->first();
		$standing[ \IPS\Member::loggedIn()->language()->addToStack( 'average_time_to_pay' ) ] = array(
			'value'		=> \IPS\Member::loggedIn()->language()->formatNumber( $val, 2 ) . ' ' . \IPS\Member::loggedIn()->language()->addToStack('days'),
			'avg'		=> \IPS\Member::loggedIn()->language()->formatNumber( $comparisons['avg'], 2 ) . ' ' . \IPS\Member::loggedIn()->language()->addToStack('days'),
			'highest'	=> \IPS\Member::loggedIn()->language()->formatNumber( $comparisons['max'], 2 ) . ' ' . \IPS\Member::loggedIn()->language()->addToStack('days'),
			'lowest'	=> \IPS\Member::loggedIn()->language()->formatNumber( $comparisons['min'], 2 ) . ' ' . \IPS\Member::loggedIn()->language()->addToStack('days'),
			'lowval'	=> $comparisons['min'],
			'highval'	=> $comparisons['max'],
			'avgval'	=> $comparisons['avg'],
			'thisval'	=> $val,
			'avgpct'	=> \floatval( $comparisons['max'] ) ? round( ( ( $comparisons['avg'] - $comparisons['min'] ) / $comparisons['max'] ) * 100, 1 ) : 0,
			'thispct'	=> \floatval( $comparisons['max'] ) ? round( ( ( $val - $comparisons['min'] ) / $comparisons['max'] ) * 100, 1 ) : 0,
			'type'		=> 'timetopay'
		);
		
		$sum = "LEAST( ( count(*) / ( ( {$time} - core_members.joined ) / 2592000 ) ), count(*) )";
		try
		{
			$val = \IPS\Db::i()->select( "{$sum} as sum", 'nexus_support_requests', array( 'r_member=?', $this->member->member_id ), 'joined', NULL, 'joined' )->join( 'core_members', 'member_id=r_member' )->first();
		}
		catch( \UnderflowException $e )
		{
			$val = 0;
		}
		$comparisons = \IPS\Db::i()->select( 'AVG(xval) AS avg, MAX(xval) AS max, MIN(xval) AS min', \IPS\Db::i()->select( "{$sum} as xval", 'nexus_support_requests', 'r_member<>0', NULL, NULL, 'joined' ) )->join( 'core_members', 'member_id=r_member' )->first();
		$standing[ \IPS\Member::loggedIn()->language()->addToStack( 'average_monthly_support_requests' ) ] = array(
			'value'		=> \IPS\Member::loggedIn()->language()->formatNumber( $val, 2 ),
			'avg'		=> \IPS\Member::loggedIn()->language()->formatNumber( $comparisons['avg'], 2 ),
			'highest'	=> \IPS\Member::loggedIn()->language()->formatNumber( $comparisons['max'], 2 ),
			'lowest'	=> \IPS\Member::loggedIn()->language()->formatNumber( $comparisons['min'], 2 ),
			'lowval'	=> $comparisons['min'],
			'highval'	=> $comparisons['max'],
			'avgval'	=> $comparisons['avg'],
			'thisval'	=> $val,
			'avgpct'	=> $comparisons['max'] ? round( ( ( $comparisons['avg'] - $comparisons['min'] ) / $comparisons['max'] ) * 100, 1 ) : 0,
			'thispct'	=> $comparisons['max'] ? round( ( ( $val - $comparisons['min'] ) / $comparisons['max'] ) * 100, 1 ) : 0,
			'type'		=> 'support'
		);	
		
		return \IPS\Theme::i()->getTemplate( 'customers', 'nexus' )->standing( $standing );
	}
}