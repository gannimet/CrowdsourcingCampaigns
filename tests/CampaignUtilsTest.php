<?php

namespace CrowdsourcingCampaign\Test;

include_once('CampaignUtils.php');

use CrowdsourcingCampaign\CampaignUtils;
use \DateTime;

class CampaignUtilsTest extends \PHPUnit_Framework_TestCase {

	public function setUp() {
		date_default_timezone_set('Europe/Berlin');
	}

	public function testTimeRepresentation() {
		$this->assertEquals(40225, CampaignUtils::getNumericTimeRepresentation(new DateTime('2012-05-03 11:10:25')));
		$this->assertEquals(40285, CampaignUtils::getNumericTimeRepresentation(new DateTime('2012-05-03 11:11:25')));
	}

	public function testWeekdayName() {
		$this->assertEquals('Saturday', CampaignUtils::getWeekdayName(new DateTime('2015-03-14')));
	}

}

?>
