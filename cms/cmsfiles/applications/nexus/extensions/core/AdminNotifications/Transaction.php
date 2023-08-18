<?php
/**
 * @brief		ACP Notification: Transactions Requiring Attention
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		25 Jul 2018
 */

namespace IPS\nexus\extensions\core\AdminNotifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ACP Notification: Transactions Requiring Attention
 */
class _Transaction extends \IPS\core\AdminNotification
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
	public static $itemPriority = 2;
	
	/**
	 * Get queue HTML
	 *
	 * @param	string	$status	Status to show
	 * @return	string
	 */
	public static function queueHtml( $status )
	{
		$select = \IPS\Db::i()->select( '*', 'nexus_transactions', array( 't_status=?', $status ), 't_date ASC', array( 0, 12 ) );
		
		if ( \count( $select ) )
		{ 	
			return \IPS\Theme::i()->getTemplate( 'notifications', 'nexus' )->transactions( new \IPS\Patterns\ActiveRecordIterator( $select, 'IPS\nexus\Transaction' ) );
		}
		else
		{
			return '';
		}
	}
	
	/**
	 * Title for settings
	 *
	 * @return	string
	 */
	public static function settingsTitle()
	{
		return 'acp_notification_Transaction';
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
			$this->count = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_transactions', array( 't_status=?', $this->extra ) )->first();
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
		return $member->hasAcpRestriction( 'nexus', 'payments', 'transactions_manage' );
	}
	
	/**
	 * Notification Title (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function title()
	{		
		return \IPS\Member::loggedIn()->language()->addToStack( 'acpNotification_nexusTransaction_' . $this->extra, FALSE, array( 'pluralize' => array( $this->count() ) ) );
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
			return \IPS\DateTime::ts( \IPS\Db::i()->select( 't_date', 'nexus_transactions', array( 't_status=?', $this->extra ), 't_date asc', 1 )->first() )->relative();
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
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('admin_notifications.js', 'nexus') );
		
		return static::queueHtml( $this->extra );
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
		if ( $this->extra === \IPS\nexus\Transaction::STATUS_DISPUTED )
		{
			return static::STYLE_WARNING;
		}
		else
		{
			return static::STYLE_INFORMATION;
		}
	}
	
	/**
	 * Quick link from popup menu
	 *
	 * @return	bool
	 */
	public function link()
	{
		return \IPS\Http\Url::internal('app=nexus&module=payments&controller=transactions&attn=1');
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