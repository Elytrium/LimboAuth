<?php
/**
 * @brief		External redirector with key checks
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Jun 2013
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Redirect
 */
class _redirect extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Is this for displaying "content"? Affects if advertisements may be shown
	 */
	public $isContentPage = FALSE;

	/**
	 * Handle munged links
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* The URL may have had a line-break added in the outbound email if it's a long URL, we need to fix that. Then check the key matches.  */
		try
		{
			$url = str_replace( [ '%20','%0D' ], '', (string) \IPS\Http\Url::createFromString( \IPS\Request::i()->url ) );
			if ( \IPS\Login::compareHashes( hash_hmac( "sha256", $url, \IPS\Settings::i()->site_secret_key ), (string) \IPS\Request::i()->key ) OR \IPS\Login::compareHashes( hash_hmac( "sha256", $url, \IPS\Settings::i()->site_secret_key . 'r' ), (string) \IPS\Request::i()->key ) )
			{
				/* Construct the URL */
				$url = \IPS\Http\Url::external( $url );

				/* If this is coming from email tracking, log the click */
				if( isset( \IPS\Request::i()->email ) AND \IPS\Settings::i()->prune_log_emailstats != 0 )
				{
					/* If we have a row for "today" then update it, otherwise insert one */
					$today = \IPS\DateTime::create()->format( 'Y-m-d' );

					try
					{
						/* We only include the time column in the query so that the db index can be effectively used */
						if( !\IPS\Request::i()->type )
						{
							$currentRow = \IPS\Db::i()->select( '*', 'core_statistics', array( 'type=? AND time>? AND value_4=? AND extra_data IS NULL', 'email_clicks', 1, $today ) )->first();
						}
						else
						{
							$currentRow = \IPS\Db::i()->select( '*', 'core_statistics', array( 'type=? AND time>? AND value_4=? AND extra_data=?', 'email_clicks', 1, $today, \IPS\Request::i()->type ) )->first();
						}

						\IPS\Db::i()->update( 'core_statistics', "value_1=value_1+1", array( 'id=?', $currentRow['id'] ) );
					}
					catch( \UnderflowException $e )
					{
						\IPS\Db::i()->insert( 'core_statistics', array( 'type' => 'email_clicks', 'value_1' => 1, 'value_4' => $today, 'time' => time(), 'extra_data' => \IPS\Request::i()->type ) );
					}
				}

				/* Send the user to the URL after setting the referrer policy header */
				\IPS\Output::i()->sendHeader( "Referrer-Policy: origin" );
				\IPS\Output::i()->redirect( $url, \IPS\Request::i()->email ? '' : \IPS\Member::loggedIn()->language()->addToStack('external_redirect'), 303, \IPS\Request::i()->email ? FALSE : TRUE );
			}
			/* If it doesn't validate, send the user to the index page */
			else
			{
				throw new \DomainException();
			}
		}
		catch( \DomainException $e )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('') );
		}
	}

	/**
	 * Redirect an advertisement click
	 *
	 * @return	void
	 */
	protected function advertisement()
	{
		/* Get the advertisement */
		$advertisement	= array();

		if( isset( \IPS\Request::i()->ad ) )
		{
			try
			{
				$advertisement	= \IPS\core\Advertisement::load( \IPS\Request::i()->ad );
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'ad_not_found', '2C159/2', 404, 'ad_not_found_admin' );
			}
		}

		if( !$advertisement->id OR !$advertisement->link )
		{
			\IPS\Output::i()->error( 'ad_not_found', '2C159/1', 404, 'ad_not_found_admin' );
		}

		if ( \IPS\Login::compareHashes( hash_hmac( "sha256", $advertisement->link, \IPS\Settings::i()->site_secret_key ), (string) \IPS\Request::i()->key ) OR \IPS\Login::compareHashes( hash_hmac( "sha256", $advertisement->link, \IPS\Settings::i()->site_secret_key . 'a' ), (string) \IPS\Request::i()->key ) )
		{
			/* We need to update click count for this advertisement. Does it need to be shut off too due to hitting click maximum?
				Note that this needs to be done as a string to do "col=col+1", which is why we're not using the ActiveRecord save() method.
				Updating by doing col=col+1 is more reliable when there are several clicks at nearly the same time. */
			$update	= "ad_clicks=ad_clicks+1";

			if( $advertisement->maximum_unit == 'c' AND $advertisement->maximum_value > -1 AND $advertisement->clicks + 1 >= $advertisement->maximum_value )
			{
				$update	.= ", ad_active=0";
			}

			/* Update the database */
			\IPS\Db::i()->update( 'core_advertisements', $update, array( 'ad_id=?', $advertisement->id ) );

			/* And do the redirect */
			\IPS\Output::i()->redirect( \IPS\Http\Url::external( $advertisement->link ) );
		}
		/* If it doesn't validate, send the user to the index page */
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('') );
		}
	}
}