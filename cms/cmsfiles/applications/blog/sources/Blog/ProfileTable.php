<?php
/**
 * @brief		Blog Profile Table Helper
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		18 Mar 2014
 */

namespace IPS\blog\Blog;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Blog Profile Table Helper
 */
class _ProfileTable extends Table
{
	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url	$url	Base URL
	 * @return	void
	 */
	public function __construct( \IPS\Http\Url $url=NULL )
	{
		parent::__construct( $url );
	}

	/**
	 * Get rows
	 *
	 * @param	array	$advancedSearchValues	Values from the advanced search form
	 * @return	array
	 */
	public function getRows( $advancedSearchValues )
	{
		$rows = parent::getRows( $advancedSearchValues );
		
		$return = array();
		foreach( $rows AS $row )
		{
			if ( $row->owner() instanceof \IPS\Member )
			{
				$return['owner'][ $row->id ]		= $row;
			}
			else
			{
				$return['contributor'][ $row->id ]	= $row;
			}
		}
		
		return $return;
	}
}