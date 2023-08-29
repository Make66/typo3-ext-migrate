<?php
namespace Taketool\Migrate\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

/*
 * CLI from domain root: ./typo3/sysext/core/bin/typo3 migrate:tca
 */

class TcaCommand extends Command
{
    protected $objectManager = null;
    protected $configRepository = null;
    protected static $defaultName = 'migrate:tca';  //To make your command lazily loaded

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setHelp('Migrates configRecord flexform to match renamed availibility => availability.');
    }

    /**
     * Executes the command
     * cli> typo3/sysext/core/bin/typo3 tool:mig_tca
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());
die();
        // check db connection
        $LC = include './typo3conf/LocalConfiguration.php';
        $io->writeln('Connecting to DB '.$LC['DB']['Connections']['Default']['dbname']);
        $db = new \mysqli(
            $LC['DB']['Connections']['Default']['host'],
            $LC['DB']['Connections']['Default']['user'],
            $LC['DB']['Connections']['Default']['password'],
            $LC['DB']['Connections']['Default']['dbname']
        );
        if ($db->connect_error) {
            $io->writeln(' -> ERROR ');
            return 1;
        } else $io->writeln(' -> SUCCESS ');

        /*********************************************************
         delete empty tables created with installation of EXT:tool
         ********************************************************/
        $io->writeln('Drop exiting new db tables created by installing the extension');
        $res = $db->query("SELECT * FROM tx_tool_domain_model_config");
        $row = $res->fetch_assoc();
        $isNoDataPresent = $row['COUNT(*)'] == 0;
        //$io->writeln(' -> isNoDataPresent in new config table: ' .serialize($row).':'.(($isNoDataPresent)?'true':'false'));
        if ($isNoDataPresent)
        {
            $res = $db->query("DROP TABLE
            tx_tool_domain_model_config,
            tx_tool_domain_model_configcollection,
            tx_tool_domain_model_anmeldung,
            tx_tool_domain_model_voucher ");
            if ($res === false) {
                $io->writeln(' -> ERROR ');
                return 2;
            } else $io->writeln(' -> SUCCESS ');
        } else {
            $io->writeln(' -> BREAK: New config table contains data - no dropping');
        }

        /***********************
         rename cotasx db tables
         ***********************/
        $io->writeln('Renaming db tables');
        if ($isNoDataPresent)
        {
            $res = $db->query("RENAME TABLE
            tx_cotasx_domain_model_config TO tx_tool_domain_model_config,
            tx_cotasx_domain_model_config_collection TO tx_tool_domain_model_configcollection,
            tx_cotasx_anmeldung TO tx_tool_domain_model_anmeldung,
            tx_cotasx_domain_model_voucher TO tx_tool_domain_model_voucher ");
            if ($res === false) {
                $io->writeln(' -> ERROR ');
                //return 3;  // no error if run for 2nd time
            } else $io->writeln(' -> SUCCESS ');
        } else {
            $io->writeln(' -> BREAK: Config table contains data - no renaming');
        }
        /* SQL for rename back in case of error
         $res = $db->query("RENAME TABLE
            tx_tool_domain_model_config TO tx_cotasx_domain_model_config,
            tx_tool_domain_model_configcollection TO tx_cotasx_domain_model_config_collection,
            tx_tool_domain_model_anmeldung TO tx_cotasx_anmeldung,
            tx_tool_domain_model_voucher TO tx_cotasx_domain_model_voucher ");
         */

        /**************************************************
         migrate all cotasx-plugins by tt_content.list_type
         *************************************************/
        $io->writeln('Migrating plugins');
        foreach (['pi1','pi2','pi3','pi4'] as $pi)
        {
            $io->writeln(' -> '."UPDATE tt_content SET list_type='tool_".$pi."' WHERE list_type='cotasx_".$pi."'");
            $res = $db->query("UPDATE tt_content SET list_type='tool_".$pi."' WHERE list_type='cotasx_".$pi."'");
            //$db->num_rows

            if ($res === false) {
                $io->writeln(' -> ERROR ');
                return 4;
            } else $io->writeln(' -> SUCCESS ');
        }

        /********************************
         migrate template settings
         *********************************/
        $io->writeln('Migrating TS-templates');
        $sql = "SELECT uid, include_static_file, constants, config FROM sys_template WHERE deleted=0";
        $io->writeln(' -> '. $sql);
        $res = $db->query($sql);
        if ($res === false) {
            $io->writeln(' -> ERROR ');
            return 5;
        } else {
            $io->writeln(' -> SUCCESS ');
            $templates = $res->fetch_all(MYSQLI_ASSOC);
            //$io->writeln(serialize($templates));
            $newTemplates =  []; // uid => [constants, config]
            $search = 'tx_cotasx_pi';
            $replace = 'tx_tool_pi';
            $s1 = 'EXT:cotasx';
            $r1 = 'EXT:tool';
            foreach($templates as $template)
            {
                //$io->writeln('$template='.serialize($template));
                $newTemplates[$template['uid']] = [
                    'constants' => str_replace($search, $replace, $template['constants']),
                    'config' => str_replace($search, $replace, $template['config']),
                    'include_static_file' => str_replace($s1, $r1, $template['include_static_file']),
                ];
            }
            foreach($newTemplates as $uid => $template)
            {
                //$io->writeln(' -> template='.serialize($template));
                $io->writeln(' -> include_static_file='.$template['include_static_file']);
                $db->query("UPDATE sys_template SET
                        include_static_file='".$template['include_static_file']."',
                        constants='".$template['constants']."',
                        config='".$template['config']."'
                        WHERE uid=$uid");
            }
            $io->writeln(' -> DONE. '.count($newTemplates).' TS-templates processed.');
            /*
             EXT:bootstrap_package/Configuration/TypoScript,EXT:news/Configuration/TypoScript,EXT:news/Configuration/TypoScript/Styles/Twb,EXT:gridelements/Configuration/TypoScript/,EXT:bootstrap_grids/Configuration/TypoScript/,EXT:content_animations/Configuration/TypoScript/BootstrapPackage/v10,EXT:mk_plc/Configuration/TypoScript,EXT:jh_mail_configurator/Configuration/TypoScript,EXT:calendarize/Configuration/TypoScript/,EXT:kurse/Configuration/TypoScript,EXT:cotasx/Configuration/TypoScript/Pi1,EXT:cotasx/Configuration/TypoScript/Pi2
             */
        }

        /******************************
         migrate SCSS files custom.scss
         ******************************/
        $io->writeln('Edit SCSS files');
        shell_exec('find ./fileadmin/templates/ -type f -name custom.scss -exec sed -i \'s/-cotasx-/-tool-/g\' {} \; >/dev/null');
        shell_exec('find ./fileadmin/templates/ -type f -name global.scss -exec sed -i \'s/-cotasx-/-tool-/g\' {} \; >/dev/null');
        //$output = shell_exec('find ./fileadmin/templates/ -type f -name custom.scss');
        $io->writeln(' -> DONE');

        return 0; // T3v10: Command::SUCCESS;
    }

}
