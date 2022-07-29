<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider as Provider;
use Illuminate\Support\Facades\Redis;

class AppUserProvider implements Provider
{

    /**
     * Retrieve a user by their unique identifier.
     * 通过唯一标示符获取认证模型
     * @param  mixed $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        return User::find($identifier);
        //return app(User::class)::getUserByGuId($identifier);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     * 通过唯一标示符和 remember token 获取模型
     * @param  mixed  $identifier
     * @param  string $token
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        return null;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     * 通过给定的认证模型更新 remember token
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @param  string                                     $token
     * @return bool
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        return true;
    }

    /**
     * Retrieve a user by the given credentials.
     * 通过给定的凭证获取用户，比如 email 或用户名等等
     * @param  array $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        if ( !isset($credentials['api_token'])) {
            return null;
        }
        
        if (preg_match('/Bearer\s*(\S+)\b/i', $credentials['api_token'], $matches)) {
            $myapitoken = $matches[1];
        }
        if (!empty($myapitoken)) {
            $tokenarrays = explode('.', $myapitoken);
            $seriUserId = Redis::get($tokenarrays[0]);
            if (!empty($seriUserId)) {
                $unseriUserId = unserialize($seriUserId);
                $userId = $unseriUserId['uid'];
                //$userId = Redis::get($tokenarrays[0]);
                //dd($userId);
                return $this->retrieveById($userId);
            }else{
                return null;
            }
            
        }else{
            return null;
        }
        
        //return app(User::class)::getUserByToken($credentials['api_token']);
    }

    /**
     * Rules a user against the given credentials.
     * 认证给定的用户和给定的凭证是否符合
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @param  array                                      $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if ( !isset($credentials['api_token'])) {
            return false;
        }

        return true;
    }
}

