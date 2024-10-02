<?php
namespace SIM\SIGNAL;
use SIM;
use SIM\ADMIN;

class SignalEmail extends ADMIN\MailSetting{

    public $user;

    public function __construct($user) {
        // call parent constructor
		parent::__construct('signal_reminder', MODULE_SLUG);

        $this->addUser($user);

        $this->defaultSubject   = "Please add your Signal phonenumber to the %site_name% website";

        $this->defaultMessage    = 'Hi %first_name%,<br><br>';
		$this->defaultMessage   .= "We would like to know your Signal phonenumber.<br>";
		$this->defaultMessage 	.= 'Please add it to the website';
    }
}