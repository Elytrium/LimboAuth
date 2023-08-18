<?php
/**
 * @brief		Group Limits
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		07 Nov 2013
 */

namespace IPS\core\extensions\core\GroupLimits;

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
class _GroupLimits
{
	/**
	 * Get group limits by priority
	 *
	 * @return	array
	 */
	public function getLimits()
	{
		return array(
			'exclude' 		=> array( 'g_id', 'g_icon', 'prefix', 'suffix', 'g_promotion', 'g_bitoptions' ),
			'lessIsMore'	=> array( 'g_search_flood', 'g_pm_flood_mins' ),
			'neg1IsBest'	=> array( 'g_attach_max', 'g_max_bgimg_upload', 'g_max_messages', 'g_pm_perday', 'g_pm_flood_mins', 'g_max_mass_pm', 'g_rep_max_positive' ),
			'zeroIsBest'	=> array( 'g_displayname_unit', 'g_sig_unit', 'g_mod_preview', 'g_ppd_limit', 'g_ppd_unit', 'gbw_no_status_update', 'g_max_bgimg_upload', 'gbw_disable_prefixes', 'gbw_disable_tagging' ),
			'callback'		=> array(
				'g_edit_cutoff'				=> array( $this, '_editCutoff' ),
				'g_attach_per_post'			=> array( $this, '_attachMaxPerPost' ),
				'g_signature_limits'		=> array( $this, '_signatureLimits' ),
				'g_photo_max_vars'			=> array( $this, '_photoVars' ),
				'g_dname_date'				=> array( $this, '_displayNameDate' ),
				'g_dname_changes'			=> array( $this, '_displayNameChanges' ),
				'gbw_mod_post_unit_type'	=> array( $this, '_modPostUnitType' ),
				'g_mod_post_unit'			=> array( $this, '_modPostUnit' ),
				'g_edit_posts'				=> array( $this, '_perAppSettings' ),
				'g_hide_own_posts'			=> array( $this, '_perAppSettings' ),
				'g_delete_own_posts'		=> array( $this, '_perAppSettings' ),
				'g_lock_unlock_own'			=> array( $this, '_perAppSettings' ),
				'g_can_report'				=> array( $this, '_perAppSettings' ),
				'g_club_allowed_nodes'		=> array( $this, '_clubNodes' ),
				'g_create_clubs'			=> array( $this, '_createClub' ),
				'g_hide_online_list'		=> array( $this, '_hideOnlineList' ),
			)
		);
	}

	/**
	 * Allow users in the group to login anonymously?
	 *
	 * @param	array	$a		Group A's values
	 * @param	array	$b		Group B's values
	 * @param	string	$k		The key we want to get the combined value for
	 * @param	array	$member	Member data
	 * @return	mixed
	 */
	public function _hideOnlineList( $a, $b, $k, $member )
	{
		/* If any group is forced, use that as priority */
		if( $a[$k] == 1 OR $b[$k] == 1 )
		{
			return 1;
		}
		/* Else if any group allows anonymous logins, allow them */
		elseif( $a[$k] == 0 OR $b[$k] == 0 )
		{
			return 0;
		}
		/* Otherwise disable them */
		else
		{
			return 2;
		}
	}

	/**
	 * Cutoff for editing - only use a group's value if the group is able to edit
	 *
	 * @param	array	$a		Group A's values
	 * @param	array	$b		Group B's values
	 * @param	string	$k		The key we want to get the combined value for
	 * @param	array	$member	Member data
	 * @return	mixed
	 */
	public function _editCutoff( $a, $b, $k, $member )
	{
		$cutoff	= array();

		if( $a['g_edit_posts'] )
		{
			if( $a[ $k ] == 0 )
			{
				return 0;
			}
			else
			{
				$cutoff[] = $a[ $k ];
			}
		}

		if( $b['g_edit_posts'] )
		{
			if( $b[ $k ] == 0 )
			{
				return 0;
			}
			else
			{
				$cutoff[] = $b[ $k ];
			}
		}

		return \count( $cutoff ) ? max( $cutoff ) : 0;
	}

	/**
	 * Max per-content-item attachment storage space
	 *
	 * @param	array	$a		Group A's values
	 * @param	array	$b		Group B's values
	 * @param	string	$k		The key we want to get the combined value for
	 * @param	array	$member	Member data
	 * @return	mixed
	 */
	public function _attachMaxPerPost( $a, $b, $k, $member )
	{
		$maxAttachments	= array();

		if( $a['g_attach_max'] !== 0 )
		{
			if( $a[ $k ] == 0 )
			{
				return 0;
			}
			else
			{
				$maxAttachments[] = $a[ $k ];
			}
		}

		if( $b['g_attach_max'] !== 0 )
		{
			if( $b[ $k ] == 0 )
			{
				return 0;
			}
			else
			{
				$maxAttachments[] = $b[ $k ];
			}
		}

		return \count( $maxAttachments ) ? max( $maxAttachments ) : 0;
	}

	/**
	 * Per-App Settings
	 *
	 * @param	array	$a		Group A's values
	 * @param	array	$b		Group B's values
	 * @param	string	$k		The key we want to get the combined value for
	 * @param	array	$member	Member data
	 * @return	mixed
	 */
	public function _perAppSettings( $a, $b, $k, $member )
	{
		if ( $a[ $k ] == 1 or $b[ $k ] == 1 )
		{
			return 1;
		}
		
		if ( $a[ $k ] and $b[ $k ] )
		{
			return implode( ',', array_unique( array_merge( explode( ',', $a[ $k ] ), explode( ',', $b[ $k ] ) ) ) );
		}
		elseif ( $a[ $k ] )
		{
			return $a[ $k ];
		}
		elseif ( $b[ $k ] )
		{
			return $b[ $k ];
		}
		
		return 0;
	}
	
	/**
	 * Signature Limits
	 *
	 * @param	array	$a		Group A's values
	 * @param	array	$b		Group B's values
	 * @param	string	$k		The key we want to get the combined value for
	 * @param	array	$member	Member data
	 * @return	mixed
	 */
	public function _signatureLimits( $a, $b, $k, $member )
	{
		/* No limits should win out */
		if( !$a[ $k ] )
		{
			return null;
		}
		
		/* We have limits */
		if( $b[ $k ] )
		{
			$values	= explode( ':', $b[ $k ] );
			$_cur	= explode( ':', $a[ $k ] );
			$_new 	= array();

			/* If the group can't use signatures, ignore any limits saved for the group */
			if( $_cur[0] )
			{
				return $b[ $k ];
			}

			if( $values[0] )
			{
				return $a[ $k ];
			}

			foreach( $values as $index => $value )
			{
				if( $_cur[ $index ] == "" or $values[ $index ] == "" )
				{
					$_new[ $index ]	= NULL;
				}
				/* If signatures are currently disabled but aren't in the group we are checking, signatures should not be disabled (1=disabled, 0=enabled) */
				elseif ( $index == 0 and $_cur[ $index ] > $values[ $index ] )
				{
					$_new[ $index ]	= $values[ $index ];
				}
				/* If signatures are currently enabled but aren't in the group we are checking, signatures should not be disabled */
				elseif ( $index == 0 and $_cur[ $index ] < $values[ $index ] )
				{
					$_new[ $index ]	= $_cur[ $index ];
				}
				else if( $_cur[ $index ] > $values[ $index ] )
				{
					$_new[ $index ]	= $_cur[ $index ];
				}
				else
				{
					$_new[ $index ]	= $values[ $index ];
				}

				/* If we are not allowed to use any images, ignore the dimensions specified. 
				 Otherwise group A with 0 images and no limits on sizes and group B with 1 images at 200x200 results in 1 image allowed unlimited sizes. */
				if( ( $index == 2 OR $index == 3 ) AND $values[1] == 0 )
				{
					$_new[ $index ] = $_cur[ $index ];
				}
			}
														
			ksort($_new);
			return implode( ':', $_new );
		}
		else
		{
			/* Set no limits */
			return NULL;
		}
	}
	
	/**
	 * Photo Settings
	 *
	 * @param	array	$a		Group A's values
	 * @param	array	$b		Group B's values
	 * @param	string	$k		The key we want to get the combined value for
	 * @param	array	$member	Member data
	 * @return	mixed
	 */
	public function _photoVars( $a, $b, $k, $member )
	{
		/* No limits should win out */
		if( !$a[ $k ] )
		{
			return NULL;
		}

		/* If one group is set so "can edit profile" is no, ignore their photo vars options */
		if( !$a['g_edit_profile'] )
		{
			return $b[ $k ];
		}
		elseif( !$b['g_edit_profile'] )
		{
			return $a[ $k ];
		}
			
		/* We have limits */
		if( $b[ $k ] )
		{
			$values	= explode( ':', $b[ $k ] );
			$_cur	= explode( ':', $a[ $k ] );
			$_new 	= array();
		
			foreach( $values as $index => $value )
			{
				if( !$_cur[ $index ] or !$values[ $index ] )
				{
					$_new[ $index ]	= NULL;
				}
				else if( $_cur[ $index ] > $values[ $index ] )
				{
					$_new[ $index ]	= $_cur[ $index ];
				}
				else
				{
					$_new[ $index ]	= $values[ $index ];
				}
			}
				
			ksort($_new);
			return implode( ':', $_new );
		}
		else
		{
			/* Set no limits */
			return NULL;
		}
	}
	
	/**
	 * Display Name Date
	 *
	 * @param	array	$a		Group A's values
	 * @param	array	$b		Group B's values
	 * @param	string	$k		The key we want to get the combined value for
	 * @param	array	$member	Member data
	 * @return	mixed
	 */
	public function _displayNameDate( $a, $b, $k, $member )
	{
		/* This is handled by g_dname_changes below */
		return $a['g_dname_date'];
	}
	
	/**
	 * Display Name Changes
	 *
	 * @param	array	$a		Group A's values
	 * @param	array	$b		Group B's values
	 * @param	string	$k		The key we want to get the combined value for
	 * @param	array	$member	Member data
	 * @return	mixed
	 */
	public function _displayNameChanges( $a, $b, $k, $member )
	{
		$changes	= $b[ $k ];
		/* Get the most generous date allowance */
		$timeFrame	= max( array( $a['g_dname_date'], $b['g_dname_date'] ) );

        if( $changes == -1 OR $a[ $k ] == -1 )
        {
            return array(
                'g_dname_date'		=> 0,
                'g_dname_changes'	=> -1
            );
        }

		/* No time frame restriction */
		if( !$timeFrame )
		{
			/* This group allows more changes */
			if( $changes > $a[ $k ] )
			{
				return array(
					'g_dname_date'		=> 0,
					'g_dname_changes'	=> $changes
				);
			}
			
			/* Existing data is date restricted */
			else if( $a['g_dname_date'] )
			{
				if( $a[ $k ] )
				{
					$_compare	= round( $a['g_dname_date'] / $a[ $k ] );
					
					if( $_compare > $changes )
					{
						return array(
							'g_dname_date'		=> 0,
							'g_dname_changes'	=> $changes
						);
					}
				}
			}
		}
		
		/* Time frame restriction */
		else if( $changes )
		{ 
			$_compare	= round( $timeFrame / $changes );
			
			/* If there are no permitted changes allowed for group A, but there is a date set (is is default, then clear the date for the logic below */
			if ( ! $a[ $k ] and $a['g_dname_date'] )
			{
				$a['g_dname_date'] = 0;
			}
			
			/* Existing has no time frame restriction */
			if( !$a['g_dname_date'] AND $a[ $k ] )
			{
				if( $_compare < $a[ $k ] )
				{
					return array(
						'g_dname_date'		=> $timeFrame,
						'g_dname_changes'	=> $changes
					);
				}
			}
			else if( !$a['g_dname_date'] )
			{
				return array(
					'g_dname_date'		=> $timeFrame,
					'g_dname_changes'	=> $changes
				);
			}
			else if( $a['g_dname_date'] AND $a[ $k ] )
			{
				$_oldCompare	= $a['g_dname_date'] / $a[ $k ];

				if( $_compare < $_oldCompare )
				{
					return array(
						'g_dname_date'		=> $timeFrame,
						'g_dname_changes'	=> $changes
					);
				}
			}
		}
	}
	
	/**
	 * gbw_mod_post_unit_type 
	 *
	 * @param	array	$a		Group A's values
	 * @param	array	$b		Group B's values
	 * @param	string	$k		The key we want to get the combined value for
	 * @param	array	$member	Member data
	 * @return	mixed
	 */
	public function _modPostUnitType( $a, $b, $k, $member )
	{
		/* This is handled by g_mod_post_unit below */
		return $a['gbw_mod_post_unit_type'];
	}
	
	/**
	 * g_mod_post_unit 
	 *
	 * @param	array	$a		Group A's values
	 * @param	array	$b		Group B's values
	 * @param	string	$k		The key we want to get the combined value for
	 * @param	array	$member	Member data
	 * @return	mixed
	 */
	public function _modPostUnit( $a, $b, $k, $member )
	{
		/* Have we met the current requirements? */
		if ( ( !$a['gbw_mod_post_unit_type'] and $a['g_mod_post_unit'] >= $member['member_posts'] ) or ( $a['gbw_mod_post_unit_type'] and time() >= ( $member['joined'] + ( $a['g_mod_post_unit'] * 3600 ) ) ) )
		{
			/* Yes - so let's stick with this */
			return $a['g_mod_post_unit'];
		}
		else
		{
			/* No - go with the new group */
			return array(
				'g_mod_post_unit'			=> $b[ $k ],
				'gbw_mod_post_unit_type'	=> $b['gbw_mod_post_unit_type']
			);
		}
	}

	/**
	 * Available node types for clubs
	 *
	 * @param	array	$a		Group A's values
	 * @param	array	$b		Group B's values
	 * @param	string	$k		The key we want to get the combined value for
	 * @param	array	$member	Member data
	 * @return	mixed
	 */
	public function _clubNodes( $a, $b, $k, $member )
	{
		if ( $a[$k] == '*' or $b[$k] == '*' )
		{
			return '*';
		}

		/* We need to merge all content types */
		$nodeTypes = array_unique( array_merge( explode( ",", $a[$k]), explode( ",", $b[$k]) ) );

		return implode( ",", $nodeTypes );
	}
	
	/**
	 * Available club types
	 *
	 * @param	array	$a		Group A's values
	 * @param	array	$b		Group B's values
	 * @param	string	$k		The key we want to get the combined value for
	 * @param	array	$member	Member data
	 * @return	mixed
	 */
	public function _createClub( $a, $b, $k, $member )
	{
		if ( $a[$k] == '*' or $b[$k] == '*' )
		{
			return '*';
		}
		
		/* Merge together all available club types */
		$types = array_unique( array_merge( explode( ",", $a[$k] ), explode( ",", $b[$k] ) ) );
		
		return implode( ",", $types );
	}
}