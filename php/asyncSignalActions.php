<?php
namespace SIM\SIGNAL;
use SIM;

// Banking
add_action('sim-banking-statement-notification', __NAMESPACE__.'\asyncSignalMessageSend', 10, 3); 

// Events
add_action('sim-events-event-reminder', __NAMESPACE__.'\asyncSignalMessageSend', 10, 3); 
add_action('sim-events-anniversary-message', __NAMESPACE__.'\asyncSignalMessageSend', 10, 3); 

// Usermanagement
add_action('sim-user-management-birthday-message', __NAMESPACE__.'\asyncSignalMessageSend', 10, 3); 

// Prayer Message
add_action('sim-prayer-send-message', __NAMESPACE__.'\sendSignalMessage', 10, 3);
