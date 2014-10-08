<?php
$config = new \Phalcon\Config\Adapter\Ini(__DIR__ . '/config.ini');

// Use Loader() to autoload our model
$loader = new \Phalcon\Loader();

$loader->registerDirs(array(
    __DIR__ . '/models/'
))->register();

$di = new \Phalcon\DI\FactoryDefault();

//Set up the database service
$di->set('db', function () use ($config) {
    return new \Phalcon\Db\Adapter\Pdo\Mysql(array(
        "host" => $config->db->host,
        "username" => $config->db->username,
        "password" => $config->db->password,
        "dbname" => $config->db->name,
        "charset" => 'utf8'
    ));
});

//Create a events manager
$app = new \Phalcon\Mvc\Micro($di);
$app->getSharedService('response')->setContentType('application/json', 'utf-8');

/**
 * Listing available plans
 * http://docs.tsuru.io/en/latest/services/build.html#listing-available-plans
 */
$app->get('/resources/plans', function () use ($app) {
    $plans = [
        ['name' => 'small', 'description' => '...'],
        ['name' => 'big', 'description' => '...'],
        ['name' => 'medium', 'description' => '...'],
    ];

    /** @var Phalcon\Db\Adapter\Pdo $db */
    $db = $app->getDI()->getShared('db');
    $rows = $db->query("SELECT * FROM instance")->fetchAll();
    print_r($rows);die;


    $response = new \Phalcon\Http\Response();
    $response->setStatusCode(201, "");
    $response->setContent("Access is not authorized");
    return $response;
});

/**
 * Creating new instances
 * http://docs.tsuru.io/en/latest/services/build.html#creating-new-instances
 */
$app->post('/resources', function () use ($app) {

    $name = $app->request->getPost('name');
    $plan = $app->request->getPost('plan');
    $team = $app->request->getPost('team');

    /** @var Phalcon\Db\Adapter\Pdo $db */
    $db = $app->getDI()->getShared('db');
    try {
        $db->execute('CREATE DATABASE ' . $name);

        $jsonData = [
            'MYSQL_DATABASE_NAME' => $name,
            'MYSQL_HOST' => '192.168.1.241',
            'MYSQL_PORT' => "3306"
        ];
        $db->insert('instance', [$name, $team, $plan, json_encode($jsonData)], ['name', 'team', 'plan', 'params']);
    } catch (\Exception $e) {
        $response = new \Phalcon\Http\Response();
        $response->setStatusCode(401, $e->getMessage());
        return $response;
    }

    $response = new \Phalcon\Http\Response();
    $response->setStatusCode(201, "");
    return $response;
});

/**
 * Bind
 * http://docs.tsuru.io/en/latest/services/build.html#bind
 */
$app->post('/resources/{name:[a-zA-Z0-9\-]+}', function ($name) use ($app) {

    $appHost = $app->request->getPost('app-host');
    $unitHost = $app->request->getPost('unit-host');

    /** @var Phalcon\Db\Adapter\Pdo $db */
    $db = $app->getDI()->getShared('db');

    $test = $db->query("SELECT * FROM env WHERE name = '$appHost' OR code='$unitHost'")->fetch();
    if ($test) {
        $params = json_decode($test['value']);
        $params = [
            'MYSQL_DATABASE_NAME' => $params->MYSQL_DATABASE_NAME,
            'MYSQL_HOST' => $params->MYSQL_HOST,
            'MYSQL_PORT' => $params->MYSQL_PORT,
            'MYSQL_USER' => $params->MYSQL_USER,
            'MYSQL_PASSWORD' => $params->MYSQL_PASSWORD
        ];
        $response = new \Phalcon\Http\Response();
        $response->setStatusCode(201, "");
        $content = json_encode($params);
        $response->setContent($content);
        return $response;
    }

    $userName = 'u_' . rand(10, 99) . '_' .time();
    $password = md5($name . time() . rand(100, 999));
    $db->execute("CREATE USER '$userName'@'%' IDENTIFIED BY '$password'; ");
    $db->execute("GRANT ALL PRIVILEGES ON `$name`.* TO '$userName'@'%';");
    $db->execute("FLUSH PRIVILEGES;");

    $row = $db->query("SELECT * FROM instance WHERE name = '$name'")->fetch();

    $response = new \Phalcon\Http\Response();
    $response->setStatusCode(201, "");
    $params = json_decode($row['params']);
    $params = [
        'MYSQL_DATABASE_NAME' => $params->MYSQL_DATABASE_NAME,
        'MYSQL_HOST' => $params->MYSQL_HOST,
        'MYSQL_PORT' => $params->MYSQL_PORT,
        'MYSQL_USER' => $userName,
        'MYSQL_PASSWORD' => $password
    ];
    $content = json_encode($params);$response->setContent($content);
    $db->insert('env', [$appHost, $unitHost, $content], ['name', 'code', 'value']);
    return $response;
});

/**
 * Unbinding instances from apps
 * http://docs.tsuru.io/en/latest/services/build.html#removing-instances
 */
$app->delete('/resources/{name:[a-zA-Z0-9\-]+}/hostname/{host:[a-zA-Z0-9\-\.]+}', function ($name, $host) use ($app) {
    /** @var Phalcon\Db\Adapter\Pdo $db */
    $db = $app->getDI()->getShared('db');

    $row = $db->query("SELECT * FROM env WHERE name = '$name' OR code='$host'")->fetch();
    $params = json_decode($row['value']);

    $db->delete('env', "id={$row['id']}");
    $db->execute("DROP USER '{$params->MYSQL_USER}'@'%';");

    $response = new \Phalcon\Http\Response();
    $response->setStatusCode(401, "");
    return $response;
});

/**
 * Removing instances
 * http://docs.tsuru.io/en/latest/services/build.html#removing-instances
 */
$app->delete('/resources/{name:[a-zA-Z0-9\-]+}', function ($name) use ($app) {
    /** @var Phalcon\Db\Adapter\Pdo $db */
    $db = $app->getDI()->getShared('db');
    $db->execute('DROP DATABASE ' . $name);

    $response = new \Phalcon\Http\Response();
    $response->setStatusCode(201, "");
    return $response;
});

$app->notFound(function () use ($app) {
    $app->response->setStatusCode(404, "Not Found")->sendHeaders();
    $actual_link = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI] " . $app->request->getMethod();
    echo 'This is crazy, but this page was not found! ' . $actual_link;
});

$app->handle();