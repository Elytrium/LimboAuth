<?php
/**
 * @brief		downloadStats Widget
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
 * downloadStats Widget
 */
class _downloadStats extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'downloadStats';
	
	/**
	 * @brief	App
	 */
	public $app = 'downloads';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

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
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$stats = \IPS\Db::i()->select( 'COUNT(*) AS totalFiles, SUM(file_comments) AS totalComments, SUM(file_reviews) AS totalReviews', 'downloads_files', array( "file_open=?", 1 ) )->first();
		$stats['totalAuthors'] = \IPS\Db::i()->select( 'COUNT(DISTINCT file_submitter)', 'downloads_files' )->first();
		
		$latestFile = NULL;
		foreach ( \IPS\downloads\File::getItemsWithPermission( array(), NULL, 1 ) as $latestFile )
		{
			break;
		}
		
		return $this->output( $stats, $latestFile );
	}
}