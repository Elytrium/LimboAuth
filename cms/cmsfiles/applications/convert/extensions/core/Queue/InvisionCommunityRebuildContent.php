<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	convert
 * @since		08 Aug 2017
 */

namespace IPS\convert\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _InvisionCommunityRebuildContent
{
	/**
	 * @brief Number of content items to rebuild per cycle
	 */
	public $rebuild	= \IPS\REBUILD_SLOW;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data	Data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$classname = $data['class'];

		\IPS\Log::debug( "Getting preQueueData for " . $classname, 'ICrebuildPosts' );

		try
		{
			$data['count']		= $classname::db()->select( 'MAX(' . $classname::$databasePrefix . $classname::$databaseColumnId . ')', $classname::$databaseTable, ( is_subclass_of( $classname, 'IPS\Content\Comment' ) ) ? $classname::commentWhere() : array() )->first();
			$data['realCount']	= $classname::db()->select( 'COUNT(*)', $classname::$databaseTable, ( is_subclass_of( $classname, 'IPS\Content\Comment' ) ) ? $classname::commentWhere() : array() )->first();

			/* We're going to use the < operator, so we need to ensure the most recent item is rebuilt */
		    $data['runPid'] = $data['count'] + 1;
		}
		catch( \Exception $ex )
		{
			throw new \OutOfRangeException;
		}

		\IPS\Log::debug( "PreQueue count for " . $classname . " is " . $data['count'], 'ICrebuildPosts' );

		if( $data['count'] == 0 )
		{
			return null;
		}

		$data['indexed']	= 0;

		return $data;
	}

	/**
	 * Run Background Task
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	int|null					New offset or NULL if complete
	 * @throws	\IPS\Task\Queue\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( &$data, $offset )
	{
		$classname = $data['class'];
		$exploded = explode( '\\', $classname );
		if ( !class_exists( $classname ) or !\IPS\Application::appIsEnabled( $exploded[1] ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		/* Make sure there's even content to parse */
		if( !isset( $classname::$databaseColumnMap['content'] ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		/* Intentionally no try/catch as it means app doesn't exist */
		try
		{
			$this->app = \IPS\convert\App::load( $data['app'] );

			/* This extension is ONLY for InvisionCommunity conversions */
			if( $this->app->app_key != 'invisioncommunity' )
			{
				throw new \IPS\Task\Queue\OutOfRangeException;
			}
		}
		catch( \OutOfRangeException $e )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		$softwareClass = $this->app->getSource( FALSE, FALSE );

		\IPS\Log::debug( "Running " . $classname . ", with an offset of " . $offset, 'ICrebuildPosts' );

		$where	  = ( is_subclass_of( $classname, 'IPS\Content\Comment' ) ) ? ( \is_array( $classname::commentWhere() ) ? array( $classname::commentWhere() ) : array() ) : array();
		$select   = $classname::db()->select( '*', $classname::$databaseTable, array_merge( $where, array( array( $classname::$databasePrefix . $classname::$databaseColumnId . ' < ?',  $data['runPid'] ) ) ), $classname::$databasePrefix . $classname::$databaseColumnId . ' DESC', array( 0, $this->rebuild ) );
		$iterator = new \IPS\Patterns\ActiveRecordIterator( $select, $classname );
		$last     = NULL;

		foreach( $iterator as $item )
		{
			$idColumn = $classname::$databaseColumnId;

			/* Is this converted content? */
			try
			{
				/* Just checking, we don't actually need anything */
				$this->app->checkLink( $item->$idColumn, $data['link'] );
			}
			catch( \OutOfRangeException $e )
			{
				$last = $item->$idColumn;
				$data['indexed']++;
				continue;
			}

			$contentColumn	= $classname::$databaseColumnMap['content'];

			$source = new \IPS\Xml\DOMDocument( '1.0', 'UTF-8' );
			$source->loadHTML( \IPS\Xml\DOMDocument::wrapHtml( $item->$contentColumn ) );

			if( mb_stristr( $item->$contentColumn, 'data-mentionid' ) )
			{
				/* Get mentions */
				$mentions = $source->getElementsByTagName( 'a' );

				foreach( $mentions as $element )
				{
					if( $element->hasAttribute( 'data-mentionid' ) )
					{
						$this->updateMention( $element );
					}
				}
			}

			/* embeds */
			if( mb_stristr( $item->$contentColumn, 'data-embedcontent' ) )
			{
				/* Get mentions */
				$embeds = $source->getElementsByTagName( 'iframe' );

				foreach( $embeds as $element )
				{
					if( $element->hasAttribute( 'data-embedcontent' ) )
					{
						$this->updateEmbed( $element );
					}
				}
			}

			/* quotes */
			if( mb_stristr( $item->$contentColumn, 'data-ipsquote' ) )
			{
				/* Get mentions */
				$quotes = $source->getElementsByTagName( 'blockquote' );

				foreach( $quotes as $element )
				{
					if( $element->hasAttribute( 'data-ipsquote' ) )
					{
						$this->updateQuote( $element );
					}
				}
			}

			/* Get DOMDocument output */
			$content = \IPS\Text\DOMParser::getDocumentBodyContents( $source );

			/* Replace file storage tags */
			$content = preg_replace( '/&lt;fileStore\.([\d\w\_]+?)&gt;/i', '<fileStore.$1>', $content );

			/* DOMDocument::saveHTML will encode the base_url brackets, so we need to make sure it's in the expected format. */
			$item->$contentColumn = str_replace( '&lt;___base_url___&gt;', '<___base_url___>', $content );

			$item->save();

			$last = $item->$idColumn;

			$data['indexed']++;
		}

		/* Store the runPid for the next iteration of this Queue task. This allows the progress bar to show correctly. */
		$data['runPid'] = $last;

		if( $last === NULL )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		/* Return the number rebuilt so far, so that the rebuild progress bar text makes sense */
		return $data['indexed'];
	}

	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array	Text explaining task and percentage complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function getProgress( $data, $offset )
	{
		$class = $data['class'];
		$exploded = explode( '\\', $class );
		if ( !class_exists( $class ) or !\IPS\Application::appIsEnabled( $exploded[1] ) )
		{
			throw new \OutOfRangeException;
		}

		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('rebuilding_stuff', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title . '_pl', FALSE, array( 'strtolower' => TRUE ) ) ) ) ), 'complete' => $data['realCount'] ? ( round( 100 / $data['realCount'] * $data['indexed'], 2 ) ) : 100 );
	}

	/**
	 * Update mentions with new display name, ID and URL
	 *
	 * @param	\DOMElement		$element	DOM element
	 * @return	void
	 */
	public function updateMention( \DOMElement $element )
	{
		try
		{
			/* Get new member ID */
			$newMemberId = $this->app->getLink( $element->getAttribute( 'data-mentionid' ), 'core_members' );

			/* Get new member */
			$member = \IPS\Member::load( $newMemberId );

			$element->setAttribute( 'data-mentionid', $newMemberId );
			$element->setAttribute( 'href', str_replace( \IPS\Settings::i()->base_url, '<___base_url___>/', $member->url() ) );
			$element->setAttribute( 'data-ipshover-target', str_replace( \IPS\Settings::i()->base_url, '<___base_url___>/', $member->url()->setQueryString( 'do', 'hovercard' ) ) );
			$element->nodeValue = '@' . $member->name;
		}
		catch( \Exception $e ) {}
	}

	/**
	 * @brief	Mapping of content types to converter lookups - Add more for other apps when we support them
	 */
	protected $embedLocations = array( 'forums' => array( 'content' => 'forums_topics', 'comment' => 'forums_posts' ) );

	/**
	 * Update local embeds for new names, IDs
	 *
	 * @param	\DOMElement		$element	DOM element
	 * @return	void
	 */
	public function updateEmbed( \DOMElement $element )
	{
		try
		{
			$url = \IPS\Http\Url::createFromString( str_replace( '<___base_url___>', rtrim( \IPS\Settings::i()->base_url, '/' ), $element->getAttribute( 'src' ) ) );

			if( !\in_array( $url->hiddenQueryString['app'], array_keys( $this->embedLocations ) ) )
			{
				return;
			}

			$url->hiddenQueryString['id'] = $this->app->getLink( $url->hiddenQueryString['id'], $this->embedLocations[ $url->hiddenQueryString['app'] ]['content'] );

			try
			{
				if( isset( $url->queryString['comment'] ) )
				{
					$url = $url->setQueryString( 'comment', $this->app->getLink( $url->queryString['comment'], $this->embedLocations[ $url->hiddenQueryString['app'] ]['comment'] ) );
				}
			}
			catch( \OutOfRangeException $e )
			{
				$url = $url->stripQueryString( 'comment' );
			}

			try
			{
				if( isset( $url->queryString['embedComment'] ) )
				{
					$url = $url->setQueryString( 'embedComment', $this->app->getLink( $url->queryString['embedComment'], $this->embedLocations[ $url->hiddenQueryString['app'] ]['comment'] ) );
				}
			}
			catch( \OutOfRangeException $e )
			{
				$url = $url->stripQueryString( 'embedComment' );
			}

			$element->setAttribute( 'src', str_replace( \IPS\Settings::i()->base_url, '<___base_url___>/', (string) $url->correctFriendlyUrl() ) );
		}
		catch( \Exception $e ) {}
	}

	/**
	 * Update quotes for new names, IDs
	 *
	 * @param	\DOMElement		$element	DOM element
	 * @return	void
	 */
	public function updateQuote( \DOMElement $element )
	{
		try
		{
			/* Lookup the memnbers new ID */
			$newMemberId = $this->app->getLink( $element->getAttribute( 'data-ipsquote-userid' ), 'core_members' );

			/* Get new member */
			$member = \IPS\Member::load( $newMemberId );

			/* Get old username */
			$oldUsername = $element->getAttribute( 'data-ipsquote-username' );
			$element->setAttribute( 'data-ipsquote-username', $member->name );
			$element->setAttribute( 'data-ipsquote-userid', $member->member_id );

			/* Is this forums? */
			if( $element->hasAttribute( 'data-ipsquote-contentapp' ) AND $element->hasAttribute( 'data-ipsquote-contentapp' ) == 'forums' )
			{
				$element->setAttribute( 'data-ipsquote-contentcommentid', $this->app->getLink( $element->getAttribute( 'data-ipsquote-contentcommentid' ), 'forums_posts' ) );
				$element->setAttribute( 'data-ipsquote-contentid', $this->app->getLink( $element->getAttribute( 'data-ipsquote-contentid' ), 'forums_topics' ) );
			}

			/* find the citation to update the username */
			foreach ( $element->childNodes as $child )
			{
				if ( $child instanceof \DOMElement and $child->getAttribute('class') == 'ipsQuote_citation' )
				{
					$child->nodeValue = str_replace( $oldUsername, $member->name, $child->nodeValue );
				}
			}
		}
		catch( \Exception $e ) {}
	}
}