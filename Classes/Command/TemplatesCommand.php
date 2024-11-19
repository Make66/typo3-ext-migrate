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
 *  - remove "Basis Setup" from 'basedOn' if exists
 *  - add "Taketool Sitepackage (sitepackage)" to the end of 'include_static_file' if not exists
 *
 * CLI from domain root: ./vendor/bin/typo3 migrate:templates
 */

class TemplatesCommand extends Command
{
    //protected $objectManager;
    //protected $configRepository;
    protected static $defaultName = 'migrate:templates';  //To make your command lazily loaded
    protected \mysqli $db;
    protected SymfonyStyle $io;
    private array $rootPages;
    private array $rootTemplates;

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setHelp('Migrates root templates to meet v11 requirements');
    }

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

        // becomes s/t like "/home/test.taketool.eu/public"
        $publicPath = $environment->getPublicPath();
        $localConfPath = $publicPath .'/typo3conf/LocalConfiguration.php';

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

        // init; returns array of rootpage id's
        $this->getAllRootPages();

        // migration
        $this->removeBasedOn();
        $this->addSitepackage();

        return 0; // T3v10: Command::SUCCESS;
    }

    /**
     * remove "Basis Setup (sys_template_24)" from 'basedOn' if exists
     * @return void
     */
    private function removeBasedOn() {
        $this->io->section('Migrating root templates: remove "Basis Setup (sys_template_24)" from basedOn if exists');
        $remove = '24';  // sys_template_24
        $this->getAllRootTemplates();

        $cnt = 0;
        foreach ($this->rootTemplates as $template)
        {
            $altered = false;
            if (!empty($template['basedOn'])) {
                $basedOn = explode(',', $template['basedOn']);
                $key = array_search($remove, $basedOn);
                //$this->io->writeln($template['pid'] . ':' . $template['basedOn'] . '[' . $key . ']');
                if ($key===false) {
                } else {
                    $this->io->writeln($key);
                    unset($basedOn[$key]);
                    $altered = true;
                }
            }
            if ($altered)
            {
                $templateUid = $template['uid'];
                $basedOn = implode(',', $basedOn);
                $res = $this->db->query("UPDATE sys_template SET basedOn='$basedOn' WHERE uid=$templateUid");
                if ($res === false) $this->io->writeln($this->db->error);
            }
            $cnt++;
        }
        $this->io->writeln('  -> DONE ('.$cnt.' elements)'."\n");
    }

    /**
     * for all root-templates,
     * add "Taketool Sitepackage (sitepackage)" to the end of 'include_static_file' if not exists
     */
    private function addSitepackage()
    {
        $this->io->section('Migrating root templates: add "Taketool Sitepackage (sitepackage)"');
        $addInclude = 'EXT:sitepackage/Configuration/TypoScript';

        $this->getAllRootTemplates();
        $cnt = 0;
        foreach ($this->rootTemplates as $template)
        {
            $altered = false;
            $includeStaticFiles = explode(',', $template['include_static_file']);
            if (!in_array($addInclude, $includeStaticFiles))
            {
                array_push($includeStaticFiles, $addInclude);
                $altered = true;
            }
            if ($altered)
            {
                $templateUid = $template['uid'];
                $includeStaticFiles = implode(',', $includeStaticFiles);
                $res = $this->db->query("UPDATE sys_template SET include_static_file='$includeStaticFiles' WHERE uid=$templateUid");
                if ($res === false) $this->io->writeln($this->db->error);
            }
            $cnt++;
        }
        $this->io->writeln('  -> DONE ('.$cnt.' elements)'."\n");
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
        $rootPageIds = implode(',', $this->rootPages);
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

}
