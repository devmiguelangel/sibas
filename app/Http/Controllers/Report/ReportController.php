<?php

namespace Sibas\Http\Controllers\Report;

use Sibas\Http\Controllers\Excel\ExportXlsController;
use DB;
use Illuminate\Http\Request;
use Sibas\Http\Requests;
use Sibas\Http\Controllers\Controller;

class ReportController extends Controller {
    var $object = [];
    public $value = '';
    public $valueOr = '';
    public function __construct() {
        $this->users = DB::table('ad_users')->lists('username', 'id');
        $this->agencies = DB::table('ad_agencies')->lists('name', 'id');
        $this->cities = DB::table('ad_cities')->lists('name', 'id');
        $this->extencion = DB::table('ad_cities')->lists('name', 'abbreviation');
        $this->observation = DB::table('op_de_observations')->orderBy('id','DESC')->get();
    }

    /**
     * 
     * @param \Illuminate\Http\Request $request
     * @return type
     */
    public function general(Request $request) {
        $users = $this->users;
        $agencies = $this->agencies;
        $cities = $this->cities;
        $extencion = $this->extencion;
        
        $valueForm = $this->addValueForm($request);
        
        $opClients = DB::table('op_clients')
                ->join('op_de_details', 'op_clients.id', '=', 'op_de_details.op_client_id')
                ->select('op_de_details.op_de_header_id');

        $query = DB::table('op_de_headers')
                ->join('ad_users', 'op_de_headers.ad_user_id', '=', 'ad_users.id')
                ->join('ad_coverages', 'op_de_headers.ad_coverage_id', '=', 'ad_coverages.id')
                ->select('op_de_headers.policy_number', 'op_de_headers.id', 'ad_coverages.name', 'op_de_headers.operation_number', 'op_de_headers.amount_requested', 'op_de_headers.currency', 'op_de_headers.term', 'op_de_headers.type_term', 'op_de_headers.total_rate', 'op_de_headers.total_premium', 'op_de_headers.date_issue', 'ad_users.username', 'ad_users.full_name')
                //edw-->->where('op_de_headers.issued', 1)
                ->where('op_de_headers.type', 'I');
        $details = array();
        $result = array();
        $flagClient = 0;
        
        if ($request->get('_token')) {
            # relacion facultativos
            if ($request->get('rechazado') || $request->get('no_freecover') || $request->get('extraprima') || $request->get('no_extraprima') || $request->get('pendiente') || $request->get('subsanado') || $request->get('observado')){
                $query->join('op_de_details', 'op_de_headers.id', '=', 'op_de_details.op_de_header_id');
                $query->leftJoin('op_de_facultatives', 'op_de_facultatives.op_de_detail_id', '=', 'op_de_details.id');
            }
            
            $arr = $this->role($request);
            
            foreach ($arr as $key => $value) {
                
                if ($key == 'and') {
                    $this->value = $value;
                    $query->where(function($q) {
                        foreach ($this->value as $key2 => $value2) {
                            $q->whereRaw($key2 . '="'.$value2.'"');
                        }
                        $q->whereRaw('`op_de_headers`.`type`="I"');
                    });
                }elseif ($key == 'or'){
                    foreach ($value as $key => $value) {
                        $this->valueOr = $value;
                        $query->orWhere(function($q) {
                            foreach ($this->valueOr as $key2 => $value2) {
                                $q->whereRaw($key2 . '="'.$value2.'"');
                            }
                            $q->whereRaw('`op_de_headers`.`type`="I"');
                        });
                    }
                }
                
            }
            
            # numero poliza
            if ($request->get('numero_poliza'))
                $query->where('op_de_headers.policy_number', $request->get('numero_poliza'));

            # usuario vendedor
            if ($request->get('usuario'))
                $query->where('op_de_headers.ad_user_id', $request->get('usuario'));

            # usuario vendedor agencia
            if ($request->get('agencia'))
                $query->where('ad_users.ad_agency_id', $request->get('agencia'));

            # usuario vendedor sucursal
            if ($request->get('sucursal'))
                $query->where('ad_users.ad_city_id', $request->get('sucursal'));

            # fecha de emision inicial
            if ($request->get('fecha_ini'))
                $query->where('op_de_headers.date_issue', '>=', date('Y-m-d', strtotime(str_replace('/', '-', $request->get('fecha_ini')))));

            # fecha de emision final
            if ($request->get('fecha_fin'))
                $query->where('op_de_headers.date_issue', '<=', date('Y-m-d', strtotime('+1 days', strtotime(str_replace('/', '-', $request->get('fecha_fin'))))));

            # extencion cliente
            if ($request->get('extension'))
                $opClients->where('op_clients.extension', $request->get('extension'));
            # ci cliente
            if ($request->get('ci'))
                $opClients->where('op_clients.dni', $request->get('ci'));

            # nombre cliente
            if ($request->get('cliente'))
                $opClients->where('op_clients.first_name', 'LIKE', '%' . $request->get('cliente') . '%');


            if ($request->get('extension') || $request->get('ci') || $request->get('cliente'))
                $flagClient = 1;

            $details = $opClients->get();

            $result = $query->get();
        }else {
            $result = $query->get();
        }

        # validacion filtra poliza enbase al cliente
        if (count($details) > 0 || $flagClient == 1) {
            $idHeaders = $this->returnIdHeades($details);
            $var = array();
            foreach ($result as $key => $value) {
                if (in_array($value->id, $idHeaders))
                    $var[] = $value;
            }
            $result = $var;
        }
        
        $result = $this->observations($request, $result);
        

        # validacion exporta xls
        if ($request->get('xls_download'))
            $this->exportXls($result, 'General', 1);

        return view('report.general', compact('result', 'users', 'agencies', 'cities', 'extencion', 'valueForm'));
    }
    
    /**
     * regla de consulta para el filtro
     * @param type $request
     * @return array
     */
    public function role($request) {
        $consult = [];
        if ($request->get('freecover'))
            $consult[] = array('`op_de_headers`.`issued`' => 1, '`op_de_headers`.`facultative`' => 0);

        # no freecover
        if ($request->get('no_freecover'))
            $consult[] = array('`op_de_headers`.`issued`' => 1, '`op_de_headers`.`facultative`' => 1, '`op_de_facultatives`.`state`' => 'PR', '`op_de_facultatives`.`approved`' => 1);

        # emitido
        if ($request->get('emitido'))
            $consult[] = array('`op_de_headers`.`issued`' => 1);

        # no emitido
        if ($request->get('no_emitido'))
            $consult[] = array('`op_de_headers`.`issued`' => 0);
        
        # extraprima
        if ($request->get('extraprima'))
            $consult[] = array('`op_de_headers`.`issued`' => 1, '`op_de_facultatives`.`state`' => 'PR', '`op_de_headers`.`facultative`' => 1, '`op_de_facultatives`.`surcharge`' => 1);

        # no extraprima
        if ($request->get('no_extraprima'))
            $consult[] = array('`op_de_headers`.`issued`' => 1, '`op_de_facultatives`.`state`' => 'PR', '`op_de_headers`.`facultative`' => 1, '`op_de_facultatives`.`surcharge`' => 0);

        # rechazados
        if ($request->get('rechazado'))
            $consult[] = array('`op_de_facultatives`.`approved`' => 0, '`op_de_facultatives`.`state`' => 'PR');

        # polizas anuladas
        if ($request->get('anulado'))
            $consult[] = array('`op_de_headers`.`issued`' => 1, '`op_de_headers`.`canceled`' => 0);

        # pendiente
        if ($request->get('pendiente'))
            $consult[] = array('`op_de_headers`.`issued`' => 0, '`op_de_headers`.`facultative`' => 1, '`op_de_facultatives`.`state`' => 'PE');
        
        # subsanado
        if ($request->get('subsanado'))
            $consult[] = array('`op_de_headers`.`issued`' => 0, '`op_de_headers`.`facultative`' => 1, '`op_de_facultatives`.`state`' => 'PE');
        
        # observado
        if ($request->get('observado'))
            $consult[] = array('`op_de_headers`.`issued`' => 0, '`op_de_headers`.`facultative`' => 1, '`op_de_facultatives`.`state`' => 'PE');
        
        $arr=[];
        foreach ($consult as $key => $value) {
            if($key == 0){
                $arr['and']=$value;
            }else{
                $arr['or'][]=$value;
            }
        }
        return $arr;
                
    }
    
    /**
     * regla de consulta observacion para el filtro
     * @param type $request
     * @return array
     */
    public function observations($request, $array) {
        # registros observaciones
        $opDeObservations = DB::table('op_de_observations')->orderBy('id','desc')->get();
        $observation = [];
        foreach ($opDeObservations as $key => $value) {
            if(!isset($observation[$value->op_de_facultative_id])){
                $observation[$value->op_de_facultative_id] =  $value;       
            }
        }
        
        # estados
        $adStates = DB::table('ad_states')->get();
        $states = [];
        foreach ($adStates as $key => $value) {
            $states[$value->id] = $value;
        }
        
        $consult = [];
        if ($request->get('pendiente'))
            $consult[] = 'no';
        
        if ($request->get('subsanado')){
            $consult[] = 'si';
            $consult[] = 'cl';
        }
        
        if ($request->get('subsanado'))
            $consult[] = 'si';
        
        if(count($consult)>0){
            $ress = [];
            foreach ($array as $key => $value) {
                if (in_array('no', $consult) && !in_array('si', $consult)) {
                    # no debe existir en observaciones
                    if(!isset($observation['1453731639'])){
                            $ress[$key] = $value;
                        }
                } elseif (in_array('no', $consult) && in_array('si', $consult)) {
                    #exista o no ingresan todos
                    $ress[$key] = $value;
                    
                } elseif (in_array('si', $consult) && !in_array('no', $consult)) {
                    
                    if (in_array('cl', $consult)) {
                        if(isset($observation['1453731639']) && $states[$observation['1453731639']->ad_state_id]->slug == 'cl'){
                            $ress[$key] = $value;
                        }
                    } else {
                        #solo si existe
                        if(isset($observation['1453731639'])){
                            $ress[$key] = $value;
                        }
                    }
                    
                }
            }
            return $ress;
        }else{
            return $array;
        }
    }

   

    /**
     * fucion retorna cotizaciones
     * @param \Illuminate\Http\Request $request
     * @return type
     */
    public function cotizacion(Request $request) {
        $users = $this->users;
        $agencies = $this->agencies;
        $cities = $this->cities;
        $extencion = $this->extencion;
        
        $valueForm = $this->addValueForm($request);
        
        $opClients = DB::table('op_clients')
                ->join('op_de_details', 'op_clients.id', '=', 'op_de_details.op_client_id')
                ->select('op_de_details.op_de_header_id');

        $query = DB::table('op_de_headers')
                ->join('ad_users', 'op_de_headers.ad_user_id', '=', 'ad_users.id')
                ->join('ad_coverages', 'op_de_headers.ad_coverage_id', '=', 'ad_coverages.id')
                ->select('ad_coverages.name', 'op_de_headers.id', 'op_de_headers.amount_requested', 'op_de_headers.currency', 'op_de_headers.term', 'op_de_headers.type_term', 'op_de_headers.total_rate', 'op_de_headers.total_premium', 'op_de_headers.created_at', 'ad_users.username', 'ad_users.full_name','op_de_headers.ad_user_id')
                //edw-->->where('op_de_headers.issued', 0)
                ->where('op_de_headers.type', 'Q');
        $details = array();
        $result = array();
        $flagClient = 0;
        if ($request->get('_token')) {

            

            # usuario vendedor
            if ($request->get('usuario'))
                $query->where('op_de_headers.ad_user_id', $request->get('usuario'));

            # usuario vendedor agencia
            if ($request->get('agencia'))
                $query->where('ad_users.ad_agency_id', $request->get('agencia'));

            # usuario vendedor sucursal
            if ($request->get('sucursal'))
                $query->where('ad_users.ad_city_id', $request->get('sucursal'));

            # fecha de emision inicial
            if ($request->get('fecha_ini'))
                $query->where('op_de_headers.created_at', '>=', date('Y-m-d', strtotime(str_replace('/', '-', $request->get('fecha_ini')))));

            # fecha de emision final
            if ($request->get('fecha_fin'))
                $query->where('op_de_headers.created_at', '<=', date('Y-m-d', strtotime('+1 days', strtotime(str_replace('/', '-', $request->get('fecha_fin'))))));

            # extencion cliente
            if ($request->get('extension'))
                $opClients->where('op_clients.extension', $request->get('extension'));
            # ci cliente
            if ($request->get('ci'))
                $opClients->where('op_clients.dni', $request->get('ci'));

            # nombre cliente
            if ($request->get('cliente'))
                $opClients->where('op_clients.first_name', 'LIKE', '%' . $request->get('cliente') . '%');


            if ($request->get('extension') || $request->get('ci') || $request->get('cliente'))
                $flagClient = 1;

            $details = $opClients->get();

            $result = $query->get();
        }else {
            $result = $query->get();
        }

        # validacion filtra poliza enbase al cliente
        if (count($details) > 0 || $flagClient == 1) {
            $idHeaders = $this->returnIdHeades($details);
            $var = array();
            foreach ($result as $key => $value) {
                if (in_array($value->id, $idHeaders))
                    $var[] = $value;
            }
            $result = $var;
        }

        # validacion exporta xls
        if ($request->get('xls_download'))
            $this->exportXls($result, 'Cotizacion', 1);

        return view('report.cotizacion', compact('result', 'users', 'agencies', 'cities', 'extencion', 'valueForm'));
    }
    
    /**
     * 
     * @param type $array
     * @param type $name
     * @param type $key
     */
    public function exportXls($array,$name, $key){
        $edd = new ExportXlsController();
            $edd->arrayObj($array, $name, $key);
            $edd->freezeColumn('A');
            $edd->freezeFila('A2');
            $edd->cabecera('A1:M1');
            $edd->exportXls();
    }
    
    /**
     * funcion retorna ids de polizas emitidas en formato array
     * @param type $object
     * @return type
     */
    public function returnIdHeades($object) {
        $val = array();
        if (count($object) > 0) {
            foreach ($object as $key => $value) {
                $val[] = $value->op_de_header_id;
            }
        }
        return $val;
    }

    /**
     * funcion retorna value para el formulario filtro 
     * @param type $request
     * @return type
     */
    public function addValueForm($request) {
        $request->get('numero_poliza');
        $arr = array(
            'numero_poliza' => ($request->get('numero_poliza')) ? $request->get('numero_poliza') : '',
            'cliente' => ($request->get('cliente')) ? $request->get('cliente') : '',
            'agencia' => ($request->get('agencia')) ? $request->get('agencia') : '',
            'ci' => ($request->get('ci')) ? $request->get('ci') : '',
            'usuario' => ($request->get('usuario')) ? $request->get('usuario') : '',
            'extension' => ($request->get('extension')) ? $request->get('extension') : '',
            'sucursal' => ($request->get('sucursal')) ? $request->get('sucursal') : '',
            'fecha_ini' => ($request->get('fecha_ini')) ? $request->get('fecha_ini') : '',
            'fecha_fin' => ($request->get('fecha_fin')) ? $request->get('fecha_fin') : '',
            'anulado' => ($request->get('anulado')) ? $request->get('anulado') : '',
            'rechazado' => ($request->get('rechazado')) ? $request->get('rechazado') : '',
            'freecover' => ($request->get('freecover')) ? $request->get('freecover') : '',
            'no_freecover' => ($request->get('no_freecover')) ? $request->get('no_freecover') : '',
            'extraprima' => ($request->get('extraprima')) ? $request->get('extraprima') : '',
            'no_extraprima' => ($request->get('no_extraprima')) ? $request->get('no_extraprima') : '',
            'emitido' => ($request->get('emitido')) ? $request->get('emitido') : '',
            'no_emitido' => ($request->get('no_emitido')) ? $request->get('no_emitido') : '',
            'pendiente' => ($request->get('pendiente')) ? $request->get('pendiente') : '',
            'subsanado' => ($request->get('subsanado')) ? $request->get('subsanado') : '',
            'observado' => ($request->get('observado')) ? $request->get('observado') : '',
        );
        return $arr;
    }

}
