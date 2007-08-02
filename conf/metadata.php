<?php
/**
 * Metadata for configuration manager plugin
 * Additions for the blog plugin
 *
 * @author    Esther Brunner <wikidesign@gmail.com>
 */
$meta['namespace']    = array('string');
$meta['formposition'] = array('multichoice',
                         '_choices' => array('top', 'bottom'));
$meta['dateprefix']   = array('string');
$meta['sortkey']      = array('multichoice',
                        '_choices' => array('cdate', 'mdate', 'pagename', 'id', 'title'));
$meta['sortorder']    = array('multichoice',
                         '_choices' => array('ascending', 'descending'));

//Setup VIM: ex: et ts=2 enc=utf-8 :
