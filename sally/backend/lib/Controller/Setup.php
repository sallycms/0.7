<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Setup extends sly_Controller_Backend implements sly_Controller_Interface {
	protected $flash;
	protected $lang;

	private $init = false;

	protected function init() {
		if ($this->init) return;

		$this->flash = sly_Core::getFlashMessage();
		$this->init  = true;
		$this->lang  = sly_request('lang', 'string');

		sly_Core::getI18N()->appendFile(SLY_SALLYFOLDER.'/backend/lang/pages/setup/');
	}

	public function indexAction()	{
		$this->init();

		$languages = sly_I18N::getLocales(SLY_SALLYFOLDER.'/backend/lang');

		// forward if only one locale available
		if (count($languages) === 1) {
			$params = array('func' => 'license', 'lang' => reset($languages));
			sly_Core::getCurrentApp()->redirect('setup', $params);
		}

		$this->render('setup/chooselang.phtml', array(), false);
	}

	public function licenseAction() {
		$this->init();
		$this->render('setup/license.phtml', array(), false);
	}

	public function syscheckAction() {
		$this->init();

		$errors    = false;
		$sysErrors = false;
		$warnings  = false;
		$results   = array();
		$tester    = new sly_Util_Requirements();
		$level     = error_reporting(0);

		$results['php_version']    = array('5.2', '5.4', $tester->phpVersion());
		$results['php_time_limit'] = array('20s', '60s', $tester->execTime());
		$results['php_mem_limit']  = array('16MB', '32MB', $tester->memoryLimit());
		$results['php_pseudo']     = array('translate:none', 'translate:none', $tester->nonsenseSecurity());

		error_reporting($level);

		foreach ($results as $result) {
			$errors   |= $result[2]['status'] === sly_Util_Requirements::FAILED;
			$warnings |= $result[2]['status'] === sly_Util_Requirements::WARNING;
		}

		$sysErrors = $errors;

		// init directories

		$cantCreate = $this->checkDirsAndFiles();
		$protected  = array(SLY_DEVELOPFOLDER, SLY_DYNFOLDER.'/internal');
		$protects   = array();

		foreach ($protected as $i => $directory) {
			if (!sly_Util_Directory::createHttpProtected($directory)) {
				$protects[] = realpath($directory);
				$errors = true;
			}
		}

		if (!empty($cantCreate)) {
			$errors = true;
		}

		// forward if OK
		if (!$errors && !$warnings) {
			return $this->dbconfigAction();
		}

		$params = compact('sysErrors', 'results', 'protects', 'errors', 'cantCreate', 'tester');
		$this->render('setup/syscheck.phtml', $params, false);
	}

	public function dbconfigAction() {
		$this->init();

		$config  = sly_Core::config();
		$data    = $config->get('DATABASE');
		$isSent  = isset($_POST['submit']);
		$drivers = sly_DB_PDO_Driver::getAvailable();

		if (empty($drivers)) {
			$this->flash->appendWarning(t('setup_no_drivers_available'));
			$sent = false;
		}

		if ($isSent) {
			$TABLE_PREFIX = sly_post('prefix', 'string');
			$HOST         = sly_post('host', 'string');
			$LOGIN        = sly_post('user', 'string');
			$PASSWORD     = sly_post('pass', 'string');
			$NAME         = sly_post('dbname', 'string');
			$DRIVER       = sly_post('driver', 'string');
			$create       = sly_post('create_db', 'bool') && ($DRIVER !== 'sqlite' && $DRIVER !== 'oci');

			try {
				if (!in_array($DRIVER, $drivers)) {
					throw new sly_Exception(t('setup_invalid_driver'));
				}

				// open connection

				if ($create) {
					$db = new sly_DB_PDO_Persistence($DRIVER, $HOST, $LOGIN, $PASSWORD);
				}
				else {
					$db = new sly_DB_PDO_Persistence($DRIVER, $HOST, $LOGIN, $PASSWORD, $NAME);
				}

				// prepare version check, retrieve min versions from driver

				$driverClass = 'sly_DB_PDO_Driver_'.strtoupper($DRIVER);
				$driverImpl  = new $driverClass('', '', '', '');
				$constraints = $driverImpl->getVersionConstraints();

				// check version

				$helper = new sly_Util_Requirements();
				$result = $helper->pdoDriverVersion($db->getConnection(), $constraints);

				// warn only, but continue workflow
				if ($result['status'] === sly_Util_Requirements::WARNING) {
					$this->flash->appendWarning($result['text']);
				}

				// stop further code
				elseif ($result['status'] === sly_Util_Requirements::FAILED) {
					throw new sly_Exception($result['text']);
				}

				if ($create) {
					$createStmt = $driverImpl->getCreateDatabaseSQL($NAME);
					$db->query($createStmt);
				}

				$data = compact('DRIVER', 'HOST', 'LOGIN', 'PASSWORD', 'NAME', 'TABLE_PREFIX');

				$config->setLocal('DATABASE', $data);
				unset($_POST['submit']);
				$this->initdbAction();
				return;
			}
			catch (sly_DB_PDO_Exception $e) {
				$this->flash->appendWarning($e->getMessage());
			}
		}

		$this->render('setup/dbconfig.phtml', array(
			'host'    => $data['HOST'],
			'user'    => $data['LOGIN'],
			'pass'    => $data['PASSWORD'],
			'dbname'  => $data['NAME'],
			'prefix'  => $data['TABLE_PREFIX'],
			'driver'  => $data['DRIVER'],
			'drivers' => $drivers
		), false);
	}

	public function configAction() {
		$this->init();

		$config = sly_Core::config();
		$isSent = isset($_POST['submit']);

		if ($isSent) {
			$uid = sha1(microtime(true).mt_rand(10000, 90000));
			$uid = substr($uid, 0, 20);

			$config->set('PROJECTNAME', sly_post('projectname', 'string'));
			$config->setLocal('INSTNAME', 'sly'.$uid);

			$config->set('TIMEZONE', sly_post('timezone', 'string', null));
			$config->set('DEFAULT_LOCALE', $this->lang);

			unset($_POST['submit']);
			$this->createuserAction();
			return;
		}

		$this->render('setup/config.phtml', array(
			'projectName' => $config->get('PROJECTNAME'),
			'timezone'    => @date_default_timezone_get()
		), false);
	}

	public function initdbAction() {
		$this->init();

		$dbInitFunction = sly_post('db_init_function', 'string', '');

		if (isset($_POST['submit'])) {
			$config  = sly_Core::config();
			$prefix  = $config->get('DATABASE/TABLE_PREFIX');
			$driver  = $config->get('DATABASE/DRIVER');
			$success = true;

			// benötigte Tabellen prüfen

			$requiredTables = array(
				$prefix.'article',
				$prefix.'article_slice',
				$prefix.'clang',
				$prefix.'file',
				$prefix.'file_category',
				$prefix.'user',
				$prefix.'slice',
				$prefix.'registry'
			);

			switch ($dbInitFunction) {
				case 'drop': // delete old database
					$db = sly_DB_Persistence::getInstance();

					// 'DROP TABLE IF EXISTS' is MySQL-only...
					foreach ($db->listTables() as $tblname) {
						if (in_array($tblname, $requiredTables)) $db->query('DROP TABLE '.$tblname);
					}

					// fallthrough

				case 'setup': // setup empty database with fresh tables
					$script  = SLY_COREFOLDER.'/install/'.strtolower($driver).'.sql';
					$success = $this->setupImport($script);

					break;

				case 'nop': // do nothing
				default:
			}

			// Wenn kein Fehler aufgetreten ist, aber auch etwas geändert wurde, prüfen
			// wir, ob dadurch alle benötigten Tabellen erzeugt wurden.

			if ($success) {
				$existingTables = array();
				$db             = sly_DB_Persistence::getInstance();
				foreach ($db->listTables() as $tblname) {
					if (substr($tblname, 0, strlen($prefix)) === $prefix) {
						$existingTables[] = $tblname;
					}
				}

				foreach (array_diff($requiredTables, $existingTables) as $missingTable) {
					$this->flash->appendWarning(t('setup_initdb_table_not_found', $missingTable));
					$success = false;
				}
			}

			if ($success) {
				// redirect
				unset($_POST['submit']);
				$this->configAction();
				return;
			}
			else {
				$this->flash->appendWarning(t('setup_initdb_reinit'));
			}
		}

		$this->render('setup/initdb.phtml', array(
			'dbInitFunction'  => $dbInitFunction,
			'dbInitFunctions' => array('setup', 'nop', 'drop')
		), false);
	}

	public function createuserAction() {
		$this->init();

		$config      = sly_Core::config();
		$prefix      = $config->get('DATABASE/TABLE_PREFIX');
		$pdo         = sly_DB_Persistence::getInstance();
		$usersExist  = $pdo->listTables($prefix.'user') && $pdo->magicFetch('user', 'id') !== false;
		$createAdmin = !sly_post('no_admin', 'boolean', false);
		$adminUser   = sly_post('admin_user', 'string');
		$adminPass   = sly_post('admin_pass', 'string');
		$success     = true;

		if (isset($_POST['submit'])) {
			if ($createAdmin) {
				if (empty($adminUser)) {
					$this->flash->appendWarning(t('setup_createuser_no_admin_given'));
					$success = false;
				}

				if (empty($adminPass)) {
					if (!empty($error)) $error .= ' ';
					$this->flash->appendWarning(t('setup_createuser_no_password_given'));
					$success = false;
				}

				if ($success) {
					$service = sly_Service_Factory::getUserService();
					$user    = $service->find(array('login' => $adminUser));
					$user    = empty($user) ? new sly_Model_User() : reset($user);

					$user->setName(ucfirst(strtolower($adminUser)));
					$user->setLogin($adminUser);
					$user->setRights('#admin[]#');
					$user->setStatus(true);
					$user->setCreateDate(time());
					$user->setUpdateDate(time());
					$user->setLastTryDate(0);
					$user->setCreateUser('setup');
					$user->setUpdateUser('setup');
					$user->setPassword($adminPass);
					$user->setRevision(0);

					try {
						$service->save($user, $user);
					}
					catch (Exception $e) {
						$this->flash->appendWarning(t('setup_createuser_cant_create_admin'));
						$this->flash->appendWarning($e->getMessage());
						$success = false;
					}
				}
			}
			elseif (!$usersExist) {
				$this->flash->appendWarning(t('setup_createuser_no_users_found'));
				$success = false;
			}

			if ($success) {
				// redirect
				unset($_POST['submit']);
				$this->finishAction();
				return;
			}
		}

		$this->render('setup/createuser.phtml', array(
			'usersExist' => $usersExist,
			'adminUser'  => $adminUser
		), false);
	}

	public function finishAction() {
		$this->init();
		sly_Core::config()->setLocal('SETUP', false);
		$this->render('setup/finish.phtml', array(), false);
	}

	protected function title($title) {
		$layout = sly_Core::getLayout();
		$layout->pageHeader($title);
	}

	protected function checkDirsAndFiles() {
		$s         = DIRECTORY_SEPARATOR;
		$errors    = array();
		$writables = array(
			SLY_MEDIAFOLDER,
			SLY_DEVELOPFOLDER.$s.'templates',
			SLY_DEVELOPFOLDER.$s.'modules'
		);

		$level = error_reporting(0);

		foreach ($writables as $dir) {
			if (!sly_Util_Directory::create($dir)) {
				$errors[] = $dir;
			}
		}

		error_reporting($level);
		return $errors;
	}

	protected function printHiddens($func, $form) {
		$form->addHiddenValue('page', 'setup');
		$form->addHiddenValue('func', $func);
		$form->addHiddenValue('lang', $this->lang);
	}

	protected function setupImport($sqlScript) {
		if (file_exists($sqlScript)) {
			try {
				$importer = new sly_DB_Importer();
				$importer->import($sqlScript);
			}
			catch (Exception $e) {
				$this->flash->addWarning($e->getMessage());
				return false;
			}
		}
		else {
			$this->flash->addWarning(t('setup_import_dump_not_found'));
			return false;
		}

		return true;
	}

	public function checkPermission($action) {
		return sly_Core::config()->get('SETUP') === true;
	}
}
