@php $lang_local = app()->getLocale() @endphp
@extends('layouts.app')
@section('head')
    <link href="{{ asset('module/booking/css/checkout.css?_ver='.config('app.version')) }}" rel="stylesheet">
@endsection
@section('content')
    <div class="bravo-booking-page padding-content" >
        <div class="container">
            <div id="bravo-checkout-page" >
                <div class="row">
                    <div class="col-md-8">
                        <h3 class="form-title">{{__('Booking Submission')}}</h3>
                         <div class="booking-form">
                                 <div class="container">

        <form class="form-horizontal" id="bayar" onsubmit="return submitForm();">

            <!-- Form Name -->

            <div class="row">
                <div class="col-md-4">

                    <!-- Text input-->
                    <div class="form-group">
                        <label class="control-label" for="bayar_name">Nama</label>
                        <div>
                            <input id="bayar_name" name="bayar_name" type="text" placeholder="Enter your name.." value="{{$user->first_name ?? ''}}" class="form-control input-md"
                                required="">

                        </div>
                    </div>

                </div>

                <div class="col-md-4">

                    <!-- Text input-->
                    <div class="form-group">
                        <label class="control-label" for="bayar_email">Email</label>
                        <div>
                            <input id="bayar_email" name="bayar_email" type="text" placeholder="Enter your email.." class="form-control input-md" value="{{$user->email ?? ''}}"
                                required="">
    
                        </div>
                    </div>
    
                </div>

                <div class="col-md-4">
                    @php
                        $service_translation = $service->translateOrOrigin($lang_local);
                    @endphp

                    <!-- Select Basic -->
                    <div class="form-group">
                        <label class="control-label" for="bayar_type">Type</label>
                        <div>
                            <input id="bayar_type" name="bayar_type" type="text" placeholder="Enter your type" class="form-control input-md" value="{{$service_translation->title}}"
                                required="">
                        </div>
                    </div>

                </div>
            </div>

            <div class="row">
                <div class="col-md-6">

                    <!-- Prepended text-->
                    <label for="">Amount</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="basic-addon1">Rp</span>
                        </div>
                        <input id="amount" name="amount" class="form-control" placeholder="" type="number" min="10000" max="999999999" value="{{$booking->total}}" required="" readonly>
                    </div>

                </div>
                <div class="col-md-6">

                    <!-- Textarea -->
                    <div class="form-group">
                        <label class="control-label" for="note">Note (Optional)</label>
                        <div>
                            <textarea class="form-control" id="note" name="note"></textarea>
                        </div>
                    </div>

                </div>
            </div>

            <button id="submit" class="btn btn-success">Submit</button>

        </form>

        <br>

        {{-- <table class="table table-striped" id="list">
            <tr>
                <th>ID</th>
                <th>Nama</th>
                <th>Amount</th>
                <th>Type</th>
                <th>Status</th>
                <th style="text-align: center;">Pay</th>
            </tr>
            @foreach ($pembayaran as $bayar)
            <tr>
                <td><code>{{ $bayar->id }}</code></td>
                <td>{{ $bayar->bayar_name }}</td>
                <td>Rp. {{ number_format($bayar->amount) }},-</td>
                <td>{{ ucwords(str_replace('_', ' ', $bayar->bayar_type)) }}</td>
                <td>{{ ucfirst($bayar->status) }}</td>
                <td style="text-align: center;">
                    @if ($bayar->status == 'pending')
                    <button class="btn btn-success btn-sm" onclick="snap.pay('{{ $bayar->snap_token }}')">Complete Payment</button>
                    @endif
                </td>
            </tr>
            @endforeach
            <tr>
                <td colspan="6">{{ $pembayaran->links() }}</td>
            </tr>
        </table> --}}

    </div>
    <script
        src="https://code.jquery.com/jquery-3.3.1.min.js"
        integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
        crossorigin="anonymous"></script>
    <script src="{{ !config('services.midtrans.isProduction') ? 'https://app.sandbox.midtrans.com/snap/snap.js' : 'https://app.midtrans.com/snap/snap.js' }}" data-client-key="SB-Mid-client-yNNbRxaPM-DKEzbq"></script>
    <script>
    function submitForm() {
        $.post("{{ route('bayar.store') }}",
        {
            _method: 'POST',
            _token: '{{ csrf_token() }}',
            amount: $('input#amount').val(),
            note: $('textarea#note').val(),
            bayar_type: $('input#bayar_type').val(),
            bayar_name: $('input#bayar_name').val(),
            bayar_email: $('input#bayar_email').val(),
        },
        function (data, status) {
            if (data.status == 'error') {
                alert(data.message);
            } else {
                snap.pay(data.snap_token, {
                    // Optional
                    onSuccess: function (result) {
                        location.reload();
                    },
                    // Optional
                    onPending: function (result) {
                        location.reload();
                    },
                    // Optional
                    onError: function (result) {
                        location.reload();
                    }
                });
            }
        });
        return false;
    }
    </script>
                         </div>
                    </div>
                    <div class="col-md-4">
                        <div class="booking-detail">
                            @include ($service->checkout_booking_detail_file ?? '')
                            <div class="val">{{format_money($booking->total)}}</div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('footer')
{{-- <script
        src="https://code.jquery.com/jquery-3.3.1.min.js"
        integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
        crossorigin="anonymous"></script>
    <script src="{{ !config('services.midtrans.isProduction') ? 'https://app.sandbox.midtrans.com/snap/snap.js' : 'https://app.midtrans.com/snap/snap.js' }}" data-client-key="SB-Mid-client-yNNbRxaPM-DKEzbq"></script>
    <script>
    function submitForm() {
        $.post("{{ route('pembayaran.store') }}",
        {
            _method: 'POST',
            _token: '{{ csrf_token() }}',
            amount: $('input#amount').val(),
            note: $('textarea#note').val(),
            pembayaran_type: $('select#pembayaran_type').val(),
            bayar_name: $('input#bayar_name').val(),
            bayar_email: $('input#bayar_email').val(),
        },
        function (data, status) {
            if (data.status == 'error') {
                alert(data.message);
            } else {
                snap.pay(data.snap_token, {
                    // Optional
                    onSuccess: function (result) {
                        location.reload();
                    },
                    // Optional
                    onPending: function (result) {
                        location.reload();
                    },
                    // Optional
                    onError: function (result) {
                        location.reload();
                    }
                });
            }
        });
        return false;
    }
    </script> --}}
    <script src="{{ asset('module/booking/js/checkout.js') }}"></script>
    <script type="text/javascript">
        jQuery(function () {
            $.ajax({
                'url': bookingCore.url + '/booking/{{$booking->code}}/check-status',
                'cache': false,
                'type': 'GET',
                success: function (data) {
                    if (data.redirect !== undefined && data.redirect) {
                        window.location.href = data.redirect
                    }
                }
            });
        })
    </script>
@endsection