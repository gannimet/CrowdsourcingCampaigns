<?php

namespace CrowdsourcingCampaign\Test;

include_once('CampaignValidator.php');

class CampaignValidationTestEX1 extends \PHPUnit_Framework_TestCase {

	public function testValidity() {
		date_default_timezone_set('Europe/Berlin');
		$validator = new \CrowdsourcingCampaign\CampaignValidator($_SERVER['PWD'] . '/tests/ex1.xml', 50);
		$result = $validator->checkValidity();

		$this->assertTrue($result->isValid());
	}

}

?>
