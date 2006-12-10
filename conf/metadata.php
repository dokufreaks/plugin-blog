<?php
/**
 * Metadata for configuration manager plugin
 * Additions for the blog plugin
 *
 * @author    Esther Brunner <wikidesign@gmail.com>
 */
$meta['namespace']     = array('string');
$meta['formposition']  = array('multichoice',
                          '_choices' => array('top', 'bottom'));
$meta['sortkey']       = array('multichoice',
                          '_choices' => array('cdate', 'pagename', 'id'));
$meta['dateprefix']    = array('string');

//Setup VIM: ex: et ts=2 enc=utf-8 :
