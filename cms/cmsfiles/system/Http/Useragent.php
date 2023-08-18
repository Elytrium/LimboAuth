<?php
/**
 * @brief		User-Agent Management Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Aug 2013
 */

namespace IPS\Http;
 
/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * User-Agent Management Class
 */
class _Useragent
{
	/**
	 * @brief	Search engine spider?
	 */
	public $spider = FALSE;

	/**
	 * @brief	Browser name
	 */
	public $browser = NULL;
	
	/**
	 * @brief	Browser version
	 */
	public $browserVersion = NULL;
	
	/**
	 * @brief	Platform Name
	 */
	public $platform = NULL;
	
	/**
	 * @brief	Full user agent string
	 */
	public $useragent = NULL;
	
	/**
	 * @brief	Store parsed agents
	 */
	protected static $parsedAgents = array();
	
	/**
	 * Constructor
	 *
	 * @param	string	$userAgent	The user agent string
	 * @return	void
	 */
	protected function __construct( $userAgent )
	{
		$this->useragent = $userAgent;
	}
	
	/**
	 * Constructor
	 *
	 * @param	string	$userAgent	The user agent to parse (defaults to $_SERVER['HTTP_USER_AGENT'] if none supplied)
	 * @return	\IPS\Http\Useragent
	 */
	public static function parse( $userAgent=NULL )
	{
		$userAgent	= $userAgent ?: ( ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) ? $_SERVER['HTTP_USER_AGENT'] : '' );
		
		if ( ! isset( static::$parsedAgents[ $userAgent ] ) )
		{
			$obj	= new static( $userAgent );
			$obj->parseUserAgent();

			static::$parsedAgents[ $userAgent ] = $obj;
		}
		
		return static::$parsedAgents[ $userAgent ];
	}
	
	/**
	 * Parse the user agent data
	 *
	 * @return	void
	 */
	public function parseUserAgent()
	{
		/* Set basic data */
		require_once( \IPS\ROOT_PATH . '/system/3rd_party/PhpUserAgent/UserAgentParser.php' );
		$data = \donatj\UserAgent\parse_user_agent( $this->useragent );
		$this->platform = $data['platform'];
		$this->browser = $data['browser'];
		$this->browserVersion = $data['version'];
		
		/* Is this a spider? */
		foreach( $this->searchEngineUseragents as $key => $regex )
		{
			if( \is_array( $regex ) )
			{
				foreach( $regex as $_expression )
				{
					if( preg_match( "#" . $_expression . "#im", $this->useragent, $matches ) )
					{
						$this->spider = $key;
						break 2;
					}
				}
			}
			else
			{
				if( preg_match( "#" . $regex . "#im", $this->useragent, $matches ) )
				{
					$this->spider = $key;
					break;
				}
			}
		}
	}
	
	/**
	 * @brief	List of search engine user agent strings with regex to parse out the data.
	 * @note	Matches will be checked based on the order of this list - put more specific matches first and more generic matches later
	 * @note	If you wish to capture a version, be sure to have just ONE capturing parenthesis group (i.e. $matches[1])
	 * @note	You may put multiple regex definitions for a single key into an array
	 * @note	If the UA matches an entry in this array, $this->spider will be set to TRUE
	 */
	protected $searchEngineUseragents	= array(
		'about'			=> "Libby[_/ ]([0-9.]{1,10})",
		'adsense'		=> array( "Mediapartners-Google/([0-9.]{1,10})", "Mediapartners-Google" ),
		'ahrefs'		=> "AhrefsBot",
		'alexa'			=> "^ia_archive",
		'altavista'		=> "Scooter[ /\-]*[a-z]*([0-9.]{1,10})",
		'ask'			=> "Ask[ \-]?Jeeves",
		'baidu'			=> array( "^baiduspider\-", "baiduspider[ /]([0-9.]{1,10})" ),
		'bing'			=> array( "bingbot[ /]([0-9.]{1,10})", "msnbot(?:-media)?[ /]([0-9.]{1,10})" ),
		'brandwatch'	=> "magpie-crawler",
		'excite'		=> "Architext[ \-]?Spider",
		'google'		=> array( "Googl(?:e|ebot)(?:-Image|-Video|-News)?/([0-9.]{1,10})", "Googl(?:e|ebot)(?:-Image|-Video|-News)?/?" ),
		'googlemobile'	=> array( "Googl(?:e|ebot)(?:-Mobile)?/([0-9.]{1,10})", "Googl(?:e|ebot)(?:-Mobile)?/" ),
		'facebook'		=> "facebookexternalhit/([0-9.]{1,10})",
		'infoseek'		=> array( "SideWinder[ /]?([0-9a-z.]{1,10})", "Infoseek" ),
		'inktomi'		=> "slurp@inktomi\.com",
		'internetseer'	=> "^InternetSeer\.com",
		'look'			=> "www\.look\.com",
		'looksmart'		=> "looksmart-sv-fw",
		'lycos'			=> "Lycos_Spider_",
		'majestic'		=> "MJ12bot\/v([0-9.]{1,10})",
		'msproxy'		=> "MSProxy[ /]([0-9.]{1,10})",
		'webcrawl'		=> "webcrawl\.net",
		'websense'		=> "(?:Sqworm|websense|Konqueror/3\.(?:0|1)(?:\-rc[1-6])?; i686 Linux; 2002[0-9]{4})",
		'yahoo'			=> "Yahoo(?:.*?)(?:Slurp|FeedSeeker)",
		'yandex'		=> "Yandex(?:[^\/]+?)\/([0-9.]{1,10})",
		'seznam'		=> array( "SeznamBot[ /]([0-9.]{1,10})", "Seznam screenshot-generator ([0-9.]{1,10})" ),
		'dotbot'		=> "DotBot[ /]([0-9.]{1,10})",
		'sogou'			=> "Sogou web spider[ /]([0-9.]{1,10})",
		'isetallabot'	=> "istellabot[ /][a-z]([0-9.]{1,10})",
		'blexbot'		=> "BLEXBot[ /]([0-9.]{1,10})",
		'semrush'		=> "SemrushBot/([0-9.]{1,10})"
	);
	
	/**
	 * Human-Readable Browser Name
	 *
	 * @return	string
	 */
	public function __toString()
	{
		return \IPS\Member::loggedIn()->language()->addToStack( 'user_agent_parsed', FALSE, array( 'sprintf' => array( $this->browser, $this->browserVersion, $this->platform ) ) );
	}
		
	/**
	 * @brief	List of Facebook IP addresses
	 * @see		<a href='https://developers.facebook.com/docs/ApplicationSecurity/#facebook_scraper'>Facebook application security</a>
	 * @note	List pulled via suggested whois command on Dec 13 2016
	 */
	protected $facebookIpRange	= array('204.15.20.0/22', '69.63.176.0/20', '66.220.144.0/20', '66.220.144.0/21', '69.63.184.0/21', '69.63.176.0/21', '74.119.76.0/22', '69.171.255.0/24', '173.252.64.0/18', '69.171.224.0/19', '69.171.224.0/20', '103.4.96.0/22', '69.63.176.0/24', '173.252.64.0/19', '173.252.70.0/24', '31.13.64.0/18', '31.13.24.0/21', '66.220.152.0/21', '66.220.159.0/24', '69.171.239.0/24', '69.171.240.0/20', '31.13.64.0/19', '31.13.64.0/24', '31.13.65.0/24', '31.13.67.0/24', '31.13.68.0/24', '31.13.69.0/24', '31.13.70.0/24', '31.13.71.0/24', '31.13.72.0/24', '31.13.73.0/24', '31.13.74.0/24', '31.13.75.0/24', '31.13.76.0/24', '31.13.77.0/24', '31.13.96.0/19', '31.13.66.0/24', '173.252.96.0/19', '69.63.178.0/24', '31.13.78.0/24', '31.13.79.0/24', '31.13.80.0/24', '31.13.82.0/24', '31.13.83.0/24', '31.13.84.0/24', '31.13.85.0/24', '31.13.86.0/24', '31.13.87.0/24', '31.13.88.0/24', '31.13.89.0/24', '31.13.90.0/24', '31.13.91.0/24', '31.13.92.0/24', '31.13.93.0/24', '31.13.94.0/24', '31.13.95.0/24', '69.171.253.0/24', '69.63.186.0/24', '31.13.81.0/24', '179.60.192.0/22', '179.60.192.0/24', '179.60.193.0/24', '179.60.194.0/24', '179.60.195.0/24', '185.60.216.0/22', '45.64.40.0/22', '185.60.216.0/24', '185.60.217.0/24', '185.60.218.0/24', '185.60.219.0/24', '129.134.0.0/16', '157.240.0.0/16', '157.240.8.0/24', '157.240.0.0/24', '157.240.1.0/24', '157.240.2.0/24', '157.240.3.0/24', '157.240.4.0/24', '157.240.5.0/24', '157.240.6.0/24', '157.240.7.0/24', '157.240.9.0/24', '157.240.10.0/24', '204.15.20.0/22', '69.63.176.0/20', '69.63.176.0/21', '69.63.184.0/21', '66.220.144.0/20', '    69.63.176.0/20', '2620:0:1c00::/40', '2a03:2880::/32', '2a03:2880:fffe::/48', '2a03:2880:ffff::/48', '2620:0:1cff::/48', '2a03:2880:f000::/48', '2a03:2880:f001::/48', '2a03:2880:f002::/48', '2a03:2880:f003::/48', '2a03:2880:f004::/48', '2a03:2880:f005::/48', '2a03:2880:f006::/48', '2a03:2880:f007::/48', '2a03:2880:f008::/48', '2a03:2880:f009::/48', '2a03:2880:f00a::/48', '2a03:2880:f00b::/48', '2a03:2880:f00c::/48', '2a03:2880:f00d::/48', '2a03:2880:f00e::/48', '2a03:2880:f00f::/48', '2a03:2880:f010::/48', '2a03:2880:f011::/48', '2a03:2880:f012::/48', '2a03:2880:f013::/48', '2a03:2880:f014::/48', '2a03:2880:f015::/48', '2a03:2880:f016::/48', '2a03:2880:f017::/48', '2a03:2880:f018::/48', '2a03:2880:f019::/48', '2a03:2880:f01a::/48', '2a03:2880:f01b::/48', '2a03:2880:f01c::/48', '2a03:2880:f01d::/48', '2a03:2880:f01e::/48', '2a03:2880:f01f::/48', '2a03:2880:1000::/36', '2a03:2880:2000::/36', '2a03:2880:3000::/36', '2a03:2880:4000::/36', '2a03:2880:5000::/36', '2a03:2880:6000::/36', '2a03:2880:7000::/36', '2a03:2880:f020::/48', '2a03:2880:f021::/48', '2a03:2880:f022::/48', '2a03:2880:f023::/48', '2a03:2880:f024::/48', '2a03:2880:f025::/48', '2a03:2880:f026::/48', '2a03:2880:f027::/48', '2a03:2880:f028::/48', '2a03:2880:f029::/48', '2a03:2880:f02b::/48', '2a03:2880:f02c::/48', '2a03:2880:f02d::/48', '2a03:2880:f02e::/48', '2a03:2880:f02f::/48', '2a03:2880:f030::/48', '2a03:2880:f031::/48', '2a03:2880:f032::/48', '2a03:2880:f033::/48', '2a03:2880:f034::/48', '2a03:2880:f035::/48', '2a03:2880:f036::/48', '2a03:2880:f037::/48', '2a03:2880:f038::/48', '2a03:2880:f039::/48', '2a03:2880:f03a::/48', '2a03:2880:f03b::/48', '2a03:2880:f03c::/48', '2a03:2880:f03d::/48', '2a03:2880:f03e::/48', '2a03:2880:f03f::/48', '2401:db00::/32', '2a03:2880::/36', '2803:6080::/32', '2a03:2880:f100::/48', '2a03:2880:f200::/48', '2a03:2880:f101::/48', '2a03:2880:f201::/48', '2a03:2880:f102::/48', '2a03:2880:f202::/48', '2a03:2880:f103::/48', '2a03:2880:f203::/48', '2a03:2880:f104::/48', '2a03:2880:f204::/48', '2a03:2880:f107::/48', '2a03:2880:f207::/48', '2a03:2880:f108::/48', '2a03:2880:f208::/48', '2a03:2880:f109::/48', '2a03:2880:f209::/48', '2a03:2880:f10a::/48', '2a03:2880:f20a::/48', '2a03:2880:f10b::/48', '2a03:2880:f20b::/48', '2a03:2880:f10d::/48', '2a03:2880:f20d::/48', '2a03:2880:f10e::/48', '2a03:2880:f20e::/48', '2a03:2880:f10f::/48', '2a03:2880:f20f::/48', '2a03:2880:f110::/48', '2a03:2880:f210::/48', '2a03:2880:f111::/48', '2a03:2880:f211::/48', '2a03:2880:f112::/48', '2a03:2880:f212::/48', '2a03:2880:f114::/48', '2a03:2880:f214::/48', '2a03:2880:f115::/48', '2a03:2880:f215::/48', '2a03:2880:f116::/48', '2a03:2880:f216::/48', '2a03:2880:f117::/48', '2a03:2880:f217::/48', '2a03:2880:f118::/48', '2a03:2880:f218::/48', '2a03:2880:f119::/48', '2a03:2880:f219::/48', '2a03:2880:f11a::/48', '2a03:2880:f21a::/48', '2a03:2880:f11f::/48', '2a03:2880:f21f::/48', '2a03:2880:f121::/48', '2a03:2880:f221::/48', '2a03:2880:f122::/48', '2a03:2880:f222::/48', '2a03:2880:f123::/48', '2a03:2880:f223::/48', '2a03:2880:f10c::/48', '2a03:2880:f20c::/48', '2a03:2880:f126::/48', '2a03:2880:f226::/48', '2a03:2880:f105::/48', '2a03:2880:f205::/48', '2a03:2880:f125::/48', '2a03:2880:f225::/48', '2a03:2880:f106::/48', '2a03:2880:f206::/48', '2a03:2880:f11b::/48', '2a03:2880:f21b::/48', '2a03:2880:f113::/48', '2a03:2880:f213::/48', '2a03:2880:f11c::/48', '2a03:2880:f21c::/48', '2a03:2880:f128::/48', '2a03:2880:f228::/48', '2a03:2880:f02a::/48', '2a03:2880:f12a::/48', '2a03:2880:f22a::/48');

	/**
	 * Verify a supplied IP address is within the Facebook range
	 *
	 * @param	string	$ip		IP address to check
	 * @return	bool
	 * @see		<a href='http://stackoverflow.com/questions/7951061/matching-ipv6-address-to-a-cidr-subnet'>Stackoverflow: check IPv6 against CIDR</a>
	 * @see		<a href='http://stackoverflow.com/questions/594112/matching-an-ip-to-a-cidr-mask-in-php5'>Stackoverflow: check IPv4 against CIDR</a>
	 */
	public function facebookIpVerified( $ip )
	{
		/* Is this an IPv6 address? */
		if( \strpos( $ip, ':' ) !== FALSE )
		{
			$ip	= $this->_convertCompressedIpv6ToBits( inet_pton( $ip ) );

			foreach( $this->facebookIpRange as $range )
			{
				if( \strpos( $range, ':' ) === FALSE )
				{
					continue;
				}

				list( $net, $maskBits )	= explode( '/', $range );

				$net	= $this->_convertCompressedIpv6ToBits( inet_pton( $net ) );

				if( $ip == $net )
				{
					return TRUE;
				}
			}
		}
		else
		{
			foreach( $this->facebookIpRange as $range )
			{
				if( \strpos( $range, ':' ) !== FALSE )
				{
					continue;
				}

				list( $net, $maskBits )	= explode( '/', $range );

				if( ( ip2long( $ip ) & ~( ( 1 << ( 32 - $maskBits ) ) - 1 ) ) == ip2long( $net ) )
				{
					return TRUE;
				}
			}
		}

		return FALSE;
	}

	/**
	 * Convert an IPv6 address to bits
	 *
	 * @param	string	$ip		Compressed IPv6 address
	 * @return	array
	 * @see		<a href='http://stackoverflow.com/questions/7951061/matching-ipv6-address-to-a-cidr-subnet'>Stackoverflow: check IPv6 against CIDR</a>
	 */
	protected function _convertCompressedIpv6ToBits( $ip )
	{
		$unpackedAddress	= unpack( 'A16', $ip );
		$unpackedAddress	= str_split( $unpackedAddress[1] );
		$ipAddress			= '';

		foreach( $unpackedAddress as $char )
		{
			$ipAddress	.= str_pad( decbin( \ord( $char ) ), 8, '0', STR_PAD_LEFT );
		}

		return $ipAddress;
	}
}