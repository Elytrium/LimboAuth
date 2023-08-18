<?php
/**
 * @brief		4.2.6 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		31 May 2017
 */

namespace IPS\core\setup\upg_102030;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.2.6 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Restore missing entries to the deletion log
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		foreach( \IPS\Application::allExtensions( 'core', 'ContentRouter', FALSE ) as $object )
		{
			foreach( $object->classes as $itemClass )
			{
				try
				{
					$commentClass = NULL;
					$reviewClass  = NULL;

					if ( isset( $itemClass::$commentClass ) )
					{
						$commentClass = $itemClass::$commentClass;
					}

					if ( isset( $itemClass::$reviewClass ) )
					{
						$reviewClass = $itemClass::$reviewClass;
					}

					\IPS\Task::queue( 'core', 'FixDeletionLog', array( 'class' => $itemClass ), 4, array( 'class' ) );

					if ( $commentClass )
					{
						\IPS\Task::queue( 'core', 'FixDeletionLog', array( 'class' => $commentClass ), 4, array( 'class' ) );
					}

					if ( $reviewClass )
					{
						\IPS\Task::queue( 'core', 'FixDeletionLog', array( 'class' => $reviewClass ), 4, array( 'class' ) );
					}
				}
				catch( \Exception $e ) { }
			}
		}

		$profile = new \IPS\core\extensions\core\FileStorage\ProfileField;
		\IPS\Task::queue( 'core', 'RepairFileUrls', array( 'storageExtension' => 'filestorage__core_ProfileField', 'count' => $profile->count() ), 1 );
		
		$club = new \IPS\core\extensions\core\FileStorage\Clubs;
		\IPS\Task::queue( 'core', 'RepairFileUrls', array( 'storageExtension' => 'filestorage__core_Clubs', 'count' => $club->count() ), 1 );
		
		$clubFields = new \IPS\core\extensions\core\FileStorage\ClubField;
		\IPS\Task::queue( 'core', 'RepairFileUrls', array( 'storageExtension' => 'filestorage__core_ClubField', 'count' => $clubFields->count() ), 1 );

		return TRUE;
	}
}