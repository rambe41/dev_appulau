<?php
namespace Modules\Booking\Controllers;
use App\Model\Bayar;
use Illuminate\Http\Request;
use App\Http\Requests;
// use App\Http\Controllers\Controller;

use App\Veritrans\Midtrans;
use DebugBar\DebugBar;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Mockery\Exception;
//use Modules\Booking\Events\VendorLogPayment;
use Modules\Tour\Models\TourDate;
use Validator;
// use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Booking\Models\Booking;
use App\Helpers\ReCaptchaEngine;

class BookingController extends \App\Http\Controllers\Controller
{
    use AuthorizesRequests;
    protected $booking;
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->booking = Booking::class;
        Midtrans::$serverKey = 'SB-Mid-server-ERRFNLE4-BiSuuOVHEDFSzAL';
        Midtrans::$isProduction = config('services.midtrans.isProduction');
    }

    public function checkout($code)
    {

        $booking = $this->booking::where('code', $code)->first();

        if (empty($booking)) {
            abort(404);
        }
        if ($booking->customer_id != Auth::id()) {
            abort(404);
        }

        if($booking->status != 'draft'){
            return redirect('/');
        }
        $data = [
            'page_title' => __('Checkout'),
            'booking'    => $booking,
            'service'    => $booking->service,
            'gateways'   => $this->getGateways(),
            'user'       => Auth::user()
        ];
        $data['pembayaran'] = Bayar::orderBy('id', 'desc')->paginate(8);
        // $data['pembayaran'] = Bayar::where('bayar_email', $booking->email)->paginate(8);
        return view('Booking::frontend/checkout', $data);
    }

    public function checkStatusCheckout($code)
    {
        $booking = $this->booking::where('code', $code)->first();
        $data = [
            'error'    => false,
            'message'  => '',
            'redirect' => ''
        ];
        if (empty($booking)) {
            $data = [
                'error'    => true,
                'redirect' => url('/')
            ];
        }
        if ($booking->customer_id != Auth::id()) {
            $data = [
                'error'    => true,
                'redirect' => url('/')
            ];
        }
        if ($booking->status != 'draft') {
            $data = [
                'error'    => true,
                'redirect' => url('/')
            ];
        }
        return response()->json($data, 200);
    }

    public function doCheckout(Request $request)
    {

        /**
         * @param Booking $booking
         */
        $validator = Validator::make($request->all(), [
            'code' => 'required',
        ]);
        if ($validator->fails()) {
            $this->sendError('', ['errors' => $validator->errors()]);
        }
        $code = $request->input('code');
        $booking = $this->booking::where('code', $code)->first();
        if (empty($booking)) {
            abort(404);
        }
        if ($booking->customer_id != Auth::id()) {
            abort(404);
        }
        if ($booking->status != 'draft') {
            return $this->sendError('',[
                'url'=>$booking->getDetailUrl()
            ]);
        }
        $service = $booking->service;
        if (empty($service)) {
            $this->sendError(__("Service not found"));
        }
        /**
         * Google ReCapcha
         */
        if(ReCaptchaEngine::isEnable() and setting_item("booking_enable_recaptcha")){
            $codeCapcha = $request->input('g-recaptcha-response');
            if(!$codeCapcha or !ReCaptchaEngine::verify($codeCapcha)){
                $this->sendError(__("Please verify the captcha"));
            }
        }
        $rules = [
            'first_name'      => 'required|string|max:255',
            'last_name'       => 'required|string|max:255',
            'email'           => 'required|string|email|max:255',
            'phone'           => 'required|string|max:255',
            'country' => 'required',
            'payment_gateway' => 'required',
            'term_conditions' => 'required'
        ];
        $rules = $service->filterCheckoutValidate($request, $rules);
        if (!empty($rules)) {
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $this->sendError('', ['errors' => $validator->errors()]);
            }
        }
        if (!empty($rules['payment_gateway'])) {
            $payment_gateway = $request->input('payment_gateway');
            $gateways = get_payment_gateways();
            if (empty($gateways[$payment_gateway]) or !class_exists($gateways[$payment_gateway])) {
                $this->sendError(__("Payment gateway not found"));
            }
            $gatewayObj = new $gateways[$payment_gateway]($payment_gateway);
            if (!$gatewayObj->isAvailable()) {
                $this->sendError(__("Payment gateway is not available"));
            }
        }
        $service->beforeCheckout($request, $booking);
        // Normal Checkout
        $booking->first_name = $request->input('first_name');
        $booking->last_name = $request->input('last_name');
        $booking->email = $request->input('email');
        $booking->phone = $request->input('phone');
        $booking->address = $request->input('address_line_1');
        $booking->address2 = $request->input('address_line_2');
        $booking->city = $request->input('city');
        $booking->state = $request->input('state');
        $booking->zip_code = $request->input('zip_code');
        $booking->country = $request->input('country');
        $booking->customer_notes = $request->input('customer_notes');
        $booking->gateway = $payment_gateway;
        $booking->save();

//        event(new VendorLogPayment($booking));

        $user = Auth::user();
        $user->first_name = $request->input('first_name');
        $user->last_name = $request->input('last_name');
        $user->phone = $request->input('phone');
        $user->address = $request->input('address_line_1');
        $user->address2 = $request->input('address_line_2');
        $user->city = $request->input('city');
        $user->state = $request->input('state');
        $user->zip_code = $request->input('zip_code');
        $user->country = $request->input('country');
        $user->save();

        $booking->addMeta('locale',app()->getLocale());

        $service->afterCheckout($request, $booking);
        try {

            $gatewayObj->process($request, $booking, $service);
        } catch (Exception $exception) {
            $this->sendError($exception->getMessage());
        }
    }

    public function confirmPayment(Request $request, $gateway)
    {

        $gateways = get_payment_gateways();
        if (empty($gateways[$gateway]) or !class_exists($gateways[$gateway])) {
            $this->sendError(__("Payment gateway not found"));
        }
        $gatewayObj = new $gateways[$gateway]($gateway);
        if (!$gatewayObj->isAvailable()) {
            $this->sendError(__("Payment gateway is not available"));
        }
        return $gatewayObj->confirmPayment($request);
    }

    public function cancelPayment(Request $request, $gateway)
    {

        $gateways = get_payment_gateways();
        if (empty($gateways[$gateway]) or !class_exists($gateways[$gateway])) {
            $this->sendError(__("Payment gateway not found"));
        }
        $gatewayObj = new $gateways[$gateway]($gateway);
        if (!$gatewayObj->isAvailable()) {
            $this->sendError(__("Payment gateway is not available"));
        }
        return $gatewayObj->cancelPayment($request);
    }

    /**
     * @todo Handle Add To Cart Validate
     *
     * @param Request $request
     * @return string json
     */
    public function addToCart(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'service_id'   => 'required|integer',
            'service_type' => 'required'
        ]);
        if ($validator->fails()) {
            $this->sendError('', ['errors' => $validator->errors()]);
        }
        $service_type = $request->input('service_type');
        $service_id = $request->input('service_id');
        $allServices = get_bookable_services();
        if (empty($allServices[$service_type])) {
            $this->sendError(__('Service type not found'));
        }
        $module = $allServices[$service_type];
        $service = $module::find($service_id);
        if (empty($service) or !is_subclass_of($service, '\\Modules\\Booking\\Models\\Bookable')) {
            $this->sendError(__('Service not found'));
        }
        if (!$service->isBookable()) {
            $this->sendError(__('Service is not bookable'));
        }
        //        try{
        $service->addToCart($request);
        //
        //        }catch(\Exception $ex){
        //            $this->sendError($ex->getMessage(),['code'=>$ex->getCode()]);
        //        }
    }

    protected function getGateways()
    {

        $all = get_payment_gateways();
        $res = [];
        foreach ($all as $k => $item) {
            if (class_exists($item)) {
                $obj = new $item($k);
                if ($obj->isAvailable()) {
                    $res[$k] = $obj;
                }
            }
        }
        return $res;
    }

    public function detail(Request $request, $code)
    {

        $booking = Booking::where('code', $code)->first();
        if (empty($booking)) {
            abort(404);
        }

        if ($booking->status == 'draft') {
            return redirect($booking->getCheckoutUrl());
        }
        if ($booking->customer_id != Auth::id()) {
            abort(404);
        }
        $data = [
            'page_title' => __('Booking Details'),
            'booking'    => $booking,
            'service'    => $booking->service,
        ];
        if ($booking->gateway) {
            $data['gateway'] = get_payment_gateway_obj($booking->gateway);
        }
        return view('Booking::frontend/detail', $data);
    }
	public function exportIcal($service_type = 'tour', $id)
	{
		\Debugbar::disable();
		$allServices = get_bookable_services();
		if (empty($allServices[$service_type])) {
			$this->sendError(__('Service type not found'));
		}
		$module = $allServices[$service_type];

		$path ='/ical/';
		$fileName = 'booking_' . $service_type . '_' . $id . '.ics';
		$fullPath = $path.$fileName;

		$content  = $this->booking::getContentCalendarIcal($service_type,$id,$module);
		Storage::disk('uploads')->put($fullPath, $content);
		$file = Storage::disk('uploads')->get($fullPath);

		header('Content-Type: text/calendar; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $fileName . '"');

		echo $file;
    }
    /**
     *
     * @return array
     */
    public function submitBayar()
    {
        $validator = \Validator::make(request()->all(), [
            'bayar_name'  => 'required',
            'bayar_email' => 'required|email',
            'amount'      => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return [
              'status'  => 'error',
              'message' => $validator->errors()->first()
            ];
        }

        \DB::transaction(function(){
            // Save donasi ke database
            $data = Bayar::create([
                'bayar_name' => $this->request->bayar_name,
                'bayar_email' => $this->request->bayar_email,
                'bayar_type' => $this->request->bayar_type,
                'amount' => floatval($this->request->amount),
                'note' => $this->request->note,
            ]);

            // Buat transaksi ke midtrans kemudian save snap tokennya.
            $payload = [
                'transaction_details' => [
                    'order_id'      => $data->id,
                    'gross_amount'  => $data->amount,
                ],
                'customer_details' => [
                    'first_name'    => $data->bayar_name,
                    'email'         => $data->bayar_email,
                    // 'phone'         => '08888888888',
                    // 'address'       => '',
                ],
                'item_details' => [
                    [
                        'id'       => $data->bayar_type,
                        'price'    => $data->amount,
                        'quantity' => 1,
                        'name'     => ucwords(str_replace('_', ' ', $data->bayar_type))
                    ]
                ]
            ];
            $snapToken = Midtrans::getSnapToken($payload);
            $data->snap_token = $snapToken;
            $data->save();

            // Beri response snap token
            $this->response['snap_token'] = $snapToken;
        });

        return response()->json($this->response);
    }

    /**
     * Midtrans notification handler.
     *
     * @param Request $request
     * 
     * @return void
     */
    public function notificationHandler(Request $request)
    {
        $notif = new Veritrans_Notification();
        \DB::transaction(function() use($notif) {

          $transaction = $notif->transaction_status;
          $type = $notif->payment_type;
          $orderId = $notif->order_id;
          $fraud = $notif->fraud_status;
          $data = Bayar::findOrFail($orderId);

          if ($transaction == 'capture') {

            // For credit card transaction, we need to check whether transaction is challenge by FDS or not
            if ($type == 'credit_card') {

              if($fraud == 'challenge') {
                // TODO set payment status in merchant's database to 'Challenge by FDS'
                // TODO merchant should decide whether this transaction is authorized or not in MAP
                // $data->addUpdate("Transaction order_id: " . $orderId ." is challenged by FDS");
                $data->setPending();
              } else {
                // TODO set payment status in merchant's database to 'Success'
                // $data->addUpdate("Transaction order_id: " . $orderId ." successfully captured using " . $type);
                $data->setSuccess();
              }

            }

          } elseif ($transaction == 'settlement') {

            // TODO set payment status in merchant's database to 'Settlement'
            // $data->addUpdate("Transaction order_id: " . $orderId ." successfully transfered using " . $type);
            $data->setSuccess();

          } elseif($transaction == 'pending'){

            // TODO set payment status in merchant's database to 'Pending'
            // $data->addUpdate("Waiting customer to finish transaction order_id: " . $orderId . " using " . $type);
            $data->setPending();

          } elseif ($transaction == 'deny') {

            // TODO set payment status in merchant's database to 'Failed'
            // $data->addUpdate("Payment using " . $type . " for transaction order_id: " . $orderId . " is Failed.");
            $data->setFailed();

          } elseif ($transaction == 'expire') {

            // TODO set payment status in merchant's database to 'expire'
            // $data->addUpdate("Payment using " . $type . " for transaction order_id: " . $orderId . " is expired.");
            $data->setExpired();

          } elseif ($transaction == 'cancel') {

            // TODO set payment status in merchant's database to 'Failed'
            // $data->addUpdate("Payment using " . $type . " for transaction order_id: " . $orderId . " is canceled.");
            $data->setFailed();

          }

        });

        return;
    }


}
