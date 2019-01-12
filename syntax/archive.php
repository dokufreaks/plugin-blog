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

    function getType() { return 'substition'; }
    function getPType() { return 'block'; }
    function getSort() { return 309; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{archive>.*?\}\}', $mode, 'plugin_blog_archive');
    }

    function handle($match, $state, $pos, Doku_Handler $handler) {
        global $ID;

        $match = substr($match, 10, -2); // strip {{archive> from start and }} from end
        list($match, $flags) = explode('&', $match, 2);
        $flags = explode('&', $flags);
        list($match, $refine) = explode(' ', $match, 2);
        list($ns, $rest) = explode('?', $match, 2);

        $author = NULL;
        foreach($flags as $i=>$flag) {
            if(preg_match('/(\w+)\s*=(.+)/', $flag, $temp) == 1) {
                if ($temp[1] == 'author') {
                    $author = trim($temp[2]);
                    unset($flags[$i]);
                }
            }
        }

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
            $end    = PHP_INT_MAX;

            // unknown format
        } else {
            return false;
        }

        return array($ns, $start, $end, $flags, $refine, $author);
    }

    function render($mode, Doku_Renderer $renderer, $data) {
        list($ns, $start, $end, $flags, $refine, $author) = $data;

        // get the blog entries for our namespace
        /** @var helper_plugin_blog $my */
        if ($my =& plugin_load('helper', 'blog')) $entries = $my->getBlog($ns, NULL, $author);
        else return false;

        // use tag refinements?
        if ($refine) {
            /** @var helper_plugin_tag $tag */
            if (plugin_isdisabled('tag') || (!$tag = plugin_load('helper', 'tag'))) {
                msg($this->getLang('missing_tagplugin'), -1);
            } else {
                $entries = $tag->tagRefine($entries, $refine);
            }
        }

        if (!$entries) return true; // nothing to display

        if ($mode == 'xhtml') {
            if ($this->getConf('showhistogram')) {
                $alt_list = $this->_build_alternative_list($start, $end, $entries);

                // Add histogram and posts list
                $renderer->doc .= '<div class="level1">';
                $renderer->doc .= '<h1>' . $this->getLang('archive_title') . '</h1>';
                $renderer->doc .= $alt_list;
                $renderer->doc .= '</div>' . DOKU_LF;
            } else {
                // let Pagelist Plugin do the work for us
                if (plugin_isdisabled('pagelist')
                        || (!$pagelist =& plugin_load('helper', 'pagelist'))) {
                    msg($this->getLang('missing_pagelistplugin'), -1);
                    return false;
                }
                /** @var helper_plugin_pagelist $pagelist */
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
            /** @var Doku_Renderer_metadata $renderer */
            // use the blog plugin cache handler in order to ensure that the cache is expired whenever a page, comment
            // or linkback is added
            if (time() < $end) $renderer->meta['plugin_blog']['purgefile_cache'] = true;

            foreach ($entries as $entry) {

                // entry in the right date range?
                if (($start > $entry['date']) || ($entry['date'] >= $end)) continue;

                $renderer->meta['relation']['references'][$entry['id']] = true;
                $renderer->meta['plugin_blog']['archive_pages'][] = $entry['id'];
            }

            return true;
        }
        return false;
    }

    // Generate alternative posts list
    function _build_alternative_list($start, $end, $entries) {
        $current_year ='';
        $current_month ='';
        $ul_open = false;

        $histogram_count = array();
        $histogram_higher = 0;

        $list = '';
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
                $current_month = '';
            }
            if ($current_month != date('m',$entry['date'])) {
                if ($ul_open) {
                    $list .= '</ul>' . DOKU_LF;
                }
                $current_month = date('m',$entry['date']);
                $list .= '<h3 id="m' . date('o-m',$entry['date']) . '">' . $this->getLang('month_' . $current_month) . '</h3><ul>' . DOKU_LF;
                $ul_open = true;
            }
            $histogram_count[date('o-m',$entry['date'])] += 1;
            if ($histogram_higher < $histogram_count[date('o-m',$entry['date'])]) {
                $histogram_higher = $histogram_count[date('o-m',$entry['date'])];
            }
            $list .= '<li>' . date('d',$entry['date']) . ' - <a href="' . wl($entry['id']) . '" title="' . $entry['id'] . '">' . $entry['title'] . '</a></li>' . DOKU_LF;
        }
        $list .= '</ul>' . DOKU_LF;

        $histogram = $this->_build_histogram($histogram_count, $histogram_higher);

        return $histogram . $list;
    }

    // Generate histogram
    function _build_histogram($histogram_count, $histogram_higher) {
        if (empty($histogram_count)) return '';

        $histogram = '<p>';
        $max_months = $this->getConf('max_months');
        $histogram_height = $this->getConf('histogram_height');
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
            $histogram .= '<a href="#m' . $key . '" title="' . $alt . '">';
            $histogram .= '<img class="blog_archive_bar" alt="' . $alt . '" style="height: ' . $current_height . 'px;" src="'.DOKU_BASE.'lib/images/blank.gif"/></a>' . DOKU_LF;
            $month_count += 1;
        }
        $histogram .= '</p>';

        return $histogram;
    }

}
// vim:ts=4:sw=4:et:enc=utf-8:
