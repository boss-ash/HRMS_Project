<?php
/**
 * Google Sign-In (Gmail). Copy to google_oauth.php and fill in.
 * Get credentials: https://console.cloud.google.com/apis/credentials
 * - Create OAuth 2.0 Client ID (Web application)
 * - Authorized redirect URI: https://your-domain.com/HRMS_Project/google_callback.php
 * - For localhost: http://localhost/HRMS_Project/google_callback.php
 */
return [
    'client_id'     => 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com',
    'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET',
    'redirect_uri'  => 'http://localhost/HRMS_Project/google_callback.php', // must match Google Console
];
