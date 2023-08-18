<?php
/**
 * @brief		Promotions API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 June 2022
 */

namespace IPS\core\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
    header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * @brief	Promotions API
 */
class _promotions extends \IPS\Node\Api\NodeController
{
    /**
     * GET /core/promotions
     * Get list of promoted items
     *
     * @apiparam	int		page			Page number
     * @apiparam	int		perPage			Number of results per page - defaults to 25
     * @return		\IPS\Api\PaginatedResponse<IPS\core\Promote>
     * @throws		2C429/1	NO_PERMISSION	The current authorized user does not have permission to issue warnings and as such cannot view the list of warn reasons
     */
    public function GETindex()
    {
		$page		= isset( \IPS\Request::i()->page ) ? \IPS\Request::i()->page : 1;
		$perPage	= isset( \IPS\Request::i()->perPage ) ? \IPS\Request::i()->perPage : 25;

		$sortDir = ( isset( \IPS\Request::i()->sortDir ) and \in_array( mb_strtolower( \IPS\Request::i()->sortDir ), array( 'asc', 'desc' ) ) ) ? \IPS\Request::i()->sortDir : 'asc';

		$sortBy = ( isset( \IPS\Request::i()->sortBy ) and \in_array( mb_strtolower( \IPS\Request::i()->sortBy ), array( 'promote_id', 'promote_scheduled' ) ) ) ? \IPS\Request::i()->sortBy  :'promote_id';

		/* Check permissions */
        if( $this->member AND !$this->member->modPermission('mod_see_warn') )
        {
            throw new \IPS\Api\Exception( 'NO_PERMISSION', '2C429/1', 403 );
        }

		/* Return */
		return new \IPS\Api\PaginatedResponse(
			200,
			\IPS\Db::i()->select( '*', 'core_social_promote', array(), "{$sortBy} {$sortDir}" ),
			$page,
			'IPS\core\Promote',
			\IPS\Db::i()->select( 'COUNT(*)', 'core_social_promote', array() )->first(),
			$this->member,
			$perPage
		);
    }

    /**
     * GET /core/promotions/{id}
     * Get specific promotion object
     *
     * @param		int		$id			ID Number
     * @throws		12C429/2	INVALID_ID	The promotion does not exist
     * @return		\IPS\core\Promote
     */
    public function GETitem( $id )
    {
        try
        {
            $promotion = \IPS\core\Promote::load( $id );

			/* Return */
			return new \IPS\Api\Response( 200, $promotion->apiOutput( $this->member ) );
        }
        catch ( \OutOfRangeException $e )
        {
            throw new \IPS\Api\Exception( 'INVALID_ID', '2C429/2', 404 );
        }
    }
}