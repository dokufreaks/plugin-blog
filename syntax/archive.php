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

    function getInfo() {
        return array(
                'author' => 'Gina Häußge, Michael Klier, Esther Brunner',
                'email'  => 'dokuwiki@chimeric.de',
                'date'   => @file_get_contents(DOKU_PLUGIN . 'blog/VERSION'),
                'name'   => 'Blog Plugin (archive component)',
                'desc'   => 'Displays a list of wiki pages from a given month',
                'url'    => 'http://dokuwiki.org/plugin:blog',
                );
    }

    function getType() { return 'substition'; }
    function getPType() { return 'block'; }
    function getSort() { return 309; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{archive>.*?\}\}', $mode, 'plugin_blog_archive');
    }

    function handle($match, $state, $pos, &$handler) {
        global $ID;

        $match = substr($match, 10, -2); // strip {{archive> from start and }} from end
        list($match, $flags) = explode('&', $match, 2);
        $flags = explode('&', $flags);
        list($match, $refine) = explode(' ', $match, 2);
        list($ns, $rest) = explode('?', $match, 2);

        if (!$rest) {
            $rest = $ns;
            $ns   = '';
        }

        if ($ns == '') $ns = cleanID($this->getConf('namespace'));
        elseif (($ns == '*') || ($ns == ':')) $ns = '';
        elseif ($ns == '.') $ns = getNS($ID);
        else $ns = cleanID($ns);

        // daily archive
        if (preg_match("/\d{4}-\d{2}-\d{2}/", $rest)) {
            list($year, $month, $day) = explode('-', $rest, 3);

            $start  = mktime(0, 0, 0, $month, $day, $year);
            $end    = $start + 24*60*60;

            // monthly archive
        } elseif (preg_match("/\d{4}-\d{2}/", $rest)) {
            list($year, $month) = explode('-', $rest, 2);

            // calculate start and end times
            $nextmonth   = $month + 1;
            $year2       = $year;
            if ($nextmonth > 12) {
                $nextmonth = 1;
                $year2     = $year + 1;
            }

            $start  = mktime(0, 0, 0, $month, 1, $year);
            $end    = mktime(0, 0, 0, $nextmonth, 1, $year2);

            // a whole year
        } elseif (preg_match("/\d{4}/", $rest)) {
            $start  = mktime(0, 0, 0, 1, 1, $rest);
            $end    = mktime(0, 0, 0, 1, 1, $rest + 1);

            // all entries from that namespace up to now
        } elseif ($rest == '*') {
            $start  = 0;
            $end    = time();

            // unknown format
        } else {
            return false;
        }

        return array($ns, $start, $end, $flags, $refine);
    }

    function render($mode, &$renderer, $data) {
        list($ns, $start, $end, $flags, $refine) = $data;

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

        if ($mode == 'xhtml') {
            // Configuration
            $archive_mode = $this->getConf('archive_mode');
            $max_months = $this->getConf('max_months');
            $histogram_height = $this->getConf('histogram_height');


            // prevent caching for current month to ensure content is always fresh
            if (time() < $end) $renderer->info['cache'] = false;

            if ($this->getConf('showhistogram')) {
                $current_year ='';
                $current_month ='';
                $ul_open = false;

                $histogram = '';
                $histogram_count = array();
                $histogram_higher = 0;
                $posts_count = 0;

                $list = '';

                // Generate posts list
                foreach ($entries as $entry) {
                    // entry in the right date range?
                    if (($start > $entry['date']) || ($entry['date'] >= $end)) continue;

                    if ($current_year != date('o',$entry['date'])) {
                        if ($ul_open) {
                            $list .= '</ul>' . DOKU_LF;
                            $ul_open = false;
                        }
                        $current_year = date('o',$entry['date']);
                        $list .= '<h2>' . $current_year . '</h2>' . DOKU_LF;
                    }
                    if ($current_month != date('m',$entry['date'])) {
                        if ($ul_open) {
                            $list .= '</ul>' . DOKU_LF;
                        }
                        $current_month = date('m',$entry['date']);
                        $list .= '<h3 id="m' . date('o-m',$entry['date']) . '">' . $this->getLang('month_' . $current_month) . '</h3><ul>' . DOKU_LF;
                        $ul_open = true;
                    }
                    $posts_count += 1;
                    $histogram_count[date('o-m',$entry['date'])] += 1;
                    if ($histogram_higher < $histogram_count[date('o-m',$entry['date'])]) {
                        $histogram_higher = $histogram_count[date('o-m',$entry['date'])];
                    }
                    $list .= '<li>' . date('d',$entry['date']) . ' - <a href="' . wl($entry['id']) . '" title="' . $entry['id'] . '">' . $entry['title'] . '</a></li>' . DOKU_LF;
                }
                $list .= '</ul>' . DOKU_LF;

                if ($posts_count > $max_posts) {
                    $posts_count = $max_posts;
                }
                // Generate histogram
                $histogram_count = array_reverse($histogram_count);
                $month_count = 0;
                foreach ($histogram_count as $key => $month_reference) {
                    // Check the max_months parameter
                    if ($month_count >= $max_months) {
                        break;
                    }
                    if ($month_reference > 0) {
                        // Height in "px"
                        $current_height = $histogram_height / $histogram_higher * $month_reference;
                    } else {
                        // Height in "px"
                        $current_height = 1;
                    }
                    // Generate the alt attribute
                    $alt = $key.': '.$month_reference.' ';
                    if ($month_reference > 1) {
                        $alt .= $this->getLang('entries');
                    } else {
                        $alt .= $this->getLang('entry');
                    }
                    $histogram .= '<a href="#m' . $key . '" title="#m' . $key . '">';
                    $histogram .= '<img class="blog_archive_bar" alt="' . $alt . '" height="' . $current_height . '" src="lib/images/blank.gif"/></a>' . DOKU_LF;
                    $month_count += 1;
                }
                // Add histogram and posts list
                $renderer->doc .= '<div class="level1"><h1>' . $this->getLang('archive_title') . '</h1>' . $histogram . '<br/><br/>' . $list . '</div>' . DOKU_LF; 
            } else {
                // prevent caching for current month to ensure content is always fresh
                if (time() < $end) $renderer->info['cache'] = false;

                // let Pagelist Plugin do the work for us
                if (plugin_isdisabled('pagelist')
                        || (!$pagelist =& plugin_load('helper', 'pagelist'))) {
                    msg($this->getLang('missing_pagelistplugin'), -1);
                    return false;
                }
                $pagelist->setFlags($flags);
                $pagelist->startList();
                foreach ($entries as $entry) {

                    // entry in the right date range?
                    if (($start > $entry['date']) || ($entry['date'] >= $end)) continue;

                    $pagelist->addPage($entry);
                }
                $renderer->doc .= $pagelist->finishList();
            }
            return true;

            // for metadata renderer
        } elseif ($mode == 'metadata') {
            foreach ($entries as $entry) {

                // entry in the right date range?
                if (($start > $entry['date']) || ($entry['date'] >= $end)) continue;

                $renderer->meta['relation']['references'][$entry['id']] = true;
            }

            return true;
        }
        return false;
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
