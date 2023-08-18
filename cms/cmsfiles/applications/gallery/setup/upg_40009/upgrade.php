<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		24 Oct 2014
 */

namespace IPS\gallery\setup\upg_40009;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Convert albums
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		if( \IPS\Db::i()->checkForTable( 'gallery_subscriptions' ) )
		{
			foreach( \IPS\Db::i()->select( '*', 'gallery_subscriptions', "sub_type='album'" ) as $subscription )
			{
				$followId = md5( 'gallery;albums;' . $subscription['sub_toid'] . ';' .  $subscription['sub_mid'] );
				\IPS\Db::i()->replace( 'core_follow', array(
					'follow_id'				=> $followId,
					'follow_app'			=> 'gallery',
					'follow_area'			=> 'albums',
					'follow_rel_id'			=> $subscription['sub_toid'],
					'follow_member_id'		=> $subscription['sub_mid'],
					'follow_is_anon'		=> 0,
					'follow_added'			=> time(),
					'follow_notify_do'		=> 1,
					'follow_notify_freq'	=> 'immediate',
					'follow_visible'		=> 1,
				)	);
			}
		}
		
		return TRUE;
	}

	/**
	 * Convert favorites
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$perCycle	= 500;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );

		if( \IPS\Db::i()->checkForTable( 'gallery_subscriptions' ) )
		{
			foreach( \IPS\Db::i()->select( '*', 'gallery_subscriptions', "sub_type='image'", 'sub_id ASC', array( $limit, $perCycle ) ) as $subscription )
			{
				$did++;
				
				$followId = md5( 'gallery;images;' . $subscription['sub_toid'] . ';' . $subscription['sub_mid'] );
				\IPS\Db::i()->replace( 'core_follow', array(
					'follow_id'				=> $followId,
					'follow_app'			=> 'gallery',
					'follow_area'			=> 'images',
					'follow_rel_id'			=> $subscription['sub_toid'],
					'follow_member_id'		=> $subscription['sub_mid'],
					'follow_is_anon'		=> 0,
					'follow_added'			=> time(),
					'follow_notify_do'		=> 1,
					'follow_notify_freq'	=> 'immediate',
					'follow_visible'		=> 1,
				)	);
			}
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			return TRUE;
		}
	}

	/**
	 * Convert subscriptions
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		$perCycle	= 500;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );

		if( \IPS\Db::i()->checkForTable( 'gallery_favorites' ) )
		{
			foreach( \IPS\Db::i()->select( '*', 'gallery_favorites', NULL, 'id ASC', array( $limit, $perCycle ) ) as $favorite )
			{
				$did++;

				\IPS\Db::i()->replace( 'core_follow', array(
					'follow_app'			=> 'gallery',
					'follow_area'			=> 'images',
					'follow_rel_id'			=> $favorite['img_id'],
					'follow_member_id'		=> $favorite['member_id'],
					'follow_is_anon'		=> 0,
					'follow_added'			=> time(),
					'follow_notify_do'		=> 0,
					'follow_notify_freq'	=> 'immediate',
					'follow_visible'		=> 1,
				)	);
			}
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			return TRUE;
		}
	}
}