<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class DiaGetSessionIdController extends Controller
{
    public function index(){
        $wsdatas    = DB::table('dia_wsuser')->where('id', '=', 1 )->first();
        $url        = $wsdatas->wsadres.'/sis/json' ;
        $usercode   = $wsdatas->usercode ;
        $password   = $wsdatas->password ;
        $apikey     = $wsdatas->apikey;

        $data = [
            "login" => [
                "username"              => $usercode,
                "password"              => $password,
                "disconnect_same_user"  => true,
                "lang"                  => "tr",
                "params"                => [
                    "apikey" => $apikey,
                ]
            ]
        ];

        $data = json_encode($data);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data))
        );
        curl_setopt($curl, CURLOPT_URL, $url);
        $result = curl_exec($curl);

        $json = json_decode($result, true);
        curl_close($curl);

        DB::table('dia_wsuser')
            ->where('id', 1)
            ->update(['sessionid' => $json['msg'],'updated_at'=>date('Y-m-d H:i:s')]);

        return $json['code'];
    }
}
