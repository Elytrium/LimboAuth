<?php
/**
 * @brief		Telephone input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Mar 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Telephone input class for Form Builder
 */
class _Tel extends Text
{
	/**
	 * @brief	Child default Options
	 */
	protected $childDefaultOptions = array( 'htmlAutocomplete' => "tel" );

	/**
	 * @brief	Dialling Codes
	 */
	public static $diallingCodes = array (
	  'AD' => 
	  array (
	    0 => '376',
	  ),
	  'AE' => 
	  array (
	    0 => '971',
	  ),
	  'AF' => 
	  array (
	    0 => '93',
	  ),
	  'AG' => 
	  array (
	    0 => '1268',
	  ),
	  'AI' => 
	  array (
	    0 => '1264',
	  ),
	  'AL' => 
	  array (
	    0 => '355',
	  ),
	  'AM' => 
	  array (
	    0 => '374',
	  ),
	  'AO' => 
	  array (
	    0 => '244',
	  ),
	  'AQ' => 
	  array (
	    0 => '672',
	  ),
	  'AR' => 
	  array (
	    0 => '54',
	  ),
	  'AS' => 
	  array (
	    0 => '684',
	  ),
	  'AT' => 
	  array (
	    0 => '43',
	  ),
	  'AU' => 
	  array (
	    0 => '61',
	  ),
	  'AW' => 
	  array (
	    0 => '297',
	  ),
	  'AX' => 
	  array (
	    0 => '358',
	  ),
	  'AZ' => 
	  array (
	    0 => '994',
	  ),
	  'BA' => 
	  array (
	    0 => '387',
	  ),
	  'BB' => 
	  array (
	    0 => '1246',
	  ),
	  'BD' => 
	  array (
	    0 => '880',
	  ),
	  'BE' => 
	  array (
	    0 => '32',
	  ),
	  'BF' => 
	  array (
	    0 => '226',
	  ),
	  'BG' => 
	  array (
	    0 => '359',
	  ),
	  'BH' => 
	  array (
	    0 => '973',
	  ),
	  'BI' => 
	  array (
	    0 => '257',
	  ),
	  'BJ' => 
	  array (
	    0 => '229',
	  ),
	  'BL' => 
	  array (
	    0 => '590',
	  ),
	  'BM' => 
	  array (
	    0 => '1441',
	  ),
	  'BN' => 
	  array (
	    0 => '673',
	  ),
	  'BO' => 
	  array (
	    0 => '591',
	  ),
	  'BQ' => 
	  array (
	    0 => '599',
	  ),
	  'BR' => 
	  array (
	    0 => '55',
	  ),
	  'BS' => 
	  array (
	    0 => '1242',
	  ),
	  'BT' => 
	  array (
	    0 => '975',
	  ),
	  'BV' => 
	  array (
	    0 => '47',
	  ),
	  'BW' => 
	  array (
	    0 => '267',
	  ),
	  'BY' => 
	  array (
	    0 => '375',
	  ),
	  'BZ' => 
	  array (
	    0 => '501',
	  ),
	  'CA' => 
	  array (
	    0 => '1',
	  ),
	  'CC' => 
	  array (
	    0 => '891',
	  ),
	  'CD' => 
	  array (
	    0 => '243',
	  ),
	  'CF' => 
	  array (
	    0 => '236',
	  ),
	  'CG' => 
	  array (
	    0 => '242',
	  ),
	  'CH' => 
	  array (
	    0 => '41',
	  ),
	  'CI' => 
	  array (
	    0 => '225',
	  ),
	  'CK' => 
	  array (
	    0 => '682',
	  ),
	  'CL' => 
	  array (
	    0 => '56',
	  ),
	  'CM' => 
	  array (
	    0 => '237',
	  ),
	  'CN' => 
	  array (
	    0 => '86',
	  ),
	  'CO' => 
	  array (
	    0 => '57',
	  ),
	  'CR' => 
	  array (
	    0 => '506',
	  ),
	  'CU' => 
	  array (
	    0 => '53',
	  ),
	  'CV' => 
	  array (
	    0 => '238',
	  ),
	  'CW' => 
	  array (
	    0 => '599',
	  ),
	  'CX' => 
	  array (
	    0 => '61',
	  ),
	  'CY' => 
	  array (
	    0 => '357',
	  ),
	  'CZ' => 
	  array (
	    0 => '420',
	  ),
	  'DE' => 
	  array (
	    0 => '49',
	  ),
	  'DJ' => 
	  array (
	    0 => '253',
	  ),
	  'DK' => 
	  array (
	    0 => '45',
	  ),
	  'DM' => 
	  array (
	    0 => '1767',
	  ),
	  'DO' => 
	  array (
	    0 => '1809',
	    1 => '1829',
	    2 => '1849',
	  ),
	  'DZ' => 
	  array (
	    0 => '213',
	  ),
	  'EC' => 
	  array (
	    0 => '593',
	  ),
	  'EE' => 
	  array (
	    0 => '372',
	  ),
	  'EG' => 
	  array (
	    0 => '20',
	  ),
	  'EH' => 
	  array (
	    0 => '212',
	  ),
	  'ER' => 
	  array (
	    0 => '291',
	  ),
	  'ES' => 
	  array (
	    0 => '34',
	  ),
	  'ET' => 
	  array (
	    0 => '251',
	  ),
	  'FI' => 
	  array (
	    0 => '358',
	  ),
	  'FJ' => 
	  array (
	    0 => '679',
	  ),
	  'FK' => 
	  array (
	    0 => '500',
	  ),
	  'FM' => 
	  array (
	    0 => '691',
	  ),
	  'FO' => 
	  array (
	    0 => '298',
	  ),
	  'FR' => 
	  array (
	    0 => '33',
	  ),
	  'GA' => 
	  array (
	    0 => '241',
	  ),
	  'GB' => 
	  array (
	    0 => '44',
	  ),
	  'GD' => 
	  array (
	    0 => '1473',
	  ),
	  'GE' => 
	  array (
	    0 => '995',
	  ),
	  'GF' => 
	  array (
	    0 => '594',
	  ),
	  'GG' => 
	  array (
	    0 => '44',
	  ),
	  'GH' => 
	  array (
	    0 => '233',
	  ),
	  'GI' => 
	  array (
	    0 => '350',
	  ),
	  'GL' => 
	  array (
	    0 => '299',
	  ),
	  'GM' => 
	  array (
	    0 => '220',
	  ),
	  'GN' => 
	  array (
	    0 => '224',
	  ),
	  'GP' => 
	  array (
	    0 => '590',
	  ),
	  'GQ' => 
	  array (
	    0 => '240',
	  ),
	  'GR' => 
	  array (
	    0 => '30',
	  ),
	  'GS' => 
	  array (
	    0 => '500',
	  ),
	  'GT' => 
	  array (
	    0 => '502',
	  ),
	  'GU' => 
	  array (
	    0 => '1671',
	  ),
	  'GW' => 
	  array (
	    0 => '245',
	  ),
	  'GY' => 
	  array (
	    0 => '592',
	  ),
	  'HK' => 
	  array (
	    0 => '852',
	  ),
	  'HM' => 
	  array (
	    0 => '61',
	  ),
	  'HN' => 
	  array (
	    0 => '504',
	  ),
	  'HR' => 
	  array (
	    0 => '385',
	  ),
	  'HT' => 
	  array (
	    0 => '509',
	  ),
	  'HU' => 
	  array (
	    0 => '36',
	  ),
	  'ID' => 
	  array (
	    0 => '62',
	  ),
	  'IE' => 
	  array (
	    0 => '353',
	  ),
	  'IL' => 
	  array (
	    0 => '972',
	  ),
	  'IM' => 
	  array (
	    0 => '44',
	  ),
	  'IN' => 
	  array (
	    0 => '91',
	  ),
	  'IO' => 
	  array (
	    0 => '246',
	  ),
	  'IQ' => 
	  array (
	    0 => '964',
	  ),
	  'IR' => 
	  array (
	    0 => '98',
	  ),
	  'IS' => 
	  array (
	    0 => '354',
	  ),
	  'IT' => 
	  array (
	    0 => '39',
	  ),
	  'JE' => 
	  array (
	    0 => '44',
	  ),
	  'JM' => 
	  array (
	    0 => '1876',
	  ),
	  'JO' => 
	  array (
	    0 => '962',
	  ),
	  'JP' => 
	  array (
	    0 => '81',
	  ),
	  'KE' => 
	  array (
	    0 => '254',
	  ),
	  'KG' => 
	  array (
	    0 => '996',
	  ),
	  'KH' => 
	  array (
	    0 => '855',
	  ),
	  'KI' => 
	  array (
	    0 => '686',
	  ),
	  'KM' => 
	  array (
	    0 => '269',
	  ),
	  'KN' => 
	  array (
	    0 => '1869',
	  ),
	  'KP' => 
	  array (
	    0 => '850',
	  ),
	  'KR' => 
	  array (
	    0 => '82',
	  ),
	  'KW' => 
	  array (
	    0 => '965',
	  ),
	  'KY' => 
	  array (
	    0 => '1345',
	  ),
	  'KZ' => 
	  array (
	    0 => '7',
	  ),
	  'LA' => 
	  array (
	    0 => '856',
	  ),
	  'LB' => 
	  array (
	    0 => '961',
	  ),
	  'LC' => 
	  array (
	    0 => '1758',
	  ),
	  'LI' => 
	  array (
	    0 => '423',
	  ),
	  'LK' => 
	  array (
	    0 => '94',
	  ),
	  'LR' => 
	  array (
	    0 => '231',
	  ),
	  'LS' => 
	  array (
	    0 => '266',
	  ),
	  'LT' => 
	  array (
	    0 => '370',
	  ),
	  'LU' => 
	  array (
	    0 => '352',
	  ),
	  'LV' => 
	  array (
	    0 => '371',
	  ),
	  'LY' => 
	  array (
	    0 => '218',
	  ),
	  'MA' => 
	  array (
	    0 => '212',
	  ),
	  'MC' => 
	  array (
	    0 => '377',
	  ),
	  'MD' => 
	  array (
	    0 => '373',
	  ),
	  'ME' => 
	  array (
	    0 => '382',
	  ),
	  'MF' => 
	  array (
	    0 => '1',
	  ),
	  'MG' => 
	  array (
	    0 => '261',
	  ),
	  'MH' => 
	  array (
	    0 => '692',
	  ),
	  'MK' => 
	  array (
	    0 => '389',
	  ),
	  'ML' => 
	  array (
	    0 => '223',
	  ),
	  'MM' => 
	  array (
	    0 => '95',
	  ),
	  'MN' => 
	  array (
	    0 => '976',
	  ),
	  'MO' => 
	  array (
	    0 => '853',
	  ),
	  'MP' => 
	  array (
	    0 => '1',
	  ),
	  'MQ' => 
	  array (
	    0 => '596',
	  ),
	  'MR' => 
	  array (
	    0 => '222',
	  ),
	  'MS' => 
	  array (
	    0 => '1664',
	  ),
	  'MT' => 
	  array (
	    0 => '356',
	  ),
	  'MU' => 
	  array (
	    0 => '230',
	  ),
	  'MV' => 
	  array (
	    0 => '960',
	  ),
	  'MW' => 
	  array (
	    0 => '265',
	  ),
	  'MX' => 
	  array (
	    0 => '52',
	  ),
	  'MY' => 
	  array (
	    0 => '60',
	  ),
	  'MZ' => 
	  array (
	    0 => '258',
	  ),
	  'NA' => 
	  array (
	    0 => '264',
	  ),
	  'NC' => 
	  array (
	    0 => '687',
	  ),
	  'NE' => 
	  array (
	    0 => '227',
	  ),
	  'NF' => 
	  array (
	    0 => '672',
	  ),
	  'NG' => 
	  array (
	    0 => '234',
	  ),
	  'NI' => 
	  array (
	    0 => '505',
	  ),
	  'NL' => 
	  array (
	    0 => '31',
	  ),
	  'NO' => 
	  array (
	    0 => '47',
	  ),
	  'NP' => 
	  array (
	    0 => '977',
	  ),
	  'NR' => 
	  array (
	    0 => '674',
	  ),
	  'NU' => 
	  array (
	    0 => '683',
	  ),
	  'NZ' => 
	  array (
	    0 => '64',
	  ),
	  'OM' => 
	  array (
	    0 => '968',
	  ),
	  'PA' => 
	  array (
	    0 => '507',
	  ),
	  'PE' => 
	  array (
	    0 => '51',
	  ),
	  'PF' => 
	  array (
	    0 => '689',
	  ),
	  'PG' => 
	  array (
	    0 => '675',
	  ),
	  'PH' => 
	  array (
	    0 => '63',
	  ),
	  'PK' => 
	  array (
	    0 => '92',
	  ),
	  'PL' => 
	  array (
	    0 => '48',
	  ),
	  'PM' => 
	  array (
	    0 => '508',
	  ),
	  'PN' => 
	  array (
	    0 => '64',
	  ),
	  'PR' => 
	  array (
	    0 => '1787',
	  ),
	  'PS' => 
	  array (
	    0 => '970',
	  ),
	  'PT' => 
	  array (
	    0 => '351',
	  ),
	  'PW' => 
	  array (
	    0 => '680',
	  ),
	  'PY' => 
	  array (
	    0 => '595',
	  ),
	  'QA' => 
	  array (
	    0 => '974',
	  ),
	  'RE' => 
	  array (
	    0 => '262',
	  ),
	  'RO' => 
	  array (
	    0 => '40',
	  ),
	  'RS' => 
	  array (
	    0 => '381',
	  ),
	  'RU' => 
	  array (
	    0 => '7',
	  ),
	  'RW' => 
	  array (
	    0 => '250',
	  ),
	  'SA' => 
	  array (
	    0 => '966',
	  ),
	  'SB' => 
	  array (
	    0 => '677',
	  ),
	  'SC' => 
	  array (
	    0 => '248',
	  ),
	  'SD' => 
	  array (
	    0 => '249',
	  ),
	  'SE' => 
	  array (
	    0 => '46',
	  ),
	  'SG' => 
	  array (
	    0 => '65',
	  ),
	  'SH' => 
	  array (
	    0 => '290',
	  ),
	  'SI' => 
	  array (
	    0 => '386',
	  ),
	  'SJ' => 
	  array (
	    0 => '47',
	  ),
	  'SK' => 
	  array (
	    0 => '421',
	  ),
	  'SL' => 
	  array (
	    0 => '232',
	  ),
	  'SM' => 
	  array (
	    0 => '378',
	  ),
	  'SN' => 
	  array (
	    0 => '221',
	  ),
	  'SO' => 
	  array (
	    0 => '252',
	  ),
	  'SR' => 
	  array (
	    0 => '597',
	  ),
	  'SS' => 
	  array (
	    0 => '211',
	  ),
	  'ST' => 
	  array (
	    0 => '239',
	  ),
	  'SV' => 
	  array (
	    0 => '503',
	  ),
	  'SX' => 
	  array (
	    0 => '508',
	  ),
	  'SY' => 
	  array (
	    0 => '963',
	  ),
	  'SZ' => 
	  array (
	    0 => '268',
	  ),
	  'TC' => 
	  array (
	    0 => '1649',
	  ),
	  'TD' => 
	  array (
	    0 => '235',
	  ),
	  'TF' => 
	  array (
	    0 => '262',
	  ),
	  'TG' => 
	  array (
	    0 => '228',
	  ),
	  'TH' => 
	  array (
	    0 => '66',
	  ),
	  'TJ' => 
	  array (
	    0 => '992',
	  ),
	  'TK' => 
	  array (
	    0 => '690',
	  ),
	  'TL' => 
	  array (
	    0 => '670',
	  ),
	  'TM' => 
	  array (
	    0 => '993',
	  ),
	  'TN' => 
	  array (
	    0 => '216',
	  ),
	  'TO' => 
	  array (
	    0 => '676',
	  ),
	  'TR' => 
	  array (
	    0 => '90',
	  ),
	  'TT' => 
	  array (
	    0 => '1868',
	  ),
	  'TV' => 
	  array (
	    0 => '688',
	  ),
	  'TW' => 
	  array (
	    0 => '886',
	  ),
	  'TZ' => 
	  array (
	    0 => '255',
	  ),
	  'UA' => 
	  array (
	    0 => '380',
	  ),
	  'UG' => 
	  array (
	    0 => '256',
	  ),
	  'UM' => 
	  array (
	    0 => '1',
	  ),
	  'US' => 
	  array (
	    0 => '1',
	  ),
	  'UY' => 
	  array (
	    0 => '598',
	  ),
	  'UZ' => 
	  array (
	    0 => '998',
	  ),
	  'VA' => 
	  array (
	    0 => '379',
	  ),
	  'VC' => 
	  array (
	    0 => '1784',
	  ),
	  'VE' => 
	  array (
	    0 => '58',
	  ),
	  'VG' => 
	  array (
	    0 => '1284',
	  ),
	  'VI' => 
	  array (
	    0 => '1',
	  ),
	  'VN' => 
	  array (
	    0 => '84',
	  ),
	  'VU' => 
	  array (
	    0 => '678',
	  ),
	  'WF' => 
	  array (
	    0 => '681',
	  ),
	  'WS' => 
	  array (
	    0 => '685',
	  ),
	  'XX' => 
	  array (
	    0 => '882',
	  ),
	  'YE' => 
	  array (
	    0 => '967',
	  ),
	  'YT' => 
	  array (
	    0 => '269',
	  ),
	  'ZA' => 
	  array (
	    0 => '27',
	  ),
	  'ZM' => 
	  array (
	    0 => '260',
	  ),
	  'ZW' => 
	  array (
	    0 => '263',
	  ),
	);
}