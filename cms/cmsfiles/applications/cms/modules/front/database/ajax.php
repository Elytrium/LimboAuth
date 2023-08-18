<?php
/**
 * @brief		Ajax only methods
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		01 Oct 2014
 */

namespace IPS\cms\modules\front\database;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Ajax only methods
 */
class _ajax extends \IPS\Dispatcher\Controller
{
	/**
	 * Return a FURL
	 *
	 * @return	void
	 */
	protected function makeFurl()
	{
		return \IPS\Output::i()->json( array( 'slug' => \IPS\Http\Url\Friendly::seoTitle( \IPS\Request::i()->slug ) ) );
	}

	/**
	 * Find Record
	 *
	 * @return	void
	 */
	public function findRecord()
	{
		$results  = array();
		$database = \IPS\cms\Databases::load( \IPS\Request::i()->id );
		$input    = mb_strtolower( \IPS\Request::i()->input );
		$field    = "field_" . $database->field_title;
		$class    = '\IPS\cms\Records' . $database->id;
		$category = '';

		$where = array( $field . " LIKE CONCAT('%', ?, '%')" );
		$binds = array( $input );

		foreach ( \IPS\Db::i()->select( '*', 'cms_custom_database_' . $database->id, array_merge( array( implode( ' OR ', $where ) ), $binds ), 'LENGTH(' . $field . ') ASC', array( 0, 20 ) ) as $row )
		{
			$record = $class::constructFromData( $row );
			
			if ( ! $record->canView() )
			{
				continue;
			}
			
			if ( $database->use_categories )
			{
				$category = \IPS\Member::loggedIn()->language()->addToStack( 'cms_autocomplete_category', FALSE, array( 'sprintf' => array( $record->container()->_title ) ) );
			}

			$results[] = array(
				'id'	   => $record->_id,
				'value'    => $record->_title,
				'category' => $category,
				'date'	   => \IPS\DateTime::ts( $record->record_publish_date )->html(),
			);
		}

		\IPS\Output::i()->json( $results );
	}
	
}