<?php
/**
 * @brief		4.1.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		23 Sep 2015
 */

namespace IPS\downloads\setup\upg_101000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix broken downloads reviews from 100044
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$perCycle	= 1000;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );
		
		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'downloads_reviews', 'review_date is null', 'review_id ASC', array( $limit, $perCycle ) ) as $review )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;
			
			try
			{
				$member = \IPS\Member::load( $review['review_mid'] );

				\IPS\Db::i()->update( 'downloads_reviews', array( 'review_date' => time(), 'review_text' => '', 'review_author_name' => $member->name ), array( 'review_id=?', $review['review_id'] ) );
			}
			catch( \Exception $e ){}
		}
		
		if ( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			unset( $_SESSION['_step1Count'] );
			
			return TRUE;
		}
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step1Count'] ) )
		{
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'downloads_reviews', 'review_date is null' )->first();
		}
		
		return "Adjusting empty download reviews (Upgraded so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
	}

	/**
	 * Reset the forum ID to post to if the forum ID does not exist
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		if ( \IPS\Application::appIsEnabled( 'forums' ) )
		{
			$prefix = \IPS\Db::i()->prefix;
	
			\IPS\Db::i()->update( 'downloads_categories', array( 'cforum_id' => 0 ), 'cforum_id NOT IN( SELECT id FROM ' . $prefix . 'forums_forums )' );
		}

		return TRUE;
	}
}