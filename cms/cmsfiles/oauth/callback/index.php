<?php
/**
 * @brief     OAuth Client Redirection Endpoint
 * @author    <a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright  (c) Invision Power Services, Inc.
 * @license       https://www.invisioncommunity.com/legal/standards/
 * @package       Invision Community
 * @since     31 May 2017
 */
\define('REPORT_EXCEPTIONS', TRUE);
require '../../init.php';
\IPS\Session\Front::i();

if ( isset( \IPS\Request::i()->state ) and $explodedData = explode( '-', \IPS\Request::i()->state ) and \count( $explodedData ) === 4 and $destination = @base64_decode( $explodedData[1] ) )
{
	try
	{
		$destination = \IPS\Http\Url::createFromString( $destination )->setQueryString( array(
			'_processLogin'    => $explodedData[0],
			'csrfKey'     => $explodedData[2],
			'ref'        => $explodedData[3],
		) );
		if ( !( $destination instanceof \IPS\Http\Url\Internal ) )
		{
			throw new \Exception;
		}

		if ( isset( \IPS\Request::i()->error ) )
		{
			foreach ( array( 'error', 'error_description', 'error_uri' ) as $k )
			{
				if ( isset( \IPS\Request::i()->$k ) )
				{
					$destination = $destination->setQueryString( $k, \IPS\Request::i()->$k );
				}
			}
		}

		/* OAuth 2 */
		if ( isset( \IPS\Request::i()->access_token ) )
		{
			foreach ( array( 'access_token', 'token_type', 'expires_in', 'scope', 'state' ) as $k )
			{
				if ( isset( \IPS\Request::i()->$k ) )
				{
					$destination = $destination->setQueryString( $k, \IPS\Request::i()->$k );
				}
			}
		}
		/* OAuth 1 */
		elseif ( isset( \IPS\Request::i()->oauth_token ) )
		{
			foreach ( array( 'oauth_token', 'oauth_verifier', 'state' ) as $k )
			{
				if ( isset( \IPS\Request::i()->$k ) )
				{
					$destination = $destination->setQueryString( $k, \IPS\Request::i()->$k );
				}
			}
		}
		elseif ( isset( \IPS\Request::i()->code ) )
		{
			/* Sign in with Apple does not make name available any later in the process */
			if ( isset( \IPS\Request::i()->user ) )
			{
				$_SESSION['oauth_user'] = \IPS\Request::i()->user;
			}

			$destination = $destination->setQueryString( 'code', \IPS\Request::i()->code );
		}



		if ( \IPS\Request::i()->requestMethod() === 'POST' )
		{

			@header( "Cache-control: no-cache, no-store, must-revalidate, max-age=0, s-maxage=0" );
			@header( "Expires: 0" );
			$queryStringComponents = $destination->queryString;
			$loading = \IPS\Member::loggedIn()->language()->get('loading');
			$message = \IPS\Member::loggedIn()->language()->get('oauth_post_redirect_submit');
			$destination = \IPS\Http\Url::createFromString( @base64_decode( $explodedData[1] ) );
			$output = <<<HTML
<!DOCTYPE html>
<html>
   <head>
      <title>{$loading}</title>
   </head>
   <body>
      <noscript>{$message}</noscript>
        <form style="display: none" action="{$destination}" method="POST">
HTML;
			foreach ( $queryStringComponents as $k => $v )
			{
				if ( $k === 'ref' )
				{
					if ( !$v )
					{
						$v = base64_encode( (string) \IPS\Http\Url::baseUrl() );
					}
				}
				$output .= <<<HTML
            <input name="{$k}" value="{$v}" >
HTML;

			}
			$output .= <<<HTML
        <input type="submit" value="{$message}" />
    </form>
    <script>
        const form = document.querySelector('form');
        form.submit();
    </script>
   </body>
</html>
HTML;
			echo $output;
			exit;
		}

		\IPS\Output::i()->redirect( $destination );
		exit;
	}
	catch ( \Exception $e ) {}
}

$url = (string) \IPS\Http\Url::internal( 'oauth/callback/', 'none' );

/* Force no caching */
@header( "Cache-control: no-cache, no-store, must-revalidate, max-age=0, s-maxage=0" );
@header( "Expires: 0" );
?><!DOCTYPE html>
<html>
<head>
	<title><?php echo \IPS\Member::loggedIn()->language()->get( 'loading' ); ?></title>
	<script>
		if ( window.location.hash ) {
			var hash = window.location.hash.substr( 0, 1 ) == '#' ? window.location.hash.substr( 1 ) : window.location.hash;
			window.location = "<?php echo $url; ?>?" + hash;
		}
	</script>
</head>
<body>
<noscript><?php echo \IPS\Member::loggedIn()->language()->get( 'oauth_implicit_no_js' ); ?></noscript>
</body>
</html>