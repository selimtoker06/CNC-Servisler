<?php

namespace App\Http\Controllers;

use App\Console\Enums\DataDurum;
use Dflydev\DotAccessData\Data;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiaAddSiparisFaturalandirController extends Controller
{
    public function index($datas){

        $wsdatas    = DB::table('dia_wsuser')->where('id', '=', 1 )->first();
        $url        = $wsdatas->wsadres.'/scf/json' ;
        $firmakodu  = $wsdatas->firmakodu ;
        $donemkodu  = $wsdatas->donemkodu ;
        $sessionid  = $wsdatas->sessionid ;

        $data =[
            "scf_siparis_irsaliyelendir_faturalandir" => [
                "session_id"    => $sessionid,
                "firma_kodu"    => $firmakodu,
                "donem_kodu"    => $donemkodu,
                "kart"=> [
                    "aktarilanSiparis"              => $datas->sipariskey,
                    "islem"                         => "FATURALANDIRMA2",
                    "faturano"                      => "00000000001",
                    "tarih"                         => $datas->tarih,
                    "saat"                          => $datas->saat,
                    "aciklama1"                     => $datas->aciklama1,
                    "aciklama2"                     => $datas->aciklama2,
                    "aciklama3"                     => $datas->aciklama3,
                    "_earsivgonderimsekli"          => "H",
                    "_earsivsenaryosu"              => "",
                    "_efaturatipkodu"               => "",
                    "_efaturavergimuafiyetkodu"     => "",
                    "_efaturavergimuafiyetsebebi"   =>"",
                    //"kargogonderimtarihi"           => "2017-08-26"
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
            if ($datas->datadurum==DataDurum::Iptal){
                $datas->faturano = $json['extra']['islembelgeno'];
                $faturaiptalstate = (new DiaUpdateFaturaIptalBySiparisNoController)->index($datas);
                return ['status' => $faturaiptalstate['status'], 'title' => $faturaiptalstate['title'], 'message' => $faturaiptalstate['message'],
                    'response' => $faturaiptalstate['response']];
            }
            return ['status' => 'success', 'title' => 'Basarili', 'message' => 'Siparis Faturalandirildi.', 'response' => true];
        }else{
            return ['status'  => 'info', 'title'   => 'Dikkat', 'message' => $json['msg'], 'response' => null];
        }
    }
}
