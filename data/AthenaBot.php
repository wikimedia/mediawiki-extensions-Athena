<?php
/**
 * A script to grab information about new pages from a wiki
 *
 * @file
 * @ingroup Maintenance
 * @author Richard Cook <cook879@shoutwiki.com>
 * @date 28 November 2015
 */

/**
 * Set the correct include path for PHP so that we can run this script from
 * $IP/extensions/Athena and we don't need to move this file to
 * $IP/maintenance/.
 */
ini_set( 'include_path', dirname( __FILE__ ) . '/../../../maintenance' );

require_once( 'Maintenance.php' );

class AthenaBot extends Maintenance {

    public function __construct() {
        parent::__construct();
        $this->mDescription = 'Adds the new pages in the given json file to the site, with the given user and language contexts';
    }

    public function execute() {
        global $wgServer, $wgScriptPath, $session_name, $url;
        $session_name = 'spam_session';

        $file = fopen( $this->getArg(), 'r' );

        // Making lot's of assumptions about input (json file, one line of code)
        // TODO data validation and stuff?
        $file_contents = fgets($file);
        $json = json_decode($file_contents, true);

        $url = $wgServer . $wgScriptPath . '/api.php';
        foreach($json as $page) {
           // echo 'Namespace: ' . $page['namespace'] . "\n";
            //echo 'Title: ' . $page['title'] . "\n";
            //echo 'Comment: ' . $page['comment'] . "\n";
            //echo 'Content: ' . $page['content'] . "\n";
            //echo 'Timestamp: ' . $page['timestamp'] . "\n";
            //echo 'User timestamp: ' . $page['user-timestamp'] . "\n";
           // echo 'Lang: ' . $page['lang'] . "\n";
            //echo "\n\n\n";

            // Can't use edit.php as it doesn't let you specify anon
            $apiCall = 'action=edit&format=json&title=' .
                urlencode(AthenaBot::getNamespace($page['namespace']) . $page['title'])
                . '&text=' . urlencode($page['content']);

            if( !empty($page['comment']) )
                $apiCall .= '&summary=' . urlencode($page['comment']);

            // Using CURL as need sessions, which file_get_contents can't provide
            $ch = curl_init();

            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
            //curl_setopt( $ch, CURLOPT_VERBOSE, 1 );
            // Header needed for cookies
            //curl_setopt( $ch, CURLOPT_HEADER, 1 );

            // User time
            if( $page['user-timestamp'] === 0 ) {
                $apiCall .= '&token=' . urlencode('+\\');
            } else {
                // Cookies for the login
                $cookieString = AthenaBot::createUser();
                curl_setopt( $ch, CURLOPT_COOKIE, $cookieString);

                // Get an edit token
                $token = AthenaBot::getEditToken($cookieString);
            }

            curl_setopt( $ch, CURLOPT_POSTFIELDS, $apiCall );

            $response = curl_exec($ch);
            echo($response);
            echo("\n\n");
            curl_close($ch);

            sleep(60);
            // Can't use below code as cookies

            /*$apiCall = array('action' => 'edit',
                            'format' => 'json',
                            'title' => AthenaBot::getNamespace($page['namespace']) . $page['title'],
                            'text' => $page['content']
            );
            if( !empty($page['comment']) )
                $apiCall['summary'] = $page['comment'];

            // User time
            if( $page['user-timestamp'] === 0 ) {
                $apiCall['token'] = '+\\';
            } else {
                $apiCall['token'] = AthenaBot::createUser($url);

            }
            $options = array(
                'http' => array(
                    'header'  => "Content-type: application/x-www-form-urlencoded",
                    'method'  => 'POST',
                    // this should handle all the urlencoding for us
                    'content' => http_build_query($apiCall),
                ),
            );

            print_r($apiCall);
            print_r($options);

            $response = file_get_contents($url, false, stream_context_create($options));
            echo($response);
            echo("\n\n");
            sleep(5);*/
        }

        fclose($file);
    }

    /**
     * Takes a namespace id and returns the relevant text, including the colon.
     *
     * @param $id int
     * @return string
     */
    public static function getNamespace($id) {
        switch ($id) {
            case 0:
                return '';
            case 1:
                return 'Talk:';
            case 2:
                return 'User:';
            case 3:
                return 'User talk:';
            case 4:
                return 'Project:';
            case 5:
                return 'Project talk:';
            case 6:
                return 'File:';
            case 7:
                return 'File talk:';
            case 8:
                return 'MediaWiki:';
            case 9:
                return 'MediaWiki talk:';
            case 10:
                return 'Template:';
            case 11:
                return 'Template talk:';
            case 12:
                return 'Help:';
            case 13:
                return 'Help talk:';
            case 14:
                return 'Category:';
            case 15:
                return 'Category talk:';
            default:
                return '';
        }
    }

     /**
     * Creates a new user. Returns their session cookie
     *
     * @return string
     */
    public static function createUser() {
        global $session_name, $url;
        // Let's create a new user
        // Random unique string generator from http://stackoverflow.com/questions/19017694/one-line-php-random-string-generator
        $username = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 1).substr(md5(time()),1);

        $apiCall = 'action=createaccount&format=json&name=' . urlencode($username) . '&password=123&token=';

        // Using CURL as need sessions, which file_get_contents can't provide
        $ch = curl_init();

        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        //curl_setopt( $ch, CURLOPT_VERBOSE, 1 );
        // Header needed for cookies
        curl_setopt( $ch, CURLOPT_HEADER, 1 );
        curl_setopt( $ch, CURLOPT_POST, 5 ); // Number of fields
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $apiCall );

        $response = curl_exec($ch);
        //echo($response);
        //echo("\n\n");

        // From http://stackoverflow.com/questions/895786/how-to-get-the-cookies-from-a-php-curl-into-a-variable
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
        $cookies = array();
        foreach($matches[1] as $item) {
            parse_str($item, $cookie);
            $cookies = array_merge($cookies, $cookie);
        }
        // Let's assume we only get one cookie and it's the one we want
        //var_dump($cookies);
        curl_setopt( $ch, CURLOPT_COOKIE, $session_name . '=' .$cookies[$session_name].'; path=/');

        // We only want to parse the body of the response as json
        $header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
        $body = substr($response, $header_size);

        $json = json_decode($body, true);
        //print_r($json);
        $apiCall .= $json['createaccount']['token'];
        //print_r($apiCall);

        curl_setopt( $ch, CURLOPT_POSTFIELDS, $apiCall );

        // Don't need the headers anymore
        curl_setopt( $ch, CURLOPT_HEADER, 0 );

        $response = curl_exec($ch);
        echo($response);
        echo("\n\n");

        // END CREATION
        // START LOGIN

        $apiCall = 'action=login&format=json&lgname=' . urlencode($username) . '&lgpassword=123';
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $apiCall );
        $response = curl_exec($ch);
        echo($response);
        echo("\n\n");


        $json = json_decode($response, true);
        //print_r($json);
        $apiCall .= '&lgtoken=' . $json['login']['token'];
        //print_r($apiCall);

        curl_setopt( $ch, CURLOPT_POSTFIELDS, $apiCall );

        $response = curl_exec($ch);
        echo($response);
        echo("\n\n");

        $cookieString = $json['login']['cookieprefix'] . '=' . $json['login']['sessionid'] .'; path=/';


        curl_close($ch);

        return $cookieString;

        /* $apiCall = array('action' => 'createaccount',
             'format' => 'json',
             'name' => $username,
             'password' => '123',
             'token' => ''
         );


         $options = array(
             'http' => array(
                 'header'  => "Content-type: application/x-www-form-urlencoded",
                 'method'  => 'POST',
                 // this should handle all the urlencoding for us
                 'content' => http_build_query($apiCall),
             ),
         );
         print_r($options);

         $context = stream_context_create($options);
         $response = file_get_contents($url, false, $context);
         echo($response);
         echo("\n\n");

         // Get the response token
         $json = json_decode($response, true);
         echo("json is ");
         print_r($json);
         $apiCall['token'] = $json['createaccount']['token'];

         print_r($apiCall);
         $options['http']['content'] = http_build_query($apiCall);
         print_r($options);

         stream_context_set_option($context, $options);
         $response = file_get_contents($url, false, $context);
         echo($response);
         echo("\n\n");*/
    }
    /**
     * Gets an edit token for a user
     *
     * @param $cookieString string
     * @return string
     */
    public static function getEditToken($cookieString) {
        global $url;

        //$apiCall = 'action=query&format=json&meta=tokens';
        $apiCall = 'action=query&format=json&meta=userinfo&uiprop=rights|hasmsg';
        // Using CURL as need sessions, which file_get_contents can't provide
        $ch = curl_init();

        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_COOKIE, $cookieString);
        curl_setopt( $ch, CURLOPT_VERBOSE, 1);
        echo($cookieString);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $apiCall );

        $response = curl_exec($ch);
        echo($response);
        echo("\n\n");


        $json = json_decode($response, true);
        print_r($json);

        curl_close($ch);

    }

}

$maintClass = 'AthenaBot';
require_once( RUN_MAINTENANCE_IF_MAIN );
