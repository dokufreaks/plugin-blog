<?php
/**
 * Options for the Blog Plugin
 */
$conf['namespace']        = 'blog';  // default location for blog entries
$conf['sortkey']          = 'cdate'; // sort key for blog entries
$conf['dateprefix']       = '';      // prefix date to new entry IDs
$conf['firstseconly']     = 0;       // limit entries on main blog page to first section
$conf['showtaglogos']     = 0;       // display image for first tag
$conf['showlink']         = 1;       // display permalink below blog entries
$conf['showdate']         = 1;       // display date below blog entries
$conf['showuser']         = 1;       // display username below blog entries
$conf['user_namespace']   = 'user';  // namespace for user pages
$conf['archive_showdate'] = 1;       // display date in achives
$conf['archive_showuser'] = 1;       // display username in archives

//Setup VIM: ex: et ts=2 enc=utf-8 :