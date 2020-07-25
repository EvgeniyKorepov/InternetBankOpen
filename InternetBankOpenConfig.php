<?php

@ini_set("display_errors", "1"); error_reporting(E_ALL);
//@ini_set("display_errors", "0"); error_reporting(0);

class InternetBankOpenAPIConfig {

	// Токен клиента, получаем в Личном кабинете банка
	protected $ClientToken = "*****************************************************************************************";

	protected $TimeZone = "Europe/Moscow";

	protected $log_file = "/opt/InternetBankOpen/log/InternetBankOpen_%date%.log";

}

