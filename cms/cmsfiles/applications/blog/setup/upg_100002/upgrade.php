<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		30 Oct 2014
 */

namespace IPS\blog\setup\upg_100002;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix SEO titles
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Db::i()->update( 'blog_blogs', array( 'blog_seo_name' => NULL ) );
		\IPS\Db::i()->update( 'blog_entries', array( 'entry_name_seo' => NULL ) );

		return true;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Resetting blog friendly URL titles";
	}
	
	/**
	 * Fix Polls
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \intval( \IPS\Request::i()->extra ) : 0;
		
		$select = \IPS\Db::i()->select( '*', 'blog_polls', NULL, 'poll_id ASC', array( $limit, 1000 ) );
		
		if ( !$select->count() )
		{
			return TRUE;
		}
		
		foreach ( $select as $poll )
		{
			/* Sometimes data from 3.4.x isn't always as well formed as we'd like, especially when non-latin chars are involved */
			$data	= unserialize( $poll['choices'] );
	
			if( !\is_array( $data ) )
			{
				$poll['choices']	= str_replace( '\\"', '"', $poll['choices'] );
				$data	= unserialize( $poll['choices'] );
			}
	
			$_decoded	= FALSE;
	
			if( !\is_array( $data ) )
			{
				$data	= unserialize( utf8_decode( $poll['choices'] ) );
	
				if( \is_array( $data ) )
				{
					$_decoded	= TRUE;
				}
			}
	
			if( \is_array( $data ) AND $_decoded )
			{
				foreach( $data as $_key => $_data )
				{
					$data[ $_key ]['question']	= utf8_encode( $_data[ $_key ]['question'] );
	
					if( \is_array( $_data['choice'] ) )
					{
						foreach( $_data['choice'] as $_idx => $_choice )
						{
							$data[ $_key ]['choice'][ $_idx ]	= utf8_encode( $_choice );
						}
					}
				}
			}
	
			$result = @json_encode( $data );
			
			/* Sometimes the array unserializes fine but there are still "bad" characters in there */
			if( $result === FALSE )
			{
				$data	= unserialize( $poll['choices'] );
	
				if( !\is_array( $data ) )
				{
					$poll['choices']	= str_replace( '\\"', '"', $poll['choices'] );
					$data	= unserialize( $poll['choices'] );
				}
	
				if( !\is_array( $data ) )
				{
					$data	= unserialize( utf8_decode( $poll['choices'] ) );
				}
	
				if( \is_array( $data ) )
				{
					foreach( $data as $_key => $_data )
					{
						$data[ $_key ]['question']	= utf8_encode( $_data['question'] );
	
						if( \is_array( $_data['choice'] ) )
						{
							foreach( $_data['choice'] as $_idx => $_choice )
							{
								$data[ $_key ]['choice'][ $_idx ] = utf8_encode( $_choice );
							}
						}
						else
						{
							/* No choices, no poll */
							\IPS\Db::i()->delete( 'core_polls', array( 'pid=?', $poll['pid'] ) );
							continue;
						}
					}
				}
	
				$result = @json_encode( $data );
			}
			
			unset( $poll['poll_id'] );
			$poll['choices'] = $result;
			$poll['poll_only'] = 0;
			$poll['poll_view_voters'] = 0;
			
			$entryId = $poll['entry_id'];
			unset( $poll['entry_id'] );
			
			$pollId = \IPS\Db::i()->insert( 'core_polls', $poll );
			\IPS\Db::i()->update( 'blog_entries', array( 'entry_poll_state' => $pollId ), array( 'entry_id=?', $entryId ) );
		}
		
		return $limit + 1000;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Upgrading polls";
	}
	
	/**
	 * Fix Voters
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \intval( \IPS\Request::i()->extra ) : 0;
		
		$select = \IPS\Db::i()->select( '*', 'blog_voters', NULL, 'vote_id ASC', array( $limit, 1000 ) );
		
		if ( !$select->count() )
		{
			return TRUE;
		}
		
		foreach ( $select as $vote )
		{
			try
			{
				$pollId = \IPS\Db::i()->select( 'entry_poll_state', 'blog_entries', array( 'entry_id=?', $vote['entry_id'] ) )->first();
				
				unset( $vote['vote_id'] );
				unset( $vote['entry_id'] );
				$vote['member_choices'] = null;
				$vote['poll'] = $pollId;
				
				\IPS\Db::i()->insert( 'core_voters', $vote );
			}
			catch( \UnderflowException $ex ) { }
		}
		
		return $limit + 1000;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Upgrading poll voters";
	}
}