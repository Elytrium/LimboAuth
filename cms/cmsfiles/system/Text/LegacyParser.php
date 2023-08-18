<?php
/**
 * @brief		Legacy Text Parser
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Jun 2013
 */

namespace IPS\Text;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Legacy Text Parser
 */
class _LegacyParser
{	
	/**
	 * @brief	Maximum number of embeds we will try.
	 * @note	Attempting to pull too many during the rebuild process can easily time out
	 */
	public $maxEmbeds	= 10;

	/**
	 * Parse statically
	 *
	 * @param	string				$value				The value to parse
	 * @param	\IPS\Member|null	$member				The member posting. NULL will use currently logged in member.
	 * @param	bool				$allowHtml			Allow HTML
	 * @param	string				$attachClass		Key to use for attachments
	 * @param	int					$id1				ID1 to use for attachments
	 * @param	int					$id2				ID2 to use for attachments
	 * @param	int|NULL			$id3				ID3 to use for attachments
	 * @param	string				$itemClass			The *item* classname (e.g. IPS\forums\Topic)
	 * @return	string
	 * @see		__construct
	 */
	public static function parseStatic( $value, $member=NULL, $allowHtml=FALSE, $attachClass=null, $id1=0, $id2=0, $id3=NULL, $itemClass=NULL )
	{
		$obj = new static( $member, $allowHtml, $attachClass, $id1, $id2, $id3, $itemClass );
		return $obj->parse( $value );
	}
	
	/**
	 * @brief	BBcodes
	 */
	public $bbcodes	= NULL;

	/**
	 * @brief	Emoticons
	 */
	public $emoticons	= NULL;

	/**
	 * Can use plugin?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	string		$bbcode	BBCode
	 * @return	bool
	 */
	public static function canUse( \IPS\Member $member, $bbcode )
	{
		foreach( static::$bbcodes as $code )
		{
			if( $code['bbcode_tag'] == $bbcode OR ( $code['bbcode_aliases'] AND \in_array( $bbcode, explode( ',', $code['bbcode_aliases'] ) ) ) )
			{
				if( $code['bbcode_groups'] == 'all' )
				{
					return true;
				}

				if( $member->inGroup( explode( ',', $bbcode['bbcode_groups'] ) ) )
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @brief	Member
	 */
	protected $member		= NULL;

	/**
	 * @brief	Allow HTML
	 */
	protected $allowHtml	= FALSE;

	/**
	 * @brief	Parser object
	 */
	public $parser		= NULL;

	/**
	 * @brief	Key for attachments
	 */
	protected $attachClass		= NULL;
	
	/**
	 * @brief	Item classname
	 */
	protected $itemClass	= NULL;

	/**
	 * @brief	ID1 to use for attachments
	 */
	protected $idOne			= 0;

	/**
	 * @brief	ID2 to use for attachments
	 */
	protected $idTwo			= 0;
	
	/**
	 * @brief	ID3 to use for attachments
	 */
	protected $idThree			= 0;

	/**
	 * @brief   Are Acronyms Supported?
	 */
	protected static $_newAcronyms = null;

	/**
	 * Constructor
	 *
	 * @param	\IPS\Member|null	$member				The member posting. NULL will use guest.
	 * @param	bool				$allowHtml			Allow HTML
	 * @param	string				$attachClass		Key to use for attachments
	 * @param	int					$id1				ID1 to use for attachments
	 * @param	int					$id2				ID2 to use for attachments
	 * @param	int|NULL			$id3				ID3 to use for attachments
	 * @param	string				$itemClass			The *item* classname (e.g. IPS\forums\Topic)
	 * @return	void
	 */
	public function __construct( $member=NULL, $allowHtml=FALSE, $attachClass=null, $id1=0, $id2=0, $id3=NULL, $itemClass=NULL )
	{
		/* Get member */
		$this->member = $member === NULL ? \IPS\Member::load(0) : $member;

		/* Remember if we allow HTML */
		$this->allowHtml	= $allowHtml;

		/* Set attachment data */
		$this->attachClass	= $attachClass;
		$this->idOne			= $id1;
		$this->idTwo			= $id2;
		$this->idThree			= $id3;
		
		/* And item class */
		$this->itemClass = $itemClass;

		/* Grab legacy custom bbcodes */
		try
		{
			$this->bbcodes	= iterator_to_array( \IPS\Db::i()->select( '*', 'custom_bbcode' )->setKeyField( 'bbcode_tag' ) );
		}
		catch( \IPS\Db\Exception $e )
		{
			$this->bbcodes	= array();
		}

		/* Grab emoticons */
		try
		{
			$this->emoticons	= iterator_to_array( \IPS\Db::i()->select( '*', 'core_emoticons' )->setKeyField( 'typed' ) );
		}
		catch( \IPS\Db\Exception $e )
		{
			$this->emoticons	= array();
		}

		/* The `a_type` column is added in 4.5, so we need to account for this if running the legacy parser during an older upgrade */
		if( static::$_newAcronyms === NULL )
		{
			static::$_newAcronyms = \IPS\Db::i()->checkForColumn( 'core_acronyms', 'a_type' );
		}

		/* Grab a new parser object */
		\IPS\Text\Parser::$requestTimeout = 1;
		$this->parser	= new \IPS\Text\Parser( TRUE, $this->idOne ? array( $this->idOne, $this->idTwo, $this->idThree ) : NULL, $this->member, $this->attachClass, TRUE, ( $this->allowHtml ? FALSE : TRUE ), NULL, static::$_newAcronyms );

		/* If we're in the upgrader and the new acronym support hasn't been added yet, populate acronyms manually */
		if( !static::$_newAcronyms )
		{
			$this->parser->caseSensitiveAcronyms = iterator_to_array( \IPS\Db::i()->select( array( 'a_short', 'a_long', "'acronym' as `a_type`" ), 'core_acronyms', array( 'a_casesensitive=1' ) )->setKeyField( 'a_short' ) );
			
			$this->parser->caseInsensitiveAcronyms = array();
			foreach ( \IPS\Db::i()->select( array( 'a_short', 'a_long', "'acronym' as `a_type`" ), 'core_acronyms', array( 'a_casesensitive=0' ) )->setKeyField( 'a_short' ) as $k => $v )
			{
				$this->parser->caseInsensitiveAcronyms[ mb_strtolower( $k ) ] = $v;
			} 
		}

		/* We need to force bbcode to parse for the legacy parser */
		$this->parser->forceBbcodeEnabled = TRUE;

		$updatedBbcodeTags	= array_keys( $this->parser->bbcodeTags( $this->member, TRUE ) );

		foreach( $updatedBbcodeTags as $tag )
		{
			if( isset( $this->bbcodes[ $tag ] ) AND $tag != 'media' )
			{
				unset( $this->bbcodes[ $tag ] );
			}
		}
	}
	
	/**
	 * Parse
	 *
	 * @param	string	$value	Text to parse
	 * @return	string
	 */
	public function parse( $value )
	{
		/* We have to fix up newlines a little bit */
		$codeBlocks = array();
		$value	= str_replace( array( "\r\n", "\r" ), "\n", $value );

		if ( ! \strstr( $value, "\n" ) && ( \stristr( $value, '<br>' ) || \stristr( $value, '<br />' ) ) )
		{
			$value = \str_ireplace( array( '<br>', '<br />' ), "\n", $value );
		}
		else
		{
			if ( \stristr( $value, '<br>' ) || \stristr( $value, '<br />' ) || \stristr( $value, '<p>' ) )
			{
				/* Protect code tags first... */
				$value = preg_replace_callback( "/\[(?:code|codebox|sql|php|xml|html).*?\](.+?)\[\/(?:code|codebox|sql|php|xml|html)\]/ims", function( $match ) use ( &$codeBlocks ) {
					$hash = md5( $match[0] . mt_rand() );
					$codeBlocks[ $hash ] = ( \stristr( $match[0], '<br>' ) || \stristr( $match[0], '<br />' ) || \stristr( $match[0], '<p>' ) ) ? \str_replace( "\n", "", $match[0] ) : $match[0];
					return $hash;
				}, $value );

				$value = preg_replace_callback( "/\<pre\s+?class=[\"']_prettyXprint(?:.*?)[\"']\>(.+?)\<\/pre\>/ims", function( $match ) use ( &$codeBlocks ) {
					$hash = md5( $match[0] . mt_rand() );
					$codeBlocks[ $hash ] = ( \stristr( $match[0], '<br>' ) || \stristr( $match[0], '<br />' ) || \stristr( $match[0], '<p>' ) ) ? \str_replace( "\n", "", $match[0] ) : $match[0];
					return $hash;
				}, $value );

				$value = \str_replace( "\n", "", $value );
			}
		}

		/* If we allow HTML we have to swap out entities - codeblocks are already protected at this point */
		if( $this->allowHtml )
		{
			/* Fixes an issue with legacy posts */
			$value = str_replace( '&quot;', '"', $value );
			$value = str_replace( '&lt;', '<', $value );
			$value = str_replace( '&gt;', '>', $value );
		}

		$value = nl2br( $value );

		/* If we had any code blocks, put them back now */
		foreach( $codeBlocks as $hash => $code )
		{
			$value = \str_replace( $hash, $code, $value );
		}

		/* In case the content rebuild has ran or partially ran on this content... */
		$value = preg_replace( '#<([^>]+?)(href|src)=(\'|")<fileStore\.([\d\w\_]+?)>/#i', '<\1\2=\3%7BfileStore.\4%7D/', $value );
		$value = preg_replace( '#<([^>]+?)(href|src)=(\'|")<___base_url___>/#i', '<\1\2=\3%7B___base_url___%7D/', $value );
		$value = preg_replace( '#<([^>]+?)(data-fileid)=(\'|")<___base_url___>/#i', '<\1\2=\3%7B___base_url___%7D/', $value );
		
		/* Start with legacy emoticon parsing */
		$value = str_replace( "<#EMO_DIR#>", "&lt;#EMO_DIR&gt;", $value );
		$value = preg_replace( "#(\s)?<([^>]+?)emoid=\"(.+?)\"([^>]*?)".">(\s)?#is", "\\1\\3\\5", $value );

		preg_match_all( "#(<img(?:[^>]+?)class=['\"]bbc_emoticon[\"'](?:[^>]+?)alt=['\"](.+?)[\"'](?:[^>]+?)?>)#is", $value, $matches );

		if( \is_array($matches[1]) AND \count($matches[1]) )
		{
			foreach( $matches[1] as $index => $match )
			{
				$value	= str_replace( $match, $matches[2][ $index ], $value );
			}
		}

		/* Parse emoticons */
		usort( $this->emoticons, function( $a, $b ){
			if ( mb_strlen( $a['typed'] ) == mb_strlen( $b['typed'] ) )
			{
				return 0;
			}

			return ( mb_strlen( $a['typed'] ) > mb_strlen( $b['typed'] ) ) ? -1 : 1;
		});

		foreach( $this->emoticons as $emoticon )
		{
			$code	= str_replace( '<', '&lt;', str_replace( '>', '&gt;', $emoticon['typed'] ) );	

			if( !$code or !\stristr( $value, $code ) )
			{
				continue;
			}

			if ( ! \IPS\File::isFullyQualifiedUrl( $emoticon['image'] ) )
			{
				$emoticon['image'] = \IPS\File::getClass('core_Emoticons')->baseUrl() . '/' . $emoticon['image'];
			}

			$quotedCode = preg_quote( $code, '/' );
			$value = preg_replace( "/(^|[^a-zA-Z0-9\'\"\/]){$quotedCode}($|[^a-zA-Z0-9\'\"\/])/i", "$1<img src='" . $emoticon['image'] . "' alt='" . $code . "'>$2", $value );
		}

		/* Old char conversions */
		$value = str_replace( "&#160;", " ", $value );
		$value = str_replace( "&#39;", "'", $value );
		$value = str_replace( "&#58;", ":", $value );
		$value = str_replace( "&amp;", "&", $value );
		/* The macro is not replaced out at runtime in 4.x and if we've made it to this point it is because the direct URL is still present
			and the emoticon was not properly embedded. When trying to parse a URL with this macro in it, an error is thrown and the image will
			always be broken, so our best option is to swap the macro out with the default folder ("default") as this will work for most users
			and there is no other option to look up the emoticon at this point */
		$value = str_replace( "&lt;#EMO_DIR&gt;", "default", $value );

		/* Unconvert code */
		$value = preg_replace_callback( "#<!--sql-->(.+?)<!--sql1-->(.+?)<!--sql2-->(.+?)<!--sql3-->#is", array( $this, '_parseOldCode'), $value );
		$value = preg_replace_callback( "#<!--html-->(.+?)<!--html1-->(.+?)<!--html2-->(.+?)<!--html3-->#is", array( $this, '_parseOldCode'), $value );
		$value = preg_replace_callback( "#<!--Flash (.+?)-->.+?<!--End Flash-->#", array( $this, '_parseOldFlash'), $value );
		$value = preg_replace( "#<!--c1-->(.+?)<!--ec1-->#", '[code]', $value );
		$value = preg_replace( "#<!--c2-->(.+?)<!--ec2-->#", '[/code]', $value );
		$value = preg_replace( "#<div class=[\"']codetop['\"]>(.+?)</div><div class=[\"']codemain['\"] style=[\"']height:200px;white\-space:pre;overflow:auto['\"]>(.+?)</div>#is", "[code]\\2[/code]", $value );

		/* Capital inconsistency */
		foreach( array_keys( $this->parser->bbcodeTags( $this->member, TRUE ) ) as $bbcode )
		{
			$value = str_replace( '[' . mb_strtoupper( $bbcode ), '[' . $bbcode, $value );
			$value = str_replace( '[/' . mb_strtoupper( $bbcode ), '[/' . $bbcode, $value );
		}

		/* Preserve code data */
		$codeboxes = array();
		preg_match_all( "/\[(code|codebox|sql|php|xml|html)(.*?)\](.+?)\[\/(code|codebox|sql|php|xml|html)\]/ims", $value, $matches );

		foreach( $matches[0] as $k => $m )
		{
			$c = \count( $codeboxes );
			$codeboxes[ $c ] = $m;

			$replacement = '<!--codeboxes{' . $c . '}-->';

			$value = str_replace( $m, $replacement, $value );
		}

		preg_match_all( "/\<pre\s+?class=[\"']_prettyXprint(?:.*?)[\"']\>(.+?)\<\/pre\>/ims", $value, $matches );

		foreach( $matches[0] as $k => $m )
		{
			$c = \count( $codeboxes );
			$codeboxes[ $c ] = $m;

			$replacement = '<!--codeboxes{' . $c . '}-->';

			$value = str_replace( $m, $replacement, $value );
		}

		preg_match_all( "/\<table([^>]*?)>(.+?)\<\/table\>/ims", $value, $matches );

		$tables = array();
		foreach( $matches[0] as $k => $m )
		{
			$c = \count( $tables );
			$tables[ $c ] = \str_ireplace( '<td><br />', '<td>', $m ); // Fix up un-needed BRs added earlier in this method

			$replacement = '<!--tables{' . $c . '}-->';

			$value = str_replace( $m, $replacement, $value );
		}

		/* Nested spoilers do not parse correctly, so just do that manually here for now */
		$value = str_replace( "[spoiler]", '</p><div class="ipsSpoiler" data-ipsSpoiler><div class="ipsSpoiler_header"><span></span></div><div class="ipsSpoiler_contents"><p>', $value );
		$value = str_replace( "[/spoiler]", "</p></div></div><p>", $value );
		
		/* Just remove this - our new parser doesn't need (or like) it */
		$value = str_replace( "[/*]", '', $value );

		/* URL bbcode tags that didn't have a scheme specified would get fixed automatically in 3.x */
		$value = preg_replace_callback( "/\[url\](.+?)\[\/url\]/i", function( $matches ){
			if( mb_substr( $matches[1], 0, 7 ) !== 'http://' AND mb_substr( $matches[1], 0, 8 ) !== 'https://' AND mb_substr( $matches[1], 0, 6 ) !== 'ftp://' )
			{
				return 'http://' . $matches[1];
			}
			else
			{
				return $matches[1];
			}
		}, $value );

		/* Convert some old HTML back into bbcode to be properly parsed */
		$value = preg_replace( "#<a href=[\"']index\.php\?automodule=blog(&|&amp;)showentry=(.+?)['\"]>(.+?)</a>#is", "[entry=\"\\2\"]\\3[/entry]", $value );
		$value = preg_replace( "#<a href=[\"']index\.php\?automodule=blog(&|&amp;)blogid=(.+?)['\"]>(.+?)</a>#is", "[blog=\"\\2\"]\\3[/blog]", $value );
		$value = preg_replace( "#<a href=[\"']index\.php\?act=findpost(&|&amp;)pid=(.+?)['\"]>(.+?)</a>#is", "[post=\"\\2\"]\\3[/post]", $value );
		$value = preg_replace( "#<a href=[\"']index\.php\?showtopic=(.+?)['\"]>(.+?)</a>#is", "[topic=\"\\1\"]\\2[/topic]", $value );
		$value = preg_replace( "#<a href=[\"'](.*?)index\.php\?act=findpost(&|&amp;)pid=(.+?)['\"]><\{POST_SNAPBACK\}></a>#is", "[snapback]\\3[/snapback]", $value );
		$value = preg_replace( "#<!--blog\.extract\.start-->(.+?)<!--blog\.extract\.end-->#is", "[extract]\\1[/extract]", $value );
		$value = preg_replace( "#<span style=[\"']color:\#000000;background:\#000000['\"]>(.+?)</span>#is", "[spoiler]\\1[/spoiler]", $value );

		/* Unconvert quotes */
		$value = preg_replace( "#<!--QuoteBegin-->(.+?)<!--QuoteEBegin-->#"							, '[quote]'								, $value );
		$value = preg_replace( "#<!--QuoteBegin-{1,2}([^>]+?)\+([^>]+?)-->(.+?)<!--QuoteEBegin-->#"	, "[quote name='\\1' date='\\2']"		, $value );
		$value = preg_replace( "#<!--QuoteBegin-{1,2}([^>]+?)\+-->(.+?)<!--QuoteEBegin-->#"			, "[quote name='\\1']"					, $value );
		$value = preg_replace( "#<!--QuoteEnd-->(.+?)<!--QuoteEEnd-->#"								, '[/quote]'							, $value );
		$value = preg_replace( "#\[quote=(.+?),(.+?)\]#i"											, "[quote name='\\1' date='\\2']"		, $value );
		$value = preg_replace( "#\[quote=(.*?)\[url(.*?)\](.+?)\[\/url\]\]#i"						, "[quote=\\1\\3]"						, $value );
		$value = preg_replace_callback( "#<!--quoteo([^>]+?)?-->(.+?)<!--quotec-->#si"				, array( $this, '_parseOldQuote' )		, $value );

		/* Unconvert indent tag */
		while( preg_match( "#<blockquote>(.+?)</blockquote>#is", $value ) )
		{
			$value = preg_replace( "#<blockquote>(.+?)</blockquote>#is"  , "[indent]\\1[/indent]", $value );
		}

		/* Convert quote tag */
		$value = preg_replace_callback( "#<blockquote\s+?class=['\"]ipsBlockquote[\"']([^>]*?)>#si", array( $this, '_parseOldBlockquote' )		, $value );
		$value = preg_replace_callback( "#\[quote([^>]*?)\]#si"									, array( $this, '_parseOldQuoteBbcode' )	, $value );
		$value = str_replace( "</blockquote>", "</div></blockquote>", $value );
		$value = str_replace( "[/quote]", "</div></blockquote>", $value );

		/* Fix download manager embedded screenshot URLs */
		$value = preg_replace_callback( "#<img src=['\"]" . preg_quote( rtrim( \IPS\Settings::i()->base_url, '/' ), '#' ) . "/index\.php\?app=downloads(?:&amp;|&)module=display(?:&amp;|&)section=screenshot(?:&amp;|&)id=(\d+?)['\"]([^>]*?)>#si", array( $this, '_fixDownloadsScreenshots' ), $value );

		/* These were auto-replacements previously */
		$value = str_ireplace( "(c)"	, "&copy;"	, $value );
		$value = str_ireplace( "(tm)"	, "&#153;"	, $value );
		$value = str_ireplace( "(r)"	, "&reg;"	, $value );
		
		/* Convert PHP tags if necessary */
		$value = str_ireplace( "<?php"	, "&lt;?php", $value );
		$value = str_ireplace( "?>"		, "?&gt;"	, $value );

		/* Fix bbcode attributes */
		preg_match_all( "/\[([^\]]+?)=(\"|'|&#39;|&#039;|&quot;)([^\]]+?)(\"|'|&#39;|&#039;|&quot;)\]/", $value, $matches );

		foreach( $matches[0] as $bbcodeTag )
		{
			$newBbcodeTag	= preg_replace_callback( "/\[([^\]]+?)=(\"|'|&#39;|&#039;|&quot;)([^\]]+?)(\"|'|&#39;|&#039;|&quot;)\]/", function( $tagMatches ) {
				/* We strip the enclosing quotes and we strip any trailing ';' */
				return "[" . $tagMatches[1] . "=" . rtrim( $tagMatches[3], ';' ) . "]";
			}, $bbcodeTag );

			$value = str_replace( $bbcodeTag, $newBbcodeTag, $value );
		}

		/* Convert bbcode tags, but only those our new parser doesn't handle */
		foreach( $this->bbcodes as $code )
		{
			/* If we don't have a regex replacement, probably because we used to use a plugin, we can't do this automatically */
			if( !$code['bbcode_replace'] )
			{
				continue;
			}

			/* Build the regex */
			$regex = "/\[(?:{$code['bbcode_tag']}" . ( $code['bbcode_aliases'] ? '|' . str_replace( ',', '|', $code['bbcode_aliases'] ) : '' ) . ")" .
				( $code['bbcode_useoption'] ? ( $code['bbcode_optional_option'] ? "(?:=(.+?))?" : "=(.+?)" ) : '' ) . "\]" . 
				( $code['bbcode_single_tag'] ? '' : ( "(.+?)\[\/(?:{$code['bbcode_tag']}" . ( $code['bbcode_aliases'] ? '|' . str_replace( ',', '|', $code['bbcode_aliases'] ) : '' ) . ")\]" ) ) .
				"/ims";

			/* Now actually perform the replacement */
			$depth = 0;
			while( preg_match( $regex, $value ) )
			{
				/* We want to allow nested BBCodes, but we don't want an infinite loop */
				$depth++;
				if ( $depth > 20 )
				{
					break;
				}
				
				$value = preg_replace_callback( $regex, function( $matches ) use ( $code ) {
					$replacement	= $code['bbcode_replace'];

					if( $code['bbcode_single_tag'] )
					{
						if( $code['bbcode_useoption'] )
						{
							$replacement	= str_replace( '{option}', $matches[1], $replacement );
						}
					}
					else
					{
						if( $code['bbcode_useoption'] )
						{
							$replacement	= str_replace( '{option}', $matches[1], $replacement );
							$replacement	= str_replace( '{content}', $matches[2], $replacement );
						}
						else
						{
							$replacement	= str_replace( '{content}', $matches[1], $replacement );
						}
					}

					return $replacement;
				}, $value );
			}
		}

		/* Really really old versions of IPB used to use [attachmentid=X] as markers for attachments */
		$value = preg_replace( "/\[attachmentid=(.+?)\]/i", "[attachment=$1:name]", $value );

		/* Figure out what attachments are missing so we can add them at the end */
		if( $this->attachClass !== NULL AND $this->idOne !== 0 )
		{
			$embeddedAttachments	= array();

			preg_match_all( "/\[attachment=(.+?):(.+?)\]/ims", $value, $matches );

			if( \count( $matches[1] ) )
			{
				foreach( $matches[1] as $id )
				{
					$embeddedAttachments[ $id ]	= $id;
				}
			}

			$where = array( array( 'location_key=?', $this->attachClass ), array( 'id1=?', $this->idOne ) );

			if( $this->idTwo !== 0 )
			{
				$where[]	= array( 'id2=?', $this->idTwo );
			}

			$mappedAttachments		= iterator_to_array( \IPS\Db::i()->select( '*', 'core_attachments_map', $where )->setKeyField( 'attachment_id' ) );

			foreach( $mappedAttachments as $attachmentId => $map )
			{
				if( !\in_array( $attachmentId, $embeddedAttachments ) )
				{
					$value	.= '<p>[attachment=' . $attachmentId . ':name]</p>';
				}
			}
		}

		/* Now fix shared media */
		$value = preg_replace_callback( "/\[sharedmedia=(.+?):(.+?):(.+?)\]/ims", function( $matches ) {

			switch( $matches[1] )
			{
				case 'calendar':
					try
					{
						$event = \IPS\Db::i()->select( '*', 'calendar_events', array( 'event_id=?', (int) $matches[3] ) )->first();
						$url = \IPS\Http\Url::internal( "app=calendar&module=events&controller=event&id={$event['event_id']}", 'front', 'calendar_event', $event['event_title_seo'] );
						return "<p><a href='{$url}'>{$url}</a></p>";
					}
					catch( \Exception $e )
					{
						return "";
					}
				break;
	
				case 'gallery':
					/* Make sure gallery is enabled first */
					if ( \IPS\Application::appIsEnabled( 'gallery' ) === FALSE )
					{
						return "";
					}
					
					if( $matches[2] == 'images' )
					{
						try
						{
							$image = \IPS\gallery\Image::constructFromData( \IPS\Db::i()->select( '*', 'gallery_images', array( 'image_id=?', (int) $matches[3] ) )->first() );
							return "<p><a href='{$image->url()}'><img src='{$image->embedImage()->url}' alt='{$image->caption}'></a></p>";
						}
						catch( \Exception $e )
						{
							return "";
						}
					}
					else
					{
						try
						{
							$album = \IPS\gallery\Album::constructFromData( \IPS\Db::i()->select( '*', 'gallery_albums', array( 'album_id=?', (int) $matches[3] ) )->first() );
							return \IPS\Text\Parser::embeddableMedia( \IPS\Http\Url::createFromString( $album->url(), TRUE, TRUE ), FALSE, $this->member );
						}
						catch( \Exception $e )
						{
							return "";
						}
					}
				break;
	
				case 'downloads':
					try
					{
						$file = \IPS\Db::i()->select( '*', 'downloads_files', array( 'file_id=?', (int) $matches[3] ) )->first();
						$url = \IPS\Http\Url::internal( "app=downloads&module=downloads&controller=view&id={$file['file_id']}", 'front', 'downloads_file', $file['file_name_furl'] );
						return "<p><a href='{$url}'>{$url}</a></p>";
					}
					catch( \Exception $e )
					{
						return "";
					}
				break;
	
				case 'core':
					/* We'll just let the attachment parsing next take care of this */
					return "[attachment={$matches[3]}:string]";
				break;
	
				case 'blog':
					try
					{
						$entry = \IPS\Db::i()->select( '*', 'blog_entries', array( 'entry_id=?', (int) $matches[3] ) )->first();
						$url = \IPS\Http\Url::internal( "app=blog&module=blogs&controller=entry&id={$entry['entry_id']}", 'front', 'blog_entry', $entry['entry_name_seo'] );
						return "<p><a href='{$url}'>{$url}</a></p>";
					}
					catch( \Exception $e )
					{
						return "";
					}
				break;
			}

			/* Could be a third party app - just return original embed code if we are still here */
			return $matches[0];
		}, $value );

		/* Some people may have tried to embed the direct youtube video...tsk tsk */
		preg_match_all( '#youtube.com/v/([^ \[]+?)#is', $value, $matches );

		foreach( $matches[0] as $idx => $m )
		{
			$value = str_replace( $m, "youtube.com/watch?v=" . $matches[1][ $idx ], $value );
		}

		/* We previously supported multiple types of 'media' tags which we need to convert now */
		foreach( array( 'youtube', 'blogmedia', 'flash', 'movie', 'video' ) as $tagname )
		{
			$value = str_replace( array( '[' . $tagname . ']', '[/' . $tagname . ']' ), array( '', ' ' ), $value );
		}

		/* Media */
		$urls = array();
		
		/* Parse [URL]url[/URL] bbcode */
		preg_match_all( '#\[url]([^\[]+?)\[\/url\]#is', $value, $matches );
		
		foreach( $matches[0] as $idx => $m )
		{
			$value = str_replace( $m, "<a href='" . $matches[1][ $idx ] . "'>" . $matches[1][ $idx ] . "</a>", $value );
		}

		/* Parse [URL=url]text[/URL] bbcode */
		preg_match_all( '#\[url=(?:\'|"|&\#039;)?(.+?)(?:\'|"|&\#039;)?\](.+?)\[\/url\]#is', $value, $matches );

		foreach( $matches[0] as $idx => $m )
		{
			$value = str_replace( $m, "<a href='" . $matches[1][ $idx ] . "'>" . $matches[2][ $idx ] . "</a>", $value );
		}

		/* Parse img bbcode */
		preg_match_all( '#\[img\](.+?)\[\/img\]#is', $value, $matches );

		foreach( $matches[0] as $idx => $m )
		{
			$image = basename( $matches[1][ $idx ] );
			$value = str_replace( $m, "<img src='" . $matches[1][ $idx ] . "' alt='{$image}'>", $value );
		}

		/* Get rid of empty hyperlinks, which can be present from bugs in older versions of IPB */
		preg_match_all( '#<a(?:[^>]+?)href=[\'"]["\'](?:[^>]*?)>(.+?)\<\/a\>#is', $value, $matches );

		foreach( $matches[0] as $k => $v )
		{
			$value = str_replace( $v, $matches[1][ $k ], $value );
		}

		/* Get rid of nested iframes if they exist */
		preg_match_all( "/<iframe src=['\"]<iframe(.+?)>[\"'].+?>/", $value, $matches );
		
		foreach( $matches[1] as $k => $m )
		{
			$realUrl = preg_replace( "/^.*?src=['\"]([^'\"]+?)[\"'].*?$/", "$1", $m );
			
			$value = str_replace( $matches[0][ $k ], $realUrl, $value );
		}

		/* Fix hrefs missing the wrapping " or ', but first protect <fileStore...> */
		$value = preg_replace( '#<([^>]+?)(href|src)=(\'|")<fileStore\.([\d\w\_]+?)>/#i', '<\1\2=\3%7BfileStore.\4%7D/', $value );

		$value = preg_replace_callback( '#<a (.+?)>#is', function( $matches ){
			if( mb_strpos( $matches[1], 'href="' ) !== FALSE OR mb_strpos( $matches[1], "href='" ) !== FALSE )
			{
				return $matches[0];
			}

			$params = explode( ' ', $matches[1] );

			foreach( $params as $_idx => $attribute )
			{
				$attribute = trim( $attribute );

				if( mb_strpos( $attribute, 'href=' ) === 0 )
				{
					$params[ $_idx ] = "href='" . str_replace( 'href=', '', $attribute ) . "'";
					break;
				}
			}

			return "<a " . implode( ' ', $params ) . '>';
		}, $value );

		$value = preg_replace( '#<([^>]+?)(href|src)=(\'|")%7BfileStore\.([\d\w\_]+?)%7D/#i', '<\1\2=\3<fileStore.\4>/', $value );

		/* Store links */
		preg_match_all( '#<a.+?href=[\'"](.*?)["\'].*?>(.+?)\<\/a\>#is', $value, $matches );

		/* Embeds */
		$embedCount = 0;

		foreach( $matches[0] as $k => $m )
		{
			$url = $matches[1][ $k ];

			if( mb_strpos( $url, '#entry' ) !== FALSE )
			{
				$sep = '?';

				if( mb_strpos( $url, '?' ) )
				{
					$sep = '&';
				}

				$url = preg_replace( "/#entry(\d+)/", $sep . "do=findComment&amp;comment=$1", $url );
			}

			$c = \count( $urls );
			$urls[ $c ] = str_replace( $matches[1][ $k ], $url, $m );

			$replacement = '<!--url{' . $c . '}-->';

			try
			{
				/* In 3.x, hyperlinked YouTube URLs did replace as long as a data attribute wasn't present */
				if( ! mb_strpos( $matches[1][ $k ], \IPS\Settings::i()->base_url ) AND ( mb_strpos( $matches[0][ $k ], 'youtube.co' ) OR mb_strpos( $matches[0][ $k ], 'youtu.be' ) ) and ! mb_strpos( $matches[0][ $k ], 'nomediaparse' ) AND $response = \IPS\Text\Parser::embeddableMedia( \IPS\Http\Url::createFromString( $matches[1][ $k ], FALSE, TRUE ), FALSE, $this->member ) )
				{
					$urls[ $c ] = $response;
				}
			}
			catch( \UnexpectedValueException $e ){}

			$value = str_replace( $m, $replacement, $value );
		}

		/* Store images */
		preg_match_all( '#<img[^>]+?src=[\'"](.+?)["\'].*?>#is', $value, $matches );

		foreach( $matches[0] as $m )
		{
			$c = \count( $urls );
			$urls[ $c ] = $m;
			
			$value = str_replace( $m, '<!--url{' . $c . '}-->', $value );
		}

		/* Media URLs such as [media]http://site.com/something (data).mp3[/media] will not match the next regex, so fix them first */
		preg_match_all( "/\[media\](.+?)\[\/media\]/is", $value, $matches );

		foreach( $matches[1] as $k => $m )
		{
			$c = \count( $urls );
			$urls[ $c ] = "<a href='{$m}'>{$m}</a>";
			
			$value = str_replace( $matches[0][ $k ], '<!--url{' . $c . '}-->', $value );
		}

		/* Parse standard URLs */
		preg_match_all( '#(?:^|\s|\)|\(|\{|\}|/>|>|\]|\[|;|href=\S)((http|https|news|ftp)://(?:[^<>\)\[\"\s]+|[a-zA-Z0-9/\._\-!&\#;,%\+\?:=]+))#is', $value, $matches );

		foreach( $matches[1] as $k => $m )
		{
			$me = null;

			/* If this was an image URL that was not already embedded, we shouldn't try to embed it now - also this causes timeouts in
				many cases as the images that were linked in old posts are no longer available. Also don't try to embed 50 youtube videos in one post. */
			if( !preg_match( "/(.+?)\.(jpg|jpeg|png|gif)$/i", $m ) AND $embedCount < $this->maxEmbeds )
			{
				$url = $m;

				if( mb_strpos( $m, '#entry' ) !== FALSE )
				{
					$sep = '?';

					if( mb_strpos( $m, '?' ) )
					{
						$sep = '&';
					}

					$url = preg_replace( "/#entry(\d+)/", $sep . "do=findComment&amp;comment=$1", $m );
				}

				try
				{
					$me = \IPS\Text\Parser::embeddableMedia( \IPS\Http\Url::createFromString( $url, FALSE, TRUE ), FALSE, $this->member );
				}
				catch( \Exception $e ){}
			}

			if( $me )
			{
				$embedCount++;
			}

			$c = \count( $urls );
			$urls[ $c ] = $me ?: "<a href='{$m}'>{$m}</a>";
			
			$value = str_replace( $matches[1][ $k ], '<!--url{' . $c . '}-->', $value );
		}

		foreach( $urls as $k => $v )
		{
			$value = str_replace( '<!--url{' . $k . '}-->', $v, $value );
		}

		$value = preg_replace( "/\<a href=([^>]+?)\>\<a href=([^>]+?)\>(.+?)\<\/a\>\<\/a\>/ims", "<a href=$1>$3</a>", $value );

		/* Now fix attachments */
		$value = preg_replace_callback( "/\[attachment=(.+?):(.+?)\]/ims", function( $matches ) {
			try
			{
				$attachment = \IPS\Db::i()->select( '*', 'core_attachments', array( 'attach_id=?', (int) $matches[1] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$attachment = array( 'attach_is_image' => 0, 'attach_location' => '' );
			}

			$url = \IPS\Http\Url::baseUrl( \IPS\Http\Url::PROTOCOL_RELATIVE ) . "applications/core/interface/file/attachment.php?id=" . $attachment['attach_id'];
			if ( $attachment['attach_security_key'] )
			{
				$url .= "&key={$attachment['attach_security_key']}";
			}

			if( $attachment['attach_is_image'] )
			{
				$attachment['attach_thumb_location']	= $attachment['attach_thumb_location'] ?: $attachment['attach_location'];
				$return = "<a class='ipsAttachLink ipsAttachLink_image' href='{fileStore.core_Attachment}/{$attachment['attach_location']}'><img src='{fileStore.core_Attachment}/{$attachment['attach_thumb_location']}' data-fileid='" . \IPS\Settings::i()->base_url . "applications/core/interface/file/attachment.php?id={$attachment['attach_id']}' class='ipsImage ipsImage_thumbnailed'></a>";
			}
			elseif( \in_array( $attachment['attach_ext'], \IPS\File::$videoExtensions ) )
			{
				$return =  \IPS\Theme::i()->getTemplate( 'editor', 'core', 'global' )->attachedVideo( $attachment['attach_location'], $url, $attachment['attach_file'], \IPS\File::getMimeType( $attachment['attach_file'] ), $attachment['attach_id'] );
			}
			elseif( \in_array( $attachment['attach_ext'], \IPS\File::$audioExtensions ) )
			{
				$return = \IPS\Theme::i()->getTemplate( 'editor', 'core', 'global' )->attachedAudio( $attachment['attach_location'], $url, $attachment['attach_file'], \IPS\File::getMimeType( $attachment['attach_file'] ), $attachment['attach_id'] );
			}
			else
			{
				$return = $attachment['attach_location'] ? "<a href='{$url}'>{$attachment['attach_file']}</a>" : '';
			}

			return $return;
		}, $value );

		/* Some old posts might have used <br> instead of <p>, which can cause severe parsing issues, so try to catch this and adapt */
		if( mb_substr( $value, 0, 3 ) !== '<p>' AND mb_substr( $value, 0, 3 ) !== '<p ' AND mb_substr( $value, 0, 5 ) !== '<div>' AND mb_substr( $value, 0, 5 ) !== '<div ' AND ( mb_strpos( $value, '<br>' ) !== FALSE OR mb_strpos( $value, '<br />' ) !== FALSE ) )
		{
			$value	= '<p>' . $value . '</p>';
			$value	= str_replace( array( '<br>', '<br />' ), '</p><p>', $value );
		}
		
		$value = preg_replace( '/<p>\s*<\/p>/i', '<p>&nbsp;</p>', $value );

		/* But that may break blockquotes, which are block elements */
		$value = str_replace( "<p><blockquote", "<blockquote", $value );
		$value = str_replace( "</blockquote></p>", "</blockquote>", $value );

		/* Fix lists */
		$value = str_replace( "[list]", "</p><ul data-ipsBBCode-list=\"true\">", $value );

		$value = preg_replace_callback( "/\[list=['\"]?(.+?)['\"]?\](.+?)\[\/list\]/ims", function( $matches ){
			switch( $matches[1] )
			{
				case '1':
					return "</p><ol data-ipsBBCode-list=\"true\" style='list-style-type: decimal'>{$matches[2]}</ol><p>";
				break;

				case '0':
					return "</p><ol data-ipsBBCode-list=\"true\" style='list-style-type: decimal-leading-zero'>{$matches[2]}</ol><p>";
				break;

				case 'a':
					return "</p><ol data-ipsBBCode-list=\"true\" style='list-style-type: lower-alpha'>{$matches[2]}</ol><p>";
				break;

				case 'A':
					return "</p><ol data-ipsBBCode-list=\"true\" style='list-style-type: upper-alpha'>{$matches[2]}</ol><p>";
				break;

				case 'i':
					return "</p><ol data-ipsBBCode-list=\"true\" style='list-style-type: lower-roman'>{$matches[2]}</ol><p>";
				break;

				case 'I':
					return "</p><ol data-ipsBBCode-list=\"true\" style='list-style-type: upper-roman'>{$matches[2]}</ol><p>";
				break;
			}

			return "</p><ul data-ipsBBCode-list=\"true\">{$matches[2]}</ul>";
		}, $value );

		$value = str_replace( "[/list]", "</ul><p>", $value );

		$value = preg_replace( "/(<[u|o]l data-ipsBBCode-list=\"true\"(?:.+?)?>)\s*?<br \/>\s*?\[\*\]/", "$1\n[*]", $value );
		$value = preg_replace( "/(<[u|o]l data-ipsBBCode-list=\"true\"(?:.+?)?>)\s*?<br>\s*?\[\*\]/", "$1\n[*]", $value );
		$value = preg_replace( "/(<br \/>|<br>)\s*?\[\*\]/", "\n[*]", $value );

		$value = preg_replace_callback( "/<[u|o]l data-ipsBBCode-list=\"true\"(?:.*?)>.+?<\/[u|o]l>/ims", function( $matches )
		{
			$v = str_replace( '</p><p>', '<br>', $matches[0] );
			$v = str_replace( array( '<p>', '</p>' ), '', $v );
			$v = str_replace( '[*]', '</li><li>', $v );
			$v = str_replace( '<ul></li>', '<ul>', $v );
			$v = str_replace( '<ol></li>', '<ol>', $v );
			$v = str_replace( '</ul>', '</li></ul>', $v );
			$v = str_replace( '</ol>', '</li></ol>', $v );
			$v = str_replace( ' data-ipsBBCode-list="true"', '', $v );
			return $v;
		}, $value );

		$value = preg_replace( "/(\<[u|o]l(?:.*?)?\>)\s*?\<\/li\>/ims", "$1", $value );
		$value = preg_replace( "/(\<[u|o]l(?:.*?)?\>)\<br\>\<li\>/ims", "$1<li>", $value );

		/* Put codeboxes back */
		foreach( $codeboxes as $k => $v )
		{
			$value = str_replace( '<!--codeboxes{' . $k . '}-->', $v, $value );
		}
		/* Put tables back */
		foreach( $tables as $k => $v )
		{
			$value = str_replace( '<!--tables{' . $k . '}-->', $v, $value );
		}

		/* Block level tags */
		$value = preg_replace( "/\[(code|codebox|sql|php|xml|html)(.*?)\](.+?)\[\/(code|codebox|sql|php|xml|html)\]/ims", "</p><div>[$1$2]$3[/$4]</div><p>", $value );
		$value = preg_replace( "/\[(indent|left|center|right|spoiler|page)\](.+?)\[\/(indent|left|center|right|spoiler|page)\]/ims", "</p><p>[$1]$2[/$3]</p><p>", $value );
		$value = preg_replace( "/\<p\>\s*?\[page\]\s*?\<\/p\>/ims", "[page]", $value );

		/* Fix old code class names */
		$value = preg_replace_callback( "/<pre\s+?class=['\"](.+?)[\"']>/i", function( $matches ) {
			$classes	= explode( ' ', $matches[1] );
			$newClasses	= array();

			foreach( $classes as $class )
			{
				if( $class == '_prettyXprint' )
				{
					$class = 'prettyprint';
				}

				if( mb_substr( $class, 0, 1 ) == '_' )
				{
					$class = mb_substr( $class, 1 );
				}

				$newClasses[]	= $class;
			}

			return "<pre class='ipsCode " . implode( ' ', $newClasses ) . "'>";
		}, $value );

		/* Put the dynamic replacements back now */
		$value = preg_replace( '#<([^>]+?)(href|src|srcset)=(\'|")(?:{|%7B)fileStore\.([\d\w\_]+?)(?:}|%7D)/#i', '<\1\2=\3<fileStore.\4>/', $value );
		$value = preg_replace( '#<([^>]+?)(srcset)=(\'|")(?:{|%7B)fileStore\.([\d\w\_]+?)(?:}|%7D)/#i', '<\1\2=\3<fileStore.\4>/', $value );
		$value = preg_replace( '#<([^>]+?)(href|src|srcset)=(\'|")(?:{|%7B)___base_url___(?:}|%7D)/#i', '<\1\2=\3<___base_url___>/', $value );
		$value = preg_replace( '#<([^>]+?)(data-fileid)=(\'|")(?:{|%7B)___base_url___(?:}|%7D)/#i', '<\1\2=\3<___base_url___>/', $value );

		//print htmlspecialchars( $value );print "<hr style='color:red;'>";print $value;print "<hr style='color:red;'>";

		/* Return */
		if ( mb_stristr( $value, '<fileStore.' ) )
		{
			\IPS\Output::i()->parseFileObjectUrls( $value );
		}
		
		$result = $this->parser->parse( $value );

		/* If they are not using UTF8MB4, we need to convert emoji back to html entities as HTMLPurifier will have converted them on us */
		if ( \IPS\Settings::i()->getFromConfGlobal('sql_utf8mb4') !== TRUE )
		{
			$result = preg_replace_callback( '/[\x{10000}-\x{10FFFF}]/u', function( $mb4Character ) {
				return mb_convert_encoding( $mb4Character[0], 'HTML-ENTITIES', 'UTF-8' );
			}, $result );
		}

		/* URLs that are turned into embeds end up as <a href=''><iframe ...></a> */
		$result = preg_replace( "/\<a href=[\"']{2}.*?\>(?:&gt;)?(\<iframe.+?\>)\<\/a\>/i", "$1", $result );

		return $result;
	}

	/**
	 * Parse new quotes
	 *
	 * @param	array	$matches	Data from preg_replace callback
	 * @return	string	Converted text
	 */
	protected function _parseOldQuote( $matches=array() )
	{
		if ( !$matches[1] )
		{
			return '[quote]';
		}
		else
		{
			$return		= array();

			preg_match( "#\(post=(.+?)?:date=(.+?)?:name=(.+?)?\)#", $matches[1], $match );
			
			if ( $match[3] )
			{
				$return[]	= " name='" . static::_cleanQuoteName( $match[3] ) . "'";
			}
			
			if ( $match[1] )
			{
				$return[]	= " post='" . \intval($match[1]) . "'";
			}
			
			if ( $match[2] )
			{
				$return[]	= " date='{$match[2]}'";
			}
			
			return str_replace( '  ', ' ', '[quote' . implode( ' ', $return ).']' );
		}
	}

	/**
	 * Parse new quotes #2
	 *
	 * @param	array	$matches	Data from preg_replace callback
	 * @return	string	Converted text
	 */
	protected function _parseOldBlockquote( $matches=array() )
	{
		$parameters	= array( 'data-ipsQuote' => '', 'class' => 'ipsQuote' );

		if( \count( $matches ) )
		{
			preg_match( "/data-author=['\"](.+?)[\"']/i", static::_cleanQuoteName( $matches[1] ), $author );
			preg_match( "/data-cid=['\"](.+?)[\"']/i", $matches[1], $cid );
			preg_match( "/data-time=['\"](.+?)[\"']/i", $matches[1], $time );

			if( isset( $cid[1] ) )
			{
				$parameters['data-ipsquote-contentcommentid']	= $cid[1];

				if( $this->attachClass and $this->itemClass )
				{
					$pieces = explode( '_', $this->attachClass );

					$parameters['data-ipsquote-contentapp']		= $pieces[0];
					$parameters['data-ipsquote-contenttype']	= mb_strtolower( $pieces[1] );
					$parameters['data-ipsquote-contentclass']	= str_replace( '\\', '_', mb_substr( $this->itemClass, 4 ) );
					$parameters['data-ipsquote-contentid']		= $this->idOne;
				}
			}

			if( isset( $author[1] ) )
			{
				$parameters['data-ipsquote-username']	= $author[1];
				$parameters['data-cite']				= $author[1];
			}

			if( isset( $time[1] ) )
			{
				$parameters['data-ipsquote-timestamp']	= $time[1];
			}
		}
		
		$_parameterString	= '';

		foreach( $parameters as $key => $value )
		{
			$_parameterString	.= ' ' . $key . '="' . str_replace( '"', '\\"', $value ) . '"';
		}
		
		return "<blockquote{$_parameterString}><div>";
	}

	/**
	 * Parse new quotes #3
	 *
	 * @param	array	$matches	Data from preg_replace callback
	 * @return	string	Converted text
	 */
	protected function _parseOldQuoteBbcode( $matches=array() )
	{
		$parameters	= array( 'data-ipsQuote' => '', 'class' => 'ipsQuote' );

		if( \count( $matches ) )
		{
			preg_match( "/name=['\"](.+?)[\"']/i", $matches[1], $author );
			preg_match( "/post=['\"](.+?)[\"']/i", $matches[1], $cid );
			preg_match( "/timestamp=['\"](.+?)[\"']/i", $matches[1], $time );

			if( isset( $cid[1] ) )
			{
				$parameters['data-ipsquote-contentcommentid']	= $cid[1];
			}

			if( isset( $author[1] ) )
			{
				$parameters['data-ipsquote-username']	= $author[1];
				$parameters['data-cite']				= $author[1];
			}

			if( isset( $time[1] ) )
			{
				$parameters['data-ipsquote-timestamp']	= $time[1];
			}
		}

		/* Try to set the other parameters */
		if( isset( $this->attachClass ) )
		{
			$attachBits = explode( '_', $this->attachClass );

			$parameters['data-ipsquote-contentapp']		= $attachBits[0];
			$parameters['data-ipsquote-contenttype']	= mb_strtolower( $attachBits[1] );

			/* This is not perfect - if you quoted from another topic this would be wrong, however in most cases
				this is the best guess so we'll use it */
			$parameters['data-ipsquote-contentid']		= $this->idOne;
		}

		if( isset( $this->itemClass ) )
		{
			$parameters['data-ipsquote-contentclass']	= str_replace( '\\', '_', mb_substr( $this->itemClass, 4 ) );
		}

		$_parameterString	= '';

		foreach( $parameters as $key => $value )
		{
			$_parameterString	.= ' ' . $key . '="' . str_replace( '"', '\\"', $value ) . '"';
		}
		
		return "<blockquote{$_parameterString}><div>";
	}

	/**
	 * Convert flash HTML back into BBCode
	 *
	 * @param	array	$matches	Data from preg_replace callback
	 * @return	string	Converted text
	 */
	protected function _parseOldFlash( $matches=array() )
	{
		$f_arr	= explode( "+", $matches[1] );
		
		return '[flash=' . $f_arr[0] . ',' . $f_arr[1] . ']' . $f_arr[2] . '[/flash]';
	}

	/**
	 * Convert old code tags back into bbcode
	 *
	 * @param	array	$matches	Data from preg_replace callback
	 * @return	string	Converted text
	 */
	protected function _parseOldCode( $matches=array() )
	{
		return '[code]' . rtrim( str_replace( "</span>", '', preg_replace( "#<span style='.+?'>#is", "", stripslashes( $matches[2] ) ) ) ) . '[/code]';
	}
	
	/**
	 * Convert download manager screenshot URLs
	 *
	 * @param	array	$matches	Data from preg_replace callback
	 * @return	string	Converted text
	 */
	protected function _fixDownloadsScreenshots( $matches )
	{
		if( isset( $matches[1] ) )
		{
			try
			{
				$screenshot = \IPS\Db::i()->select( 'record_location, record_realname', 'downloads_files_records', array( "record_file_id=? and record_type IN('sslink','ssupload')", (int) $matches[1] ), 'record_id ASC', array( 0, 1 ) )->first();

				return "<img src='{$screenshot['record_location']}' alt='{$screenshot['record_realname']}'>";
			}
			catch( \Exception $e )
			{
				return $matches[0];
			}
		}
		else
		{
			return $matches[0];
		}
	}
	
	/**
	 * Legacy names can have things like $ and ' which break new parser
	 *
	 * @param	string	$name	Name
	 * @return	string	Converted name
	 */
	protected static function _cleanQuoteName( $name )
	{
		$name = str_replace( "'", '&#39;', $name );
		$name = str_replace( '$', '&#36;', $name );
		$name = str_replace( '[', '&#91;', $name );
		$name = str_replace( ']', '&#93;', $name );
		
		return $name;	
	}
}
