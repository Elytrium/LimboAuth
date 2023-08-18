<?php
/**
 * @brief		Member filter extension: Content Count
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		22 Mar 2017
 */

namespace IPS\core\extensions\core\MemberFilter;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Member filter: Content Count
 */
class _Content
{
	/**
	 * Determine if the filter is available in a given area
	 *
	 * @param	string	$area	Area to check
	 * @return	bool
	 */
	public function availableIn( $area )
	{
		return \in_array( $area, array( 'bulkmail', 'group_promotions', 'automatic_moderation' ) );
	}

	/**
	 * Get Setting Field
	 *
	 * @param	mixed	$criteria	Value returned from the save() method
	 * @return	array 	Array of form elements
	 */
	public function getSettingField( $criteria )
	{
		return array(
			new \IPS\Helpers\Form\Custom( 'mf_content_count', array( 0 => isset( $criteria['content_count_operator'] ) ? $criteria['content_count_operator'] : NULL, 1 => isset( $criteria['content_count_score'] ) ? $criteria['content_count_score'] : NULL ), FALSE, array(
				'getHtml'	=> function( $element )
				{
					return \IPS\Theme::i()->getTemplate( 'forms', 'core' )->select( "{$element->name}[0]", $element->value[0], $element->required, array(
						'any'	=> \IPS\Member::loggedIn()->language()->addToStack('any'),
						'gt'	=> \IPS\Member::loggedIn()->language()->addToStack('gt'),
						'lt'	=> \IPS\Member::loggedIn()->language()->addToStack('lt'),
						'eq'	=> \IPS\Member::loggedIn()->language()->addToStack('exactly'),
					),
						FALSE,
						NULL,
						FALSE,
						array(
							'any'	=> array(),
							'gt'	=> array( 'elNumber_' . $element->name . '-qty' ),
							'lt'	=> array( 'elNumber_' . $element->name . '-qty' ),
							'eq'	=> array( 'elNumber_' . $element->name . '-qty' ),
						) )
					. ' '
					. \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->number( "{$element->name}[1]", $element->value[1], $element->required, NULL, FALSE, NULL, NULL, NULL, 0, NULL, FALSE, NULL, array(), array(), array(), $element->name . '-qty' );
				}
			) )
		);
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
		return array( 'content_count_operator' => $post['mf_content_count'][0], 'content_count_score' => $post['mf_content_count'][1] );
	}

	/**
	 * Get where clause to add to the member retrieval database query
	 *
	 * @param	mixed				$data	The array returned from the save() method
	 * @return	array|NULL			Where clause - must be a single array( "clause" )
	 */
	public function getQueryWhereClause( $data )
	{
		if ( isset( $data['content_count_operator'] ) and isset( $data['content_count_score'] ) )
		{
			switch ( $data['content_count_operator'] )
			{
				case 'gt':
					return array( "member_posts>{$data['content_count_score']}" );
					break;
				case 'lt':
					return array( "member_posts<{$data['content_count_score']}" );
					break;
				case 'eq':
					return array( "member_posts={$data['content_count_score']}" );
					break;
			}
		}

		return NULL;
	}

	/**
	 * Determine if a member matches specified filters
	 *
	 * @note	This is only necessary if availableIn() includes group_promotions
	 * @param	\IPS\Member	$member		Member object to check
	 * @param	array 		$filters	Previously defined filters
	 * @param	object|NULL	$object		Calling class
	 * @return	bool
	 */
	public function matches( \IPS\Member $member, $filters, $object=NULL )
	{
		/* If we aren't filtering by this, then any member matches */
		if( !isset( $filters['content_count_operator'] ) OR !isset( $filters['content_count_score'] ) )
		{
			return TRUE;
		}

		switch ( $filters['content_count_operator'] )
		{
			case 'gt':
				return (bool) ( $member->member_posts > (int) $filters['content_count_score'] );
				break;
			case 'lt':
				return (bool) ( $member->member_posts < (int) $filters['content_count_score'] );
				break;
			case 'eq':
				return (bool) ( $member->member_posts == (int) $filters['content_count_score'] );
				break;
		}

		/* If we are still here, then there wasn't an appropriate operator (maybe they selected 'any') so return true */
		return TRUE;
	}
	
	/**
	 * Return a lovely human description for this rule if used
	 *
	 * @param	mixed				$filters	The array returned from the save() method
	 * @return	string|NULL
	 */
	public function getDescription( $filters )
	{
		if ( ! empty( $filters['content_count_score'] ) and $filters['content_count_score'] > 0 )
		{
			switch ( $filters['content_count_operator'] )
			{
				case 'gt':
					return \IPS\Member::loggedIn()->language()->addToStack( 'member_filter_core_content_gt_desc', FALSE, array( 'sprintf' => array( $filters['content_count_score'] ) ) );
				break;
				case 'lt':
					return \IPS\Member::loggedIn()->language()->addToStack( 'member_filter_core_content_lt_desc', FALSE, array( 'sprintf' => array( $filters['content_count_score'] ) ) );
				break;
				case 'eq':
					return \IPS\Member::loggedIn()->language()->addToStack( 'member_filter_core_content_eq_desc', FALSE, array( 'sprintf' => array( $filters['content_count_score'] ) ) );
				break;
			}
		}
		
		return NULL;
	}
}