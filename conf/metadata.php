<?php
/**
 * Metadata for configuration manager plugin
 * Additions for the blog plugin
 *
 * @author    Esther Brunner <wikidesign@gmail.com>
 */
$meta['namespace']    = array('string');
$meta['formposition'] = array('multichoice',
                         '_choices' => array('top', 'bottom', 'none'));
$meta['newentrytitle'] = array('string');
$meta['dateprefix']   = array('string');
$meta['sortkey']      = array('multichoice',
                        '_choices' => array('cdate', 'mdate', 'pagename', 'id', 'title'));
$meta['sortorder']    = array('multichoice',
                         '_choices' => array('ascending', 'descending'));
$meta['excluded_pages'] = array('string');

$meta['showhistogram'] = array('onoff');
$meta['max_months'] = array('numeric');
$meta['histogram_height'] = array('numeric');
// vim:ts=4:sw=4:et:enc=utf-8:
