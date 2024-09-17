<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index()
    {
        // Fetch all users from the 'users' table
        $users = User::all();

        // Return users data as JSON
        return response()->json([
            'status' => 200,
            'data' => $users
        ], 200);
    }

    public function findByName(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:191',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages(),
            ], 422);
        }

        // Retrieve users with the specified name
        $users = User::where('name', $request->input('name'))->first();

        return response()->json([
            'status' => "success",
            'data' => $users
        ], 200);
    }
    public function getCategory()
    {
        // Fetch categories where status is Active and home is 1
        $categories = DB::table('category')
            ->select('*')
            ->where('category_status', 'Active')
            ->where('home', 1)
            ->limit(10)
            ->get();

        if ($categories->isEmpty()) {
            $response = [
                'status' => false,
                'message' => 'Sub Categories Not Found ...'
            ];
        } else {
            $discount_array = [];  // Initialize discount_array

            foreach ($categories as $category) {
                // Fetch sub-categories for each category
                $subCategories = DB::table('sub_category_1')
                    ->select('sub_category_1_id', 'sub_category_1_name')
                    ->where('status', 'Active')
                    ->where('category_id', $category->category_id)
                    ->get();

                $discount_array1 = [];

                foreach ($subCategories as $subCategory) {
                    // Fetch sub-sub-categories for each sub-category
                    $subSubCategories = DB::table('sub_category_2')
                        ->select('sub_category_2_id', 'sub_category_2_name')
                        ->where('status', 'Active')
                        ->where('sub_category_1_id', $subCategory->sub_category_1_id)
                        ->get();

                    // Prepare banner data for sub-categories
                    $banner_data = [
                        'sub_category_id' => $subCategory->sub_category_1_id,
                        'sub_category_name' => $subCategory->sub_category_1_name,
                        'sub_category_array' => $subSubCategories->toArray() // Convert to array
                    ];

                    array_push($discount_array1, $banner_data);
                }

                // Prepare banner data for categories
                $banner_data = [
                    'category_id' => $category->category_id,
                    'category_name' => $category->category_name,
                    'category_array' => $discount_array1
                ];

                array_push($discount_array, $banner_data);
            }

            // Prepare final response
            $response = [
                'status' => true,
                'message' => 'Sub Categories Found Successfully',
                'categories_array' => $discount_array
            ];
        }

        return response()->json($response);
    }
    public function register_as_seller(Request $request)
    {
        date_default_timezone_set('Asia/Kolkata');


        $data = $request->json()->all();


        $sellerInfo = $data['seller_info'] ?? [];
        $business_info = $data['business_info'] ?? [];
        $bank_details = $data['bank_details'] ?? [];
        $store_info = $data['store_info'] ?? [];


        // Fetch the last user ID for unique ID generation
        $lastUserId = DB::table('users')->orderBy('user_id', 'DESC')->value('user_id') ?? 0;

        // Fetch state subdivision details
        $state = DB::table('states')
            ->select('state_subdivision_id', 'state_subdivision_code')
            ->where('state_subdivision_id', $business_info['stateID'] ?? '')
            ->first();

        if ($state) {
            $uniqueId = $state->state_subdivision_code . 'S' . $state->state_subdivision_id . $lastUserId;
        } else {
            $uniqueId = 'UnknownStateS' . ($lastUserId + 1); // Default or handle missing state
        }

        // Prepare seller info array
        $sellerData = [
            'name' => trim(($sellerInfo['firstName'] ?? '') . ' ' . ($sellerInfo['lastName'] ?? '')),
            'password' => $sellerInfo['password'] ?? '',
            'email' => $sellerInfo['email'] ?? '',
            'mobile' => $sellerInfo['mobile'] ?? '',
            // 'city' => $business_info['city'] ?? '',
            // 'country' => $business_info['countryID'] ?? '',
            // 'state' => $business_info['stateID'] ?? '',
            // 'address' => $business_info['address'] ?? '',
        ];

        $existingSellerEmail = DB::table('users')
            ->where('email', $sellerData['email'])
            ->where('role', 'seller')
            ->first();

        if (!$existingSellerEmail) {
            $existingSellerMobile = DB::table('users')
                ->where('mobile', $sellerData['mobile'])
                ->where('role', 'seller')
                ->first();

            if (!$existingSellerMobile) {
                $userId = DB::table('users')->insertGetId(array_merge($sellerData, ['role' => 'seller', 'status' => 'Active']));

                if ($userId) {
                    $businessData = [
                        'business_name' => $business_info['businessName'] ?? '',
                        'registration_number' => $business_info['companyRegisterNumber'] ?? '',
                        'city' => $business_info['city'] ?? '',
                        'country_id' => $business_info['countryID'] ?? '',
                        'state_subdivision_id' => $business_info['stateID'] ?? '',
                        'address1' => $business_info['apartment'] ?? '',
                        'address2' => $business_info['address'] ?? '',
                        'postal_code' => $business_info['postalCode'] ?? '',
                        'user_id' => $userId
                    ];
                    DB::table('business_info')->insert($businessData);

                    // Prepare and insert bank details
                    $bankData = [
                        'account_holder_name' => $bank_details['accountHoldername'] ?? '',
                        'account_number' => $bank_details['bankAccountNo'] ?? '',
                        'ifsc_code' => $bank_details['IFSCcode'] ?? '',
                        'user_id' => $userId

                    ];

                    DB::table('bank_details')->insert($bankData);

                    $storeData = [
                        'store_name' => $store_info['storeName'] ?? '',
                        'country_id' => $store_info['storecountryID'] ?? '',
                        'state_subdivision_id' => $store_info['storestateID'] ?? '',
                        'city' => $store_info['storeCity'] ?? '',
                        'address1' => $store_info['storeAppartment'] ?? '',
                        'address2' => $store_info['storeAddress'] ?? '',
                        'postal_code' => $store_info['storePostalCode'] ?? '',
                        'user_id' => $userId
                    ];
                    DB::table('store_info')->insert($storeData);

                    return response()->json([
                        'status' => true,
                        'message' => 'Seller Added Successfully'
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Seller Error ..'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Mobile Number Already Registered As Seller ..'
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Email Id Already Registered As Seller ..'
            ]);
        }
    }

    public function register_as_seller_2(Request $request)
    {
        date_default_timezone_set('Asia/Kolkata');


        $data = $request->json()->all();


        $sellerInfo = $data['seller_info'] ?? [];
        $businessInfo = $data['business_info'] ?? [];
        $bankDetails = $data['bank_details'] ?? [];
        $storeInfo = $data['store_info'] ?? [];

        // Fetch the last user ID for unique ID generation
        $lastUserId = DB::table('users')->orderBy('user_id', 'DESC')->value('user_id') ?? 0;

        // Fetch state subdivision details
        $state = DB::table('states')
            ->select('state_subdivision_id', 'state_subdivision_code')
            ->where('state_subdivision_id', $sellerInfo['state'] ?? '')
            ->first();

        if ($state) {
            $uniqueId = $state->state_subdivision_code . 'S' . $state->state_subdivision_id . $lastUserId;
        } else {
            $uniqueId = 'UnknownStateS' . ($lastUserId + 1); // Default or handle missing state
        }

        // Get current date and time
        $currentDateTime = Carbon::now();
        $insertDate = $currentDateTime->toDateString(); // Current date
        $insertTime = $currentDateTime->toTimeString(); // Current time
        // Prepare seller info array
        $sellerData = [
            'name' => $sellerInfo['firstName'] . $sellerInfo['lastName'] ?? '',
            'password' => Hash::make($sellerInfo['password'] ?? ''), // Hash the password
            'email' => $sellerInfo['email'] ?? '',
            'mobile' => $sellerInfo['mobile'] ?? '',
            'unique_id' => $uniqueId,
            'rating' => '0',
            // Add current time
            'inserted_date' => $insertDate,  // Add current date
            'inserted_time' => $insertTime,


        ];

        // Check for existing seller by email
        $existingSellerEmail = DB::table('users')
            ->where('email', $sellerData['email'])
            ->where('role', 'seller')
            ->first();

        if (!$existingSellerEmail) {
            // Check for existing seller by mobile number
            $existingSellerMobile = DB::table('users')
                ->where('mobile', $sellerData['mobile'])
                ->where('role', 'seller')
                ->first();

            if (!$existingSellerMobile) {
                // Insert new seller
                $userId = DB::table('users')->insertGetId(array_merge($sellerData, ['role' => 'seller', 'status' => 'Active']));

                if ($userId) {
                    // Prepare and insert business information
                    $businessData = [
                        'business_name' => $businessInfo['businessName'] ?? '',
                        'registration_number' => $businessInfo['companyRegisterNumber'] ?? '',
                        'city' => $businessInfo['city'] ?? '',
                        'country_id' => $businessInfo['country_id'] ?? '',
                        'state_subdivision_id' => $businessInfo['state'] ?? '',
                        'address1' => $businessInfo['apartment'] ?? '',
                        'address2' => $businessInfo['address'] ?? '',
                        'postal_code' => $businessInfo['postal_code'] ?? '',
                        'user_id' => $userId,
                        'inserted_date' => $insertDate,  // Add current date
                        'inserted_time' => $insertTime,
                    ];
                    DB::table('business_info')->insert($businessData);

                    // Prepare and insert bank details
                    $bankData = [
                        'account_holder_name' => $bankDetails['accountHoldername'] ?? '',
                        'account_number' => $bankDetails['bankAccountNo'] ?? '',
                        'ifsc_code' => $bankDetails['IFSCcode'] ?? '',
                        'user_id' => $userId,
                        'inserted_date' => $insertDate,  // Add current date
                        'inserted_time' => $insertTime,

                    ];

                    DB::table('bank_details')->insert($bankData);

                    // Prepare and insert store information
                    $storeData = [
                        'store_name' => $storeInfo['storeName'] ?? '',
                        'country_id' => $storeInfo['storeCountryID'] ?? '',
                        'state_subdivision_id' => $storeInfo['storeStateID'] ?? '',
                        'city' => $storeInfo['storeCity'] ?? '',
                        'address1' => $storeInfo['storeAppartment'] ?? '',
                        'address2' => $storeInfo['storeAddress'] ?? '',
                        'postal_code' => $storeInfo['storePostalCode'] ?? '',
                        'user_id' => $userId,
                        'inserted_date' => $insertDate,  // Add current date
                        'inserted_time' => $insertTime,
                    ];
                    DB::table('store_info')->insert($storeData);

                    return response()->json([
                        'status' => true,
                        'message' => 'Seller Added Successfully'
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Seller Error ..'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Mobile Number Already Registered As Seller ..'
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Email Id Already Registered As Seller ..'
            ]);
        }
    }



    public function open_otp(Request $request)
    {


        $otp = rand(1000, 9999);

        $mobile = $request->input('mobile');

        $customer = DB::table('customers')
            ->where('mobile', $mobile)
            ->first();


        if (empty($customer)) {

            $otp_data = DB::table('open_otp')
                ->where('mobile', $mobile)
                ->value('otp');

            if (empty($otp_data)) {

                $data = [
                    'mobile' => $mobile,
                    'otp' => $otp
                ];

                DB::table('open_otp')->insert($data);

                $response = [
                    'status' => true,
                    'otp' => $otp,
                    'message' => 'New Customer, OTP sent ...'
                ];

            } else {

                $data = [
                    'otp' => $otp
                ];
                DB::table('open_otp')->where('mobile', $mobile)->update($data);

                $response = [
                    'status' => true,
                    'otp' => $otp,
                    'message' => 'Resending OTP ...'
                ];

            }

        } else {
            $response = [
                'status' => false,
                'message' => 'Customer Already Exists ...'
            ];
        }
        return response()->json($response);

    }




    public function register_as_influencer(Request $request)
    {
        date_default_timezone_set('Asia/Kolkata');

        $data = $request->json()->all();

        $influencerInfo = $data['influencer_info'] ?? [];
        $bankDetails = $data['bank_details'] ?? [];

        $lastUserId = DB::table('users')->orderBy('user_id', 'DESC')->value('user_id') ?? 0;

        $state = DB::table('states')->select('state_subdivision_code', 'state_subdivision_id')
            ->where('state_subdivision_id', $influencerInfo['state_subdivision_id'] ?? '')
            ->first();

        if ($state) {
            $uniqueId = $state->state_subdivision_code . 'F' . $state->state_subdivision_id . $lastUserId;
        } else {
            $uniqueId = 'UnknownStateF' . ($lastUserId + 1);
        }

        $influencerData = [
            'name' => trim(($influencerInfo['firstName'] ?? '') . ' ' . ($influencerInfo['lastName'] ?? '')),
            'password' => $influencerInfo['password'] ?? '',
            'email' => $influencerInfo['email'] ?? '',
            'mobile' => $influencerInfo['mobile'] ?? '',
            'rating' => '0'
        ];

        $existingInfluencerEmail = DB::table('users')->where('email', $influencerData['email'])
            ->where('role', 'influencer')
            ->first();

        if (!$existingInfluencerEmail) {
            $existingInfluencerMobile = DB::table('users')->where('mobile', $influencerData['mobile'])
                ->where('role', 'influencer')
                ->first();

            if (!$existingInfluencerMobile) {
                $userId = DB::table('users')->insertGetId(array_merge($influencerData, ['role' => 'influencer', 'status' => 'Active']));

                if ($userId) {
                    // Prepare and insert bank details
                    $bankData = [
                        'account_holder_name' => $bankDetails['accountHoldername'] ?? '',

                        'account_number' => $bankDetails['bankAccountNo'] ?? '',
                        'ifsc_code' => $bankDetails['IFSCcode'] ?? '',

                    ];
                    DB::table('bank_details')->insert($bankData);

                    return response()->json([
                        'status' => true,
                        'message' => 'Influencer Added Successfully'
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Error Adding Influencer'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Mobile Number Already Registered As Influencer'
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Email Id Already Registered As Influencer'
            ]);
        }
    }

    public function updateProfile(Request $request)
    {
        $data = $request->json()->all();

        $userInfo = $data['user_info'] ?? [];


        $user = DB::table('users')->where('user_id', $userInfo['user_id'])->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Prepare user update data
        $updateData = [
            // 'name' => ($userInfo['firstName']) . ' ' . ($userInfo['lastName']) ?? $user->name,
            // 'gender' => $userInfo['gender'] ?? $user->gender,
            // 'email' => $userInfo['email'] ?? $user->email,
            // 'mobile' => $userInfo['mobile'] ?? $user->mobile,
            // 'address' => $userInfo['address'] ?? $user->address,
            // 'country' => $userInfo['country'] ?? $user->country,
            // 'state' => $userInfo['state'] ?? $user->state,
            // 'city' => $userInfo['city'] ?? $user->city,
            // 'locality' => $userInfo['locality'] ?? $user->locality,
            'password' => $userInfo['password'] ?? $user->password
        ];


        DB::table('users')->where('user_id', $userInfo['user_id'])->update(['password' => Hash::make($updateData['password'])]);

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully'
        ]);
    }

    public function mobile_otp_user(Request $request)
    {
        if ($request->user_id) {

            $finduser = DB::table('users')
                ->where('role', $request->input('role'))
                ->where('user_id', $request->input('user_id'))
                ->where('status', 'Active');

            if ($request->filled('email')) {
                $finduser->where('email', $request->input('email'));
            } elseif ($request->filled('mobile')) {
                $finduser->where('mobile', $request->input('mobile'));
            }

            $user = $finduser->first();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => "User not found"
                ]);
            }
            $otp = rand(1000, 9999);

            $query = DB::table('users')
                ->where('user_id', $request->input('user_id'))->where('status', 'Active')->where('role', $request->input('role'));

            if ($request->filled('email')) {
                $updateSuccess = $query->update(['email_otp' => $otp]);
            } elseif ($request->filled('mobile')) {
                $updateSuccess = $query->update(['mobile_otp' => $otp]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => "Neither email nor phone provided"
                ]);
            }

            return response()->json([
                'status' => true,
                'otp' => $otp,
                'user_id' => $user->user_id,
                'message' => $request->role . ' ' . 'Found ...'
            ]);

        } else {
            return response()->json([
                'status' => false,
                'message' => "User not found"
            ]);
        }


    }

    public function reset_user_password(Request $request)
    {
        if ($request->user_id) {

            $finduser = DB::table('users')
                ->where('role', $request->input('role'))
                ->where('user_id', $request->input('user_id'))
                ->where('status', 'Active');

            if ($request->filled('email_otp')) {
                $finduser->where('email_otp', $request->input('email_otp'));
            } elseif ($request->filled('mobile_otp')) {
                $finduser->where('mobile_otp', $request->input('mobile_otp'));
            }

            $user = $finduser->first();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => "User not found"
                ]);
            }

            $updatepass = DB::table('users')
                ->where('user_id', $request->user_id)->where('status', 'Active')->where('role', $request->input('role'))
                ->update([
                    'verify_mobile' => 'Yes',
                    'password' => Hash::make($request->getPassword()) // Hash the password
                ]);

            return response()->json([
                'status' => true,
                'user_id' => $user->user_id,
                'message' => 'Password Update Successfully'
            ]);

        } else {
            return response()->json([
                'status' => false,
                'message' => "User not found"
            ]);
        }
    }

    public function user_login(Request $request)
    {
        // Validate incoming request data
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $validated['email'];
        $password = $validated['password'];

        $user = DB::table('users')
            // ->join('countries', 'users.country', '=', 'countries.country_id')
            // ->join('states', 'users.state', '=', 'states.state_subdivision_id')
            ->where('users.status', 'Active')
            ->where('email', $email)
            ->first();


        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Email ...',
            ]);
        }

        // Verify the password
        if (!Hash::check($password, $user->password)) {
            // If password does not match
            return response()->json([
                'status' => false,
                'message' => 'Invalid Password ...',
            ]);
        }

        // Prepare success response
        return response()->json([
            'status' => true,
            'user_id' => $user->user_id,
            'role' => $user->role,
            // 'profile_photo' => 'https://alas.genixbit.com/public/images/user/' . $user->image,
            'message' => 'User Found ...',
            'user_data' => $user
        ]);
    }


    public function password_verify_otp(Request $request)
    {

        if ($request->type === 'user') {

            $finduser = DB::table('users')
                ->where('email', $request->input('email'))
                ->where('status', 'Active');

            if ($request->filled('email_otp')) {
                $finduser->where('email_otp', $request->input('email_otp'));
            } elseif ($request->filled('mobile_otp')) {
                $finduser->where('mobile_otp', $request->input('mobile_otp'));
            }

            $user = $finduser->first();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => "User not found"
                ]);
            }


            return response()->json([
                'status' => true,
                'message' => "User "
            ]);
        } else if ($request->type === 'customer') {
            return response()->json([
                'status' => true,
                'message' => "customer"
            ]);
        }
    }



}
