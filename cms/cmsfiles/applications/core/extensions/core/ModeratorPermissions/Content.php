<?php
/**
 * @brief		Moderator Permissions: Content
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		9 Jul 2013
 */

namespace IPS\core\extensions\core\ModeratorPermissions;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Moderator Permissions: Content
 */
class _Content
{
	/**
	 * Get Permissions
	 *
	 * @param	array	$toggles	Toggle data
	 * @code
	 	return array(
	 		'key'	=> 'YesNo',	// Can just return a string with type
	 		'key'	=> array(	// Or an array for more options
	 			'YesNo'				// Type
	 			array( ... )		// Options (as defined by type's class)
	 			'prefix',			// Prefix
	 			'suffix'			// Suffix
	 		),
	 		...
	 	);
	 * @endcode
	 * @return	array
	 */
	public function getPermissions( $toggles )
	{
		$return = array(
			'can_pin_content'				=> array( 'YesNo', array( 'togglesOff' => $toggles['pin'] ) ),
			'can_unpin_content'				=> array( 'YesNo', array( 'togglesOff' => $toggles['unpin'] ) ),
			'can_feature_content'			=> array( 'YesNo', array( 'togglesOff' => $toggles['feature'] ) ),
			'can_unfeature_content'			=> array( 'YesNo', array( 'togglesOff' => $toggles['unfeature'] ) ),
			'can_edit_content'				=> array( 'YesNo', array( 'togglesOff' => $toggles['edit'] ) ),
			'can_hide_content'				=> array( 'YesNo', array( 'togglesOff' => $toggles['hide'] ) ),
			'can_unhide_content'			=> array( 'YesNo', array( 'togglesOff' => $toggles['unhide'] ) ),
			'can_view_hidden_content'		=> array( 'YesNo', array( 'togglesOff' => $toggles['view_hidden'] ) ),
			'can_future_publish_content'	=> array( 'YesNo', array( 'togglesOff' => $toggles['future_publish'] ) ),
			'can_view_future_content'		=> array( 'YesNo', array( 'togglesOff' => $toggles['view_future'] ) ),
			'can_move_content'				=> array( 'YesNo', array( 'togglesOff' => $toggles['move'] ) ),
			'can_lock_content'				=> array( 'YesNo', array( 'togglesOff' => $toggles['lock'] ) ),
			'can_unlock_content'			=> array( 'YesNo', array( 'togglesOff' => $toggles['unlock'] ) ),
			'can_reply_to_locked_content'	=> array( 'YesNo', array( 'togglesOff' => $toggles['reply_to_locked'] ) ),
			'can_delete_content'			=> array( 'YesNo', array( 'togglesOff' => $toggles['delete'] ) ),
			'can_split_merge_content'		=> array( 'YesNo', array( 'togglesOff' => $toggles['split_merge'] ) ),
			'can_feature_comments_content'	=> array( 'YesNo', array( 'togglesOff' => $toggles['feature_comments'] ) ),
			'can_unfeature_comments_content'=> array( 'YesNo', array( 'togglesOff' => $toggles['unfeature_comments'] ) ),
			'can_add_item_message_content'	=> array( 'YesNo', array( 'togglesOff' => $toggles['add_item_message'] ) ),
			'can_edit_item_message_content'	=> array( 'YesNo', array( 'togglesOff' => $toggles['edit_item_message'] ) ),
			'can_delete_item_message_content'=> array( 'YesNo', array( 'togglesOff' => $toggles['delete_item_message'] ) ),
			'can_toggle_item_moderation_content' => array( 'YesNo', array( 'togglesOff' => $toggles['toggle_item_moderation'] ) ),
			'can_recognize_content'			=> array( 'YesNo', array( 'togglesOn' => [ 'can_recognize_content_options', 'can_recognize_content_no_point_limit' ] ) ),
			'can_recognize_content_options' => array( 'CheckboxSet', array(
				'options' => [
					'points' => 'can_recognize_content_points',
					'badges' => 'can_recognize_content_badges',
					]
			) ),
			'can_recognize_content_no_point_limit' => 'YesNo'
		);
		
		if ( \IPS\Settings::i()->edit_log == 2 )
		{
			$return['can_view_editlog'] = 'YesNo';
		}
		
		$return['can_view_moderation_log'] = 'YesNo';
		$return['can_view_reports'] = 'YesNo';
		$return['can_manage_announcements']	= 'YesNo';
		
		$return['can_see_poll_voters']	= 'YesNo';
		$return['can_close_polls'] 		= 'YesNo';
		
		$return['can_manage_deleted_content'] = 'YesNo';
		$return['can_view_anonymous_posters'] = 'YesNo';

		$return['can_manage_alerts']	= 'YesNo';
		
		return $return;
	}
	
	/**
	 * After change
	 *
	 * @param	array	$moderator	The moderator
	 * @param	array	$changed	Values that were changed
	 * @return	void
	 */
	public function onChange( $moderator, $changed )
	{
		/* Rebuild the create menu cache if the announcement permission was changed */
		if ( $changed == 'can_manage_announcements' )
		{
			\IPS\Member::clearCreateMenu();
		}
	}
	
	/**
	 * After delete
	 *
	 * @param	array	$moderator	The moderator
	 * @return	void
	 */
	public function onDelete( $moderator )
	{
		
	}
}