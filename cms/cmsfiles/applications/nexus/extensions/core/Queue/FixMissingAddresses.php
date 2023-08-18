<?php
/**
 * @brief		Background task to fix refunded transactions following the 4.3.5 change to treat refunds and credits separately
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		10 Apr 2019
 */

namespace IPS\nexus\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background task to fix refunded transactions following the 4.3.5 change to treat refunds and credits separately
 */
class _FixMissingAddresses
{
	/**
	 * @brief	Number of records to process per cycle
	 */
	public $perCycle = \IPS\REBUILD_QUICK;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$data['count'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_invoices', array( 'i_status=? AND i_billaddress IS NULL', \IPS\nexus\Invoice::STATUS_PAID ) )->first();

		if( $data['count'] == 0 )
		{
			return NULL;
		}

		$data['done'] = 0;
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
		$invoices = \IPS\Db::i()->select( '*', 'nexus_invoices', array( array( 'i_id>?', $offset ), array( 'i_status=? AND i_billaddress IS NULL', \IPS\nexus\Invoice::STATUS_PAID ) ), 'i_id ASC', $this->perCycle );
		$lastId = 0;
		
		foreach( $invoices as $invoiceData )
		{
			$invoice = \IPS\nexus\Invoice::constructFromData( $invoiceData );

			try
			{					
				$invoice->billaddress = \IPS\nexus\Customer\Address::constructFromData( \IPS\Db::i()->select( '*', 'nexus_customer_addresses', array( '`member`=? AND primary_billing=1', $invoiceData['i_member'] ) )->first() )->address;
				$invoice->save();
			}
			catch ( \UnderflowException $e ) { }

			$lastId = $invoiceData['i_id'];
		}
		
		if( $lastId == 0 )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$data['done'] += $this->perCycle;

		return $lastId;
	}
	
	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array( 'text' => 'Doing something...', 'complete' => 50 )	Text explaining task and percentage complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function getProgress( $data, $offset )
	{
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('upgrade_fix_missing_addresses'), 'complete' => $data['done'] ? ( round( 100 / $data['done'] * $data['count'], 2 ) ) : 0  );
	}
}