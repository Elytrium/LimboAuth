<?php
/**
 * @brief		Member ACP Profile - Content Statistics Tab: Downloads
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		30 Nov 2017
 */

namespace IPS\downloads\extensions\core\MemberACPProfileContentTab;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Member ACP Profile - Content Statistics Tab: Downloads
 */
class _Downloads extends \IPS\core\MemberACPProfile\Block
{
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output()
	{
		$fileCount = \IPS\Db::i()->select( 'COUNT(*)', 'downloads_files', array( 'file_submitter=?', $this->member->member_id ) )->first();
		$diskspaceUsed = \IPS\Db::i()->select( 'SUM(file_size)', 'downloads_files', array( 'file_submitter=?', $this->member->member_id ) )->first();
		$numberOfDownloads = \IPS\Db::i()->select( 'COUNT(*)', 'downloads_downloads', array( 'dmid=?', $this->member->member_id ) )->first();
		$totalDownloads = \IPS\Db::i()->select( 'COUNT(*)', 'downloads_downloads' )->first();
		$bandwidthUsed = \IPS\Db::i()->select( 'SUM(dsize)', 'downloads_downloads', array( 'dmid=?', $this->member->member_id ) )->first();

		$allFiles = \IPS\Db::i()->select( 'COUNT(*)', 'downloads_files' )->first();
		$totalFileSize = \IPS\Db::i()->select( 'SUM(file_size)', 'downloads_files' )->first();
		$totalDownloadSize = \IPS\Db::i()->select( 'SUM(dsize)', 'downloads_downloads' )->first();

		return \IPS\Theme::i()->getTemplate( 'stats', 'downloads' )->information( $this->member, \IPS\Theme::i()->getTemplate( 'global', 'core' )->definitionTable( array(
			'files_submitted'		=>
				\IPS\Member::loggedIn()->language()->addToStack('downloads_stat_of_total', FALSE, array( 'sprintf' => array(
						\IPS\Member::loggedIn()->language()->formatNumber( $fileCount ),
						\IPS\Member::loggedIn()->language()->formatNumber( ( ( $allFiles ? ( 100 / $allFiles ) : 0 ) * $fileCount ), 2 ) ) )
				),
			'diskspace_used'		=>
				\IPS\Member::loggedIn()->language()->addToStack('downloads_stat_of_total', FALSE, array( 'sprintf' => array(
						\IPS\Output\Plugin\Filesize::humanReadableFilesize( $diskspaceUsed ),
						\IPS\Member::loggedIn()->language()->formatNumber( ( ( $totalFileSize ? ( 100 / $totalFileSize ) : 0 ) * $diskspaceUsed ), 2 ) ) )
				),
			'average_filesize_downloads'		=>
				\IPS\Member::loggedIn()->language()->addToStack('downloads_stat_average', FALSE, array( 'sprintf' => array(
						\IPS\Output\Plugin\Filesize::humanReadableFilesize( \IPS\Db::i()->select( 'AVG(file_size)', 'downloads_files', array( 'file_submitter=?', $this->member->member_id ) )->first() ),
						\IPS\Output\Plugin\Filesize::humanReadableFilesize( \IPS\Db::i()->select( 'AVG(file_size)', 'downloads_files' )->first() ) ))
				),
			'number_of_downloads'	=>
				\IPS\Member::loggedIn()->language()->addToStack('downloads_stat_of_total_link', FALSE, array( 'htmlsprintf' => array(
						\IPS\Member::loggedIn()->language()->formatNumber( $numberOfDownloads ),
						\IPS\Member::loggedIn()->language()->formatNumber( ( ( $totalDownloads ? ( 100 / $totalDownloads ) : 0 ) * $numberOfDownloads ), 2 ),
						\IPS\Theme::i()->getTemplate( 'stats', 'downloads' )->link( $this->member, 'downloads', 'downloads' ) ) )
				),
			'downloads_bandwidth_used'		=>
				\IPS\Member::loggedIn()->language()->addToStack('downloads_stat_of_total_link', FALSE, array( 'htmlsprintf' => array(
						\IPS\Output\Plugin\Filesize::humanReadableFilesize( $bandwidthUsed ),
						\IPS\Member::loggedIn()->language()->formatNumber( ( ( $totalDownloadSize ? ( 100 / $totalDownloadSize ) : 0 ) * $bandwidthUsed ), 2 ),
						\IPS\Theme::i()->getTemplate( 'stats', 'downloads' )->link( $this->member, 'bandwidth', 'bandwidth_use' ) ) )
				)
		) ) );
	}
	
	/**
	 * Edit Window
	 *
	 * @return	string
	 */
	public function edit()
	{
		switch ( \IPS\Request::i()->chart )
		{
			case 'downloads':
				$downloadsChart = new \IPS\Helpers\Chart\Database( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=editBlock&block=IPS\\downloads\\extensions\\core\\MemberACPProfileContentTab\\Downloads&id={$this->member->member_id}&chart=downloads&_graph=1" ), 'downloads_downloads', 'dtime', '', array(
						'backgroundColor' 	=> '#ffffff',
						'colors'			=> array( '#10967e', '#ea7963', '#de6470', '#6b9dde', '#b09be4', '#eec766', '#9fc973', '#e291bf', '#55c1a6', '#5fb9da' ),
						'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
						'lineWidth'			=> 1,
						'areaOpacity'		=> 0.4
					), 'ColumnChart', 'monthly', array( 'start' => 0, 'end' => 0 ), array( 'dfid', 'dtime', 'dsize', 'dua', 'dip' ) );
				$downloadsChart->where[] = array( 'dmid=?', $this->member->member_id );
				$downloadsChart->availableTypes = array( 'AreaChart', 'ColumnChart', 'BarChart', 'Table' );
				$downloadsChart->tableParsers = array(
					'dfid'	=> function( $val )
					{
						try
						{
							$file = \IPS\downloads\File::load( $val );
							return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $file->url(), TRUE, $file->name );
						}
						catch ( \OutOfRangeException $e )
						{
							return \IPS\Member::loggedIn()->language()->addToStack('deleted_file');
						}
					},
					'dtime'	=> function( $val )
					{
						return (string) \IPS\DateTime::ts( $val );
					},
					'dsize'	=> function( $val )
					{
						return \IPS\Output\Plugin\Filesize::humanReadableFilesize( $val );
					},
					'dua'	=> function( $val )
					{
						return (string) \IPS\Http\Useragent::parse( $val );
					},
					'dip'	=> function( $val )
					{
						return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( \IPS\Http\Url::internal( "app=core&module=members&controller=ip&ip={$val}&tab=downloads_DownloadLog" ), FALSE, $val );
					}
				);
				$downloadsChart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('downloads'), 'number', 'COUNT(*)', FALSE );
				$output = ( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->_graph ) ) ? (string) $downloadsChart : \IPS\Theme::i()->getTemplate( 'stats', 'downloads' )->graphs( (string) $downloadsChart );
			break;
		
			case 'bandwidth_use':
				$bandwidthChart = new \IPS\Helpers\Chart\Database( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=editBlock&block=IPS\\downloads\\extensions\\core\\MemberACPProfileContentTab\\Downloads&id={$this->member->member_id}&chart=bandwidth_use&_graph=1" ), 'downloads_downloads', 'dtime', '', array( 
						'vAxis' => array( 'title' => '(' . \IPS\Member::loggedIn()->language()->addToStack( 'filesize_raw_k' ) . ')' ),
						'backgroundColor' 	=> '#ffffff',
						'colors'			=> array( '#10967e', '#ea7963', '#de6470', '#6b9dde', '#b09be4', '#eec766', '#9fc973', '#e291bf', '#55c1a6', '#5fb9da' ),
						'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
						'lineWidth'			=> 1,
						'areaOpacity'		=> 0.4 
					) );
				$bandwidthChart->where[] = array( 'dmid=?', $this->member->member_id );
				$bandwidthChart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('bandwidth_use'), 'number', 'ROUND((SUM(dsize)/1024),2)', FALSE );
				$output = ( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->_graph ) ) ? (string) $bandwidthChart : \IPS\Theme::i()->getTemplate( 'stats', 'downloads' )->graphs( (string) $bandwidthChart );
			break;
		}
		
		return $output;
	}
}