<?php

namespace CrowdsourcingCampaign\Test;

include_once('CampaignValidator.php');

class CampaignValidationTestEX3 extends \PHPUnit_Framework_TestCase {

	public function testValidity() {
		date_default_timezone_set('Europe/Berlin');
		$validator = new \CrowdsourcingCampaign\Validator\CampaignValidator($_SERVER['PWD'] . '/tests/ex3.xml', 50);
		$result = $validator->checkValidity();

		$this->assertTrue($result->isValid());
	}

}

?>
