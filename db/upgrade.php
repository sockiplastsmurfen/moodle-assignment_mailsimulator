<?php

function xmldb_assignment_type_mailsimulator_upgrade($oldversion=0) {
    global $CFG, $THEME, $db;

    $result = true;

    if ($result && $oldversion < 2011042800) {

        /// Define table assignment_mailsimulator_filetypes to be created
        $table = new XMLDBTable('assignment_mailsimulator_filetypes');

        /// Adding fields to table assignment_mailsimulator_filetypes
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('assignment', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('filetype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);

        /// Adding keys to table assignment_mailsimulator_filetypes
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

        /// Launch create table for assignment_mailsimulator_filetypes
        $result = $result && create_table($table);
    }

    return $result;
}

?>
