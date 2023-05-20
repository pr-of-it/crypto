<?php

require __DIR__ . '/autoload.php';

// Функция подписи для обращения к API
function get_signed_params_gateio($market_api_key, $market_api_secret_key, $params)
{
    $params = array_merge(['api_key' => $market_api_key], $params);
    ksort($params);
    $signature = hash_hmac('sha256', urldecode(http_build_query($params)), $market_api_secret_key);
    return http_build_query($params) . "&sign=$signature";
}

$start = microtime(true);

$market_api_url = 'https://api.gateio.ws/api/v4';
$market_api_key = '';
$market_api_secret_key = '';

// Данные у нас читаются из базы и при чтении помещаются в массив, пример которого получается ниже
$array_pairs = [
    ['pair_id' => '1', 'coin1' => 'USDT', 'coin2' => '10SET'],
    ['pair_id' => '2', 'coin1' => '1INCH3L', 'coin2' => 'USDT'],
    ['pair_id' => '3', 'coin1' => '1ART', 'coin2' => 'USDT'],
    ['pair_id' => '4', 'coin1' => '3KM', 'coin2' => 'USDT'],
    ['pair_id' => '5', 'coin1' => '88MPH', 'coin2' => 'ETH'],
    ['pair_id' => '6', 'coin1' => 'A5T', 'coin2' => 'ETH'],
    ['pair_id' => '7', 'coin1' => 'A5T', 'coin2' => 'USDT'],
    ['pair_id' => '8', 'coin1' => 'AAA', 'coin2' => 'ETH'],
    ['pair_id' => '9', 'coin1' => 'AAA', 'coin2' => 'USDT'],
    ['pair_id' => '10', 'coin1' => 'AAG', 'coin2' => 'USDT'],
    ['pair_id' => '11', 'coin1' => 'AAVE', 'coin2' => 'ETH'],
    ['pair_id' => '12', 'coin1' => 'AAVE', 'coin2' => 'TRY'],
    ['pair_id' => '13', 'coin1' => 'AAVE', 'coin2' => 'USDT'],
    ['pair_id' => '14', 'coin1' => 'ACE', 'coin2' => 'USDT'],
    ['pair_id' => '15', 'coin1' => 'ACM', 'coin2' => 'USDT'],
    ['pair_id' => '16', 'coin1' => 'ACS', 'coin2' => 'USDT'],
    ['pair_id' => '17', 'coin1' => 'ACX', 'coin2' => 'USDT']
];

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

    $tasks[] = function () use ($coin1, $coin2) {
        require __DIR__ . '/Http/Requester.php';
        foreach ([
                     [$coin1, $coin2],
                     [$coin2, $coin1],
                 ] as $pair) {

            echo "Начинаем обработку пары {$pair[0]} - {$pair[1]}\r\n";
            $requester = new \App\Http\Requester();

            $response = $requester->getResponseByPair($pair[0], $pair[1]);
            if (isset($response['bids']) && isset($response['asks'])) {
                break;
            }
        }

        echo '<pre>';
        print_r($response);
        echo '</pre>';

        return $response;

    };
}

// ----

$futures = [];
$max = 5;
while (true) {

    echo 'THREADS: ' . count($futures) . PHP_EOL;

    // Если еще есть незапущенные задачи
    if (!empty($tasks)) {
        // Сколько можем запустить? Добить до максимума, но не больше, чем осталось задач
        $toRun = min($max - count($futures), count($tasks));
        for ($i = 1; $i <= $toRun; $i++) {
            // Забираем задачу из готовых, удаляя ее из списка
            $task = array_shift($tasks);
            // Запускаем эту задачу в потоке
            $thread = new \parallel\Runtime();
            $future = $thread->run($task);
            $futures[] = $future;
            echo 'THREADS: ' . count($futures) . PHP_EOL;
        }
    }

    //sleep(1);
    usleep(100_000);

    // Проверим, не закончились ли какие-либо задачи
    foreach ($futures as $n => $future) {
        if ($future->done()) {
            unset($futures[$n]);
            echo 'THREADS: ' . count($futures) . PHP_EOL;
            $value = $future->value();
            echo 'RESULT:';
            var_dump($value);
        }
    }

    // Выходим, если все задачи уже выполнены и нет тех, что можно запустить
    if (empty($futures) && empty($tasks)) {
        echo 'THREADS: ' . count($futures) . PHP_EOL;
        break;
    }

}

// Выводим на экран результат парсинга
echo 'Общее время выполнения скрипта: ' . round(microtime(true) - $start, 4) . " сек. \r\n";