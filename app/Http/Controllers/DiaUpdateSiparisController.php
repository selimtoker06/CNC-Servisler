<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AuthControllers\GetDiaSessionIdController;
use Illuminate\Support\Facades\DB;

class DiaUpdateSiparisController extends Controller
{
    public function index($datas){
        $responseGetSiparis = (new DiaGetSiparisBySiparisKeyController)->index($datas);
        if ($responseGetSiparis['status']!='success')
            return ['status' => $responseGetSiparis['status'], 'title' => $responseGetSiparis['title'], 'message' => $responseGetSiparis['message'], 'response' => $responseGetSiparis['response']];

        $datas = $responseGetSiparis['response'];

        $wsdatas    = DB::table('dia_wsuser')->where('id', '=', 1 )->first();
        $url        = $wsdatas->wsadres.'/scf/json' ;
        $firmakodu  = $wsdatas->firmakodu ;
        $donemkodu  = $wsdatas->donemkodu ;
        $sessionid  = $wsdatas->sessionid ;

        $sipariskart = $this->karthazirla($datas);
        $data=[
            "scf_siparis_guncelle" =>[
                "session_id"    => $sessionid,
                "firma_kodu"    => $firmakodu,
                "donem_kodu"    => $donemkodu,
                "kart"          => $sipariskart
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
                return response()->json(['status'  => 'error', 'title'   => '', 'message' => 'Session Kimlik Hatasi', 'response' => false]);
        }elseif($json['code']==200){
            return ['status' => 'success', 'title' => 'Basarili', 'message' => 'Siparis Eklendi ve Iptal Edildi', 'response' => true];
        }else{
            return ['status' => 'error', 'title' => 'Hata', 'message' => $json['msg'], 'response' => false];
        }
    }

    public function karthazirla($datas){
        $m_kalemler = [];

        foreach ($datas->sipariskalemleri as $kalem){
            $m_kalemler[]=[
                "_key"                      => $kalem['_key'],
                "_key_kalemturu"            => $kalem['_key_kalemturu']['_key'],
                "_key_scf_kalem_birimleri"  => $kalem['_key_scf_kalem_birimleri'][1],
                "onay"                      => 'RET',
                "kalemturu"                 => 'MLZM',
                "_key_sis_doviz"            => ["adi"=> 'TL'],
                "anamiktar"                 => $kalem['miktar'],
                "miktar"                    => $kalem['miktar'],
                "dovizkuru"                 => "1.0000",
                "sirano"                    => $kalem['sirano']
            ];
        }

        $kart = [
            "_key"          => (int)$datas->sipariskey,
            "onay"          => 'RET',
            "m_kalemler"    => $m_kalemler
        ];
        return $kart;
    }
}
