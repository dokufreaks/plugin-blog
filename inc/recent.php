<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class plugin_class_recent extends DokuWiki_Plugin {

  var $idx_dir    = '';      // directory for index files
  var $page_idx   = array(); // array of existing pages
  var $cdate_idx  = array(); // array of creation dates of pages
  
  /**
   * Constructor
   */
  function plugin_class_recent(){
    global $conf;
    
    // determine where index files are saved
    if (@file_exists($conf['indexdir'].'/cdate.idx')){ // new word length based index
      $this->idx_dir = $conf['indexdir'];
      if (!@file_exists($this->idx_dir.'cdate.idx')) $this->_importCDateIndex();
    } else {                                          // old index
      $this->idx_dir = $conf['cachedir'];
    }
    
    // load page and creation date index
    $this->page_idx  = file($this->idx_dir.'/page.idx');
    $this->cdate_idx = @file($this->idx_dir.'/cdate.idx');
  }

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
      'date'   => '2006-12-03',
      'name'   => 'Blog Plugin (recent class)',
      'desc'   => 'Displays a number of recent entries from a given namesspace',
      'url'    => 'http://www.wikidesign.ch/en/plugin/blog/start',
    );
  }

  /**
   * Get blog entries from a given namespace
   */
  function _getBlogEntries($ns){
    
    // add pages in given namespace
    $sortkey = $this->getConf('sortkey');
    $result  = array();
    $c       = count($this->page_idx);
    for ($i = 0; $i < $c; $i++){
      $id = substr($this->page_idx[$i], 0, -1);
      
      // do some checks first
      if (isHiddenPage($id)) continue;                     // skip excluded pages
      if (($ns) && (strpos($id, $ns.':') !== 0)) continue; // filter namespaces
      if (!@file_exists(wikiFN($id))) continue;            // skip deleted
      $perm = auth_quickaclcheck($id);
      if ($perm < AUTH_READ) continue;                     // check ACL
                
      // okay, add the page
      $cdate = substr($this->cdate_idx[$i], 0, -1);
      if (!$cdate) $cdate = $this->_getCDate($id, $i);
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
  function _getCDate($id, $pid){
    
    $cdate = p_get_metadata($id, 'date created');
    if (!$cdate) $cdate = filectime(wikiFN($id));
    
    // check lines and fill creation date in
    for ($i = 0; $i < $pid; $i++){
      if (empty($this->cdate_idx[$i])) $this->cdate_idx[$i] = "\n";
    }
    $this->cdate_idx[$pid] = "$cdate\n";
    
    // save creation date index
    $fh = fopen($this->idx_dir.'/cdate.idx', 'w');
    if (!$fh) return false;
    fwrite($fh, join('', $this->cdate_idx));
    fclose($fh);
    
    return $cdate;
  }
  
  /**
   * Update creation date index
   */
  function _updateCDateIndex($id, $date){
  
    // get page id (this is the linenumber in page.idx)
    $pid = array_search("$id\n", $this->page_idx);
    if (!is_int($pid)){
      $this->page_idx[] = "$id\n";
      $pid = count($this->page_idx) - 1;
      // page was new - write back
      $this->_saveIndex('page');
    }
    
    // check lines and fill creation date in
    for ($i = 0; $i < $pid; $i++){
      if (empty($this->cdate_idx[$i])) $this->cdate_idx[$i] = "\n";
    }
    $this->cdate_idx[$pid] = "$date\n";    
    
    // save creation date index
    $this->_saveIndex('cdate');
  }
  
  /**
   * Save creation date or page index
   */
  function _saveIndex($idx = 'cdate'){
    $fh = fopen($this->idx_dir.'/'.$idx.'.idx', 'w');
    if (!$fh) return false;
    if ($id == 'page') fwrite($fh, join('', $this->page_idx));
    else fwrite($fh, join('', $this->cdate_idx));
    fclose($fh);
  }
  
  /**
   * Import old creation date index
   */
  function _importCDateIndex(){
    global $conf;
    
    $old = $conf['cachedir'].'/cdate.idx';
    $new = $conf['indexdir'].'/cdate.idx';
    
    if (@file_exists($old)) return false;
        
    if (@copy($old, $new)){
      @unlink($old);
      return true;
    }
    return false;
  }
  
}
  
//Setup VIM: ex: et ts=4 enc=utf-8 :
