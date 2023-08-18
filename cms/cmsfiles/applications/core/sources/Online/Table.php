<?php
/**
 * @brief		Online Users Table Helper
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		8th September 2017
 */

namespace IPS\core\Online;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Promote Table Helper
 */
class _Table extends \IPS\Helpers\Table\Table
{	
	/**
	 * @brief	Rows
	 */
	protected static $rows = null;
	
	/**
	 * @brief	WHERE clause
	 */
	protected $where = array();
	
	/**
	 * @brief	WHERE clause
	 */
	public $count = 0;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url	Base URL
	 * @return	void
	 */
	public function __construct( \IPS\Http\Url $url=NULL )
	{
		/* Init */	
		parent::__construct( $url );
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
			/* Always return an array */
			static::$rows = array();
			
			/* What are we sorting by? */
			$sortDirection = ( mb_strtolower( $this->sortDirection ) == 'asc' ? 'asc' : 'desc' );
			$flags = \IPS\Session\Store::ONLINE_MEMBERS | \IPS\Session\Store::ONLINE_GUESTS;
			$memberGroup = NULL;
			
			if ( $this->filter == 'filter_loggedin' )
			{
				$flags = \IPS\Session\Store::ONLINE_MEMBERS;
			}
			elseif ( mb_stristr( $this->filter, 'group_' ) )
			{
				$memberGroup = \intval( $this->filters[ $this->filter ] );
			}

			$this->count = \IPS\Session\Store::i()->getOnlineUsers( $flags | \IPS\Session\Store::ONLINE_COUNT_ONLY, $sortDirection, NULL, $memberGroup, \IPS\Member::loggedIn()->isAdmin() );
			
			/* If we're not filtering - update our most online count */
			if ( $this->filter === NULL )
			{
				$mostOnline = json_decode( \IPS\Settings::i()->most_online, TRUE );
				if ( $this->count > $mostOnline['count'] )
				{
					\IPS\Settings::i()->changeValues( array( 'most_online' => json_encode( array(
						'count'		=> $this->count,
						'time'		=> time()
					) ) ) );
				}
			}

	  		$this->pages = ceil( $this->count / $this->limit );
	
			/* Get results */
			$rows = \IPS\Session\Store::i()->getOnlineUsers( $flags, $sortDirection, array( ( $this->limit * ( $this->page - 1 ) ), $this->limit ), $memberGroup, \IPS\Member::loggedIn()->isAdmin() );
			
			/* Loop the data */
			foreach ( $rows as $rowId => $row )
			{
				/* Add in any 'custom' fields */
				$_row = $row;
				if ( $this->include !== NULL )
				{
					$row = array();
					foreach ( $this->include as $k )
					{
						$row[ $k ] = isset( $_row[ $k ] ) ? $_row[ $k ] : NULL;
					}
					
					if( !empty( $advancedSearchValues ) AND !isset( \IPS\Request::i()->noColumn ) )
					{
						foreach ( $advancedSearchValues as $k => $v )
						{
							$row[ $k ] = isset( $_row[ $k ] ) ? $_row[ $k ] : NULL;
						}
					}
				}
			
				foreach ( $row as $k => $v )
				{
					/* Parse if necessary (NB: deliberately do this before removing the row in case we need to do some processing, but don't want the column to actually show) */
					if( isset( $this->parsers[ $k ] ) )
					{
						$parserFunction = $this->parsers[ $k ];
						$v = $parserFunction( $v, $_row );
					}
					else
					{
						$v = htmlspecialchars( $v, ENT_DISALLOWED | ENT_QUOTES, 'UTF-8', FALSE );
					}
	
					/* Are we including this one? */
					if( ( ( $this->include !== NULL and !\in_array( $k, $this->include ) ) or ( $this->exclude !== NULL and \in_array( $k, $this->exclude ) ) ) and !array_key_exists( $k, $advancedSearchValues ) )
					{
						unset( $row[ $k ] );
						continue;
					}
												
					/* Add to array */
					$row[ $k ] = $v;
				}
				
				static::$rows[ $rowId ] = $row;
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