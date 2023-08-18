<?php
/**
 * @brief		topDownloads Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		09 Jan 2014
 */

namespace IPS\downloads\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * topDownloads Widget
 */
class _topDownloads extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'topDownloads';
	
	/**
	 * @brief	App
	 */
	public $app = 'downloads';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * @brief	Cache Expiration
	 * @note	We allow this cache to be valid for 48 hours
	 */
	public $cacheExpiration = 172800;

	/**
	* Init the widget
	*
	* @return	void
	*/
	public function init()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'widgets.css', 'downloads', 'front' ) );

		parent::init();
	}
	
	/**
	 * Specify widget configuration
	 *
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
 	{
		$form = parent::configuration( $form );
 		
		$form->add( new \IPS\Helpers\Form\Number( 'number_to_show', isset( $this->configuration['number_to_show'] ) ? $this->configuration['number_to_show'] : 5, TRUE ) );
		return $form;
 	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$categories = array();

		foreach( \IPS\Db::i()->select( 'perm_type_id', 'core_permission_index', array( 'app=? and perm_type=? and (' . \IPS\Db::i()->findInSet( 'perm_' . \IPS\downloads\Category::$permissionMap['read'], \IPS\Member::loggedIn()->groups ) . ' OR ' . 'perm_' . \IPS\downloads\Category::$permissionMap['read'] . '=? )', 'downloads', 'category', '*' ) ) as $category )
		{
			$categories[]	= $category;
		}

		if( !\count( $categories ) )
		{
			return '';
		}

		foreach ( array( 'week' => 'P1W', 'month' => 'P1M', 'year' => 'P1Y', 'all' => NULL ) as $time => $interval )
		{			
			$where = array( array( 'file_cat IN(' . implode( ',', $categories ) . ')' ) );
			if ( $interval )
			{
				$where[] = array( 'dtime>?', \IPS\DateTime::create()->sub( new \DateInterval( $interval ) )->getTimestamp() );
			}
			
			$ids	= array();
			$cases	= array();

			foreach( \IPS\Db::i()->select( 'dfid, count(*) AS downloads', 'downloads_downloads', $where, 'downloads DESC', isset( $this->configuration['number_to_show'] ) ? $this->configuration['number_to_show'] : 5, array( 'dfid' ) )->join( 'downloads_files', 'dfid=file_id' ) as $download )
			{
				$ids[]		= $download['dfid'];
				$cases[]	= "WHEN file_id={$download['dfid']} THEN {$download['downloads']}";
			}

			if( \count( $ids ) )
			{
				$$time = new \IPS\Patterns\ActiveRecordIterator(
					\IPS\Db::i()->select(
						'*, CASE ' . implode( ' ', $cases ) . ' END AS file_downloads',
						'downloads_files',
						'file_id IN(' . implode( ',', $ids ) . ')',
						'file_downloads DESC'
					),
					'IPS\downloads\File'
				);
			}
			else
			{
				$$time = array();
			}
		}
		
		return $this->output( $week, $month, $year, $all );
	}
}