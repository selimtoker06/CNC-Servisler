<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DiaModuleControllers\DiaGetSessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiaGetStokBirimListesiController extends Controller
{
    public function index(){
        $wsdatas    = DB::table('dia_wsuser')->where('id', '=', 1 )->first();
        $url        = $wsdatas->wsadres.'/scf/json' ;
        $firmakodu  = $wsdatas->firmakodu ;
        $donemkodu  = $wsdatas->donemkodu ;
        $sessionid  = $wsdatas->sessionid ;

        $data =[
            "scf_stokkart_birimleri_listele" => [
                "session_id"=> $sessionid,
                "firma_kodu"=> $firmakodu,
                "donem_kodu"=> $donemkodu,
                "filters"=>[],
                "sorts"=> [],
                "params"=> [],
            ]];
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
        }elseif ($json['code']=200){
            $stokbirimlistesi = $json['result'];
            if (count($stokbirimlistesi)>0){
                foreach ($stokbirimlistesi as $birim) {
                    $dbdata[] = [
                        'stokkartkodu'      => $birim['stokkartkodu'],
                        'birimkod'          => $birim['birimkod'],
                        'diakey'            => $birim['_key'],
                        'stokkey'           => $birim['_key_scf_stokkart'],
                    ];
                }
                DB::table('dia_stokbirimlistesi')->truncate();
                if(!empty($dbdata)){
                    $chunk_data = array_chunk($dbdata, 1000);
                    foreach ($chunk_data as $chunk){
                        DB::table('dia_stokbirimlistesi')->insert($chunk);
                    }
                }
                return response()->json(['status'  => 'success', 'title'   => 'Basarili', 'message' => 'Stok Birim Listesi Guncellendi', 'response' => true]);
            }else{
                return response()->json(['status'  => 'info', 'title'   => 'Dikkat', 'message' => 'Stok Birim Listesi Diadan Bos Olarak Gelmektedir.', 'response' => true]);
            }
        }else{
            return response()->json(['status'  => 'error', 'title'   => 'Hata', 'message' => $json['msg'], 'response' => false]);
        }
    }
}
