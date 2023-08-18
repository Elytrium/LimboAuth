<?php
/**
 * Wolfram|Alpha password strength calculation
 *
 * @see     https://gist.github.com/1514997 (original source this class is based on)
 * @see     http://www.wolframalpha.com/input/?i=password+strength+for+god
 * @see     https://github.com/rchouinard/phpass
 * @license MIT
 */
class PasswordStrength {

	const STRENGTH_VERY_WEAK   = 1;
	const STRENGTH_WEAK        = 2;
	const STRENGTH_FAIR        = 3;
	const STRENGTH_STRONG      = 4;
	const STRENGTH_VERY_STRONG = 5;

	public function classifyScore($score) {
		if ($score < 50) return self::STRENGTH_VERY_WEAK;
		if ($score < 60) return self::STRENGTH_WEAK;
		if ($score < 75) return self::STRENGTH_FAIR;
		if ($score < 90) return self::STRENGTH_STRONG;
		return self::STRENGTH_VERY_STRONG;
	}

	public function classify($pw) {
		return $this->classifyScore($this->calculate($pw));
	}

	/**
	 * Calculate score for a password
	 *
	 * @param  string $pw  the password to work on
	 * @return int         score
	 */
	public function calculate($pw) {
		$length    = strlen($pw);
		$score     = $length * 4;
		$nUpper    = 0;
		$nLower    = 0;
		$nNum      = 0;
		$nSymbol   = 0;
		$locUpper  = array();
		$locLower  = array();
		$locNum    = array();
		$locSymbol = array();
		$charDict  = array();
		// count character classes
		for ($i = 0; $i < $length; ++$i) {
			$ch   = $pw[$i];
			$code = ord($ch);
			/* [0-9] */ if     ($code >= 48 && $code <= 57)  { $nNum++;    $locNum[]    = $i; }
			/* [A-Z] */ elseif ($code >= 65 && $code <= 90)  { $nUpper++;  $locUpper[]  = $i; }
			/* [a-z] */ elseif ($code >= 97 && $code <= 122) { $nLower++;  $locLower[]  = $i; }
			/* .     */ else                                 { $nSymbol++; $locSymbol[] = $i; }
			if (!isset($charDict[$ch])) {
				$charDict[$ch] = 1;
			}
			else {
				$charDict[$ch]++;
			}
		}
		// reward upper/lower characters if pw is not made up of only either one
		if ($nUpper !== $length && $nLower !== $length) {
			if ($nUpper !== 0) {
				$score += ($length - $nUpper) * 2;
			}
			if ($nLower !== 0) {
				$score += ($length - $nLower) * 2;
			}
		}
		// reward numbers if pw is not made up of only numbers
		if ($nNum !== $length) {
			$score += $nNum * 4;
		}
		// reward symbols
		$score += $nSymbol * 6;
		// middle number or symbol
		foreach (array($locNum, $locSymbol) as $list) {
			$reward = 0;
			foreach ($list as $i) {
				$reward += ($i !== 0 && $i !== $length -1) ? 1 : 0;
			}
			$score += $reward * 2;
		}
		// chars only
		if ($nUpper + $nLower === $length) {
			$score -= $length;
		}
		// numbers only
		if ($nNum === $length) {
			$score -= $length;
		}
		// repeating chars
		$repeats = 0;
		foreach ($charDict as $count) {
			if ($count > 1) {
				$repeats += $count - 1;
			}
		}
		if ($repeats > 0) {
			$score -= (int) (floor($repeats / ($length-$repeats)) + 1);
		}
		if ($length > 2) {
			// consecutive letters and numbers
			foreach (array('/[a-z]{2,}/', '/[A-Z]{2,}/', '/[0-9]{2,}/') as $re) {
				preg_match_all($re, $pw, $matches, PREG_SET_ORDER);
				if (!empty($matches)) {
					foreach ($matches as $match) {
						$score -= (strlen($match[0]) - 1) * 2;
					}
				}
			}
			// sequential letters
			$locLetters = array_merge($locUpper, $locLower);
			sort($locLetters);
			foreach ($this->findSequence($locLetters, mb_strtolower($pw)) as $seq) {
				if (count($seq) > 2) {
					$score -= (count($seq) - 2) * 2;
				}
			}
			// sequential numbers
			foreach ($this->findSequence($locNum, mb_strtolower($pw)) as $seq) {
				if (count($seq) > 2) {
					$score -= (count($seq) - 2) * 2;
				}
			}
		}
		return $score;
	}
	/**
	 * Find all sequential chars in string $src
	 *
	 * Only chars in $charLocs are considered. $charLocs is a list of numbers.
	 * For example if $charLocs is [0,2,3], then only $src[2:3] is a possible
	 * substring with sequential chars.
	 *
	 * @param  array  $charLocs
	 * @param  string $src
	 * @return array             [[c,c,c,c], [a,a,a], ...]
	 */
	private function findSequence($charLocs, $src) {
		$sequences = array();
		$sequence  = array();
		for ($i = 0; $i < count($charLocs)-1; ++$i) {
			$here         = $charLocs[$i];
			$next         = $charLocs[$i+1];
			$charHere     = $src[$charLocs[$i]];
			$charNext     = $src[$charLocs[$i+1]];
			$distance     = $next - $here;
			$charDistance = ord($charNext) - ord($charHere);
			if ($distance === 1 && $charDistance === 1) {
				// We find a pair of sequential chars!
				if (empty($sequence)) {
					$sequence = array($charHere, $charNext);
				}
				else {
					$sequence[] = $charNext;
				}
			}
			elseif (!empty($sequence)) {
				$sequences[] = $sequence;
				$sequence    = array();
			}
		}
		if (!empty($sequence)) {
			$sequences[] = $sequence;
		}
		return $sequences;
	}
}