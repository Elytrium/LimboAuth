<?php
/**
 * @brief		Upgrader: Custom Upgrade Options
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		5 Jun 2014
 */

$options = array();

/* Do any admins have ACP restrictions?  */
if( \IPS\Db::i()->select( 'COUNT(*)', 'admin_permission_rows' )->first() )
{
	$members	= array();

	foreach( \IPS\Db::i()->select( 'member_id, email, members_display_name', 'admin_permission_rows', array( 'row_id_type=?', 'member' ) )->join( 'members', 'member_id=row_id' ) as $member )
	{
		$members[ $member['member_id'] ]	= $member['members_display_name'];
	}

	foreach( \IPS\Db::i()->select( 'row_id', 'admin_permission_rows', array( 'row_id_type=?', 'group' ) ) as $group )
	{
		$group = (int) $group;

		foreach( \IPS\Db::i()->select( 'member_id, email, members_display_name', 'members', "member_group_id={$group} or mgroup_others like'%,{$group},%'" ) as $member )
		{
			$members[ $member['member_id'] ]	= $member['members_display_name'];
		}
	}


	/* Get the list of admins here to display and then output */
	$options[] = new \IPS\Helpers\Form\Custom( '40000_acp_restrictions', null, FALSE, array( 'getHtml' => function( $element ) use ( $members ){
		return "ACP restrictions cannot be converted during the upgrade. Please log in and review ACP restrictions for the following users once the upgrade is complete:<br><ul class='ipsField_fieldList ipsField_fieldList_content ipsList_bullets'><li>" . implode( '</li><li>', $members ) . "</li></ul>";
	} ), function( $val ) {}, NULL, NULL, '40000_acp_restrictions' );
}

$options[] = new \IPS\Helpers\Form\Radio( '40000_username_or_displayname', 'display_name', TRUE, array( 'options' => array( 'name' => '40000_name', 'display_name' => '40000_display_name' ) ) );