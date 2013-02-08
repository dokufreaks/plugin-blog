<?php
/**
 * Options for the Blog Plugin
 */
$conf['namespace']    = 'blog';       // default location for blog entries
$conf['formposition'] = 'bottom';     // position of new entry form
$conf['newentrytitle'] = '';          // Title text for the 'new entry' form
$conf['dateprefix']   = '';           // prefix date to new entry IDs
$conf['sortkey']      = 'cdate';      // sort key for blog entries
$conf['sortorder']    = 'descending'; // ascending or descending

$conf['showhistogram'] = 1;           // show histogramm in archive
$conf['max_months']    = 100;         // max months to show in the histogram
$conf['histogram_height'] = 50;       // height of the histogram in pixels

$conf['excluded_pages'] = '!^blog:\d{4}(:\d{2})?$!'; // regex for pages to exclude from bloglisting

// vim:ts=4:sw=4:et:enc=utf-8:
