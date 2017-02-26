<?php
/**
 * Provides a {@link DataObject} to store a ban against a specififc IP.
 *
 * @package silverstripe-iplogger
 */
class IPBanEntry extends DataObject
{
    private static $db = array(
        'Event'   => 'Varchar(255)',
        'IP'      => 'Varchar(255)'
    );

    private static $default_sort = 'Created';

    private static $summary_fields = array(
        'Created',
        'IP',
        'Event'
    );

    public function Title()
    {
        return $this->Event;
    }
}
