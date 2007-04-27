<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class helper_plugin_blog extends DokuWiki_Plugin {

  var $idx_dir    = '';      // directory for index files
  var $page_idx   = array(); // array of existing pages
  var $cdate_idx  = array(); // array of creation dates of pages
  
  /**
   * Constructor
   */
  function helper_plugin_blog(){
    global $conf;
    
    // determine where index files are saved
    if (@file_exists($conf['indexdir'].'/page.idx')){ // new word length based index
      $this->idx_dir = $conf['indexdir'];
      if (!@file_exists($this->idx_dir.'/cdate.idx')) $this->_importCDateIndex();
    } else {                                          // old index
      $this->idx_dir = $conf['cachedir'];
    }
    
    // load page and creation date index
    $this->page_idx  = @file($this->idx_dir.'/page.idx');
    $this->cdate_idx = @file($this->idx_dir.'/cdate.idx');
  }
  
  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2007-04-27',
      'name'   => 'Blog Plugin (helper class)',
      'desc'   => 'Returns a number of recent entries from a given namespace',
      'url'    => 'http://www.wikidesign.ch/en/plugin/blog/start',
    );
  }
  
  function getMethods(){
    $result = array();
    $result[] = array(
      'name'   => 'getBlog',
      'desc'   => 'returns blog entries in reverse chronological order',
      'params' => array(
        'namespace' => 'string',
        'number (optional)' => 'integer'),
      'return' => array('pages' => 'array'),
    );
    return $result;
  }

  /**
   * Get blog entries from a given namespace
   */
  function getBlog($ns, $num = NULL){
    
    // add pages in given namespace
    $sortkey = $this->getConf('recent_sortkey');
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
      $draft = $this->_isDraft($id);
      if (($perm < AUTH_ADMIN) && $draft) continue;        // skip drafts unless for admins
                
      // okay, add the page
      $cdate = substr($this->cdate_idx[$i], 0, -1);
      if (!$cdate) $cdate = $this->_getCDate($id, $i);
      
      // determine array key
      if ($sortkey == 'id') $key = $id;
      elseif ($sortkey == 'pagename') $key = noNS($id);
      else $key = $cdate;
      
      $result[$key] = array(
        'id'       => $id,
        'date'     => $cdate,
        'exists'   => true,
        'perm'     => $perm,
        'draft'    => $draft,
      );
    }
    
    // finally sort by sort key
    krsort($result);
    
    if (is_numeric($num)) $result = array_slice($result, 0, $num);
          
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
    if ($idx == 'page') fwrite($fh, join('', $this->page_idx));
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
    
    if (!@file_exists($old)) return false;
        
    if (@copy($old, $new)){
      @unlink($old);
      return true;
    }
    return false;
  }
  
  /**
   * Check whether the blog entry is marked as draft
   */
  function _isDraft($id){
    $type = p_get_metadata($id, 'type');
    return ($type == 'draft');
  }
  
}
  
//Setup VIM: ex: et ts=4 enc=utf-8 :
