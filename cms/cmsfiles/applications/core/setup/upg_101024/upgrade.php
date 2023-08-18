<?php
/**
 * @brief		4.1.8 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Jan 2016
 */

namespace IPS\core\setup\upg_101024;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.8 Upgrade Code
 */
class _Upgrade
{
	/**
	 * We never cleaned up core_tags_cache with previous upgrades
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Some init */
		$did		= 0;
		$limit		= 0;
		
		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= \IPS\Request::i()->extra;
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();
		
		foreach( \IPS\Db::i()->select( '*', 'core_tags_cache', null, 'tag_cache_key ASC', array( $limit, 500 ) ) as $cache )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			/* The data may be serialized, so check that */
			$results = @unserialize( $cache['tag_cache_text'] );
			$update  = null;

			if( \is_array( $results ) AND \count( $results ) )
			{
				$update = $results;
			}
			else
			{
				/* It may be json_encoded...which is normally fine, but a previous bug may have resulted in the 'tags' array being two levels deep */
				$results = @json_decode( $cache['tag_cache_text'], true );

				if( \is_array( $results ) AND \count( $results ) )
				{
					if( isset( $results['tags'] ) AND \is_array( $results['tags'] ) )
					{
						if( isset( $results['tags'][0] ) AND \is_array( $results['tags'][0] ) )
						{
							$update = array( 'tags' => $results['tags'][0], 'prefix' => $results['prefix'] );
						}
						else
						{
							$update = $results;
						}
					}
				}
			}

			if( $update !== null )
			{
				\IPS\Db::i()->update( 'core_tags_cache', array( 'tag_cache_text' => json_encode( $update ) ), array( 'tag_cache_key=?', $cache['tag_cache_key'] ) );
			}
		}

		if( $did )
		{
			return $limit + $did;
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
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_tags_cache' )->first();
		}

		return "Cleaning up tags (Converted so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
	}
}