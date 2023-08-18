<?php
/**
 * @brief		4.0.10 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		06 Jul 2015
 */

namespace IPS\core\setup\upg_100038;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.10 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix polls
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$perCycle	= 250;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );
		$cutOff		= \IPS\core\Setup\Upgrade::determineCutoff();

		/* If this is the first cycle, make sure votes column can hold all the votes */
		if( $limit === 0 )
		{
			\IPS\Db::i()->changeColumn( 'core_polls', 'votes', array(
				'name'			=> 'votes',
				'type'			=> 'int',
				'length'		=> null,
				'unsigned'		=> false,
				'allow_null'	=> false,
				'default'		=> "0"
			) );
		}

		foreach( \IPS\Db::i()->select( '*', 'core_polls', NULL, 'pid ASC', array( $limit, $perCycle ) ) as $poll )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}
			$did++;
			
			$poll = \IPS\Poll::constructFromData( $poll );

			if( \is_array( $poll->choices ) )
			{
				$poll->votes = 0;
				foreach ( $poll->choices as $key => $data )
				{
					$numberOfVotes = ( \is_array( $data['votes'] ) ) ? array_sum( $data['votes'] ) : 0;
					if ( $numberOfVotes > $poll->votes )
					{
						$poll->votes = $numberOfVotes;
					}
				}

				try
				{
					$poll->save();
				}
				catch( \IPS\Db\Exception $e )
				{
					\IPS\Log::log( $e, 'upgrade_error' );

					continue;
				}
			}
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
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Fixing polls";
	}
}