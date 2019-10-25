<?php
namespace App\Auth;

use Cake\Auth\BaseAuthorize;
use Cake\Network\Request;
use Cake\Log\Log;

class RbacAuthorize extends BaseAuthorize
{
    public function authorize($user, Request $request)
    {
        Log::info("RbacAuthorize: uid={$user['id']}",'info');
        Log::info($request->params, 'info');

//        return false;
        return true;
    }
}
?>