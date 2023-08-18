<?php
/**
 * @brief		Stripe Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		20 Jul 2017
 */

\define('REPORT_EXCEPTIONS', TRUE);
require_once '../../../../init.php';
\IPS\Session\Front::i();

\IPS\Output::i()->pageCaching = FALSE;

/* Little IN_DEV helper to prevent firing this until we've triggered the webhooks */
if ( \IPS\IN_DEV and !isset( \IPS\Request::i()->indevconfirm ) )
{
	echo "<a href='" . \IPS\Request::i()->url()->setQueryString( 'indevconfirm', 1 ) . "'>Continue</a>";
	exit;
}

/* Wait a few seconds so the webhook has time to come through */
sleep( 5 );

/* Load Source */
try
{
	$transaction = \IPS\nexus\Transaction::load( \IPS\Request::i()->nexusTransactionId );
	$source = $transaction->method->api( 'sources/' . preg_replace( '/[^A-Z0-9_]/i', '', \IPS\Request::i()->source ), NULL, 'get' );
	if ( $source['client_secret'] != \IPS\Request::i()->client_secret )
	{
		throw new \Exception;
	}
}
catch ( \Exception $e )
{
	\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=nexus&module=checkout&controller=checkout&do=transaction&id=&t=" . \IPS\Request::i()->nexusTransactionId, 'front', 'nexus_checkout', \IPS\Settings::i()->nexus_https ) );
}

/* If we're a guest, but the transaction belongs to a member, that's because the webhook has
	processed the transaction and created an account - so we need to log the newly created
	member in. This is okay to do because we've checked client_secret is correct, meaning
	we know this is a genuine redirect back from Stripe after payment of this transaction */
if ( !\IPS\Member::loggedIn()->member_id and $transaction->member->member_id )
{
	\IPS\Session::i()->setMember( $transaction->member );
	\IPS\Member\Device::loadOrCreate( $transaction->member, FALSE )->updateAfterAuthentication( NULL );
}

/* And then send them on */
if ( $source['status'] === 'failed' )
{
	\IPS\Output::i()->redirect( $transaction->invoice->checkoutUrl() );
}
else
{
	\IPS\Output::i()->redirect( $transaction->url()->setQueryString( 'pending', 1 ) );
}