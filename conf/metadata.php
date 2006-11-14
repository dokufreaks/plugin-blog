<?php
/**
 * Metadata for configuration manager plugin
 * Additions for the blog plugin
 *
 * @author    Esther Brunner <wikidesign@gmail.com>
 */
$meta['namespace']        = array('string');
$meta['sortkey']          = array(
                             'multichoice',
                             '_choices' => array('cdate', 'pagename', 'id')
                            );
$meta['dateprefix']       = array('string');
$meta['firstseconly']     = array('onoff');
$meta['showlink']         = array('onoff');
$meta['showdate']         = array('onoff');
$meta['showuser']         = array('onoff');
$meta['user_namespace']   = array('string');
$meta['archive_showdate'] = array('onoff');
$meta['archive_showuser'] = array('onoff');

//Setup VIM: ex: et ts=2 enc=utf-8 :
