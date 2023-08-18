<?php
/**
 * @brief		Privacy Action
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		07 March 2023
 */

namespace IPS\Member;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * PrivacyAction Model
 */
class _PrivacyAction extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief    [ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief    [ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_member_privacy_actions';

	/**
	 * @brief    [ActiveRecord] Multiton Map
	 */
	protected static $multitonMap = [];

	/**
	 * @brief    PII request
	 */
	const TYPE_REQUEST_PII = 'pii_download';

	/**
	 * @brief    Account deletion request
	 */
	const TYPE_REQUEST_DELETE = 'delete_account';

	/**
	 * @brief    Account deletion requires validation
	 */
	const TYPE_REQUEST_DELETE_VALIDATION = 'delete_account_validation';

	/**
	 * Request the PII Data
	 * 
	 * @param \IPS\Member|NULL $member
	 * @return void
	 */
	public static function requestPiiData( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		$obj = new static;
		$obj->member_id = $member->member_id;
		$obj->request_date = time();
		$obj->action = static::TYPE_REQUEST_PII;
		$obj->save();

		\IPS\core\AdminNotification::send( 'core', 'PiiDataRequest', NULL, TRUE, $member );
	}

	/**
	 * Can the member request his PII Data?
	 * 
	 * @param \IPS\Member|NULL $member
	 * @return bool
	 */
	public static function canRequestPiiData( \IPS\Member $member = NULL ): bool
	{
		$member = $member ?: \IPS\Member::loggedIn();
		if ( $member->member_id  AND !static::hasPiiRequest($member) )
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Can the member download his PII Data?
	 * 
	 * @param \IPS\Member|NULL $member
	 * @return bool
	 */
	public static function canDownloadPiiData( \IPS\Member $member = NULL ): bool
	{
		$member = $member ?: \IPS\Member::loggedIn();
		if( !$member->member_id )
		{
			return FALSE;
		}
		
		if ( \IPS\Db::i()->select( 'count(*)', static::$databaseTable, [ 'member_id=? AND action=? AND approved=?', $member->member_id, static::TYPE_REQUEST_PII, 1 ] )->first() > 0 )
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Get the deletion request by member and key
	 * 
	 * @param \IPS\Member $member
	 * @param string $key
	 * @return static
	 * @throws \OutOfRangeException
	 */
	public static function getDeletionRequestByMemberAndKey( \IPS\Member $member, string $key ): static
	{
		try
		{
			$where = [];
			$where[] = ['member_id=?', $member->member_id];
			$where[] = ['vkey=?', $key];
			$where[] = [ \IPS\Db::i()->in( 'action',[static::TYPE_REQUEST_DELETE, static::TYPE_REQUEST_DELETE_VALIDATION ] )];
			return static::constructFromData(\IPS\Db::i()->select( '*', static::$databaseTable, $where  )->first() );
		}
		catch( \UnderflowException $e ){
			throw new \OutOfRangeException;
		}
	}

	/**
	 * Is a PII Data Request pending for this member?
	 * 
	 * @param \IPS\Member|NULL $member
	 * @param bool $approved
	 * @return bool
	 */
	public static function hasPiiRequest( \IPS\Member $member = NULL, bool $approved = FALSE ): bool
	{
		$member = $member ?: \IPS\Member::loggedIn();
		try
		{
			\IPS\Db::i()->select( '*', static::$databaseTable, [ 'member_id=? AND action=? AND approved=?', $member->member_id, static::TYPE_REQUEST_PII, (int) $approved ] )->first();
			return TRUE;
		}
		catch( \UnderflowException $e ){}
		return FALSE;
	}

	/**
	 * Approve the PII Request
	 * 
	 * @return void
	 */
	public function approvePiiRequest()
	{
		$this->approved = TRUE;
		$this->save();
		$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'pii_data', $this, array( $this ) );
		$notification->recipients->attach( $this->member );
		$notification->send();
		static::resetPiiAcpNotifications();
	}

	/**
	 * Reject the PII Data Request
	 * 
	 * @return void
	 */
	public function rejectPiiRequest()
	{
		$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'pii_data_rejected', $this, array( $this ) );
		$notification->recipients->attach( $this->member );
		$notification->send();
		$this->delete();
		static::resetPiiAcpNotifications();
	}

	/**
	 * Get the member object
	 * 
	 * @return \IPS\Member
	 */
	public function get_member(): \IPS\Member
	{
		return \IPS\Member::load( $this->member_id );
	}

	/**
	 * Reset the PII request ACP notifications
	 *
	 * @return void
	 */
	public static function resetPiiAcpNotifications()
	{
		if ( !\IPS\Db::i()->select( 'COUNT(*)', static::$databaseTable, array( 'action=? AND approved=?', static::TYPE_REQUEST_PII, 0 ) )->first() )
		{
			\IPS\core\AdminNotification::remove( 'core', 'PiiDataRequest' );
		}
	}

	/**
	 * Reset the account deletion ACP notifications
	 * 
	 * @return void
	 */
	public static function resetDeletionAcpNotifications()
	{
		if ( !\IPS\Db::i()->select( 'COUNT(*)', static::$databaseTable, array( 'action=?', static::TYPE_REQUEST_DELETE ) )->first() )
		{
			\IPS\core\AdminNotification::remove( 'core', 'AccountDeletion' );
		}
	}

	/**
	 * Can the current member request account deletion?
	 * 
	 * @param \IPS\Member|NULL $member
	 * @return bool
	 */
	public static function canDeleteAccount( \IPS\Member $member = NULL ): bool
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		return \IPS\Db::i()->select( 'count(*)', static::$databaseTable, [ 'member_id=? AND action=?', $member->member_id, static::TYPE_REQUEST_DELETE ] )->first() == 0;
	}

	/**
	 * Create and log the account  deletion request
	 * 
	 * @param \IPS\Member|NULL $member
	 * @return void
	 */
	public static function requestAccountDeletion( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		$obj = new static;
		$obj->member_id = $member->member_id;
		$obj->request_date = time();
		$obj->action = static::TYPE_REQUEST_DELETE_VALIDATION;
		$vkey = md5( $member->members_pass_hash . \IPS\Login::generateRandomString() );
		$obj->vkey = $vkey;
		\IPS\Email::buildFromTemplate( 'core', 'account_deletion_confirmation', array( $member, $vkey ), \IPS\Email::TYPE_TRANSACTIONAL )->send( $member );

		$obj->save();
	}

	/**
	 * Confirm the account deletion request
	 *
	 * @return void
	 */
	public function confirmAccountDeletion()
	{
		$this->action = static::TYPE_REQUEST_DELETE;
		\IPS\core\AdminNotification::send( 'core', 'AccountDeletion', NULL, TRUE, $this->member );
		$this->member->logHistory( 'core', 'privacy', array( 'type' => 'account_deletion_requested' ) );
		$this->save();
	}

	/**
	 * Delete the account
	 * 
	 * @return void
	 */
	public function deleteAccount()
	{
		/** @var \IPS\Member $member */
		$member = $this->member;

		$member->delete( TRUE, FALSE );
		static::resetDeletionAcpNotifications();
		\IPS\Session::i()->log( 'acplog__members_deleted_id', array( $member->name => FALSE, $member->member_id => FALSE ) );
	}

	/**
	 * Reject the account deletion request
	 *
	 * @return void
	 */
	public function rejectDeletionRequest()
	{
		$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'account_del_request_rejected', $this, array( $this ) );
		$notification->recipients->attach( $this->member );
		$notification->send();
		$this->delete();
		static::resetDeletionAcpNotifications();
	}
}
