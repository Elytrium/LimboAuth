<?php
/**
 * @brief		ACP Notification: Pending Shipping Orders
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		26 Jul 2018
 */

namespace IPS\nexus\extensions\core\AdminNotifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ACP Notification: Pending Shipping Orders
 */
class _Shipment extends \IPS\core\AdminNotification
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
	public static $itemPriority = 3;
	
	/**
	 * Title for settings
	 *
	 * @return	string
	 */
	public static function settingsTitle()
	{
		return 'acp_notification_Shipments';
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
			$this->count = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_ship_orders', array( 'o_status=?', 'pend' ) )->first();
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
		return $member->hasAcpRestriction( 'nexus', 'payments', 'shiporders_manage' ) and \IPS\Db::i()->select( 'COUNT(*)', 'nexus_shipping' )->first();
	}
	
	/**
	 * Notification Title (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function title()
	{		
		return \IPS\Member::loggedIn()->language()->addToStack( 'acpNotification_nexusShipments', FALSE, array( 'pluralize' => array( $this->count() ) ) );
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
			return \IPS\DateTime::ts( \IPS\Db::i()->select( 'o_date', 'nexus_ship_orders', array( 'o_status=?', 'pend' ), 'o_date asc', 1 )->first() )->relative();
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
		$table = \IPS\nexus\Shipping\Order::table( \IPS\Http\Url::internal('app=core&module=overview&controller=notifications&_table=nexus_Shipment') );
		$table->limit = 10;
		$table->filters = array();
		$table->quickSearch = NULL;
		$table->advancedSearch = NULL;
		$table->where[] = array( 'o_status=?', 'pend' );		
		
		return $table;
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
		return \IPS\Http\Url::internal('app=nexus&module=payments&controller=shipping');
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