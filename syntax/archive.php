<?php
/**
 * Archive Plugin: displays links to all wiki pages from a given month
 * 
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_blog_archive extends DokuWiki_Syntax_Plugin {

  /**
   * return some info
   */
  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2006-12-04',
      'name'   => 'Blog Plugin (archive component)',
      'desc'   => 'Displays a list of wiki pages from a given month',
      'url'    => 'http://www.wikidesign.ch/en/plugin/blog/start',
    );
  }

  function getType(){ return 'substition'; }
  function getPType(){ return 'block'; }
  function getSort(){ return 309; }
  function connectTo($mode) { $this->Lexer->addSpecialPattern('\{\{archive>.+?\}\}',$mode,'plugin_blog_archive'); }

  /**
   * Handle the match
   */
  function handle($match, $state, $pos, &$handler){
    $match = substr($match, 10, -2); // strip {{archive> from start and }} from end
    list($ns, $rest) = explode("?", $match);
    if (!$rest){
      $rest = $ns;
      $ns   = '';
    }
    
    // monthly archive
    if (preg_match("/\d{4}-\d{2}/", $rest)){
      list($year, $month) = explode("-", $rest);
      
      // calculate start and end times
      $nextmonth   = $month + 1;
      $year2       = $year;
      if ($nextmonth > 12){
        $nextmonth = 1;
        $year2     = $year + 1;
      }
      
      $start  = mktime(0, 0, 0, $month, 1, $year);
      $end    = mktime(0, 0, 0, $nextmonth, 1, $year2);
      
      return array($ns, $start, $end);
    }
    
    return false;
  }

  /**
   * Create output
   */
  function render($mode, &$renderer, $data) {
    global $ID;
    global $conf;
        
    $ns = $data[0];
    if ($ns == '') $ns = cleanID($this->getConf('namespace'));
    elseif ($ns == '*') $ns = '';
    elseif ($ns == '.') $ns = getNS($ID);
    
    // get the blog entries for our namespace
    require_once(DOKU_PLUGIN.'blog/inc/recent.php');
    $recent  = new plugin_class_recent;
    $entries = $recent->_getBlogEntries($ns);
    
    if (!count($entries)) return true; // nothing to display
    
    if ($mode == 'xhtml'){
      
      // prevent caching for current month to ensure content is always fresh
      if (time() < $data[2]) $renderer->info['cache'] = false;
  
      $renderer->doc .= '<table class="archive">';
      foreach ($entries as $entry){
        $date  = $entry['date'];
      
        // entry in the right date range?
        if (($data[1] > $date) || ($date >= $data[2])) continue;
        
        $renderer->doc .= '<tr><td class="page">';
        
        // page title
        $id    = $entry['id'];
        $meta  = p_get_metadata($id);
        $title = $meta['title'];
        $user  = $meta['creator'];
        if (!$title) $title = str_replace('_', ' ', noNS($id));
        $renderer->doc .= $renderer->internallink(':'.$id, $title).'</td>';
        
        // creation date
        if ($this->getConf('archive_showdate')){
          $renderer->doc .= '<td class="date">'.date($conf['dformat'], $date).'</td>';
        }
        
        // author
        if ($this->getConf('archive_showuser')){
          if ($user) $renderer->doc .= '<td class="user">'.$user.'</td>';
          else $renderer->doc .= '<td class="user">&nbsp;</td>';
        }        
        $renderer->doc .= '</tr>';
      }
      $renderer->doc .= '</table>';
      
      return true;
      
    // for metadata renderer
    } elseif ($mode == 'metadata'){
      foreach ($entries as $entry){
        $id   = $entry['id'];
        $date = $entry['date'];
      
        // entry in the right date range?
        if (($data[1] > $date) || ($date >= $data[2])) continue;

        $renderer->meta['relation']['references'][$id] = true;
      }
      
      return true;
    }
    return false;
  }
    
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
