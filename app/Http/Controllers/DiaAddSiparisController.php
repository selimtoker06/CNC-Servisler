<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiaAddSiparisController extends Controller
{
    /**DATA ICERIGI
     * .datatur
     * .carikartkodu
     * .tarih
     * .saat
     * .adreskey
     * .odemeplanikey
     * .dovizturu
     * .dovizkuru
     * .aciklama1(not)
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
     * ..kdvdurumu]
     */
    private function validatePart($request){
        $state = 0000; $message = '';
        if (!isset($request->carikartkodu)){
            $message='Carikartkodu Alani Zorunludur';
            $state = 0001;
        }elseif (!isset($request->tarih)){
            $message='Tarih Zorunludur';
            $state = 0001;
        }elseif (!isset($request->saat)){
            $message='Saat Zorunludur';
            $state = 0001;
        }elseif (!isset($request->dovizturu)){
            $message='Dovizturu Zorunludur';
            $state = 0001;
        }elseif (!isset($request->dovizkuru)){
            $message='Kur Zorunludur';
            $state = 0001;
        }elseif (!isset($request->aciklama1)){
            $message='Aciklama1 Zorunludur';
            $state = 0001;
        }elseif (!isset($request->aciklama2)){
            $message='Aciklama2 Zorunludur';
            $state = 0001;
        }elseif (!isset($request->belgeno)){
            $message='Belgeno Zorunludur';
            $state = 0001;
        }
        return ['status' => $state, 'message' => $message];
    }

    public function index($datas){
        $validate = $this->validatePart($datas);
        if($validate['status'] != 0000) return ['status' => 'error', 'title' => 'Hata', 'message' => $validate['message'], 'response' => false];

        $wsdatas    = DB::table('dia_wsuser')->where('id', '=', 1 )->first();
        $url        = $wsdatas->wsadres.'/scf/json' ;
        $firmakodu  = $wsdatas->firmakodu ;
        $donemkodu  = $wsdatas->donemkodu ;
        $sessionid  = $wsdatas->sessionid ;

        $kart = $this->karthazirla($datas);
        if ($kart['status']!='success')
            return response()->json(['status'  => 'error', 'title'   => 'Hata', 'message' => $kart['message'], 'response' => false]);

        $data =[
            "scf_siparis_ekle" => [
                "session_id"    => $sessionid,
                "firma_kodu"    => $firmakodu,
                "donem_kodu"    => $donemkodu,
                "kart"          => $kart['data'],
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

        }elseif ($json['code'] == 200){
            $datas->sipariskey = $json['key'];
            $responseFatura = (new DiaAddSiparisFaturalandirController)->index($datas);
            return ['status' => $responseFatura['status'], 'title' => $responseFatura['title'], 'message' => $responseFatura['message'], 'response' => $responseFatura['response']];
        } else{
            return ['status'  => 'error', 'title'   => 'Hata', 'message' => $json['msg'], 'response' => null];
        }
    }

    public function karthazirla($datas){
        foreach ($datas->kalemler as $kalem){
            $kalem = json_decode(json_encode($kalem));

            $stokkey = DB::table('dia_stoklistesi')->where('stokkartkodu',$kalem->stokkartkodu)->pluck('_key')->first();
            if ($stokkey) $stokbirimkey = DB::table('dia_stokbirimlistesi')->where('_key_scf_stokkart',$stokkey)
                ->where('birimkodu',$kalem->birimkodu)->pluck('_key')->first();
            else
                return ['status' => 'error', 'message' => 'Sistemde Kayitli Olmayan Stok ile Islem Yapilamaz. lutfen Kontrol Ediniz.', 'data'=>false];

            $kalemler[] = [
                "_key_kalemturu"            => ["stokkartkodu" => $kalem->stokkartkodu],
                "_key_scf_kalem_birimleri"  => $stokbirimkey,
                "_key_sis_doviz"            => ["adi" => $kalem->dovizturu],
                "anamiktar"                 => $kalem->miktar,
                "birimfiyati"               => $kalem->fiyat,
                "dovizkuru"                 => $kalem->dovizkuru,
                "kalemturu"                 => "MLZM",
                "miktar"                    => $kalem->miktar,
                "kdv"                       => $kalem->kdvorani,
                "kdvdurumu"                 => $kalem->kdvdurumu,
                "onay"                      => "KABUL",
                "_key_sis_depo_source"      => 80884,
                //"_key_scf_odeme_plani"    => ["kodu" => "000001"],
                //"indirim1"                => "0.000000",
                //"indirim2"                => "0.000000",
                //"indirim3"                => "0.000000",
                //"indirim4"                => "0.000000",
                //"indirim5"                => "0.000000",
                //"indirimtoplam"           => "0.000000",
                //"indirimtutari"           => "0.000000",
                //"kdvtutari"               => "216.000000",
                //"note"                    => "",
                //"note2"                   => "",
                //"rezervasyon"             => "H",
                //"siptarih"                  => "",
                //"sirano"                  => (string)((int)$kalem->order_line*10),
                //"sonbirimfiyati"            => $kalem->order_unit_cost,
                //"teslimattarihi"            => $kalem->due_date,
                //"tutari"                  => "1200.000000",
                //"yerelbirimfiyati"        => "1200.000000",
            ];
        }
        $siparis = [
            "_key_scf_carikart"             => ["carikartkodu" => $datas->carikartkodu],
            "_key_sis_depo_source"          => 80884,
            "_key_sis_doviz"                => ["adi" => $datas->dovizturu],
            "_key_sis_doviz_raporlama"      => ["adi" => $datas->dovizturu],
            "_key_sis_sube_source"          => 80883,
            "dovizkuru"                     => $datas->dovizkuru,
            "fisno"                         => '0000000000001',
            "onay"                          => "KABUL",
            "raporlamadovizkuru"            => $datas->dovizkuru,
            "saat"                          => $datas->saat,
            "tarih"                         => $datas->tarih,
            "turu"                          => 2,
            'm_kalemler'                    => $kalemler,
            "belgeno"                       => $datas->belgeno,
            "aciklama1"                     => $datas->aciklama1,
            "aciklama2"                     => $datas->aciklama2,
            "aciklama3"                     => $datas->aciklama3,
            //"ekalan1"                     => "",
            //"_key_sis_ozelkod1"           => 0,
            //"_key_scf_carikart_adresleri" => 178718,
            //"_key_scf_odeme_plani"        => ["kodu" => "000001"],
            //"_key_sis_ozelkod2"           => 0,
            //"net"                         => "1416.000000",
            //"netdvz"                      => "1416.000000",
            //"odemeislemli"                => "f",
            //"odemeli"                     => "f",
            //"ortalamavade"                => "2017-04-19",
            //"sevkadresi1"                 => "YILDIRIMÖNÜ MAH. ÇAMDALI SOK. NO:118",
            //"sevkadresi2"                 => "",
            //"sevkadresi3"                 => "Keçiören ANKARA",
            //"teslimat_adres1"             => "",
            //"teslimat_adres2"             => "",
            //"teslimat_adsoyad"            => "",
            //"teslimat_ceptel"             => "",
            //"teslimat_ilce"               => "",
            //"teslimat_key_sis_sehirler"   => 0,
            //"teslimat_telefon"            => "",
            //"toplam"                      => "1200.000000",
            //"toplamdvz"                   => "1200.000000",
            //"toplamindirim"               => "0.000000",
            //"toplamindirimdvz"            => "0.000000",
            //"toplamkdv"                   => "216.000000",
            //"toplamkdvdvz"                => "216.000000",
            //"toplammasraf"                => "0.000000",
            //"toplammasrafdvz"             => "0.000000",
        ];
        return ['status' => 'success', 'message' => true, 'data'=>$siparis];
    }
}
