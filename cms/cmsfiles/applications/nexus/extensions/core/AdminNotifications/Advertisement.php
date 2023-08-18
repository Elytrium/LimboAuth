<?php
/**
 * @brief		ACP Notification: Advertisements Requiring Approval
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 Jul 2018
 */

namespace IPS\nexus\extensions\core\AdminNotifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ACP Notification: Advertisements Requiring Approval
 */
class _Advertisement extends \IPS\core\AdminNotification
{	
	/**
	 * @brief	Identifier for what to group this notification type with on the settings form
	 */
	public static $group = 'commerce';
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this group compared to others
	 */
	public static $groupPriority = 4;
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this notification type compared to others in the same group
	 */
	public static $itemPriority = 4;
	
	/**
	 * Title for settings
	 *
	 * @return	string
	 */
	public static function settingsTitle()
	{
		return 'acp_notification_Advertisement';
	}
	
	/**
	 * Is this type of notification ever optional (controls if it will be selectable as "viewable" in settings)
	 *
	 * @return	string
	 */
	public static function mayBeOptional()
	{
		return TRUE;
	}
	
	/**
	 * Is this type of notification might recur (controls what options will be available for the email setting)
	 *
	 * @return	bool
	 */
	public static function mayRecur()
	{
		return TRUE;
	}
	
	/**
	 * @brief	Current count
	 */
	protected $count = NULL;
	
	/**
	 * Get count
	 *
	 * @return	bool
	 */
	public function count()
	{
		if ( $this->count === NULL )
		{
			$this->count = \IPS\Db::i()->select( 'COUNT(*)', 'core_advertisements', array( 'ad_active=-1' ) )->first();
		}
		return $this->count;
	}
	
	/**
	 * Can a member access this type of notification?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public static function permissionCheck( \IPS\Member $member )
	{
		return $member->hasAcpRestriction( 'core', 'promotion', 'advertisements_manage' ) and \IPS\Db::i()->select( 'COUNT(*)', 'nexus_packages_ads' )->first();
	}
	
	/**
	 * Notification Title (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function title()
	{		
		return \IPS\Member::loggedIn()->language()->addToStack( 'acpNotification_nexusAdvertisements', FALSE, array( 'pluralize' => array( $this->count() ) ) );
	}
	
	/**
	 * Notification Subtitle (no HTML)
	 *
	 * @return	string
	 */
	public function subtitle()
	{
		try
		{
			return \IPS\DateTime::ts( \IPS\Db::i()->select( 'ad_start', 'core_advertisements', array( 'ad_active=-1' ), 'ad_start asc', 1 )->first() )->relative();
		}
		catch ( \UnderflowException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Notification Body (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function body()
	{
		$table = \IPS\core\modules\admin\promotion\advertisements::table( \IPS\Http\Url::internal('app=core&module=overview&controller=notifications&_table=nexus_Advertisement') );
		$table->limit = 10;
		$table->filters = array();
		$table->quickSearch = NULL;
		$table->advancedSearch = NULL;
		$table->where[] = array( 'ad_active=-1' );
		
		return (string) $table;
	}
	
	/**
	 * Severity
	 *
	 * @return	string
	 */
	public function severity()
	{
		return static::SEVERITY_OPTIONAL;
	}
	
	/**
	 * Dismissible?
	 *
	 * @return	string
	 */
	public function dismissible()
	{	
		return static::DISMISSIBLE_NO;
	}
	
	/**
	 * Style
	 *
	 * @return	bool
	 */
	public function style()
	{
		return static::STYLE_INFORMATION;
	}
	
	/**
	 * Quick link from popup menu
	 *
	 * @return	bool
	 */
	public function link()
	{
		return \IPS\Http\Url::internal('app=core&module=promotion&controller=advertisements&filter=ad_filters_pending');
	}
	
	/**
	 * Should this notification dismiss itself?
	 *
	 * @note	This is checked every time the notification shows. Should be lightweight.
	 * @return	bool
	 */
	public function selfDismiss()
	{
		return !$this->count();
	}
}