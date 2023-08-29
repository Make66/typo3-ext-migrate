<?php

namespace Taketool\Migrate\Updates;

use Doctrine\DBAL\DBALException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

class GalleryUpdate implements UpgradeWizardInterface
{
    protected string $title = 'Taketool Migrate tt_content Gallery';

    protected string $description =
        'This wizard migrates all tt_content records that contain galerie in field layout, and set field ' .
        'layout to 0 and frame_layout to galerie';


    /**
     * Return the identifier for this wizard
     * This should be the same string as used in the ext_localconf class registration
     *
     * @return string
     */
    public function getIdentifier(): string {
        return 'tool_gallery';
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
        $tableName = 'tt_content';
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName);

        $galleries = $connection->fetchAll("SELECT uid,layout,frame_layout FROM tt_content WHERE layout='galerie'");

        foreach($galleries as $gallery)
        {
            //\nn\t3::debug($gallery); die();
            executeQuery("UPDATE $tableName SET layout='0', frame_layout='galerie' WHERE uid=".$gallery['uid']);
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

}

