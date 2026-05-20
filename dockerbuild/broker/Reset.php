<?php

use common\helpers\MySqlDateTime;

return [
    'reset1' => [
        'id' => 1,
        'uuid' => '11111111111111111111111111111111',
        'user_id' => 1,
        'expires' => MySqlDateTime::relativeTime('+1 hour'),
        'created' => MySqlDateTime::now(),
    ],
    'reset2' => [
        'id' => 2,
        'uuid' => '22222222222222222222222222222222',
        'user_id' => 2,
        'expires' => MySqlDateTime::now(),
        'created' => MySqlDateTime::now(),
    ],
    'reset3' => [
        'id' => 3,
        'uuid' => '33333333333333333333333333333333',
        'user_id' => 3,
        'expires' => MySqlDateTime::relativeTime('+1 hour'),
        'created' => MySqlDateTime::now(),
    ],
    'reset4' => [
        'id' => 4,
        'uuid' => '44444444444444444444444444444444',
        'user_id' => 4,
        'expires' => MySqlDateTime::relativeTime('+1 hour'),
        'created' => MySqlDateTime::now(),
    ],
];
