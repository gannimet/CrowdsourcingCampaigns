<?php

namespace CrowdsourcingCampaign\Test;

include_once('Campaign.php');

use \DateTime;
use \CrowdsourcingCampaign\Location;

class CampaignExecutionTestEX1 extends \PHPUnit_Framework_TestCase {

	private $campaign;

	public function setUp() {
		date_default_timezone_set('Europe/Berlin');
		$this->campaign = new \CrowdsourcingCampaign\Campaign($_SERVER['PWD'] . '/tests/ex1.xml');
	}

	public function testWholeGroundTruthScoring() {
		$this->assertFalse($this->campaign->hasGroundTruth());
		$this->assertNull($this->campaign->getGroundTruthScore(NULL));
	}

	public function testAgeScoring() {
		$this->assertEquals(2, $this->campaign->getAgeScore(20));
		$this->assertEquals(2, $this->campaign->getAgeScore(22));
		$this->assertEquals(2, $this->campaign->getAgeScore(24.726351));
		$this->assertEquals(2, $this->campaign->getAgeScore(28));
		$this->assertEquals(2, $this->campaign->getAgeScore(30));
		$this->assertEquals(0, $this->campaign->getAgeScore(19));
		$this->assertEquals(0, $this->campaign->getAgeScore(31));
	}

	public function testTimeScoring() {
		$this->assertEquals(0, $this->campaign->getTimeScore(new DateTime('2015-03-25T17:00:00Z')));
		$this->assertEquals(3, $this->campaign->getTimeScore(new DateTime('2015-03-20T17:00:00Z')));
		$this->assertEquals(1, $this->campaign->getTimeScore(new DateTime('2015-03-20T13:00:00Z')));
		$this->assertEquals(1, $this->campaign->getTimeScore(new DateTime('2015-03-20T21:00:00Z')));
		$this->assertEquals(0, $this->campaign->getTimeScore(new DateTime('2015-03-20T21:00:01Z')));
	}

	public function testLocationScoring() {
		$this->assertEquals(4, $this->campaign->getLocationScore(new Location(43.014252, -3.209216)));
		$this->assertEquals(4, $this->campaign->getLocationScore(new Location(41.314252, -3.309216)));
		$this->assertEquals(0, $this->campaign->getLocationScore(new Location(62.314252, -15.309216)));
	}

	public function testEducationScoring() {
		$this->assertEquals(0, $this->campaign->getEducationScore('none'));
		$this->assertEquals(0, $this->campaign->getEducationScore('abitur'));
		$this->assertEquals(15, $this->campaign->getEducationScore('promotion'));
		$this->assertEquals(15, $this->campaign->getEducationScore('habilitation'));
	}

	public function testLanguagesScoring() {
		$languages1 = array('es' => true, 'pt' => false);
		$languages2 = array('es' => true);
		$languages3 = array('es' => false);
		$languages4 = array('en' => true, 'es' => false);
		$languages5 = array('de' => true, 'pt' => false);

		$this->assertEquals(10, $this->campaign->getLanguagesScore($languages1));
		$this->assertEquals(10, $this->campaign->getLanguagesScore($languages2));
		$this->assertEquals(5, $this->campaign->getLanguagesScore($languages3));
		$this->assertEquals(5, $this->campaign->getLanguagesScore($languages4));
		$this->assertEquals(0, $this->campaign->getLanguagesScore($languages5));
	}

	public function testRewardPointsScore() {
		$this->assertEquals(0, $this->campaign->getRewardPointsScore(0));
		$this->assertEquals(0, $this->campaign->getRewardPointsScore(9));
		$this->assertEquals(2, $this->campaign->getRewardPointsScore(10));
		$this->assertEquals(2.5, $this->campaign->getRewardPointsScore(15));
		$this->assertEquals(3, $this->campaign->getRewardPointsScore(20));
		$this->assertEquals(5, $this->campaign->getRewardPointsScore(40));
		$this->assertEquals(11, $this->campaign->getRewardPointsScore(100));
		$this->assertEquals(21, $this->campaign->getRewardPointsScore(200));
	}

	public function testTargetScore() {
		$perfectAge = 25;
		$perfectTime = new DateTime('2015-03-20T17:00:00Z');
		$perfectLocation = new Location(43.014252, -3.209216);
		$perfectEducation = 'habilitation';
		$perfectLanguages = array('es' => true);
		$perfectRewardPoints = 300;

		$this->assertEquals(65, $this->campaign->getTargetScore(
			$perfectAge, $perfectTime, $perfectLocation, $perfectEducation, $perfectLanguages, $perfectRewardPoints));

		$imperfectLanguages = array('es' => false);

		$this->assertEquals(0, $this->campaign->getTargetScore(
			NULL, NULL, NULL, NULL, $imperfectLanguages, 12));
		$this->assertEquals(11, $this->campaign->getTargetScore(
			NULL, NULL, NULL, NULL, $imperfectLanguages, 50));
	}

	public function testRewardPointsCalculation() {
		$this->assertEquals(30, $this->campaign->getRewardPoints(NULL, 20));
		$this->assertEquals(0, $this->campaign->getRewardPoints(NULL, 0));
		$this->assertEquals(15, $this->campaign->getRewardPoints(5, 10));
	}

}

?>
