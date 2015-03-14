<?php

namespace CrowdsourcingCampaign\Validator;

include_once('CampaignUtils.php');

use \CrowdsourcingCampaign\Utils\CampaignUtils;

class ValidationResult {

	private $valid;
	private $errorList;

	public function __construct($valid, $errorList = NULL) {
		$this->valid = $valid;
		$this->errorList = $errorList;
	}

	public function isValid() {
		return $this->valid;
	}

	public function getErrorList() {
		return $this->errorList;
	}

}

class CampaignValidator {

	private $doc;
	private $groundTruth;
	private $target;
	private $reward;
	private $namespaces;
	private $m;

	public function __construct($filename, $m) {
		$this->doc = simplexml_load_file($filename);
		$this->m = $m;
		$this->namespaces = $this->doc->getNameSpaces(true);

		$docChildren = $this->doc->children($this->namespaces['cc']);

		$this->groundTruth = $docChildren->{'ground-truth'};
		$this->target = $docChildren->target;
		$this->reward = $docChildren->reward;
	}

	public function checkValidity() {
		$validity = array(
			$this->checkEndDateAfterStartDate(),
			$this->checkPresenceOfCorrectAnswers(),
			$this->checkPresenceOfDiscriminator(),
			$this->checkAllDateTimeFormatsMatch(),
			$this->checkMinLessThanMax(),
			$this->checkMeanAndScoreMinForLinearDistributions(),
			$this->checkWeekdaysOccurrences(),
			$this->checkMaxDistanceBigEnough(),
			$this->checkScoreMaxGreaterThanScoreMin(),
			$this->checkEducationOccurrences(),
			$this->checkLanguageAndNativeOccurrences(),
			$this->checkReferenceAndScoreReferenceOccurrence(),
			$this->checkRewardReferenceMeanAndMaxExclusivity(),
			$this->checkRewardFormula()
		);

		$errorList = array_reduce($validity, array("\CrowdsourcingCampaign\Utils\CampaignUtils", "stripTrues"), array());

		if (empty($errorList)) {
			return new ValidationResult(true);
		} else {
			return new ValidationResult(false, $errorList);
		}
	}

	// Constraint #1
	private function checkEndDateAfterStartDate() {
		$startDateString = $this->doc->attributes()->{'start-date'};
		$endDateString = $this->doc->attributes()->{'end-date'};

		if (!$startDateString || !$endDateString) {
			// one of the two is not given, then it's deffo ok
			return true;
		} else {
			// both given, check validity
			$startDate = \DateTime::createFromFormat('Y-m-d', $startDateString);
			$endDate = \DateTime::createFromFormat('Y-m-d', $endDateString);

			if ($endDate >= $startDate) {
				return true;
			} else {
				return 'Start date of campaign is after end date.';
			}
		}
	}

	// Constraint #2
	private function checkPresenceOfCorrectAnswers() {
		if (!$this->groundTruth) {
			return true;
		}

		foreach ($this->groundTruth->children($this->namespaces['cc']) as $question) {
			// for every question, collect # of correct answers

			$numCorrectAnswers = 0;
			foreach ($question->children($this->namespaces['cc']) as $answer) {
				if (strtolower($answer->getName()) == 'answer' && strtolower($answer->attributes()->correct) == 'true') {
					$numCorrectAnswers++;
				}
			}

			if ($numCorrectAnswers < 1) {
				return 'Question "' . $question->text . '" has no correct answer.';
			}
		}

		return true;
	}

	// Constraint #3
	private function checkPresenceOfDiscriminator() {
		if (!$this->target) {
			return true;
		}

		if (count($this->target->children($this->namespaces['cc'])) >= 1) {
			return true;
		} else {
			return 'There must be at least one discriminator defined inside the target.';
		}
	}

	// Constraint #4
	private function checkAllDateTimeFormatsMatch() {
		if (!$this->target) {
			return true;
		}

		$timeElement = $this->target->time;
		if ($timeElement) {
			$minElement = $timeElement->min;
			$maxElement = $timeElement->max;
			$meanElement = $timeElement->mean;

			$minHasDate = CampaignUtils::doesISOStringContainDate($minElement);
			$maxHasDate = CampaignUtils::doesISOStringContainDate($maxElement);

			if ($meanElement) {
				// mean element also there, check all three
				$meanHasDate = CampaignUtils::doesISOStringContainDate($meanElement);

				if (($minHasDate && $maxHasDate && $meanHasDate) ||
						(!$minHasDate && !$maxHasDate && !$meanHasDate)) {
					return true;
				} else {
					return 'min, max and mean element in time discriminator do not ' .
						'specify their date/time values in the same format.';
				}
			} else {
				// no mean element, only check min and max
				if ($minHasDate && $maxHasDate || !$minHasDate && $maxHasDate) {
					return true;
				} else {
					return 'min and,  max element in time discriminator do not ' .
						'specify their date/time values in the same format.';
				}
			}
 		}
	}

	// Constraint #5
	private function checkMinLessThanMax() {
		if (!$this->target) {
			return true;
		}

		// Check age discriminator
		$ageElement = $this->target->age;
		if ($ageElement) {
			$min = intval($ageElement->min);
			$max = intval($ageElement->max);

			if ($min >= $max) {
				return 'min value in age discriminator is equal to or greater than max value.';
			}

			if ($ageElement->mean) {
				$mean = intval($ageElement->mean);

				if ($min > $mean || $mean > $max) {
					return 'mean value in age discriminator is less than min value or greater than max value.';
				}
			}
		}

		// Check time discriminator
		$timeElement = $this->target->time;
		if ($timeElement) {
			$min = CampaignUtils::getDateTimeFromISOString($timeElement->min);
			$max = CampaignUtils::getDateTimeFromISOString($timeElement->max);

			if ($min >= $max) {
				return 'min value in time discriminator is equal to or greater than max value.';
			}

			if ($timeElement->mean) {
				$mean = CampaignUtils::getDateTimeFromISOString($timeElement->mean);

				if ($min > $mean || $mean > $max) {
					return 'mean value in time discriminator is less than min value or greater than max value.';
				}
			}
		}

		$rewardPointsElement = $this->target->{'reward-points'};
		if ($rewardPointsElement) {
			$min = floatval($rewardPointsElement->min);

			if ($rewardPointsElement->max) {
				// max element present
				$max = floatval($rewardPointsElement->max);

				if ($min >= $max) {
					return 'min value in reward-points discriminator is equal to or greater than max value.';
				}

				if ($rewardPointsElement->mean) {
					// max and mean elements present
					$mean = floatval($rewardPointsElement->mean);

					if ($min > $mean || $mean > $max) {
						return 'mean value in reward-points discriminator is less than min value or greater than max value.';
					}
				}
			} else {
				if ($rewardPointsElement->mean) {
					// no max element, but mean element present
					$mean = floatval($rewardPointsElement->mean);

					if ($min > $mean) {
						return 'mean value in reward-points discriminator is less than min value.';
					}
				}
			}

			if ($rewardPointsElement->reference) {
				$reference = floatval($rewardPointsElement->reference);

				if ($min >= $reference) {
					return 'min value in reward-points discriminator is equal to or greater than reference value.';
				}
			}
		}

		return true;
	}

	// Constraint #6
	private function checkMeanAndScoreMinForLinearDistributions() {
		if (!$this->target) {
			return true;
		}

		$discriminatorElements = array(
			$this->target->age,
			$this->target->time,
			$this->target->{'reward-points'}
		);
		foreach ($discriminatorElements as $discriminatorElement) {
			if ($discriminatorElement) {
				$scoreMinAttr = $discriminatorElement->attributes()->{'score-min'};
				$meanElement = $discriminatorElement->mean;

				if (strtolower($discriminatorElement->attributes()->{'score-dist'}) == 'linear') {
					// linear distribution, so score-min needs to be set
					if (!$scoreMinAttr) {
						return 'Discriminator ' . $discriminatorElement->getName() .
							' does not have a score-min attribute despite the score ' .
							'distribution being linear.';
					}

					// the following constraint is only true for age and time
					if (strtolower($discriminatorElement->getName()) !== 'reward-points') {
						if (!$meanElement) {
							return 'Discriminator ' . $discriminatorElement->getName() .
							' does not have a mean element despite the score distribution ' .
							'being linear.';
						}
					}
				} else {
					// binary distribution, so mean and score-min are not allowed
					if ($scoreMinAttr) {
						return 'Discriminator ' . $discriminatorElement->getName() .
							' has a score-min attribute despite the score distribution ' .
							'being binary.';
					}

					if ($meanElement) {
						return 'Discriminator ' . $discriminatorElement->getName() .
							' has a mean element despite the score distribution ' .
							'being binary.';
					}
				}
			}
		}

		return true;
	}

	// Constraint #7
	private function checkWeekdaysOccurrences() {
		if (!$this->target) {
			return true;
		}

		$timeElement = $this->target->time;
		if ($timeElement) {
			$excludeWeekdays = $timeElement->{'exclude-weekdays'};

			if ($excludeWeekdays) {
				$encounteredWeekdays = array();

				foreach ($excludeWeekdays->children($this->namespaces['cc']) as $weekday) {
					$weekdayStr = (string) $weekday;

					if (in_array($weekdayStr, $encounteredWeekdays)) {
						// weekday already occurred
						return 'Weekday "' . $weekdayStr . '" occurred multiple times inside exclude-weekdays.';
					}

					$encounteredWeekdays[] = $weekdayStr;

				}
			}
		}

		return true;
	}

	// Constraint #8
	private function checkMaxDistanceBigEnough() {
		if (!$this->target) {
			return true;
		}

		$locationElement = $this->target->location;
		if ($locationElement) {
			$maxDistance = floatval($locationElement->{'max-distance'});

			if ($maxDistance >= 2 * $this->m) {
				return true;
			} else {
				return 'max-distance in location discriminator is less than two times m (m=' . $this->m . ')';
			}
		}

		return true;
	}

	// Constraint #9
	private function checkScoreMaxGreaterThanScoreMin() {
		if (!$this->target) {
			return true;
		}

		foreach ($this->target->children($this->namespaces['cc']) as $discriminator) {
			$scoreMinAttr = $discriminator->attributes()->{'score-min'};
			$scoreMaxAttr = $discriminator->attributes()->{'score-max'};

			if ($scoreMinAttr && $scoreMaxAttr) {
				$scoreMin = floatval($scoreMinAttr);
				$scoreMax = floatval($scoreMaxAttr);

				if ($scoreMinAttr >= $scoreMax) {
					return 'score-min attribute in ' . $discriminator->getName() .
						' discriminator is equal to or greater than score-max attribute.';
				}
			}
		}

		return true;
	}

	// Constraint #10
	private function checkEducationOccurrences() {
		if (!$this->target) {
			return true;
		}

		$educationElement = $this->target->education;
		if ($educationElement) {
			$encounteredQualifications = array();

			foreach ($educationElement->children($this->namespaces['cc']) as $qualification) {
				$qualName = $qualification->attributes()->name;

				if (in_array($qualName, $encounteredQualifications)) {
					// this qualification occurred previously
					return 'Qualification ' . $qualName . ' occurred multiple times ' .
						'inside education discriminator.';
				}

				$encounteredQualifications[] = (string) $qualName;
			}
		}

		return true;
	}

	// Constraint #11
	private function checkLanguageAndNativeOccurrences() {
		if (!$this->target) {
			return true;
		}

		$languagesElement = $this->target->languages;
		if ($languagesElement) {
			$encounteredLanguages = array();

			foreach ($languagesElement->children($this->namespaces['cc']) as $language) {
				$code = (string) $language->attributes()->code;
				$native = strtolower($language->attributes()->native) == 'true';
				$needle = array($code, $native);
				
				if (in_array($needle, $encounteredLanguages)) {
					// language combination occurred previously
					$nativeStr = $native ? 'native' : 'non-native';
					return 'Language "' . $code . '" as a ' . $nativeStr . ' language ' .
						'occurred multiple times inside languages discriminator.';
				}

				$encounteredLanguages[] = $needle;
			}
		}

		return true;
	}

	// Constraint #12
	private function checkReferenceAndScoreReferenceOccurrence() {
		if (!$this->target) {
			return true;
		}

		$rewardPointsElement = $this->target->{'reward-points'};
		if ($rewardPointsElement &&
				strtolower($rewardPointsElement->attributes()->{'score-dist'}) !== 'linear') {
			// binary score distribution
			// neither reference nor score-reference may appear
			$scoreRefAttr = $rewardPointsElement->attributes()->{'score-reference'};
			$referenceElement = $rewardPointsElement->reference;

			if ($scoreRefAttr) {
				return 'score-reference attribute in reward-points discriminator is given ' .
					'despite the score distribution being binary.';
			} else if ($referenceElement) {
				return 'reference element in reward-points discriminator is given ' .
					'despite the score distribution being binary.';
			}
		}

		return true;
	}

	// Constraint #13
	private function checkRewardReferenceMeanAndMaxExclusivity() {
		if (!$this->target) {
			return true;
		}

		$rewardPointsElement = $this->target->{'reward-points'};
		if ($rewardPointsElement &&
				strtolower($rewardPointsElement->attributes()->{'score-dist'}) === 'linear') {
			$referenceElement = $rewardPointsElement->reference;
			$scoreRefAttr = $rewardPointsElement->attributes()->{'score-reference'};
			$meanElement = $rewardPointsElement->mean;
			$maxElement = $rewardPointsElement->max;
			$scoreMaxAttr = $rewardPointsElement->attributes()->{'score-max'};

			if ($referenceElement) {
				// score-reference must appear
				if (!$scoreRefAttr) {
					return 'score-reference attribute in reward-points discriminator does not ' .
						'appear although there is a reference element present.';
				}

				// mean, max and score-max must not appear
				if ($meanElement) {
					return 'mean element appears in reward-points discriminator while ' .
						'reference element is present at the same time.';
				} else if ($maxElement) {
					return 'max element appears in reward-points discriminator while ' .
						'reference element is present at the same time.';
				} else if ($scoreMaxAttr) {
					return 'score-max attribute appears in reward-points discriminator while ' .
						'reference element is present at the same time.';
				}
			} else {
				// no reference element given

				// score-reference attribute must not appear
				if ($scoreRefAttr) {
					return 'score-reference attribute given in reward-points discriminator ' .
						'although there is no reference element.';
				}

				// no reference or score-reference
				// mean, max and score-max must all appear
				if (!$meanElement) {
					return 'mean element in reward-points discriminator is required if reference ' .
						'element is not given.';
				} else if (!$maxElement) {
					return 'max element in reward-points discriminator is required if reference ' .
						'element is not given.';
				} else if (!$scoreMaxAttr) {
					return 'score-max attribute in reward-points discriminator is required if reference ' .
						'element is not given.';
				}
			}
		}

		return true;
	}

	// Constraint #14
	private function checkRewardFormula() {
		if (!$this->reward) {
			return true;
		}
		
		$formula = (string) $this->reward->formula;
		
		// Quotation characters inside the formula are invalid
		if (strpos($formula, '\'') !== false || strpos($formula, '"') !== false) {
			return 'A quotation mark appears inside the reward formula.';
		}

		// Find all identifiers inside the formula
		preg_match_all("/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/", $formula, $hits);
		$identifiersInFormula = $hits[0];

		foreach ($identifiersInFormula as $identifier) {
			if ($identifier !== 'targetScore' && $identifier !== 'groundTruthScore') {
				// illegal string occurred
				return 'An identifier or keyword other than "targetScore" or "groundTruthScore" ' .
					'appeared inside the reward formula.';
			}

			if ($identifier === 'targetScore' && !$this->target) {
				// targetScore was used without specifying a target element
				return 'The identifier "targetScore" appeared inside the reward formula ' .
					'although there is no target element.';
			}

			if ($identifier === 'groundTruthScore' && !$this->groundTruth) {
				// groundTruthScore was used without sepcifying a ground truth element
				return 'The identifier "groundTruthScore" appeared inside the reward formula ' .
					'although there is no ground-truth element.';
			}
		}

		// Now it should be safe to make the dangerous eval() call
		$targetScore = 1;
		$groundTruthScore = 1;

		ob_start();
		$evalResult = eval("\$formulaResult = $formula;");

		if ('' !== ob_get_clean()) {
			// error on eval
			return 'The reward formula is not evaluable.';
		}

		if (is_numeric($formulaResult)) {
			return true;
		} else {
			return 'The result of the reward formula is not numeric.';
		}
	}

}

?>
