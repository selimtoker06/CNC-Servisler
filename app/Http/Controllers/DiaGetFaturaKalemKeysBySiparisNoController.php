<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class DiaGetFaturaKalemKeysBySiparisNoController extends Controller
{
    public function index($datas){
        $wsdatas    = DB::table('dia_wsuser')->where('id', '=', 1 )->first();
        $url        = $wsdatas->wsadres.'/rpr/json' ;
        $firmakodu  = $wsdatas->firmakodu ;
        $donemkodu  = $wsdatas->donemkodu ;
        $sessionid  = $wsdatas->sessionid ;

        $data = [
            "rpr_raporsonuc_getir" => [
                "session_id"    => $sessionid,
                "firma_kodu"    => $firmakodu,
                "donem_kodu"    => $donemkodu,
                "report_code"   => "RPR00000003",
                "tasarim_key"   => "90000",
                "param"         => ["siparisno" => $datas->aciklama1],
                "format_type"   => "json",
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
                return ['status'  => 'error', 'title'   => '', 'message' => 'Session Kimlik Hatasi', 'response' => false];

        }elseif ($json['code'] = 200){
            $result = json_decode(base64_decode($json['result']),true);
            $faturakey = $result['__rows'][0]['faturakey'];
            $faturakalemleri = $result['__rows'][0]['__detailrows']['kalembilgileri'];
            return ['status'  => 'success', 'title'   => 'Basarili', 'message' => 'Fatura Bilgileri Alindi', 'response' => ['faturakey'=>$faturakey,'faturakalemleri'=>$faturakalemleri]];
        } else{
            return ['status'  => 'error', 'title'   => 'Hata', 'message' => $json['msg'], 'response' => null];
        }
    }
}
