<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 3.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at the following url:           |
// | http://www.php.net/license/3_0.txt.                                  |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Ian Eure <ieure@php.net>                                     |
// +----------------------------------------------------------------------+
//
// $Id$

require_once 'System/Mount.php';

// Create the mount class
$m = &new System_Mount();

// Get an object representing the CD-ROM entry
$cdrom = &$m->getEntryForPath('/cdrom');

if (PEAR::isError($cdrom)) {
    die($cdrom->message."\n");
}

// Mount it
$res = $cdrom->mount();
if (PEAR::isError($res)) {
    die($res->getMessage()."\n");
}

// List it's contents
print `ls {$cdrom->mountPoint}`;

// Unmount it
$cdrom->unmount();
if (PEAR::isError($res)) {
    die($res->getMessage()."\n");
}

?>