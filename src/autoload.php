<?php

//we do not want to use composer autoloading in this case
include_once __DIR__ . '/Exception/RuntimeException.php';
include_once __DIR__ . '/Exception/InvalidArgumentException.php';
include_once __DIR__ . '/Config.php';
include_once __DIR__ . '/ConfigFactory.php';
include_once __DIR__ . '/PgPass.php';
include_once __DIR__ . '/SyncAdapter/AdapterAbstract.php';
include_once __DIR__ . '/SyncAdapter/FileSystem.php';
include_once __DIR__ . '/SyncAdapter/Database.php';
require_once __DIR__ . '/Migrator.php';
