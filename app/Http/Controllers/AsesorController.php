<?php

namespace App\Http\Controllers;

use App\Models\Usertokens;
use Illuminate\Http\Request;
use App\Models\Notifications;
use App\Models\Asesor;
use App\Models\Package;
use App\Models\Zonas;
use App\Services\ResponseService;

use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Log;


class AsesorController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //

        return view('asesores.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }




    public function show(Request $request){

        $offset = $request->input('offset', 0);
        $limit  = $request->input('limit', 10);
        $sort   = $request->input('sort', 'sequence');
        $order  = $request->input('order', 'ASC');

        $ciudadeCodes = [];

        if(Auth::user()->zone != NULL){
            $zona = Zonas::where(['id' => Auth::user()->zone])->with('ciudades')->get();
            $zona[0]->ciudades->each(function ($ciudad) use (&$ciudadeCodes, &$ventasCiudad) {
                array_push($ciudadeCodes, $ciudad->codigo);
            });


            Log::warning($ciudadeCodes);

            $sql = Asesor::orderBy($sort, $order);
            $sql->where(['cargo' =>'asesor'])->whereIn('ciudad', $ciudadeCodes);
        }else{
            $sql = Asesor::orderBy($sort, $order);
            $sql->where(['cargo' => 'asesor']);
        }



        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->where('id', 'LIKE', "%$search%")->orwhere('email', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%");
        }


        $total = $sql->count();

        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }

        $res = $sql->with('ventas')->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $count = 1;


        $presupuesto = 1000000;

        foreach ($res as $row) {


            $tempRow = $row->toArray();

            $operate = '<a  id="' . $row->id . '"  data-id="' . $row->id . '"class="btn icon btn-primary btn-sm rounded-pill editdata"  data-bs-toggle="modal" data-bs-target="#viewDetailAsesor"  title="Detalle"><i class="fa fa-eye"></i></a>';

            $ventas = 0;

            foreach($row->ventas as $venta){
                $ventas += intval((Package::where(['id' => $venta->plan])->first())->price);
            }

            $seguimiento = $ventas * 100 / $presupuesto;

            $tempRow['nombre']  = $row->nombres.' '.$row->apellidos;
            $tempRow['operate'] = $operate;
            $tempRow['presupuesto'] = $seguimiento;
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);

    }




    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        if (!has_permissions('update', 'advertisement')) {
            $response['error'] = true;
            $response['message'] = PERMISSION_ERROR_MSG;
            return response()->json($response);
        } else {
            Advertisement::find($request->id)->update(['status' => $request->edit_adv_status]);

            $adv = Advertisement::with('customer')->find($request->id);
            $status = $adv->status;
            if ($adv->customer->notification == 1) {
                if ($status == '0') {
                    $status_text  = 'Approved';
                } else if ($status == '1') {
                    $status_text  = 'Pending';
                } else if ($status == '2') {
                    $status_text  = 'Rejected';
                }
                $user_token = Usertokens::where('customer_id', $adv->customer->id)->pluck('fcm_id')->toArray();
                //START :: Send Notification To Customer
                $fcm_ids = array();
                $fcm_ids = $user_token;
                if (!empty($fcm_ids)) {
                    $registrationIDs = $fcm_ids;
                    $fcmMsg = array(
                        'title' => 'Advertisement Request',
                        'message' => 'Advertisement Request Is ' . $status_text,
                        'type' => 'advertisement_request',
                        'body' => 'Advertisement Request Is ' . $status_text,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'sound' => 'default',
                        'id' => (string)$adv->id,
                    );
                    send_push_notification($registrationIDs, $fcmMsg);
                }
                //END ::  Send Notification To Customer

                Notifications::create([
                    'title' => 'Property Inquiry Updated',
                    'message' => 'Your Advertisement Request is ' . $status_text,
                    'image' => '',
                    'type' => '1',
                    'send_type' => '0',
                    'customers_id' => $adv->customer->id,
                    'propertys_id' => $adv->id
                ]);
            }

            ResponseService::successRedirectResponse('Advertisement status update Successfully');
        }
    }

    public function updateStatus(Request $request)
    {
        if (!has_permissions('update', 'advertisement')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            Advertisement::where('id', $request->id)->update(['is_enable' => $request->status]);
            ResponseService::successResponse($request->status ? "Advertisement Activatd Successfully" : "Advertisement Deactivatd Successfully");
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
