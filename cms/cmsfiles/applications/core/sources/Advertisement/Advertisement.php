<?php
/**
 * @brief		Advertisements Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		30 Sept 2013
 */

namespace IPS\core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Advertisements Model
 */
class _Advertisement extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	HTML ad
	 */
	const AD_HTML	= 1;

	/**
	 * @brief	Images ad
	 */
	const AD_IMAGES	= 2;

	/**
	 * @brief	Email ad
	 */
	const AD_EMAIL	= 3;

	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_advertisements';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'ad_';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
		
	/**
	 * @brief	Advertisements loaded during this request (used to update impression count)
	 * @see		static::updateImpressions()
	 */
	public static $advertisementIds	= array();

	/**
	 * @brief	Advertisements sent via email (used to update impression count)
	 * @see		static::updateEmailImpressions()
	 */
	public static $advertisementIdsEmail = array();

	/**
	 * @brief	Stored advertisements we can display on this page
	 */
	protected static $advertisements = NULL;

	/**
	 * @brief	Stored advertisements we can send in emails
	 */
	protected static $emailAdvertisements = NULL;

	/**
	 * Fetch advertisements and return the appropriate one to display
	 *
	 * @param	string	$location	Advertisement location
	 * @return	\IPS\core\Advertisement|NULL
	 */
	public static function loadByLocation( $location )
	{
		/* If we know there are no ads, we don't need to bother */
		if ( !\IPS\Settings::i()->ads_exist )
		{
			return NULL;
		}
		
		/* Fetch our advertisements, if we haven't already done so */
		if( static::$advertisements  === NULL )
		{
			static::$advertisements = array();

			$where[] = array( "ad_type!=?",  static::AD_EMAIL );
			$where[] = array( "ad_active=1" );
			$where[] = array( "ad_start<?", time() );
			$where[] = array( "(ad_end=0 OR ad_end>?)", time() );

			if( \IPS\Dispatcher::hasInstance() AND ( !isset( \IPS\Dispatcher::i()->dispatcherController ) OR !\IPS\Dispatcher::i()->dispatcherController->isContentPage ) )
			{
				$where[] =array( 'ad_nocontent_page_output=?', 1 );
			}

			foreach( \IPS\Db::i()->select( '*' ,'core_advertisements', $where ) as $row )
			{
				foreach ( explode( ',', $row['ad_location'] ) as $_location )
				{
					static::$advertisements[ $_location ][] = static::constructFromData( $row );
				}
			}
		}

		/* Weed out any we don't see due to our group. This is done after loading the advertisements so that the cache can be properly primed regardless of group. Note that $ad->exempt, is, confusingly who to SHOW to, not who is exempt */
		foreach( static::$advertisements as $adLocation => $ads )
		{
			foreach( $ads as $index => $ad )
			{
				if ( ! empty( $ad->exempt ) and $ad->exempt != '*' )
				{
					$groupsToHideFrom = array_diff( array_keys(\IPS\Member\Group::groups()), json_decode( $ad->exempt, TRUE ) );

					if ( \IPS\Member::loggedIn()->inGroup( $groupsToHideFrom ) )
					{
						unset( static::$advertisements[ $adLocation ][ $index ] );
						continue;
					}
				}
			}
		}

		/* No advertisements? Just return then */
		if( !\count( static::$advertisements ) OR !isset( static::$advertisements[ $location ] ) OR !\count( static::$advertisements[ $location ] ) )
		{
			return NULL;
		}

		return static::selectAdvertisement( static::$advertisements[ $location ] );
	}

	/**
	 * Fetch advertisements for emails and return the appropriate one to display
	 *
	 * @param	array|null	$container	The container that spawned the email, or NULL
	 * @return	\IPS\core\Advertisement|NULL
	 */
	public static function loadForEmail( $container=NULL )
	{
		/* If we know there are no ads, we don't need to bother */
		if ( !\IPS\Settings::i()->ads_exist )
		{
			return NULL;
		}
		
		/* Fetch our advertisements, if we haven't already done so */
		if( static::$emailAdvertisements  === NULL )
		{
			static::$emailAdvertisements = array();

			foreach( \IPS\Db::i()->select( '*' ,'core_advertisements', array( "ad_type=? AND ad_active=1 AND ad_start < ? AND ( ad_end=0 OR ad_end > ? )", static::AD_EMAIL, time(), time() ) ) as $row )
			{
				foreach ( explode( ',', $row['ad_location'] ) as $_location )
				{
					static::$emailAdvertisements[] = static::constructFromData( $row );
				}
			}
		}

		/* Whittle down the advertisements to use based on container limitations */
		$adsToCheckFrom = array();

		/* First see if we have any for this specific configuration */
		if( $container !== NULL )
		{
			foreach( static::$emailAdvertisements as $advertisement )
			{
				if( isset( $advertisement->_additional_settings['email_container'] ) AND isset( $advertisement->_additional_settings['email_node'] ) )
				{
					if( $advertisement->_additional_settings['email_container'] == $container['className'] AND $advertisement->_additional_settings['email_node'] == $container['id'] )
					{
						$adsToCheckFrom[] = $advertisement;
					}
				}
			}
		}

		/* If we didn't find any, then look for generic ones for the node class */
		if( $container !== NULL )
		{
			if( !\count( $adsToCheckFrom ) )
			{
				foreach( static::$emailAdvertisements as $advertisement )
				{
					if( isset( $advertisement->_additional_settings['email_container'] ) AND ( !isset( $advertisement->_additional_settings['email_node'] ) OR !$advertisement->_additional_settings['email_node'] ) )
					{
						if( $advertisement->_additional_settings['email_container'] == $container['className'] )
						{
							$adsToCheckFrom[] = $advertisement;
						}
					}
				}
			}
		}

		/* If we still don't have any, look for generic ones allowed in all emails */
		if( !\count( $adsToCheckFrom ) )
		{
			foreach( static::$emailAdvertisements as $advertisement )
			{
				if( !isset( $advertisement->_additional_settings['email_container'] ) OR $advertisement->_additional_settings['email_container'] == '*' )
				{
					$adsToCheckFrom[] = $advertisement;
				}
			}
		}

		/* No advertisements? Just return then */
		if( !\count( $adsToCheckFrom ) )
		{
			return NULL;
		}

		return static::selectAdvertisement( $adsToCheckFrom );
	}

	/**
	 * Select an advertisement from an array and return it
	 *
	 * @param	array	$ads	Array of advertisements to select from
	 * @return	static
	 */
	static protected function selectAdvertisement( $ads )
	{
		/* Reset so we don't throw an error */
		$ads = array_values( $ads );

		/* If we only have one, that is the one we will show */
		if( \count( $ads ) === 1 )
		{
			$advertisement	= $ads[0];
		}
		else
		{
			/* Figure out which one to show you */
			switch( \IPS\Settings::i()->ads_circulation )
			{
				case 'random':
					$advertisement	= $ads[ array_rand( $ads ) ];
				break;

				case 'newest':
					usort( $ads, function( $a, $b ){
						return strcmp( $a->start, $b->start );
					} );

					$advertisement	= $ads[0];
				break;

				case 'oldest':
					usort( $ads, function( $a, $b ){
						return strcmp( $b->start, $a->start );
					} );

					$advertisement	= $ads[0];
				break;

				case 'least':
					usort( $ads, function( $a, $b ){
						if ( $a->impressions == $b->impressions )
						{
							return 0;
						}
						
						return ( $a->impressions < $b->impressions ) ? -1 : 1;
					} );

					$advertisement	= $ads[0];
				break;
			}
		}

		return $advertisement;
	}

	/**
	 * Convert the advertisement to an HTML string
	 *
	 * @param	string				$emailType	html or plaintext email advertisement
	 * @param	\IPS\Email|NULL		$email		For an email advertisement, this will be the email object, otherwise NULL
	 * @return	string
	 */
	public function toString( $emailType='html', $email=NULL )
	{
		/* Showing HTML or an image? */
		if( $this->type == static::AD_HTML )
		{
			if( \IPS\Request::i()->isSecure() AND $this->html_https_set )
			{
				$result	= $this->html_https;
			}
			else
			{
				$result	= $this->html;
			}
		}
		elseif( $this->type == static::AD_IMAGES )
		{
			$result	= \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->advertisementImage( $this );
		}
		elseif( $this->type == static::AD_EMAIL )
		{
			$result = \IPS\Email::template( 'core', 'advertisement', $emailType, array( $this, $email ) );
		}

		/* Did we just hit the maximum impression count? If so, disable and then clear the cache so it will rebuild next time. */
		if( $this->maximum_unit == 'i' AND $this->maximum_value > -1 AND $this->impressions + 1 >= $this->maximum_value )
		{
			$this->active	= 0;
			$this->save();
			
			if ( !\IPS\Db::i()->select( 'COUNT(*)', 'core_advertisements', 'ad_active=1' )->first() )
			{
				\IPS\Settings::i()->changeValues( array( 'ads_exist' => 0 ) );
			}			
		}

		/* Store the id so we can update impression count and return the ad */
		if( $this->type == static::AD_EMAIL )
		{
			static::$advertisementIdsEmail[] = $this->id;
		}
		else
		{
			static::$advertisementIds[]	= $this->id;
		}
		
		return $result;
	}

	/**
	 * Convert the advertisement to an HTML string
	 *
	 * @return	string
	 */
	public function __toString()
	{
		return $this->toString();
	}

	/**
	 * Get images
	 *
	 * @return	array
	 */
	public function get__images()
	{
		if( !isset( $this->_data['_images'] ) )
		{
			$this->_data['_images']	= $this->_data['images'] ? json_decode( $this->_data['images'], TRUE ) : array();
		}

		return $this->_data['_images'];
	}
	
	/**
	 * Get additional settings
	 *
	 * @return	array
	 */
	public function get__additional_settings()
	{
		if( !isset( $this->_data['_additional_settings'] ) )
		{
			$this->_data['_additional_settings'] = $this->_data['additional_settings'] ? json_decode( $this->_data['additional_settings'], TRUE ) : array();
		}

		return $this->_data['_additional_settings'];
	}

	/**
	 * Get the file system storage extension
	 *
	 * @return string
	 */
	public function storageExtension()
	{
		if ( $this->member )
		{
			return 'nexus_Ads';
		}
		else
		{
			return 'core_Advertisements';
		}
	}
	
	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		/* If we have images, delete them */
		if( \count( $this->_images ) )
		{
			\IPS\File::get( $this->storageExtension(), $this->_images['large'] )->delete();

			if( isset( $this->_images['small'] ) )
			{
				\IPS\File::get( $this->storageExtension(), $this->_images['small'] )->delete();
			}

			if( isset( $this->_images['medium'] ) )
			{
				\IPS\File::get( $this->storageExtension(), $this->_images['medium'] )->delete();
			}
		}

		/* Delete the translatable title */
		\IPS\Lang::deleteCustom( 'core', "core_advert_{$this->id}" );
		
		/* Delete */
		parent::delete();
		
		/* Make sure we still have active ads */
		if ( !\IPS\Db::i()->select( 'COUNT(*)', 'core_advertisements', 'ad_active=1' )->first() )
		{
			\IPS\Settings::i()->changeValues( array( 'ads_exist' => 0 ) );
		}
	}

	/**
	 * Update ad impressions for advertisements loaded
	 *
	 * @return	void
	 */
	public static function updateImpressions()
	{
		if( \count( static::$advertisementIds ) )
		{
			static::updateCounter( static::$advertisementIds );

			/* Reset in case execution continues and more ads are shown */
			static::$advertisementIds = array();
		}
	}

	/**
	 * Update ad impressions for advertisements sent in emails
	 *
	 * @param	int		$impressions	Number of impressions (may be more than one if mergeAndSend() was called)
	 * @return	void
	 */
	public static function updateEmailImpressions( $impressions=1 )
	{
		if( \count( static::$advertisementIdsEmail ) )
		{
			static::updateCounter( static::$advertisementIdsEmail, $impressions );

			/* Reset in case execution continues and more ads are sent */
			static::$advertisementIdsEmail = array();
		}
	}

	/**
	 * Update the advert impression counters
	 *
	 * @param array $ids	Array of IDs
	 * @param int $by		Number to increment by
	 * @return void
	 */
	protected static function updateCounter( array $ids, $by=1 )
	{
		$countUpdated = false;
		if ( \IPS\REDIS_ENABLED and \IPS\CACHE_METHOD == 'Redis' and ( \IPS\CACHE_CONFIG or \IPS\REDIS_CONFIG ) )
		{
			foreach( $ids as $id )
			{
				try
				{
					\IPS\Redis::i()->zIncrBy( 'advert_impressions', $by, $id );
					$countUpdated = true;
				}
				catch ( \Exception $e )
				{
				}
			}
		}

		if ( ! $countUpdated )
		{
			\IPS\Db::i()->update( 'core_advertisements', "ad_impressions=ad_impressions+" . $by, "ad_id IN(" . implode( ',', $ids ) . ")" );
		}
	}
}


