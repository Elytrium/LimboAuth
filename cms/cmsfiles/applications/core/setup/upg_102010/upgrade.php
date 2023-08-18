<?php
/**
 * @brief		4.2.3 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		31 Jul 2017
 */

namespace IPS\core\setup\upg_102010;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.2.3 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Clean up orphaned reactions
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* First, see if we have any to even worry about */
		$orphaned = \IPS\Db::i()->select( 'count(*)', 'core_reputation_index', array( 'reaction=?', '0' ) )->first();

		if( !$orphaned )
		{
			return TRUE;
		}

		/* Figure out highest position in case we have to insert any reactions */
		$position = \IPS\Db::i()->select( 'max(reaction_position)', 'core_reactions' )->first();

		/* Path fallback if a custom AdminCP directory is used. */
		$path = \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY;
		if( \IPS\CP_DIRECTORY != 'admin' AND !file_exists( \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY . '/install/reaction' ) )
		{
			$path = \IPS\ROOT_PATH . '/admin';
		}

		/* Do we have any negative reputations that need to be fixed? */
		$negative = \IPS\Db::i()->select( 'count(*)', 'core_reputation_index', array( 'reaction=? and rep_rating=?', '0', '-1' ) )->first();

		if( $negative )
		{
			/* Do we have a negative reaction? If not create one, but disable it */
			try
			{
				$id = \IPS\Db::i()->select( 'reaction_id', 'core_reactions', array( "reaction_icon LIKE CONCAT( '%', ? )", 'react_down.png' ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$fileObj = \IPS\File::create( 'core_Reaction', 'react_down.png', file_get_contents( $path . '/install/reaction/react_down.png' ), 'reactions', FALSE, NULL, FALSE );
				$id = \IPS\Db::i()->insert( 'core_reactions', array(
					'reaction_value'	=> -1,
					'reaction_icon'		=> (string) $fileObj,
					'reaction_position'	=> ++$position,
					'reaction_enabled'	=> 0,
				) );
				\IPS\Lang::saveCustom( 'core', 'reaction_title_' . $id, 'Downvote' );
			}

			\IPS\Db::i()->update( 'core_reputation_index', array( 'reaction' => $id ), array( 'reaction=? and rep_rating=?', '0', '-1' ) );
		}

		/* Do we have any positive reputations that need to be fixed? */
		$positive = \IPS\Db::i()->select( 'count(*)', 'core_reputation_index', array( 'reaction=? and rep_rating=?', '0', '1' ) )->first();

		if( $positive )
		{
			/* If the old method was 'like' just use that, otherwise we need to use the react_up positive reaction. */
			if( \IPS\Settings::i()->reputation_point_types == 'like' )
			{
				$id = 1;
			}
			else
			{
				try
				{
					$id = \IPS\Db::i()->select( 'reaction_id', 'core_reactions', array( "reaction_icon LIKE CONCAT( '%', ? )", 'react_up.png' ) )->first();
				}
				catch( \UnderflowException $e )
				{
					$fileObj = \IPS\File::create( 'core_Reaction', 'react_up.png', file_get_contents( $path . '/install/reaction/react_up.png' ), 'reactions', FALSE, NULL, FALSE );
					$id = \IPS\Db::i()->insert( 'core_reactions', array(
						'reaction_value'	=> 1,
						'reaction_icon'		=> (string) $fileObj,
						'reaction_position'	=> ++$position,
						'reaction_enabled'	=> 0,
					) );
					\IPS\Lang::saveCustom( 'core', 'reaction_title_' . $id, 'Upvote' );
				}
			}

			\IPS\Db::i()->update( 'core_reputation_index', array( 'reaction' => $id ), array( 'reaction=? and rep_rating=?', '0', '1' ) );
		}

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Fixing orphaned reputation";
	}

	/**
	 * Step 2
	 * Clean up deletion logs
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		/* Remove not existing deletion log records */
		$perCycle	= 500;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		$toDelete = array();

		foreach( \IPS\Db::i()->select( '*', 'core_deletion_log', null, 'dellog_id ASC', array( $limit, $perCycle ) ) as $record )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$class	= $record['dellog_content_class'];

			if( class_exists( $class ) )
			{
				try
				{
					$item	= $class::load( $record['dellog_content_id'] );
				}
					/* Item may no longer exist */
				catch ( \OutOfRangeException $e)
				{
					$toDelete[] = $record['dellog_id'];
				}
			}
			else
			{
				/* App doesn't exist anymore, so it's safe to delete it */
				$toDelete[] = $record['dellog_id'];
			}

			$did++;
		}
		if ( \count( $toDelete ) > 0 )
		{
			\IPS\Db::i()->delete( 'core_deletion_log', \IPS\Db::i()->in( 'dellog_id', $toDelete) );
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
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step2CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		
		return "Fixing orphaned deletion log (" . $limit . " processed so far)";
	}

	/**
	 *  Repair Custom Field URL's
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		$profile = new \IPS\core\extensions\core\FileStorage\ProfileField;
		\IPS\Task::queue( 'core', 'RepairFileUrls', array( 'storageExtension' => 'filestorage__core_ProfileField', 'count' => $profile->count() ), 1 );
		
		$club = new \IPS\core\extensions\core\FileStorage\Clubs;
		\IPS\Task::queue( 'core', 'RepairFileUrls', array( 'storageExtension' => 'filestorage__core_Clubs', 'count' => $club->count() ), 1 );
		
		$clubFields = new \IPS\core\extensions\core\FileStorage\ClubField;
		\IPS\Task::queue( 'core', 'RepairFileUrls', array( 'storageExtension' => 'filestorage__core_ClubField', 'count' => $clubFields->count() ), 1 );
		return TRUE;
	}
}