<?php
/**
 * @brief		4.4.5 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		28 May 2019
 */

namespace IPS\core\setup\upg_104030;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.4.5 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix ACP Notifications for CiC
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* This is CIC specific */
		if( !\IPS\CIC )
		{
			return TRUE;
		}

		if ( $count = \IPS\Db::i()->select( 'COUNT(*)', 'core_acp_notifications', array( 'app=? AND ext=?', 'core', 'NewVersion' ) )->first() )
		{
			\IPS\Db::i()->delete( 'core_acp_notifcations_dismissals', array('notification IN(?)', \IPS\Db::i()->select( 'id', 'core_acp_notifications', array( 'app=? AND ext=?', 'core', 'NewVersion' ) ) ) );
			\IPS\Db::i()->delete( 'core_acp_notifications', array( 'app=? AND ext=?', 'core', 'NewVersion' ) );
		}

		unset( \IPS\Data\Store::i()->acpNotifications );
		unset( \IPS\Data\Store::i()->acpNotificationIds );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step1CustomTitle()
	{
		return "Removing invalid AdminCP notifications";
	}

	/**
	 * Step 2
	 * Update delete log container references
	 *
	 * @return	bool
	 */
	public function step2()
	{
		/* Init */
		$perCycle	= 250;
		$did		= 0;
		$lastId		= \IPS\Request::i()->extra ? \intval( \IPS\Request::i()->extra ) : 0;
		$cutOff		= \IPS\core\Setup\Upgrade::determineCutoff();

		/* Loop */
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_deletion_log', array( "dellog_id>?", $lastId ), 'dellog_id', array( 0, $perCycle ) ), 'IPS\core\DeletionLog' ) as $row )
		{
			/* Timeout? */
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return $lastId;
			}

			/* Step up */
			$did++;
			$lastId = $row->id;

			try
			{
				$class = $row->content_class;
				$content = $class::load( $row->content_id );

				try
				{
					$item = $content;
					if ( $content instanceof \IPS\Content\Comment )
					{
						$item = $content->item();
					}

					$row->content_container_id		= $item->container()->_id;
					$row->content_container_class	= $item::$containerNodeClass;
				}
				catch( \OutOfRangeException | \BadMethodCallException $e )
				{
					$row->content_container_id		= 0;
					$row->content_container_class	= NULL;
				}

				$row->save();
			}
			catch( \OutOfRangeException | \Error $e )
			{
				/* Orphaned log - just remove it */
				$row->delete();
			}
		}

		/* Did we do anything? */
		if( $did )
		{
			return $lastId;
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
		return "Fixing deletion logs";
	}

	/**
	 * Repair Custom Field URLs
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		$profile = new \IPS\core\extensions\core\FileStorage\ProfileField;
		\IPS\Task::queue( 'core', 'RepairFileUrls', array( 'storageExtension' => 'filestorage__core_ProfileField', 'count' => $profile->count() ), 1 );

		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}
