<?php

namespace Taketool\Migrate\Controller;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Taketool\Tool\Domain\Repository\ConfigRepository;
use Taketool\Tool\Utility\GeneralCotasxUtility;
use Taketool\Tool\Utility\ValidatorUtility;
use Taketool\Tool\Utility\NimbuscloudUtility;
use Taketool\Tool\Utility\MicrotangoUtility;
use Taketool\Kurse\Controller\DataController;
//use function PHPSTORM_META\type;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017-2018 Martin Keller  <martin.keller@taketool.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

/**
 * Module for the 'migrate' extension.
 *
 * @author      Martin Keller  <martin.keller@taketool.de>
 * @package     Taketool
 * @subpackage  Migrate
 */
class Mod1Controller extends ActionController
{

    /**
     * @var string
     */
    private $serviceURL = 'http://www.kurstool.de/index.php?eID=tool'; // &token=<token>&mandant=<mandant>';

    /**
     * @var array
     */
    protected $usersRootPages = array();

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /*
     * @var \Taketool\Tool\Domain\Repository\Config
     */
    protected $configObject;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     */
    //  protected $persistenceManager;

    /**
     * @var \Taketool\Tool\Utility\CotasxUtility
     * @TYPO3\CMS\Extbase\Annotation\Inject
     */
    protected $toolUtility = null;

    /**
     * @var \TYPO3\CMS\Core\Database\Query\QueryBuilder
     */
    protected $connectionPool = null;

    /**
     * @param ConfigRepository $configRepository
     */
    public function injectConfigRepository(ConfigRepository $configRepository)
    {
        $this->configRepository = $configRepository;
    }

    /*
     * @param \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
     */
//    public function injectPersistenceManager(\TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager) {
//        $this->persistenceManager = $persistenceManager;
//    }


    /**
     * initialize action
     */
    public function initializeAction()
    {

        // Fetch user's root pages
        $rootPages = GeneralCotasxUtility::getRecordsByField('pages', 'is_siteroot', 1, '', '', 'title');
        //\TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($rootPages,'$rootPages in initializeAction()');
        foreach ($rootPages as $rootPage) {
            if ($GLOBALS['BE_USER']->isInWebMount($rootPage['uid'])) {
                $this->usersRootPages[$rootPage['uid']] = $rootPage;
            }
        }
        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $this->configRepository = GeneralUtility::makeInstance(configRepository::class);
        /*
        debug([
            'arguments'=> $this->arguments,
            '$this->configRepository'=> $this->configRepository,
            ], __line__.':'.__class__.'->'.__function__);
        */
        $extensionManagementUtility = GeneralUtility::makeInstance(ExtensionManagementUtility::class);
        //$isExtTool = $extensionManagementUtility::isLoaded('tool');
    }

    /**
     * https://www.taketool.de/typo3/index.php?route=%2Fmodule%2Ftaketool%2FToolBackend&token=7c953b6b765c8c8eb955692624b700f676d09039&
     * tx_tool_taketool_toolbackend[interface]=2&tx_tool_taketool_toolbackend[action]=list&tx_tool_taketool_toolbackend[controller]=Mod1
     *
     * interface null: show all, 1..4 show selected interface types only
     *
     */
    public function listAction()
    {
        // Check permission to read config tables
        if (!$GLOBALS['BE_USER']->check('tables_select', 'tx_tool_domain_model_config')) {
            $this->addFlashMessage('Berechtigung für diese Seite fehlt.', '', AbstractMessage::ERROR);
            return;
        }
        //$this->request->getArguments();
/*
        debug([
            '$interface' => $interface,
            '$this->request->getArguments()'=>$this->request->getArguments(),
            '$this->arguments'=>$this->arguments,
            '$this->arguments->interface'=>$this->arguments->interface],
            __line__.':Mod1Controller->listAction()');
*/
        $configs = [];
        $interface = null;
        foreach ($this->usersRootPages as $uid => $rootPage) {
            $configRecordsByPid = $this->getConfigRecordsByPid($uid, $interface);
            /*
            debug([
                '$configRecordsByPid' => $configRecordsByPid,
                ],__line__.':Mod1Controller->listAction()');
            */
            switch (count($configRecordsByPid)) {
                case 0: // no config record on this page
                    /*
                    $configs[] = [
                        '_page' => $rootPage,
                        'pid' => $uid,
                        'status' => 'gray'
                    ];
                    */
                    break;
                case 1: // continue with this data
                    $status = 'gray'; //'green';
                    $configRecordsByPid[0]['_page'] = $rootPage;
                    //$configRecordsByPid[0]['data'] = unserialize($configRecordsByPid[0]['data']);
                    //if ($configRecordsByPid[0]['data'] === false) {
                    //    $status = 'red';
                    //}
                    $configRecordsByPid[0]['status'] = GeneralCotasxUtility::interfaceColor($configRecordsByPid[0]['interface']);
                    $configs[] = $configRecordsByPid[0];
                    break;
                default:
                    foreach ($configRecordsByPid as $key => $record) {
                        $status = 'gray'; //'green';
                        $record['_page'] = $rootPage;
                        //$record['data'] = unserialize($record['data']);
                        //if ($record['data'] === false) {
                        //    $status = 'red';
                        //}
                        $record['status'] = GeneralCotasxUtility::interfaceColor($configRecordsByPid[0]['interface']);
                        $configs[] = $record;
                    }
            }
        }
        //$configs = GeneralCotasxUtility::sort($configs,'mandant');
/*
        $interfaceStr = '';
        $interface = ($interface) ?? 99;
        switch ($interface)
        {
            case 0: $interfaceStr = 'Cotasx'; break;
            case 1: $interfaceStr = 'GUI3'; break;
            case 2: $interfaceStr = 'Nimbus'; break;
            case 4: $interfaceStr = 'KurseT3'; break;
            default: $interfaceStr = 'Alle';
        }
        $this->view->assign('interface', $interface);
        $this->view->assign('interfaceStr', $interfaceStr);
*/
        $this->view->assign('configs', $configs);
        //debug(['$configs'=>$configs,'$interface'=>$interface],__line__.':Mod1Controller->listAction()');
        //die();

    }

    /**
     * show action
     *
     * @param int $configPid
     * @param int $configUid
     */
    public function showAction($configPid = 0, $configUid = 0)
    {
        if (!$GLOBALS['BE_USER']->check('tables_select', 'tx_tool_domain_model_config') || !array_key_exists($configPid,
                $this->usersRootPages)) {
            $this->addFlashMessage('Berechtigung für diese Seite fehlt.', '', AbstractMessage::ERROR);
            return;
        }
        $config = $this->getConfigRecordByUid($configUid);
        $config['_page'] = $this->usersRootPages[$configPid];
        $data = unserialize($config['data']);

        if ($data === false) {
            $config['data'] = [];
            $this->addFlashMessage("Daten des Configs sind fehlerhaft.\n Das muss repariert werden.",
                '', AbstractMessage::ERROR);
        } else {
            // Sort the multidimensional array
            usort($data, (function ($a,$b) { return $a['kursid']>$b['kursid']; }));
            // Define the custom sort function

            $config['data'] = $data;
        }

        $this->view->assignMultiple([
            'config' => $config,
            'isCotasx' => $config['interface']==0,
            'isGUI3' => $config['interface']==1,
            'isNimbuscloud' => $config['interface']==2,
            'isMicrotango' => $config['interface']==3,
            'isKurseT3' => $config['interface']==4,
        ]);
    }

    /**
     * show raw data action
     *
     * @param int $configPid
     * @param int $configUid
     */
    public function showRawDataAction($configPid = 0, $configUid = 0)
    {
        if (!$GLOBALS['BE_USER']->check('tables_select', 'tx_tool_domain_model_config') || !array_key_exists($configPid,
                $this->usersRootPages)) {
            $this->addFlashMessage('Berechtigung für diese Seite fehlt.', '', AbstractMessage::ERROR);
            return;
        }

        $config = $this->getConfigRecordByUid($configUid);
        $config['_page'] = $this->usersRootPages[$configPid];
        $data = [
            //'original' => $config['data'],
            'kurse' => unserialize($config['data']),
            //'dump' => null,
            // 'dumpPlain' => null
        ];
        //DebuggerUtility::var_dump($config);

        if ($data['kurse'] !== false) {
            //foreach ($data['unserialized'] as $key=>$d) {
            //    unset($data['unserialized'][$key]['termine']);
            //    unset($data['unserialized'][$key]['_DC_']['events']);
           // }
            //$data['dumpPlain'] = $this->viewArray($data['unserialized']);
            /*
            $data['dump'] = DebuggerUtility::var_dump(
                $data['unserialized'],
                '',
                8,
                false,
                true,
                true
            );
            */
        } else {
            $this->addFlashMessage(
                'Daten des Configs sind fehlerhaft. Das muss repariert werden.',
                '',
                AbstractMessage::ERROR
            );
        }
        $kurse = [];
        foreach ($data['kurse'] as $kurs) $kurse[][0] = $kurs;
        //$this->view->assign('config', $config);
        $this->view->assign('mandant', $config['mandant']);
        $this->view->assign('pagetitle', $config['_page']['title']);
        $this->view->assign('kurse', $kurse);
    }

    /**
     * import nimbuscloud data action
     *
     * @param int $configPid
     * @param int $configUid
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function importNimbuscloudDataAction($configPid = 0, $configUid = 0)
    {
        $this->configObject = $this->configRepository->findByUid($configUid);
        //
        debug(['$configPid'=>$configPid,'$configUid'=>$configUid],__line__.':'.__class__.'->'.__function__);

        if (!$this->configObject->isNimbuscloud()) {
            $this->addFlashMessage('Kompatibilität für Nimbuscloud nicht aktiviert. Es erfolgt kein Import.', '', AbstractMessage::ERROR);
            return;
        }

        //$types = NimbuscloudUtility::getRemoteTypes($this->configObject->getMandant(), $this->configObject->getToken());
        /*
                debug([
                    '$GLOBALS[BE_USER]' => $GLOBALS['BE_USER'],
                    '$fetchTime'=>$fetchTime,
                    '$types[types]' => $types['types'],
                ], 'Mod1Controller::importDanceCloudDataAction - Types');
        */

        // 'courses','hash','fetchTime','message'
        $courses = NimbuscloudUtility::getRemoteCourses(
            $this->configObject->getMandant(),
            $this->configObject->getToken(),
            $isTest=false); // isTest reduces count courses to 10
        //debug($courses,'importNimbuscloudDataAction: $courses');
        //debug($courses['message']['error'][0],'$courses[\'message\'][\'error\'][0]');

        $message = '';
        if (isset($courses['message']['error'])) {
            $this->addFlashMessage($courses['message']['error'][0], 'Nimbuscloud meldet', AbstractMessage::ERROR);
        } else {
            // convert Nimbuscloud to Cotas-X
            $data = NimbuscloudUtility::convertDc2Cx($courses['courses'], $this->configObject->getMandant());
            $oldHash = $this->configObject->getDataHash();
            $newHash = $courses['hash'];
            $isUpdateNeeded = ($newHash != $oldHash) ? true : false;
            $doNotUpdate = false;
            if (is_array($courses['courses'])) {
                $cdbgStr = '';
                foreach ($courses['courses'] as $c) {
                    $cdbgStr .= '<div>' . $c['onlineCourseId'] . ': ' . $c['onlineTypeName'] . '/' . $c['onlineLevelName'] . '/' . $c['onlineName'] . '</div>';
                }
                $cdbg[] = $cdbgStr;
            } else {
                $doNotUpdate = true;  // prevents deleting of old data by empty response
            }
            /*
            debug([
                '$GLOBALS[BE_USER]' => $GLOBALS['BE_USER'],
                //'Types fetchTime' => $types['fetchTime'],
                'Courses fetchTime' => $courses['fetchTime'],
                '$oldHash' => $oldHash,
                '$newHash' => $newHash,
                '$isUpdateNeeded' => $isUpdateNeeded,
                'dbgKurse' =>$cdbg,
                '$courses' => $courses['courses'],
                '$message' => $courses['message'],
                'API Key' => $this->configObject->getToken(),
                '$response' => $courses['response'],
                'cachePidList' => $this->configObject->getCachepidlist(),
                //'$types' => $types['types'],
                //'$data' => $data,
            ], __line__.':Mod1Controller::importNimbuscloudDataAction');

            //die('feddisch');
            */
            //if (true)
            if (true) //($isUpdateNeeded && !$doNotUpdate) //() // hash different, => config needs update
            {
                // Properties aktualisieren
                $this->configObject->setNimbuscloudTypes(serialize($courses['allData']));
                $this->configObject->setLastupdate(new \DateTime());
                $this->configObject->setFetchTime($courses['fetchTime']);
                $this->configObject->setData(serialize($data));
                $this->configObject->setDataBlob($data);
                $this->configObject->setDataHash($newHash);
                //$this->configObject->setNimbuscloudTypes($types['types']);

                $cachePidListSub = GeneralCotasxUtility::getPidsToBeCacheCleared($this->configObject->getCachepidlist());
                $pageIds = explode(',',$cachePidListSub );
                // debug($pageIds, __line__.':Mod1Controller->importNimbuscloudDataAction()->$pageIds');
                GeneralCotasxUtility::clearPageCache($pageIds);

                $message = 'Update: Nimbuscloud Daten wurden in ' . $courses['fetchTime'] . ' sec eingelesen (verändert) und der Webseiten Cache für die pids '.$cachePidListSub.' wurde geleert. '
                    .'Die Seitencaches wurden gelöscht. ';
            } else {  // hash same, no update needed
                $this->configObject->setLastupdate(new \DateTime());
                $this->configObject->setFetchTime($courses['fetchTime']);
                //$this->configObject->setNimbuscloudTypes($types['types']);
                $message = 'Nimbuscloud Daten wurden in ' . $courses['fetchTime'] . ' sec eingelesen (unverändert) und der Webseiten Cache wurde NICHT geleert. ';
            }
            $message .= 'Verwendeter API-Key: ' . $this->configObject->getToken() . "\n";
            $message .= 'Es wurden ' . count($data) . ' Kurse eingelesen. ' . "\n";
            $message .= 'Der Config-Record wurde ' . (($doNotUpdate) ? 'nicht' : '') . ' aktualisiert. ' . "\n";


            // Repository mit dem Objekt aktualisieren
            $this->configRepository->update($this->configObject);  // persistance implizit!
            $this->addFlashMessage($message, '', AbstractMessage::INFO);
        }
        // Ausgabe vorbereiten
        $confi = $this->configObject->getConfigRAW();
        $confi['_page'] = $this->usersRootPages[$configPid];

        /*
        $data = [
            'isNimbuscloud' => true,
            'original' => $confi['data'],
            'unserialized' => unserialize($confi['data']),
            'dump' => null,
            'dumpPlain' => null
        ];
        */
        $confi['data'] = $data;
        $this->view->assign('isNimbuscloud', true);
        $this->view->assign('config', $confi);
        //$this->view->assign('data', $data);
    }

    /**
     * import microtango data action
     *
     * @param int $configPid
     * @param int $configUid
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function importMicrotangoDataAction($configPid = 0, $configUid = 0)
    {
        $this->configObject = $this->configRepository->findByUid($configUid);
        /*
        debug([
            'arguments'=> $this->arguments,
            '$configPid' => $configPid,
            '$configUid' => $configUid,
            '$this->configRepository'=> $this->configRepository,
            'configObject' =>$this->configObject], __line__.':'._class__.'->'.__function__);
        */

        if (!$this->configObject->isMicrotango()) {
            $this->addFlashMessage('Kompatibilität für Microtango nicht aktiviert. Es erfolgt kein Import.', '', AbstractMessage::ERROR);
            return;
        }

        // 'courses','hash','fetchTime','message'
        $courses = MicrotangoUtility::getRemoteCourses(
            $this->configObject->getToken(),
            $isTest=false); // isTest reduces count courses to 10
        //debug($courses,'importNimbuscloudDataAction: $courses');
        //debug($courses['message']['error'][0],'$courses[\'message\'][\'error\'][0]');

        $message = '';
        if (isset($courses['message']['error'])) {
            $this->addFlashMessage($courses['message']['error'][0], 'Nimbuscloud meldet', AbstractMessage::ERROR);
        } else {
            // convert Microtango to Cotas-X
            $data = MicrotangoUtility::convertMt2Cx($courses['courses'], $this->configObject->getMandant(), $this->configObject->getToken());
            $oldHash = $this->configObject->getDataHash();
            $newHash = $courses['hash'];
            $isUpdateNeeded = ($newHash != $oldHash) ? true : false;
            $doNotUpdate = false;
            if (is_array($courses['courses'])) {
                $cdbgStr = '';
                foreach ($courses['courses'] as $c) {
                    $cdbgStr .= '<div>' . $c['onlineCourseId'] . ': ' . $c['onlineTypeName'] . '/' . $c['onlineLevelName'] . '/' . $c['onlineName'] . '</div>';
                }
                $cdbg[] = $cdbgStr;
            } else {
                $doNotUpdate = true;  // prevents deleting of old data by empty response
            }
            /*
            debug([
                '$GLOBALS[BE_USER]' => $GLOBALS['BE_USER'],
                //'Types fetchTime' => $types['fetchTime'],
                'Courses fetchTime' => $courses['fetchTime'],
                '$oldHash' => $oldHash,
                '$newHash' => $newHash,
                '$isUpdateNeeded' => $isUpdateNeeded,
                'dbgKurse' =>$cdbg,
                '$courses' => $courses['courses'],
                '$message' => $courses['message'],
                'API Key' => $this->configObject->getToken(),
                '$response' => $courses['response'],
                'cachePidList' => $this->configObject->getCachepidlist(),
                //'$types' => $types['types'],
                //'$data' => $data,
            ], __line__.':Mod1Controller::importNimbuscloudDataAction');

            //die('feddisch');
            */
            //if (true)
            if (true) //($isUpdateNeeded && !$doNotUpdate) //() // hash different, => config needs update
            {
                // Properties aktualisieren
                //$this->configObject->setMicrotangoTypes(serialize($courses['allData']));
                $this->configObject->setLastupdate(new \DateTime());
                $this->configObject->setFetchTime($courses['fetchTime']);
                $this->configObject->setData(serialize($data));
                $this->configObject->setDataBlob($data);
                $this->configObject->setDataHash($newHash);
                //$this->configObject->setNimbuscloudTypes($types['types']);

                $cachePidListSub = GeneralCotasxUtility::getPidsToBeCacheCleared($this->configObject->getCachepidlist());
                $pageIds = explode(',',$cachePidListSub );
                // debug($pageIds, __line__.':Mod1Controller->importNimbuscloudDataAction()->$pageIds');
                GeneralCotasxUtility::clearPageCache($pageIds);

                $message = 'Update: Nimbuscloud Daten wurden in ' . $courses['fetchTime'] . ' sec eingelesen (verändert) und der Webseiten Cache für die pids '.$cachePidListSub.' wurde geleert. '
                    .'Die Seitencaches wurden gelöscht. ';
            } else {  // hash same, no update needed
                $this->configObject->setLastupdate(new \DateTime());
                $this->configObject->setFetchTime($courses['fetchTime']);
                //$this->configObject->setNimbuscloudTypes($types['types']);
                $message = 'Nimbuscloud Daten wurden in ' . $courses['fetchTime'] . ' sec eingelesen (unverändert) und der Webseiten Cache wurde NICHT geleert. ';
            }
            $message .= 'Verwendeter API-Key: ' . $this->configObject->getToken() . "\n";
            $message .= 'Es wurden ' . count($data) . ' Kurse eingelesen. ' . "\n";
            $message .= 'Der Config-Record wurde ' . (($doNotUpdate) ? 'nicht' : '') . ' aktualisiert. ' . "\n";


            // Repository mit dem Objekt aktualisieren
            $this->configRepository->update($this->configObject);  // persistance implizit!
            $this->addFlashMessage($message, '', AbstractMessage::INFO);
        }
        // Ausgabe vorbereiten
        $confi = $this->configObject->getConfigRAW();
        $confi['_page'] = $this->usersRootPages[$configPid];

        /*
        $data = [
            'isNimbuscloud' => true,
            'original' => $confi['data'],
            'unserialized' => unserialize($confi['data']),
            'dump' => null,
            'dumpPlain' => null
        ];
        */
        $confi['data'] = $data;
        $this->view->assign('isNimbuscloud', true);
        $this->view->assign('config', $confi);
        //$this->view->assign('data', $data);
    }

    /**
     * import KurseT3 data action
     * @@ clear_cacheCmd currently not active!
     * @param int $configUid
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function importKurseT3DataAction($configUid = 0)
    {
        $this->configObject = $this->configRepository->findByUid($configUid);

        if (!$this->configObject->isKurseT3()) {
            $this->addFlashMessage('Kompatibilität für KurseT3 nicht aktiviert. Es erfolgt kein Import.', '', AbstractMessage::ERROR);
            return;
        }

        $mt = \microtime();
        $kurseT3 = $this->objectManager->get(DataController::class);
        $kurseT3->init();
        $data = $kurseT3->getDataAction();  // comes serialized

        $oldHash = $this->configObject->getDataHash();
        $newHash = md5($data);
        $isUpdateNeeded = ($newHash != $oldHash) ? true : false;
        $doNotUpdate = false;

        $this->configObject->setData($data);
        $data = unserialize($data);
        $this->configObject->setDataBlob($data);
        $this->configObject->setLastupdate(new \DateTime());
        $this->configObject->setLastDebugMessage($kurseT3->checkDataTxt());
        $this->configObject->setLastDebugUpdate(new \DateTime());
        $this->configObject->setDataHash($newHash);
        $this->configObject->setFetchTime(\microtime() - $mt);
        $this->configRepository->update($this->configObject);

        GeneralCotasxUtility::clearPageCache($this->configObject->getCachepidlist());

        /*
        debug([
            '$data' => unserialize($data),
            '$this->configObject' => $this->configObject,
            'checkDataTxt' => $kurseT3->checkDataTxt(),
        ],'Mod1Controller->importKurseT3DataAction()');
        */

        $this->addFlashMessage(count($data).' Kurse aus KurseT3 wurden übernommen. ('
            .(\microtime() - $mt).'s, '
            .(new \DateTime())->setTimezone(new \DateTimezone("europe/rome"))->format('d.m.Y H:i:s').')', 'Erfolg', AbstractMessage::OK);
        $this->redirect('list', 'Mod1');
    }

    public function migrateFlexformAction()
    {
        /*
        $msg1 = $this->convertFF('tool_pi1');
        $msg1 .= $this->convertFF2('tool_pi1'); // settings.settings.
        $msg2 = $this->convertFF('tool_pi2');

        $this->view->assign('msg1', $msg1);
        $this->view->assign('msg2', $msg2);
        */
    }

     /**
     * submit data action
     *
     * @param int $configPid
     * @param int $configUid
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function submitDataAction($configPid = 0, $configUid = 0)
    {
        if (!$GLOBALS['BE_USER']->check('tables_select', 'tx_tool_domain_model_config') || !array_key_exists($configPid,
                $this->usersRootPages)) {
            $this->addFlashMessage('Berechtigung für diese Seite fehlt.', '', AbstractMessage::ERROR);
            return;
        }

        $config = $this->getConfigRecordByUid($configUid);
        $data = unserialize($config['data']);

        // get rid of quotes and other stuff breaking the json
        foreach ($data as $i => $k) {
            $data[$i]['infotext'] = htmlspecialchars($k['infotext'], $flags = ENT_COMPAT | ENT_HTML401,
                $encoding = ini_get("default_charset"), $double_encode = false);
        }

        $jsonData = json_encode($data, JSON_HEX_APOS | JSON_HEX_QUOT); // JSON_HEX_TAG | JSON_HEX_AMP |

        $options = array(
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $jsonData,
            // should be json_encode format
            CURLOPT_RETURNTRANSFER => true,
            // return the transfer as a string
            CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Content-Length: ' . strlen($jsonData)),
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            // martin
            CURLOPT_HEADER => false,
            // TRUE if you want to get all the headers as well; has nothing to do with CURLOPT_HTTPHEADER
            CURLOPT_VERBOSE => true,
            //TRUE to output verbose information. Writes output to STDERR, or the file specified using CURLOPT_STDERR.
            // todo: Setting CURLOPT_STDERR fails
//            CURLOPT_STDERR => $_SERVER['DOCUMENT_ROOT'].'/curl_log.txt',
            CURLOPT_SSL_VERIFYPEER => true,
            // we have a self signed certificate in the chain
            CURLOPT_SSL_VERIFYHOST => true,
        );
        $ch = curl_init($this->serviceURL . '&debug=1&token=' . $config['token'] . '&mandant=' . $config['mandant']);
        if ($ch === false) {
            $this->addFlashMessage(__file__ . '' . __line__ . ': serviceURL konnte nicht initialisiert werden.', '',
                AbstractMessage::ERROR);
            $this->redirect('show', null, null, array('configPid' => $configPid));
        }
        $res = curl_setopt_array($ch, $options);

        //execute post
        $json = curl_exec($ch);

        $response = curl_getinfo($ch);
        $httpCode = $response['http_code'];

        // close connection
        curl_close($ch);

        switch ($httpCode) {
            case '100':
                $message = 'Unerwartete Daten zurückerhalten. zB. falsche Content Länge angegeben.';
                break;
            case '200':
                $message = 'Daten erfolgreich übertragen. URL=' . $this->serviceURL . '&debug=1&&token=' . $config['token'] . '&mandant=' . $config['mandant'];
                break;
            case '401':
                $message = 'Error 401 - Authentisierung fehlgeschlagen.';
                break;
            case '510':
                $message = 'Error 510 - Bitte Angaben vervollständigen';
                break;
            default:
                $message = 'Etwas Unvorhergesehenes ist geschehen (HTTP ' . $httpCode . ').';
        }

        $this->addFlashMessage($message, '', AbstractMessage::INFO);

        $this->redirect('show', null, null, array('configPid' => $configPid));
    }

    /**
     * do validate data action
     *
     * @param int $configPid
     * @param int $configUid
     * @todo: add Paypal ID validator
     */
    public function validateDataAction($configPid = 0, $configUid = 0)
    {
        if (!$GLOBALS['BE_USER']->check('tables_select', 'tx_tool_domain_model_config') || !array_key_exists($configPid,
                $this->usersRootPages)) {
            $this->addFlashMessage('Berechtigung für diese Seite fehlt.', '', AbstractMessage::ERROR);
            return;
        }
        $config = $this->getConfigRecordByUid($configUid);
        $config['_page'] = $this->usersRootPages[$configPid];

        // Validate Config Record
        $configRecordFieldsToValidate = array(
            'mandant' => 'required',
            'token' => 'required',
            'rechtstexteurl' => 'required,rechtstexteurl',
            'cachepidlist' => 'required',
            'defaultzscolumns' => 'min:4',
            'pidstore' => 'required',
            'smtpServer' => 'smtp'
        );
        $configRecordResult = array();
        $configRecordErrorCounter = 0;
        foreach ($configRecordFieldsToValidate as $fieldName => $validatorList) {
            $configRecordResult[$fieldName] = ['errors' => []];

            $validators = GeneralUtility::trimExplode(',', $validatorList, true);
            foreach ($validators as $validator) {
                $validatorParts = GeneralUtility::trimExplode(':', $validator);
                switch ($validatorParts[0]) {
                    case 'required':
                        if (empty($config[$fieldName])) {
                            $configRecordResult[$fieldName]['errors'][] = 'Feld darf nicht leer sein.';
                            $configRecordErrorCounter++;
                        }
                        break;
                    case 'rechtstexteurl':
                        $requiredProtocol = 'https://';
                        if (substr($config[$fieldName], 0, strlen($requiredProtocol)) !== $requiredProtocol) {
                            $configRecordResult[$fieldName]['errors'][] = 'Der Pfad muss mit "https://" beginnen.';
                            $configRecordErrorCounter++;
                        }
                        // Check path with agb.pdf
                        if ($fieldName === 'rechtstexteurl' && GeneralCotasxUtility::remoteFileExists($config[$fieldName] . 'agb.pdf') === false) {
                            $configRecordResult[$fieldName]['errors'][] = 'Der Pfad "' . $config[$fieldName] . 'agb.pdf" konnte nicht geöffnet werden.';
                            $configRecordErrorCounter++;
                        }
                        break;
                    case 'min':

                        //debug(['$fieldName'=>$fieldName, '$config[$fieldName]'=>$config[$fieldName]], 'Neue $this->configObject');
                        //die();

                        $cntFields = count(explode(',', $config[$fieldName]));
                        if ($cntFields < $validatorParts[1]) {
                            if (empty($config[$fieldName])) {
                                $configRecordResult[$fieldName]['errors'][] = 'Feld muss mindestens ' . $validatorParts[1] . ' Einträge umfassen.';
                                $configRecordErrorCounter++;
                            }
                        }
                        break;
                    case 'smtp':
                        if (!empty($config['smtpServer'])) {
                            $testSmtpMailerResult = ValidatorUtility::testSmtpMailer($config, $configPid);
                            if ($testSmtpMailerResult !== true) {
                                $configRecordResult[$fieldName]['errors'][] = $testSmtpMailerResult;
                            }
                        } else {
                            $configRecordResult[$fieldName]['errors'][] = 'SMTP Server ist nicht konfiguriert.';
                        }
                        break;
                }
            }
        }
        $this->view->assignMultiple([
            'configRecordResult' => $configRecordResult,
            'configRecordErrorCounter' => $configRecordErrorCounter
        ]);

        // Validate Course Data
        $data = unserialize($config['data']);
        $dataResult = array();
        $dataResultGeneralError = '';

        if ($data !== false) {
            $dataResult = ValidatorUtility::validate($data);
        } else {
            $dataResultGeneralError = 'Daten des Configs sind fehlerhaft. Das muss repariert werden.';
        }
        $this->view->assignMultiple([
            'dataResult' => $dataResult,
            'dataResultErrorCounter' => !$dataResultGeneralError ? count($dataResult) : 'unbekannt viele',
            'dataResultGeneralError' => $dataResultGeneralError
        ]);

        $this->view->assign('config', $config);
    }

    /**
     * sort array by certain key, works together with self::sort()
     * @param string $key
     * @return \Closure
     */
    private static function build_sorter(string $key): \Closure
    {
        return function ($a, $b) use ($key) {
            return strnatcmp($b[$key], $a[$key]);
        };
    }

    /**
     * @param $list_type
     * @return string
     */
    private function convertFF($list_type)
    {
        return;

        $tt_content = GeneralCotasxUtility::getRecordsByField(
            'tt_content',
            'list_type',
            $list_type);
        /*
        debug([
            'uid' => $tt_content[0]['uid'],
            'pid' => $tt_content[0]['pid'],
            'list_type' => $tt_content[0]['list_type'],
            'pi_flexform' => $tt_content[0]['pi_flexform'],
        ], __line__ . ':Mod1Controller->migrateFlexformAction()');
        */

        /*
         * cotax_pi1 - Zielseitenplugin
         * 1. <field index="dynField"> => <field index="standort"
         * 2. <field index="dynField"> => <field index="zielseite"
         * 3. <field index="dynField"> => <field index="zscols"
         * 4. <field index="xxx"> => <field index="settings.xxx">
         *
         * cotax_pi2 - Anmeldeplugin
         * 1. <field index="xxx"> => <field index="settings.xxx">
         */

        if (!strpos($tt_content[0]['pi_flexform'], 'field index="settings.')) // avoid processing twice
        {
            foreach ($tt_content as $tt_c) {

                if (strpos($tt_c['pi_flexform'], 'field index="dynField')) {  // pi1
                    $ff = $this->str_replaceOne($tt_c['pi_flexform'], 'field index="dynField"', 'field index="standort"');
                    $ff = $this->str_replaceOne($ff, 'field index="dynField"', 'field index="zielseite"');
                    $ff = $this->str_replaceOne($ff, 'field index="dynField"', 'field index="zscols"');
                    $ff = str_replace('field index="', 'field index="settings.', $ff);
                } else {  // pi2
                    $ff = str_replace('field index="', 'field index="settings.', $tt_c['pi_flexform']);
                }
                GeneralCotasxUtility::updateRecordByUid('tt_content', 'pi_flexform', $ff, $tt_c['uid']);
            }
            $msg = $list_type . ' wurde jetzt umgestellt ('.count($tt_content).' Einträge)';
        } else {
            $msg = $list_type . ' war schon umgestellt ('.count($tt_content).' Einträge)';
        }
        return $msg;
    }

    /**
     * @param $list_type
     * @return string
     */
    private function convertFF2($list_type)
    {
        return;
        $tt_content = GeneralCotasxUtility::getRecordsByField(
            'tt_content',
            'list_type',
            $list_type);

        if (!strpos($tt_content[0]['pi_flexform'], 'settings.settings.')) // avoid processing twice
        {
            foreach ($tt_content as $tt_c) {
                if (strpos($tt_c['pi_flexform'], 'settings.settings.')) {  // pi1
                    $ff = str_replace($tt_c['pi_flexform'], 'settings.settings.', 'settings.');
                }
                GeneralCotasxUtility::updateRecordByUid('tt_content', 'pi_flexform', $ff, $tt_c['uid']);
            }
            $msg = $list_type . ' wurde jetzt umgestellt ('.count($tt_content).' Einträge)';
        } else {
            $msg = $list_type . ' war schon umgestellt ('.count($tt_content).' Einträge)';
        }
        return $msg;
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @param string $replace
     * @return string
     */
    private function str_replaceOne($haystack, $needle, $replace)
    {
        $newstring = $haystack;
        $pos = strpos($haystack, $needle);
        if ($pos !== false) {
            $newstring = substr_replace($haystack, $replace, $pos, strlen($needle));
        }
        return $newstring;
    }

    /**
     * getMenuForPages() Returns Array with key/value pairs;
     * keys are page-uid numbers. values are the corresponding page records
     * Attention: no recursion yet!
     *
     * @param $pidList
     * @return string
     */
    private function getPidsToBeCacheCleared($pidList)
    {
        $query = $this->connectionPool->getQueryBuilderForTable('pages');
        $res = $query->select('uid')
            ->from('pages')
            ->where($query->expr()->in('pid', $pidList))
            ->execute();
        $pages = $res->fetchAll();  // there may be more than one ->collection
        foreach ($pages as $key => $p) {
            $a2[] = $p['uid'];
        }
        //DebuggerUtility::var_dump(['$pidList' => $pidList,  'newPidList' => $pidList.','.implode(',', $a2)], 'getPidsToBeCacheCleared');
        return $pidList.','.implode(',', $a2);
    }

    /**
     * Return array as HTML table
     *
     * @param $arrayIn
     * @return string
     */
    private function viewArray($arrayIn)
    {
        if (is_array($arrayIn)) {
            $result = '<table border="1" cellpadding="1" cellspacing="0" bgcolor="white">';
            if (count($arrayIn) == 0) {
                $result .= '<tr><td><font face="Verdana,Arial" size="1"><strong>EMPTY!</strong></font></td></tr>';
            } else {
                foreach ($arrayIn as $key => $val) {
                    $result .= '<tr><td valign="top"><font face="Verdana,Arial" size="1">' . htmlspecialchars((string)$key) . '</font></td><td>';
                    if (is_array($val)) {
                        $result .= $this->viewArray($val);
                    } elseif (is_object($val)) {
                        $string = '';
                        if (method_exists($val, '__toString')) {
                            $string .= get_class($val) . ': ' . (string)$val;
                        } else {
                            $string .= print_r($val, true);
                        }
                        $result .= '<font face="Verdana,Arial" size="1" color="red">' . nl2br(htmlspecialchars($string)) . '<br /></font>';
                    } else {
                        if (gettype($val) == 'object') {
                            $string = 'Unknown object';
                        } else {
                            $string = (string)$val;
                        }
                        $result .= '<font face="Verdana,Arial" size="1" color="red">' . nl2br(htmlspecialchars($string)) . '<br /></font>';
                    }
                    $result .= '</td></tr>';
                }
            }
            $result .= '</table>';
        } else {
            $result = '<table border="1" cellpadding="1" cellspacing="0" bgcolor="white"><tr>' .
                '<td><font face="Verdana,Arial" size="1" color="red">' . nl2br(htmlspecialchars((string)$arrayIn)) . '<br /></font></td>' .
                '</tr></table>';
        }
        // Output it as a string.
        return $result;
    }


    /**
     * Get config record(s) by pid
     *
     * @param int $pid
     * @return array
     */
    private function getConfigRecordsByPid($pid, $interface = null)
    {
        $query = $this->connectionPool->getQueryBuilderForTable('tx_tool_domain_model_config');

        $interfaceQueryFilter = ($interface == null)
            ? '1=1'
            : $query->expr()->eq('interface', intval($interface));

        $res = $query->select('*')
            ->from('tx_tool_domain_model_config')
            ->where($query->expr()->eq('pid', $pid))
            ->andWhere($query->expr()->eq('hidden', 0))
            ->andWhere($interfaceQueryFilter)
            ->execute();
        $config = $res->fetchAll();  // there may be more than one ->collection
        //DebuggerUtility::var_dump(['$query' =>$query, '$res'=>$res, '$config'=>$config], 'getConfigRecordsByPid');
        /*
        debug([
            '$config'=>$config,
            '$interface'=>$interface,
            '$interfaceQueryFilter' => $interfaceQueryFilter,
        ],__line__.':Mod1Controller->getConfigRecordsByPid()');
        */
        return $config;
    }

    /**
     * Get config record by its uid
     *
     * @param int $uid
     * @return array
     */
    private function getConfigRecordByUid($uid)
    {
        $query = $this->connectionPool->getQueryBuilderForTable('tx_tool_domain_model_config');
        $res = $query->select('*')
            ->from('tx_tool_domain_model_config')
            ->where($query->expr()->eq('Uid', $uid))
            ->andWhere($query->expr()->eq('hidden', 0))
            ->execute();
        $config = $res->fetch();
        //DebuggerUtility::var_dump(['$query' =>$query, '$res'=>$res, '$config'=>$config], 'getConfigRecordsByPid');
        return $config;
    }

    /**
     * @param array $array
     * @param string $key
     * @return array
     */
    private static function sort(array $array, string $key): array
    {
        usort($array, self::build_sorter($key));
        return $array;
    }
}
