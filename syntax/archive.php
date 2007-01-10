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
      'date'   => '2006-12-14',
      'name'   => 'Blog Plugin (archive component)',
      'desc'   => 'Displays a list of wiki pages from a given month',
      'url'    => 'http://www.wikidesign.ch/en/plugin/blog/start',
    );
  }

  function getType(){ return 'substition'; }
  function getPType(){ return 'block'; }
  function getSort(){ return 309; }
  
  function connectTo($mode){
    $this->Lexer->addSpecialPattern('\{\{archive>.+?\}\}', $mode, 'plugin_blog_archive');
  }

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
    
    if (preg_match("/\d{4}-\d{2}/", $rest)){ // monthly archive
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
    } elseif ($rest == '*'){                 // all entries from that namespace
      return array($ns, 0, time() + 604800);
    }
    
    return false;
  }

  /**
   * Create output
   */
  function render($mode, &$renderer, $data) {
    global $ID;
    global $conf;
        
    list($ns, $start, $end) = $data;
    if ($ns == '') $ns = cleanID($this->getConf('namespace'));
    elseif (($ns == '*') || ($ns == ':')) $ns = '';
    elseif ($ns == '.') $ns = getNS($ID);
    else $ns = cleanID($ns);
    
    // get the blog entries for our namespace
    if ($my =& plugin_load('helper', 'blog')) $entries = $my->getBlog($ns);
    if (!$entries) return true; // nothing to display
    
    if ($mode == 'xhtml'){
      
      // prevent caching for current month to ensure content is always fresh
      if (time() < $end) $renderer->info['cache'] = false;
      
      // let Pagelist Plugin do the work for us
      if (plugin_isdisabled('pagelist')
        || (!$pagelist =& plugin_load('helper', 'pagelist'))){
        msg('The Pagelist Plugin must be installed for archive lists to work.', -1);
        return false;
      }
      $pagelist->startList();
      foreach ($entries as $entry){
      
        // entry in the right date range?
        if (($start > $entry['date']) || ($entry['date'] >= $end)) continue;
        
        $pagelist->addPage($entry);
      }
      $renderer->doc .= $pagelist->finishList();
      return true;
        
    // for metadata renderer
    } elseif ($mode == 'metadata'){
      foreach ($entries as $entry){
      
        // entry in the right date range?
        if (($start > $entry['date']) || ($entry['date'] >= $end)) continue;

        $renderer->meta['relation']['references'][$entry['id']] = true;
      }
      
      return true;
    }
    return false;
  }
    
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
