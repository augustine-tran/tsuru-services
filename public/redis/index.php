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
    $response->setContent(json_encode($plans));
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

    $response = new \Phalcon\Http\Response();
    $response->setStatusCode(201, "");
    return $response;
});

/**
 * Bind
 * http://docs.tsuru.io/en/latest/services/build.html#bind
 */
$app->post('/resources/{name:[a-zA-Z0-9\-]+}', function ($name) use ($app, $config) {

    $appHost = $app->request->getPost('app-host');
    $unitHost = $app->request->getPost('unit-host');

    $response = new \Phalcon\Http\Response();
    $response->setStatusCode(201, "");
    $params = [
        'REDIS_DB' => (string)(rand(1, 1000)),
        'REDIS_HOST' => $config->redis->host,
        'REDIS_PORT' => "6379",
    ];
    $content = json_encode($params);
    $response->setContent($content);
    return $response;
});

/**
 * Unbinding instances from apps
 * http://docs.tsuru.io/en/latest/services/build.html#removing-instances
 */
$app->delete('/resources/{name:[a-zA-Z0-9\-]+}/hostname/{host:[a-zA-Z0-9\-\.]+}', function ($name, $host) use ($app) {
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