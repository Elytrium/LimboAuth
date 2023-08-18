<?php
/**
 * @brief		IP Address Lookup: Display name changes
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Oct 2016
 */

namespace IPS\core\extensions\core\IpAddresses;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * IP Address Lookup: Display name changes
 */
class _Dnames
{
	/**
	 * Removes the logged IP address
	 * 
	 * @param int $timestamp
	 * @return void
	 */
	public function pruneIpAddresses(int $time)
	{
		\IPS\Db::i()->update('core_member_history', [ 'log_ip_address=?', '' ] , [ 'log_ip_address != "" AND log_type=? AND log_date <?', 'display_name', $time ] );
	}
	
	/**
	 * Supported in the ACP IP address lookup tool?
	 *
	 * @return	bool
	 * @note	If the method does not exist in an extension, the result is presumed to be TRUE
	 */
	public function supportedInAcp()
	{
		return FALSE;
	}

	/**
	 * Supported in the ModCP IP address lookup tool?
	 *
	 * @return	bool
	 * @note	If the method does not exist in an extension, the result is presumed to be TRUE
	 */
	public function supportedInModCp(): bool
	{
		return TRUE;
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
			return \IPS\Db::i()->select( 'COUNT(*)', 'core_member_history', array( "log_type=? AND log_ip_address LIKE ?", 'display_name', $ip ) )->first();
		}
		
		/* Init Table */
		$table = new \IPS\Member\History( $baseUrl, array( array( 'log_app=? AND log_type=? AND log_ip_address LIKE ?', 'core', 'display_name', $ip ) ) );
		
		/* Columns we need */
		$table->include = array( 'log_member', 'log_data', 'log_date', 'log_ip_address' );
		$table->mainColumn = 'log_date';

		$table->tableTemplate  = array( \IPS\Theme::i()->getTemplate( 'tables', 'core', 'admin' ), 'table' );
		$table->rowsTemplate  = array( \IPS\Theme::i()->getTemplate( 'tables', 'core', 'admin' ), 'rows' );
		$table->filters = NULL;
				
		/* Default sort options */
		$table->sortBy = $table->sortBy ?: 'log_date';
		$table->sortDirection = $table->sortDirection ?: 'desc';
		
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
		return iterator_to_array( \IPS\Db::i()->select( "log_ip_address AS ip, count(*) AS count, MIN(log_date) AS first, MAX(log_date) AS last", 'core_member_history', array( 'log_type=? AND log_member=? AND log_by=?', 'display_name', $member->member_id, $member->member_id ), NULL, NULL, 'log_ip_address' )->setKeyField( 'ip' ) );
	}	
}