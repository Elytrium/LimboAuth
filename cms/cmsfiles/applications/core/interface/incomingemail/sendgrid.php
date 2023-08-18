<?php
/**
 * @brief		Handle incoming email that has been POSTed to this script
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 November 2016
 */

define('REPORT_EXCEPTIONS', TRUE);
require_once str_replace( 'applications/core/interface/incomingemail/sendgrid.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';

if ( isset( $_POST['email'] ) )
{
	$incomingEmail = new \IPS\Email\Incoming\Email( $_POST['email'] );
	$incomingEmail->route();
}