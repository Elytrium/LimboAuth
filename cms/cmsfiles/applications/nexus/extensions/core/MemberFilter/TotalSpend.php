<?php
/**
 * @brief		Member filter extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		8 Aug 2018
 */

namespace IPS\nexus\extensions\core\MemberFilter;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member filter extension
 */
class _TotalSpend
{
	/**
	 * Determine if the filter is available in a given area
	 *
	 * @param	string	$area	Area to check
	 * @return	bool
	 */
	public function availableIn( $area )
	{
		return \in_array( $area, array( 'bulkmail' ) );
	}

	/** 
	 * Get Setting Field
	 *
	 * @param	mixed	$criteria	Value returned from the save() method
	 * @return	array 	Array of form elements
	 */
	public function getSettingField( $criteria )
	{
		return array( $this->_combine( 'nexus_bm_filters_total_spend', 'IPS\nexus\Form\Money', $criteria ) );
	}
	
	/**
	 * Save the filter data
	 *
	 * @param	array	$post	Form values
	 * @return	mixed			False, or an array of data to use later when filtering the members
	 * @throws \LogicException
	 */
	public function save( $post )
	{
		$amounts = array();

		foreach( $post['nexus_bm_filters_total_spend'][1] as $amount )
		{
			$amounts[$amount->currency] = $amount->amount;
		}

		return array( 'total_spend_operator' => $post['nexus_bm_filters_total_spend'][0], 'total_spend_amounts' => json_encode( $amounts ) );
	}
	
	/**
	 * Get where clause to add to the member retrieval database query
	 *
	 * @param	mixed				$data	The array returned from the save() method
	 * @return	string|array|NULL	Where clause
	 */
	public function getQueryWhereClause( $data )
	{
		if ( ( isset( $data['total_spend_operator'] ) and $data['total_spend_operator'] !== 'any' ) and isset( $data['total_spend_amounts'] ) )
		{
			$where = array();

			foreach( json_decode( $data['total_spend_amounts'], true ) as $currency => $amount )
			{
				switch ( $data['total_spend_operator'] )
				{
					case 'gt':
						$operator = ">";
						break;
					case 'lt':
						$operator = "<";
						break;
				}

				$where[] = "(nexus_customer_spend.spend_amount{$operator}'{$amount}' and nexus_customer_spend.spend_currency='{$currency}')";
			}

			return array( implode( ' OR ', $where ) );
		}
		return NULL;
	}
	
	/**
	 * Callback for member retrieval database query
	 * Can be used to set joins
	 *
	 * @param	mixed			$data	The array returned from the save() method
	 * @param	\IPS\Db\Query	$query	The query
	 * @return	void
	 */
	public function queryCallback( $data, &$query )
	{
		$currencies = \IPS\nexus\Money::currencies();
		$defaultCurrency = array_shift( $currencies );

		if ( ( isset( $data['total_spend_operator'] ) and $data['total_spend_operator'] !== 'any' ) and isset( $data['total_spend_amounts'] ) )
		{
			$query->join( 'nexus_customer_spend', "core_members.member_id=nexus_customer_spend.spend_member_id AND nexus_customer_spend.spend_currency='{$defaultCurrency}'" );
		}
	}

	/**
	 * Combine two fields
	 *
	 * @param	string		$name			Field name
	 * @param	bool		$field2Class	Classname for second field
	 * @param	mixed		$criteria		Value returned from the save() method
	 * @return	\IPS\Helpers\Form\Custom
	 */
	public function _combine( $name, $field2Class, $criteria )
	{
		$validate = NULL;

			$options = array(
				'options' => array(
					'any'	=> 'any_value',
					'gt'	=> 'gt',
					'lt'	=> 'lt'
				),
				'toggles' => array(
					'gt'	=> array( $name . '_unit' ),
					'lt'	=> array( $name . '_unit' ),
				)
			);

		$field1 = new \IPS\Helpers\Form\Select( $name . '_type', isset($criteria['total_spend_operator']) ? $criteria['total_spend_operator'] : '', FALSE, $options, NULL, NULL, NULL );
		$field2 = new $field2Class( $name . '_unit', isset( $criteria['total_spend_amounts'] ) ? $criteria['total_spend_amounts'] : NULL, FALSE, array() );

		return new \IPS\Helpers\Form\Custom( $name, array( "gt", NULL ), FALSE, array(
			'getHtml'	=> function() use ( $name, $field1, $field2 )
			{
				return \IPS\Theme::i()->getTemplate( 'forms', 'nexus', 'global' )->combined( $name, $field1, $field2 );
			},
			'formatValue'	=> function() use ( $field1, $field2 )
			{
				return array( $field1->value, $field2->value );
			}
		) );
	}
}