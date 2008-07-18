<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class helper_plugin_blog extends DokuWiki_Plugin {

    var $sort       = '';      // sort key

    /**
     * Constructor
     */
    function helper_plugin_blog() {
        global $conf;

        // load sort key from settings
        $this->sort = $this->getConf('sortkey');
    }

    function getInfo() {
        return array(
                'author' => 'Gina Häußge, Michael Klier, Esther Brunner',
                'email'  => 'dokuwiki@chimeric.de',
                'date'   => @file_get_contents(DOKU_PLUGIN . 'blog/VERSION'),
                'name'   => 'Blog Plugin (helper class)',
                'desc'   => 'Returns a number of recent entries from a given namespace',
                'url'    => 'http://wiki.splitbrain.org/plugin:blog',
                );
    }

    function getMethods() {
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
    function getBlog($ns, $num = NULL) {
        global $conf;

        // add pages in given namespace
        $result  = array();
        global $conf;

        require_once (DOKU_INC.'inc/search.php');

        $pages = array();
        $unique_keys_memoize = array();

        $dir = str_replace(':', '/', $ns);
        search($pages, $conf['datadir'], 'search_pagename', array('query' => '.txt'), $dir);

        foreach ($pages as $page) {
            $id = $page['id'];
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
            
            if ($this->sort == 'mdate') {
                $date = $meta['date']['modified'];
                if (!$date) $date = filemtime(wikiFN($id));
            } else {
                $date = $meta['date']['created'];
                if (!$date) $date = filectime(wikiFN($id));
            }
            
            $title = $meta['title'];

            // determine the sort key
            if ($this->sort == 'id') $key = $id;
            elseif ($this->sort == 'pagename') $key = noNS($id);
            elseif ($this->sort == 'title') $key = $title;
            else $key = $date;

            // get a unique sortable key
            $key = $this->_uniqueKey($key, $unique_keys_memoize);

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
     * Function to create sortable, unique array keys
     *
     * @author    Esther Brunner <wikidesign@gmail.com>
     * @author    Ilya S. Lebedev <ilya@lebedev.net>
     * @author    Balazs Attila-Mihaly <x_at_y_or_z@yahoo.com>
     */
    function _uniqueKey($key, &$unique_keys_memoize){
        //convert numeric keys to string
        if (is_numeric($key))
            $key = sprintf('%08x', $key);
        if (!array_key_exists($key, $unique_keys_memoize))
            $unique_keys_memoize[$key] = 0;

        return sprintf('%s_%s', $key, $unique_keys_memoize[$key]++);
    }

}
// vim:ts=4:sw=4:et:enc=utf-8:  
