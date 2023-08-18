<?php
/**
 * @brief		4.1.14 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		26 Jul 2016
 */

namespace IPS\nexus\setup\upg_101042;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.14 Upgrade Code
 */
class _Upgrade
{
	/**
	 * The upgrader from 3.x previously didn't remove the "nexus" element in nexus_purchases.ps_extra
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_purchases', NULL, 'ps_id', array( $offset, 500 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{		
				$extra = json_decode( $row['ps_extra'], TRUE );
				if ( isset( $extra['nexus'] ) )
				{
					$extra = $extra['nexus'];
					\IPS\Db::i()->update( 'nexus_purchases', array(
						'ps_extra'	=> json_encode( $extra ),
					), array( 'ps_id=?', $row['ps_id'] ) );

				}
			}
			
			return $offset + 500;
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
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases' )->first();
		}

		return "Upgrading commmerce purchases (Upgraded so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
	}
}