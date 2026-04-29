<?php
namespace TSJIPPY\SIGNAL;
use TSJIPPY;

// Banking
add_action('tsjippy-banking-statement-notification', __NAMESPACE__.'\asyncSignalMessageSend', 10, 3); 

// Events
add_action('tsjippy-events-event-reminder', __NAMESPACE__.'\asyncSignalMessageSend', 10, 3); 
add_action('tsjippy-events-anniversary-message', __NAMESPACE__.'\asyncSignalMessageSend', 10, 3); 

// Usermanagement
add_action('tsjippy-user-management-birthday-message', __NAMESPACE__.'\asyncSignalMessageSend', 10, 3); 

// Prayer Message
add_action('tsjippy-prayer-send-message', __NAMESPACE__.'\sendSignalMessage', 10, 3);
