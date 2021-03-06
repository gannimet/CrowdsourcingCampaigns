<?php

namespace CrowdsourcingCampaign;

include_once('CampaignUtils.php');

use \DateTime;

class GroundTruthAnswer {

	private $nr;
	private $text;
	private $correct;

	public function __construct($nr, $text, $correct) {
		$this->nr = $nr;
		$this->text = $text;
		$this->correct = $correct;
	}

	public function getNr() {
		return $this->nr;
	}

	public function getText() {
		return $this->text;
	}

	public function isCorrect() {
		return $this->correct;
	}

}

class GroundTruthQuestion {

	private $id;
	private $text;
	private $answers;
	private $score;

	public function __construct($id, $text, $answers, $score) {
		$this->id = $id;
		$this->text = $text;
		$this->answers = $answers;
		$this->score = $score;
	}

	public function getID() {
		return $this->id;
	}

	public function getText() {
		return $this->text;
	}

	public function getAnswers() {
		return $this->answers;
	}

	public function getScore() {
		return $this->score;
	}

}

class Location {

	private $latitude;
	private $longitude;

	private static $EARTH_RADIUS = 6378.0;

	public function __construct($latitude, $longitude) {
		$this->latitude = $latitude;
		$this->longitude = $longitude;
	}

	public function getLatitude() {
		return $this->latitude;
	}

	public function getLongitude() {
		return $this->longitude;
	}

	public function distanceTo($otherLocation) {
		$lat_1 = $this->getLatitude() / 180.0 * M_PI;
		$lon_1 = $this->getLongitude() / 180.0 * M_PI;
		$lat_2 = $otherLocation->getLatitude() / 180.0 * M_PI;
		$lon_2 = $otherLocation->getLongitude() / 180.0 * M_PI;

		$e = acos(
			sin($lat_1) * sin($lat_2) +
			cos($lat_1) * cos($lat_2) * cos($lon_2 - $lon_1)
		);

		return $e * Location::$EARTH_RADIUS;
	}

	public function getObfuscatedCopy($m) {
		$lat_real = $this->getLatitude();
		$lon_real = $this->getLongitude();

		// Step 1
		$d_lat = 111.31949;
		$delta_lat_max = $m / $d_lat;

		// Step 2
		$delta_lat = (mt_rand() / mt_getrandmax()) * $delta_lat_max;

		// Step 3
		$northSouthShift = (mt_rand() / mt_getrandmax() - 0.5) > 0 ? 1 : -1;

		// Step 4
		$lat_obf = $lat_real + $northSouthShift * $delta_lat;

		// Step 5
		$delta_lon_max = rad2deg(
			acos(
				(
					cos($m / Location::$EARTH_RADIUS) -
					sin(deg2rad($lat_real)) * sin(deg2rad($lat_obf))
				) / (
					cos(deg2rad($lat_real)) * cos(deg2rad($lat_obf))
				)
			)
		);

		// Step 6
		$delta_lon = (mt_rand() / mt_getrandmax()) * $delta_lon_max;

		// Step 7
		$eastWestShift = (mt_rand() / mt_getrandmax() - 0.5) > 0 ? 1 : -1;

		// Step 8
		$lon_obf = $lon_real + $eastWestShift * $delta_lon;

		return new Location($lat_obf, $lon_obf);
	}

}

class Campaign {

	private $groundTruth;
	private $target;
	private $reward;
	private $namespace;

	public function __construct($filename) {
		$doc = simplexml_load_file($filename);

		$namespaces = $doc->getNameSpaces(true);
		$this->namespace = $namespaces['cc'];
		
		$this->groundTruth = $doc->children($this->namespace)->{'ground-truth'};
		$this->target = $doc->children($this->namespace)->target;
		$this->reward = $doc->children($this->namespace)->reward;
	}

	public function hasGroundTruth() {
		return !!$this->groundTruth;
	}

	public function hasTarget() {
		return !!$this->target;
	}

	public function hasReward() {
		return !!$this->reward;
	}

	public function getGroundTruthQuestions() {
		if (!$this->groundTruth) {
			return NULL;
		}

		$questions = array();
		$counter = 0;

		foreach ($this->groundTruth->question as $questionElement) {
			$questionText = (string) $questionElement->text;
			$questionScore = floatval($questionElement->attributes()->{'score-max'});
			$answers = array();
			$answerCounter = 0;

			foreach ($questionElement->answer as $answerElement) {
				$answerText = (string) $answerElement;
				$answerCorrect = strtolower($answerElement->attributes()->correct) === 'true';

				$answers[] = new GroundTruthAnswer($answerCounter, $answerText, $answerCorrect);
				$answerCounter++;
			}

			$questions[] = new GroundTruthQuestion($counter, $questionText, $answers, $questionScore);

			$counter++;
		}

		return $questions;
	}

	public function getGroundTruthQuestion($id) {
		if (!$this->groundTruth) {
			return NULL;
		}

		$questions = $this->getGroundTruthQuestions();
		return $questions[$id];
	}

	public function getGroundTruthScoreForQuestion($id, $checkedAnswers) {
		$question = $this->getGroundTruthQuestion($id);
		$answers = $question->getAnswers();

		// Max score
		$s_max = $question->getScore();
		// Number of correct answers
		$c_max = 0; // to be determined
		// Number of answer options
		$a_max = count($answers);
		// Number of checked answers
		$a_gew = count($checkedAnswers);
		// Number of correctly checked answers
		$c_gew = 0; // to be determined

		foreach ($answers as $answer) {
			if ($answer->isCorrect()) {
				$c_max++;

				if (in_array($answer->getNr(), $checkedAnswers)) {
					$c_gew++;
				}
			}
		}

		// Basic points
		$s_basic = $s_max / $c_max * $c_gew;
		// Basic guess level
		$b = $c_max / $a_max;
		// User guess level
		$n = $a_gew / $a_max;
		// Penalty
		$s_penalty = $n > $b ? ($n - $b) * $s_max / (1 - $b) : 0;

		// Final score
		$score = $s_basic > $s_penalty ? $s_basic - $s_penalty : 0;

		return $score;
	}

	public function getGroundTruthScore($answerPatterns) {
		if (!$this->groundTruth) {
			return NULL;
		}

		$scoreThreshold = floatval($this->groundTruth->attributes()->{'score-threshold'});
		$questions = $this->getGroundTruthQuestions();

		if (count($questions) !== count($answerPatterns)) {
			throw 'Question count and answer pattern count do not match';
		}

		$resultScore = 0;
		foreach ($questions as $question) {
			$questionID = $question->getID();
			$resultScore += $this->getGroundTruthScoreForQuestion($questionID, $answerPatterns[$questionID]);
		}

		// Is the result score greater or equal to the threshold?
		return $resultScore >= $scoreThreshold ? $resultScore : 0;
	}

	private function getGenericScoreForLinearDistribution($d, $d_min, $d_zentral, $d_max, $s_min, $s_max) {
		if ($d < $d_min || $d > $d_max) {
			return 0;
		}

		if ($d < $d_zentral) {
			return ($s_max - $s_min) / ($d_zentral - $d_min) * ($d - $d_min) + $s_min;
		}

		return ($s_max - $s_min) / ($d_zentral - $d_max) * ($d - $d_max) + $s_min;
	}

	private function getGenericScoreForUnlimitedLinearDistribution($d, $d_min, $d_reference, $s_min, $s_reference) {
		if ($d < $d_min) {
			return 0;
		}

		return ($s_reference - $s_min) / ($d_reference - $d_min) * ($d - $d_min) + $s_min;
	}

	public function getAgeScore($d) {
		$ageElement = $this->target->age;

		if (!$ageElement) {
			return 0;
		}

		$d_min = floatval($ageElement->min);
		$d_max = floatval($ageElement->max);
		$s_max = floatval($ageElement->attributes()->{'score-max'});

		if ($d < $d_min || $d > $d_max) {
			return 0;
		}

		$applyLinearDistribution = strtolower($ageElement->attributes()->{'score-dist'}) === 'linear';
		if ($applyLinearDistribution) {
			// linear score distribution
			$d_zentral = floatval($ageElement->mean);
			$s_min = floatval($ageElement->attributes()->{'score-min'});

			return $this->getGenericScoreForLinearDistribution($d, $d_min, $d_zentral, $d_max, $s_min, $s_max);
		} else {
			// binary score distribution
			return $s_max;
		}
	}

	public function getTimeScore($actualTime) {
		$timeElement = $this->target->time;

		if (!$timeElement) {
			return 0;
		}

		$excludeWeekdaysElement = $timeElement->{'exclude-weekdays'};
		if ($excludeWeekdaysElement) {
			$excludedWeekdays = CampaignUtils::getExcludedWeekdays($excludeWeekdaysElement);
			$discriminatorWeekday = CampaignUtils::getWeekdayName($actualTime);

			if (in_array($discriminatorWeekday, $excludedWeekdays)) {
				// Weekday should be excluded, so score of zero
				return 0;
			}
		}

		$timeElementMin = (string) $timeElement->min;
		$timeElementMax = (string) $timeElement->max;
		$s_max = floatval($timeElement->attributes()->{'score-max'});

		// Are the times in the xml specified with or without a date?
		$withDate = CampaignUtils::doesISOStringContainDate($timeElementMin);

		$d = CampaignUtils::getTimestamp($actualTime, $withDate);
		$d_min = CampaignUtils::getTimestamp(
			CampaignUtils::getDateTimeFromISOString($timeElementMin),
			$withDate
		);
		$d_max = CampaignUtils::getTimestamp(
			CampaignUtils::getDateTimeFromISOString($timeElementMax),
			$withDate
		);

		if ($d < $d_min || $d > $d_max) {
			return 0;
		}

		$applyLinearDistribution = strtolower($timeElement->attributes()->{'score-dist'}) === 'linear';
		if ($applyLinearDistribution) {
			// linear score distribution
			$d_zentral = CampaignUtils::getTimestamp(
				CampaignUtils::getDateTimeFromISOString((string) $timeElement->mean),
				$withDate
			);
			$s_min = floatval($timeElement->attributes()->{'score-min'});

			return $this->getGenericScoreForLinearDistribution($d, $d_min, $d_zentral, $d_max, $s_min, $s_max);
		} else {
			// binary score distribution
			return $s_max;
		}
	}

	public function getLocationScore($actualLocation) {
		$locationElement = $this->target->location;

		if (!$locationElement) {
			return 0;
		}

		$targetedLatitude = floatval($locationElement->mean->lat);
		$targetedLongitude = floatval($locationElement->mean->lon);
		$targetedLocation = new Location($targetedLatitude, $targetedLongitude);
		$maxDistance = floatval($locationElement->{'max-distance'});
		$actualDistance = $targetedLocation->distanceTo($actualLocation);
		$s_max = floatval($locationElement->attributes()->{'score-max'});

		$applyLinearDistribution = strtolower($locationElement->attributes()->{'score-dist'}) === 'linear';
		if ($applyLinearDistribution) {
			$s_min = floatval($locationElement->attributes()->{'score-min'});

			return $this->getGenericScoreForLinearDistribution($actualDistance, 0, 0, $maxDistance, $s_min, $s_max);
		} else {
			// binary score distribution
			if ($actualDistance <= $maxDistance) {
				return $s_max;
			}
		}

		return 0;
	}

	public function getEducationScore($d) {
		$educationElement = $this->target->education;

		if (!$educationElement) {
			return 0;
		}

		$educationScores = CampaignUtils::getQualificationScores($educationElement);

		if (array_key_exists($d, $educationScores)) {
			return $educationScores[$d];
		}

		return 0;
	}

	public function getLanguagesScore($languages) {
		$languagesElement = $this->target->languages;

		if (!$languagesElement) {
			return 0;
		}

		$languageScores = CampaignUtils::getLanguageScores($languagesElement);

		$resultScore = 0;
		foreach ($languages as $userLanguage => $userNative) {
			// for each language the user speaks …
			if (array_key_exists($userLanguage, $languageScores)) {
				// check whether it should be scored
				$targetLanguage = $languageScores[$userLanguage];

				$resultScore += $userNative ? $targetLanguage['native'] : $targetLanguage['non-native'];
			}
		}

		return $resultScore;
	}

	public function getRewardPointsScore($d) {
		$rewardPointsElement = $this->target->{'reward-points'};

		if (!$rewardPointsElement) {
			return 0;
		}

		$d_min = floatval($rewardPointsElement->min);

		$applyLinearDistribution = strtolower($rewardPointsElement->attributes()->{'score-dist'}) === 'linear';
		if ($applyLinearDistribution) {
			// linear distribution
			$s_min = floatval($rewardPointsElement->attributes()->{'score-min'});

			$useClassicalLinearDistribution = !!$rewardPointsElement->mean;
			if ($useClassicalLinearDistribution) {
				// good old linear distribution with min, mean and max
				$d_zentral = floatval($rewardPointsElement->mean);
				$d_max = floatval($rewardPointsElement->max);
				$s_max = floatval($rewardPointsElement->attributes()->{'score-max'});

				return $this->getGenericScoreForLinearDistribution($d, $d_min, $d_zentral, $d_max, $s_min, $s_max);
			} else {
				// extra special fancy linear distribution with reference
				$d_reference = floatval($rewardPointsElement->reference);
				$s_reference = floatval($rewardPointsElement->attributes()->{'score-reference'});

				return $this->getGenericScoreForUnlimitedLinearDistribution($d, $d_min, $d_reference, $s_min, $s_reference);
			}
		} else {
			// binary distribution
			$s_max = floatval($rewardPointsElement->attributes()->{'score-max'});
			$maxElement = $rewardPointsElement->max;

			if ($maxElement) {
				$d_max = floatval($maxElement);

				if ($d < $d_min || $d > $d_max) {
					return 0;
				}

				return $s_max;
			}

			if ($d < $d_min) {
				return 0;
			}

			return $s_max;
		}
	}

	public function getTargetScore($age, $time, $location, $qualification, $languages, $rewardPoints) {
		if (!$this->target) {
			return NULL;
		}

		$ageScore = $age !== NULL ? $this->getAgeScore($age) : 0;
		$timeScore = $time !== NULL ? $this->getTimeScore($time) : 0;
		$locationScore = $location !== NULL ? $this->getLocationScore($location) : 0;
		$educationScore = $qualification !== NULL ? $this->getEducationScore($qualification) : 0;
		$languagesScore = $languages !== NULL ? $this->getLanguagesScore($languages) : 0;
		$rewardPointsScore = $rewardPoints != NULL ? $this->getRewardPointsScore($rewardPoints) : 0;

		$result = $ageScore + $timeScore + $locationScore +
			$educationScore + $languagesScore + $rewardPointsScore;

		$threshold = floatval($this->target->attributes()->{'score-threshold'});

		return $result >= $threshold ? $result : 0;
	}

	public function getRewardPoints($groundTruthScore, $targetScore) {
		if (!$this->hasReward()) {
			return 0;
		}

		$formula = (string) $this->reward->formula;
		
		// Quotation characters inside the formula are invalid
		if (CampaignUtils::doesFormulaContainQuotes($formula)) {
			throw 'A quotation mark appears inside the reward formula.';
		}

		$allowedIdentifiers = array();
		if ($this->hasTarget()) {
			$allowedIdentifiers[] = 'targetScore';
		}
		if ($this->hasGroundTruth()) {
			$allowedIdentifiers[] = 'groundTruthScore';
		}

		$nonAllowedIdentifiers = CampaignUtils::getNonAllowedIdentifiersInFormula($formula, $allowedIdentifiers);
		if (!empty($nonAllowedIdentifiers)) {
			throw 'There are non-allowed identifiers in the reward formula.';
		}

		// Execute the formula
		ob_start();
		$evalResult = eval("\$formulaResult = $formula;");

		if ('' !== ob_get_clean()) {
			// error on eval
			throw 'The reward formula is not evaluable.';
		}

		if (!is_numeric($formulaResult)) {
			throw 'The result of the reward formula is not numeric.';
		}

		return $formulaResult;
	}

}

?>
