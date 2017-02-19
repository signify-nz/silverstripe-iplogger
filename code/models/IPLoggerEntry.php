<?php
/**
 * Provides a {@link DataObject} to store an event against an IP.
 *
 * @package silverstripe-iplogger
 */
class IPLoggerEntry extends DataObject
{
    private static $db = array(
        'Event' => 'Varchar(255)',
        'IP'    => 'Varchar(255)'
    );
}
