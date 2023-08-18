<?php
/**
 * @brief		Dashboard extension: Latest News
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Aug 2013
 */

namespace IPS\core\extensions\core\Dashboard;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Dashboard extension: Latest News
 */
class _LatestNews
{
	/**
	* Can the current user view this dashboard item?
	*
	* @return	bool
	*/
	public function canView()
	{
		return TRUE;
	}

	/**
	 * Return the block to show on the dashboard
	 *
	 * @return	string
	 */
	public function getBlock()
	{
		//
	}

	/**
	 * Updates news store
	 *
	 * @return	void
	 * @throws	\IPS\Http\Request\Exception
	 */
	protected function refreshNews()
	{
		\IPS\Data\Store::i()->ips_news = json_encode( array(
			'content'	=> \IPS\Http\Url::ips( 'news' )->request()->get()->decodeJson(),
			'time'		=> time()
		) );
	}
}