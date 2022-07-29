<?php
return [
    'id'                    =>      'LpNCrvoUINNCs5KVrEw7CAh85SSweWJv',
    'key'                   =>      'u^6sG06o8^$oLFOhDdREawS$#PKdEZMF1zS0V&I!h9u23IIryD8Jzl@K4H*62uB7',
    'url'                   =>      '148.66.58.170/',
    'base_uri'              =>      'Rapidzpay/index.php/Back/',
    'add_seller'            =>      'ShopAdmin/addSeller',
    'edit_seller'           =>      'ShopAdmin/changeSeller',
    'add_pos'               =>      'ShopAdmin/addPos',
    'all_seller'            =>      'ShopAdmin/getAllSeller',
    'all_order'             =>      'SellerOrder/getAllSellerOrder',
    'all_deposit'           =>      'SellerOrder/getAllSellerDeposit',
    'all_withdraw'          =>      'SellerOrder/getAllSellerWithdraw',
    'all_seller_balance'    =>      'SellerWallets/getAllSellerBalance',
    'seller_balance'        =>      'SellerWallets/getSellerBalance',
    'node_balance'          =>      'SellerWallets/getNodeBalance',
    'slave_balance'         =>      'SellerWallets/getSlaveBalance',
    'withdraw'              =>      'SellerOrder/SellerSendMoney',
    'exchange_rate'         =>      'SellerWallets/getAmountByMoney',
    'amount_exchange_money' =>      'SellerWallets/getMoneyByAmount',
    'modify_balance'        =>      'SellerOrder/adjustSellerBalance',
    'modify_pos'            =>      'ShopAdmin/changePosIDName',
    'pay_uri'               =>      'Rapidzpay/index.php/Api/',
    'pay'                   =>      'Base/pos_pay',
    'check_pay_result'      =>      'Base/check_pay_result',
    'get_seller_balance'    =>      'SellerData/getSellerBalanceByPosID',

    'IOS_PASSWORD'              =>      '.12345678',
    'IOS_PATH'            =>      '/',
    'IOS_SSL'             =>      'ssl://gateway.push.apple.com:2195',


    'BACKDOOR_KEY' => 'pQLaVq8gqGIyHy81rFx3gPcWAG1rcgcQ',
//     'PUSH_NOTIFICATIONS_SECRET_KEY' => 'B258FAD1C7E87DFAF20394B716EBA09E34328C7D6E40CE06BC9E644737618ABF', // 谷歌推送
    'PUSH_NOTIFICATIONS_SECRET_KEY' => '3519D835D4015509DCDE04166B83AFDC35754F48B2178F064B1B28CBD07AAEF3', // 谷歌推送
    'PUSH_NOTIFICATIONS_INSTANCE_ID' => 'e1fafd61-edf4-4836-8651-e2b1ab9caad8', // 谷歌推送实例id

];