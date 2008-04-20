<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class helper_plugin_blog extends DokuWiki_Plugin {

  var $sort       = '';      // sort key
  var $idx_type   = 'cdate'; // creation of modification date?
  var $idx_dir    = '';      // directory for index files
  var $page_idx   = array(); // array of existing pages
  var $date_idx   = array(); // array of creation dates of pages
  
  /**
   * Constructor
   */
  function helper_plugin_blog(){
    global $conf;
    
    // load sort key from settings
    $this->sort = $this->getConf('sortkey');
    if ($this->sort == 'mdate') $this->idx_type = 'mdate';
    
    // determine where index files are saved
    if (@file_exists($conf['indexdir'].'/page.idx')){ // new word length based index
      $this->idx_dir = $conf['indexdir'];
      if (!@file_exists($this->idx_dir.'/'.$this->idx_type.'.idx'))
        $this->_importDateIndex();
    } else {                                          // old index
      $this->idx_dir = $conf['cachedir'];
    }
    
    // load page and creation date index
    $this->page_idx = @file($this->idx_dir.'/page.idx');
    $this->date_idx = @file($this->idx_dir.'/'.$this->idx_type.'.idx');
  }
  
  function getInfo(){
    return array(
      'author' => 'Gina Häußge, Michael Klier, Esther Brunner',
      'email'  => 'dokuwiki@chimeric.de',
      'date'   => '2008-04-20',
      'name'   => 'Blog Plugin (helper class)',
      'desc'   => 'Returns a number of recent entries from a given namespace',
      'url'    => 'http://wiki.splitbrain.org/plugin:blog',
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
    global $conf;
    
    // add pages in given namespace
    $result  = array();
    $c       = count($this->page_idx);
    for ($i = 0; $i < $c; $i++){
      $id = substr($this->page_idx[$i], 0, -1);
      $file = wikiFN($id);
      
      // do some checks first
      if (isHiddenPage($id)) continue;                     // skip excluded pages
      $excluded_pages = $this->getConf('excluded_pages');
      if (strlen($excluded_pages) > 0 && preg_match($excluded_pages, $id)) continue; 
      if (($ns) && (strpos($id, $ns.':') !== 0)) continue; // filter namespaces
      if (!@file_exists($file)) continue;                  // skip deleted
      
      
      $perm = auth_quickaclcheck($id);
      if ($perm < AUTH_READ) continue;                     // check ACL
      
      // skip drafts unless for users with create priviledge
      $meta = p_get_metadata($id);
      $draft = ($meta['type'] == 'draft');
      if ($draft && ($perm < AUTH_CREATE)) continue;
                
      // okay, add the page
      $date = substr($this->date_idx[$i], 0, -1);
      if ((!$date)
        || (($this->idx_type == 'mdate')
        && ($date + $conf['cachetime'] < filemtime($file)))){
        $date = $this->_getDate($id, $i);
      }
      $title = $meta['title'];
      
      // determine the sort key
      if ($this->sort == 'id') $key = $id;
      elseif ($this->sort == 'pagename') $key = noNS($id);
      elseif ($this->sort == 'title') $key = $title;
      else $key = $date;
      
      // check if key is unique
      $key = $this->_uniqueKey($key, $result);
      
      $result[$key] = array(
        'id'     => $id,
        'title'  => $title,
        'date'   => $date,
        'user'   => $meta['creator'],
        'desc'   => $meta['description']['abstract'],
        'exists' => true,
        'perm'   => $perm,
        'draft'  => $draft,
      );
    }
    
    // finally sort by sort key
    if ($this->getConf('sortorder') == 'ascending') ksort($result);
    else krsort($result);
    
    if (is_numeric($num)) $result = array_slice($result, 0, $num);
          
    return $result;
  }
  
  /**
   * Get the creation or modification date of a page from metadata or file system
   */
  function _getDate($id, $pid){
    
    if ($this->sort == 'mdate'){
      $date = p_get_metadata($id, 'date modified');
      if (!$date) $date = filemtime(wikiFN($id));
    } else {
      $date = p_get_metadata($id, 'date created');
      if (!$date) $date = filectime(wikiFN($id));
    }
    
    // check lines and fill creation / modification date in
    for ($i = 0; $i < $pid; $i++){
      if (empty($this->date_idx[$i])) $this->date_idx[$i] = "\n";
    }
    $this->date_idx[$pid] = "$date\n";
    
    // save creation or modification date index
    $fh = fopen($this->idx_dir.'/'.$this->idx_type.'.idx', 'w');
    if (!$fh) return false;
    fwrite($fh, join('', $this->date_idx));
    fclose($fh);
    
    return $date;
  }
  
  /**
   * Update creation date index
   */
  function _updateDateIndex($id, $date){
  
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
      if (empty($this->date_idx[$i])) $this->date_idx[$i] = "\n";
    }
    $this->date_idx[$pid] = "$date\n";    
    
    // save creation date index
    $this->_saveIndex($this->idx_type);
  }
  
  /**
   * Save creation date or page index
   */
  function _saveIndex($idx = 'cdate'){
    $fh = fopen($this->idx_dir.'/'.$idx.'.idx', 'w');
    if (!$fh) return false;
    if ($idx == 'page') fwrite($fh, join('', $this->page_idx));
    else fwrite($fh, join('', $this->date_idx));
    fclose($fh);
  }
  
  /**
   * Import old creation date index
   */
  function _importDateIndex(){
    global $conf;
    
    $old = $conf['cachedir'].'/'.$this->idx_type.'.idx';
    $new = $conf['indexdir'].'/'.$this->idx_type.'.idx';
    
    if (!@file_exists($old)) return false;
        
    if (@copy($old, $new)){
      @unlink($old);
      return true;
    }
    return false;
  }
    
  /**
   * Non-recursive function to check whether an array key is unique
   *
   * @author    Esther Brunner <wikidesign@gmail.com>
   * @author    Ilya S. Lebedev <ilya@lebedev.net>
   */
  function _uniqueKey($key, &$result){
    
    // increase numeric keys by one
    if (is_numeric($key)){
      while (array_key_exists($key, $result)) $key++;
      return $key;
      
    // append a number to literal keys
    } else {
      $num     = 0;
      $testkey = $key;
      while (array_key_exists($testkey, $result)){
        $testkey = $key.$num;
        $num++;
      }
      return $testkey;
    }
  }
  
}
  
//Setup VIM: ex: et ts=4 enc=utf-8 :
