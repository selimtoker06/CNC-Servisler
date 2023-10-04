<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiaUpdateFaturaIptalBySiparisNoController extends Controller
{
    public function index($datas){
        $faturaGetResponse = (new DiaGetFaturaKeyBySiparisNoController)->index($datas);

        $wsdatas    = DB::table('dia_wsuser')->where('id', '=', 1 )->first();
        $url        = $wsdatas->wsadres.'/scf/json' ;
        $firmakodu  = $wsdatas->firmakodu ;
        $donemkodu  = $wsdatas->donemkodu ;
        $sessionid  = $wsdatas->sessionid ;

        $data =[
            "scf_fatura_iptalet" => [
                "session_id"    => $sessionid,
                "firma_kodu"    => $firmakodu,
                "donem_kodu"    => $donemkodu,
                "key"           => (string)$faturaGetResponse['response'],
                "params"        => [
                    "bagliIrsaliyeIptal"    => "True",
                    "iptalNedeni"           => "Iptal"
                ]
            ]
        ];
        $data =json_encode($data);
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
        if($json['code']==419 or $json['code']==401){
            $sessioncode = (new DiaGetSessionIdController)->index();
            if($sessioncode==200)
                return $this->index($datas);
            else
                return ['status' => 'error', 'title' => '', 'message' => 'Session Kimlik Hatasi', 'response' => false];
        }elseif ($json['code'] == 200){
            $siparisUpdateResponse = (new DiaUpdateSiparisController)->index($datas);
            return ['status'  => $siparisUpdateResponse['status'], 'title'   => $siparisUpdateResponse['title'], 'message' => $siparisUpdateResponse['message'], 'response' => $siparisUpdateResponse['response']];
        }else{
            return ['status'  => 'info', 'title'   => 'Dikkat', 'message' => $json['msg'], 'response' => null];
        }
    }
}
