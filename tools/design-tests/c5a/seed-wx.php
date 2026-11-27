<?php require $argv[1] . '/app/bootstrap.php'; require_once $argv[1] . '/app/lib/PageCache.php'; $pdo = Database::connection();
$now = ['temp_f' => 41, 'text' => 'Light Snow', 'wind' => '15 mph W', 'humidity' => 70, 'pop' => 60, 'at' => gmdate('c')];
$per = []; for ($i = 0; $i < 6; $i++) $per[] = ['name' => 'P' . $i, 'temp' => 40 + $i, 'short' => 'Snow Showers', 'wind' => '10 mph', 'pop' => 50, 'day' => $i % 2 === 0, 'start' => gmdate('c', time() + $i * 43200)];
$hours = []; for ($i = 0; $i < 24; $i++) $hours[] = ['at' => gmdate('c', time() + $i * 3600), 'temp' => 38 + $i % 6, 'pop' => 40, 'mph' => 12, 'short' => 'Snow', 'day' => true];
$w = ['now' => $now, 'periods' => $per, 'hours' => $hours, 'days' => [['day' => 'Fri', 'hi' => 44, 'lo' => 25, 'short' => 'Snow', 'pop' => 60, 'night' => false]], 'link' => 'https://forecast.weather.gov/',
      'alerts' => [['event' => 'Winter Storm Warning', 'headline' => 'Heavy snow expected above 6000 feet through Saturday morning.', 'severity' => 'Severe', 'ends' => gmdate('c', time() + 86400), 'instruction' => 'If you must travel, keep an extra flashlight, food and water in your vehicle.', 'description' => '']]];
PageCache::put($pdo, 'wxs:34.254,-110.03', 43200, $w);
PageCache::put($pdo, 'az511:wzdx', 86400, [['road' => 'SR 260', 'road_name' => 'SR 260', 'where' => 'Milepost 340 to 342, Show Low', 'what' => 'Lane restrictions for paving', 'closed' => false, 'type' => 'Work zone', 'start' => time() - 86400, 'end' => time() + 5 * 86400, 'pts' => [[34.25, -110.03]], 'updated' => time()]]);
echo "seeded\n";
