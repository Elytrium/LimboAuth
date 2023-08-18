<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		13 Apr 2023
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _UpdateClubRenewals
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array|null
	 */
	public function preQueueData( $data ): array|null
	{
		$data['count'] = $this->getQuery( 'COUNT(*)', $data )->first();

		if( $data['count'] == 0 )
		{
			return null;
		}

		return $data;
	}

	/**
	 * Run Background Task
	 *
	 * @param	mixed						$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int							$offset	Offset
	 * @return	int							New offset
	 * @throws	\IPS\Task\Queue\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( $data, $offset )
	{
		if ( !\IPS\Application::appisEnabled( 'nexus' ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$select	= $this->getQuery( 'nexus_purchases.*', $data, $offset );

		if ( !$select->count() or $offset > $data['count'] )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		foreach( $select AS $row )
		{
			try
			{
				$club = \IPS\Member\Club::load( $data['club'] );
				$purchase = \IPS\nexus\Purchase::constructFromData( $row );

				$club->updatePurchase( $purchase, $data['changes'], TRUE );
			}
			catch( \Exception $e ) {}

			$offset++;
		}

		return $offset;
	}
	
	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array( 'text' => 'Doing something...', 'complete' => 50 )	Text explaining task and percentage complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function getProgress( $data, $offset ): array
	{
		$text = \IPS\Member::loggedIn()->language()->addToStack('updating_club_renewals', FALSE, array() );

		return array( 'text' => $text, 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $offset, 2 ) ) : 100 );
	}

	/**
	 * Return the query
	 *
	 * @param	string	$select		What to select
	 * @param	array	$data		Queue data
	 * @param	int		$offset		Offset to use (FALSE to not apply limit)
	 * @return	\IPS\Db\Select
	 */
	protected function getQuery( $select, $data, $offset=FALSE )
	{
		return \IPS\Db::i()->select( $select, 'nexus_purchases', array( "ps_app=? and ps_type=? and ps_item_id=?", 'core', 'club', $data['club'] ), 'ps_id', ( $offset !== FALSE ) ? array( $offset, \IPS\REBUILD_QUICK ) : array()  );
	}
}