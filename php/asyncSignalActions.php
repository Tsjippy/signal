<?php

namespace TSJIPPY\SIGNAL;

use TSJIPPY;

// Events
add_action('tsjippy-events-event-reminder', __NAMESPACE__ . '\asyncSignalMessageSend', 10, 3);
add_action('tsjippy-events-anniversary-message', __NAMESPACE__ . '\asyncSignalMessageSend', 10, 3);

// Usermanagement
add_action('tsjippy-user-management-birthday-message', __NAMESPACE__ . '\asyncSignalMessageSend', 10, 3);

// Daily Message
add_action('tsjippy-daily-message-send', __NAMESPACE__ . '\sendSignalMessage', 10, 3);
