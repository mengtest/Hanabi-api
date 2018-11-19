<?php
$v = 'v1';
return [
    'OPTIONS '.$v.'/<controller:[^\/]+>/<action:[^\/]+>' => $v.'/my-active/option',
    'OPTIONS '.$v.'/<controller:[^\/]+>' => $v.'/my-active/option',
    [
        'class' => 'yii\rest\UrlRule',
        'controller' => [$v.'/user',$v.'/room',$v.'/game'],
        'pluralize' => false
    ],
    'POST '.$v.'/room/list' => $v.'/room/list',
    'POST '.$v.'/room/list-refresh' => $v.'/room/refresh-sys-lastupdated',
    'POST '.$v.'/my-room/enter' => $v.'/my-room/enter',  //进入房间 （是否有位置， 如有密码，密码验证）
    'POST '.$v.'/my-room/exit' => $v.'/my-room/exit',  //退出房间
    //'POST '.$v.'/my-room/is-in-room' => $v.'/my-room/is-in-room',  //判断是否在房间中  如是返回房间i
    'POST '.$v.'/my-room/get-info' => $v.'/my-room/get-info',  //判断是否在房间中  如是返回房间i
    'POST '.$v.'/my-room/do-ready' => $v.'/my-room/do-ready',  //判断是否在房间中  如是返回房间i


    'POST '.$v.'/my-game/start' => $v.'/my-game/start',  //开始游戏
    'POST '.$v.'/my-game/get-info' => $v.'/my-game/get-info',  //开始游戏
    //'POST '.$v.'/my-game/is-in-game' => $v.'/my-game/is-in-game',  //开始游戏
    'POST '.$v.'/my-game/end' => $v.'/my-game/end',  //开始游戏
    'POST '.$v.'/my-game/do-discard' => $v.'/my-game/do-discard',  //弃牌
    'POST '.$v.'/my-game/do-play' => $v.'/my-game/do-play',  //出牌
    'POST '.$v.'/my-game/do-cue' => $v.'/my-game/do-cue',  //提示

    'POST '.$v.'/my-game/auto-play' => $v.'/my-game/auto-play',  //挂机(每回合超过30秒)自动出牌


    'POST '.$v.'/admin/login' => $v.'/user/admin-login',  //提交登录 生成token
    //'OPTIONS '.$v.'/admin/login' => $v.'/user/admin-login',  //提交登录 生成token
    'GET '.$v.'/admin/info' => $v.'/user/admin-info',  //提交登录 生成token
    'POST '.$v.'/admin/logout' => $v.'/user/admin-logout',  //提交登录 生成token

    'POST '.$v.'/register' => $v.'/user/register',  //提交注册 生成token
    'POST '.$v.'/auth' => $v.'/user/auth',  //提交登录 生成token
    'DELETE '.$v.'/auth' => $v.'/user/auth-delete', //退出 清空token
    //'POST v1/auth-delete' => 'v1/user/auth-delete',  //退出 清空token

    'OPTIONS '.$v.'/auth' => $v.'/user/auth-delete',
    'GET '.$v.'/auth' => $v.'/user/auth-user-info', //读取用户信息（自动登录）


    'GET '.$v.'/wxauth/code2session' => $v.'/auth/wxcode2session', //打开小程序根据用户code转换为session （检查本地应用中是否已经存在）
    'GET '.$v.'/wxauth' => $v.'/auth/wxauth', //使用存在微信小程序storage中的session_key  与 wx_auth表中session_key 鉴权

];