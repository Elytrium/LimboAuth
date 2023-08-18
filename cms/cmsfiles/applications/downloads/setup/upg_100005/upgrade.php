<?php
/**
 * @brief		4.0.0 Alpha 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		25 Mar 2013
 */

namespace IPS\downloads\setup\upg_100005;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Alpha 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Fix downloads changelog
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$limit		= 0;
		$did		= 0;
		$perCycle	= 500;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= (int) \IPS\Request::i()->extra;
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( 'b_id, b_changelog', 'downloads_filebackup', array( "b_changelog is not null and b_changelog != ''" ), 'b_id ASC', array( $limit, $perCycle ) ) as $backup )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			if( mb_strpos( $backup['b_changelog'], '<p' ) === FALSE AND mb_strpos( $backup['b_changelog'], '<ul' ) === FALSE )
			{
				$contents	= explode( "\n", str_replace( "\r", '', $backup['b_changelog'] ) );
				$newContents	= "<ul><li>" . implode( "</li><li>", $contents ) . "</li></ul>";

				\IPS\Db::i()->update( 'downloads_filebackup', array( 'b_changelog' => $newContents ), array( 'b_id=?', $backup['b_id'] ) );
			}
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
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'downloads_filebackup', array( "b_changelog is not null and b_changelog != ''" ) )->first();
		}
		
		return "Upgrading backup changelogs (Upgraded so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
	}

	/**
	 * Step 2
	 * Fix downloads changelog
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$limit		= 0;
		$did		= 0;
		$perCycle	= 500;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= (int) \IPS\Request::i()->extra;
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( 'file_id, file_changelog', 'downloads_files', array( "file_changelog is not null and file_changelog != ''" ), 'file_id ASC', array( $limit, $perCycle ) ) as $file )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			if( mb_strpos( $file['file_changelog'], '<p' ) === FALSE AND mb_strpos( $file['file_changelog'], '<ul' ) === FALSE )
			{
				$contents	= explode( "\n", str_replace( "\r", '', $file['file_changelog'] ) );
				$newContents	= "<ul><li>" . implode( "</li><li>", $contents ) . "</li></ul>";

				\IPS\Db::i()->update( 'downloads_files', array( 'file_changelog' => $newContents ), array( 'file_id=?', $file['file_id'] ) );
			}
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			unset( $_SESSION['_step2Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step2Count'] ) )
		{
			$_SESSION['_step2Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'downloads_files', array( "file_changelog is not null and file_changelog != ''" ) )->first();
		}
		
		return "Upgrading file changelogs (Upgraded so far: " . ( ( $limit > $_SESSION['_step2Count'] ) ? $_SESSION['_step2Count'] : $limit ) . ' out of ' . $_SESSION['_step2Count'] . ')';
	}

	/**
	 * Step 3
	 * Fix currency
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		if( !isset( \IPS\Settings::i()->nexus_currency ) or !\IPS\Application::appIsEnabled( 'nexus', TRUE ) )
		{
			return TRUE;
		}

		$limit		= 0;
		$did		= 0;
		$perCycle	= 500;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= (int) \IPS\Request::i()->extra;
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();
		
		foreach( \IPS\Db::i()->select( 'file_id, file_cost, file_renewal_price', 'downloads_files', array( "file_cost is not null and file_cost != ''" ), 'file_id ASC', array( $limit, $perCycle ) ) as $file )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			$update = array();

			/* Base price */
			$costs	= @json_decode( $file['file_cost'], TRUE );

			if( !\is_array( $costs ) and $file['file_cost'] )
			{
				$costs	= array();

				foreach( \IPS\nexus\Money::currencies() as $currency )
				{
					$costs[ $currency ]		= array( 'amount' => $file['file_cost'], 'currency' => $currency );
				}

				$update['file_cost']	= json_encode( $costs );
			}

			/* Renewal price */
			$costs	= @json_decode( $file['file_renewal_price'], TRUE );

			if( !\is_array( $costs ) )
			{
				$costs	= array();

				foreach( \IPS\nexus\Money::currencies() as $currency )
				{
					$costs[ $currency ]		= array( 'amount' => $file['file_renewal_price'], 'currency' => $currency );
				}

				$update['file_renewal_price']	= json_encode( $costs );
			}

			if( \count( $update ) )
			{
				\IPS\Db::i()->update( 'downloads_files', $update, array( 'file_id=?', $file['file_id'] ) );
			}
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			unset( $_SESSION['_step3Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		if( !isset( \IPS\Settings::i()->nexus_currency ) )
		{
			return "No file costs to update";
		}

		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step3Count'] ) )
		{
			$_SESSION['_step3Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'downloads_files', array( "file_cost is not null and file_cost != ''" ) )->first();
		}
		
		return "Upgrading file costs (Upgraded so far: " . ( ( $limit > $_SESSION['_step3Count'] ) ? $_SESSION['_step3Count'] : $limit ) . ' out of ' . $_SESSION['_step3Count'] . ')';
	}
}