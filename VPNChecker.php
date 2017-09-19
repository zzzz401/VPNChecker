<?php

	require __DIR__.'/twilio-php/Twilio/autoload.php';

	class VPNChecker {

		function __construct(){
			set_error_handler("exceptionHandler");
			set_exception_handler("exceptionHandler");
			$this->twillioConfig = parse_ini_file("twilio.ini");
		}

		private function getConnectionDetails(){
			$ch = curl_init("https://ifconfig.co/json");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$info = json_decode(curl_exec($ch), true);
			return $info;
		}

		private function checkPIA(){
			$dom = new DOMDocument();
			@$dom->loadHTMLFile("https://www.privateinternetaccess.com/pages/whats-my-ip/");

			$xpath = new DOMXPath($dom);

			$elements = $xpath->query("//*[contains(@class, 'topbar__list topbar__list--marker')]");

			$ip = "";
			$isp = "";
			$protected = false;
			foreach($elements as $element){
				foreach($element->childNodes as $node){
					$value = trim($node->nodeValue);
					
					if($value === ""){
						continue;
					}

					if($value === "You are protected by PIA"){
						$protected = true;
					}

					if(stripos($value, "Your IP Address:") !== false){
						$ip = substr($value, 17);
					}

					if(stripos($value, "Your ISP:") !== false){
						$isp = substr($value, 11);
					}
				}
			}
			return array('protected' => $protected, 'ip' => $ip, 'isp' => $isp);
		}

		private function exceptionHandler($exception){
			$this->sendSMS("VPNChecker Exception: {$exception->getMessage()}");
		}

		private function errorHandler($errno, $errstr, $errfile, $errline){
			$this->sendSMS("VPNChecker Error: {$errno} Message: {$errstr} File: {$errfile} Line: {$errline}");
		}


		private function sendSMS($text){
			$tw = new Twilio\Rest\Client($this->twillioConfig['sid'], $this->twillioConfig['token']);
			$tw->messages->create(
				$this->twillioConfig['myNumber'], array(
					'from' => $this->twillioConfig['number'],
					'body' => $text
				));
		}
	}
$vpn = new VPNChecker();
$vpn->twillio_test();