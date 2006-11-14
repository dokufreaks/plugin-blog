<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_blog extends DokuWiki_Action_Plugin {

  /**
   * return some info
   */
  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2006-11-05',
      'name'   => 'Blog Plugin',
      'desc'   => 'Brings blog functionality to DokuWiki',
      'url'    => 'http://wiki:splitbrain.org/plugin:blog',
    );
  }

  /**
   * register the eventhandlers
   */
  function register(&$contr){
    $contr->register_hook('ACTION_ACT_PREPROCESS',
                          'BEFORE',
                          $this,
                          'handle_act_preprocess',
                           array());
                              

    $contr->register_hook('IO_WIKIPAGE_WRITE',
                          'AFTER',
                          $this,
                          'cdateIndex',
                          array());
  }

  /**
   * Update the creation date index the blog plugin uses
   *
   * @author  Esther Brunner  <wikidesign@gmail.com>
   */
  function cdateIndex(&$event, $param){
    global $INFO;
    global $conf;
    
    if ($event->data[3]) return false;     // old revision saved
    if ($INFO['exists']) return false;     // file not new
    if (!$event->data[0][1]) return false; // file is empty
    
    // get needed information
    $id   = ($event->data[1] ? $event->data[1].':' : '').$event->data[2];
    $date = filectime($event->data[0][0]);
    
    // index files
    $page_file  = $conf['cachedir'].'/page.idx';
    $cdate_file = $conf['cachedir'].'/cdate.idx';
        
    // load indexes
    $page_idx  = file($page_file);
    if (!@file_exists($cdate_file)) $cdate_idx = array();
    else $cdate_idx = file($cdate_file);
    
    // get page id (this is the linenumber in page.idx)
    $pid = array_search("$id\n", $page_idx);
    if (!is_int($pid)){
      $page_idx[] = "$id\n";
      $pid = count($page_idx)-1;
      // page was new - write back
      $fh = fopen($page_file, 'w');
      if (!$fh) return false;
      fwrite($fh, join('', $page_idx));
      fclose($fh);
    }
    
    // check lines and fill creation date in
    for ($i = 0; $i < $pid; $i++){
      if (empty($cdate_idx[$i])) $cdate_idx[$i] = "\n";
    }
    $cdate_idx[$pid] = "$date\n";    
    
    // save creation date index
    $fh = fopen($cdate_file, 'w');
    if (!$fh) return false;
    fwrite($fh, join('', $cdate_idx));
    fclose($fh);
    
    return true;
  }
  
  /**
   * Checks if 'newentry' was given as action, if so we
   * do handle the event our self and no further checking takes place
   */
  function handle_act_preprocess(&$event, $param){
    if ($event->data != 'newentry') return; // nothing to do for us
    
    global $ACT;
    global $ID;

    // we can handle it -> prevent others
    $event->stopPropagation();
    $event->preventDefault();
    
    $ns    = $_REQUEST['ns'];
    $title = str_replace(':', '', $_REQUEST['title']);
    
    // season handling by Michael Arlt <michael.arlt@sk-chwanstetten.de>
    $dateprefix  = $this->getConf('dateprefix');
    if (substr($dateprefix, 0, 1) == '<') {
      // <9?%y1-%y2:%d.%m._   ->  05-06:31.08._ | 06-07:01.09._
      list($newmonth, $dateprefix) = explode('?', substr($dateprefix, 1));
      if (intval(strftime("%m")) < intval($newmonth)){
        $longyear2 = strftime("%Y");
        $longyear1 = $longyear2 - 1;
      } else {
        $longyear1 = strftime("%Y");
        $longyear2 = $longyear1 + 1;
      }
      $shortyear1 = substr($longyear1, 2);
      $shortyear2 = substr($longyear2, 2);
      $dateprefix = str_replace(
        array('%Y1', '%Y2', '%y1', '%y2'),
        array($longyear1, $longyear2, $shortyear1, $shortyear2),
        $dateprefix
      );
    }
    $pre   = strftime($dateprefix);
    $id    = ($ns ? $ns.':' : '').$pre.cleanID($title);
    
    // check if we are allowed to create this file
    if (auth_quickaclcheck($id) >= AUTH_CREATE){
      $back = $ID;
      $ID   = $id;
      $file = wikiFN($ID);
      
      //check if locked by anyone - if not lock for my self      
      if (checklock($ID)){
        $ACT = 'locked';
      } else {
        lock($ID);
      }

      // prepare the new thread file with default stuff
      if (!@file_exists($file)){
        global $TEXT;
        global $INFO;
        global $conf;
        
        $TEXT = pageTemplate(array($ns.':'.$title));
        if (!$TEXT) $TEXT = "====== $title ======\n\n\n\n".
                            "~~DISCUSSION~~\n";
        $ACT = 'preview';
      } else {
        $ACT = 'edit';
      }
    } else {
      $ACT = 'show';
    }
  }

}

//Setup VIM: ex: et ts=4 enc=utf-8 :
