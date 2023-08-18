<?php
/**
 * @brief		4.5.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		14 Oct 2019
 */

namespace IPS\downloads\setup\upg_105013;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.5.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Copy follow data to new file versions notify table
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

		foreach( \IPS\Db::i()->select( '*', 'core_follow', array( 'follow_app=? and follow_area=?', 'downloads', 'file' ), 'follow_rel_id ASC', array( $limit, $perCycle ) ) as $follow )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			/* Don't insert if this is the file author, however, because author's can't receive notifications of their own files */
			try
			{
				if( $follow['follow_member_id'] != \IPS\Db::i()->select( 'file_submitter', 'downloads_files', array( 'file_id=?', $follow['follow_rel_id'] ) )->first() )
				{
					\IPS\Db::i()->insert( 'downloads_files_notify', array( 'notify_member_id' => $follow['follow_member_id'], 'notify_file_id' => $follow['follow_rel_id'], 'notify_sent' => $follow['follow_notify_sent'] ) );
				}
			}
			catch( \UnderflowException $e )
			{
				/* The follow record is orphaned - just remove it */
				\IPS\Db::i()->delete( 'core_follow', array( 'follow_id=?', $follow['follow_id'] ) );
			}
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
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_follow', array( 'follow_app=? and follow_area=?', 'downloads', 'file' ) )->first();
		}

		return "Copying downloads follows (Copied so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
	}
}