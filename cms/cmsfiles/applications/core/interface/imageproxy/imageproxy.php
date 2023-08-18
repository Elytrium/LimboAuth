<?php

/**
 * The image proxy feature has been removed
 */

if( isset( $_SERVER['SERVER_PROTOCOL'] ) and \strstr( $_SERVER['SERVER_PROTOCOL'], '/1.0' ) !== false )
{
	header( "HTTP/1.0 410 Gone" );
}
else
{
	header( "HTTP/1.1 410 Gone" );
}

exit;