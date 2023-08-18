<?php
/**
 * @brief		Member Sync
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		31 Mar 2014
 */

namespace IPS\core\extensions\core\MemberSync;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member Sync
 */
class _System
{
	/**
	 * Account Created
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	void
	 */
	public function onCreateAccount( $member )
	{
		\IPS\Api\Webhook::fire( 'member_create', $member, $member->webhookFilters() );

		if( $member->id AND $member->completed )
		{
			\IPS\Api\Webhook::fire( 'member_registration_complete', $member, $member->webhookFilters() );
		}
		
		if ( \IPS\Dispatcher::hasInstance() and \IPS\Dispatcher::i()->controllerLocation === 'front' and isset( \IPS\Request::i()->cookie[ 'referred_by' ] ) )
		{
			\IPS\Member::load( \IPS\Request::i()->cookie[ 'referred_by' ] )->addReferral( $member );
			\IPS\Request::i()->setCookie( 'referred_by', NULL );
		}
	}

	/**
	 * Member is merged with another member
	 *
	 * @param	\IPS\Member	$member		Member being kept
	 * @param	\IPS\Member	$member2	Member being removed
	 * @return	void
	 */
	public function onMerge( $member, $member2 )
	{
		/* Check and remove any reputation the users have given each other */
		if ( !\IPS\Settings::i()->reputation_can_self_vote )
		{
			$repWhere = array( '(member_id=? AND member_received=?) OR (member_id=? AND member_received=?)', $member->member_id, $member2->member_id, $member2->member_id, $member->member_id );

			$sum = \IPS\Db::i()->select( 'sum( rep_rating )', 'core_reputation_index', $repWhere )->first();
			\IPS\Db::i()->delete( 'core_reputation_index', $repWhere );

			$member->pp_reputation_points = $member->pp_reputation_points + $member2->pp_reputation_points - $sum;
		}
		else
		{
			$member->pp_reputation_points = $member->pp_reputation_points + $member2->pp_reputation_points;
		}

		/* Standard stuff */
		foreach ( array(
				array( 'core_admin_logs', 'member_id' ),
				array( 'core_advertisements', 'ad_member' ),
				array( 'core_announcements', 'announce_member_id' ),
				array( 'core_attachments', 'attach_member_id' ),
				array( 'core_clubs', 'owner' ),
				array( 'core_clubs_memberships', 'member_id' ),
				array( 'core_edit_history', 'member' ),
				array( 'core_error_logs', 'log_member' ),
				array( 'core_follow', 'follow_member_id' ),
				array( 'core_ignored_users', 'ignore_owner_id' ),
				array( 'core_ignored_users', 'ignore_ignore_id' ),
				array( 'core_incoming_emails', 'rule_added_by' ),
				array( 'core_member_history', 'log_member' ),
				array( 'core_member_history', 'log_by' ),
				array( 'core_members_warn_logs', 'wl_member' ),
				array( 'core_members_warn_logs', 'wl_moderator' ),
				array( 'core_message_posts', 'msg_author_id' ),
				array( 'core_message_topic_user_map', 'map_user_id' ),
				array( 'core_message_topics', 'mt_starter_id' ),
				array( 'core_moderator_logs', 'member_id' ),
				array( 'core_notification_preferences', 'member_id' ),
				array( 'core_notifications', 'member' ),
				array( 'core_oauth_server_access_tokens', 'member_id' ),
				array( 'core_polls', 'starter_id' ),
				array( 'core_profile_completion', 'member_id' ),
				array( 'core_ratings', 'member' ),
				array( 'core_rc_comments', 'comment_by' ),
				array( 'core_rc_index', 'first_report_by' ),
				array( 'core_rc_index', 'author' ),
				array( 'core_rc_reports', 'report_by' ),
				array( 'core_reputation_index', 'member_id' ),
				array( 'core_reputation_index', 'member_received' ),
				array( 'core_soft_delete_log', 'sdl_obj_member_id' ),
				array( 'core_sys_social_group_members', 'member_id' ),
				array( 'core_sys_social_groups', 'owner_id' ),
				array( 'core_tags', 'tag_member_id' ),
				array( 'core_upgrade_history', 'upgrade_mid' ),
				array( 'core_voters', 'member_id' ),
				array( 'core_saved_charts', 'chart_member' ),
				array( 'core_members_known_devices', 'member_id' ),
				array( 'core_members_known_ip_addresses', 'member_id' ),
				array( 'core_streams', 'member' ),
				array( 'core_rss_import', 'rss_import_member' ),
				array( 'core_anonymous_posts', 'anonymous_member_id' ),
				array( 'core_member_badges', 'member' ),
				array( 'core_notifications_pwa_keys', 'member' ),
				array( 'core_members_logins', 'member_id' ),
				array( 'core_stream_subscriptions', 'member_id' ),
			    array( 'core_alerts', 'alert_member_id' ),
			    array( 'core_alerts', 'alert_recipient_user' ),
			) as $toMerge
		)
		{
			/* core_follow is a bit special because two columns need updating. */
			if ( $toMerge[0] == 'core_follow' )
			{
				\IPS\Db::i()->update( 'core_follow', "`follow_id` = MD5( CONCAT( `follow_app`, ';', `follow_area`, ';', `follow_rel_id`, ';', {$member->member_id} ) ), `follow_member_id` = {$member->member_id}", array( "follow_member_id=?", $member2->member_id ), array(), NULL, \IPS\Db::IGNORE );
			}
			else
			{
				\IPS\Db::i()->update( $toMerge[0], array( $toMerge[1] => $member->member_id ), array( '`' . $toMerge[1] . '`=?', $member2->member_id ), array(), NULL, \IPS\Db::IGNORE );
			}
		}

		/* Referrals */
		\IPS\Db::i()->update( 'core_referrals', array( 'member_id' => $member->member_id ), array( 'member_id=?', $member2->member_id ), array(), NULL, \IPS\Db::IGNORE );
		\IPS\Db::i()->update( 'core_referrals', array( 'referred_by' => $member->member_id ), array( 'referred_by=?', $member2->member_id ) );

		/* Admin/Mod */
		\IPS\Db::i()->update( 'core_admin_permission_rows', array( 'row_id' => $member->member_id ), array( 'row_id=? AND row_id_type=?', $member2->member_id, 'member' ), array(), NULL, \IPS\Db::IGNORE );
		\IPS\Db::i()->update( 'core_leaders', array( 'leader_type_id' => $member->member_id ), array( 'leader_type_id=? AND leader_type=?', $member2->member_id, 'm' ), array(), NULL, \IPS\Db::IGNORE );
		\IPS\Db::i()->update( 'core_moderators', array( 'id' => $member->member_id ), array( 'id=? AND type=?', $member2->member_id, 'm' ), array(), NULL, \IPS\Db::IGNORE );

		/* Followers */
		\IPS\Db::i()->update( 'core_follow', array( 'follow_rel_id' => $member->member_id ), array( 'follow_app=? AND follow_area=? AND follow_rel_id=?', 'core', 'member', $member2->member_id ), array(), NULL, \IPS\Db::IGNORE );

		/* Solved */
		\IPS\Db::i()->update( 'core_solved_index', array( 'member_id' => $member->member_id ), array( 'member_id=?', $member2->member_id ), array(), NULL, \IPS\Db::IGNORE );

		/* Achievements */
		\IPS\Db::i()->update( 'core_achievements_log_milestones', array( 'milestone_member_id' => $member->member_id ), array( 'milestone_member_id=?', $member2->member_id ), array(), NULL, \IPS\Db::IGNORE );

		/* Delete duplicate stuff */
		\IPS\Db::i()->delete( 'core_item_markers', array( 'item_member_id=?', $member2->member_id ) );
		\IPS\Db::i()->delete( 'core_security_answers', array( 'answer_member_id=?', $member2->member_id ) );
		\IPS\Db::i()->delete( 'core_sessions', array( 'member_id=?', $member2->member_id ) );
		\IPS\Db::i()->delete( 'core_sys_cp_sessions', array( 'session_member_id=?', $member2->member_id ) );
		\IPS\Db::i()->delete( 'core_validating', array( 'member_id=?', $member2->member_id ) );
		\IPS\Db::i()->delete( array( 'row1' => 'core_reputation_index', 'row2' => 'core_reputation_index' ), 'row1.id > row2.id AND row1.member_id = row2.member_id AND row1.app = row2.app AND row1.type = row2.type AND row1.type_id = row2.type_id AND row1.member_id IN(' . $member->member_id . ',' . $member2->member_id . ')', NULL, NULL, NULL, 'row1' );
		\IPS\Db::i()->delete( 'core_message_topic_user_map', array( 'map_user_id=?', $member2->member_id ) );
		\IPS\Db::i()->delete( array( 'row1' => 'core_message_topic_user_map', 'row2' => 'core_message_topic_user_map' ), 'row1.map_id > row2.map_id AND row1.map_user_id = row2.map_user_id AND row1.map_topic_id = row2.map_topic_id AND row1.map_user_id IN(' . $member->member_id . ',' . $member2->member_id . ')', NULL, NULL, NULL, 'row1' );
		\IPS\Db::i()->delete( 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=follow_member_id', 'core', 'member') );
		\IPS\Db::i()->delete( 'core_follow_count_cache', array( 'id=? AND class=?', $member2->member_id, 'IPS\core\Member' ) );
		\IPS\Db::i()->delete( 'core_acp_notifcations_dismissals', array( '`member`=?', $member2->member_id ) );
		\IPS\Db::i()->delete( 'core_acp_notifications_preferences', array( '`member`=?', $member2->member_id ) );
		\IPS\Db::i()->delete( 'core_marketplace_tokens', array( 'id=?', $member2->member_id ) );
		\IPS\Db::i()->delete( 'core_achievements_log_milestones', array( 'milestone_member_id=?', $member2->member_id ) );
		\IPS\Db::i()->delete( 'core_member_privacy_actions', ['member_id=?', $member2->member_id] );

		/* Update PM recipient counts */
		foreach( \IPS\Db::i()->select( 'map_topic_id', 'core_message_topic_user_map', array( 'map_user_id=?', $member->member_id ) ) as $topicId )
		{
			\IPS\Db::i()->update( 'core_message_topics', array( 'mt_to_count' => \IPS\Db::i()->select( 'count(*)', 'core_message_topic_user_map', array( 'map_topic_id=?', $topicId ) ) ), array( 'mt_id=?', $topicId ) );
		}

		/* If one user is following both of the members involved in the merge, there would be duplicates */
		$uniqueFollows		= array();
		$duplicateFollows	= array();

		foreach( \IPS\Db::i()->select( '*', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=?', 'core', 'member', $member->member_id ) ) as $follow )
		{
			if( !\in_array( $follow['follow_member_id'], $uniqueFollows ) )
			{
				$uniqueFollows[]	= $follow['follow_member_id'];
			}
			else
			{
				$duplicateFollows[]	= $follow['follow_id'];
			}
		}

		if( \count( $duplicateFollows ) )
		{
			\IPS\Db::i()->delete( 'core_follow', array( "follow_id IN('" . implode( "','", $duplicateFollows ) . "')" ) );
		}

		/* If both of the users involed in the merge is following a single member, there would be duplicates */
		$uniqueFollows		= array();
		$duplicateFollows	= array();

		foreach( \IPS\Db::i()->select( '*', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_member_id=?', 'core', 'member', $member->member_id ) ) as $follow )
		{
			if( !\in_array( $follow['follow_rel_id'], $uniqueFollows ) )
			{
				$uniqueFollows[]	= $follow['follow_rel_id'];
			}
			else
			{
				$duplicateFollows[]	= $follow['follow_id'];
			}
		}

		if( \count( $duplicateFollows ) )
		{
			\IPS\Db::i()->delete( 'core_follow', array( "follow_id IN('" . implode( "','", $duplicateFollows ) . "')" ) );
		}
		
		/* Remove duplicate PWA subscriptions */
		$duplicatePwa = array();
		$uniquePwa = array();
		foreach( \IPS\Db::i()->select( '*', 'core_notifications_pwa_keys', array( "`member`=?", $member->member_id ) ) AS $pwa )
		{
			$key = "{$pwa['p256dh']}-{$pwa['auth']}";
			if ( !\in_array( $key, $uniquePwa ) )
			{
				$uniquePwa[ $pwa['id'] ] = $key;
			}
			else
			{
				$duplicatePwa[ $pwa['id'] ] = $key;
			}
		}
		
		if ( \count( $duplicatePwa ) )
		{
			\IPS\Db::i()->delete( 'core_notifications_pwa_keys', array( \IPS\Db::i()->in( 'id', \array_keys( $duplicatePwa ) ) ) );
		}

		/* Set warning level */
		$member->warn_level				+= $member2->warn_level;

		/* Recount notifications */
		$member->recountNotifications();

		/* Rebuild permission array */
		$member->rebuildPermissionArray();

		\IPS\Api\Webhook::fire( 'member_merged', ['kept' => $member->apiOutput(), 'removed' => $member2->apiOutput()] );
	}
	
	/**
	 * Member is deleted
	 *
	 * @param	$member	\IPS\Member	The member
	 * @return	void
	 */
	public function onDelete( $member )
	{
		/* We have to remove notifications for these, otherwise once we remove the actual items below any existing notifications will throw an
			uncaught exception since the status or reply object won't be loaded */
		foreach( \IPS\Db::i()->select( 'reply_id', 'core_member_status_replies', array( 'reply_member_id=?', $member->member_id ) ) as $reply )
		{
			\IPS\Db::i()->delete( 'core_notifications', array( 'item_class=? AND item_id=?', 'IPS\\core\\Statuses\\Reply', $reply ) );

			/* Remove item from deletion log */
			\IPS\Db::i()->delete( 'core_deletion_log', array( "dellog_content_class=? AND dellog_content_id=?", "IPS\\core\\Statuses\\Reply", $reply ) );
			
			/* Remove reports */
			\IPS\Db::i()->delete( 'core_rc_index', array( "class=? AND content_id=?", "IPS\\core\\Statuses\\Reply", $reply ) );		
		}
		
		\IPS\Content\Search\Index::i()->removeClassFromSearchIndex( 'IPS\core\Statuses\Reply', NULL, $member->member_id );

		foreach( \IPS\Db::i()->select( 'status_id', 'core_member_status_updates', array( 'status_member_id=? OR status_author_id=?', $member->member_id, $member->member_id ) ) as $status )
		{
			\IPS\Db::i()->delete( 'core_notifications', array( 'item_class=? AND item_id=?', 'IPS\\core\\Statuses\\Status', $status ) );

			/* Remove item from deletion log */
			\IPS\Db::i()->delete( 'core_deletion_log', array( "dellog_content_class=? AND dellog_content_id=?", "IPS\\core\\Statuses\\Status",  $status ) );
		
			/* Remove reports */
			\IPS\Db::i()->delete( 'core_rc_index', array( "class=? AND content_id=?", "IPS\\core\\Statuses\\Reply",  $status ) );
		}

		\IPS\Content\Search\Index::i()->removeClassFromSearchIndex( 'IPS\core\Statuses\Status', NULL, $member->member_id );
		\IPS\Content\Search\Index::i()->removeClassFromSearchIndex( 'IPS\core\Statuses\Status', $member->member_id );

		/* Generic deletes */
		foreach ( array(
			array( 'core_clubs_memberships', 'member_id' ),
			array( 'core_error_logs', 'log_member' ),
			array( 'core_follow', 'follow_member_id' ),
			array( 'core_ignored_users', 'ignore_owner_id' ),
			array( 'core_ignored_users', 'ignore_ignore_id' ),
			array( 'core_item_markers', 'item_member_id' ),
			array( 'core_member_history', 'log_member' ),
			array( 'core_members_warn_logs', 'wl_member' ),
			array( 'core_notification_preferences', 'member_id' ),
			array( 'core_notifications', 'member' ),
			array( 'core_oauth_server_access_tokens', 'member_id' ),
			array( 'core_pfields_content', 'member_id' ),
			array( 'core_profile_completion', 'member_id' ),
			array( 'core_ratings', 'member' ),
			array( 'core_reputation_index', 'member_id' ),
			array( 'core_reputation_index', 'member_received' ),
			array( 'core_security_answers', 'answer_member_id' ),
			array( 'core_sessions', 'member_id' ),
			array( 'core_sys_cp_sessions', 'session_member_id' ),
			array( 'core_sys_social_groups', 'owner_id' ),
			array( 'core_sys_social_group_members', 'member_id' ),
			array( 'core_validating', 'member_id' ),
            array( 'core_member_status_updates', 'status_member_id' ),
            array( 'core_member_status_updates', 'status_author_id' ),
            array( 'core_member_status_replies', 'reply_member_id' ),
            array( 'core_login_links', 'token_member' ),
            array( 'core_saved_charts', 'chart_member' ),
            array( 'core_members_known_devices', 'member_id' ),
            array( 'core_members_known_ip_addresses', 'member_id' ),
            array( 'core_acp_notifcations_dismissals', 'member' ),
            array( 'core_acp_notifications_preferences', 'member' ),
            array( 'core_streams', 'member' ),
			array( 'core_referrals', 'referred_by' ),
			array( 'core_referrals', 'member_id' ),
			array( 'core_anonymous_posts', 'anonymous_member_id' ),
			array( 'core_marketplace_tokens', 'id' ),
			array( 'core_member_badges', 'member' ),
			array( 'core_notifications_pwa_keys', 'member' ),
		    array( 'core_achievements_log_milestones', 'milestone_member_id' ),
			array( 'core_members_logins', 'member_id' ),
			array( 'core_stream_subscriptions', 'member_id' ),
			array( 'core_alerts_seen', 'seen_member_id' ),
			array( 'core_member_privacy_actions', 'member_id' ),
		) as $toDelete )
		{
			\IPS\Db::i()->delete( $toDelete[0], array( '`' . $toDelete[1] . '`=?', $member->member_id ) );
		}

		\IPS\Db::i()->update( 'core_announcements', array( 'announce_member_id' => 0 ), array( 'announce_member_id=?', $member->member_id ) );
		\IPS\Db::i()->update( 'core_attachments', array( 'attach_member_id' => 0 ), array( 'attach_member_id=?', $member->member_id ) );
		\IPS\Db::i()->update( 'core_clubs', array( 'owner' => NULL ), array( 'owner=?', $member->member_id ) );
		\IPS\Db::i()->update( 'core_edit_history', array( 'member' => 0 ), array( '`member`=?', $member->member_id ) );
		\IPS\Db::i()->update( 'core_incoming_emails', array( 'rule_added_by' => 0 ), array( 'rule_added_by=?', $member->member_id ) );
		\IPS\Db::i()->update( 'core_members_warn_logs', array( 'wl_moderator' => 0 ), array( 'wl_moderator=?', $member->member_id ) );
		\IPS\Db::i()->update( 'core_moderator_logs', array( 'member_id' => 0 ), array( 'member_id=?', $member->member_id ) );
		\IPS\Db::i()->update( 'core_polls', array( 'starter_id' => 0 ), array( 'starter_id=?', $member->member_id ) );
		\IPS\Db::i()->update( 'core_soft_delete_log', array( 'sdl_obj_member_id' => 0 ), array( 'sdl_obj_member_id=?', $member->member_id ) );
		\IPS\Db::i()->update( 'core_upgrade_history', array( 'upgrade_mid' => 0 ), array( 'upgrade_mid=?', $member->member_id ) );
		\IPS\Db::i()->update( 'core_voters', array( 'member_id' => 0 ), array( 'member_id=?', $member->member_id ) );
		\IPS\Db::i()->update( 'core_rss_import', array( 'rss_import_member' => 0 ), array( "rss_import_member=?", $member->member_id ) );
		
		\IPS\Db::i()->delete( 'core_acp_tab_order', array( 'id=?', $member->member_id ) );
		\IPS\Db::i()->delete( 'core_admin_permission_rows', array( 'row_id=? AND row_id_type=?', $member->member_id, 'member' ) );
		\IPS\Db::i()->delete( 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=?', 'core', 'member', $member->member_id ) );
		\IPS\Db::i()->delete( 'core_follow_count_cache', array( 'id=? AND class=?', $member->member_id, 'IPS\core\Member' ) );
		\IPS\Db::i()->delete( 'core_moderators', array( 'id=? AND type=?', $member->member_id, 'm' ) );
		\IPS\Db::i()->delete( 'core_member_recognize', array( 'r_member_id=? OR r_given_by=?', $member->member_id, $member->member_id ) );

		unset ( \IPS\Data\Store::i()->moderators );

		/* Remove the group from the staff directory */
		foreach ( \IPS\core\StaffDirectory\User::roots() as $staff )
		{
			if ( $staff->type == 'm' AND $staff->type_id == $member->member_id )
			{
				$staff->delete();
			}
		}

		\IPS\File::unclaimAttachments( 'core_Signatures', $member->member_id );
		\IPS\File::unclaimAttachments( 'core_Staffdirectory', $member->member_id );
		\IPS\File::unclaimAttachments( 'core_Members', NULL, NULL, $member->member_id );

		$member->deletePhoto();
		$member->coverPhoto()->delete();

		\IPS\Api\Webhook::fire( 'member_delete', $member, $member->webhookFilters() );
	}

	/**
	 * Member account has been updated
	 *
	 * @param	$member		\IPS\Member	Member updating profile
	 * @param	$changes	array		The changes
	 * @return	void
	 */
	public function onProfileUpdate( $member, $changes )
	{
		$groupIds = array();

		/* Were groups changed? */
		foreach( $changes as $k => $v )
		{
			if( $k == 'member_group_id' )
			{
				$groupIds[]	= $v;
			}
			elseif( $k == 'mgroup_others' )
			{
				$groupIds = array_unique( array_merge( $groupIds, explode( ',', $v ) ) );
			}
		}

		if( \count( $groupIds ) )
		{
			foreach( $groupIds as $id )
			{
				$key = 'groupMembersCount_' . $id;
				unset( \IPS\Data\Store::i()->$key );
			}

			/* Reset profile completion flag to see if there are any items to complete now permissions are elevated */
			$member->members_bitoptions['profile_completed'] = 0;
		}

		foreach( $member::$changesToSkipForMemberEditWebhooks as $fieldToRemove )
		{
			if( \in_array($fieldToRemove, $changes ) )
			{
				unset( $changes[$fieldToRemove] );
			}
		}

		if( \count( $changes ) )
		{
			$params = [
				'member' => $member->apiOutput(),
				'changes' => $changes
			];

			\IPS\Api\Webhook::fire( 'member_edited', $params, $member->webhookFilters() );
		}
	}
}
