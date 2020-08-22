@extends('Email::layout')
@section('content')

    <div class="b-container">
        <div class="b-panel">
            @switch($to)
                @case ('admin')
                    <h3 class="email-headline"><strong>{{__('Hello Administrator')}}</strong></h3>
                    <p>{{__('New booking has been made')}}</p>
                   <p>{{__(' Transfer Antar Bank Lakukan pembayaran Anda langsung ke rekening bank BNI an PT APPULAU INDONESIA Norek 3129012921 . Silakan gunakan ID Pesanan Anda sebagai referensi pembayaran. Pesanan Anda tidak akan dikirim sampai dana sudah diterima di akun kami. silahkan konfirmasi pembayaran di akun resmi whatsapp kami 089531898161')}}</p>
                @break
                @case ('vendor')
                    <h3 class="email-headline"><strong>{{__('Hello :name',['name'=>$booking->vendor->nameOrEmail ?? ''])}}</strong></h3>
                    <p>{{__('Your service has new booking')}}</p>
                @break

                @case ('customer')
                    <h3 class="email-headline"><strong>{{__('Hello :name',['name'=>$booking->first_name ?? ''])}}</strong></h3>
                    <p>{{__('Thank you for booking with us. Here are your booking information:')}}</p>
                @break

            @endswitch

            @include($service->email_new_booking_file ?? '')
        </div>
        @include('Booking::emails.parts.panel-customer')
    </div>
@endsection
