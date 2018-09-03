<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_blog extends DokuWiki_Action_Plugin {

    /**
     * register the eventhandlers
     */
    function register(Doku_Event_Handler $contr) {
        $contr->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_act_preprocess', array());
        $contr->register_hook('FEED_ITEM_ADD', 'BEFORE', $this, 'handle_feed_item');
        $contr->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'handle_cache');
    }

    /**
     * Checks if 'newentry' was given as action, if so we
     * do handle the event our self and no further checking takes place
     */
    function handle_act_preprocess(Doku_Event $event, $param) {
        if ($event->data != 'newentry') return; // nothing to do for us

        $event->data = $this->_handle_newEntry($event);
    }

    /**
     * Removes draft entries from feeds
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function handle_feed_item(&$event, $param) {
        global $conf;

        $url = parse_url($event->data['item']->link);
        $base_url = getBaseURL();

        // determine page id by rewrite mode
        switch($conf['userewrite']) {
            case 0:
                preg_match('#id=([^&]*)#', $url['query'], $match);
                if($base_url != '/') {
                    $id = cleanID(str_replace($base_url, '', $match[1]));
                } else {
                    $id = cleanID($match[1]);
                }
                break;

            case 1:
                if($base_url != '/') {
                    $id = cleanID(str_replace('/',':',str_replace($base_url, '', $url['path'])));
                } else {
                    $id = cleanID(str_replace('/',':', $url['path']));
                }
                break;

            case 2:
                preg_match('#doku.php/([^&]*)#', $url['path'], $match);
                if($base_url != '/') {
                    $id = cleanID(str_replace($base_url, '', $match[1]));
                } else {
                    $id = cleanID($match[1]);
                }
                break;
        }

        // don't add drafts to the feed
        if(p_get_metadata($id, 'type') == 'draft') {
            $event->preventDefault();
            return;
        }
    }

    /**
     * Creates a new entry page
     */
    function _handle_newEntry(Doku_Event $event) {
        global $ID, $INFO;

        $ns    = cleanID($_REQUEST['ns']);
        $title = str_replace(':', '', $_REQUEST['title']);
        $ID    = $this->_newEntryID($ns, $title);
        $INFO  = pageinfo();

        // check if we are allowed to create this file
        if ($INFO['perm'] >= AUTH_CREATE) {

            // prepare the new thread file with default stuff
            if (!@file_exists($INFO['filepath'])) {

                //check if locked by anyone - if not lock for my self
                if ($INFO['locked']) return 'locked';
                else lock($ID);

                global $TEXT;

                $TEXT = pageTemplate($ID);
                if (!$TEXT) {
                    // if there is no page template, load our custom one
                    $TEXT  = io_readFile(DOKU_PLUGIN.'blog/_template.txt');
                }

                $data = array('id' => $ID, 'ns' => $ns, 'title' => $_REQUEST['title']);
                // Apply replacements regardless if they have already been applied by DokuWiki in order to
                // make custom replacements like @TITLE@ available in standard page templates.
                $TEXT = $this->_pageTemplate($TEXT, $data);

                return 'preview';
            } else {
                return 'edit';
            }
        } else {
            return 'show';
        }
    }

    /**
     * Adapted version of pageTemplate() function
     */
    function _pageTemplate($text, $data) {
        global $conf, $INFO;

        $id   = $data['id'];
        $user = $_SERVER['REMOTE_USER'];

        // standard replacements
        $replace = array(
                '@ID@'   => $id,
                '@NS@'   => $data['ns'],
                '@PAGE@' => strtr(noNS($id),'_',' '),
                '@USER@' => $user,
                '@NAME@' => $INFO['userinfo']['name'],
                '@MAIL@' => $INFO['userinfo']['mail'],
                '@DATE@' => strftime($conf['dformat']),
                );

        // additional replacements
        $replace['@TITLE@'] = $data['title'];

        // tag if tag plugin is available
        if ((@file_exists(DOKU_PLUGIN.'tag/syntax/tag.php'))
                && (!plugin_isdisabled('tag'))) {
            $replace['@TAG@'] = "\n\n{{tag>}}";
        } else {
            $replace['@TAG@'] = '';
        }

        // discussion if discussion plugin is available
        if ((@file_exists(DOKU_PLUGIN.'discussion/syntax/comments.php'))
                && (!plugin_isdisabled('discussion'))) {
            $replace['@DISCUSSION@'] = "~~DISCUSSION~~";
        } else {
            $replace['@DISCUSSION@'] = '';
        }

        // linkbacks if linkback plugin is available
        if ((@file_exists(DOKU_PLUGIN.'linkback/syntax.php'))
                && (!plugin_isdisabled('linkback'))) {
            $replace['@LINKBACK@'] = "~~LINKBACK~~";
        } else {
            $replace['@LINKBACK@'] = '';
        }

        // do the replace
        return str_replace(array_keys($replace), array_values($replace), $text);
    }

    /**
     * Returns the ID of a new entry based on its namespace, title and the date prefix
     *
     * @author  Esther Brunner <wikidesign@gmail.com>
     * @author  Michael Arlt <michael.arlt@sk-chwanstetten.de>
     */
    function _newEntryID($ns, $title) {
        $dateprefix  = $this->getConf('dateprefix');
        if (substr($dateprefix, 0, 1) == '<') {
            // <9?%y1-%y2:%d.%m._   ->  05-06:31.08._ | 06-07:01.09._
            list($newmonth, $dateprefix) = explode('?', substr($dateprefix, 1));
            if (intval(strftime("%m")) < intval($newmonth)) {
                $longyear2 = strftime("%Y");
                $longyear1 = $longyear2 - 1;
            } else {
                $longyear1 = strftime("%Y");
                $longyear2 = $longyear1 + 1;
            }
            $shortyear1 = substr($longyear1, 2);
            $shortyear2 = substr($longyear2, 2);
            $dateprefix = str_replace(
                    array('%Y1', '%Y2', '%y1', '%y2'),
                    array($longyear1, $longyear2, $shortyear1, $shortyear2),
                    $dateprefix
                    );
        }
        $pre = strftime($dateprefix);
        return cleanID(($ns ? $ns.':' : '').$pre.$title);
    }

    /**
     * Expire the renderer cache of archive pages whenever a page is updated or a comment or linkback is added
     *
     * @author Michael Hamann <michael@content-space.de>
     */
    function handle_cache(Doku_Event $event, $params) {
        global $conf;
        /** @var cache_parser $cache */
        $cache = $event->data;
        if (!in_array($cache->mode, array('xhtml', 'metadata'))) return;
        $page = $cache->page;

        // try to extract the page id from the file if possible
        if (empty($page)) {
            if (strpos($cache->file, $conf['datadir']) === 0) {
                $page = pathID(substr($cache->file, strlen($conf['datadir'])+1));
            } else {
                return;
            }
        }

        $meta = p_get_metadata($page, 'plugin_blog');
        if ($meta === null) return;

        if (isset($meta['purgefile_cache'])) {
            $cache->depends['files'][] = $conf['cachedir'].'/purgefile';
            $cache->depends['files'][] = $conf['metadir'].'/_comments.changes';
            $cache->depends['files'][] = $conf['metadir'].'/_linkbacks.changes';
        }

        // purge the cache when a page is listed that the current user can't access
        if (isset($meta['archive_pages'])) {
            foreach ($meta['archive_pages'] as $page) {
                if (auth_quickaclcheck($page) < AUTH_READ) {
                    $cache->depends['purge'] = true;
                    return;
                }
            }
        }
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
