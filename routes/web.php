<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Http\Request;
use App\Services\Identity;
use App\Customer;
use App\Conversation;
use App\Transaction;

Route::get('/', function () {
    return view('welcome');
});


function make_response($message, $next = null){
	$data = [
		[
      		'action' => 'talk',
      		'voiceName' => 'Jennifer',
    		'text' => "$message"
    	],
	];
	if( !is_null($data) ){
		$data[] = $next;
	}
	return response()->json($data);
}


Route::get('/answer', function (Request $request)
{
	// Verify the number
	$customer = Customer::where('number', $request->from)->first();

	if( is_null($customer) )
		return make_response('Number not registered. Please call with registered number.');

	$conversation_id = $request->conversation_uuid;
	$conversation = new Conversation;

	$conversation->customer_id = $customer->id;
	$conversation->conversation_id = $conversation_id;
	$conversation->last_input = 0;

	$conversation->save();

	$ncco = [
            	"action" => "input",
            	"submitOnHash" => "true",
            	"eventUrl" => [config('app.url') . '/auth'],
            	"timeOut" => "15",
            	"bargeIn" => true
            ];

  	return make_response("Welcome to mPay. Please type your t pin", $ncco);
});



// Auth Route
Route::post('/auth', function (Request $request)
{
	$identity = new Identity($request->from);
	$identity->authenticate($request->dtmf);
	if( !$identity->auth ){
		$ncco =  [
            "action" => "input",
            "submitOnHash" => "true",
            "eventUrl" => [config('app.url') . '/auth'],
            "timeOut" => "15",
            "bargeIn" => true
        ];
		return make_response("Invalid t pin. Please try again.", $ncco);
	}

	$ncco = 
    	[
            "action" => "input",
            "submitOnHash" => "true",
            "timeOut" => "10",
            "eventUrl" => [config('app.url') . '/menu']
        ];
  	return make_response("Thanks for the authentication, Press 1 to Transfer Money, Press 2 to check balance, Press 3 for", $ncco );
});

Route::post('/menu', function(Request $request){
	// TODO: Check if authorized
	$identity = new Identity($request->from);
	$conversation_id = $request->conversation_uuid;
	$conversation = Conversation::where('conversation_id', $conversation_id)->orderby('id', 'desc')
								->first();

	$ncco = 
	[
        "action" => "input",
        "submitOnHash" => "true",
        "timeOut" => "10",
        "eventUrl" => [config('app.url') . '/menu']
    ];

	$dtmf = $request->dtmf;
	if( $dtmf > '5' || $dtmf < '1'){
		$ncco = 
    	[
            "action" => "input",
            "submitOnHash" => "true",
            "timeOut" => "10",
            "eventUrl" => [config('app.url') . '/menu']
        ];
		return make_response("Invalid Choice, Please try again", $ncco);
	}
	switch($dtmf){
		case '1': 
			$text = "Enter the amonut to transfer";
		break;
		case '2':
			$text = " You Balance is ". $conversation->customer->balance;
		break;

	}

	return make_response($text, $ncco);

});

Route::post('/log', function(Request $request){
	//log
	$conversation_id = $request->conversation_uuid;
	$conversation = Conversation::where('conversation_id', $conversation_id)->orderby('id', 'desc')
								->first();
	$customer_id = $conversation->customer->id;
	$uuid = $request->uuid;
	$number = $conversation->customer->number;
	$direction = $request->direction;
	$status = $request->status;
	$raw_data = json_encode($request->all());

	return 1;

});

