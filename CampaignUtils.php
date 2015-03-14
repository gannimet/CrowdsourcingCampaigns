<?php

namespace CrowdsourcingCampaign\Utils;

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

}

?>
