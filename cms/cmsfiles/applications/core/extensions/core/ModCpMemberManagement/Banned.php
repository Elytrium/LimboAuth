<?php
/**
 * @brief		Moderator Control Panel Member Management Extension: Banned Users
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		29 Oct 2013
 */

namespace IPS\core\extensions\core\ModCpMemberManagement;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Banned Ussers
 */
class _Banned
{
	/**
	 * Returns the tab key for the navigation bar
	 *
	 * @return	string|null
	 */
	public function getTab()
	{
		return 'banned';
	}
	
	/**
	 * Get content to display
	 *
	 * @return	string
	 */
	public function manage()
	{
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_members', \IPS\Http\Url::internal( 'app=core&module=modcp&tab=members&area=banned' ), 'temp_ban<>0' );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'modcp', 'core', 'front' ), 'memberManagementRow' );
		
		$table->langPrefix = 'members_';
		
		/* Columns we need */
		$table->include = array( 'member_id', 'name', 'email', 'joined', 'member_group_id', 'photo', 'member_id' );
		$table->mainColumn = 'name';
		
		/* Custom parsers */
		$table->parsers = array(
				'name'				=> function( $val, $row )
				{
					if ( $row['temp_ban'] == "-1" )
					{
						return \IPS\Member::loggedIn()->language()->addToStack( 'modcp_status_banned', FALSE, array( 'sprintf' => array( $val ) ) );
					}
					else
					{
						return \IPS\Member::loggedIn()->language()->addToStack( 'modcp_status_suspended', FALSE, array( 'sprintf' => array( $val, \IPS\DateTime::ts( $row['temp_ban'] )->localeDate() ) ) );
					}
				},
				'joined'			=> function( $val, $row )
				{
					return \IPS\DateTime::ts( $val )->localeDate();
				},
				'member_group_id'	=> function( $val, $row )
				{
					return \IPS\Member\Group::load( $val )->formattedName;
				},
				'photo' => function( $val, $row )
				{
					return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( \IPS\Member::constructFromData( $row ), 'mini' );
				},
		);
		
		/* Individual member actions */
		$table->rowButtons = function( $row )
		{
			$member = \IPS\Member::constructFromData( $row );
				
			$return = array();
				
			$return['edit'] = array(
					'icon'		=> 'pencil',
					'title'		=> 'edit',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=profile&do=edit&id=' . $member->member_id, 'front', 'edit_profile', $member->members_seo_name )
			);
			
			$return['warn'] = array(
					'icon'		=> '',
					'title'		=> 'modcp_view_warnings',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=system&controller=warnings&id=' . $member->member_id, 'front', 'warn_list', $member->members_seo_name )
			);
			
			$return['contact'] = array(
					'icon'		=> 'envelope',
					'title'		=> 'message',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=messaging&controller=messenger&do=compose&to=' . $member->member_id, 'front', 'messenger_compose', $member->members_seo_name )
			);
				
			return $return;
		};
		
		/* Default sort options */
		$table->sortBy = $table->sortBy ?: 'joined';
		$table->sortDirection = $table->sortDirection ?: 'desc';
		
		return (string) $table;
	}
}