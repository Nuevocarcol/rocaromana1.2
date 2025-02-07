<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Usertokens;
use Illuminate\Http\Request;
use App\Models\Advertisement;
use App\Models\Notifications;
use App\Services\HelperService;
use App\Services\ResponseService;
use Illuminate\Support\Facades\Log;

class AdvertisementController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        if (!has_permissions('read', 'advertisement')) {
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
        }
        return view('advertisement.index');
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

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        if (!has_permissions('read', 'advertisement')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');



        // \DB::enableQueryLog(); // Enable query log
        $sql = Advertisement::with('customer','property:id,title_image')->orderBy($sort, $order);

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql = $sql->where('id', 'LIKE', "%$search%")->orwhere('title', 'LIKE', "%$search%")->orWhereHas('customer', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%$search%");
            });
        }

        $total = $sql->count();

        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }
        $res = $sql->get();
        // dd(\DB::getQueryLog());

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $count = 1;
        $status = '';


        $operate = '';
        foreach ($res as $row) {
            $operate = '<a  id="' . $row->id . '"  class="btn icon btn-primary btn-sm rounded-pill edit_btn"  data-status="' . $row->status . '" data-oldimage="' . $row->image . '" data-types="' . $row->id . '" data-bs-toggle="modal" data-bs-target="#editModal"  onclick="setValue(this.id);" title="Edit"><i class="fa fa-edit edit_icon"></i></a>';
            $tempRow = $row->toArray();


            $tempRow['edit_status_url'] = route('featured_properties.update-advertisement-status');

            if ($row->status == 0) {
                $status = trans('Approved');
            }
            if ($row->status == 1) {
                $status = trans('Pending');
            }
            if ($row->status == 2) {
                $status = trans('Rejected');
            }
            $tempRow['status'] = $status;

            $tempRow['operate'] = $operate;
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
            return redirect()->back()->with('error', PERMISSION_ERROR_MSG);
            /*$response['error'] = true;
            $response['message'] = PERMISSION_ERROR_MSG;
            return response()->json($response);*/
        } else {
            Advertisement::find($request->id)->update(['status' => $request->edit_adv_status]);

            $adv = Advertisement::with('customer')->find($request->id);
            $status = $adv->status;
            if ($status == '0') {
                $status_text  = 'Approved';
            } else if ($status == '1') {
                $status_text  = 'Pending';
            } else if ($status == '2') {
                $status_text  = 'Rejected';
            }

            try {
                $advertisementData = Advertisement::with('customer:id,name,email','property:id,title')->select('id','customer_id','property_id')->find($request->id);
                if($advertisementData->customer->email){
                    // Get Data of email type
                    $emailTypeData = HelperService::getEmailTemplatesTypes("property_ads_status");

                    // Email Template
                    $userStatusTemplateData = system_setting($emailTypeData['type']);
                    $appName = env("APP_NAME") ?? "eBroker";
                    $variables = array(
                        'app_name' => $appName,
                        'user_name' => $advertisementData->customer->name,
                        'property_name' => $advertisementData->property->title,
                        'advertisement_status' => $status_text,
                        'email' => $advertisementData->customer->email
                    );
                    if(empty($userStatusTemplateData)){
                        $userStatusTemplateData = "Your Property :- ".$variables['propertyName']."'s feature status ".$variables['status'];
                    }
                    $userStatusTemplate = HelperService::replaceEmailVariables($userStatusTemplateData,$variables);

                    $data = array(
                        'email_template' => $userStatusTemplate,
                        'email' => $advertisementData->customer->email,
                        'title' => $emailTypeData['title'],
                    );
                    HelperService::sendMail($data);

                }


            } catch (Exception $e) {
                Log::error("Something Went Wrong in Feature Mail Sending");
            }

            if ($adv->customer->notification == 1) {
                
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
