<?php
/**
 * @brief		Profanity Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Nov 2016
 */

namespace IPS\core;

/* To prevent PHP errors (extending class does not exist) revealing path */

use IPS\Text\DOMParser;

if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Profanity Model
 */
class _Profanity extends \IPS\Patterns\ActiveRecord implements \JsonSerializable
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_profanity_filters';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = '';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
		
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'wid';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'type' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * @brief	Action Type
	 */
	public static $actionTypes = array( 'swap', 'moderate', 'block' );
	
	/**
	 * Table
	 *
	 * @return	\IPS\Helpers\Table
	 */
	public static function table()
	{
		$table = new \IPS\Helpers\Table\Db( 'core_profanity_filters', \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&tab=profanityFilters' ) );
		$table->langPrefix = 'profanity_';
		$table->mainColumn = 'type';
		
		/* Columns we need */
		$table->include = array( 'type', 'action', 'm_exact' );

		/* Default sort options */
		$table->sortBy = $table->sortBy ?: 'type';
		$table->sortDirection = $table->sortDirection ?: 'asc';
		
		/* Filters */
		$table->filters = array(
			'profanity_require_approval'	=> "action='moderate'",
			'profanity_block'				=> "action='block'",
			'profanity_replace_text'		=> "action='swap'",
		);
		
		/* Search */
		$table->quickSearch = 'type';
		
		/* Custom parsers */
		$table->parsers = array(
			'action'				=> function( $val, $row )
			{
				if ( $val == 'swap' )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'profanity_replace_with_x', FALSE, array( 'sprintf' => array( $row['swop'] ) ) );
				}
				else if ( $val == 'block' )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'profanity_block' );
				}
				else
				{
					if ( $row['min_posts'] )
					{
						return \IPS\Member::loggedIn()->language()->addToStack( 'profanity_filter_action_moderate_min_posts', FALSE, array( 'pluralize' => array( $row['min_posts'] ) ) );
					}

					return \IPS\Member::loggedIn()->language()->addToStack('profanity_filter_action_moderate');
				}
			},
			'm_exact'				=> function( $val, $row )
			{
				return ( $val ) ? \IPS\Member::loggedIn()->language()->addToStack('profanity_filter_exact') : \IPS\Member::loggedIn()->language()->addToStack('profanity_filter_loose');
			}
		);
		
		/* Specify the root buttons */

		$table->rootButtons['add'] = array(
			'icon'		=> 'plus',
			'title'		=> 'profanity_add',
			'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&do=profanity' ),
			'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('profanity_add') )
		);

		$table->rootButtons['download'] = array(
			'icon'		=> 'download',
			'title'		=> 'download',
			'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&do=downloadProfanity' ),
			'data'		=> array( 'confirm' => '', 'confirmMessage' => \IPS\Member::loggedIn()->language()->addToStack('profanity_download'), 'confirmIcon' => 'info', 'confirmButtons' => json_encode( array( 'ok' => \IPS\Member::loggedIn()->language()->addToStack('download'), 'cancel' => \IPS\Member::loggedIn()->language()->addToStack('cancel') ) ) )
		);

		/* And the row buttons */
		$table->rowButtons = function( $row )
		{
			$return = array();

			$return['edit'] = array(
				'icon'		=> 'pencil',
				'title'		=> 'edit',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&do=profanity&id=' ) . $row['wid'],
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit') )
			);

			$return['delete'] = array(
				'icon'		=> 'times',
				'title'		=> 'delete',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=posting&do=deleteProfanityFilters&id=' ) . $row['wid'],
				'data'		=> array( 'delete' => '' ),
			);
				
			return $return;
		};
		
		return $table;
	}
	
	/**
	 * Form
	 *
	 * @param	\IPS\core\Profanity|NULL	$current	If we are editing, an \IPS\core\Profanity instance of the record
	 * @return	\IPS\Helpers\Form
	 */
	public static function form( \IPS\core\Profanity $current=NULL )
	{
		$form = new \IPS\Helpers\Form;
		
		if ( !$current )
		{
			$form->addTab('add');
		}
		
		$form->add( new \IPS\Helpers\Form\Text( 'profanity_type', ( $current ) ? $current->type : NULL, NULL, array(), function( $val )
		{
			if ( $val )
			{
				try
				{
					$word = static::load( $val, 'type' );

					if ( ! isset( \IPS\Request::i()->id ) or ( isset( \IPS\Request::i()->id ) and \IPS\Request::i()->id != $word->wid ) )
					{
						throw new \LogicException( 'profanity_already_exists' );
					}
				}
				catch( \OutOfRangeException $e )
				{
					/* Nothing exists, so we are good */
				}
			}
		} ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'profanity_action', ( $current ) ? $current->action : 'swap', FALSE, array(
			'options'	=> array(
				'swap'		=> 'profanity_filter_action_swap',
				'block'		=> 'profanity_filter_action_block',
				'moderate'	=> 'profanity_filter_action_moderate'
			),
			'toggles'	=> array(
				'swap'		=> array( 'profanity_swop' ),
				'moderate'	=> array( 'profanity_min_posts' )
			)
		) ) );

		$form->add( new \IPS\Helpers\Form\Number( 'profanity_min_posts', ( $current ) ? $current->min_posts : 0, FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'profanity_min_posts_unlimited' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('profanity_min_posts_prefix'), \IPS\Member::loggedIn()->language()->addToStack('profanity_min_posts_suffix'), 'profanity_min_posts' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'profanity_swop', ( $current ) ? $current->swop : NULL, NULL, array(), NULL, NULL, NULL, 'profanity_swop' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'profanity_m_exact', ( $current ) ? $current->m_exact : NULL, FALSE, array(
			'options' => array(
				'1' => 'profanity_filter_exact',
				'0'	=> 'profanity_filter_loose' )
		), NULL, NULL, NULL, 'profanity_m_exact' ) );
		
		if ( !$current )
		{
			$form->addTab('upload');
			$form->add( new \IPS\Helpers\Form\Upload( 'profanity_upload', NULL, NULL, array( 'allowedFileTypes' => array( 'xml' ), 'temporary' => TRUE ) ) );
		}
		
		return $form;
	}
	
	/**
	 * Create From Form
	 *
	 * @param	array						$values		Array of values
	 * @param	\IPS\core\Profanity|NULL	$current	If we are editing, an \IPS\core\Profanity instance of the record
	 * @return	\IPS\core\Profanity
	 */
	public static function createFromForm( array $values, \IPS\core\Profanity $current=NULL )
	{
		if ( $current )
		{
			$obj = $current;
		}
		else
		{
			$obj = new static;
		}
		
		if ( array_key_exists( 'type', $values ) )
		{
			$obj->type = $values['type'];
		}
		
		if ( array_key_exists( 'swop', $values ) )
		{
			$obj->swop = $values['swop'];
		}
		
		if ( array_key_exists( 'm_exact', $values ) )
		{
			$obj->m_exact = $values['m_exact'];
		}

		if ( array_key_exists( 'm_exact', $values ) )
		{
			$obj->m_exact = $values['m_exact'];
		}

		if ( array_key_exists( 'min_posts', $values ) )
		{
			$obj->min_posts = $values['min_posts'];
		}
		
		if ( array_key_exists( 'action', $values ) )
		{
			if ( \in_array( $values['action'], static::$actionTypes ) )
			{
				$obj->action = $values['action'];
			}
		}

		$obj->save();

		return $obj;
	}
	
	/**
	 * Get Profanity
	 *
	 * @return	array
	 */
	public static function getProfanity()
	{
		$return = array();

		foreach( static::getStore() AS $id => $row )
		{
			$return[ $id ] = static::constructFromData( $row );
		}

		return $return;
	}

	/**
	 * Get all profanity filters
	 *
	 * @return	array
	 */
	public static function getStore()
	{
		if ( !isset( \IPS\Data\Store::i()->profanityFilters ) )
		{
			\IPS\Data\Store::i()->profanityFilters = iterator_to_array( \IPS\Db::i()->select( '*', static::$databaseTable )->setKeyField( 'wid' ) );
		}

		return \IPS\Data\Store::i()->profanityFilters;
	}
	
	/**
	 * Check if the content should be hidden by profanity or url filters
	 *
	 * @param	string				$content	The content to check
	 * @param	\IPS\Member|NULL	$member		The author of the content
	 * @param	array|NULL			$filtersMatched	What matched, passed by reference
	 * @return	bool
	 */
	public static function hiddenByFilters( $content, $member=NULL, array &$filtersMatched = array() )
	{
		$return = static::_checkProfanityFilters( $content, $member );
		
		if ( \is_string( $return ) )
		{
			$filtersMatched = array(
				'type'	=> 'profanity',
				'match'	=> $return
			);
			
			return TRUE;
		}
		
		$return = static::_checkUrlFilters( $content );
		
		if ( \is_string( $return ) )
		{
			$filtersMatched = array(
				'type'	=> 'url',
				'match'	=> $return
			);
			
			return TRUE;
		}

		$return = static::_checkEmailFilters( $content );
		
		if ( \is_string( $return ) )
		{
			/* Add to approval queue table */
			$filtersMatched = array(
				'type'	=> 'email',
				'match'	=> $return
			);
			
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Check Profanity Filters
	 *
	 * @param	string				$content	The content to check
	 * @param	\IPS\Member|NULL	$member		The author of the content
	 * @return	bool|string
	 */
	protected static function _checkProfanityFilters( $content, $member=NULL )
	{
		$looseProfanity = array();
		$exactProfanity = array();

		/* Clean up profanity a little to remove citation details and inline data attributes.
		   We don't want to remove <blockquotes> entirely as users can then hide profanity in a quote box to
		   bypass the system. */
		$content = preg_replace( "#<blockquote\s+?class=\"ipsQuote\"[^>]+?>#", "<blockquote>", $content );
		$content = preg_replace( "#<div\s+?class=\"ipsQuote_citation\"([^>]+?)?>(.+?)</div>#s", "", $content );

		foreach( static::getProfanity() AS $profanity )
		{
			if ( $profanity->action == 'moderate' )
			{
				if ( ( $member and $profanity->min_posts ) and $member->member_posts > $profanity->min_posts )
				{
					continue;
				}
				
				if ( $profanity->m_exact )
				{
					$exactProfanity[] = $profanity->type;
				}
				else
				{
					$looseProfanity[] = $profanity->type;
				}
			}
		}
		
		/* Loose is easy - if any of the words are present, then mod queue */
		if ( \count( $looseProfanity ) )
		{
			foreach( $looseProfanity AS $word )
			{
				if ( mb_stristr( $content, $word ) )
				{
					return $word;
					break;
				}
			}
		}
		
		/* Still here? Check exact - this gets a bit more complicated. */
		if ( \count( $exactProfanity ) )
		{
			$words = array();
			foreach( $exactProfanity AS $word )
			{
				$words[] = preg_quote( $word, '/' );
			}
			
			$split = preg_split( '/((?=<^|\b)(?:' . implode( '|', $words ) . ')(?=\b|$))/iu', $content, null, PREG_SPLIT_DELIM_CAPTURE );

			if ( \is_array( $split ) )
			{
				foreach( $split AS $section )
				{
					if ( \in_array( mb_strtolower( $section ), array_map( 'mb_strtolower', $exactProfanity ) ) )
					{
						return $section;
						break;
					}
				}
			}
		}
		
		/* Still here? All good */
		return FALSE;
	}
	
	/**
	 * Check URL Filters
	 *
	 * @param	string	$content	The content to check
	 * @return	bool|string
	 */
	protected static function _checkUrlFilters( $content )
	{
		/* If we are allowing ANY URL's and not doing anything, then do that */
		if ( \IPS\Settings::i()->ipb_url_filter_option == 'none' AND \IPS\Settings::i()->url_filter_any_action == 'allow' )
		{
			return FALSE;
		}
		
		/* If we are using a black or white list, but not moderating, do that. */
		if ( \in_array( \IPS\Settings::i()->ipb_url_filter_option, array( 'black', 'white' ) ) AND \IPS\Settings::i()->url_filter_action == 'block' )
		{
			return FALSE;
		}
		
		$urls = ( \IPS\Settings::i()->ipb_url_filter_option == 'black' ) ? explode( ',', \IPS\Settings::i()->ipb_url_blacklist ) : ( \IPS\Settings::i()->ipb_url_filter_option != 'none' ? explode( ',', \IPS\Settings::i()->ipb_url_whitelist ) : [] );
		
		if ( \IPS\Settings::i()->ipb_url_filter_option == 'white' OR \IPS\Settings::i()->url_filter_any_action == 'moderate' )
		{
			$urls[] = "http://" . parse_url( \IPS\Settings::i()->base_url, PHP_URL_HOST ) . "/*";
			$urls[] = "https://" . parse_url( \IPS\Settings::i()->base_url, PHP_URL_HOST ) . "/*";
		}

		if ( $urls )
		{
			/* We are only checking the content to see if it should be filtered, not using it later. We need to fix embeds, otherwise they won't trigger post moderation
				even if they should */
			$content = str_replace( '<___base_url___>', rtrim( \IPS\Settings::i()->base_url, '/' ), $content );

			try
			{
				/* Load the content so we can look for URL's */
				$dom = new \IPS\Xml\DOMDocument;
				$dom->loadHTML( $content );
				
				/* Gather up all URL's */
				$selector = new \DOMXPath($dom);
				$tags = $selector->query('//img | //a | //iframe');
				$good = NULL;

				foreach( $tags AS $tag )
				{
					if ( ( $tag->hasAttribute( 'href' ) and !$tag->hasAttribute( 'data-mentionid' ) AND !$tag->hasAttribute( 'data-fileid' ) ) OR ( ( $tag->hasAttribute( 'src' ) OR $tag->hasAttribute( 'data-embed-src' ) ) AND !$tag->hasAttribute( 'data-emoticon' ) AND !$tag->hasAttribute( 'data-fileid' ) ) )
					{
						if ( \IPS\Settings::i()->ipb_url_filter_option == 'none' AND \IPS\Settings::i()->url_filter_action == 'allow' )
						{
							return $tag->hasAttribute( 'href' );
						}

						$urlToCheck = $tag->hasAttribute( 'href' ) ? $tag->getAttribute( 'href' ) : ( $tag->hasAttribute( 'src' ) ? $tag->getAttribute( 'src' ) : $tag->getAttribute( 'data-embed-src' ) );

						/* If this is an embed routed through our internal embed handler, we need to retrieve the actual URL that was embedded */
						if( mb_strpos( $urlToCheck, 'controller=embed' ) !== FALSE AND mb_strpos( $urlToCheck, 'url=' ) !== FALSE )
						{
							$urlToCheck = \IPS\Http\Url::external( $urlToCheck )->queryString['url'];
						}

						foreach( $urls AS $url )
						{
							/* Make sure we're not doing something weird like storing a blank URL */
							if ( $url )
							{
								/* If this is an attachment, we never want to do this. */
								if ( preg_match( '/<fileStore\.(.+)>/i', $urlToCheck ) )
								{
									$good = TRUE;
									break;
								}
								
								$url = preg_quote( $url, '/' );
								$url = str_replace( '\*', "(.*?)", $url );
								
								/* If it's a blacklist, check that */
								if ( \IPS\Settings::i()->ipb_url_filter_option == 'black' )
								{
									if ( preg_match( '/' . $url . '/i', $urlToCheck ) )
									{
										return $urlToCheck;
									}
								}
								/* If it's a whitelist, check that */
								else if ( \IPS\Settings::i()->ipb_url_filter_option == 'white' )
								{
									/* @note http:// is hard-coded here as we're simply validating that the URL is on the same domain, so the protocol doesn't matter for the base_url replacement */
									if ( !preg_match( '/' . $url . '/i', str_replace( '<___base_url___>', 'http://' . parse_url( \IPS\Settings::i()->base_url, PHP_URL_HOST ), $urlToCheck ), $matches ) )
									{
										$good = $urlToCheck;
									}
									else
									{
										$good = TRUE;
										break;
									}
								}
							}
						}

						/* Check the URL after we have cycled through the black/white list. */
						if ( $good !== TRUE )
						{
							/* If we are moderating links, only return if the URL was caught by a rule above. */
							if ( \IPS\Settings::i()->url_filter_any_action == 'moderate' AND ( \IPS\Settings::i()->ipb_url_filter_option == 'none' OR $good !== NULL ) )
							{
								return $urlToCheck;
							}

							if ( \IPS\Settings::i()->ipb_url_filter_option == 'none' )
							{
								return $good;
							}
						}
					}
				}
				
				if ( $good !== TRUE AND \IPS\Settings::i()->ipb_url_filter_option == 'white' )
				{
					return $good;
				}
			}
			catch( \Exception | \ValueError $e ) {}
		}
		
		return FALSE;
	}

	/**
	 * Check Email Filters
	 *
	 * @param	string	$content	The content to check
	 * @return	bool|string
	 */
	protected static function _checkEmailFilters( $content )
	{
		/* If we are allowing ANY URL's and not doing anything, then do that */
		if ( \IPS\Settings::i()->email_filter_action == 'moderate')
		{
			/* Ensure that image and file names don't trip up the email filter */
			$source = new \IPS\Xml\DOMDocument( '1.0', 'UTF-8' );
			$source->loadHTML( \IPS\Xml\DOMDocument::wrapHtml( $content ) );

			$contentImages = $source->getElementsByTagName( 'img' );

			foreach( $contentImages as $element )
			{
				foreach( [ 'data-src', 'src', 'srcset', 'alt', 'title' ] as $attr )
				{
					if ( $element->hasAttribute( $attr ) )
					{
						/* Remove any filenames, alts or titles so they don't trigger the moderation filter */
						$element->setAttribute( $attr, '' );
					}
				}
			}

			/* Get document URLs */
			$contentUrls = $source->getElementsByTagName( 'a' );

			foreach( $contentUrls as $element )
			{
				foreach( [ 'href', 'title' ] as $attr )
				{
					if ( $element->hasAttribute( $attr ) )
					{
						if ( $attr === 'href' and ! mb_stristr( $element->getAttribute( $attr ), 'mailto:' ) )
						{
							/* Remove any hrefs or titles so they don't trigger the moderation filter */
							$element->setAttribute( $attr, '' );
						}
					}
				}
			}

			/* Get DOMDocument output */
			$content = \IPS\Text\DOMParser::getDocumentBodyContents( $source );

			if( preg_match( '/' . \IPS\Text\Parser::EMAIL_REGEX . '/u', $content, $matches ) )
			{
				return $matches[0];
			}
		}

		return FALSE;
	}

	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array( 'profanityFilters' );

	/**
	 * JSON Serialize
	 *
	 * @return	array
	 */
	public function jsonSerialize()
	{
		return array(
			'wid'		=> $this->wid,
			'type'		=> $this->type,
			'swop'		=> $this->swop,
			'm_exact'	=> $this->m_exact,
			'action'	=> $this->action
		);
	}
}