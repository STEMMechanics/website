<?php
namespace Faker\Provider;

use Faker\Provider\Internet as BaseInternet;

class CustomInternetProvider extends BaseInternet
{
    public function userNameWithMinLength($minLength = 6)
    {
        $username = $this->userName();
        while (strlen($username) < $minLength) {
            $username .= $this->randomNumber();
        }
        return $username;
    }
}
