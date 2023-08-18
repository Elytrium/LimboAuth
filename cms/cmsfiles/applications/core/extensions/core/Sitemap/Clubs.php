<?php
/**
 * @brief		Support Clubs in sitemaps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		01 Nov 2022
 */

namespace IPS\core\extensions\core\Sitemap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Support Clubs in sitemaps
 */
class _Clubs
{
	/**
	 * @brief	Recommended Settings
	 */
	public $recommendedSettings = array(
        'sitemap_club_include' => true,
        'sitemap_club_count' => -1,
        'sitemap_club_priority' => 1
    );

	/**
	 * Add settings for ACP configuration to the form
	 *
	 * @return	array
	 */
	public function settings(): array
	{
        $settings = \IPS\Settings::i()->sitemap_club_settings ? json_decode( \IPS\Settings::i()->sitemap_club_settings, TRUE ) : array();
        $return = array();

        $countToInclude = $settings["sitemap_club_count"] ?? $this->recommendedSettings["sitemap_club_count"];
        $return["sitemap_club_include"] = new \IPS\Helpers\Form\YesNo( "sitemap_club_include", $countToInclude != 0, FALSE, array( 'togglesOn' => array( "sitemap_club_count", "sitemap_club_priority" ) ), NULL, NULL, NULL, "sitemap_club_include" );
        $return["sitemap_club_include"]->label = \IPS\Member::loggedIn()->language()->addToStack( 'sitemap_include_generic_desc' );
        $return["sitemap_club_count"]	 = new \IPS\Helpers\Form\Number( "sitemap_club_count", $countToInclude, FALSE, array( 'min' => '-1', 'unlimited' => '-1' ), NULL, NULL, NULL, "sitemap_club_count" );
        $return["sitemap_club_count"]->label	= \IPS\Member::loggedIn()->language()->addToStack( 'sitemap_number_generic' );
        $return["sitemap_club_priority"] = new \IPS\Helpers\Form\Select( "sitemap_club_priority", $settings["sitemap_club_priority"] ?? $this->recommendedSettings["sitemap_club_priority"], FALSE, array( 'options' => \IPS\Sitemap::$priorities, 'unlimited' => '-1', 'unlimitedLang' => 'sitemap_dont_include' ), NULL, NULL, NULL, "sitemap_club_priority" );
        $return["sitemap_club_priority"]->label	= \IPS\Member::loggedIn()->language()->addToStack( 'sitemap_priority_generic' );
        
	    return $return;
	}

	/**
	 * Save settings for ACP configuration
	 *
	 * @param	array	$values	Values
	 * @return	void
	 */
	public function saveSettings( array $values )
	{
		if ( $values['sitemap_configuration_info'] )
		{
			// Store default  values for any settings you define
			\IPS\Settings::i()->changeValues( array( 'sitemap_club_settings' => json_encode( array() ) ) );
		}
		else
		{
			// Store the actual submitted value for any settings you define, from the $values array
			$settings = \IPS\Settings::i()->sitemap_club_settings ? json_decode( \IPS\Settings::i()->sitemap_club_settings, TRUE ) : array();
			$settings['sitemap_club_count'] = $values['sitemap_club_include'] ? $values['sitemap_club_count'] : 0;
			$settings['sitemap_club_priority'] = $values['sitemap_club_priority'];
            \IPS\Settings::i()->changeValues( array( 'sitemap_club_settings' => json_encode( $settings ) ) );
		}
	}

	/**
	 * Get the sitemap filename(s)
	 *
	 * @return	array
	 */
	public function getFilenames(): array
	{
        $settings = \IPS\Settings::i()->sitemap_club_settings ? json_decode( \IPS\Settings::i()->sitemap_club_settings, TRUE ) : array();
        $limit = $settings['sitemap_club_count'] ?? -1;
        if( $limit == 0 )
        {
            return array();
        }

		$visibleClubs = iterator_to_array(
			\IPS\Db::i()->select( 'id', 'core_clubs', array( '`type` != ? and approved=?', \IPS\Member\Club::TYPE_PRIVATE, 1 ) )
		);
		$count = \count( $visibleClubs );
        $files  = array();
        $count = ceil( $count / \IPS\SITEMAP_MAX_PER_FILE );

        for( $i=1; $i <= $count; $i++ )
        {
            $files[] = 'sitemap_clubs_' . $i;
        }

		/* Skip if we have no visible clubs */
		foreach( $visibleClubs as $clubId )
		{
			$pagesCount = (int)\IPS\Db::i()->select( 'count(page_id)', 'core_club_pages', array( 'page_club=? and page_meta_index=?', $clubId, 1 ) )->first();
			$pagesCount = ceil( $pagesCount / \IPS\SITEMAP_MAX_PER_FILE );
			for( $i=1; $i <= $pagesCount; $i++ )
			{
				$files[] =  $clubId . '_sitemap_clubs_pages_' . $i;
			}
		}

        return $files;
	}

	/**
	 * Generate the sitemap
	 *
	 * @param	string			$filename	The sitemap file to build (should be one returned from getFilenames())
	 * @param	\IPS\Sitemap	$sitemap	Sitemap object reference
	 * @return	mixed
	 */
	public function generateSitemap( string $filename, \IPS\Sitemap $sitemap ): mixed
	{
        $entries	= array();
        $lastId		= 0;
        $settings = \IPS\Settings::i()->sitemap_club_settings ? json_decode( \IPS\Settings::i()->sitemap_club_settings, TRUE ) : array();

        $exploded	= explode( '_', $filename );
        $block		= (int) array_pop( $exploded );
        $totalLimit = $settings["sitemap_club_count"] ?? -1;
        $offset		= ( $block - 1 ) * \IPS\SITEMAP_MAX_PER_FILE;
        $limit		= \IPS\SITEMAP_MAX_PER_FILE;

        if ( ! $totalLimit )
        {
            return NULL;
        }

        if ( $totalLimit > -1 and ( $offset + $limit ) > $totalLimit )
        {
            $limit = $totalLimit - $offset;
        }

        /* Create limit clause */
        $limitClause	= array( $offset, $limit );

		if( \strpos( $filename, '_pages_' ) !== false )
		{
			$tmp = explode( '_', $filename );
			$clubId = \intval( array_shift( $tmp ) );
			$where = array(
				array( 'page_club=?', $clubId ),
				array( 'page_meta_index=?', 1 )
			);

			try
			{
				$lastId = \IPS\Db::i()->select( 'last_id', 'core_sitemap', array( array( 'sitemap=?', implode( '_', $exploded ) . '_' . ( $block - 1 ) ) ) )->first();
				if( $lastId > 0 )
				{
					$where[] = array( 'id > ?', $lastId );
					$limitClause	= $limit;
				}
			}
			catch( \UnderflowException $e ){}

			foreach( new \IPS\Patterns\ActiveRecordIterator(
						 \IPS\Db::i()->select( '*', 'core_club_pages', $where, 'page_id', $limitClause ),
						 'IPS\Member\Club\Page'
					 ) as $page )
			{
				if( !$page->canView( new \IPS\Member ) )
				{
					continue;
				}

				$data = array(
					'url' => (string)$page->url()
				);

				$priority = $settings['sitemap_club_priority'] ?? 1;
				if ( $priority !== -1 )
				{
					$data['priority'] = $priority;
				}

				$entries[] = $data;

				$lastId = $page->id;
			}
		}
		else
		{
			$where = array(
				array( '`type` != ?', \IPS\Member\Club::TYPE_PRIVATE ),
				array( 'approved=?', 1 )
			);

			/* Try to fetch the highest ID built in the last sitemap, if it exists */
			try
			{
				$lastId = \IPS\Db::i()->select( 'last_id', 'core_sitemap', array( array( 'sitemap=?', implode( '_', $exploded ) . '_' . ( $block - 1 ) ) ) )->first();

				if( $lastId > 0 )
				{
					$where[] = array( 'id > ?', $lastId );
					$limitClause	= $limit;
				}
			}
			catch( \UnderflowException $e ){}

			foreach( new \IPS\Patterns\ActiveRecordIterator(
						 \IPS\Db::i()->select( '*', 'core_clubs', $where, 'id', $limitClause ),
						 'IPS\Member\Club'
					 ) as $club )
			{
				if( !$club->canView( new \IPS\Member ) )
				{
					continue;
				}

				$data = array(
					'url' => $club->url(),
					'lastmod' => $club->last_activity
				);

				$priority = $settings['sitemap_club_priority'] ?? 1;
				if ( $priority !== -1 )
				{
					$data['priority'] = $priority;
				}

				$entries[] = $data;

				$lastId = $club->id;
			}
		}

        $sitemap->buildSitemapFile( $filename, $entries, $lastId );
        return $lastId;
	}

}