<?php
/**
 * @brief		Advertisements
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		24 Sep 2013
 */

namespace IPS\core\modules\admin\promotion;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Advertisements
 */
class _advertisements extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;

	/**
	 * @brief	Active tab
	 */
	protected $activeTab	= '';

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'advertisements_manage' );

		/* Get tab content */
		$this->activeTab = \IPS\Request::i()->tab ?: 'site';

		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Work out output */
		$thisUrl = \IPS\Http\Url::internal( 'app=core&module=promotion&controller=advertisements' );
		$activeTabContents = (string) static::table( $thisUrl, ( $this->activeTab == 'emails' ) );
		
		/* If this is an AJAX request, just return it */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}

		/* Action Buttons */
		\IPS\Output::i()->sidebar['actions']['settings'] = array(
			'title'		=> 'ad_settings',
			'icon'		=> 'cog',
			'link'		=> \IPS\Http\Url::internal( 'app=core&module=promotion&controller=advertisements&do=settings' ),
			'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('ad_settings') )
		);
		
		/* If Nexus is not installed, show message */
		if( !\IPS\Application::appIsEnabled( 'nexus' ) AND !\IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'promotion' )->advertisementMessage( );
		}

		/* Display */
		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'promotion/advertisements.css', 'core', 'admin' ) );
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('advertisements');

		/* Build tab list */
		$tabs = array( 'site' => 'advertisements_site', 'emails' => 'advertisements_emails' );
		\IPS\Output::i()->output	.= \IPS\Theme::i()->getTemplate( 'global' )->tabs( $tabs, $this->activeTab, $activeTabContents, $thisUrl );
	}
	
	/**
	 * Get table
	 *
	 * @param	\IPS\Http\Url	$url	The URL the table will be shown on
	 * @param	bool			$email	Email advertisements (true) or website (false)
	 * @return	void
	 */
	public static function table( \IPS\Http\Url $url, $email=FALSE )
	{
		/* Create the table */
		$where = NULL;

		if( $email === TRUE )
		{
			$where = array( array( 'ad_type=?', \IPS\core\Advertisement::AD_EMAIL ) );
			$url = $url->setQueryString( 'tab', 'emails' );

			\IPS\Member::loggedIn()->language()->words['ads_ad_impressions'] = \IPS\Member::loggedIn()->language()->addToStack('ads_ad_sends');
		}
		else
		{
			$where = array( array( 'ad_type!=?', \IPS\core\Advertisement::AD_EMAIL ) );
			$url = $url->setQueryString( 'tab', 'site' );
		}

		$table = new \IPS\Helpers\Table\Db( 'core_advertisements', $url, $where );
		$table->langPrefix = 'ads_';
		$table->rowClasses = array( 'ad_html' => array( 'ipsTable_wrap' ) );

		/* Columns we need */
		$table->include = array( 'word_custom', 'ad_html', 'ad_impressions' );

		if( $email === TRUE )
		{
			$table->include[] = 'ad_email_views';
			$table->include[] = 'ad_clicks';
			$table->include[] = 'ad_active';
		}
		else
		{
			$table->include[] = 'ad_clicks';
			$table->include[] = 'ad_active';
		}
		$table->mainColumn = 'word_custom';
		$table->noSort	= array( 'ad_images', 'ad_html' );
		$table->joins = array(
			array( 'select' => 'w.word_custom', 'from' => array( 'core_sys_lang_words', 'w' ), 'where' => "w.word_key=CONCAT( 'core_advert_', core_advertisements.ad_id ) AND w.lang_id=" . \IPS\Member::loggedIn()->language()->id )
		);

		/* Default sort options */
		$table->sortBy = $table->sortBy ?: 'ad_start';
		$table->sortDirection = $table->sortDirection ?: 'desc';

		$table->quickSearch = 'word_custom';
		$table->advancedSearch = array(
			'ad_html'			=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'ad_start'			=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'ad_end'			=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'ad_impressions'	=> \IPS\Helpers\Table\SEARCH_NUMERIC,
			'ad_clicks'			=> \IPS\Helpers\Table\SEARCH_NUMERIC,
			);

		/* Filters */
		$table->filters = array(
			'ad_filters_active'				=> array( 'ad_active=1 AND ( ad_end=0 OR ad_end > ? )', time() ),
			'ad_filters_inactive'			=> array( '(ad_active=0 OR (ad_end>0 AND ad_end<?))', time() ),
		);

		/* If Nexus is installed, we get the pending filter too */
		if( \IPS\Application::appIsEnabled( 'nexus' ) AND $email === FALSE )
		{
			$table->filters['ad_filters_pending']	= 'ad_active=-1';
		}
		
		/* Custom parsers */
		$table->parsers = array(
            'word_custom'			=> function( $val, $row )
            {
                return \IPS\Member::loggedIn()->language()->checkKeyExists( "core_advert_{$row['ad_id']}" ) ? \IPS\Member::loggedIn()->language()->addToStack( "core_advert_{$row['ad_id']}" ) : \IPS\Theme::i()->getTemplate( 'global' )->shortMessage( 'ad_title_none', array( 'ipsType_light' ) );
            },
			'ad_html'			=> function( $val, $row )
			{
				if( $row['ad_type'] == \IPS\core\Advertisement::AD_HTML )
				{
					$preview	= \IPS\Theme::i()->getTemplate( 'promotion' )->advertisementIframePreview( $row['ad_id'] );
				}
				else
				{
					$advert = \IPS\core\Advertisement::constructFromData( $row );

					if( !\count( $advert->_images ) )
					{
						return '';
					}
					
					$preview	= \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->advertisementImage( $advert, \IPS\Http\Url::external( $advert->link ) );
				}

				return $preview;
			},
			'ad_active'			=> function( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate( 'promotion' )->activeBadge( $row['ad_id'], ( $val == -1 ) ? 'ad_filters_pending' : ( ( $val == 0 ) ? 'ad_filters_inactive' : 'ad_filters_active' ), $val, $row );
			},
			'ad_clicks'			=> function( $val, $row )
			{
				return $row['ad_html'] ? \IPS\Theme::i()->getTemplate( 'global' )->shortMessage( 'unavailable', array( 'ipsType_light' ) ) : $val;
			},
			'ad_impressions'	=> function( $val, $row )
			{
				return $val;
			},
			'ad_email_views'	=> function( $val, $row )
			{
				return \intval( $val );
			},
		);
		
		/* Specify the buttons */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'promotion', 'advertisements_add' ) )
		{
			$table->rootButtons = array(
				'add'	=> array(
					'icon'		=> 'plus',
					'title'		=> 'add',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=promotion&controller=advertisements&do=form&type=' . ( ( $email === TRUE ) ? 'emails' : 'site' ) )
				)
			);
		}

		$table->rowButtons = function( $row )
		{
			$return = array();

			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'promotion', 'advertisements_edit' ) )
			{
				if ( $row['ad_active'] == -1 )
				{
					$return['approve'] = array(
						'icon'		=> 'check',
						'title'		=> 'approve',
						'link'		=> \IPS\Http\Url::internal( 'app=core&module=promotion&controller=advertisements&do=toggle&status=1&id=' . $row['ad_id'] )->csrf(),
						'hotkey'	=> 'a',
					);
				}
				
				$return['edit'] = array(
					'icon'		=> 'pencil',
					'title'		=> 'edit',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=promotion&controller=advertisements&do=form&id=' . $row['ad_id'] ),
					'hotkey'	=> 'e',
				);
			}

			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'promotion', 'advertisements_delete' ) )
			{
				$return['delete'] = array(
					'icon'		=> 'times-circle',
					'title'		=> 'delete',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=promotion&controller=advertisements&do=delete&id=' . $row['ad_id'] ),
					'data'		=> array( 'delete' => '' ),
				);
			}
			
			return $return;
		};
		
		/* Return */
		return $table;
	}

	/**
	 * Advertisement settings
	 *
	 * @return	void
	 */
	protected function settings()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Select( 'ads_circulation', \IPS\Settings::i()->ads_circulation, TRUE, array( 'options' => array( 'random' => 'ad_circ_random', 'newest' => 'ad_circ_newest', 'oldest' => 'ad_circ_oldest', 'least' => 'ad_circ_leasti' ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'ads_force_sidebar', \IPS\Settings::i()->ads_force_sidebar, TRUE ) );
		
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			\IPS\Session::i()->log( 'acplog_ad_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=promotion&controller=advertisements' ), 'saved' );
		}
	
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('ad_settings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'ad_settings', $form, FALSE );
	}

	/**
	 * Delete an advertisement
	 *
	 * @return	void
	 */
	protected function delete()
	{
		/* Permission check */
		\IPS\Dispatcher::i()->checkAcpPermission( 'advertisements_delete' );

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		/* Get our record */
		try
		{
			$record	= \IPS\core\Advertisement::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C157/2', 404, '' );
		}

		/* Delete the record */
		$record->delete();

        \IPS\Lang::deleteCustom( 'core', 'advert_' . $record->id );

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();

		/* Log and redirect */
		\IPS\Session::i()->log( 'acplog_ad_deleted', array() );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=promotion&controller=advertisements' ), 'deleted' );
	}

	/**
	 * Toggle an advertisement state to active or inactive
	 *
	 * @note	This also takes care of approving a pending advertisement
	 * @return	void
	 */
	protected function toggle()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Get our record */
		try
		{
			$record	= \IPS\core\Advertisement::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C157/5', 404, '' );
		}

		/* Toggle the record */
		$record->active	= (int) \IPS\Request::i()->status;
		$record->save();
		
		/* Reset ads_exist setting */
		$adsExist = (bool) \IPS\Db::i()->select( 'COUNT(*)', 'core_advertisements', 'ad_active=1' )->first();
		if ( $adsExist != \IPS\Settings::i()->ads_exist )
		{
			\IPS\Settings::i()->changeValues( array( 'ads_exist' => $adsExist ) );
		}

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();

		/* Log and redirect */
		if( $record->active == -1 )
		{
			\IPS\Session::i()->log( 'acplog_ad_approved', array() );
		}
		else if( $record->active == 1 )
		{
			\IPS\Session::i()->log( 'acplog_ad_enabled', array() );
		}
		else
		{
			\IPS\Session::i()->log( 'acplog_ad_disabled', array() );
		}

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=promotion&controller=advertisements' )->setQueryString( 'filter', \IPS\Request::i()->filter ), \IPS\Request::i()->status ? 'ad_toggled_visible' : 'ad_toggled_notvisible' );
		}
	}

	/**
	 * Add/edit an advertisement
	 *
	 * @return	void
	 */
	protected function form()
	{
		/* Are we editing? */
		if( isset( \IPS\Request::i()->id ) )
		{
			/* Permission check */
			\IPS\Dispatcher::i()->checkAcpPermission( 'advertisements_edit' );

			try
			{
				$record	= \IPS\core\Advertisement::load( \IPS\Request::i()->id );

				if( $record->type == \IPS\core\Advertisement::AD_EMAIL )
				{
					\IPS\Request::i()->type = 'emails';
					\IPS\Member::loggedIn()->language()->words['ad_impressions'] = \IPS\Member::loggedIn()->language()->addToStack('ad_sends_so_far');
				}
				else
				{
					\IPS\Request::i()->type = 'site';
				}
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2C157/1', 404, '' );
			}
		}
		else
		{
			/* Permission check */
			\IPS\Dispatcher::i()->checkAcpPermission( 'advertisements_add' );

			$record = new \IPS\core\Advertisement;
		}

		/* Start the form */
		$form	= new \IPS\Helpers\Form;
        $form->add( new \IPS\Helpers\Form\Translatable( 'ad_title', NULL, TRUE, array( 'app' => 'core', 'key' => ( !$record->id ) ? NULL : "core_advert_{$record->id}" ) ) );

        if( \IPS\Request::i()->type != 'emails' )
        {
       		$form->add( new \IPS\Helpers\Form\Radio( 'ad_type', ( $record->id ) ? $record->type : \IPS\core\Advertisement::AD_HTML, TRUE, array( 'options' => array( 1 => 'ad_type_html', 2 => 'ad_type_image' ), 'toggles' => array( \IPS\core\Advertisement::AD_HTML => array( 'ad_html', 'ad_html_specify_https', 'ad_maximums_html', 'ad_location', 'ad_exempt' ), \IPS\core\Advertisement::AD_IMAGES => array( 'ad_url', 'ad_image_alt', 'ad_new_window', 'ad_image', 'ad_image_more', 'ad_clicks', 'ad_maximums_image', 'ad_location', 'ad_exempt' ) ) ), NULL, NULL, NULL, 'ad_type' ) );

			/* Show the fields for an HTML advertisement */
			$form->add( new \IPS\Helpers\Form\Codemirror( 'ad_html', ( $record->id ) ? $record->html : NULL, NULL, array(), function( $val ) {
				if( \IPS\Request::i()->ad_type == 1 AND !$val ) {
					throw new \DomainException('form_required');
				}
			}, NULL, NULL, 'ad_html' ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'ad_html_specify_https', ( $record->id ) ? $record->html_https_set : FALSE, FALSE, array( 'togglesOn' => array( 'ad_html_https' ) ), NULL, NULL, NULL, 'ad_html_specify_https' ) );
			$form->add( new \IPS\Helpers\Form\Codemirror( 'ad_html_https', ( $record->id ) ? $record->html_https : NULL, FALSE, array(), NULL, NULL, NULL, 'ad_html_https' ) );
		}

		/* Show the fields for an image advertisement (and most of the email ad settings as well) */
		$form->add( new \IPS\Helpers\Form\Url( 'ad_url', ( $record->id ) ? $record->link : NULL, FALSE, array(), NULL, NULL, NULL, 'ad_url' ) );

		if( \IPS\Request::i()->type != 'emails' )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'ad_new_window', ( $record->id ) ? $record->new_window : FALSE, FALSE, array(), NULL, NULL, NULL, 'ad_new_window' ) );
		}

		$form->add( new \IPS\Helpers\Form\Upload( 'ad_image', ( $record->id ) ? ( ( isset( $record->_images['large'] ) and $record->_images['large'] ) ? \IPS\File::get( 'core_Advertisements', $record->_images['large'] ) : NULL ) : NULL, FALSE, array( 'image' => TRUE, 'storageExtension' => 'core_Advertisements' ), NULL, NULL, NULL, 'ad_image' ) );

		if( \IPS\Request::i()->type != 'emails' )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'ad_image_more', ( $record->id ) ? ( ( ( isset( $record->_images['medium'] ) and $record->_images['medium'] ) OR ( ( isset( $record->_images['small'] ) and $record->_images['small']  ) ) ) ? TRUE : FALSE ) : FALSE, FALSE, array( 'togglesOn' => array( 'ad_image_small', 'ad_image_medium' ) ), NULL, NULL, NULL, 'ad_image_more' ) );
			$form->add( new \IPS\Helpers\Form\Upload( 'ad_image_small', ( $record->id ) ? ( ( isset( $record->_images['small'] ) and $record->_images['small']  ) ? \IPS\File::get( 'core_Advertisements', $record->_images['small'] ) : NULL ) : NULL, FALSE, array( 'image' => TRUE, 'storageExtension' => 'core_Advertisements' ), NULL, NULL, NULL, 'ad_image_small' ) );
			$form->add( new \IPS\Helpers\Form\Upload( 'ad_image_medium', ( $record->id ) ? ( ( isset( $record->_images['medium'] ) and $record->_images['medium']  ) ? \IPS\File::get( 'core_Advertisements', $record->_images['medium'] ) : NULL ) : NULL, FALSE, array( 'image' => TRUE, 'storageExtension' => 'core_Advertisements' ), NULL, NULL, NULL, 'ad_image_medium' ) );
		}

		$form->add( new \IPS\Helpers\Form\Text( 'ad_image_alt', ( $record->id ) ? $record->image_alt : NULL, FALSE, array(), NULL, NULL, NULL, 'ad_image_alt' ) );

		if( \IPS\Request::i()->type != 'emails' )
		{
			/* Add the location fields, remember to call extensions for additional locations.
				Array format: location => array of toggle fields to show */
			$defaultLocations	= array(
				'ad_global_header'	=> array(),
				'ad_global_footer'	=> array(),
				'ad_sidebar'		=> array(),
			);

			$currentValues	= ( $record->id ) ? json_decode( $record->additional_settings, TRUE ) : array();

			/* Now grab ad location extensions */
			foreach ( \IPS\Application::allExtensions( 'core', 'AdvertisementLocations', FALSE, 'core' ) as $key => $extension )
			{
				$result	= $extension->getSettings( $currentValues );

				$defaultLocations	= array_merge( $defaultLocations, $result['locations'] );

				if( isset( $result['settings'] ) )
				{
					foreach( $result['settings'] as $setting )
					{
						$form->add( $setting );
					}
				}
			}
			
			$defaultLocations['_ad_custom_'] = array('ad_location_custom');

			/* Add the locations to the form, and make sure the toggles get set properly */
			$locations = array();
			$customLocations = array();
			if ( $record->id )
			{
				$locations = explode( ',', $record->location );
				$customLocations = array_diff( $locations, array_keys( $defaultLocations ) );
				if ( !empty( $customLocations ) )
				{
					$locations[] = '_ad_custom_';
				}
				$locations = array_intersect( $locations, array_keys( $defaultLocations ) );
			}
			
			$form->add( new \IPS\Helpers\Form\CheckboxSet( 'ad_location',
				$locations,
				TRUE,
				array(
					'options'	=> array_combine( array_keys( $defaultLocations ), array_keys( $defaultLocations ) ),
					'toggles'	=> $defaultLocations,
				),
				NULL,
				NULL,
				NULL,
				'ad_location'
			) );
			
			$form->add( new \IPS\Helpers\Form\Stack( 'ad_location_custom', $customLocations, FALSE, array(), NULL, NULL, NULL, 'ad_location_custom' ) );

			/* Generic fields available for both html and image ads */
			$form->add( new \IPS\Helpers\Form\CheckboxSet( 'ad_exempt', ( $record->id ) ? ( ( $record->exempt == '*' ) ? '*' : json_decode( $record->exempt, TRUE ) ) : '*', FALSE, array( 'options' => \IPS\Member\Group::groups(), 'parse' => 'normal', 'multiple' => TRUE, 'unlimited' => '*', 'unlimitedLang' => 'everyone', 'impliedUnlimited' => TRUE ), NULL, NULL, NULL, 'ad_exempt' ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'ad_nocontent_page_output', ( $record->id ) ? $record->nocontent_page_output : FALSE ) );
		}
		else
		{
			/* Container restrictions for email ads */
			$containerClasses = array();
			$containerToggles = array();

			foreach( \IPS\Content::routedClasses( FALSE, FALSE, TRUE ) as $contentItemClass )
			{
				if( isset( $contentItemClass::$containerNodeClass ) AND $contentItemClass::$containerNodeClass AND !isset( $containerClasses[ $contentItemClass::$containerNodeClass ] ) )
				{
					$containerClass = $contentItemClass::$containerNodeClass;

					$containerClasses[ $containerClass ] = $contentItemClass::$title . '_pl';
					$containerToggles[ $containerClass ] = array( 'node_' . md5( $containerClass ) );
				}
			}

			if( \count( $containerClasses ) )
			{
				$form->add( new \IPS\Helpers\Form\Select( '_ad_email_container', ( $record->id AND isset( $record->_additional_settings['email_container'] ) ) ? $record->_additional_settings['email_container'] : '*', FALSE, array( 'options' => $containerClasses, 'multiple' => FALSE, 'unlimited' => '*', 'unlimitedLang' => 'unrestricted', 'toggles' => $containerToggles ), NULL, NULL, NULL, '_ad_email_container' ) );

				foreach( $containerClasses as $classname => $lang )
				{
					$form->add( new \IPS\Helpers\Form\Node( 'node_' . md5( $classname ), ( $record->id ) ? ( isset( $record->_additional_settings['email_node'] ) ? $record->_additional_settings['email_node'] : 0 ) : 0, FALSE, array( 'class' => $classname, 'zeroVal' => 'any', 'multiple' => TRUE, 'forceOwner' => FALSE ), NULL, NULL, NULL, 'node_' . md5( $classname ) ) );

					\IPS\Member::loggedIn()->language()->words[ 'node_' . md5( $classname ) ] = \IPS\Member::loggedIn()->language()->addToStack( $classname::$nodeTitle );
				}
			}
		}

		/* Generic fields for all ad types */
		$form->add( new \IPS\Helpers\Form\Date( 'ad_start', ( $record->id ) ? \IPS\DateTime::ts( $record->start ) : new \IPS\DateTime, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Date( 'ad_end', ( $record->id ) ? ( $record->end ? \IPS\DateTime::ts( $record->end ) : 0 ) : 0, FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'indefinitely' ) ) );

		/* Number of clicks, number of impressions */
		if( $record->id )
		{
			$form->add( new \IPS\Helpers\Form\Number( 'ad_impressions', ( $record->id ) ? $record->impressions : 0, FALSE, array(), NULL, NULL, NULL, 'ad_impressions' ) );
			$form->add( new \IPS\Helpers\Form\Number( 'ad_clicks', ( $record->id ) ? $record->clicks : 0, FALSE, array(), NULL, NULL, NULL, 'ad_clicks' ) );

			if( \IPS\Request::i()->type == 'emails' )
			{
				$form->add( new \IPS\Helpers\Form\Number( 'ad_email_views', ( $record->id ) ? $record->email_views : 0, FALSE, array(), NULL, NULL, NULL, 'ad_email_views' ) );
			}
		}

		/* Click/impression maximum cutoffs, toggled depending upon HTML or image type ad */
		if( \IPS\Request::i()->type != 'emails' )
		{
			$form->add( new \IPS\Helpers\Form\Number( 'ad_maximums_html', ( $record->id ) ? $record->maximum_value : -1, FALSE, array( 'unlimited' => -1 ), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('ad_max_impressions'), 'ad_maximums_html' ) );
		}

		$form->add( new \IPS\Helpers\Form\Custom( 'ad_maximums_image', array( 'value' => ( $record->id ) ? $record->maximum_value : -1, 'type' => ( $record->id ) ? $record->maximum_unit : 'i' ), FALSE, array(
			'getHtml'	=> function( $element )
			{
				return \IPS\Theme::i()->getTemplate( 'promotion', 'core' )->imageMaximums( $element->name, $element->value['value'], $element->value['type'] );
			},
			'formatValue' => function( $element )
			{
				if( !\is_array( $element->value ) AND $element->value == -1 )
				{
					return array( 'value' => -1, 'type' => 'i' );
				}

				return array( 'value' => $element->value['value'], 'type' => $element->value['type'] );
			}
		), NULL, NULL, NULL, 'ad_maximums_image' ) );

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			if( \IPS\Request::i()->type != 'emails' )
			{
				$locations = $values['ad_location'];
				$customKey = array_search( '_ad_custom_', $locations );
				if ( $customKey !== FALSE )
				{
					unset( $locations[ $customKey ] );
					$locations = array_merge( $locations, $values['ad_location_custom'] );
				}
			}
			else
			{
				$locations = array();
				$values['ad_type'] = \IPS\core\Advertisement::AD_EMAIL;
			}
			
			/* Let us start with the easy stuff... */
			$record->type					= $values['ad_type'];
			$record->location				= implode( ',', $locations );
			$record->html					= ( $values['ad_type'] == \IPS\core\Advertisement::AD_HTML ) ? $values['ad_html'] : NULL;
			$record->link					= ( $values['ad_type'] != \IPS\core\Advertisement::AD_HTML ) ? $values['ad_url'] : NULL;
			$record->image_alt				= ( $values['ad_type'] != \IPS\core\Advertisement::AD_HTML ) ? $values['ad_image_alt'] : NULL;
			$record->new_window				= ( $values['ad_type'] == \IPS\core\Advertisement::AD_IMAGES ) ? $values['ad_new_window'] : 0;
			$record->impressions			= ( isset( $values['ad_impressions'] ) ) ? $values['ad_impressions'] : 0;
			$record->clicks					= ( $values['ad_type'] != \IPS\core\Advertisement::AD_HTML AND isset( $values['ad_clicks'] ) ) ? $values['ad_clicks'] : 0;
			$record->email_views			= ( $values['ad_type'] == \IPS\core\Advertisement::AD_EMAIL AND isset( $values['ad_email_views'] ) ) ? $values['ad_email_views'] : NULL;
			$record->active					= ( $record->id ) ? $record->active : 1;
			$record->html_https				= ( $values['ad_type'] == \IPS\core\Advertisement::AD_HTML ) ? $values['ad_html_https'] : NULL;
			$record->start					= $values['ad_start'] ? $values['ad_start']->getTimestamp() : 0;
			$record->end					= $values['ad_end'] ? $values['ad_end']->getTimestamp() : 0;
			$record->exempt					= ( $values['ad_type'] != \IPS\core\Advertisement::AD_EMAIL ) ? ( $values['ad_exempt'] == '*' ) ? '*' : json_encode( $values['ad_exempt'] ) : NULL;
			$record->images					= NULL;
			$record->maximum_value			= ( $values['ad_type'] == \IPS\core\Advertisement::AD_HTML ) ? $values['ad_maximums_html'] : $values['ad_maximums_image']['value'];
			$record->maximum_unit			= ( $values['ad_type'] == \IPS\core\Advertisement::AD_HTML ) ? 'i' : $values['ad_maximums_image']['type'];
			$record->additional_settings	= NULL;
			$record->html_https_set			= ( $values['ad_type'] == \IPS\core\Advertisement::AD_HTML ) ? $values['ad_html_specify_https'] : 0;
			$record->nocontent_page_output = isset( $values['ad_nocontent_page_output'] ) ? $values['ad_nocontent_page_output'] : FALSE;

			/* Figure out the ad_images */
			$images	= array();

			if( $values['ad_type'] == \IPS\core\Advertisement::AD_IMAGES )
			{
				$images = array( 'large' => (string) $values['ad_image'] );

				if( $values['ad_image_more'] and isset( $values['ad_image_small'] ) AND $values['ad_image_small'] )
				{
					$images['small']	= (string) $values['ad_image_small'];
				}

				if( $values['ad_image_more'] and isset( $values['ad_image_medium'] ) AND $values['ad_image_medium'] )
				{
					$images['medium']	= (string) $values['ad_image_medium'];
				}

				/* If there are images, but we disabled additional images, remove them */
				if( !$values['ad_image_more'] and isset( $values['ad_image_small'] ) AND $values['ad_image_small'] )
				{
					$values['ad_image_small']->delete();
				}

				if( !$values['ad_image_more'] and isset( $values['ad_image_medium'] ) AND $values['ad_image_medium'] )
				{
					$values['ad_image_medium']->delete();
				}
			}
			elseif( $values['ad_type'] == \IPS\core\Advertisement::AD_EMAIL )
			{
				$images = array( 'large' => (string) $values['ad_image'] );

				/* Make sure we don't retain any small/medium copies if they switched to images then to email */
				if( isset( $values['ad_image_small'] ) AND $values['ad_image_small'] )
				{
					$values['ad_image_small']->delete();
				}

				if( isset( $values['ad_image_medium'] ) AND $values['ad_image_medium'] )
				{
					$values['ad_image_medium']->delete();
				}
			}
			elseif( $values['ad_type'] == \IPS\core\Advertisement::AD_HTML )
			{
				/* Did they upload images and then switch back to an html type ad, by chance? */
				if( isset( $values['ad_image'] ) AND $values['ad_image'] )
				{
					$values['ad_image']->delete();
				}

				if( isset( $values['ad_image_small'] ) AND $values['ad_image_small'] )
				{
					$values['ad_image_small']->delete();
				}

				if( isset( $values['ad_image_medium'] ) AND $values['ad_image_medium'] )
				{
					$values['ad_image_medium']->delete();
				}
			}

			/* If we are editing, and we changed from image/email -> html/email, clean up old images */
			if( $record->id AND \count( $record->_images ) AND ( $values['ad_type'] == \IPS\core\Advertisement::AD_HTML OR $values['ad_type'] == \IPS\core\Advertisement::AD_EMAIL ) )
			{
				if( $values['ad_type'] == \IPS\core\Advertisement::AD_HTML )
				{
					\IPS\File::get( 'core_Advertisements', $record->_images['large'] )->delete();
				}

				if( isset( $record->_images['small'] ) )
				{
					\IPS\File::get( 'core_Advertisements', $record->_images['small'] )->delete();
				}

				if( isset( $record->_images['medium'] ) )
				{
					\IPS\File::get( 'core_Advertisements', $record->_images['medium'] )->delete();
				}
			}

			$record->images	= json_encode( $images );

			/* Any additional settings to save? */
			$additionalSettings	= array();

			if( $values['ad_type'] == \IPS\core\Advertisement::AD_EMAIL )
			{
				$additionalSettings['email_container']	= $values['_ad_email_container'];
				$additionalSettings['email_node']		= ( $values['_ad_email_container'] != '*' ) ? ( ( $values['node_' . md5( $values['_ad_email_container'] ) ] == 0 ) ? 0 : array_keys( $values['node_' . md5( $values['_ad_email_container'] ) ] ) ) : 0;
			}
			else
			{
				foreach ( \IPS\Application::allExtensions( 'core', 'AdvertisementLocations', FALSE, 'core' ) as $key => $extension )
				{
					$settings	= $extension->parseSettings( $values );

					$additionalSettings	= array_merge( $additionalSettings, $settings );
				}
			}

			$record->additional_settings	= json_encode( $additionalSettings );

			/* Insert or update */
			if( $record->id )
			{
				\IPS\Session::i()->log( 'acplog_ad_edited', array() );
			}
			else
			{
				\IPS\Session::i()->log( 'acplog_ad_added', array() );
			}
			$record->save();
			
			/* Save if any exist */
			$adsExist = $record->active ? TRUE : (bool) \IPS\Db::i()->select( 'COUNT(*)', 'core_advertisements', 'ad_active=1' )->first();

			if ( $adsExist != \IPS\Settings::i()->ads_exist )
			{
				\IPS\Settings::i()->changeValues( array( 'ads_exist' => $adsExist ) );
			}

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

            /* Set the title */
            \IPS\Lang::saveCustom( 'core', 'core_advert_' . $record->id, $values[ 'ad_title' ] );

			/* Redirect */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=promotion&controller=advertisements&tab=' . \IPS\Request::i()->type ), 'saved' );
		}

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( ( !isset( \IPS\Request::i()->id ) ) ? 'add_advertisement' : 'edit_advertisement' );
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( ( !isset( \IPS\Request::i()->id ) ) ? 'add_advertisement' : 'edit_advertisement', $form );
	}

	/**
	 * Show advertisement HTML code
	 *
	 * @return	void
	 */
	protected function getHtml()
	{
		/* Are we editing? */
		if( \IPS\Request::i()->id )
		{
			try
			{
				$record	= \IPS\core\Advertisement::load( \IPS\Request::i()->id );
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2C157/6', 404, '' );
			}
		}
		else
		{
			\IPS\Output::i()->error( 'node_error', '2C157/7', 404, '' );
		}

		$preview = '';

		if( $record->html )
		{
			if( \IPS\Request::i()->isSecure() AND $record->html_https_set )
			{
				$preview	= $record->html_https;
			}
			else
			{
				$preview	= $record->html;
			}
		}

		$preview	= preg_replace( "/<script(?:[^>]*?)?>.*<\/script>/ims", \IPS\Theme::i()->getTemplate( 'global' )->shortMessage( 'ad_script_disabled', array( 'ipsType_light' ) ), $preview );

		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core', 'admin' )->blankTemplate( $preview ) );
	}
	
	/**
	 * Adsense Help
	 *
	 * @return	void
	 */
	protected function adsense()
	{
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('google_adsense_header');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('promotion')->adsenseHelp();
	}
}