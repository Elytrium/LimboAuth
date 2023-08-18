<?php
/**
 * @brief		Error Logs
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		06 Aug 2013
 */

namespace IPS\core\modules\admin\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Error Logs
 */
class _errorLogs extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'system_logs_view' );
		parent::execute();
	}
	
	/**
	 * Get table
	 *
	 * @param	\IPS\Http\Url	$url	The URL where the table will be displayed
	 * @return	\IPS\Helpers\Table\Db
	 */
	public static function table( \IPS\Http\Url $url )
	{
		$table = new \IPS\Helpers\Table\Db( 'core_error_logs', $url );
		$table->langPrefix = 'errorlogs_';
		$table->include = array( 'log_error_code', 'log_error', 'log_ip_address', 'log_request_uri', 'log_member', 'log_date' );
		$table->mainColumn = 'log_error_code';
		$table->widths = array( 'log_error_code' => 10, 'log_ip_address' => 10, 'log_request_uri' => 30, 'log_date' => 10, 'log_member' => 10 );
		$table->rowClasses = array( 'log_error' => array( 'ipsTable_wrap' ), 'log_request_uri' => array( 'ipsTable_wrap' ) );
		$table->parsers = array(
			'log_member'	=> function( $val )
			{
				$member = \IPS\Member::load( $val );

				if( $member->member_id )
				{
					return htmlentities( $member->name, ENT_DISALLOWED, 'UTF-8', FALSE );
				}
				else
				{
					return '';
				}
			},
			'log_ip_address'=> function( $val )
			{
				if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'membertools_ip' ) )
				{
					return "<a href='" . \IPS\Http\Url::internal( "app=core&module=members&controller=ip&ip={$val}" ) . "'>{$val}</a>";
				}
				return $val;
			},
			'log_date'		=> function( $val )
			{
				return (string) \IPS\DateTime::ts( $val );
			},
			'log_error'		=> function( $val )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( $val );
			},
			'log_request_uri'	=> function( $val )
			{
				return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->truncatedUrl( $val );
			}
		);
		$table->sortBy = $table->sortBy ?: 'log_date';
		$table->sortDirection = $table->sortDirection ?: 'desc';
		
		$table->advancedSearch = array(
			'log_member'			=> \IPS\Helpers\Table\SEARCH_MEMBER,
			'log_ip_address'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'log_date'				=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'log_error'				=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'log_error_code'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'log_request_uri'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
		);
		$table->quickSearch = 'log_error';
		
		return $table;
	}

	/**
	 * Manage Error Logs
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'support', 'diagnostic_log_settings' ) )
		{
			\IPS\Output::i()->sidebar['actions'] = array(
				'settings'	=> array(
					'title'		=> 'settings',
					'icon'		=> 'cog',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=support&controller=errorLogs&do=settings' ),
					'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('settings') )
				)
			);
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('errorlogs');
		\IPS\Output::i()->output	= (string) static::table( \IPS\Http\Url::internal( 'app=core&module=support&controller=errorLogs' ) );
	}
	
	/**
	 * Settings
	 *
	 * @return	void
	 */
	protected function settings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'diagnostic_log_settings' );
		
		$levelOptions = array(
			'0' => 'level_number_0',
			'1'	=> 'level_number_1',
			'2'	=> 'level_number_2',
			'3'	=> 'level_number_3',
			'4'	=> 'level_number_4',
			'5'	=> 'level_number_5',
		);
		
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Radio( 'error_log_level', \IPS\Settings::i()->error_log_level, FALSE, array( 'options' => $levelOptions ) ) );
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'error_prune' ) )
		{
			$form->add( new \IPS\Helpers\Form\Interval( 'prune_log_error', \IPS\Settings::i()->prune_log_error, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'prune_log_error' ) );
		}
		
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplog__errorlog_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=support&controller=errorLogs' ), 'saved' );
		}
	
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('errorlogssettings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'errorlogssettings', $form, FALSE );
	}

}