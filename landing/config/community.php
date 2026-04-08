<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gövde başına izin verilen maksimum <a> sayısı (spam önleme)
    |--------------------------------------------------------------------------
    */
    'max_links_per_body' => (int) env('COMMUNITY_MAX_LINKS', 20),

    /*
    |--------------------------------------------------------------------------
    | Engelli bağlantı ana bilgisayarları (virgülle, küçük harf)
    |--------------------------------------------------------------------------
    */
    'blocked_link_hosts' => array_values(array_filter(array_map(
        static fn (string $h): string => strtolower(trim($h)),
        explode(',', (string) env('COMMUNITY_BLOCKED_HOSTS', 'bit.ly,t.co,tinyurl.com'))
    ))),

];
