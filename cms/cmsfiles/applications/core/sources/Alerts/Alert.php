<?php
/**
 * @brief		Alert model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 May 2022
 * @todo js to hide modal when replying
 */

namespace IPS\core\Alerts;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Alerts Model
 */
class _Alert extends \IPS\Content\Item
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_alerts';

	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array( 'alerts' );

	/**
	 * @brief	Application
	 */
	public static $application = 'core';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'alert_';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
		
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
			'title'			=> 'title',
			'date'			=> 'start',
			'author'		=> 'member_id',
			'views'			=> 'views',
			'content'		=> 'content'
	);
	
	/**
	 * @brief	Title
	 */
	public static $title = 'alert';
	
	/**
	 * @brief	Title
	 */
	public static $icon = 'bullhorn';


	/**
	 * Get SEO name
	 *
	 * @return	string
	 */
	public function get_seo_title()
	{
		if( !$this->_data['seo_title'] )
		{
			$this->seo_title	= \IPS\Http\Url\Friendly::seoTitle( $this->title );
			$this->save();
		}

		return $this->_data['seo_title'] ?: \IPS\Http\Url\Friendly::seoTitle( $this->title );
	}

	/**
	 * Display Form
	 *
	 * @param	static|NULL	$alert	Existing alert (for edits)
	 * @return	\IPS\Helpers\Form
	 */
	public static function form( $alert )
	{
		/* Build the form */
		$form = new \IPS\Helpers\Form( NULL, 'save' );
		$form->class = 'ipsForm_vertical';

		if ( ! \IPS\Member::loggedIn()->canUseMessenger() )
		{
			$form->addMessage( 'alert_you_cannot_receive_messages', 'ipsMessage ipsMessage_info' );
		}

		$form->add( new \IPS\Helpers\Form\Text( 'alert_title', ( $alert ) ? $alert->title : NULL, TRUE, array( 'maxLength' => 255 ) ) );

		$today = new \IPS\DateTime;
		$form->add( new \IPS\Helpers\Form\Date( 'alert_start', ( $alert ) ? \IPS\DateTime::ts( $alert->start ) : new \IPS\DateTime, TRUE, array( 'time' => TRUE ) ) );
		$form->add( new \IPS\Helpers\Form\Date( 'alert_end', ( $alert AND $alert->end ) ? \IPS\DateTime::ts( $alert->end ) : 0, FALSE, array( 'min' => $today->setTime( 0, 0, 1 )->add( new \DateInterval( 'P1D' ) ), 'unlimited' => 0, 'unlimitedLang' => 'none' ) ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'alert_content', ( $alert ) ? $alert->content : NULL, TRUE, array( 'app' => 'core', 'key' => 'Alert', 'autoSaveKey' => ( $alert ? 'editAlert__' . $alert->id : 'createAlert' ), 'attachIds' => $alert ? array( $alert->id, NULL, 'alert' ) : NULL ), NULL, NULL, NULL, 'alert_content' ) );

		$options = array( 'user' => 'alert_type_user', 'group' => 'alert_type_group' );
		$toggles = array( 'user' => array( 'alert_recipient_user' ), 'group' => array( 'alert_recipient_group', 'alert_show_to' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'alert_recipient_type', ( $alert ) ? $alert->recipient_type : NULL, TRUE, array( 'options' => $options, 'toggles' => $toggles ) ) );

		$formFields = array();

		foreach( $formFields as $field )
		{
			$form->add( $field );
		}

		$groups = array();
		foreach ( \IPS\Member\Group::groups( TRUE, FALSE ) as $group )
		{
			$groups[ $group->g_id ] = $group->name;
		}

		$autocomplete = [
			'source' 				=> 'app=core&module=system&controller=ajax&do=findMember',
			'resultItemTemplate' 	=> 'core.autocomplete.memberItem',
			'commaTrigger'			=> false,
			'unique'				=> true,
			'minAjaxLength'			=> 3,
			'disallowedCharacters'  => array(),
			'lang'					=> 'mem_optional',
			'maxItems'				=> 1,
			'minimized' => FALSE
		];

		$form->add( new \IPS\Helpers\Form\Member( 'alert_recipient_user',  ( $alert and $alert->recipient_user ) ? \IPS\Member::load( $alert->recipient_user ) : ( isset( \IPS\Request::i()->user ) ? \IPS\Member::load( \IPS\Request::i()->user ) : NULL ), FALSE, array( 'multiple' => 1, 'autocomplete' => $autocomplete ), function( $member ) use ( $form )
		{
			if ( \IPS\Request::i()->alert_recipient_type === 'user' )
			{
				if( !\is_object( $member ) or !$member->member_id )
				{
					throw new \InvalidArgumentException( 'alert_no_recipient_selected' );
				}

				if ( ! $member->canUseMessenger() and \IPS\Request::i()->alert_reply == 2 )
				{
					throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack( 'alert_member_pm_disabled', NULL, [ 'sprintf' => $member->name ] ) );
				}
			}
		},
			NULL, NULL, 'alert_recipient_user' ) );

		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'alert_recipient_group', ( $alert and $alert->recipient_group ) ? explode( ',', $alert->recipient_group ) : array(), FALSE, array( 'options' => $groups, 'multiple' => TRUE ), function( $groups )
		{
			if ( \IPS\Request::i()->alert_reply == 2 )
			{
				$module = \IPS\Application\Module::get( 'core', 'messaging' );
				$names = [];
				foreach( $groups as $group )
				{
					$group = \IPS\Member\Group::load( $group );
					if ( ! ( \IPS\Application::load( $module->application )->canAccess( $group ) and ( $module->protected or $module->can( 'view', $group ) ) ) )
					{
						$names[] = $group->name;
					}
				}

				if ( \count( $names ) )
				{
					throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack( 'alert_member_group_pm_disabled', NULL, [ 'htmlsprintf' => \IPS\Member::loggedIn()->language()->formatList( $names ) ] ) );
				}
			}
		},
			NULL, NULL, 'alert_recipient_group' ) );

		$form->add(  new \IPS\Helpers\Form\Radio( 'alert_show_to', ( $alert and $alert->show_to ) ? $alert->show_to : 'all', FALSE, [
			'options' => [
				'all' => 'alert_show_to_all',
				'new' => 'alert_show_to_new'
			]
		], NULL, NULL, NULL, 'alert_show_to' ) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'alert_anonymous', ( $alert and $alert->anonymous ) ? TRUE : FALSE, TRUE, array( 'togglesOff' => array( 'alert_reply') ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'alert_reply', $alert ? $alert->reply : 0, TRUE, array( 'disabled' => (bool) \IPS\Member::loggedIn()->members_disable_pm, 'options' => array( '0' => 'alert_no_reply', '1' => 'alert_can_reply', '2' => 'alert_must_reply' ) ), NULL, NULL, NULL, 'alert_reply' ) );

		if ( \IPS\Member::loggedIn()->members_disable_pm )
		{
			\IPS\Member::loggedIn()->language()->words['alert_reply_desc'] = \IPS\Member::loggedIn()->language()->get('alert_reply__nopm_desc');
		}

		return $form;
	}

	/**
	 * Get data store
	 *
	 * @return	array
	 */
	public static function getStore()
	{
		if ( !isset( \IPS\Data\Store::i()->alerts ) )
		{
			\IPS\Data\Store::i()->alerts = iterator_to_array( \IPS\Db::i()->select( '*', static::$databaseTable, [
				[ 'alert_enabled=?', 1 ],
				[ 'alert_start < ?', time() ],
				[ '( alert_end = 0 or alert_end > ? )', time() ]
			], "alert_start ASC" )->setKeyField( 'alert_id' ) );
		}

		return \IPS\Data\Store::i()->alerts;
	}
	
	/**
	 * Create from form
	 *
	 * @param	array	$values	Values from form
	 * @param	\IPS\core\Alerts\Alert|NULL $current	Current alert
	 * @return	\IPS\core\Alerts\Alert
	 */
	public static function _createFromForm( $values, $current )
	{
		if( $current )
		{
			$obj = static::load( $current->id );
		}
		else
		{
			$obj = new static;
			$obj->member_id = \IPS\Member::loggedIn()->member_id;
		}

		$obj->title			= $values['alert_title'];
		$obj->seo_title 	= \IPS\Http\Url\Friendly::seoTitle( $values['alert_title'] );
		$obj->content		= $values['alert_content'];
		$obj->start			= !empty( $values['alert_start'] ) ? ( $values['alert_start']->getTimestamp() < time() ) ? time() : $values['alert_start']->getTimestamp() : time();
		$obj->end			= !empty ($values['alert_end'] ) ? $values['alert_end']->getTimestamp() : 0;

		$obj->recipient_type = $values['alert_recipient_type'];

		if( $obj->recipient_type == 'user' )
		{
			if( \is_object( $values['alert_recipient_user'] ) )
			{
				$values['alert_recipient_user']	= $values['alert_recipient_user']->member_id;
			}
			$obj->recipient_user = $values['alert_recipient_user'];
			$obj->recipient_group = NULL;
		}
		else
		{
			$obj->recipient_user = NULL;
			$obj->recipient_group = implode( ",", $values['alert_recipient_group'] );
			$obj->show_to = $values['alert_show_to'];
		}

		$obj->anonymous = $values['alert_anonymous'];
		$obj->reply = ( ! \IPS\Member::loggedIn()->canUseMessenger() ) ? 0 : $values['alert_reply'];

		$obj->save();
		
		if( !$current )
		{
			\IPS\File::claimAttachments( 'createAlert', $obj->id, NULL, 'alert' );
		}
		
		return $obj;
	}

	/**
	 * @brief	Cached URLs
	 */
	protected $_url	= array();

	/**
	 * Get URL
	 *
	 * @param	string|NULL		$action		Action
	 * @return	\IPS\Http\Url
	 */
	public function url( $action=NULL )
	{
		$_key	= md5( $action );

		if( !isset( $this->_url[ $_key ] ) )
		{
			$this->_url[ $_key ] = \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=alerts&id={$this->id}", 'front', 'modcp_alerts' );
			$this->_url[ $_key ] = $this->_url[ $_key ]->setQueryString( 'action', $action );
		}

		return $this->_url[ $_key ];
	}

	/**
	 * Get owner
	 *
	 * @return	\IPS\Member
	 */
	public function owner()
	{
		return \IPS\Member::load( $this->member_id );
	}
	
	/**
	 * Unclaim attachments
	 *
	 * @return	void
	 */
	protected function unclaimAttachments()
	{
		\IPS\File::unclaimAttachments( 'core_Alert', $this->id, NULL, 'alert' );
	}

	/**
	 * Return the filters that are available for selecting table rows
	 *
	 * @return	array
	 */
	public static function getTableFilters()
	{
		return array(
			'active', 'inactive'
		);
	}

	/**
	 * @param \IPS\Member $member
	 *
	 * @return \IPS\core\Alerts\Alert|NULL
	 */
	public static function getNextAlertForMember( \IPS\Member $member ): ?Alert
	{
		$alerts = static::getStore();

		if ( ! \count( $alerts ) )
		{
			return NULL;
		}

		/* Get possible alerts for this member (group alerts are checked in PHP) */
		$query = \IPS\Db::i()->select( '*', 'core_alerts', [
			[ 'alert_enabled=?', 1 ],
			[ 'alert_start < ?', time() ],
			[ '( alert_end = 0 or alert_end > ? )', time() ],
			[ '( alert_recipient_type=? OR ( alert_recipient_type=? AND alert_recipient_user=? ) )', 'group', 'user', $member->member_id ],
			[ 'alert_id NOT IN (?)', \IPS\Db::i()->select( 'seen_alert_id', 'core_alerts_seen', [ 'seen_member_id=?', $member->member_id ] ) ]
		], 'alert_start ASC' );

		foreach( $query as $data )
		{
			$alert = static::constructFromData( $data );
			if ( $alert->forMember( $member ) )
			{
				return $alert;
			}
		}

		return NULL;
	}

	/**
	 * Is this alert valid for the member?
	 *
	 * @return	Bool
	 */
	public function forMember( $member )
	{
		/* Is it disabled? */
		if ( ! $this->enabled )
		{
			return FALSE;
		}

		/* Are we the alert author? */
		if ( $this->member_id == \IPS\Member::loggedIn()->member_id )
		{
			return FALSE;
		}

		/* Is it in the future? */
		if( $this->start > time() )
		{
			return FALSE;
		}

		/* Is it in the past? */
		if( $this->end and $this->end < time() )
		{
			return FALSE;
		}

		/* Show only if to this user or group */
		if( $this->recipient_type == 'user' and $this->recipient_user !== $member->member_id )
		{
			return FALSE;
		}

		if( $this->recipient_type == 'group' )
		{
			if( !$member->inGroup( explode( ",", $this->recipient_group ) ) )
			{
				return FALSE;
			}

			if ( $this->show_to == 'new' and ( \IPS\Member::loggedIn()->joined->getTimestamp() < $this->start ) )
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	/**
	 * Return the names of the groups for this alert.
	 * Convenience method for use in templates.
	 *
	 * @return array
	 */
	public function groupNames(): array
	{
		$names = [];

		if ( $this->recipient_type == 'group' and $this->recipient_group )
		{
			$groups = explode( ',', $this->recipient_group );

			foreach ( \IPS\Member\Group::groups() as $group )
			{
				if ( \in_array( $group->g_id, $groups ) )
				{
					$names[] = $group->name;
				}
			}
		}

		return $names;
	}

	/**
	 * Return the namee of the member for this alert
	 * Convenience method for use in templates.
	 *
	 * @return string
	 */
	public function memberName(): string
	{
		$member = \IPS\Member::load( $this->recipient_user );
		return ( $member->member_id ) ? $member->name :\IPS\Member::loggedIn()->language()->addToStack( 'deleted_member' );
	}

	/**
	 * Mark this alert as viewed
	 *
	 * @param \IPS\Member|null $member	Member who viewed
	 *
	 * @return void
	 */
	public function viewed( \IPS\Member $member=NULL )
	{
		$this->viewed++;
		$this->save();
	}

	/**
	 * Dismiss alert
	 *
	 * @param \IPS\Member|null $member	Member who viewed
	 *
	 * @return	void
	 */
	public function dismiss( \IPS\Member $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		if( !$this->forMember( $member ) )
		{
			return;
		}

		\IPS\Db::i()->insert( 'core_alerts_seen', [
			'seen_alert_id' => $this->id,
			'seen_member_id' => $member->member_id,
			'seen_date' => time()
		], TRUE );

		$member->latest_alert = $this->start;
		$member->save();
	}

	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();

		\IPS\Db::i()->delete( 'core_alerts_seen', [ 'seen_alert_id=?', $this->id ] );
	}

	/**
	 * Get the count of how many replies have been sent in via this alert
	 *
	 * @return int
	 */
	public function membersRepliedCount(): int
	{
		return (int) \IPS\Db::i()->select( 'COUNT(*)', 'core_message_topics', [ 'mt_alert=?', $this->id ] )->first();
	}

	/**
	 * Returns the current alert that messenger is being filtered by
	 *
	 * @param \IPS\Member|null $member
	 * @return Alert|null
	 */
	public static function getAlertCurrentlyFilteringMessages( ?\IPS\Member $member = NULL ): ?\IPS\core\Alerts\Alert
	{
		$member = $member ?: \IPS\Member::loggedIn();

		if ( isset( $_SESSION['mt_alert'] ) )
		{
			try
			{
				$alert = static::load( $_SESSION['mt_alert'] );

				/* Only show details about this alert to the alert owner */
				if ( $alert->author()->member_id != $member->member_id )
				{
					return NULL;
				}

				return $alert;
			}
			catch( \Exception $e ) { }
		}

		return NULL;
	}

	/**
	 * Set the alert to currently filter by
	 *
	 * @param Alert $alert
	 * @return void
	 */
	public static function setAlertCurrentlyFilteringMessages( \IPS\core\Alerts\Alert $alert )
	{
		$_SESSION['mt_alert'] = $alert->id;
	}

	/**
	 * Unset any messenger filters
	 *
	 * @return void
	 */
	public static function clearMessengerFilters()
	{
		unset( $_SESSION['mt_alert'] );
	}
}