<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/*  Copyright 2014 Sutherland Boswell  (email : sutherland.boswell@gmail.com)
Forked by Yehuda Hassine 2020
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Require thumbnail provider class
require_once( VIDEO_THUMBNAILS_RL_PATH . '/php/providers/class-video-thumbnails-provider.php' );

class Vimeo_Thumbnails extends Video_Thumbnails_Provider {

	// Human-readable name of the video provider
	public $service_name = 'Vimeo';
	const service_name = 'Vimeo';
	// Slug for the video provider
	public $service_slug = 'vimeo';
	const service_slug = 'vimeo';

	public $options_section = array(
		'description' => '<p><strong>Optional</strong>: Only required for accessing private videos. <a href="https://developer.vimeo.com/apps">Register an app with Vimeo</a> then fill in the appropriate keys below.</p>',
		'fields' => array(
			'client_id' => array(
				'name' => 'Client ID',
				'type' => 'text',
				'description' => ''
			),
			'client_secret' => array(
				'name' => 'Client Secret',
				'type' => 'text',
				'description' => ''
			),
			'access_token' => array(
				'name' => 'Access token',
				'type' => 'text',
				'description' => ''
			),
			'access_token_secret' => array(
				'name' => 'Access token secret',
				'type' => 'text',
				'description' => ''
			)
		)
	);

	public static function register_provider( $providers ) {
		$providers[self::service_slug] = new self;
		return $providers;
	}

	// Regex strings
	public $regexes = array(
		'#<object[^>]+>.+?http://vimeo\.com/moogaloop.swf\?clip_id=([A-Za-z0-9\-_]+)&.+?</object>#s', // Standard Vimeo embed code
		'#(?:https?:)?//player\.vimeo\.com/video/([0-9]+)#', // Vimeo iframe player
		'#\[vimeo id=([A-Za-z0-9\-_]+)]#', // JR_embed shortcode
		'#\[vimeo clip_id="([A-Za-z0-9\-_]+)"[^>]*]#', // Another shortcode
		'#\[vimeo video_id="([A-Za-z0-9\-_]+)"[^>]*]#', // Yet another shortcode
		'#(?:https?://)?(?:www\.)?vimeo\.com/([0-9]+)#', // Vimeo URL
		'#(?:https?://)?(?:www\.)?vimeo\.com/channels/(?:[A-Za-z0-9]+)/([0-9]+)#' // Channel URL
	);

	// Thumbnail URL
	public function get_thumbnail_url( $id ) {
		// Get our settings
		$client_id = ( isset( $this ) && isset( $this->options['client_id'] ) && $this->options['client_id'] != '' ? $this->options['client_id'] : false );
		$client_secret = ( isset( $this ) && isset( $this->options['client_secret'] ) && $this->options['client_secret'] != '' ? $this->options['client_secret'] : false );
		$access_token = ( isset( $this ) && isset( $this->options['access_token'] ) && $this->options['access_token'] != '' ? $this->options['access_token'] : false );
		$access_token_secret = ( isset( $this ) && isset( $this->options['access_token_secret'] ) && $this->options['access_token_secret'] != '' ? $this->options['access_token_secret'] : false );
		// If API credentials are entered, use the API
		if ( $client_id && $client_secret && $access_token && $access_token_secret ) {
			$vimeo = new phpVimeo( $this->options['client_id'], $this->options['client_secret'] );
			$vimeo->setToken( $this->options['access_token'], $this->options['access_token_secret'] );
			$response = $vimeo->call('vimeo.videos.getThumbnailUrls', array('video_id'=>$id));
			$result = $response->thumbnails->thumbnail[count($response->thumbnails->thumbnail)-1]->_content;
		} else {
			$request = "http://vimeo.com/api/oembed.json?url=http%3A//vimeo.com/$id";
			$response = wp_remote_get( $request );
			if( is_wp_error( $response ) ) {
				$result = $this->construct_info_retrieval_error( $request, $response );
			} elseif ( $response['response']['code'] == 404 ) {
				$result = new WP_Error( 'vimeo_info_retrieval', __( 'The Vimeo endpoint located at <a href="' . $request . '">' . $request . '</a> returned a 404 error.<br />Details: ' . $response['response']['message'], 'video-thumbnails' ) );
			} elseif ( $response['response']['code'] == 403 ) {
				$result = new WP_Error( 'vimeo_info_retrieval', __( 'The Vimeo endpoint located at <a href="' . $request . '">' . $request . '</a> returned a 403 error.<br />This can occur when a video has embedding disabled or restricted to certain domains. Try entering API credentials in the provider settings.', 'video-thumbnails' ) );
			} else {
				$result = json_decode( $response['body'] );
				$result = $result->thumbnail_large;
			}
		}
		return $result;
	}

	// Test cases
	public static function get_test_cases() {
		return array(
			array(
				'markup'        => '<iframe src="http://player.vimeo.com/video/41504360" width="500" height="281" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>',
				'expected'      => 'http://i.vimeocdn.com/video/287850781_1280.jpg',
				'expected_hash' => '5388e0d772b827b0837444b636c9676c',
				'name'          => __( 'iFrame Embed', 'video-thumbnails' )
			),
			array(
				'markup'        => '<object width="500" height="281"><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="movie" value="http://vimeo.com/moogaloop.swf?clip_id=41504360&amp;force_embed=1&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=1&amp;color=00adef&amp;fullscreen=1&amp;autoplay=0&amp;loop=0" /><embed src="http://vimeo.com/moogaloop.swf?clip_id=41504360&amp;force_embed=1&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=1&amp;color=00adef&amp;fullscreen=1&amp;autoplay=0&amp;loop=0" type="application/x-shockwave-flash" allowfullscreen="true" allowscriptaccess="always" width="500" height="281"></embed></object>',
				'expected'      => 'http://i.vimeocdn.com/video/287850781_1280.jpg',
				'expected_hash' => '5388e0d772b827b0837444b636c9676c',
				'name'          => __( 'Flash Embed', 'video-thumbnails' )
			),
			array(
				'markup'        => 'https://vimeo.com/channels/soundworkscollection/44520894',
				'expected'      => 'http://i.vimeocdn.com/video/502998892_1280.jpg',
				'expected_hash' => 'fde254d7ef7b6463cbd2451a99f2ddb1',
				'name'          => __( 'Channel URL', 'video-thumbnails' )
			),
		);
	}

}

// Vimeo API class
if( !class_exists( 'phpVimeo' ) ) :
class phpVimeo
{
    const API_REST_URL = 'http://vimeo.com/api/rest/v2';
    const API_AUTH_URL = 'http://vimeo.com/oauth/authorize';
    const API_ACCESS_TOKEN_URL = 'http://vimeo.com/oauth/access_token';
    const API_REQUEST_TOKEN_URL = 'http://vimeo.com/oauth/request_token';

    const CACHE_FILE = 'file';

    private $_consumer_key = false;
    private $_consumer_secret = false;
    private $_cache_enabled = false;
    private $_cache_dir = false;
    private $_token = false;
    private $_token_secret = false;
    private $_upload_md5s = array();

    public function __construct($consumer_key, $consumer_secret, $token = null, $token_secret = null)
    {
        $this->_consumer_key = $consumer_key;
        $this->_consumer_secret = $consumer_secret;

        if ($token && $token_secret) {
            $this->setToken($token, $token_secret);
        }
    }

    /**
     * Cache a response.
     *
     * @param array $params The parameters for the response.
     * @param string $response The serialized response data.
     */
    private function _cache($params, $response)
    {
        // Remove some unique things
        unset($params['oauth_nonce']);
        unset($params['oauth_signature']);
        unset($params['oauth_timestamp']);

        $hash = md5(serialize($params));

        if ($this->_cache_enabled == self::CACHE_FILE) {
            $file = $this->_cache_dir.'/'.$hash.'.cache';
            if (file_exists($file)) {
                unlink($file);
            }
            return file_put_contents($file, $response);
        }
    }

    /**
     * Create the authorization header for a set of params.
     *
     * @param array $oauth_params The OAuth parameters for the call.
     * @return string The OAuth Authorization header.
     */
    private function _generateAuthHeader($oauth_params)
    {
        $auth_header = 'Authorization: OAuth realm=""';

        foreach ($oauth_params as $k => $v) {
            $auth_header .= ','.self::url_encode_rfc3986($k).'="'.self::url_encode_rfc3986($v).'"';
        }

        return $auth_header;
    }

    /**
     * Generate a nonce for the call.
     *
     * @return string The nonce
     */
    private function _generateNonce()
    {
        return md5(uniqid(microtime()));
    }

    /**
     * Generate the OAuth signature.
     *
     * @param array $args The full list of args to generate the signature for.
     * @param string $request_method The request method, either POST or GET.
     * @param string $url The base URL to use.
     * @return string The OAuth signature.
     */
    private function _generateSignature($params, $request_method = 'GET', $url = self::API_REST_URL)
    {
        uksort($params, 'strcmp');
        $params = self::url_encode_rfc3986($params);

        // Make the base string
        $base_parts = array(
            strtoupper($request_method),
            $url,
            urldecode(http_build_query($params, '', '&'))
        );
        $base_parts = self::url_encode_rfc3986($base_parts);
        $base_string = implode('&', $base_parts);

        // Make the key
        $key_parts = array(
            $this->_consumer_secret,
            ($this->_token_secret) ? $this->_token_secret : ''
        );
        $key_parts = self::url_encode_rfc3986($key_parts);
        $key = implode('&', $key_parts);

        // Generate signature
        return base64_encode(hash_hmac('sha1', $base_string, $key, true));
    }

    /**
     * Get the unserialized contents of the cached request.
     *
     * @param array $params The full list of api parameters for the request.
     */
    private function _getCached($params)
    {
        // Remove some unique things
        unset($params['oauth_nonce']);
        unset($params['oauth_signature']);
        unset($params['oauth_timestamp']);

        $hash = md5(serialize($params));

        if ($this->_cache_enabled == self::CACHE_FILE) {
            $file = $this->_cache_dir.'/'.$hash.'.cache';
            if (file_exists($file)) {
                return unserialize(file_get_contents($file));
            }
        }
    }

    /**
     * Call an API method.
     *
     * @param string $method The method to call.
     * @param array $call_params The parameters to pass to the method.
     * @param string $request_method The HTTP request method to use.
     * @param string $url The base URL to use.
     * @param boolean $cache Whether or not to cache the response.
     * @param boolean $use_auth_header Use the OAuth Authorization header to pass the OAuth params.
     * @return string The response from the method call.
     */
    private function _request($method, $call_params = array(), $request_method = 'GET', $url = self::API_REST_URL, $cache = true, $use_auth_header = true)
    {
        // Prepare oauth arguments
        $oauth_params = array(
            'oauth_consumer_key' => $this->_consumer_key,
            'oauth_version' => '1.0',
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_nonce' => $this->_generateNonce()
        );

        // If we have a token, include it
        if ($this->_token) {
            $oauth_params['oauth_token'] = $this->_token;
        }

        // Regular args
        $api_params = array('format' => 'php');
        if (!empty($method)) {
            $api_params['method'] = $method;
        }

        // Merge args
        foreach ($call_params as $k => $v) {
            if (strpos($k, 'oauth_') === 0) {
                $oauth_params[$k] = $v;
            }
            else if ($call_params[$k] !== null) {
                $api_params[$k] = $v;
            }
        }

        // Generate the signature
        $oauth_params['oauth_signature'] = $this->_generateSignature(array_merge($oauth_params, $api_params), $request_method, $url);

        // Merge all args
        $all_params = array_merge($oauth_params, $api_params);

        // Returned cached value
        if ($this->_cache_enabled && ($cache && $response = $this->_getCached($all_params))) {
            return $response;
        }

        // Curl options
        if ($use_auth_header) {
            $params = $api_params;
        }
        else {
            $params = $all_params;
        }

        $args = array(
            'method' => strtoupper($request_method),
            'body' => $params,
            'timeout' => 30,
        );

        if ( $args['method'] === 'GET' ) {
            $url = $url . '?' . http_build_query( $params );
            unset( $args['body'] );
        }

        // Authorization header
        if ($use_auth_header) {
            $args['headers'] = array($this->_generateAuthHeader($oauth_params));
        }

        // Call the API
        $result = wp_remote_request( $url, $args );

        if ( is_wp_error( $result ) ) {
            return false;
        }

        $responseCode = wp_remote_retrieve_response_code( $result );

        if ( $responseCode !== 200 && $responseCode !== 201 ) {
            return false;
        }

        $response = wp_remote_retrieve_body( $result );
        // Cache the response
        if ($this->_cache_enabled && $cache) {
            $this->_cache($all_params, $response);
        }

        // Return
        if (!empty($method)) {
            $response = unserialize($response);
            if ($response->stat == 'ok') {
                return $response;
            }
            else if ($response->err) {
                throw new VimeoAPIException($response->err->msg, $response->err->code);
            }

            return false;
        }

        return $response;
    }

    /**
     * Send the user to Vimeo to authorize your app.
     * http://www.vimeo.com/api/docs/oauth
     *
     * @param string $perms The level of permissions to request: read, write, or delete.
     */
    public function auth($permission = 'read', $callback_url = 'oob')
    {
        $t = $this->getRequestToken($callback_url);
        $this->setToken($t['oauth_token'], $t['oauth_token_secret'], 'request', true);
        $url = $this->getAuthorizeUrl($this->_token, $permission);
        header("Location: {$url}");
    }

    /**
     * Call a method.
     *
     * @param string $method The name of the method to call.
     * @param array $params The parameters to pass to the method.
     * @param string $request_method The HTTP request method to use.
     * @param string $url The base URL to use.
     * @param boolean $cache Whether or not to cache the response.
     * @return array The response from the API method
     */
    public function call($method, $params = array(), $request_method = 'GET', $url = self::API_REST_URL, $cache = true)
    {
        $method = (substr($method, 0, 6) != 'vimeo.') ? "vimeo.{$method}" : $method;
        return $this->_request($method, $params, $request_method, $url, $cache);
    }

    /**
     * Enable the cache.
     *
     * @param string $type The type of cache to use (phpVimeo::CACHE_FILE is built in)
     * @param string $path The path to the cache (the directory for CACHE_FILE)
     * @param int $expire The amount of time to cache responses (default 10 minutes)
     */
    public function enableCache($type, $path, $expire = 600)
    {
        $this->_cache_enabled = $type;
        if ($this->_cache_enabled == self::CACHE_FILE) {
            $this->_cache_dir = $path;
            $files = scandir($this->_cache_dir);
            foreach ($files as $file) {
                $last_modified = filemtime($this->_cache_dir.'/'.$file);
                if (substr($file, -6) == '.cache' && ($last_modified + $expire) < time()) {
                    unlink($this->_cache_dir.'/'.$file);
                }
            }
        }
        return false;
    }

    /**
     * Get an access token. Make sure to call setToken() with the
     * request token before calling this function.
     *
     * @param string $verifier The OAuth verifier returned from the authorization page or the user.
     */
    public function getAccessToken($verifier)
    {
        $access_token = $this->_request(null, array('oauth_verifier' => $verifier), 'GET', self::API_ACCESS_TOKEN_URL, false, true);
        parse_str($access_token, $parsed);
        return $parsed;
    }

    /**
     * Get the URL of the authorization page.
     *
     * @param string $token The request token.
     * @param string $permission The level of permissions to request: read, write, or delete.
     * @param string $callback_url The URL to redirect the user back to, or oob for the default.
     * @return string The Authorization URL.
     */
    public function getAuthorizeUrl($token, $permission = 'read')
    {
        return self::API_AUTH_URL."?oauth_token={$token}&permission={$permission}";
    }

    /**
     * Get a request token.
     */
    public function getRequestToken($callback_url = 'oob')
    {
        $request_token = $this->_request(
            null,
            array('oauth_callback' => $callback_url),
            'GET',
            self::API_REQUEST_TOKEN_URL,
            false,
            false
        );

        parse_str($request_token, $parsed);
        return $parsed;
    }

    /**
     * Get the stored auth token.
     *
     * @return array An array with the token and token secret.
     */
    public function getToken()
    {
        return array($this->_token, $this->_token_secret);
    }

    /**
     * Set the OAuth token.
     *
     * @param string $token The OAuth token
     * @param string $token_secret The OAuth token secret
     * @param string $type The type of token, either request or access
     * @param boolean $session_store Store the token in a session variable
     * @return boolean true
     */
    public function setToken($token, $token_secret, $type = 'access', $session_store = false)
    {
        $this->_token = $token;
        $this->_token_secret = $token_secret;

        if ($session_store) {
            $_SESSION["{$type}_token"] = $token;
            $_SESSION["{$type}_token_secret"] = $token_secret;
        }

        return true;
    }

    /**
     * Upload a video in one piece.
     *
     * @param string $file_path The full path to the file
     * @param boolean $use_multiple_chunks Whether or not to split the file up into smaller chunks
     * @param string $chunk_temp_dir The directory to store the chunks in
     * @param int $size The size of each chunk in bytes (defaults to 2MB)
     * @return int The video ID
     */
    public function upload($file_path, $use_multiple_chunks = false, $chunk_temp_dir = '.', $size = 2097152, $replace_id = null)
    {
        if (!file_exists($file_path)) {
            return false;
        }

        // Figure out the filename and full size
        $path_parts = pathinfo($file_path);
        $file_name = $path_parts['basename'];
        $file_size = filesize($file_path);

        // Make sure we have enough room left in the user's quota
        $quota = $this->call('vimeo.videos.upload.getQuota');
        if ($quota->user->upload_space->free < $file_size) {
            throw new VimeoAPIException('The file is larger than the user\'s remaining quota.', 707);
        }

        // Get an upload ticket
        $params = array();

        if ($replace_id) {
            $params['video_id'] = $replace_id;
        }

        $rsp = $this->call('vimeo.videos.upload.getTicket', $params, 'GET', self::API_REST_URL, false);
        $ticket = $rsp->ticket->id;
        $endpoint = $rsp->ticket->endpoint;

        // Make sure we're allowed to upload this size file
        if ($file_size > $rsp->ticket->max_file_size) {
            throw new VimeoAPIException('File exceeds maximum allowed size.', 710);
        }

        // Split up the file if using multiple pieces
        $chunks = array();
        if ($use_multiple_chunks) {
            if (!is_writeable($chunk_temp_dir)) {
                throw new Exception('Could not write chunks. Make sure the specified folder has write access.');
            }

            // Create pieces
            $number_of_chunks = ceil(filesize($file_path) / $size);
            for ($i = 0; $i < $number_of_chunks; $i++) {
                $chunk_file_name = "{$chunk_temp_dir}/{$file_name}.{$i}";

                // Break it up
                $chunk = file_get_contents($file_path, FILE_BINARY, null, $i * $size, $size);
                $file = file_put_contents($chunk_file_name, $chunk);

                $chunks[] = array(
                    'file' => realpath($chunk_file_name),
                    'size' => filesize($chunk_file_name)
                );
            }
        }
        else {
            $chunks[] = array(
                'file' => realpath($file_path),
                'size' => filesize($file_path)
            );
        }

        // Upload each piece
        foreach ($chunks as $i => $chunk) {
            $args = array(
                'headers' => array(
                    'accept' => 'application/json',
                    'content-type'  => 'application/binary',
                ),
                'body' => array(
                    'oauth_consumer_key'     => $this->_consumer_key,
                    'oauth_token'            => $this->_token,
                    'oauth_signature_method' => 'HMAC-SHA1',
                    'oauth_timestamp'        => time(),
                    'oauth_nonce'            => $this->_generateNonce(),
                    'oauth_version'          => '1.0',
                    'ticket_id'              => $ticket,
                    'chunk_id'               => $i,
                    'oauth_signature' => $this->_generateSignature($params, 'POST', self::API_REST_URL),
                    'file_data'       => $chunk['file']
                )
            );

            // Post the file
            wp_remote_post( $endpoint, $args );
        }

        // Verify
        $verify = $this->call('vimeo.videos.upload.verifyChunks', array('ticket_id' => $ticket));

        // Make sure our file sizes match up
        foreach ($verify->ticket->chunks as $chunk_check) {
            $chunk = $chunks[$chunk_check->id];

            if ($chunk['size'] != $chunk_check->size) {
                // size incorrect, uh oh
                echo "Chunk {$chunk_check->id} is actually {$chunk['size']} but uploaded as {$chunk_check->size}<br>";
            }
        }

        // Complete the upload
        $complete = $this->call('vimeo.videos.upload.complete', array(
            'filename' => $file_name,
            'ticket_id' => $ticket
        ));

        // Clean up
        if (count($chunks) > 1) {
            foreach ($chunks as $chunk) {
                unlink($chunk['file']);
            }
        }

        // Confirmation successful, return video id
        if ($complete->stat == 'ok') {
            return $complete->ticket->video_id;
        }
        else if ($complete->err) {
            throw new VimeoAPIException($complete->err->msg, $complete->err->code);
        }
    }

    /**
     * Upload a video in multiple pieces.
     *
     * @deprecated
     */
    public function uploadMulti($file_name, $size = 1048576)
    {
        // for compatibility with old library
        return $this->upload($file_name, true, '.', $size);
    }

    /**
     * URL encode a parameter or array of parameters.
     *
     * @param array/string $input A parameter or set of parameters to encode.
     */
    public static function url_encode_rfc3986($input)
    {
        if (is_array($input)) {
            return array_map(array('phpVimeo', 'url_encode_rfc3986'), $input);
        }
        else if (is_scalar($input)) {
            return str_replace(array('+', '%7E'), array(' ', '~'), rawurlencode($input));
        }
        else {
            return '';
        }
    }

}

endif;

if( !class_exists( 'VimeoAPIException' ) ) {
    class VimeoAPIException extends Exception {}
}

?>