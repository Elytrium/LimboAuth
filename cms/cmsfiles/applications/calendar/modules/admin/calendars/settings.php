<?php
/**
 * @brief		Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		18 Dec 2013
 */

namespace IPS\calendar\modules\admin\calendars;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Settings
 */
class _settings extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'settings_manage' );
		parent::execute();
	}

	/**
	 * Manage Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('settings');

		$form = new \IPS\Helpers\Form;

		$form->add( new \IPS\Helpers\Form\Radio( 'calendar_default_view', \IPS\Settings::i()->calendar_default_view, TRUE, array( 'options' => array( 'overview' => 'cal_df_overview', 'month' => 'cal_df_month', 'week' => 'cal_df_week', 'day' => 'cal_df_day' ) ) ) );

		$options	= array_combine( array_keys( \IPS\calendar\Date::$dateFormats ), array_map( function( $val ){ return "calendar_df_" . $val; }, array_keys( \IPS\calendar\Date::$dateFormats ) ) );
		$form->add( new \IPS\Helpers\Form\Select( 'calendar_date_format', \IPS\Settings::i()->calendar_date_format, TRUE, array( 'options' => $options, 'unlimited' => '-1', 'unlimitedLang' => "calendar_custom_df", 'unlimitedToggles' => array( 'calendar_date_format_custom' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'calendar_date_format_custom', \IPS\Settings::i()->calendar_date_format_custom, FALSE, array(), NULL, NULL, NULL, 'calendar_date_format_custom' ) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'ipb_calendar_mon', \IPS\Settings::i()->ipb_calendar_mon ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'calendar_rss_feed', \IPS\Settings::i()->calendar_rss_feed, FALSE, array( 'togglesOn' => array( 'calendar_rss_feed_days', 'calendar_rss_feed_order' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Number( 'calendar_rss_feed_days', \IPS\Settings::i()->calendar_rss_feed_days, FALSE, array( 'unlimited' => -1 ), NULL, NULL, NULL, 'calendar_rss_feed_days' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'calendar_rss_feed_order', \IPS\Settings::i()->calendar_rss_feed_order, FALSE, array( 'options' => array(
			0 => 'calendar_rss_feed_order_date',
			1 => 'calendar_rss_feed_order_publish'
		) ), NULL, NULL, NULL, 'calendar_rss_feed_order' ) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'calendar_venues_enabled', \IPS\Settings::i()->calendar_venues_enabled ) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'calendar_block_past_changes', \IPS\Settings::i()->calendar_block_past_changes ) );

		if( \IPS\GeoLocation::enabled() )
		{
			$form->add( new \IPS\Helpers\Form\Number( 'map_center_lat', \IPS\Settings::i()->map_center_lat, FALSE, array( 'decimals' => 8, 'min' => "-180", 'max' => "180" ), NULL, NULL, NULL, 'map_center_lat' ) );
			$form->add( new \IPS\Helpers\Form\Number( 'map_center_lon', \IPS\Settings::i()->map_center_lon, FALSE, array( 'decimals' => 8, 'min' => "-180", 'max' => "180" ), NULL, NULL, NULL, 'map_center_lon' ) );
		}

		if ( $values = $form->values() )
		{
			if( $values['calendar_date_format'] == -1 AND !$values['calendar_date_format_custom'] )
			{
				$form->error	= \IPS\Member::loggedIn()->language()->addToStack('calendar_no_date_format');
				\IPS\Output::i()->output = $form;
				return;
			}

			$form->saveAsSettings();

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			\IPS\Session::i()->log( 'acplogs__calendar_settings' );
		}

		\IPS\Output::i()->output = $form;
	}
}