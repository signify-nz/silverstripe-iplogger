<?php
/**
 * Provides a central point via which code can use to log and review events.
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
     * @return string The current clients IP address.
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
     * @param string $event The event.
     * @return DataList A list of logged events.
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
     *
     * @param string $event The event being logged.
     * @return true|string Whether the client has had too many of $event logged
     *         against their IP. If they have exceeded the limit return ban time.
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
