<?php
/**
 * @brief		IP Address Lookup: Poll Votes
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Mar 2016
 */

namespace IPS\core\extensions\core\IpAddresses;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * IP Address Lookup: Poll Votes
 */
class _Votes
{
	/**
	 * Removes the logged IP address
	 *
	 * @param int $timestamp
	 * @return void
	 */
	public function pruneIpAddresses(int $time)
	{
		\IPS\Db::i()->update('core_voters', [ 'ip_address=?', '' ] , [ "ip_address != '' AND vote_date <?", $time ] );
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
			return \IPS\Db::i()->select( 'COUNT(*)', 'core_voters', array( "ip_address LIKE ?", $ip ) )->first();
		}
		
		/* Init Table */
		$table = new \IPS\Helpers\Table\Db( 'core_voters', $baseUrl, array( "ip_address LIKE ?", $ip ) );
				
		/* Columns we need */
		$table->include = array( 'member_id', 'poll', 'vote_date', 'ip_address' );
		$table->mainColumn = 'vote_date';
		$table->langPrefix = 'poll_';

		$table->tableTemplate  = array( \IPS\Theme::i()->getTemplate( 'tables', 'core', 'admin' ), 'table' );
		$table->rowsTemplate  = array( \IPS\Theme::i()->getTemplate( 'tables', 'core', 'admin' ), 'rows' );
				
		/* Default sort options */
		$table->sortBy = $table->sortBy ?: 'vote_date';
		$table->sortDirection = $table->sortDirection ?: 'desc';
		
		/* Custom parsers */
		$table->parsers = array(
			'member_id'			=> function( $val, $row )
			{
				$member = \IPS\Member::load( $val );
				return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( $member, 'tiny' ) . ' ' . $member->link();
			},
			'vote_date'			=> function( $val, $row )
			{
				return \IPS\DateTime::ts( $val );
			},
			'poll'				=> function( $val, $row )
			{
				try
				{
					$poll = \IPS\Poll::load( $val );
					return htmlspecialchars( $poll->poll_question, ENT_DISALLOWED, 'UTF-8', FALSE );
				}
				catch ( \OutOfRangeException $e )
				{
					return '';
				}
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
		return \IPS\Db::i()->select( "ip_address AS ip, count(*) AS count, MIN(vote_date) AS first, MAX(vote_date) AS last", 'core_voters', array( 'member_id=?', $member->member_id ), NULL, NULL, 'ip_address' )->setKeyField( 'ip' );
	}	
}