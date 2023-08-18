<?php
/**
 * @brief		ACP Member Profile: Block
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Nov 2017
 */

namespace IPS\core\MemberACPProfile;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: Block
 */
abstract class _LazyLoadingBlock extends Block
{
	/**
	 * Get Output
	 *
	 * @return	string
	 */
	public function output()
	{
		return \IPS\Theme::i()->getTemplate('memberprofile')->lazyLoad( $this->member, \get_called_class() );
	}
	
	/**
	 * Get Real Output
	 *
	 * @return	string
	 */
	abstract public function lazyOutput();
}