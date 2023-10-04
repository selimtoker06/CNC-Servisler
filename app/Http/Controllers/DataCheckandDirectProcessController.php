<?php

namespace App\Http\Controllers;

use App\Console\Enums\DataTur;
use Illuminate\Http\Request;

class DataCheckandDirectProcessController extends Controller

{
    private function validatePart($request){
        $state = 0000; $message = '';
        if (!isset($request->datatur)){
            $message='DataTur Alani Zorunludur';
            $state = 0001;
        }
        return ['state' => $state, 'message' => $message];
    }

    public function index(Request $request){
        /**DATA ICERIGI
         * .datatur
         * .datadurum
         * .carikartkodu
         * .tarih
         * .saat
         * .dovizturu
         * .dovizkuru
         * .aciklama1(siparisno)
         * .aciklama2(pazaryeri)
         * .aciklama3
         * .belgeno(siparisno)
         * .fisno
         * .kalemler[
         * ..stokkartkodu
         * ..miktar
         * ..fiyat
         * ..birimkodu
         * ..dovizturu
         * ..dovizkuru
         * ..kdvorani
         * ..kdvdurumu--D/H]
        */
        $validate = $this->validatePart($request);
        if($validate['state'] != 0000)
            return response()->json(['status'  => 'error', 'title'   => 'Hata', 'message' => $validate['message'], 'response' => false]);

        $datas = json_decode(json_encode($request->post()));

        if ($datas->datatur==DataTur::Siparis){
            $adddiastate = (new DiaAddSiparisController)->index($request);
        }elseif($datas->datatur==DataTur::Iade){
            $adddiastate = (new DiaAddIadeFaturasiController)->index($request);
        }else{
            return response()->json(['status'  => 'error', 'title'   => 'Hata', 'message' => 'DataTur Hatalidir. Lutfen Kontrol Ediniz', 'response' => false]);
        }


        return response()->json(['status'  => $adddiastate['status'], 'title'   => $adddiastate['title'], 'message' => $adddiastate['message'], 'response' => $adddiastate['response']]);
    }
}
