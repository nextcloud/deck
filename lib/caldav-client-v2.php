<?php
/**
* A Class for connecting to a caldav server
*
* @package   awl
*
* @subpackage   caldav
* @author Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Andrew McMillan
* @license   http://www.gnu.org/licenses/lgpl-3.0.txt  GNU LGPL version 3 or later
*/

require_once('XMLDocument.php');

/**
 * A class for holding basic calendar information
 * @package awl
 */
class CalendarInfo {
  public $url;
  public $displayname;
  public $getctag;

  function __construct( $url, $displayname = null, $getctag = null ) {
    $this->url = $url;
    $this->displayname = $displayname;
    $this->getctag = $getctag;
  }

  function __toString() {
    return( '(URL: '.$this->url.'   Ctag: '.$this->getctag.'   Displayname: '.$this->displayname .')'. "\n" );
  }
}

if(!defined("_FSOCK_TIMEOUT")){
  define("_FSOCK_TIMEOUT", 10);
}

/**
* A class for accessing DAViCal via CalDAV, as a client
*
* @package   awl
*/
class CalDAVClient {
  /**
  * Server, username, password, calendar
  *
  * @var string
  */
  protected $base_url, $user, $pass, $entry, $protocol, $server, $port;

  /**
  * The principal-URL we're using
  */
  protected $principal_url;

  /**
  * The calendar-URL we're using
  */
  protected $calendar_url;

  /**
  * The calendar-home-set we're using
  */
  protected $calendar_home_set;

  /**
  * The calendar_urls we have discovered
  */
  protected $calendar_urls;

  /**
  * The useragent which is send to the caldav server
  *
  * @var string
  */
  public $user_agent = 'DAViCalClient';

  protected $headers = array();
  protected $body = "";
  protected $requestMethod = "GET";
  protected $httpRequest = "";  // for debugging http headers sent
  protected $xmlRequest = "";   // for debugging xml sent
  protected $xmlResponse = "";  // xml received
  protected $httpResponseCode = 0; // http response code
  protected $httpResponseHeaders = "";
  protected $httpParsedHeaders;
  protected $httpResponseBody = "";  

  protected $parser; // our XML parser object
  
  private $debug = false; // Whether we are debugging

  /**
  * Constructor, initialises the class
  *
  * @param string $base_url  The URL for the calendar server
  * @param string $user      The name of the user logging in
  * @param string $pass      The password for that user
  */
  function __construct( $base_url, $user, $pass ) {
    $this->user = $user;
    $this->pass = $pass;
    $this->headers = array();

    if ( preg_match( '#^(https?)://([a-z0-9.-]+)(:([0-9]+))?(/.*)$#', $base_url, $matches ) ) {
      $this->server = $matches[2];
      $this->base_url = $matches[5];
      if ( $matches[1] == 'https' ) {
        $this->protocol = 'ssl';
        $this->port = 443;
      }
      else {
        $this->protocol = 'tcp';
        $this->port = 80;
      }
      if ( $matches[4] != '' ) {
        $this->port = intval($matches[4]);
      }
    }
    else {
      trigger_error("Invalid URL: '".$base_url."'", E_USER_ERROR);
    }
  }

  
  /**
   * Call this to enable / disable debugging.  It will return the prior value of the debugging flag.
   * @param boolean $new_value The new value for debugging.
   * @return boolean The previous value, in case you want to restore it later.
   */
  function SetDebug( $new_value ) {
    $old_value = $this->debug;
    if ( $new_value )
      $this->debug = true;
    else
      $this->debug = false;
    return $old_value;
  }

  
  
  /**
  * Adds an If-Match or If-None-Match header
  *
  * @param bool $match to Match or Not to Match, that is the question!
  * @param string $etag The etag to match / not match against.
  */
  function SetMatch( $match, $etag = '*' ) {
    $this->headers['match'] = sprintf( "%s-Match: \"%s\"", ($match ? "If" : "If-None"), trim($etag,'"'));
  }

  /**
  * Add a Depth: header.  Valid values are 0, 1 or infinity
  *
  * @param int $depth  The depth, default to infinity
  */
  function SetDepth( $depth = '0' ) {
    $this->headers['depth'] = 'Depth: '. ($depth == '1' ? "1" : ($depth == 'infinity' ? $depth : "0") );
  }

  /**
  * Add a Depth: header.  Valid values are 1 or infinity
  *
  * @param int $depth  The depth, default to infinity
  */
  function SetUserAgent( $user_agent = null ) {
    if ( !isset($user_agent) ) $user_agent = $this->user_agent;
    $this->user_agent = $user_agent;
  }

  /**
  * Add a Content-type: header.
  *
  * @param string $type  The content type
  */
  function SetContentType( $type ) {
    $this->headers['content-type'] = "Content-type: $type";
  }

  /**
  * Set the calendar_url we will be using for a while.
  *
  * @param string $url The calendar_url
  */
  function SetCalendar( $url ) {
    $this->calendar_url = $url;
  }

  /**
  * Split response into httpResponse and xmlResponse
  *
  * @param string Response from server
   */
  function ParseResponse( $response ) {
    $pos = strpos($response, '<?xml');
    if ($pos !== false) {
      $this->xmlResponse = trim(substr($response, $pos));
      $this->xmlResponse = preg_replace('{>[^>]*$}s', '>',$this->xmlResponse );
      $parser = xml_parser_create_ns('UTF-8');
      xml_parser_set_option ( $parser, XML_OPTION_SKIP_WHITE, 1 );
      xml_parser_set_option ( $parser, XML_OPTION_CASE_FOLDING, 0 );

      if ( xml_parse_into_struct( $parser, $this->xmlResponse, $this->xmlnodes, $this->xmltags ) === 0 ) {
        printf( "XML parsing error: %s - %s\n", xml_get_error_code($parser), xml_error_string(xml_get_error_code($parser)) );
//        debug_print_backtrace();
//        echo "\nNodes array............................................................\n"; print_r( $this->xmlnodes );
//        echo "\nTags array............................................................\n";  print_r( $this->xmltags );
        printf( "\nXML Reponse:\n%s\n", $this->xmlResponse );
      }

      xml_parser_free($parser);
    }
  }

  /**
  * Split httpResponseHeaders into an array of headers
  *
  * @return array of arrays of header lines
   */
  function ParseResponseHeaders() {
    if ( empty($this->httpResponseHeaders) ) return array();
    if ( !isset($this->httpParsedHeaders) ) {
      $this->httpParsedHeaders = array();
      $headers = str_replace("\r\n", "\n", $this->httpResponseHeaders);
      $ar_headers = explode("\n", $headers);
      $last_header = '';
      foreach ($ar_headers as $cur_headers) {
        if( preg_match( '{^\s*\S}', $cur_headers) )  $header_name = $last_header;
        else if ( preg_match( '{^(\S*):', $cur_headers, $matches) ) {
          $header_name = $matches[1];
          $last_header = $header_name;
          if ( empty($this->httpParsedHeaders[$header_name]) ) $this->httpParsedHeaders[$header_name] = array();
        }
        $this->httpParsedHeaders[$header_name][] = $cur_headers;
      }
    }
    return $this->httpParsedHeaders;
  }

  /**
   * Output http request headers
   *
   * @return HTTP headers
   */
  function GetHttpRequest() {
      return $this->httpRequest;
  }
  /**
   * Output http response headers
   *
   * @return HTTP headers
   */
  function GetResponseHeaders() {
      return $this->httpResponseHeaders;
  }
  /**
   * Output http response body
   *
   * @return HTTP body
   */
  function GetResponseBody() {
      return $this->httpResponseBody;
  }
  /**
   * Output xml request
   *
   * @return raw xml
   */
  function GetXmlRequest() {
      return $this->xmlRequest;
  }
  /**
   * Output xml response
   *
   * @return raw xml
   */
  function GetXmlResponse() {
      return $this->xmlResponse;
  }

  /**
  * Send a request to the server
  *
  * @param string $url The URL to make the request to
  *
  * @return string The content of the response from the server
  */
  function DoRequest( $url = null ) {
    $headers = array();

    if ( !isset($url) ) $url = $this->base_url;
    $this->request_url = $url;
    $url = preg_replace('{^https?://[^/]+}', '', $url);
    // URLencode if it isn't already
    if ( preg_match( '{[^%?&=+,.-_/a-z0-9]}', $url ) ) {
      $url = str_replace(rawurlencode('/'),'/',rawurlencode($url));
      $url = str_replace(rawurlencode('?'),'?',$url);
      $url = str_replace(rawurlencode('&'),'&',$url);
      $url = str_replace(rawurlencode('='),'=',$url);
      $url = str_replace(rawurlencode('+'),'+',$url);
      $url = str_replace(rawurlencode(','),',',$url);
    }
    $headers[] = $this->requestMethod." ". $url . " HTTP/1.1";
    $headers[] = "Authorization: Basic ".base64_encode($this->user .":". $this->pass );
    $headers[] = "Host: ".$this->server .":".$this->port;

    if ( !isset($this->headers['content-type']) ) $this->headers['content-type'] = "Content-type: text/plain";
    foreach( $this->headers as $ii => $head ) {
      $headers[] = $head;
    }
    $headers[] = "Content-Length: " . strlen($this->body);
    $headers[] = "User-Agent: " . $this->user_agent;
    $headers[] = 'Connection: close';
    $this->httpRequest = join("\r\n",$headers);
    $this->xmlRequest = $this->body;

    $this->xmlResponse = '';

    $fip = fsockopen( $this->protocol . '://' . $this->server, $this->port, $errno, $errstr, _FSOCK_TIMEOUT); //error handling?
    if ( !(get_resource_type($fip) == 'stream') ) return false;
    if ( !fwrite($fip, $this->httpRequest."\r\n\r\n".$this->body) ) { fclose($fip); return false; }
    $response = "";
    while( !feof($fip) ) { $response .= fgets($fip,8192); }
    fclose($fip);

    list( $this->httpResponseHeaders, $this->httpResponseBody ) = preg_split( '{\r?\n\r?\n}s', $response, 2 );
    if ( preg_match( '{Transfer-Encoding: chunked}i', $this->httpResponseHeaders ) ) $this->Unchunk();
    if ( preg_match('/HTTP\/\d\.\d (\d{3})/', $this->httpResponseHeaders, $status) )
      $this->httpResponseCode = intval($status[1]);
    else
      $this->httpResponseCode = 0;

    $this->headers = array();  // reset the headers array for our next request
    $this->ParseResponse($this->httpResponseBody);
    return $response;
  }


  /**
  * Unchunk a chunked response
  */
  function Unchunk() {
    $content = '';
    $chunks = $this->httpResponseBody;
    // printf( "\n================================\n%s\n================================\n", $chunks );
    do {
      $bytes = 0;
      if ( preg_match('{^((\r\n)?\s*([ 0-9a-fA-F]+)(;[^\n]*)?\r?\n)}', $chunks, $matches ) ) {
        $octets = $matches[3];
        $bytes = hexdec($octets);
        $pos = strlen($matches[1]);
        // printf( "Chunk size 0x%s (%d)\n", $octets, $bytes );
        if ( $bytes > 0 ) {
          // printf( "---------------------------------\n%s\n---------------------------------\n", substr($chunks,$pos,$bytes) );
          $content .= substr($chunks,$pos,$bytes);
          $chunks = substr($chunks,$pos + $bytes + 2);
          // printf( "+++++++++++++++++++++++++++++++++\n%s\n+++++++++++++++++++++++++++++++++\n", $chunks );
        }
      }
      else {
        $content .= $chunks;
      }
    }
    while( $bytes > 0 );
    $this->httpResponseBody = $content;
    // printf( "~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~\n%s\n~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~\n", $content );
  }


  /**
  * Send an OPTIONS request to the server
  *
  * @param string $url The URL to make the request to
  *
  * @return array The allowed options
  */
  function DoOptionsRequest( $url = null ) {
    $this->requestMethod = "OPTIONS";
    $this->body = "";
    $this->DoRequest($url);
    $this->ParseResponseHeaders();
    $allowed = '';
    foreach( $this->httpParsedHeaders['Allow'] as $allow_header ) {
      $allowed .= preg_replace( '/^(Allow:)?\s+([a-z, ]+)\r?\n.*/is', '$1,', $allow_header );
    }
    $options = array_flip( preg_split( '/[, ]+/', trim($allowed, ', ') ));
    return $options;
  }



  /**
  * Send an XML request to the server (e.g. PROPFIND, REPORT, MKCALENDAR)
  *
  * @param string $method The method (PROPFIND, REPORT, etc) to use with the request
  * @param string $xml The XML to send along with the request
  * @param string $url The URL to make the request to
  *
  * @return array An array of the allowed methods
  */
  function DoXMLRequest( $request_method, $xml, $url = null ) {
    $this->body = $xml;
    $this->requestMethod = $request_method;
    $this->SetContentType("text/xml");
    return $this->DoRequest($url);
  }



  /**
  * Get a single item from the server.
  *
  * @param string $url The URL to GET
  */
  function DoGETRequest( $url ) {
    $this->body = "";
    $this->requestMethod = "GET";
    return $this->DoRequest( $url );
  }


  /**
  * Get the HEAD of a single item from the server.
  *
  * @param string $url The URL to HEAD
  */
  function DoHEADRequest( $url ) {
    $this->body = "";
    $this->requestMethod = "HEAD";
    return $this->DoRequest( $url );
  }


  /**
  * PUT a text/icalendar resource, returning the etag
  *
  * @param string $url The URL to make the request to
  * @param string $icalendar The iCalendar resource to send to the server
  * @param string $etag The etag of an existing resource to be overwritten, or '*' for a new resource.
  *
  * @return string The content of the response from the server
  */
  function DoPUTRequest( $url, $icalendar, $etag = null ) {
    $this->body = $icalendar;

    $this->requestMethod = "PUT";
    if ( $etag != null ) {
      $this->SetMatch( ($etag != '*'), $etag );
    }
    $this->SetContentType('text/calendar; charset="utf-8"');
    $this->DoRequest($url);

    $etag = null;
    if ( preg_match( '{^ETag:\s+"([^"]*)"\s*$}im', $this->httpResponseHeaders, $matches ) ) $etag = $matches[1];
    if ( !isset($etag) || $etag == '' ) {
      if ( $this->debug ) printf( "No etag in:\n%s\n", $this->httpResponseHeaders );
      $save_request = $this->httpRequest;
      $save_response_headers = $this->httpResponseHeaders;
      $this->DoHEADRequest( $url );
      if ( preg_match( '{^Etag:\s+"([^"]*)"\s*$}im', $this->httpResponseHeaders, $matches ) ) $etag = $matches[1];
      if ( !isset($etag) || $etag == '' ) {
        if ( $this->debug ) printf( "Still No etag in:\n%s\n", $this->httpResponseHeaders );
      }
      $this->httpRequest = $save_request;
      $this->httpResponseHeaders = $save_response_headers;
    }
    return $etag;
  }


  /**
  * DELETE a text/icalendar resource
  *
  * @param string $url The URL to make the request to
  * @param string $etag The etag of an existing resource to be deleted, or '*' for any resource at that URL.
  *
  * @return int The HTTP Result Code for the DELETE
  */
  function DoDELETERequest( $url, $etag = null ) {
    $this->body = "";

    $this->requestMethod = "DELETE";
    if ( $etag != null ) {
      $this->SetMatch( true, $etag );
    }
    $this->DoRequest($url);
    return $this->httpResponseCode;
  }


  /**
  * Get a single item from the server.
  *
  * @param string $url The URL to PROPFIND on
  */
  function DoPROPFINDRequest( $url, $props, $depth = 0 ) {
    $this->SetDepth($depth);
    $xml = new XMLDocument( array( 'DAV:' => '', 'urn:ietf:params:xml:ns:caldav' => 'C' ) );
    $prop = new XMLElement('prop');
    foreach( $props AS $v ) {
      $xml->NSElement($prop,$v);
    }

    $this->body = $xml->Render('propfind',$prop );

    $this->requestMethod = "PROPFIND";
    $this->SetContentType("text/xml");
    $this->DoRequest($url);
    return $this->GetXmlResponse();
  }


  /**
  * Get/Set the Principal URL
  *
  * @param $url string The Principal URL to set
  */
  function PrincipalURL( $url = null ) {
    if ( isset($url) ) {
      $this->principal_url = $url;
    }
    return $this->principal_url;
  }


  /**
  * Get/Set the calendar-home-set URL
  *
  * @param $url array of string The calendar-home-set URLs to set
  */
  function CalendarHomeSet( $urls = null ) {
    if ( isset($urls) ) {
      if ( ! is_array($urls) ) $urls = array($urls);
      $this->calendar_home_set = $urls;
    }
    return $this->calendar_home_set;
  }


  /**
  * Get/Set the calendar-home-set URL
  *
  * @param $urls array of string The calendar URLs to set
  */
  function CalendarUrls( $urls = null ) {
    if ( isset($urls) ) {
      if ( ! is_array($urls) ) $urls = array($urls);
      $this->calendar_urls = $urls;
    }
    return $this->calendar_urls;
  }


  /**
  * Return the first occurrence of an href inside the named tag.
  *
  * @param string $tagname The tag name to find the href inside of
  */
  function HrefValueInside( $tagname ) {
    foreach( $this->xmltags[$tagname] AS $k => $v ) {
      $j = $v + 1;
      if ( $this->xmlnodes[$j]['tag'] == 'DAV::href' ) {
        return rawurldecode($this->xmlnodes[$j]['value']);
      }
    }
    return null;
  }


  /**
  * Return the href containing this property.  Except only if it's inside a status != 200
  *
  * @param string $tagname The tag name of the property to find the href for
  * @param integer $which Which instance of the tag should we use
  */
  function HrefForProp( $tagname, $i = 0 ) {
    if ( isset($this->xmltags[$tagname]) && isset($this->xmltags[$tagname][$i]) ) {
      $j = $this->xmltags[$tagname][$i];
      while( $j-- > 0 && $this->xmlnodes[$j]['tag'] != 'DAV::href' ) {
//        printf( "Node[$j]: %s\n", $this->xmlnodes[$j]['tag']);
        if ( $this->xmlnodes[$j]['tag'] == 'DAV::status' && $this->xmlnodes[$j]['value'] != 'HTTP/1.1 200 OK' ) return null;
      }
//      printf( "Node[$j]: %s\n", $this->xmlnodes[$j]['tag']);
      if ( $j > 0 && isset($this->xmlnodes[$j]['value']) ) {
//        printf( "Value[$j]: %s\n", $this->xmlnodes[$j]['value']);
        return rawurldecode($this->xmlnodes[$j]['value']);
      }
    }
    else {
      if ( $this->debug ) printf( "xmltags[$tagname] or xmltags[$tagname][$i] is not set\n");
    }
    return null;
  }


  /**
  * Return the href which has a resourcetype of the specified type
  *
  * @param string $tagname The tag name of the resourcetype to find the href for
  * @param integer $which Which instance of the tag should we use
  */
  function HrefForResourcetype( $tagname, $i = 0 ) {
    if ( isset($this->xmltags[$tagname]) && isset($this->xmltags[$tagname][$i]) ) {
      $j = $this->xmltags[$tagname][$i];
      while( $j-- > 0 && $this->xmlnodes[$j]['tag'] != 'DAV::resourcetype' );
      if ( $j > 0 ) {
        while( $j-- > 0 && $this->xmlnodes[$j]['tag'] != 'DAV::href' );
        if ( $j > 0 && isset($this->xmlnodes[$j]['value']) ) {
          return rawurldecode($this->xmlnodes[$j]['value']);
        }
      }
    }
    return null;
  }


  /**
  * Return the <prop> ... </prop> of a propstat where the status is OK
  *
  * @param string $nodenum The node number in the xmlnodes which is the href
  */
  function GetOKProps( $nodenum ) {
    $props = null;
    $level = $this->xmlnodes[$nodenum]['level'];
    $status = '';
    while ( $this->xmlnodes[++$nodenum]['level'] >= $level ) {
      if ( $this->xmlnodes[$nodenum]['tag'] == 'DAV::propstat' ) {
        if ( $this->xmlnodes[$nodenum]['type'] == 'open' ) {
          $props = array();
          $status = '';
        }
        else {
          if ( $status == 'HTTP/1.1 200 OK' ) break;
        }
      }
      elseif ( !isset($this->xmlnodes[$nodenum]) || !is_array($this->xmlnodes[$nodenum]) ) {
        break;
      }
      elseif ( $this->xmlnodes[$nodenum]['tag'] == 'DAV::status' ) {
        $status = $this->xmlnodes[$nodenum]['value'];
      }
      else {
        $props[] = $this->xmlnodes[$nodenum];
      }
    }
    return $props;
  }


  /**
  * Attack the given URL in an attempt to find a principal URL
  *
  * @param string $url The URL to find the principal-URL from
  */
  function FindPrincipal( $url=null ) {
    $xml = $this->DoPROPFINDRequest( $url, array('resourcetype', 'current-user-principal', 'owner', 'principal-URL',
                                  'urn:ietf:params:xml:ns:caldav:calendar-home-set'), 1);

    $principal_url = $this->HrefForProp('DAV::principal');

    if ( !isset($principal_url) ) {
      foreach( array('DAV::current-user-principal', 'DAV::principal-URL', 'DAV::owner') AS $href ) {
        if ( !isset($principal_url) ) {
          $principal_url = $this->HrefValueInside($href);
        }
      }
    }

    return $this->PrincipalURL($principal_url);
  }


  /**
  * Attack the given URL in an attempt to find a principal URL
  *
  * @param string $url The URL to find the calendar-home-set from
  */
  function FindCalendarHome( $recursed=false ) {
    if ( !isset($this->principal_url) ) {
      $this->FindPrincipal();
    }
    if ( $recursed ) {
      $this->DoPROPFINDRequest( $this->principal_url, array('urn:ietf:params:xml:ns:caldav:calendar-home-set'), 0);
    }

    $calendar_home = array();
    foreach( $this->xmltags['urn:ietf:params:xml:ns:caldav:calendar-home-set'] AS $k => $v ) {
      if ( $this->xmlnodes[$v]['type'] != 'open' ) continue;
      while( $this->xmlnodes[++$v]['type'] != 'close' && $this->xmlnodes[$v]['tag'] != 'urn:ietf:params:xml:ns:caldav:calendar-home-set' ) {
//        printf( "Tag: '%s' = '%s'\n", $this->xmlnodes[$v]['tag'], $this->xmlnodes[$v]['value']);
        if ( $this->xmlnodes[$v]['tag'] == 'DAV::href' && isset($this->xmlnodes[$v]['value']) )
          $calendar_home[] = rawurldecode($this->xmlnodes[$v]['value']);
      }
    }

    if ( !$recursed && count($calendar_home) < 1 ) {
      $calendar_home = $this->FindCalendarHome(true);
    }

    return $this->CalendarHomeSet($calendar_home);
  }


  /**
  * Find the calendars, from the calendar_home_set
  */
  function FindCalendars( $recursed=false ) {
    if ( !isset($this->calendar_home_set[0]) ) {
      $this->FindCalendarHome();
    }
    $this->DoPROPFINDRequest( $this->calendar_home_set[0], array('resourcetype','displayname','http://calendarserver.org/ns/:getctag'), 1);

    $calendars = array();
    if ( isset($this->xmltags['urn:ietf:params:xml:ns:caldav:calendar']) ) {
      $calendar_urls = array();
      foreach( $this->xmltags['urn:ietf:params:xml:ns:caldav:calendar'] AS $k => $v ) {
        $calendar_urls[$this->HrefForProp('urn:ietf:params:xml:ns:caldav:calendar', $k)] = 1;
      }

      foreach( $this->xmltags['DAV::href'] AS $i => $hnode ) {
        $href = rawurldecode($this->xmlnodes[$hnode]['value']);

        if ( !isset($calendar_urls[$href]) ) continue;

//        printf("Seems '%s' is a calendar.\n", $href );

        $calendar = new CalendarInfo($href);
        $ok_props = $this->GetOKProps($hnode);
        foreach( $ok_props AS $v ) {
//          printf("Looking at: %s[%s]\n", $href, $v['tag'] );
          switch( $v['tag'] ) {
            case 'http://calendarserver.org/ns/:getctag':
              $calendar->getctag = $v['value'];
              break;
            case 'DAV::displayname':
              $calendar->displayname = $v['value'];
              break;
          }
        }
        $calendars[] = $calendar;
      }
    }

    return $this->CalendarUrls($calendars);
  }


  /**
  * Find the calendars, from the calendar_home_set
  */
  function GetCalendarDetails( $url = null ) {
    if ( isset($url) ) $this->SetCalendar($url);

    $calendar_properties = array( 'resourcetype', 'displayname', 'http://calendarserver.org/ns/:getctag', 'urn:ietf:params:xml:ns:caldav:calendar-timezone', 'supported-report-set' );
    $this->DoPROPFINDRequest( $this->calendar_url, $calendar_properties, 0);

    $hnode = $this->xmltags['DAV::href'][0];
    $href = rawurldecode($this->xmlnodes[$hnode]['value']);

    $calendar = new CalendarInfo($href);
    $ok_props = $this->GetOKProps($hnode);
    foreach( $ok_props AS $k => $v ) {
      $name = preg_replace( '{^.*:}', '', $v['tag'] );
      if ( isset($v['value'] ) ) {
        $calendar->{$name} = $v['value'];
      }
/*      else {
        printf( "Calendar property '%s' has no text content\n", $v['tag'] );
      }*/
    }

    return $calendar;
  }


  /**
  * Get all etags for a calendar
  */
  function GetCollectionETags( $url = null ) {
    if ( isset($url) ) $this->SetCalendar($url);

    $this->DoPROPFINDRequest( $this->calendar_url, array('getetag'), 1);

    $etags = array();
    if ( isset($this->xmltags['DAV::getetag']) ) {
      foreach( $this->xmltags['DAV::getetag'] AS $k => $v ) {
        $href = $this->HrefForProp('DAV::getetag', $k);
        if ( isset($href) && isset($this->xmlnodes[$v]['value']) ) $etags[$href] = $this->xmlnodes[$v]['value'];
      }
    }

    return $etags;
  }


  /**
  * Get a bunch of events for a calendar with a calendar-multiget report
  */
  function CalendarMultiget( $event_hrefs, $url = null ) {

    if ( isset($url) ) $this->SetCalendar($url);

    $hrefs = '';
    foreach( $event_hrefs AS $k => $href ) {
      $href = str_replace( rawurlencode('/'),'/',rawurlencode($href));
      $hrefs .= '<href>'.$href.'</href>';
    }
    $this->body = <<<EOXML
<?xml version="1.0" encoding="utf-8" ?>
<C:calendar-multiget xmlns="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
<prop><getetag/><C:calendar-data/></prop>
$hrefs
</C:calendar-multiget>
EOXML;

    $this->requestMethod = "REPORT";
    $this->SetContentType("text/xml");
    $this->DoRequest( $this->calendar_url );

    $events = array();
    if ( isset($this->xmltags['urn:ietf:params:xml:ns:caldav:calendar-data']) ) {
      foreach( $this->xmltags['urn:ietf:params:xml:ns:caldav:calendar-data'] AS $k => $v ) {
        $href = $this->HrefForProp('urn:ietf:params:xml:ns:caldav:calendar-data', $k);
//        echo "Calendar-data:\n"; print_r($this->xmlnodes[$v]);
        $events[$href] = $this->xmlnodes[$v]['value'];
      }
    }
    else {
      foreach( $event_hrefs AS $k => $href ) {
        $this->DoGETRequest($href);
        $events[$href] = $this->httpResponseBody;
      }
    }

    return $events;
  }


  /**
  * Given XML for a calendar query, return an array of the events (/todos) in the
  * response.  Each event in the array will have a 'href', 'etag' and '$response_type'
  * part, where the 'href' is relative to the calendar and the '$response_type' contains the
  * definition of the calendar data in iCalendar format.
  *
  * @param string $filter XML fragment which is the <filter> element of a calendar-query
  * @param string $url The URL of the calendar, or empty/null to use the 'current' calendar_url
  *
  * @return array An array of the relative URLs, etags, and events from the server.  Each element of the array will
  *               be an array with 'href', 'etag' and 'data' elements, corresponding to the URL, the server-supplied
  *               etag (which only varies when the data changes) and the calendar data in iCalendar format.
  */
  function DoCalendarQuery( $filter, $url = '' ) {

    if ( !empty($url) ) $this->SetCalendar($url);

    $this->body = <<<EOXML
<?xml version="1.0" encoding="utf-8" ?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
  <D:prop>
    <C:calendar-data/>
    <D:getetag/>
  </D:prop>$filter
</C:calendar-query>
EOXML;

    $this->requestMethod = "REPORT";
    $this->SetContentType("text/xml");
    $this->DoRequest( $this->calendar_url );

    $report = array();
    foreach( $this->xmlnodes as $k => $v ) {
      switch( $v['tag'] ) {
        case 'DAV::response':
          if ( $v['type'] == 'open' ) {
            $response = array();
          }
          elseif ( $v['type'] == 'close' ) {
            $report[] = $response;
          }
          break;
        case 'DAV::href':
          $response['href'] = basename( rawurldecode($v['value']) );
          break;
        case 'DAV::getetag':
          $response['etag'] = preg_replace('/^"?([^"]+)"?/', '$1', $v['value']);
          break;
        case 'urn:ietf:params:xml:ns:caldav:calendar-data':
          $response['data'] = $v['value'];
          break;
      }
    }
    return $report;
  }


  /**
  * Get the events in a range from $start to $finish.  The dates should be in the
  * format yyyymmddThhmmssZ and should be in GMT.  The events are returned as an
  * array of event arrays.  Each event array will have a 'href', 'etag' and 'event'
  * part, where the 'href' is relative to the calendar and the event contains the
  * definition of the event in iCalendar format.
  *
  * @param timestamp $start The start time for the period
  * @param timestamp $finish The finish time for the period
  * @param string    $relative_url The URL relative to the base_url specified when the calendar was opened.  Default ''.
  *
  * @return array An array of the relative URLs, etags, and events, returned from DoCalendarQuery() @see DoCalendarQuery()
  */
  function GetEvents( $start = null, $finish = null, $relative_url = '' ) {
    $filter = "";
    if ( isset($start) && isset($finish) )
        $range = "<C:time-range start=\"$start\" end=\"$finish\"/>";
    else
        $range = '';

    $filter = <<<EOFILTER
  <C:filter>
    <C:comp-filter name="VCALENDAR">
      <C:comp-filter name="VEVENT">
        $range
      </C:comp-filter>
    </C:comp-filter>
  </C:filter>
EOFILTER;

    return $this->DoCalendarQuery($filter, $relative_url);
  }


  /**
  * Get the todo's in a range from $start to $finish.  The dates should be in the
  * format yyyymmddThhmmssZ and should be in GMT.  The events are returned as an
  * array of event arrays.  Each event array will have a 'href', 'etag' and 'event'
  * part, where the 'href' is relative to the calendar and the event contains the
  * definition of the event in iCalendar format.
  *
  * @param timestamp $start The start time for the period
  * @param timestamp $finish The finish time for the period
  * @param boolean   $completed Whether to include completed tasks
  * @param boolean   $cancelled Whether to include cancelled tasks
  * @param string    $relative_url The URL relative to the base_url specified when the calendar was opened.  Default ''.
  *
  * @return array An array of the relative URLs, etags, and events, returned from DoCalendarQuery() @see DoCalendarQuery()
  */
  function GetTodos( $start, $finish, $completed = false, $cancelled = false, $relative_url = "" ) {

    if ( $start && $finish ) {
$time_range = <<<EOTIME
                <C:time-range start="$start" end="$finish"/>
EOTIME;
    }

    // Warning!  May contain traces of double negatives...
    $neg_cancelled = ( $cancelled === true ? "no" : "yes" );
    $neg_completed = ( $cancelled === true ? "no" : "yes" );

    $filter = <<<EOFILTER
  <C:filter>
    <C:comp-filter name="VCALENDAR">
          <C:comp-filter name="VTODO">
                <C:prop-filter name="STATUS">
                        <C:text-match negate-condition="$neg_completed">COMPLETED</C:text-match>
                </C:prop-filter>
                <C:prop-filter name="STATUS">
                        <C:text-match negate-condition="$neg_cancelled">CANCELLED</C:text-match>
                </C:prop-filter>$time_range
          </C:comp-filter>
    </C:comp-filter>
  </C:filter>
EOFILTER;

    return $this->DoCalendarQuery($filter, $relative_url);
  }


  /**
  * Get the calendar entry by UID
  *
  * @param uid
  * @param string    $relative_url The URL relative to the base_url specified when the calendar was opened.  Default ''.
  * @param string    $component_type The component type inside the VCALENDAR.  Default 'VEVENT'.
  *
  * @return array An array of the relative URL, etag, and calendar data returned from DoCalendarQuery() @see DoCalendarQuery()
  */
  function GetEntryByUid( $uid, $relative_url = '', $component_type = 'VEVENT' ) {
    $filter = "";
    if ( $uid ) {
      $filter = <<<EOFILTER
  <C:filter>
    <C:comp-filter name="VCALENDAR">
          <C:comp-filter name="$component_type">
                <C:prop-filter name="UID">
                        <C:text-match icollation="i;octet">$uid</C:text-match>
                </C:prop-filter>
          </C:comp-filter>
    </C:comp-filter>
  </C:filter>
EOFILTER;
    }

    return $this->DoCalendarQuery($filter, $relative_url);
  }


  /**
  * Get the calendar entry by HREF
  *
  * @param string    $href         The href from a call to GetEvents or GetTodos etc.
  *
  * @return string The iCalendar of the calendar entry
  */
  function GetEntryByHref( $href ) {
    $href = str_replace( rawurlencode('/'),'/',rawurlencode($href));
    return $this->DoGETRequest( $href );
  }

}
