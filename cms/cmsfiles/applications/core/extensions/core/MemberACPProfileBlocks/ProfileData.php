<?php
/**
 * @brief		ACP Member Profile: Profile Data Block
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Nov 2017
 */

namespace IPS\core\extensions\core\MemberACPProfileBlocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: Profile Data Block
 */
class _ProfileData extends \IPS\core\MemberACPProfile\TabbedBlock
{
	/**
	 * @brief	Fields
	 */
	protected $fields = array();
	
	/**
	 * @brief	Clubs
	 */
	protected $clubs = NULL;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	void
	 */
	public function __construct( \IPS\Member $member )
	{
		parent::__construct( $member );
		
		$this->fields = $this->member->profileFields( \IPS\core\ProfileFields\Field::STAFF );
		$this->clubs = \IPS\Settings::i()->clubs ? \IPS\Member\Club::clubs( NULL, NULL, 'last_activity', array( 'member' => $this->member, 'statuses' => array( \IPS\Member\Club::STATUS_MODERATOR, \IPS\Member\Club::STATUS_EXPIRED_MODERATOR, \IPS\Member\Club::STATUS_LEADER ) ) ) : array();
	}
	
	/**
	 * Get Block Title
	 *
	 * @return	string
	 */
	public function blockTitle()
	{
		return 'profile_data';
	}
	
	/**
	 * Get Tab Names
	 *
	 * @return	string
	 */
	public function tabs()
	{
		$return = array();
		if ( \count( $this->fields ) || $this->member->rank['title'] || $this->member->rank['image'] || \IPS\Settings::i()->profile_birthday_type != 'none' || \IPS\Settings::i()->signatures_enabled )
		{
			$return['fields'] = 'profile_fields';
		}		
		if ( \count( $this->clubs ) )
		{
			$return['clubs'] = 'club_ownership';
		}
		
		return $return;
	}
	
	/**
	 * Show Edit Link?
	 *
	 * @return	bool
	 */
	protected function showEditLink()
	{
		return true;
	}

	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function tabOutput( $tab )
	{
		if ( $tab == 'fields' )
		{
			return \IPS\Theme::i()->getTemplate('memberprofile')->profileData( $this->member, $this->fields );
		}
		elseif ( $tab == 'clubs' )
		{			
			return \IPS\Theme::i()->getTemplate('memberprofile')->clubs( $this->member, $this->clubs );
		}
	}
	
	/**
	 * Edit Window
	 *
	 * @return	string
	 */
	public function edit()
	{
		/* Build basic form */
		$form = new \IPS\Helpers\Form;
		$form->addHeader('profile_data');
		if ( \IPS\Settings::i()->profile_birthday_type !== 'none' )
		{
			$form->add( new \IPS\Helpers\Form\Custom( 'bday', array( 'year' => $this->member->bday_year, 'month' => $this->member->bday_month, 'day' => $this->member->bday_day ), FALSE, array( 'getHtml' => function( $element )
			{
				return strtr( \IPS\Member::loggedIn()->language()->preferredDateFormat(), array(
					'DD'	=> \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->bdayForm_day( $element->name, $element->value, $element->error ),
					'MM'	=> \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->bdayForm_month( $element->name, $element->value, $element->error ),
					'YY'	=> \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->bdayForm_year( $element->name, $element->value, $element->error ),
					'YYYY'	=> \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->bdayForm_year( $element->name, $element->value, $element->error ),
				) );
			} ) ) );
		}
		if ( \IPS\Settings::i()->signatures_enabled )
		{
			$form->add( new \IPS\Helpers\Form\Editor( 'signature', $this->member->signature, FALSE, array( 'app' => 'core', 'key' => 'Signatures', 'autoSaveKey' => "sig-{$this->member->member_id}", 'attachIds' => array( $this->member->member_id ) ) ) );
		}
	
		/* Profile Fields */
		try
		{
			$values = \IPS\Db::i()->select( '*', 'core_pfields_content', array( 'member_id=?', $this->member->member_id ) )->first();
		}
		catch( \UnderflowException $e )
		{
			$values	= array();
		}
		if( \count( $values ) )
		{
			foreach ( \IPS\core\ProfileFields\Field::fields( $values, \IPS\core\ProfileFields\Field::STAFF, $this->member ) as $group => $fields )
			{
				$form->addHeader( "core_pfieldgroups_{$group}" );
				foreach ( $fields as $field )
				{
					$form->add( $field );
				}
			}
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Profile Fields */
			try
			{
				$profileFields = \IPS\Db::i()->select( '*', 'core_pfields_content', array( 'member_id=?', $this->member->member_id ) )->first();
				
				if ( !\is_array( $profileFields ) ) // If \IPS\Db::i()->select()->first() has only one column, then the contents of that column is returned. We do not want this here
				{
					$profileFields = array();
				}
			}
			catch( \UnderflowException $e )
			{
				$profileFields	= array();
			}			
			$profileFields['member_id'] = $this->member->member_id;
			foreach ( \IPS\core\ProfileFields\Field::fields( $profileFields, \IPS\core\ProfileFields\Field::STAFF, $this->member ) as $group => $fields )
			{
				foreach ( $fields as $id => $field )
				{
					if ( $field instanceof \IPS\Helpers\Form\Upload )
					{
						$profileFields[ "field_{$id}" ] = (string) $values[ $field->name ];
					}
					else
					{
						$profileFields[ "field_{$id}" ] = $field::stringValue( !empty( $values[ $field->name ] ) ? $values[ $field->name ] : NULL );
					}
				}
			}
			$this->member->changedCustomFields = $profileFields;
			\IPS\Db::i()->replace( 'core_pfields_content', $profileFields );

			/* Profile Preferences */
			if ( $values['bday'] )
			{
				$this->member->bday_day	= $values['bday']['day'];
				$this->member->bday_month	= $values['bday']['month'];
				$this->member->bday_year	= $values['bday']['year'];
			}
			else
			{
				$this->member->bday_day = NULL;
				$this->member->bday_month = NULL;
				$this->member->bday_year = NULL;
			}
			if ( \IPS\Settings::i()->signatures_enabled )
			{
				$this->member->signature = $values['signature'];
			}
			$this->member->save();
											
			/* Log and Redirect */
			\IPS\Session::i()->log( 'acplog__members_edited_profile', array( $this->member->name => FALSE ) );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=view&id={$this->member->member_id}" ), 'saved' );
		}
		
		/* Display */
		return $form;
	}
}