<?php
/**
 * Created by IntelliJ IDEA.
 * User: apple
 * Date: 16/3/2
 * Time: 下午3:52
 */

function concatParams($params)
{
    ksort($params);
    $pairs = array();
    foreach ($params as $key => $val)
        array_push($pairs, $key . '=' . $val);
    return join('&', $pairs);
}

function genSig($pathUrl, $params, $consumerSecret)
{
    $params = concatParams($params);
    $str = $pathUrl . '?' . $params . $consumerSecret;
    return md5($str);
}

/**
 * cur post的处理函数
 */
function curl($url, $postFields = null, $headers = null)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    //curl_setopt($ch, CURLOPT_FAILONERROR, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);//设置为POST方式
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

    $reponse = curl_exec($ch);
    curl_close($ch);
    return $reponse;
    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch), 0);
    } else {
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (200 !== $httpStatusCode) {
            throw new Exception($reponse, $httpStatusCode);
        }
    }
    curl_close($ch);
    return $reponse;
}

/**
 * cur get的处理函数
 */
function curl_get($url, $postFields = null, $headers)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    //curl_setopt($ch, CURLOPT_FAILONERROR, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $reponse = curl_exec($ch);
    curl_close($ch);
    //var_dump($reponse);
    return $reponse;
    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch), 0);
    } else {
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (200 !== $httpStatusCode) {
            throw new Exception($reponse, $httpStatusCode);
        }
    }
    curl_close($ch);
    return $reponse;
}


//对象转数组,使用get_object_vars返回对象属性组成的数组
function objectToArray($obj)
{
    $arr = is_object($obj) ? get_object_vars($obj) : $obj;
    if (is_array($arr)) {
        return array_map(__FUNCTION__, $arr);
    } else {
        return $arr;
    }
}

/**
 * 打印数组的函数
 */
function p($array)
{
    dump($array, 1, '<pre>', 0);
}

/**
 * 拼接字段函数
 */
function get_str($arr)
{
    $fields_string = '';
    foreach ($arr as $key => $value) {
        $fields_string .= $key . '=' . $value . '&';
    }
    return rtrim($fields_string, '&');
}

// 自动转换字符集 支持数组转换
function auto_charset($fContents, $from, $to)
{
    $from = strtoupper($from) == 'UTF8' ? 'utf-8' : $from;
    $to = strtoupper($to) == 'UTF8' ? 'utf-8' : $to;
    if (strtoupper($from) === strtoupper($to) || empty($fContents) || (is_scalar($fContents) && !is_string($fContents))) {
        //如果编码相同或者非字符串标量则不转换
        return $fContents;
    }
    if (is_string($fContents)) {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($fContents, $to, $from);
        } elseif (function_exists('iconv')) {
            return iconv($from, $to, $fContents);
        } else {
            return $fContents;
        }
    } elseif (is_array($fContents)) {
        foreach ($fContents as $key => $val) {
            $_key = auto_charset($key, $from, $to);
            $fContents[$_key] = auto_charset($val, $from, $to);
            if ($key != $_key)
                unset($fContents[$key]);
        }
        return $fContents;
    } else {
        return $fContents;
    }
}

?>
