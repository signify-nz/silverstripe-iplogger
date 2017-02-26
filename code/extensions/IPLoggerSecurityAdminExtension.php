<?php
/**
 * Provides a CMS interface to administer {@link IPLoggerEntry} {@link IPLoggerBan}.
 *
 * @package silverstripe-iplogger
 */
class IPLoggerSecurityAdminExtension extends LeftAndMainExtension
{
    private static $dependencies = array(
        'loggerService' => '%$IPLoggerService',
        'loggerEntry'   => '%$IPLoggerEntry',
        'banEntry'      => '%$IPBanEntry'
    );

    public $loggerService;
    
    public $loggerEntry;

    public $banEntry;
    
    public function updateEditForm($form)
    {
        if (Permission::check('ADMIN')) {
            $fields = $form->Fields();

            $loggerTab = $fields->findOrMakeTab('Root.IPLogs', 'IP Logs');
        
            // List ban entries
            $banClass = get_class($this->banEntry);
        
            $banEntries = $banClass::get()->sort('Created DESC');

            $banGrid = GridField::create('BanGrid', 'Bans', $banEntries);

            $banGrid->setForm($form);

            $banGridConfig = GridFieldConfig_RecordEditor::create();
            $banGridConfig->removeComponentsByType('GridFieldAddNewButton');

            // Add a new sudo column to show whether or not a ban is active
            $dataColumns = $banGridConfig->getComponentByType('GridFieldDataColumns');

            $displayFields = $dataColumns->getDisplayFields($banGrid);

            $displayFields = array('Active' => '') + $displayFields;

            $dataColumns->setDisplayFields($displayFields);

            $loggerService = $this->loggerService;
            $dataColumns->setFieldFormatting(
                array(
                    'Active' => function ($value, $item) use ($loggerService) {
                        $banSecondsAgo = $item->obj('Created')->TimeDiffIn('seconds');
                        $banSeconds = $loggerService->getRule($item->Event)['bantime'];

                        $active = $banSeconds === 0 || $banSecondsAgo < $banSeconds;

                        $colour = 'rgb(255, 0, 0)';
                        $value = 'Expired';
                        if ($active) {
                            $colour = 'rgb(76, 153, 0)';
                            $value = 'Active';
                        }

                        $html = "<strong style='color: {$colour};
font-size: 120%;'>{$value}</strong>";

                        return $html;
                    }
                )
            );

            $banGrid->setConfig($banGridConfig);

            $loggerTab->push($banGrid);

            // List log entries
            $entryClass = get_class($this->loggerEntry);
        
            $logEntries = $entryClass::get()->sort('Created DESC');

            $entryGrid = GridField::create('EntryGrid', 'Log Entries', $logEntries);

            $entryGrid->setForm($form);

            $entryGridConfig = GridFieldConfig_RecordEditor::create();
            $entryGridConfig->removeComponentsByType('GridFieldAddNewButton');

            $entryGrid->setConfig($entryGridConfig);

            $loggerTab->push($entryGrid);
        }
    }
}
