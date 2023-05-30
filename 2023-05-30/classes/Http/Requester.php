<?php

namespace App\Http;

class Requester
{

    private function get_signed_params_gateio($market_api_key, $market_api_secret_key, $params)
    {
        $params = array_merge(['api_key' => $market_api_key], $params);
        ksort($params);
        $signature = hash_hmac('sha256', urldecode(http_build_query($params)), $market_api_secret_key);
        return http_build_query($params) . "&sign=$signature";
    }

    private function makeRequest($url)
    {
        $curl=curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        //curl_setopt($curl, CURLOPT_INTERFACE, 0);

        echo "\r\n Соединяемся с: ".$url."\r\n";
        
        $response=curl_exec($curl);
        
        return json_decode($response,true);
    }

    public function getResponseByPair($market_api_url, $market_api_key, $market_api_secret_key, $market_api_method, $params, $coin1, $coin2, $delimiter, $ip = null)
    {
        $pairs = $coin1.$delimiter.$coin2;

        $url = $market_api_url.$market_api_method;

        $qs=$this->get_signed_params_gateio($market_api_key, $market_api_secret_key, $params);

        $curl_url=$url."?".$qs;
        return $this->makeRequest($curl_url);
    }
}
