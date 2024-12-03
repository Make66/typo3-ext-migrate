<?php
namespace Taketool\Migrate\Updates\V76;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

//use TYPO3\CMS\Install\Attribute\UpgradeWizard;

/**
 * Migrate CType 'textpic' to 'textmedia' for extension 'frontend'
 */
//#[UpgradeWizard('textpicToTextMedia')]
class TextpicToTextMediaUpdate extends AbstractContentTypeToTextMediaUpdate
{

    protected string $CType = 'textpic';

    function getIdentifier(): string
    {
        return 'migrate_textpicToTextMedia';
    }

}
