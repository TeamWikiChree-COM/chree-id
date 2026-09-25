<?php

return [
    /*
     * 問い合わせ先のサービス。client_id・endpoint・token の3つが揃ったものだけ使う。
     *
     * token はサービス側と同じ値を両方の .env に置く共有の鍵。
     * client_secret を流用しないのは、ChreeID 側にはハッシュしか残っていないため。
     */
    'sources' => [
        'dokufarm' => [
            'label' => 'DokuFarm',
            'client_id' => env('WIKI_HUB_DOKUFARM_CLIENT_ID'),
            'endpoint' => env('WIKI_HUB_DOKUFARM_ENDPOINT'),
            'token' => env('WIKI_HUB_DOKUFARM_TOKEN'),
        ],
        'wikichree' => [
            'label' => 'WikiChree.COM',
            'client_id' => env('WIKI_HUB_WIKICHREE_CLIENT_ID'),
            'endpoint' => env('WIKI_HUB_WIKICHREE_ENDPOINT'),
            'token' => env('WIKI_HUB_WIKICHREE_TOKEN'),
        ],
    ],

    /* 1サービスあたりの待ち秒数。1つが遅くても一覧全体を待たせない */
    'timeout' => (int) env('WIKI_HUB_TIMEOUT', 5),

    /* 取得結果を持っておく秒数。アクセス数は厳密でなくてよい */
    'cache_seconds' => (int) env('WIKI_HUB_CACHE_SECONDS', 300),
];
