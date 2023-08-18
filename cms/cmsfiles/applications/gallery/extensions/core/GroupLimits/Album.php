<?php
/**
 * @brief		Group Limits
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		23 May 2015
 */

namespace IPS\gallery\extensions\core\GroupLimits;

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
 * 
 */
class _Album
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
						'lessIsMore'	=> array(),
						'neg1IsBest'	=> array(),
						'zeroIsBest'	=> array( 'g_img_album_limit', 'g_max_upload', 'g_max_transfer', 'g_max_views', 'g_movie_size' ),
						'callback'		=> array( 'g_album_limit' => function( $a, $b, $k, $member ) {
							/* We only want to use the limit if this group can create albums */
							$limits = array();
							if ( $a['g_create_albums'] )
							{
								if ( $a[ $k ] == 0 )
								{
									return 0;
								}
								else
								{
									$limits[] = $a[ $k ];
								}
							}

							if ( $b['g_create_albums'] )
							{
								if ( $b[ $k ] == 0 )
								{
									return 0;
								}
								else
								{
									$limits[] = $b[ $k ];
								}
							}

							return \count( $limits ) ? \max( $limits ) : 0;
						} )
				);
	}
}