<?php
/**
 * @brief		Group Limits
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		21 Nov 2013
 */

namespace IPS\downloads\extensions\core\GroupLimits;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Group Limits
 *
 * This extension is used to define which limit values "win" when a user has secondary groups defined
 */
class _Downloads
{
	/**
	 * Get group limits by priority
	 *
	 * @return	array
	 */
	public function getLimits()
	{
		return array (
			'exclude' 		=> array(),
			'lessIsMore'	=> array( 'idm_wait_period' ),
			'neg1IsBest'	=> array( 'idm_max_size' ),
			'zeroIsBest'	=> array( 'idm_throttling' ),
			'callback'		=> array( 'idm_restrictions' => function( $a, $b, $k )
			{
				// Decode
				if ( isset( $a[ $k ] ) AND $a[ $k ] )
				{
					$a = json_decode( $a[ $k ], TRUE );
				}
				else
				{
					if( !isset( $b[ $k ] ) )
					{
						return null;
					}

					return $b[ $k ];
				}
				if ( isset( $b[ $k ] ) AND $b[ $k ] )
				{
					$b = json_decode( $b[ $k ], TRUE );
				}
				else
				{
					if( !isset( $a[ $k ] ) )
					{
						return null;
					}

					return json_encode( $a[ $k ] );
				}
				$return = array();
				
				// Lower is better
				foreach ( array( 'limit_sim', 'min_posts' ) as $k )
				{
					$return[ $k ] = ( $a[ $k ] < $b[ $k ] ) ? $a[ $k ] : $b[ $k ];
				}
				
				// Higher is better
				foreach ( array( 'daily_bw', 'weekly_bw', 'monthly_bw', 'daily_dl', 'weekly_dl', 'monthly_dl' ) as $k )
				{
					if( $a[ $k ] == 0 OR $b[ $k ] == 0 )
					{
						$return[ $k ] = 0;
						continue;
					}

					$return[ $k ] = ( $a[ $k ] > $b[ $k ] ) ? $a[ $k ] : $b[ $k ];
				}
				
				// Encode
				return json_encode( $return );
			} )
		);
	}
}