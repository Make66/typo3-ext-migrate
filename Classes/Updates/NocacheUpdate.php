<?php

namespace Taketool\Migrate\Updates;

use Doctrine\DBAL\DBALException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

class NocacheUpdate implements UpgradeWizardInterface
{
    private string $search = 'config.no_cache=1';

    protected string $title = 'Taketool Migrate remove config.no_cache=1';

    protected string $description =
        'This wizard removes no_cache=1 in all templates';

    private array $rootPages;
    private array $allTemplates;

    /**
     * Return the identifier for this wizard
     * This should be the same string as used in the ext_localconf class registration
     *
     * @return string
     */
    public function getIdentifier(): string {
        return 'tool_nocache';
    }

    /**
     * Return the speaking name of this wizard
     *
     * @return string
     */
    public function getTitle(): string {
        return $this->title;
    }

    /**
     * Return the description for this wizard
     *
     * @return string
     */
    public function getDescription(): string {
        return $this->description;
    }

    /**
     * Execute the update
     *
     * Called when a wizard reports that an update is necessary
     *
     * @return bool
     * @throws DBALException
     */
    public function executeUpdate(): bool
    {
        $tableName = 'sys_template';
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName);

        $search = 'config.no_cache=1';

        foreach ($this->getAllTemplates() as $template)
        {
            $newConstants = '';
            $newSetup = '';
            $altered = false;
            if ($template['constants'] != '')
            if (strpos($this->search, $template['constants'])!==false)
            {
                $newConstants = str_replace($this->search, '', $template['constants']);
                $altered = true;
            }
            if ($template['config'] != '')
            if (strpos($this->search, $template['config'])!==false)
            {
                $newSetup = str_replace($this->search, '', $template['config']);
                $altered = true;
            }
            if ($altered)
            {
                $templateUid = $template['uid'];
                $connection->executeQuery("UPDATE $tableName SET constants='$newConstants', config='$newSetup' WHERE uid=$templateUid");
            }
        }
        return true;
    }

    public function updateNecessary(): bool
    {
        /*
        $this->allTemplates = $this->getAllTemplates();
        foreach ( $this->allTemplates as $template) {
            if (strpos($template['constants'], $this->search)===false) {
            } else {
                return true;
            }
            if (strpos($template['config'], $this->search)===false) {
            } else {
                return true;
            }
        }
        */
        return true;
    }

    public function getPrerequisites(): array
    {
        // TODO: Implement getPrerequisites() method.
        return [];
    }

    private function getAllRootPages()
    {
        $tableName = 'pages';
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName);

        /*
         * pages becomes an array of [n] => ['uid' => pid]
         */
        $pages = $connection->fetchAll("SELECT uid FROM $tableName WHERE is_siteroot=1");
        $rootPages = [];
        //\nn\t3::debug($pages);
        foreach ($pages as $page)
        {
            $rootPages[] = $page['uid'] ;
        }
        $this->rootPages = $rootPages;
        //\nn\t3::debug($rootPages);die();
    }

    private function getAllRootTemplates()
    {
        $tableName = 'sys_template';
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName);

        $rootPageIds = implode(',',$this->rootPages);
        $rootTemplates = $connection->fetchAll("SELECT * FROM $tableName WHERE pid IN ($rootPageIds)");
        //\nn\t3::debug($rootTemplates);

        return $rootTemplates;
    }

    private function getAllTemplates()
    {
        $tableName = 'sys_template';
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName);
        $allTemplates = $connection->fetchAll("SELECT * FROM `$tableName`");
        //\nn\t3::debug($allTemplates);

        return $allTemplates;
    }

}

