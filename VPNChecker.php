<?php

	require __DIR__.'/twilio-php/Twilio/autoload.php';

	class VPNChecker {

		function __construct(){
			set_error_handler(array($this,'errorHandler'));
			set_exception_handler(array($this,'exceptionHandler'));
			$this->logfile = "VPNChecker.log";
			$this->sleepInterval = 1000;
			$this->twilioConfig = parse_ini_file("twilio.ini");
			$this->tw = new Twilio\Rest\Client($this->twilioConfig['sid'], $this->twilioConfig['token']);
		}

		private function getConnectionDetails(){
			$ch = curl_init("https://ifconfig.co/json");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$json = curl_exec($ch);

			if($json === false){
				throw new Exception("Unable to curl JSON from ifconfig.co");
			}

			$info = json_decode($json, true);

			if($info === false || $info === null){
				throw new Exception("Unable to parse JSON from ifconfig.co");
			}

			return $info;
		}

		function checkPIA(){
			libxml_use_internal_errors(true);

			$dom = new DOMDocument();
			$dom->loadHTMLFile("https://www.privateinternetaccess.com/pages/whats-my-ip/");

			$xpath = new DOMXPath($dom);

			$elements = $xpath->query("//*[contains(@class, 'topbar__list topbar__list--marker')]");

			libxml_use_internal_errors(false);

			if($elements === false || $elements->length === 0){
				throw new Exception("Unable to parse PIA HTML");
			}

			$ip = null;
			$isp = null;
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

			if($ip === null || $isp === null){
				throw new Exception("Unable to parse PIA HTML");
			}

			return array('protected' => $protected, 'ip' => $ip, 'isp' => $isp);
		}

		function exceptionHandler($exception){
			$msg = 
			"Exception: {$exception->getMessage()}\r\nLine: {$exception->getLine()}\r\nTrace: {$exception->getTraceAsString()}";
			$this->writeToLog($msg);
			$this->sendSMS($msg);
			exit(1);
		}

		function errorHandler($errno, $errstr, $errfile, $errline){
			$msg = "Error: {$errno}\r\nMessage: {$errstr}\r\nLine: {$errline}";
			$this->writeToLog($msg);
			$this->sendSMS($msg);
			exit(1);
		}

		private function writeToLog($text){
			echo $text."\r\n";
			file_put_contents($this->logfile, date('Y-m-d H:i:s').": ".$text."\r\n", FILE_APPEND);
		}

		private function sendEmail($text){

		}

		private function sendSMS($text){
			$this->tw->messages->create(
				$this->twilioConfig['myNumber'], array(
					'from' => $this->twilioConfig['number'],
					'body' => "VPNChecker\r\n".$text
				));
		}

		function checkVPN(){
			$this->writeToLog("VPNChecker Started");
			
			$htmlCheck = $this->checkPIA();
			$curlCheck = $this->getConnectionDetails();

			if($htmlCheck['protected']){
				$protected = "True";
			} else {
				$protected = "False";
			}

			$connectionDetails = "\r\nPIA:\r\n";
			$connectionDetails .= "\tProtected: {$protected}\r\n";
			$connectionDetails .= "\tIP: {$htmlCheck['ip']}\r\n";
			$connectionDetails .= "\tISP: {$htmlCheck['isp']}\r\n";
			$connectionDetails .= "\r\nIfconfig.co:\r\n";
			$connectionDetails .= "\tIP: {$curlCheck['ip']}\r\n";
			$connectionDetails .= "\tHostname: {$curlCheck['hostname']}\r\n";
			$connectionDetails .= "\tCity: {$curlCheck['city']}\r\n";
			$connectionDetails .= "\tCountry: {$curlCheck['country']}\r\n";

			$this->writeToLog($connectionDetails);
			//$this->sendSMS($connectionDetails);
		}
	}
$vpn = new VPNChecker();
$vpn->checkVPN();
//$vpn->twilio_test();