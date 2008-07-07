<?php
/**
 * Blog Plugin: displays a number of recent entries from the blog subnamespace
 * 
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Esther Brunner <wikidesign@gmail.com>
 * @author   Robert Rackl <wiki@doogie.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_blog_blog extends DokuWiki_Syntax_Plugin {

    function getInfo() {
        return array(
                'author' => 'Gina Häußge, Michael Klier, Esther Brunner',
                'email'  => 'dokuwiki@chimeric.de',
                'date'   => @file_get_contents(DOKU_PLUGIN . 'blog/VERSION'),
                'name'   => 'Blog Plugin (blog component)',
                'desc'   => 'Displays a number of recent entries from a given namesspace',
                'url'    => 'http://wiki.splitbrain.org/plugin:blog',
                );
    }

    function getType() { return 'substition'; }
    function getPType() { return 'block'; }
    function getSort() { return 307; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{blog>.*?\}\}',$mode,'plugin_blog_blog');
    }

    function handle($match, $state, $pos, &$handler) {
        global $ID;

        $match = substr($match, 7, -2); // strip {{blog> from start and }} from end
        list($match, $flags) = explode('&', $match, 2);
        $flags =  explode('&', $flags);
        $flags[] = 'permalink'; // always make the first header of a blog entry a permalink
        list($match, $refine) = explode(' ', $match, 2);
        list($ns, $num) = explode('?', $match, 2);

        if (!is_numeric($num)) {
            if (is_numeric($ns)) {
                $num = $ns;
                $ns  = '';
            } else {
                $num = 5;
            }
        }

        if ($ns == '') $ns = cleanID($this->getConf('namespace'));
        elseif (($ns == '*') || ($ns == ':')) $ns = '';
        elseif ($ns == '.') $ns = getNS($ID);
        else $ns = cleanID($ns);

        return array($ns, $num, $flags, $refine);
    }

    function render($mode, &$renderer, $data) {
        list($ns, $num, $flags, $refine) = $data;

        $first = $_REQUEST['first'];
        if (!is_numeric($first)) $first = 0;

        // get the blog entries for our namespace
        if ($my =& plugin_load('helper', 'blog')) $entries = $my->getBlog($ns);

        // use tag refinements?
        if ($refine) {
            if (plugin_isdisabled('tag') || (!$tag =& plugin_load('helper', 'tag'))) {
                msg($this->getLang('missing_tagplugin'), -1);
            } else {
                $entries = $tag->tagRefine($entries, $refine);
            }
        }

        if (!$entries) {
            if ((auth_quickaclcheck($ns.':*') >= AUTH_CREATE) && ($mode == 'xhtml')) {
                $renderer->info['cache'] = false;
                $renderer->doc .= $this->_newEntryForm($ns);
            }
            return true; // nothing to display
        }

        // slice the needed chunk of pages
        $more = ((count($entries) > ($first + $num)) ? true : false);
        $entries = array_slice($entries, $first, $num);

        // load the include helper plugin
        if (plugin_isdisabled('include') || (!$include =& plugin_load('helper', 'include'))) {
            msg($this->getLang('missing_includeplugin'), -1);
            return false;
        }

        if ($mode == 'xhtml') {

            // prevent caching to ensure the included pages are always fresh
            $renderer->info['cache'] = false;

            // show new entry form
            $perm_create = (auth_quickaclcheck($ns.':*') >= AUTH_CREATE);
            if ($perm_create && ($this->getConf('formposition') == 'top'))
                $renderer->doc .= $this->_newEntryForm($ns);

            // current section level
            $clevel = 0;
            preg_match_all('|<div class="level(\d)">|i', $renderer->doc, $matches, PREG_SET_ORDER);
            $n = count($matches)-1;
            if ($n > -1) $clevel = $matches[$n][1];
            $include->setLevel($clevel);

            // close current section
            if ($clevel) $renderer->doc .= '</div>'.DOKU_LF;
            $renderer->doc .= '<div class="hfeed">'.DOKU_LF;
        }

        // now include the blog entries
        foreach ($entries as $entry) {
            if (!$include->setPage($entry)) continue; // returns false if include recursion
            if ($mode == 'xhtml') {
                $include->setFlags($flags);
                $include->renderXHTML($renderer, $info);
            } elseif ($mode == 'metadata') {
                $renderer->meta['relation']['haspart'][$entry['id']] = true;
                $include->pages = array(); // clear filechain - important!
            }
        }

        if ($mode == 'xhtml') {
            // resume the section
            $renderer->doc .= '</div>'.DOKU_LF;
            if ($clevel) $renderer->doc .= '<div class="level'.$clevel.'">'.DOKU_LF;

            // show older / newer entries links
            $renderer->doc .= $this->_browseEntriesLinks($more, $first, $num);

            // show new entry form
            if ($perm_create && ($this->getConf('formposition') == 'bottom'))
                $renderer->doc .= $this->_newEntryForm($ns);
        }

        return true;
    }

    /* ---------- (X)HTML Output Functions ---------- */

    /**
     * Displays links to older newer entries of the blog namespace
     */
    function _browseEntriesLinks($more, $first, $num) {
        global $ID;

        $ret = '';
        $last = $first+$num;
        if ($first > 0) {
            $first -= $num;
            if ($first < 0) $first = 0;
            $ret .= '<p class="centeralign">'.DOKU_LF.'<a href="'.wl($ID, 'first='.$first).'"'.
                ' class="wikilink1">&lt;&lt; '.$this->getLang('newer').'</a>';
            if ($more) $ret .= ' | ';
            else $ret .= '</p>';
        } else if ($more) {
            $ret .= '<p class="centeralign">'.DOKU_LF;
        }
        if ($more) {
            $ret .= '<a href="'.wl($ID, 'first='.$last).'" class="wikilink1">'.
                $this->getLang('older').' &gt;&gt;</a>'.DOKU_LF.'</p>'.DOKU_LF;
        }
        return $ret;
    }

    /**
     * Displays a form to enter the title of a new entry in the blog namespace
     * and then open that page in the edit mode
     */
    function _newEntryForm($ns) {
        global $lang;
        global $ID;

        return '<div class="newentry_form">'.DOKU_LF.
            '<form id="blog__newentry_form" method="post" action="'.script().'" accept-charset="'.$lang['encoding'].'">'.DOKU_LF.
            DOKU_TAB.'<fieldset>'.DOKU_LF.
            DOKU_TAB.DOKU_TAB.'<legend>'.$this->getLang('newentry').'</legend>'.DOKU_LF.
            DOKU_TAB.DOKU_TAB.'<input type="hidden" name="id" value="'.$ID.'" />'.DOKU_LF.
            DOKU_TAB.DOKU_TAB.'<input type="hidden" name="do" value="newentry" />'.DOKU_LF.
            DOKU_TAB.DOKU_TAB.'<input type="hidden" name="ns" value="'.$ns.'" />'.DOKU_LF.
            DOKU_TAB.DOKU_TAB.'<input class="edit" type="text" name="title" id="blog__newentry_title" size="40" tabindex="1" />'.DOKU_LF.
            DOKU_TAB.DOKU_TAB.'<input class="button" type="submit" value="'.$lang['btn_create'].'" tabindex="2" />'.DOKU_LF.
            DOKU_TAB.'</fieldset>'.DOKU_LF.
            '</form>'.DOKU_LF.
            '</div>'.DOKU_LF;
    }  
}
// vim:ts=4:sw=4:et:enc=utf-8:
