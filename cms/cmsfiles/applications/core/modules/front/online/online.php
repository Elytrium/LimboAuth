<?php
/**
 * @brief		Online Users
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 Aug 2013
 */

namespace IPS\core\modules\front\online;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Online Users
 */
class _online extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief These properties are used to specify datalayer context properties.
	 *
	 */
	public static $dataLayerContext = array(
		'community_area' =>  [ 'value' => 'online_user_list', 'odkUpdate' => 'true']
	);

	/**
	 * Show Online Users
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Set Session Location */
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=core&module=online&controller=online', 'front', 'online' ), array(), 'loc_viewing_online_users' );

		/* Sessions are written on shutdown so let's do it now instead */
		\IPS\Session\Front::i()->setTheme( \IPS\Member::loggedIn()->skin ?: 0 );
		session_write_close();
		
		/* Create the table */
		$table = new \IPS\core\Online\Table( \IPS\Http\Url::internal( 'app=core&module=online&controller=online', 'front', 'online' ) );
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'online', 'core', 'front' ), 'onlineUsersTable' );
		$table->rowsTemplate	  = array( \IPS\Theme::i()->getTemplate( 'online', 'core', 'front' ), 'onlineUsersRow' );
		$table->langPrefix = 'online_users_';
		$table->include = array( 'photo', 'member_name', 'location_lang', 'running_time', 'ip_address', 'login_type' );
		$table->limit = 30;

		/* Custom parsers */
		$table->parsers = array(
			'location_lang'	=> function( $val, $row )
			{
				return \IPS\Session\Front::getLocation( $row );
			},
			'photo' => function( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( \IPS\Member::load( $row['member_id'] ), 'mini' );
			},
			'running_time' => function( $val, $row )
			{
				return \IPS\DateTime::ts( $val )->relative();
			},
			'member_name' => function( $val, $row )
			{
				if( $row['member_id'] )
				{
					return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userLink( \IPS\Member::load( $row['member_id'] ) );
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'guest' );
				}
			},
		);
		
		$table->filters = array(
			'filter_loggedin'	=> 'filter_loggedin',
		);
		
		foreach ( \IPS\Member\Group::groups( TRUE, TRUE, TRUE ) as $group )
		{
			/* Alias the lang keys */
			$realLangKey = "core_group_{$group->g_id}";
			$fakeLangKey = "online_users_group_{$group->g_id}";
			\IPS\Member::loggedIn()->language()->words[ $fakeLangKey ] = \IPS\Member::loggedIn()->language()->addToStack( $realLangKey, FALSE );
			
			if( $group->g_id == \IPS\Settings::i()->guest_group )
			{
				$table->filters[ 'group_' . $group->g_id ] = $group->g_id;
			}
			else
			{
				$table->filters[ 'group_' . $group->g_id ] = $group->g_id;
			}
		}

		$table->sortDirection = $table->sortDirection ?: 'desc';
		
		/* Display */
		\IPS\Output::i()->linkTags['canonical'] = (string) \IPS\Http\Url::internal( 'app=core&module=online&controller=online', 'front', 'online' );
		\IPS\Output::i()->title	 = \IPS\Member::loggedIn()->language()->addToStack('online_users');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'online', 'core', 'front' )->onlineUsersList( (string) $table, $table->count );
	}
}