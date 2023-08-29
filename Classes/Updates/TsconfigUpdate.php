<?php

namespace Taketool\Migrate\Updates;

use Doctrine\DBAL\DBALException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

class TsconfigUpdate implements UpgradeWizardInterface
{
    protected string $title = 'Taketool Migrate add TSconfig gallery extension';

    protected string $description =
        'This wizard adds 2 lines of TSconfig to every root page ' .
        'to make gallery options in image objects available';

    private array $rootPages;

    /**
     * Return the identifier for this wizard
     * This should be the same string as used in the ext_localconf class registration
     *
     * @return string
     */
    public function getIdentifier(): string {
        return 'tool_tsconfig';
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
        $this->getAllRootPages();

        $tableName = 'pages';
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName);

        foreach($this->rootPages as $pageUid)
        {
            $tsConfig = $connection
                ->fetchAssoc("SELECT TSconfig FROM $tableName WHERE uid=".$pageUid);
            $newTsConfig = "'"
                . $tsConfig['TSconfig']
                . "\nTCEFORM.tt_content.frame_layout.addItems.galerie = Galerie"
                . "\nTCEFORM.tt_content.frame_layout.addItems.rollover_image = Bild mit Hover-Effekt'";
            //\nn\t3::debug($newTsConfig); die();
            $connection
               ->executeQuery("UPDATE $tableName SET `TSconfig`=" . $newTsConfig . " WHERE uid=".$pageUid);
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

}

