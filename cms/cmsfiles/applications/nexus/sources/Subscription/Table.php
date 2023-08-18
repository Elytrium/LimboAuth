<?php
/**
 * @brief		Subscriptions table
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		09 Feb 2018
 */

namespace IPS\nexus\Subscription;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Subscriptions table
 */
class _Table extends \IPS\Helpers\Table\Table
{
	/**
	 * @brief	Sort options
 	*/
	public $sortOptions = array( 'sp_position' );

	/**
	 * @brief	Active Subscription
	 */
	public $activeSubscription = NULL;
	
	/**
	 * @brief	Rows
	 */
	protected static $rows = null;
	
	/**
	 * @brief	WHERE clause
	 */
	protected $where = array();
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url	$url	Base URL
	 * @return	void
	 */
	public function __construct( \IPS\Http\Url $url=NULL )
	{
		/* Init */	
		parent::__construct( $url );

		$this->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'subscription', 'nexus', 'front' ), 'table' );
		$this->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'subscription', 'nexus', 'front' ), 'rows' );
	}

	/**
	 * Get rows
	 *
	 * @param	array	$advancedSearchValues	Values from the advanced search form
	 * @return	array
	 */
	public function getRows( $advancedSearchValues=NULL )
	{
		if ( static::$rows === NULL )
		{
			/* Check sortBy */
			$this->sortBy = \in_array( $this->sortBy, $this->sortOptions ) ? $this->sortBy : 'sp_position';
	
			/* What are we sorting by? */
			$sortBy = $this->sortBy . ' ' . ( mb_strtolower( $this->sortDirection ) == 'desc' ? 'desc' : 'asc' );
	
			/* Specify filter in where clause */
			$where = isset( $this->where ) ? \is_array( $this->where ) ? $this->where : array( $this->where ) : array();
			
			if ( $this->filter and isset( $this->filters[ $this->filter ] ) )
			{
				$where[] = \is_array( $this->filters[ $this->filter ] ) ? $this->filters[ $this->filter ] : array( $this->filters[ $this->filter ] );
			}

			// We want also the disabled but active subscription
			if( !$this->activeSubscription )
			{
				$where[] = array( 'sp_enabled=1' );
			}
			else
			{
				$where[] = array( '(sp_enabled=1 OR sp_id=?)', $this->activeSubscription->package_id );
			}
	
			/* Get Count */
			$count = \IPS\Db::i()->select( 'COUNT(*) as cnt', 'nexus_member_subscription_packages', $where )->first();
	  		$this->pages = ceil( $count / $this->limit );
	
			/* Get results */
			$it = \IPS\Db::i()->select( '*', 'nexus_member_subscription_packages', $where, $sortBy, array( ( $this->limit * ( $this->page - 1 ) ), $this->limit ) );
			$rows = iterator_to_array( $it );
	
			foreach( $rows as $index => $row )
			{
				try
				{
					static::$rows[ $index ]	= \IPS\nexus\Subscription\Package::constructFromData( $row );
				}
				catch ( \Exception $e ) { }
			}
		}
		
		/* Return */
		return static::$rows;
	}

	/**
	 * Return the table headers
	 *
	 * @param	array|NULL	$advancedSearchValues	Advanced search values
	 * @return	array
	 */
	public function getHeaders( $advancedSearchValues )
	{
		return array();
	}
}