<?php
/**
 * 火山AI生图 - 支付宝当面付工具类
 * 
 * 从 data/settings.json 读取配置，支持后台页面配置
 */
class Alipay {
    private $app_id;
    private $private_key;
    private $alipay_public_key;
    private $gateway = 'https://openapi.alipay.com/gateway.do';
    private $notify_url;

    public function __construct() {
        // 从 settings.json 读取支付配置
        $settingsFile = __DIR__ . '/../data/settings.json';
        $settings = [];
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
        }
        
        $this->app_id = $settings['alipay_app_id'] ?? '';
        $this->private_key = $settings['alipay_private_key'] ?? '';
        $this->alipay_public_key = $settings['alipay_public_key'] ?? '';
        
        // 通知地址：优先用用户配置的，否则自动检测当前域名
        $this->notify_url = $settings['payment_notify_url'] ?? '';
        if (empty($this->notify_url)) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $this->notify_url = "{$protocol}://{$host}/pay/notify.php";
        }
        
        if (empty($this->app_id) || empty($this->private_key) || empty($this->alipay_public_key)) {
            throw new Exception('支付宝未配置完整，请先在后台 /admin/ 配置收款信息');
        }
    }

    public function precreate($out_trade_no, $total_amount, $subject, $body = '') {
        $biz_content = [
            'out_trade_no' => $out_trade_no,
            'total_amount' => number_format($total_amount, 2, '.', ''),
            'subject' => $subject,
            'body' => $body ?: $subject,
            'timeout_express' => '30m',
        ];

        $params = $this->buildParams('alipay.trade.precreate', $biz_content);
        $result = $this->curl($params);

        $response_key = 'alipay_trade_precreate_response';
        if (!isset($result[$response_key])) {
            throw new Exception('支付宝返回格式异常: ' . json_encode($result));
        }
        $response = $result[$response_key];
        if ($response['code'] != '10000') {
            throw new Exception('支付宝错误: ' . ($response['sub_msg'] ?? $response['msg'] ?? '未知错误'));
        }
        return $response;
    }

    public function query($out_trade_no) {
        $biz_content = ['out_trade_no' => $out_trade_no];
        $params = $this->buildParams('alipay.trade.query', $biz_content);
        $result = $this->curl($params);
        $response_key = 'alipay_trade_query_response';
        if (!isset($result[$response_key])) {
            throw new Exception('查询返回格式异常');
        }
        return $result[$response_key];
    }

    public function verifyNotify($params) {
        $sign = $params['sign'] ?? '';
        $sign_params = $params;
        unset($sign_params['sign'], $sign_params['sign_type']);
        $content = $this->getSignContent($sign_params);
        $pubKey = openssl_get_publickey($this->alipay_public_key);
        if (!$pubKey) return false;
        $ok = openssl_verify($content, base64_decode($sign), $pubKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($pubKey);
        return $ok;
    }

    private function buildParams($method, $biz_content) {
        $params = [
            'app_id' => $this->app_id,
            'method' => $method,
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'notify_url' => $this->notify_url,
            'biz_content' => json_encode($biz_content, JSON_UNESCAPED_UNICODE),
        ];
        $params['sign'] = $this->sign($params);
        return $params;
    }

    private function sign($params) {
        $content = $this->getSignContent($params);
        $privateKey = openssl_get_privatekey($this->private_key);
        if (!$privateKey) throw new Exception('支付宝私钥无效，请在后台重新配置');
        openssl_sign($content, $sign, $privateKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($privateKey);
        return base64_encode($sign);
    }

    private function getSignContent($params) {
        ksort($params);
        $parts = [];
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) continue;
            $parts[] = $k . '=' . $v;
        }
        return implode('&', $parts);
    }

    private function curl($params) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->gateway . '?' . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HEADER => false,
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code != 200) {
            throw new Exception("支付宝接口请求失败: HTTP {$http_code}");
        }
        $result = json_decode($response, true);
        if (!$result) {
            throw new Exception("支付宝接口返回非JSON: " . substr($response, 0, 200));
        }
        return $result;
    }
}
