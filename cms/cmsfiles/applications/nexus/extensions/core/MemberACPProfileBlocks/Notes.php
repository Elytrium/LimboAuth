<?php
/**
 * @brief		ACP Member Profile: Notes
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		5 Dec 2017
 */

namespace IPS\nexus\extensions\core\MemberACPProfileBlocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: Notes
 */
class _Notes extends \IPS\core\MemberACPProfile\Block
{
	/**
	 * @brief	Notes
	 */
	protected $notes;
	
	/**
	 * @brief	Note Count
	 */
	protected $noteCount;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	void
	 */
	public function __construct( \IPS\Member $member )
	{
		parent::__construct( $member );
		
		$this->notes = NULL;
		$this->noteCount = 0;
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'customer_notes_view' ) )
		{
			$this->noteCount = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_notes', array( 'note_member=?', $this->member->member_id ) )->first();
			
			$this->notes = new \IPS\Helpers\Table\Db( 'nexus_notes', $this->member->acpUrl()->setQueryString( array( 'view' => 'notes', 'support' => isset( \IPS\Request::i()->support ) ? \IPS\Request::i()->support : 0 ) ), array( 'note_member=?', $this->member->member_id ) );
			$this->notes->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'customers', 'nexus' ), 'notes' );
			$this->notes->sortBy = 'note_date';
			
			$this->notes->parsers = array(
				'note_member'	=> function( $val )
				{
					return \IPS\Member::load( $val );
				},
				'note_text'		=> function( $val )
				{
					return $val;
				}
			);
			
			$this->notes->rowButtons = function( $row )
			{
				$return = array();
				if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'customer_notes_edit' ) )
				{
					if ( !isset( \IPS\Request::i()->support ) or !\IPS\Request::i()->support )
					{
						$return['edit'] = array(
							'link'	=> \IPS\Http\Url::internal("app=nexus&module=customers&controller=view&id={$this->member->member_id}")->setQueryString( array( 'do' => 'noteForm', 'note_id' => $row['note_id'], 'support' => isset( \IPS\Request::i()->support ) ? \IPS\Request::i()->support : 0 ) ),
							'title'	=> 'edit',
							'icon'	=> 'pencil',
							'data'	=> array( 'ipsDialog' => true, 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit_note') )
						);
					}
					else
					{
						$return['edit'] = array(
							'link'	=> \IPS\Http\Url::internal("app=nexus&module=customers&controller=view&id={$this->member->member_id}")->setQueryString( array( 'do' => 'noteForm', 'note_id' => $row['note_id'], 'support' => isset( \IPS\Request::i()->support ) ? \IPS\Request::i()->support : 0 ) ),
							'title'	=> 'edit',
							'icon'	=> 'pencil',
						);
					}
				}
				if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'customer_notes_delete' ) )
				{
					$return['delete'] = array(
						'link'	=> \IPS\Http\Url::internal("app=nexus&module=customers&controller=view&id={$this->member->member_id}")->setQueryString( array( 'do' => 'deleteNote', 'note_id' => $row['note_id'], 'support' => isset( \IPS\Request::i()->support ) ? \IPS\Request::i()->support : 0 ) )->csrf(),
						'title'	=> 'delete',
						'icon'	=> 'times-circle',
						'data'	=> array( 'confirm' => '' )
					);
				}
				return $return;
			};
			
			if ( ( !isset( \IPS\Request::i()->support ) or !\IPS\Request::i()->support ) and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'customer_notes_add' ) )
			{
				$this->notes->rootButtons = array(
					'add'	=> array(
						'link'	=> \IPS\Http\Url::internal("app=nexus&module=customers&controller=view&id={$this->member->member_id}")->setQueryString( array( 'do' => 'noteForm', 'support' => isset( \IPS\Request::i()->support ) ? \IPS\Request::i()->support : 0 ) ),
						'title'	=> 'add',
						'icon'	=> 'plus',
						'data'	=> array( 'ipsDialog' => true, 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('add_note') )
					)
				);
			}
		}
	}
	
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output()
	{
		$this->notes->limit = 2;
		$this->notes->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'customers', 'nexus' ), 'notesOverview' );
		$this->notes->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'customers', 'nexus' ), 'notesOverviewRows' );
		
		return \IPS\Theme::i()->getTemplate( 'customers', 'nexus' )->notesBlock( $this->member, $this->noteCount, $this->notes );
	}
	
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function lazyOutput()
	{
		return \IPS\Theme::i()->getTemplate( 'customers', 'nexus' )->customerPopup( $this->notes );
	}
}