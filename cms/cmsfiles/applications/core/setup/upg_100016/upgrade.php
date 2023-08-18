<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		24 Feb 2015
 */

namespace IPS\core\setup\upg_100016;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 RC 3 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Convert question & answer
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach( \IPS\Db::i()->select( 'qa_id, qa_answers', 'core_question_and_answer' ) as $qa )
		{
			if ( \is_array( json_decode( $qa['qa_answers'], TRUE ) ) )
			{
				continue;
			}

			$answers = explode( "\n", \str_replace( "\r", "", $qa['qa_answers'] ) );
			\IPS\Db::i()->update( 'core_question_and_answer', array( 'qa_answers' => json_encode( $answers ) ), array( 'qa_id=?', $qa['qa_id'] ) );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Updating question & answer";
	}
}