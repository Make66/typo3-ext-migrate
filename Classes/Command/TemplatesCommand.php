<?php
namespace Taketool\Migrate\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

/*
 * all root templates:
 *  - replace Gridelements (deprecated) with Gridelements (recommended) in field include_static_file
 *  - replace Grids for Bootstrap (deprecated) with Gridelements (recommended) in field include_static_file
 * all tt_content records
 *  - move tt_content element layout to frame_layout
 * all root pages
 *  - add 2 new frame_layout to page TSconfig
 *
 * CLI from domain root: ./typo3/sysext/core/bin/typo3 tool:mig_cotasx2tool
 */

class TemplatesCommand extends Command
{
    //protected $objectManager;
    //protected $configRepository;
    protected static $defaultName = 'migrate:templates';  //To make your command lazily loaded
    protected \mysqli $db;
    protected SymfonyStyle $io;
    private string $publicPath;
    private array $rootPages;
    private array $rootTemplates;


    /**
     * Executes the command
     * cli> typo3/sysext/core/bin/typo3 tool:mig_v9v10
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int error code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title($this->getDescription());
        $this->io->writeln(str_repeat('=', 65));

        // check db connection
        $environment = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Core\Environment::class);
        /*
        $this->io->writeln('projectPath: ' . $environment->getProjectPath());
        $this->io->writeln('publicPath: ' . $environment->getPublicPath());
        $this->io->writeln('configPath: ' . $environment->getConfigPath());
        $this->io->writeln('extensionsPath: ' . $environment->getExtensionsPath());
        $this->io->writeln('varPath: ' . $environment->getVarPath());
        $this->io->writeln('context: ' . $environment->getContext());
        $this->io->writeln('composerMode: ' . ($environment->isComposerMode()?'true':'false'));

            projectPath: /home/test.taketool.eu
            publicPath: /home/test.taketool.eu/public
            configPath: /home/test.taketool.eu/config
            extensionsPath: /home/test.taketool.eu/public/typo3conf/ext
            varPath: /home/test.taketool.eu/var
            context: Production
            composerMode: true
         */
        return 0;

        // becomes s/t like "/home/test.taketool.eu"
        $this->publicPath = $environment->getPublicPath();
        $localConfPath = $this->publicPath.'/typo3conf/LocalConfiguration.php';

        $LC = include $localConfPath;
        $this->io->writeln('Connecting to DB '.$LC['DB']['Connections']['Default']['dbname']);
        $this->db = new \mysqli(
            $LC['DB']['Connections']['Default']['host'],
            $LC['DB']['Connections']['Default']['user'],
            $LC['DB']['Connections']['Default']['password'],
            $LC['DB']['Connections']['Default']['dbname']
        );
        if ($this->db->connect_error) {
            $this->io->writeln(' -> ERROR ');
            return 1;
        } else $this->io->writeln(' -> SUCCESS ');

        // init
        $this->getAllRootPages();

        // migration
        $this->includeStatics();
        $this->includeCss();
        $this->themeCSS();
        // v12: $this->layoutGallery();
        // v12: $this->tsConfig();

        return 0; // T3v10: Command::SUCCESS;
    }

    private function getAllRootPages()
    {
        $res = $this->db->query("SELECT uid FROM pages WHERE is_siteroot=1");
        $this->io->writeln($this->db->error);
        $rootPages = [];
        foreach ($res->fetch_all() as $pages)
        {
            $rootPages[] = $pages[0] ;
        }
        $this->rootPages = $rootPages;
    }

    /**
     * needs to be refreshed before using
     *
     * @return void
     */
    private function getAllRootTemplates()
    {
        $rootPageIds = implode(',',$this->rootPages);
        $res = $this->db->query("SELECT * FROM sys_template WHERE pid IN ($rootPageIds)");
        $this->io->writeln($this->db->error);
        $this->rootTemplates = $res->fetch_all(MYSQLI_ASSOC);
    }

    private function getAllRootTemplatesCleanedUp($pattern)
    {
        $rootPageIds = implode(',',$this->rootPages);
        $this->io->writeln("SELECT uid, pid, REGEXP_REPLACE(config, '$pattern', '') as config_cleaned, constants FROM sys_template WHERE pid IN ($rootPageIds)");
        $res = $this->db->query("SELECT uid, pid, REGEXP_REPLACE(config, '$pattern', '') as config_cleaned, constants FROM sys_template WHERE pid IN ($rootPageIds)");
        $this->io->writeln($this->db->error);
        $rootTemplatesCleanedUp = $res->fetch_all(MYSQLI_ASSOC);
        $this->io->writeln('First root template=');
        $this->io->writeln(($rootTemplatesCleanedUp[0]['config_cleaned'])); die();
        return $rootTemplatesCleanedUp;
    }

    /**
     * for all root-templates, replace deprecated include scripts
     * (not implemented: if $oldInclude2 is not there, add $newInclude2)
     */
    private function includeStatics()
    {
        $this->io->writeln('Migrating root templates for gridElements');
        $oldInclude1 = 'EXT:gridelements/Configuration/TypoScript/';
        $newInclude1 = 'EXT:gridelements/Configuration/TypoScript/DataProcessingLibContentElement';
        $oldInclude2 = 'EXT:bootstrap_grids/Configuration/TypoScript';
        $newInclude2 = 'EXT:bootstrap_grids/Configuration/TypoScript/DataProcessingLibContentElement/';

        $rootTemplates = $this->getAllRootTemplates();
        $cnt = 0;
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
                $res = $this->db->query("UPDATE sys_template SET include_static_file='$includeStaticFiles' WHERE uid=$templateUid");
                if ($res === false) $this->io->writeln($this->db->error);
            }
            $cnt++;
        }
        $this->io->writeln('  -> DONE ('.$cnt.' elements)'."\n");
    }


    /*
     * for all root-templates, remove page.includeCSS
     * this is now part of the base-template
     */
    private function includeCss()
    {
        $this->io->writeln('Migrating root templates for removing includeCss');
        //$pattern1 = '/(page.includeCSS {)[\s\S]*(}\n)/';  // https://regex101.com/library
        $l1 = 'styles = fileadmin/templates/_bootstrap_package/Resources/Public/Scss/Theme/global.scss';
        $l2 = 'styles =  fileadmin/templates/_bootstrap_package/Resources/Public/Scss/Theme/global.scss';
        $l3 = 'customTemplate = fileadmin/templates/{$mandant}/bootstrap_package/Resources/Public/Scss/Theme/custom.scss';

        $rootTemplates = $this->getAllRootTemplates();
        $cnt = 0;
        foreach ($rootTemplates as $template)
        {
            $newConstants = str_replace('_img/', 'img/', $template['constants']);

            /* this never works; neither in php nor in MariaDB
            $newConfig = preg_replace($pattern1, '', $template['config'],1);
            //$newConfig = preg_replace($pattern2, "\n", $temp);
            $this->io->writeln($template['pid'].':'.'newConfig=' ."\n" . $newConfig); die();
            */

            $tempConfig = str_replace('  ', ' ', $template['config']);
            $tempConfig = str_replace($l1, '', $tempConfig);
            $tempConfig = str_replace($l2, '', $tempConfig);
            $tempConfig = str_replace($l3, '', $tempConfig);
            $tempConfig = str_replace('  ', ' ', $tempConfig);
            $tempConfig = str_replace('  ', ' ', $tempConfig);
            $tempConfig = str_replace("\n\n", "\n", $tempConfig);
            $newConfig = str_replace(" \n", "", $tempConfig);

            $altered = $newConfig != $template['config'] || $newConstants != $template['constants'];
            if ($altered)
            {
                $templateUid = $template['uid'];
                $sql = "UPDATE sys_template SET config='$newConfig', constants='$newConstants' WHERE uid=$templateUid";
                $res = $this->db->query($sql);
                //if ($res === false) $this->io->writeln(str_repeat('=',64)."\npid=".$template['pid']."\nERROR=".$this->db->error. "\n\noriginal SQL=\n" . $sql);
                if ($res === false) $this->io->writeln("  -> ERROR in template on pid=".$template['pid']);
            }
            $cnt++;
        }
        $this->io->writeln('  -> DONE ('.$cnt.' elements)'."\n");
    }

    /*
     * all tt_content records that contain 'galerie' in field layout,
     * set field layout to '0' and frame_layout to 'galerie'
     */
    private function layoutGallery()
    {
        return;
        $this->io->writeln('nur Boostrap_Package 12.x: Migrating tt_content layoutGallery()');
        $res = $this->db->query("SELECT uid,layout,frame_layout FROM tt_content WHERE layout='galerie'");
        $galleries = $res->fetch_all(MYSQLI_ASSOC);
        $cnt = 0;
        foreach($galleries as $gallery)
        {
            //\nn\t3::debug($gallery); die();
            $res = $this->db->query("UPDATE tt_content SET layout='0', frame_layout='galerie' WHERE uid=".$gallery['uid']);
            if ($res === false) $this->io->writeln($this->db->error);
            $cnt++;
        }
        $this->io->writeln('  -> DONE ('.$cnt.' elements)'."\n");
    }

    /*
     * add line 'TCEFORM.tt_content.frame_layout.addItems.galerie = Galerie' to page TSconfig
     * add line 'TCEFORM.tt_content.frame_layout.addItems.rollover_image = Bild mit Hover-Effekt' to page TSconfig
     */
    private function tsConfig()
    {
        return;
        $this->io->writeln('nur Boostrap_Package 12.x: Migrating TSconfig adding galerie and rollover_image types');
        $cnt = 0;

        foreach($this->rootPages as $pageUid)
        {
            $res = $this->db->query("SELECT TSconfig FROM pages WHERE uid=".$pageUid);
            $tsConfig = $res->fetch_assoc();
            $newTsConfig = "'"
                . $tsConfig['TSconfig']
                . "\nTCEFORM.tt_content.frame_layout.addItems.galerie = Galerie"
                . "\nTCEFORM.tt_content.frame_layout.addItems.rollover_image = Bild mit Hover-Effekt'";
            $sql = "UPDATE pages SET `TSconfig`=" . $newTsConfig . " WHERE uid=".$pageUid;
            //\nn\t3::debug($newTsConfig); die();
            //$this->io->writeln($sql); die();

            $res = $this->db->query($sql);
            if ($res === false) $this->io->writeln($this->db->error);
            $cnt++;
        }
        $this->io->writeln('  -> DONE ('.$cnt.' elements)'."\n");
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setHelp('Migrates various T3v9 content settings to match v10 changes');
    }

    /**
     * create a file theme.css under each templates/mandant/ directory
     * and fills it with three lines of code:
     *  @ import "../../../typo3conf/ext/bootstrap_package/Resources/Public/Scss/Theme/theme";
     *  @ import "../bootstrap_package/Resources/Public/Scss/Theme/global";
     *  @ import "bootstrap_package/Resources/Public/Scss/Theme/custom";
     */
    private function themeCSS()
    {
        $this->io->writeln('Create a theme.css for every mandant');
        $path = $this->projectPath . '/public/fileadmin/templates';
        $d = dir($path);

        $customToDelete = [
            '@import "typo3conf/ext/bootstrap_package/Resources/Public/Contrib/bootstrap4/scss/_functions.scss";',
            '@import "typo3conf/ext/bootstrap_package/Resources/Public/Contrib/bootstrap4/scss/_variables.scss";',
            '@import "typo3conf/ext/bootstrap_package/Resources/Public/Contrib/bootstrap4/scss/mixins/_breakpoints.scss";',
        ];
        $imports =
             '@import "../../../typo3conf/ext/bootstrap_package/Resources/Public/Scss/Theme/theme";'."\n"
             .'@import "../bootstrap_package/Resources/Public/Scss/Theme/global";'."\n"
             .'@import "bootstrap_package/Resources/Public/Scss/Theme/custom";'."\n";

        $this->io->writeln( "     Path: " . $d->path);
        $cnt = 0;
        while (false !== ($mandant = $d->read())) {

            // create theme.scss
            $themePath = $path.'/'.$mandant;
            $skipDot = substr($mandant,0,1) === '.';
            $skipUl = substr($mandant,0,1) === '_';
            $skipTx = substr($mandant,0,3) === 'tx_';
            if ($skipUl || $skipDot || $skipTx) continue;
            if (file_exists($themePath))
            {
                $filepath = $themePath.'/theme.scss';
                $this->io->writeln('     writing to '.$filepath);
                $handle = fopen($filepath, "w");
                if ($handle === false)
                {
                    $this->io->writeln('   ! error opening file '.$filepath);
                    continue;
                }
                fwrite($handle, $imports);
                fclose($handle);
                chmod($filepath, 0660);
                //unlink($path.'/'.$entry.'/theme.css');
            }

            // custom.scss clean up
            $customPath = $this->projectPath . "/public/fileadmin/templates/$mandant/bootstrap_package/Resources/Public/Scss/Theme/custom.scss";
            if (file_exists($customPath))
            {
                $this->io->writeln('     processing '.$customPath);
                $oldCustom = file_get_contents($customPath);
                foreach($customToDelete as $del)
                {
                    $newCustom = str_replace($del, '', $oldCustom);
                    $oldCustom = $newCustom;
                }
                $newCustom = str_replace('_img/', 'img/', $oldCustom);

                $handle = fopen($customPath, "w");
                if ($handle === false)
                {
                    $this->io->writeln('   ! error opening file '.$customPath);
                    continue;
                }
                fwrite($handle, $newCustom);
                fclose($handle);
                chmod($customPath, 0660);
            }

            // rename dir mandant/_img if exists
            if (file_exists($themePath.'/_img'))
            {
                rename($themePath.'/_img', $themePath.'/img');
            }

            // delete old _img directories
            $customPath = $this->publicPath . "/fileadmin/templates/$mandant/bootstrap_package/Resources/Public/Scss/Theme/_img";
            if (file_exists($customPath))
            {
                array_map('unlink', glob($customPath."/*.*"));
                rmdir($customPath);
            }
            // update sys_file for /_img/
            /*
             UPDATE sys_file f
                SET f.identifier = REPLACE (f.identifier, '/_img/', '/img/')
                WHERE f.identifier LIKE '%/_img/%'
                Select identifier from sys_file where identifier like '%stroh/img/%' limit 50;
             */
            $sql = "UPDATE sys_file f
                    SET f.identifier = REPLACE (f.identifier, '/_img/', '/img/')
                    WHERE f.identifier LIKE '%/_img/%'";
            $res = $this->db->query($sql);

            $cnt++;
        }
        $d->close();
        $this->io->writeln('  -> DONE ('.$cnt.' elements)'."\n");
    }

}
