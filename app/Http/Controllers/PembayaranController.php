<?php

namespace App\Http\Controllers;
use App\Model\Pembayaran;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Veritrans\Midtrans;

class PembayaranController extends Controller
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
        $data['pembayaran'] = Pembayaran::orderBy('id', 'desc')->paginate(8);

        return view('welcome', $data);
    }

    /**
     * Submit donation.
     *
     * @return array
     */
    public function submitPembayaran()
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
            $pembayaran = Pembayaran::create([
                'bayar_name' => $this->request->bayar_name,
                'bayar_email' => $this->request->bayar_email,
                'bayar_type' => $this->request->bayar_type,
                'amount' => floatval($this->request->amount),
                'note' => $this->request->note,
            ]);

            // Buat transaksi ke midtrans kemudian save snap tokennya.
            $payload = [
                'transaction_details' => [
                    'order_id'      => $bayar->id,
                    'gross_amount'  => $bayar->amount,
                ],
                'customer_details' => [
                    'first_name'    => $pembayaran->bayar_name,
                    'email'         => $pembayaran->bayar_email,
                    // 'phone'         => '08888888888',
                    // 'address'       => '',
                ],
                'item_details' => [
                    [
                        'id'       => $pembayaran->bayar_type,
                        'price'    => $bayar->amount,
                        'quantity' => 1,
                        'name'     => ucwords(str_replace('_', ' ', $pembayaran->bayar_type))
                    ]
                ]
            ];
            $snapToken = Midtrans::getSnapToken($payload);
            $donation->snap_token = $snapToken;
            $donation->save();

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
          $donation = Pembayaran::findOrFail($orderId);

          if ($transaction == 'capture') {

            // For credit card transaction, we need to check whether transaction is challenge by FDS or not
            if ($type == 'credit_card') {

              if($fraud == 'challenge') {
                // TODO set payment status in merchant's database to 'Challenge by FDS'
                // TODO merchant should decide whether this transaction is authorized or not in MAP
                // $donation->addUpdate("Transaction order_id: " . $orderId ." is challenged by FDS");
                $donation->setPending();
              } else {
                // TODO set payment status in merchant's database to 'Success'
                // $donation->addUpdate("Transaction order_id: " . $orderId ." successfully captured using " . $type);
                $donation->setSuccess();
              }

            }

          } elseif ($transaction == 'settlement') {

            // TODO set payment status in merchant's database to 'Settlement'
            // $donation->addUpdate("Transaction order_id: " . $orderId ." successfully transfered using " . $type);
            $donation->setSuccess();

          } elseif($transaction == 'pending'){

            // TODO set payment status in merchant's database to 'Pending'
            // $donation->addUpdate("Waiting customer to finish transaction order_id: " . $orderId . " using " . $type);
            $donation->setPending();

          } elseif ($transaction == 'deny') {

            // TODO set payment status in merchant's database to 'Failed'
            // $donation->addUpdate("Payment using " . $type . " for transaction order_id: " . $orderId . " is Failed.");
            $donation->setFailed();

          } elseif ($transaction == 'expire') {

            // TODO set payment status in merchant's database to 'expire'
            // $donation->addUpdate("Payment using " . $type . " for transaction order_id: " . $orderId . " is expired.");
            $donation->setExpired();

          } elseif ($transaction == 'cancel') {

            // TODO set payment status in merchant's database to 'Failed'
            // $donation->addUpdate("Payment using " . $type . " for transaction order_id: " . $orderId . " is canceled.");
            $donation->setFailed();

          }

        });

        return;
    }
}
