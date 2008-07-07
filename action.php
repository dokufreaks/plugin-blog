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
     * return some info
     */
    function getInfo() {
        return array(
                'author' => 'Gina Häußge, Michael Klier, Esther Brunner',
                'email'  => 'dokuwiki@chimeric.de',
                'date'   => @file_get_contents(DOKU_PLUGIN . 'blog/VERSION'),
                'name'   => 'Blog Plugin',
                'desc'   => 'Brings blog functionality to DokuWiki',
                'url'    => 'http://wiki.splitbrain.org/plugin:blog',
                );
    }

    /**
     * register the eventhandlers
     */
    function register(&$contr) {
        $contr->register_hook('ACTION_ACT_PREPROCESS',
                'BEFORE',
                $this,
                'handle_act_preprocess',
                array());
    }

    /**
     * Checks if 'newentry' was given as action, if so we
     * do handle the event our self and no further checking takes place
     */
    function handle_act_preprocess(&$event, $param) {
        if ($event->data != 'newentry') return; // nothing to do for us
        // we can handle it -> prevent others
        // $event->stopPropagation();
        $event->preventDefault();    

        $event->data = $this->_handle_newEntry();
    }

    /**
     * Creates a new entry page
     */
    function _handle_newEntry() {
        global $ID, $INFO;

        $ns    = cleanID($_REQUEST['ns']);
        $title = str_replace(':', '', $_REQUEST['title']);
        $ID    = $this->_newEntryID($ns, $title);
        $INFO  = pageinfo();

        // check if we are allowed to create this file
        if ($INFO['perm'] >= AUTH_CREATE) {

            //check if locked by anyone - if not lock for my self      
            if ($INFO['locked']) return 'locked';
            else lock($ID);

            // prepare the new thread file with default stuff
            if (!@file_exists($INFO['filepath'])) {
                global $TEXT;

                $TEXT = pageTemplate(array(($ns ? $ns.':' : '').$title));
                if (!$TEXT) {
                    $data = array('id' => $ID, 'ns' => $ns, 'title' => $title);
                    $TEXT = $this->_pageTemplate($data);
                }
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
    function _pageTemplate($data) {
        global $conf, $INFO;

        $id   = $data['id'];
        $user = $_SERVER['REMOTE_USER'];
        $tpl  = io_readFile(DOKU_PLUGIN.'blog/_template.txt');

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
        $tpl = str_replace(array_keys($replace), array_values($replace), $tpl);
        return $tpl;
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
        return ($ns ? $ns.':' : '').$pre.cleanID($title);
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:  
