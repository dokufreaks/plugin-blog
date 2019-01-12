<?php
/**
 * Dynamic Archive Plugin: dynamically displays 
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
class syntax_plugin_blog_autoarchive extends DokuWiki_Syntax_Plugin {

    function getType() { return 'substition'; }
    function getPType() { return 'block'; }
    function getSort() { return 309; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{autoarchive>.*?\}\}', $mode, 'plugin_blog_autoarchive');
    }

    function handle($match, $state, $pos, Doku_Handler $handler) {
        global $ID;

        $match = substr($match, 14, -2); // strip {{autoarchive> from start and }} from end
        list($match, $flags) = explode('?', $match, 2);
        $flags = explode('&', $flags);
        list($ns, $refine) = explode(' ', $match, 2);

        if ($ns == '') $ns = cleanID($this->getConf('namespace'));
        elseif (($ns == '*') || ($ns == ':')) $ns = '';
        elseif ($ns == '.') $ns = getNS($ID);
        elseif (preg_match('/^\.:/', $ns)){
            if (getNS($ID)) {
                $ns = getNS($ID) . ltrim($ns, ".");
            } else {
                $ns = ltrim($ns, ".:");
            }
        }
        else $ns = cleanID($ns);

        return array($ns, $flags, $refine, $pos);
    }

    function render($mode, Doku_Renderer $renderer, $data) {
        list($ns, $flags, $refine, $pos) = $data;
        if ($mode != 'xhtml') return false;

        // no caching for dynamic content
        $renderer->info['cache'] = false;

        // get the blog entries for our namespace
        if ($my =& plugin_load('helper', 'blog')) $entries = $my->getBlog($ns);

        // use tag refinements?
        if ($refine) {
            if (plugin_isdisabled('tag') || (!$tag = plugin_load('helper', 'tag'))) {
                msg($this->getLang('missing_tagplugin'), -1);
            } else {
                $entries = $tag->tagRefine($entries, $refine);
            }
        }

        if (!$entries) return true; // nothing to display

        // what to display
        if(preg_match('/^\d\d\d\d-\d\d$/',$_REQUEST['blogarchive'])){
            $now = $_REQUEST['blogarchive'];
        }else{
            $now = strftime('%Y-%m'); // current month
        }
        list($y,$m) = explode('-',$now);

        // display the archive overview
        $cnt = $this->_buildTimeChooser($renderer, $entries, $now);

        $renderer->header($this->_posts($cnt,$m,$y),2,$pos);
        $renderer->section_open(2);

        // let Pagelist Plugin do the work for us
        if (plugin_isdisabled('pagelist')
                || (!$pagelist =& plugin_load('helper', 'pagelist'))) {
            msg($this->getLang('missing_pagelistplugin'), -1);
            return false;
        }
        $pagelist->setFlags($flags);
        $pagelist->startList();
        foreach ($entries as $entry) {
            $date = strftime('%Y-%m',$entry['date']);
            // entry in the right date range?
            if($date < $now || $date > $now) continue;
            $pagelist->addPage($entry);
        }
        $renderer->doc .= $pagelist->finishList();

        $renderer->section_close();
        return true;

    }

    /**
     * Creates a list of monthly archive links
     *
     * @param object reference $R - the XHTML renderer
     * @param array reference $entries - all entries metadata
     * @param string $now - currently selected month ('YYYY-MM')
     * @return int - number of posts for selected month
     */
    function _buildTimeChooser(&$R, &$entries, $now){
        global $ID;

        // get the months where posts exist
        $months = array();
        foreach($entries as $entry){
            $y = date('Y',$entry['date']);
            $m = date('m',$entry['date']);
            if(isset($months[$y][$m])) {
                $months[$y][$m]++;
            }else{
                $months[$y][$m] = 1;
            }
        }

        $ret = 0;
        // output
        $R->doc .= '<div class="autoarchive_selector">';
        foreach($months as $y => $mdata){
            $R->listu_open();
            $R->listitem_open(1);
            $R->listcontent_open();
            $R->doc .= $y.'<span>:</span>';
            $R->listcontent_close();
            ksort($mdata);
            foreach($mdata as $m => $cnt){
                $R->listu_open();
                $R->listitem_open(2);
                $R->listcontent_open();
                if("$y-$m" == $now) $R->doc .= '<span class="cur">';
                $R->doc .= '<a href="'.wl($ID,array('blogarchive'=>"$y-$m")).'" class="wikilink1" title="'.$this->_posts($cnt,$m,$y).'">';
                $R->doc .= $this->getLang('month_'.$m);
                $R->doc .= '</a>';
                if("$y-$m" == $now){
                    $R->doc .= '</span>';
                    $ret = $cnt;
                }
                $R->listcontent_close();
                $R->listitem_close();
                $R->listu_close();
            }
            $R->listitem_close();
            $R->listu_close();
        }
        $R->doc .='</div>';
        return $ret;
    }

    function _posts($num,$month,$year){
        return sprintf($this->getLang('autoarchive'),
                       $num, $this->getLang("month_$month"),
                       $year);
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
