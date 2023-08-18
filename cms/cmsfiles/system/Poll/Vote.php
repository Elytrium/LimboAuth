<?php
/**
 * @brief		Poll Vote Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Jan 2014
 */

namespace IPS\Poll;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Poll Vote Model
 */
class _Vote extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_voters';
	
	/**
	 * @brief	Database ID Column
	 */
	public static $databaseColumnId = 'vid';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'member_id' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * Create from form
	 *
	 * @param	array|NULL	$values	Form values
	 * @return	\IPS\Poll\Vote
	 */
	public static function fromForm( $values )
	{
		$vote = new static;
		$vote->member_id = \IPS\Member::loggedIn()->member_id;
		if ( $values )
		{
			$vote->member_choices = $values;
		}
		$vote->ip_address = \IPS\Request::i()->ipAddress();
		return $vote;
	}
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->vote_date = new \IPS\DateTime;
	}
	
	/**
	 * Set vote date
	 *
	 * @param	\IPS\DateTime	$value	Value
	 * @return	void
	 */
	public function set_vote_date( \IPS\DateTime $value )
	{
		$this->_data['vote_date'] = $value->getTimestamp();
	}
	
	/**
	 * Get vote date
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_vote_date()
	{
		return \IPS\DateTime::ts( $this->_data['vote_date'] );
	}
	
	/**
	 * Set choices
	 *
	 * @param	array	$value	Value
	 * @return	void
	 */
	public function set_member_choices( array $value )
	{
		$this->_data['member_choices'] = json_encode( $value );
	}
	
	/**
	 * Get choices
	 *
	 * @return	array
	 */
	public function get_member_choices()
	{
		return isset( $this->_data['member_choices'] ) ? json_decode( $this->_data['member_choices'], TRUE ) : NULL;
	}
	
	/**
	 * Set poll
	 *
	 * @param	\IPS\Poll	$value	Value
	 * @return	void
	 */
	public function set_poll( \IPS\Poll $value )
	{
		$this->_data['poll'] = $value->pid;
	}
	
	/**
	 * Get poll
	 *
	 * @return	array
	 */
	public function get_poll()
	{
		return \IPS\Poll::load( $this->_data['poll'] );
	}
	
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		if ( $this->member_choices !== NULL )
		{
			$choices = $this->poll->choices;
			foreach ( $this->member_choices as $k => $v )
			{
				if ( isset( $choices[ $k ] ) ) // If the question has been deleted since this vote was cast, this won't be set
				{
					if ( \is_array( $v ) )
					{
						foreach ( $v as $key => $memberValues )
						{
							if ( $choices[ $k ]['votes'][ $memberValues ] > 0 )
							{
								$choices[ $k ]['votes'][ $memberValues ]--;
							}
						}
					}
					else
					{
						if ( $choices[ $k ]['votes'][ $v ] > 0 )
						{
							$choices[ $k ]['votes'][ $v ]--;
						}
					}
				}

			}
			$this->poll->choices = $choices;
			$this->poll->save();
		}
		
		parent::delete();
	}
}