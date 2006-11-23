<?php
/**
 * XML feed export
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/common.php');
require_once(DOKU_INC.'inc/events.php');
require_once(DOKU_INC.'inc/parserutils.php');
require_once(DOKU_INC.'inc/feedcreator.class.php');
require_once(DOKU_INC.'inc/auth.php');
require_once(DOKU_INC.'inc/pageutils.php');
require_once(DOKU_PLUGIN.'blog/inc/recent.php');

//close session
session_write_close();

$num   = $_REQUEST['num'];
$type  = $_REQUEST['type'];
$ns    = $_REQUEST['ns'];
$title = $_REQUEST['title'];

if($type == '')
  $type = $conf['rss_type'];

switch ($type){
  case 'rss':
    $type = 'RSS0.91';
    $mime = 'text/xml';
    break;
  case 'rss2':
    $type = 'RSS2.0';
    $mime = 'text/xml';
    break;
  case 'atom':
    $type = 'ATOM0.3';
    $mime = 'application/xml';
    break;
  case 'atom1':
    $type = 'ATOM1.0';
    $mime = 'application/atom+xml';
    break;
  default:
    $type = 'RSS1.0';
    $mime = 'application/xml';
}

// the feed is dynamic - we need a cache for each combo
// (but most people just use the default feed so it's still effective)
$cache = getCacheName('blog'.$num.$type.$ns.$_SERVER['REMOTE_USER'],'.feed');

// check cacheage and deliver if nothing has changed since last
// time or the update interval has not passed, also handles conditional requests
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Type: application/xml; charset=utf-8');
$cmod = @filemtime($cache); // 0 if not exists
if ($cmod &&
  (($cmod + $conf['rss_update'] > time()) || ($cmod > @filemtime($conf['changelog'])))){
  http_conditionalRequest($cmod);
  if($conf['allowdebug']) header("X-CacheUsed: $cache");
  print io_readFile($cache);
  exit;
} else {
  http_conditionalRequest(time());
}

// create new feed
$rss = new DokuWikiFeedCreator();
$rss->title = $title.' '.(($ns) ? ' '.ucwords($ns) : '').' · '.$conf['title'];
$rss->link  = DOKU_URL;
$rss->syndicationURL = DOKU_URL.'lib/plugins/blog/feed.php';
$rss->cssStyleSheet  = DOKU_URL.'lib/styles/feed.css';

$image = new FeedImage();
$image->title = $conf['title'];
$image->url = DOKU_URL."lib/images/favicon.ico";
$image->link = DOKU_URL;
$rss->image = $image;

rssBlogEntries($rss, $num, $ns);

$feed = $rss->createFeed($type, 'utf-8');

// save cachefile
io_saveFile($cache, $feed);

// finally deliver
print $feed;

/* ---------- */

/**
 * Add blog entries to feed object
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Esther Brunner <wikidesign@gmail.com>
 */
function rssBlogEntries(&$rss, $num, $ns){
  global $conf;
  
  if (!$num) $num = $conf['recent'];
  
  // get the blog entries for our namespace
  $recent = new plugin_class_recent;
  $entries = array_slice($recent->_getBlogEntries($ns), 0, $num);
 
  foreach ($entries as $entry){
    $item = new FeedItem();
    $meta = p_get_metadata($entry['id']);

    if ($meta['title']) $item->title = $meta['title'];
    else $item->title = ucwords($entry['id']);

    $item->link = wl($entry['id'], '', true);

    $item->description = htmlspecialchars($meta['description']['abstract']);
    $item->date        = date('r', $entry['date']);
    if ($meta['subject']){
      if (is_array($meta['subject'])) $item->category = $meta['subject'][0];
      else $item->category = $meta['subject'];
    }
    $item->author = $meta['creator'];

    $rss->addItem($item);
  }
}

//Setup VIM: ex: et ts=2 enc=utf-8 :
