<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DiaAddIadeFaturasiController extends Controller
{
    /**DATA ICERIGI
     * .datatur
     * .datadurum
     * .carikartkodu
     * .tarih
     * .saat
     * .adreskey
     * .odemeplanikey
     * .dovizturu
     * .kur
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
     * ..kur
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
        }elseif (!isset($request->kalemler)){
            $message='Kalemler Zorunludur';
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

        $faturaGetResponse = (new DiaGetFaturaKalemKeysBySiparisNoController)->index($datas);

        $kart = $this->karthazirla($datas,$faturaGetResponse['response']['faturakalemleri']);

        if ($kart['status']!='success')
            return response()->json(['status'  => 'error', 'title'   => 'Hata', 'message' => $kart['message'], 'response' => false]);

        $data =[
            "scf_fatura_ekle" => [
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

        }elseif ($json['code'] = 200){
            return ['status' => 'success', 'title' => 'Basarili', 'message' => 'Iade Faturasi Eklendi', 'response' => $json];
        }else{
            return ['status'  => 'error', 'title'   => 'Hata', 'message' => $json['msg'], 'response' => $json];
        }
    }

    public function karthazirla($datas,$faturakalemleri){
        foreach ($datas->kalemler as $kalem){
            $kalem = json_decode(json_encode($kalem));

            $stokkey = DB::table('dia_stoklistesi')->where('stokkartkodu',$kalem->stokkartkodu)->pluck('_key')->first();
            if ($stokkey) $stokbirimkey = DB::table('dia_stokbirimlistesi')->where('_key_scf_stokkart',$stokkey)
                ->where('birimkodu',$kalem->birimkodu)->pluck('_key')->first();
            else
                return ['status' => 'error', 'message' => 'Sistemde Kayitli Olmayan Stok ile Islem Yapilamaz. lutfen Kontrol Ediniz.', 'data'=>false];

            foreach ($faturakalemleri as $faturakalemi){
                if ($faturakalemi['stokkartkodu']==$kalem->stokkartkodu)
                    $kalem->_key_scf_fatura_kalemi_iade=$faturakalemi['faturakalemkey'];
            }
            $kalemler[] = [
                "_key_kalemturu"                => ["stokkartkodu" => $kalem->stokkartkodu],
                "_key_scf_fatura_kalemi_iade"   => $kalem->_key_scf_fatura_kalemi_iade ?? 0,
                "_key_scf_kalem_birimleri"      => $stokbirimkey,
                "_key_sis_depo_source"          => 176601,
                "_key_sis_doviz"                => ["adi" => $kalem->dovizturu],
                "anamiktar"                     => $kalem->miktar,
                "miktar"                        => $kalem->miktar,
                "birimfiyati"                   => $kalem->fiyat,
                "dovizkuru"                     => $kalem->dovizkuru,
                "kalemturu"                     => "MLZM",
                "kdv"                           => $kalem->kdvorani,
                "kdvdurumu"                     => $kalem->kdvdurumu,
                //"_key_bagli_fatura"   => 0,
                //"_key_dmr_demirbas"     => 0,
                //"_key_muh_masrafmerkezi"=> ["kodu"=> "1.01.0002"],
                //"_key_prj_proje"=> ["kodu"=> "000001"],
                //"_key_scf_carikart_mustahsil"=> 0,
                //"_key_scf_fatura_kalemi_bagli"=> 0,
                //"_key_scf_fiyatkart"=> 0,
                //"_key_scf_iadenedeni"=> 0,
                //"_key_scf_karsi_fatura_kalemi"=> 0,
                //"_key_scf_odeme_plani"=> ["kodu"=> "000001"],
                //"_key_scf_promosyon"=> 0,
                //"_key_scf_satiselemani"=> 0,
                //"_key_scf_siparis_kalemi"=> 0,
                //"_key_sis_depo_dest"=> 0,
                //"_key_sis_ozelkod"=> 0,
                //"indirim1"=> "0.000000",
                //"indirim2"=> "0.000000",
                //"indirim3"=> "0.000000",
                //"indirim4"=> "0.000000",
                //"indirim5"=> "0.000000",
                //"indirimtoplam"=> "0.000000",
                //"indirimtutari"=> "0.000000",
                //"kdvtevkifatorani"=> "0",
                //"kdvtevkifattutari"=> "0.000000",
                //"kdvtutari"=> "335.160000",
                //"maliyet_fatura_key"=> 0,
                //"maliyet_key"=> 0,
                //"note"=> "",
                //"note2"=> "",
                //"ovkdvoran"=> "E",
                //"ovkdvtutar"=> "E",
                //"ovmanuel"=> "H",
                //"ovorantutari"=> "0.000000",
                //"ovtoplamtutari"=> "0.000000",
                //"ovtutartutari"=> "0.000000",
                //"ovtutartutari2"=> "0.000000",
                //"ozelalan1"=> "0.000000",
                //"ozelalan2"=> "0.000000",
                //"ozelalan3"=> "0.000000",
                //"ozelalan4"=> "0.000000",
                //"ozelalan5"=> "0.000000",
                //"ozelalanf"=> "",
                //"promosyonkalemid"=> "",
                //"sirano"=> 10,
                //"yerelbirimfiyati"=> "266.000000",
                //"m_varyantlar"=> []
            ];
        }
        $fatura = [
            "_key_scf_carikart"                 => ["carikartkodu" => $datas->carikartkodu],
            "_key_sis_depo_source"              => 80884,
            "_key_sis_doviz"                    => ["adi" => $datas->dovizturu],
            "_key_sis_doviz_raporlama"          => ["adi" => $datas->dovizturu],
            "_key_sis_sube_source"              => 80883,
            "aciklama1"                         => $datas->aciklama1,
            "aciklama2"                         => $datas->aciklama2,
            "aciklama3"                         => $datas->aciklama3,
            "belgeno"                           => $datas->belgeno,
            "belgeno2"                          => "0000000000001",
            "dovizkuru"                         => $datas->dovizkuru,
            "earsivgonderimeposta"              => "",
            "earsivgonderimsekli"               => "K",
            "earsivodemetarihi"                 => null,
            "efatalias"                         => "",
            "efaturasenaryosu"                  => "",
            "efaturatipkodu"                    => "",
            "efaturavergimuafiyetkodu"          => "",
            "efaturavergimuafiyetsebebi"        => "",
            "fisno"                             => "0000000000001",
            "raporlamadovizkuru"                => $datas->dovizkuru,
            "saat"                              => $datas->saat,
            "tarih"                             => $datas->tarih,
            "turu"                              => 7,
            "m_kalemler"                        => $kalemler
            //"_key_ith_kart_ihr"             => 0,
            //"_key_ith_kart_ith"             => 0,
            //"_key_karsi_fatura"             => 0,
            //"_key_krg_firma"                => 0,
            //"_key_krg_gonderifisi"          => 0,
            //"_key_muh_masrafmerkezi"        => 0,
            //"_key_prj_proje"                => ["kodu"=> "000001"],
            //"_key_satiselemanlari"          => [],
            //"_key_scf_carihesap_fisi_kdviade"   => 0,
            //"_key_scf_carikart_adresleri"       => 178724,
            //"_key_scf_kasa"                     => 0,
            //"_key_scf_malzeme_baglantisi"       => 0,
            //"_key_scf_odeme_plani"              => ["kodu"=> "000001"],
            //"_key_scf_satiselemani"             => 0,
            //"_key_sis_depo_dest"                => 176601,//80884,
            //"_key_sis_firma_dest"               => 0,
            //"_key_sis_ozelkod1"                 => 0,
            //"_key_sis_ozelkod2"                 => 0,
            //"_key_sis_ozelkod_kasafisi"         => 0,
            //"_key_sis_seviyekodu"               => 0,
            //"_key_sis_seviyekodu_kasafisi"      => 0,
            //"_key_sis_sube_dest"                => 0,
            //"babsdegeri"                        => "0",
            //"bagkur"                            => "0.000000",
            //"bagkurdvz"                         => "0.000000",
            //"bagkuryuzde"                       => "0.000000",
            //"borsa"                             => "0.000000",
            //"borsadvz"                          => "0.000000",
            //"borsayuzde"                        => "0.000000",
            //"ek1"                               => "0.000000",
            //"ek1dvz"                            => "0.000000",
            //"ek1yuzde"                          => "0.000000",
            //"ek2"                               => "0.000000",
            //"ek2dvz"                            => "0.000000",
            //"ek2yuzde"                          => "0.000000",
            //"ek3"                               => "0.000000",
            //"ek3dvz"                            => "0.000000",
            //"ek3yuzde"                          => "0.000000",
            //"ek4"                               => "0.000000",
            //"ek4dvz"                            => "0.000000",
            //"ek4yuzde"                          => "0.000000",
            //"ekalan1"                           => "",
            //"ekalan2"                           => "",
            //"ekalan3"                           => "",
            //"ekalan4"                           => "",
            //"ekalan5"                           => "",
            //"ekalan6"                           => "",
            //"ekmaliyet"                         => "0.000000",
            //"formbabsgoster"                    => "t",
            //"gecikmetutari"                     => "0.000000",
            //"iptal"                             => "-",
            //"iptalnedeni"                       => "",
            //"istemcitipi"                       => "G",
            //"ithtipi"                           => "0",
            //"kargogonderimtarihi"               => null,
            //"karsifirma"                        => "C",
            //"kasafisno"                         => "000023",
            //"kategori"                          => "F",
            //"kdvduzenorani"                     => "+",
            //"kdvduzentutari"                    => "0.000000",
            //"kdvtebligi85"                      => "f",
            //"kilitli"                           => "f",
            //"komisyon"                          => "0.000000",
            //"komisyondvz"                       => "0.000000",
            //"komisyonkdv"                       => "0.000000",
            //"komisyonkdvdvz"                    => "0.000000",
            //"komisyonkdvyuzde"                  => "0.000000",
            //"komisyonyuzde"                     => "0.000000",
            //"konsinyeurunfaturasi"              => "f",
            //"mustahsil_tamam"                   => "f",
            //"navlun"                            => "0.000000",
            //"navlundvz"                         => "0.000000",
            //"navlunkdv"                         => "0.000000",
            //"navlunkdvdvz"                      => "0.000000",
            //"navlunkdvyuzde"                    => "0.000000",
            //"odemeislemli"                      => "t",
            //"sevkadresi1"                       => "YILDIRIMÖNÜ MAH. ÇAMDALI SOK. NO:118",
            //"sevkadresi2"                       => "",
            //"sevkadresi3"                       => "Keçiören ANKARA",
            //"ssdf"                              => "0.000000",
            //"ssdfdvz"                           => "0.000000",
            //"ssdfyuzde"                         => "0.000000",
            //"stopaj"                            => "0.000000",
            //"stopajdvz"                         => "0.000000",
            //"stopajodemeturu"                   => "011",
            //"stopajyuzde"                       => "0.000000",
        ];
        return ['status' => 'success', 'message' => true, 'data' => $fatura];
    }
}
