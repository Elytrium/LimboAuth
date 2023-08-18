<?php
/**
 * @brief		ACP Notification: IPS Bulletins
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		16 Jul 2018
 */

namespace IPS\core\extensions\core\AdminNotifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ACP Notification: IPS Bulletins
 */
class _Bulletin extends \IPS\core\AdminNotification
{
	/**
	 * @brief	Identifier for what to group this notification type with on the settings form
	 */
	public static $group = 'important';
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this group compared to others
	 */
	public static $groupPriority = 1;
	
	/**
	 * @brief	Priority 1-5 (1 being highest) for this notification type compared to others in the same group
	 */
	public static $itemPriority = 2;
		
	/**
	 * Title for settings
	 *
	 * @return	string
	 */
	public static function settingsTitle()
	{
		return 'acp_notification_Bulletin';
	}
	
	/**
	 * Can a member access this type of notification?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public static function permissionCheck( \IPS\Member $member )
	{
		return $member->hasAcpRestriction( 'core', 'overview', 'ips_notifications' );
	}
	
	/**
	 * Is this type of notification ever optional (controls if it will be selectable as "viewable" in settings)
	 *
	 * @return	string
	 */
	public static function mayBeOptional()
	{
		return FALSE;
	}
	
	/**
	 * Is this type of notification might recur (controls what options will be available for the email setting)
	 *
	 * @return	bool
	 */
	public static function mayRecur()
	{
		return FALSE;
	}

	/**
	 * @brief	Cached data (so we don't query it multiple times)
	 */
	protected $_bulletinData = NULL;
		
	/**
	 * Get notification data
	 *
	 * @return	array
	 */
	public function data()
	{
		if( $this->_bulletinData !== NULL )
		{
			return $this->_bulletinData;
		}

		try
		{
			$data = \IPS\Db::i()->select( '*', 'core_ips_bulletins', array( 'id=?', $this->extra ) )->first();
		}
		catch( \UnderflowException $e )
		{
			$data = array( 'cached' => 0, 'id' => $this->extra );
		}
		
		if ( ( time() - $data['cached'] ) > 3600 ) // If data was cached more than an hour ago, check again in case it's been updated
		{
			try
			{
				$response = \IPS\Http\Url::ips("bulletin/{$data['id']}")->request()->get();
				$bulletin = $response->decodeJson();
				if ( isset( $bulletin['title'] ) )
				{
					$data = array(
						'id' 			=> $data['id'],
						'title'			=> $bulletin['title'],
						'body'			=> $bulletin['body'],
						'severity'		=> $bulletin['severity'],
						'style'			=> $bulletin['style'],
						'dismissible'	=> $bulletin['dismissible'],
						'link'			=> $bulletin['link'],
						'conditions'	=> $bulletin['conditions'],
						'cached'		=> time(),
						'min_version'	=> $bulletin['minVersion'],
						'max_version'	=> $bulletin['maxVersion']
					);
					\IPS\Db::i()->update( 'core_ips_bulletins', $data, array( 'id=?', $this->extra ) );
				}
				else
				{
					if( (int) $response->httpResponseCode === 410 )
					{
						\IPS\Db::i()->delete( 'core_ips_bulletins', [ 'id=?', $this->extra ] );
						$this->delete();
					}

					throw new \DomainException;
				}
			}
			catch ( \Exception $e )
			{
				\IPS\Db::i()->update( 'core_ips_bulletins', array( 'cached' => ( time() + 3600 - 900 ) ), array( 'id=?', $this->extra ) ); // Try again in 15 minutes

				$data = array(
					'id' 			=> $data['id'],
					'title'			=> null,
					'body'			=> null,
					'severity'		=> null,
					'style'			=> null,
					'dismissible'	=> false,
					'link'			=> null,
					'conditions'	=> null,
					'cached'		=> ( time() + 3600 - 900 ),
					'min_version'	=> null,
					'max_version'	=> null
				);
			}
		}

		$this->_bulletinData = $data;
		
		return $data;
	}
	
	/**
	 * Notification Title (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function title()
	{		
		return $this->data()['title'];
	}
	
	/**
	 * Notification Body (full HTML, must be escaped where necessary)
	 *
	 * @return	string
	 */
	public function body()
	{
		return $this->data()['body'];
	}
	
	/**
	 * Severity
	 *
	 * @return	string
	 */
	public function severity()
	{
		return $this->data()['severity'];
	}
	
	/**
	 * Dismissible?
	 *
	 * @return	string
	 */
	public function dismissible()
	{
		return $this->data()['dismissible'];
	}
	
	/**
	 * Style
	 *
	 * @return	bool
	 */
	public function style()
	{
		return $this->data()['style'];
	}
	
	/**
	 * Quick link from popup menu
	 *
	 * @return	bool
	 */
	public function link()
	{
		return $this->data()['link'] ?: parent::link();
	}
	
	/**
	 * Should this notification dismiss itself?
	 *
	 * @note	This is checked every time the notification shows. Should be lightweight.
	 * @return	bool
	 */
	public function selfDismiss()
	{
		try
		{
			if( $this->data()['min_version'] AND $this->data()['min_version'] > \IPS\Application::load('core')->long_version )
			{
				return TRUE;
			}

			if( $this->data()['max_version'] AND $this->data()['max_version'] < \IPS\Application::load('core')->long_version )
			{
				return TRUE;
			}

			return !@eval( $this->data()['conditions'] );
		}
		catch ( \Throwable | \Exception $e )
		{
			return FALSE;
		}
	}
}