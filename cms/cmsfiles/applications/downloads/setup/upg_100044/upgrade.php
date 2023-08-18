<?php
/**
 * @brief		4.0.13 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		31 Jul 2015
 */

namespace IPS\downloads\setup\upg_100044;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.13 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Convert Ratings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$perCycle	= 1000;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );

		/* Try to prevent timeouts to the extent possible */
		$cutOff	= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'downloads_files', array( 'file_votes IS NOT NULL' ), 'file_id ASC', array( $limit, $perCycle ) ) as $file )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			$votes = unserialize( $file['file_votes'] );
			$voteCount = 0;
			
			if ( \is_array( $votes ) and \count( $votes ) )
			{
				foreach( $votes as $member => $rating )
				{
					try
					{
						$member = \IPS\Member::load( $member );

						\IPS\Db::i()->insert( 'downloads_reviews', array(
							'review_fid'			=> $file['file_id'],
							'review_mid'			=> $member->member_id,
							'review_author_name'	=> $member->name,
							'review_rating'			=> $rating,
							/* The review_ip column does not accept NULL values */
							'review_ip'				=> '',
							'review_date'			=> time(),
							'review_approved'		=> 1,
							/* The search index does not allow blank text */
							'review_text'			=> '',
						) );
		
						$voteCount++;
					}
					catch( \Exception $e ){}
				}
			}
			
			\IPS\Db::i()->update( 'downloads_files', array( 'file_reviews' => $file['file_reviews'] + $voteCount ), array( "file_id=?", $file['file_id'] ) );
		}

		if( $did )
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
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'downloads_files', array( 'file_votes IS NOT NULL' ) )->first();
		}

		return "Converting file ratings (Updated so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
	}
}