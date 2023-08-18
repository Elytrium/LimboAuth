<?php

// HTMLPurifier will automatically use http://php.net/idn_to_ascii if
// the IDN extension is installed. This is a small Polyfill to declare
// that method and use the php-punycode library so that IDNs can be
// supported in all environments

// Punycode does not support setting flags or variants, but we specify them as parameters for compatability with the PECL function.
// @see <a href='https://www.php.net/manual/en/function.idn-to-ascii'>PHP Documentation</a>

if ( !\defined( 'IDNA_NONTRANSITIONAL_TO_ASCII' ) )
{
	\define( 'IDNA_NONTRANSITIONAL_TO_ASCII', 16 );
}

if ( !\defined( 'INTL_IDNA_VARIANT_UTS46' ) )
{
	\define( 'INTL_IDNA_VARIANT_UTS46', 1 );
}

function idn_to_ascii( $string, $flags = 0, $variant = 1, &$idna_info = null )
{
	$punycode = new \TrueBV\Punycode;
	try
	{
		return $punycode->encode( $string );
	}
	catch ( \Exception $e )
	{
		return $string;
	}
}

