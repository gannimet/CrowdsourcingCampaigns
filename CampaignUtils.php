<?php

namespace CrowdsourcingCampaign;

class CampaignUtils {

	public static function doesISOStringContainDate($isoString) {
		$result = \DateTime::createFromFormat(\DateTime::ISO8601, $isoString);

		return !!$result;
	}

	public static function getDateTimeFromISOString($isoString) {
		$result = \DateTime::createFromFormat(\DateTime::ISO8601, $isoString);
		if (!$result) {
			$result = \DateTime::createFromFormat('H:i:sT', $isoString);
		}

		return $result;
	}

	public static function stripTrues($carry, $item) {
		if ($item !== true) {
			$carry[] = $item;
		}

		return $carry;
	}

	public static function getNumericTimeRepresentation($dateTime) {
		$hours = intval($dateTime->format('H'));
		$minutes = intval($dateTime->format('i'));
		$seconds = intval($dateTime->format('s'));

		return $hours * 60 * 60 + $minutes * 60 + $seconds;
	}

	public static function getWeekdayName($dateTime) {
		return $dateTime->format('l');
	}

	public static function getTimestamp($dateTime, $withDate) {
		if ($withDate) {
			return $dateTime->getTimestamp();
		}

		return CampaignUtils::getNumericTimeRepresentation($dateTime);
	}

	public static function getExcludedWeekdays($excludeWeekdaysElement) {
		$result = array();
		foreach ($excludeWeekdaysElement->weekday as $weekdayElement) {
			$result[] = (string) $weekdayElement;
		}

		return $result;
	}

	public static function getQualificationScores($educationElement) {
		$result = array();
		foreach ($educationElement->qualification as $qualification) {
			$result[(string) $qualification->attributes()->name] = floatval($qualification->attributes()->score);
		}

		return $result;
	}

	public static function getLanguageScores($languagesElement) {
		$result = array();
		foreach ($languagesElement->language as $language) {
			$code = (string) $language->attributes()->code;
			$native = strtolower($language->attributes()->native) === 'true' ? true : false;
			$score = floatval($language->attributes()->score);

			if (array_key_exists($code, $result)) {
				$result[$code] = array(
					'native' => $native ? $score : $result[$code]['native'],
					'non-native' => !$native ? $score : $result[$code]['non-native']
				);
			} else {
				$result[$code] = array(
					'native' => $score,
					'non-native' => !$native ? $score : 0
				);
			}
		}

		return $result;
	}

	public static function doesFormulaContainQuotes($formula) {
		return strpos($formula, '\'') !== false || strpos($formula, '"') !== false;
	}

	public static function getNonAllowedIdentifiersInFormula($formula, $allowedIdentifiers) {
		// Find all identifiers inside the formula
		preg_match_all("/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/", $formula, $hits);
		$identifiersInFormula = $hits[0];

		$result = array();
		foreach ($identifiersInFormula as $identifier) {
			if (!in_array($identifier, $allowedIdentifiers)) {
				$result[] = $identifier;
			}
		}

		return $result;
	}

}

?>
