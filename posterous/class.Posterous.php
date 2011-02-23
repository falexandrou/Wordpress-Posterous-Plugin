<?php
/***********
 * Posterous Class
 * @author Fotis Alexandrou - fotis@redmark.gr
 * Published under GPL License
 * 
 */

class Posterous{
    protected $url;
    protected $site_id;
    protected $email;
    private $pass;
    protected $current_tag;
    protected $num_posts;
    protected $page;
    protected $date_format;
    protected $tags;

    public function __construct($url, $uname, $pass, $num_posts='10', $date_format='d-m-Y') {
	$this->url = $url;
	$this->email = $uname;
	$this->pass = $pass;
	$this->page = (int)$_GET['ppg'];
	if ($this->page <= 0)
	    $this->page = 1;
	$this->num_posts = $num_posts;
	$this->date_format = $date_format;
	$this->site_id = $this->getSiteID();
	$this->tags = $this->getTags();
	$tag = (int)$_GET['ptg'];
	if ($tag<=0)
	    $this->current_tag = null;
	else
	    $this->current_tag = $this->getTagName($tag);
    }

    function getTags(){
	$site_id = $this->site_id;
	$response = $this->getResponse('http://posterous.com/api/gettags', array('site_id'=>$site_id));
	if ($response == null)
	    return array();
	$res = simplexml_load_string($response);

	$tags = array();

	foreach($res->tag as $tag){
	    $tags[] = array('id'=>(int)$tag->id, 'tag_string'=>(string)$tag->tag_string, 'count'=>(int)$tag->count);
	}

	return $tags;
    }

    function getTagCount($str){
	$tags = $this->tags;
	foreach ($tags as $tag){
	    if ((string)$tag['tag_string']==(string)$str){
		return (int)$tag['count'];
	    }
	}
    }

    function getTagName($id){
	$tags = $this->tags;
	foreach ($tags as $tag){
	    if ((int)$tag['id']==(int)$id){
		return $tag['tag_string'];
	    }
	}
    }

    function getPages(){
	$posterous = $this->url;
	if ($this->current_tag!=null){
	    $opts = array('tag'=>$this->current_tag);
	    $num_posts = (int)$this->getTagCount($this->current_tag);
	}else{
	    $sites = $this->getResponse('http://posterous.com/api/getsites', $opts);
	    $xml = simplexml_load_string($sites);
	    $id = $xml->xpath('/rsp/site[url="' . $posterous . '"]');
	    $node = $id[0];
	    $num_posts = (int)$node->num_posts;
	}

	$perpage = $this->num_posts;
	$total = $num_posts/(int)$perpage;
	$round = round((double)$total);
	
	if ($total>$round)
	    $round++;
	
	$total = $round;
	return $total;
    }

    function getPagination(){
	$page = $this->page;
	$total = $this->getPages();
	$html = "<div class='posterous_pagination'>\n";

	for ($i=1; $i<=$total; $i++){
	    $html .= "\t<span class='item'>";

	    if ($i==$page)
		$html .= "<strong>$i</strong>";
	    else
		$html .= "<a href=\"" . $this->changeUrl('ppg') . "ppg=$i\">$i</a>";
	    
	    $html .= "</span>\n";
	}

	$html .= "</div>\n";
	return $html;
    }

    function getTagLink($tag){
	$tags = $this->tags;
	if (empty($tags) || !is_array($tags))
	    return;

	foreach ($tags as $t){
	    if ((string)$t['tag_string']==(string)$tag){
		return '<a href="' . $this->changeUrl('ptg') . 'ptg=' . $t['id'] . '">' . $t['tag_string'] . '</a>';
	    }
	}
	return;
    }

    function getPosts($strip = true){
	$posterous = $this->url;
	$page = $this->page;
	$site_id = $this->site_id;
	$num_posts = $this->num_posts;
	
	$tag = $this->current_tag;
	$dateFormat = $this->date_format;

	$opts = array('site_id'=>$site_id, 'num_posts'=>$num_posts, 'page'=>$page);
	
	if ($tag!=null)
	    $opts['tag'] = $tag;

	$res = $this->getResponse('http://posterous.com/api/readposts', $opts);
	if ($res == null)
		return array();
	$res = simplexml_load_string($res);
	$posts = array();

	$tags = $this->tags;
	foreach ($res->post as $post){
	    $title = (string)$post->title;
	    $link = (string)$post->link;
	    $short = (string)$post->url;
	    $content = (string)$post->body;
	    $content = preg_replace('/\<\!\[CDATA\[(.*)\]\]/iUs', '', $content);
	    $date = date($dateFormat, strtotime((string)$post->date));
	    $views = (int)$post->views;
	    $author = (string)$post->author;
	    $pic = (string)$post->authorpic;
	    $comments = (string)$post->commentsCount;
	    //Remove duplicate line changes
	    $content = str_replace("\n\n", "", $content);
	    $taglinks = '';
	    $thetags = array();
	    if ($post->tag != null){
		foreach ($post->tag as $ptag){
		    $thetags[] = (string)$ptag;
		}
	    }
	    $links = array();
	    if (!empty($thetags)){
		foreach ($thetags as $tag){
		    $links[] = $this->getTagLink($tag, $tags);
		}
	    }
	    $lc = count($links);

	    for($i=0; $i<$lc; $i++){
		$taglinks .= $links[$i];
		if ($i<($lc-1))
		    $taglinks .= ', ';
	    }

	    if ($strip == true)
		$content = strip_selected_tags($content, array('div', 'blockquote'));
	    //TODO: Add images, videos, audio and Comments
	    $posts[] = array('title'=>$title, 'link'=>$link, 'url'=>$short, 'content'=>$content, 'date'=>$date,
			    'views'=>$views, 'author'=>$author, 'authorpic'=>$pic, 'comments'=>$comments,
			    'tags'=>$taglinks);
	}
	return $posts;
    }
    
    function getResponse($url, $args = array()){
	$username = $this->email;
	$pass = $this->pass;

	$curl = new CURL($url);
	$curl->setName($username);
	$curl->setPass($pass);
	$argline = '';
	$i = 0;

	if (is_array($args) && !empty($args)){
	    while(list($key, $value) = each($args)){
		if ($i>0)
		    $argline = '&';
		$argline = $key.'='.$value;
		$i++;
	    }
	}
	
	$response = $curl->createCurl($url .'?'. $argline);

	$status = $curl->getHttpStatus();

	if ($status == '401' || $status == '403')
	    echo 'Problem with posterous Auth. Please check your credentials';
	elseif ($status!='200')
	    echo 'Problem with Posterous API server. Please try again later';
	return $response;
    }

    function getSiteID(){
	$posterous = $this->url;
	$sites = $this->getResponse('http://posterous.com/api/getsites');
	$xml = simplexml_load_string($sites);
	if (!is_object($xml) || $xml == null)
	    return;
	$id = $xml->xpath('/rsp/site[url="' . $posterous . '"]');
	$node = $id[0];
	return (int)$node->id;
    }

    public function changeUrl($excludeKey=''){
	    $args = $_SERVER['argv'][0];
	    $args = explode("&", $args);
	    $arguments = array();
	    for ($i=0; $i<count($args); $i++){
		    $arg = $args[$i];
		    $arg = explode("=", $arg);
		    $key = $arg[0];
		    $val = $arg[1];
		    $arguments[$key] = $val;
	    }
	    if (!array_key_exists($excludeKey, $arguments)){
		    $str = http_build_query($arguments);
		    if ($str == '')
			    $str = "?";
		    else
			    $str = "?" . $str . "&";
		    return $str;
	    }

	    $final = array();
	    while (list($key, $value) = each($arguments)){
		    if ($key!=$excludeKey)
			    $final[$key] = $value;
	    }

	    $str = http_build_query($final);

	    if ($str == '')
		    $str = "?";
	    else
		    $str = "?" . $str . "&";

	    return $str;
    }
}

//Strip Selected Tags From HTML snippet - found on PHP.net User Comments
function strip_selected_tags($str, $tags = array()) {
	if(!is_array($tags)) {
        $tags = (strpos($str, '>') !== false ? explode('>', str_replace('<', '', $tags)) : array($tags));
        if(end($tags) == '') array_pop($tags);
    }
    foreach($tags as $tag) $str = preg_replace('#</?'.$tag.'[^>]*>#is', '', $str);
    return $str;
}

//Curl Class found on PHP.net User Comments
class CURL {
     protected $_useragent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)';
     protected $_url;
     protected $_followlocation;
     protected $_timeout;
     protected $_maxRedirects;
     protected $_cookieFileLocation = './cookie.txt';
     protected $_post;
     protected $_postFields;
     protected $_referer ="http://www.google.com";

     protected $_session;
     protected $_webpage;
     protected $_includeHeader;
     protected $_noBody;
     protected $_status;
     protected $_binaryTransfer;
     public    $authentication = 1;
     public    $auth_name      = '';
     public    $auth_pass      = '';

     public function useAuth($use){
       $this->authentication = 0;
       if($use == true) $this->authentication = 1;
     }

     public function setName($name){
       $this->auth_name = $name;
     }
     public function setPass($pass){
       $this->auth_pass = $pass;
     }

     public function __construct($url,$followlocation = true,$timeOut = 30,$maxRedirecs = 4,$binaryTransfer = false,$includeHeader = false,$noBody = false) {
         $this->_url = $url;
         $this->_followlocation = $followlocation;
         $this->_timeout = $timeOut;
         $this->_maxRedirects = $maxRedirecs;
         $this->_noBody = $noBody;
         $this->_includeHeader = $includeHeader;
         $this->_binaryTransfer = $binaryTransfer;

         $this->_cookieFileLocation = dirname(__FILE__).'/cookie.txt';

     }

     public function setReferer($referer){
       $this->_referer = $referer;
     }

     public function setCookiFileLocation($path){
         $this->_cookieFileLocation = $path;
     }

     public function setPost ($postFields){
        $this->_post = true;
        $this->_postFields = $postFields;
     }

     public function setUserAgent($userAgent){
         $this->_useragent = $userAgent;
     }

     public function createCurl($url = null){
        if($url != null){
          $this->_url = $url;
        }

         $s = curl_init();

         curl_setopt($s,CURLOPT_URL,$this->_url);
         //curl_setopt($s,CURLOPT_HTTPHEADER,array('Expect:'));
         curl_setopt($s,CURLOPT_TIMEOUT,$this->_timeout);
         curl_setopt($s,CURLOPT_MAXREDIRS,$this->_maxRedirects);
         curl_setopt($s,CURLOPT_RETURNTRANSFER,true);
         //curl_setopt($s,CURLOPT_FOLLOWLOCATION,$this->_followlocation);
         curl_setopt($s,CURLOPT_COOKIEJAR,$this->_cookieFileLocation);
         curl_setopt($s,CURLOPT_COOKIEFILE,$this->_cookieFileLocation);

         //if($this->authentication == 1){
           curl_setopt($s, CURLOPT_USERPWD, $this->auth_name.':'.$this->auth_pass);
         //}
         if($this->_post){
             curl_setopt($s,CURLOPT_POST,true);
             curl_setopt($s,CURLOPT_POSTFIELDS,$this->_postFields);

         }

         $this->_webpage = curl_exec($s);
         $this->_status = curl_getinfo($s,CURLINFO_HTTP_CODE);
         curl_close($s);
		return (string)$this->_webpage;
     }


   public function getHttpStatus(){
       return $this->_status;
   }

   public function contents(){
      return (string)$this->_webpage;
   }
}