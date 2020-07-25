<?php

@ini_set("display_errors", "1"); error_reporting(E_ALL);
//@ini_set("display_errors", "0"); error_reporting(0);

require_once(__DIR__ . DIRECTORY_SEPARATOR . "InternetBankOpenConfig.php");

class InternetBankOpenAPI extends InternetBankOpenAPIConfig {
  private $Debug = false;
  private $curl;

	private $APIURL = "https://internetbankmb.open.ru/webapi-2.1/";
	
	public $Error = false;
	public $Message = "";

	private $ShortInfo = null;

	function __construct($Debug = false) {
		date_default_timezone_set($this->TimeZone);
		$this->Debug = $Debug;
		$this->log_file = str_replace("%date%", date("Y.m.d"), $this->log_file);
		$this->Log("---------------------------------------------------------------------------------------------------------------");
		$this->Log("Конструктор класса InternetBankOpenAPI");

		$this->InitCurl();
		$this->HTTPGetShortInfo();
  }

	function __destruct() {
		$this->Log("Деструктор класса InternetBankOpenAPI");
		if (isset($this->curl)) {
	  	curl_close($this->curl);
		}
	}

	private function Log($Title, $Value = false) {	
		if (is_array($Title))
			$Title = print_r($Title, true) . "\n";

		if ($Value !== false) {
			if (is_array($Value)) 
				$Value = "\n" . print_r($Value, true);
			else
				$Value = "\t" . $Value;
		}
		$Message = date("Y.m.d H:i:s ") . $Title . $Value . "\n";
		if ($this->Debug)
			echo $Message;
		file_put_contents($this->log_file, $Message, FILE_APPEND);	
	}

	//-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	private function RawHeadersParser($rawHeaders) {
		$headers = array();
		$key = '';

		foreach (explode("\n", $rawHeaders) as $headerRow) {
			if (trim($headerRow) === '') 
				break;
			$headerArray = explode(':', $headerRow, 2);

			if (isset($headerArray[1])) {
				if (!isset($headers[$headerArray[0]])) {
					$headers[trim($headerArray[0])] = trim($headerArray[1]);
				} elseif (is_array($headers[$headerArray[0]])) {
					$headers[trim($headerArray[0])] = array_merge($headers[trim($headerArray[0])], array(trim($headerArray[1])));
				} else 
					$headers[trim($headerArray[0])] = array_merge(array($headers[trim($headerArray[0])]), array(trim($headerArray[1])));
				$key = $headerArray[0];
			} else {
					if (substr($headerArray[0], 0, 1) === "\t") {
						$headers[$key] .= "\r\n\t" . trim($headerArray[0]);
					} elseif (!$key) {
						$headers[0] = trim($headerArray[0]);
					}
			}
		}

		return $headers;
	}

	//-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	private function InitCurl() {
	  $this->curl = curl_init();

	  curl_setopt($this->curl, CURLOPT_HEADER, true);
	  curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 10);   // Количество секунд ожидания при попытке соединения
		curl_setopt($this->curl, CURLOPT_TIMEOUT, 600);   // Максимально позволенное количество секунд для выполнения cURL-функций.
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curl, CURLOPT_BINARYTRANSFER, true);
	}

	//-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	private function HTTPPost($Query, $DataArray = false) {
		$this->Error = false;
		if ($DataArray != false) {
			$DataJSON = json_encode($DataArray, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, $DataJSON);
		} else
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, "");

		$URL = $this->APIURL . $Query;
		$this->Log("HTTP Post $URL");

		$HeadersArray = array(
			"Content-Type: application/json; charset=utf-8",
			"Authorization: Bearer sso_1.0_" . $this->ClientToken,
		);
//		$this->Log("HTTPPost HeadersArray", $HeadersArray);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $HeadersArray);

    curl_setopt($this->curl, CURLOPT_URL, $URL);
		curl_setopt($this->curl, CURLOPT_POST, true);

		// $this->Log("curl_exec:", $this->curl);
		$response = curl_exec($this->curl);
		// $this->Log("Raw responce:", $response);
		$curlErrno = curl_errno($this->curl);
		if ($curlErrno) {
			$curlError = curl_error($this->curl);
			$this->Message = "Error Post : curlErrno=$curlErrno, curlError=$curlError";
			$this->Log($this->Message);
			$this->Error = true;
	    return false;
		}

		$httpHeaderSize = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
		$httpHeaders    = $this->RawHeadersParser(substr($response, 0, $httpHeaderSize));
		$httpBody       = substr($response, $httpHeaderSize);
		$responseInfo   = curl_getinfo($this->curl);


		// $this->Log("responseInfo", $responseInfo);
		// $this->Log("httpHeaders", $httpHeaders);
		// $this->Log("httpBody", $httpBody);

    $ResponseArray = json_decode($httpBody, true);		
		if (!isset($ResponseArray)) {
			$this->Error = true;
			$this->Message = "Ошибка парсинга ответа банка $Query";
			$this->Log($this->Message, $httpBody);
			return false;		
		}		
		return $ResponseArray;
	}

	//-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	private function HTTPGet($Query, $StatementFormat = "JSON") {
		$this->Error = false;

		$URL = $this->APIURL . $Query;

		$this->Log("HTTP Get $URL");

		$HeadersArray = array(
			"Content-Type: application/json; charset=utf-8",
			"Authorization: Bearer sso_1.0_" . $this->ClientToken,
		);
//		$this->Log("HTTPGet HeadersArray", $HeadersArray);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $HeadersArray);

    curl_setopt($this->curl, CURLOPT_URL, $URL);
		curl_setopt($this->curl, CURLOPT_POST, false);

		// $this->Log("curl_exec: ", $URL);
		$response = curl_exec($this->curl);
		// $this->Log("Raw responce: ", $response);
		$curlErrno = curl_errno($this->curl);
		if ($curlErrno) {
			$curlError = curl_error($this->curl);
			$this->Message = "Error Get : curlErrno=$curlErrno, curlError=$curlError";
			$this->Log($this->Message);
			$this->Error = true;
	    return false;
		}

		$httpHeaderSize = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
		$httpHeaders    = $this->RawHeadersParser(substr($response, 0, $httpHeaderSize));
		$httpBody       = substr($response, $httpHeaderSize);
		$responseInfo   = curl_getinfo($this->curl);

		// $this->Log("responseInfo", $responseInfo);
		// $this->Log("httpHeaders", $httpHeaders);
		// $this->Log("httpBody", $httpBody);

		if ($StatementFormat == "TXT")
			return $httpBody;
		else {
	    $ResponseArray = json_decode($httpBody, true);		
			if (!isset($ResponseArray)) {
				$this->Error = true;
				$this->Message = "Ошибка парсинга ответа банка $Query";
				$this->Log($this->Message, $httpBody);
				return false;		
			}		
			return $ResponseArray;
		}
	}

	//-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
  // функция получения короткой информации https://internetbankmb.open.ru/openhub/tokens/how-to#clients
	private function HTTPGetShortInfo() {
		$Query = "persons/short-info/@me";
		$Result = $this->HTTPGet($Query);
		if ($this->Error)	
			return false;
		$this->ShortInfo = $Result;
		return true;
	}

	//-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
  // функция запроса выписки https://internetbankmb.open.ru/openhub/tokens/how-to#statement
	// Формат даты ГГГГ-ММ-ДД
	private function HTTPPostStatement($AccountId, $StatementFormat = "TXT", $DateFrom, $DateTo = false) {
		$this->Log("HTTPPostStatement($AccountId, $StatementFormat, $DateFrom)");
		if ($DateTo === false)
			$DateTo = date("Y-m-d");

		$Query = "accounts/$AccountId/statement?format=$StatementFormat&from=$DateFrom&to=$DateTo";
		$Result = $this->HTTPPost($Query);
		if ($this->Error)	
			return false;
		if (isset($Result["data"]["statementId"]))
			return $Result["data"]["statementId"];
		else
			return false;
	}

	//-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
  // функция запроса выписки https://internetbankmb.open.ru/openhub/tokens/how-to#statement
	// Формат даты ГГГГ-ММ-ДД
	private function HTTPGetStatementStatus($AccountId, $StatementID) {
		$this->Log("HTTPGetStatementStatus($AccountId, $StatementID)");
		$Query = "accounts/$AccountId/statement/$StatementID";
		$Result = $this->HTTPGet($Query);
		if ($this->Error)	
			return false;

		if (isset($Result["data"]["status"]))
			return $Result["data"]["status"];
		else
			return false;
	}

	//-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
  // функция получения выписки https://internetbankmb.open.ru/openhub/tokens/how-to#statement
	// Print признак печати true/false. Если передать true вернется текст. Если передать false вернется файл.
	private function HTTPGetStatement($AccountId, $StatementID, $StatementFormat, $Print = true) { 
		$this->Log("HTTPGetStatement($AccountId, $StatementID)");
		if ($Print)
			$Print = "true";
		else
			$Print = "false";
		$Query = "accounts/$AccountId/statement/$StatementID/print?print=$Print";
		$Result = $this->HTTPGet($Query, $StatementFormat);
		if ($this->Error)	
			return false;

		return $Result;
	}

	//-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
  // функция получение ID счета по его номеру
	private function GetAccountIdByAccountNumber($AccountNumber) {
		$this->Error = true;
		if (!isset($this->ShortInfo)) {
			$this->Message = "Не получена короткая информация из банка";
			return false;
		}
		foreach($this->ShortInfo["data"]["accounts"] as $Account) {
			if ($Account["number"] == $AccountNumber) {
				$this->Error = false;			
				return $Account["id"];
			}
		}
		$this->Message = "Не найден счет с номером $AccountNumber";		
		return false;
	}

	//-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
  // функция запроса и получения выписки https://internetbankmb.open.ru/openhub/tokens/how-to#statement
	// Формат даты ГГГГ-ММ-ДД
	public function GetStatement($AccountNumber, $StatementFormat = "TXT", $DateFrom, $DateTo = false, $Print = true) {
		$this->Error = true;

		$AccountId = $this->GetAccountIdByAccountNumber($AccountNumber);
		if ($AccountId == false)
			return false;

		$StatementID = $this->HTTPPostStatement($AccountId, $StatementFormat, $DateFrom, $DateTo);
		if ($StatementID == false)
			return false;

		$RetryCount = 30;
		while(true) {
			$StatementStatus = $this->HTTPGetStatementStatus($AccountId, $StatementID);
			$this->Log("GetStatement StatementStatus : ", $StatementStatus);
			if ($StatementStatus === false)
				return false;
			if ($StatementStatus == "SUCCESS")
				break;
			$RetryCount--;
			if ($RetryCount <= 0)	{
				$this->Message = "Таймаут получения выписки StatementStatus = $StatementStatus";
				return false;
			}
			sleep(1);
		}

		$Content = $this->HTTPGetStatement($AccountId, $StatementID, $StatementFormat, $Print = true);
		if ($Content === false) 
			return false;
		$this->Error = false;
		return $Content;
	}

	//-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
  // функция выдачи короткой информации 
	public function GetShortInfo() {
		if (isset($this->ShortInfo))
			return $this->ShortInfo;
		else
			return false;
	}



}













