<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DiaGetStokListesiController extends Controller
{
    public function index(){
        $wsdatas    = DB::table('dia_wsuser')->where('id', '=', 1 )->first();
        $url        = $wsdatas->wsadres.'/scf/json' ;
        $firmakodu  = $wsdatas->firmakodu ;
        $donemkodu  = $wsdatas->donemkodu ;
        $sessionid  = $wsdatas->sessionid ;

        $data =[
            "scf_stokkart_listele" => [
                "session_id"    => $sessionid,
                "firma_kodu"    => $firmakodu,
                "donem_kodu"    => $donemkodu,
                "filters"       => [["field" => "durum", "operator" => "=","value"=>"A"]],
                "sorts"         => ["field" => "stokkartkodu", "sorttype" => "ASC"],
                "params"        => ["_key_sis_depo"=> 0],
                "tarih"         => "2099-12-31"
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
                return $this->index();
            else
                return response()->json(['status'  => 'error', 'title'   => '', 'message' => 'Session Kimlik Hatasi', 'response' => false]);

        }elseif ($json['code'] = 200){
            $data = $json['result'];

            if (count($data)>0){
                foreach ($data as $stok) {
                    $stoklistesi[] = [
                        'diakey'                => $stok['_key'],
                        'stokkartkodu'          => $stok['stokkartkodu'] ?? '',
                        'aciklama'              => $stok['aciklama'] ?? '',
                        'fiilistokmiktari'      => (float)$stok['fiili_stok'],
                    ];
                }
                DB::table('dia_stoklistesi')->truncate();
                DB::table('dia_stoklistesi')->insert($stoklistesi);
                return response()->json(['status'  => 'success',
                    'title'   => 'Basarili',
                    'message' => 'Stok Listesi Basarili Sekilde Gonderildi.',
                    'response' => $stoklistesi]);
            }
            return response()->json(['status'  => 'info',
                                     'title'   => 'Dikkat',
                                     'message' => 'Stok Listesi Sistemden Bos Olarak Gelmektedir.',
                                     'response' => null]);
        }
        else{
            return response()->json(['status'  => 'info',
                'title'   => 'Dikkat',
                'message' => $json['msg'],
                'response' => null]);
        }
    }
}
