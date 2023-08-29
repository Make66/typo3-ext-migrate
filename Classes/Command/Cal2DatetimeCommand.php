<?php
namespace Taketool\Migrate\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

/*
 * CLI from domain root: ./typo3/sysext/core/bin/typo3 tool:mig_cal
 */

class Cal2DatetimeCommand extends Command
{
    protected $objectManager = null;
    protected $configRepository = null;
    protected static $defaultName = 'migrate:cal';  //To make your command lazily loaded

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setHelp('Migrates configRecord flexform to match renamed availibility => availability.');
    }

    /**
     * Executes the command
     * cli> typo3/sysext/core/bin/typo3 tool:mig_cal
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());
        $io->writeln('=== Migrate caledarize unix_timestamp to DateTime ===');

        // check db connection
        $LC = include './typo3conf/LocalConfiguration.php';
        $io->writeln('1) Connecting to DB '.$LC['DB']['Connections']['Default']['dbname']);
        $db = new \mysqli(
            $LC['DB']['Connections']['Default']['host'],
            $LC['DB']['Connections']['Default']['user'],
            $LC['DB']['Connections']['Default']['password'],
            $LC['DB']['Connections']['Default']['dbname']
        );
        if ($db->connect_error) {
            $io->writeln('   -> Connection -> ERROR ');
            return 1;
        } else $io->writeln('   -> Connection -> SUCCESS ');

        $tables2migrate = [
            'tx_calendarize_domain_model_configuration',
            'tx_calendarize_domain_model_index'
        ];

        // Rename old columns and add new DATE columns
        $io->writeln('2) Rename old columns and add new DATE columns ');
        foreach($tables2migrate as  $table)
        {
            $res = $db->query("ALTER TABLE $table CHANGE COLUMN IF EXISTS `start_date` `ts_start_date` int(11) NOT NULL default 0");
            if ($res === false) { $io->writeln('   ERROR: ' . $db->error); exit; }
            $res = $db->query("ALTER TABLE $table CHANGE COLUMN IF EXISTS `end_date` `ts_end_date` int(11) NOT NULL default 0");
            if ($res === false) { $io->writeln('   ERROR: ' . $db->error); exit; }
            $res = $db->query("ALTER TABLE $table ADD IF NOT EXISTS `start_date` DATE DEFAULT NULL AFTER ts_start_date");
            if ($res === false) { $io->writeln('   ERROR: ' . $db->error); exit; }
            $res = $db->query("ALTER TABLE $table ADD IF NOT EXISTS `end_date` DATE DEFAULT NULL AFTER ts_end_date");
            if ($res === false) { $io->writeln('   ERROR: ' . $db->error); exit; }
        }

        $io->writeln('3) Copy TS columns to new DATE columns ');
        foreach($tables2migrate as $table)
        {
            $io->writeln('   - table: ' . $table);
            $res = $db->query("SELECT uid, ts_start_date, ts_end_date FROM $table");
            if ($res === false) { $io->writeln('   ERROR: ' . $db->error); exit; }

            $cnt = 0;

            while($row = $res->fetch_assoc())
            {
                $cnt++;
                $uid = $row['uid'];
                //$io->writeln('   - $uid: ' . $uid . ', ts_start_date: ' . $row['ts_start_date']);

                //Object of class DateTime could not be converted to string
                $start_date = new \DateTime();
                $start_date = $start_date->setTimestamp(intval($row['ts_start_date']))->format('Y-m-d H:i:s');
                //$io->writeln('   - $start_date: ' . $start_date);

                $end_date = new \DateTime();
                $end_date = $end_date->setTimestamp(intval($row['ts_end_date']))->format('Y-m-d H:i:s');
                //$io->writeln('   - $end_date: ' . $end_date->format('Y-m-d H:i:s'));

                $res2 = $db->query("UPDATE $table SET start_date='$start_date', end_date='$end_date' WHERE uid=$uid");
                if ($res2 === false) { $io->writeln('   ERROR: ' . $db->error); exit; }
            }
            $io->writeln(' -> DONE for table '.$table.'. '.$cnt.' lines altered.');
        }

        return 0; // T3v10: Command::SUCCESS;
    }

}
