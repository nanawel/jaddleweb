JaddleWeb
=========

A simple web page to display what MPD is playing right now and what it played before.
Very useful as frontend when using as a web-radio!

Requirements:
- PHP 5.3 or greater
- Apache mod_rewrite (for embedded HTML5/Flash player)

Features:
- Displays the current track details (artist, album, track #, title, full path)
- Includes player buttons to control MPD via MPC (required)
- Navigate through the whole history of played tracks
- Includes direct links to Wikipedia for artist, album an title
- Includes HTML5 and Flash embedded player (JW Player) to play stream from your browser
- Includes also a proxified link to the stream from MPD
- Automatic cover retrieval from iTunes (when available; cached for faster subsequent access)
- Covers browser to modify or remove incorrectly detected covers
- Link to lyrics from Wikia (when available)