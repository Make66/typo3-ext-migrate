<?php

namespace Taketool\Migrate\Updates;

use Doctrine\DBAL\DBALException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

class GridelementsUpdate implements UpgradeWizardInterface
{
    protected string $title = 'Taketool Migrate migrate Gridelements';

    protected string $description =
        'This wizard migrates deprecated include-static-templates for ' .
        'gridelements and bootstrap_grids in root templates';

    private array $rootPages;

    /**
     * Return the identifier for this wizard
     * This should be the same string as used in the ext_localconf class registration
     *
     * @return string
     */
    public function getIdentifier(): string {
        return 'tool_gridelements';
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

        $oldInclude1 = 'EXT:gridelements/Configuration/TypoScript/';
        $newInclude1 = 'EXT:gridelements/Configuration/TypoScript/DataProcessingLibContentElement';
        $oldInclude2 = 'EXT:bootstrap_grids/Configuration/TypoScript';
        $newInclude2 = 'EXT:bootstrap_grids/Configuration/TypoScript/DataProcessingLibContentElement/';

        $this->getAllRootPages();
        $rootTemplates = $this->getAllRootTemplates();
        foreach ($rootTemplates as $template)
        {
            $altered = false;
            $includeStaticFiles = explode(',', $template['include_static_file']);
            if ($key = array_search($oldInclude1, $includeStaticFiles))
            {
                $includeStaticFiles[$key] = $newInclude1;
                $altered = true;
            }
            if ($key = array_search($oldInclude2, $includeStaticFiles))
            {
                $includeStaticFiles[$key] = $newInclude2;
                $altered = true;
            }
            if ($altered)
            {
                $templateUid = $template['uid'];
                $includeStaticFiles = implode(',', $includeStaticFiles);
                //\nn\t3::debug(['altered'=>$altered,'uid'=>$template['pid'], 'include_static_file'=>$includeStaticFiles]);die();
                $connection->executeQuery("UPDATE $tableName SET include_static_file='$includeStaticFiles' WHERE uid=$templateUid");
            }
        }
        return true;
    }

    public function updateNecessary(): bool
    {
        // TODO: Implement updateNecessary() method.
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

}

