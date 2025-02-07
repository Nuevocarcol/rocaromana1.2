<?php

use Google\Client;
use App\Models\User;
use GuzzleHttp\Pool;
use App\Models\Article;
use App\Models\Setting;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Language;
use App\Models\Projects;
use App\Models\Property;
use App\Models\Favourite;
use App\Models\parameter;
use App\Models\Usertokens;
use Illuminate\Support\Str;

use App\Models\user_reports;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Promise\Utils;
use App\Models\Advertisement;
use App\Models\InterestedUser;
use App\Models\BlockedChatUser;
use Illuminate\Support\Carbon;
use App\Models\PropertysInquiry;
use kornrunner\Blurhash\Blurhash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\UserPurchasedPackage;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Client as GuzzleClient;
use Intervention\Image\ImageManagerStatic as Image;


if (!function_exists('system_setting')) {

    function system_setting($type)
    {

        $db = Setting::where('type', $type)->first();
        return (isset($db)) ? $db->data : '';
    }
}

function form_submit($data = '', $value = '', $extra = '')
{
    $defaults = array(
        'type' => 'submit',
        'name' => is_array($data) ? '' : $data,
        'value' => $value
    );

    return '<input ' . _parse_form_attributes($data, $defaults) . _attributes_to_string($extra) . " />\n";
}
function _parse_form_attributes($attributes, $default)
{
    if (is_array($attributes)) {
        foreach ($default as $key => $val) {
            if (isset($attributes[$key])) {
                $default[$key] = $attributes[$key];
                unset($attributes[$key]);
            }
        }

        if (count($attributes) > 0) {
            $default = array_merge($default, $attributes);
        }
    }

    $att = '';

    foreach ($default as $key => $val) {
        if ($key === 'value') {
            $val = ($val);
        } elseif ($key === 'name' && !strlen($default['name'])) {
            continue;
        }

        $att .= $key . '="' . $val . '" ';
    }

    return $att;
}


// ------------------------------------------------------------------------

if (!function_exists('_attributes_to_string')) {
    /**
     * Attributes To String
     *
     * Helper function used by some of the form helpers
     *
     * @param	mixed
     * @return	string
     */
    function _attributes_to_string($attributes)
    {
        if (empty($attributes)) {
            return '';
        }

        if (is_object($attributes)) {
            $attributes = (array) $attributes;
        }

        if (is_array($attributes)) {
            $atts = '';

            foreach ($attributes as $key => $val) {
                $atts .= ' ' . $key . '="' . $val . '"';
            }

            return $atts;
        }

        if (is_string($attributes)) {
            return ' ' . $attributes;
        }

        return FALSE;
    }
}

if (!function_exists('send_push_notification')) {
    //send Notification
    /*function send_push_notification($registrationIDs = array(), $fcmMsg = '', $send_payload = NULL)
    {
        $unregisteredIDs = array(); // Array to store unregistered FCM IDs
        if (!count($registrationIDs)) {
            return false;
        }
        
        foreach ($registrationIDs as $registeredId) {
            if(!empty($registeredId)){
                $fcmFields = array(
                    'message' => array(
                        'token' => $registeredId, // expects an array of ids
                        'notification' => array('title' => $fcmMsg['title'],'body' => $fcmMsg['body']),
                        'data' => $fcmMsg
                    )
                );
                $access_token = getAccessToken();
                $projectId = system_setting('firebase_project_id');
                $url='https://fcm.googleapis.com/v1/projects/'. $projectId . '/messages:send';

                $headers = array(
                    'Authorization: Bearer ' . $access_token,
                    'Content-Type: application/json'
                );

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

                // Disabling SSL Certificate support temporarly
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmFields));

                // Execute post
                $result = curl_exec($ch);

                if ($result == FALSE) {
                    die('Curl failed: ' . curl_error($ch));
                } else{
                    $decodedResult = json_decode($result); // Decode the Result
                }

                // Get the Unregistered Ids Whose result error code got 400 that is FCM is not valid
                if (isset($decodedResult)) {
                    if (isset($decodedResult->error) && $decodedResult->error->code == 400) {
                        $unregisteredIDs[] = $registeredId;
                    }
                }

                // Close connection
                curl_close($ch);
            }else{
                $unregisteredIDs[] = $registeredId;
            }


        }

        if (!empty($unregisteredIDs)) {
            Usertokens::whereIn('fcm_id', $unregisteredIDs)->delete();
        }
        return $result ?? null;

    }*/
    function send_push_notification($registrationIDs = array(), $fcmMsg = '') {
        if (!count($registrationIDs)) {
            return false;
        }
    
        $client = new GuzzleClient();
        $access_token = getAccessToken(); // Get Access Token
        $projectId = system_setting('firebase_project_id'); // Get Project Id
        $url = 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send'; // Create URL
        // Headers
        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ];
    
        // Create Requests
        $requests = function ($registrationIDs) use ($url, $headers, $fcmMsg) {
            foreach ($registrationIDs as $registrationID) {
                $fcmFields = [
                    'json' => [
                        'message' => [
                            'token' => $registrationID,
                            'notification' => [
                                'title' => $fcmMsg['title'],
                                'body' => $fcmMsg['body']
                            ],
                            'data' => $fcmMsg
                        ]
                    ]
                ];
                yield new Request('POST', $url, $headers, json_encode($fcmFields['json']));
            }
        };
    
        // This is used to Process multiple Request at a same time
        $pool = new Pool($client, $requests($registrationIDs), [
            'concurrency' => 10, // Adjust based on your server capability
            'fulfilled' => function ($response, $index) {
                // Code after fulfilled Request
            },
            'rejected' => function ($reason, $index) use (&$unregisteredIDs, $registrationIDs) {
                $response = $reason->getResponse();
                if ($response) {
                    $decodedResult = json_decode($response->getBody(), true);
                    if (isset($decodedResult['error']['status']) && ($decodedResult['error']['status'] == 'INVALID_ARGUMENT' || $decodedResult['error']['status'] == 'NOT_FOUND')) {
                        $unregisteredIDs[] = $registrationIDs[$index];
                    }
                }
                Log::error($reason->getMessage());
            },
        ]);
    
        $promise = $pool->promise();
        $promise->wait();
    
        if (!empty($unregisteredIDs)) {
            Usertokens::whereIn('fcm_id', $unregisteredIDs)->delete();
        }
    
        return true;
    }
}

if (!function_exists('get_countries_from_json')) {
    function get_countries_from_json()
    {
        $country =  json_decode(file_get_contents(public_path('json') . "/cities.json"), true);

        $tempRow = array();
        foreach ($country['countries'] as $row) {
            $tempRow[] = $row['country'];
        }
        return $tempRow;
    }
}

if (!function_exists('get_states_from_json')) {
    function get_states_from_json($country)
    {


        $state =  json_decode(file_get_contents(public_path('json') . "/cities.json"), true);

        $tempRow = array();
        foreach ($state['countries'] as $row) {
            // echo $row;
            if ($row['country'] == $country) {
                $tempRow = $row['states'];
            }
        }

        return $tempRow;
    }
}


if (!function_exists('parameterTypesByCategory')) {
    function parameterTypesByCategory($category_id, $property_id = null)
    {
        // $parameter_types = DB::table('categories')->select('parameter_types')->where('categories.id', $category_id)->first();
        $parameterTypes = Category::where('id', $category_id)->pluck('parameter_types')->first();

        // Explode the parameter type having string number separated by comma
        $parameterTypes = explode(',', $parameterTypes);

        if (!empty($parameterTypes)) {

            // Check the parameter
            $parameterQueryData = parameter::whereIn('id', $parameterTypes)
                ->with(['assigned_parameter' => function ($query) use ($property_id) {
                    if ($property_id) {
                        $query->where('modal_id', $property_id);
                    }
                }])
                ->get();

            // Sort the collection in the order of $parameterTypes
            $sortedParameterData = $parameterQueryData->sortBy(function ($item) use ($parameterTypes) {
                return array_search($item->id, $parameterTypes);
            });

            // Reset the keys on the sorted collection
            $parameterData = $sortedParameterData->values();
        } else {
            $parameterData = array(); // return an empty Array if $parameterTypes is empty
        }

        return  $parameterData;
    }
}
function update_subscription($user_id)
{
    DB::enableQueryLog();
    $updateUserPackage = array();
    // User Package Query
    $userPackages = UserPurchasedPackage::with('package','customer')->where('modal_id', $user_id);
    // Result Data
    $result = $userPackages->clone()->with('package','customer')->get();

    // Get Package Count
    $packageCount = $userPackages->clone()->where(function($query){
        $query->where('prop_status', 1)->orWhere('adv_status', 1);
    })->count();

    // loop on result data
    if(collect($result)->isNotEmpty()){
        foreach ($result as $key => $row) {
            // Get end date of current looped data
            $endDate = Carbon::parse($row->end_date, 'UTC')->startOfDay(); // Parse the date with UTC time zone and set time to start of the day
            $currentDate = Carbon::now()->startOfDay(); // Set current date time to start of the day

            // Get Difference in days
            $diffInDays = $currentDate->diffInDays($endDate, false); // Use 'false' parameter to get absolute difference

            // If days are zero or in negative
            if ($diffInDays < 0) {
                $updateUserPackage[] = array(
                    'id' => $row->id,
                    'prop_status' => 0,
                    'adv_status' => 0
                );
                if (!empty($row->package) && $row->package->type == "premium_user"){
                    $customerPremiumStatus = 0;
                }
            }

        }
    }
    // if ($data) {
    // }
}
function get_hash($img)
{

    $image_make = Image::make($img);
    $width = $image_make->width();
    $height = $image_make->height();

    $pixels = [];
    for ($y = 0; $y < $height; ++$y) {
        $row = [];
        for ($x = 0; $x < $width; ++$x) {
            $colors = $image_make->pickColor($x, $y);

            $row[] = [$colors[0], $colors[1], $colors[2]];
        }
        $pixels[] = $row;
    }

    $components_x = 4;
    $components_y = 3;
    $hash =  Blurhash::encode($pixels, $components_x, $components_y);
    //  "ll";
    return $hash;
}
if (!function_exists('form_hidden')) {
    /**
     * Hidden Input Field
     *
     * Generates hidden fields. You can pass a simple key/value string or
     * an associative array with multiple values.
     *
     * @param	mixed	$name		Field name
     * @param	string	$value		Field value
     * @param	bool	$recursing
     * @return	string
     */
    function form_hidden($name, $value = '', $recursing = FALSE)
    {
        static $form;

        if ($recursing === FALSE) {
            $form = "\n";
        }

        if (is_array($name)) {
            foreach ($name as $key => $val) {
                form_hidden($key, $val, TRUE);
            }

            return $form;
        }

        if (!is_array($value)) {
            $form .= '<input type="hidden" name="' . $name . '" value="' . ($value) . "\" />\n";
        } else {
            foreach ($value as $k => $v) {
                $k = is_int($k) ? '' : $k;
                form_hidden($name . '[' . $k . ']', $v, TRUE);
            }
        }

        return $form;
    }
}
if (!function_exists('form_close')) {
    /**
     * Form Close Tag
     *
     * @param	string
     * @return	string
     */
    function form_close($extra = '')
    {
        return '</form>' . $extra;
    }
}
function get_property_details($result, $current_user = NULL)
{
    $rows = array();
    $tempRow = array();
    //return $result;
    $count = 1;
    $rows1 = array();
    $tempRow1 = array();
    //return $result;
    $count1 = 1;
    // if (!$result->isEmpty()) {
    // print_r($result->toArray());
    foreach ($result as $row) {
        // print_r($row->user->toArray());

        $customer = $row->customer;
        $user = $row->user->first();

        if ($customer && $row->added_by != 0) {
            $isBlockedByMe = false;
            $isBlockedByUser = false;
            if($current_user){

                $isBlockedByMe = BlockedChatUser::where('by_user_id', $current_user)
                    ->where('user_id', $row->added_by)
                    ->exists();

                $isBlockedByUser = BlockedChatUser::where('by_user_id', $row->added_by)
                    ->where('user_id', $current_user)
                    ->exists();

            }
            $tempRow['is_blocked_by_me'] = $isBlockedByMe;
            $tempRow['is_blocked_by_user'] = $isBlockedByUser;
            $tempRow['customer_name'] = $customer->name;
            $tempRow['email'] = $customer->email;
            $tempRow['mobile'] = $customer->mobile;

            $tempRow['profile'] = $customer->profile;
            $tempRow['client_address'] = $customer->address;
        } else if ($row->added_by == 0) {
            $isBlockedByMe = false;
            $isBlockedByAdmin = false;
            if($current_user){

                $isBlockedByMe = BlockedChatUser::where('by_user_id', $current_user)
                    ->where('admin', 1)
                    ->exists();

                $isBlockedByUser = BlockedChatUser::where('by_admin', 1)
                    ->where('user_id', $current_user)
                    ->exists();

            }

            $tempRow['is_blocked_by_me'] = $isBlockedByMe;
            $tempRow['is_blocked_by_user'] = $isBlockedByAdmin;

            $adminData = User::where('type',0)->select('id','name','profile')->first();

            $adminCompanyTel1 = system_setting('company_tel1');
            $adminEmail = system_setting('company_email');



            $tempRow['customer_name'] = "Admin";
            $tempRow['mobile'] = !empty($adminCompanyTel1) ? $adminCompanyTel1 : "";
            $tempRow['email'] = !empty($adminEmail) ? $adminEmail : "";
            $tempRow['profile'] = !empty($adminData->getRawOriginal('profile')) ? $adminData->profile : url('assets/images/faces/2.jpg');
            $tempRow['client_address'] = $row->client_address;
        }

        $tempRow['id'] = $row->id;
        $tempRow['slug_id'] = $row->slug_id;
        $tempRow['title'] = $row->title;
        $tempRow['price'] = $row->price;
        $tempRow['category'] = $row->category;

        $tempRow['description'] = $row->description;
        $tempRow['address'] = $row->address;

        /*if ($row->propery_type == 0) {
            $tempRow['property_type'] = "sell";
        } elseif ($row->propery_type == 1) {
            $tempRow['property_type'] = "rent";
        } elseif ($row->propery_type == 2) {
            $tempRow['property_type'] = "sold";
        } elseif ($row->propery_type == 3) {
            $tempRow['property_type'] = "Rented";
        }*/

        $tempRow['property_type'] = $row->propery_type;



        $tempRow['title_image'] = $row->title_image;

        $tempRow['title_image_hash'] = $row->title_image_hash != '' ? $row->title_image_hash : '';
        $tempRow['threeD_image'] = $row->threeD_image;

        $tempRow['post_created'] = $row->created_at->diffForHumans();
        $tempRow['gallery'] = $row->gallery;
        $tempRow['documents'] = $row->documents;
        $tempRow['total_view'] = $row->total_click;
        $tempRow['status'] = $row->status;
        $tempRow['state'] = $row->state;
        $tempRow['city'] = $row->city;
        $tempRow['country'] = $row->country;
        $tempRow['latitude'] = $row->latitude;
        $tempRow['longitude'] = $row->longitude;
        $tempRow['added_by'] = $row->added_by;
        $tempRow['video_link'] = $row->video_link;
        $tempRow['rentduration'] = ($row->rentduration != '') ? $row->rentduration : "Monthly";
        $tempRow['meta_title'] = $row->meta_title;
        $tempRow['meta_description'] = $row->meta_description;
        $tempRow['meta_keywords'] = $row->meta_keywords;
        $tempRow['meta_image'] = $row->meta_image;
        $tempRow['is_premium'] = $row->is_premium ? true : false;
        $tempRow['assign_facilities'] = $row->assign_facilities;
        $tempRow['is_verified'] = $row->is_user_verified;
        $tempRow['code'] = $row->code;


        /*$tempRow['assign_facilities'] = [];
        foreach ($row->assignfacilities as $facility) {
            $tempRow['assign_facilities'][] = [
                'id' => $facility->id,
                'property_id' => $facility->property_id,
                'facility_id' => $facility->facility_id,
                'distance' => $facility->distance,
                'created_at' => $facility->created_at,
                'updated_at' => $facility->updated_at,
                'name' => $facility->outdoorfacilities->name,
                'image' => $facility->outdoorfacilities->image,
            ];
        }*/


        $inquiry = PropertysInquiry::where('customers_id', $current_user)->where('propertys_id', $row->id)->where('status', 2)->first();

        if ($inquiry) {
            $tempRow['inquiry'] = true;
        } else {
            $tempRow['inquiry'] = false;
        }
        $promoted = Advertisement::where('property_id', $row->id)->first();

        $tempRow['promoted'] = $row->is_promoted;

        $interested_users = array();
        $favourite_users = array();
        $s = '';
        foreach ($row->favourite as $favourite_user) {

            if ($favourite_user->property_id == $row->id) {

                array_push($favourite_users, $favourite_user->user_id);
                $s .= $favourite_user->user_id . ',';
            }
        }

        foreach ($row->interested_users as $interested_user) {

            if ($interested_user->property_id == $row->id) {

                array_push($interested_users, $interested_user->customer_id);
                $s .= $interested_user->user_id . ',';
            }
        }

        $tempRow['favourite_users'] = $favourite_users;
        $tempRow['total_favourite_users'] = count($favourite_users);
        $tempRow['interested_users'] = $interested_users;
        $tempRow['total_interested_users'] = count($interested_users);


        $favourite = Favourite::where('property_id', $row->id)->where('user_id', $current_user)->get();

        $interest = InterestedUser::where('property_id', $row->id)->where('customer_id', $current_user)->get();

        $report_count = user_reports::where('property_id', $row->id)->where('customer_id', $current_user)->get();


        if (count($report_count) != 0) {

            $tempRow['is_reported'] = true;
        } else {
            $tempRow['is_reported'] = false;
        }


        if (count($favourite) != 0) {
            $tempRow['is_favourite'] = 1;
        } else {
            $tempRow['is_favourite'] = 0;
        }

        if (count($interest) != 0) {
            $tempRow['is_interested'] = 1;
        } else {
            $tempRow['is_interested'] = 0;
        }
        /*$tempRow['favourite_users'] = $favourite_users;
        $tempRow['interested_users'] = $interested_users;

        $tempRow['total_interested_users'] = count($interested_users);
        $tempRow['total_favourite_users'] = count($favourite_users);*/


        $arr = [];
        $arr1 = [];

        if ($row->advertisement) {
            $tempRow['advertisement'] = $row->advertisement;
        }
        // print_r($row->parameters->toArray());
        $tempRow['parameters'] = [];

        $parameterTypeByCategoryData = parameterTypesByCategory($row->category_id, $row->id);
        foreach ($parameterTypeByCategoryData as $res) {
            if (!empty($res->assigned_parameter) && !empty($res->assigned_parameter->value)) {
                if (is_string($res->assigned_parameter->value) && is_array(json_decode($res->assigned_parameter->value, true))) {
                    $value = json_decode($res->assigned_parameter->value, true);
                } else {
                    if ($res->type_of_parameter == "file") {
                        if ($res->assigned_parameter->value == "null") {
                            $value = "";
                        } else {
                            $value = url('') . config('global.IMG_PATH') . config('global.PARAMETER_IMG_PATH') . '/' .  $res->assigned_parameter->value;
                        }
                    } else {
                        if ($res->assigned_parameter->value == "null" || $res->assigned_parameter->value == null) {
                            $value = "";
                        } else {
                            $value = $res->assigned_parameter->value;
                        }
                    }
                }
                $parameter = [
                    'id' => $res->id,
                    'name' => $res->name,
                    'type_of_parameter' => $res->type_of_parameter,
                    'type_values' => $res->type_values,
                    'image' => $res->image,
                    'value' => $value,
                ];
                array_push($tempRow['parameters'], $parameter);
            }
        }


        $rows[] = $tempRow;
        $parameters[] = $arr;
        $count++;
    }
    return $rows;
}
function get_language()
{
    return Language::get();
}
function get_unregistered_fcm_ids($registeredIDs = array())
{

    // Convert the arrays to lowercase for case-insensitive comparison
    $registeredIDsLower = array_map('strtolower', $registeredIDs);



    // Retrieve the FCM IDs from the 'usertoken' table
    $fcmIDs = Usertokens::pluck('fcm_id')->toArray();

    // Now you have an array ($fcmIDs) containing all the FCM IDs from the 'usertoken' table

    $allIDsLower = array_map('strtolower', $fcmIDs);


    // Use array_diff to find the FCM IDs that are not registered
    $unregisteredIDsLower = array_diff($allIDsLower, $registeredIDsLower);


    // Convert the IDs back to their original case
    $unregisteredIDs = array_map('strtoupper', $unregisteredIDsLower);
    Usertokens::WhereIn('fcm_id', $fcmIDs)->delete();
}
function handleFileUpload($request, $key, $destinationPath, $filename, $databaseData = null)
{
    if ($request->hasFile($key)) {
        if(!empty($databaseData)){
            // Delete the old file if it exists
            $oldFilePath = $destinationPath . '/' . $databaseData;
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }
        $extension = $request->file($key)->getClientOriginalExtension();
        // Change the file name
        $filename = microtime(true).'.'.$extension;

        $profile = $request->file($key);
        $profile->move($destinationPath, $filename);
        $value = $filename;

        return $value;
    }

    return null;
}
function get_url_contents($url)
{
    $crl = curl_init();

    curl_setopt($crl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)');
    curl_setopt($crl, CURLOPT_URL, $url);
    curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, 5);

    $ret = curl_exec($crl);
    curl_close($crl);
    return $ret;
}

function check_subscription($user, $type, $status)
{
    DB::enableQueryLog();
    $current_package = UserPurchasedPackage::where('modal_id', $user)
        // ->with(['package' => function ($q) use ($type) {
        //     $q->select('id', $type)->where($type, '>', 0)->orWhere($type, null);
        // }])

        ->whereHas('package', function ($q) use ($type) {
            $q->where($type, '>', 0)->orWhere($type, null);
        })->where($status, 1)
        ->first();

    return $current_package;
}
function store_image($file, $path)
{
    $destinationPath = public_path('images') . config('global.' . $path);
    if (!is_dir($destinationPath)) {
        mkdir($destinationPath, 0777, true);
    }

    // Check if the file is an instance of UploadedFile
    if ($file instanceof \Illuminate\Http\UploadedFile) {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();

        // Initialize the filename
        // $filename = $originalName . '.' . $extension;
        //$filename = str_replace(' ', '_', $originalName) . '_' . time(). '.' . $extension;
        $filename = microtime(true). '.' . $extension;

        $file->move($destinationPath, $filename);
        return $filename;
    } else {
        // Handle the case when the file is not an instance of UploadedFile
        // You can log an error or throw an exception here
        // For now, we return null
        return null;
    }
}
function unlink_image($url)
{
    if(!empty($url)){
        $relativePath = parse_url($url, PHP_URL_PATH);
        if (file_exists(public_path()  . $relativePath)) {
            unlink(public_path()  . $relativePath);
        }
    }
}

/** Generate Slugs Functions */
if (!function_exists('generateUniqueSlug')) {
    function generateUniqueSlug($title, $type, $originalSlug = null, $exceptId = null) {
        if (!$originalSlug) {
            $originalSlug = Str::slug($title);
        } else {
            $originalSlug = Str::slug($originalSlug);
        }

        if(empty($originalSlug)){
            $originalSlug = "slug";
        }

        $tableNames = [
            1 => 'propertys',
            2 => 'articles',
            3 => 'categories',
            4 => 'projects',
            5 => 'customers',
            6 => 'users'
        ];

        $tableName = $tableNames[$type] ?? null;

        return generateSlug($originalSlug, $tableName, $exceptId);
    }
}

if (!function_exists('generateSlug')) {
    function generateSlug($originalSlug, $tableName) {
        $counter = 1;
        $slug = $originalSlug;

        if(empty($exceptId)){
            while (DB::table($tableName)->where('slug_id', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
        }else{
            while (DB::table($tableName)->whereNot('id',$exceptId)->where('slug_id', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
        }
        return $slug;
    }
}
/** END OF Generate Slugs Functions */
if (!function_exists('getAccessToken')) {
    function getAccessToken(){
        $file_name = system_setting('firebase_service_json_file');

        $file_path = public_path() . '/assets/'. $file_name;

        $client = new Client();
        $client->setAuthConfig($file_path);
        $client->setScopes(['https://www.googleapis.com/auth/firebase.messaging']);
        $accessToken=$client->fetchAccessTokenWithAssertion()['access_token'];


        return $accessToken;
    }
}

if (!function_exists('updateEnv')) {
    function updateEnv($envUpdates){
        $envFile = file_get_contents(base_path('.env'));

        foreach ($envUpdates as $key => $value) {
            // Check if the key exists in the .env file
            if (strpos($envFile, "{$key}=") === false) {
                // If the key doesn't exist, add it
                $envFile .= "\n{$key}=\"{$value}\"";
            } else {
                // If the key exists, replace its value
                $envFile = preg_replace("/{$key}=.*/", "{$key}=\"{$value}\"", $envFile);
            }
        }

        // Save the updated .env file
        file_put_contents(base_path('.env'), $envFile);
    }
}
