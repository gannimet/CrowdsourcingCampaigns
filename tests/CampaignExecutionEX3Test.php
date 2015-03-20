<?php

namespace CrowdsourcingCampaign\Test;

include_once('Campaign.php');

use \DateTime;
use \CrowdsourcingCampaign\Location;

class CampaignExecutionTestEX3 extends \PHPUnit_Framework_TestCase {

	private $campaign;

	public function setUp() {
		date_default_timezone_set('Europe/Berlin');
		$this->campaign = new \CrowdsourcingCampaign\Campaign($_SERVER['PWD'] . '/tests/ex3.xml');
	}

	public function testSingleGroundTruthScoring() {
		$correctAnswerPattern = array(1, 2, 4);
		$wrongAnswerPattern1 = array(1, 2);
		$wrongAnswerPattern2 = array(1, 2, 3, 4);
		$wrongAnswerPattern3 = array(0, 3, 4, 5);
		$wrongAnswerPattern4 = array(4, 5);

		$this->assertEquals(9, $this->campaign->getGroundTruthScoreForQuestion(1, $correctAnswerPattern));
		$this->assertEquals(6, $this->campaign->getGroundTruthScoreForQuestion(1, $wrongAnswerPattern1));
		$this->assertEquals(6, $this->campaign->getGroundTruthScoreForQuestion(1, $wrongAnswerPattern2));
		$this->assertEquals(0, $this->campaign->getGroundTruthScoreForQuestion(1, $wrongAnswerPattern3));
		$this->assertEquals(3, $this->campaign->getGroundTruthScoreForQuestion(1, $wrongAnswerPattern4));
	}

	public function testWholeGroundTruthScoring() {
		$perfectPatterns = array(
			array(1),
			array(1, 2, 4)
		);

		$wrongPatterns = array(
			array(0, 1),
			array(1, 3, 4)
		);

		$this->assertEquals(13, $this->campaign->getGroundTruthScore($perfectPatterns));
		$this->assertEquals(8, $this->campaign->getGroundTruthScore($wrongPatterns));
	}

	public function testLocationDistance() {
		$loc1 = new Location(43.314252, -3.009216);
		$loc2 = new Location(43.014252, -3.209216);

		$this->assertEquals(
			37.13398,
			$loc1->distanceTo($loc2),
			'Distance calculation for bilbao wrong',
			0.0001
		);

		$this->assertEquals(
			0,
			$loc1->distanceTo($loc1),
			'Distance between the same locations should be zero'
		);
	}

	public function testLocationObfuscation() {
		$lat_real = 43.314252;
		$lon_real = -3.009216;
		$loc_real = new Location($lat_real, $lon_real);
		$iterations = 1000;
		$maxRadius = 50;

		$latLessCount = 0;
		$lonLessCount = 0;

		for ($i = 0; $i < $iterations; $i++) { 
			$loc_obf = $loc_real->getObfuscatedCopy($maxRadius);

			$distance = $loc_real->distanceTo($loc_obf);

			$this->assertLessThanOrEqual($maxRadius, $distance);
			$this->assertNotEquals($loc_real->getLatitude(), $loc_obf->getLatitude());
			$this->assertNotEquals($loc_real->getLongitude(), $loc_obf->getLongitude());

			$latLessCount += $lat_real - $loc_obf->getLatitude() > 0 ? 1 : 0;
			$lonLessCount += $lon_real - $loc_obf->getLongitude() > 0 ? 1 : 0;
		}
		
		$this->assertGreaterThanOrEqual($iterations / 3, $latLessCount, 'Latitudes too often greater');
		$this->assertLessThanOrEqual($iterations * 2 / 3, $latLessCount, 'Latitudes too often less');
		$this->assertGreaterThanOrEqual($iterations / 3, $lonLessCount, 'Longitudes too often greater');
		$this->assertLessThanOrEqual($iterations * 2 / 3, $lonLessCount, 'Longitudes too often less');
	}

	public function testAgeScoring() {
		$this->assertEquals(2, $this->campaign->getAgeScore(20));
		$this->assertEquals(2.6, $this->campaign->getAgeScore(21));
		$this->assertEquals(3.2, $this->campaign->getAgeScore(22));
		$this->assertEquals(4.4, $this->campaign->getAgeScore(24));
		$this->assertEquals(5, $this->campaign->getAgeScore(25));
		$this->assertEquals(4.4, $this->campaign->getAgeScore(26));
		$this->assertEquals(3.8, $this->campaign->getAgeScore(27));
		$this->assertEquals(2, $this->campaign->getAgeScore(30));
		$this->assertEquals(0, $this->campaign->getAgeScore(19));
		$this->assertEquals(0, $this->campaign->getAgeScore(31));
	}

	public function testTimeScoring() {
		$this->assertEquals(10, $this->campaign->getTimeScore(new DateTime('2012-03-23 17:00:00')));
		$this->assertEquals(1, $this->campaign->getTimeScore(new DateTime('2012-03-23 13:00:00')));
		$this->assertEquals(1, $this->campaign->getTimeScore(new DateTime('2012-03-23 21:00:00')));
		$this->assertEquals(0, $this->campaign->getTimeScore(new DateTime('2012-03-23 12:59:59')));
		$this->assertEquals(0, $this->campaign->getTimeScore(new DateTime('2012-03-23 21:00:01')));
		$this->assertEquals(0, $this->campaign->getTimeScore(new DateTime('2012-03-10 14:21:30')));
	}

	public function testLocationScoring() {
		$this->assertEquals(10, $this->campaign->getLocationScore(new Location(43.014252, -3.209216)));
		$this->assertLessThan(10, $this->campaign->getLocationScore(new Location(41.314252, -3.309216)));
		$this->assertGreaterThan(0, $this->campaign->getLocationScore(new Location(41.314252, -3.309216)));
		$this->assertEquals(0, $this->campaign->getLocationScore(new Location(62.314252, -15.309216)));
	}

	public function testEducationScoring() {
		$this->assertEquals(5, $this->campaign->getEducationScore('bachelor'));
		$this->assertEquals(10, $this->campaign->getEducationScore('master'));
		$this->assertEquals(0, $this->campaign->getEducationScore('none'));
	}

	public function testLanguagesScoring() {
		$languages1 = array('es' => true, 'pt' => false);
		$languages2 = array('en' => true);
		$languages3 = array('en' => false);
		$languages4 = array('en' => true, 'es' => false);
		$languages5 = array('de' => true, 'pt' => false);

		$this->assertEquals(30, $this->campaign->getLanguagesScore($languages1));
		$this->assertEquals(5, $this->campaign->getLanguagesScore($languages2));
		$this->assertEquals(0, $this->campaign->getLanguagesScore($languages3));
		$this->assertEquals(15, $this->campaign->getLanguagesScore($languages4));
		$this->assertEquals(20, $this->campaign->getLanguagesScore($languages5));
	}

	public function testRewardPointsScore() {
		$this->assertEquals(73, $this->campaign->getRewardPointsScore(27));
		$this->assertEquals(28, $this->campaign->getRewardPointsScore(86));
		$this->assertEquals(10, $this->campaign->getRewardPointsScore(20));
		$this->assertEquals(0, $this->campaign->getRewardPointsScore(19));
		$this->assertEquals(10, $this->campaign->getRewardPointsScore(100));
		$this->assertEquals(0, $this->campaign->getRewardPointsScore(101));
	}

	public function testRewardPointsCalculation() {
		$this->assertEquals(60, $this->campaign->getRewardPoints(10, 20));
		$this->assertEquals(0, $this->campaign->getRewardPoints(0, 0));
		$this->assertEquals(30, $this->campaign->getRewardPoints(5, 10));
	}

}

?>
