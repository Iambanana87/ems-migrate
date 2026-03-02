<?php

date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!defined('JWT_SECRET')) {
  define('JWT_SECRET', 'change_this_super_secret_key_32+chars'); 
}

if (!defined('IAM_API')) {
  define('IAM_API', 'http://192.168.110.2/web_develop/iam'); 
}

// Database connection configuration (backend)
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'production');
     define('DB_USER', 'device');
     define('DB_PASS', 'BtyLX96qZ4nDL!0w');
    //develop
    // define('DB_HOST', 'localhost');
    //  define('DB_NAME', 'develop');
    //  define('DB_USER', 'it01');
    //  define('DB_PASS', 'leUHgG9HBWEwSW!G');   

// discord webhook URL for notifications
define('DISCORD_WEBHOOK_URLS', [
    'mold'    => 'https://discord.com/api/webhooks/1394488629270806661/ANzTjH8WrD2OEwPWXqe5KJO09WY_q9XgUGUUTwAgIZ-GjEWs1VIRyHtUSgbehVXidDJl',
    'tuft'    => 'https://discord.com/api/webhooks/1394488987581677599/n9lk3akKXobwXa3kQN_7P_kYdKTo8fZ1VTvoIvCRuOfDKcoXTdSouvHak5oexPDCoKHr',
    'blister' => 'https://discord.com/api/webhooks/1394489193543110707/bLtvNuh2unDn9e0XpjuSnzTDWH2zi2X_NAI16wA3zms_C6mk926I_dV-nDqiQsBQma_9',
    'default' => 'https://discord.com/api/webhooks/1371655447752343602/F_iuvYiMebxkgYkZrUamxiaMhppuSSTLLrnI3Ntu_kySmF9mDbdHgUhE20yyaeoJtq0k'
]);
?>