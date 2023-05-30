<?php

$options = getopt('', ['ip:']);
$ip = $options['ip'];

// Для того чтобы в конце узнать какое время выполняется скрипт
$start = microtime(true);

// Биржа GATE.IO - реквизиты доступа
// Согласно документации API, максимально можно делать 900 обращений в секунду. Если выше - бан на пару минут
$market_api_url = 'https://api.gateio.ws/api/v4';
$market_api_key = '525269c3c5669480930c1f7ce3ff1f43';
$market_api_secret_key = '4071f76f2cb3a06ee4e2495a5501fb31e9cb08ec3212ab1536e49da57838a232';
//

require __DIR__ . '/classes/autoload.php';

// Функция подписи для обращения к API
function get_signed_params_gateio($market_api_key, $market_api_secret_key, $params)
    {
    $params = array_merge(['api_key' => $market_api_key], $params);
    ksort($params);
    $signature = hash_hmac('sha256', urldecode(http_build_query($params)), $market_api_secret_key);
    return http_build_query($params) . "&sign=$signature";
    }
//

//Получаем список пар, у которых необходимо получать среднюю стоимость
$array_pairs = require __DIR__.'/array_pairs.php';

// Счетчик количества пар
$kolvo = count($array_pairs);    
    
$tasks = [];

// Начинаем перебор пар монет по массиву
foreach ($array_pairs as $k => $v) {
    foreach ($v as $item => $val) {
        if ($item == 'pair_id') {
            $pair_id = $val;
        }
        if ($item == 'coin1') {
            $coin1 = $val;
        }
        if ($item == 'coin2') {
            $coin2 = $val;
        }
    }

    $tasks[] = function () use ($market_api_url, $market_api_key, $market_api_secret_key, $coin1, $coin2) {
        
        require __DIR__ . '/classes/Http/Requester.php';
        foreach ([
                     [$coin1, $coin2],
                     [$coin2, $coin1],
                 ] as $pair) {

            echo "Начинаем обработку пары {$pair[0]} - {$pair[1]}\r\n";
            $requester = new \App\Http\Requester();

			// Разделитель между парами (у каждой биржи он свой)
            $delimiter = '_';
            
            $pairs = $pair[0].$delimiter.$pair[1];
            
            // Используем метод АПИ для получения данных книги заказов
            $market_api_method = '/spot/order_book';
            
            $params = [
            'currency_pair' => strtoupper($pairs)
            ];            
            
            $response = $requester->getResponseByPair(
                $market_api_url, $market_api_key, $market_api_secret_key, $market_api_method, $params, $pair[0], $pair[1], $delimiter,
                $ip
            );
            if (isset($response['bids']) && isset($response['asks'])) 
                {
                $response['coin1'] = $coin1;
                $response['coin2'] = $coin2;
                break;
                }
        }

        // echo '<pre>';
        // print_r($response);
        // echo '</pre>';

        return $response;

    };
}

// ----

$futures = [];

// Максимальное количество потоков
//$max = 200;

// Максимальное количество задач, которое выполнится за 1 секунду
$maxInSecond = 50;

while (true) {

    $startTime = time();

    // echo 'THREADS: ' . count($futures) . PHP_EOL;

    // Если еще есть незапущенные задачи
    if (!empty($tasks)) {
        // Сколько можем запустить? Добить до максимума, но не больше, чем осталось задач
        $toRun = min($maxInSecond - count($futures), count($tasks));
        for ($i = 1; $i <= $toRun; $i++) {
            // Забираем задачу из готовых, удаляя ее из списка
            $task = array_shift($tasks);
            // Запускаем эту задачу в потоке
            $thread = new \parallel\Runtime();
            $future = $thread->run($task);
            $futures[] = $future;
            // echo 'THREADS: ' . count($futures) . PHP_EOL;
        }
    }

    while (!empty($futures)) {
        // Проверим, не закончились ли какие-либо задачи
        foreach ($futures as $n => $future) {
            if ($future->done()) {
                unset($futures[$n]);
                // echo 'THREADS: ' . count($futures) . PHP_EOL;
                $value = $future->value();
                echo 'RESULT:';
                var_dump($value);
            }
        }
    }

    // Все 10 задач здесь уже завершены
    // Ждем следующую секунду
    while (!(time() >= $startTime + 1)) {}

    // Выходим, если все задачи уже выполнены и нет тех, что можно запустить
    if (empty($futures) && empty($tasks)) {
        // echo 'THREADS: ' . count($futures) . PHP_EOL;
        break;
    }

}


// Выводим на экран результат парсинга
echo 'Количество пар: '.$kolvo."\r\n";
echo 'Общее время выполнения скрипта: ' . round(microtime(true) - $start, 4) . " сек. \r\n";
