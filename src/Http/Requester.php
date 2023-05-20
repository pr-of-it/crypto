<?php

namespace App\Http;

class Requester
{
    private const CONFIG = [
        'market_api_url' => 'https://api.gateio.ws/api/v4',
        'market_api_key' => '',
        'market_api_secret_key' => '',
    ];

    private function get_signed_params_gateio($market_api_key, $market_api_secret_key, $params)
    {
        $params = array_merge(['api_key' => $market_api_key], $params);
        ksort($params);
        $signature = hash_hmac('sha256', urldecode(http_build_query($params)), $market_api_secret_key);
        return http_build_query($params) . "&sign=$signature";
    }

    private function makeRequest(string $url): array
    {
        $curl=curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        $response=curl_exec($curl);

        return json_decode($response,true);
    }

    public function getResponseByPair($coin1, $coin2)
    {
        $pairs = $coin1.'_'.$coin2;

        // Используем метод АПИ для получения данных книги заказов
        $market_api_method = '/spot/order_book';

        $params = [
            'currency_pair' => strtoupper($pairs)
        ];

        $url = self::CONFIG['market_api_url'].$market_api_method;

        $qs=$this->get_signed_params_gateio(self::CONFIG['market_api_key'], self::CONFIG['market_api_secret_key'], $params);

        $curl_url=$url."?".$qs;
        return $this->makeRequest($curl_url);
    }
}