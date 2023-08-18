<?php
/**
 * @brief		Webhooks API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		5 Feb 2020
 */

namespace IPS\core\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Webhooks API
 */
class _webhooks extends \IPS\Api\Controller
{
	/**
	 * POST /core/webhooks
	 * Create a webhook
	 *
	 * @apiclientonly
	 * @reqapiparam	array	events	List of events to subscribe to
	 * @reqapiparam	string	url		URL to send webhook to
	 * @apiparam	string	content_header	The content type for the request.
	 * @return		\IPS\Api\Webhook
	 * @throws		1C293/1	NO_EVENTS	No events were specified
	 * @throws		1C293/2	INVALID_URL	The URL specified was not valid
	 */
	public function POSTindex()
	{				
		$events = \IPS\Request::i()->events;
		if ( !$events )
		{
			throw new \IPS\Api\Exception( 'NO_EVENTS', '1C293/1', 400 );
		}
		
		try
		{
			$url = new \IPS\Http\Url( \IPS\Request::i()->url );
		}
		catch ( \IPS\Http\Url\Exception $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_URL', '1C293/2', 400 );
		}
		
		$webhook = new \IPS\Api\Webhook;
		$webhook->api_key = $this->apiKey;
		$webhook->events = $events;
		$webhook->filters = ( \IPS\Request::i()->filters ?: array() );
		$webhook->url = $url;
		if( \IPS\Request::i()->content_header )
		{
			$webhook->content_type = \IPS\Request::i()->content_header;
		}
		$webhook->save();
		
		return new \IPS\Api\Response( 201, $webhook->apiOutput() );
	}
	
	/**
	 * DELETE /core/webhooks/{id}
	 * Deletes a webhook
	 *
	 * @apiclientonly
	 * @param		int		$id					ID Number
	 * @return		void
	 * @throws		1C293/3	INVALID_ID		The ID provided does not match any webhook
	 * @throws		3C293/4	WRONG_API_KEY	The API key making this request is not the API key that created the webhook
	 */
	public function DELETEitem( $id )
	{
		try
		{
			$webhook = \IPS\Api\Webhook::load( $id );
			
			if ( $webhook->api_key != $this->apiKey )
			{
				throw new \IPS\Api\Exception( 'WRONG_API_KEY', '3C293/4', 403 );
			}

			$webhook->delete();

			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '1C293/3', 404 );
		}
	}
}