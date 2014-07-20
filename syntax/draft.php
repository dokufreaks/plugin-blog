<?php
/**
 * Blog Plugin, draft component: marks the current page as draft
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_blog_draft extends DokuWiki_Syntax_Plugin {

    function getType() { return 'substition'; }
    function getSort() { return 99; }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~DRAFT~~', $mode, 'plugin_blog_draft');
    }

    function handle($match, $state, $pos, Doku_Handler $handler) {
        return true;
    }

    /**
     * The only thing this plugin component does is to set the metadata 'type' to 'draft'
     */
    function render($mode, Doku_Renderer $renderer, $data) {
        if ($mode == 'xthml') {
            return true; // don't output anything
        } elseif ($mode == 'metadata') {
            $renderer->meta['type'] = 'draft';
        }
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
