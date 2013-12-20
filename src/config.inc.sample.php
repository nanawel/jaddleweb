<?php
/*
 * DB Configuration
 */
define('JADDLE_DB_TYPE', 'mysql');							// Use "pgsql" for PostgreSQL
define('JADDLE_DB_HOST', 'localhost');
//define(JADDLE_DB_PORT, '');								// Uncomment if using a non-default port
define('JADDLE_DB_USER', 'PUT_DB_USERNAME_HERE');
define('JADDLE_DB_PASSWORD', 'PUT_DB_PASSWORD_HERE');
define('JADDLE_DB_NAME', 'mpdlogger');
define('JADDLE_DB_TABLE_HISTORY', 'history');

/*
 * Preferences
 */
define('JADDLE_MPD_MUSIC_DIR', '/var/lib/mpd/music');    	// Use "music_directory" value defined in mdp.conf
define('JADDLE_MPD_STREAM_URL', 'http://localhost:8000');   // URL to MPD stream
define('JADDLE_MPD_STREAM_FORMAT', 'ogg');                  // "ogg" or "mp3"
define('JADDLE_TRACKWATCH_TIMER', 5);                		// Check track change every 5 sec without reloading page
define('JADDLE_DISABLE_HEAVYWEIGHT_STATS', false);   		// Set to "true" to speed up page loading
define('JADDLE_COVERS_BACKGROUND_DISABLE', false);          // Disable covers mosaic
define('JADDLE_COVERS_DISABLE', false);
define('JADDLE_COVERS_EXCLUDE_FILTERS',
    'karaoke,karaoké,wedding music'                         // List of terms separated by commas
);
define('JADDLE_DISPLAY_CURRENT_TRACK_IN_TITLE', 'prefix');	// Valid values are: "prefix", "suffix", "none"/false
define('JADDLE_ENABLE_WIKIPEDIA_BUTTON', true);
define('JADDLE_ENABLE_LYRICS_BUTTON', true);
define('JADDLE_LYRICS_MODE', 'full');                       // "full" / "link"

/*
 * Should not be modified
 */
define('JADDLE_DB_CONNECTIONSTRING', JADDLE_DB_TYPE.':dbname='.JADDLE_DB_NAME.';host='.JADDLE_DB_HOST.(defined('JADDLE_DB_PORT')?';port='.JADDLE_DB_PORT:''));
define('JADDLE_PAGE_LIMIT_COUNT', 40);
define('JADDLE_PAGE_LIMIT_OFFSET', 0);
define('JADDLE_ROOT_DIR', __DIR__);
define('JADDLE_TEMPLATES_DIR', JADDLE_ROOT_DIR . '/templates');
define('JADDLE_LIBS_DIR', JADDLE_ROOT_DIR . '/libs');
define('JADDLE_CACHE_DIR', JADDLE_ROOT_DIR . '/var/cache');
define('JADDLE_COVER_SIZE', 100);
