<?php
/**
 * @brief		IP Address Lookup: Admin Login Logs
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Oct 2016
 */

namespace IPS\core\extensions\core\IpAddresses;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * IP Address Lookup: Admin Login Logs
 */
class _AdminLoginLogs
{
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
			return \IPS\Db::i()->select( 'COUNT(*)', 'core_admin_login_logs', array( "admin_ip_address LIKE ?", $ip ) )->first();
		}
		
		/* Init Table */
		$table = new \IPS\Helpers\Table\Db( 'core_admin_login_logs', $baseUrl, array( "admin_ip_address LIKE ?", $ip ) );
				
		/* Columns we need */
		$table->include = array( 'admin_username', 'admin_time', 'admin_success', 'admin_ip_address' );
		$table->mainColumn = 'admin_time';
		$table->langPrefix = 'adminloginlogs_';

		$table->tableTemplate  = array( \IPS\Theme::i()->getTemplate( 'tables', 'core', 'admin' ), 'table' );
		$table->rowsTemplate  = array( \IPS\Theme::i()->getTemplate( 'tables', 'core', 'admin' ), 'rows' );
				
		/* Default sort options */
		$table->sortBy = $table->sortBy ?: 'admin_time';
		$table->sortDirection = $table->sortDirection ?: 'desc';
		
		/* Custom parsers */
		$table->parsers = array(
			'admin_time'	=> function( $val, $row )
			{
				return \IPS\DateTime::ts( $val );
			},
			'admin_success'	=> function( $val, $row )
			{
				return ( $val ) ? "<i class='fa fa-check'></i>" : "<i class='fa fa-times'></i>";
			},
		);
		
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
		/* Log table stores IP addresses, but not member ID associations */
		return array();
	}	
}