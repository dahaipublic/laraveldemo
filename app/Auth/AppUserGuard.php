<?php

namespace App\Auth;

use Illuminate\Http\Request;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class AppUserGuard implements Guard

{
    use GuardHelpers;

    protected $user = null;

    protected $request;

    protected $provider;

    /**
     * The name of the query string item from the request containing the API token.
     *
     * @var string
     */
    protected $inputKey;

    /**
     * The name of the token "column" in persistent storage.
     *
     * @var string
     */
    protected $storageKey;

    /**
     * The user we last attempted to retrieve
     * @var
     */
    protected $lastAttempted;

    /**
     * UserGuard constructor.
     * @param UserProvider $provider
     * @param Request      $request
     * @return void
     */
    public function __construct(UserProvider $provider, Request $request)
    {
        $this->request = $request;
        $this->provider = $provider;
        $this->inputKey = 'Authorization';
        $this->storageKey = 'api_token';
    }

    /**
     * Get the currently authenticated user.
     * 获取当前认证的用户
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user()
    {
        if(!is_null($this->user)) {
            return $this->user;
        }

        $user = null;

        $token = $this->getTokenForRequest();
        
        if(!empty($token)) {
            $user = $this->provider->retrieveByCredentials(
                [$this->storageKey => $token]
            );
        }

        return $this->user = $user;
    }

    /**
     * Rules a user's credentials.
     * 根据提供的消息认证用户
     * @param  array $credentials
     * @return bool
     */
    public function validate(array $credentials = [])
    {
        if (empty($credentials[$this->inputKey])) {
            return false;
        }

        $credentials = [$this->storageKey => $credentials[$this->inputKey]];

        $this->lastAttempted = $user = $this->provider->retrieveByCredentials($credentials);

        return $this->hasValidCredentials($user, $credentials);
    }

    /**
     * Determine if the user matches the credentials.
     * @param  mixed $user
     * @param  array $credentials
     * @return bool
     */
    protected function hasValidCredentials($user, $credentials)
    {
        return !is_null($user) && $this->provider->validateCredentials($user, $credentials);
    }


    /**
     * Get the token for the current request.
     * @return string
     */
    public function getTokenForRequest()
    {
        $token = $this->request->header($this->inputKey);

        return $token;
    }

    /**
     * Set the current request instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }
    /**
     * Set the current user.
     *
     * @param  Array $user User info
     * @return void
     */
    public function setUser(Authenticatable $user)
    {
        $this->user = $user;
        return $this;
    }
}

