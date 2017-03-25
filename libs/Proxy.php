<?php
include Router::$basePath.'/libs/functions/__base__.php';

class Proxy extends Component {
    public static $sid;
    public static $pwd;
    public static $baseUrl;
    public static $schoolCode;
    public static $orcApi;
    public static $startDate;

    public $isTestOk = false;

    public static function globalConfig($config){//这个设计的十分不好
      foreach($config as $key=>$value)
        self::$$key = $value;
    }

    public function init(){//一些其他component的调用初始化需要放在这里
        //这里是包括component的runtime config
        $this->Curl->timeout(1);
        $this->Curl->autoReferer(true);
        __base__::$proxy = $this;

        //write($uri,$extension,$conent)
        $this->DataMgr->Hook->before('write',function(&$uri,&$extension,&$content){
            $imagesExt = array(
                'jpeg',
                'png',
                'jpg'
            );
            if(in_array($extension,$imagesExt))
                $this->waterMark->loadFromStr($content)
                                ->addText('微报',[156,156,156],'迷你简黄草',20)
                                ->run();
        });
    }

    public function __construct($session=null) {
        //component的基本workflow
        if($session != null)
            $this->setSession($session);
        else{
            $this->setSession($this->getSession());
        }
    }

    public function __call($func, $arguments){//对,还是有点优势的,因为从执行流上面就暗示了没有加载过嘛
      include 'functions/' . $func . '.php';
      $this->{$func} = new $func;
      return call_user_func_array(array($this,$func), $arguments);
    }

    public function updateFields(array $fields, bool $newTerm){
        //没有了就是这个而已
        foreach($fields as $value){
            $this->{$fields}->store();
            //你看折腾这么久,其实这个func只有那么这几行代码而已,压档的工作都放在store里面去了,这里只管更新
        }
    }

    public function login($sid, $pwd, $captcha) {
        //getData
        $_hash = function($s) {
            return strtoupper(substr(md5($s), 0, 30));
        };
        $responseText = $this->curl
                             ->post()
                             ->url(self::$baseUrl.'_data/Index_LOGIN.aspx')
                             ->data(array(
                                'Sel_Type' => 'STU',
                                'txt_asmcdefsddsd'   => $sid,
                                'txt_pewerwedsdfsdff' => urlencode($pwd),
                                'txt_sdertfgsadscxcadsads' => $captcha,
                                'fgfggfdgtyuuyyuuckjg' => $_hash($_hash(strtoupper($captcha)) . self::$schoolCode),
                                'dsdsdsdsdxcxdfgfg' => $_hash($sid . $_hash($pwd) . self::$schoolCode),
                              ))
                        ->getResponse()->body;

        //parse
        if (!strpos($responseText, '正在加载权限数据')) {
            preg_match(
                '/color:Red;">(.*?)</', $responseText, $matches);
            if (isset($matches[1])) {
                return $matches[1];//这里输出验证码错误还有密码错误信息
            } else {
                return '系统错误，无法登录';
            }
        } else {
            return true;
        }
    }

    public function autoLogin(){
        for($i = 0, $check = false; $check !== true && $i < 4; $i++){
            $captcha = $this->getCaptchaText();
            $check = $this->login(self::$sid, self::$pwd, $captcha);
            if($check === true)
                return true;
            else{
                echo $check.'<br>';
            }
        }
        return $check;
    }

    public function getSession() {
        if (!isset($this->Curl->cookies['ASP.NET_SessionId']))
            return $this->generateSessionId();
        return $this->Curl->cookies['ASP.NET_SessionId'];
    } 

    public function getCaptcha() {
        return $this->Curl->get()
                          ->url(self::$baseUrl.'sys/ValidateCode.aspx')
                          ->getResponse()
                          ->body;
    }

    public function getCaptchaText(){
        $check = $this->DataMgr->Pub->direct->write('captcha','jpg',$this->getCaptcha());
        $captchaUrl = 'HTTP://'.$_SERVER['HTTP_HOST'].'/data/captcha.jpg';
        $this->Curl->get()->url(self::$orcApi.$captchaUrl)
                    ->getResponse()->body;
    }

    public function setSession($session) {
        $this->Curl->cookies(array('ASP.NET_SessionId' => $session));
    }

    protected function generateSessionId() {
        $str = '012345abcdefghijklmnopqrstuvwxyz';
        $result = '';
        for ($i=0; $i < 24; $i++) {
            $result .= $str[rand(0, strlen($str)-1)];
        }
        return $result;
    }
    
    public function weekNumber($date=null) {
        if ($date)
            $now = strtotime($date);
        else
            $now = time();
        $start = strtotime(self::$startDate);
        $week_offset  = (int)date('N',$start) - 1;
        $start_ajusted = $start - $week_offset * 86400;
        $day = ($now - $start_ajusted)/86400;
        $week = floor($day/7);
        return $week;
    }

    public function getXNXQ() {
        return $this->getXN() . $this->getXQ();
    }
    public function getXN() {
        $month = (int) date('m');
        $year = (int) date('Y');
        if ($month <= 7)
            $year -= 1;
        return $year;
    }
    public function getXQ() {
        $month = (int) date('m');
        return ($month < 2 || $month > 7 ? '0' : '1');
    }
    
}