<?php
/**
 * @brief		ACP Member Profile: Support
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		5 Dec 2017
 */

namespace IPS\nexus\extensions\core\MemberACPProfileBlocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: Support
 */
class _Support extends \IPS\core\MemberACPProfile\Block
{
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output()
	{
		$customerStream = \IPS\nexus\Support\Stream::customer( $this->member );
		$supportCount = $customerStream->count( \IPS\Member::loggedIn() );
		$results = $customerStream->results( \IPS\Member::loggedIn(), 'r_started DESC', 1, 15 );
		$supportRequestsInView = array_keys( iterator_to_array( $results ) );
		$tracked = iterator_to_array( \IPS\Db::i()->select( array( 'request_id', 'notify' ), 'nexus_support_tracker', array( array( 'member_id=?', \IPS\Member::loggedIn()->member_id ), array( \IPS\Db::i()->in( 'request_id', $supportRequestsInView ) ) ) )->setKeyField( 'request_id' )->setValueField( 'notify' ) );
		$participatedIn = iterator_to_array( \IPS\Db::i()->select( 'DISTINCT reply_request', 'nexus_support_replies', array( array( 'reply_member=?', \IPS\Member::loggedIn()->member_id ), array( \IPS\Db::i()->in( 'reply_request', $supportRequestsInView ) ) ) ) );
		$support = \IPS\Theme::i()->getTemplate( 'support', 'nexus' )->requestsTableResults( $results, NULL, FALSE, $tracked, $participatedIn, FALSE );
		
		return \IPS\Theme::i()->getTemplate( 'customers', 'nexus' )->support( $this->member, $support, $supportCount );
	}
}