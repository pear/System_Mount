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

require_once 'PEAR.php';
require_once 'File/Fstab.php';
require_once 'System/Command.php';

/**
 * Mount and unmount devices in fstab
 *
 * $sm = &new System_Mount();
 * $cdrom = &$sm->getEntryForPath('/cdrom');
 * $cdrom->mount();
 * ...
 * $cdrom->unMount();
 * 
 * @package @package@
 * @version @version@
 * @author Ian Eure <ieure@php.net>
 * @copyright Copyright &copy; 2004, Ian Eure
 * @license http://www.php.net/license/3_0.txt PHP License 3.0
 * @link http://atomized.org/PEAR/System_Mount/
 */
class System_Mount extends File_Fstab {
    /**
     * Default options
     *
     * @var array
     */
    var $_defaultMountOptions = array(
        'entryClass' => "System_Mount_Entry",
        'mtabFile' => "/etc/mtab",
        'mountCmd' => "mount",
        'umountCmd' => "umount"
    );


    /**
     * Constructor
     *
     * @param $options array Class options
     * @see File_Fstab::setOptions()
     * @return void
     */
    function System_Mount($options = false)
    {
        $pc = get_parent_class($this);

        // This is a bit ugly.
        if (!$options) {
            $options = array();
        }
        $opts = array_merge($this->_defaultMountOptions, $options);

        // Get a static mtab instance
        $mtab = &PEAR::getStaticProperty('@package@', 'mtab');
        $mtab = File_Fstab::singleton($opts['mtabFile']);

        parent::$pc($opts);

        // Make sure the entryClass knows how to mount/unmount
        $options = &PEAR::getStaticProperty('@package@', 'options');
        $options = $this->options;
    }
}

/**
 * Class which handles the mount/unmount operation
 *
 * This is the heart of System_Mount- the main class exists only to instruct
 * File_Fstab to use this class, making it easier on the end-user.
 *
 * @package @package@
 * @version @version@
 * @author Ian Eure <ieure@php.net>
 * @copyright Copyright &copy; 2004, Ian Eure
 * @license http://www.php.net/license/3_0.txt PHP License 3.0
 */
class System_Mount_Entry extends File_Fstab_Entry {
    /**
     * File_Fstab instance
     *
     * This contains an instance of File_Fstab (created with
     * {@link File_Fstab::singleton()} so we don't waste memory) which parses
     * /etc/mtab and holds the current device mount state.
     *
     * @var object
     * @see File_Fstab
     * @access protected
     */
    var $_mtab;

    /**
     * System_Mount options
     *
     * This contains the options passed to System_Mount when it was instantiated
     *
     * @var array
     * @see System_Mount::System_Mount()
     * @access protected
     */
    var $_smOptions;
    

    /**
     * Constructor
     *
     * @param $entry fstab entry
     * @see File_Fstab::File_Fstab()
     * @return void
     */
    function System_Mount_Entry($entry = false)
    {
        $this->_smOptions = &PEAR::getStaticProperty('@package@', 'options');
        $this->_mtab = &PEAR::getStaticProperty('@package@', 'mtab');

        // Thunk to parent's constructor
        $pc = get_parent_class($this);
        return parent::$pc($entry);
    }

    /**
     * Is this device mounted?
     *
     * @return boolean true if mounted, false if unmounted
     */
    function isMounted()
    {
        $this->_mtab->load();
        PEAR::pushErrorHandling(PEAR_ERROR_RETURN);
        $ent = $this->_mtab->getEntryForPath($this->mountPoint);
        PEAR::popErrorHandling();

        if (PEAR::isError($ent)) {
            return false;
        }
        
        return true;
    }

    /**
     * May a user mount this device?
     *
     * @return boolean true if user may mount, false if not
     */
    function userMayMount()
    {
        return $this->hasMountOption('user');
    }


    /**
     * May this device be mounted?
     *
     * Similar to userMayMount(), but this takes the UID the script is running as
     * in to account, and returns 'true' if the current UID is 0, or the result of
     * userMayMount otherwise.
     *
     * @see userMayMount()
     * @return boolean true if user may mount, false if not
     */
    function mayMount()
    {
        // Root may mount anything
        if (posix_getuid() == 0) {
            return true;
        }

        return $this->userMayMount();
    }

    /**
     * Mount the device
     *
     * @return mixed boolean or PEAR_Error
     * @see _mount()
     */
    function mount()
    {
        if ($this->isMounted()) {
            return PEAR::raiseError("{$this->mountPoint} is already mounted");
        }
            
        return $this->_mount($this->_smOptions['mountCmd']);
    }

    /**
     * Unmount the device
     *
     * @return mixed boolean true on success, PEAR_Error otherwise
     * @see _mount()
     */
    function unMount()
    {
        if (!$this->isMounted()) {
            return PEAR::raiseError("{$this->mountPoint} is not mounted");
        }
        return $this->_mount($this->_smOptions['umountCmd']);
    }

    /**
     * Dispatch an (un)mount command
     *
     * @param int $command SYSTEM_MOUNT_CMD_MOUNT (to mount device) or _UNMOUNT
     *                     (to unmount device)
     * @return return value from System_Command or PEAR_Error
     * @see System_Command::execute()
     * @access protected
     */
    function _mount($command)
    {
        if (!$this->mayMount()) {
            return PEAR::raiseError("Users may not (un)mount {$this->mountPoint}");
        }

        $cmd = new System_Command;
        $cmd->pushCommand($command, $this->mountPoint);
        $res = $cmd->execute();

        // Update mtab
        $this->_mtab->load();

        return $res;
    }
}
?>