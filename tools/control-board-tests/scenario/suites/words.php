<?php
if (in_array('debug', $GLOBALS['only'], true)) { fresh(); var_dump(report(['overture_bulk' => ['failed', "TimeoutError('x')", []]])); var_dump($GLOBALS['pdo']->query("SELECT * FROM control_alarms")->fetchAll(PDO::FETCH_ASSOC)); }
