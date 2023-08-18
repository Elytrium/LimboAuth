<?php
/**
 * @brief		Search results paginated response
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Oct 2017
*/

namespace IPS\Content\Search;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Abstract Search Query
 */
class _ApiResponse extends \IPS\Api\PaginatedResponse
{
	/**
	 * Data to output
	 *
	 * @return	array
	 */
	public function getOutput()
	{
		/* Get the query object from the stored select */
		$queryObject = $this->iterator[0];

		/* Set pagination */
		$queryObject->setLimit( $this->resultsPerPage );
		$queryObject->setPage( $this->page );

		/* Run query */
		$searchResults = $queryObject->search(
			$this->iterator[2],
			$this->iterator[3],
			$this->iterator[1] + \IPS\Content\Search\Query::TAGS_MATCH_ITEMS_ONLY,
			$this->iterator[4]
		);

		$this->count	= $searchResults->count( TRUE );

		/* Search tracking, only if there's a term for a non-member search and we're on the first page */
		if( \IPS\Request::i()->q AND $this->page == 1 AND ( !isset( \IPS\Request::i()->doNotTrack ) OR \IPS\Request::i()->doNotTrack != 1 ) )
		{
			$memberPermissions = $this->authorizedMember;

			if( !$this->authorizedMember AND isset( \IPS\Request::i()->search_as ) )
			{
				$memberPermissions = \IPS\Member::load( \IPS\Request::i()->search_as );

				if( !$memberPermissions->member_id )
				{
					$memberPermissions = NULL;
				}
			}

			\IPS\Db::i()->insert( 'core_statistics', array( 
				'type'			=> 'search',
				'time'			=> time(),
				'value_4'		=> \IPS\Request::i()->q,
				'value_2'		=> $this->count,
				'extra_data'	=> $memberPermissions ? md5( $memberPermissions->email . $memberPermissions->joined ) : md5( 'rest-api' ) // Intentionally anonymized
			) );
		}

		$results = array();
		foreach ( $searchResults as $result )
		{
			$resultData	= $result->asArray();
			$indexClass	= $resultData['indexData']['index_class'];
			$itemClass	= ( \in_array( 'IPS\Content\Comment', class_parents( $indexClass ) ) ) ? $indexClass::$itemClass : $indexClass;

			/* Container details */
			$containerUrl = NULL;
			$containerTitle = NULL;
			if ( isset( $itemClass::$containerNodeClass ) )
			{
				$containerClass	= $itemClass::$containerNodeClass;
				$containerTitle	= \IPS\Member::loggedIn()->language()->addToStack( $containerClass::$titleLangPrefix . $resultData['indexData']['index_container_id'], 'NULL', array( 'escape' => true ) );
				$containerUrl	= $containerClass::urlFromIndexData( $resultData['indexData'], $resultData['itemData'], $resultData['containerData'] );
			}
					
			/* Reputation - if we are showing the total value, then we need to load them up and total up all of the values */
			if ( \IPS\Settings::i()->reaction_count_display == 'count' )
			{
				$repCount = 0;
				foreach( $resultData['reputationData'] AS $memberId => $reactionId )
				{
					try
					{
						$repCount += \IPS\Content\Reaction::load( $reactionId )->value;
					}
					catch( \OutOfRangeException $e ) {}
				}
			}
			else
			{
				$repCount = \count( $resultData['reputationData'] );
			}

			$commentCount = NULL;

			if( isset( $itemClass::$databaseColumnMap['num_comments'] ) )
			{
				$commentCount = $resultData['itemData'][ $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['num_comments'] ];

				if( $itemClass::$firstCommentRequired === TRUE )
				{
					$commentCount -= 1;
				}
			}

			$reviewCount = NULL;

			if( isset( $itemClass::$databaseColumnMap['num_reviews'] ) )
			{
				$reviewCount = $resultData['itemData'][ $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['num_reviews'] ];
			}
			
			$results[] = array(
				'title'			=> ( isset( $itemClass::$databaseColumnMap['title'] ) and isset( $resultData['itemData'][ $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['title'] ] ) ) ? $resultData['itemData'][ $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['title'] ] : $resultData['indexData']['index_title'],
				'content'		=> $resultData['indexData']['index_content'],
				'class'			=> $indexClass,
				'objectId'		=> $resultData['indexData']['index_object_id'],
				'itemClass'		=> $itemClass,
				'itemId'		=> $resultData['indexData']['index_item_id'],
				'started'		=> $result->createdDate->rfc3339(),
				'updated'		=> $result->lastUpdatedDate->rfc3339(),
				'itemUrl'		=> (string) $itemClass::urlFromIndexData( $resultData['indexData'], $resultData['itemData'] ),
				'objectUrl'		=> (string) $itemClass::urlFromIndexData( $resultData['indexData'], $resultData['itemData'] )->setQueryString( array( 'do' => 'findComment', 'comment' => $resultData['indexData']['index_object_id'] ) ),
				'reputation'	=> $repCount,
				'comments'		=> $commentCount,
				'reviews'		=> $reviewCount,
				'container'		=> $containerTitle,
				'containerUrl'	=> (string) $containerUrl,
				'author'		=> $resultData['authorData']['name'],
				'authorUrl'		=> $resultData['authorData']['member_id'] ? (string) \IPS\Http\Url::internal( "app=core&module=members&controller=profile&id={$resultData['authorData']['member_id']}", 'front', 'profile', $resultData['authorData']['members_seo_name'] ) : NULL,
				'authorPhoto'	=> \IPS\Member::photoUrl( $resultData['authorData'], FALSE ),
				'authorPhotoThumbnail'	=> \IPS\Member::photoUrl( $resultData['authorData'] ),
				'tags'			=> ( !empty( $resultData['indexData']['index_tags'] ) ) ? explode( ',', $resultData['indexData']['index_tags'] ) : array()
			);
		}
				
		return array(
			'page'			=> $this->page,
			'perPage'		=> $this->resultsPerPage,
			'totalResults'	=> $this->count,
			'totalPages'	=> ceil( $this->count / $this->resultsPerPage ),
			'results'		=> $results
		);
	}
}