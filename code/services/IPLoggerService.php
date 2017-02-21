<?php
/**
 * Provides a central point via which code can log and review events.
 * Ideally this service should be injected using SilverStripe's injector.
 *
 * @package silverstripe-iplogger
 * @see https://docs.silverstripe.org/en/3.1/developer_guides/extending/injector/
 */
class IPLoggerService extends Object
{
    private static $rules = array();

    private static $delete_expired = false;
    
    private static $dependencies = array(
        'loggerEntry' => '%$IPLoggerEntry',
        'banEntry'    => '%$IPBanEntry'
    );

    protected $rule = null;

    public function __construct()
    {
    }

    /**
     * Returns the IP address of the current client; relies on
     * {@link SS_HTTPRequest} to provide the IP address.
     *
     * @return string The current clients IP address
     */
    public function getIP()
    {
        $request = Controller::curr()->getRequest();

        return $request->getIP();
    }

    /**
     * Logs an event against a clients IP address.
     *
     * @param $event string The event being logged.
     */
    public function log($event)
    {
        $entry = $this->loggerEntry;

        $entry->IP = $this->getIP();
        $entry->Event = $event;

        $entry->write();
    }

    /**
     * Get's an array of logs related to the clients IP and the supplied event.
     *
     * @param string $event The event
     * @return DataList A list of logged events
     */
    public function getEntries($event)
    {
        $entryClass = get_class($this->loggerEntry);

        //$this->pruneEntries($event);
        
        $entries = $entryClass::get()->filter(
            array(
                'Event' => $event,
                'IP' => $this->getIP()
            )
        );

        return $entries;
    }

    /**
     * If a rule exists for a specific event; return it.
     *
     * Any rules found are checked for validity and an error thrown if incorrect.
     * Rules should be defined in .yml config files using the following format.
     *
     * <code>
     * IPLoggerService:
     *   rules:
     *     submit_contact_form:
     *       findtime: 60
     *       hits: 4
     *       bantime: 600
     * </code>
     *
     * @param string $event The event we want a rule for
     * @return array|null The rule array
     */
    public function getRule($event)
    {
        $config = $this->config();

        $rules = $config->get('rules');

        $rule = null;
        
        if (isset($rules[$event])) {
            $rule = $rules[$event];
            
            // If the rule for this event is malformed throw an Exception;
            if (!(isset($rule['bantime']) && isset($rule['findtime']) && isset($rule['hits']))) {
                throw new Exception(
                    'Rule must contain the keys bantime, findtime and hits.'
                );
            }
        }

        return $rule;
    }

    /**
     * Get a date x seconds ago.
     *
     * @param integer $seconds The number of seconds to subtract
     * @return DateTime
     */
    public function getPastDate($seconds)
    {
        $interval = new DateInterval('PT' . $seconds  . 'S');
        $interval->invert = 1;

        $pastDate = new DateTime();
        $pastDate->add($interval);

        return $pastDate;
    }
    
    public function pruneEntries($event)
    {
        $config = $this->config();
        
        $entryClass = get_class($this->loggerEntry);

        $rule = $this->getRule($event);

        if ($rule) {
            $entries = $entryClass::get()->filter(
                array(
                    'Created:LessThan' => $minTime,
                    'Event' => $minTime->format('c'),
                    'IP' => $this->getIP()
                )
            );

            if ($entries) {
                $deleteExpired = $config->get('delete_expired');
            
                foreach ($entries as $entry) {
                    if ($deleteExpired) {
                        $entry->delete();
                    } else {
                        $entry->Expired = true;
                    }
                }
            }
        }
    }
    
    /**
     * Checks if a specific client IP is allowed to perform an event
     *
     * First if there is no rule supplied for an event string, we assume
     * that user can perform this event as many times as needed and it should
     * be logged but not restricted.
     * Next a check if performed to see if a client IP has been banned from
     * performing an event.
     * Finally we calculate the total number of logs for an event and check
     * these are within the limits set by the rules. If they are not withing
     * the limit apply a ban.
     *
     * @param string $event The event to check
     * @return true|string Is the client allowd to perform $event
     */
    public function checkAllowed($event)
    {
        $rule = $this->getRule($event);

        // If no rule is set there must be no limit to how often an event can
        // happen.
        if (!$rule) {
            return true;
        }

        $maxDate = $this->getPastDate($rule['bantime'])->format('c');
        
        $banClass = get_class($this->banEntry);
        $bans = $banClass::get()->filter(
            array(
                'Created:GreaterThan' => $maxDate,
                'Event'   => $event,
                'IP'      => $this->getIP()
            )
        );

        // Check if a ban exists.
        if ($bans->count() > 0) {
            return false;
        }

        $maxDate = $this->getPastDate($rule['findtime'])->format('c');
        
        $entries = $this->getEntries($event)->filter(
            array(
                'Created:GreaterThan' => $maxDate
            )
        );

        // If there are no log entries the client must not have triggered this
        // event before, so let it happen.
        if (!$entries) {
            return true;
        }

        // Check if the number of entries is greater than the number of hits
        // allowed in findtime.
        if ($entries->count() > $rule['hits']) {
            $banEntry = $this->banEntry;
            $banEntry->IP = $this->getIP();
            $banEntry->Event = $event;
            $banEntry->write();

            return false;
        }

        return true;
    }
}
