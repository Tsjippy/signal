# FILTERS
- apply_filters('sim_signal_post_notification_message', $excerpt, $post);
- apply_filters('sim_personal_signal_settings', '', $user, $prefs);
- apply_filters( 'password_reset_expiration', DAY_IN_SECONDS );
- return apply_filters('sim-signal-daemon-response', $response, $message, $source, $users, $name);

# Actions