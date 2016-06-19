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
        $result[] = array(
                'name'   => 'getFlags',
                'desc'   => 'get values for flags, or defaults where not supplied',
                'params' => array('flags' => 'array'),
                'return' => array('flags' => 'array'),
                );
        return $result;
    }

    /**
     * Get blog entries from a given namespace
     */
    function getBlog($ns, $num = NULL, $author = NULL) {
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
            $meta = p_get_metadata($id, '', false);
            $draft = ($meta['type'] == 'draft');
            if ($draft && ($perm < AUTH_CREATE)) continue;

            // filter by author
            if ($author && ($meta['user'] != $author)) continue;

            $date = $meta['date']['modified'];
            if (!$date) $date = filemtime(wikiFN($id));
            if ($this->sort != 'mdate') {
                $cdate = $meta['date']['created'];
                if (!$cdate) $cdate = filectime(wikiFN($id));
                // prefer the date further in the past:
                $date = min($date, $cdate);
            }

            if (isset($meta['title'])) {
                $title = $meta['title'];
            } else {
                $title = $id;
            }

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
     * Turn a list of user-supplied flags into a complete list of all flags
     * required by the Blog plugin (not including those for the Include plugin),
     * using global configuration options or plugin defaults where flags have
     * not been supplied.
     *
     * Currently handles 'formpos' and 'newentrytitle'.
     *
     * @author Sam Wilson <sam@samwilson.id.au>
     * @param array $setflags Flags that have been set by the user
     * @return array All flags required by the Blog plugin (only)
     */
    function getFlags($setflags) {
        $flags = array();

        // Form Position
        $flags['formpos'] = $this->getConf('formposition');
        if(in_array('topform', $setflags)) {
            $flags['formpos'] = 'top';
        }elseif(in_array('bottomform', $setflags)) {
            $flags['formpos'] = 'bottom';
        }elseif(in_array('noform', $setflags)) {
            $flags['formpos'] = 'none';
        }

        // New Entry Title
        $newentrytitle = preg_grep('|newentrytitle=.*|', $setflags);
        if (count($newentrytitle) > 0) {
            $newentrytitle = array_pop(explode('=', array_pop($newentrytitle), 2));
            if (!empty($newentrytitle)) {
                $flags['newentrytitle'] = $newentrytitle;
            }
        } elseif ($conf_title = $this->getConf('newentrytitle')) {
            $flags['newentrytitle'] = $conf_title;
        } else {
            $flags['newentrytitle'] = $this->getLang('newentry');
        }

        return $flags;
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
