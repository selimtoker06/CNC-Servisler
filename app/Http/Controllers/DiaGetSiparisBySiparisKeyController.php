<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiaGetSiparisBySiparisKeyController extends Controller
{
    public function index($datas){
        $wsdatas    = DB::table('dia_wsuser')->where('id', '=', 1 )->first();
        $url        = $wsdatas->wsadres.'/scf/json' ;
        $firmakodu  = $wsdatas->firmakodu ;
        $donemkodu  = $wsdatas->donemkodu ;
        $sessionid  = $wsdatas->sessionid ;

        $data =[
            "scf_siparis_getir" => [
                "session_id"    => $sessionid,
                "firma_kodu"    => $firmakodu,
                "donem_kodu"    => $donemkodu,
                "key"           => (string)$datas->sipariskey,
                "params"        => "",
            ],
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
                return response()->json(['status'  => 'error', 'title'   => '', 'message' => 'Session Kimlik Hatasi', 'response' => false]);

        }elseif ($json['code'] = 200){
            $sipariskalemleri = $json['result']['m_kalemler'];
            $datas->sipariskalemleri = $sipariskalemleri;
            return ['status'  => 'success', 'title'   => 'Basarili', 'message' => 'Siparis Bilgileri Alindi', 'response' => $datas];
        }else{
            return ['status'  => 'error', 'title'   => 'Hata', 'message' => $json['msg'], 'response' => false];
        }
    }
}
