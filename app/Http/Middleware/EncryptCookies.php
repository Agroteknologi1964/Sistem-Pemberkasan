<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * Cookie yang dikecualikan dari enkripsi Laravel.
     *
     * jwt_token harus dikecualikan agar JWTAuth bisa membaca
     * raw token string langsung dari cookie tanpa dekripsi Laravel.
     */
    protected $except = [
        'jwt_token',
    ];
}
