<?php
/**
 * @brief		Pixabay AJAX functions Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Jan 2020
 */
 
namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Editor AJAX functions Controller
 */
class _pixabay extends \IPS\Dispatcher\Controller
{
	/**
	 * Show the dialog window
	 *
	 * @return void
	 * @throws \Exception
	 */
	protected function manage()
	{
		if ( \IPS\Settings::i()->pixabay_enabled )
		{
			$output = \IPS\Theme::i()->getTemplate( 'system' )->pixabay( \IPS\Request::i()->uploader );
			\IPS\Output::i()->sendOutput( $output );
		}
	}

	/**
	 * Search pixabay
	 *
	 * @return void
	 * @throws \Exception
	 */
	protected function search()
	{
		if ( \IPS\Settings::i()->pixabay_enabled )
		{
			$limit = isset( \IPS\Request::i()->limit ) ? \IPS\Request::i()->limit : 20;
			$offset = isset( \IPS\Request::i()->offset ) ? \IPS\Request::i()->offset : 0;
			$query = isset( \IPS\Request::i()->search ) ? \IPS\Request::i()->search : '';
			$url = \IPS\Http\Url::external( "https://pixabay.com/api/" );

			$parameters = array(
				'key' => \IPS\Settings::i()->pixabay_apikey,
				'image_type' => 'photo',
				'per_page' => 20,
				'page' => ( $offset ) ? ceil( $offset / 20 ) + 1 : 1,
				'safesearch' => ( \IPS\Settings::i()->pixabay_safesearch ) ? 'true' : 'false',
			);

			$parameters['q'] = urlencode( $query );
			$cacheKey = 'pixabay_' . md5( json_encode( $parameters ) );

			try
			{
				$request = \IPS\Data\Cache::i()->getWithExpire( $cacheKey, TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$url = $url->setQueryString($parameters);
				$request = json_decode( $url->request()->get()->content, true );

				\IPS\Data\Cache::i()->storeWithExpire( $cacheKey, $request, \IPS\DateTime::create()->add( new \DateInterval('P1D') ), TRUE );
			}

			if ( isset( $request['message'] ) AND $request['message'] )
			{
				\IPS\Output::i()->json( array('error' => $request['message'] ) );
			}


			$results = array( 'pagination' => array( 'total_count' => $request['total'] ) );
			foreach ( $request['hits'] as $row )
			{
				$results['images'][] = array(
					'thumb'	=> $row['webformatURL'],
					'url'   => $row['largeImageURL'],
					'imgid'	=> $row['id'],
				);
			}

			if ( empty( $results['images'] ) )
			{
				\IPS\Output::i()->json( array( 'error' => \IPS\Theme::i()->getTemplate( 'system', 'core' )->noResults() ) );
			}

			\IPS\Output::i()->json( $results );
		}
	}

	/**
	 * Search pixabay
	 *
	 * @return void
	 */
	protected function getById()
	{
		if ( isset( \IPS\Request::i()->id ) )
		{

			$url = \IPS\Http\Url::external( "https://pixabay.com/api/" )->setQueryString( array(
				'key' => \IPS\Settings::i()->pixabay_apikey,
				'id' => \IPS\Request::i()->id
			) );
			

			$request = json_decode( $url->request()->get()->content, true );
			
			$url = NULL;
			$filename = NULL;
			foreach ( $request['hits'] as $row )
			{
				$url = $row['largeImageURL'];
				
				if ( ! empty( $row['previewURL'] ) )
				{
					$filename = str_replace( '_150.', '.', basename( $row['previewURL'] ) );
				}
			}

			/* Now get the URL contents */
			$data = \IPS\Http\Url::external( $url )->request()->get();
			
			if ( ! $filename )
			{
				$filename = basename( $url );
			}

			list( $image, $type ) = explode( '/', $data->httpHeaders['Content-Type'] );
			\IPS\Output::i()->json( array( 'content' => base64_encode( $data->content ), 'type' => $data->httpHeaders['Content-Type'], 'imageType' => $type, 'filename' => $filename ) );
		}
	}
}