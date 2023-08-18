<?php
/**
 * @brief		4.1.6 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		IPS Social Suite
 * @since		30 Dec 2015
 */

namespace IPS\core\setup\upg_101021;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.6 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Rebuild imported status updates to address XSS issue
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Init */
		$perCycle	= 250;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );
		$cutOff		= \IPS\core\Setup\Upgrade::determineCutoff();
		$parser		= new \IPS\Text\LegacyParser;
		
		/* Loop */
		foreach( \IPS\Db::i()->select( array( 'status_id', 'status_member_id', 'status_content' ), 'core_member_status_updates', array( 'status_imported=?', 1 ), 'status_id', array( $limit, $perCycle ) ) as $row )
		{
			/* Timeout? */
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}
			
			/* Step up */
			$did++;
			
			/* Rebuild the content */
			$content = $parser->parser->purify( $row['status_content'] );
			
			/* Save */
			\IPS\Db::i()->update( 'core_member_status_updates', array( 'status_content' => $content ), array( 'status_id=?', $row['status_id'] ) );
		}
		
		/* Did we do anything? */
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
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_member_status_updates', array( "status_imported=?", 1 ) )->first();
		}

		return "Fixing imported status updates (Upgraded so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
	}


}