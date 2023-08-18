<?php
/**
 * @brief		4.2.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		06 Feb 2017
 */

namespace IPS\core\setup\upg_101100;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.2.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Enable/disable letter photos appropriately
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* If the installation can't write text to images anyways, just return now and normal settings insert will insert with the normal default value */
		if( !\IPS\Image::canWriteText() )
		{
			return true;
		}

		\IPS\Db::i()->insert( 'core_sys_conf_settings', array(
				'conf_key' => 'letter_photos',
				'conf_default' => 'letters',
				'conf_value' => $_SESSION['upgrade_options']['core']['101100']['letter_photos'] ? 'letters' : 'default',
				'conf_app' => 'core'
			), TRUE );
		\IPS\Settings::i()->clearCache();

		return true;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Setting letter photo preference";
	}

	/**
	 * Step 2
	 * Fix orphaned status updates
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$queries = array();

		/* Orphaned status updates */
		try
		{
			\IPS\Db::i()->select( 'status_id', 'core_member_status_updates', array( 'status_member_id NOT IN ( ' . \IPS\Db::i()->select( 'member_id', 'core_members' ) . ' )' ), NULL, 1 )->first();

			/* Remove the offending entries */
			\IPS\Db::i()->returnQuery = TRUE;
			$queries[] = array(
				'table' => 'core_member_status_updates',
				'query' => \IPS\Db::i()->delete( 'core_member_status_updates', array( 'status_member_id NOT IN ( ' . \IPS\Db::i()->select( 'member_id', 'core_members' ) . ' )' ) )
			);
			\IPS\Db::i()->returnQuery = FALSE;
		}
		catch( \UnderflowException $e ) {}

		/* Orphaned ignore entries */
		try
		{
			\IPS\Db::i()->select( 'ignore_id', 'core_ignored_users', array( 'ignore_owner_id NOT IN ( ' . \IPS\Db::i()->select( 'member_id', 'core_members' ) . ' )' ), NULL, 1 )->first();

			/* Remove the offending entries */
			\IPS\Db::i()->returnQuery = TRUE;
			$queries[] = array(
				'table' => 'core_ignored_users',
				'query' => \IPS\Db::i()->delete( 'core_ignored_users', array( 'ignore_owner_id NOT IN ( ' . \IPS\Db::i()->select( 'member_id', 'core_members' ) . ' )' ) )
			);
			\IPS\Db::i()->returnQuery = FALSE;
		}
		catch( \UnderflowException $e ) {}

		/* Orphaned reputation data */
		try
		{
			\IPS\Db::i()->select( 'id', 'core_reputation_index', array( 'member_id NOT IN ( ' . \IPS\Db::i()->select( 'member_id', 'core_members' ) . ' )' ), NULL, 1 )->first();

			/* Remove the offending entries */
			\IPS\Db::i()->returnQuery = TRUE;
			$queries[] = array(
				'table' => 'core_reputation_index',
				'query' => \IPS\Db::i()->delete( 'core_reputation_index', array( 'member_id NOT IN ( ' . \IPS\Db::i()->select( 'member_id', 'core_members' ) . ' )' ) )
			);
			\IPS\Db::i()->returnQuery = FALSE;
		}
		catch( \UnderflowException $e ) {}

		/* Is this site affected by any of these issues? */
		if( !\count( $queries ) )
		{
			return TRUE;
		}

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $queries );

		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 3 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Removing orphaned data";
	}

	/**
	 * Move Display Name history to the Member History Table
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		/* Some init */
		$did		= 0;
		$limit		= 0;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit = \IPS\Request::i()->extra;
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff = \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'core_dnames_change', null, 'dname_id ASC', array( $limit, 500 ) ) as $displayName )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			\IPS\Db::i()->insert( 'core_member_history', array(
				'log_app'			=> 'core',
				'log_member'		=> $displayName['dname_member_id'],
				'log_by'			=> NULL,
				'log_type'			=> 'display_name',
				'log_data'			=> json_encode( array( 'old' => $displayName['dname_previous'], 'new' => $displayName['dname_current'] ) ),
				'log_date'			=> $displayName['dname_date'],
				'log_ip_address'	=> $displayName['dname_ip_address']
			) );
		}

		if( $did )
		{
			return $limit + $did;
		}
		else
		{
			/* Delete table */
			\IPS\Db::i()->dropTable( 'core_dnames_change' );

			unset( $_SESSION['_step3Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step3Count'] ) )
		{
			$_SESSION['_step3Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_dnames_change' )->first();
		}

		return "Converting display name history (Converted so far: " . ( ( $limit > $_SESSION['_step3Count'] ) ? $_SESSION['_step3Count'] : $limit ) . ' out of ' . $_SESSION['_step3Count'] . ')';
	}
	

	/**
	 * Fix not existing moderator groups
	 *
	 * @return bool
	 */
	public function step4()
	{
		\IPS\Db::i()->delete( 'core_moderators', array( 'type=? AND ' . \IPS\Db::i()->in( 'id', iterator_to_array( \IPS\Db::i()->select('g_id', 'core_groups' ) ), TRUE ), 'g' ) );
		unset ( \IPS\Data\Store::i()->moderators );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step4CustomTitle()
	{
		return "Removing non-existent moderator groups";
	}
	
	/**
	 * Step 8
	 * Set up promoted items
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step5()
	{
		/* Populate the table */
		if ( ! \IPS\Db::i()->select( 'COUNT(*)', 'core_social_promote_sharers' )->first() )
		{
			foreach( array( 'Facebook', 'Twitter', 'Internal' ) as $service )
			{
				\IPS\Db::i()->insert( 'core_social_promote_sharers', array( 'sharer_key' => $service, 'sharer_settings' => '[]', 'sharer_enabled' => 0 ) );
			}
			
			/* Add menu item */
			\IPS\core\FrontNavigation::insertMenuItem( NULL, array( 'app' => 'core', 'key' => 'Promoted' ), \IPS\Db::i()->select( 'MAX(position)', 'core_menu' )->first() );
		}
		
		return true;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step5CustomTitle()
	{
		return "Setting up defaults for promotion";
	}

	/**
	 * Step 9
	 * Create reaction types based on current settings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step6()
	{
		$position = 0;

		/* Path fallback if a custom AdminCP directory is used. */
		$path = \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY;
		if( \IPS\CP_DIRECTORY != 'admin' AND !file_exists( \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY . '/install/reaction' ) )
		{
			$path = \IPS\ROOT_PATH . '/admin';
		}

		/* Go ahead and insert the default "Like" - if we aren't use likes, leave it disabled. */
		$fileObj = \IPS\File::create( 'core_Reaction', 'react_like.png', file_get_contents( $path . '/install/reaction/react_like.png' ), 'reactions', FALSE, NULL, FALSE );
		$id = \IPS\Db::i()->insert( 'core_reactions', array(
			'reaction_value'	=> 1,
			'reaction_icon'		=> (string) $fileObj,
			'reaction_position'	=> ++$position,
			'reaction_enabled'	=> 1,
		) );
		\IPS\Lang::saveCustom( 'core', 'reaction_title_' . $id, 'Like' );

		foreach( array( 'thanks', 'haha', 'confused', 'sad' ) AS $reaction )
		{
			$fileObj = \IPS\File::create( 'core_Reaction', "react_{$reaction}.png", file_get_contents( $path . "/install/reaction/react_{$reaction}.png" ), 'reactions', FALSE, NULL, FALSE );
			$id = \IPS\Db::i()->insert( 'core_reactions', array(
				'reaction_value'	=> ( \in_array( $reaction, array( 'confused', 'sad' ) ) ) ? 0 : 1,
				'reaction_icon'		=> (string) $fileObj,
				'reaction_position'	=> ++$position,
				'reaction_enabled'	=> ( \IPS\Settings::i()->reputation_point_types == 'like' ) ? 1 : 0,
			) );
			\IPS\Lang::saveCustom( 'core', 'reaction_title_' . $id, ucwords( $reaction ) );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step6CustomTitle()
	{
		return "Creating default reactions";
	}

	/**
	 * Step 10
	 * Create reaction types based on current settings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step7()
	{
		$position = \IPS\Db::i()->select( 'max(reaction_position)', 'core_reactions' )->first();

		/* Path fallback if a custom AdminCP directory is used. */
		$path = \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY;
		if( \IPS\CP_DIRECTORY != 'admin' AND !file_exists( \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY . '/install/reaction' ) )
		{
			$path = \IPS\ROOT_PATH . '/admin';
		}

		/* Now insert a positive or negative row based on settings and then run queries to adjust current reputation */
		switch( \IPS\Settings::i()->reputation_point_types )
		{				
			case 'positive':
				$fileObj = \IPS\File::create( 'core_Reaction', 'react_up.png', file_get_contents( $path . '/install/reaction/react_up.png' ), 'reactions', FALSE, NULL, FALSE );
				$id = \IPS\Db::i()->insert( 'core_reactions', array(
					'reaction_value'	=> 1,
					'reaction_icon'		=> (string) $fileObj,
					'reaction_position'	=> ++$position,
					'reaction_enabled'	=> 1,
				) );
				\IPS\Lang::saveCustom( 'core', 'reaction_title_' . $id, 'Upvote' );
				break;
			
			case 'negative':
				$fileObj = \IPS\File::create( 'core_Reaction', 'react_down.png', file_get_contents( $path . '/install/reaction/react_down.png' ), 'reactions', FALSE, NULL, FALSE );
				$id = \IPS\Db::i()->insert( 'core_reactions', array(
					'reaction_value'	=> -1,
					'reaction_icon'		=> (string) $fileObj,
					'reaction_position'	=> ++$position,
					'reaction_enabled'	=> 1,
				) );
				\IPS\Lang::saveCustom( 'core', 'reaction_title_' . $id, 'Downvote' );
				break;
			
			case 'both':
				$fileObj = \IPS\File::create( 'core_Reaction', 'react_up.png', file_get_contents( $path . '/install/reaction/react_up.png' ), 'reactions', FALSE, NULL, FALSE );
				$posId = \IPS\Db::i()->insert( 'core_reactions', array(
					'reaction_value'	=> 1,
					'reaction_icon'		=> (string) $fileObj,
					'reaction_position'	=> ++$position,
					'reaction_enabled'	=> 1,
				) );
				\IPS\Lang::saveCustom( 'core', 'reaction_title_' . $posId, 'Upvote' );
				
				$fileObj = \IPS\File::create( 'core_Reaction', 'react_down.png', file_get_contents( $path . '/install/reaction/react_down.png' ), 'reactions', FALSE, NULL, FALSE );
				$negId = \IPS\Db::i()->insert( 'core_reactions', array(
					'reaction_value'	=> -1,
					'reaction_icon'		=> (string) $fileObj,
					'reaction_position'	=> ++$position,
					'reaction_enabled'	=> 1,
				) );
				\IPS\Lang::saveCustom( 'core', 'reaction_title_' . $negId, 'Downvote' );
				break;
		}
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step7CustomTitle()
	{
		return "Creating default reactions";
	}

	/**
	 * Step 11
	 * Map reaction types based on current settings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step8()
	{
		/* Now insert a positive or negative row based on settings and then run queries to adjust current reputation */
		switch( \IPS\Settings::i()->reputation_point_types )
		{
			case 'like':
				$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
					'table'			=> 'core_reputation_index',
					'query'			=> "UPDATE `" . \IPS\Db::i()->prefix . "core_reputation_index` SET reaction = 1 WHERE rep_rating = 1;"
				) ) );
				break;

			case 'positive':
				$id = \IPS\Db::i()->select( 'reaction_id', 'core_reactions', array( "reaction_icon LIKE CONCAT( '%', ? )", 'react_up.png' ) )->first();
				$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
					'table'			=> 'core_reputation_index',
					'query'			=> "UPDATE `" . \IPS\Db::i()->prefix . "core_reputation_index` SET reaction = {$id} WHERE rep_rating = 1;"
				) ) );
				break;

			case 'negative':
				$id = \IPS\Db::i()->select( 'reaction_id', 'core_reactions', array( "reaction_icon LIKE CONCAT( '%', ? )", 'react_down.png' ) )->first();
				$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
					'table'				=> 'core_reputation_index',
					'query'				=> "UPDATE `" . \IPS\Db::i()->prefix . "core_reputation_index` SET reaction = {$id} WHERE rep_rating = -1;"
				) ) );
				break;

			case 'both':
				$negId = \IPS\Db::i()->select( 'reaction_id', 'core_reactions', array( "reaction_icon LIKE CONCAT( '%', ? )", 'react_down.png' ) )->first();
				$posId = \IPS\Db::i()->select( 'reaction_id', 'core_reactions', array( "reaction_icon LIKE CONCAT( '%', ? )", 'react_up.png' ) )->first();
				$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
					'table'				=> 'core_reputation_index',
					'query'				=> "UPDATE `" . \IPS\Db::i()->prefix . "core_reputation_index` SET reaction = {$negId} WHERE rep_rating = -1;"
				),array(
					'table'			=> 'core_reputation_index',
					'query'			=> "UPDATE `" . \IPS\Db::i()->prefix . "core_reputation_index` SET reaction = {$posId} WHERE rep_rating = 1;"
				) ) );
				break;
		}

		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 9 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step8CustomTitle()
	{
		return "Mapping existing reactions";
	}

	/**
	 * Step 12
	 * Fix broken ranks
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step9()
	{
		\IPS\Db::i()->update( 'core_member_ranks', 'pips=100', array('pips!=? AND pips IS NOT NULL AND pips>?', '', 100 ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step9CustomTitle()
	{
		return 'Fixing ranks';
	}

	/**
	 * Step 13
	 * Convert group promotion rules
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step10()
	{
		$position = 1;

		foreach( \IPS\Db::i()->select( '*', 'core_groups' ) as $group )
		{
			if( $group['g_promotion'] != '-1&-1' )
			{
				list( $gid, $gUnit ) = explode( '&', $group['g_promotion'] );

				if ( $gid > 0 and $gUnit > 0 )
				{
					$filters = array();

					/* Join date */
					if( $group['g_promotion_type'] == 1 )
					{
						$filters['core_Joined'] = array( 'days' => $gUnit );
					}
					/* Reputation */
					elseif( $group['g_promotion_type'] == 2 )
					{
						$filters['core_Reputation'] = array( 'reputation_operator' => ( $gUnit == 1 ) ? 'eq' : 'gt', 'reputation_score' => ( $gUnit == 1 ) ? $gUnit : ( $gUnit - 1 ) );
					}
					/* Post count */
					else
					{
						$filters['core_Content'] = array( 'content_count_operator' => ( $gUnit == 1 ) ? 'eq' : 'gt', 'content_count_score' => ( $gUnit == 1 ) ? $gUnit : ( $gUnit - 1 ) );
					}

					$filters['core_Group'] = array( 'groups' => $group['g_id'] );

					$insertId = \IPS\Db::i()->insert( 'core_group_promotions', array(
						'promote_position'		=> $position,
						'promote_enabled'		=> 1,
						'promote_filters'		=> json_encode( $filters ),
						'promote_actions'		=> json_encode( array( 'primary_group' => $gid, 'secondary_group' => array(), 'secondary_remove' => array() ) )
					) );

					$defaultLanguageId =\IPS\Lang::defaultLanguage();

					\IPS\Lang::saveCustom( 'core', "g_promotion_" . $insertId, 'From: ' . \IPS\Lang::load($defaultLanguageId)->get( "core_group_{$group['g_id']}" ) );

					$position++;
				}
			}
		}

		/* Now drop the group columns that aren't needed */
		\IPS\Db::i()->dropColumn( 'core_groups', array( 'g_promotion_type', 'g_promotion' ) );

		/* And finally, set up the exclude flag by default for all admin and moderator groups */
		foreach( \IPS\Db::i()->select( 'id', 'core_moderators', array( 'type=?', 'g' ) ) as $moderator )
		{
			\IPS\Db::i()->update( 'core_groups', array( 'g_promote_exclude' => 1 ), array( 'g_id=?', $moderator ) );
		}

		foreach( \IPS\Db::i()->select( 'row_id', 'core_admin_permission_rows', array( 'row_id_type=?', 'group' ) ) as $admin )
		{
			\IPS\Db::i()->update( 'core_groups', array( 'g_promote_exclude' => 1 ), array( 'g_id=?', $admin ) );
		}

		unset( \IPS\Data\Store::i()->groups );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step10CustomTitle()
	{
		return 'Converting group promotion rules';
	}
	
	/**
	 * Step 14
	 * Convert Profile Fields to Profile Completion Steps
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step11()
	{
		if ( \IPS\Settings::i()->use_coppa OR \IPS\Settings::i()->minimum_age )
		{
			try
			{
				\IPS\Db::i()->select( 'conf_value', 'core_sys_conf_settings', array( 'conf_key=?', 'quick_register' ) )->first();
			}
			catch( \UnderflowException $ex )
			{
				$insert = array(
					'conf_key'      => 'quick_register',
					'conf_value'    => 0,
					'conf_default'  => 1, 
					'conf_keywords' => ''
				);
				
				/* This key was added in 4.0.8 so it may not exist */
				\IPS\Db::i()->insert( 'core_sys_conf_settings', $insert );
			}
			
			\IPS\Settings::i()->changeValues( array( 'quick_register' => 0 ) );
		}
		
		$required = array();
		foreach( \IPS\Db::i()->select( '*', 'core_pfields_data', array( "pf_not_null=? AND pf_admin_only!=?", 1, 1 ) ) AS $row )
		{
			$required[] = "core_pfield_{$row['pf_id']}";
		}
		
		if ( \count( $required ) )
		{
			$step						= new \IPS\Member\ProfileStep;
			$step->required				= 1;
			$step->extension			= 'core_ProfileFields';
			$step->completion_act		= "profile_fields";
			$step->subcompletion_act	= $required;
			$step->save();
			
			\IPS\Lang::saveCustom( 'core', "profile_step_title_{$step->id}", "Required Profile Information" );
			\IPS\Lang::saveCustom( 'core', "profile_step_text_{$step->id}", "Required Profile Information" );
		}
		
		$optional = array();
		foreach( \IPS\Db::i()->select( '*', 'core_pfields_data', array( "pf_show_on_reg=? AND pf_not_null=? AND pf_admin_only!=?", 1, 0, 1 ) ) AS $row )
		{
			$optional[] = "core_pfield_{$row['pf_id']}";
		}
		
		if ( \count( $optional ) )
		{
			$step						= new \IPS\Member\ProfileStep;
			$step->required				= 0;
			$step->extension			= 'core_ProfileFields';
			$step->completion_act		= "profile_fields";
			$step->subcompletion_act	= $optional;
			$step->save();
			
			\IPS\Lang::saveCustom( 'core', "profile_step_title_{$step->id}", "Optional Profile Information" );
			\IPS\Lang::saveCustom( 'core', "profile_step_text_{$step->id}", "Optional Profile Information" );
		}
		
		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step11CustomTitle()
	{
		return 'Setting up profile completion';
	}

	/**
	 * Step 15
	 * Enable social promotion feature for admins
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step12()
	{
		foreach( \IPS\Db::i()->select( 'row_id', 'core_admin_permission_rows', array( 'row_id_type=?', 'group' ) ) as $admin )
		{
			\IPS\Db::i()->update( 'core_groups', "g_bitoptions2=g_bitoptions2|8", array( 'g_id=?', $admin ) );
		}
		
		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step12CustomTitle()
	{
		return 'Enabling social promotion for administrators';
	}

	/**
	 * Step 16
	 * Delete any orphaned moderator log entries
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step13()
	{
		/* Remove the offending entries */
		\IPS\Db::i()->returnQuery = TRUE;
		$query = array(
			'table' => 'core_moderator_logs',
			'query' => \IPS\Db::i()->delete( 'core_moderator_logs', array( 'appcomponent NOT IN ( ' . \IPS\Db::i()->select( 'app_directory', 'core_applications' ) . ' )' ) )
		);
		\IPS\Db::i()->returnQuery = FALSE;

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( $query ) );

		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 14 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return true;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step13CustomTitle()
	{
		return 'Removing orphaned moderator logs';
	}

	/**
	 * Step 17
	 * Create a new default 4.2 theme
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step14()
	{
		/* This step is no longer needed following AdminCP theme changes in 4.5 */

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step14CustomTitle()
	{
		return 'Creating 4.2 theme';
	}

	/**
	 * Step 18
	 * Add clubs menu entry
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step15()
	{
		\IPS\core\FrontNavigation::insertMenuItem( NULL, array( 'app' => 'core', 'key' => 'Clubs' ), \IPS\Db::i()->select( 'MAX(position)', 'core_menu' )->first() );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step15CustomTitle()
	{
		return 'Adding clubs menu item';
	}

	/**
	 * Finish - This is run after all apps have been upgraded
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 * @note	We opted not to let users run this immediately during the upgrade because of potential issues (it taking a long time and users stopping it or getting frustrated) but we can revisit later
	 */
	public function finish()
	{
		\IPS\Content\Search\Index::i()->rebuild();
	}
}