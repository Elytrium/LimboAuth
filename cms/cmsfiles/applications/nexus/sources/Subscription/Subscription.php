<?php
/**
 * @brief		User subscription model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Feb 2018
 */

namespace IPS\nexus;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * User subscription model
 */
class _Subscription extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'nexus_member_subscriptions';

	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'sub_';

	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'sub_purchase_id' );

	/**
	 * Get the DateTime object for when this little subscription doth run out
	 *
	 * @return	\IPS\DateTime|NULL
	 */
	public function get__expire()
	{
		if ( $this->expire )
		{
			if ( $this->purchase_id )
			{
				/* This has been invoiced, so there is a purchase row - fetch the expiration from that */
				try
				{
					$purchase = \IPS\nexus\Purchase::load( $this->purchase_id );
					
					if ( $purchase->expire )
					{
						return $purchase->expire;
					} 
				}
				catch( \OutOfRangeException $e ) { }
			}
			
			return \IPS\DateTime::ts( $this->expire );
		}
		
		return NULL;
	}

	/**
	 * Get the package (ooh, very mysterious, sounds like something from Narcos)
	 *
	 * @return	\IPS\nexus\Subscription\Package or NULL, I don't really care at this point.
	 */
	public function get_package()
	{
		try
		{
			return \IPS\nexus\Subscription\Package::load( $this->package_id );
		}
		catch( \OutOfRangeException $ex )
		{
			return NULL;
		}
	}
	
	/**
	 * Get the purchase
	 *
	 * @return	\IPS\nexus\Subscription\Package or NULL, I don't really care at this point.
	 */
	public function get_purchase()
	{
		try
		{
			return \IPS\nexus\Purchase::load( $this->purchase_id );
		}
		catch( \OutOfRangeException $ex )
		{
			return NULL;
		}
	}
	
	/**
	 * Change the subscription package
	 *
	 * @param	\IPS\nexus\Subscription\Package		$package		The new package innit
	 * @param	\IPS\DateTime|NULL					$expires		The new expiration date
	 * @return void
	 */
	public function changePackage( \IPS\nexus\Subscription\Package $package, $expires=NULL )
	{
		$this->package_id = $package->id;
		$this->expire     = ( $expires === NULL ) ? 0 : $expires->getTimeStamp();
		$this->renews     = ! empty( $package->renew_options ) ? 1 : 0;
		$this->save();
	}
	
	/**
	 * Get a nice blurb explaining about the current subscription. If you want, up to you.
	 *
	 * @return string
	 */
	public function currentBlurb()
	{
		if ( !$this->active AND !$this->purchase->cancelled )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'nexus_subs_subscribed_expired' );
		}
		elseif( $this->purchase->cancelled AND $this->purchase->can_reactivate )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'nexus_subs_subscribed_cancelled' );
		}
		elseif( $this->purchase->cancelled )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'nexus_subs_subscribed_cancelled_no_reactivate' );
		}
		if ( $this->expire and $this->renews and !$this->manually_added )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'nexus_subs_subscribed_with_expire' . ( ( $this->purchase and $this->purchase->renewals ) ? '' : '_no_renewal' ), NULL, array( 'sprintf' => array( $this->_expire->dayAndMonth() . ' ' . $this->_expire->format('Y') ) ) );
		}
		
		return \IPS\Member::loggedIn()->language()->addToStack( 'nexus_subs_subscribed' );
	}
	
	/**
	 * Find and return the package this person is currently subscribed to, or NULL
	 *
	 * @param	\IPS\Member					$member			The member
	 * @param	bool						$activeOnly		If TRUE, returns only active subscription
	 * @return	?\IPS\nexus\Subscription
	 */
	public static function loadByMember( \IPS\Member $member, bool $activeOnly )
	{		
		try
		{
			$where = [];
			$where[] = [ 'sub_member_id=?', $member->member_id ];
			if ( $activeOnly )
			{
				$where[] = [ 'sub_active=1' ];
			}
			
			return static::constructFromData( \IPS\Db::i()->select( '*', 'nexus_member_subscriptions', $where, 'sub_active DESC, sub_start DESC' )->first() );
		}
		catch( \Exception $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Find and return the package this person is currently subscribed to, or NULL
	 *
	 * @param		\IPS\Member|NULL	$member		Pass a member in if you like, no pressure really up to you
	 * @return		\IPS\nexus\Subscription or NULL
	 * @deprecated	Use loadByMember() instead
	 */
	public static function loadActiveByMember( $member=NULL )
	{
		return static::loadByMember( $member ? $member : \IPS\Member::loggedIn(), TRUE );
	}

	/**
	 * Load a subscription by member and package
	 *
	 * @param	\IPS\Member							$member		Take a guess
	 * @param	\IPS\nexus\Subscription\Package		$package	I mean it's really writing itself
	 * @param	boolean								$activeOnly		Only get active packages
	 * @return	\IPS\nexus\Subscription
	 * @throws \OutOfRangeException
	 */
	public static function loadByMemberAndPackage( \IPS\Member $member, \IPS\nexus\Subscription\Package $package, $activeOnly = TRUE )
	{
		try
		{
			$where = array( array( 'sub_package_id=? and sub_member_id=?', $package->id, $member->member_id ) );
			
			if ( $activeOnly === TRUE )
			{
				$where[] = array( 'sub_active=1' );
			}

			return static::constructFromData( \IPS\Db::i()->select( '*', 'nexus_member_subscriptions', $where )->first() );
		}
		catch( \Exception $ex )
		{
			throw new \OutOfRangeException;
		}
	}
	
	/**
	 * Mark all subscriptions by this member as inactive
	 *
	 * @param	\IPS\Member		$member		I dunno, take a guess
	 * @return	void
	 */
	public static function markInactiveByUser( \IPS\Member $member )
	{
		\IPS\Db::i()->update( 'nexus_member_subscriptions', array( 'sub_active' => 0 ), array( 'sub_member_id=?', $member->member_id ) );
	}
}