<?php
/**
 * @brief		Flat Shipping Rate Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		13 Feb 2014
 */

namespace IPS\nexus\Shipping;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Flat Shipping Rate Node
 */
class _FlatRate extends \IPS\Node\Model implements \IPS\nexus\Shipping\Rate
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'nexus_shipping';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 's_';
		
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'order';
		
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'shipping_rates';
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'nexus_shiprate_';

	/**
	 * @brief	[Node] ACP Restrictions
	 * @code
	 	array(
	 		'app'		=> 'core',				// The application key which holds the restrictrions
	 		'module'	=> 'foo',				// The module key which holds the restrictions
	 		'map'		=> array(				// [Optional] The key for each restriction - can alternatively use "prefix"
	 			'add'			=> 'foo_add',
	 			'edit'			=> 'foo_edit',
	 			'permissions'	=> 'foo_perms',
	 			'delete'		=> 'foo_delete'
	 		),
	 		'all'		=> 'foo_manage',		// [Optional] The key to use for any restriction not provided in the map (only needed if not providing all 4)
	 		'prefix'	=> 'foo_',				// [Optional] Rather than specifying each  key in the map, you can specify a prefix, and it will automatically look for restrictions with the key "[prefix]_add/edit/permissions/delete"
	 * @endcode
	 */
	protected static $restrictions = array(
		'app'		=> 'nexus',
		'module'	=> 'payments',
		'all'		=> 'shipmethods_manage'
	);
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->add( new \IPS\Helpers\Form\Translatable( 'shiprate_name', NULL, TRUE, array( 'app' => 'nexus', 'key' => $this->id ? "nexus_shiprate_{$this->id}" : NULL, 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('shiprate_name_placeholder') ) ) );
		$form->add( new \IPS\nexus\Form\StateSelect( 's_locations', ( $this->id and $this->locations != '*' ) ? json_decode( $this->locations, TRUE ) : '*', TRUE, array( 'unlimitedLang' => 'all_locations' ) ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'shiprate_delivery_estimate', NULL, TRUE, array( 'app' => 'nexus', 'key' => $this->id ? "nexus_shiprate_de_{$this->id}" : NULL, 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('shiprate_delivery_estimate_placeholder') ) ) );
		$form->add( new \IPS\Helpers\Form\Node( 's_tax', $this->id ? $this->tax : 0, FALSE, array( 'class' => '\IPS\nexus\Tax', 'zeroVal' => 'do_not_tax' ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 's_type', $this->type, TRUE, array(
			'options' => array(
				't'	=> 's_type_t',
				'w'	=> 's_type_w',
				'q'	=> 's_type_q'
			),
			'toggles'	=> array(
				'w'	=> array( 's_rates_w' ),
				't'	=> array( 's_rates_t' ),
				'q'	=> array( 's_rates_q' ),
			)
		) ) );

		$weightMatrix = new \IPS\Helpers\Form\Matrix( 'w_matrix_' . ( $this->_id ?: 'new' ) );
		$weightMatrix->columns = array(
			'w_rate_min'=> function( $key, $value, $data )
			{
				return new \IPS\nexus\Form\Weight( $key, ( \is_numeric( $value ) ) ? new \IPS\nexus\Shipping\Weight( $value ) : $value, FALSE, array( 'unlimitedLang' => 's_any_value' ) );
			},
			'w_rate_max'=> function( $key, $value, $data )
			{
				return new \IPS\nexus\Form\Weight( $key, ( \is_numeric( $value ) ) ? new \IPS\nexus\Shipping\Weight( $value ) : $value, FALSE, array( 'unlimitedLang' => 's_any_value' ) );
			},
			'w_rate_price'=> function( $key, $value, $data )
			{
				return new \IPS\nexus\Form\Money( $key, $value, FALSE, array() );
			},
		);
		if ( $this->type === 'w' )
		{
			foreach ( json_decode( $this->rates, TRUE ) as $row )
			{
				$prices = array();
				foreach ( $row['price'] as $currency => $amount )
				{
					$prices[ $currency ] = new \IPS\nexus\Money( $amount, $currency );
				}
				
				$weightMatrix->rows[] = array(
					'w_rate_min'	=> $row['min'],
					'w_rate_max'	=> $row['max'],
					'w_rate_price'	=> $prices,
				);
			}
		}
		else
		{
			$weightMatrix->rows [] = array();
		}
		$form->addMatrix( 's_rates_w', $weightMatrix );
		
		$totalMatrix = new \IPS\Helpers\Form\Matrix( 't_matrix_' . ( $this->_id ?: 'new' ) );
		$totalMatrix->columns = array(
			't_rate_min'=> function( $key, $value, $data )
			{
				return new \IPS\nexus\Form\Money( $key, $value, FALSE, array( 'unlimitedLang' => 's_any_value' ) );
			},
			't_rate_max'=> function( $key, $value, $data )
			{
				return new \IPS\nexus\Form\Money( $key, $value, FALSE, array( 'unlimitedLang' => 's_any_value' ) );
			},
			't_rate_price'=> function( $key, $value, $data )
			{
				return new \IPS\nexus\Form\Money( $key, $value, FALSE, array() );
			},
		);
		if ( $this->type === 't' )
		{
			foreach ( json_decode( $this->rates, TRUE ) as $row )
			{
				$prices = array();
				foreach ( $row['price'] as $currency => $amount )
				{
					$prices[ $currency ] = new \IPS\nexus\Money( $amount, $currency );
				}
				
				$totalMatrix->rows[] = array(
					't_rate_min'	=> $row['min'],
					't_rate_max'	=> $row['max'],
					't_rate_price'	=> $prices,
				);
			}
		}
		else
		{
			$totalMatrix->rows [] = array();
		}
		$form->addMatrix( 's_rates_t', $totalMatrix );
		
		$itemsMatrix = new \IPS\Helpers\Form\Matrix( 'q_matrix_' . ( $this->_id ?: 'new' ) );
		$itemsMatrix->columns = array(
			'q_rate_min'=> function( $key, $value, $data )
			{
				return new \IPS\Helpers\Form\Number( $key, $value, FALSE, array( 'unlimited' => '*', 'unlimitedLang' => 's_any_value' ) );
			},
			'q_rate_max'=> function( $key, $value, $data )
			{
				return new \IPS\Helpers\Form\Number( $key, $value, FALSE, array( 'unlimited' => '*', 'unlimitedLang' => 's_any_value' ) );
			},
			'q_rate_price'=> function( $key, $value, $data )
			{
				return new \IPS\nexus\Form\Money( $key, $value, FALSE, array() );
			},
		);
		if ( $this->type === 'q' )
		{
			foreach ( json_decode( $this->rates, TRUE ) as $row )
			{
				$prices = array();
				foreach ( $row['price'] as $currency => $amount )
				{
					$prices[ $currency ] = new \IPS\nexus\Money( $amount, $currency );
				}
				
				$itemsMatrix->rows[] = array(
					'q_rate_min'	=> $row['min'],
					'q_rate_max'	=> $row['max'],
					'q_rate_price'	=> $prices,
				);
			}
		}
		else
		{
			$itemsMatrix->rows [] = array();
		}
		$form->addMatrix( 's_rates_q', $itemsMatrix );

	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{		
		if( isset( $values['s_locations'] ) )
		{
			$values['s_locations'] = $values['s_locations'] == '*' ? '*' : json_encode( $values['s_locations'] );
		}
		
		if( isset( $values['s_type'] ) AND isset( $values[ "s_rates_{$values['s_type']}" ] ) )
		{			
			$rates = array();
			/* Populate */
			foreach ( $values[ "s_rates_{$values['s_type']}" ] as $k => $rate )
			{				
				if ( $rate["{$values['s_type']}_rate_min"] === '*' )
				{
					$min = '*';
				}
				else
				{
					switch ( $values['s_type'] )
					{
						case 'w':
							$min = $rate["{$values['s_type']}_rate_min"]->kilograms;
							break;
						case 't':
							$min = array();	
							foreach ( $rate["{$values['s_type']}_rate_min"] as $money )
							{
								$min[ $money->currency ] = $money->amount;
							}
							break;
						case 'q':
							$min = $rate["{$values['s_type']}_rate_min"];
							break;
					}
				}
				
				if ( $rate["{$values['s_type']}_rate_max"] === '*' )
				{
					$max = '*';
				}
				else
				{
					switch ( $values['s_type'] )
					{
						case 'w':
							$max = $rate["{$values['s_type']}_rate_max"]->kilograms;
							break;
						case 't':
							$max = array();
							foreach ( $rate["{$values['s_type']}_rate_max"] as $money )
							{
								$max[ $money->currency ] = $money->amount;
							}
							break;
						case 'q':
							$max = $rate["{$values['s_type']}_rate_max"];
							break;
					}
				}
								
				if ( !$min and !$max )
				{
					continue;
				}
								
				$price = array();
				foreach ( $rate["{$values['s_type']}_rate_price"] as $money )
				{
					$price[ $money->currency ] = $money->amount;
				}
				if ( !\count( $price ) )
				{
					foreach ( \IPS\nexus\Money::currencies() as $currency )
					{
						$price[ $currency ] = 0;
					}
				}
				
				$rates[] = array(
					'min'	=> $min,
					'max'	=> $max,
					'price'	=> $price
				);
			}
			
			/* Remove duplicates */
			$_rates = array();
			foreach ( $rates as $k => $v )
			{
				$_rates[ $k ] = json_encode( $v );
			}
			$__rates = array_unique( $_rates );
			foreach( array_diff( array_keys( $_rates ), array_keys( $__rates ) ) as $k )
			{
				unset( $rates[$k] );
			}
										
			/* Save */
			$values['rates'] = json_encode( $rates );
		}
		
		$values['s_tax'] = $values['s_tax'] ? $values['s_tax']->id : 0;

		if ( !$this->id )
		{
			$this->save();
		}

		if( isset( $values['shiprate_name'] ) )
		{
			\IPS\Lang::saveCustom( 'nexus', "nexus_shiprate_{$this->id}", $values['shiprate_name'] );
			unset( $values['shiprate_name'] );
		}

		if( isset( $values['shiprate_delivery_estimate'] ) )
		{
			\IPS\Lang::saveCustom( 'nexus', "nexus_shiprate_de_{$this->id}", $values['shiprate_delivery_estimate'] );
			unset( $values['shiprate_delivery_estimate'] );
		}

		if( isset( $values['s_rates_w'] ) )
		{
			unset( $values['s_rates_w'] );
		}

		if( isset( $values['s_rates_t'] ) )
		{
			unset( $values['s_rates_t'] );
		}

		if( isset( $values['s_rates_q'] ) )
		{
			unset( $values['s_rates_q'] );
		}
		
		return $values;
	}
	
	/**
	 * Is available?
	 *
	 * @param	\IPS\GeoLocation		$destination	Desired destination
	 * @param	array					$items			Items
	 * @param	string					$currency		Desired currency
	 * @param	\IPS\nexus\Invoice|NULL	$invoice		The invoice
	 * @return	bool
	 */
	public function isAvailable( \IPS\GeoLocation $destination, array $items, $currency, \IPS\nexus\Invoice $invoice = NULL )
	{		
		/* Is available to destination? */
		if ( $this->locations !== '*' )
		{
			$locations = json_decode( $this->locations, TRUE );
			
			if ( isset( $locations[ $destination->country ] ) )
			{
				if ( $locations[ $destination->country ] !== '*' and !\in_array( $destination->region, $locations[ $destination->country ] ) )
				{
					return FALSE;
				}
			}
			else
			{
				return FALSE;
			}
		}
		
		/* Is it available for what's being bought? */
		$criteria = $this->_getCriteria( $items, $invoice );
		$rates = json_decode( $this->rates, TRUE );
		foreach ( $rates as $rate )
		{
			$min = $rate['min'];
			if ( \is_array( $min ) )
			{
				$min = $min[ $currency ];
			}
			$max = $rate['max'];
			if ( \is_array( $max ) )
			{
				$max = $max[ $currency ];
			}
						
			if ( ( $min === '*' or $criteria->compare( new \IPS\Math\Number( number_format( $min, 5, '.', '' ) ) ) !== -1 ) and ( $max === '*' or $criteria->compare( new \IPS\Math\Number( number_format( $max, 5, '.', '' ) ) ) !== 1 ) )
			{
				return TRUE;
			}
		}
		return FALSE;
	}
	
	/**
	 * Name
	 *
	 * @return	string
	 */
	public function getName()
	{
		return \IPS\Member::loggedIn()->language()->get( 'nexus_shiprate_' . $this->id );
	}
	
	/**
	 * Get criteria
	 *
	 * @param	array					$items		Items
	 * @param	\IPS\nexus\Invoice|NULL	$invoice	The invoice
	 * @return	\IPS\Math\Number		The criteria the rased is based on (e.g. if the rate is based on invoice total, will return the invoice total)
	 */
	protected function _getCriteria( array $items, \IPS\nexus\Invoice $invoice = NULL )
	{
		switch ( $this->type )
		{
			case 't':
				if ( $invoice )
				{
					$criteria = $invoice->total->amount;
				}
				else
				{
					$criteria = new \IPS\Math\Number('0');
					foreach ( $items as $item )
					{
						$criteria = $criteria->add( $item->linePrice()->amount );
					}
				}
				break;
				
			case 'q':
				$criteria = new \IPS\Math\Number('0');
				foreach ( $items as $item )
				{
					$criteria = $criteria->add( new \IPS\Math\Number( "{$item->quantity}" ) );
				}
				break;
				
			case 'w':
				$criteria = new \IPS\Math\Number('0');
				foreach ( $items as $item )
				{
					if ( isset( $item->weight ) )
					{
						$criteria = $criteria->add( ( new \IPS\Math\Number( number_format( $item->weight->kilograms, 5, '.', '' ) ) )->multiply( new \IPS\Math\Number( "$item->quantity" ) ) );
					}
				}
		}
		
		return $criteria;
	}
	
	/**
	 * Price
	 *
	 * @param	array					$items		Items
	 * @param	string					$currency	Desired currency
	 * @param	\IPS\nexus\Invoice|NULL	$invoice	The invoice
	 * @return	\IPS\nexus\Money
	 */
	public function getPrice( array $items, $currency, \IPS\nexus\Invoice $invoice = NULL )
	{
		$criteria = $this->_getCriteria( $items, $invoice );
				
		$rates = json_decode( $this->rates, TRUE );
		foreach ( $rates as $rate )
		{
			$min = $rate['min'];
			if ( \is_array( $min ) )
			{
				$min = $min[ $currency ];
			}
			$max = $rate['max'];
			if ( \is_array( $max ) )
			{
				$max = $max[ $currency ];
			}

			if ( ( $min === '*' or $criteria->compare( new \IPS\Math\Number( number_format( $min, 5, '.', '' ) ) ) !== -1 ) and ( $max === '*' or $criteria->compare( new \IPS\Math\Number( number_format( $max, 5, '.', '' ) ) ) !== 1 ) )
			{
				return new \IPS\nexus\Money( $rate['price'][ $currency ], $currency );
			}
		}
		
		if ( isset( $rate ) )
		{
			return new \IPS\nexus\Money( $rate['price'][ $currency ], $currency );
		}
		
		return new \IPS\nexus\Money( 0, $currency );
	}
	
	/**
	 * Tax
	 *
	 * @return	\IPS\nexus\Tax|NULL
	 */
	public function getTax()
	{
		try
		{
			return $this->tax ? \IPS\nexus\Tax::load( $this->tax ) : NULL;
		}
		catch ( \Exception $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Estimated delivery date
	 *
	 * @param	array					$items		Items
	 * @param	\IPS\nexus\Invoice|NULL	$invoice	The invoice
	 * @return	string
	 */
	public function getEstimatedDelivery( array $items, \IPS\nexus\Invoice $invoice = NULL )
	{
		return \IPS\Member::loggedIn()->language()->addToStack( "nexus_shiprate_de_{$this->id}" );
	}
	
	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse		int		id		ID number
	 * @apiresponse		string	name	Name
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		return array(
			'id'	=> $this->id,
			'name'	=> \IPS\Member::loggedIn()->language()->addToStack( 'nexus_shiprate_' . $this->id )
		);
	}

	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();

		\IPS\Lang::deleteCustom( 'nexus', "nexus_shiprate_de_{$this->id}" );
	}

	/**
	 * [ActiveRecord] Duplicate
	 *
	 * @return	void
	 */
	public function __clone()
	{
		$oldId = $this->_id;

		parent::__clone();

		\IPS\Lang::saveCustom( 'nexus', "nexus_shiprate_de_{$this->id}", iterator_to_array( \IPS\Db::i()->select( 'word_custom, lang_id', 'core_sys_lang_words', array( 'word_key=?', "nexus_shiprate_de_{$oldId}" ) )->setKeyField( 'lang_id' )->setValueField('word_custom') ) );
	}
}