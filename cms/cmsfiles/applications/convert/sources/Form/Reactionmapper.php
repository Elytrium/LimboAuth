<?php

/**
 * @brief		Custom Reactions Form Helper
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		02 Oct 2019
 */

namespace IPS\convert\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}


class _Reactionmapper extends \IPS\Helpers\Form\FormAbstract
{

	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html() : string
	{
		/* Setup our local reaction descriptions and options */
		foreach( \IPS\Content\Reaction::getStore() as $ipsReaction )
		{
			$this->options['options'][ $ipsReaction['reaction_id'] ] = (string) \IPS\File::get( 'core_Reaction', $ipsReaction['reaction_icon'] )->url;
			$this->options['descriptions'][ $ipsReaction['reaction_id'] ] = \IPS\Member::loggedIn()->language()->addToStack('reaction_title_' . $ipsReaction['reaction_id'] );
		}

		/* Specific options for creating a new reaction */
		$this->options['options'][ 'none' ] = FALSE;
		$this->options['descriptions']['none'] = \IPS\Member::loggedIn()->language()->addToStack('convert_create_reaction' );

		/* Get our controller */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_forms.js', 'convert' ) );

		/* Sort out the pre-selected value */
		$value = $this->value;
		if ( !\is_array( $this->value ) AND $this->value !== NULL AND $this->value !== '' )
		{
			$value = array( $this->value );
		}

		return \IPS\Theme::i()->getTemplate( 'forms' )->reactionmapper( $this->name, $value, $this->options['reactions'], $this->options['options'], $this->options['descriptions'] );
	}

	/**
	 * Get Value
	 *
	 * @return	mixed
	 */
	public function getValue()
	{
		$name = $this->name;
		$value = \IPS\Request::i()->$name;

		return $value;
	}

	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @throws	\DomainException
	 * @return	TRUE
	 */
	public function validate()
	{
		parent::validate();

		/* Check all of the values are present */
		if( \count( $this->value ) !== \count( $this->options['reactions'] ) )
		{
			throw new \InvalidArgumentException( 'err_convert_reaction' );
		}

		/* Check that the values are valid reactions, or 'none' */
		foreach( $this->value as $value )
		{
			/* None is a special value indicating that we create a new reaction */
			if( $value === 'none' )
			{
				continue;
			}

			/* Zero is an unselected reaction */
			if( (int) $value === 0 )
			{
				throw new \InvalidArgumentException( 'err_convert_reaction_not_selected' );
			}

			/* Any value greater than zero is an IPS reaction ID */
			if( (int) $value > 0 )
			{
				try
				{
					\IPS\Content\Reaction::load( $value );
				}
				catch( \OutOfRangeException $e )
				{
					throw new \InvalidArgumentException( 'err_convert_reaction_not_exist' );
				}
			}
		}

		return TRUE;
	}
}