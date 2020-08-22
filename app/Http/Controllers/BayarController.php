<?php

namespace App\Http\Controllers;
use App\Model\Bayar;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Veritrans\Midtrans;

class BayarController extends Controller
{
    /**
     * Make request global.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * Class constructor.
     *
     * @param \Illuminate\Http\Request $request User Request
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;

        // Set midtrans configuration
        Midtrans::$serverKey = 'SB-Mid-server-ERRFNLE4-BiSuuOVHEDFSzAL';
        Midtrans::$isProduction = config('services.midtrans.isProduction');
        // Midtrans::$isSanitized = config('services.midtrans.isSanitized');
        // Midtrans::$is3ds = config('services.midtrans.is3ds');
    }

    /**
     * Show index page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $data['pembayaran'] = Bayar::orderBy('id', 'desc')->paginate(8);

        return view('welcome', $data);
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
                    'phone'         => '08888888888',
                    'address'       => 'Bambu Apus',
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
