<?php
/**
 * @brief		{version_human} Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		24 Jun 2019
 */

namespace IPS\cms\setup\upg_104030;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * {version_human} Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix Record and Comment Hide Reasons
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Init */
		$perCycle	= 250;
		$did		= 0;
		$lastId		= \IPS\Request::i()->extra ? \intval( \IPS\Request::i()->extra ) : 0;
		$cutOff		= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( 'sdl_id, sdl_obj_id', 'core_soft_delete_log', array( 'sdl_obj_key=? AND sdl_id > ?', 'ccs-records', $lastId ) ) as $hide )
		{
			/* Timeout? */
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return $lastId;
			}

			/* Step up */
			$did++;
			$lastId = $hide['sdl_id'];

			$hidekey = NULL;
			$items = 0;

			foreach( \IPS\cms\Databases::databases() as $key => $db )
			{
				/* Try to fetch the record */
				$item    = 'IPS\cms\Records' . $key;
				try
				{
					$record = $item::load( $hide['sdl_obj_id'] );
					if ( $record->hidden() )
					{
						$hidekey = 'css-records' . $key;
						$items++;
					}
				}
				catch ( \OutOfRangeException $e ) { }

				/* Try to fetch the comment */
				try
				{
					$commentClass = 'IPS\cms\Records\Comment' . $key;
					$comment = $commentClass::load( $hide['sdl_obj_id'] );
					if ( $comment->hidden() )
					{
						$hidekey = 'ccs-records' . $key .  '-comments';
						$items++;
					}
				}
				catch ( \OutOfRangeException $e ) { }
			}

			/* If we have only one item, we can update the row */
			if ( $items == 1 )
			{
				\IPS\Db::i()->update( 'core_soft_delete_log', array( 'sdl_obj_key' => $hidekey ), array( 'sdl_id=?', $hide['sdl_id'] ) );
			}
			/* Else just delete the row */
			else
			{
				\IPS\Db::i()->delete( 'core_soft_delete_log', array( 'sdl_id=?', $hide['sdl_id'] ) );
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
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Fixing database hide reasons";
	}

	/**
	 * Fix Record Review Hide Reasons
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		/* Init */
		$perCycle	= 250;
		$did		= 0;
		$lastId		= \IPS\Request::i()->extra ? \intval( \IPS\Request::i()->extra ) : 0;
		$cutOff		= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( 'sdl_id, sdl_obj_id', 'core_soft_delete_log', array( 'sdl_obj_key=? AND sdl_id > ?', 'ccs-records-review', $lastId ) ) as $hide )
		{
			/* Timeout? */
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return $lastId;
			}

			/* Step up */
			$did++;
			$lastId = $hide['sdl_id'];

			$hidekey = NULL;
			$items = 0;

			foreach ( \IPS\cms\Databases::databases() as $key => $db )
			{
				/* Try to fetch the review */
				try
				{
					$reviewClass = 'IPS\cms\Records\Review' . $key;
					$review = $reviewClass::load( $hide['sdl_obj_id'] );
					if ( $review->hidden() )
					{
						$hidekey = 'ccs-records' . $key . '-reviews';
						$items++;
					}
				}
				catch ( \OutOfRangeException $e )
				{
				}
			}

			/* If we have only one item, we can update the row */
			if ( $items == 1 )
			{
				\IPS\Db::i()->update( 'core_soft_delete_log', array( 'sdl_obj_key' => $hidekey ), array( 'sdl_id=?', $hide['sdl_id'] ) );
			}
			/* Else just delete the row */
			else
			{
				\IPS\Db::i()->delete( 'core_soft_delete_log', array( 'sdl_id=?', $hide['sdl_id'] ) );
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
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Fixing database review hide reasons";
	}
}