<?php
/**
 * JaddleWeb main class
 *
 * @author Nanawel <nanawel at -no-spam- gmail dot com>
 * @since 2013-09
 */
class JaddleWeb
{
    const VERSION = '0.6.0';

    const ACTION_KEY_CONTROL = 'c';
    const ACTION_KEY_IMAGE = 'i';
    const ACTION_KEY_AJAX = 'a';
    const ACTION_KEY_STREAMING = 's';

    const URL_MISSING_COVER = 'img/nocover.png';


    protected $_debugEnabled;
    protected $_profiler = null;

    protected $_pdo = null;
    protected $_connectionString = null;
    protected $_user = null;
    protected $_password = null;

    protected $_tracksHistory = array();
    protected $_trackDetails = array();
    protected $_statistics = array();

    protected $_lyricsApi = null;


    public function __construct($connectionString, $username, $password, $enableDebug = true) {
        $this->_connectionString = $connectionString;
        $this->_user = $username;
        $this->_password = $password;
        $this->_debugEnabled = $enableDebug;

        self::initLibs();
    }

    public static function initLibs() {
        static $init = false;

        if (!$init) {
            include JADDLE_LIBS_DIR . '/EZProfiler.class.php';

            // iTunes API
            include JADDLE_LIBS_DIR . '/iTunes.class.php';

            // LyricWiki API
            include JADDLE_LIBS_DIR . '/botclasses.classes.php';
            include JADDLE_LIBS_DIR . '/lyricwiki.classes.php';

            $init = true;
        }
    }

    public function getVersion() {
        return self::VERSION;
    }

    public function handleRequest() {
        if (!$this->_handleActions()) {
            $this->getProfiler()->start('PAGE');
            $this->_renderPage();

            if ($this->_debugEnabled) {
                $this->getProfiler()->dump();
            }
        }
    }

    protected function _handleActions() {
        $this->_handleActionControl()
            || $this->_handleActionImage()
            || $this->_handleActionAjax()
            || $this->_handleActionStreaming();
    }

    protected function _handleActionControl() {
        if (isset($_GET[self::ACTION_KEY_CONTROL]) && $control = (string) $_GET[self::ACTION_KEY_CONTROL]) {
            $command = null;
            switch($control) {
            	case 'prev':
            	    $command = 'mpc prev';
            	    break;
            	case 'stop':
            	    $command = 'mpc stop';
            	    break;
            	case 'play':
            	    $command = 'mpc play';
            	    break;
            	case 'next':
            	    $command = 'mpc next';
            	    break;
            }

            if ($command !== null) {
                $res = shell_exec($command);

                // Wait a bit, to be sure we display the new current song on refresh
                sleep(2);

                //Refresh page to display the new current song
                $url = parse_url($_SERVER['REQUEST_URI']);
                header('Location: ' . $url['path']);

                return true;
            }
        }
        return false;
    }

    protected function _handleActionImage() {
        if (isset($_GET[self::ACTION_KEY_IMAGE]) && $imageType = (string) $_GET[self::ACTION_KEY_IMAGE]) {
            switch($imageType) {
            	case 'cover':
            	    $tid = isset($_GET['tid']) && ((int) $_GET['tid']) ? ((int) $_GET['tid']) : null;
            	    $coverDetails = $this->getCover($tid);

            	    header('Content-Type: image/jpeg');
            	    header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 60 * 60 * 24 * 360)); // 1 year
            	    $h = fopen($coverDetails['path'], 'r');
            	    $this->log($coverDetails['path'], 'Cover | Returning cover from cache');
            	    fpassthru($h);
            	    exit;

            	case 'bgmosaic':
            	    $width = isset($_GET['w']) && is_numeric($_GET['w']) ? (int) $_GET['w'] : 2000;
            	    $height = isset($_GET['h']) && is_numeric($_GET['h']) ? (int) $_GET['h'] : 400;
            	    $color = isset($_GET['c']) ? (string) $_GET['c'] : 'eee';

            	    // Set some limits...
            	    $width = min($width, 3000);
            	    $height = min($height, 2000);

            	    $img = false;
            	    if (!JADDLE_COVERS_BACKGROUND_DISABLE) {
            	        $files = glob(JADDLE_CACHE_DIR . '/*.jpg');
            	        $img = self::_imageMosaic($files, false, 100, $width, $height);

            	        // Adjust brigtness & contrast
            	        //imagefilter($img, IMG_FILTER_BRIGHTNESS, 160);
            	        //imagefilter($img, IMG_FILTER_CONTRAST, 20);

            	        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 60 * 30));    // 30 mn
            	    }

            	    // Fallback if covers background disabled OR can't create mosaic
            	    if (!$img) {
            	        $img = imagecreatetruecolor($width, $height);
            	        imagealphablending($img, true);
            	        imagesavealpha($img, true);

            	        $rgbColor = self::_hex2rgb($color);
            	        if (!is_array($rgbColor)) {
            	            $rgbColor = array(238, 238, 238);
            	        }
            	        $hexColor = imagecolorallocatealpha($img, $rgbColor[0], $rgbColor[1], $rgbColor[2], 0);
            	        imagefill($img, 0, 0, $hexColor);

            	        // Add logo
            	        $logo = imagecreatefrompng(__DIR__ . '/../img/subheader-bg.png');
            	        imagesavealpha($logo, true);
            	        imagecopy($img, $logo, 180, 60, 0, 0, imagesx($logo), imagesy($logo));
            	        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 60 * 60 * 24));    // 1 day
            	    }
            	    if ($img) {
            	        // Rendering
            	        header('Content-Type: image/jpeg');
            	        imagejpeg($img);
            	        imagedestroy($img);
            	    }
            	    exit;

        	    case 'currentlyplayingboxbg':
        	        $color = isset($_GET['c']) ? (string) $_GET['c'] : 'fff';

        	        // Covers disabled => background is fully transparent (127/127)
        	        // Covers enabled => background is almost completely opaque (12/127)
        	        $alpha = JADDLE_COVERS_BACKGROUND_DISABLE ? 127 : 12;

    	            $img = imagecreatetruecolor(1, 1);
    	            imagealphablending($img, true);
    	            imagesavealpha($img, true);

    	            $rgbColor = self::_hex2rgb($color);
    	            if (!is_array($rgbColor)) {
    	                $rgbColor = array(255, 255, 255);
    	            }
    	            $hexColor = imagecolorallocatealpha($img, $rgbColor[0], $rgbColor[1], $rgbColor[2], $alpha);
    	            imagefill($img, 0, 0, $hexColor);

    	            // Rendering
    	            header('Content-Type: image/png');
    	            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 60 * 30));    // 30 mn
    	            imagepng($img);
    	            imagedestroy($img);
        	        exit;
            }
            return true;
        }
        return false;
    }

    protected function _handleActionAjax() {
        if (isset($_GET[self::ACTION_KEY_AJAX]) && $action = (string) $_GET[self::ACTION_KEY_AJAX]) {
            switch($action) {
            	case 'trackwatch':
            	    $currentTrackId = $this->getCurrentTrackId();
            	    $currentTrackProgress = $this->getCurrentTrackProgress();

            	    header('Cache-Control: no-cache, must-revalidate');
            	    header('Content-Type" content="application/json;charset=UTF-8');
            	    echo json_encode(array(
        	            'id'       => $currentTrackId,
        	            'progress' => $currentTrackProgress ? $currentTrackProgress : 0
            	    ));
            	    exit;

            	case 'coverbrowser':
            	    $trackId = isset($_GET['tid']) && ((int) $_GET['tid']) ? ((int) $_GET['tid']) : null;
            	    $terms = isset($_GET['s']) && ((string) $_GET['s']) ? ((string) $_GET['s']) : null;

            	    header('Cache-Control: no-cache, must-revalidate');
                    header('Content-Type" content="text/html;charset=UTF-8');
                    include JADDLE_TEMPLATES_DIR . '/cover/browser.phtml';
            	    exit;

            	case 'updatecover':
            	    $trackId = isset($_GET['tid']) && ((int) $_GET['tid']) ? ((int) $_GET['tid']) : null;
            	    $coverUrl = isset($_GET['coverUrl']) && ((string) $_GET['coverUrl']) ? ((string) $_GET['coverUrl']) : null;

            	    if ($trackId && $coverUrl) {
                	    $cacheConfig = $this->_getCacheConfigForCover($trackId);
                        $this->_saveCover($trackId, $coverUrl);

                	    header('Cache-Control: no-cache, must-revalidate');
                	    header('Content-Type" content="application/json;charset=UTF-8');
                	    echo json_encode(array(
            	            'url' => $cacheConfig['url']
                	    ));
            	    }
            	    else {
            	       echo '{}';
            	    }
            	    exit;

            	case 'lyricsviewer':
            	    $trackId = isset($_GET['tid']) && ((int) $_GET['tid']) ? ((int) $_GET['tid']) : null;

            	    $lyricsData = $this->getLyrics($trackId);
            	    if ($lyricsData) {
                	    $lyricsPageContent = file_get_contents($lyricsData['url']);
                	    preg_match('#(<article.*?</article>)#is', $lyricsPageContent, $m);
                	    $lyricsPageContent = $m[1];

                	    header('Cache-Control: no-cache, must-revalidate');
                        header('Content-Type" content="text/html;charset=UTF-8');
                        echo $lyricsPageContent;
            	    }
            	    exit;
            }
            return true;
        }
        return false;
    }

    protected function _handleActionStreaming() {
        if (isset($_GET[self::ACTION_KEY_STREAMING]) && $action = (string) $_GET[self::ACTION_KEY_STREAMING]
            && isset($_GET['f']) && $format = (string) $_GET['f']) {
            switch($action) {
            	case 'listen':
                    header('Cache-Control: no-cache, no-store');
                    header('Pragma: no-cache');
                    header('Content-Type: audio/' . $format);
                    readfile(JADDLE_MPD_STREAM_URL);
            }
            return true;
        }
        return false;
    }

    protected function _renderPage() {
        header('Cache-Control: no-cache, must-revalidate');
        header('Content-Type" content="text/html;charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET');
        include JADDLE_TEMPLATES_DIR . '/page.phtml';
    }

    public function getPageTitle() {
    	$title = '';
    	$currentTrackDetails = $this->getCurrentTrackDetails(true);
    	if (!$currentTrackDetails) {
    		return 'Jaddle';
    	}

    	$track = $currentTrackDetails['artist'] . ' - ' . $currentTrackDetails['title'];
    	switch (JADDLE_DISPLAY_CURRENT_TRACK_IN_TITLE) {
    		case 'prefix':
    			$title = $track . ' - Jaddle';
    			break;
    		case 'suffix':
    			$title = 'Jaddle - ' . $track;
    			break;
    		case 'none':
    		default:
    			$title = 'Jaddle';
    			break;
    	}
    	return $title;
    }

    public function getCurrentTrackDetails($falseIfNotPlaying = true) {
        $trackDetails = $this->getTrackDetails();
        if ($trackDetails) {
            if ($trackDetails['progress'] > ($trackDetails['time'] + 15) && $falseIfNotPlaying) { 	// 15 seconds margin
                $return = false;
            }
            else {
                $return = $trackDetails;
            }
        }
        else {
            $return = false;
        }
        return $return;
    }

    public function &getTrackDetails($trackId = null) {
        $conn = $this->getConnection();

        if (!isset($this->_trackDetails[$trackId])) {
            $sql = 'select * from ' . JADDLE_DB_TABLE_HISTORY;
            if ($trackId) {
                $sql .= ' where ID = ' . $trackId;
            }
            $sql .= ' order by ID desc limit 1';
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $trackDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (isset($trackDetails[0])) {
                $trackDetails = $trackDetails[0];
                foreach($trackDetails as $key => &$value) {
                    if (null === $value) {
                        $value = '';
                    }
                }

                // Calculate progress
                $playDateStart = strtotime($trackDetails['date']);
                $trackDetails['progress'] = time() - $playDateStart;
                $trackDetails['progress_percent'] = $trackDetails['progress'] <= 0 ? 0 : round($trackDetails['progress'] * 100 / $trackDetails['time']);

                $this->_trackDetails[$trackId] = $trackDetails;
            }
            else {
                $this->_trackDetails[$trackId] = false;
            }
        }
        return $this->_trackDetails[$trackId];
    }

    public function getCurrentTrackId() {
        $currentTrackDetails = $this->getCurrentTrackDetails(false);
        if ($currentTrackDetails) {
            return $currentTrackDetails['id'];
        }
        return 0;
    }

    public function getCurrentTrackProgress() {
        $currentTrackDetails = $this->getCurrentTrackDetails(false);
        if ($currentTrackDetails) {
            return $currentTrackDetails['progress_percent'];
        }
        return 0;
    }

    ///////// STREAMING /////////
    public function getStreamExternalUrl() {
        return trim($this->getCurrentUrl(), '/') . '/listen.' . JADDLE_MPD_STREAM_FORMAT;
    }

    protected function getCurrentUrl() {
        $url = 'http';
        if (isset($_SERVER['HTTPS'])) {
            $url .= 's';
        }
        $url .= '://';
        if ($_SERVER['SERVER_PORT'] != '80') {
            $url .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
        }
        else {
            $url .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        }
        return $url;
    }


    ///////// OUTPUT ////////

    public function getGoToPageUrl($page) {
        $stats = $this->getTracksStats();

        $totalTracks = (int) $stats['Total Tracks Count'];
        $limit = $this->getLimit();
        $offset = $this->getOffset();
        switch($page)  {
        	case 'previous':
        	    if ($offset - $limit < 0) {
        	        $url = "?l=$limit&o=0";
        	    }
        	    else {
        	        $url = "?l=$limit&o=" . ($offset - $limit);
        	    }
        	    break;
        	case 'next':
        	    $url = "?l=$limit&o=" . ($offset + $limit);
        	    break;
        	case 'first':
        	    $url = "?l=$limit&o=0";
        	    break;
        	case 'last':
        	    $url = "?l=$limit&o=" . ($totalTracks - $totalTracks % $limit);
        	    break;

        	default:
        	    $url = '#';
        }
        return htmlentities($url);
    }

    public function getLimit() {
        if (isset($_GET['l'])) {
            return ((int) $_GET['l']) || 'all' == $_GET['l'] ? $_GET['l'] : JADDLE_PAGE_LIMIT_COUNT;
        }
        else {
            return JADDLE_PAGE_LIMIT_COUNT;
        }
    }

    public function getOffset() {
        if (isset($_GET['o'])) {
            return ((int) $_GET['o']) ? (int) $_GET['o'] : JADDLE_PAGE_LIMIT_OFFSET;
        }
        else {
            return JADDLE_PAGE_LIMIT_OFFSET;
        }
    }

    public function renderStat($key, $value) {
        if (is_array($value)) {
            $_tableData = $value;
            include JADDLE_TEMPLATES_DIR . '/statistics/table.phtml';
        }
        else {
            echo self::htmlText($value);
        }
    }

    public function getSearchLinkUrl($key) {
        if (!JADDLE_ENABLE_WIKIPEDIA_BUTTON) {
            return false;
        }
        $currentTrackDetails = $this->getCurrentTrackDetails();
        if ($currentTrackDetails) {
            return 'http://fr.wikipedia.org/w/index.php?search=' . urlencode($currentTrackDetails[$key]);
        }
        return false;
    }

    ///////// IMAGES / COVERS /////////

    public function getCover($trackId = null) {
        $this->log($trackId ? $trackId : '<current>', 'Cover | Track ID');
        $trackDetails = $this->getTrackDetails($trackId);
        $this->log($trackDetails ? $trackDetails : '<none>', 'Cover | Track details');
        if ($trackDetails) {
            $cacheConfig = $this->_getCacheConfigForCover($trackId);

            $this->log($cacheConfig['path'], 'Cover | Checking cache');

            ///////////////////////////////////////////////////////////////////////
            // First check if a cover exists in the directory and can be used
            if (!file_exists($cacheConfig['path'])) {
                $this->log($cacheConfig['path'], 'Cover | Files does not exist in cache, checking album\'s directory');
                $this->_useCoverFromAlbumDir($trackId);
            }

            ///////////////////////////////////////////////////////////////////////
            // Otherwise use a remote service
            if (!file_exists($cacheConfig['path'])) {
                $results = $this->getAllCovers($trackId);

                if ($results) {
                    $this->log($results[0]['url'], 'Cover | Remote image found');
                    $this->_saveCover($trackId, $results[0]['url']);
                    $this->log($cacheConfig['path'], 'Cover | Remote image saved in cache');
                }
            }
            if (file_exists($cacheConfig['path'])) {
                $cover = array(
                    'path' => $cacheConfig['path'],
                    'url' => $cacheConfig['url'],
                    'height' => JADDLE_COVER_SIZE,
                    'width' => JADDLE_COVER_SIZE,
                    'alt' => $trackDetails['album'] . ' cover'
                );
                return $cover;
            }
        }
        return array(
            'path' => JADDLE_ROOT_DIR . '/' . self::URL_MISSING_COVER,
            'url' => self::URL_MISSING_COVER,
            'height' => JADDLE_COVER_SIZE,
            'width' => JADDLE_COVER_SIZE,
            'alt' => '(Missing cover)'
        );
    }

    protected function _getCacheConfigForCover($trackId) {
        if (!is_dir(JADDLE_CACHE_DIR)) {
            mkdir(JADDLE_CACHE_DIR, 0777, true);
        }

        $trackDetails = $this->getTrackDetails($trackId);
        $cacheKey = sha1($trackDetails['artist'] . $trackDetails['album']) . '.jpg';
        return array(
            'key' => $cacheKey,
        	'url'  => substr(JADDLE_CACHE_DIR, strlen(JADDLE_ROOT_DIR) + 1) . '/' . $cacheKey,
            'path' => JADDLE_CACHE_DIR . '/' . $cacheKey
        );
    }

    protected function _saveCover($trackId, $url) {
        $cacheConfig = $this->_getCacheConfigForCover($trackId);
        if ($url == self::URL_MISSING_COVER) {
            $imageData = file_get_contents(JADDLE_ROOT_DIR . '/' . $url);
        }
        else {
            $imageData = file_get_contents($url);
        }
        file_put_contents($cacheConfig['path'], $imageData);
        chmod($cacheConfig['path'], 0777);
    }

    protected function _useCoverFromAlbumDir($trackId = null) {
        $trackDetails = $this->getTrackDetails($trackId);
        $cacheConfig = $this->_getCacheConfigForCover($trackId);

        $trackDir = JADDLE_MPD_MUSIC_DIR . '/' . $trackDetails['fullpath'];
        if (is_dir($trackDir) && is_readable($trackDir)) {
            $supportedExtensions = array('png', 'jpg', 'jpeg', 'gif');
            $globPattern = $trackDir . '/*.{' . implode(',', $supportedExtensions) . '}';
            $globPattern = str_replace(array('[', ']'), array('\[', '\]'), $globPattern);
            $files = glob($globPattern, GLOB_BRACE);

            // Here we assume that the biggest image is likely to be the cover
            usort($files, array(__CLASS__, '_compareImages'));

            foreach($files as $file) {
                if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $supportedExtensions)) {
                    $this->_createCoverThumbnailFromImage($file, $cacheConfig['path']);
                    return true;
                }
            }
        }
        return false;
    }

    /**
     *
     * @param int $trackId
     * @return array
     */
    public function getAllCovers($trackId = null, $searchTerms = null) {
        $this->log($trackId, 'Cover | Preparing to use external service');

        $trackDetails = $this->getTrackDetails($trackId);

        // Remove year prefix (my own formatting for folders)
        $album = preg_replace('/^(?:\[[0-9]{4}\])? (.*)/', '$1', $trackDetails['album']);

        $found = false;
        $results = null;
        if ($searchTerms === null) {
            $search = array(
                    $trackDetails['artist'] . ' ' . $album,
                    $album,
                    $trackDetails['artist'],
            );
        }
        else {
            $search = array($searchTerms);
        }
        for ($i = 0; $i < count($search) && !$found; $i++) {
            $this->log($search[$i], 'Cover | Searching iTunes');

            $searchStart = microtime(true);
            $results = iTunes::search($search[$i], array(
                    'media' => 'music'
            ));
            $searchTime = microtime(true) - $searchStart;

            $this->log(round($searchTime, 4), 'Cover | iTunes Results Time (seconds)');
            $this->log($results, 'Cover | iTunes Results');

            if ($results && $results->resultCount) {
                $results = $results->results;
                $found = $results && $results[0];
            }
        }

        $urls = array();
        $formattedResults = array();
        foreach($results as $r) {
            if (isset($r->artworkUrl100) && array_search($r->artworkUrl100, $urls) === false) {
                $formattedResult = array(
                	'url' => $r->artworkUrl100,
                    'width' => JADDLE_COVER_SIZE,
                    'height' => JADDLE_COVER_SIZE,
                    'artist' => isset($r->artistName) ? $r->artistName : '',
                    'album' => isset($r->collectionName) ? $r->collectionName : ''
                );
                $formattedResult['alt'] = $formattedResult['artist'];
                $formattedResult['alt'] .= $formattedResult['album'] ? ' - ' . $formattedResult['album'] : '';
            	$formattedResults[] = $formattedResult;
                $urls[] = $r->artworkUrl100;
            }
        }
        $formattedResults = $this->_filterCovers($formattedResults);
        $formattedResults[] = array(
            'url' => 'img/nocover.png',
            'width' => JADDLE_COVER_SIZE,
            'height' => JADDLE_COVER_SIZE,
            'artist' => '',
            'album' => '',
            'alt' => '(Missing cover)'
        );
        return $formattedResults;
    }

    /**
     * Filter cover results
     *
     * @param array $results
     * @return array The filtered results
     */
    protected function _filterCovers($results) {
        $filteredResults = array();
        if (defined('JADDLE_COVERS_EXCLUDE_FILTERS')) {
            $terms = array_map('trim', explode(',', JADDLE_COVERS_EXCLUDE_FILTERS));
            if ($terms) {
                foreach($results as $result) {
                    $clean = true;
                    foreach($terms as $term) {
                        if (stripos($result['alt'], $term) !== false) {
                            $clean = false;
                            break;
                        }
                    }
                    if ($clean) {
                        $filteredResults[] = $result;
                    }
                }
            }
        }
        return $filteredResults;
    }

    protected function _createCoverThumbnailFromImage($imagePath, $thumbnailPath) {
        $this->log($imagePath, 'Cover | Generating thumbnail');
        list($width, $height) = getimagesize($imagePath);

        if ($width / $height < 1) {
            $newHeight = 100;
            $newWidth = 100 * $width / $height;
        }
        else {
            $newHeight = 100 * $height / $width;
            $newWidth = 100;
        }

        $newWidth = round($newWidth);
        $newHeight = round($newHeight);

        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        $source = imagecreatefromjpeg($imagePath);

        imagecopyresized($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagejpeg($thumb, $thumbnailPath);

        $this->log($thumbnailPath, 'Cover | Thumbnail generated');

        imagedestroy($thumb);
    }

    protected static function _compareImages($a, $b) {
        return filesize($b) - filesize($a);
    }

    /**
     *
     * @see http://bavotasan.com/2011/convert-hex-color-to-rgb-using-php/
     * @param string $hex
     * @return multitype:number
     */
    protected static function _hex2rgb($hex) {
        $hex = str_replace('#', '', $hex);

        if(strlen($hex) == 3) {
            $r = hexdec(substr($hex,0,1).substr($hex,0,1));
            $g = hexdec(substr($hex,1,1).substr($hex,1,1));
            $b = hexdec(substr($hex,2,1).substr($hex,2,1));
        } else {
            $r = hexdec(substr($hex,0,2));
            $g = hexdec(substr($hex,2,2));
            $b = hexdec(substr($hex,4,2));
        }
        $rgb = array($r, $g, $b);
        return $rgb;
    }

    protected static function _imageMosaic(array $inputFiles, $targetFilename, $sourceWidthHeight = 100,
                                           $targetWidth = 1600, $targetHeight = 1200, $shuffle = true)
    {
        if(count($inputFiles) == 0) {
            trigger_error('$inputFiles must not be empty', E_USER_WARNING);
            return false;
        }

        $outputImage = imagecreatetruecolor($targetWidth, $targetHeight);

        $mosaicWidth = $targetWidth / $sourceWidthHeight;
        $mosaicHeight = $targetHeight / $sourceWidthHeight;
        $mosaicSize = $mosaicWidth * $mosaicHeight;

        $filesUsed = 0;
        $filesIgnored = 0;

        $fileHashes = array();

        reset($inputFiles);
        for ($i = 0; $i < $mosaicWidth; $i++) {
            for ($j = 0; $j < $mosaicHeight; $j++) {
                $fileOk = false;
                while (!$fileOk) {
                    if (!$inputFile = next($inputFiles)) {
                        reset($inputFiles);
                        $inputFile = next($inputFiles);
                    }
                    $fileHash = md5_file($inputFile);
                    if (false === $fileHash || isset($fileHashes[$fileHash])) {
                        unset($inputFiles[key($inputFiles)]);
                        if (empty($inputFiles)) {
                            trigger_error('Not enough valid image files found!', E_USER_ERROR);
                            return false;
                        }
                        continue;
                    }
                    $inputImage = @imagecreatefromjpeg($inputFile);
                    if (false === $inputImage) {
                        unset($inputFiles[key($inputFiles)]);
                        if (empty($inputFiles)) {
                            trigger_error('No valid image file found!', E_USER_ERROR);
                            return false;
                        }
                        continue;
                    }
                    $iw = imagesx($inputImage);
                    $ih = imagesy($inputImage);
                    if ($iw == $ih && $iw == $sourceWidthHeight) {
                        $fileOk = imagecopy($outputImage, $inputImage, $i * $sourceWidthHeight, $j * $sourceWidthHeight, 0, 0, $sourceWidthHeight, $sourceWidthHeight);
                    }
                    else {
                        unset($inputFiles[key($inputFiles)]);
                        if (empty($inputFiles)) {
                            trigger_error('No valid image file found!', E_USER_ERROR);
                            return false;
                        }
                        $filesIgnored++;
                    }
                    imagedestroy($inputImage);
                }
                if ($shuffle) {
                    shuffle($inputFiles);
                }
                $filesUsed++;
            }
        }

        if (is_string($targetFilename)) {
            $return = @imagejpeg($outputImage, $targetFilename, 80);
            imagedestroy($outputImage);
        }
        else {
            $return = $outputImage;
        }
        return $return;
    }

    ///////// LYRICS /////////

    public function getLyrics($trackId = null) {
        if (!JADDLE_ENABLE_LYRICS_BUTTON) {
            return false;
        }
        if (!$this->_lyricsApi) {
            $this->_lyricsApi = new lyricwiki();
            $this->_lyricsApi->quiet = true;
        }

        $trackDetails = $this->getTrackDetails($trackId);
        if (!isset($trackDetails['lyrics_data'])) {
            $response = $this->_lyricsApi->getSong($trackDetails['artist'], $trackDetails['title']);
            if (!isset($response['lyrics']) || $response['lyrics'] == 'Not found') {
                $trackDetails['lyrics_data'] = false;
            }
            else {
                $trackDetails['lyrics_data'] = array(
                    'lyrics' => utf8_decode($response['lyrics']),
                    'url'    => $response['url']
                );
            }
        }
        return $trackDetails['lyrics_data'];
    }

    ///////// DATA RETRIEVING /////////

    /**
     *
     * @return PDO
     */
    public function getConnection() {
        if ($this->_pdo === null) {
            $this->_pdo = new PDO($this->_connectionString, $this->_user, $this->_password);
        }
        return $this->_pdo;
    }

    /**
     *
     * @return array
     */
    public function getTracksHistory() {
        if (!$this->_tracksHistory) {
            $limit = $this->getLimit();
            $offset = $this->getOffset();
            $query = 'select * from ' . JADDLE_DB_TABLE_HISTORY
                   . ' order by ID desc'
                   . ' limit ' . $limit . ' offset ' . $offset;
            $stmt = $this->getConnection()->prepare($query);
            $this->getProfiler()->start('HISTORY SELECT', $query);
            $stmt->execute();
            $this->_tracksHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->getProfiler()->stop('HISTORY SELECT');
        }
        return $this->_tracksHistory;
    }

    public function getTracksStats() {
        if (!$this->_statistics) {
            $conn = $this->getConnection();
            $profiler = $this->getProfiler();

            $query = 'select count(id) from ' . JADDLE_DB_TABLE_HISTORY;
            $stmt = $conn->prepare($query);
            $profiler->start('HISTORY COUNT RECORDS', $query);
            $stmt->execute();
            $this->_statistics['Total Tracks Count'] = $stmt->fetchColumn(0);
            $profiler->stop('HISTORY COUNT RECORDS');

            $query ='select DATE from ' . JADDLE_DB_TABLE_HISTORY . ' order by DATE asc limit 1';
            $stmt = $conn->prepare($query);
            $profiler->start('OLDEST HISTORY RECORD', $query);
            $stmt->execute();
            $this->_statistics['Oldest History Record'] = $stmt->fetchColumn(0);
            $profiler->stop('OLDEST HISTORY RECORD');

            if (!JADDLE_DISABLE_HEAVYWEIGHT_STATS) {
                $query = 'select ARTIST, count(ID) as PLAY_COUNT from ' . JADDLE_DB_TABLE_HISTORY . ' group by ARTIST order by PLAY_COUNT desc limit 10';
                $stmt = $conn->prepare($query);
                $profiler->start('MOST PLAYED ARTISTS', $query);
                $stmt->execute();
                $this->_statistics['Most Played Artists'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $profiler->stop('MOST PLAYED ARTISTS');

                $query = 'select ALBUM, ARTIST, count(ID) as PLAY_COUNT '
                        . 'from ' . JADDLE_DB_TABLE_HISTORY . ' as MAIN_TABLE group by ALBUM, ARTIST order by PLAY_COUNT desc limit 10';
                $stmt = $conn->prepare($query);
                $profiler->start('MOST PLAYED ALBUMS', $query);
                $stmt->execute();
                $this->_statistics['Most Played Albums'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $profiler->stop('MOST PLAYED ALBUMS');
            }
        }
        return $this->_statistics;
    }

    ///////// HELPERS /////////

    public static function formatTrackData($key, $value) {
        switch ($key) {
        	case 'time':
        	    $mn = (($mn = floor($value / 60)) < 10) ? '0' . ($mn) : $mn;
        	    $sec = (($sec = floor($value % 60)) < 10) ? '0' . ($sec) : $sec;
        	    $value = $mn . ':' . $sec;
        	    break;

        	default:
        }
        return $value;
    }

    /**
     *
     * @param string $text
     * @param boolean $underscoresToSpaces
     * @return string
     */
    public static function htmlText($text, $underscoresToSpaces = false) {
        $text = htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
    	return $underscoresToSpaces ? str_replace('_', ' ', $text) : $text;
    }

    ///////// LOGGING & PROFILING /////////

    /**
     *
     * @param string $data
     * @param string $label
     * @param string $filename
     * @return boolean
     */
    public function log($data, $label = '', $filename = 'debug.log') {
        if ($this->_debugEnabled) {
            if (!is_file($filename)) {
                file_put_contents($filename, '');
                chmod($filename, 0777); //Ensure everyone can read and if needed, truncate it
            }
            $r = file_put_contents($filename, date('c') . ': ' . ($label ? '[' . $label . '] ' : '') . print_r($data, true) . "\n", FILE_APPEND);
            return $r === false ? false : $r;
        }
        return false;
    }

    /**
     *
     * @return EZProfiler
     */
    public function getProfiler() {
        if ($this->_profiler === null) {
            $this->_profiler = new EZProfiler();
        }
        return $this->_profiler;
    }

    public function getPageExecTime() {
        return $this->getProfiler()->get('PAGE', true, true);
    }
}