<?php
/**
 * @brief		IP Address Lookup: Logins
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Feb 2017
 */

namespace IPS\core\extensions\core\IpAddresses;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * IP Address Lookup: Logins
 */
class _Logins
{
	/**
	 * Removes the logged IP address
	 *
	 * @param int $timestamp
	 * @return void
	 */
	public function pruneIpAddresses(int $time)
	{
		\IPS\Db::i()->update('core_members_known_ip_addresses', [ 'ip_address=?', '' ] , [ "ip_address != '' AND last_seen <?", $time ] );
	}
	
	/**
	 * Supported in the ACP IP address lookup tool?
	 *
	 * @return	bool
	 * @note	If the method does not exist in an extension, the result is presumed to be TRUE
	 */
	public function supportedInAcp()
	{
		return TRUE;
	}

	/**
	 * Supported in the ModCP IP address lookup tool?
	 *
	 * @return	bool
	 * @note	If the method does not exist in an extension, the result is presumed to be TRUE
	 */
	public function supportedInModCp(): bool
	{
		return FALSE;
	}

	/** 
	 * Find Records by IP
	 *
	 * @param	string			$ip			The IP Address
	 * @param	\IPS\Http\Url	$baseUrl	URL table will be displayed on or NULL to return a count
	 * @return	\IPS\Helpers\Table|int|null
	 */
	public function findByIp( $ip, \IPS\Http\Url $baseUrl = NULL )
	{
		/* Return count */
		if ( $baseUrl === NULL )
		{
			return \IPS\Db::i()->select( 'COUNT(*)', 'core_members_known_ip_addresses', array( "ip_address LIKE ?", $ip ) )->first();
		}
		
		/* Init Table */
		$table = new \IPS\Helpers\Table\Db( 'core_members_known_ip_addresses', $baseUrl, array( array( 'ip_address LIKE ?', $ip ) ) );
		$table->joins[] = array( 'select' => 'user_agent', 'from' => 'core_members_known_devices', 'where' => 'core_members_known_devices.device_key=core_members_known_ip_addresses.device_key' );
		$table->langPrefix = 'device_table_';
		$table->include = array( 'user_agent', 'member_id', 'login_handler', 'last_seen' );
		$table->sortBy = $table->sortBy ?: 'last_seen';
		$table->parsers = array(
			'user_agent'	=> function( $val, $row ) {
				return (string) \IPS\Http\UserAgent::parse( $val );
			},
			'member_id'	=> function( $val ) {
				$member = \IPS\Member::load( $val );
				if ( $member->member_id )
				{
					return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( $member, 'tiny' ) . \IPS\Theme::i()->getTemplate( 'global', 'core' )->userLink( $member, 'tiny' );
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack('deleted_member');
				}
			},
			'login_handler'		=> function( $val, $row ) {
				return \IPS\Theme::i()->getTemplate('members')->deviceHandler( $val );
			},
			'last_seen'	=> function( $val ) {
				return \IPS\DateTime::ts( $val );
			},
		);
		$table->rowButtons = function( $row )
		{
			return array(
				'view'	=> array(
					'title'	=> 'view',
					'icon'	=> 'search',
					'link'	=> \IPS\Http\Url::internal( 'app=core&module=members&controller=devices&do=device' )->setQueryString( 'key', $row['device_key'] )->setQueryString( 'member', $row['member_id'] ),
				),
			);
		};
		
		/* Return */
		return (string) $table;
	}
	
	/**
	 * Find IPs by Member
	 *
	 * @code
	 	return array(
	 		'::1' => array(
	 			'ip'		=> '::1'// string (IP Address)
		 		'count'		=> ...	// int (number of times this member has used this IP)
		 		'first'		=> ... 	// int (timestamp of first use)
		 		'last'		=> ... 	// int (timestamp of most recent use)
		 	),
		 	...
	 	);
	 * @endcode
	 * @param	\IPS\Member	$member	The member
	 * @return	array
	 */
	public function findByMember( $member )
	{
		return \IPS\Db::i()->select( "ip_address AS ip, count(*) AS count, MIN(last_seen) AS first, MAX(last_seen) AS last", 'core_members_known_ip_addresses', array( 'member_id=?', $member->member_id ), NULL, NULL, 'ip_address' )->setKeyField( 'ip' );
	}	
}