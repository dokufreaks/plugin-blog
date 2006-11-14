<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class plugin_class_recent extends DokuWiki_Plugin {

  /**
   * Plugin needs to tell its name. Important for settings and localized strings!
   */
  function getPluginName(){
    return 'blog';
  }
  
  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2006-11-06',
      'name'   => 'Blog Plugin (recent class)',
      'desc'   => 'Displays a number of recent entries from a given namesspace',
      'url'    => 'http://wiki.splitbrain.org/plugin:blog',
    );
  }

  /**
   * Get blog entries from a given namespace
   */
  function _getBlogEntries($ns){
    global $conf;
    
    // load page and creation date index
    $page_idx  = file($conf['cachedir'].'/page.idx');
    if (!@file_exists($conf['cachedir'].'/cdate.idx')) $cdate_idx = array();
    else $cdate_idx = file($conf['cachedir'].'/cdate.idx');
    
    // add pages in given namespace
    $sortkey = $this->getConf('sortkey');
    $result  = array();
    $c       = count($page_idx);
    for ($i = 0; $i < $c; $i++){
      $id = substr($page_idx[$i], 0, -1);
      
      // do some checks first
      if (isHiddenPage($id)) continue;                     // skip excluded pages
      if (($ns) && (strpos($id, $ns.':') !== 0)) continue; // filter namespaces
      if (!@file_exists(wikiFN($id))) continue;            // skip deleted
      $perm = auth_quickaclcheck($id);
      if ($perm < AUTH_READ) continue;                     // check ACL
                
      // okay, add the page
      $cdate = substr($cdate_idx[$i], 0, -1);
      if (!$cdate) $cdate = $this->_getCDate($id, $i, $cdate_idx);
      switch ($sortkey){
        case 'id':
          $key = $id;
          break;
        case 'pagename':
          $key = noNS($id);
          break;
        default:
          $key = $cdate;
      }
      $result[$key] = array(
        'id'     => $id,
        'date'   => $cdate,
        'exists' => true,
        'perm'   => $perm,
      );
    }
    
    // finally sort by sort key
    krsort($result);
          
    return $result;
  }
  
  /**
   * Get the creation date of a page from metadata or filectime
   */
  function _getCDate($id, $pid, &$idx){
    global $conf;
    
    $cdate = p_get_metadata($id, 'date created');
    if (!$cdate) $cdate = filectime(wikiFN($id));
    
    // check lines and fill creation date in
    for ($i = 0; $i < $pid; $i++){
      if (empty($idx[$i])) $idx[$i] = "\n";
    }
    $idx[$pid] = "$cdate\n";
    
    // save creation date index
    $fh = fopen($conf['cachedir'].'/cdate.idx', 'w');
    if (!$fh) return false;
    fwrite($fh, join('', $idx));
    fclose($fh);
    
    return $cdate;
  }
  
}
  
//Setup VIM: ex: et ts=4 enc=utf-8 :
