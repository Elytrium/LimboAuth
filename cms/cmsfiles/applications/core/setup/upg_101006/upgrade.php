<?php
/**
 * @brief		4.1.0 Beta 7 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		16 Oct 2015
 */

namespace IPS\core\setup\upg_101006;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.0 Beta 7 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Clean up orphaned status updates
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$toRunQueries	= array(
			array(
				'table'	=> 'core_member_status_updates',
				'query'	=> "DELETE FROM " . \IPS\Db::i()->prefix . "core_member_status_updates WHERE status_member_id NOT IN(SELECT member_id FROM " . \IPS\Db::i()->prefix . "core_members)",
			),
			array(
				'table'	=> 'core_member_status_updates',
				'query'	=> "DELETE FROM " . \IPS\Db::i()->prefix . "core_member_status_updates WHERE status_author_id NOT IN(SELECT member_id FROM " . \IPS\Db::i()->prefix . "core_members)",
			)
		);

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $toRunQueries );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 'extra' => array( '_upgradeStep' => 2 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
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
		return "Removing orphaned status updates";
	}

	/**
	 * We need to clean up template editing logs
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		foreach( \IPS\Db::i()->select( '*', 'core_admin_logs', array( 'do=? OR do=?', 'deleteTemplate', 'saveTemplate' ) ) as $template )
		{
			$update	= array();
			$data	= json_decode( $template['note'], TRUE );

			/* deleteTemplate previously just stored "name => FALSE", so we need to populate the rest of the data which we don't have -
				as this is legacy though and won't be an issue moving forward, it isn't too big a deal */
			if( $template['do'] == 'deleteTemplate' )
			{
				$keys = array_keys( $data );

				$update['note'] = json_encode( array( '(unknown)' => FALSE, '(unknown)' => FALSE, '(unknown)' => FALSE, $keys[0] => FALSE ) );
			}
			/* saveTemplate stored all the data but in a way that won't be retrievable, so we need to convert it */
			else
			{
				$update['note'] = json_encode( array( $data['app'] => FALSE, $data['location'] => FALSE, $data['group'] => FALSE, $data['name'] => FALSE ) );
			}

			\IPS\Db::i()->update( 'core_admin_logs', $update, array( 'id=?', $template['id'] ) );
		}

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Adjusting administrator logs";
	}

	/**
	 * Trim leading path slash if present
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		$toRunQueries	= array(
			array(
				'table'	=> 'core_attachments',
				'query'	=> "UPDATE " . \IPS\Db::i()->prefix . "core_attachments SET attach_location=SUBSTR( attach_location, 2 ) WHERE attach_location LIKE '/%'",
			),
			array(
				'table'	=> 'core_attachments',
				'query'	=> "UPDATE " . \IPS\Db::i()->prefix . "core_attachments SET attach_thumb_location=SUBSTR( attach_thumb_location, 2 ) WHERE attach_thumb_location LIKE '/%'",
			)
		);

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $toRunQueries );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 'extra' => array( '_upgradeStep' => 4 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Fixing broken attachment paths";
	}

	/**
	 * Fix moderator permissions - containers need to be -1 instead of 0 to represent "all containers"
	 * This has to be run last so that the other app tables have been renamed
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		/* We have to figure out which fields are 'node' fields */
		$nodeFields = array();

		foreach ( \IPS\Application::allExtensions( 'core', 'ModeratorPermissions', FALSE, 'core' ) as $k => $ext )
		{
			foreach( $ext->getPermissions( array() ) as $name => $data )
			{
				$type = \is_array( $data ) ? $data[0] : $data;

				if( $type == 'Node' )
				{
					$nodeFields[ $name ]	= $name;
				}
			}
		}

		/* Now loop over moderators and fix them */
		foreach( \IPS\Db::i()->select( '*', 'core_moderators' ) as $moderator )
		{
			/* We only need to fix them if the perms aren't * */
			if( $moderator['perms'] !== '*' )
			{
				$perms		= json_decode( $moderator['perms'], TRUE );
				$hasChange	= FALSE;

				foreach( $perms as $k => $v )
				{
					if( \in_array( $k, $nodeFields ) AND $v == 0 )
					{
						$perms[ $k ]	= -1;
						$hasChange		= TRUE;
					}
				}

				/* Only bother updating if we need to */
				if( $hasChange )
				{
					\IPS\Db::i()->update( 'core_moderators', array( 'perms' => json_encode( $perms ) ), array( 'type=? AND id=?', $moderator['type'], $moderator['id'] ) );
				}
			}
		}

		return TRUE;
	}
}