<?php
/**
 * @brief		Member ACP Profile - Content Statistics Tab: Gallery
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		30 Nov 2017
 */

namespace IPS\gallery\extensions\core\MemberACPProfileContentTab;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Member ACP Profile - Content Statistics Tab: Gallery
 */
class _Gallery extends \IPS\core\MemberACPProfile\Block
{
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output()
	{
		$imageCount = \IPS\Db::i()->select( 'COUNT(*)', 'gallery_images', array( 'image_member_id=?', $this->member->member_id ) )->first();
		$diskspaceUsed = \IPS\Db::i()->select( 'SUM(image_file_size)', 'gallery_images', array( 'image_member_id=?', $this->member->member_id ) )->first();
		$numberOfViews = \IPS\Db::i()->select( 'COUNT(*)', 'gallery_bandwidth', array( 'member_id=?', $this->member->member_id ) )->first();
		$bandwidthUsed = \IPS\Db::i()->select( 'SUM(bsize)', 'gallery_bandwidth', array( 'member_id=?', $this->member->member_id ) )->first();
		
		$allImages = \IPS\Db::i()->select( 'COUNT(*)', 'gallery_images' )->first();
		$totalFilesize = \IPS\Db::i()->select( 'SUM(image_file_size)', 'gallery_images' )->first();
		$allBandwidth = \IPS\Db::i()->select( 'COUNT(*)', 'gallery_bandwidth' )->first();
		$totalBandwidth = \IPS\Db::i()->select( 'SUM(bsize)', 'gallery_bandwidth' )->first();
		
		return \IPS\Theme::i()->getTemplate( 'stats', 'gallery' )->information( $this->member, \IPS\Theme::i()->getTemplate( 'global', 'core' )->definitionTable( array(
			'images_submitted'		=> 
				\IPS\Member::loggedIn()->language()->addToStack( 'images_stat_of_total', FALSE, array( 'sprintf' => array(
				\IPS\Member::loggedIn()->language()->formatNumber( $imageCount ),
				\IPS\Member::loggedIn()->language()->formatNumber( ( ( $allImages ? ( 100 / $allImages ) : 0 ) * $imageCount ), 2 ) ) )
			),
			'gdiskspace_used'		=> 
				\IPS\Member::loggedIn()->language()->addToStack( 'images_stat_of_total', FALSE, array( 'sprintf' => array(
				\IPS\Output\Plugin\Filesize::humanReadableFilesize( $diskspaceUsed ),
				\IPS\Member::loggedIn()->language()->formatNumber( ( ( $totalFilesize ? ( 100 / $totalFilesize ) : 0 ) * $diskspaceUsed ), 2 ) ) )
			),
			'gaverage_filesize'		=> 
				\IPS\Member::loggedIn()->language()->addToStack( 'images_stat_average' , FALSE, array( 'sprintf' => array(
				\IPS\Output\Plugin\Filesize::humanReadableFilesize( \IPS\Db::i()->select( 'AVG(image_file_size)', 'gallery_images', array( 'image_member_id=?', $this->member->member_id ) )->first() ),
				\IPS\Output\Plugin\Filesize::humanReadableFilesize( \IPS\Db::i()->select( 'AVG(image_file_size)', 'gallery_images' )->first() ) ) )
			),
			'number_of_views'		=> 
				\IPS\Member::loggedIn()->language()->addToStack( 'images_stat_of_total', FALSE, array( 'sprintf' => array(
				\IPS\Member::loggedIn()->language()->formatNumber( $numberOfViews ),
				\IPS\Member::loggedIn()->language()->formatNumber( ( ( $allBandwidth ? ( 100 / $allBandwidth ) : 0 ) * $imageCount ), 2 ) ))
			),
			'gallery_bandwidth_used'		=> 
				\IPS\Member::loggedIn()->language()->addToStack( 'images_stat_of_total_link', FALSE, array( 'htmlsprintf' => array(
				\IPS\Output\Plugin\Filesize::humanReadableFilesize( $bandwidthUsed ),
				\IPS\Member::loggedIn()->language()->formatNumber( ( ( $totalBandwidth ? ( 100 / $totalBandwidth ) : 0 ) * $bandwidthUsed ), 2 ),
				\IPS\Theme::i()->getTemplate( 'stats', 'gallery' )->bandwidthButton( $this->member ) ) )
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
		$bandwidthChart = new \IPS\Helpers\Chart\Database( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=editBlock&block=IPS\\gallery\\extensions\\core\\MemberACPProfileContentTab\\Gallery&id={$this->member->member_id}&chart=downloads&_graph=1" ), 'gallery_bandwidth', 'bdate', '', array( 'vAxis' => array( 'title' => \IPS\Member::loggedIn()->language()->addToStack( 'filesize_raw_k' ) ) ), 'LineChart', 'daily' );
		$bandwidthChart->groupBy = 'bdate';
		$bandwidthChart->where[] = array( 'member_id=?', $this->member->member_id );
		$bandwidthChart->addSeries( \IPS\Member::loggedIn()->language()->addToStack('bandwidth_use_gallery'), 'number', 'ROUND((SUM(bsize)/1024),2)', FALSE );
		return ( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->_graph ) ) ? (string) $bandwidthChart : \IPS\Theme::i()->getTemplate( 'stats', 'gallery' )->graphs( (string) $bandwidthChart );
	}
}