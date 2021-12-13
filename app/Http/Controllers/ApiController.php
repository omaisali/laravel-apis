<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Validator;
use Pusher\Pusher;
use App\Models\Bid;

class ApiController extends Controller
{
    public function fetchAllOffers(Request $request){

        $rules=array(
            'lat' => 'required',
            'lng' => 'required'
        );
        $messages=array(
        'lat.required' => 'latitude parameter missing',
        'lng.required' => 'longitude parameter missing'
        );
        $validator=Validator::make($request->all(),$rules,$messages);
        if($validator->fails())
        {
            $messages=$validator->messages();
            $errors=$messages->all();
            return response()->json($errors, 404);
        }

        $lat = $request->lat;
        $lng = $request->lng;
        
        $foods =DB::table('foods')->get()->toArray();

        $restaurants = DB::table("restaurants")
            ->select("restaurants.id as restaurant_id", "restaurants.name as restaurant_name", "restaurants.active as status"
                ,DB::raw("6371 * acos(cos(radians(" . $lat . ")) 
                * cos(radians(restaurants.latitude)) 
                * cos(radians(restaurants.longitude) - radians(" . $lng . ")) 
                + sin(radians(" .$lat. ")) 
                * sin(radians(restaurants.latitude))) AS distance"))
                ->groupBy("restaurants.id", "distance")
                ->orderBy("distance")
                ->join('foods', 'foods.restaurant_id', '=', 'restaurants.id')
                ->get();

                
                $foods = DB::table('foods');
                
                foreach ($restaurants as $restaurant) {       
                    $foods->select('id as offer_id', 'price as first_price', 'discount_price as offer_price', 'restaurant_id as vendor_id');
                    $foods->Orwhere('foods.restaurant_id', $restaurant->restaurant_id);                 
                    
                }
                $foods = $foods->get();
        
                foreach($foods as $food){
                    foreach($restaurants as $restaurant){
                        if($food->vendor_id == $restaurant->restaurant_id){
                            $food->vendor_name = $restaurant->restaurant_name;
                            $restaurant->distance = round($restaurant->distance * 1.60934, 2);
                            $food->total_distance = $restaurant->distance ." km";
                            $food->total_time = round(3.8 * ($restaurant->distance), 0) .' min';
                            $food->status = $restaurant->status;
                        }
                    }
                }

        return response()->json($foods, 200);
    }

    public function fetchOffers(Request $request){

        $rules=array(
            'lat' => 'required',
            'lng' => 'required',
            'radius' => 'required'
        );
        $messages=array(
        'lat.required' => 'latitude parameter missing',
        'lng.required' => 'longitude parameter missing',
        'radius.required' => 'radius parameter missing'
        );
        $validator=Validator::make($request->all(),$rules,$messages);
        if($validator->fails())
        {
            $messages=$validator->messages();
            $errors=$messages->all();
            return response()->json($errors, 404);
        }

        $lat = $request->lat;
        $lng = $request->lng;
        $radius = $request->radius;
       

        $restaurants = DB::table("restaurants")
            ->select("restaurants.id as restaurant_id", "restaurants.name as restaurant_name", "restaurants.active as status"
                ,DB::raw("6371 * acos(cos(radians(" . $lat . ")) 
                * cos(radians(restaurants.latitude)) 
                * cos(radians(restaurants.longitude) - radians(" . $lng . ")) 
                + sin(radians(" .$lat. ")) 
                * sin(radians(restaurants.latitude))) AS distance"))
                ->groupBy("restaurants.id", "distance")
                ->orderBy("distance")
                ->join('foods', 'foods.restaurant_id', '=', 'restaurants.id')
                ->get();

                
                $foods = DB::table('foods');
                
                foreach ($restaurants as $restaurant) {       
                    $foods->select('id as offer_id', 'price as first_price', 'discount_price as offer_price', 'restaurant_id as vendor_id');
                    $foods->Orwhere('foods.restaurant_id', $restaurant->restaurant_id);                 
                    
                }
                $foods = $foods->get();
                $filtered_offers;
                foreach($foods as $food){
                    foreach($restaurants as $restaurant){
                        if($food->vendor_id == $restaurant->restaurant_id && round($restaurant->distance * 1.60934, 2) <= $request->radius ){                            
                            $food->vendor_name = $restaurant->restaurant_name;
                            $restaurant->distance = round($restaurant->distance * 1.60934, 2);
                            $food->total_distance = $restaurant->distance ." km";
                            $food->total_time = round(3.8 * ($restaurant->distance), 0) .' min';
                            $food->status = $restaurant->status;
                            $filtered_offers = $food;        
                        }else{
                           
                        }
                    }
                    
                }

                
                foreach($foods as $key=>$food){
                    if(!property_exists($food, "vendor_name"))
                    $foods->pull($key); 
                }

                $options = array(
                    'cluster' => env('PUSHER_APP_CLUSTER'),
                    'encrypted' => true
                    );
                $pusher = new Pusher(
                    env('PUSHER_APP_KEY'),
                    env('PUSHER_APP_SECRET'),
                    env('PUSHER_APP_ID'), 
                    $options
                    );
                    
                $data['message'] = 'Offers sent';
                $pusher->trigger('notify-channel', 'App\\Events\\Notify', $data);
                    
                       

        return response()->json($foods, 200);
    }

    public function acceptBid(Request $request){

        $rules=array(
            'status' => 'required',
            'vendor_id' => 'required',
            'offer_id' => 'required'
        );

        $messages=array(
            'status.required' => 'status parameter missing',
            'vendor_id.required' => 'vendor_id parameter missing',
            'offer_id.required' => 'offer_id parameter missing'
            );
            $validator=Validator::make($request->all(),$rules,$messages);
            if($validator->fails())
            {
                $messages=$validator->messages();
                $errors=$messages->all();
                return response()->json($errors, 404);
            }
    
            $bids = new Bid();
            $bids->offer_id = $request->offer_id;
            $bids->vendor_id = $request->vendor_id;
            $bids->status = $request->status;
            $bids->save();

            return response()->json($request->status, 200);
    }

    public function offerRate(Request $request){

        $rules=array(
            'offer_id' => 'required',
            'offer_rate' => 'required'
        );

        $messages=array(
            'offer_id.required' => 'offer_id parameter missing',
            'offer_rate.required' => 'offer_rate parameter missing'
            );
            $validator=Validator::make($request->all(),$rules,$messages);
            if($validator->fails())
            {
                $messages=$validator->messages();
                $errors=$messages->all();
                return response()->json($errors, 404);
            }

            $foods =DB::table('foods')->select("foods.id as offer_id", "foods.price as old_rate", "foods.discount_price as discount_rate", "foods.restaurant_id as vendor_id");
            $foods = $foods->addSelect(DB::raw("'$request->offer_rate' as offer_rate"));
            $foods = $foods->where("id", $request->offer_id)->get();
    
            return response()->json($foods, 200);
    }


}
